<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Khadikul\GooglePlaces\Data\Place;
use Khadikul\GooglePlaces\Exceptions\NotFoundException;
use Khadikul\GooglePlaces\Facades\GooglePlaces;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PlaceDetailsTest extends TestCase
{
    #[Test]
    public function it_maps_a_place_detail_response(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response($this->fixture('place-details')),
        ]);

        $place = GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');

        $this->assertInstanceOf(Place::class, $place);
        $this->assertSame('ChIJN1t_tDeuEmsRUsoyG83frY4', $place->id);
        $this->assertSame('Google Sydney', $place->name);
        $this->assertSame('48 Pirrama Rd, Pyrmont NSW 2009, Australia', $place->address);
        $this->assertSame(4.5, $place->rating);
        $this->assertSame(1234, $place->reviewCount);
        $this->assertSame('https://maps.google.com/?cid=10281119596374313554', $place->googleMapsUri);
        $this->assertSame('Corporate office', $place->primaryType);
        $this->assertTrue($place->isOperational());
    }

    #[Test]
    public function the_convenience_methods_mirror_the_properties(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        $place = GooglePlaces::place('ChIJN1t_tDeuEmsRUsoyG83frY4');

        $this->assertSame($place->name, $place->name());
        $this->assertSame($place->rating, $place->rating());
        $this->assertSame($place->reviewCount, $place->reviewCount());
        $this->assertSame($place->googleMapsUri, $place->mapsUrl());
        $this->assertSame($place->reviews->all(), $place->reviews()->all());
    }

    #[Test]
    public function it_sends_the_configured_field_mask_unprefixed_for_details(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::place('ChIJ123', fields: ['displayName', 'rating', 'userRatingCount', 'reviews', 'googleMapsUri']);

        Http::assertSent(fn (Request $request): bool => $request->header('X-Goog-FieldMask')[0]
            === 'displayName,rating,userRatingCount,reviews,googleMapsUri');
    }

    #[Test]
    public function it_de_duplicates_and_trims_the_field_mask(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        GooglePlaces::place('ChIJ123', fields: ['rating', ' rating ', 'displayName', '']);

        Http::assertSent(fn (Request $request): bool => $request->header('X-Goog-FieldMask')[0] === 'rating,displayName');
    }

    #[Test]
    public function a_narrow_field_mask_produces_null_properties_rather_than_an_error(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response([
                'id' => 'ChIJ123',
                'displayName' => ['text' => 'Minimal Business', 'languageCode' => 'en'],
            ]),
        ]);

        $place = GooglePlaces::place('ChIJ123', fields: ['id', 'displayName']);

        $this->assertSame('Minimal Business', $place->name);
        $this->assertNull($place->rating);
        $this->assertNull($place->reviewCount);
        $this->assertNull($place->address);
        $this->assertTrue($place->reviews()->isEmpty());
        $this->assertTrue($place->photos()->isEmpty());
    }

    #[Test]
    public function it_derives_the_id_from_the_resource_name_when_id_is_not_in_the_mask(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response([
                'name' => 'places/ChIJDerivedFromName',
                'displayName' => ['text' => 'No Id Field'],
            ]),
        ]);

        $this->assertSame('ChIJDerivedFromName', GooglePlaces::place('ChIJDerivedFromName')->id);
    }

    #[Test]
    public function it_url_encodes_the_place_id_in_the_path(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['id' => 'x'])]);

        GooglePlaces::place('ChIJ/../secret');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'places/ChIJ%2F..%2Fsecret'));
    }

    #[Test]
    public function it_throws_not_found_when_google_returns_an_empty_body(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response('', 200)]);

        $this->expectException(NotFoundException::class);

        GooglePlaces::place('ChIJMissing');
    }

    #[Test]
    public function it_throws_not_found_on_a_404(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(
                ['error' => ['code' => 404, 'message' => 'Place not found', 'status' => 'NOT_FOUND']],
                404,
            ),
        ]);

        $this->expectException(NotFoundException::class);

        GooglePlaces::place('ChIJMissing');
    }

    #[Test]
    public function it_resolves_a_photo_url_server_side_without_exposing_the_api_key(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*/photos/*/media*' => Http::response([
                'name' => 'places/ChIJ123/photos/AeeoHcK/media',
                'photoUri' => 'https://lh3.googleusercontent.com/places/resolved-image',
            ]),
        ]);

        $url = GooglePlaces::photoUrl('places/ChIJ123/photos/AeeoHcK', maxWidthPx: 400);

        $this->assertSame('https://lh3.googleusercontent.com/places/resolved-image', $url);
        $this->assertStringNotContainsString('test-api-key', (string) $url);

        Http::assertSent(function (Request $request): bool {
            $this->assertStringContainsString('skipHttpRedirect=true', $request->url());
            $this->assertStringContainsString('maxWidthPx=400', $request->url());

            return true;
        });
    }

    #[Test]
    public function photo_attributions_are_preserved_for_the_required_credit(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response($this->fixture('place-details'))]);

        $photo = GooglePlaces::place('ChIJ123')->photos()->first();

        $this->assertNotNull($photo);
        $this->assertSame(['A Google User'], $photo->attributionNames());
        $this->assertSame(4032, $photo->widthPx);
    }
}
