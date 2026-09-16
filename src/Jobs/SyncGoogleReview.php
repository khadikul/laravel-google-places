<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Exceptions\NotFoundException;
use Khadikul\GooglePlaces\Exceptions\TokenExpiredException;
use Khadikul\GooglePlaces\Models\GoogleNotificationReceipt;
use Khadikul\GooglePlaces\Services\ReviewSyncService;
use Throwable;

/**
 * Fetches one review from Google and writes it to the local database.
 *
 * Dispatched by the webhook so the HTTP response to Pub/Sub stays fast, and
 * usable on its own for targeted re-syncs.
 *
 * Safe to run more than once: the write upserts on the Google review name, and
 * ShouldBeUnique collapses the burst of duplicate deliveries Pub/Sub can
 * produce for a single review.
 */
class SyncGoogleReview implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Seconds after which the uniqueness lock is released regardless. */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $locationName,
        public readonly string $reviewName,
        public readonly ?string $messageId = null,
    ) {
        $this->onConnection(config('google-places.queue.connection'));
        $this->onQueue(config('google-places.queue.queue', 'default'));
    }

    public function handle(ReviewSyncService $sync): void
    {
        try {
            $sync->syncReview($this->locationName, $this->reviewName);
        } catch (NotFoundException|ConnectionNotFoundException $e) {
            // A deleted review, or a location this application no longer
            // synchronises. Neither improves on retry.
            $this->fail($e);

            return;
        } catch (TokenExpiredException $e) {
            // Needs a human to reconnect; retrying just burns attempts.
            $this->fail($e);

            return;
        } catch (ApiException $e) {
            if (! $e->isRetryable()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        if ($this->messageId !== null) {
            GoogleNotificationReceipt::markProcessed($this->messageId);
        }
    }

    public function tries(): int
    {
        return (int) config('google-places.queue.tries', 5);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $backoff = config('google-places.queue.backoff', [10, 30, 120, 300]);

        return array_values(array_filter((array) $backoff, 'is_int')) ?: [10, 30, 120, 300];
    }

    public function timeout(): int
    {
        return (int) config('google-places.queue.timeout', 60);
    }

    /**
     * One job per review, not per delivery.
     */
    public function uniqueId(): string
    {
        return 'google-review:'.sha1($this->reviewName);
    }

    public function failed(Throwable $exception): void
    {
        if (! config('google-places.logging.enabled', true)) {
            return;
        }

        $channel = config('google-places.logging.channel');

        \Illuminate\Support\Facades\Log::channel(is_string($channel) && $channel !== '' ? $channel : null)
            ->error('Failed to synchronise a Google review.', [
                'location' => $this->locationName,
                'review' => $this->reviewName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }
}
