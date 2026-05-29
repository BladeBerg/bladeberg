/**
 * Stub for @wordpress/private-apis lock-unlock internals.
 *
 * The real implementation uses a WeakMap so that private APIs can be
 * attached to public objects and retrieved only by code that imports
 * this module. We replicate that contract here so that packages which
 * call lock() then unlock() work correctly, while still preventing
 * the collaborative-editing features of @wordpress/sync from loading.
 */

const store = new WeakMap();

export function lock(object, privateData) {
    if (object != null) {
        store.set(object, privateData);
    }
}

export function unlock(object) {
    if (object != null && store.has(object)) {
        return store.get(object);
    }
    // Graceful fallback: return a proxy that silently absorbs any method
    // calls so the editor can continue even if lock() was never called
    // for this particular object (e.g. stubs for disabled features).
    return new Proxy(
        {},
        {
            get: (_target, prop) => {
                if (prop === 'then') return undefined; // not a Promise
                return new Proxy(() => {}, {
                    get: () => () => {},
                    apply: () => undefined,
                });
            },
        }
    );
}
