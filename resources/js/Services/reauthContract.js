export function resolveReauthTarget(payload = {}) {
    return payload?.meta?.reauth_to
        || payload?.meta?.redirect_to
        || payload?.reauth_to
        || payload?.redirect_to
        || null;
}

export function redirectToReauthTarget(payload = {}, redirect = null) {
    const target = resolveReauthTarget(payload);

    if (!target) {
        return false;
    }

    const performRedirect = redirect ?? ((url) => window.location.assign(url));
    performRedirect(target);

    return true;
}
