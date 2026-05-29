<?php

declare(strict_types=1);

namespace Bladeberg\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerDynamicBlock(string $blockName, string $view)
 * @method static bool isDynamicBlock(string $blockName)
 * @method static bool hasBlock(string $blockName)
 * @method static string|null getDynamicBlockView(string $blockName)
 * @method static array<string, string> getRegisteredBlocks()
 * @method static string render(string $content)
 *
 * @see \Bladeberg\BladebergRegistry
 */
class Bladeberg extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bladeberg';
    }
}
