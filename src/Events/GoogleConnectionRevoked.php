<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;

/**
 * Fired when Google rejects the stored refresh token, or the application
 * disconnects deliberately. Listen for this to prompt the user to reconnect.
 */
class GoogleConnectionRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly GoogleBusinessConnection $connection,
        public readonly ?string $reason = null,
    ) {}
}
