/**
 * BladeBerg editor init layer.
 *
 * Responsibilities:
 *   1. Expose React / ReactDOM as globals for the isolated-block-editor browser
 *      build (it declares react / react-dom as webpack externals).
 *   2. Wait for window.wp.attachEditor (provided by the browser build) and mount
 *      an editor on every <textarea data-bladeberg-editor> element.
 *   3. Intercept the parent form's submit event to rewrite `<!-- wp:… -->` block
 *      comment delimiters to `<!-- bb:… -->` before the data is sent to the server.
 *   4. Restore the right-click → block "Options" menu (see installBlockContextMenu).
 *
 * Why form-submit interception (not a serialize patch)?
 *   The isolated-block-editor browser bundle only exposes two globals:
 *     window.wp.attachEditor / window.wp.detachEditor
 *   It never sets window.wp.blocks, so patching window.wp.blocks.serialize is a
 *   complete no-op. The real serializer is a private webpack closure (IA) that
 *   writes directly to textarea.value = content — there is no hook to override it
 *   from outside the bundle. Intercepting the form submit is the only reliable
 *   client-side point where we can rewrite the value before it leaves the browser.
 *
 * Intentionally imports NOTHING from @wordpress/* — all Gutenberg packages are
 * bundled at compatible versions inside the browser build.
 */
import React from 'react';
import ReactDOM from 'react-dom';
import '../css/editor.scss';
import { bladebergMediaUpload } from './media/mediaUpload.js';
import { registerApiFetchMiddleware } from './media/apiFetchMiddleware.js';
import { MediaLibraryModal } from './media/MediaLibraryModal.jsx';

// Make React available as globals for the isolated-block-editor browser build.
// It declares `externals: { react: 'React', 'react-dom': 'ReactDOM' }` in its
// webpack config, so these globals must exist before it runs.
window.React    = React;
window.ReactDOM = ReactDOM;

// Public API surface exposed for host apps.
window.Bladeberg = {
    _queue: [],

    /**
     * Register a custom block type.
     * Queues the call if the editor has not mounted yet; the queue is flushed
     * inside mountEditors() once window.wp.blocks is available.
     */
    registerBlock(name, settings) {
        if (window.wp?.blocks?.registerBlockType) {
            window.wp.blocks.registerBlockType(name, settings);
        } else {
            this._queue.push({ name, settings });
        }
    },

    /**
     * Return the current block content for a given editor textarea, with all
     * `<!-- wp:… -->` block comment prefixes rewritten to the configured prefix
     * (default `bb`, overridable via config('bladeberg.block_prefix')).
     *
     * Use this when submitting content via AJAX instead of a native <form>:
     *
     *   const content = window.Bladeberg.getContent('content');
     *   fetch('/posts', { method: 'POST', body: JSON.stringify({ content }) });
     *
     * @param {string|HTMLTextAreaElement} nameOrEl  Field name or textarea element
     * @returns {string}
     */
    getContent(nameOrEl) {
        const prefix = window.BladebergConfig?.blockPrefix ?? 'bb';
        const ta = typeof nameOrEl === 'string'
            ? document.querySelector(`textarea[name="${nameOrEl}"][data-bladeberg-editor]`)
            : nameOrEl;
        const raw = ta?.value ?? '';
        return raw.replace(/<!--\s*(\/?)wp:/g, `<!-- $1${prefix}:`);
    },
};

/**
 * Mount the block editor on every <textarea data-bladeberg-editor> element.
 * Retries up to ~3 s if the browser build hasn't finished loading yet.
 *
 * @param {number} attempts
 */
function mountEditors(attempts = 0) {
    if (!window.wp?.attachEditor) {
        if (attempts < 60) {
            setTimeout(() => mountEditors(attempts + 1), 50);
        }
        return;
    }

    // Flush any queued custom block registrations now that wp.blocks is live.
    if (window.wp?.blocks?.registerBlockType) {
        (window.Bladeberg._queue || []).forEach(({ name, settings }) => {
            window.wp.blocks.registerBlockType(name, settings);
        });
        window.Bladeberg._queue = [];
    }

    document.querySelectorAll('textarea[data-bladeberg-editor]').forEach((textarea) => {
        if (textarea.dataset.bladebergMounted) return;
        textarea.dataset.bladebergMounted = '1';

        let userSettings = {};
        const raw = textarea.getAttribute('data-settings');
        if (raw) {
            try { userSettings = JSON.parse(raw); } catch (_) {}
        }

        // The editor's internal parser (inside the browser bundle) looks for
        // <!-- wp: block comments. Pre-convert any stored configured-prefix content
        // (default bb:) so the editor can parse previously-saved content correctly.
        if (textarea.value) {
            const inPrefix = window.BladebergConfig?.blockPrefix ?? 'bb';
            const inRe = new RegExp(`<!--\\s*(\\/?)\\s*${inPrefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}:`, 'g');
            textarea.value = textarea.value.replace(inRe, '<!-- $1wp:');
        }

        const editorSettings = { ...userSettings };

        // Wire the media manager based on config('bladeberg.media.mode').
        //
        //   disabled / link — nothing wired; Gutenberg blocks show a plain URL input.
        //   select          — media library modal (browse only, no upload).
        //   upload          — full media library: browse + upload.
        //
        // BladebergConfig is injected as an inline script by editor.blade.php.
        const mediaMode = window.BladebergConfig?.mediaMode ?? 'disabled';

        if (mediaMode === 'select' || mediaMode === 'upload') {
            // Intercept /wp/v2/media API calls → redirect to /bladeberg/media
            registerApiFetchMiddleware();

            // Replace the default media component with our modal.
            // The modal reads mediaMode from BladebergConfig to decide whether
            // to show the upload zone.
            if (window.wp?.hooks?.addFilter) {
                window.wp.hooks.addFilter(
                    'editor.MediaUpload',
                    'bladeberg/media-upload',
                    () => MediaLibraryModal
                );
            }

            if (mediaMode === 'upload') {
                // Only upload mode provides the mediaUpload callback.
                // Without it Gutenberg falls back to its built-in URL input.
                editorSettings.editor = {
                    ...(editorSettings.editor ?? {}),
                    mediaUpload: bladebergMediaUpload,
                };

                // Grant upload permission so the image block shows the upload UI.
                if (window.wp?.data) {
                    try {
                        window.wp.data
                            .dispatch('core')
                            ?.receiveUserPermission?.('create/media', true);
                    } catch (_) {
                        // core store might not expose receiveUserPermission in all versions
                    }
                }
            }
        }

        window.wp.attachEditor(textarea, editorSettings);

        // ── Form-submit interceptor ──────────────────────────────────────────
        // The isolated-block-editor browser bundle writes `<!-- wp:… -->` block
        // comment delimiters to textarea.value via a hardcoded internal closure
        // that cannot be patched from outside the bundle. The form submit event
        // fires AFTER the bundle has finished writing to the textarea but BEFORE
        // the browser encodes and sends the request — this is the only reliable
        // client-side point where we can rewrite wp: → bb: in the stored value.
        const form = textarea.closest('form');
        if (form && !form.dataset.bbSubmitWired) {
            form.dataset.bbSubmitWired = '1';
            form.addEventListener('submit', () => {
                const outPrefix = window.BladebergConfig?.blockPrefix ?? 'bb';
                form.querySelectorAll('textarea[data-bladeberg-editor]').forEach((ta) => {
                    if (ta.value) {
                        ta.value = ta.value.replace(/<!--\s*(\/?)wp:/g, `<!-- $1${outPrefix}:`);
                    }
                });
            }, { capture: true }); // capture: true ensures we run before any preventDefault
        }
    });
}

/**
 * Replace visible "WordPress" / "wp:" text with "BladeBerg" / "bb:" throughout
 * the editor UI.
 *
 * Branding replacements (applied in order):
 *   WordPress          → BladeBerg
 *   WP (standalone)    → BB
 *   wp:blockname       → bb:blockname  (block-comment notation in error messages)
 *   core/paragraph     → bb/paragraph  (block type names in validation notices)
 *   core/              → bb/           (other core/ prefixed block type labels)
 *
 * TEXTAREA and INPUT elements are intentionally excluded — editing them would
 * corrupt the stored block markup that the server parser depends on.
 *
 * The Code Editor mode's raw-markup textarea gets a separate visual overlay
 * (see installCodeEditorOverlay) so users see "bb:" notation without the
 * underlying stored value being touched.
 */
function applyBranding() {
    const prefix = window.BladebergConfig?.blockPrefix ?? 'bb';
    const REPLACEMENTS = [
        { from: /WordPress/g,                    to: 'BladeBerg' },
        { from: /\bWP\b(?![-_])/g,               to: 'BB' },
        // Block-comment notation: wp:paragraph → <prefix>:paragraph
        { from: /\bwp:([\w-]+)/g,                to: `${prefix}:$1` },
        // Core block type names in validation notices: core/paragraph → <prefix>/paragraph
        { from: /\bcore\/([\w-]+)/g,              to: `${prefix}/$1` },
    ];

    /**
     * Walk all text nodes under `root` and apply substitutions.
     * Skips script/style/input/textarea elements — those hold live editor data.
     *
     * @param {Node} root
     */
    function patchTextNodes(root) {
        if (!root) return;

        if (root.nodeType === Node.TEXT_NODE) {
            applyToTextNode(root);
            return;
        }

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                const tag = node.parentElement?.tagName ?? '';
                if (['SCRIPT', 'STYLE', 'INPUT', 'TEXTAREA'].includes(tag)) {
                    return NodeFilter.FILTER_REJECT;
                }
                const hasMatch = REPLACEMENTS.some(r => {
                    r.from.lastIndex = 0;
                    return r.from.test(node.textContent);
                });
                return hasMatch ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            },
        });

        // Collect first, then mutate — avoids TreeWalker invalidation.
        const nodes = [];
        let n;
        while ((n = walker.nextNode())) nodes.push(n);
        nodes.forEach(applyToTextNode);
    }

    function applyToTextNode(node) {
        let text = node.textContent;
        for (const { from, to } of REPLACEMENTS) {
            from.lastIndex = 0;
            text = text.replace(from, to);
        }
        if (text !== node.textContent) node.textContent = text;
    }

    // Initial pass — scan everything already in the DOM.
    patchTextNodes(document.body);

    // Watch for lazily added nodes: inserter panel, popovers, modal dialogs.
    const observer = new MutationObserver((mutations) => {
        for (const { addedNodes } of mutations) {
            for (const node of addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) patchTextNodes(node);
            }
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Expose for cleanup (e.g. during hot-reload or SPA navigation)
    window.Bladeberg._brandingObserver = observer;

    // Overlay the Code Editor textarea so its raw view shows bb: instead of wp:
    installCodeEditorOverlay();
}

/**
 * The Code Editor mode (Ctrl+Shift+Alt+M in Gutenberg) renders an editable
 * `<textarea>` with raw block markup containing `<!-- wp:paragraph -->` etc.
 * We cannot replace text in that textarea without corrupting the block data.
 *
 * Instead we layer a read-only `<pre>` on top that shows the branded version,
 * and hide the textarea visually (it stays active for keyboard events/value).
 * When the user types, the overlay content is refreshed from the textarea value.
 */
function installCodeEditorOverlay() {
    const OVERLAY_ATTR = 'data-bb-code-overlay';

    function brandRawMarkup(raw) {
        const prefix = window.BladebergConfig?.blockPrefix ?? 'bb';
        return raw
            .replace(/wp:([\w-]+)/g,    `${prefix}:$1`)
            .replace(/WordPress/g,      'BladeBerg')
            .replace(/core\/([\w-]+)/g, `${prefix}/$1`);
    }

    function attachOverlay(textarea) {
        if (textarea.hasAttribute(OVERLAY_ATTR)) return;
        textarea.setAttribute(OVERLAY_ATTR, '1');

        // The overlay is positioned absolutely over the textarea —
        // make sure the parent provides a positioning context.
        const parent = textarea.parentElement;
        if (parent && getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
        }

        const pre = document.createElement('pre');
        pre.className   = 'bb-code-editor-overlay';
        pre.textContent = brandRawMarkup(textarea.value);
        pre.setAttribute('aria-hidden', 'true');

        textarea.parentNode?.insertBefore(pre, textarea.nextSibling);

        // Keep overlay in sync as user types — the textarea keeps real value
        textarea.addEventListener('input', () => {
            pre.textContent = brandRawMarkup(textarea.value);
        });
    }

    // Find any existing code editor textareas
    document.querySelectorAll('.editor-post-text-editor, .block-editor-plain-text')
        .forEach(attachOverlay);

    // Watch for the code editor being opened (deferred / lazily mounted)
    const overlayObserver = new MutationObserver((mutations) => {
        for (const { addedNodes } of mutations) {
            for (const node of addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;
                node.querySelectorAll?.('.editor-post-text-editor, .block-editor-plain-text')
                    .forEach(attachOverlay);
                if (
                    node.matches?.('.editor-post-text-editor') ||
                    node.matches?.('.block-editor-plain-text')
                ) {
                    attachOverlay(node);
                }
            }
        }
    });
    overlayObserver.observe(document.body, { childList: true, subtree: true });
    window.Bladeberg._overlayObserver = overlayObserver;
}

/**
 * Restore the right-click → block "Options" context menu.
 *
 * isolated-block-editor v2.30 (its final release) ships no context-menu feature
 * and exposes no wp.data store we could call to open one programmatically. We
 * emulate Gutenberg's behaviour with the DOM only:
 *
 *   1. On right-click inside a block ([data-block]) we suppress the browser's
 *      native menu and replay a left-button mousedown/mouseup on the target so
 *      the block-editor selects that block (selection is what renders the
 *      block toolbar).
 *   2. Once the toolbar mounts we click its existing "Options" (⋮) toggle
 *      (`.block-editor-block-settings-menu`), which already contains the full
 *      Gutenberg action list: Copy, Duplicate, Move to, Edit as HTML, Group,
 *      Lock, Remove, etc. No custom menu UI is built or maintained.
 *
 * Right-clicks outside any editor block fall through untouched, so the native
 * browser menu (spellcheck, etc.) still works everywhere else on the page.
 */
function installBlockContextMenu() {
    const EDITOR_SCOPE =
        '.block-editor-block-list__layout, .editor-styles-wrapper, .iso-editor';

    function openOptionsMenu(attempt = 0) {
        const toggle =
            document.querySelector('.block-editor-block-settings-menu__toggle') ||
            document.querySelector('.block-editor-block-settings-menu button') ||
            document.querySelector('button[aria-label="Options"]') ||
            document.querySelector('.block-editor-block-settings-menu [role="button"]');

        if (toggle) {
            toggle.click();
            return;
        }
        // Toolbar may still be mounting after selection — retry briefly.
        if (attempt < 10) setTimeout(() => openOptionsMenu(attempt + 1), 30);
    }

    document.addEventListener(
        'contextmenu',
        (event) => {
            const target  = event.target;
            const blockEl = target?.closest?.('[data-block]');
            if (!blockEl || !blockEl.closest(EDITOR_SCOPE)) {
                return; // not inside an editor block → keep the native menu
            }

            event.preventDefault();

            // Select the block by replaying a left-button press on the target.
            const opts = { bubbles: true, cancelable: true, view: window, button: 0 };
            target.dispatchEvent(new MouseEvent('mousedown', opts));
            target.dispatchEvent(new MouseEvent('mouseup', opts));
            if (typeof blockEl.focus === 'function') {
                try { blockEl.focus({ preventScroll: true }); } catch (_) {}
            }

            // Let selection settle, then open the existing Options dropdown.
            setTimeout(() => openOptionsMenu(), 30);
        },
        { capture: true }
    );
}

// With `defer` (used by the Blade component), this script and the browser-build
// script both execute before DOMContentLoaded. Listening for DOMContentLoaded
// guarantees isolated-block-editor.js has already run and set window.wp.attachEditor.
document.addEventListener('DOMContentLoaded', () => {
    mountEditors();
    applyBranding();
    installBlockContextMenu();
});
