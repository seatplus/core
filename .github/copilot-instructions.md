# Copilot Instructions

## Project Overview

Seatplus Core is an EVE Online management platform built on Laravel 11. It handles character, corporation, and alliance data via the EVE Swagger Interface (ESI). The codebase is a **monorepo** of four internal packages with a strict one-way dependency hierarchy, managed via `wikimedia/composer-merge-plugin`.

## Package Hierarchy

**This hierarchy is enforced and must never be reversed.** Lower packages must not import from higher packages.

```
esi-client   (no internal deps)      — standalone Guzzle HTTP client, RFC 7234 caching
     ↓
eveapi       (requires esi-client)   — ESI queue jobs, Eloquent models for EVE data, Horizon
     ↓
auth         (requires eveapi)       — THE CORE: EVE OAuth, role system, permission model, SSO compliance
     ↓
web          (requires auth+eveapi)  — OPTIONAL: Vue 3 + Inertia.js frontend, recruiter UI
```

The `web` package is intentionally **omittable**. The `esi-client` + `eveapi` + `auth` stack is a fully self-contained headless service layer. Any new feature that belongs logically in `auth`, `eveapi`, or `esi-client` must not be placed in `web` just for convenience.

Each package has its own `composer.json` merged into the root. The root uses `"replace"` to satisfy cross-package dependencies without fetching from Packagist.

---

## ★ Auth Package — The Core

`packages/auth/` (`Seatplus\Auth`) is the most important package. It has **full test coverage, 100% type coverage, PHPStan analysis**, and is the authoritative implementation of every security, permission, and compliance concern. When in doubt about auth behaviour, the tests in `packages/auth/tests/` are the source of truth.

### User & Character Model

A `User` can own **multiple EVE characters** linked via `CharacterUser`. One character is designated `main_character_id`. All permission resolution and compliance checks operate on the user's full character set, not just the main character.

```
User (has many) → CharacterUser → CharacterInfo (EVE character)
```

### Role System

Roles extend Spatie's `Role` model and carry a `RoleType` enum:

| Type | Behaviour |
|------|-----------|
| `automatic` | Assigned automatically to all users whose characters are in the configured corps/alliances. Cannot be joined manually. |
| `on-request` | User applies → moderator approves/denies. |
| `manual` | Admin explicitly adds/removes individual users. |
| `opt-in` | Criteria-gated self-service: user joins if they meet the criteria. |

The concrete service classes (`AutomaticRoleService`, `OnRequestRoleService`, `ManualRoleService`, `OptInRoleService`) all extend `AbstractRoleService` and implement `RoleServiceInterface`. Always instantiate via the concrete service, never `AbstractRoleService` directly.

`handleMembers()` on any role service re-syncs the membership — calls `syncMembers()` → revokes the Spatie role from users who lost eligibility → grants it to newly eligible users.

### Affiliation System

Every role has `Affiliation` records (polymorphic to `CharacterInfo`, `CorporationInfo`, or `AllianceInfo`) with an `AffiliationType`:

| Type | Meaning |
|------|---------|
| `allowed` | These IDs (and their members) are in scope |
| `inverse` | Everyone **except** these IDs is in scope |
| `forbidden` | Explicitly excluded, always wins |

`RoleAffiliatedIdsService` resolves the final set: `(allowed ∪ inverse_expansion) ∖ forbidden`. Affiliation to a corporation or alliance transitively includes all of its characters.

### Permission Checking

`CanUserService::check(User, ValidateIdsDTO, permissions[], corporation_roles[])` runs a **Laravel Pipeline** to validate a set of EVE entity IDs:

1. Strip IDs the user **owns** (always passes)
2. Strip IDs covered by the user's in-game **corporation roles** (e.g. `Director`)
3. Strip IDs covered by the user's **Spatie permissions**
4. Any remaining IDs = denied

`superuser` permission bypasses everything. Permission objects are **cached per-user for 5 minutes** (`user_permissions_{id}`).

`UserPermissionService` builds the permission object from the user's roles + their characters' in-game corporation roles. `RolePermissionObjectService` maps each role's permissions to its affiliated IDs.

### SSO Scope Compliance

`IsUserCompliantService` answers: "does every character owned by this user have all required OAuth scopes?"

Required scopes are aggregated by `BuildScopesArrayService` from:
- Global scopes (`GlobalSsoScopesService`)
- Corporation-level `SsoScopes` records
- Alliance-level `SsoScopes` records
- Application corporation/alliance scopes (optional, controlled by `$consider_applications` flag)

A user is **non-compliant** if any character is missing any required scope. Non-compliant users have their role memberships set to `RoleMembershipStatus::INACTIVE` — they lose their Spatie roles automatically on next `handleMembers()` call.

`BuildScopesArrayService::get()` accepts either a `User` or a `CharacterInfo` and always returns the full per-character breakdown with `required_scopes` and `missing_scopes`.

### RoleMembership Statuses

| Status | Meaning |
|--------|---------|
| `active` | User has the role and meets all criteria |
| `inactive` | Was a member but is now SSO non-compliant |
| `pending` | Submitted an on-request application awaiting approval |

### Architecture Test

`packages/auth/tests/Architecture/ArchitectureTest.php` asserts no `dd()`/`dump()` calls exist. Keep it passing.

---

## ESI Data Layer (eveapi)

### Data Flow

1. A queued job (`EsiBase` subclass) calls `$this->retrieve()`.
2. `RetrieveFromEsiBase` builds an `EsiRequestContainer` and calls `RetrieveEsiData` facade.
3. `esi-client` fires the Guzzle request with RFC 7234 cache middleware; returns `EsiResponse`.
4. `executeJob()` upserts Eloquent models inside a DB transaction (auto-wrapped by `EsiBase::handle()`).

### ESI Job Pattern

All ESI jobs:
- Extend `EsiBase` (`ShouldQueue + ShouldBeUnique`, 3 tries, exponential backoff via Redis throttle)
- Pass `method`, `endpoint`, `version` to the parent constructor
- Implement capability interfaces from `Seatplus\Eveapi\Esi\` as needed:
  - `HasPathValuesInterface` / `HasPathValues` trait — URL path substitutions
  - `HasRequiredScopeInterface` — authenticated (character-scoped) endpoints
  - `HasQueryParametersInterface` — query string params
  - `HasRequestBodyInterface` — POST body
- Override `tags(): array` (used as unique ID and Horizon display tag)
- Override `executeJob(): void`; already runs inside a DB transaction

```php
class CharacterInfoJob extends EsiBase implements HasPathValuesInterface
{
    use HasPathValues;

    public function __construct(public int $character_id)
    {
        parent::__construct('get', '/characters/{character_id}/', 'v5');
        $this->setPathValues(['character_id' => $character_id]);
    }

    public function tags(): array { return ['character', 'info', "character_id:{$this->character_id}"]; }

    public function executeJob(): void
    {
        $response = $this->retrieve();
        if ($response->isCachedLoad()) return;
        CharacterInfo::updateOrCreate(['character_id' => $this->character_id], [...]);
    }
}
```

---

## Web Package (Optional Frontend)

- Pages live in `packages/web/resources/js/Pages/` as Vue SFCs.
- Inertia.js bridges Laravel controllers to Vue — no separate API layer.
- After route changes run `php artisan wayfinder:generate` to regenerate the typed route file.
- JS/CSS assets are published to the root project via `php artisan vendor:publish --tag=web --force`.
- Custom query macros `whereAffiliatedCorporations` and `whereAffiliatedCharacters` (registered in `WebServiceProvider`) apply affiliation joins; superusers bypass them.

### Recruitment System

Corporations open for recruitment via an `Enlistments` record (eveapi). The web package extends this as `Enlistment` adding a **watchlist** — polymorphic many-to-many of EVE universe entities (systems, regions, types, groups, categories) recruiters track on applicants.

- `Application` is **polymorphic** — `applicationable` is either a `User` (account-wide) or a `CharacterInfo` (single character).
- Applications have statuses: `open`, `accepted`, `rejected`.
- Multi-step review: `Enlistments.steps_count` vs `decision_count` (log entries of type `decision`). Auto-accepted only when all steps pass.
- Recruiters can **impersonate** a recruit (`ImpersonateRecruit`) to see the platform as the applicant — only for `User`-type open applications.
- Recruiters can trigger `UpdateCharacter` on demand to refresh ESI data mid-review.

### Member Compliance (powered by auth)

Answers: "Do corporation members have the required ESI scopes?"

- `SsoScopes` records define required OAuth scopes per corporation or alliance.
- Gated by `view member compliance` permission **or** `director` corporation role (resolved via `CanUserService`).
- `CorporationComplianceResource` computes compliance per user by delegating to `IsUserCompliantService` (auth package).
- `member compliance: review user` permission gates the per-user drill-down.

---

## Commands

### Frontend
```bash
npm run dev       # Vite dev server
npm run build     # Production build
```

### Backend
```bash
php artisan serve
php artisan horizon                                    # Start queue worker
php artisan wayfinder:generate                         # Regen routes after changes
php artisan vendor:publish --tag=web --force           # Republish web package assets
```

### Testing (run from inside each package directory)
```bash
cd packages/auth   # or eveapi, esi-client, web

composer run test              # Full suite: lint + types + type-coverage + unit
composer run test:unit         # Pest unit tests only
composer run test:lint         # Pint formatting check
composer run lint              # Auto-fix formatting with Pint
composer run test:types        # PHPStan / Larastan
composer run test:type-coverage # 100% type coverage required
```

To run a single test file or test by name:
```bash
vendor/bin/pest tests/Unit/Services/SomeServiceTest.php
vendor/bin/pest --filter "test name"
```

### Line coverage (local, Laravel Herd)

`composer run test:unit-coverage` uses `XDEBUG_MODE=coverage` but Herd only activates Xdebug on-demand for **web requests** — CLI PHP bypasses Herd's proxy, so the extension is never injected. To run line coverage from the CLI without permanently enabling Xdebug in Herd, load it inline:

```bash
php -d zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-85-arm64.so \
    -d xdebug.mode=coverage \
    vendor/bin/pest --coverage
```

Adjust the filename for the active PHP version and architecture (e.g. `xdebug-83-arm64.so`, `xdebug-85-x86.so`). The extension files live at `/Applications/Herd.app/Contents/Resources/xdebug/`.

Root-level tests (array cache + sync queue, no PostgreSQL):
```bash
./vendor/bin/phpunit
```

Package tests require **PostgreSQL** (`seatplus`/`secret` @ 127.0.0.1:5432) and **Redis** (127.0.0.1:6379).

## Code Quality

All packages enforce:
- **100% type coverage** — `pest --type-coverage --min=100`
- **PHPStan/Larastan** static analysis
- **Laravel Pint** (PSR-12) formatting

## Code Style — Spatie Guidelines

Follow the [Spatie PHP/Laravel coding guidelines](https://spatie.be/guidelines/laravel) in all PHP code. Key rules that apply here:

### Strings
Prefer string interpolation over `sprintf` and the `.` operator. Extract function-call results to a variable first when they cannot be embedded in `{…}` directly.
```php
// ✅
$greeting = "Hi, I am {$name}.";
$message = "Request for {$method} -> {$uri}";

// ❌
$greeting = 'Hi, I am ' . $name . '.';
$message = sprintf('Request for %s -> %s', $method, $uri);
```

### Types
- Use short nullable notation: `?string` not `string|null`.
- Always declare return types, including `: void` for methods that return nothing.
- Always type properties.

### Docblocks
- Omit docblocks for methods that are fully type-hinted (unless adding context beyond the signature).
- Single-line docblocks preferred. Never use a description that just restates the method name.
- Only use `@throws` when the exception is part of the documented contract.

### Constructor property promotion
Use promoted constructor parameters. Each on its own line with a trailing comma:
```php
public function __construct(
    protected string $firstArgument,
    protected string $secondArgument,
) {}
```

### Naming
- Methods and properties: `camelCase` (including private/protected — no snake_case).
- Config keys and database columns: `snake_case`.

### If statements & flow control
- Always use curly brackets.
- Happy path last: put early-return guard clauses first.
- Avoid `else` — use early returns or ternary instead.
- Prefer separate `if` statements over compound `&&`/`||` conditions for better debuggability.

### Comments
Avoid comments that restate what the code already says. Write expressive code instead. Comments should only explain *why*, not *what*.

### Whitespace
- Blank lines between distinct statements for readability.
- No extra blank lines inside `{}` brackets.

## Testing Conventions

- Package tests use [Orchestra Testbench](https://packages.tools/testbench) with `LazilyRefreshDatabase`.
- Each package's `TestCase` registers all required service providers manually.
- `Factory::guessFactoryNamesUsing(...)` resolves factories across packages by namespace prefix.
- `Model::shouldBeStrict()` is set in `setUp()`.
- `Queue::fake()` is called globally — assert dispatched jobs, don't execute them.
- Helper functions in `tests/Pest.php`: `faker()`, `assignPermissionToTestUser()`, `updateRefreshTokenWithScopes()`.

## Key Configuration

- `EVE_CLIENT_ID`, `EVE_CLIENT_SECRET`, `EVE_CALLBACK_URL` — EVE OAuth credentials
- Trusted proxies set to `*` (reverse proxy support)
- Logging uses `daily` driver

## Autonomy Limits

**Never merge PRs or create git tags/releases without explicit user approval.** Always create the PR, present it to the user, and wait for their go-ahead before merging or tagging.
