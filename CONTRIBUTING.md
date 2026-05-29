# Contributing to BladeBerg

Thank you for considering a contribution to BladeBerg!

---

## Development Setup

```bash
# Clone the repo
git clone https://github.com/BladeBerg/bladeberg.git
cd bladeberg

# Install PHP dependencies
composer install

# Install JS dependencies (editor bundle)
npm install

# Build the editor bundle
npm run build
```

---

## PHP Tests

Unit tests live in `tests/Unit/` and run via PHPUnit:

```bash
composer test
```

Please add or update tests for any code you change.

---

## Building the Editor

The React editor is built as a self-contained IIFE using Vite:

```bash
npm run build
# outputs: dist/bladeberg-editor.iife.js  dist/bladeberg-editor.css
```

Commit the built `dist/` files so users don't need a Node build step.

---

## Coding Standards

- PHP: PSR-12, `declare(strict_types=1)`, typed properties, PHPDoc on public methods
- JS/JSX: standard React conventions, no TypeScript required (yet)

---

## Pull Request Process

1. Fork the repository and create a feature branch from `main`
2. Write tests for your changes
3. Run `composer test` and ensure all tests pass
4. Build the editor bundle if you changed any JS: `npm run build`
5. Update `CHANGELOG.md` under `[Unreleased]`
6. Open a PR with a clear description of the problem and solution

---

## Reporting Bugs

Please open a GitHub issue with:
- PHP / Laravel version
- Steps to reproduce
- Expected vs actual behavior

For security vulnerabilities, do **not** open a public issue — see the README security section.
