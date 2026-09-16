<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A Business Profile location, as returned by the Business Information API.
 *
 * Mind the two naming schemes: the Business Information API identifies a
 * location as "locations/{location_id}", while the (legacy, still current)
 * Reviews API addresses it as "accounts/{account_id}/locations/{location_id}".
 * This DTO keeps both, so the account it was listed under is never lost.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Location implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $name  Resource name, "locations/{location_id}".
     * @param  ?string  $accountName  Owning account, "accounts/{account_id}".
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?string $accountName = null,
        public ?string $title = null,
        public ?string $storeCode = null,
        public ?string $address = null,
        public ?string $websiteUri = null,
        public ?string $phoneNumber = null,
        public ?string $placeId = null,
        public ?string $mapsUri = null,
        public ?string $newReviewUri = null,
        public ?bool $hasVoiceOfMerchant = null,
        public ?string $languageCode = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload, ?string $accountName = null): self
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $phones = is_array($payload['phoneNumbers'] ?? null) ? $payload['phoneNumbers'] : [];

        return new self(
            name: self::normaliseName(self::str($payload, 'name') ?? ''),
            accountName: $accountName,
            title: self::str($payload, 'title'),
            storeCode: self::str($payload, 'storeCode'),
            address: self::formatAddress($payload['storefrontAddress'] ?? null),
            websiteUri: self::str($payload, 'websiteUri'),
            phoneNumber: self::str($phones, 'primaryPhone'),
            placeId: self::str($metadata, 'placeId'),
            mapsUri: self::str($metadata, 'mapsUri'),
            newReviewUri: self::str($metadata, 'newReviewUri'),
            hasVoiceOfMerchant: isset($metadata['hasVoiceOfMerchant'])
                ? (bool) $metadata['hasVoiceOfMerchant']
                : null,
            languageCode: self::str($payload, 'languageCode'),
            raw: $payload,
        );
    }

    /**
     * The bare location ID, i.e. "locations/123" becomes "123".
     */
    public function id(): string
    {
        return str_starts_with($this->name, 'locations/')
            ? substr($this->name, strlen('locations/'))
            : $this->name;
    }

    /**
     * The fully qualified name the Reviews API expects,
     * "accounts/{account_id}/locations/{location_id}".
     *
     * Returns null when the owning account is unknown, because reviews cannot
     * be addressed without it.
     */
    public function resourceName(): ?string
    {
        if ($this->accountName === null || $this->accountName === '') {
            return null;
        }

        return rtrim($this->accountName, '/').'/locations/'.$this->id();
    }

    public function forAccount(string $accountName): self
    {
        return new self(
            name: $this->name,
            accountName: $accountName,
            title: $this->title,
            storeCode: $this->storeCode,
            address: $this->address,
            websiteUri: $this->websiteUri,
            phoneNumber: $this->phoneNumber,
            placeId: $this->placeId,
            mapsUri: $this->mapsUri,
            newReviewUri: $this->newReviewUri,
            hasVoiceOfMerchant: $this->hasVoiceOfMerchant,
            languageCode: $this->languageCode,
            raw: $this->raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id(),
            'resource_name' => $this->resourceName(),
            'account_name' => $this->accountName,
            'title' => $this->title,
            'store_code' => $this->storeCode,
            'address' => $this->address,
            'website_uri' => $this->websiteUri,
            'phone_number' => $this->phoneNumber,
            'place_id' => $this->placeId,
            'maps_uri' => $this->mapsUri,
            'new_review_uri' => $this->newReviewUri,
            'has_voice_of_merchant' => $this->hasVoiceOfMerchant,
            'language_code' => $this->languageCode,
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
     * Some endpoints return "accounts/1/locations/2"; reduce to "locations/2"
     * so the canonical form is consistent everywhere.
     */
    private static function normaliseName(string $name): string
    {
        if (preg_match('#(locations/[^/]+)$#', $name, $matches) === 1) {
            return $matches[1];
        }

        return $name;
    }

    private static function formatAddress(mixed $address): ?string
    {
        if (! is_array($address)) {
            return null;
        }

        $parts = [];

        foreach ((array) ($address['addressLines'] ?? []) as $line) {
            if (is_string($line) && $line !== '') {
                $parts[] = $line;
            }
        }

        foreach (['locality', 'administrativeArea', 'postalCode', 'regionCode'] as $key) {
            $value = $address[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function str(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
