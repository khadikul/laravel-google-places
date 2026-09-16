<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Unit;

use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the central promise of this package: the author supplies code and
 * nothing else. No credential, no host, no fallback endpoint of theirs may ever
 * appear in the shipped source.
 *
 * These assertions are deliberately crude string scans. They are meant to fail
 * loudly if someone ever adds a convenience default that quietly routes a
 * user's Google data somewhere it should not go.
 */
final class ZeroVendorInfrastructureTest extends TestCase
{
    /** Hosts that would mean the author is operating infrastructure. */
    private const FORBIDDEN_HOSTS = [
        'khadikul.com',
        'khadikul.dev',
        'khadikul-server',
        'laravel-google-places.com',
        'bitekservices.com',
    ];

    /** Only Google and the host application may be contacted. */
    private const ALLOWED_HOST_SUFFIXES = [
        'googleapis.com',
        'google.com',
        'gstatic.com',
        'example.com',
        'example.test',
        'github.com',
        'packagist.org',
        'developers.google.com',
        'cloud.google.com',
        'about.google',
        'maps.google.com',
        'laravel.com',
        'opensource.org',
        'schema.org',
        'www.w3.org',
        'json-schema.org',
    ];

    #[Test]
    public function no_source_file_references_a_host_owned_by_the_package_author(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $contents = strtolower((string) file_get_contents($file));

            foreach (self::FORBIDDEN_HOSTS as $host) {
                $this->assertStringNotContainsString(
                    $host,
                    $contents,
                    sprintf('%s references %s. The package author must operate no infrastructure.', $file, $host),
                );
            }
        }
    }

    #[Test]
    public function every_outbound_url_in_the_source_points_at_google(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            // Each label must be alphanumeric, so placeholder URLs written as
            // "https://..." in docblocks are not mistaken for a real host.
            preg_match_all('#https?://([a-z0-9\-]+(?:\.[a-z0-9\-]+)+)#i', $contents, $matches);

            foreach (array_unique($matches[1]) as $host) {
                $this->assertTrue(
                    $this->isAllowed(strtolower($host)),
                    sprintf('%s contains an unexpected host: %s', $file, $host),
                );
            }
        }
    }

    #[Test]
    public function no_credential_is_hard_coded_anywhere(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            // Google API keys start AIza; OAuth client IDs end in this suffix;
            // refresh tokens start "1//"; access tokens start "ya29.".
            $this->assertDoesNotMatchRegularExpression(
                '/AIza[0-9A-Za-z_\-]{20,}/',
                $contents,
                $file.' appears to contain a hard-coded Google API key.',
            );

            $this->assertStringNotContainsString(
                '.apps.googleusercontent.com',
                $contents,
                $file.' appears to contain a hard-coded OAuth client ID.',
            );

            $this->assertDoesNotMatchRegularExpression(
                '/[\x27"]ya29\./',
                $contents,
                $file.' appears to contain a hard-coded access token.',
            );
        }
    }

    #[Test]
    public function every_credential_in_the_config_comes_from_the_environment(): void
    {
        $config = (string) file_get_contents(__DIR__.'/../../config/google-places.php');

        foreach ([
            'GOOGLE_PLACES_API_KEY',
            'GOOGLE_PLACES_CLIENT_ID',
            'GOOGLE_PLACES_CLIENT_SECRET',
            'GOOGLE_PLACES_REDIRECT_URI',
            'GOOGLE_PLACES_PUBSUB_TOPIC',
        ] as $variable) {
            $this->assertStringContainsString("env('".$variable."')", $config);
        }

        // None of them may carry a default value.
        $this->assertDoesNotMatchRegularExpression(
            "/env\('GOOGLE_PLACES_(API_KEY|CLIENT_ID|CLIENT_SECRET|REDIRECT_URI|PUBSUB_TOPIC)',\s*[^)]/",
            $config,
            'A credential in the config has a default value. Credentials must come only from the user environment.',
        );
    }

    #[Test]
    public function the_shipped_config_starts_with_no_credentials_at_all(): void
    {
        // Loaded with no environment set at all, as on a fresh install.
        $config = require __DIR__.'/../../config/google-places.php';

        $this->assertNull($config['oauth']['client_id']);
        $this->assertNull($config['oauth']['client_secret']);
        $this->assertNull($config['oauth']['redirect_uri']);
        $this->assertNull($config['notifications']['pubsub_topic']);

        // Mode B is opt-in, so nothing listens until the developer says so.
        $this->assertFalse($config['notifications']['enabled']);
    }

    #[Test]
    public function the_webhook_route_is_relative_to_the_host_application(): void
    {
        $config = require __DIR__.'/../../config/google-places.php';

        $route = $config['notifications']['route'];

        $this->assertSame('google-places/webhook', $route);
        $this->assertStringNotContainsString('://', $route, 'The webhook must be a local route, never an absolute URL.');
    }

    #[Test]
    public function the_default_webhook_authentication_is_on(): void
    {
        $config = require __DIR__.'/../../config/google-places.php';

        $this->assertTrue(
            $config['notifications']['auth']['oidc']['enabled'],
            'OIDC verification must default to on so a public webhook is never unauthenticated by accident.',
        );
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        foreach (['src', 'config', 'routes', 'database'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.DIRECTORY_SEPARATOR.$directory)
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $this->assertNotEmpty($files);

        return $files;
    }

    private function isAllowed(string $host): bool
    {
        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
