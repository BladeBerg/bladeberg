# Changelog

All notable changes to BladeBerg are documented here.

This project follows [Semantic Versioning](https://semver.org/) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) conventions.

---

## [Unreleased]

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
- `window.Bladeberg.registerBlock(name, settings)` global helper for registering custom blocks from host-app JS
- `window.wp.element`, `window.wp.blockEditor`, `window.wp.components` exposed from the bundled `@wordpress/*` packages
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

[Unreleased]: https://github.com/BladeBerg/bladeberg/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/BladeBerg/bladeberg/releases/tag/v0.0.1
