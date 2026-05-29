<?php

declare(strict_types=1);

namespace Bladeberg\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'bladeberg:install';

    protected $description = 'Publish BladeBerg assets, config, and views';

    public function handle(): int
    {
        $this->info('Installing BladeBerg...');

        $this->call('vendor:publish', [
            '--tag'   => 'bladeberg-assets',
            '--force' => true,
        ]);
        $this->line('  <fg=green;options=bold>✓</> Assets published to <comment>public/vendor/bladeberg/</comment>');

        $this->call('vendor:publish', [
            '--tag' => 'bladeberg-config',
        ]);
        $this->line('  <fg=green;options=bold>✓</> Config published to <comment>config/bladeberg.php</comment>');

        $this->newLine();
        $this->info('BladeBerg installed successfully.');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Add <comment><x-bladeberg-editor name="content" /></comment> to your create/edit form.');
        $this->line('  2. Add <comment><x-bladeberg-render :content="$post->content" /></comment> to your show view.');
        $this->line('  3. Register dynamic blocks in AppServiceProvider using <comment>Bladeberg::registerDynamicBlock()</comment>.');
        $this->line('  4. Re-run <comment>php artisan bladeberg:install</comment> after package updates to refresh assets.');

        if ($this->confirm('Would you like to enable the BladeBerg Media Manager?', false)) {
            $this->setupMediaManager();
        }

        return self::SUCCESS;
    }

    private function setupMediaManager(): void
    {
        $this->newLine();
        $this->info('Setting up Media Manager…');

        $this->call('vendor:publish', [
            '--tag' => 'bladeberg-migrations',
        ]);
        $this->line('  <fg=green;options=bold>✓</> BladeBerg migrations published.');

        $useSpatie = $this->confirm('Use spatie/laravel-medialibrary for thumbnails and conversions? (recommended)', true);

        if ($useSpatie) {
            if (!class_exists(\Spatie\MediaLibrary\HasMedia::class)) {
                $this->warn('  spatie/laravel-medialibrary is not installed.');
                $this->line('  Run: <comment>composer require spatie/laravel-medialibrary</comment>');
                $this->line('  Then re-run: <comment>php artisan bladeberg:install</comment>');
                $this->line('  Or set <comment>media.driver = "filesystem"</comment> in config/bladeberg.php to skip Spatie.');
            } else {
                $this->call('vendor:publish', [
                    '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                    '--tag'      => 'medialibrary-migrations',
                ]);
                $this->line('  <fg=green;options=bold>✓</> Spatie migrations published.');
            }
        }

        $this->call('migrate');
        $this->line('  <fg=green;options=bold>✓</> Migrations run.');

        $this->newLine();
        $this->line('  Enable the media manager in <comment>config/bladeberg.php</comment>:');
        $this->line("  <comment>'media' => ['enabled' => true, 'driver' => '" . ($useSpatie ? 'spatie' : 'filesystem') . "']</comment>");
    }
}
