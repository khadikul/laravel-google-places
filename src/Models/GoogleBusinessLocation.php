<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Khadikul\GooglePlaces\Data\Location;
use Khadikul\GooglePlaces\Models\Concerns\UsesPackageConnection;

/**
 * A Business Profile location the application has chosen to synchronise.
 *
 * "location_name" holds the fully qualified Reviews API name
 * ("accounts/{account}/locations/{location}") because that is what every
 * downstream call and every inbound notification uses.
 *
 * @property int $id
 * @property int $connection_id
 * @property string $location_name
 * @property ?string $account_name
 * @property ?string $place_id
 * @property ?string $title
 * @property bool $sync_enabled
 */
class GoogleBusinessLocation extends Model
{
    use UsesPackageConnection;

    protected $table = null;

    protected $guarded = [];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'metadata' => 'array',
        'average_rating' => 'float',
        'total_review_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    protected function packageTableKey(): string
    {
        return 'locations';
    }

    /**
     * @return BelongsTo<GoogleBusinessConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleBusinessConnection::class, 'connection_id');
    }

    /**
     * @return HasMany<GoogleReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(GoogleReview::class, 'location_name', 'location_name');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSyncEnabled(Builder $query): Builder
    {
        return $query->where('sync_enabled', true);
    }

    public static function storeFrom(Location $location, GoogleBusinessConnection $connection): self
    {
        $resourceName = $location->resourceName()
            ?? rtrim((string) $connection->google_account_name, '/').'/locations/'.$location->id();

        /** @var self $model */
        $model = static::query()->updateOrCreate(
            ['location_name' => $resourceName],
            [
                'connection_id' => $connection->getKey(),
                'account_name' => $location->accountName ?? $connection->google_account_name,
                'place_id' => $location->placeId,
                'title' => $location->title,
                'store_code' => $location->storeCode,
                'address' => $location->address,
                'website_uri' => $location->websiteUri,
                'phone_number' => $location->phoneNumber,
                'maps_uri' => $location->mapsUri,
                'metadata' => $location->raw,
            ],
        );

        return $model;
    }
}
