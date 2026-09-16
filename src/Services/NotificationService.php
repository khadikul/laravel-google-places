<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Khadikul\GooglePlaces\Clients\BusinessProfileApiClient;
use Khadikul\GooglePlaces\Exceptions\InvalidConfigurationException;
use Khadikul\GooglePlaces\Models\GoogleBusinessConnection;

/**
 * Subscribes a Business Profile account to real-time notifications on the
 * application owner's own Pub/Sub topic.
 *
 * This package cannot create Google Cloud resources for you. The topic, the
 * push subscription and the IAM grant to Google's publisher service account all
 * have to exist in your project first: run `php artisan google-places:setup`
 * for the exact commands. What this service does is the one API call that tells
 * Google which topic to publish to.
 */
class NotificationService
{
    /**
     * The Google-owned service account that publishes Business Profile
     * notifications. It needs roles/pubsub.publisher on your topic.
     */
    public const PUBLISHER_SERVICE_ACCOUNT = 'mybusiness-api-pubsub@system.gserviceaccount.com';

    /**
     * Review-related notification types this package acts on.
     *
     * @var list<string>
     */
    public const REVIEW_TYPES = ['NEW_REVIEW', 'UPDATED_REVIEW'];

    /**
     * Every type Google currently documents, for validation.
     *
     * @var list<string>
     */
    public const SUPPORTED_TYPES = [
        'GOOGLE_UPDATE',
        'NEW_REVIEW',
        'UPDATED_REVIEW',
        'NEW_CUSTOMER_MEDIA',
        'DUPLICATE_LOCATION',
        'VOICE_OF_MERCHANT_UPDATED',
    ];

    public function __construct(
        protected BusinessProfileApiClient $client,
        protected OAuthService $oauth,
        protected Config $config,
    ) {}

    /**
     * Point a Business Profile account at your Pub/Sub topic.
     *
     * @param  string  $accountId  Either "123" or "accounts/123".
     * @param  list<string>|null  $types
     * @return array<string, mixed> The notification setting Google stored.
     *
     * @throws InvalidConfigurationException
     */
    public function subscribe(
        string $accountId,
        ?array $types = null,
        ?string $pubsubTopic = null,
        int|string|GoogleBusinessConnection|null $connection = null,
    ): array {
        $connection = $this->oauth->connection($connection);
        $topic = $pubsubTopic ?? $this->topic();
        $accountName = $this->qualifyAccount($accountId);

        $setting = $this->client->updateNotificationSetting(
            $connection,
            $accountName,
            $topic,
            $this->validateTypes($types ?? $this->configuredTypes()),
        );

        $connection->forceFill([
            'notifications_subscribed_at' => now(),
            'pubsub_topic' => $topic,
            'google_account_name' => $connection->google_account_name ?? $accountName,
        ])->save();

        return $setting;
    }

    /**
     * Stop Google publishing notifications for this account.
     *
     * Google exposes no delete method here, so the setting is patched to an
     * empty topic and an empty type list.
     *
     * @return array<string, mixed>
     */
    public function unsubscribe(
        string $accountId,
        int|string|GoogleBusinessConnection|null $connection = null,
    ): array {
        $connection = $this->oauth->connection($connection);

        $setting = $this->client->updateNotificationSetting(
            $connection,
            $this->qualifyAccount($accountId),
            null,
            [],
        );

        $connection->forceFill([
            'notifications_subscribed_at' => null,
            'pubsub_topic' => null,
        ])->save();

        return $setting;
    }

    /**
     * Read back what Google currently has configured for this account.
     *
     * @return array<string, mixed>
     */
    public function setting(
        string $accountId,
        int|string|GoogleBusinessConnection|null $connection = null,
    ): array {
        return $this->client->getNotificationSetting(
            $this->oauth->connection($connection),
            $this->qualifyAccount($accountId),
        );
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('google-places.notifications.enabled', false);
    }

    /**
     * The gcloud commands the developer has to run in their own project.
     *
     * @return list<string>
     */
    public function setupCommands(?string $topic = null, ?string $endpoint = null): array
    {
        $topic ??= (string) $this->config->get('google-places.notifications.pubsub_topic', 'projects/YOUR_PROJECT/topics/YOUR_TOPIC');
        $shortTopic = str_contains($topic, '/topics/') ? substr($topic, (int) strrpos($topic, '/') + 1) : $topic;
        $endpoint ??= 'https://your-app.example.com/'.ltrim((string) $this->config->get('google-places.notifications.route', 'google-places/webhook'), '/');

        return [
            sprintf('gcloud pubsub topics create %s', $shortTopic),
            sprintf(
                'gcloud pubsub topics add-iam-policy-binding %s --member="serviceAccount:%s" --role="roles/pubsub.publisher"',
                $shortTopic,
                self::PUBLISHER_SERVICE_ACCOUNT,
            ),
            sprintf(
                'gcloud pubsub subscriptions create %s-push --topic=%s --push-endpoint="%s" '
                .'--push-auth-service-account="YOUR_SA@YOUR_PROJECT.iam.gserviceaccount.com" --push-auth-token-audience="%s"',
                $shortTopic,
                $shortTopic,
                $endpoint,
                $endpoint,
            ),
        ];
    }

    /**
     * @param  list<string>  $types
     * @return list<string>
     */
    protected function validateTypes(array $types): array
    {
        $valid = [];

        foreach ($types as $type) {
            if (! is_string($type)) {
                continue;
            }

            $type = strtoupper(trim($type));

            if ($type === '' || in_array($type, $valid, true)) {
                continue;
            }

            if (! in_array($type, self::SUPPORTED_TYPES, true)) {
                throw new InvalidConfigurationException(sprintf(
                    'Unknown notification type [%s]. Google currently supports: %s.',
                    $type,
                    implode(', ', self::SUPPORTED_TYPES),
                ));
            }

            $valid[] = $type;
        }

        return $valid;
    }

    /**
     * @return list<string>
     */
    protected function configuredTypes(): array
    {
        $types = $this->config->get('google-places.notifications.types', self::REVIEW_TYPES);

        return array_values(array_filter((array) $types, 'is_string'));
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function topic(): string
    {
        $topic = $this->config->get('google-places.notifications.pubsub_topic');

        if (! is_string($topic) || trim($topic) === '') {
            throw InvalidConfigurationException::missingPubSubTopic();
        }

        return $topic;
    }

    protected function qualifyAccount(string $accountId): string
    {
        return str_starts_with($accountId, 'accounts/') ? $accountId : 'accounts/'.$accountId;
    }
}
