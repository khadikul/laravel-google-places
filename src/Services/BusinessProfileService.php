<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Services;

use Illuminate\Support\Collection;
use Khadikul\GooglePlaces\Clients\BusinessProfileApiClient;
use Khadikul\GooglePlaces\Data\BusinessAccount;
use Khadikul\GooglePlaces\Data\Location;
use Khadikul\GooglePlaces\Exceptions\NotFoundException;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;

/**
 * Discovery and selection of the business locations to synchronise.
 *
 * Handles the awkward part of Google's model: accounts come from one API,
 * locations from a second that names them "locations/{id}", and reviews from a
 * third that insists on "accounts/{id}/locations/{id}".
 */
class BusinessProfileService
{
    public function __construct(
        protected BusinessProfileApiClient $client,
        protected OAuthService $oauth,
    ) {}

    /**
     * Every Business Profile account the connected user can manage.
     *
     * @return Collection<int, BusinessAccount>
     */
    public function accounts(int|string|GoogleBusinessConnection|null $connection = null): Collection
    {
        $connection = $this->oauth->connection($connection);

        $accounts = new Collection;
        $pageToken = null;

        do {
            $payload = $this->client->listAccounts($connection, $pageToken);

            foreach ((array) ($payload['accounts'] ?? []) as $account) {
                if (is_array($account)) {
                    $accounts->push(BusinessAccount::fromApi($account));
                }
            }

            $pageToken = is_string($payload['nextPageToken'] ?? null) ? $payload['nextPageToken'] : null;
        } while ($pageToken !== null);

        return $accounts;
    }

    /**
     * Every location under one account.
     *
     * @param  string  $accountId  Either "123" or "accounts/123".
     * @param  list<string>|null  $readMask
     * @return Collection<int, Location>
     */
    public function locations(
        string $accountId,
        int|string|GoogleBusinessConnection|null $connection = null,
        ?array $readMask = null,
    ): Collection {
        $connection = $this->oauth->connection($connection);
        $accountName = $this->qualifyAccount($accountId);

        $locations = new Collection;
        $pageToken = null;

        do {
            $payload = $this->client->listLocations($connection, $accountName, $readMask, $pageToken);

            foreach ((array) ($payload['locations'] ?? []) as $location) {
                if (is_array($location)) {
                    $locations->push(Location::fromApi($location, $accountName));
                }
            }

            $pageToken = is_string($payload['nextPageToken'] ?? null) ? $payload['nextPageToken'] : null;
        } while ($pageToken !== null);

        return $locations;
    }

    /**
     * Mark a location for synchronisation and remember it locally.
     *
     * The account is required to build the fully qualified name the Reviews API
     * needs. When it is not given, every account on the connection is searched
     * for the location.
     *
     * @throws NotFoundException
     */
    public function connectLocation(
        string $locationId,
        ?string $accountId = null,
        int|string|GoogleBusinessConnection|null $connection = null,
    ): GoogleBusinessLocation {
        $connection = $this->oauth->connection($connection);

        $location = $this->resolveLocation($locationId, $accountId, $connection);

        /*
         | Refuse to persist a location whose ID could not be read.
         |
         | Without this the resource name degrades to "accounts/1/locations/",
         | which can never be synced and — because location_name is unique —
         | would be silently overwritten by the next location that failed the
         | same way. Failing here keeps one bad response from corrupting the
         | table.
         */
        if (trim($location->id()) === '' || $location->resourceName() === null) {
            throw new NotFoundException(sprintf(
                'Google returned no usable location ID for [%s]. The location may not exist, '
                .'or the account may not have access to it.',
                $locationId,
            ), 404);
        }

        $model = GoogleBusinessLocation::storeFrom($location, $connection);

        $model->forceFill(['sync_enabled' => true])->save();

        return $model;
    }

    /**
     * Stop synchronising a location without deleting the reviews already stored.
     */
    public function disconnectLocation(string $locationName): bool
    {
        $model = GoogleBusinessLocation::query()
            ->where('location_name', $this->qualifyLocationLoosely($locationName))
            ->orWhere('location_name', 'like', '%/'.ltrim($this->shortLocation($locationName), '/'))
            ->first();

        if ($model === null) {
            return false;
        }

        $model->forceFill(['sync_enabled' => false])->save();

        return true;
    }

    /**
     * Locations this application has chosen to synchronise.
     *
     * @return Collection<int, GoogleBusinessLocation>
     */
    public function connectedLocations(): Collection
    {
        return GoogleBusinessLocation::query()->syncEnabled()->get();
    }

    /**
     * @throws NotFoundException
     */
    protected function resolveLocation(
        string $locationId,
        ?string $accountId,
        GoogleBusinessConnection $connection,
    ): Location {
        $short = $this->shortLocation($locationId);

        // An account was named, or one can be read straight off the identifier.
        $accountName = $accountId !== null
            ? $this->qualifyAccount($accountId)
            : $this->accountFromLocation($locationId);

        if ($accountName !== null) {
            $payload = $this->client->getLocation($connection, $short);

            return Location::fromApi($payload, $accountName);
        }

        // Otherwise look through every account the connection can see.
        foreach ($this->accounts($connection) as $account) {
            foreach ($this->locations($account->name, $connection) as $location) {
                if ($location->name === $short) {
                    return $location;
                }
            }
        }

        throw new NotFoundException(sprintf(
            'Location [%s] was not found on any account reachable by this connection. '
            .'Pass the account explicitly if the connection manages many accounts.',
            $locationId,
        ), 404);
    }

    protected function qualifyAccount(string $accountId): string
    {
        return str_starts_with($accountId, 'accounts/') ? $accountId : 'accounts/'.$accountId;
    }

    protected function shortLocation(string $locationId): string
    {
        if (preg_match('#(locations/[^/]+)#', $locationId, $matches) === 1) {
            return $matches[1];
        }

        return 'locations/'.$locationId;
    }

    protected function qualifyLocationLoosely(string $locationName): string
    {
        return str_starts_with($locationName, 'accounts/')
            ? $locationName
            : $this->shortLocation($locationName);
    }

    protected function accountFromLocation(string $locationId): ?string
    {
        if (preg_match('#^(accounts/[^/]+)/locations/#', $locationId, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
