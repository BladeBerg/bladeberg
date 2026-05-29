<?php

declare(strict_types=1);

namespace Bladeberg\Tests\Unit;

use Bladeberg\Support\HtmlBranding;
use Bladeberg\Tests\TestCase;

final class HtmlBrandingTest extends TestCase
{
    public function test_it_normalizes_bb_block_classes_for_render(): void
    {
        $html = '<p class="bb-block-paragraph">Hello</p>';

        $this->assertSame(
            '<p class="wp-block-paragraph">Hello</p>',
            HtmlBranding::normalizeForRender($html, 'bb')
        );
    }

    public function test_it_leaves_wp_classes_unchanged_when_prefix_is_wp(): void
    {
        $html = '<p class="wp-block-paragraph">Hello</p>';

        $this->assertSame($html, HtmlBranding::normalizeForRender($html, 'wp'));
    }
}
