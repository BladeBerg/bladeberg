# @bladeberg/editor

**Gutenberg, standalone.** No WordPress. No Laravel. Just the block editor in your React app.

BladeBerg wraps [`@automattic/isolated-block-editor`](https://github.com/Automattic/isolated-block-editor) — the same pre-built browser bundle Automattic uses to run Gutenberg outside of wp-admin — and ships it as a lazy-loaded npm package. You get paragraphs, headings, images, columns, embeds, the whole core block library, without installing a single `@wordpress/*` package yourself.

> Using Laravel? See the full [BladeBerg docs](https://github.com/BladeBerg/bladeberg#readme) for the Composer package, Blade components, PHP rendering, and media API.

---

## Install

```bash
npm install @bladeberg/editor
```

That's it. **No React install needed** — the package bundles React 18 (required by Gutenberg) and the full editor runtime. Your app can use React 19 for its own UI without conflict.

**Requirements:** A modern browser and a bundler that supports ESM (`import`).

---

## Quick start

```jsx
import { createEditor } from '@bladeberg/editor';
import '@bladeberg/editor/style.css';

const editor = await createEditor({
  target: '#editor',       // selector or DOM element
  value: savedContent,     // optional — existing block HTML
  blockPrefix: 'bb',       // prefix in stored content (default: bb)
  onChange: (html) => {
    draft = html;          // live updates (optional)
  },
});

// When the user saves:
await fetch('/api/posts/1', {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ content: editor.getContent() }),
});

// On route change / unmount:
editor.destroy();
```

`createEditor()` is async — it lazy-loads the Gutenberg runtime on first call (~2 MB, cached after that).

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

Gutenberg saves blocks as HTML comments:

```html
<!-- bb:paragraph --><p>Hello world</p><!-- /bb:paragraph -->
```

The `bb:` prefix (configurable via `blockPrefix`) keeps your database free of WordPress branding. On load, BladeBerg converts it back to `wp:` internally so Gutenberg can parse it, then converts it back on save.

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

## SSR / Next.js / Nuxt

The editor is **browser-only**. Import it from a client component:

```jsx
'use client';

import { useEffect, useRef } from 'react';

export default function PostEditor({ content }) {
  const ref = useRef(null);

  useEffect(() => {
    let editor;
    import('@bladeberg/editor').then(({ createEditor }) => {
      createEditor({ target: ref.current, value: content }).then((e) => { editor = e; });
    });
    return () => editor?.destroy();
  }, [content]);

  return <div ref={ref} />;
}
```

Don't forget `import '@bladeberg/editor/style.css'` somewhere in your client bundle.

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
