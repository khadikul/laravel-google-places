<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use Khadikul\GooglePlaces\Exceptions\WebhookException;

/**
 * One decoded Google Business Profile notification, extracted from a Pub/Sub
 * push envelope.
 *
 * The envelope is standard Pub/Sub:
 *   {"message": {"data": "<base64>", "messageId": "...", "publishTime": "..."},
 *    "subscription": "projects/p/subscriptions/s"}
 *
 * The inner payload is a Business Profile notification. Google documents it in
 * proto terms (snake_case) while the JSON transport uses camelCase, and field
 * spellings have drifted between the v4 and v1 surfaces, so every field is read
 * through a list of accepted aliases rather than a single hard-coded key.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class NotificationPayload implements Arrayable
{
    /**
     * @param  array<string, mixed>  $data  The decoded inner notification.
     * @param  array<string, mixed>  $envelope  The raw Pub/Sub envelope.
     */
    public function __construct(
        public string $messageId,
        public ?string $notificationType,
        public ?string $locationName,
        public ?string $reviewName,
        public ?string $accountName,
        public ?string $subscription = null,
        public ?string $publishTime = null,
        public array $data = [],
        public array $envelope = [],
    ) {}

    /**
     * @param  array<string, mixed>  $envelope
     *
     * @throws WebhookException
     */
    public static function fromPushRequest(array $envelope): self
    {
        $message = $envelope['message'] ?? null;

        if (! is_array($message)) {
            throw WebhookException::invalidPayload('the "message" object is missing.');
        }

        $messageId = self::str($message, 'messageId') ?? self::str($message, 'message_id');

        if ($messageId === null) {
            throw WebhookException::invalidPayload('the message has no messageId.');
        }

        $data = self::decodeData($message['data'] ?? null);

        $locationName = self::first($data, ['location', 'locationName', 'location_name']);
        $reviewName = self::first($data, ['review', 'reviewName', 'review_name']);
        $accountName = self::first($data, ['account', 'accountName', 'account_name']);

        return new self(
            messageId: $messageId,
            notificationType: self::first($data, ['notificationType', 'notification_type']),
            locationName: self::normaliseLocation($locationName, $reviewName, $accountName),
            reviewName: $reviewName,
            accountName: $accountName ?? self::accountFromName($reviewName ?? $locationName),
            subscription: self::str($envelope, 'subscription'),
            publishTime: self::str($message, 'publishTime') ?? self::str($message, 'publish_time'),
            data: $data,
            envelope: $envelope,
        );
    }

    public function isReviewNotification(): bool
    {
        return in_array($this->notificationType, ['NEW_REVIEW', 'UPDATED_REVIEW'], true);
    }

    /**
     * Whether there is enough information to go and fetch the review.
     */
    public function isActionable(): bool
    {
        return $this->isReviewNotification()
            && $this->reviewName !== null
            && $this->locationName !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'notification_type' => $this->notificationType,
            'location_name' => $this->locationName,
            'review_name' => $this->reviewName,
            'account_name' => $this->accountName,
            'subscription' => $this->subscription,
            'publish_time' => $this->publishTime,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws WebhookException
     */
    private static function decodeData(mixed $data): array
    {
        // A message with no data is legal Pub/Sub but carries no notification.
        if ($data === null || $data === '') {
            return [];
        }

        if (! is_string($data)) {
            throw WebhookException::invalidPayload('"message.data" must be a base64 string.');
        }

        $json = base64_decode(strtr($data, '-_', '+/'), true);

        if ($json === false) {
            throw WebhookException::invalidPayload('"message.data" is not valid base64.');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw WebhookException::invalidPayload('"message.data" is not valid JSON: '.$e->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Notifications sometimes carry only "locations/123" without the account
     * prefix the Reviews API needs. Recover the full name from the review name
     * (which is always fully qualified) or from the account field.
     */
    private static function normaliseLocation(?string $location, ?string $review, ?string $account): ?string
    {
        if ($review !== null && preg_match('#^(accounts/[^/]+/locations/[^/]+)/reviews/#', $review, $m) === 1) {
            return $m[1];
        }

        if ($location === null) {
            return null;
        }

        if (str_starts_with($location, 'accounts/')) {
            return $location;
        }

        if ($account !== null && str_starts_with($location, 'locations/')) {
            return rtrim($account, '/').'/'.$location;
        }

        return $location;
    }

    private static function accountFromName(?string $name): ?string
    {
        if ($name !== null && preg_match('#^(accounts/[^/]+)#', $name, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private static function first(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = self::str($payload, $key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
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
