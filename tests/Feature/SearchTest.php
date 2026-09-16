<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Exceptions\AuthenticationException;
use Khadikul\GooglePlaces\Exceptions\AuthorizationException;
use Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException;
use Khadikul\GooglePlaces\Exceptions\RateLimitException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SearchTest extends TestCase
{
    #[Test]
    public function it_maps_search_results_into_place_objects(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:searchText' => Http::response($this->fixture('search-results')),
        ]);

        $results = GooglePlaces::search('Google Sydney');

        $this->assertCount(2, $results);
        $this->assertSame('ChIJN1t_tDeuEmsRUsoyG83frY4', $results[0]->id);
        $this->assertSame('Google Sydney', $results[0]->name);
        $this->assertSame('48 Pirrama Rd, Pyrmont NSW 2009, Australia', $results[0]->address);
        $this->assertSame(4.5, $results[0]->rating);
        $this->assertSame(1234, $results[0]->reviewCount);
        $this->assertSame(-33.866489, $results[0]->latitude);
    }

    #[Test]
    public function it_sends_the_api_key_as_a_header_and_never_in_the_url(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fixture('search-results')),
        ]);

        GooglePlaces::search('Google Sydney');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('test-api-key', $request->header('X-Goog-Api-Key')[0]);
            $this->assertStringNotContainsString('test-api-key', $request->url());

            return true;
        });
    }

    #[Test]
    public function it_prefixes_the_field_mask_for_search_responses(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fixture('search-results')),
        ]);

        GooglePlaces::search('Google Sydney', fields: ['id', 'displayName']);

        Http::assertSent(function (Request $request): bool {
            return $request->header('X-Goog-FieldMask')[0] === 'places.id,places.displayName,nextPageToken';
        });
    }

    #[Test]
    public function it_applies_a_location_bias_when_coordinates_are_given(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fixture('search-results')),
        ]);

        GooglePlaces::search('Torlyx Security', latitude: 23.8103, longitude: 90.4125);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('Torlyx Security', $body['textQuery']);
            $this->assertSame(23.8103, $body['locationBias']['circle']['center']['latitude']);
            $this->assertSame(90.4125, $body['locationBias']['circle']['center']['longitude']);
            $this->assertSame(5000.0, $body['locationBias']['circle']['radius']);

            return true;
        });
    }

    #[Test]
    public function it_clamps_the_bias_radius_to_the_documented_maximum(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('search-results'))]);

        GooglePlaces::search('Anything', latitude: 1.0, longitude: 2.0, radius: 999999.0);

        Http::assertSent(
            fn (Request $request): bool => $request->data()['locationBias']['circle']['radius'] === 50000.0
        );
    }

    #[Test]
    public function it_returns_an_empty_collection_when_google_matches_nothing(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([])]);

        $this->assertTrue(GooglePlaces::search('nothing at all')->isEmpty());
    }

    #[Test]
    public function it_fails_loudly_when_no_api_key_is_configured(): void
    {
        config(['google-places.api_key' => null]);

        Http::fake();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('GOOGLE_PLACES_API_KEY');

        GooglePlaces::search('anything');
    }

    #[Test]
    public function it_never_leaks_the_api_key_in_an_exception_message(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                ['error' => ['code' => 403, 'message' => 'Denied', 'status' => 'PERMISSION_DENIED']],
                403,
            ),
        ]);

        try {
            GooglePlaces::search('anything');
            $this->fail('Expected an AuthorizationException.');
        } catch (ApiException $e) {
            $this->assertStringNotContainsString('test-api-key', $e->getMessage());
        }
    }

    #[Test]
    #[DataProvider('errorStatuses')]
    public function it_maps_http_statuses_to_specific_exceptions(int $status, string $expected): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                ['error' => ['code' => $status, 'message' => 'Boom', 'status' => 'FAILED']],
                $status,
            ),
        ]);

        $this->expectException($expected);

        GooglePlaces::search('anything');
    }

    /**
     * @return array<string, array{0: int, 1: class-string}>
     */
    public static function errorStatuses(): array
    {
        return [
            '400 bad request' => [400, ApiException::class],
            '401 unauthenticated' => [401, AuthenticationException::class],
            '403 forbidden' => [403, AuthorizationException::class],
            '429 rate limited' => [429, RateLimitException::class],
            '500 server error' => [500, ApiException::class],
            '503 unavailable' => [503, ApiException::class],
        ];
    }

    #[Test]
    public function it_wraps_connection_failures_in_an_api_exception(): void
    {
        Http::fake(function (): void {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        try {
            GooglePlaces::search('anything');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertSame('CONNECTION_FAILED', $e->reason());
            $this->assertTrue($e->isRetryable());
        }
    }

    #[Test]
    public function it_reports_a_rate_limit_retry_after_header(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                ['error' => ['code' => 429, 'message' => 'Quota exceeded', 'status' => 'RESOURCE_EXHAUSTED']],
                429,
                ['Retry-After' => '30'],
            ),
        ]);

        try {
            GooglePlaces::search('anything');
            $this->fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            $this->assertSame(30, $e->retryAfter());
        }
    }

    #[Test]
    public function it_raises_an_api_exception_when_google_returns_invalid_json(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response('<html>not json</html>', 200)]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('not valid JSON');

        GooglePlaces::search('anything');
    }
}
