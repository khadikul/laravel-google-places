<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Support;

/**
 * Builds X-Goog-FieldMask header values for the Places API (New).
 *
 * The API rejects any request without a field mask and bills according to the
 * most expensive field requested, so masks are normalised (de-duplicated,
 * trimmed, order preserved) rather than passed through blindly.
 */
final class FieldMask
{
    /**
     * @param  list<string>  $fields
     */
    public static function build(array $fields): string
    {
        return implode(',', self::normalise($fields));
    }

    /**
     * Search responses nest places under a "places" key, so every field in the
     * mask needs that prefix. "nextPageToken" is a sibling of "places" and is
     * therefore never prefixed.
     *
     * @param  list<string>  $fields
     */
    public static function forSearch(array $fields, bool $withPageToken = true): string
    {
        $prefixed = [];

        foreach (self::normalise($fields) as $field) {
            if ($field === 'nextPageToken' || str_starts_with($field, 'places.')) {
                $prefixed[] = $field;

                continue;
            }

            $prefixed[] = 'places.'.$field;
        }

        if ($withPageToken && ! in_array('nextPageToken', $prefixed, true)) {
            $prefixed[] = 'nextPageToken';
        }

        return implode(',', $prefixed);
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    public static function normalise(array $fields): array
    {
        $normalised = [];

        foreach ($fields as $field) {
            if (! is_string($field)) {
                continue;
            }

            $field = trim($field);

            if ($field === '' || in_array($field, $normalised, true)) {
                continue;
            }

            $normalised[] = $field;
        }

        return $normalised;
    }

    /**
     * A stable fingerprint of a mask, used in cache keys so that widening the
     * mask does not serve a narrower cached response.
     *
     * @param  list<string>  $fields
     */
    public static function fingerprint(array $fields): string
    {
        $normalised = self::normalise($fields);
        sort($normalised);

        return substr(sha1(implode(',', $normalised)), 0, 12);
    }
}
