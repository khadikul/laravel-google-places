<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Clients\PlacesApiClient;
use Khadikul\GooglePlaces\Data\Photo;
use Khadikul\GooglePlaces\Data\Place;
use Khadikul\GooglePlaces\Exceptions\NotFoundException;
use Khadikul\GooglePlaces\Support\FieldMask;

/**
 * Mode A: everything you can do with nothing but your own API key.
 *
 * Search, place details, photo URLs and their caching all live here. No OAuth,
 * no database and no queue are required to use any of it.
 */
class GooglePlacesService
{
    public function __construct(
        protected PlacesApiClient $client,
        protected CacheFactory $cache,
        protected Config $config,
    ) {}

    /**
     * Text search, optionally biased towards a point.
     *
     * A location bias nudges ranking towards the area without excluding
     * anything outside it, which is what you want for "find my business".
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
        $fields = FieldMask::normalise($fields ?? $this->defaultFields('search'));

        $options = array_filter([
            'pageSize' => $limit !== null ? min(max($limit, 1), 20) : null,
            'languageCode' => $languageCode,
            'regionCode' => $regionCode,
            'locationBias' => $this->locationBias($latitude, $longitude, $radius),
        ], static fn (mixed $value): bool => $value !== null);

        $payload = $this->remember(
            $this->key('search', $query, FieldMask::fingerprint($fields), md5(serialize($options))),
            fn (): array => $this->client->searchText($query, $fields, $options),
        );

        return $this->mapPlaces($payload['places'] ?? []);
    }

    /**
     * Fetch one place by its place ID.
     *
     * @param  list<string>|null  $fields  Overrides the configured detail mask.
     */
    public function place(
        string $placeId,
        ?array $fields = null,
        ?string $languageCode = null,
        ?string $regionCode = null,
    ): Place {
        $fields = FieldMask::normalise($fields ?? $this->defaultFields('details'));

        $options = array_filter([
            'languageCode' => $languageCode,
            'regionCode' => $regionCode,
        ], static fn (mixed $value): bool => $value !== null);

        $payload = $this->remember(
            $this->key('place', $placeId, FieldMask::fingerprint($fields), md5(serialize($options))),
            fn (): array => $this->client->placeDetails($placeId, $fields, $options),
        );

        if ($payload === []) {
            throw new NotFoundException(
                sprintf('Google returned no data for place [%s].', $placeId),
                404,
            );
        }

        return Place::fromApi($payload);
    }

    /**
     * The reviews Google publishes for a place, without any OAuth.
     *
     * Note that Google returns only a small, algorithmically chosen subset
     * here, and offers no pagination. For the full review history of a business
     * you own, connect it through Mode B instead.
     *
     * @param  list<string>|null  $fields
     * @return Collection<int, \Khadikul\GooglePlaces\Data\Review>
     */
    public function publicReviews(string $placeId, ?array $fields = null): Collection
    {
        $fields = $fields ?? ['id', 'reviews'];

        return $this->place($placeId, $fields)->reviews();
    }

    /**
     * Resolve a photo reference to a displayable image URL.
     *
     * Done server-side so the API key stays on the server; the URL Google
     * returns is temporary but contains no credential of yours.
     */
    public function photoUrl(Photo|string $photo, ?int $maxWidthPx = null, ?int $maxHeightPx = null): ?string
    {
        $name = $photo instanceof Photo ? $photo->name : $photo;

        if ($name === '') {
            return null;
        }

        return $this->remember(
            $this->key('photo', $name, (string) $maxWidthPx, (string) $maxHeightPx),
            fn (): array => ['uri' => $this->client->photoUri($name, $maxWidthPx, $maxHeightPx)],
        )['uri'] ?? null;
    }

    /**
     * Drop every cached Places response for a place ID.
     *
     * Cache keys embed the field mask, so a tag-less store cannot enumerate
     * them. A per-place version counter is bumped instead, which invalidates
     * all variants at once on any store, including the array and file drivers.
     */
    public function forget(string $placeId): void
    {
        $store = $this->store();

        if ($store === null) {
            return;
        }

        $key = $this->versionKey($placeId);

        // Read-modify-write rather than increment(), because increment() on a
        // missing key behaves differently across drivers.
        $store->forever($key, (int) ($store->get($key) ?? 0) + 1);
    }

    public function cacheEnabled(): bool
    {
        return (bool) $this->config->get('google-places.cache.enabled', true);
    }

    /**
     * @return list<string>
     */
    protected function defaultFields(string $type): array
    {
        $fields = $this->config->get('google-places.fields.'.$type, []);

        return array_values(array_filter((array) $fields, 'is_string'));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function locationBias(?float $latitude, ?float $longitude, ?float $radius): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'circle' => [
                'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                // Google accepts 0 to 50000 metres.
                'radius' => min(max($radius ?? 5000.0, 0.0), 50000.0),
            ],
        ];
    }

    /**
     * @return Collection<int, Place>
     */
    protected function mapPlaces(mixed $places): Collection
    {
        if (! is_array($places)) {
            return new Collection;
        }

        return (new Collection($places))
            ->filter(static fn (mixed $place): bool => is_array($place))
            ->map(static fn (array $place): Place => Place::fromApi($place))
            ->values();
    }

    /**
     * @template TValue of array<string, mixed>
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    protected function remember(string $key, callable $callback): array
    {
        $store = $this->store();

        if ($store === null) {
            return $callback();
        }

        $ttl = (int) $this->config->get('google-places.cache.ttl', 3600);

        if ($ttl <= 0) {
            return $callback();
        }

        return $store->remember($key, $ttl, $callback);
    }

    protected function store(): ?CacheRepository
    {
        if (! $this->cacheEnabled()) {
            return null;
        }

        $name = $this->config->get('google-places.cache.store');

        return $this->cache->store(is_string($name) && $name !== '' ? $name : null);
    }

    protected function key(string $type, string $identifier, string ...$parts): string
    {
        return implode(':', array_filter([
            (string) $this->config->get('google-places.cache.prefix', 'google-places'),
            $type,
            $this->version($this->versionScope($identifier)),
            sha1($identifier.'|'.implode('|', $parts)),
        ], static fn (string $part): bool => $part !== ''));
    }

    /**
     * Which place a cache entry belongs to, so that forget($placeId) also drops
     * that place's photo URLs. Search results are keyed by query text and are
     * left to expire on their own TTL.
     */
    protected function versionScope(string $identifier): string
    {
        if (preg_match('#^places/([^/]+)#', $identifier, $matches) === 1) {
            return $matches[1];
        }

        return $identifier;
    }

    protected function version(string $identifier): string
    {
        $store = $this->store();

        if ($store === null) {
            return '0';
        }

        $version = $store->get($this->versionKey($identifier));

        return is_numeric($version) ? (string) (int) $version : '0';
    }

    protected function versionKey(string $identifier): string
    {
        return $this->config->get('google-places.cache.prefix', 'google-places').':v:'.sha1($identifier);
    }
}
