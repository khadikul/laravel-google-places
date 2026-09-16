<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Khadikul\GooglePlaces\Clients\BusinessProfileApiClient;
use Khadikul\GooglePlaces\Clients\PlacesApiClient;
use Khadikul\GooglePlaces\Console\InstallCommand;
use Khadikul\GooglePlaces\Console\PruneNotificationsCommand;
use Khadikul\GooglePlaces\Console\SetupCommand;
use Khadikul\GooglePlaces\Console\SyncReviewsCommand;
use Khadikul\GooglePlaces\Console\TestCommand;
use Khadikul\GooglePlaces\Contracts\ProvidesAccessTokens;
use Khadikul\GooglePlaces\Http\Middleware\VerifyPubSubToken;
use Khadikul\GooglePlaces\Services\BusinessProfileService;
use Khadikul\GooglePlaces\Services\GooglePlacesService;
use Khadikul\GooglePlaces\Services\NotificationService;
use Khadikul\GooglePlaces\Services\OAuthService;
use Khadikul\GooglePlaces\Services\ReviewSyncService;
use Khadikul\GooglePlaces\Support\OpenIdTokenVerifier;

/**
 * Registers the package with the host application.
 *
 * Auto-discovered through composer.json, so nothing needs adding to
 * bootstrap/providers.php.
 */
class GooglePlacesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/google-places.php', 'google-places');

        $this->registerClients();
        $this->registerServices();
        $this->registerManager();
    }

    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootMigrations();
        $this->bootRoutes();
        $this->bootCommands();
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            PlacesApiClient::class,
            BusinessProfileApiClient::class,
            GooglePlacesService::class,
            BusinessProfileService::class,
            ReviewSyncService::class,
            OAuthService::class,
            NotificationService::class,
            ProvidesAccessTokens::class,
            GooglePlacesManager::class,
            'google-places',
        ];
    }

    protected function registerClients(): void
    {
        $this->app->singleton(PlacesApiClient::class, fn ($app): PlacesApiClient => new PlacesApiClient(
            $app->make(HttpFactory::class),
            $app->make(Config::class),
        ));

        $this->app->singleton(OpenIdTokenVerifier::class, fn ($app): OpenIdTokenVerifier => new OpenIdTokenVerifier(
            $app->make(HttpFactory::class),
            $app->make(CacheFactory::class),
        ));

        $this->app->singleton(BusinessProfileApiClient::class, fn ($app): BusinessProfileApiClient => new BusinessProfileApiClient(
            $app->make(HttpFactory::class),
            $app->make(Config::class),
            $app->make(ProvidesAccessTokens::class),
        ));
    }

    protected function registerServices(): void
    {
        $this->app->singleton(OAuthService::class, fn ($app): OAuthService => new OAuthService(
            $app->make(HttpFactory::class),
            $app->make(Config::class),
            $app->make(Dispatcher::class),
        ));

        // Token supply is an interface so an application with its own token
        // store can bind a replacement without touching the clients.
        $this->app->bind(ProvidesAccessTokens::class, OAuthService::class);

        $this->app->singleton(GooglePlacesService::class, fn ($app): GooglePlacesService => new GooglePlacesService(
            $app->make(PlacesApiClient::class),
            $app->make(CacheFactory::class),
            $app->make(Config::class),
        ));

        $this->app->singleton(BusinessProfileService::class, fn ($app): BusinessProfileService => new BusinessProfileService(
            $app->make(BusinessProfileApiClient::class),
            $app->make(OAuthService::class),
        ));

        $this->app->singleton(ReviewSyncService::class, fn ($app): ReviewSyncService => new ReviewSyncService(
            $app->make(BusinessProfileApiClient::class),
            $app->make(OAuthService::class),
            $app->make(GooglePlacesService::class),
            $app->make(Dispatcher::class),
        ));

        $this->app->singleton(NotificationService::class, fn ($app): NotificationService => new NotificationService(
            $app->make(BusinessProfileApiClient::class),
            $app->make(OAuthService::class),
            $app->make(Config::class),
        ));
    }

    protected function registerManager(): void
    {
        $this->app->singleton(GooglePlacesManager::class, fn ($app): GooglePlacesManager => new GooglePlacesManager(
            $app->make(GooglePlacesService::class),
            $app->make(BusinessProfileService::class),
            $app->make(ReviewSyncService::class),
            $app->make(OAuthService::class),
            $app->make(NotificationService::class),
        ));

        $this->app->alias(GooglePlacesManager::class, 'google-places');
    }

    protected function bootPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/google-places.php' => $this->app->configPath('google-places.php'),
        ], ['google-places', 'google-places-config']);

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], ['google-places', 'google-places-migrations']);
    }

    protected function bootMigrations(): void
    {
        if ($this->app->make(Config::class)->get('google-places.database.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function bootRoutes(): void
    {
        $router = $this->app->make('router');

        $router->aliasMiddleware('google-places.pubsub', VerifyPubSubToken::class);

        // Routes are conditional on configuration, so they are skipped entirely
        // when the application only ever uses Mode A.
        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/google-places.php');
        }
    }

    protected function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            SetupCommand::class,
            TestCommand::class,
            SyncReviewsCommand::class,
            PruneNotificationsCommand::class,
        ]);
    }
}
