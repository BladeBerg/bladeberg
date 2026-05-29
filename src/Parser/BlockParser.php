<?php

declare(strict_types=1);

namespace Bladeberg\Parser;

/**
 * Parses serialized block content into an array of Block objects.
 *
 * Supported delimiters
 * ─────────────────────
 *  Standard block:      <!-- wp:name {"k":"v"} -->…<!-- /wp:name -->
 *  Self-closing block:  <!-- wp:name {"k":"v"} /-->
 *  Nested inner blocks: recursive, arbitrary depth
 *  Freeform HTML:       content between block comments (no delimiter)
 *
 * Accepted prefixes
 * ──────────────────
 *  Both `wp:` (Gutenberg default) and `bb:` (BladeBerg storage format) are
 *  accepted. The `parse()` entry-point normalizes `bb:` → `wp:` before any
 *  regex matching, so all internal logic works with the canonical `wp:` form.
 *  This means content saved before and after the BladeBerg rebranding is
 *  parsed correctly with no migration required.
 *
 * Limitations
 * ────────────
 *  Intentionally simpler than WP_Block_Parser. Edge-cases such as JSON attribute
 *  values that contain `}` followed by `-->` are not supported.
 */
class BlockParser
{
    /**
     * Pattern matching a self-closing block comment.
     * Group 1: block name, Group 2: JSON attrs (optional)
     */
    private const SELF_CLOSING_PATTERN = '/<!--\s+wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)\s*(\{(?:[^}]|\}(?!\s*\/))*\})?\s*\/-->/s';

    /**
     * Pattern matching an opening block comment.
     * Group 1: block name, Group 2: JSON attrs (optional)
     */
    private const OPENING_PATTERN = '/<!--\s+wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)\s*(\{(?:[^}]|\}(?!\s*-->))*\})?\s*-->/s';

    /**
     * Parse serialized block content and return a flat list of top-level blocks.
     *
     * Both `<!-- wp:paragraph -->` (Gutenberg default) and `<!-- bb:paragraph -->`
     * (BladeBerg storage format) are accepted. On entry the document is normalized
     * from `bb:` to `wp:` so all internal regex patterns work unchanged.
     *
     * @param  string $document Raw block HTML using either `wp:` or `bb:` delimiters
     * @return Block[]
     */
    public function parse(string $document): array
    {
        $document = trim($document);

        if ($document === '') {
            return [];
        }

        // Normalize the configured block prefix (default 'bb') → 'wp:' so all
        // internal regex patterns work with the canonical Gutenberg form.
        // Falls back gracefully when called outside a full Laravel context
        // (e.g. PHPUnit tests that do not boot the service container).
        $configuredPrefix = (function_exists('app') && app()->bound('config'))
            ? (string) config('bladeberg.block_prefix', 'bb')
            : 'bb';

        if ($configuredPrefix !== 'wp') {
            $escapedPrefix = preg_quote($configuredPrefix, '/');
            $document = (string) preg_replace(
                "/<!--\\s*(\\/?)\\s*{$escapedPrefix}:/",
                '<!-- $1wp:',
                $document
            );
        }

        return $this->parseBlocks($document);
    }

    /**
     * Recursively parse blocks from a segment of content.
     *
     * @return Block[]
     */
    protected function parseBlocks(string $content): array
    {
        $blocks = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $next = $this->findNextBlock($content, $offset);

            if ($next === null) {
                $tail = substr($content, $offset);
                if (trim($tail) !== '') {
                    $blocks[] = $this->makeFreeformBlock($tail);
                }
                break;
            }

            // Emit leading freeform HTML
            if ($next['offset'] > $offset) {
                $leading = substr($content, $offset, $next['offset'] - $offset);
                if (trim($leading) !== '') {
                    $blocks[] = $this->makeFreeformBlock($leading);
                }
            }

            $blocks[] = $next['block'];
            $offset = $next['offset'] + $next['length'];
        }

        return $blocks;
    }

    /**
     * Locate the next block (self-closing or standard) starting from $offset.
     *
     * @return array{offset: int, length: int, block: Block}|null
     */
    protected function findNextBlock(string $content, int $offset): ?array
    {
        // Try self-closing first because its pattern is a strict subset of the
        // opening-tag pattern and must be tested before the opening pattern.
        $selfClose = $this->matchSelfClosing($content, $offset);
        $opening   = $this->matchOpening($content, $offset);

        // Pick whichever appears first in the string
        if ($selfClose === null && $opening === null) {
            return null;
        }

        if ($selfClose !== null && ($opening === null || $selfClose['offset'] <= $opening['offset'])) {
            return $selfClose;
        }

        return $opening;
    }

    /**
     * Try to match a self-closing block comment at or after $offset.
     *
     * @return array{offset: int, length: int, block: Block}|null
     */
    protected function matchSelfClosing(string $content, int $offset): ?array
    {
        if (!preg_match(self::SELF_CLOSING_PATTERN, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }

        $name  = $matches[1][0];
        $attrs = $this->decodeAttrs($matches[2][0] ?? '');
        $raw   = $matches[0][0];

        $block = new Block(
            blockName: $name,
            attrs: $attrs,
            innerBlocks: [],
            innerHTML: '',
            innerContent: [],
            isSelfClosing: true,
        );

        return [
            'offset' => (int) $matches[0][1],
            'length' => strlen($raw),
            'block'  => $block,
        ];
    }

    /**
     * Try to match a standard (opening + closing) block comment at or after $offset.
     *
     * @return array{offset: int, length: int, block: Block}|null
     */
    protected function matchOpening(string $content, int $offset): ?array
    {
        if (!preg_match(self::OPENING_PATTERN, $content, $openMatches, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }

        $name       = $openMatches[1][0];
        $attrs      = $this->decodeAttrs($openMatches[2][0] ?? '');
        $openStart  = (int) $openMatches[0][1];
        $openEnd    = $openStart + strlen($openMatches[0][0]);

        // Find the matching closing tag (handles nesting of same block type)
        $closingTag = '<!-- /wp:' . $name . ' -->';
        $totalLength = 0;
        $innerHtml  = $this->extractInnerHtml($content, $name, $openEnd, $totalLength);

        if ($innerHtml === null) {
            // No closing tag found — treat remainder as freeform
            return null;
        }

        $innerBlocks = $this->parseBlocks($innerHtml);

        $block = new Block(
            blockName: $name,
            attrs: $attrs,
            innerBlocks: $innerBlocks,
            innerHTML: $innerHtml,
            innerContent: [$innerHtml],
            isSelfClosing: false,
        );

        return [
            'offset' => $openStart,
            'length' => strlen($openMatches[0][0]) + strlen($innerHtml) + strlen($closingTag),
            'block'  => $block,
        ];
    }

    /**
     * Extract the raw innerHTML between the opening comment and its matching
     * closing comment, correctly handling nested same-name blocks.
     *
     * @param int    $startAfterOpen  Offset immediately after the opening comment
     * @param int    $totalLength     Populated with the byte-length consumed (inner + closing tag)
     */
    protected function extractInnerHtml(
        string $content,
        string $name,
        int $startAfterOpen,
        int &$totalLength = 0
    ): ?string {
        $openTag    = '<!-- wp:' . $name;
        $closeTag   = '<!-- /wp:' . $name . ' -->';
        $depth      = 1;
        $pos        = $startAfterOpen;
        $contentLen = strlen($content);

        while ($pos < $contentLen && $depth > 0) {
            $nextOpen  = strpos($content, $openTag, $pos);
            $nextClose = strpos($content, $closeTag, $pos);

            if ($nextClose === false) {
                return null;
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + strlen($openTag);
            } else {
                $depth--;
                if ($depth === 0) {
                    $innerHtml   = substr($content, $startAfterOpen, $nextClose - $startAfterOpen);
                    $totalLength = ($nextClose - $startAfterOpen) + strlen($closeTag);
                    return $innerHtml;
                }
                $pos = $nextClose + strlen($closeTag);
            }
        }

        return null;
    }

    /**
     * Decode a JSON attribute string into an associative array.
     *
     * @return array<string, mixed>
     */
    protected function decodeAttrs(string $json): array
    {
        $json = trim($json);

        if ($json === '' || $json === '{}') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a freeform (unnamed) block from raw HTML.
     */
    protected function makeFreeformBlock(string $html): Block
    {
        return new Block(
            blockName: null,
            attrs: [],
            innerBlocks: [],
            innerHTML: $html,
            innerContent: [$html],
        );
    }
}
