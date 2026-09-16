<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Khadikul\GooglePlaces\Exceptions\WebhookException;
use Khadikul\GooglePlaces\Support\OpenIdTokenVerifier;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Authenticates an inbound Pub/Sub push request before the controller sees it.
 *
 * The endpoint is public by necessity, so it is treated as hostile until proven
 * otherwise. Two schemes are supported and both may be enabled at once:
 *
 *   OIDC   Pub/Sub signs each push with a Google-issued token. Verified
 *          cryptographically against Google's published keys.
 *   Token  A shared secret in the query string. Weaker, but available where
 *          OIDC cannot be configured.
 *
 * The request body is never parsed here; nothing untrusted is acted upon until
 * the caller has been authenticated.
 */
class VerifyPubSubToken
{
    public function __construct(
        protected Config $config,
        protected OpenIdTokenVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $oidc = (array) $this->config->get('google-places.notifications.auth.oidc', []);
        $token = (array) $this->config->get('google-places.notifications.auth.token', []);

        $oidcEnabled = (bool) ($oidc['enabled'] ?? false);
        $tokenEnabled = (bool) ($token['enabled'] ?? false);

        try {
            if ($tokenEnabled) {
                $this->verifySharedToken($request, $token);
            }

            if ($oidcEnabled) {
                $this->verifyOidc($request, $oidc);
            }
        } catch (WebhookException $e) {
            $this->log($e->getMessage(), $request);

            // A deliberately terse body: never tell a prober which check failed.
            return new Response('Unauthorized', 401);
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws WebhookException
     */
    protected function verifySharedToken(Request $request, array $config): void
    {
        $expected = $config['value'] ?? null;

        if (! is_string($expected) || $expected === '') {
            throw WebhookException::unauthenticated('shared token authentication is enabled but no secret is configured.');
        }

        $provided = $request->query('token');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            throw WebhookException::unauthenticated('the shared token is missing or incorrect.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws WebhookException
     */
    protected function verifyOidc(Request $request, array $config): void
    {
        $header = $request->header('Authorization');

        if (! is_string($header) || ! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw WebhookException::unauthenticated('no bearer token was supplied.');
        }

        $issuers = array_values(array_filter((array) ($config['issuers'] ?? []), 'is_string'));

        $claims = $this->verifier->verify(
            token: $matches[1],
            certsUrl: (string) ($config['certs_url'] ?? 'https://www.googleapis.com/oauth2/v3/certs'),
            issuers: $issuers !== [] ? $issuers : ['https://accounts.google.com', 'accounts.google.com'],
            audience: is_string($config['audience'] ?? null) && $config['audience'] !== ''
                ? $config['audience']
                : null,
            serviceAccount: is_string($config['service_account'] ?? null) && $config['service_account'] !== ''
                ? $config['service_account']
                : null,
            leeway: (int) ($config['leeway'] ?? 60),
        );

        $request->attributes->set('google_places.pubsub_claims', $claims);
    }

    protected function log(string $message, Request $request): void
    {
        if (! $this->config->get('google-places.logging.enabled', true)) {
            return;
        }

        $channel = $this->config->get('google-places.logging.channel');

        // Only the reason and the caller IP: no headers, no body, no token.
        \Illuminate\Support\Facades\Log::channel(is_string($channel) && $channel !== '' ? $channel : null)
            ->warning('Rejected Google Pub/Sub push request.', [
                'reason' => $message,
                'ip' => $request->ip(),
            ]);
    }
}
