<?php

declare(strict_types=1);

namespace Bladeberg\Support;

/**
 * Rebrand Gutenberg HTML class prefixes in stored block markup.
 *
 * Stored content uses the configured prefix (e.g. bb-block-paragraph).
 * Frontend block CSS targets wp-block-* — normalize back at render time.
 */
final class HtmlBranding
{
    /** @var list<string> */
    private const TOKENS = ['block', 'element', 'container'];

    /**
     * Rewrite configured-prefix HTML classes back to wp-* for Gutenberg CSS.
     */
    public static function normalizeForRender(string $html, ?string $prefix = null): string
    {
        $prefix ??= (function_exists('config') && app()->bound('config'))
            ? (string) config('bladeberg.block_prefix', 'bb')
            : 'bb';

        if ($prefix === 'wp' || $html === '') {
            return $html;
        }

        return str_replace(
            self::fromTokens($prefix),
            self::fromTokens('wp'),
            $html
        );
    }

    /**
     * @return list<string>
     */
    private static function fromTokens(string $prefix): array
    {
        return array_map(
            static fn (string $token): string => "{$prefix}-{$token}-",
            self::TOKENS
        );
    }
}
