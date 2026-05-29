# Release Checklist — v0.1.0

Follow these steps when you are ready to publish BladeBerg to Packagist.

## 1. Create a GitHub repository

Create a new **public** repository at https://github.com/new, e.g. `BladeBerg/bladeberg`.

## 2. Initialize git (run inside `packages/bladeberg/`)

```bash
cd packages/bladeberg
git init
git add -A
git commit -m "Initial release: Gutenberg inside Laravel"
git remote add origin https://github.com/BladeBerg/bladeberg.git
git branch -M main
git push -u origin main
```

## 3. Tag the release

```bash
git tag v0.1.0
git push origin v0.1.0
```

## 4. Submit to Packagist

1. Log in to https://packagist.org
2. Click **Submit**
3. Enter the repository URL: `https://github.com/BladeBerg/bladeberg`
4. Packagist will read `composer.json` and register the package as `bladeberg/bladeberg`

## 4b. Set up npm publishing (`@bladeberg/editor`)

The repository publishes a second artifact to npm from the same `v*` tag.

1. Own the `@bladeberg` scope on https://npmjs.com.
2. Create an npm **Automation** access token and add it as the `NPM_TOKEN` repository secret (Settings → Secrets and variables → Actions).
3. The `.github/workflows/npm-publish.yml` workflow runs on every `v*` tag: it builds `dist-npm/` and runs `npm publish --access public`.

> One `vX.Y.Z` tag now drives **both** registries: Packagist auto-updates via the GitHub App, and the workflow publishes to npm. Bump `version` in `package.json` to match the tag before releasing.

## 5. Update the demo app

Once the package is live on Packagist, update the root `composer.json`:

```json
"require": {
    "bladeberg/bladeberg": "^0.1"
}
```

And remove the `repositories` path repo entry:

```json
// remove this block:
"repositories": [
    {
        "type": "path",
        "url": "./packages/bladeberg"
    }
]
```

Then run:

```bash
composer update bladeberg/bladeberg
php artisan bladeberg:install
```

## 6. Verify

```bash
composer test          # package unit tests
php artisan test       # demo app feature tests
php artisan serve      # smoke-test at http://localhost:8000/posts
```
