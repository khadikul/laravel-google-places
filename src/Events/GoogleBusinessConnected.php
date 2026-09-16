<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;

/**
 * Fired once the OAuth callback has stored a working connection.
 *
 * The model hides its token columns, so listeners and any log of this event
 * cannot leak credentials.
 */
class GoogleBusinessConnected
{
    use Dispatchable;

    public function __construct(
        public readonly GoogleBusinessConnection $connection,
    ) {}
}
