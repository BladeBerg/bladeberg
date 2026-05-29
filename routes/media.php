<?php

declare(strict_types=1);

use Bladeberg\Media\BbMediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BladeBerg Media API Routes
|--------------------------------------------------------------------------
|
| These routes are registered automatically by BladebergServiceProvider
| when config('bladeberg.media.enabled') is true.
|
| The prefix and middleware are configurable:
|
|   'route_prefix' => 'bladeberg'          → /bladeberg/media
|   'middleware'   => ['web', 'auth']       → protected by session auth
|
| Adjust both values in config/bladeberg.php to suit your application.
|
*/

Route::prefix(config('bladeberg.media.route_prefix', 'bladeberg'))
    ->middleware(config('bladeberg.media.middleware', ['web', 'auth']))
    ->group(function (): void {
        Route::get('media',         [BbMediaController::class, 'index']);
        Route::post('media',        [BbMediaController::class, 'store']);
        Route::get('media/{id}',    [BbMediaController::class, 'show'])->whereNumber('id');
        Route::delete('media/{id}', [BbMediaController::class, 'destroy'])->whereNumber('id');
    });
