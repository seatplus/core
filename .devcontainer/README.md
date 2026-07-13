# Dev container — seatplus-core

A **backend-only** dev container for the seatplus monorepo (PHP 8.5 CLI). It runs the
Laravel backend, package test suites, Horizon, and the scheduler. The **frontend (Vite/npm)
runs natively on the Mac (Herd)** — do *not* run `npm`/`vite` in here (it would hit the Mac's
`node_modules` over the bind mount and throw ESM "wrong platform" errors). PostgreSQL and Redis
are the host's Herd services, reached over `host.docker.internal`.

## What runs automatically

- **`postCreateCommand`** (first create only): `composer install` + create `.env` and an app
  key if missing. `vendor/` is shared via the bind mount, so that's all the backend needs.
- **`postStartCommand`** (every start): `bash .devcontainer/dev-services.sh` — launches the
  dev-services supervisor (below).

## The permanent worker (Horizon + scheduler)

`.devcontainer/dev-services.sh` is a small bash supervisor. It keeps two long-running services
alive, re-checking every `SUPERVISE_INTERVAL` seconds (default 10) and restarting either if it
dies:

- `php artisan horizon` — the queue worker
- `php artisan schedule:work` — the task scheduler

Logs: `storage/logs/{supervisor,horizon,schedule}.log`.

### Restart it to pick up code changes

Queue workers hold your code in memory, so after editing backend/package code you must restart
them or the change won't take effect:

```bash
bash .devcontainer/dev-services.sh restart
```

`restart` stops the supervisor, gracefully `horizon:terminate`s, kills the scheduler, waits, then
relaunches both with the new code. Other subcommands:

```bash
bash .devcontainer/dev-services.sh stop     # stop supervisor + both services
bash .devcontainer/dev-services.sh start    # start supervisor (idempotent; the default)
```

## Rebuilding the container

After changing `.devcontainer/` (Dockerfile, `devcontainer.json`, this supervisor), the running
container must be **rebuilt** — Claude Code, running inside the locked container, cannot update it:

- **PhpStorm:** View → Tool Windows → **Services** → right-click the container → **Rebuild Container**.
- **CLI:** `devcontainer rebuild`.

Named volumes (`seatplus-claude-home`, `seatplus-jetbrains-cache`, `seatplus-jetbrains-data`)
persist Claude auth/memory and PhpStorm indexes across rebuilds, so you won't re-login or re-index.

## Running package tests locally (important)

The container sets `DB_DATABASE=seatplus` (the **dev** database) via `containerEnv`. Package
`phpunit.xml` files pin the test DB to `laravel` with `force="true"`, but **that force does not
win over the container's exported `DB_DATABASE` for Laravel's `env()`** — so an un-prefixed
`vendor/bin/pest` run migrates/refreshes the **dev** database and wipes it.

**Always prefix local package test runs with the test DB:**

```bash
cd packages/eveapi   # or auth, web
DB_DATABASE=laravel php -d memory_limit=1G vendor/bin/pest
```

Each package's `TestCase::setUp()` has a guard that **aborts the suite** if it ever resolves to
`seatplus`, so an un-prefixed run now fails loudly instead of wiping your dev data. (CI is
unaffected — it has no such export.) The `-d memory_limit=1G` avoids the 128 MB CLI default
OOMing the test renderer.

## Connection summary

| Service   | Host                     | Port | Notes                              |
|-----------|--------------------------|------|------------------------------------|
| PostgreSQL| `host.docker.internal`   | 5432 | dev DB `seatplus` / user `seatplus`|
| Redis     | `host.docker.internal`   | 6379 | queues + cache                     |

Only `/workspace` is mounted; host home, SSH keys, and 1Password are not accessible from here.
