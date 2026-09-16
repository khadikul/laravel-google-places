<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The package ships no views and no frontend assets, so it works the same under
 * Blade, Livewire, Inertia or a headless API.
 *
 * The one place the frontend stack leaks in is the OAuth redirect: it sends the
 * browser to accounts.google.com, and an Inertia <Link> or a wire:navigate link
 * fetches routes over XHR rather than navigating. An XHR cannot follow a
 * cross-origin 302, so those stacks need the redirect signalled in their own
 * dialect instead.
 */
final class StarterKitCompatibilityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(Repository::class)->set([
            'google-places.oauth.client_id' => 'test-client-id',
            'google-places.oauth.client_secret' => 'test-client-secret',
            'google-places.oauth.redirect_uri' => 'https://example.test/google-places/oauth/callback',

            // Routes are registered at boot, so this has to be set before it.
            'google-places.notifications.enabled' => true,
            'google-places.notifications.auth.oidc.enabled' => false,
            'google-places.notifications.auth.token.enabled' => false,
        ]);
    }

    #[Test]
    public function a_plain_browser_visit_gets_an_ordinary_redirect(): void
    {
        // Blade, and any full page navigation in any stack.
        $this->get('/google-places/oauth/redirect')
            ->assertStatus(302)
            ->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
    }

    #[Test]
    public function an_inertia_visit_gets_a_409_with_a_location_header(): void
    {
        /*
         | Inertia's contract for leaving the app: respond 409 with
         | X-Inertia-Location, and the client performs a hard visit. A plain 302
         | would make the XHR try to fetch accounts.google.com directly, which
         | fails CORS and leaves the user staring at a dead button.
         */
        $response = $this->get('/google-places/oauth/redirect', ['X-Inertia' => 'true']);

        $response->assertStatus(409);

        $this->assertStringContainsString(
            'accounts.google.com/o/oauth2/v2/auth',
            (string) $response->headers->get('X-Inertia-Location'),
        );
    }

    #[Test]
    public function any_non_inertia_request_still_gets_a_plain_redirect(): void
    {
        /*
         | Livewire is covered by this case rather than by a header of its own.
         | A Blade or Livewire page links to this route with a normal anchor, so
         | the browser navigates and the 302 is followed properly.
         |
         | The one thing to avoid there is wire:navigate on the connect link: it
         | turns the click into a fetch, which hits the same cross-origin problem
         | Inertia has. The README says to use a plain anchor.
         */
        $this->get('/google-places/oauth/redirect', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(302)
            ->assertRedirectContains('accounts.google.com');
    }

    #[Test]
    public function the_callback_redirects_normally_for_a_browser(): void
    {
        $this->get('/google-places/oauth/callback?error=access_denied')
            ->assertStatus(302)
            ->assertRedirect('/');
    }

    #[Test]
    public function the_callback_redirect_is_inertia_safe(): void
    {
        // The callback returns to a route inside the application, which is
        // same-origin, so a 302 is fine for Inertia too.
        $this->get('/google-places/oauth/callback?error=access_denied', ['X-Inertia' => 'true'])
            ->assertStatus(302);
    }

    #[Test]
    public function the_package_registers_no_views(): void
    {
        // Nothing to publish, nothing to override, no Blade dependency.
        $this->assertFalse(
            view()->exists('google-places::layout'),
            'The package must not ship views; the host application owns all markup.',
        );
    }

    #[Test]
    public function the_webhook_is_stateless_and_unaffected_by_the_frontend(): void
    {

        // Runs on the api middleware group: no session, no CSRF, no views.
        $this->postJson('/google-places/webhook', [
            'message' => ['data' => base64_encode('{}'), 'messageId' => 'stack-agnostic-1'],
        ])->assertOk();
    }
}
