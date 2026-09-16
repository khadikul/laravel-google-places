<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A Google Business Profile account, as returned by the Account Management API.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class BusinessAccount implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $name  Resource name, "accounts/{account_id}".
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?string $accountName = null,
        public ?string $type = null,
        public ?string $role = null,
        public ?string $verificationState = null,
        public ?string $vettedState = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            name: self::str($payload, 'name') ?? '',
            accountName: self::str($payload, 'accountName'),
            type: self::str($payload, 'type'),
            role: self::str($payload, 'role'),
            verificationState: self::str($payload, 'verificationState'),
            vettedState: self::str($payload, 'vettedState'),
            raw: $payload,
        );
    }

    /**
     * The bare account ID, i.e. "accounts/123" becomes "123".
     */
    public function id(): string
    {
        return str_starts_with($this->name, 'accounts/')
            ? substr($this->name, strlen('accounts/'))
            : $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id(),
            'account_name' => $this->accountName,
            'type' => $this->type,
            'role' => $this->role,
            'verification_state' => $this->verificationState,
            'vetted_state' => $this->vettedState,
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
}
