<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Queue;
use Khadikul\GooglePlaces\Jobs\SyncGoogleReview;
use Khadikul\GooglePlaces\Models\GoogleNotificationReceipt;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class WebhookTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->make(Repository::class)->set([
            'google-places.notifications.enabled' => true,
            // Authentication is exercised separately in WebhookAuthenticationTest.
            'google-places.notifications.auth.oidc.enabled' => false,
            'google-places.notifications.auth.token.enabled' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $notification
     * @return array<string, mixed>
     */
    private function envelope(array $notification, string $messageId = 'msg-1'): array
    {
        return [
            'message' => [
                'data' => base64_encode((string) json_encode($notification)),
                'messageId' => $messageId,
                'publishTime' => '2026-09-01T12:00:00.000Z',
            ],
            'subscription' => 'projects/customer-project/subscriptions/gbp-push',
        ];
    }

    #[Test]
    public function a_new_review_notification_queues_a_sync_job(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'account' => 'accounts/111',
            'location' => 'accounts/111/locations/222',
            'notificationType' => 'NEW_REVIEW',
            'review' => 'accounts/111/locations/222/reviews/rev-1',
        ]))->assertOk()->assertJson(['status' => 'queued']);

        Queue::assertPushed(
            SyncGoogleReview::class,
            fn (SyncGoogleReview $job): bool => $job->locationName === 'accounts/111/locations/222'
                && $job->reviewName === 'accounts/111/locations/222/reviews/rev-1',
        );
    }

    #[Test]
    public function an_updated_review_notification_queues_a_sync_job(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'location' => 'accounts/111/locations/222',
            'notificationType' => 'UPDATED_REVIEW',
            'review' => 'accounts/111/locations/222/reviews/rev-1',
        ]))->assertOk()->assertJson(['status' => 'queued']);

        Queue::assertPushed(SyncGoogleReview::class);
    }

    #[Test]
    public function it_accepts_the_snake_case_field_spellings_google_documents_in_proto(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'notification_type' => 'NEW_REVIEW',
            'location_name' => 'accounts/111/locations/222',
            'review_name' => 'accounts/111/locations/222/reviews/rev-9',
        ]))->assertOk()->assertJson(['status' => 'queued']);

        Queue::assertPushed(
            SyncGoogleReview::class,
            fn (SyncGoogleReview $job): bool => $job->reviewName === 'accounts/111/locations/222/reviews/rev-9',
        );
    }

    #[Test]
    public function it_rebuilds_a_qualified_location_from_the_review_name(): void
    {
        Queue::fake();

        // Only the short location form is supplied.
        $this->postJson('/google-places/webhook', $this->envelope([
            'notificationType' => 'NEW_REVIEW',
            'location' => 'locations/222',
            'review' => 'accounts/111/locations/222/reviews/rev-1',
        ]))->assertOk();

        Queue::assertPushed(
            SyncGoogleReview::class,
            fn (SyncGoogleReview $job): bool => $job->locationName === 'accounts/111/locations/222',
        );
    }

    #[Test]
    public function a_redelivered_message_is_acknowledged_without_queueing_twice(): void
    {
        Queue::fake();

        $envelope = $this->envelope([
            'notificationType' => 'NEW_REVIEW',
            'location' => 'accounts/111/locations/222',
            'review' => 'accounts/111/locations/222/reviews/rev-1',
        ], 'repeated-message-id');

        $this->postJson('/google-places/webhook', $envelope)->assertOk()->assertJson(['status' => 'queued']);
        $this->postJson('/google-places/webhook', $envelope)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->postJson('/google-places/webhook', $envelope)->assertOk()->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(SyncGoogleReview::class, 1);
        $this->assertDatabaseCount('google_notification_receipts', 1);
    }

    #[Test]
    public function it_records_what_it_received_for_auditing(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'notificationType' => 'NEW_REVIEW',
            'location' => 'accounts/111/locations/222',
            'review' => 'accounts/111/locations/222/reviews/rev-1',
        ], 'audit-me'))->assertOk();

        $receipt = GoogleNotificationReceipt::query()->where('message_id', 'audit-me')->firstOrFail();

        $this->assertSame('NEW_REVIEW', $receipt->notification_type);
        $this->assertSame('accounts/111/locations/222', $receipt->location_name);
        $this->assertSame('projects/customer-project/subscriptions/gbp-push', $receipt->subscription);
    }

    #[Test]
    public function it_acknowledges_and_ignores_notification_types_it_does_not_handle(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'notificationType' => 'GOOGLE_UPDATE',
            'location' => 'accounts/111/locations/222',
        ]))->assertOk()->assertJson(['status' => 'ignored', 'type' => 'GOOGLE_UPDATE']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_acknowledges_a_review_notification_that_names_no_review(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'notificationType' => 'NEW_REVIEW',
            'location' => 'accounts/111/locations/222',
        ]))->assertOk()->assertJson(['status' => 'incomplete']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_malformed_envelope_is_acknowledged_rather_than_redelivered_forever(): void
    {
        Queue::fake();

        // Pub/Sub retries anything that is not a 2xx, so a permanently broken
        // payload has to be acknowledged or it loops for days.
        $this->postJson('/google-places/webhook', ['not' => 'an envelope'])
            ->assertOk()
            ->assertJson(['status' => 'invalid_payload']);

        $this->postJson('/google-places/webhook', ['message' => ['data' => '!!!not base64!!!', 'messageId' => 'x']])
            ->assertOk()
            ->assertJson(['status' => 'invalid_payload']);

        $this->postJson('/google-places/webhook', [
            'message' => ['data' => base64_encode('this is not json'), 'messageId' => 'y'],
        ])->assertOk()->assertJson(['status' => 'invalid_payload']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('google_notification_receipts', 0);
    }

    #[Test]
    public function an_envelope_without_a_message_id_is_rejected(): void
    {
        Queue::fake();

        $this->postJson('/google-places/webhook', [
            'message' => ['data' => base64_encode('{"notificationType":"NEW_REVIEW"}')],
        ])->assertOk()->assertJson(['status' => 'invalid_payload']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_webhook_is_not_registered_when_notifications_are_disabled(): void
    {
        config(['google-places.notifications.enabled' => false]);

        // The route still exists in this booted app, but the controller short
        // circuits so no work is ever done.
        Queue::fake();

        $this->postJson('/google-places/webhook', $this->envelope([
            'notificationType' => 'NEW_REVIEW',
            'location' => 'accounts/111/locations/222',
            'review' => 'accounts/111/locations/222/reviews/rev-1',
        ]))->assertOk()->assertJson(['status' => 'notifications_disabled']);

        Queue::assertNothingPushed();
    }
}
