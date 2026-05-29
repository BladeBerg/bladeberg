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
// assign the React globals, then dynamic-import the prebuilt browser bundle
// (which publishes window.wp.attachEditor as a side effect).
setRuntimeLoader(() => {
    window.React = window.React ?? React;
    window.ReactDOM = window.ReactDOM ?? ReactDOM;
    return import(
        '@automattic/isolated-block-editor/build-browser/isolated-block-editor.js'
    );
});

export { createEditor, registerBlock } from './core/createEditor.js';
export { applyBranding, installCodeEditorOverlay } from './core/branding.js';
export { installBlockContextMenu } from './core/contextMenu.js';
