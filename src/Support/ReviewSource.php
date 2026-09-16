<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Support;

/**
 * Which Google surface a review came from.
 *
 * The distinction matters: the public Places API returns at most a handful of
 * Google-selected reviews for any place, while a connected Business Profile
 * returns every review of the locations the authenticated user manages.
 */
enum ReviewSource: string
{
    case Places = 'places';
    case BusinessProfile = 'business_profile';

    public function label(): string
    {
        return match ($this) {
            self::Places => 'Places API (public)',
            self::BusinessProfile => 'Business Profile (connected)',
        };
    }
}
