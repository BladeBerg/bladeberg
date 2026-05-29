<?php

declare(strict_types=1);

namespace Bladeberg\Tests\Unit;

use Bladeberg\Parser\Block;
use Bladeberg\Parser\BlockParser;
use PHPUnit\Framework\TestCase;

/**
 * BladeBerg stores content with the `bb:` block-comment prefix, so the standard
 * tests below use `bb:`. The parser also accepts Gutenberg's original `wp:`
 * prefix (for imports / third-party content) — see the backward-compatibility
 * section at the bottom.
 */
class BlockParserTest extends TestCase
{
    private BlockParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BlockParser();
    }

    // -----------------------------------------------------------------------
    // Empty / edge cases
    // -----------------------------------------------------------------------

    public function test_empty_string_returns_empty_array(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    public function test_whitespace_only_returns_empty_array(): void
    {
        $this->assertSame([], $this->parser->parse('   '));
    }

    // -----------------------------------------------------------------------
    // Freeform HTML (no block comments)
    // -----------------------------------------------------------------------

    public function test_freeform_html_becomes_unnamed_block(): void
    {
        $blocks = $this->parser->parse('<p>Hello world</p>');

        $this->assertCount(1, $blocks);
        $this->assertNull($blocks[0]->blockName);
        $this->assertSame('<p>Hello world</p>', $blocks[0]->innerHTML);
    }

    // -----------------------------------------------------------------------
    // Standard paragraph block
    // -----------------------------------------------------------------------

    public function test_parses_paragraph_block(): void
    {
        $content = "<!-- bb:paragraph -->\n<p>Test content</p>\n<!-- /bb:paragraph -->";

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('paragraph', $blocks[0]->blockName);
        $this->assertStringContainsString('Test content', $blocks[0]->innerHTML);
        $this->assertFalse($blocks[0]->isSelfClosing);
    }

    // -----------------------------------------------------------------------
    // Block with namespace (core/heading)
    // -----------------------------------------------------------------------

    public function test_parses_namespaced_block_name(): void
    {
        $content = '<!-- bb:core/heading {"level":2} --><h2>Title</h2><!-- /bb:core/heading -->';

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('core/heading', $blocks[0]->blockName);
        $this->assertSame(['level' => 2], $blocks[0]->attrs);
    }

    // -----------------------------------------------------------------------
    // Block attributes
    // -----------------------------------------------------------------------

    public function test_parses_block_attributes(): void
    {
        $content = '<!-- bb:image {"id":42,"sizeSlug":"large"} --><figure></figure><!-- /bb:image -->';

        $blocks = $this->parser->parse($content);

        $this->assertSame(['id' => 42, 'sizeSlug' => 'large'], $blocks[0]->attrs);
        $this->assertSame(42, $blocks[0]->getAttribute('id'));
        $this->assertSame('large', $blocks[0]->getAttribute('sizeSlug'));
        $this->assertNull($blocks[0]->getAttribute('missing'));
        $this->assertSame('default', $blocks[0]->getAttribute('missing', 'default'));
    }

    // -----------------------------------------------------------------------
    // Self-closing block
    // -----------------------------------------------------------------------

    public function test_parses_self_closing_block(): void
    {
        $content = '<!-- bb:separator /-->';

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('separator', $blocks[0]->blockName);
        $this->assertTrue($blocks[0]->isSelfClosing);
        $this->assertSame('', $blocks[0]->innerHTML);
    }

    public function test_parses_self_closing_block_with_attributes(): void
    {
        $content = '<!-- bb:spacer {"height":"50px"} /-->';

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('spacer', $blocks[0]->blockName);
        $this->assertTrue($blocks[0]->isSelfClosing);
        $this->assertSame(['height' => '50px'], $blocks[0]->attrs);
    }

    // -----------------------------------------------------------------------
    // Multiple top-level blocks
    // -----------------------------------------------------------------------

    public function test_parses_multiple_sequential_blocks(): void
    {
        $content = implode("\n", [
            '<!-- bb:paragraph --><p>First</p><!-- /bb:paragraph -->',
            '<!-- bb:paragraph --><p>Second</p><!-- /bb:paragraph -->',
        ]);

        $blocks = $this->parser->parse($content);

        $this->assertCount(2, $blocks);
        $this->assertStringContainsString('First', $blocks[0]->innerHTML);
        $this->assertStringContainsString('Second', $blocks[1]->innerHTML);
    }

    // -----------------------------------------------------------------------
    // Mixed block + freeform HTML
    // -----------------------------------------------------------------------

    public function test_leading_freeform_html_is_preserved(): void
    {
        $content = '<p>Intro</p><!-- bb:paragraph --><p>Block</p><!-- /bb:paragraph -->';

        $blocks = $this->parser->parse($content);

        $this->assertCount(2, $blocks);
        $this->assertNull($blocks[0]->blockName);
        $this->assertStringContainsString('Intro', $blocks[0]->innerHTML);
        $this->assertSame('paragraph', $blocks[1]->blockName);
    }

    // -----------------------------------------------------------------------
    // Nested / inner blocks
    // -----------------------------------------------------------------------

    public function test_parses_nested_inner_blocks(): void
    {
        $content = implode('', [
            '<!-- bb:columns -->',
            '<!-- bb:column --><p>Left</p><!-- /bb:column -->',
            '<!-- bb:column --><p>Right</p><!-- /bb:column -->',
            '<!-- /bb:columns -->',
        ]);

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('columns', $blocks[0]->blockName);
        $this->assertTrue($blocks[0]->hasInnerBlocks());
        $this->assertCount(2, $blocks[0]->innerBlocks);
        $this->assertSame('column', $blocks[0]->innerBlocks[0]->blockName);
        $this->assertSame('column', $blocks[0]->innerBlocks[1]->blockName);
    }

    // -----------------------------------------------------------------------
    // Block value object helpers
    // -----------------------------------------------------------------------

    public function test_block_is_named_returns_correct_values(): void
    {
        $named   = new Block('core/paragraph', [], [], '<p>Hi</p>', ['<p>Hi</p>']);
        $unnamed = new Block(null, [], [], '<p>Hi</p>', ['<p>Hi</p>']);

        $this->assertTrue($named->isNamed());
        $this->assertFalse($unnamed->isNamed());
    }

    public function test_block_has_inner_blocks(): void
    {
        $child  = new Block('core/column', [], [], '', []);
        $parent = new Block('core/columns', [], [$child], '', []);

        $this->assertTrue($parent->hasInnerBlocks());
        $this->assertFalse($child->hasInnerBlocks());
    }

    // -----------------------------------------------------------------------
    // Gutenberg `wp:` backward compatibility
    //
    // BladeBerg saves `bb:`, but the parser must still accept the original
    // Gutenberg `wp:` prefix so imported / third-party content keeps working.
    // -----------------------------------------------------------------------

    public function test_parses_legacy_wp_paragraph_block(): void
    {
        $content = "<!-- wp:paragraph -->\n<p>Gutenberg content</p>\n<!-- /wp:paragraph -->";

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('paragraph', $blocks[0]->blockName);
        $this->assertStringContainsString('Gutenberg content', $blocks[0]->innerHTML);
        $this->assertFalse($blocks[0]->isSelfClosing);
    }

    public function test_parses_legacy_wp_self_closing_block(): void
    {
        $blocks = $this->parser->parse('<!-- wp:separator /-->');

        $this->assertCount(1, $blocks);
        $this->assertSame('separator', $blocks[0]->blockName);
        $this->assertTrue($blocks[0]->isSelfClosing);
    }

    public function test_parses_legacy_wp_nested_inner_blocks(): void
    {
        $content = implode('', [
            '<!-- wp:columns -->',
            '<!-- wp:column --><p>Left</p><!-- /wp:column -->',
            '<!-- wp:column --><p>Right</p><!-- /wp:column -->',
            '<!-- /wp:columns -->',
        ]);

        $blocks = $this->parser->parse($content);

        $this->assertCount(1, $blocks);
        $this->assertSame('columns', $blocks[0]->blockName);
        $this->assertTrue($blocks[0]->hasInnerBlocks());
        $this->assertCount(2, $blocks[0]->innerBlocks);
    }

    public function test_parses_mixed_bb_and_wp_prefixes(): void
    {
        $content = implode("\n", [
            '<!-- bb:paragraph --><p>BladeBerg</p><!-- /bb:paragraph -->',
            '<!-- wp:paragraph --><p>Gutenberg</p><!-- /wp:paragraph -->',
        ]);

        $blocks = $this->parser->parse($content);

        $this->assertCount(2, $blocks);
        $this->assertSame('paragraph', $blocks[0]->blockName);
        $this->assertSame('paragraph', $blocks[1]->blockName);
        $this->assertStringContainsString('BladeBerg', $blocks[0]->innerHTML);
        $this->assertStringContainsString('Gutenberg', $blocks[1]->innerHTML);
    }
}
