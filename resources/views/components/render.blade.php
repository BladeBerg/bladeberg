@props(['content' => ''])

@php
    $parser   = new \Bladeberg\Parser\BlockParser();
    $blocks   = $parser->parse($content ?? '');
    $registry = app('bladeberg');
@endphp

{{--
    Load per-block frontend styles once per page so rendered content looks
    identical to what the editor shows. blocks-style.css comes from
    @wordpress/block-library and is safe to load on view/show pages; it only
    targets .wp-block-* classes and never touches the editor chrome.
--}}
@once('bladeberg-render-styles')
    <link rel="stylesheet" href="{{ asset('vendor/bladeberg/blocks-style.css') }}">
@endonce

<div class="bladeberg-content bb-content">
    @foreach ($blocks as $block)
        @if ($block->isNamed() && $registry->hasBlock($block->blockName))
            @include($registry->getDynamicBlockView($block->blockName), [
                'attributes'   => $block->attrs,
                'innerContent' => $block->innerHTML,
                'innerBlocks'  => $block->innerBlocks,
                'block'        => $block,
            ])
        @else
            {!! $block->innerHTML !!}
        @endif
    @endforeach
</div>
