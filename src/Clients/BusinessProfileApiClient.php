<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Clients;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Khadikul\GooglePlaces\Contracts\ProvidesAccessTokens;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Throwable;

/**
 * Transport for the Google Business Profile APIs.
 *
 * Google has split this surface across four hosts, and reviews were never
 * migrated off the legacy v4 host, so each call below targets whichever host
 * currently owns that resource:
 *
 *   accounts            mybusinessaccountmanagement.googleapis.com/v1
 *   locations           mybusinessbusinessinformation.googleapis.com/v1
 *   reviews             mybusiness.googleapis.com/v4            (legacy, current)
 *   notification setting mybusinessnotifications.googleapis.com/v1
 *
 * Every request is authorised with the application owner's own OAuth token,
 * fetched (and refreshed if stale) through ProvidesAccessTokens.
 */
class BusinessProfileApiClient
{
    use HandlesGoogleResponses;

    public function __construct(
        protected HttpFactory $http,
        protected Config $config,
        protected ProvidesAccessTokens $tokens,
    ) {}

    /**
     * GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts
     *
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function listAccounts(GoogleBusinessConnection $connection, ?string $pageToken = null, int $pageSize = 20): array
    {
        $query = array_filter([
            'pageSize' => $pageSize,
            'pageToken' => $pageToken,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->guardTransport(function () use ($connection, $query): array {
            $response = $this->request($connection)->get($this->host('account_management_url').'/accounts', $query);

            return $this->decode($response, 'Business Profile accounts list');
        }, 'Business Profile accounts list');
    }

    /**
     * GET https://mybusinessbusinessinformation.googleapis.com/v1/{parent}/locations
     *
     * readMask is mandatory on this endpoint; omitting it is an error, not a
     * request for everything.
     *
     * @param  string  $accountName  "accounts/{account_id}"
     * @param  list<string>|null  $readMask
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function listLocations(
        GoogleBusinessConnection $connection,
        string $accountName,
        ?array $readMask = null,
        ?string $pageToken = null,
        int $pageSize = 100,
        ?string $filter = null,
    ): array {
        $readMask ??= (array) $this->config->get('google-places.business_profile.location_read_mask', ['name', 'title']);

        $query = array_filter([
            'readMask' => implode(',', array_values(array_filter($readMask, 'is_string'))),
            'pageSize' => min(max($pageSize, 1), 100),
            'pageToken' => $pageToken,
            'filter' => $filter,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $url = $this->host('business_information_url').'/'.trim($accountName, '/').'/locations';

        return $this->guardTransport(function () use ($connection, $url, $query): array {
            $response = $this->request($connection)->get($url, $query);

            return $this->decode($response, 'Business Profile locations list');
        }, 'Business Profile locations list');
    }

    /**
     * GET https://mybusinessbusinessinformation.googleapis.com/v1/locations/{id}
     *
     * @param  list<string>|null  $readMask
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function getLocation(GoogleBusinessConnection $connection, string $locationName, ?array $readMask = null): array
    {
        $readMask ??= (array) $this->config->get('google-places.business_profile.location_read_mask', ['name', 'title']);

        // This host addresses a location as "locations/{id}", without the account.
        $shortName = preg_match('#(locations/[^/]+)$#', $locationName, $matches) === 1
            ? $matches[1]
            : trim($locationName, '/');

        $url = $this->host('business_information_url').'/'.$shortName;

        return $this->guardTransport(function () use ($connection, $url, $readMask): array {
            $response = $this->request($connection)->get($url, [
                'readMask' => implode(',', array_values(array_filter($readMask, 'is_string'))),
            ]);

            return $this->decode($response, 'Business Profile location lookup');
        }, 'Business Profile location lookup');
    }

    /**
     * GET https://mybusiness.googleapis.com/v4/{parent}/reviews
     *
     * @param  string  $locationName  "accounts/{account_id}/locations/{location_id}"
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function listReviews(
        GoogleBusinessConnection $connection,
        string $locationName,
        ?string $pageToken = null,
        ?int $pageSize = null,
        ?string $orderBy = null,
    ): array {
        $pageSize ??= (int) $this->config->get('google-places.business_profile.reviews_page_size', 50);

        $query = array_filter([
            // Google caps this at 50.
            'pageSize' => min(max($pageSize, 1), 50),
            'pageToken' => $pageToken,
            'orderBy' => $orderBy,
        ], static fn (mixed $value): bool => $value !== null);

        $url = $this->host('reviews_url').'/'.trim($locationName, '/').'/reviews';

        return $this->guardTransport(function () use ($connection, $url, $query): array {
            $response = $this->request($connection)->get($url, $query);

            return $this->decode($response, 'Business Profile reviews list');
        }, 'Business Profile reviews list');
    }

    /**
     * GET https://mybusiness.googleapis.com/v4/{name}
     *
     * @param  string  $reviewName  "accounts/{a}/locations/{l}/reviews/{r}"
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function getReview(GoogleBusinessConnection $connection, string $reviewName): array
    {
        $url = $this->host('reviews_url').'/'.trim($reviewName, '/');

        return $this->guardTransport(function () use ($connection, $url): array {
            $response = $this->request($connection)->get($url);

            return $this->decode($response, 'Business Profile review lookup');
        }, 'Business Profile review lookup');
    }

    /**
     * GET https://mybusinessnotifications.googleapis.com/v1/accounts/{id}/notificationSetting
     *
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function getNotificationSetting(GoogleBusinessConnection $connection, string $accountName): array
    {
        $url = $this->host('notifications_url').'/'.trim($accountName, '/').'/notificationSetting';

        return $this->guardTransport(function () use ($connection, $url): array {
            $response = $this->request($connection)->get($url);

            return $this->decode($response, 'Business Profile notification setting lookup');
        }, 'Business Profile notification setting lookup');
    }

    /**
     * PATCH https://mybusinessnotifications.googleapis.com/v1/accounts/{id}/notificationSetting
     *
     * Passing a null topic with an empty type list is how a subscription is
     * cleared; Google has no delete method for this resource.
     *
     * @param  list<string>  $notificationTypes
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function updateNotificationSetting(
        GoogleBusinessConnection $connection,
        string $accountName,
        ?string $pubsubTopic,
        array $notificationTypes,
    ): array {
        $name = trim($accountName, '/').'/notificationSetting';
        $url = $this->host('notifications_url').'/'.$name;

        $body = [
            'name' => $name,
            'pubsubTopic' => $pubsubTopic,
            'notificationTypes' => array_values($notificationTypes),
        ];

        return $this->guardTransport(function () use ($connection, $url, $body): array {
            $response = $this->request($connection)->patch(
                $url.'?'.http_build_query(['updateMask' => 'pubsubTopic,notificationTypes']),
                $body,
            );

            return $this->decode($response, 'Business Profile notification setting update');
        }, 'Business Profile notification setting update');
    }

    protected function request(GoogleBusinessConnection $connection): PendingRequest
    {
        return $this->http
            ->asJson()
            ->acceptJson()
            ->withToken($this->tokens->accessToken($connection))
            ->timeout((int) $this->config->get('google-places.business_profile.timeout', 15))
            ->connectTimeout((int) $this->config->get('google-places.business_profile.connect_timeout', 5))
            ->retry(
                max(1, (int) $this->config->get('google-places.retry.times', 2)),
                (int) $this->config->get('google-places.retry.sleep', 200),
                static function (Throwable $exception): bool {
                    if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }

                    if (! $exception instanceof \Illuminate\Http\Client\RequestException) {
                        return false;
                    }

                    return in_array($exception->response->status(), [408, 429, 500, 502, 503, 504], true);
                },
                throw: false,
            );
    }

    protected function host(string $key): string
    {
        return rtrim((string) $this->config->get('google-places.business_profile.'.$key), '/');
    }
}
