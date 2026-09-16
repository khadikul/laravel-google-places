<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Khadikul\GooglePlaces\Events\GoogleBusinessConnected;
use Khadikul\GooglePlaces\Events\GoogleConnectionRevoked;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException;
use Khadikul\GooglePlaces\Exceptions\TokenExpiredException;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Services\OAuthService;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class OAuthTest extends TestCase
{
    /**
     * OAuth routes are only registered when credentials are configured, so the
     * configuration has to be in place before the provider boots.
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(\Illuminate\Contracts\Config\Repository::class)->set([
            'google-places.oauth.client_id' => 'test-client-id',
            'google-places.oauth.client_secret' => 'test-client-secret',
            'google-places.oauth.redirect_uri' => 'https://example.test/google-places/oauth/callback',
        ]);
    }

    private function oauth(): OAuthService
    {
        return $this->app->make(OAuthService::class);
    }

    #[Test]
    public function it_builds_an_authorization_url_that_asks_for_offline_access(): void
    {
        $url = $this->oauth()->authorizationUrl();

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('https://www.googleapis.com/auth/business.manage', $query['scope']);

        // Without both of these Google returns no refresh token and
        // unattended sync stops working an hour later.
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertNotEmpty($query['state']);
    }

    #[Test]
    public function it_refuses_to_build_a_url_without_oauth_credentials(): void
    {
        config(['google-places.oauth.client_id' => null]);

        $this->expectException(InvalidConfigurationException::class);

        $this->oauth()->authorizationUrl();
    }

    #[Test]
    public function it_accepts_the_state_it_issued(): void
    {
        $this->startSession();

        $url = $this->oauth()->authorizationUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertTrue($this->oauth()->validateState($query['state']));
    }

    #[Test]
    public function it_rejects_a_forged_state(): void
    {
        $this->startSession();

        $this->oauth()->authorizationUrl();

        $this->assertFalse($this->oauth()->validateState('attacker-supplied-state'));
    }

    #[Test]
    public function it_rejects_a_missing_state(): void
    {
        $this->startSession();

        $this->oauth()->authorizationUrl();

        $this->assertFalse($this->oauth()->validateState(null));
    }

    #[Test]
    public function a_state_cannot_be_replayed(): void
    {
        $this->startSession();

        $url = $this->oauth()->authorizationUrl();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertTrue($this->oauth()->validateState($query['state']));
        $this->assertFalse($this->oauth()->validateState($query['state']));
    }

    #[Test]
    public function it_exchanges_a_code_and_stores_encrypted_tokens(): void
    {
        Event::fake([GoogleBusinessConnected::class]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.plaintext-access-token',
                'refresh_token' => '1//plaintext-refresh-token',
                'expires_in' => 3599,
                'token_type' => 'Bearer',
                'scope' => 'https://www.googleapis.com/auth/business.manage',
            ]),
        ]);

        $connection = $this->oauth()->exchangeCode('auth-code');

        $this->assertSame('ya29.plaintext-access-token', $connection->access_token);
        $this->assertSame('1//plaintext-refresh-token', $connection->refresh_token);
        $this->assertTrue($connection->is_active);
        $this->assertSame(['https://www.googleapis.com/auth/business.manage'], $connection->scopes);
        $this->assertNotNull($connection->expires_at);

        Event::assertDispatched(GoogleBusinessConnected::class);
    }

    #[Test]
    public function tokens_are_not_readable_from_the_raw_database_row(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.plaintext-access-token',
                'refresh_token' => '1//plaintext-refresh-token',
                'expires_in' => 3599,
            ]),
        ]);

        $connection = $this->oauth()->exchangeCode('auth-code');

        $raw = $this->app['db']->connection('testing')
            ->table('google_business_connections')
            ->where('id', $connection->getKey())
            ->first();

        $this->assertNotNull($raw);
        $this->assertNotSame('ya29.plaintext-access-token', $raw->access_token);
        $this->assertNotSame('1//plaintext-refresh-token', $raw->refresh_token);
        $this->assertStringNotContainsString('plaintext', (string) $raw->access_token);
        $this->assertStringNotContainsString('plaintext', (string) $raw->refresh_token);
    }

    #[Test]
    public function tokens_are_stripped_from_the_serialised_model(): void
    {
        $connection = GoogleBusinessConnection::create([
            'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh',
            'expires_at' => now()->addHour(),
        ]);

        $json = json_encode($connection->toArray());

        $this->assertStringNotContainsString('secret-access', (string) $json);
        $this->assertStringNotContainsString('secret-refresh', (string) $json);
    }

    #[Test]
    public function it_returns_a_live_access_token_without_calling_google(): void
    {
        Http::fake();

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'still-valid',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
        ]);

        $this->assertSame('still-valid', $this->oauth()->accessToken($connection));

        Http::assertNothingSent();
    }

    #[Test]
    public function it_refreshes_an_expired_access_token_automatically(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'freshly-minted',
                'expires_in' => 3599,
            ]),
        ]);

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'stale',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertSame('freshly-minted', $this->oauth()->accessToken($connection));

        // The refresh grant returns no new refresh token; the old one survives.
        $this->assertSame('refresh-token', $connection->fresh()->refresh_token);
    }

    #[Test]
    public function it_refreshes_inside_the_expiry_leeway(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'early', 'expires_in' => 3599])]);

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'about-to-expire',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addSeconds(30),
        ]);

        $this->assertSame('early', $this->oauth()->accessToken($connection));
    }

    #[Test]
    public function a_revoked_refresh_token_marks_the_connection_for_reauthorisation(): void
    {
        Event::fake([GoogleConnectionRevoked::class]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], 400),
        ]);

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'stale',
            'refresh_token' => 'revoked-token',
            'expires_at' => now()->subMinute(),
        ]);

        try {
            $this->oauth()->accessToken($connection);
            $this->fail('Expected a TokenExpiredException.');
        } catch (TokenExpiredException $e) {
            $this->assertStringContainsString('re-authorised', $e->getMessage());
        }

        $fresh = $connection->fresh();

        $this->assertTrue($fresh->isRevoked());
        $this->assertNull($fresh->access_token);

        Event::assertDispatched(GoogleConnectionRevoked::class);
    }

    #[Test]
    public function it_refuses_to_refresh_without_a_refresh_token(): void
    {
        Http::fake();

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'stale',
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(TokenExpiredException::class);

        $this->oauth()->accessToken($connection);
    }

    #[Test]
    public function revoking_clears_both_tokens_locally(): void
    {
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response('', 200)]);

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'a',
            'refresh_token' => 'b',
            'expires_at' => now()->addHour(),
        ]);

        $this->oauth()->revoke($connection);

        $fresh = $connection->fresh();

        $this->assertNull($fresh->access_token);
        $this->assertNull($fresh->refresh_token);
        $this->assertTrue($fresh->isRevoked());
    }

    #[Test]
    public function revocation_still_clears_local_state_when_google_is_unreachable(): void
    {
        Http::fake(function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('unreachable');
        });

        $connection = GoogleBusinessConnection::create([
            'access_token' => 'a',
            'refresh_token' => 'b',
            'expires_at' => now()->addHour(),
        ]);

        $this->oauth()->revoke($connection);

        $this->assertNull($connection->fresh()->refresh_token);
    }

    #[Test]
    public function it_resolves_the_most_recent_active_connection_by_default(): void
    {
        GoogleBusinessConnection::create(['access_token' => 'old', 'expires_at' => now()->addHour()]);
        $newest = GoogleBusinessConnection::create(['access_token' => 'new', 'expires_at' => now()->addHour()]);

        $this->assertTrue($newest->is($this->oauth()->connection()));
    }

    #[Test]
    public function it_ignores_revoked_connections_when_resolving(): void
    {
        $revoked = GoogleBusinessConnection::create(['access_token' => 'x', 'is_active' => false, 'revoked_at' => now()]);

        $this->expectException(ConnectionNotFoundException::class);

        $this->oauth()->connection();
    }

    #[Test]
    public function the_callback_route_rejects_a_mismatched_state(): void
    {
        $this->get('/google-places/oauth/callback?code=abc&state=forged')
            ->assertRedirect('/')
            ->assertSessionHas('google_places_error', 'invalid_state');

        $this->assertDatabaseCount('google_business_connections', 0);
    }

    #[Test]
    public function the_callback_route_handles_a_declined_consent_screen(): void
    {
        $this->get('/google-places/oauth/callback?error=access_denied')
            ->assertRedirect('/')
            ->assertSessionHas('google_places_error', 'access_denied');
    }

    #[Test]
    public function the_callback_route_stores_the_connection_on_success(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.token',
                'refresh_token' => '1//refresh',
                'expires_in' => 3599,
            ]),
        ]);

        $this->startSession();
        $this->session([OAuthService::STATE_SESSION_KEY => 'known-state']);

        $this->get('/google-places/oauth/callback?code=auth-code&state=known-state')
            ->assertRedirect('/')
            ->assertSessionHas('google_places_status', 'connected');

        $this->assertDatabaseCount('google_business_connections', 1);
    }

    #[Test]
    public function the_redirect_route_sends_the_browser_to_google(): void
    {
        $response = $this->get('/google-places/oauth/redirect');

        $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
    }
}
