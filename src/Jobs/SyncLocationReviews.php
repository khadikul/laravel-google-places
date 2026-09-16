<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Exceptions\ConnectionNotFoundException;
use Khadikul\GooglePlaces\Exceptions\TokenExpiredException;
use Khadikul\GooglePlaces\Services\ReviewSyncService;
use Throwable;

/**
 * Backfills every review for one connected location.
 *
 * Used for the initial sync after a location is connected, and for recovery
 * after downtime. Paginating through a large location takes a while, hence the
 * longer default timeout.
 */
class SyncLocationReviews implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly string $locationName,
        public readonly ?int $maxPages = null,
    ) {
        $this->onConnection(config('google-places.queue.connection'));
        $this->onQueue(config('google-places.queue.queue', 'default'));
    }

    public function handle(ReviewSyncService $sync): void
    {
        try {
            $sync->syncLocation($this->locationName, $this->maxPages);
        } catch (ConnectionNotFoundException|TokenExpiredException $e) {
            $this->fail($e);

            return;
        } catch (ApiException $e) {
            if (! $e->isRetryable()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    public function tries(): int
    {
        return (int) config('google-places.queue.tries', 5);
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
        // A full backfill paginates 50 reviews at a time, so allow more room
        // than a single-review sync.
        return max((int) config('google-places.queue.timeout', 60), 300);
    }

    public function uniqueId(): string
    {
        return 'google-location:'.sha1($this->locationName);
    }

    public function failed(Throwable $exception): void
    {
        if (! config('google-places.logging.enabled', true)) {
            return;
        }

        $channel = config('google-places.logging.channel');

        \Illuminate\Support\Facades\Log::channel(is_string($channel) && $channel !== '' ? $channel : null)
            ->error('Failed to synchronise reviews for a Google location.', [
                'location' => $this->locationName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
    }
}
