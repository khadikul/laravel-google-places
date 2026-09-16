<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Khadikul\GooglePlaces\Exceptions\AuthorizationException;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Exceptions\NotFoundException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class BusinessProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withOAuthConfig();
    }

    private function connect(): GoogleBusinessConnection
    {
        return GoogleBusinessConnection::create([
            'google_account_name' => 'accounts/111',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
        ]);
    }

    #[Test]
    public function it_lists_business_accounts(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessaccountmanagement.googleapis.com/v1/accounts*' => Http::response([
                'accounts' => [
                    [
                        'name' => 'accounts/111',
                        'accountName' => 'Torlyx Security',
                        'type' => 'LOCATION_GROUP',
                        'role' => 'OWNER',
                        'verificationState' => 'VERIFIED',
                    ],
                ],
            ]),
        ]);

        $accounts = GooglePlaces::businessAccounts();

        $this->assertCount(1, $accounts);
        $this->assertSame('accounts/111', $accounts[0]->name);
        $this->assertSame('111', $accounts[0]->id());
        $this->assertSame('Torlyx Security', $accounts[0]->accountName);
        $this->assertSame('OWNER', $accounts[0]->role);
    }

    #[Test]
    public function it_pages_through_every_account(): void
    {
        $this->connect();

        Http::fakeSequence('mybusinessaccountmanagement.googleapis.com/*')
            ->push(['accounts' => [['name' => 'accounts/1']], 'nextPageToken' => 'more'])
            ->push(['accounts' => [['name' => 'accounts/2']]]);

        $this->assertCount(2, GooglePlaces::businessAccounts());
    }

    #[Test]
    public function it_refuses_to_call_the_business_api_without_a_connection(): void
    {
        Http::fake();

        $this->expectException(ConnectionNotFoundException::class);

        GooglePlaces::businessAccounts();
    }

    #[Test]
    public function it_lists_locations_with_the_mandatory_read_mask(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response([
                'locations' => [[
                    'name' => 'locations/222',
                    'title' => 'Torlyx Security Dhaka',
                    'storeCode' => 'DHK-01',
                    'websiteUri' => 'https://torlyx.example',
                    'phoneNumbers' => ['primaryPhone' => '+880 1711 000000'],
                    'storefrontAddress' => [
                        'addressLines' => ['12 Gulshan Ave'],
                        'locality' => 'Dhaka',
                        'postalCode' => '1212',
                        'regionCode' => 'BD',
                    ],
                    'metadata' => [
                        'placeId' => 'ChIJPlaceFromMetadata',
                        'mapsUri' => 'https://maps.google.com/?cid=1',
                        'hasVoiceOfMerchant' => true,
                    ],
                ]],
            ]),
        ]);

        $locations = GooglePlaces::locations('111');

        $this->assertCount(1, $locations);

        $location = $locations[0];

        $this->assertSame('locations/222', $location->name);
        $this->assertSame('222', $location->id());
        $this->assertSame('Torlyx Security Dhaka', $location->title);

        // The bridge between a Business Profile location and its public place.
        $this->assertSame('ChIJPlaceFromMetadata', $location->placeId);

        // The Reviews API needs the account-qualified form.
        $this->assertSame('accounts/111/locations/222', $location->resourceName());
        $this->assertSame('12 Gulshan Ave, Dhaka, 1212, BD', $location->address);
        $this->assertTrue($location->hasVoiceOfMerchant);

        Http::assertSent(function (Request $request): bool {
            $this->assertStringContainsString('accounts/111/locations', $request->url());
            // readMask is required by Google; omitting it is an error.
            $this->assertStringContainsString('readMask=', $request->url());

            return true;
        });
    }

    #[Test]
    public function it_accepts_an_already_qualified_account_name(): void
    {
        $this->connect();

        Http::fake(['mybusinessbusinessinformation.googleapis.com/*' => Http::response(['locations' => []])]);

        GooglePlaces::locations('accounts/111');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/accounts/111/locations'));
    }

    #[Test]
    public function connecting_a_location_stores_it_and_enables_sync(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response([
                'name' => 'locations/222',
                'title' => 'Torlyx Security Dhaka',
                'metadata' => ['placeId' => 'ChIJPlaceFromMetadata'],
            ]),
        ]);

        $model = GooglePlaces::connectLocation('locations/222', 'accounts/111');

        $this->assertSame('accounts/111/locations/222', $model->location_name);
        $this->assertSame('ChIJPlaceFromMetadata', $model->place_id);
        $this->assertSame('Torlyx Security Dhaka', $model->title);
        $this->assertTrue($model->sync_enabled);

        $this->assertDatabaseHas('google_business_locations', [
            'location_name' => 'accounts/111/locations/222',
            'place_id' => 'ChIJPlaceFromMetadata',
        ]);
    }

    #[Test]
    public function connecting_accepts_a_fully_qualified_location_name(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response([
                'name' => 'locations/222',
                'title' => 'Qualified',
            ]),
        ]);

        $model = GooglePlaces::connectLocation('accounts/111/locations/222');

        $this->assertSame('accounts/111/locations/222', $model->location_name);
    }

    #[Test]
    public function connecting_the_same_location_twice_updates_one_row(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response([
                'name' => 'locations/222',
                'title' => 'Renamed Business',
            ]),
        ]);

        GooglePlaces::connectLocation('locations/222', 'accounts/111');
        GooglePlaces::connectLocation('locations/222', 'accounts/111');

        $this->assertDatabaseCount('google_business_locations', 1);
        $this->assertDatabaseHas('google_business_locations', ['title' => 'Renamed Business']);
    }

    #[Test]
    public function it_refuses_to_store_a_location_whose_id_could_not_be_read(): void
    {
        $this->connect();

        // A response that does not carry a location name — e.g. an endpoint
        // that answered with a list envelope instead of a single resource.
        Http::fake([
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response([
                'locations' => [['name' => 'locations/222', 'title' => 'Wrong shape']],
            ]),
        ]);

        try {
            GooglePlaces::connectLocation('locations/222', 'accounts/111');
            $this->fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertStringContainsString('no usable location ID', $e->getMessage());
        }

        // Nothing may be written: "accounts/111/locations/" is unique, so a
        // stored stub would be overwritten by the next failure and would never
        // be syncable.
        $this->assertDatabaseCount('google_business_locations', 0);
    }

    #[Test]
    public function multiple_locations_across_multiple_accounts_are_supported(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessbusinessinformation.googleapis.com/*/locations/333' => Http::response(['name' => 'locations/333']),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response(['name' => 'locations/222']),
        ]);

        GooglePlaces::connectLocation('locations/222', 'accounts/111');
        GooglePlaces::connectLocation('locations/333', 'accounts/999');

        $this->assertDatabaseCount('google_business_locations', 2);
        $this->assertCount(2, GooglePlaces::connectedLocations());
    }

    #[Test]
    public function disconnecting_stops_sync_without_deleting_reviews(): void
    {
        $this->connect();

        Http::fake(['mybusinessbusinessinformation.googleapis.com/*' => Http::response(['name' => 'locations/222'])]);

        GooglePlaces::connectLocation('locations/222', 'accounts/111');

        $this->assertTrue(GooglePlaces::disconnectLocation('accounts/111/locations/222'));

        $this->assertDatabaseHas('google_business_locations', [
            'location_name' => 'accounts/111/locations/222',
            'sync_enabled' => false,
        ]);

        $this->assertCount(0, GooglePlaces::connectedLocations());
    }

    #[Test]
    public function disconnecting_an_unknown_location_reports_false(): void
    {
        $this->assertFalse(GooglePlaces::disconnectLocation('accounts/1/locations/does-not-exist'));
    }

    #[Test]
    public function a_project_without_business_profile_quota_gets_a_clear_403(): void
    {
        $this->connect();

        Http::fake([
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'My Business Account Management API has not been used in project 1 before or it is disabled.',
                    'status' => 'PERMISSION_DENIED',
                ],
            ], 403),
        ]);

        try {
            GooglePlaces::businessAccounts();
            $this->fail('Expected an AuthorizationException.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('API is enabled on your Google Cloud project', $e->getMessage());
            $this->assertStringNotContainsString('valid-access-token', $e->getMessage());
        }
    }
}
