<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Your Google API key
    |--------------------------------------------------------------------------
    |
    | This package ships with NO credentials. Create your own Google Cloud
    | project, enable the "Places API (New)" and generate an API key that is
    | restricted to that API. The key never leaves your application.
    |
    */

    'api_key' => env('GOOGLE_PLACES_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Places API (New) endpoint
    |--------------------------------------------------------------------------
    */

    'base_url' => env('GOOGLE_PLACES_BASE_URL', 'https://places.googleapis.com/v1'),

    'timeout' => (int) env('GOOGLE_PLACES_TIMEOUT', 10),

    'connect_timeout' => (int) env('GOOGLE_PLACES_CONNECT_TIMEOUT', 5),

    'retry' => [
        'times' => (int) env('GOOGLE_PLACES_RETRY_TIMES', 2),
        'sleep' => (int) env('GOOGLE_PLACES_RETRY_SLEEP', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localisation
    |--------------------------------------------------------------------------
    |
    | Applied to Places API requests when the caller does not override them.
    | "region_code" is a two character CLDR code, e.g. "BD", "US", "GB".
    |
    */

    'language_code' => env('GOOGLE_PLACES_LANGUAGE_CODE'),

    'region_code' => env('GOOGLE_PLACES_REGION_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Field masks
    |--------------------------------------------------------------------------
    |
    | The Places API (New) REQUIRES a field mask and bills per SKU based on the
    | fields you request. Keep these lists as small as your UI allows.
    |
    | "rating" and "userRatingCount" are Enterprise SKU. "reviews" is
    | Enterprise + Atmosphere SKU and is therefore the most expensive field.
    | See the README section on billing before widening these lists.
    |
    */

    'fields' => [

        // Used by GooglePlaces::search(). The "places." prefix is added for you.
        'search' => [
            'id',
            'displayName',
            'formattedAddress',
            'location',
            'rating',
            'userRatingCount',
            'googleMapsUri',
            'businessStatus',
            'primaryTypeDisplayName',
        ],

        // Used by GooglePlaces::place().
        'details' => [
            'id',
            'displayName',
            'formattedAddress',
            'location',
            'rating',
            'userRatingCount',
            'googleMapsUri',
            'websiteUri',
            'nationalPhoneNumber',
            'internationalPhoneNumber',
            'businessStatus',
            'primaryTypeDisplayName',
            'reviews',
            'photos',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Place lookups and search results are cached in YOUR cache store. Review
    | caches are invalidated automatically whenever a review is synchronised.
    |
    */

    'cache' => [
        'enabled' => (bool) env('GOOGLE_PLACES_CACHE_ENABLED', true),
        'store' => env('GOOGLE_PLACES_CACHE_STORE'),
        'ttl' => (int) env('GOOGLE_PLACES_CACHE_TTL', 3600),
        'prefix' => env('GOOGLE_PLACES_CACHE_PREFIX', 'google-places'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth 2.0 (Mode B only)
    |--------------------------------------------------------------------------
    |
    | Required only when you want to connect a Google Business Profile and
    | synchronise owner reviews. These are YOUR OAuth client credentials from
    | YOUR Google Cloud project. Tokens are encrypted with your APP_KEY and
    | stored in your database; they are never transmitted anywhere except to
    | Google.
    |
    */

    'oauth' => [
        'client_id' => env('GOOGLE_PLACES_CLIENT_ID'),
        'client_secret' => env('GOOGLE_PLACES_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_PLACES_REDIRECT_URI'),

        'scopes' => [
            'https://www.googleapis.com/auth/business.manage',
        ],

        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'revoke_url' => 'https://oauth2.googleapis.com/revoke',
        'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',

        // Refresh the access token this many seconds before it actually expires.
        'leeway' => (int) env('GOOGLE_PLACES_TOKEN_LEEWAY', 60),

        'routes' => [
            'enabled' => (bool) env('GOOGLE_PLACES_OAUTH_ROUTES_ENABLED', true),
            'prefix' => 'google-places/oauth',
            'middleware' => ['web'],

            // Where to send the browser after a successful / failed connection.
            'success_redirect' => env('GOOGLE_PLACES_OAUTH_SUCCESS_REDIRECT', '/'),
            'failure_redirect' => env('GOOGLE_PLACES_OAUTH_FAILURE_REDIRECT', '/'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Business Profile API hosts
    |--------------------------------------------------------------------------
    |
    | Reviews have NOT been migrated off the legacy v4 host by Google, so the
    | Business Profile surface is split across several hosts. Each of these must
    | be enabled in YOUR Google Cloud project.
    |
    */

    'business_profile' => [
        'account_management_url' => 'https://mybusinessaccountmanagement.googleapis.com/v1',
        'business_information_url' => 'https://mybusinessbusinessinformation.googleapis.com/v1',
        'notifications_url' => 'https://mybusinessnotifications.googleapis.com/v1',
        'reviews_url' => 'https://mybusiness.googleapis.com/v4',

        'timeout' => (int) env('GOOGLE_BUSINESS_TIMEOUT', 15),
        'connect_timeout' => (int) env('GOOGLE_BUSINESS_CONNECT_TIMEOUT', 5),

        // Required readMask when listing locations.
        'location_read_mask' => [
            'name',
            'title',
            'storefrontAddress',
            'websiteUri',
            'phoneNumbers',
            'metadata',
            'storeCode',
            'languageCode',
        ],

        // Capped at 50 by Google.
        'reviews_page_size' => (int) env('GOOGLE_BUSINESS_REVIEWS_PAGE_SIZE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time notifications (Mode B only)
    |--------------------------------------------------------------------------
    |
    | The webhook lives inside YOUR application and receives Pub/Sub push
    | messages from YOUR Google Cloud project. No traffic is routed through any
    | infrastructure owned by the package author.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('GOOGLE_PLACES_NOTIFICATIONS_ENABLED', false),

        'route' => env('GOOGLE_PLACES_WEBHOOK_ROUTE', 'google-places/webhook'),
        'middleware' => ['api'],

        // Notification types requested from Google when subscribing.
        'types' => ['NEW_REVIEW', 'UPDATED_REVIEW'],

        // Fully qualified Pub/Sub topic you created, e.g.
        // projects/my-project/topics/gbp-notifications
        'pubsub_topic' => env('GOOGLE_PLACES_PUBSUB_TOPIC'),

        /*
         | How the incoming push request is authenticated. Configure at least one
         | in production; leaving both disabled allows anonymous POSTs.
         |
         |  - oidc:  Pub/Sub signs each push with an OIDC token. This is the
         |           recommended option. Set "audience" to the same value you
         |           configured on the push subscription (usually the full
         |           webhook URL) and "service_account" to the push subscription
         |           service account e-mail.
         |  - token: A shared secret appended to the push endpoint as
         |           ?token=... which is simpler, but weaker.
         */
        'auth' => [
            'oidc' => [
                'enabled' => (bool) env('GOOGLE_PLACES_WEBHOOK_OIDC_ENABLED', true),
                'audience' => env('GOOGLE_PLACES_WEBHOOK_AUDIENCE'),
                'service_account' => env('GOOGLE_PLACES_WEBHOOK_SERVICE_ACCOUNT'),
                'certs_url' => 'https://www.googleapis.com/oauth2/v3/certs',
                'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
                'leeway' => 60,
            ],

            'token' => [
                'enabled' => (bool) env('GOOGLE_PLACES_WEBHOOK_TOKEN_ENABLED', false),
                'value' => env('GOOGLE_PLACES_WEBHOOK_TOKEN'),
            ],
        ],

        // Pub/Sub guarantees at-least-once delivery. Delivered message IDs are
        // recorded for this many days so replays are ignored.
        'deduplicate_for_days' => (int) env('GOOGLE_PLACES_WEBHOOK_DEDUPE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('GOOGLE_PLACES_QUEUE_CONNECTION'),
        'queue' => env('GOOGLE_PLACES_QUEUE', 'default'),
        'tries' => (int) env('GOOGLE_PLACES_QUEUE_TRIES', 5),
        'timeout' => (int) env('GOOGLE_PLACES_QUEUE_TIMEOUT', 60),
        'backoff' => [10, 30, 120, 300],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Set "migrations" to false if you prefer to publish and edit the migrations
    | yourself, or if you do not need local persistence at all (Mode A only).
    |
    */

    'database' => [
        'connection' => env('GOOGLE_PLACES_DB_CONNECTION'),
        'migrations' => (bool) env('GOOGLE_PLACES_RUN_MIGRATIONS', true),

        'tables' => [
            'places' => 'google_places',
            'reviews' => 'google_reviews',
            'connections' => 'google_business_connections',
            'locations' => 'google_business_locations',
            'notifications' => 'google_notification_receipts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Credentials, tokens and API keys are NEVER written to the log regardless
    | of this setting.
    |
    */

    'logging' => [
        'enabled' => (bool) env('GOOGLE_PLACES_LOGGING_ENABLED', true),
        'channel' => env('GOOGLE_PLACES_LOG_CHANNEL'),
    ],

];
