<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Khadikul\GooglePlaces\Data\Review;
use Khadikul\GooglePlaces\Models\Concerns\UsesPackageConnection;
use Khadikul\GooglePlaces\Support\ReviewSource;

/**
 * A review stored in the application's own database.
 *
 * Idempotency hinges on review_name: it holds the Google resource name, which
 * is globally unique for both surfaces ("places/X/reviews/Y" and
 * "accounts/A/locations/L/reviews/R"), and carries a unique index. Repeated
 * Pub/Sub deliveries therefore collapse onto one row.
 *
 * @property int $id
 * @property ?string $place_id
 * @property ?string $location_name
 * @property string $review_name
 * @property string $source
 * @property ?string $author_name
 * @property ?int $rating
 * @property ?string $text
 * @property ?\Illuminate\Support\Carbon $publish_time
 * @property ?array<string, mixed> $raw_data
 */
class GoogleReview extends Model
{
    use UsesPackageConnection;

    protected $table = null;

    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
        'publish_time' => 'datetime',
        'update_time' => 'datetime',
        'replied_at' => 'datetime',
        'raw_data' => 'array',
    ];

    protected function packageTableKey(): string
    {
        return 'reviews';
    }

    /**
     * @return BelongsTo<GooglePlace, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(GooglePlace::class, 'place_id', 'place_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForPlace(Builder $query, string $placeId): Builder
    {
        return $query->where('place_id', $placeId);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForLocation(Builder $query, string $locationName): Builder
    {
        return $query->where('location_name', $locationName);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFromSource(Builder $query, ReviewSource $source): Builder
    {
        return $query->where('source', $source->value);
    }

    /**
     * Insert or update the row for this review, keyed on the Google resource
     * name so that duplicate notifications never create a second row.
     */
    public static function storeFrom(Review $review): self
    {
        $attributes = [
            'place_id' => $review->placeId,
            'location_name' => $review->locationName,
            'source' => $review->source->value,
            'author_name' => $review->authorName,
            'author_uri' => $review->authorUri,
            'author_photo_uri' => $review->authorPhotoUri,
            'rating' => $review->rating,
            'text' => $review->text,
            'language_code' => $review->languageCode,
            'publish_time' => $review->publishedAt,
            'update_time' => $review->updatedAt,
            'relative_publish_time' => $review->relativePublishTime,
            'reply_text' => $review->replyText,
            'replied_at' => $review->repliedAt,
            'google_maps_uri' => $review->googleMapsUri,
            'raw_data' => $review->raw,
        ];

        // A narrower payload must not blank out columns a richer sync filled in.
        $existing = static::query()->where('review_name', $review->reviewName)->first();

        if ($existing !== null) {
            foreach ($attributes as $column => $value) {
                if ($value === null && $existing->getAttribute($column) !== null) {
                    unset($attributes[$column]);
                }
            }
        }

        /** @var self $model */
        $model = static::query()->updateOrCreate(
            ['review_name' => $review->reviewName],
            $attributes,
        );

        return $model;
    }

    /**
     * Rebuild the DTO from the stored row.
     */
    public function toData(): Review
    {
        $raw = is_array($this->raw_data) ? $this->raw_data : [];

        return new Review(
            reviewName: (string) $this->review_name,
            authorName: $this->author_name,
            authorUri: $this->author_uri,
            authorPhotoUri: $this->author_photo_uri,
            rating: $this->rating,
            text: $this->text,
            languageCode: $this->language_code,
            publishedAt: $this->publish_time?->toDateTimeImmutable(),
            updatedAt: $this->update_time?->toDateTimeImmutable(),
            relativePublishTime: $this->relative_publish_time,
            replyText: $this->reply_text,
            repliedAt: $this->replied_at?->toDateTimeImmutable(),
            googleMapsUri: $this->google_maps_uri,
            source: ReviewSource::tryFrom((string) $this->source) ?? ReviewSource::Places,
            placeId: $this->place_id,
            locationName: $this->location_name,
            raw: $raw,
        );
    }
}
