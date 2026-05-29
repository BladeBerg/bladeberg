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

/**
 * Merge caller-provided options over any existing window.BladebergConfig and
 * write the result back to window.BladebergConfig so downstream media modules
 * can read it.
 *
 * @param {Object} [options]
 * @param {string} [options.blockPrefix]
 * @param {Object} [options.media]            { mode, apiUrl, csrfToken }
 * @returns {{ blockPrefix: string, mediaMode: string, mediaApiUrl: string, csrfToken: string }}
 */
export function resolveConfig({ blockPrefix, media } = {}) {
    const existing = window.BladebergConfig ?? {};

    const resolved = {
        blockPrefix: blockPrefix ?? existing.blockPrefix ?? 'bb',
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
 * Read a textarea's block markup with `<!-- wp:… -->` delimiters rewritten to
 * the configured prefix (default `bb`). This is the only place stored content
 * is branded.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {string} prefix
 * @returns {string}
 */
export function brandContent(textarea, prefix) {
    const raw = textarea?.value ?? '';
    return raw.replace(/<!--\s*(\/?)wp:/g, `<!-- $1${prefix}:`);
}

/**
 * Rewrite stored configured-prefix markup back to `wp:` so the editor's internal
 * parser can load previously-saved content.
 *
 * @param {string} value
 * @param {string} prefix
 * @returns {string}
 */
export function unbrandContent(value, prefix) {
    if (!value) return value;
    const re = new RegExp(`<!--\\s*(\\/?)\\s*${escapeRegExp(prefix)}:`, 'g');
    return value.replace(re, '<!-- $1wp:');
}
