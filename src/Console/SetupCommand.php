<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Console;

use Illuminate\Console\Command;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Services\NotificationService;
use Khadikul\GooglePlaces\Services\OAuthService;

/**
 * Walks the developer through wiring up real-time notifications.
 *
 * The Google Cloud resources (topic, subscription, IAM binding) cannot be
 * created from here: doing so would need service-account credentials with
 * project-admin rights that this package has no business holding. So the exact
 * commands are printed for the developer to run against their own project, and
 * then the one call this package *can* make — pointing Google at the topic — is
 * offered.
 */
class SetupCommand extends Command
{
    protected $signature = 'google-places:setup
                            {--account= : Business Profile account, e.g. accounts/123}
                            {--topic= : Fully qualified Pub/Sub topic}
                            {--subscribe : Register the notification setting with Google}';

    protected $description = 'Print the Google Cloud Pub/Sub setup steps and optionally subscribe to notifications';

    public function handle(NotificationService $notifications, OAuthService $oauth): int
    {
        $topic = $this->option('topic') ?: config('google-places.notifications.pubsub_topic');
        $endpoint = rtrim((string) config('app.url'), '/').'/'
            .ltrim((string) config('google-places.notifications.route', 'google-places/webhook'), '/');

        $this->components->info('Google Cloud Pub/Sub setup');
        $this->newLine();

        $this->line('  These run against <options=bold>your</> Google Cloud project. The package author');
        $this->line('  has no access to any of it.');
        $this->newLine();

        $this->line('  <fg=cyan>1. Create the topic and let Google publish to it</>');

        foreach ($notifications->setupCommands(is_string($topic) ? $topic : null, $endpoint) as $command) {
            $this->line('     <fg=gray>'.$command.'</>');
        }

        $this->newLine();
        $this->line('  <fg=cyan>2. Point your .env at the topic and enable the webhook</>');
        $this->line('     <fg=gray>GOOGLE_PLACES_NOTIFICATIONS_ENABLED=true</>');
        $this->line('     <fg=gray>GOOGLE_PLACES_PUBSUB_TOPIC='.($topic ?: 'projects/YOUR_PROJECT/topics/YOUR_TOPIC').'</>');
        $this->line('     <fg=gray>GOOGLE_PLACES_WEBHOOK_AUDIENCE='.$endpoint.'</>');
        $this->line('     <fg=gray>GOOGLE_PLACES_WEBHOOK_SERVICE_ACCOUNT=YOUR_SA@YOUR_PROJECT.iam.gserviceaccount.com</>');
        $this->newLine();

        $this->line('  <fg=cyan>3. Notes</>');
        $this->line('     - The push endpoint must be publicly reachable over HTTPS.');
        $this->line('     - '.NotificationService::PUBLISHER_SERVICE_ACCOUNT.' needs roles/pubsub.publisher');
        $this->line('       on the topic, or Google silently drops every notification.');
        $this->line('     - Keep a queue worker running, or nothing will be synchronised.');
        $this->newLine();

        if (! $this->option('subscribe')) {
            $this->components->info('Re-run with --subscribe --account=accounts/123 once steps 1 and 2 are done.');

            return self::SUCCESS;
        }

        return $this->subscribe($notifications, $oauth);
    }

    protected function subscribe(NotificationService $notifications, OAuthService $oauth): int
    {
        if (! $oauth->hasConnection()) {
            $this->components->error('No connected Google Business Profile. Complete the OAuth flow first.');

            return self::FAILURE;
        }

        $account = $this->option('account');

        if (! is_string($account) || $account === '') {
            $this->components->error('Pass the account to subscribe, e.g. --account=accounts/123');

            return self::FAILURE;
        }

        try {
            $setting = $notifications->subscribe(
                $account,
                null,
                is_string($this->option('topic')) && $this->option('topic') !== ''
                    ? (string) $this->option('topic')
                    : null,
            );
        } catch (GooglePlacesException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Notification setting registered with Google.');

        $this->table(
            ['Setting', 'Value'],
            [
                ['name', $setting['name'] ?? '-'],
                ['pubsubTopic', $setting['pubsubTopic'] ?? '-'],
                ['notificationTypes', implode(', ', (array) ($setting['notificationTypes'] ?? []))],
            ],
        );

        return self::SUCCESS;
    }
}
