<?php

declare(strict_types=1);

use Bladeberg\Http\RenderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BladeBerg Render API Route
|--------------------------------------------------------------------------
|
| Registered automatically by BladebergServiceProvider when
| config('bladeberg.render_api.enabled') is true. Lets headless/SPA backends
| turn stored block content into rendered HTML on demand:
|
|   POST {prefix}/render   { "content": "<!-- bb:paragraph -->..." }
|        → { "html": "<div class=\"bb-content\">...</div>" }
|
| Prefix and middleware are configurable in config/bladeberg.php.
|
*/

Route::prefix(config('bladeberg.render_api.route_prefix', 'bladeberg'))
    ->middleware(config('bladeberg.render_api.middleware', ['web']))
    ->group(function (): void {
        Route::post('render', [RenderController::class, 'render']);
    });
