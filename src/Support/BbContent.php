<?php

declare(strict_types=1);

namespace Bladeberg\Support;

/**
 * Utility methods for normalizing BladeBerg block-comment prefixes.
 *
 * The Gutenberg serializer always writes `<!-- wp:… -->` / `<!-- /wp:… -->`
 * delimiters because those patterns are hard-coded in the browser bundle's
 * private closure. BladeBerg intercepts them at the form-submit level (JS) and
 * rewrites them to the configured prefix (default `bb:`) before the data is sent.
 *
 * The active prefix is read from `config('bladeberg.block_prefix', 'bb')` so
 * developers can change it in `config/bladeberg.php` or via the
 * `BLADEBERG_BLOCK_PREFIX` environment variable.
 *
 * On the PHP side:
 *   - normalize()   converts wp: → <prefix>: for storage (called by the middleware).
 *   - denormalize() converts <prefix>: → wp: when feeding content to third-party
 *                   WP-compatible tools (REST API bridges, classic-editor, etc.).
 *
 * Both `wp:` and the configured prefix are accepted by BlockParser::parse()
 * without any manual pre-processing.
 */
final class BbContent
{
    /**
     * Return the configured block prefix (e.g. 'bb').
     *
     * Falls back gracefully when called outside a full Laravel application
     * context (e.g. PHPUnit tests that do not boot the service container).
     */
    private static function prefix(): string
    {
        if (function_exists('app') && app()->bound('config')) {
            return (string) config('bladeberg.block_prefix', 'bb');
        }
        return 'bb';
    }

    /**
     * Normalize block comment prefixes: `wp:` → configured prefix.
     *
     * Handles optional whitespace between `<!--` and the prefix, and both
     * opening (`<!-- bb:paragraph -->`) and closing (`<!-- /bb:paragraph -->`)
     * delimiters.
     *
     * @param string $html Raw block HTML from the editor textarea
     * @return string HTML with configured-prefix delimiters ready for storage
     */
    public static function normalize(string $html): string
    {
        $prefix = self::prefix();

        return (string) preg_replace(
            '/<!--\s*(\/?)wp:/',
            "<!-- \$1{$prefix}:",
            $html
        );
    }

    /**
     * Denormalize block comment prefixes: configured prefix → `wp:`.
     *
     * Use this when passing BladeBerg-stored content to tools that expect the
     * original WordPress block format (e.g. WP REST API, third-party plugins).
     *
     * @param string $html Stored HTML with configured-prefix delimiters
     * @return string HTML with `wp:` prefixes restored
     */
    public static function denormalize(string $html): string
    {
        $prefix = preg_quote(self::prefix(), '/');

        return (string) preg_replace(
            "/<!--\\s*(\\/?)\\s*{$prefix}:/",
            '<!-- $1wp:',
            $html
        );
    }
}
