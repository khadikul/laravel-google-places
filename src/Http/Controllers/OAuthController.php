<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Services\OAuthService;

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
     */
    public function redirect(Request $request): RedirectResponse
    {
        return new RedirectResponse($this->oauth->authorizationUrl());
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
