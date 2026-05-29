<?php

declare(strict_types=1);

namespace Bladeberg\Http\Middleware;

use Bladeberg\Support\BbContent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: normalize block-comment prefixes in request fields.
 *
 * When `bladeberg.content_normalization.enabled` is `true`, this middleware
 * rewrites `<!-- wp:… -->` → `<!-- bb:… -->` in every request field listed
 * under `bladeberg.content_normalization.fields` (default: `['content']`).
 *
 * The JS form-submit interceptor already handles standard <form> submissions.
 * This middleware acts as a server-side safety net for:
 *   - AJAX requests that bypass the intercept (e.g. fetch with raw textarea value)
 *   - Server-side imports / seeding scripts
 *   - Any other flow where content reaches the controller without going through
 *     the BladeBerg editor's submit handler
 *
 * Registration (route middleware alias):
 *   Route::post('/posts', [PostController::class, 'store'])
 *       ->middleware('bladeberg.normalize');
 *
 * Or register globally in app/Http/Kernel.php to apply to every request:
 *   protected $middlewareGroups = [
 *       'web' => [ ..., \Bladeberg\Http\Middleware\NormalizeBbContent::class ],
 *   ];
 */
final class NormalizeBbContent
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fields = config('bladeberg.content_normalization.fields', ['content']);
        $merge  = [];

        foreach ((array) $fields as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $normalized = BbContent::normalize($request->input($field));

                if ($normalized !== $request->input($field)) {
                    $merge[$field] = $normalized;
                }
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }

        return $next($request);
    }
}
