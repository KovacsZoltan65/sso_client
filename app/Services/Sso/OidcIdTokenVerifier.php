<?php

namespace App\Services\Sso;

use App\Exceptions\SsoAuthenticationException;

class OidcIdTokenVerifier
{
    public function __construct(
        private readonly OidcJwksService $jwksService,
        private readonly OidcDiscoveryService $discoveryService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $idToken): array
    {
        $segments = explode('.', trim($idToken));

        if (count($segments) !== 3) {
            throw new SsoAuthenticationException('Az SSO ID token ervenytelen formatumu.', 502);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = $this->decodeJwtSegment($encodedHeader, 'Az SSO ID token fejlece ervenytelen.');
        $claims = $this->decodeJwtSegment($encodedPayload, 'Az SSO ID token payloadja ervenytelen.');

        $kid = trim((string) ($header['kid'] ?? ''));
        $alg = trim((string) ($header['alg'] ?? ''));

        if ($kid === '' || $alg !== 'RS256') {
            throw new SsoAuthenticationException('Az SSO ID token fejlece ervenytelen.', 502);
        }

        $jwk = $this->jwksService->findKeyByKid($kid);
        $this->assertSignatureIsValid($encodedHeader.'.'.$encodedPayload, $encodedSignature, $jwk);
        $this->assertClaimsAreValid($claims);

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtSegment(string $segment, string $message): array
    {
        $decoded = $this->decodeBase64Url($segment);

        if ($decoded === null) {
            throw new SsoAuthenticationException($message, 502);
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload)) {
            throw new SsoAuthenticationException($message, 502);
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
            throw new SsoAuthenticationException('Az SSO ID token alairasa ervenytelen.', 401);
        }

        $publicKey = openssl_pkey_get_public($this->pemFromJwk($jwk));

        if ($publicKey === false) {
            throw new SsoAuthenticationException('Az SSO alairasi kulcs nem toltheto be.', 502);
        }

        try {
            $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verified !== 1) {
                throw new SsoAuthenticationException('Az SSO ID token alairasa ervenytelen.', 401);
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

        if (trim((string) ($claims['iss'] ?? '')) !== rtrim($expectedIssuer, '/')) {
            throw new SsoAuthenticationException('Az SSO ID token issuer claimje ervenytelen.', 401);
        }

        if (trim((string) ($claims['aud'] ?? '')) !== $expectedAudience) {
            throw new SsoAuthenticationException('Az SSO ID token audience claimje ervenytelen.', 401);
        }

        $exp = (int) ($claims['exp'] ?? 0);
        $iat = (int) ($claims['iat'] ?? 0);

        if ($exp <= 0 || $exp < ($now - $clockSkew)) {
            throw new SsoAuthenticationException('Az SSO ID token lejart.', 401);
        }

        if ($iat <= 0 || $iat > ($now + $clockSkew)) {
            throw new SsoAuthenticationException('Az SSO ID token idobelyege ervenytelen.', 401);
        }
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
}
