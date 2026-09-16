<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Console;

use Illuminate\Console\Command;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Jobs\SyncLocationReviews;
use Khadikul\GooglePlaces\Models\GoogleBusinessLocation;
use Khadikul\GooglePlaces\Services\ReviewSyncService;

/**
 * Manual review sync, for the initial backfill and for recovery.
 *
 * Schedule it as a safety net even with notifications enabled: Pub/Sub delivery
 * is reliable but not guaranteed forever, and a nightly reconciliation costs
 * one API call per location.
 */
class SyncReviewsCommand extends Command
{
    protected $signature = 'google-places:sync-reviews
                            {location? : A location resource name; omit to sync every connected location}
                            {--sync : Run inline instead of dispatching to the queue}
                            {--max-pages= : Stop after this many pages of 50 reviews}';

    protected $description = 'Synchronise Google Business Profile reviews into the local database';

    public function handle(ReviewSyncService $sync): int
    {
        $maxPages = $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null;
        $locations = $this->locations();

        if ($locations->isEmpty()) {
            $this->components->warn('No connected locations. Use GooglePlaces::connectLocation() first.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($locations as $location) {
            if (! $this->option('sync')) {
                SyncLocationReviews::dispatch($location->location_name, $maxPages);
                $this->components->twoColumnDetail($location->location_name, '<fg=cyan>queued</>');

                continue;
            }

            try {
                $reviews = $sync->syncLocation($location->location_name, $maxPages);
            } catch (GooglePlacesException $e) {
                $this->components->twoColumnDetail($location->location_name, '<fg=red>failed</>');
                $this->line('    <fg=gray>'.$e->getMessage().'</>');
                $failed++;

                continue;
            }

            $this->components->twoColumnDetail(
                $location->location_name,
                sprintf('<fg=green>%d review(s)</>', $reviews->count()),
            );
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, GoogleBusinessLocation>
     */
    protected function locations(): \Illuminate\Support\Collection
    {
        $argument = $this->argument('location');

        if (is_string($argument) && $argument !== '') {
            return GoogleBusinessLocation::query()
                ->where('location_name', $argument)
                ->orWhere('location_name', 'like', '%'.$argument.'%')
                ->get();
        }

        return GoogleBusinessLocation::query()->syncEnabled()->get();
    }
}
