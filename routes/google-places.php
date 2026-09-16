<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Khadikul\GooglePlaces\Http\Controllers\OAuthController;
use Khadikul\GooglePlaces\Http\Controllers\WebhookController;
use Khadikul\GooglePlaces\Http\Middleware\VerifyPubSubToken;

/*
|--------------------------------------------------------------------------
| Package routes
|--------------------------------------------------------------------------
|
| Registered inside the host application. Every URL here belongs to the site
| that installed the package; nothing points anywhere else.
|
| Disable either group in config/google-places.php and register your own
| routes pointing at the same controllers if you want different URLs.
|
*/

$oauth = (array) config('google-places.oauth.routes', []);

if (($oauth['enabled'] ?? true) && config('google-places.oauth.client_id')) {
    Route::middleware($oauth['middleware'] ?? ['web'])
        ->prefix($oauth['prefix'] ?? 'google-places/oauth')
        ->group(function (): void {
            Route::get('redirect', [OAuthController::class, 'redirect'])
                ->name('google-places.oauth.redirect');

            Route::get('callback', [OAuthController::class, 'callback'])
                ->name('google-places.oauth.callback');
        });
}

$notifications = (array) config('google-places.notifications', []);

if ($notifications['enabled'] ?? false) {
    Route::middleware(array_merge(
        (array) ($notifications['middleware'] ?? ['api']),
        [VerifyPubSubToken::class],
    ))->post(
        (string) ($notifications['route'] ?? 'google-places/webhook'),
        WebhookController::class,
    )->name('google-places.webhook');
}
