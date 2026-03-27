import { expect, test } from '@playwright/test';
import { authE2eConfig } from './support/authE2eConfig.mjs';

const clientSessionCookieName = 'sso_client_e2e_session';

async function openClientLogin(page) {
    await page.goto('/');
    await expect(page.getByRole('link', { name: 'SSO bejelentkezes' })).toBeVisible();
    await page.getByRole('link', { name: 'SSO bejelentkezes' }).click();
    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/login$`));
    await expect(page.getByRole('button', { name: 'SSO bejelentkezes inditasa' })).toBeVisible();
}

async function beginAuthorizationRedirect(page) {
    const authorizeRequest = page.waitForRequest((request) => {
        if (! request.isNavigationRequest()) {
            return false;
        }

        const url = new URL(request.url());

        return url.origin === authE2eConfig.serverBaseUrl
            && url.pathname === '/oauth/authorize';
    });

    await page.getByRole('button', { name: 'SSO bejelentkezes inditasa' }).click();

    return authorizeRequest;
}

async function assertAuthorizeQuery(requestPromise: Promise<import('@playwright/test').Request>) {
    const request = await requestPromise;
    const url = new URL(request.url());

    expect(url.searchParams.get('client_id')).toBe('portal-client');
    expect(url.searchParams.get('redirect_uri')).toBe(`${authE2eConfig.clientBaseUrl}/auth/sso/callback`);
    expect(url.searchParams.get('state')).toBeTruthy();
    expect(url.searchParams.get('code_challenge')).toBeTruthy();
    expect(url.searchParams.get('code_challenge_method')).toBe('S256');
}

async function loginOnServer(page) {
    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.serverBaseUrl)}/login`));
    await page.locator('#email').fill(authE2eConfig.seededServerUser.email);
    await page.locator('input[type="password"]').fill(authE2eConfig.seededServerUser.password);
    const loginResponsePromise = page.waitForResponse((response) => {
        return response.url() === `${authE2eConfig.serverBaseUrl}/login`
            && response.request().method() === 'POST';
    });

    await page.getByRole('button', { name: 'Log in' }).click();

    const loginResponse = await loginResponsePromise;
    const location = loginResponse.headers().location;

    await page.waitForLoadState('networkidle');

    return location ? new URL(location, authE2eConfig.serverBaseUrl).toString() : null;
}

async function navigateWithRedirectFallback(page, url) {
    try {
        await page.goto(url);
    } catch (error) {
        if (! String(error).includes('ERR_ABORTED')) {
            throw error;
        }
    }
}

async function loginThroughSso(page) {
    await openClientLogin(page);
    const authorizeRequest = await beginAuthorizationRedirect(page);
    const authorizeUrl = authorizeRequest.url();
    await assertAuthorizeQuery(Promise.resolve(authorizeRequest));
    const continuationUrl = await loginOnServer(page);

    if (page.url().startsWith(authE2eConfig.serverBaseUrl)) {
        await navigateWithRedirectFallback(page, continuationUrl ?? authorizeUrl);
    }

    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/dashboard$`), { timeout: 20_000 });
    await expect(page.getByRole('complementary').getByText(authE2eConfig.seededServerUser.email)).toBeVisible();
}

function escapeForRegex(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

test('completes the browser auth flow and persists the client session after reload', async ({ page, context }) => {
    await loginThroughSso(page);

    await expect(page.getByRole('button', { name: 'Kijelentkezes' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'SSO bejelentkezes' })).toHaveCount(0);

    const authCookie = (await context.cookies(authE2eConfig.clientBaseUrl))
        .find((cookie) => cookie.name === clientSessionCookieName);

    expect(authCookie?.value).toBeTruthy();

    await page.reload();

    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/dashboard$`));
    await expect(page.getByRole('complementary').getByText(authE2eConfig.seededServerUser.email)).toBeVisible();
});

test('logout returns the client to guest mode and protected routes re-enter auth without redirect loops', async ({ page, context }) => {
    await loginThroughSso(page);

    const navigations = [];
    page.on('request', (request) => {
        if (request.isNavigationRequest()) {
            navigations.push(request.url());
        }
    });

    const loginSessionCookie = (await context.cookies(authE2eConfig.clientBaseUrl))
        .find((cookie) => cookie.name === clientSessionCookieName);

    await page.getByRole('button', { name: 'Kijelentkezes' }).click();
    await expect(page).toHaveURL(`${authE2eConfig.clientBaseUrl}/`);
    await expect(page.getByRole('link', { name: 'SSO bejelentkezes' })).toBeVisible();

    const logoutSessionCookie = (await context.cookies(authE2eConfig.clientBaseUrl))
        .find((cookie) => cookie.name === clientSessionCookieName);

    expect(logoutSessionCookie?.value === undefined || logoutSessionCookie.value !== loginSessionCookie?.value).toBeTruthy();

    await page.goto('/dashboard');
    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/login$`));
    await expect(page.getByRole('button', { name: 'SSO bejelentkezes inditasa' })).toBeVisible();

    const authorizeRequest = await beginAuthorizationRedirect(page);
    await assertAuthorizeQuery(Promise.resolve(authorizeRequest));
    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/dashboard$`), { timeout: 20_000 });
    expect(navigations.filter((url) => url.startsWith(authE2eConfig.clientBaseUrl)).length).toBeLessThanOrEqual(6);
});

test('invalid state falls back safely to the login screen without authenticating', async ({ page }) => {
    await openClientLogin(page);
    await beginAuthorizationRedirect(page);
    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.serverBaseUrl)}/login`));

    await page.goto(`${authE2eConfig.clientBaseUrl}/auth/sso/callback?code=fake-code&state=tampered-state`);

    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/login$`));
    await expect(page.getByText('Ervenytelen vagy lejart SSO allapot. Probald ujra a bejelentkezest.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'SSO bejelentkezes inditasa' })).toBeVisible();
});

test('missing code falls back safely to the login screen without creating an auth session', async ({ page }) => {
    await openClientLogin(page);
    await page.goto(`${authE2eConfig.clientBaseUrl}/auth/sso/callback?state=missing-code-state`);

    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/login$`));
    await expect(page.getByText('Hianyzik az authorization code a callbackbol.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'SSO bejelentkezes inditasa' })).toBeVisible();
    await expect(page.getByText(authE2eConfig.seededServerUser.email)).toHaveCount(0);

    await page.goto('/dashboard');
    await expect(page).toHaveURL(new RegExp(`${escapeForRegex(authE2eConfig.clientBaseUrl)}/login$`));
});
