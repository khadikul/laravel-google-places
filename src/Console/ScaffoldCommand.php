<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Khadikul\GooglePlaces\Support\StackDetector;

use function Laravel\Prompts\select;

use SplFileInfo;

/**
 * Copies starter components into the application.
 *
 * These are stubs, not package views. Once published they belong to the
 * application and are never loaded, overridden or updated by the package again,
 * which is why the package still registers no view namespace.
 *
 * The markup is Tailwind, matching Laravel's own starter kits.
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'google-places:scaffold
                            {--stack= : blade, livewire, react, vue or svelte. Detected when omitted}
                            {--force : Overwrite files that already exist}
                            {--dry-run : List what would be written without writing it}';

    protected $description = 'Publish ready-made Google Places components for your frontend stack';

    public function __construct(
        protected Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $detector = new StackDetector($this->laravel->basePath());
        $report = $detector->report();

        $stack = $this->resolveStack($detector, $report);

        if ($stack === null) {
            return self::FAILURE;
        }

        $this->components->info(sprintf('Publishing %s components', $stack));

        $this->reportDetection($report, $stack);

        $sources = $this->sourcesFor($stack);
        $written = 0;
        $skipped = 0;

        foreach ($sources as $source) {
            foreach ($this->filesIn($source['from']) as $file) {
                $relative = $this->relative($source['from'], $file);
                $target = $this->laravel->basePath($source['to'].DIRECTORY_SEPARATOR.$relative);

                if ($this->files->exists($target) && ! $this->option('force')) {
                    $this->components->twoColumnDetail($this->display($target), '<fg=yellow>exists</>');
                    $skipped++;

                    continue;
                }

                if (! $this->option('dry-run')) {
                    $this->files->ensureDirectoryExists(dirname($target));
                    $this->files->copy($file, $target);
                }

                $this->components->twoColumnDetail(
                    $this->display($target),
                    $this->option('dry-run') ? '<fg=cyan>would write</>' : '<fg=green>written</>',
                );
                $written++;
            }
        }

        $this->newLine();
        $this->components->info(sprintf('%d file(s) %s, %d skipped.',
            $written,
            $this->option('dry-run') ? 'pending' : 'published',
            $skipped,
        ));

        if ($skipped > 0 && ! $this->option('force')) {
            $this->line('  <fg=gray>Re-run with --force to overwrite the files that already exist.</>');
        }

        $this->printNextSteps($stack, $report);

        return self::SUCCESS;
    }

    /**
     * @param  array{stack: string, inertia: bool, livewire: bool, tailwind: bool, client: ?string}  $report
     */
    protected function resolveStack(StackDetector $detector, array $report): ?string
    {
        $stack = $this->option('stack');

        if (is_string($stack) && $stack !== '') {
            $stack = strtolower($stack);

            if (! StackDetector::isValid($stack)) {
                $this->components->error(sprintf(
                    'Unknown stack [%s]. Choose one of: %s.',
                    $stack,
                    implode(', ', StackDetector::ALL),
                ));

                return null;
            }

            return $stack;
        }

        $detected = $report['stack'];

        if (! $this->input->isInteractive()) {
            return $detected;
        }

        return select(
            label: 'Which stack should the components target?',
            options: array_combine(StackDetector::ALL, StackDetector::ALL),
            default: $detected,
            hint: sprintf('Detected: %s', $detected),
        );
    }

    /**
     * @param  array{stack: string, inertia: bool, livewire: bool, tailwind: bool, client: ?string}  $report
     */
    protected function reportDetection(array $report, string $stack): void
    {
        $this->components->twoColumnDetail('Detected stack', $report['stack']);
        $this->components->twoColumnDetail('Publishing for', $stack);
        $this->components->twoColumnDetail('Tailwind', $report['tailwind'] ? 'found' : '<fg=yellow>not found</>');

        if (! $report['tailwind']) {
            $this->line('  <fg=gray>The components use Tailwind classes. Without Tailwind they render unstyled.</>');
        }

        $this->newLine();
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    protected function sourcesFor(string $stack): array
    {
        $root = dirname(__DIR__, 2).'/stubs';

        return match ($stack) {
            StackDetector::BLADE => [
                ['from' => $root.'/blade/views', 'to' => 'resources/views'],
                ['from' => $root.'/blade/app', 'to' => 'app'],
            ],
            StackDetector::LIVEWIRE => [
                // Livewire renders Blade, so it reuses the presentational components.
                ['from' => $root.'/blade/views/components', 'to' => 'resources/views/components'],
                ['from' => $root.'/livewire/app', 'to' => 'app'],
                ['from' => $root.'/livewire/views', 'to' => 'resources/views'],
            ],
            default => [
                ['from' => $root.'/'.$stack, 'to' => 'resources/js'],
                ['from' => $root.'/inertia/app', 'to' => 'app'],
            ],
        };
    }

    /**
     * @return list<string>
     */
    protected function filesIn(string $directory): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        return array_map(
            static fn (SplFileInfo $file): string => $file->getPathname(),
            $this->files->allFiles($directory),
        );
    }

    protected function relative(string $root, string $file): string
    {
        return ltrim(str_replace(
            [rtrim($root, '/\\'), '/'],
            ['', DIRECTORY_SEPARATOR],
            $file,
        ), '/\\');
    }

    protected function display(string $path): string
    {
        return str_replace(
            [$this->laravel->basePath().DIRECTORY_SEPARATOR, '\\'],
            ['', '/'],
            $path,
        );
    }

    /**
     * @param  array{stack: string, inertia: bool, livewire: bool, tailwind: bool, client: ?string}  $report
     */
    protected function printNextSteps(string $stack, array $report): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>Next steps</>');

        $this->line('   1. Add your own API key if you have not already:');
        $this->line('      <fg=gray>GOOGLE_PLACES_API_KEY=your-own-key</>');

        match ($stack) {
            StackDetector::BLADE => $this->bladeSteps(),
            StackDetector::LIVEWIRE => $this->livewireSteps(),
            default => $this->inertiaSteps($stack),
        };

        $this->newLine();
        $this->line('  <fg=gray>Published files are yours: the package never reads or updates them.</>');
    }

    protected function bladeSteps(): void
    {
        $this->line('   2. Point a route at the published controller:');
        $this->line('      <fg=gray>Route::get(\'/places\', [GooglePlacesPageController::class, \'search\']);</>');
        $this->line('      <fg=gray>Route::get(\'/places/{placeId}\', [GooglePlacesPageController::class, \'show\']);</>');
        $this->line('   3. Drop the public widget anywhere:');
        $this->line('      <fg=gray>&lt;x-google-places.reviews-widget :place-id="$placeId" /&gt;</>');
    }

    protected function livewireSteps(): void
    {
        $this->line('   2. Use the components directly in any view:');
        $this->line('      <fg=gray>&lt;livewire:google-places.business-search /&gt;</>');
        $this->line('      <fg=gray>&lt;livewire:google-places.review-list :place-id="$placeId" /&gt;</>');
        $this->line('      <fg=gray>&lt;livewire:google-places.location-manager /&gt;</>');
        $this->line('   3. Keep the connect link a plain anchor: wire:navigate cannot follow');
        $this->line('      <fg=gray>the cross-origin redirect to Google.</>');
    }

    protected function inertiaSteps(string $stack): void
    {
        $this->line('   2. Point routes at the published controller:');
        $this->line('      <fg=gray>Route::get(\'/places\', [GooglePlacesPageController::class, \'search\']);</>');
        $this->line('      <fg=gray>Route::get(\'/places/{placeId}\', [GooglePlacesPageController::class, \'show\']);</>');
        $this->line('      <fg=gray>Route::get(\'/admin/google\', [GooglePlacesPageController::class, \'admin\']);</>');
        $this->line('   3. Share the OAuth flash data in HandleInertiaRequests:');
        $this->line('      <fg=gray>\'googleStatus\' => fn () => $request->session()->get(\'google_places_status\'),</>');
        $this->line(sprintf('   4. Components landed in <fg=gray>resources/js/Components/GooglePlaces</> (%s).', $stack));
    }
}
