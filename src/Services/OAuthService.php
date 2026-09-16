<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;
use JsonException;
use Khadikul\GooglePlaces\Clients\HandlesGoogleResponses;
use Khadikul\GooglePlaces\Contracts\ProvidesAccessTokens;
use Khadikul\GooglePlaces\Events\GoogleBusinessConnected;
use Khadikul\GooglePlaces\Events\GoogleConnectionRevoked;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException;
use Khadikul\GooglePlaces\Exceptions\TokenExpiredException;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Throwable;

/**
 * The complete OAuth 2.0 authorization-code flow, executed inside the host
 * application against the host application's own Google OAuth client.
 *
 * There is no intermediary. The browser goes straight to accounts.google.com,
 * comes back to a route in this application, and the resulting tokens are
 * encrypted into this application's database. Nothing is relayed anywhere else.
 */
class OAuthService implements ProvidesAccessTokens
{
    use HandlesGoogleResponses;

    public const STATE_SESSION_KEY = 'google_places.oauth_state';

    public function __construct(
        protected HttpFactory $http,
        protected Config $config,
        protected Dispatcher $events,
    ) {}

    /**
     * Build the Google consent URL and remember a CSRF state token.
     *
     * access_type=offline plus prompt=consent is what makes Google return a
     * refresh token; without both, a returning user gets an access token only
     * and unattended sync breaks an hour later.
     *
     * @param  array<string, mixed>  $parameters  Extra query parameters, e.g. login_hint.
     *
     * @throws InvalidConfigurationException
     */
    public function authorizationUrl(array $parameters = [], ?string $state = null): string
    {
        $this->assertConfigured();

        $state ??= $this->generateState();

        $this->rememberState($state);

        $query = array_merge([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], $parameters);

        return $this->config->get('google-places.oauth.auth_url')
            .'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Compare the state returned by Google with the one held in the session.
     *
     * The comparison is timing-safe and the stored value is consumed either
     * way, so a state token can never be replayed.
     */
    public function validateState(?string $state): bool
    {
        $expected = $this->pullState();

        if (! is_string($expected) || $expected === '' || ! is_string($state) || $state === '') {
            return false;
        }

        return hash_equals($expected, $state);
    }

    /**
     * Exchange the authorization code for tokens and persist the connection.
     *
     * @throws ApiException|InvalidConfigurationException
     */
    public function exchangeCode(string $code, ?GoogleBusinessConnection $connection = null): GoogleBusinessConnection
    {
        $this->assertConfigured();

        $payload = $this->guardTransport(function () use ($code): array {
            $response = $this->http
                ->asForm()
                ->acceptJson()
                ->timeout((int) $this->config->get('google-places.timeout', 10))
                ->post($this->config->get('google-places.oauth.token_url'), [
                    'code' => $code,
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'redirect_uri' => $this->redirectUri(),
                    'grant_type' => 'authorization_code',
                ]);

            return $this->decode($response, 'OAuth token exchange');
        }, 'OAuth token exchange');

        $connection ??= new GoogleBusinessConnection;

        $this->fillTokens($connection, $payload);

        $connection->fill([
            'is_active' => true,
            'revoked_at' => null,
            'google_email' => $this->resolveEmail($payload) ?? $connection->google_email,
        ])->save();

        $this->events->dispatch(new GoogleBusinessConnected($connection));

        return $connection;
    }

    /**
     * Return a usable access token, refreshing transparently when needed.
     *
     * @throws TokenExpiredException|InvalidConfigurationException|ApiException
     */
    public function accessToken(GoogleBusinessConnection $connection): string
    {
        if ($connection->isRevoked()) {
            throw TokenExpiredException::refreshFailed('the connection has been revoked.');
        }

        $leeway = (int) $this->config->get('google-places.oauth.leeway', 60);

        if (! $connection->isExpired($leeway) && is_string($connection->access_token) && $connection->access_token !== '') {
            return $connection->access_token;
        }

        $this->refresh($connection);

        $token = $connection->access_token;

        if (! is_string($token) || $token === '') {
            throw TokenExpiredException::refreshFailed('Google returned no access token.');
        }

        return $token;
    }

    /**
     * Swap the refresh token for a new access token.
     *
     * Google answers invalid_grant when the user revoked access, changed their
     * password, or the token simply aged out. That is unrecoverable without
     * user interaction, so the connection is marked revoked rather than retried.
     *
     * @throws TokenExpiredException|InvalidConfigurationException|ApiException
     */
    public function refresh(GoogleBusinessConnection $connection): GoogleBusinessConnection
    {
        $this->assertConfigured();

        if (! $connection->hasRefreshToken()) {
            throw TokenExpiredException::noRefreshToken();
        }

        try {
            $payload = $this->guardTransport(function () use ($connection): array {
                $response = $this->http
                    ->asForm()
                    ->acceptJson()
                    ->timeout((int) $this->config->get('google-places.timeout', 10))
                    ->post($this->config->get('google-places.oauth.token_url'), [
                        'client_id' => $this->clientId(),
                        'client_secret' => $this->clientSecret(),
                        'refresh_token' => (string) $connection->refresh_token,
                        'grant_type' => 'refresh_token',
                    ]);

                return $this->decode($response, 'OAuth token refresh');
            }, 'OAuth token refresh');
        } catch (ApiException $e) {
            if ($this->isUnrecoverable($e)) {
                $connection->markRevoked();

                $this->events->dispatch(new GoogleConnectionRevoked($connection, $e->reason()));

                throw TokenExpiredException::refreshFailed($e->reason() ?? 'invalid_grant');
            }

            throw $e;
        }

        $this->fillTokens($connection, $payload);

        $connection->save();

        return $connection;
    }

    /**
     * Tell Google to invalidate the credential, then clear it locally.
     *
     * The local record is cleared even if Google rejects the call, because a
     * token Google will not revoke is one this application should stop holding.
     */
    public function revoke(GoogleBusinessConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;

        if (is_string($token) && $token !== '') {
            try {
                $this->http
                    ->asForm()
                    ->timeout((int) $this->config->get('google-places.timeout', 10))
                    ->post($this->config->get('google-places.oauth.revoke_url'), ['token' => $token]);
            } catch (Throwable) {
                // Revocation is best-effort; local state is authoritative below.
            }
        }

        $connection->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'revoked_at' => now(),
            'is_active' => false,
        ])->save();

        $this->events->dispatch(new GoogleConnectionRevoked($connection, 'revoked_by_application'));
    }

    /**
     * The connection to use when the caller did not name one.
     *
     * @throws ConnectionNotFoundException
     */
    public function connection(int|string|GoogleBusinessConnection|null $connection = null): GoogleBusinessConnection
    {
        if ($connection instanceof GoogleBusinessConnection) {
            return $connection;
        }

        $query = GoogleBusinessConnection::query()->active();

        $model = $connection !== null
            ? $query->whereKey($connection)->first()
            : $query->latest('id')->first();

        if ($model === null) {
            throw ConnectionNotFoundException::none();
        }

        return $model;
    }

    public function hasConnection(): bool
    {
        return GoogleBusinessConnection::query()->active()->exists();
    }

    public function isConfigured(): bool
    {
        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            $value = $this->config->get('google-places.oauth.'.$key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Ask Google who authorised the connection, for display purposes only.
     */
    public function userEmail(GoogleBusinessConnection $connection): ?string
    {
        try {
            $payload = $this->guardTransport(function () use ($connection): array {
                $response = $this->http
                    ->withToken($this->accessToken($connection))
                    ->acceptJson()
                    ->timeout((int) $this->config->get('google-places.timeout', 10))
                    ->get($this->config->get('google-places.oauth.userinfo_url'));

                return $this->decode($response, 'OAuth userinfo lookup');
            }, 'OAuth userinfo lookup');
        } catch (Throwable) {
            return null;
        }

        $email = $payload['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function fillTokens(GoogleBusinessConnection $connection, array $payload): void
    {
        $attributes = [
            'token_type' => is_string($payload['token_type'] ?? null) ? $payload['token_type'] : 'Bearer',
        ];

        if (is_string($payload['access_token'] ?? null) && $payload['access_token'] !== '') {
            $attributes['access_token'] = $payload['access_token'];
        }

        // A refresh grant does not return a new refresh token; keep the old one.
        if (is_string($payload['refresh_token'] ?? null) && $payload['refresh_token'] !== '') {
            $attributes['refresh_token'] = $payload['refresh_token'];
        }

        if (isset($payload['expires_in']) && (is_int($payload['expires_in']) || is_numeric($payload['expires_in']))) {
            $attributes['expires_at'] = now()->addSeconds((int) $payload['expires_in']);
        }

        if (is_string($payload['scope'] ?? null) && $payload['scope'] !== '') {
            $attributes['scopes'] = explode(' ', $payload['scope']);
        }

        $connection->forceFill($attributes);
    }

    /**
     * The ID token, when present, carries the e-mail without an extra request.
     *
     * Only the payload segment is read, and only for display. It is not treated
     * as proof of anything: it arrived over TLS directly from Google's token
     * endpoint in response to a request signed with the client secret.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveEmail(array $payload): ?string
    {
        $idToken = $payload['id_token'] ?? null;

        if (! is_string($idToken)) {
            return null;
        }

        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            return null;
        }

        $decoded = base64_decode(strtr($segments[1], '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        try {
            $claims = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $email = is_array($claims) ? ($claims['email'] ?? null) : null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    protected function isUnrecoverable(ApiException $e): bool
    {
        return in_array($e->reason(), ['invalid_grant', 'invalid_client', 'unauthorized_client'], true)
            || $e->status() === 400 && str_contains(strtolower($e->getMessage()), 'invalid_grant');
    }

    protected function generateState(): string
    {
        return Str::random(40);
    }

    protected function rememberState(string $state): void
    {
        $session = $this->session();

        $session?->put(self::STATE_SESSION_KEY, $state);
    }

    protected function pullState(): ?string
    {
        $value = $this->session()?->pull(self::STATE_SESSION_KEY);

        return is_string($value) ? $value : null;
    }

    protected function session(): ?\Illuminate\Contracts\Session\Session
    {
        $container = \Illuminate\Container\Container::getInstance();

        if (! $container->bound('session.store')) {
            return null;
        }

        /** @var \Illuminate\Contracts\Session\Session $session */
        $session = $container->make('session.store');

        return $session;
    }

    /**
     * @return list<string>
     */
    protected function scopes(): array
    {
        $scopes = $this->config->get('google-places.oauth.scopes', []);

        return array_values(array_filter(
            (array) $scopes,
            static fn (mixed $scope): bool => is_string($scope) && $scope !== '',
        ));
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw InvalidConfigurationException::missingOAuthCredentials();
        }
    }

    protected function clientId(): string
    {
        return (string) $this->config->get('google-places.oauth.client_id');
    }

    protected function clientSecret(): string
    {
        return (string) $this->config->get('google-places.oauth.client_secret');
    }

    protected function redirectUri(): string
    {
        return (string) $this->config->get('google-places.oauth.redirect_uri');
    }
}
