<?php

declare(strict_types=1);

namespace Bladeberg;

use Bladeberg\Support\BbContent;

class BladebergRegistry
{
    /**
     * Map of block name → Blade view path.
     *
     * @var array<string, string>
     */
    protected array $dynamicBlocks = [];

    /**
     * Register a Gutenberg block name as a dynamic block rendered by a Blade view.
     *
     * @param string $blockName The Gutenberg block name, e.g. "bladeberg/callout"
     * @param string $view      The Blade view path, e.g. "blocks.callout"
     */
    public function registerDynamicBlock(string $blockName, string $view): void
    {
        $this->dynamicBlocks[$blockName] = $view;
    }

    /**
     * Check whether a block name has been registered as a dynamic block.
     */
    public function isDynamicBlock(string $blockName): bool
    {
        return isset($this->dynamicBlocks[$blockName]);
    }

    /**
     * Alias for isDynamicBlock() — more expressive in conditionals.
     */
    public function hasBlock(string $blockName): bool
    {
        return $this->isDynamicBlock($blockName);
    }

    /**
     * Return the Blade view path for a registered dynamic block, or null if not registered.
     */
    public function getDynamicBlockView(string $blockName): ?string
    {
        return $this->dynamicBlocks[$blockName] ?? null;
    }

    /**
     * Return all registered dynamic block names and their view mappings.
     *
     * @return array<string, string>
     */
    public function getRegisteredBlocks(): array
    {
        return $this->dynamicBlocks;
    }

    // -----------------------------------------------------------------------
    // Content normalization helpers
    // -----------------------------------------------------------------------

    /**
     * Normalize block-comment prefixes for storage: `wp:` → `bb:`.
     *
     * Use this in controllers or model mutators to ensure content saved to the
     * database always uses BladeBerg's `bb:` prefix regardless of whether the
     * JS form-submit interceptor ran (e.g. for AJAX requests or imports).
     *
     *   $post->content = Bladeberg::normalize($request->input('content'));
     *
     * @param string $html Raw block HTML that may contain `<!-- wp:… -->` delimiters
     * @return string Normalized HTML with `<!-- bb:… -->` delimiters
     */
    public function normalize(string $html): string
    {
        return BbContent::normalize($html);
    }

    /**
     * Denormalize block-comment prefixes: `bb:` → `wp:`.
     *
     * Use this when passing BladeBerg-stored content to tools that expect the
     * original WordPress block format (WP REST API bridges, third-party plugins,
     * Gutenberg JS re-hydration, etc.).
     *
     *   $wpCompatibleContent = Bladeberg::denormalize($post->content);
     *
     * @param string $html Stored HTML with `<!-- bb:… -->` delimiters
     * @return string HTML with `<!-- wp:… -->` delimiters restored
     */
    public function denormalize(string $html): string
    {
        return BbContent::denormalize($html);
    }
}
