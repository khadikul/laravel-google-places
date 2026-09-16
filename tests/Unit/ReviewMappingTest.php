<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Unit;

use Khadikul\GooglePlaces\Data\Review;
use Khadikul\GooglePlaces\Support\ReviewSource;
use Khadikul\GooglePlaces\Support\StarRating;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ReviewMappingTest extends TestCase
{
    #[Test]
    public function it_maps_a_complete_places_api_review(): void
    {
        $review = Review::fromPlacesApi($this->fixture('place-details')['reviews'][0], 'ChIJ123');

        $this->assertSame(
            'places/ChIJN1t_tDeuEmsRUsoyG83frY4/reviews/ChdDSUhNMG9nS0VJQ0FnSURfN19hYkpREAE',
            $review->reviewName,
        );
        $this->assertSame('Jane Reviewer', $review->authorName);
        $this->assertStringStartsWith('https://www.google.com/maps/contrib/', (string) $review->authorUri);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Great campus, friendly staff.', $review->text);
        $this->assertSame('en', $review->languageCode);
        $this->assertSame('2 months ago', $review->relativePublishTime);
        $this->assertSame('2026-07-04', $review->publishedAt?->format('Y-m-d'));
        $this->assertSame(ReviewSource::Places, $review->source);
        $this->assertSame('ChIJ123', $review->placeId);
        $this->assertTrue($review->hasText());
    }

    #[Test]
    public function it_handles_a_places_review_with_no_author_and_no_text(): void
    {
        $review = Review::fromPlacesApi($this->fixture('place-details')['reviews'][1], 'ChIJ123');

        $this->assertNull($review->authorName);
        $this->assertNull($review->authorPhotoUri);
        $this->assertNull($review->text);
        $this->assertSame(3, $review->rating);
        $this->assertFalse($review->hasText());
    }

    #[Test]
    public function it_derives_the_place_id_from_the_review_name_when_not_supplied(): void
    {
        $review = Review::fromPlacesApi(['name' => 'places/ChIJDerived/reviews/abc', 'rating' => 4]);

        $this->assertSame('ChIJDerived', $review->placeId);
    }

    #[Test]
    public function it_builds_a_deterministic_name_for_a_review_google_did_not_name(): void
    {
        $payload = [
            'rating' => 4,
            'authorAttribution' => ['displayName' => 'Anon'],
            'publishTime' => '2026-01-01T00:00:00Z',
        ];

        $first = Review::fromPlacesApi($payload, 'ChIJ123');
        $second = Review::fromPlacesApi($payload, 'ChIJ123');

        $this->assertStringStartsWith('synthetic/', $first->reviewName);
        $this->assertSame($first->reviewName, $second->reviewName);
    }

    #[Test]
    public function it_maps_a_business_profile_review_including_the_owner_reply(): void
    {
        $review = Review::fromBusinessProfile(
            $this->fixture('business-reviews')['reviews'][0],
            'accounts/111/locations/222',
            'ChIJPlace',
        );

        $this->assertSame('accounts/111/locations/222/reviews/AbC-review-1', $review->reviewName);
        $this->assertSame('Rafiq Hasan', $review->authorName);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Excellent service, would recommend.', $review->text);
        $this->assertSame('2026-08-01', $review->publishedAt?->format('Y-m-d'));
        $this->assertSame(ReviewSource::BusinessProfile, $review->source);
        $this->assertSame('accounts/111/locations/222', $review->locationName);
        $this->assertSame('ChIJPlace', $review->placeId);
        $this->assertTrue($review->hasReply());
        $this->assertSame('Thank you!', $review->replyText);
    }

    #[Test]
    public function it_withholds_identity_for_an_anonymous_reviewer(): void
    {
        $review = Review::fromBusinessProfile(
            $this->fixture('business-reviews')['reviews'][1],
            'accounts/111/locations/222',
        );

        $this->assertNull($review->authorName);
        $this->assertNull($review->authorPhotoUri);
        $this->assertSame(2, $review->rating);
        $this->assertFalse($review->hasReply());
    }

    #[Test]
    public function it_rebuilds_the_resource_name_when_a_v4_list_omits_it(): void
    {
        $review = Review::fromBusinessProfile(
            ['reviewId' => 'only-an-id', 'starRating' => 'FOUR'],
            'accounts/111/locations/222',
        );

        $this->assertSame('accounts/111/locations/222/reviews/only-an-id', $review->reviewName);
    }

    #[Test]
    public function it_treats_an_unspecified_star_rating_as_unknown_not_zero(): void
    {
        $review = Review::fromBusinessProfile(
            ['reviewId' => 'x', 'starRating' => 'STAR_RATING_UNSPECIFIED'],
            'accounts/1/locations/2',
        );

        $this->assertNull($review->rating);
        $this->assertNull(StarRating::toInt('STAR_RATING_UNSPECIFIED'));
        $this->assertSame(3, StarRating::toInt('THREE'));
        $this->assertSame('FIVE', StarRating::fromInt(5));
    }

    #[Test]
    public function it_survives_a_malformed_timestamp(): void
    {
        $review = Review::fromPlacesApi([
            'name' => 'places/a/reviews/b',
            'publishTime' => 'not a date at all',
        ]);

        $this->assertNull($review->publishedAt);
    }

    #[Test]
    public function with_place_id_returns_the_same_instance_when_nothing_changes(): void
    {
        $review = Review::fromPlacesApi(['name' => 'places/a/reviews/b'], 'a');

        $this->assertSame($review, $review->withPlaceId('a'));
        $this->assertNotSame($review, $review->withPlaceId('different'));
        $this->assertSame('different', $review->withPlaceId('different')->placeId);
    }

    #[Test]
    public function it_serialises_to_a_database_friendly_array(): void
    {
        $array = Review::fromPlacesApi($this->fixture('place-details')['reviews'][0], 'ChIJ123')->toArray();

        $this->assertSame('Jane Reviewer', $array['author_name']);
        $this->assertSame(5, $array['rating']);
        $this->assertSame('places', $array['source']);
        $this->assertStringStartsWith('2026-07-04T', (string) $array['publish_time']);
    }
}
