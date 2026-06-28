# pinoox/app

Single-app Pinoox project for [Pinoox](https://pinoox.com). Your project root **is** the app — no `apps/` folder, no manager.

## Quick start

```bash
composer create-project pinoox/app my-shop
cd my-shop
pinx migrate
pinx dev
```

New projects include a minimal `.env`:

```dotenv
APP_ENV=development
DB_CONNECTION=devdb
```

Use `.env.example` as the full reference when you want to override defaults or connect MySQL/PostgreSQL/SQLite.

The template ships with default identity `com_pinoox_app` / **Pinoox App** so it runs immediately after install. To use your own package name, run `pinx init --package=com_vendor_app --force` or edit `app.php`, `platform/`, namespaces under `Controller/` / `Router/`, and `routes/`.

Or with global pinx CLI:

```bash
composer global require pinoox/pinx-cli
pinx new my-shop --package=com_acme_shop
```

## Commands

| Command | Description |
|---------|-------------|
| `pinx setup` | Migrate platform + app, run seeders |
| `pinx dev` | Local HTTP server (and Vite when configured) |
| `pinx migrate` | App migrations |
| `pinx build` | Build `export/*.pinx` for platform install |
| `pinx release` | Bump version + build signed-ready package |
| `pinx doctor` | Check PHP, paths, and layout |

## Layout

```text
my-shop/
├── app.php              ← package name & pinx settings
├── Controller/ Model/ routes/ theme/
├── resource/            ← app icon & static assets (default icon included)
├── platform/            ← local host + deploy layer (excluded from .pinx build)
│   ├── apps.config.php
│   ├── app-router.config.php
│   ├── domain.config.php
│   ├── pinoox.config.php
│   └── launcher/        ← bootstrap + dev server router
├── config/              ← app-level config only (app.config.php, services, …)
├── bin/pinx
└── vendor/pinoox/pincore
```

### Config layers (do not mix)

| Layer | Path | Examples |
|-------|------|----------|
| **Pincore (framework)** | `vendor/pinoox/pincore/config/` | database, paths — read-only |
| **Project deploy + dev host** | `platform/` | `apps.config.php`, `app-router.config.php`, `domain.config.php`, `launcher/` |
| **Your app** | `config/` | `app.config.php`, `query_route.config.php`, custom `*.config.php` |

`platform/` is **not** included in `pinx build` output — it is only for local development and routing on a single-app checkout. Production installs use the full Pinoox platform's own config.

`PINOOX_PROJECT_CONFIG_PATH=platform` in `.env` points pincore at this folder (default when `platform/` exists).

## Deploy to production platform

1. `pinx build` or `pinx release --sign`
2. Upload the `.pinx` file to a full Pinoox installation
3. Install via **Manager → Applications**

`pinx build` packages your app for installation on a full Pinoox platform. It applies **system defaults** automatically (excludes `vendor/`, `bin/`, `.env`, dev tooling, …) and bundles **only** third-party Composer requires when present. Override in `app.php` only when needed:

```php
'build' => [
    'exclude' => ['my-private-notes/'],  // extra paths only
    'composer' => false,                 // opt out of composer bundling
],
```

## Monorepo development

When working inside the `pinoox/pinoox` repository:

```bash
cd packages/app
composer config repositories.pinx-cli path ../pinx-cli
composer require pinoox/pinx-cli:@dev
```

## GitHub releases

This template includes `.github/workflows/release.yml`. When you **publish** a GitHub Release, CI builds a `.pinx` install package and attaches it to that release.

Release asset name: `{repo-name} v{version}.pinx` — for example `app v1.0.0.pinx` on `pinoox/app`. If `version-name` in `app.php` already starts with `v`, it is not duplicated.

Suggested flow:

```bash
# 1. Bump version locally (optional)
pinx release --yes

# 2. Commit app.php version change
git add app.php
git commit -m "chore: release 1.0.1"

# 3. Tag and push
git tag v1.0.1
git push origin main --tags

# 4. GitHub → Releases → Publish
```

Make sure `version-name` in `app.php` matches the release before you tag. CI does not bump versions — it builds from the tagged commit.

## Package signing

Pinoox signs `.pinx` packages with **Ed25519** (PHP `sodium`). A signed build adds `signature.json` inside the archive. On install, the platform can verify integrity and block updates from a different publisher.

### Generate a signing key (once)

```bash
php vendor/bin/pincore pinx:sign-keygen com_pinoox_app
# optional: --key-id=pinoox:app
```

Default key path for this layout:

```text
pinx/sign.key.json   ← never commit this file
```

Add to `.gitignore`:

```text
/pinx/sign.key.json
```

Publish the **public key** (from `sign.key.json`) in your README or docs. Keep the **secret key** local and in CI secrets only.

### Enable signing in `app.php`

```php
'pinx' => [
    'type' => 'app',
    'minpin' => 3,
    'sign' => [
        'enabled' => true,
        'key' => 'pinx/sign.key.json',
        'key_id' => 'pinoox:app',
        'require' => false,
    ],
],
```

Then build locally:

```bash
pinx build --sign --yes
# or
pinx release --sign --yes
```

### Sign in GitHub Actions

Store the full contents of `sign.key.json` as repository secret `PINX_SIGN_KEY`, then extend the release workflow:

```yaml
- uses: shivammathur/setup-php@v2
  with:
    php-version: '8.2'
    extensions: zip, mbstring, sodium

- name: Prepare signing key
  if: ${{ secrets.PINX_SIGN_KEY != '' }}
  run: |
    mkdir -p pinx
    printf '%s' "${{ secrets.PINX_SIGN_KEY }}" > pinx/sign.key.json

- name: Build pinx package
  run: php bin/pinx build --yes --sign --output=${{ steps.meta.outputs.artifact }}
```

If `pinx.sign.enabled` is `true` in `app.php`, `--sign` is optional — the build signs automatically when the key file exists.

### Trust on the install platform

| Level | Setting | Meaning |
|-------|---------|---------|
| Default | `PINX_VERIFY=true` | Verify signature when `signature.json` is present |
| Official market | `trusted_keys` in `pinx.config.php` | Only allow known publisher public keys |
| Strict | `PINX_REQUIRE_SIGNATURE=true` | Reject unsigned packages |

Example for a trusted publisher on a full Pinoox platform:

```php
// vendor/pinoox/pincore/config/pinx.config.php
'trusted_keys' => [
    'com_pinoox_app' => 'BASE64_PUBLIC_KEY_FROM_sign.key.json',
],
```

### What signing guarantees

- **Integrity** — manifest and payload were not tampered with after signing.
- **Publisher continuity** — updates must come from the same key (stored in `.pinx/identity.json` on the installed app).

Without `trusted_keys`, anyone can ship a signed package with their own key. For official releases, publish your public key and register it on target platforms.

### Do not

- Commit `pinx/sign.key.json` to a public repository.
- Put `secret_key` in `app.php`, `.env`, or workflow logs.
- Rotate signing keys without documenting the new `key_id` and fingerprint in release notes.
