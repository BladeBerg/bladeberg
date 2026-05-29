import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';
import { copyFileSync, existsSync } from 'fs';

/**
 * After Vite finishes its own bundle, copy all required pre-built CSS/JS files
 * into dist/.
 *
 * Each entry is a tuple: [srcPath relative to node_modules, destFilename in dist/]
 *
 * Sources:
 *   - isolated-block-editor browser build  — self-contained Gutenberg editor JS + layout CSS
 *   - core.css                             — full ~480 KB Gutenberg chrome styles
 *   - blocks-style.css                     — per-block FRONTEND styles (visitors see these)
 *   - blocks-editor.css                    — per-block EDITOR styles (canvas appearance)
 *   - components.css                       — @wordpress/components (popovers, modals, inputs)
 */
function copyIsolatedBlockEditorPlugin() {
    const nm = resolve(__dirname, 'node_modules');

    // [source path inside node_modules, dest filename in dist/]
    const files = [
        [
            '@automattic/isolated-block-editor/build-browser/isolated-block-editor.js',
            'isolated-block-editor.js',
        ],
        [
            '@automattic/isolated-block-editor/build-browser/isolated-block-editor.css',
            'isolated-block-editor.css',
        ],
        [
            '@automattic/isolated-block-editor/build-browser/core.css',
            'core.css',
        ],
        [
            '@wordpress/block-library/build-style/style.css',
            'blocks-style.css',
        ],
        [
            '@wordpress/block-library/build-style/editor.css',
            'blocks-editor.css',
        ],
        [
            '@wordpress/components/build-style/style.css',
            'components.css',
        ],
    ];

    return {
        name: 'bladeberg-copy-browser-build',
        closeBundle() {
            for (const [src, dest] of files) {
                const srcPath  = resolve(nm, src);
                const destPath = resolve(__dirname, 'dist', dest);

                if (existsSync(srcPath)) {
                    copyFileSync(srcPath, destPath);
                    console.log(`[bladeberg] Copied ${src.split('/').slice(-2).join('/')} → dist/${dest}`);
                } else {
                    console.warn(`[bladeberg] Not found, skipping: ${srcPath}`);
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        react(),
        copyIsolatedBlockEditorPlugin(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: ['import', 'legacy-js-api'],
            },
        },
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        lib: {
            entry: resolve(__dirname, 'resources/js/editor.jsx'),
            name: 'Bladeberg',
            fileName: 'bladeberg-editor',
            formats: ['iife'],
        },
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name === 'style.css') return 'bladeberg-editor.css';
                    return assetInfo.name;
                },
            },
        },
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production'),
    },
});
