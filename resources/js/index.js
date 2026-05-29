/**
 * @bladeberg/editor — public npm entry (ESM).
 *
 * Headless, framework-agnostic API for mounting the BladeBerg block editor in a
 * SPA, mobile webview, or any non-Blade frontend. Unlike the Blade IIFE build,
 * this entry loads the Gutenberg browser runtime itself, so consumers only need:
 *
 *   import { createEditor } from '@bladeberg/editor';
 *   import '@bladeberg/editor/style.css';
 *
 *   const editor = await createEditor({
 *     target: '#editor',
 *     value: post.content,            // stored content (your configured prefix)
 *     blockPrefix: 'bb',
 *     onChange: (html) => { draft = html; },
 *   });
 *
 *   // later, when saving:
 *   await fetch('/api/posts', {
 *     method: 'POST',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({ content: editor.getContent() }),
 *   });
 *
 * react and react-dom are peer dependencies (the host app's copies are reused as
 * window.React / window.ReactDOM for the browser build).
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { setRuntimeLoader } from './core/runtime.js';

import '../css/editor.scss';

// Register how the Gutenberg browser runtime is loaded for headless consumers:
// assign the React globals, then load the prebuilt browser bundle shipped inside
// dist-npm/ (copied at build time — not resolved from node_modules at install time).
setRuntimeLoader(() => {
    window.React = window.React ?? React;
    window.ReactDOM = window.ReactDOM ?? ReactDOM;

    if (window.wp?.attachEditor) {
        return Promise.resolve();
    }

    const url = new URL('./isolated-block-editor.js', import.meta.url).href;
    const existing = document.querySelector(`script[data-bladeberg-runtime][src="${url}"]`);

    if (existing) {
        return new Promise((resolve, reject) => {
            if (window.wp?.attachEditor) return resolve();
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('[BladeBerg] Failed to load editor runtime.')));
        });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        script.defer = true;
        script.dataset.bladebergRuntime = '1';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('[BladeBerg] Failed to load editor runtime.'));
        document.head.appendChild(script);
    });
});

export { createEditor, registerBlock } from './core/createEditor.js';
export { applyBranding, installCodeEditorOverlay } from './core/branding.js';
export { installBlockContextMenu } from './core/contextMenu.js';
