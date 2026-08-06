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

To develop a package against core, clone it **next to core in the workspace** and
switch on the local override:

```bash
# one-time: clone the package(s) you work on as siblings of core
cd ..                     # the workspace dir that contains core/
git clone git@github.com:seatplus/web.git
git clone git@github.com:seatplus/auth.git

cd core
composer run local:on     # discover seatplus/* checkouts, write composer.local.json
composer update           # resolve seatplus/* from the local checkouts
```

Inspect what is (or would be) linked at any time:

```bash
composer run local:status
```

### How discovery works

`local:on` runs `local-packages.php`, which writes a **gitignored**
`composer.local.json` containing a `type: path` repository per discovered checkout.
`wikimedia/composer-merge-plugin` (configured under `extra.merge-plugin` in
`composer.json`) merges those repositories, so Composer resolves the linked packages
from your local source, symlinked into `vendor/`.

- **Two layouts, in priority order.** `core/packages/<name>` (nested) wins over
  `<workspace>/<name>` (sibling) for the same package, so you can pin one package to
  this checkout while the rest resolve from the workspace. Sibling is the default;
  nested is the fallback for environments where only core is reachable.
- **Detection is by `composer.json` name**, which must start with `seatplus/` — never
  a hardcoded list. A new package needs no tooling change, and unrelated siblings
  (`docs/`, which has no `composer.json`) are skipped automatically. Core itself is
  never path-repo'd.
- **Urls are absolute**, anchored on the primary checkout. This is required for Orca
  worktrees: they live outside the workspace (`~/orca/workspaces/<project>/<slug>`)
  and `.worktreeinclude` copies `composer.local.json` verbatim, so a relative
  `../web` would point at nothing there. Composer treats a missing path repo as a
  *warning* and silently falls back to Packagist, which means your edits appear to do
  nothing. Regenerating inside a worktree reproduces an identical file.
- **Non-numeric branches get a version pin.** Composer derives a version from the
  branch: `5.x` → `5.x-dev` (numeric, satisfies `^5.0`), but `main` or `feat/foo` →
  `dev-main` / `dev-feat/foo`, which satisfy **no** caret constraint at all. The
  generator emits an `options.versions` pin derived from the nearest reachable tag so
  such a checkout stays resolvable. It steps aside if the package declares its own
  `version` or an `extra.branch-alias`.
- **Empty discovery is a non-destructive error.** If nothing is found, `local:on`
  exits non-zero, prints where it looked, and leaves any existing
  `composer.local.json` untouched.

Switch back to the published versions with:

```bash
composer run local:off    # park composer.local.json as composer.local.json.off
composer update           # resolve seatplus/* from Packagist again
```

Because `composer.local.json` is gitignored, CI and production always resolve from
Packagist regardless of your local state.

### `composer.lock` in local mode — do not commit it

`composer.lock` **is tracked**, and CI runs `composer install` against it. A lock
generated while `local:on` is active records the seatplus packages as
`dist.type: path` at dev versions (`5.x-dev`, `2.0.x-dev`, …). Committing that
breaks CI and production, which have no such paths.

```bash
git status --short composer.lock   # M is normal in local mode — leave it unstaged
```

To produce a committable lock, resolve from Packagist first:

```bash
composer run local:off && composer update "seatplus/*" -W --no-scripts
# commit composer.lock, then return to local dev:
composer run local:on  && composer update "seatplus/*" -W
```

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

> ⚠️ **Dedicated test database.** These tests use `RefreshDatabase` (`migrate:fresh`),
> so they must never run against the dev database — that would wipe it. `phpunit.xml`
> pins the test DB to **`seatplus_testing`** (`force="true"`, so it holds regardless
> of the `DB_DATABASE` env var). Create it once before running:
>
> ```bash
> createdb seatplus_testing        # same host/user/password as the dev DB
> ```
>
> CI must provide a `seatplus_testing` database for the browser job. SQLite `:memory:`
> is not usable (pgsql-specific migrations + the in-process browser server).
