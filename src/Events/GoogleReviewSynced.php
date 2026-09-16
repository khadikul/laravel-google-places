<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Khadikul\GooglePlaces\Data\Review;
use Khadikul\GooglePlaces\Models\GoogleReview;

/**
 * Fired after a review has been written to the local database.
 *
 * $wasRecentlyCreated distinguishes a genuinely new review from an update or a
 * redelivered notification.
 */
class GoogleReviewSynced
{
    use Dispatchable;

    public function __construct(
        public readonly GoogleReview $model,
        public readonly Review $review,
        public readonly bool $wasRecentlyCreated,
    ) {}
}
