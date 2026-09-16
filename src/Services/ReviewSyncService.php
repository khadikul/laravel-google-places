<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Clients\BusinessProfileApiClient;
use Khadikul\GooglePlaces\Data\Review;
use Khadikul\GooglePlaces\Events\GoogleReviewSynced;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;
use Khadikul\GooglePlaces\Models\GoogleReview;
use Khadikul\GooglePlaces\Support\ReviewSource;

/**
 * Writes reviews into the application's own database and keeps the cache honest.
 *
 * Every write goes through storeReview(), which upserts on the Google resource
 * name. That single rule is what makes redelivered Pub/Sub notifications, a
 * manual backfill and a scheduled resync all converge on the same one row.
 */
class ReviewSyncService
{
    public function __construct(
        protected BusinessProfileApiClient $client,
        protected OAuthService $oauth,
        protected GooglePlacesService $places,
        protected Dispatcher $events,
    ) {}

    /**
     * Pull every review for a connected location, following pagination.
     *
     * @param  string  $locationName  "accounts/{a}/locations/{l}", or a bare location ID.
     * @return Collection<int, GoogleReview>
     */
    public function syncLocation(string $locationName, ?int $maxPages = null): Collection
    {
        $location = $this->locationModel($locationName);
        $connection = $this->connectionFor($location);
        $resourceName = $location->location_name;

        $synced = new Collection;
        $pageToken = null;
        $pages = 0;
        $averageRating = null;
        $totalReviews = null;

        do {
            $payload = $this->client->listReviews($connection, $resourceName, $pageToken);

            $averageRating ??= isset($payload['averageRating']) && is_numeric($payload['averageRating'])
                ? (float) $payload['averageRating']
                : null;

            $totalReviews ??= isset($payload['totalReviewCount']) && is_numeric($payload['totalReviewCount'])
                ? (int) $payload['totalReviewCount']
                : null;

            foreach ((array) ($payload['reviews'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $review = Review::fromBusinessProfile($item, $resourceName, $location->place_id);

                $synced->push($this->storeReview($review));
            }

            $pageToken = is_string($payload['nextPageToken'] ?? null) ? $payload['nextPageToken'] : null;
            $pages++;
        } while ($pageToken !== null && ($maxPages === null || $pages < $maxPages));

        $location->forceFill(array_filter([
            'last_synced_at' => now(),
            'average_rating' => $averageRating,
            'total_review_count' => $totalReviews,
        ], static fn (mixed $value): bool => $value !== null))->save();

        $this->invalidate($location->place_id);

        return $synced;
    }

    /**
     * Fetch and store one review, which is what a NEW_REVIEW or UPDATED_REVIEW
     * notification ultimately triggers.
     *
     * @param  string  $reviewName  "accounts/{a}/locations/{l}/reviews/{r}"
     */
    public function syncReview(string $locationName, string $reviewName): GoogleReview
    {
        $location = $this->locationModel($locationName);
        $connection = $this->connectionFor($location);

        $payload = $this->client->getReview($connection, $reviewName);

        $review = Review::fromBusinessProfile($payload, $location->location_name, $location->place_id);

        $model = $this->storeReview($review);

        $this->invalidate($location->place_id);

        return $model;
    }

    /**
     * Persist the public reviews Google returns with a place lookup.
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, GoogleReview>
     */
    public function storePublicReviews(Collection $reviews): Collection
    {
        return $reviews->map(fn (Review $review): GoogleReview => $this->storeReview($review))->values();
    }

    /**
     * The idempotent write. Called from every sync path.
     */
    public function storeReview(Review $review): GoogleReview
    {
        $model = GoogleReview::storeFrom($review);

        $this->events->dispatch(new GoogleReviewSynced($model, $review, $model->wasRecentlyCreated));

        return $model;
    }

    /**
     * Read synchronised reviews back out of the local database.
     *
     * @return Collection<int, GoogleReview>
     */
    public function localReviews(
        string $placeId,
        ?int $limit = null,
        ?ReviewSource $source = null,
        string $orderBy = 'publish_time',
        string $direction = 'desc',
    ): Collection {
        $query = GoogleReview::query()
            ->where(function ($query) use ($placeId): void {
                $query->where('place_id', $placeId)
                    ->orWhere('location_name', $placeId);
            });

        if ($source !== null) {
            $query->fromSource($source);
        }

        $column = in_array($orderBy, ['publish_time', 'update_time', 'rating', 'created_at'], true)
            ? $orderBy
            : 'publish_time';

        $query->orderBy($column, strtolower($direction) === 'asc' ? 'asc' : 'desc');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Resolve the stored location record for a name in any of the shapes a
     * caller or a notification might supply.
     *
     * @throws ConnectionNotFoundException
     */
    public function locationModel(string $locationName): GoogleBusinessLocation
    {
        $short = preg_match('#(locations/[^/]+)#', $locationName, $matches) === 1
            ? $matches[1]
            : 'locations/'.$locationName;

        $model = GoogleBusinessLocation::query()
            ->where('location_name', $locationName)
            ->orWhere('location_name', 'like', '%/'.$short)
            ->first();

        if ($model === null) {
            throw ConnectionNotFoundException::forLocation($locationName);
        }

        return $model;
    }

    /**
     * @throws ConnectionNotFoundException
     */
    protected function connectionFor(GoogleBusinessLocation $location): GoogleBusinessConnection
    {
        $connection = $location->connection;

        if ($connection === null || $connection->isRevoked()) {
            throw ConnectionNotFoundException::forLocation($location->location_name);
        }

        return $connection;
    }

    /**
     * A new review makes any cached place payload stale.
     */
    protected function invalidate(?string $placeId): void
    {
        if ($placeId !== null && $placeId !== '') {
            $this->places->forget($placeId);
        }
    }
}
