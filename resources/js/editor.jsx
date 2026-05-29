/**
 * BladeBerg Blade entry (IIFE build → public/vendor/bladeberg/bladeberg-editor.iife.js).
 *
 * This is the classic, server-rendered Blade integration. It:
 *   1. Exposes React / ReactDOM as globals for the isolated-block-editor browser
 *      build, which is loaded via a separate <script defer> tag by editor.blade.php.
 *   2. Scans the page for <textarea data-bladeberg-editor> elements and mounts the
 *      shared createEditor() core on each.
 *   3. Intercepts the parent form's submit so the textarea is rewritten from
 *      Gutenberg's `wp:` delimiters to the configured prefix before the request is
 *      sent — the classic, form-based save path.
 *
 * The headless / SPA path lives in index.js (npm) and shares the same core under
 * resources/js/core/. Intentionally imports NOTHING from @wordpress/* — all
 * Gutenberg packages live inside the browser build loaded at runtime.
 */
import React from 'react';
import ReactDOM from 'react-dom';
import '../css/editor.scss';
import { createEditor, registerBlock } from './core/createEditor.js';
import { brandContent } from './core/config.js';

// Must run synchronously before the deferred isolated-block-editor.js script
// executes, since that bundle reads these globals.
window.React = React;
window.ReactDOM = ReactDOM;

// Public API surface for host apps using the Blade integration.
window.Bladeberg = {
    ...(window.Bladeberg ?? {}),
    _queue: window.Bladeberg?._queue ?? [],

    registerBlock,

    /**
     * Return the current branded block content for a given editor textarea.
     * Use this when submitting via AJAX instead of a native <form>:
     *
     *   const content = window.Bladeberg.getContent('content');
     *   fetch('/posts', { method: 'POST', body: JSON.stringify({ content }) });
     *
     * @param {string|HTMLTextAreaElement} nameOrEl
     * @returns {string}
     */
    getContent(nameOrEl) {
        const prefix = window.BladebergConfig?.blockPrefix ?? 'bb';
        const ta = typeof nameOrEl === 'string'
            ? document.querySelector(`textarea[name="${nameOrEl}"][data-bladeberg-editor]`)
            : nameOrEl;
        return brandContent(ta, prefix);
    },

    /** Programmatic headless mount (mirrors the npm createEditor export). */
    createEditor,
};

function mountEditors() {
    document.querySelectorAll('textarea[data-bladeberg-editor]').forEach((textarea) => {
        if (textarea.dataset.bladebergMounted) return;

        let userSettings = {};
        const raw = textarea.getAttribute('data-settings');
        if (raw) {
            try { userSettings = JSON.parse(raw); } catch (_) {}
        }

        createEditor({ target: textarea, settings: userSettings })
            .catch((err) => console.error('[BladeBerg] Failed to mount editor:', err));

        wireFormInterceptor(textarea);
    });
}

/**
 * Rewrite `wp:` → configured prefix in every BladeBerg textarea of the parent
 * form, at submit time (capture phase, before any preventDefault). This is the
 * only reliable client-side point to brand the value the browser is about to send.
 *
 * @param {HTMLTextAreaElement} textarea
 */
function wireFormInterceptor(textarea) {
    const form = textarea.closest('form');
    if (!form || form.dataset.bbSubmitWired) return;
    form.dataset.bbSubmitWired = '1';

    form.addEventListener('submit', () => {
        const prefix = window.BladebergConfig?.blockPrefix ?? 'bb';
        form.querySelectorAll('textarea[data-bladeberg-editor]').forEach((ta) => {
            if (ta.value) {
                ta.value = ta.value.replace(/<!--\s*(\/?)wp:/g, `<!-- $1${prefix}:`);
            }
        });
    }, { capture: true });
}

// With `defer`, this script and the browser build both run before
// DOMContentLoaded, so window.wp.attachEditor is available by the time we mount.
document.addEventListener('DOMContentLoaded', () => {
    mountEditors();
});
