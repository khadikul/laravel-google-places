<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * The stored OAuth credential can no longer be used and the end user has to
 * reconnect their Google Business Profile.
 *
 * Thrown when Google answers a refresh attempt with invalid_grant, which covers
 * an expired, revoked or otherwise invalidated refresh token.
 */
class TokenExpiredException extends GooglePlacesException
{
    public static function refreshFailed(?string $reason = null): self
    {
        return new self(trim(
            'The stored Google refresh token is no longer valid, so the connection must be '
            .'re-authorised. '.($reason !== null ? '('.$reason.')' : '')
        ));
    }

    public static function noRefreshToken(): self
    {
        return new self(
            'The access token has expired and no refresh token is stored for this connection. '
            .'Reconnect using the OAuth flow with access_type=offline and prompt=consent.'
        );
    }
}
