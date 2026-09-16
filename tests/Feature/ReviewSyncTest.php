<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Khadikul\GooglePlaces\Events\GoogleReviewSynced;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Jobs\SyncGoogleReview;
use Khadikul\GooglePlaces\Jobs\SyncLocationReviews;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;
use Khadikul\GooglePlaces\Models\GoogleReview;
use Khadikul\GooglePlaces\Services\ReviewSyncService;
use Khadikul\GooglePlaces\Support\ReviewSource;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ReviewSyncTest extends TestCase
{
    private GoogleBusinessConnection $connection;

    private GoogleBusinessLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withOAuthConfig();

        $this->connection = GoogleBusinessConnection::create([
            'google_account_name' => 'accounts/111',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
        ]);

        $this->location = GoogleBusinessLocation::create([
            'connection_id' => $this->connection->getKey(),
            'location_name' => 'accounts/111/locations/222',
            'account_name' => 'accounts/111',
            'place_id' => 'ChIJPlace',
            'title' => 'Torlyx Security',
            'sync_enabled' => true,
        ]);
    }

    private function sync(): ReviewSyncService
    {
        return $this->app->make(ReviewSyncService::class);
    }

    #[Test]
    public function it_stores_every_review_for_a_location(): void
    {
        Http::fake([
            'mybusiness.googleapis.com/v4/*/reviews*' => Http::response($this->fixture('business-reviews')),
        ]);

        $stored = $this->sync()->syncLocation('accounts/111/locations/222');

        $this->assertCount(2, $stored);
        $this->assertDatabaseCount('google_reviews', 2);

        $review = GoogleReview::query()->where('review_name', 'accounts/111/locations/222/reviews/AbC-review-1')->firstOrFail();

        $this->assertSame('Rafiq Hasan', $review->author_name);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Excellent service, would recommend.', $review->text);
        $this->assertSame('ChIJPlace', $review->place_id);
        $this->assertSame('accounts/111/locations/222', $review->location_name);
        $this->assertSame(ReviewSource::BusinessProfile->value, $review->source);
    }

    #[Test]
    public function it_authorises_with_the_stored_oauth_token(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization')[0] === 'Bearer valid-access-token');
    }

    #[Test]
    public function it_records_the_location_summary_after_a_sync(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        $fresh = $this->location->fresh();

        $this->assertSame(4.2, $fresh->average_rating);
        $this->assertSame(2, $fresh->total_review_count);
        $this->assertNotNull($fresh->last_synced_at);
    }

    #[Test]
    public function it_follows_pagination(): void
    {
        $first = $this->fixture('business-reviews');
        $first['nextPageToken'] = 'page-2';

        $second = [
            'reviews' => [[
                'name' => 'accounts/111/locations/222/reviews/page-two-review',
                'reviewId' => 'page-two-review',
                'reviewer' => ['displayName' => 'Second Page'],
                'starRating' => 'FOUR',
                'createTime' => '2026-01-01T00:00:00Z',
            ]],
        ];

        Http::fakeSequence('mybusiness.googleapis.com/*')
            ->push($first)
            ->push($second);

        $stored = $this->sync()->syncLocation('accounts/111/locations/222');

        $this->assertCount(3, $stored);
        $this->assertDatabaseHas('google_reviews', ['review_name' => 'accounts/111/locations/222/reviews/page-two-review']);
    }

    #[Test]
    public function syncing_the_same_reviews_repeatedly_never_duplicates_a_row(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');
        $this->sync()->syncLocation('accounts/111/locations/222');
        $this->sync()->syncLocation('accounts/111/locations/222');

        $this->assertDatabaseCount('google_reviews', 2);
    }

    #[Test]
    public function an_updated_review_overwrites_the_stored_row_in_place(): void
    {
        Http::fake([
            'mybusiness.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/locations/222/reviews/AbC-review-1',
                'reviewId' => 'AbC-review-1',
                'reviewer' => ['displayName' => 'Rafiq Hasan'],
                'starRating' => 'ONE',
                'comment' => 'Changed my mind entirely.',
                'createTime' => '2026-08-01T09:30:00.000Z',
                'updateTime' => '2026-09-10T10:00:00.000Z',
            ]),
        ]);

        GoogleReview::create([
            'review_name' => 'accounts/111/locations/222/reviews/AbC-review-1',
            'location_name' => 'accounts/111/locations/222',
            'source' => 'business_profile',
            'rating' => 5,
            'text' => 'Excellent service, would recommend.',
        ]);

        $this->sync()->syncReview('accounts/111/locations/222', 'accounts/111/locations/222/reviews/AbC-review-1');

        $this->assertDatabaseCount('google_reviews', 1);

        $review = GoogleReview::query()->firstOrFail();

        $this->assertSame(1, $review->rating);
        $this->assertSame('Changed my mind entirely.', $review->text);
    }

    #[Test]
    public function a_sparser_payload_does_not_blank_out_fields_a_richer_sync_stored(): void
    {
        GoogleReview::create([
            'review_name' => 'accounts/111/locations/222/reviews/keep-me',
            'location_name' => 'accounts/111/locations/222',
            'source' => 'business_profile',
            'author_name' => 'Known Author',
            'rating' => 4,
            'text' => 'Original text.',
        ]);

        Http::fake([
            'mybusiness.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/locations/222/reviews/keep-me',
                'starRating' => 'FOUR',
            ]),
        ]);

        $this->sync()->syncReview('accounts/111/locations/222', 'accounts/111/locations/222/reviews/keep-me');

        $review = GoogleReview::query()->firstOrFail();

        $this->assertSame('Known Author', $review->author_name);
        $this->assertSame('Original text.', $review->text);
    }

    #[Test]
    public function it_reports_a_first_time_review_as_newly_created(): void
    {
        Event::fake([GoogleReviewSynced::class]);

        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        Event::assertDispatched(GoogleReviewSynced::class, fn (GoogleReviewSynced $e): bool => $e->wasRecentlyCreated);
    }

    #[Test]
    public function it_reports_a_resynced_review_as_an_update(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        // Seed the rows first, with the real dispatcher in place.
        $this->sync()->syncLocation('accounts/111/locations/222');

        Event::fake([GoogleReviewSynced::class]);

        // The service is a singleton holding the dispatcher it was built with,
        // so it has to be rebuilt after faking.
        $this->app->forgetInstance(ReviewSyncService::class);

        $this->sync()->syncLocation('accounts/111/locations/222');

        Event::assertDispatched(GoogleReviewSynced::class, fn (GoogleReviewSynced $e): bool => ! $e->wasRecentlyCreated);
    }

    #[Test]
    public function it_refuses_to_sync_a_location_that_was_never_connected(): void
    {
        Http::fake();

        $this->expectException(ConnectionNotFoundException::class);

        $this->sync()->syncLocation('accounts/999/locations/888');
    }

    #[Test]
    public function it_refuses_to_sync_through_a_revoked_connection(): void
    {
        Http::fake();

        $this->connection->markRevoked();

        $this->expectException(ConnectionNotFoundException::class);

        $this->sync()->syncLocation('accounts/111/locations/222');
    }

    #[Test]
    public function it_caps_the_page_size_at_the_documented_maximum(): void
    {
        config(['google-places.business_profile.reviews_page_size' => 500]);

        Http::fake(['mybusiness.googleapis.com/*' => Http::response(['reviews' => []])]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'pageSize=50'));
    }

    #[Test]
    public function the_facade_queues_a_location_sync_by_default(): void
    {
        Queue::fake();

        $this->assertNull(GooglePlaces::syncReviews('accounts/111/locations/222'));

        Queue::assertPushed(
            SyncLocationReviews::class,
            fn (SyncLocationReviews $job): bool => $job->locationName === 'accounts/111/locations/222',
        );
    }

    #[Test]
    public function the_facade_can_sync_inline_when_asked(): void
    {
        Queue::fake();

        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $reviews = GooglePlaces::syncReviews('accounts/111/locations/222', queue: false);

        $this->assertNotNull($reviews);
        $this->assertCount(2, $reviews);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_job_writes_the_review_and_marks_the_notification_processed(): void
    {
        Http::fake([
            'mybusiness.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/locations/222/reviews/from-job',
                'reviewId' => 'from-job',
                'reviewer' => ['displayName' => 'Queued Reviewer'],
                'starRating' => 'FIVE',
                'comment' => 'Synced from the queue.',
                'createTime' => '2026-09-01T00:00:00Z',
            ]),
        ]);

        \Khadikul\GooglePlaces\Models\GoogleNotificationReceipt::claim('msg-abc');

        (new SyncGoogleReview(
            'accounts/111/locations/222',
            'accounts/111/locations/222/reviews/from-job',
            'msg-abc',
        ))->handle($this->sync());

        $this->assertDatabaseHas('google_reviews', [
            'review_name' => 'accounts/111/locations/222/reviews/from-job',
            'author_name' => 'Queued Reviewer',
        ]);

        $this->assertNotNull(
            \Khadikul\GooglePlaces\Models\GoogleNotificationReceipt::query()
                ->where('message_id', 'msg-abc')
                ->firstOrFail()
                ->processed_at,
        );
    }

    #[Test]
    public function local_reviews_read_back_from_the_database(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        $byPlace = GooglePlaces::reviews('ChIJPlace');
        $this->assertCount(2, $byPlace);

        // Most recent first by default.
        $this->assertSame('accounts/111/locations/222/reviews/AbC-review-1', $byPlace->first()->review_name);

        $this->assertCount(1, GooglePlaces::reviews('ChIJPlace', limit: 1));
        $this->assertCount(2, GooglePlaces::reviews('accounts/111/locations/222'));
        $this->assertCount(2, GooglePlaces::reviews('ChIJPlace', source: ReviewSource::BusinessProfile));
        $this->assertCount(0, GooglePlaces::reviews('ChIJPlace', source: ReviewSource::Places));
    }

    #[Test]
    public function it_can_order_local_reviews_by_rating(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        $this->assertSame(2, GooglePlaces::reviews('ChIJPlace', orderBy: 'rating', direction: 'asc')->first()->rating);
        $this->assertSame(5, GooglePlaces::reviews('ChIJPlace', orderBy: 'rating', direction: 'desc')->first()->rating);
    }

    #[Test]
    public function a_stored_review_round_trips_back_into_a_dto(): void
    {
        Http::fake(['mybusiness.googleapis.com/*' => Http::response($this->fixture('business-reviews'))]);

        $this->sync()->syncLocation('accounts/111/locations/222');

        $dto = GoogleReview::query()
            ->where('review_name', 'accounts/111/locations/222/reviews/AbC-review-1')
            ->firstOrFail()
            ->toData();

        $this->assertSame('Rafiq Hasan', $dto->authorName);
        $this->assertSame(5, $dto->rating);
        $this->assertSame(ReviewSource::BusinessProfile, $dto->source);
        $this->assertTrue($dto->hasReply());
    }

    #[Test]
    public function public_place_reviews_can_be_persisted_too(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::store('ChIJN1t_tDeuEmsRUsoyG83frY4');

        $this->assertDatabaseHas('google_places', [
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'name' => 'Google Sydney',
        ]);

        $this->assertDatabaseCount('google_reviews', 2);
        $this->assertDatabaseHas('google_reviews', [
            'source' => ReviewSource::Places->value,
            'author_name' => 'Jane Reviewer',
        ]);
    }
}
