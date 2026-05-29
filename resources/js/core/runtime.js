/**
 * BladeBerg editor runtime coordination.
 *
 * The editor is powered by the prebuilt @automattic/isolated-block-editor browser
 * bundle, which expects window.React / window.ReactDOM to exist and sets
 * window.wp.attachEditor when it executes.
 *
 * The bundle is delivered differently per entry point, so loading is injectable:
 *
 *   - Blade IIFE (editor.jsx): the bundle is loaded via a <script defer> tag, so
 *     no loader is registered and ensureRuntime() simply waits for the global to
 *     appear. Keeping the dynamic import out of this build is required because an
 *     IIFE bundle cannot code-split.
 *
 *   - npm ESM (index.js): registers a loader that dynamic-imports the browser
 *     bundle after assigning the React globals.
 */

let runtimeLoader = null;
let runtimePromise = null;

/**
 * Register the function that loads the isolated-block-editor browser bundle.
 * The loader is responsible for assigning window.React / window.ReactDOM before
 * the bundle executes.
 *
 * @param {() => Promise<unknown>} loader
 */
export function setRuntimeLoader(loader) {
    runtimeLoader = loader;
}

function waitForGlobal(predicate, timeoutMs = 5000, intervalMs = 50) {
    return new Promise((resolve, reject) => {
        const started = Date.now();
        const tick = () => {
            if (predicate()) return resolve();
            if (Date.now() - started >= timeoutMs) {
                return reject(new Error('[BladeBerg] Timed out waiting for the editor runtime.'));
            }
            setTimeout(tick, intervalMs);
        };
        tick();
    });
}

/**
 * Ensure window.wp.attachEditor is available. Idempotent.
 *
 * @returns {Promise<void>}
 */
export function ensureRuntime() {
    if (window.wp?.attachEditor) {
        return Promise.resolve();
    }
    if (runtimePromise) {
        return runtimePromise;
    }

    runtimePromise = (runtimeLoader ? runtimeLoader() : Promise.resolve())
        .then(() => waitForGlobal(() => !!window.wp?.attachEditor))
        .catch((err) => {
            runtimePromise = null;
            throw err;
        });

    return runtimePromise;
}
