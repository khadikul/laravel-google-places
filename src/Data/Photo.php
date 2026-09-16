<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A place photo reference.
 *
 * Google does not return image bytes or a public URL with the place payload,
 * only this reference. Resolve it to a displayable URL with
 * GooglePlaces::photoUrl(), which asks Google for a short lived image URL
 * server-side so your API key is never exposed in your HTML.
 *
 * Google's Terms of Service require the author attributions below to be
 * displayed alongside the photo.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Photo implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $name  Resource name, "places/{place}/photos/{photo}".
     * @param  list<array{displayName: ?string, uri: ?string, photoUri: ?string}>  $authorAttributions
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?int $widthPx = null,
        public ?int $heightPx = null,
        public array $authorAttributions = [],
        public ?string $googleMapsUri = null,
        public ?string $flagContentUri = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        $attributions = [];

        foreach ((array) ($payload['authorAttributions'] ?? []) as $attribution) {
            if (! is_array($attribution)) {
                continue;
            }

            $attributions[] = [
                'displayName' => self::str($attribution, 'displayName'),
                'uri' => self::str($attribution, 'uri'),
                'photoUri' => self::str($attribution, 'photoUri'),
            ];
        }

        return new self(
            name: self::str($payload, 'name') ?? '',
            widthPx: self::int($payload, 'widthPx'),
            heightPx: self::int($payload, 'heightPx'),
            authorAttributions: $attributions,
            googleMapsUri: self::str($payload, 'googleMapsUri'),
            flagContentUri: self::str($payload, 'flagContentUri'),
            raw: $payload,
        );
    }

    /**
     * Names of the people Google requires you to credit when displaying this photo.
     *
     * @return list<string>
     */
    public function attributionNames(): array
    {
        $names = [];

        foreach ($this->authorAttributions as $attribution) {
            if (is_string($attribution['displayName'] ?? null) && $attribution['displayName'] !== '') {
                $names[] = $attribution['displayName'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'width_px' => $this->widthPx,
            'height_px' => $this->heightPx,
            'author_attributions' => $this->authorAttributions,
            'google_maps_uri' => $this->googleMapsUri,
            'flag_content_uri' => $this->flagContentUri,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function str(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function int(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) || is_float($value) ? (int) $value : null;
    }
}
