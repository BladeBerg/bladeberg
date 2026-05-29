@props(['name', 'value' => '', 'id' => null])

@php
    $inputId  = $id ?? 'bladeberg-input-' . uniqid();
    $settings = json_encode([
        'alignWide'         => config('bladeberg.align_wide', true),
        'hasFixedToolbar'   => config('bladeberg.has_fixed_toolbar', false),
        'allowedBlockTypes' => config('bladeberg.allowed_blocks'),
    ]);

    /**
     * Map each config key to the filename it controls in public/vendor/bladeberg/.
     * Load order matters: Gutenberg chrome first, then component styles,
     * then block styles, then our own overrides last.
     */
    $bladebergStylesheets = [
        'core'          => 'core.css',
        'iso'           => 'isolated-block-editor.css',
        'components'    => 'components.css',
        'blocks_style'  => 'blocks-style.css',
        'blocks_editor' => 'blocks-editor.css',
    ];
@endphp

<div class="bladeberg-container">
    {{--
        The textarea is the direct mount target.
        window.wp.attachEditor() (from isolated-block-editor browser build) hides this
        element and inserts the block editor right after it in the DOM.
    --}}
    <textarea
        id="{{ $inputId }}"
        name="{{ $name }}"
        data-bladeberg-editor
        data-settings="{{ $settings }}"
    >{{ old($name, $value) }}</textarea>
</div>

@once
    {{--
        Load order:
          1. Stylesheet groups from config('bladeberg.styles.*')  — core Gutenberg CSS
          2. bladeberg-editor.css                                 — container overrides + CSS vars
          3. BladebergConfig inline script                        — runtime config for JS layer
          4. bladeberg-editor.iife.js (defer)                     — React globals + mount logic
          5. isolated-block-editor.js (defer)                     — full editor, consumes window.React

        With `defer`, scripts execute in document order BEFORE DOMContentLoaded, so by the
        time our mount handler fires, window.wp.attachEditor is already available.
    --}}
    @foreach($bladebergStylesheets as $key => $file)
        @if(config("bladeberg.styles.{$key}", true))
            <link rel="stylesheet" href="{{ asset("vendor/bladeberg/{$file}") }}">
        @endif
    @endforeach
    <link rel="stylesheet" href="{{ asset('vendor/bladeberg/bladeberg-editor.css') }}">

    @php
        // Resolve the active media mode, with backward-compat for the old 'enabled' key.
        $bbMediaMode = config('bladeberg.media.mode', 'disabled');
        if ($bbMediaMode === 'disabled' && config('bladeberg.media.enabled', false)) {
            $bbMediaMode = 'upload';
        }

        // API URL is only needed when server-side file routes are active.
        $bbNeedsApi    = in_array($bbMediaMode, ['select', 'upload']);
        $bbMediaApiUrl = $bbNeedsApi
            ? url(config('bladeberg.media.route_prefix', 'bladeberg') . '/media')
            : '';

        $bbBlockPrefix = config('bladeberg.block_prefix', 'bb');
    @endphp

    {{-- Runtime configuration object consumed by editor.jsx and the media JS modules --}}
    <script>
    window.BladebergConfig = {
        blockPrefix:  "{{ $bbBlockPrefix }}",
        mediaMode:    "{{ $bbMediaMode }}",
        mediaApiUrl:  "{{ $bbMediaApiUrl }}",
        csrfToken:    "{{ csrf_token() }}"
    };
    </script>

    <script src="{{ asset('vendor/bladeberg/bladeberg-editor.iife.js') }}" defer></script>
    <script src="{{ asset('vendor/bladeberg/isolated-block-editor.js') }}" defer></script>
@endonce
