<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces;

use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Data\Photo;
use Khadikul\GooglePlaces\Data\Place;
use Khadikul\GooglePlaces\Jobs\SyncLocationReviews;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;
use Khadikul\GooglePlaces\Models\GooglePlace;
use Khadikul\GooglePlaces\Models\GoogleReview;
use Khadikul\GooglePlaces\Services\BusinessProfileService;
use Khadikul\GooglePlaces\Services\GooglePlacesService;
use Khadikul\GooglePlaces\Services\NotificationService;
use Khadikul\GooglePlaces\Services\OAuthService;
use Khadikul\GooglePlaces\Services\ReviewSyncService;
use Khadikul\GooglePlaces\Support\ReviewSource;

/**
 * The object behind the GooglePlaces facade.
 *
 * Deliberately thin: it resolves arguments, picks the right service and gets
 * out of the way. All behaviour lives in the services, which can be injected
 * directly if you would rather not use the facade at all.
 */
class GooglePlacesManager
{
    public function __construct(
        protected GooglePlacesService $places,
        protected BusinessProfileService $business,
        protected ReviewSyncService $sync,
        protected OAuthService $oauth,
        protected NotificationService $notifications,
    ) {}

    // ---------------------------------------------------------------------
    // Mode A: public places, API key only
    // ---------------------------------------------------------------------

    /**
     * Search Google for a business by name.
     *
     * @param  list<string>|null  $fields
     * @return Collection<int, Place>
     */
    public function search(
        string $query,
        ?float $latitude = null,
        ?float $longitude = null,
        ?float $radius = null,
        ?int $limit = null,
        ?array $fields = null,
        ?string $languageCode = null,
        ?string $regionCode = null,
    ): Collection {
        return $this->places->search(
            $query,
            $latitude,
            $longitude,
            $radius,
            $limit,
            $fields,
            $languageCode,
            $regionCode,
        );
    }

    /**
     * Fetch the details of a single place.
     *
     * @param  list<string>|null  $fields
     */
    public function place(
        string $placeId,
        ?array $fields = null,
        ?string $languageCode = null,
        ?string $regionCode = null,
    ): Place {
        return $this->places->place($placeId, $fields, $languageCode, $regionCode);
    }

    /**
     * The public reviews Google returns for a place, straight from the API.
     *
     * @return Collection<int, Data\Review>
     */
    public function publicReviews(string $placeId): Collection
    {
        return $this->places->publicReviews($placeId);
    }

    /**
     * Resolve a photo reference to a displayable URL, server-side.
     */
    public function photoUrl(Photo|string $photo, ?int $maxWidthPx = null, ?int $maxHeightPx = null): ?string
    {
        return $this->places->photoUrl($photo, $maxWidthPx, $maxHeightPx);
    }

    /**
     * Save a place snapshot locally, optionally with its public reviews.
     */
    public function store(Place|string $place, bool $withReviews = true): GooglePlace
    {
        $place = $place instanceof Place ? $place : $this->place($place);

        $model = GooglePlace::storeFrom($place);

        if ($withReviews && $place->hasReviews()) {
            $this->sync->storePublicReviews($place->reviews());
        }

        return $model;
    }

    // ---------------------------------------------------------------------
    // Local reads
    // ---------------------------------------------------------------------

    /**
     * Reviews from your own database, the fast path for rendering a page.
     *
     * Accepts a place ID or a location resource name, so it works the same in
     * both modes.
     *
     * @return Collection<int, GoogleReview>
     */
    public function reviews(
        string $placeId,
        ?int $limit = null,
        ?ReviewSource $source = null,
        string $orderBy = 'publish_time',
        string $direction = 'desc',
    ): Collection {
        return $this->sync->localReviews($placeId, $limit, $source, $orderBy, $direction);
    }

    /**
     * Drop cached Places API responses for a place.
     */
    public function forget(string $placeId): void
    {
        $this->places->forget($placeId);
    }

    // ---------------------------------------------------------------------
    // Mode B: connected business
    // ---------------------------------------------------------------------

    public function oauth(): OAuthService
    {
        return $this->oauth;
    }

    public function notifications(): NotificationService
    {
        return $this->notifications;
    }

    public function business(): BusinessProfileService
    {
        return $this->business;
    }

    /**
     * Every Business Profile account the connected Google user manages.
     *
     * @return Collection<int, Data\BusinessAccount>
     */
    public function businessAccounts(int|string|GoogleBusinessConnection|null $connection = null): Collection
    {
        return $this->business->accounts($connection);
    }

    /**
     * Locations belonging to one Business Profile account.
     *
     * @return Collection<int, Data\Location>
     */
    public function locations(string $accountId, int|string|GoogleBusinessConnection|null $connection = null): Collection
    {
        return $this->business->locations($accountId, $connection);
    }

    /**
     * Select a location to synchronise.
     */
    public function connectLocation(
        string $locationId,
        ?string $accountId = null,
        int|string|GoogleBusinessConnection|null $connection = null,
    ): GoogleBusinessLocation {
        return $this->business->connectLocation($locationId, $accountId, $connection);
    }

    public function disconnectLocation(string $locationName): bool
    {
        return $this->business->disconnectLocation($locationName);
    }

    /**
     * @return Collection<int, GoogleBusinessLocation>
     */
    public function connectedLocations(): Collection
    {
        return $this->business->connectedLocations();
    }

    /**
     * Synchronise a connected location's reviews.
     *
     * Queued by default, because a busy location paginates through many pages.
     * Pass queue: false to run it inline and receive the stored reviews.
     *
     * @return Collection<int, GoogleReview>|null Null when queued.
     */
    public function syncReviews(string $locationName, bool $queue = true, ?int $maxPages = null): ?Collection
    {
        if ($queue) {
            SyncLocationReviews::dispatch($locationName, $maxPages);

            return null;
        }

        return $this->sync->syncLocation($locationName, $maxPages);
    }

    /**
     * Synchronise one specific review, inline.
     */
    public function syncReview(string $locationName, string $reviewName): GoogleReview
    {
        return $this->sync->syncReview($locationName, $reviewName);
    }

    // ---------------------------------------------------------------------
    // Escape hatches
    // ---------------------------------------------------------------------

    public function places(): GooglePlacesService
    {
        return $this->places;
    }

    public function syncService(): ReviewSyncService
    {
        return $this->sync;
    }
}
