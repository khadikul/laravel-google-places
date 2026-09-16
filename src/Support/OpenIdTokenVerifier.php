<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Support;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use JsonException;
use Khadikul\GooglePlaces\Exceptions\WebhookException;
use OpenSSLAsymmetricKey;

/**
 * Verifies the OIDC token Pub/Sub attaches to an authenticated push request.
 *
 * Implemented against ext-openssl rather than pulling in a JWT library: the
 * only algorithm Google signs these with is RS256, and refusing every other
 * "alg" value up front closes the algorithm-confusion class of attacks by
 * construction.
 *
 * Google's signing keys are published as a JWK set and rotate, so they are
 * fetched on demand and cached, keyed by the "kid" in the token header.
 */
class OpenIdTokenVerifier
{
    private const CACHE_KEY = 'google-places:oidc-certs';

    public function __construct(
        protected HttpFactory $http,
        protected CacheFactory $cache,
    ) {}

    /**
     * @param  list<string>  $issuers
     * @return array<string, mixed> The verified claim set.
     *
     * @throws WebhookException
     */
    public function verify(
        string $token,
        string $certsUrl,
        array $issuers,
        ?string $audience = null,
        ?string $serviceAccount = null,
        int $leeway = 60,
    ): array {
        [$header, $claims, $signingInput, $signature] = $this->decompose($token);

        if (($header['alg'] ?? null) !== 'RS256') {
            throw WebhookException::unauthenticated('the token is not signed with RS256.');
        }

        $kid = $header['kid'] ?? null;

        if (! is_string($kid) || $kid === '') {
            throw WebhookException::unauthenticated('the token header has no key ID.');
        }

        $key = $this->publicKey($kid, $certsUrl);

        if (openssl_verify($signingInput, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw WebhookException::unauthenticated('the token signature is invalid.');
        }

        $this->assertClaims($claims, $issuers, $audience, $serviceAccount, $leeway);

        return $claims;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string}
     *
     * @throws WebhookException
     */
    protected function decompose(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw WebhookException::unauthenticated('the token is not a well formed JWT.');
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $segments;

        $header = $this->decodeJson($this->base64UrlDecode($encodedHeader), 'header');
        $claims = $this->decodeJson($this->base64UrlDecode($encodedClaims), 'claim set');
        $signature = $this->base64UrlDecode($encodedSignature);

        return [$header, $claims, $encodedHeader.'.'.$encodedClaims, $signature];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $issuers
     *
     * @throws WebhookException
     */
    protected function assertClaims(
        array $claims,
        array $issuers,
        ?string $audience,
        ?string $serviceAccount,
        int $leeway,
    ): void {
        $now = time();

        $exp = $claims['exp'] ?? null;

        if (! is_numeric($exp) || (int) $exp + $leeway < $now) {
            throw WebhookException::unauthenticated('the token has expired.');
        }

        $iat = $claims['iat'] ?? null;

        if (is_numeric($iat) && (int) $iat - $leeway > $now) {
            throw WebhookException::unauthenticated('the token was issued in the future.');
        }

        $iss = $claims['iss'] ?? null;

        if (! is_string($iss) || ! in_array($iss, $issuers, true)) {
            throw WebhookException::unauthenticated('the token issuer is not Google.');
        }

        if ($audience !== null && $audience !== '') {
            $aud = $claims['aud'] ?? null;

            if (! is_string($aud) || ! hash_equals($audience, $aud)) {
                throw WebhookException::unauthenticated('the token audience does not match the configured value.');
            }
        }

        if ($serviceAccount !== null && $serviceAccount !== '') {
            $email = $claims['email'] ?? null;

            if (! is_string($email) || ! hash_equals($serviceAccount, $email)) {
                throw WebhookException::unauthenticated('the token was signed by an unexpected service account.');
            }

            if (($claims['email_verified'] ?? false) !== true) {
                throw WebhookException::unauthenticated('the service account e-mail in the token is not verified.');
            }
        }
    }

    /**
     * @throws WebhookException
     */
    protected function publicKey(string $kid, string $certsUrl): OpenSSLAsymmetricKey
    {
        $keys = $this->certs($certsUrl);

        if (! isset($keys[$kid])) {
            // The key may have rotated since the set was cached.
            $keys = $this->certs($certsUrl, refresh: true);
        }

        if (! isset($keys[$kid])) {
            throw WebhookException::unauthenticated('no Google signing key matches the token key ID.');
        }

        $key = openssl_pkey_get_public($keys[$kid]);

        if ($key === false) {
            throw WebhookException::unauthenticated('the Google signing key could not be parsed.');
        }

        return $key;
    }

    /**
     * @return array<string, string> kid => PEM
     *
     * @throws WebhookException
     */
    protected function certs(string $certsUrl, bool $refresh = false): array
    {
        $store = $this->cache->store();

        if (! $refresh) {
            $cached = $store->get(self::CACHE_KEY);

            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        }

        $response = $this->http->acceptJson()->timeout(10)->get($certsUrl);

        if (! $response->successful()) {
            throw WebhookException::unauthenticated('Google signing keys could not be retrieved.');
        }

        $keys = [];

        foreach ((array) ($response->json('keys') ?? []) as $jwk) {
            if (! is_array($jwk) || ($jwk['kty'] ?? null) !== 'RSA') {
                continue;
            }

            $kid = $jwk['kid'] ?? null;
            $pem = $this->jwkToPem($jwk);

            if (is_string($kid) && $kid !== '' && $pem !== null) {
                $keys[$kid] = $pem;
            }
        }

        if ($keys === []) {
            throw WebhookException::unauthenticated('Google returned no usable signing keys.');
        }

        // Google rotates these roughly daily; an hour keeps the set fresh
        // without a network round trip on every push.
        $store->put(self::CACHE_KEY, $keys, 3600);

        return $keys;
    }

    /**
     * Convert an RSA JWK into a PEM public key.
     *
     * openssl_pkey_get_public() cannot read a JWK, so the modulus and exponent
     * are wrapped back into the DER SubjectPublicKeyInfo structure it expects.
     *
     * @param  array<string, mixed>  $jwk
     */
    protected function jwkToPem(array $jwk): ?string
    {
        $n = $jwk['n'] ?? null;
        $e = $jwk['e'] ?? null;

        if (! is_string($n) || ! is_string($e)) {
            return null;
        }

        $modulus = $this->base64UrlDecodeQuietly($n);
        $exponent = $this->base64UrlDecodeQuietly($e);

        if ($modulus === null || $exponent === null) {
            return null;
        }

        $rsaPublicKey = $this->derSequence(
            $this->derInteger($modulus).$this->derInteger($exponent)
        );

        // AlgorithmIdentifier for rsaEncryption (1.2.840.113549.1.1.1) with NULL params.
        $algorithm = $this->derSequence(
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"."\x05\x00"
        );

        $der = $this->derSequence($algorithm.$this->derBitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    protected function derSequence(string $content): string
    {
        return "\x30".$this->derLength(strlen($content)).$content;
    }

    protected function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        // A leading high bit would be read as a negative number.
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLength(strlen($bytes)).$bytes;
    }

    protected function derBitString(string $content): string
    {
        $content = "\x00".$content; // zero unused bits

        return "\x03".$this->derLength(strlen($content)).$content;
    }

    protected function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    /**
     * @throws WebhookException
     */
    protected function base64UrlDecode(string $value): string
    {
        $decoded = $this->base64UrlDecodeQuietly($value);

        if ($decoded === null) {
            throw WebhookException::unauthenticated('the token contains invalid base64url data.');
        }

        return $decoded;
    }

    protected function base64UrlDecodeQuietly(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;

        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws WebhookException
     */
    protected function decodeJson(string $json, string $what): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw WebhookException::unauthenticated(sprintf('the token %s is not valid JSON.', $what));
        }

        if (! is_array($decoded)) {
            throw WebhookException::unauthenticated(sprintf('the token %s is not an object.', $what));
        }

        return $decoded;
    }
}
