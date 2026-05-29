<?php

declare(strict_types=1);

namespace Bladeberg\Tests\Unit;

use Bladeberg\Support\BbContent;
use PHPUnit\Framework\TestCase;

/**
 * Covers the wp: ↔ bb: rebranding performed by BbContent.
 *
 * These tests run without booting the Laravel container, so BbContent::prefix()
 * falls back to its default value of 'bb' (see BbContent::prefix()).
 */
class BbContentTest extends TestCase
{
    // -----------------------------------------------------------------------
    // normalize(): wp: → bb:
    // -----------------------------------------------------------------------

    public function test_normalize_converts_opening_delimiter(): void
    {
        $this->assertSame(
            '<!-- bb:paragraph -->',
            BbContent::normalize('<!-- wp:paragraph -->')
        );
    }

    public function test_normalize_converts_closing_delimiter(): void
    {
        $this->assertSame(
            '<!-- /bb:paragraph -->',
            BbContent::normalize('<!-- /wp:paragraph -->')
        );
    }

    public function test_normalize_converts_full_block(): void
    {
        $input    = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
        $expected = '<!-- bb:paragraph --><p>Hello</p><!-- /bb:paragraph -->';

        $this->assertSame($expected, BbContent::normalize($input));
    }

    public function test_normalize_converts_self_closing_block(): void
    {
        $this->assertSame(
            '<!-- bb:separator /-->',
            BbContent::normalize('<!-- wp:separator /-->')
        );
    }

    public function test_normalize_converts_namespaced_block_with_attributes(): void
    {
        $this->assertSame(
            '<!-- bb:core/heading {"level":2} -->',
            BbContent::normalize('<!-- wp:core/heading {"level":2} -->')
        );
    }

    public function test_normalize_converts_all_blocks_in_document(): void
    {
        $input = implode("\n", [
            '<!-- wp:heading --><h2>Title</h2><!-- /wp:heading -->',
            '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->',
        ]);

        $result = BbContent::normalize($input);

        $this->assertStringNotContainsString('wp:', $result);
        $this->assertSame(4, substr_count($result, 'bb:'));
    }

    public function test_normalize_collapses_whitespace_variants(): void
    {
        // No space and extra spaces after <!-- both normalize to a single space.
        $this->assertSame('<!-- bb:paragraph -->', BbContent::normalize('<!--wp:paragraph -->'));
        $this->assertSame('<!-- bb:paragraph -->', BbContent::normalize('<!--   wp:paragraph -->'));
    }

    public function test_normalize_leaves_plain_html_untouched(): void
    {
        $html = '<p>Just some <strong>HTML</strong> with no blocks.</p>';

        $this->assertSame($html, BbContent::normalize($html));
    }

    public function test_normalize_is_idempotent(): void
    {
        $once  = BbContent::normalize('<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->');
        $twice = BbContent::normalize($once);

        $this->assertSame($once, $twice);
    }

    // -----------------------------------------------------------------------
    // denormalize(): bb: → wp:
    // -----------------------------------------------------------------------

    public function test_denormalize_converts_opening_and_closing(): void
    {
        $input    = '<!-- bb:paragraph --><p>Hi</p><!-- /bb:paragraph -->';
        $expected = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';

        $this->assertSame($expected, BbContent::denormalize($input));
    }

    public function test_denormalize_converts_self_closing_block(): void
    {
        $this->assertSame(
            '<!-- wp:separator /-->',
            BbContent::denormalize('<!-- bb:separator /-->')
        );
    }

    public function test_denormalize_leaves_plain_html_untouched(): void
    {
        $html = '<p>Nothing to convert here.</p>';

        $this->assertSame($html, BbContent::denormalize($html));
    }

    // -----------------------------------------------------------------------
    // Round trips
    // -----------------------------------------------------------------------

    public function test_normalize_then_denormalize_restores_original_wp(): void
    {
        $wp = '<!-- wp:columns --><!-- wp:column --><p>L</p><!-- /wp:column --><!-- /wp:columns -->';

        $this->assertSame($wp, BbContent::denormalize(BbContent::normalize($wp)));
    }

    public function test_denormalize_then_normalize_restores_original_bb(): void
    {
        $bb = '<!-- bb:columns --><!-- bb:column --><p>L</p><!-- /bb:column --><!-- /bb:columns -->';

        $this->assertSame($bb, BbContent::normalize(BbContent::denormalize($bb)));
    }
}
