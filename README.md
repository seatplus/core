# Seatplus Core

This is the core package of Seatplus. It contains the basic functionality of the Seatplus ecosystem.

At its core it uses the Laravel framework.

These are the adaptions to the Laravel framework required for Seatplus.

## Adaptions

* Trusted proxies are set to `*` in `bootstrap/app.php` (`->withMiddleware(... trustProxies(at: '*'))`) to allow for reverse proxying.
* `config/logging.php` is modified to use the `daily` driver for `LOG_CHANNEL`.

## Local package development (`local:on` / `local:off`)

Core consumes the `seatplus/*` packages (`esi-client`, `esi-schema`, `eveapi`,
`auth`, `web`) from Packagist by default — a plain `composer install` pulls the
published versions, and CI/production resolve them the same way.

To develop a package against core, clone the sibling repo into `packages/<name>`
(these checkouts are gitignored — local-dev only) and switch on the local override:

```bash
# clone the package(s) you want to work on
git clone git@github.com:seatplus/web.git packages/web

composer run local:on     # generate composer.local.json with path repos for
                          # whichever packages/* are present
composer update           # resolve seatplus/* from the local checkouts
```

`local:on` runs `local-packages.php`, which writes a **gitignored**
`composer.local.json` containing a `type: path` repository for each checked-out
`packages/*`. `wikimedia/composer-merge-plugin` (configured under `extra.merge-plugin`
in `composer.json`) merges those repositories, so Composer resolves the linked
packages from your local source (symlinked into `vendor/`). A package that isn't
checked out is simply skipped, so a missing directory never breaks `composer install`.

Switch back to the published versions with:

```bash
composer run local:off    # remove composer.local.json
composer update           # resolve seatplus/* from Packagist again
```

Because `composer.local.json` is gitignored, CI and production always resolve from
Packagist regardless of your local state.

## Browser tests

End-to-end browser tests (Pest 4 + `pest-plugin-browser`, headless Chromium via
Playwright) run against the fully assembled core app. The tests themselves are
**owned by `seatplus/web`** and authored next to the Vue pages; core just runs them
against a real routing/middleware/asset stack.

```bash
composer run browser         # sync the web tests, then run headless
composer run browser:debug   # same, but headed / with debug output
composer run browser:sync    # only copy the tests in (no run)
```

`browser:sync` runs `browser-tests.php`, which mirrors
`vendor/seatplus/web/tests/Browser` into `tests/Browser/web` (the Packagist install
in CI, or the symlinked local checkout under `local:on`). That destination — and
`tests/Browser/Screenshots/` — are gitignored: they are generated, not committed.
The source of truth lives in `seatplus/web`.

Prerequisites: built frontend assets (`npm run build`) and the Playwright browser
binaries. Screenshots from a run land in `tests/Browser/Screenshots/`.
