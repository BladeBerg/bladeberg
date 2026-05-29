# @bladeberg/editor

**Gutenberg, standalone.** No WordPress. No Laravel. Drop the block editor into any SPA, mobile webview, or vanilla JS app.

BladeBerg wraps [`@automattic/isolated-block-editor`](https://github.com/Automattic/isolated-block-editor) — the same pre-built browser bundle Automattic uses to run Gutenberg outside of wp-admin — and ships it as a lazy-loaded npm package. You get paragraphs, headings, images, columns, embeds, the whole core block library, without installing a single `@wordpress/*` package yourself.

> Using Laravel? See the full [BladeBerg docs](https://github.com/BladeBerg/bladeberg#readme) for the Composer package, Blade components, PHP rendering, and media API.

---

## Table of contents

- [Install](#install)
- [How to use](#how-to-use)
  - [1. Import CSS first](#1-import-css-first)
  - [2. Add a mount point](#2-add-a-mount-point)
  - [3. Mount the editor](#3-mount-the-editor)
  - [4. Read content on save](#4-read-content-on-save)
  - [5. Tear down on unmount](#5-tear-down-on-unmount)
- [Examples by framework](#examples-by-framework)
  - [Vanilla TypeScript (Vite)](#vanilla-typescript-vite)
  - [React SPA](#react-spa)
  - [Next.js (client component)](#nextjs-client-component)
  - [Full API round-trip](#full-api-round-trip)
- [TypeScript](#typescript)
- [Quick start (minimal)](#quick-start-minimal)
- [What you get](#what-you-get)
- [API reference](#api)
- [Content format](#content-format)
- [Media (optional)](#media-optional)
- [Styling tips](#styling-tips)
- [Rendering stored content](#rendering-stored-content)
- [How it works](#how-it-works)
- [Development (maintainers)](#development-maintainers)
- [License](#license)

---

## Install

```bash
npm install @bladeberg/editor
```

That's it. **No React install needed** — the package bundles React 18 (required by Gutenberg) and the full editor runtime. Your app can use React 19 for its own UI without conflict.

**Requirements:** A modern browser and a bundler that supports ESM (`import`).

---

## How to use

### 1. Import CSS first

Always import the editor stylesheet **before** your app's global CSS. Host styles (Vite templates often set `text-align: center` on `#app`) will break the block inserter if they load after Gutenberg.

```ts
import '@bladeberg/editor/style.css';
import './your-app.css';   // your styles after
```

### 2. Add a mount point

Give the editor an empty container. Don't put borders or `overflow: hidden` on the mount element itself — wrap it if you need chrome around the editor.

```html
<!-- index.html -->
<div id="app">
  <div class="editor-host">
    <div id="editor"></div>
  </div>
</div>
```

```css
/* Your app CSS — styling around the editor, not on #editor itself */
.editor-host {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: visible;   /* important — hidden clips the block inserter popover */
}

#editor {
  min-height: 420px;
}
```

`createEditor()` automatically adds `.bladeberg-container` to your mount element (same as the Laravel Blade component).

### 3. Mount the editor

```ts
import { createEditor } from '@bladeberg/editor';

const editor = await createEditor({
  target: '#editor',                              // selector or DOM element
  value: existingContent,                         // optional — block HTML from your API
  blockPrefix: 'bb',                              // must match your backend config
  onChange: (html) => { draft = html; },          // optional — live updates
});
```

`createEditor()` is **async** — it lazy-loads the Gutenberg runtime on first call (~4 MB, cached after that).

### 4. Read content on save

```ts
const content = editor.getContent();
// → '<!-- bb:paragraph --><p>Hello</p><!-- /bb:paragraph -->'

await fetch('/api/posts/1', {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ content }),
});
```

Content is already prefixed with your `blockPrefix` — store it as-is in your database.

### 5. Tear down on unmount

```ts
editor.destroy();   // SPA route change, modal close, component unmount
```

---

## Examples by framework

### Vanilla TypeScript (Vite)

```ts
// src/main.ts
import '@bladeberg/editor/style.css';
import './style.css';
import { createEditor } from '@bladeberg/editor';
import type { EditorHandle } from '@bladeberg/editor';

document.querySelector('#app')!.innerHTML = `
  <main>
    <h1>Edit post</h1>
    <div class="editor-host"><div id="editor"></div></div>
    <button id="save" type="button">Save</button>
  </main>
`;

let editor: EditorHandle | undefined;

createEditor({
  target: '#editor',
  blockPrefix: 'bb',
  value: '<!-- bb:paragraph --><p>Hello from TypeScript.</p><!-- /bb:paragraph -->',
  onChange: (html) => console.log('draft:', html),
})
  .then((instance) => { editor = instance; })
  .catch((err) => console.error('[BladeBerg]', err));

document.querySelector<HTMLButtonElement>('#save')!.addEventListener('click', () => {
  if (!editor) return;
  console.log(editor.getContent());
});

window.addEventListener('beforeunload', () => editor?.destroy());
```

### React SPA

```tsx
// PostEditor.tsx
import { useEffect, useRef } from 'react';
import { createEditor } from '@bladeberg/editor';
import type { EditorHandle } from '@bladeberg/editor';
import '@bladeberg/editor/style.css';

interface Props {
  initialContent?: string;
  onSave: (content: string) => void;
}

export function PostEditor({ initialContent = '', onSave }: Props) {
  const mountRef = useRef<HTMLDivElement>(null);
  const editorRef = useRef<EditorHandle | undefined>(undefined);

  useEffect(() => {
    if (!mountRef.current) return;

    createEditor({
      target: mountRef.current,
      value: initialContent,
      blockPrefix: 'bb',
    }).then((editor) => { editorRef.current = editor; });

    return () => {
      editorRef.current?.destroy();
      editorRef.current = undefined;
    };
  }, [initialContent]);

  return (
    <div className="editor-host">
      <div ref={mountRef} style={{ minHeight: 420 }} />
      <button type="button" onClick={() => onSave(editorRef.current?.getContent() ?? '')}>
        Save
      </button>
    </div>
  );
}
```

You do **not** need to import React for the editor itself — BladeBerg bundles its own React 18 for Gutenberg. Your app's React version is unrelated.

### Next.js (client component)

```tsx
'use client';

import { useEffect, useRef } from 'react';
import '@bladeberg/editor/style.css';

export default function PostEditor({ content }: { content: string }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let editor: { destroy: () => void } | undefined;

    import('@bladeberg/editor').then(({ createEditor }) =>
      createEditor({ target: ref.current!, value: content, blockPrefix: 'bb' })
        .then((e) => { editor = e; })
    );

    return () => editor?.destroy();
  }, [content]);

  return <div ref={ref} style={{ minHeight: 420 }} />;
}
```

Import the CSS in a client layout or this component — not in a Server Component.

### Full API round-trip

```ts
import { createEditor } from '@bladeberg/editor';
import '@bladeberg/editor/style.css';

// ── Load existing post ──────────────────────────────────────
const res = await fetch('/api/posts/42');
const post = await res.json();

const editor = await createEditor({
  target: '#editor',
  value: post.content,       // stored block HTML from your DB
  blockPrefix: 'bb',
});

// ── Save on button click ────────────────────────────────────
document.querySelector('#save')!.addEventListener('click', async () => {
  await fetch('/api/posts/42', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content: editor.getContent() }),
  });
});

// ── Render for visitors (separate endpoint / Laravel package) ─
// POST /bladeberg/render  { "content": "<!-- bb:paragraph -->..." }
// → { "html": "<div class=\"bb-content\">...</div>" }
```

---

## TypeScript

The package does not ship types yet. Add a local declaration file:

```ts
// src/bladeberg-editor.d.ts
declare module '@bladeberg/editor' {
  export interface CreateEditorOptions {
    target: string | HTMLElement;
    value?: string;
    blockPrefix?: string;
    settings?: Record<string, unknown>;
    media?: { mode?: 'disabled' | 'select' | 'upload'; apiUrl?: string; csrfToken?: string };
    branding?: boolean;
    contextMenu?: boolean;
    onChange?: (html: string) => void;
  }

  export interface EditorHandle {
    textarea: HTMLTextAreaElement;
    getContent: () => string;
    onChange: (callback: (html: string) => void) => () => void;
    destroy: () => void;
  }

  export function createEditor(options: CreateEditorOptions): Promise<EditorHandle>;
  export function registerBlock(name: string, settings: Record<string, unknown>): void;
}

declare module '@bladeberg/editor/style.css';
```

---

## Quick start (minimal)

```js
import { createEditor } from '@bladeberg/editor';
import '@bladeberg/editor/style.css';

const editor = await createEditor({ target: '#editor', blockPrefix: 'bb' });
console.log(editor.getContent());
editor.destroy();
```

---

## What you get

| Feature | Details |
|---------|---------|
| **Full core blocks** | Paragraph, heading, list, image, quote, columns, embeds, etc. |
| **Portable HTML** | Content serializes to block comments: `<!-- bb:paragraph -->…` |
| **Branded prefix** | Default `bb:` instead of WordPress's `wp:` — configurable |
| **Right-click menu** | Block Options (Copy, Duplicate, Remove, …) restored |
| **Media upload** | Optional — wire to your own API (see below) |
| **Zero WordPress deps** | Runtime is pre-built and shipped in the tarball |

---

## API

### `createEditor(options)`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `target` | `string \| Element` | *(required)* | CSS selector or element. A `<textarea>` is mounted directly; any other element gets a hidden textarea appended. |
| `value` | `string` | `''` | Initial block HTML (your configured prefix). |
| `blockPrefix` | `string` | `'bb'` | Prefix written into block comments on save. |
| `rebrandHtmlClasses` | `boolean` | `true` | Rewrite `wp-block-*` → `{prefix}-block-*` (and element/container) in stored HTML. |
| `settings` | `object` | `{}` | Forwarded to Gutenberg's `attachEditor()`. |
| `media` | `object` | — | `{ mode, apiUrl, csrfToken }` — see [Media](#media). |
| `branding` | `boolean` | `true` | Rebrand "WordPress" strings in the UI. |
| `contextMenu` | `boolean` | `true` | Restore right-click block menu. |
| `onChange` | `(html) => void` | — | Called when content changes (polled every 300 ms). |

**Returns** `Promise<EditorHandle>`:

```js
editor.getContent()   // current block HTML (prefixed)
editor.onChange(fn)   // subscribe; returns unsubscribe fn
editor.destroy()      // detach editor + stop listeners
editor.textarea       // underlying textarea element
```

### `registerBlock(name, settings)`

```js
import { registerBlock } from '@bladeberg/editor';

registerBlock('my-plugin/callout', { /* block settings */ });
```

> **Note:** The current isolated-block-editor bundle (v2.30) does not expose `window.wp.blocks`, so custom React blocks are queued but not registered yet. Use server-rendered blocks with [BladeBerg's PHP package](https://github.com/BladeBerg/bladeberg) instead.

---

## Content format

Gutenberg saves blocks as HTML comments **and** HTML classes:

```html
<!-- bb:paragraph --><p class="bb-block-paragraph">Hello world</p><!-- /bb:paragraph -->
```

| What | Stored as | While editing (live DOM) |
|------|-----------|--------------------------|
| Block comments | `bb:paragraph` | Gutenberg uses `wp:` internally |
| HTML classes | `bb-block-*` (default) | Gutenberg uses `wp-block-*` in the canvas |
| UI labels | BladeBerg / your prefix | Patched in the chrome only |

### Why you still see `wp-*` sometimes

- **Inside the editor canvas while typing** — Gutenberg's save output uses `wp-block-*` until you call `getContent()`. That can't be changed without forking the editor bundle.
- **Editor chrome CSS** — classes like `components-button`, `iso-editor` are Gutenberg internals; not rebranded.
- **Frontend render** — PHP converts `bb-block-*` back to `wp-block-*` on output so WordPress block CSS applies to visitors.

### Disable class rebranding

If you prefer to keep `wp-block-*` in your database:

```js
// npm / headless
createEditor({ target: '#editor', rebrandHtmlClasses: false });
```

```php
// Laravel config/bladeberg.php
'rebrand_html_classes' => false,
```

Comment delimiters (`bb:` vs `wp:`) are always rebranded on save regardless of this setting.

---

## Media (optional)

Wire the editor to your own upload API:

```js
const editor = await createEditor({
  target: '#editor',
  media: {
    mode: 'upload',              // 'disabled' | 'select' | 'upload'
    apiUrl: '/api/media',        // your JSON media endpoints
    csrfToken: getCsrfToken(),   // optional
  },
});
```

If you're using [BladeBerg for Laravel](https://github.com/BladeBerg/bladeberg), the backend ships ready-made routes at `/bladeberg/media`.

---

## Styling tips

- Import `@bladeberg/editor/style.css` **before** your app's global CSS so host styles don't override Gutenberg.
- `createEditor()` adds `.bladeberg-container` to your mount element automatically (same as the Blade component).
- Avoid `overflow: hidden` on the editor wrapper — it clips block inserter popovers.
- Don't set `text-align: center` on a parent that wraps the editor (common in Vite templates) — it breaks the block inserter grid.
- Put borders/shadows on a wrapper **around** `#editor`, not on the mount element itself.
- The red accent (`#e11d1f`) is BladeBerg branding — customize via the SCSS variables in the package source if needed.

---

## Rendering stored content

This npm package is **editor-only**. To turn block HTML into visitor-facing HTML you need a renderer:

- **[BladeBerg Laravel package](https://github.com/BladeBerg/bladeberg)** — `<x-bladeberg-render>`, `Bladeberg::render()`, or `POST /bladeberg/render`
- **Your own backend** — parse `<!-- bb:… -->` comments and render block HTML yourself
- **Return raw block HTML** to the frontend and render client-side

---

## How it works

```
Your React app
    │
    ├─ import { createEditor } from '@bladeberg/editor'
    ├─ import '@bladeberg/editor/style.css'
    │
    ▼
createEditor() lazy-loads isolated-block-editor.js (bundled in the package)
    │
    ▼
window.wp.attachEditor(textarea)  ←  full Gutenberg UI
    │
    ▼
editor.getContent()  →  "<!-- bb:paragraph -->…"  →  POST to your API
```

No `@wordpress/block-editor`, no `@wordpress/data`, no dependency resolver nightmares. The hard part is already done.

---

## Development (maintainers)

```bash
cd packages/bladeberg
npm install
npm run build:npm          # → dist-npm/bladeberg.js + style.css + isolated-block-editor.js
npm pack                   # smoke-test the tarball locally
```

Publish happens via GitHub Actions on `v*` tags. See [RELEASE.md](RELEASE.md).

---

## License

GPL-2.0-or-later — same as Gutenberg. See [LICENSE](LICENSE).
