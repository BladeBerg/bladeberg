/**
 * BladeBerg apiFetch middleware.
 *
 * Gutenberg's internal data layer (@wordpress/core-data) fetches media via:
 *   GET /wp/v2/media?context=edit&...
 *   GET /wp/v2/media/{id}?context=edit
 *
 * These calls power the inserter's "Images / Videos / Audio" tabs and
 * the link control's media suggestions.
 *
 * This middleware intercepts all requests whose path starts with /wp/v2/media
 * and rewrites them to the BladeBerg media API:
 *   GET /bladeberg/media?page=1&per_page=20&...
 *
 * WordPress query params → BladeBerg params mapping:
 *   context    → dropped (our API always returns edit context)
 *   media_type → media_type (passed through)
 *   search     → search
 *   per_page   → per_page
 *   page       → page
 *   include    → dropped (not yet supported)
 */

/**
 * Strip WordPress-only query params that the BladeBerg API does not support.
 *
 * @param {string} path  The original WP REST path (e.g. /wp/v2/media?context=edit)
 * @returns {string}     The rewritten path for the BladeBerg media API
 */
function rewriteMediaPath(path) {
    const cfg        = window.BladebergConfig ?? {};
    const prefix     = cfg.mediaApiUrl ? new URL(cfg.mediaApiUrl).pathname : '/bladeberg/media';

    // Strip off the /wp/v2/media prefix, keeping the rest (/{id} or query string)
    const suffix = path.replace(/^\/wp\/v2\/media/, '');

    // Parse query params and drop WP-only ones
    const qIndex = suffix.indexOf('?');
    const idPart = qIndex >= 0 ? suffix.slice(0, qIndex) : suffix;
    const rawQuery = qIndex >= 0 ? suffix.slice(qIndex + 1) : '';

    const inParams  = new URLSearchParams(rawQuery);
    const outParams = new URLSearchParams();

    const passThrough = ['page', 'per_page', 'search', 'media_type', 'orderby', 'order'];
    for (const key of passThrough) {
        if (inParams.has(key)) outParams.set(key, inParams.get(key));
    }

    const qs       = outParams.toString();
    const newPath  = prefix + idPart + (qs ? '?' + qs : '');
    return newPath;
}

/**
 * Register the BladeBerg apiFetch middleware.
 *
 * Must be called after window.wp.apiFetch is available (i.e. after the
 * isolated-block-editor browser bundle has loaded).
 */
export function registerApiFetchMiddleware() {
    const apiFetch = window.wp?.apiFetch;

    if (!apiFetch) {
        console.warn('[BladeBerg] window.wp.apiFetch not found — media API middleware not registered.');
        return;
    }

    apiFetch.use((options, next) => {
        const path = options.path ?? '';

        if (typeof path === 'string' && path.startsWith('/wp/v2/media')) {
            return next({ ...options, path: rewriteMediaPath(path) });
        }

        return next(options);
    });
}
