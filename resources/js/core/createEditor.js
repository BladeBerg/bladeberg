/**
 * Framework-agnostic BladeBerg editor mount API.
 *
 * Works the same whether it is driven by the Blade IIFE (editor.jsx) or imported
 * directly in a SPA/mobile webview from the npm package (index.js). It owns the
 * full lifecycle: loading the editor runtime, mounting onto a target, wiring the
 * optional media manager, applying branding, and exposing a small handle for
 * reading content and tearing down.
 *
 * The only contract with the backend is a string of block HTML carrying the
 * configured prefix (default `bb`). Nothing about saving is assumed — the host
 * decides how/when to read getContent() and where to POST it.
 */
import { ensureRuntime } from './runtime.js';
import { resolveConfig, brandContent, unbrandContent } from './config.js';
import { applyBranding } from './branding.js';
import { installBlockContextMenu } from './contextMenu.js';
import { bladebergMediaUpload } from '../media/mediaUpload.js';
import { registerApiFetchMiddleware } from '../media/apiFetchMiddleware.js';
import { MediaLibraryModal } from '../media/MediaLibraryModal.jsx';

/**
 * Register a custom block type. If the editor runtime is not ready yet the call
 * is queued and flushed once window.wp.blocks becomes available.
 *
 * @param {string} name
 * @param {Object} settings
 */
export function registerBlock(name, settings) {
    window.Bladeberg = window.Bladeberg ?? { _queue: [] };
    window.Bladeberg._queue = window.Bladeberg._queue ?? [];

    if (window.wp?.blocks?.registerBlockType) {
        window.wp.blocks.registerBlockType(name, settings);
    } else {
        window.Bladeberg._queue.push({ name, settings });
    }
}

function flushBlockQueue() {
    if (!window.wp?.blocks?.registerBlockType) return;
    const queue = window.Bladeberg?._queue ?? [];
    queue.forEach(({ name, settings }) => window.wp.blocks.registerBlockType(name, settings));
    if (window.Bladeberg) window.Bladeberg._queue = [];
}

function resolveTextarea(target) {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) {
        throw new Error('[BladeBerg] createEditor: target element not found.');
    }

    // Same wrapper class the Blade component uses — scopes layout + z-index overrides.
    el.classList.add('bladeberg-container');

    if (el.tagName === 'TEXTAREA') {
        return el;
    }
    const textarea = document.createElement('textarea');
    el.appendChild(textarea);
    return textarea;
}

function wireMedia(mediaMode, editorSettings) {
    if (mediaMode !== 'select' && mediaMode !== 'upload') {
        return editorSettings;
    }

    registerApiFetchMiddleware();

    if (window.wp?.hooks?.addFilter) {
        window.wp.hooks.addFilter(
            'editor.MediaUpload',
            'bladeberg/media-upload',
            () => MediaLibraryModal
        );
    }

    if (mediaMode === 'upload') {
        editorSettings.editor = {
            ...(editorSettings.editor ?? {}),
            mediaUpload: bladebergMediaUpload,
        };

        if (window.wp?.data) {
            try {
                window.wp.data.dispatch('core')?.receiveUserPermission?.('create/media', true);
            } catch (_) {
                // core store may not expose receiveUserPermission in all versions
            }
        }
    }

    return editorSettings;
}

/**
 * Mount a BladeBerg editor.
 *
 * @param {Object} options
 * @param {string|HTMLElement} options.target        Selector or element. A <textarea>
 *                                                    is mounted directly; any other
 *                                                    element gets a textarea appended.
 * @param {string}  [options.value]                  Initial content (configured prefix).
 * @param {Object}  [options.settings]               Settings forwarded to attachEditor.
 * @param {string}  [options.blockPrefix]            Overrides config/window prefix.
 * @param {Object}  [options.media]                  { mode, apiUrl, csrfToken }.
 * @param {boolean} [options.branding=true]          Apply UI branding + code overlay.
 * @param {boolean} [options.contextMenu=true]       Restore right-click block menu.
 * @param {Function}[options.onChange]               Called with branded content on change.
 * @returns {Promise<{ getContent: Function, onChange: Function, destroy: Function, textarea: HTMLTextAreaElement }>}
 */
export async function createEditor(options = {}) {
    const {
        target,
        value,
        settings = {},
        blockPrefix,
        media,
        rebrandHtmlClasses,
        branding = true,
        contextMenu = true,
        onChange,
    } = options;

    await ensureRuntime();
    flushBlockQueue();

    const cfg = resolveConfig({ blockPrefix, media, rebrandHtmlClasses });
    const prefix = cfg.blockPrefix;
    const brandOptions = { rebrandClasses: cfg.rebrandHtmlClasses };

    const textarea = resolveTextarea(target);
    textarea.dataset.bladebergMounted = '1';

    if (value != null) textarea.value = value;
    if (textarea.value) {
        textarea.value = unbrandContent(textarea.value, prefix, brandOptions);
    }

    const editorSettings = wireMedia(cfg.mediaMode, { ...settings });

    window.wp.attachEditor(textarea, editorSettings);

    if (branding) applyBranding(prefix);
    if (contextMenu) installBlockContextMenu();

    // The browser bundle writes to textarea.value through an internal closure
    // and does not emit `input` events, so changes are detected by polling the
    // value. getContent() remains the authoritative pull-based reader.
    const subscribers = new Set();
    if (typeof onChange === 'function') subscribers.add(onChange);

    let lastValue = textarea.value;
    let pollId = null;

    function startPolling() {
        if (pollId || subscribers.size === 0) return;
        pollId = window.setInterval(() => {
            if (textarea.value === lastValue) return;
            lastValue = textarea.value;
            const branded = brandContent(textarea, prefix, brandOptions);
            subscribers.forEach((cb) => {
                try { cb(branded); } catch (err) { console.error('[BladeBerg] onChange handler error:', err); }
            });
        }, 300);
    }

    startPolling();

    return {
        textarea,
        getContent: () => brandContent(textarea, prefix, brandOptions),
        onChange(cb) {
            subscribers.add(cb);
            startPolling();
            return () => subscribers.delete(cb);
        },
        destroy() {
            if (pollId) { window.clearInterval(pollId); pollId = null; }
            subscribers.clear();
            try { window.wp?.detachEditor?.(textarea); } catch (_) {}
            delete textarea.dataset.bladebergMounted;
        },
    };
}
