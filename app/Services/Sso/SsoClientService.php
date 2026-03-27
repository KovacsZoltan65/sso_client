<?php

namespace App\Services\Sso;

use App\Data\SsoStatusData;
use App\Exceptions\SsoAuthenticationException;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SsoClientService
{
    public function status(): SsoStatusData
    {
        $serverBaseUrl = $this->serverBaseUrl();
        $authorizeEndpoint = $this->configuredEndpoint('authorize_endpoint');
        $tokenEndpoint = $this->configuredEndpoint('token_endpoint');
        $userinfoEndpoint = $this->configuredEndpoint('userinfo_endpoint');
        $redirectUri = $this->redirectUri();
        $scopes = config('sso.scopes', []);
        $localAuthEnabled = (bool) config('sso.local_auth_enabled');
        $configured = filled($serverBaseUrl)
            && filled(config('sso.client_id'))
            && filled($authorizeEndpoint)
            && filled($tokenEndpoint)
            && filled($userinfoEndpoint)
            && filled($redirectUri);

        return new SsoStatusData(
            configured: $configured,
            localAuthEnabled: $localAuthEnabled,
            serverBaseUrl: $serverBaseUrl,
            authorizeEndpoint: $authorizeEndpoint,
            tokenEndpoint: $tokenEndpoint,
            userinfoEndpoint: $userinfoEndpoint,
            redirectUri: $redirectUri,
            scopes: $scopes,
            mode: $configured ? 'authorization-code-session' : 'missing-configuration',
            message: $configured
                ? 'Az SSO kliens redirect, callback es lokalis session flow-val mukodik.'
                : 'Az SSO kapcsolat meg mindig hianyos konfiguracioval fut.',
        );
    }

    public function authorizationRedirectUrl(): ?string
    {
        return $this->configuredEndpoint('authorize_endpoint');
    }

    public function buildAuthorizationUrl(Request $request): string
    {
        $this->ensureConfigured();

        $state = Str::random(64);
        $codeVerifier = Str::random(96);
        $codeChallenge = $this->codeChallengeFromVerifier($codeVerifier);

        $request->session()->put(config('sso.state_session_key'), $state);
        $request->session()->put(config('sso.pkce_verifier_session_key'), $codeVerifier);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('sso.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', config('sso.scopes', [])),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return $this->configuredEndpoint('authorize_endpoint').'?'.$query;
    }

    public function authenticateFromCallback(Request $request): User
    {
        $this->ensureConfigured();

        if ($request->filled('error')) {
            throw new SsoAuthenticationException('Az SSO szerver hibaval terjen vissza a bejelentkezesbol.', 401);
        }

        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();
        $expectedState = (string) $request->session()->pull(config('sso.state_session_key'));
        $codeVerifier = (string) $request->session()->pull(config('sso.pkce_verifier_session_key'));

        if ($code === '') {
            throw new SsoAuthenticationException('Hianyzik az authorization code a callbackbol.', 422);
        }

        if ($state === '') {
            throw new SsoAuthenticationException('Hianyzik a state ertek a callbackbol.', 422);
        }

        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            throw new SsoAuthenticationException('Ervenytelen vagy lejart SSO allapot. Probald ujra a bejelentkezest.', 401);
        }

        if ($codeVerifier === '') {
            throw new SsoAuthenticationException('Hianyzo PKCE verifier miatt nem folytathato a bejelentkezes. Inditsd ujra a login folyamatot.', 401);
        }

        $accessToken = $this->exchangeCodeForAccessToken($code, $codeVerifier);
        $userInfo = $this->fetchUserInfo($accessToken);
        $user = $this->resolveLocalUser($userInfo);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->forget(config('sso.state_session_key'));
        $request->session()->forget(config('sso.pkce_verifier_session_key'));
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function exchangeCodeForAccessToken(string $code, string $codeVerifier): string
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('sso.timeout', 10))
                ->post($this->configuredEndpoint('token_endpoint'), array_filter([
                    'grant_type' => 'authorization_code',
                    'client_id' => (string) config('sso.client_id'),
                    'client_secret' => config('sso.client_secret'),
                    'redirect_uri' => $this->redirectUri(),
                    'code' => $code,
                    'code_verifier' => $codeVerifier,
                ], fn (mixed $value) => filled($value)))
                ->throw();
        } catch (RequestException $exception) {
            throw new SsoAuthenticationException('Nem sikerult tokenre valtani a kapott kodot.', 502, $exception);
        }

        $accessToken = (string) $response->json('access_token');

        if ($accessToken === '') {
            throw new SsoAuthenticationException('Az SSO token valasz nem tartalmaz ervenyes access tokent.', 502);
        }

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserInfo(string $accessToken): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout((int) config('sso.timeout', 10))
                ->withToken($accessToken)
                ->get($this->configuredEndpoint('userinfo_endpoint'))
                ->throw();
        } catch (RequestException $exception) {
            throw new SsoAuthenticationException('Nem sikerult lekerdezni a felhasznaloi adatokat az SSO szervertol.', 502, $exception);
        }

        $userInfo = $response->json();

        if (! is_array($userInfo)) {
            throw new SsoAuthenticationException('Ervenytelen userinfo valasz erkezett az SSO szervertol.', 502);
        }

        return $userInfo;
    }

    /**
     * Email alapu mappinget hasznalunk, mert ez illeszkedik a jelenlegi lokalis
     * users tablahoza a legkisebb schema- es auth-komplexitassal.
     *
     * @param  array<string, mixed>  $userInfo
     */
    private function resolveLocalUser(array $userInfo): User
    {
        $email = trim((string) data_get($userInfo, 'email'));

        if ($email === '') {
            throw new SsoAuthenticationException('Az SSO userinfo valasz nem tartalmaz felhasznalhato email cimet.', 422);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $name = $this->resolveDisplayName($userInfo, $email);

            if ($user->name !== $name) {
                $user->forceFill(['name' => $name])->save();
            }

            return $user;
        }

        return User::query()->create([
            'name' => $this->resolveDisplayName($userInfo, $email),
            'email' => $email,
            'password' => Str::password(32),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $userInfo
     */
    private function resolveDisplayName(array $userInfo, string $fallbackEmail): string
    {
        return trim((string) (data_get($userInfo, 'name')
            ?: data_get($userInfo, 'preferred_username')
            ?: data_get($userInfo, 'nickname')
            ?: Str::before($fallbackEmail, '@')))
            ?: 'SSO User';
    }

    private function ensureConfigured(): void
    {
        if (! $this->status()->configured) {
            throw new SsoAuthenticationException('Az SSO kliens konfiguracioja hianyos.', 500);
        }
    }

    private function serverBaseUrl(): ?string
    {
        $baseUrl = trim((string) config('sso.server_base_url'));

        return $baseUrl === '' ? null : rtrim($baseUrl, '/');
    }

    private function redirectUri(): ?string
    {
        $configured = trim((string) config('sso.redirect_uri'));

        if ($configured !== '') {
            return $configured;
        }

        return route('auth.sso.callback', absolute: true);
    }

    private function configuredEndpoint(string $key): ?string
    {
        $endpoint = trim((string) config("sso.{$key}"));

        if ($endpoint === '') {
            return null;
        }

        if (Str::startsWith($endpoint, ['http://', 'https://'])) {
            return $endpoint;
        }

        $baseUrl = $this->serverBaseUrl();

        if ($baseUrl === null) {
            return null;
        }

        return $baseUrl.'/'.ltrim($endpoint, '/');
    }

    private function codeChallengeFromVerifier(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        $encoded = base64_encode($hash);

        if ($encoded === false) {
            throw new RuntimeException('PKCE challenge generation failed.');
        }

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }
}
