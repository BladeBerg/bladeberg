<?php

declare(strict_types=1);

namespace Bladeberg\Tests\Unit;

use Bladeberg\BladebergRegistry;
use PHPUnit\Framework\TestCase;

class BladebergRegistryTest extends TestCase
{
    private BladebergRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new BladebergRegistry();
    }

    public function test_initially_no_blocks_are_registered(): void
    {
        $this->assertEmpty($this->registry->getRegisteredBlocks());
    }

    public function test_register_and_check_dynamic_block(): void
    {
        $this->registry->registerDynamicBlock('bladeberg/callout', 'blocks.callout');

        $this->assertTrue($this->registry->isDynamicBlock('bladeberg/callout'));
        $this->assertTrue($this->registry->hasBlock('bladeberg/callout'));
    }

    public function test_unregistered_block_returns_false(): void
    {
        $this->assertFalse($this->registry->isDynamicBlock('bladeberg/missing'));
        $this->assertFalse($this->registry->hasBlock('bladeberg/missing'));
    }

    public function test_get_dynamic_block_view_returns_correct_path(): void
    {
        $this->registry->registerDynamicBlock('bladeberg/hero', 'blocks.hero');

        $this->assertSame('blocks.hero', $this->registry->getDynamicBlockView('bladeberg/hero'));
    }

    public function test_get_dynamic_block_view_returns_null_for_missing(): void
    {
        $this->assertNull($this->registry->getDynamicBlockView('bladeberg/ghost'));
    }

    public function test_get_registered_blocks_returns_all_entries(): void
    {
        $this->registry->registerDynamicBlock('bladeberg/callout', 'blocks.callout');
        $this->registry->registerDynamicBlock('bladeberg/hero', 'blocks.hero');

        $blocks = $this->registry->getRegisteredBlocks();

        $this->assertCount(2, $blocks);
        $this->assertArrayHasKey('bladeberg/callout', $blocks);
        $this->assertArrayHasKey('bladeberg/hero', $blocks);
        $this->assertSame('blocks.callout', $blocks['bladeberg/callout']);
    }

    public function test_registering_same_block_twice_overwrites_view(): void
    {
        $this->registry->registerDynamicBlock('bladeberg/callout', 'blocks.callout-v1');
        $this->registry->registerDynamicBlock('bladeberg/callout', 'blocks.callout-v2');

        $this->assertSame('blocks.callout-v2', $this->registry->getDynamicBlockView('bladeberg/callout'));
        $this->assertCount(1, $this->registry->getRegisteredBlocks());
    }
}
