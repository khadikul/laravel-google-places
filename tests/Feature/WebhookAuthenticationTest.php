<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Khadikul\GooglePlaces\Jobs\SyncGoogleReview;
use Khadikul\GooglePlaces\Tests\TestCase;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\Test;

/**
 * The webhook is a public endpoint, so these tests are the ones that matter
 * most: they prove an unauthenticated or forged push cannot make the
 * application do anything.
 */
final class WebhookAuthenticationTest extends TestCase
{
    private const AUDIENCE = 'https://customer-site.test/google-places/webhook';

    private const SERVICE_ACCOUNT = 'pusher@customer-project.iam.gserviceaccount.com';

    private static ?OpenSSLAsymmetricKey $privateKey = null;

    /** @var array<string, mixed>|null */
    private static ?array $jwk = null;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(Repository::class)->set([
            'google-places.notifications.enabled' => true,
            'google-places.notifications.auth.oidc.enabled' => true,
            'google-places.notifications.auth.oidc.audience' => self::AUDIENCE,
            'google-places.notifications.auth.oidc.service_account' => self::SERVICE_ACCOUNT,
            'google-places.notifications.auth.token.enabled' => false,
            'cache.default' => 'array',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeGoogleCerts();
    }

    #[Test]
    public function a_correctly_signed_push_is_accepted(): void
    {
        Queue::fake();

        $this->pushWith($this->token())
            ->assertOk()
            ->assertJson(['status' => 'queued']);

        Queue::assertPushed(SyncGoogleReview::class);
    }

    #[Test]
    public function a_push_with_no_authorization_header_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith(null)->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_token_signed_by_the_wrong_key_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith($this->token(key: $this->key('test-attacker-key')))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_unsigned_none_algorithm_token_is_rejected(): void
    {
        Queue::fake();

        $header = $this->b64((string) json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => 'test-key']));
        $claims = $this->b64((string) json_encode($this->claims()));

        $this->pushWith($header.'.'.$claims.'.')->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_hmac_signed_token_is_rejected_even_with_a_known_kid(): void
    {
        Queue::fake();

        // Algorithm confusion: sign with HS256 using the public key as the
        // secret. Refusing any alg but RS256 makes this impossible.
        $header = $this->b64((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'test-key']));
        $claims = $this->b64((string) json_encode($this->claims()));
        $signature = $this->b64(hash_hmac('sha256', $header.'.'.$claims, 'whatever', true));

        $this->pushWith($header.'.'.$claims.'.'.$signature)->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_expired_token_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith($this->token(['exp' => time() - 3600]))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_token_for_a_different_audience_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith($this->token(['aud' => 'https://someone-elses-site.test/webhook']))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_token_from_a_different_issuer_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith($this->token(['iss' => 'https://evil.example.com']))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_token_from_an_unexpected_service_account_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith($this->token(['email' => 'someone-else@evil.iam.gserviceaccount.com']))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_token_with_an_unverified_service_account_email_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith($this->token(['email_verified' => false]))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_malformed_bearer_token_is_rejected(): void
    {
        Queue::fake();

        $this->pushWith('this-is-not-a-jwt')->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_rejection_body_reveals_nothing_about_why(): void
    {
        $response = $this->pushWith($this->token(['exp' => time() - 3600]));

        $response->assertStatus(401);
        $this->assertSame('Unauthorized', $response->getContent());
    }

    #[Test]
    public function the_shared_token_scheme_accepts_the_configured_secret(): void
    {
        config([
            'google-places.notifications.auth.oidc.enabled' => false,
            'google-places.notifications.auth.token.enabled' => true,
            'google-places.notifications.auth.token.value' => 'shared-secret-value',
        ]);

        Queue::fake();

        $this->postJson('/google-places/webhook?token=shared-secret-value', $this->envelope())
            ->assertOk()
            ->assertJson(['status' => 'queued']);

        Queue::assertPushed(SyncGoogleReview::class);
    }

    #[Test]
    public function the_shared_token_scheme_rejects_a_wrong_or_missing_secret(): void
    {
        config([
            'google-places.notifications.auth.oidc.enabled' => false,
            'google-places.notifications.auth.token.enabled' => true,
            'google-places.notifications.auth.token.value' => 'shared-secret-value',
        ]);

        Queue::fake();

        $this->postJson('/google-places/webhook?token=wrong', $this->envelope())->assertStatus(401);
        $this->postJson('/google-places/webhook', $this->envelope())->assertStatus(401);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_shared_token_scheme_fails_closed_when_no_secret_is_configured(): void
    {
        config([
            'google-places.notifications.auth.oidc.enabled' => false,
            'google-places.notifications.auth.token.enabled' => true,
            'google-places.notifications.auth.token.value' => null,
        ]);

        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope())->assertStatus(401);

        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function pushWith(?string $token): \Illuminate\Testing\TestResponse
    {
        $headers = $token !== null ? ['Authorization' => 'Bearer '.$token] : [];

        return $this->postJson('/google-places/webhook', $this->envelope(), $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(): array
    {
        return [
            'message' => [
                'data' => base64_encode((string) json_encode([
                    'notificationType' => 'NEW_REVIEW',
                    'location' => 'accounts/111/locations/222',
                    'review' => 'accounts/111/locations/222/reviews/rev-1',
                ])),
                'messageId' => 'msg-'.bin2hex(random_bytes(6)),
                'publishTime' => '2026-09-01T12:00:00.000Z',
            ],
            'subscription' => 'projects/customer-project/subscriptions/gbp-push',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::AUDIENCE,
            'azp' => '1234567890',
            'sub' => '1234567890',
            'email' => self::SERVICE_ACCOUNT,
            'email_verified' => true,
            'iat' => time() - 10,
            'exp' => time() + 3600,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function token(array $overrides = [], ?OpenSSLAsymmetricKey $key = null): string
    {
        $header = $this->b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'test-key']));
        $claims = $this->b64((string) json_encode($this->claims($overrides)));

        $signature = '';
        openssl_sign($header.'.'.$claims, $signature, $key ?? $this->privateKey(), OPENSSL_ALGO_SHA256);

        return $header.'.'.$claims.'.'.$this->b64($signature);
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Load one of the throwaway RSA keys committed under tests/Fixtures.
     *
     * They are fixtures, not secrets: they exist only so these tests can sign a
     * token and have the verifier check the signature for real, rather than
     * generating a key at runtime (which needs an openssl.cnf that not every
     * machine has).
     */
    private function key(string $name): OpenSSLAsymmetricKey
    {
        $pem = file_get_contents(__DIR__.'/../Fixtures/'.$name.'.pem');

        $this->assertIsString($pem);

        $key = openssl_pkey_get_private($pem);

        $this->assertNotFalse($key, 'Could not load the test signing key.');

        return $key;
    }

    private function privateKey(): OpenSSLAsymmetricKey
    {
        if (self::$privateKey === null) {
            $key = $this->key('test-signing-key');

            self::$privateKey = $key;

            $details = openssl_pkey_get_details($key);
            $this->assertIsArray($details);

            self::$jwk = [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'test-key',
                'n' => $this->b64($details['rsa']['n']),
                'e' => $this->b64($details['rsa']['e']),
            ];
        }

        return self::$privateKey;
    }

    /**
     * Stand in for Google's JWK endpoint with a key we control, so the
     * signature check is genuinely exercised rather than stubbed out.
     */
    private function fakeGoogleCerts(): void
    {
        $this->privateKey();

        Http::fake([
            'www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [self::$jwk]]),
        ]);
    }
}
