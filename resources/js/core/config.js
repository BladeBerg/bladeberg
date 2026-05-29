/**
 * Runtime configuration helpers shared by the Blade and headless entry points.
 *
 * In the Blade flow, editor.blade.php injects a `window.BladebergConfig` inline
 * script. In the headless flow, the host passes options to createEditor(). Both
 * paths funnel through resolveConfig() so the media JS modules (which read
 * window.BladebergConfig) keep working regardless of how the editor was mounted.
 */

/**
 * Escape a string for safe use inside a RegExp.
 *
 * @param {string} value
 * @returns {string}
 */
export function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Gutenberg HTML class prefixes rewritten alongside block comment delimiters. */
const HTML_CLASS_TOKENS = ['block', 'element', 'container'];

/**
 * @param {string} html
 * @param {string} prefix
 * @returns {string}
 */
export function brandHtmlClasses(html, prefix) {
    if (!html || prefix === 'wp') {
        return html;
    }

    let result = html;
    for (const token of HTML_CLASS_TOKENS) {
        result = result.replaceAll(`wp-${token}-`, `${prefix}-${token}-`);
    }

    return result;
}

/**
 * @param {string} html
 * @param {string} prefix
 * @returns {string}
 */
export function unbrandHtmlClasses(html, prefix) {
    if (!html || prefix === 'wp') {
        return html;
    }

    let result = html;
    for (const token of HTML_CLASS_TOKENS) {
        result = result.replaceAll(`${prefix}-${token}-`, `wp-${token}-`);
    }

    return result;
}

/**
 * @param {Object} [options]
 * @param {string} [options.blockPrefix]
 * @param {boolean} [options.rebrandHtmlClasses]
 * @param {Object} [options.media]            { mode, apiUrl, csrfToken }
 * @returns {{ blockPrefix: string, rebrandHtmlClasses: boolean, mediaMode: string, mediaApiUrl: string, csrfToken: string }}
 */
export function resolveConfig({ blockPrefix, media, rebrandHtmlClasses } = {}) {
    const existing = window.BladebergConfig ?? {};

    const resolved = {
        blockPrefix: blockPrefix ?? existing.blockPrefix ?? 'bb',
        rebrandHtmlClasses: rebrandHtmlClasses ?? existing.rebrandHtmlClasses ?? true,
        mediaMode: media?.mode ?? existing.mediaMode ?? 'disabled',
        mediaApiUrl: media?.apiUrl ?? existing.mediaApiUrl ?? '',
        csrfToken:
            media?.csrfToken
            ?? existing.csrfToken
            ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            ?? '',
    };

    window.BladebergConfig = { ...existing, ...resolved };

    return resolved;
}

/**
 * Brand block comment delimiters and (optionally) wp-* HTML classes for storage.
 *
 * @param {string} value
 * @param {string} prefix
 * @param {{ rebrandClasses?: boolean }} [options]
 * @returns {string}
 */
export function brandHtml(value, prefix, { rebrandClasses = true } = {}) {
    let out = value.replace(/<!--\s*(\/?)wp:/g, `<!-- $1${prefix}:`);

    if (rebrandClasses && prefix !== 'wp') {
        out = brandHtmlClasses(out, prefix);
    }

    return out;
}

/**
 * Read a textarea's block markup with wp: delimiters (and classes) rewritten
 * to the configured prefix for storage.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {string} prefix
 * @param {{ rebrandClasses?: boolean }} [options]
 * @returns {string}
 */
export function brandContent(textarea, prefix, options = {}) {
    const rebrandClasses = options.rebrandClasses
        ?? window.BladebergConfig?.rebrandHtmlClasses
        ?? true;

    return brandHtml(textarea?.value ?? '', prefix, { rebrandClasses });
}

/**
 * Rewrite stored configured-prefix markup back to wp: / wp-* so Gutenberg can parse it.
 *
 * @param {string} value
 * @param {string} prefix
 * @param {{ rebrandClasses?: boolean }} [options]
 * @returns {string}
 */
export function unbrandContent(value, prefix, options = {}) {
    if (!value) return value;

    const rebrandClasses = options.rebrandClasses
        ?? window.BladebergConfig?.rebrandHtmlClasses
        ?? true;

    const re = new RegExp(`<!--\\s*(\\/?)\\s*${escapeRegExp(prefix)}:`, 'g');
    let out = value.replace(re, '<!-- $1wp:');

    if (rebrandClasses && prefix !== 'wp') {
        out = unbrandHtmlClasses(out, prefix);
    }

    return out;
}
