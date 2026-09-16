<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * A place returned by the Places API (New).
 *
 * Every field other than the ID is nullable: the API returns exactly the fields
 * named in the request field mask and nothing else, so a narrow mask produces a
 * mostly-empty Place rather than an error.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Place implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, Review>  $reviews
     * @param  Collection<int, Photo>  $photos
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $address = null,
        public ?float $rating = null,
        public ?int $reviewCount = null,
        public ?string $googleMapsUri = null,
        public ?string $websiteUri = null,
        public ?string $phoneNumber = null,
        public ?string $internationalPhoneNumber = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $businessStatus = null,
        public ?string $primaryType = null,
        public Collection $reviews = new Collection,
        public Collection $photos = new Collection,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $id = self::resolveId($payload);
        $location = is_array($payload['location'] ?? null) ? $payload['location'] : [];

        return new self(
            id: $id,
            name: self::displayName($payload['displayName'] ?? null),
            address: self::str($payload, 'formattedAddress') ?? self::str($payload, 'shortFormattedAddress'),
            rating: self::float($payload, 'rating'),
            reviewCount: self::int($payload, 'userRatingCount'),
            googleMapsUri: self::str($payload, 'googleMapsUri'),
            websiteUri: self::str($payload, 'websiteUri'),
            phoneNumber: self::str($payload, 'nationalPhoneNumber'),
            internationalPhoneNumber: self::str($payload, 'internationalPhoneNumber'),
            latitude: self::float($location, 'latitude'),
            longitude: self::float($location, 'longitude'),
            businessStatus: self::str($payload, 'businessStatus'),
            primaryType: self::displayName($payload['primaryTypeDisplayName'] ?? null)
                ?? self::str($payload, 'primaryType'),
            reviews: self::mapReviews($payload['reviews'] ?? null, $id),
            photos: self::mapPhotos($payload['photos'] ?? null),
            raw: $payload,
        );
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function rating(): ?float
    {
        return $this->rating;
    }

    public function reviewCount(): ?int
    {
        return $this->reviewCount;
    }

    /**
     * @return Collection<int, Review>
     */
    public function reviews(): Collection
    {
        return $this->reviews;
    }

    /**
     * @return Collection<int, Photo>
     */
    public function photos(): Collection
    {
        return $this->photos;
    }

    public function mapsUrl(): ?string
    {
        return $this->googleMapsUri;
    }

    public function hasReviews(): bool
    {
        return $this->reviews->isNotEmpty();
    }

    public function isOperational(): bool
    {
        return $this->businessStatus === null || $this->businessStatus === 'OPERATIONAL';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'rating' => $this->rating,
            'review_count' => $this->reviewCount,
            'google_maps_uri' => $this->googleMapsUri,
            'website_uri' => $this->websiteUri,
            'phone_number' => $this->phoneNumber,
            'international_phone_number' => $this->internationalPhoneNumber,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'business_status' => $this->businessStatus,
            'primary_type' => $this->primaryType,
            'reviews' => $this->reviews->map(fn (Review $review) => $review->toArray())->all(),
            'photos' => $this->photos->map(fn (Photo $photo) => $photo->toArray())->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The API returns "id" when requested, but the resource "name"
     * ("places/ChIJ...") is always present, so fall back to parsing it.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function resolveId(array $payload): string
    {
        $id = self::str($payload, 'id');

        if ($id !== null) {
            return $id;
        }

        $name = self::str($payload, 'name');

        if ($name !== null && str_starts_with($name, 'places/')) {
            return substr($name, strlen('places/'));
        }

        return $name ?? '';
    }

    /**
     * @return Collection<int, Review>
     */
    private static function mapReviews(mixed $reviews, string $placeId): Collection
    {
        if (! is_array($reviews)) {
            return new Collection;
        }

        return (new Collection($reviews))
            ->filter(static fn (mixed $review): bool => is_array($review))
            ->map(static fn (array $review): Review => Review::fromPlacesApi($review, $placeId))
            ->values();
    }

    /**
     * @return Collection<int, Photo>
     */
    private static function mapPhotos(mixed $photos): Collection
    {
        if (! is_array($photos)) {
            return new Collection;
        }

        return (new Collection($photos))
            ->filter(static fn (mixed $photo): bool => is_array($photo))
            ->map(static fn (array $photo): Photo => Photo::fromApi($photo))
            ->values();
    }

    /**
     * displayName is a LocalizedText object: {"text": "...", "languageCode": "en"}.
     */
    private static function displayName(mixed $value): ?string
    {
        if (is_array($value) && is_string($value['text'] ?? null) && $value['text'] !== '') {
            return $value['text'];
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function str(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function int(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) || is_float($value) ? (int) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function float(array $payload, string $key): ?float
    {
        $value = $payload[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
