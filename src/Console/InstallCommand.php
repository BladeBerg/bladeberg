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
        $this->newLine();
        $this->line('Media manager (optional): set <comment>media.mode</comment> in <comment>config/bladeberg.php</comment>');
        $this->line('  (or <comment>BLADEBERG_MEDIA_MODE</comment>). It re-uses your app\'s <comment>FILESYSTEM_DISK</comment> by default.');
        $this->line('  Only run migrations if you opt into the <comment>spatie</comment> driver.');

        return self::SUCCESS;
    }
}
