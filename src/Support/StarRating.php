<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Support;

/**
 * Translates the Business Profile v4 StarRating enum to and from integers.
 *
 * STAR_RATING_UNSPECIFIED maps to null rather than to zero, so an unset rating
 * is never mistaken for a legitimate score.
 */
final class StarRating
{
    private const MAP = [
        'ONE' => 1,
        'TWO' => 2,
        'THREE' => 3,
        'FOUR' => 4,
        'FIVE' => 5,
    ];

    public static function toInt(?string $rating): ?int
    {
        if ($rating === null) {
            return null;
        }

        return self::MAP[strtoupper($rating)] ?? null;
    }

    public static function fromInt(?int $rating): ?string
    {
        if ($rating === null) {
            return null;
        }

        $flipped = array_flip(self::MAP);

        return $flipped[$rating] ?? null;
    }
}
