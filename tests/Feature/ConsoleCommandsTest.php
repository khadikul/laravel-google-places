<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Khadikul\GooglePlaces\Jobs\SyncLocationReviews;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;
use Khadikul\GooglePlaces\Models\GoogleNotificationReceipt;
use Khadikul\GooglePlaces\Services\NotificationService;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConsoleCommandsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(Repository::class)->set([
            'google-places.notifications.pubsub_topic' => 'projects/customer-project/topics/gbp-notifications',
        ]);
    }

    #[Test]
    public function the_test_command_reports_a_healthy_configuration(): void
    {
        $this->artisan('google-places:test', ['--no-api' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function the_test_command_fails_when_the_api_key_is_missing(): void
    {
        config(['google-places.api_key' => null]);

        $this->artisan('google-places:test', ['--no-api' => true])
            ->expectsOutputToContain('GOOGLE_PLACES_API_KEY')
            ->assertFailed();
    }

    #[Test]
    public function the_test_command_fails_when_the_webhook_would_be_unauthenticated(): void
    {
        config([
            'google-places.notifications.enabled' => true,
            'google-places.notifications.auth.oidc.enabled' => false,
            'google-places.notifications.auth.token.enabled' => false,
        ]);

        $this->artisan('google-places:test', ['--no-api' => true])
            ->expectsOutputToContain('unauthenticated')
            ->assertFailed();
    }

    #[Test]
    public function the_test_command_can_make_a_live_search(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('search-results'))]);

        $this->artisan('google-places:test', ['query' => 'Google Sydney'])
            ->expectsOutputToContain('ChIJN1t_tDeuEmsRUsoyG83frY4')
            ->assertSuccessful();
    }

    #[Test]
    public function the_test_command_surfaces_an_api_failure_clearly(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                ['error' => ['code' => 403, 'message' => 'Denied', 'status' => 'PERMISSION_DENIED']],
                403,
            ),
        ]);

        $this->artisan('google-places:test', ['query' => 'anything'])->assertFailed();
    }

    #[Test]
    public function the_setup_command_prints_the_commands_the_developer_must_run_themselves(): void
    {
        $this->artisan('google-places:setup')
            ->expectsOutputToContain('gcloud pubsub topics create')
            ->expectsOutputToContain(NotificationService::PUBLISHER_SERVICE_ACCOUNT)
            ->assertSuccessful();
    }

    #[Test]
    public function the_setup_command_refuses_to_subscribe_without_a_connection(): void
    {
        $this->artisan('google-places:setup', ['--subscribe' => true, '--account' => 'accounts/111'])
            ->assertFailed();
    }

    #[Test]
    public function the_setup_command_subscribes_when_asked(): void
    {
        $this->withOAuthConfig();

        GoogleBusinessConnection::create([
            'google_account_name' => 'accounts/111',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'mybusinessnotifications.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/notificationSetting',
                'pubsubTopic' => 'projects/customer-project/topics/gbp-notifications',
                'notificationTypes' => ['NEW_REVIEW', 'UPDATED_REVIEW'],
            ]),
        ]);

        $this->artisan('google-places:setup', ['--subscribe' => true, '--account' => 'accounts/111'])
            ->expectsOutputToContain('registered with Google')
            ->assertSuccessful();
    }

    #[Test]
    public function the_sync_command_queues_every_connected_location(): void
    {
        Queue::fake();

        $connection = GoogleBusinessConnection::create(['access_token' => 'x', 'expires_at' => now()->addHour()]);

        GoogleBusinessLocation::create([
            'connection_id' => $connection->getKey(),
            'location_name' => 'accounts/111/locations/222',
            'sync_enabled' => true,
        ]);

        GoogleBusinessLocation::create([
            'connection_id' => $connection->getKey(),
            'location_name' => 'accounts/111/locations/333',
            'sync_enabled' => false,
        ]);

        $this->artisan('google-places:sync-reviews')->assertSuccessful();

        Queue::assertPushed(SyncLocationReviews::class, 1);
    }

    #[Test]
    public function the_sync_command_reports_when_nothing_is_connected(): void
    {
        $this->artisan('google-places:sync-reviews')
            ->expectsOutputToContain('No connected locations')
            ->assertSuccessful();
    }

    #[Test]
    public function the_prune_command_removes_only_expired_receipts(): void
    {
        GoogleNotificationReceipt::create(['message_id' => 'old', 'received_at' => now()->subDays(30)]);
        GoogleNotificationReceipt::create(['message_id' => 'recent', 'received_at' => now()->subHour()]);

        $this->artisan('google-places:prune-notifications')->assertSuccessful();

        $this->assertDatabaseMissing('google_notification_receipts', ['message_id' => 'old']);
        $this->assertDatabaseHas('google_notification_receipts', ['message_id' => 'recent']);
    }

    #[Test]
    public function the_install_command_publishes_the_config_and_explains_the_setup(): void
    {
        $this->artisan('google-places:install', ['--no-migrate' => true, '--force' => true])
            ->expectsOutputToContain('ships with no Google credentials')
            ->expectsOutputToContain('GOOGLE_PLACES_API_KEY')
            ->assertSuccessful();
    }
}
