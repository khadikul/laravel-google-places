<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Facades;

use Illuminate\Support\Facades\Facade;
use Khadikul\GooglePlaces\GooglePlacesManager;

/**
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Data\Place> search(string $query, ?float $latitude = null, ?float $longitude = null, ?float $radius = null, ?int $limit = null, ?array $fields = null, ?string $languageCode = null, ?string $regionCode = null)
 * @method static \Khadikul\GooglePlaces\Data\Place place(string $placeId, ?array $fields = null, ?string $languageCode = null, ?string $regionCode = null)
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Data\Review> publicReviews(string $placeId)
 * @method static ?string photoUrl(\Khadikul\GooglePlaces\Data\Photo|string $photo, ?int $maxWidthPx = null, ?int $maxHeightPx = null)
 * @method static \Khadikul\GooglePlaces\Models\GooglePlace store(\Khadikul\GooglePlaces\Data\Place|string $place, bool $withReviews = true)
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Models\GoogleReview> reviews(string $placeId, ?int $limit = null, ?\Khadikul\GooglePlaces\Support\ReviewSource $source = null, string $orderBy = 'publish_time', string $direction = 'desc')
 * @method static void forget(string $placeId)
 * @method static \Khadikul\GooglePlaces\Services\OAuthService oauth()
 * @method static \Khadikul\GooglePlaces\Services\NotificationService notifications()
 * @method static \Khadikul\GooglePlaces\Services\BusinessProfileService business()
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Data\BusinessAccount> businessAccounts(int|string|\Khadikul\GooglePlaces\Models\GoogleBusinessConnection|null $connection = null)
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Data\Location> locations(string $accountId, int|string|\Khadikul\GooglePlaces\Models\GoogleBusinessConnection|null $connection = null)
 * @method static \Khadikul\GooglePlaces\Models\GoogleBusinessLocation connectLocation(string $locationId, ?string $accountId = null, int|string|\Khadikul\GooglePlaces\Models\GoogleBusinessConnection|null $connection = null)
 * @method static bool disconnectLocation(string $locationName)
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Models\GoogleBusinessLocation> connectedLocations()
 * @method static \Illuminate\Support\Collection<int, \Khadikul\GooglePlaces\Models\GoogleReview>|null syncReviews(string $locationName, bool $queue = true, ?int $maxPages = null)
 * @method static \Khadikul\GooglePlaces\Models\GoogleReview syncReview(string $locationName, string $reviewName)
 * @method static \Khadikul\GooglePlaces\Services\GooglePlacesService places()
 * @method static \Khadikul\GooglePlaces\Services\ReviewSyncService syncService()
 *
 * @see GooglePlacesManager
 */
class GooglePlaces extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GooglePlacesManager::class;
    }
}
