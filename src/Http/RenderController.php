<?php

declare(strict_types=1);

namespace Bladeberg\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side render endpoint for headless / API consumers.
 *
 * Accepts stored block content (carrying the configured prefix) and returns the
 * rendered HTML, identical to what the <x-bladeberg-render> component produces.
 * Disabled by default; enable via config('bladeberg.render_api.enabled').
 */
final class RenderController
{
    public function render(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['present', 'nullable', 'string'],
        ]);

        $html = app('bladeberg')->render($validated['content'] ?? '');

        return response()->json(['html' => $html]);
    }
}
