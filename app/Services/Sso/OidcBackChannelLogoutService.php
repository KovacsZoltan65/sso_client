<?php

namespace App\Services\Sso;

use App\Exceptions\SsoAuthenticationException;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OidcBackChannelLogoutService
{
    private const LOGOUT_EVENT_CLAIM = 'http://schemas.openid.net/event/backchannel-logout';

    public function __construct(
        private readonly OidcJwksService $jwksService,
        private readonly OidcDiscoveryService $discoveryService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function handle(Request $request): string
    {
        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.logout.backchannel_received',
            description: 'Client back-channel logout received.',
            causer: $request->user(),
            properties: [
                ...$this->auditLogService->requestContext($request),
                'status' => 'received',
            ],
        );

        $logoutToken = trim((string) $request->input('logout_token', ''));

        if ($logoutToken === '') {
            $this->logVerificationFailure($request, 'missing_logout_token');

            throw new SsoAuthenticationException('Ervenytelen back-channel logout token.', 400);
        }

        try {
            $claims = $this->verifyLogoutToken($logoutToken);
        } catch (SsoAuthenticationException $exception) {
            $this->logVerificationFailure($request, 'invalid_logout_token');

            throw $exception;
        }

        if (! $this->rememberLogoutJti((string) $claims['jti'])) {
            return 'Back-channel logout already processed.';
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('sso_user_id', (string) $claims['sub'])
            ->first();

        $deletedSessions = $this->clearSessionsForUser($request, $user);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.logout.backchannel_local_completed',
            description: 'Client back-channel local logout completed.',
            subject: $user,
            causer: $user,
            properties: [
                'target_local_user_id' => $user?->getKey(),
                'affected_count' => $deletedSessions,
                'status' => 'logged_out',
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return 'Back-channel logout completed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyLogoutToken(string $logoutToken): array
    {
        $segments = explode('.', trim($logoutToken));

        if (count($segments) !== 3) {
            throw new SsoAuthenticationException('Ervenytelen back-channel logout token.', 400);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = $this->decodeJwtSegment($encodedHeader, 'Ervenytelen back-channel logout token.');
        $claims = $this->decodeJwtSegment($encodedPayload, 'Ervenytelen back-channel logout token.');

        $kid = trim((string) ($header['kid'] ?? ''));
        $alg = trim((string) ($header['alg'] ?? ''));

        if ($kid === '' || $alg !== 'RS256') {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.logout.backchannel_verification_failed',
                description: 'Client back-channel logout verification failed.',
                properties: [
                    'kid' => $kid === '' ? null : $kid,
                    'status' => 'invalid_header',
                ],
            );

            throw new SsoAuthenticationException('Ervenytelen back-channel logout token.', 401);
        }

        try {
            $jwk = $this->jwksService->findKeyByKid($kid);
        } catch (SsoAuthenticationException $exception) {
            $this->logVerificationFailure(null, 'kid_not_found', $kid);

            throw $exception;
        }

        $this->assertSignatureIsValid($encodedHeader.'.'.$encodedPayload, $encodedSignature, $jwk);
        $this->assertClaimsAreValid($claims);

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.logout.backchannel_verified',
            description: 'Client back-channel logout verified.',
            properties: [
                'kid' => $kid,
                'status' => 'verified',
            ],
        );

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtSegment(string $segment, string $message): array
    {
        $decoded = $this->decodeBase64Url($segment);

        if ($decoded === null) {
            throw new SsoAuthenticationException($message, 400);
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload)) {
            throw new SsoAuthenticationException($message, 400);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function assertSignatureIsValid(string $signingInput, string $encodedSignature, array $jwk): void
    {
        $signature = $this->decodeBase64Url($encodedSignature);

        if ($signature === null) {
            throw new SsoAuthenticationException('Ervenytelen back-channel logout token alairas.', 401);
        }

        $publicKey = openssl_pkey_get_public($this->pemFromJwk($jwk));

        if ($publicKey === false) {
            throw new SsoAuthenticationException('Az SSO alairasi kulcs nem toltheto be.', 502);
        }

        try {
            $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verified !== 1) {
                throw new SsoAuthenticationException('Ervenytelen back-channel logout token alairas.', 401);
            }
        } finally {
            openssl_free_key($publicKey);
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertClaimsAreValid(array $claims): void
    {
        $configuredIssuer = trim((string) config('sso.oidc_expected_issuer', ''));
        $expectedIssuer = $configuredIssuer !== ''
            ? rtrim($configuredIssuer, '/')
            : ($this->discoveryService->resolveDiscoveryValue('issuer') ?? rtrim((string) config('sso.server_base_url'), '/'));
        $expectedAudience = trim((string) config('sso.client_id'));
        $clockSkew = max(0, (int) config('sso.oidc_clock_skew_seconds', 60));
        $now = now()->timestamp;

        if (trim((string) ($claims['iss'] ?? '')) !== $expectedIssuer) {
            throw new SsoAuthenticationException('A back-channel logout issuer claimje ervenytelen.', 401);
        }

        if (trim((string) ($claims['aud'] ?? '')) !== $expectedAudience) {
            throw new SsoAuthenticationException('A back-channel logout audience claimje ervenytelen.', 401);
        }

        $sub = trim((string) ($claims['sub'] ?? ''));
        $jti = trim((string) ($claims['jti'] ?? ''));
        $iat = (int) ($claims['iat'] ?? 0);
        $exp = (int) ($claims['exp'] ?? 0);
        $events = $claims['events'] ?? null;

        if ($sub === '' || $jti === '') {
            throw new SsoAuthenticationException('A back-channel logout token hianyos.', 401);
        }

        if ($iat <= 0 || $iat > ($now + $clockSkew)) {
            throw new SsoAuthenticationException('A back-channel logout token idobelyege ervenytelen.', 401);
        }

        if ($exp > 0 && $exp < ($now - $clockSkew)) {
            throw new SsoAuthenticationException('A back-channel logout token lejart.', 401);
        }

        if (! is_array($events) || ! array_key_exists(self::LOGOUT_EVENT_CLAIM, $events)) {
            throw new SsoAuthenticationException('A back-channel logout token esemeny claimje ervenytelen.', 401);
        }
    }

    private function rememberLogoutJti(string $jti): bool
    {
        $ttl = max(60, (int) config('sso.backchannel_logout_replay_cache_seconds', 300));

        return Cache::add('sso.oidc.backchannel_logout.'.sha1($jti), true, $ttl);
    }

    private function clearSessionsForUser(Request $request, ?User $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        $deletedSessions = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();

        if ($request->user() instanceof User && $request->user()?->is($user)) {
            Auth::guard('web')->logout();
            $request->session()->forget(config('sso.pending_auth_session_key'));
            $request->session()->forget(config('sso.identity_validation_session_key'));
            $request->session()->forget(config('sso.oidc_session_context_key'));
            $request->session()->forget(config('sso.logout_state_session_key'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $deletedSessions;
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function pemFromJwk(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || trim((string) ($jwk['n'] ?? '')) === '' || trim((string) ($jwk['e'] ?? '')) === '') {
            throw new SsoAuthenticationException('Az SSO JWKS kulcs ervenytelen.', 502);
        }

        $modulus = $this->decodeBase64Url((string) $jwk['n']);
        $exponent = $this->decodeBase64Url((string) $jwk['e']);

        if ($modulus === null || $exponent === null) {
            throw new SsoAuthenticationException('Az SSO JWKS kulcs ervenytelen.', 502);
        }

        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($modulus).
            $this->asn1Integer($exponent)
        );

        $subjectPublicKeyInfo = $this->asn1Sequence(
            $this->asn1Sequence(
                $this->asn1ObjectIdentifier("\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01").
                $this->asn1Null()
            ).
            $this->asn1BitString($rsaPublicKey)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Integer(string $value): string
    {
        if ($value === '') {
            $value = "\x00";
        }

        if (ord($value[0]) > 0x7f) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1BitString(string $value): string
    {
        return "\x03".$this->asn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function asn1ObjectIdentifier(string $value): string
    {
        return "\x06".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding !== 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return $decoded === false ? null : $decoded;
    }

    private function logVerificationFailure(?Request $request, string $reason, ?string $kid = null): void
    {
        $this->auditLogService->logFailure(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.logout.backchannel_verification_failed',
            description: 'Client back-channel logout verification failed.',
            properties: array_filter([
                'reason' => $reason,
                'kid' => $kid,
                'status' => 'verification_failed',
                ...($request instanceof Request ? $this->auditLogService->requestContext($request) : []),
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}
