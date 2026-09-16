<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Console;

use Illuminate\Console\Command;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Services\GooglePlacesService;
use Khadikul\GooglePlaces\Services\OAuthService;

/**
 * Diagnoses a configuration without the developer having to write any code.
 *
 * Runs one real, cheap Places call when a query is supplied, so a key that
 * looks right but is restricted to the wrong API is caught here rather than in
 * production.
 */
class TestCommand extends Command
{
    protected $signature = 'google-places:test
                            {query? : Run a live search, e.g. "Torlyx Security"}
                            {--no-api : Check configuration only, make no API call}';

    protected $description = 'Verify your Google Places configuration and credentials';

    public function handle(GooglePlacesService $places, OAuthService $oauth): int
    {
        $failures = 0;

        $this->components->info('Configuration');

        $failures += $this->check(
            'Places API key',
            is_string(config('google-places.api_key')) && trim((string) config('google-places.api_key')) !== '',
            'Set GOOGLE_PLACES_API_KEY in .env (your own key, from your own Google Cloud project).',
        );

        $this->check(
            'Cache',
            (bool) config('google-places.cache.enabled', true),
            'Caching is off. Every call will hit Google and be billed.',
            required: false,
        );

        $this->newLine();
        $this->components->info('Connected business (optional)');

        $oauthConfigured = $oauth->isConfigured();

        $this->check(
            'OAuth client',
            $oauthConfigured,
            'Only needed for Mode B. Set GOOGLE_PLACES_CLIENT_ID, _SECRET and _REDIRECT_URI.',
            required: false,
        );

        if ($oauthConfigured) {
            $this->check(
                'Active connection',
                $oauth->hasConnection(),
                'No Google Business Profile connected yet. Send a user through /google-places/oauth/redirect.',
                required: false,
            );
        }

        $notificationsEnabled = (bool) config('google-places.notifications.enabled', false);

        $this->check(
            'Notifications',
            $notificationsEnabled,
            'Real-time sync is off. Set GOOGLE_PLACES_NOTIFICATIONS_ENABLED=true when your topic is ready.',
            required: false,
        );

        if ($notificationsEnabled) {
            $failures += $this->check(
                'Pub/Sub topic',
                is_string(config('google-places.notifications.pubsub_topic'))
                    && trim((string) config('google-places.notifications.pubsub_topic')) !== '',
                'Set GOOGLE_PLACES_PUBSUB_TOPIC to your own fully qualified topic.',
            );

            $failures += $this->check(
                'Webhook authentication',
                (bool) config('google-places.notifications.auth.oidc.enabled', false)
                    || (bool) config('google-places.notifications.auth.token.enabled', false),
                'Your webhook is unauthenticated and will accept anonymous POSTs. Enable OIDC verification.',
            );

            if (config('google-places.notifications.auth.oidc.enabled', false)) {
                $this->check(
                    'OIDC audience',
                    is_string(config('google-places.notifications.auth.oidc.audience'))
                        && config('google-places.notifications.auth.oidc.audience') !== '',
                    'Set GOOGLE_PLACES_WEBHOOK_AUDIENCE to the push endpoint URL for a stricter check.',
                    required: false,
                );
            }
        }

        $query = $this->argument('query');

        if (is_string($query) && $query !== '' && ! $this->option('no-api')) {
            $this->newLine();
            $this->components->info('Live API call');

            try {
                $results = $places->search($query, limit: 5);
            } catch (GooglePlacesException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            if ($results->isEmpty()) {
                $this->components->warn('The call succeeded but Google matched no places.');
            } else {
                $this->table(
                    ['Place ID', 'Name', 'Rating', 'Reviews'],
                    $results->map(fn ($place): array => [
                        $place->id,
                        $place->name ?? '-',
                        $place->rating ?? '-',
                        $place->reviewCount ?? '-',
                    ])->all(),
                );
            }
        }

        $this->newLine();

        if ($failures > 0) {
            $this->components->error(sprintf('%d required check(s) failed.', $failures));

            return self::FAILURE;
        }

        $this->components->info('Configuration looks good.');

        return self::SUCCESS;
    }

    protected function check(string $label, bool $passed, string $hint, bool $required = true): int
    {
        if ($passed) {
            $this->components->twoColumnDetail($label, '<fg=green>ok</>');

            return 0;
        }

        $this->components->twoColumnDetail($label, $required ? '<fg=red>missing</>' : '<fg=yellow>not set</>');
        $this->line('    <fg=gray>'.$hint.'</>');

        return $required ? 1 : 0;
    }
}
