<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Services\NotificationService;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NotificationSubscriptionTest extends TestCase
{
    private GoogleBusinessConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withOAuthConfig();

        config([
            'google-places.notifications.pubsub_topic' => 'projects/customer-project/topics/gbp-notifications',
        ]);

        $this->connection = GoogleBusinessConnection::create([
            'google_account_name' => 'accounts/111',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
        ]);
    }

    #[Test]
    public function it_registers_the_customers_own_topic_with_google(): void
    {
        Http::fake([
            'mybusinessnotifications.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/notificationSetting',
                'pubsubTopic' => 'projects/customer-project/topics/gbp-notifications',
                'notificationTypes' => ['NEW_REVIEW', 'UPDATED_REVIEW'],
            ]),
        ]);

        $setting = GooglePlaces::notifications()->subscribe('accounts/111');

        $this->assertSame('projects/customer-project/topics/gbp-notifications', $setting['pubsubTopic']);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('PATCH', $request->method());
            $this->assertStringContainsString('accounts/111/notificationSetting', $request->url());
            $this->assertStringContainsString('updateMask=pubsubTopic%2CnotificationTypes', $request->url());

            $body = $request->data();
            $this->assertSame('projects/customer-project/topics/gbp-notifications', $body['pubsubTopic']);
            $this->assertSame(['NEW_REVIEW', 'UPDATED_REVIEW'], $body['notificationTypes']);

            // The topic must belong to the application owner, never the package author.
            $this->assertStringStartsWith('projects/customer-project/', $body['pubsubTopic']);

            return true;
        });
    }

    #[Test]
    public function it_records_the_subscription_against_the_connection(): void
    {
        Http::fake(['mybusinessnotifications.googleapis.com/*' => Http::response(['name' => 'accounts/111/notificationSetting'])]);

        GooglePlaces::notifications()->subscribe('111');

        $fresh = $this->connection->fresh();

        $this->assertNotNull($fresh->notifications_subscribed_at);
        $this->assertSame('projects/customer-project/topics/gbp-notifications', $fresh->pubsub_topic);
    }

    #[Test]
    public function it_qualifies_a_bare_account_id(): void
    {
        Http::fake(['mybusinessnotifications.googleapis.com/*' => Http::response([])]);

        GooglePlaces::notifications()->subscribe('111');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/accounts/111/notificationSetting'));
    }

    #[Test]
    public function it_refuses_to_subscribe_without_a_topic(): void
    {
        config(['google-places.notifications.pubsub_topic' => null]);

        Http::fake();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('GOOGLE_PLACES_PUBSUB_TOPIC');

        GooglePlaces::notifications()->subscribe('accounts/111');
    }

    #[Test]
    public function it_rejects_a_notification_type_google_does_not_support(): void
    {
        Http::fake();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('MADE_UP_TYPE');

        GooglePlaces::notifications()->subscribe('accounts/111', ['MADE_UP_TYPE']);
    }

    #[Test]
    public function it_normalises_and_deduplicates_requested_types(): void
    {
        Http::fake(['mybusinessnotifications.googleapis.com/*' => Http::response([])]);

        GooglePlaces::notifications()->subscribe('accounts/111', ['new_review', 'NEW_REVIEW', ' updated_review ']);

        Http::assertSent(fn (Request $request): bool => $request->data()['notificationTypes'] === ['NEW_REVIEW', 'UPDATED_REVIEW']);
    }

    #[Test]
    public function unsubscribing_clears_the_topic_because_google_has_no_delete_method(): void
    {
        Http::fake(['mybusinessnotifications.googleapis.com/*' => Http::response([])]);

        GooglePlaces::notifications()->unsubscribe('accounts/111');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertNull($body['pubsubTopic']);
            $this->assertSame([], $body['notificationTypes']);

            return true;
        });

        $this->assertNull($this->connection->fresh()->notifications_subscribed_at);
    }

    #[Test]
    public function it_reads_back_the_current_setting(): void
    {
        Http::fake([
            'mybusinessnotifications.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/notificationSetting',
                'pubsubTopic' => 'projects/customer-project/topics/gbp-notifications',
                'notificationTypes' => ['NEW_REVIEW'],
            ]),
        ]);

        $setting = GooglePlaces::notifications()->setting('accounts/111');

        $this->assertSame(['NEW_REVIEW'], $setting['notificationTypes']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    }

    #[Test]
    public function the_setup_commands_name_googles_publisher_service_account(): void
    {
        $commands = GooglePlaces::notifications()->setupCommands();

        $joined = implode("\n", $commands);

        // Without this IAM binding Google silently publishes nothing.
        $this->assertStringContainsString(NotificationService::PUBLISHER_SERVICE_ACCOUNT, $joined);
        $this->assertStringContainsString('roles/pubsub.publisher', $joined);
        $this->assertStringContainsString('gcloud pubsub topics create gbp-notifications', $joined);
        $this->assertStringContainsString('--push-auth-token-audience', $joined);
    }

    #[Test]
    public function the_setup_commands_point_at_the_customers_own_endpoint(): void
    {
        $joined = implode("\n", GooglePlaces::notifications()->setupCommands(
            'projects/customer-project/topics/gbp-notifications',
            'https://customer-site.test/google-places/webhook',
        ));

        $this->assertStringContainsString('https://customer-site.test/google-places/webhook', $joined);
    }
}
