<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Http;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;
use Khadikul\GooglePlaces\Services\ReviewSyncService;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CacheTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The base test case disables caching; these tests are about it.
        $app->make(Repository::class)->set([
            'google-places.cache.enabled' => true,
            'google-places.cache.ttl' => 3600,
        ]);
    }

    #[Test]
    public function a_second_lookup_is_served_from_the_cache(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');
        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');
        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');

        Http::assertSentCount(1);
    }

    #[Test]
    public function a_different_field_mask_is_cached_separately(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        // Otherwise a narrow first call would poison the cache for a wide one.
        GooglePlaces::place('ChIJ123', fields: ['id', 'displayName']);
        GooglePlaces::place('ChIJ123', fields: ['id', 'displayName', 'rating']);

        Http::assertSentCount(2);
    }

    #[Test]
    public function searches_are_cached_per_query(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('search-results'))]);

        GooglePlaces::search('Torlyx Security');
        GooglePlaces::search('Torlyx Security');
        GooglePlaces::search('Somewhere Else');

        Http::assertSentCount(2);
    }

    #[Test]
    public function a_search_with_a_location_bias_is_cached_separately(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('search-results'))]);

        GooglePlaces::search('Torlyx Security');
        GooglePlaces::search('Torlyx Security', latitude: 23.8103, longitude: 90.4125);

        Http::assertSentCount(2);
    }

    #[Test]
    public function forget_causes_the_next_lookup_to_hit_google_again(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');
        Http::assertSentCount(1);

        GooglePlaces::forget('ChIJN1t_tDeuEmsRUsoyG83frY4');

        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');
        Http::assertSentCount(2);
    }

    #[Test]
    public function forget_also_drops_cached_photo_urls_for_that_place(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*/photos/*' => Http::response(['photoUri' => 'https://lh3/img']),
        ]);

        GooglePlaces::photoUrl('places/ChIJ123/photos/AeeoHcK');
        GooglePlaces::photoUrl('places/ChIJ123/photos/AeeoHcK');
        Http::assertSentCount(1);

        GooglePlaces::forget('ChIJ123');

        GooglePlaces::photoUrl('places/ChIJ123/photos/AeeoHcK');
        Http::assertSentCount(2);
    }

    #[Test]
    public function forgetting_one_place_leaves_another_cached(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['id' => 'x'])]);

        GooglePlaces::place('ChIJ-one');
        GooglePlaces::place('ChIJ-two');
        Http::assertSentCount(2);

        GooglePlaces::forget('ChIJ-one');

        GooglePlaces::place('ChIJ-two');
        Http::assertSentCount(2);

        GooglePlaces::place('ChIJ-one');
        Http::assertSentCount(3);
    }

    #[Test]
    public function synchronising_a_review_invalidates_the_cached_place(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fixture('place-details')),
            'mybusiness.googleapis.com/*' => Http::response([
                'name' => 'accounts/111/locations/222/reviews/new-one',
                'reviewId' => 'new-one',
                'starRating' => 'FIVE',
                'comment' => 'Brand new review.',
                'createTime' => '2026-09-16T00:00:00Z',
            ]),
        ]);

        $connection = GoogleBusinessConnection::create([
            'google_account_name' => 'accounts/111',
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
        ]);

        GoogleBusinessLocation::create([
            'connection_id' => $connection->getKey(),
            'location_name' => 'accounts/111/locations/222',
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'sync_enabled' => true,
        ]);

        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');
        $placeCalls = 1;

        $this->app->make(ReviewSyncService::class)->syncReview(
            'accounts/111/locations/222',
            'accounts/111/locations/222/reviews/new-one',
        );

        // The cached place is now stale, so this must go back to Google.
        GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'places.googleapis.com'));
        $this->assertSame(
            2,
            Http::recorded(fn ($request): bool => str_contains($request->url(), 'places.googleapis.com'))->count(),
        );
    }

    #[Test]
    public function caching_can_be_switched_off_entirely(): void
    {
        config(['google-places.cache.enabled' => false]);

        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::place('ChIJ123');
        GooglePlaces::place('ChIJ123');

        Http::assertSentCount(2);
    }

    #[Test]
    public function a_zero_ttl_disables_caching_without_disabling_invalidation(): void
    {
        config(['google-places.cache.ttl' => 0]);

        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::place('ChIJ123');
        GooglePlaces::place('ChIJ123');

        Http::assertSentCount(2);

        // Must not blow up even though nothing was stored.
        GooglePlaces::forget('ChIJ123');
    }
}
