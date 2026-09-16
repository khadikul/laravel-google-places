<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use JsonException;
use Khadikul\GooglePlaces\Exceptions\ApiException;
use Khadikul\GooglePlaces\Exceptions\AuthenticationException;
use Khadikul\GooglePlaces\Exceptions\AuthorizationException;
use Khadikul\GooglePlaces\Exceptions\ConflictException;
use Khadikul\GooglePlaces\Exceptions\GooglePlacesException;
use Khadikul\GooglePlaces\Exceptions\NotFoundException;
use Khadikul\GooglePlaces\Exceptions\RateLimitException;
use Throwable;

/**
 * Turns a Google JSON error response into the right package exception.
 *
 * Google's error envelope is consistent across all the APIs used here:
 *   {"error": {"code": 403, "message": "...", "status": "PERMISSION_DENIED",
 *              "details": [...]}}
 */
trait HandlesGoogleResponses
{
    /**
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    protected function decode(Response $response, string $operation): array
    {
        if ($response->failed()) {
            throw $this->mapError($response, $operation);
        }

        $body = $response->body();

        // 204 and empty 200s are legitimate for PATCH-style calls.
        if (trim($body) === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ApiException(
                sprintf('%s returned a response that is not valid JSON: %s', $operation, $e->getMessage()),
                $response->status(),
                null,
                $e,
            );
        }

        if (! is_array($decoded)) {
            throw new ApiException(
                sprintf('%s returned an unexpected JSON structure.', $operation),
                $response->status(),
            );
        }

        return $decoded;
    }

    protected function mapError(Response $response, string $operation): ApiException
    {
        $status = $response->status();
        [$message, $reason] = $this->extractError($response);

        $summary = sprintf('%s failed with HTTP %d: %s', $operation, $status, $message);

        return match (true) {
            $status === 401 => new AuthenticationException(
                $summary.' Check that the credential is valid and has not been revoked.',
                $status,
                $reason,
            ),
            $status === 403 => new AuthorizationException(
                $summary.' Check that the API is enabled on your Google Cloud project, that your '
                .'key or token has access to it, and that any key restrictions permit this request.',
                $status,
                $reason,
            ),
            $status === 404 => new NotFoundException($summary, $status, $reason),
            $status === 409 => new ConflictException($summary, $status, $reason),
            $status === 429 => new RateLimitException(
                $summary.' You have exhausted the quota on your own Google Cloud project.',
                $status,
                $reason,
                $this->retryAfter($response),
            ),
            default => new ApiException($summary, $status, $reason),
        };
    }

    /**
     * Wraps transport failures so callers only ever have to catch package
     * exceptions, never Guzzle or Illuminate ones.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws ApiException
     */
    protected function guardTransport(callable $callback, string $operation)
    {
        try {
            return $callback();
        } catch (ConnectionException $e) {
            throw new ApiException(
                sprintf('%s could not reach Google: %s', $operation, $e->getMessage()),
                0,
                'CONNECTION_FAILED',
                $e,
            );
        } catch (GooglePlacesException $e) {
            // Already one of ours (a missing API key, an expired token): passing
            // it through keeps the specific type the caller is catching.
            throw $e;
        } catch (Throwable $e) {
            throw new ApiException(
                sprintf('%s failed unexpectedly: %s', $operation, $e->getMessage()),
                0,
                null,
                $e,
            );
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function extractError(Response $response): array
    {
        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (is_array($decoded)) {
            $error = $decoded['error'] ?? null;

            if (is_array($error)) {
                $message = is_string($error['message'] ?? null) ? $error['message'] : null;
                $status = is_string($error['status'] ?? null) ? $error['status'] : null;

                if ($message !== null) {
                    return [$message, $status];
                }
            }

            // The OAuth token endpoint uses a different, flatter envelope.
            if (is_string($decoded['error'] ?? null)) {
                $description = is_string($decoded['error_description'] ?? null)
                    ? $decoded['error_description']
                    : $decoded['error'];

                return [$description, $decoded['error']];
            }
        }

        return [$this->reasonPhrase($response->status()), null];
    }

    private function reasonPhrase(int $status): string
    {
        return match ($status) {
            400 => 'the request was rejected as invalid.',
            401 => 'the credential was not accepted.',
            403 => 'access to this resource was denied.',
            404 => 'the resource was not found.',
            409 => 'the request conflicted with the current state.',
            429 => 'the rate limit or quota was exceeded.',
            500 => 'Google reported an internal error.',
            502 => 'Google returned a bad gateway.',
            503 => 'the service is temporarily unavailable.',
            504 => 'the request timed out inside Google.',
            default => 'no further detail was provided.',
        };
    }

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        if ($header === '' || ! ctype_digit($header)) {
            return null;
        }

        return (int) $header;
    }
}
