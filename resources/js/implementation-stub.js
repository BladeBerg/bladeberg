/**
 * Stub for @wordpress/private-apis build-module/implementation.mjs
 *
 * The real implementation uses a Symbol + WeakMap pair to let WordPress
 * packages share private APIs in a controlled way. When bundled as a single
 * IIFE, circular-dependency init order can cause unlock() to be called on an
 * object before lock() has run for it. This stub makes unlock() gracefully
 * return an empty Proxy in that edge case instead of throwing.
 *
 * All exports that the real implementation.mjs provides are replicated here
 * so packages that import from it continue to work.
 */

const lockedData = new WeakMap();
const __private = Symbol('Private API ID');

function lock(object, privateData) {
    if (!object) return;
    if (!(object[__private])) {
        object[__private] = {};
    }
    lockedData.set(object[__private], privateData);
}

function unlock(object) {
    if (!object || !(object[__private])) {
        // Graceful fallback: absorb any method calls so the editor keeps working.
        return new Proxy({}, {
            get(_target, prop) {
                if (prop === 'then') return undefined; // not a Promise
                return new Proxy(() => undefined, {
                    get: () => () => undefined,
                    apply: () => undefined,
                });
            },
        });
    }
    return lockedData.get(object[__private]);
}

const __dangerousOptInToUnstableAPIsOnlyForCoreModules = (_consent, _moduleName) => {
    return { lock, unlock };
};

export {
    __dangerousOptInToUnstableAPIsOnlyForCoreModules,
    lock,
    unlock,
};

export function allowCoreModule(_name) {}
export function resetAllowedCoreModules() {}
