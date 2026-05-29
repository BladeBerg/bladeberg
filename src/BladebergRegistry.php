<?php

declare(strict_types=1);

namespace Bladeberg;

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

    /**
     * Render stored block content (configured prefix) to HTML.
     *
     * Mirrors the <x-bladeberg-render> Blade component, so server-rendered and
     * API/headless consumers produce identical markup. Useful for JSON render
     * endpoints, queued jobs, sitemaps, RSS feeds, etc.
     *
     *   $html = Bladeberg::render($post->content);
     *
     * @param string $content Block HTML with the configured prefix (e.g. bb:)
     * @return string Rendered HTML
     */
    public function render(string $content): string
    {
        return view('bladeberg::components.render', ['content' => $content])->render();
    }
}
