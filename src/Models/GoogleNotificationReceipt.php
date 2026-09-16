<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Khadikul\GooglePlaces\Models\Concerns\UsesPackageConnection;

/**
 * A ledger of Pub/Sub message IDs that have already been accepted.
 *
 * Pub/Sub guarantees at-least-once delivery, so the same message can arrive
 * several times, including concurrently. The unique index on message_id is the
 * real guard: claim() lets the database arbitrate rather than relying on a
 * read-then-write that two workers could interleave.
 *
 * @property int $id
 * @property string $message_id
 * @property ?string $notification_type
 * @property ?string $location_name
 * @property ?string $review_name
 */
class GoogleNotificationReceipt extends Model
{
    use UsesPackageConnection;

    protected $table = null;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected function packageTableKey(): string
    {
        return 'notifications';
    }

    /**
     * Record this message ID, returning false when it has been seen before.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function claim(string $messageId, array $attributes = []): bool
    {
        try {
            static::query()->create(array_merge($attributes, [
                'message_id' => $messageId,
                'received_at' => now(),
            ]));

            return true;
        } catch (QueryException $e) {
            if (static::isUniqueViolation($e)) {
                return false;
            }

            throw $e;
        }
    }

    public static function markProcessed(string $messageId): void
    {
        static::query()
            ->where('message_id', $messageId)
            ->update(['processed_at' => now()]);
    }

    /**
     * Drop receipts older than the retention window so the table stays small.
     */
    public static function prune(int $days): int
    {
        return static::query()
            ->where('received_at', '<', now()->subDays(max(1, $days)))
            ->delete();
    }

    /**
     * Unique-constraint detection across the drivers Laravel supports.
     */
    protected static function isUniqueViolation(QueryException $e): bool
    {
        // 23000/23505 are the SQL state codes for integrity constraint violations.
        if (in_array($e->getCode(), ['23000', '23505'], true)) {
            return true;
        }

        $message = strtolower($e->getMessage());

        foreach (['unique constraint', 'duplicate entry', 'unique violation', 'duplicate key'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
