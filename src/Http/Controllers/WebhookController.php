<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Khadikul\GooglePlaces\Data\NotificationPayload;
use Khadikul\GooglePlaces\Exceptions\WebhookException;
use Khadikul\GooglePlaces\Jobs\SyncGoogleReview;
use Khadikul\GooglePlaces\Models\GoogleNotificationReceipt;

/**
 * Receives Pub/Sub push messages from the application owner's own subscription.
 *
 * This endpoint lives in the application that installed the package. Nothing is
 * forwarded anywhere: the payload is validated, recorded and handed to a queue.
 *
 * Status codes matter to Pub/Sub. 2xx acknowledges the message and stops
 * redelivery; anything else makes Pub/Sub retry with backoff. So a payload that
 * can never succeed is acknowledged, and only genuine server-side faults are
 * signalled with a 500.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected Config $config,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->config->get('google-places.notifications.enabled', false)) {
            return $this->ok('notifications_disabled');
        }

        try {
            $payload = NotificationPayload::fromPushRequest($this->envelope($request));
        } catch (WebhookException $e) {
            $this->log('warning', 'Discarded a malformed Google Pub/Sub notification.', [
                'reason' => $e->getMessage(),
            ]);

            // Malformed for us is malformed forever: acknowledge it rather than
            // letting Pub/Sub redeliver the same broken message for days.
            return $this->ok('invalid_payload');
        }

        // The unique index on message_id arbitrates, so two workers handling the
        // same redelivery cannot both dispatch.
        $claimed = GoogleNotificationReceipt::claim($payload->messageId, [
            'notification_type' => $payload->notificationType,
            'location_name' => $payload->locationName,
            'review_name' => $payload->reviewName,
            'subscription' => $payload->subscription,
            'payload' => $payload->data,
        ]);

        if (! $claimed) {
            return $this->ok('duplicate');
        }

        if (! $payload->isReviewNotification()) {
            // Google sends other types on the same topic; acknowledge and move on.
            return $this->ok('ignored', ['type' => $payload->notificationType]);
        }

        if (! $payload->isActionable()) {
            $this->log('warning', 'Review notification arrived without a usable review or location name.', [
                'type' => $payload->notificationType,
                'message_id' => $payload->messageId,
            ]);

            return $this->ok('incomplete');
        }

        SyncGoogleReview::dispatch(
            (string) $payload->locationName,
            (string) $payload->reviewName,
            $payload->messageId,
        );

        return $this->ok('queued');
    }

    /**
     * @return array<string, mixed>
     */
    protected function envelope(Request $request): array
    {
        $decoded = $request->json()->all();

        if (! is_array($decoded) || $decoded === []) {
            // Not JSON, or an empty body.
            throw WebhookException::invalidPayload('the request body is not a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function ok(string $status, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge(['status' => $status], $extra), 200);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (! $this->config->get('google-places.logging.enabled', true)) {
            return;
        }

        $channel = $this->config->get('google-places.logging.channel');

        Log::channel(is_string($channel) && $channel !== '' ? $channel : null)->{$level}($message, $context);
    }
}
