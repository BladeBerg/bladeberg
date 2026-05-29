# Changelog

All notable changes to BladeBerg are documented here.

This project follows [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) conventions.

---

## [Unreleased]

---

## [0.2.4] — 2026-05-29

### Fixed

- **Gutenberg runtime crash with React 19** — `isolated-block-editor@2.30` requires React 18 globals (`React.__SECRET_INTERNALS…`). The npm package now **bundles React 18** and assigns `window.React` / `window.ReactDOM` before loading the runtime. Host apps can use React 19 for their own UI without breaking the editor.

### Changed

- **Removed `react` / `react-dom` peer dependencies** — no longer required in consumer apps.

---

## [0.2.3] — 2026-05-29

### Fixed

- **React 19 crash in `bladeberg.js`** (`ReactCurrentOwner`) — the npm bundle no longer inlines a legacy JSX runtime; `react/jsx-runtime` is externalized so React 18 and 19 both work for BladeBerg's own components.

---

## [0.2.2] — 2026-05-29

### Changed

- **Peer dependencies** now accept React 18 or 19 (`^18.2.0 || ^19.0.0`) so `npm install` works in React 19 projects without `--legacy-peer-deps`.

---

## [0.2.1] — 2026-05-29

### Fixed

- **npm install no longer pulls in `@wordpress/*`** — `@automattic/isolated-block-editor` moved to devDependencies; the Gutenberg browser runtime is copied into `dist-npm/` at build time and loaded from the package itself. Fixes `ETARGET: No matching version found for @wordpress/base-styles@^9.0.0` for consumers.

### Changed

- npm publish workflow copies `README.npm.md` → `README.md` so the npm registry shows standalone docs.

---

## [0.2.0] — 2026-05-29

### Added

- **Headless / decoupled mode** for API-based projects (SPA, mobile, separate frontends) without splitting the repo
- **`@bladeberg/editor` npm package** — a framework-agnostic ESM build published alongside the Composer package from the same repo
- **`createEditor()` JS API** (decoupled from Blade forms): mounts on any element, lazy-loads the editor runtime, and exposes `getContent()` / `onChange()` / `destroy()`; shared core under `resources/js/core/` powers both the Blade IIFE and the npm entry
- **Optional render API**: `POST /{prefix}/render` (`render_api` config, disabled by default) plus a `Bladeberg::render($content)` facade method that turns stored block content into HTML
- npm publish GitHub Actions workflow (publishes on `v*` tags via `NPM_TOKEN`)

### Changed

- Simplified `bladeberg:install` — removed all interactive media prompts and auto-`migrate`; the media manager is now configured purely via `config/bladeberg.php` (`media.mode`) / env, re-using the app's `FILESYSTEM_DISK`
- Added a unified `bladeberg` publish tag: `php artisan vendor:publish --tag=bladeberg` publishes assets + config (granular tags remain)
- `media.driver` resolution fallback corrected from `spatie` to `filesystem` (matches the config default)

### Removed

- `NormalizeBbContent` middleware, the `bladeberg.normalize` alias, and the `content_normalization` config block — the `wp:` → configured-prefix rewrite happens entirely client-side at save time, so the server-side safety net was redundant
- `Bladeberg::normalize()` / `Bladeberg::denormalize()` facade methods and the `BbContent` helper (plus `BbContentTest`) — prefix conversion is handled in JS; import/paste re-prefixing is done client-side

---

## [0.1.0] — 2026-05-29

### Added

- Configurable `block_prefix` (default `bb`) — content is saved as `<!-- bb:… -->` and normalized back to `wp:` for parsing
- Right-click → block **Options** context menu (restores behaviour the standalone bundle omits)
- Optional media manager with `mode` (`disabled` / `link` / `select` / `upload`) backed by the app's `FILESYSTEM_DISK` — `filesystem` driver needs no database table; `spatie` driver supported
- `BbContent` helper + `Bladeberg::normalize()` / `denormalize()` and `bladeberg.normalize` middleware for server-side prefix conversion
- SCSS source for editor styling (`resources/css/editor.scss`); accent color rebranded from WordPress blue `#0085ba` to BladeBerg red `#e11d1f`
- `config/bladeberg.php` with `allowed_blocks`, `has_fixed_toolbar`, `align_wide`, and `content_storage` options
- Config values are passed through to the React editor via a `data-settings` attribute
- `bladeberg:install` Artisan command — publishes assets, config, and prints next-steps guidance
- `BladebergRegistry::hasBlock()` alias for `isDynamicBlock()`
- `BladebergRegistry::getRegisteredBlocks()` introspection method
- `window.Bladeberg.getContent(name)` helper to read the current `bb:`-prefixed editor content
- `window.Bladeberg.registerBlock(name, settings)` — reserved forward-compatible API for custom editor blocks (see README "Building custom blocks"; not functional with the current standalone bundle)
- Hardened `BlockParser`: self-closing blocks (`<!-- wp:name /-->`), nested inner blocks, depth-aware closing-tag search, typed `Block` value object with `isNamed()`, `hasInnerBlocks()`, `getAttribute()`, `isSelfClosing`
- `Block::getAttribute(string $key, mixed $default = null)` helper
- PHPDoc coverage across all public APIs
- Unit test suite: `BlockParserTest`, `BladebergRegistryTest`, `BbContentTest`
- `BbContentTest` — covers the `wp:` ↔ `bb:` rebranding (normalize/denormalize, self-closing, namespaced, whitespace variants, idempotency, round-trips)
- `BlockParserTest` — added `bb:` prefix coverage (paragraph, self-closing, attributes, nested, mixed `bb:`/`wp:`)
- `phpunit.xml` inside the package
- `composer test` script

### Changed

- **License changed to GPL-2.0-or-later** (from MIT) for compatibility with the embedded WordPress/Gutenberg editor
- `composer.json`: added `license`, `authors`, `keywords`, `homepage`, `support` links, dev dependencies (`phpunit/phpunit`, `orchestra/testbench`), Packagist alias in `extra.laravel.aliases`
- Fixed `"Illuminate/support"` → `"illuminate/support"` capitalization
- `render.blade.php` passes `$innerBlocks` and `$block` to dynamic block views
- Editor Blade component now uses `htmlspecialchars()` on the settings JSON attribute

---

## [0.0.1] — 2026-05-27

### Added

- Initial working spike
- React + `@automattic/isolated-block-editor` bundled as IIFE
- `<x-bladeberg-editor>` and `<x-bladeberg-render>` Blade components
- `BladebergRegistry::registerDynamicBlock()` API
- Basic `BlockParser` for standard block comments
- Laravel auto-discovery via service provider
- Demo Laravel app with posts create/show

[Unreleased]: https://github.com/BladeBerg/bladeberg/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/BladeBerg/bladeberg/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/BladeBerg/bladeberg/releases/tag/v0.0.1
