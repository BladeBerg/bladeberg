<?php

declare(strict_types=1);

namespace Bladeberg\Parser;

/**
 * Represents a single parsed Gutenberg block.
 *
 * Content stored in `innerHTML` is always admin-authored serialized block HTML
 * and is intentionally rendered unescaped. Do NOT pass untrusted user input
 * through the editor or store it directly in the content column.
 */
final class Block
{
    /**
     * @param string|null  $blockName    Registered block name, e.g. "core/paragraph". Null for freeform HTML.
     * @param array<string, mixed> $attrs       Block attributes decoded from the opening comment.
     * @param Block[]      $innerBlocks  Parsed child blocks (for container blocks like columns).
     * @param string       $innerHTML    The rendered HTML content of the block.
     * @param array<string> $innerContent Raw inner content segments (mirrors WP_Block_Parser_Block).
     * @param bool         $isSelfClosing True for void blocks such as <!-- wp:image /-->.
     */
    public function __construct(
        public readonly ?string $blockName,
        public readonly array $attrs,
        public readonly array $innerBlocks,
        public readonly string $innerHTML,
        public readonly array $innerContent,
        public readonly bool $isSelfClosing = false,
    ) {}

    /**
     * Whether this is a named (registered) block or a freeform HTML fragment.
     */
    public function isNamed(): bool
    {
        return $this->blockName !== null;
    }

    /**
     * Whether this block has child blocks.
     */
    public function hasInnerBlocks(): bool
    {
        return count($this->innerBlocks) > 0;
    }

    /**
     * Retrieve a single attribute with an optional default.
     *
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }
}
