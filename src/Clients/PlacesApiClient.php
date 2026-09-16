<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Clients;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException;
use Khadikul\GooglePlaces\Support\FieldMask;
use Throwable;

/**
 * Thin transport for the Places API (New).
 *
 * Authenticates with the application owner's own API key, sent in the
 * X-Goog-Api-Key header rather than as a query parameter so that it does not
 * end up in access logs or referrer headers.
 */
class PlacesApiClient
{
    use HandlesGoogleResponses;

    public function __construct(
        protected HttpFactory $http,
        protected Config $config,
    ) {}

    /**
     * POST /v1/places:searchText
     *
     * @param  list<string>  $fields  Unprefixed field names; "places." is added for you.
     * @return array<string, mixed>
     *
     * @throws ApiException|InvalidConfigurationException
     */
    public function searchText(string $query, array $fields, array $options = []): array
    {
        $body = array_filter([
            'textQuery' => $query,
            'pageSize' => $options['pageSize'] ?? null,
            'pageToken' => $options['pageToken'] ?? null,
            'languageCode' => $options['languageCode'] ?? $this->config->get('google-places.language_code'),
            'regionCode' => $options['regionCode'] ?? $this->config->get('google-places.region_code'),
            'locationBias' => $options['locationBias'] ?? null,
            'locationRestriction' => $options['locationRestriction'] ?? null,
            'includedType' => $options['includedType'] ?? null,
            'minRating' => $options['minRating'] ?? null,
            'openNow' => $options['openNow'] ?? null,
            'rankPreference' => $options['rankPreference'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->guardTransport(function () use ($body, $fields): array {
            $response = $this->request(FieldMask::forSearch($fields))
                ->post($this->url('places:searchText'), $body);

            return $this->decode($response, 'Places text search');
        }, 'Places text search');
    }

    /**
     * GET /v1/places/{placeId}
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>
     *
     * @throws ApiException|InvalidConfigurationException
     */
    public function placeDetails(string $placeId, array $fields, array $options = []): array
    {
        $query = array_filter([
            'languageCode' => $options['languageCode'] ?? $this->config->get('google-places.language_code'),
            'regionCode' => $options['regionCode'] ?? $this->config->get('google-places.region_code'),
            'sessionToken' => $options['sessionToken'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->guardTransport(function () use ($placeId, $fields, $query): array {
            $response = $this->request(FieldMask::build($fields))
                ->get($this->url('places/'.$this->segment($placeId)), $query);

            return $this->decode($response, 'Places details lookup');
        }, 'Places details lookup');
    }

    /**
     * Resolves a photo reference to a temporary image URL.
     *
     * skipHttpRedirect makes Google answer with JSON containing "photoUri"
     * instead of a 302, which lets the URL be resolved server-side so the API
     * key never appears in markup served to browsers.
     *
     * @throws ApiException|InvalidConfigurationException
     */
    public function photoUri(string $photoName, ?int $maxWidthPx = null, ?int $maxHeightPx = null): ?string
    {
        if ($maxWidthPx === null && $maxHeightPx === null) {
            $maxWidthPx = 800;
        }

        $query = array_filter([
            'maxWidthPx' => $maxWidthPx,
            'maxHeightPx' => $maxHeightPx,
            'skipHttpRedirect' => 'true',
        ], static fn (mixed $value): bool => $value !== null);

        $payload = $this->guardTransport(function () use ($photoName, $query): array {
            $response = $this->request(null)
                ->get($this->url(trim($photoName, '/').'/media'), $query);

            return $this->decode($response, 'Places photo lookup');
        }, 'Places photo lookup');

        $uri = $payload['photoUri'] ?? null;

        return is_string($uri) && $uri !== '' ? $uri : null;
    }

    protected function request(?string $fieldMask): PendingRequest
    {
        $headers = ['X-Goog-Api-Key' => $this->apiKey()];

        if ($fieldMask !== null && $fieldMask !== '') {
            $headers['X-Goog-FieldMask'] = $fieldMask;
        }

        $retry = (array) $this->config->get('google-places.retry', []);

        return $this->http
            ->asJson()
            ->acceptJson()
            ->withHeaders($headers)
            ->timeout((int) $this->config->get('google-places.timeout', 10))
            ->connectTimeout((int) $this->config->get('google-places.connect_timeout', 5))
            ->retry(
                max(1, (int) ($retry['times'] ?? 1)),
                (int) ($retry['sleep'] ?? 200),
                $this->retryDecider(),
                throw: false,
            );
    }

    /**
     * Retry only what can plausibly succeed on a second attempt. Retrying a 400
     * or a 403 just burns quota.
     */
    protected function retryDecider(): callable
    {
        return static function (Throwable $exception, PendingRequest $request): bool {
            if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                return true;
            }

            if (! $exception instanceof \Illuminate\Http\Client\RequestException) {
                return false;
            }

            return in_array($exception->response->status(), [408, 429, 500, 502, 503, 504], true);
        };
    }

    protected function url(string $path): string
    {
        return rtrim((string) $this->config->get('google-places.base_url'), '/').'/'.ltrim($path, '/');
    }

    protected function segment(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function apiKey(): string
    {
        $key = $this->config->get('google-places.api_key');

        if (! is_string($key) || trim($key) === '') {
            throw InvalidConfigurationException::missingApiKey();
        }

        return $key;
    }
}
