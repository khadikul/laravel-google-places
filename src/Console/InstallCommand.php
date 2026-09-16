<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * First-run helper: publishes the config and migrations, and explains exactly
 * which credentials the developer has to create themselves.
 */
class InstallCommand extends Command
{
    protected $signature = 'google-places:install
                            {--force : Overwrite any files that already exist}
                            {--no-migrate : Publish the migrations without running them}';

    protected $description = 'Publish the Google Places config and migrations, and print the setup checklist';

    public function handle(): int
    {
        $this->components->info('Installing khadikul/laravel-google-places');

        $this->callSilently('vendor:publish', [
            '--tag' => 'google-places-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->task('Published config/google-places.php');

        $this->callSilently('vendor:publish', [
            '--tag' => 'google-places-migrations',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->task('Published migrations');

        if (! $this->option('no-migrate') && $this->shouldMigrate()) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->warn('This package ships with no Google credentials. Everything below is yours.');
        $this->newLine();

        $this->line('  <fg=cyan>Mode A — public places (API key only)</>');
        $this->line('   1. Create a Google Cloud project and enable billing.');
        $this->line('   2. Enable the <options=bold>Places API (New)</>.');
        $this->line('   3. Create an API key, restrict it to that API, and add it to .env:');
        $this->line('      <fg=gray>GOOGLE_PLACES_API_KEY=your-own-key</>');
        $this->newLine();

        $this->line('  <fg=cyan>Mode B — connected business (optional)</>');
        $this->line('   4. Request Business Profile API access and wait for approval.');
        $this->line('   5. Enable: My Business Account Management API, My Business Business');
        $this->line('      Information API, My Business Notifications API, and the legacy');
        $this->line('      Google My Business API (reviews still live there).');
        $this->line('   6. Create an OAuth 2.0 client (Web application) and add to .env:');
        $this->line('      <fg=gray>GOOGLE_PLACES_CLIENT_ID=</>');
        $this->line('      <fg=gray>GOOGLE_PLACES_CLIENT_SECRET=</>');
        $this->line('      <fg=gray>GOOGLE_PLACES_REDIRECT_URI='.rtrim((string) config('app.url'), '/').'/google-places/oauth/callback</>');
        $this->line('   7. Run <options=bold>php artisan google-places:setup</> for the Pub/Sub steps.');
        $this->newLine();

        $this->components->info('Verify your configuration with: php artisan google-places:test');

        return self::SUCCESS;
    }

    protected function shouldMigrate(): bool
    {
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm('Run the migrations now?', default: true);
    }
}
