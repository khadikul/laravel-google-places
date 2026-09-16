<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Console;

use Illuminate\Console\Command;
use Khadikul\GooglePlaces\Models\GoogleNotificationReceipt;

/**
 * Trims the deduplication ledger.
 *
 * Retention has to outlive Pub/Sub's redelivery window, so the default is seven
 * days rather than something tighter.
 */
class PruneNotificationsCommand extends Command
{
    protected $signature = 'google-places:prune-notifications
                            {--days= : Override the configured retention window}';

    protected $description = 'Delete Pub/Sub notification receipts older than the retention window';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('google-places.notifications.deduplicate_for_days', 7);

        $deleted = GoogleNotificationReceipt::prune($days);

        $this->components->info(sprintf(
            'Deleted %d notification receipt(s) older than %d day(s).',
            $deleted,
            max(1, $days),
        ));

        return self::SUCCESS;
    }
}
