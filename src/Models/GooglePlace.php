<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Khadikul\GooglePlaces\Data\Place;
use Khadikul\GooglePlaces\Models\Concerns\UsesPackageConnection;

/**
 * A locally cached snapshot of a place.
 *
 * Optional: Mode A works entirely without persistence. This exists so that a
 * site can render business details without an API call on every page view.
 *
 * @property int $id
 * @property string $place_id
 * @property ?string $name
 * @property ?string $address
 * @property ?float $rating
 * @property ?int $review_count
 * @property ?string $google_maps_uri
 * @property ?array<string, mixed> $metadata
 */
class GooglePlace extends Model
{
    use UsesPackageConnection;

    protected $table = null;

    protected $guarded = [];

    protected $casts = [
        'rating' => 'float',
        'review_count' => 'integer',
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    protected function packageTableKey(): string
    {
        return 'places';
    }

    /**
     * @return HasMany<GoogleReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(GoogleReview::class, 'place_id', 'place_id');
    }

    /**
     * Upsert from a Place DTO. Fields absent from the field mask are left
     * untouched rather than being overwritten with null.
     */
    public static function storeFrom(Place $place): self
    {
        $attributes = array_filter([
            'name' => $place->name,
            'address' => $place->address,
            'rating' => $place->rating,
            'review_count' => $place->reviewCount,
            'google_maps_uri' => $place->googleMapsUri,
            'website_uri' => $place->websiteUri,
            'phone_number' => $place->phoneNumber,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'business_status' => $place->businessStatus,
        ], static fn (mixed $value): bool => $value !== null);

        $attributes['metadata'] = $place->raw;
        $attributes['synced_at'] = now();

        /** @var self $model */
        $model = static::query()->updateOrCreate(['place_id' => $place->id], $attributes);

        return $model;
    }
}
