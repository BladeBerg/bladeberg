import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';
import { existsSync, readFileSync, writeFileSync } from 'fs';

/**
 * npm package build for @bladeberg/editor (ESM, headless/SPA consumers).
 *
 * Separate from the Blade build (vite.config.js → dist/, IIFE + copied raw CSS).
 * This build:
 *   - emits an ESM bundle (resources/js/index.js → dist-npm/bladeberg.js) that
 *     lazy-loads the Gutenberg browser runtime;
 *   - externalizes react / react-dom (provided by the host app as peers);
 *   - produces a single dist-npm/style.css = the raw Gutenberg stylesheets
 *     followed by BladeBerg's compiled overrides, so consumers import one file.
 */

// Raw Gutenberg stylesheets, prepended (in load order) to our compiled CSS.
const GUTENBERG_CSS = [
    '@automattic/isolated-block-editor/build-browser/core.css',
    '@automattic/isolated-block-editor/build-browser/isolated-block-editor.css',
    '@wordpress/components/build-style/style.css',
    '@wordpress/block-library/build-style/style.css',
    '@wordpress/block-library/build-style/editor.css',
];

function bundleStylesheet() {
    const nm = resolve(__dirname, 'node_modules');
    const stylePath = resolve(__dirname, 'dist-npm', 'style.css');

    return {
        name: 'bladeberg-npm-bundle-css',
        closeBundle() {
            const parts = [];

            for (const rel of GUTENBERG_CSS) {
                const p = resolve(nm, rel);
                if (existsSync(p)) {
                    parts.push(`/* ${rel} */\n${readFileSync(p, 'utf8')}`);
                } else {
                    console.warn(`[bladeberg] CSS not found, skipping: ${p}`);
                }
            }

            // Our compiled overrides (emitted by the lib build from editor.scss).
            if (existsSync(stylePath)) {
                parts.push(`/* bladeberg overrides */\n${readFileSync(stylePath, 'utf8')}`);
            }

            writeFileSync(stylePath, parts.join('\n\n'));
            console.log('[bladeberg] Wrote dist-npm/style.css');
        },
    };
}

export default defineConfig({
    plugins: [react(), bundleStylesheet()],
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: ['import', 'legacy-js-api'],
            },
        },
    },
    build: {
        outDir: 'dist-npm',
        emptyOutDir: true,
        cssCodeSplit: false,
        lib: {
            entry: resolve(__dirname, 'resources/js/index.js'),
            formats: ['es'],
            fileName: () => 'bladeberg.js',
        },
        rollupOptions: {
            external: ['react', 'react-dom'],
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) return 'style.css';
                    return assetInfo.name;
                },
            },
        },
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production'),
    },
});
