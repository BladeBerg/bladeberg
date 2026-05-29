<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Block Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used inside block comment delimiters when content is saved.
    | BladeBerg replaces Gutenberg's default `wp:` with this value before
    | the form is submitted to the server.
    |
    | Default: 'bb' → saves as <!-- bb:paragraph --> instead of <!-- wp:paragraph -->
    |
    | Developers may change this to any lowercase identifier:
    |   'block_prefix' => 'myapp'   // → <!-- myapp:paragraph -->
    |
    | The PHP parser and BbContent helper automatically normalize the
    | configured prefix back to `wp:` for internal processing, so changing
    | this value does not break existing stored content.
    |
    */
    'block_prefix' => env('BLADEBERG_BLOCK_PREFIX', 'bb'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Blocks
    |--------------------------------------------------------------------------
    |
    | Restrict which Gutenberg blocks are available in the editor. Set to null
    | to allow all registered blocks, or provide an array of block names
    | (e.g. ['core/paragraph', 'core/heading', 'bladeberg/callout']).
    |
    */
    'allowed_blocks' => null,

    /*
    |--------------------------------------------------------------------------
    | Fixed Toolbar
    |--------------------------------------------------------------------------
    |
    | When true, the block toolbar is fixed to the top of the editor instead
    | of appearing inline next to the selected block.
    |
    */
    'has_fixed_toolbar' => false,

    /*
    |--------------------------------------------------------------------------
    | Wide Alignment
    |--------------------------------------------------------------------------
    |
    | Enable wide and full-width alignment options for blocks that support it.
    |
    */
    'align_wide' => true,

    /*
    |--------------------------------------------------------------------------
    | Content Storage Format
    |--------------------------------------------------------------------------
    |
    | Determines how block content is stored. Currently only 'html' is
    | supported. 'json' support is planned for a future release.
    |
    | Supported: "html"
    |
    */
    'content_storage' => 'html',

    /*
    |--------------------------------------------------------------------------
    | Stylesheet Groups
    |--------------------------------------------------------------------------
    |
    | Control which pre-built CSS bundles BladeBerg loads. Each key maps to a
    | file in public/vendor/bladeberg/ that is injected by the editor Blade
    | component. Disable any group by setting its value to false.
    |
    | Stylesheet files (rebuilt whenever you run `npm run build` in the package):
    |
    |   core.css             — Gutenberg editor chrome (toolbar, canvas, popovers)
    |   isolated-block-editor.css — isolated-block-editor layout shell
    |   components.css       — @wordpress/components (inputs, modals, dropdowns)
    |   blocks-style.css     — per-block FRONTEND styles (visible to visitors on show pages)
    |   blocks-editor.css    — per-block EDITOR styles (appearance inside the canvas)
    |
    */
    'styles' => [
        // Gutenberg editor chrome — toolbar, canvas, popover chrome.
        // The browser build of isolated-block-editor already bundles its own
        // compatible copies of @wordpress/components and @wordpress/block-library
        // inside core.css, so loading those separately causes version conflicts.
        'core'          => true,

        // isolated-block-editor layout shell (header rail, sidebar split, etc.)
        'iso'           => true,

        // @wordpress/components stylesheet from root node_modules.
        // Leave FALSE unless you need to override component styles; the version
        // bundled inside core.css is already compatible with the editor.
        'components'    => false,

        // Per-block FRONTEND styles loaded by <x-bladeberg-render>.
        // Set to false here; render.blade.php always loads blocks-style.css
        // independently so that post/page view pages get proper block styling.
        'blocks_style'  => false,

        // Per-block EDITOR styles from root @wordpress/block-library.
        // Leave FALSE — these are already included in core.css at the correct
        // compatible version and loading them twice causes visual conflicts.
        'blocks_editor' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Manager
    |--------------------------------------------------------------------------
    |
    | BladeBerg includes an optional media manager that wires into Gutenberg's
    | image / file / gallery blocks.
    |
    | mode
    |   'disabled' — no media integration (default). Gutenberg blocks show a
    |                plain URL/link input for image src, video src, etc.
    |   'link'     — same as disabled from the server's perspective; a future
    |                client-side URL-picker may be added here. Currently
    |                identical to 'disabled'.
    |   'select'   — browse and insert files already stored on the configured
    |                disk. Upload is disabled. Activates the read-only API routes
    |                (GET /media, GET /media/{id}).
    |   'upload'   — full media library: browse existing files AND upload new
    |                ones. All API routes are active.
    |
    |   Set via env: BLADEBERG_MEDIA_MODE=upload
    |
    | disk
    |   The Laravel filesystem disk used for reading and writing media files.
    |   Defaults to the BLADEBERG_MEDIA_DISK env variable, then to whatever
    |   FILESYSTEM_DISK is set to in your .env (i.e. Laravel's own default disk),
    |   and finally falls back to 'public'.
    |
    |   This means BladeBerg re-uses the SAME storage your app already configures
    |   — no separate storage setup is required.
    |
    | directory
    |   Sub-directory inside the disk where BladeBerg stores uploaded files.
    |   Files already in other directories on the disk are NOT listed unless
    |   you change this to '' (empty = disk root) or a common parent directory.
    |
    | driver
    |   'filesystem' — default. Uses Laravel Storage directly; scans the
    |                  configured disk directory for files. No database table
    |                  or migrations required.
    |   'spatie'     — uses spatie/laravel-medialibrary for automatic thumbnail
    |                  conversions and multi-disk management. Requires
    |                  `composer require spatie/laravel-medialibrary`.
    |
    | route_prefix
    |   URL prefix for the media API. Routes become:
    |     GET  /{prefix}/media
    |     POST /{prefix}/media      (upload mode only)
    |     GET  /{prefix}/media/{id}
    |     DELETE /{prefix}/media/{id}
    |
    | middleware
    |   Applied to all media API routes. Override for your own auth guard.
    |
    | max_file_size_kb
    |   Maximum upload size in KB. Default: 10 MB.
    |
    | allowed_mime_types
    |   MIME types accepted on upload (upload mode only).
    |
    | conversions
    |   Image size variants generated by the Spatie driver (ignored by filesystem).
    |
    */
    'media' => [
        'mode'      => env('BLADEBERG_MEDIA_MODE', 'disabled'),
        'driver'    => env('BLADEBERG_MEDIA_DRIVER', 'filesystem'),

        // Uses your app's FILESYSTEM_DISK by default — no separate config needed.
        'disk'      => env('BLADEBERG_MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),

        // Sub-directory inside the disk for BladeBerg uploads.
        'directory' => env('BLADEBERG_MEDIA_DIRECTORY', 'bladeberg'),

        'route_prefix'     => 'bladeberg',
        'middleware'       => ['web', 'auth'],
        'max_file_size_kb' => 10240,
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'video/mp4', 'video/webm', 'video/ogg',
            'audio/mpeg', 'audio/ogg', 'audio/wav',
            'application/pdf',
        ],
        'conversions' => [
            'thumbnail' => ['width' => 300, 'height' => 300],
            'medium'    => ['width' => 768,  'height' => null],
            'large'     => ['width' => 1200, 'height' => null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Normalization
    |--------------------------------------------------------------------------
    |
    | BladeBerg's JS form-submit interceptor rewrites `<!-- wp:… -->` block
    | comment delimiters to `<!-- bb:… -->` before the browser sends the form.
    | This middleware provides an identical server-side safety net for requests
    | that bypass the interceptor (AJAX submissions, server-side imports, etc.).
    |
    | enabled
    |   Set to true to activate the NormalizeBbContent middleware globally via
    |   its alias. The middleware is always available as 'bladeberg.normalize'
    |   for per-route use regardless of this setting.
    |
    | fields
    |   Request field names that carry block-editor content. The middleware
    |   normalizes wp: → bb: in each of these fields if they are present and
    |   contain a string value.
    |
    | Per-route usage (no global activation required):
    |   Route::post('/posts', [PostController::class, 'store'])
    |       ->middleware('bladeberg.normalize');
    |
    | Or call BbContent::normalize($content) manually in a controller / model.
    |
    */
    'content_normalization' => [
        'enabled' => false,
        'fields'  => ['content'],
    ],

];
