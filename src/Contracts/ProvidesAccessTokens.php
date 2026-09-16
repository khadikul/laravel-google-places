<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Contracts;

use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;

/**
 * Supplies a usable access token for a stored connection, refreshing it first
 * if necessary.
 *
 * Kept as an interface so the Business Profile client never depends on the
 * OAuth service directly, and so applications with an existing token store can
 * substitute their own implementation.
 */
interface ProvidesAccessTokens
{
    /**
     * @throws \Khadikul\GooglePlaces\Exceptions\TokenExpiredException
     * @throws \Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException
     */
    public function accessToken(GoogleBusinessConnection $connection): string;
}
