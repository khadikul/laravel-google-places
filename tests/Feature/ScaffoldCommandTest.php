<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Tests\Feature;

use Illuminate\Support\Facades\File;
use Khadikul\GooglePlaces\Support\StackDetector;
use Khadikul\GooglePlaces\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ScaffoldCommandTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        // Scaffolding writes into the application, so each test gets its own
        // throwaway base path rather than scribbling in the package.
        $this->sandbox = sys_get_temp_dir().'/gp-scaffold-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->sandbox);

        $this->app->setBasePath($this->sandbox);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $composer
     * @param  array<string, mixed>  $package
     */
    private function manifests(array $composer = [], array $package = []): void
    {
        File::put($this->sandbox.'/composer.json', (string) json_encode($composer));
        File::put($this->sandbox.'/package.json', (string) json_encode($package));
    }

    #[Test]
    public function it_publishes_blade_components(): void
    {
        $this->manifests();

        $this->artisan('google-places:scaffold', ['--stack' => 'blade'])->assertSuccessful();

        foreach ([
            'resources/views/components/google-places/rating.blade.php',
            'resources/views/components/google-places/review-card.blade.php',
            'resources/views/components/google-places/reviews-widget.blade.php',
            'resources/views/components/google-places/place-card.blade.php',
            'resources/views/components/google-places/connect-button.blade.php',
            'resources/views/components/google-places/location-manager.blade.php',
            'resources/views/google-places/search.blade.php',
            'resources/views/google-places/show.blade.php',
            'resources/views/google-places/admin.blade.php',
            'app/Http/Controllers/GooglePlacesPageController.php',
        ] as $file) {
            $this->assertFileExists($this->sandbox.'/'.$file);
        }
    }

    #[Test]
    public function it_publishes_livewire_components_and_reuses_the_blade_partials(): void
    {
        $this->manifests();

        $this->artisan('google-places:scaffold', ['--stack' => 'livewire'])->assertSuccessful();

        $this->assertFileExists($this->sandbox.'/app/Livewire/GooglePlaces/BusinessSearch.php');
        $this->assertFileExists($this->sandbox.'/app/Livewire/GooglePlaces/ReviewList.php');
        $this->assertFileExists($this->sandbox.'/app/Livewire/GooglePlaces/LocationManager.php');
        $this->assertFileExists($this->sandbox.'/resources/views/livewire/google-places/business-search.blade.php');

        // Presentational components are shared with the Blade stack.
        $this->assertFileExists($this->sandbox.'/resources/views/components/google-places/review-card.blade.php');
    }

    #[Test]
    #[DataProvider('inertiaStacks')]
    public function it_publishes_inertia_components(string $stack, string $extension): void
    {
        $this->manifests();

        $this->artisan('google-places:scaffold', ['--stack' => $stack])->assertSuccessful();

        foreach (['Rating', 'ReviewCard', 'ReviewsWidget', 'PlaceCard', 'ConnectGoogleButton', 'LocationManager'] as $component) {
            $this->assertFileExists($this->sandbox."/resources/js/Components/GooglePlaces/{$component}.{$extension}");
        }

        foreach (['Search', 'Show', 'Admin'] as $page) {
            $this->assertFileExists($this->sandbox."/resources/js/Pages/GooglePlaces/{$page}.{$extension}");
        }

        $this->assertFileExists($this->sandbox.'/app/Http/Controllers/GooglePlacesPageController.php');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function inertiaStacks(): array
    {
        return [
            'react' => ['react', 'jsx'],
            'vue' => ['vue', 'vue'],
            'svelte' => ['svelte', 'svelte'],
        ];
    }

    #[Test]
    public function it_refuses_an_unknown_stack(): void
    {
        $this->manifests();

        $this->artisan('google-places:scaffold', ['--stack' => 'angular'])
            ->expectsOutputToContain('Unknown stack')
            ->assertFailed();
    }

    #[Test]
    public function it_does_not_overwrite_existing_files_without_force(): void
    {
        $this->manifests();

        $target = $this->sandbox.'/resources/views/components/google-places/rating.blade.php';
        File::ensureDirectoryExists(dirname($target));
        File::put($target, 'MY OWN VERSION');

        $this->artisan('google-places:scaffold', ['--stack' => 'blade'])->assertSuccessful();
        $this->assertSame('MY OWN VERSION', File::get($target));

        $this->artisan('google-places:scaffold', ['--stack' => 'blade', '--force' => true])->assertSuccessful();
        $this->assertStringContainsString('No rating yet', File::get($target));
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        $this->manifests();

        $this->artisan('google-places:scaffold', ['--stack' => 'blade', '--dry-run' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($this->sandbox.'/resources/views/components/google-places/rating.blade.php');
    }

    // -----------------------------------------------------------------
    // Stack detection
    // -----------------------------------------------------------------

    #[Test]
    public function it_detects_blade_when_nothing_else_is_installed(): void
    {
        $this->manifests(['require' => ['laravel/framework' => '^13.0']]);

        $this->assertSame(StackDetector::BLADE, (new StackDetector($this->sandbox))->detect());
    }

    #[Test]
    public function it_detects_livewire(): void
    {
        $this->manifests(['require' => ['livewire/livewire' => '^3.0']]);

        $this->assertSame(StackDetector::LIVEWIRE, (new StackDetector($this->sandbox))->detect());
    }

    #[Test]
    public function it_detects_the_inertia_client_framework(): void
    {
        foreach ([
            '@inertiajs/react' => StackDetector::REACT,
            '@inertiajs/vue3' => StackDetector::VUE,
            '@inertiajs/svelte' => StackDetector::SVELTE,
        ] as $node => $expected) {
            $this->manifests(
                ['require' => ['inertiajs/inertia-laravel' => '^2.0']],
                ['dependencies' => [$node => '^2.0']],
            );

            $this->assertSame($expected, (new StackDetector($this->sandbox))->detect());
        }
    }

    #[Test]
    public function inertia_wins_when_an_application_has_both(): void
    {
        // Plenty of apps keep Livewire installed while rendering through
        // Inertia; the components have to be JavaScript in that case.
        $this->manifests(
            ['require' => ['livewire/livewire' => '^3.0', 'inertiajs/inertia-laravel' => '^2.0']],
            ['dependencies' => ['@inertiajs/vue3' => '^2.0']],
        );

        $this->assertSame(StackDetector::VUE, (new StackDetector($this->sandbox))->detect());
    }

    #[Test]
    public function it_detects_tailwind_from_either_package(): void
    {
        $this->manifests([], ['devDependencies' => ['tailwindcss' => '^4.0']]);
        $this->assertTrue((new StackDetector($this->sandbox))->hasTailwind());

        // Tailwind 4 is often only pulled in through the Vite plugin.
        $this->manifests([], ['devDependencies' => ['@tailwindcss/vite' => '^4.0']]);
        $this->assertTrue((new StackDetector($this->sandbox))->hasTailwind());

        $this->manifests([], ['devDependencies' => ['bootstrap' => '^5.0']]);
        $this->assertFalse((new StackDetector($this->sandbox))->hasTailwind());
    }

    #[Test]
    public function detection_survives_missing_or_broken_manifests(): void
    {
        // No manifests at all.
        $this->assertSame(StackDetector::BLADE, (new StackDetector($this->sandbox))->detect());

        File::put($this->sandbox.'/composer.json', '{ not json');
        File::put($this->sandbox.'/package.json', '');

        $this->assertSame(StackDetector::BLADE, (new StackDetector($this->sandbox))->detect());
    }

    #[Test]
    public function it_warns_when_tailwind_is_missing(): void
    {
        $this->manifests([], ['devDependencies' => ['bootstrap' => '^5.0']]);

        $this->artisan('google-places:scaffold', ['--stack' => 'blade', '--dry-run' => true])
            ->expectsOutputToContain('Tailwind')
            ->assertSuccessful();
    }
}
