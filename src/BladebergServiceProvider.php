<?php

declare(strict_types=1);

namespace Bladeberg;

use Bladeberg\Console\InstallCommand;
use Bladeberg\Http\Middleware\NormalizeBbContent;
use Bladeberg\Media\FilesystemMediaDriver;
use Bladeberg\Media\MediaDriverInterface;
use Bladeberg\Media\SpatieMediaDriver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladebergServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bladeberg.php', 'bladeberg');

        $this->app->singleton('bladeberg', function () {
            return new BladebergRegistry();
        });

        $this->registerMediaDriver();
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bladeberg');

        // Load media API routes for any mode that needs server-side file access.
        // 'select' → read-only listing routes only (store() returns 403).
        // 'upload' → full CRUD routes.
        // Backward compat: respect the legacy 'enabled' key if 'mode' is not set.
        $mediaMode    = config('bladeberg.media.mode', 'disabled');
        $legacyActive = config('bladeberg.media.enabled', false);
        if (in_array($mediaMode, ['select', 'upload'], true) || $legacyActive) {
            $this->loadRoutesFrom(__DIR__.'/../routes/media.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../dist' => public_path('vendor/bladeberg'),
            ], 'bladeberg-assets');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/bladeberg'),
            ], 'bladeberg-views');

            $this->publishes([
                __DIR__.'/../config/bladeberg.php' => config_path('bladeberg.php'),
            ], 'bladeberg-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'bladeberg-migrations');

            $this->commands([InstallCommand::class]);
        }

        Blade::component('bladeberg::components.editor', 'bladeberg-editor');
        Blade::component('bladeberg::components.render', 'bladeberg-render');

        // Register the middleware alias so users can apply it per-route:
        //   Route::post('/posts', ...)->middleware('bladeberg.normalize');
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('bladeberg.normalize', NormalizeBbContent::class);
    }

    /**
     * Bind the appropriate media driver based on config and available packages.
     *
     * Driver resolution order:
     *   1. If driver = 'spatie' AND spatie/laravel-medialibrary is installed → SpatieMediaDriver
     *   2. Everything else → FilesystemMediaDriver
     */
    private function registerMediaDriver(): void
    {
        $this->app->bind(MediaDriverInterface::class, function () {
            $driver  = config('bladeberg.media.driver', 'spatie');
            $spatiePkg = 'Spatie\\MediaLibrary\\HasMedia';

            if ($driver === 'spatie' && interface_exists($spatiePkg)) {
                return new SpatieMediaDriver();
            }

            return new FilesystemMediaDriver();
        });
    }
}
