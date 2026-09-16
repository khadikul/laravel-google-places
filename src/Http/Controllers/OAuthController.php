<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Services\OAuthService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The application's own OAuth endpoints.
 *
 * Both routes run on the "web" middleware group by default, so the session
 * needed for CSRF state is available. Authorising a Google Business Profile is
 * an administrative action: protect these routes with your own auth middleware
 * via config("google-places.oauth.routes.middleware").
 */
class OAuthController extends Controller
{
    public function __construct(
        protected OAuthService $oauth,
        protected Config $config,
    ) {}

    /**
     * Send the browser to Google's consent screen.
     *
     * Inertia fetches routes over XHR, and an XHR cannot follow a redirect to
     * another origin: the browser would try to load accounts.google.com inside
     * the request and fail CORS, leaving the user on a dead button. Inertia's
     * protocol for leaving the application is a 409 carrying X-Inertia-Location,
     * which the client turns into a hard visit.
     *
     * Detected by header, so this costs no dependency and is inert in a Blade or
     * Livewire application.
     */
    public function redirect(Request $request): SymfonyResponse
    {
        $url = $this->oauth->authorizationUrl();

        if ($request->hasHeader('X-Inertia')) {
            return new Response('', SymfonyResponse::HTTP_CONFLICT, ['X-Inertia-Location' => $url]);
        }

        return new RedirectResponse($url);
    }

    /**
     * Handle the return trip from Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        // The user declined, or Google refused the request outright.
        if ($request->filled('error')) {
            return $this->failure($request, (string) $request->query('error'));
        }

        if (! $this->oauth->validateState($request->query('state'))) {
            return $this->failure($request, 'invalid_state');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failure($request, 'missing_code');
        }

        try {
            $connection = $this->oauth->exchangeCode($code);
        } catch (GooglePlacesException $e) {
            return $this->failure($request, 'token_exchange_failed');
        }

        return $this->success($request, $connection->getKey());
    }

    protected function success(Request $request, mixed $connectionId): RedirectResponse
    {
        return $this->redirectBack($request, 'success_redirect', [
            'google_places_status' => 'connected',
            'google_places_connection_id' => $connectionId,
        ]);
    }

    protected function failure(Request $request, string $reason): RedirectResponse
    {
        return $this->redirectBack($request, 'failure_redirect', [
            'google_places_status' => 'failed',
            'google_places_error' => $reason,
        ]);
    }

    /**
     * Flashing needs a session, which these routes have via the "web" group.
     * If they have been moved onto a stateless group the redirect still works;
     * only the flash data is skipped.
     *
     * @param  array<string, mixed>  $flash
     */
    protected function redirectBack(Request $request, string $configKey, array $flash): RedirectResponse
    {
        $target = (string) $this->config->get('google-places.oauth.routes.'.$configKey, '/');

        $response = new RedirectResponse($target);

        if ($request->hasSession()) {
            $response->setSession($request->session());

            foreach ($flash as $key => $value) {
                $response->with($key, $value);
            }
        }

        return $response;
    }
}
