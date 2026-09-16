<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Data;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Khadikul\GooglePlaces\Support\ReviewSource;
use Khadikul\GooglePlaces\Support\StarRating;

/**
 * A single Google review, normalised across the two very different shapes
 * Google returns it in.
 *
 * Places API (New) reviews and Business Profile v4 reviews share almost no
 * field names, so both are mapped onto this one DTO. Every field except the
 * identifier and the rating is optional, because Google omits anything the
 * reviewer did not provide (anonymous reviewers have no name or photo, and a
 * star-only review has no text at all).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Review implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $reviewName  Stable Google resource name, unique across all of Google.
     * @param  array<string, mixed>  $raw  The untouched Google payload.
     */
    public function __construct(
        public string $reviewName,
        public ?string $authorName = null,
        public ?string $authorUri = null,
        public ?string $authorPhotoUri = null,
        public ?int $rating = null,
        public ?string $text = null,
        public ?string $languageCode = null,
        public ?DateTimeImmutable $publishedAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?string $relativePublishTime = null,
        public ?string $replyText = null,
        public ?DateTimeImmutable $repliedAt = null,
        public ?string $googleMapsUri = null,
        public ReviewSource $source = ReviewSource::Places,
        public ?string $placeId = null,
        public ?string $locationName = null,
        public array $raw = [],
    ) {}

    /**
     * Build from a Places API (New) review object.
     *
     * Shape (all optional except name):
     *   {
     *     "name": "places/ChIJ.../reviews/ChdD...",
     *     "relativePublishTimeDescription": "2 months ago",
     *     "rating": 5,
     *     "text": {"text": "...", "languageCode": "en"},
     *     "originalText": {...},
     *     "authorAttribution": {"displayName": "...", "uri": "...", "photoUri": "..."},
     *     "publishTime": "2024-01-02T03:04:05Z",
     *     "googleMapsUri": "https://..."
     *   }
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPlacesApi(array $payload, ?string $placeId = null): self
    {
        $author = is_array($payload['authorAttribution'] ?? null) ? $payload['authorAttribution'] : [];

        $name = self::str($payload, 'name')
            ?? self::syntheticName($placeId, $author['displayName'] ?? null, $payload['publishTime'] ?? null);

        return new self(
            reviewName: $name,
            authorName: self::str($author, 'displayName'),
            authorUri: self::str($author, 'uri'),
            authorPhotoUri: self::str($author, 'photoUri'),
            rating: self::int($payload, 'rating'),
            text: self::localizedText($payload['text'] ?? null)
                ?? self::localizedText($payload['originalText'] ?? null),
            languageCode: self::localizedLanguage($payload['text'] ?? null)
                ?? self::localizedLanguage($payload['originalText'] ?? null),
            publishedAt: self::time($payload, 'publishTime'),
            updatedAt: self::time($payload, 'updateTime'),
            relativePublishTime: self::str($payload, 'relativePublishTimeDescription'),
            googleMapsUri: self::str($payload, 'googleMapsUri'),
            source: ReviewSource::Places,
            placeId: $placeId ?? self::placeIdFromReviewName($name),
            raw: $payload,
        );
    }

    /**
     * Build from a Business Profile v4 review object.
     *
     * Shape:
     *   {
     *     "name": "accounts/1/locations/2/reviews/3",
     *     "reviewId": "3",
     *     "reviewer": {"displayName": "...", "profilePhotoUrl": "...", "isAnonymous": false},
     *     "starRating": "FIVE",
     *     "comment": "...",
     *     "createTime": "2024-01-02T03:04:05Z",
     *     "updateTime": "2024-01-02T03:04:05Z",
     *     "reviewReply": {"comment": "...", "updateTime": "..."}
     *   }
     *
     * Note that v4 list responses omit "name"; only "reviewId" is present. The
     * parent location name is therefore required to rebuild a stable identifier.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromBusinessProfile(array $payload, string $locationName, ?string $placeId = null): self
    {
        $reviewer = is_array($payload['reviewer'] ?? null) ? $payload['reviewer'] : [];
        $reply = is_array($payload['reviewReply'] ?? null) ? $payload['reviewReply'] : [];

        $name = self::str($payload, 'name');

        if ($name === null) {
            $reviewId = self::str($payload, 'reviewId') ?? self::syntheticName(
                $locationName,
                $reviewer['displayName'] ?? null,
                $payload['createTime'] ?? null
            );

            $name = rtrim($locationName, '/').'/reviews/'.$reviewId;
        }

        $anonymous = (bool) ($reviewer['isAnonymous'] ?? false);

        return new self(
            reviewName: $name,
            authorName: $anonymous ? null : self::str($reviewer, 'displayName'),
            authorUri: null,
            authorPhotoUri: $anonymous ? null : self::str($reviewer, 'profilePhotoUrl'),
            rating: StarRating::toInt(self::str($payload, 'starRating')),
            text: self::str($payload, 'comment'),
            languageCode: null,
            publishedAt: self::time($payload, 'createTime'),
            updatedAt: self::time($payload, 'updateTime'),
            relativePublishTime: null,
            replyText: self::str($reply, 'comment'),
            repliedAt: self::time($reply, 'updateTime'),
            googleMapsUri: null,
            source: ReviewSource::BusinessProfile,
            placeId: $placeId,
            locationName: $locationName,
            raw: $payload,
        );
    }

    public function authorName(): ?string
    {
        return $this->authorName;
    }

    public function rating(): ?int
    {
        return $this->rating;
    }

    public function text(): ?string
    {
        return $this->text;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function hasText(): bool
    {
        return $this->text !== null && trim($this->text) !== '';
    }

    public function hasReply(): bool
    {
        return $this->replyText !== null && trim($this->replyText) !== '';
    }

    /**
     * A copy of this review tagged with a place ID, used once a Business
     * Profile location has been matched to a Places place ID.
     */
    public function withPlaceId(?string $placeId): self
    {
        if ($placeId === $this->placeId) {
            return $this;
        }

        return new self(
            reviewName: $this->reviewName,
            authorName: $this->authorName,
            authorUri: $this->authorUri,
            authorPhotoUri: $this->authorPhotoUri,
            rating: $this->rating,
            text: $this->text,
            languageCode: $this->languageCode,
            publishedAt: $this->publishedAt,
            updatedAt: $this->updatedAt,
            relativePublishTime: $this->relativePublishTime,
            replyText: $this->replyText,
            repliedAt: $this->repliedAt,
            googleMapsUri: $this->googleMapsUri,
            source: $this->source,
            placeId: $placeId,
            locationName: $this->locationName,
            raw: $this->raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'review_name' => $this->reviewName,
            'author_name' => $this->authorName,
            'author_uri' => $this->authorUri,
            'author_photo_uri' => $this->authorPhotoUri,
            'rating' => $this->rating,
            'text' => $this->text,
            'language_code' => $this->languageCode,
            'publish_time' => $this->publishedAt?->format(DateTimeInterface::ATOM),
            'update_time' => $this->updatedAt?->format(DateTimeInterface::ATOM),
            'relative_publish_time' => $this->relativePublishTime,
            'reply_text' => $this->replyText,
            'replied_at' => $this->repliedAt?->format(DateTimeInterface::ATOM),
            'google_maps_uri' => $this->googleMapsUri,
            'source' => $this->source->value,
            'place_id' => $this->placeId,
            'location_name' => $this->locationName,
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
     * Places review names look like "places/{placeId}/reviews/{reviewId}".
     */
    private static function placeIdFromReviewName(string $name): ?string
    {
        if (preg_match('#^places/([^/]+)/reviews/#', $name, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Deterministic fallback identifier for payloads with no usable name, so
     * repeated syncs of the same review still collapse onto one row.
     */
    private static function syntheticName(?string $scope, mixed $author, mixed $publishedAt): string
    {
        return 'synthetic/'.sha1(implode('|', [
            is_string($scope) ? $scope : '',
            is_string($author) ? $author : '',
            is_string($publishedAt) ? $publishedAt : '',
        ]));
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function str(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function int(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) || is_float($value) ? (int) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function time(array $payload, string $key): ?DateTimeImmutable
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private static function localizedText(mixed $value): ?string
    {
        if (is_array($value) && is_string($value['text'] ?? null) && $value['text'] !== '') {
            return $value['text'];
        }

        // Older responses expose the text as a bare string.
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function localizedLanguage(mixed $value): ?string
    {
        if (is_array($value) && is_string($value['languageCode'] ?? null) && $value['languageCode'] !== '') {
            return $value['languageCode'];
        }

        return null;
    }
}
