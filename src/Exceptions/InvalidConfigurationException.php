<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * A required configuration value is missing or malformed.
 *
 * The factory methods below deliberately name the missing key and never echo
 * the value that was found.
 */
class InvalidConfigurationException extends GooglePlacesException
{
    public static function missingApiKey(): self
    {
        return new self(
            'No Google API key configured. Set GOOGLE_PLACES_API_KEY in your .env file. '
            .'This package does not ship with credentials: create your own Google Cloud '
            .'project, enable the Places API (New) and generate your own key.'
        );
    }

    public static function missingOAuthCredentials(): self
    {
        return new self(
            'Google OAuth is not configured. Set GOOGLE_PLACES_CLIENT_ID, '
            .'GOOGLE_PLACES_CLIENT_SECRET and GOOGLE_PLACES_REDIRECT_URI in your .env file '
            .'using an OAuth client you created in your own Google Cloud project.'
        );
    }

    public static function missingPubSubTopic(): self
    {
        return new self(
            'No Pub/Sub topic configured. Set GOOGLE_PLACES_PUBSUB_TOPIC to the fully '
            .'qualified topic you created in your own Google Cloud project, e.g. '
            .'projects/my-project/topics/gbp-notifications'
        );
    }

    public static function missing(string $key, string $hint = ''): self
    {
        return new self(trim(sprintf('Missing configuration value [%s]. %s', $key, $hint)));
    }
}
