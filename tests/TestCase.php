<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests;

use Illuminate\Contracts\Config\Repository;
use Khadikul\GooglePlaces\GooglePlacesServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [GooglePlacesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app->make(Repository::class);

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $config->set('cache.default', 'array');
        $config->set('queue.default', 'sync');

        // Every test runs against a fake key. No test ever reaches Google.
        $config->set('google-places.api_key', 'test-api-key');
        $config->set('google-places.cache.enabled', false);
        $config->set('google-places.retry.times', 1);
        $config->set('google-places.retry.sleep', 0);
    }

    /**
     * Turn on the Mode B configuration a test needs.
     */
    protected function withOAuthConfig(): void
    {
        config([
            'google-places.oauth.client_id' => 'test-client-id',
            'google-places.oauth.client_secret' => 'test-client-secret',
            'google-places.oauth.redirect_uri' => 'https://example.test/google-places/oauth/callback',
        ]);
    }

    /**
     * Read a JSON fixture.
     *
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        $path = __DIR__.'/Fixtures/'.$name.'.json';

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->fail('Missing fixture: '.$path);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
