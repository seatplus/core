<?php

declare(strict_types=1);

/*
 * Toggle local cross-package development.
 *
 *   composer run local:on      (php local-packages.php on)
 *   composer run local:off     (php local-packages.php off)
 *   composer run local:status  (php local-packages.php status)
 *   php local-packages.php path seatplus/web
 *
 * `on` writes composer.local.json with a `type: path` repository per discovered
 * seatplus/* checkout; merge-plugin merges them so composer resolves seatplus/*
 * locally. `off` parks the file as composer.local.json.off. Either way, run
 * `composer update` afterwards to apply.
 *
 * Two layouts are supported, in priority order:
 *
 *   1. NESTED   core/packages/<name>  — checkouts inside core. Needed anywhere
 *      only core is reachable and the sibling clones are not (a bind-mounted or
 *      otherwise partial checkout).
 *   2. SIBLING  <workspace>/<name>    — clones next to core. This is the default
 *      and the layout Orca's layered model produces: each backend package is its
 *      own Orca project, i.e. its own top-level clone. See "Working in Orca" in
 *      CLAUDE.md.
 *
 * Nested wins over sibling for the same package, so you can pin one package to
 * this checkout while the rest resolve from the workspace.
 *
 * Discovery is by composer.json `name` (must be `seatplus/*`) — never a hardcoded
 * list — so a new package needs no change here, and unrelated siblings (docs/,
 * which has no composer.json) are skipped for free.
 *
 * Emitted urls are ABSOLUTE and anchored on the PRIMARY checkout (resolved via
 * `git rev-parse --git-common-dir`), because .worktreeinclude COPIES this file
 * verbatim into Orca worktrees, which live outside the workspace
 * (~/orca/workspaces/<project>/<slug>). A relative `../web` would resolve to a
 * nonexistent path there, and composer treats a missing path repo as a WARNING —
 * silently falling back to Packagist, so your edits appear to do nothing. Anchoring
 * on the primary means regenerating inside a worktree reproduces the same file.
 *
 * composer.local.json is gitignored, so CI/prod always resolve from Packagist.
 */

const ROOT_PACKAGE = 'seatplus/core';

$root = __DIR__;
$file = "$root/composer.local.json";
$parked = "$file.off";
$mode = $argv[1] ?? 'on';

function git(string $dir, string ...$args): ?string
{
    $cmd = 'git -C '.escapeshellarg($dir);

    foreach ($args as $arg) {
        $cmd .= ' '.escapeshellarg($arg);
    }

    $out = @shell_exec($cmd.' 2>/dev/null');
    $out = is_string($out) ? trim($out) : '';

    return $out === '' ? null : $out;
}

/** @return array<string, mixed>|null */
function manifest(string $dir): ?array
{
    if (! is_file("$dir/composer.json")) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents("$dir/composer.json"), true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * The clone that owns this checkout. For a git worktree, --git-common-dir points
 * back at the primary clone's .git — exactly the anchor we want, since sibling
 * packages live next to the PRIMARY core, not next to the worktree.
 */
function primaryRoot(string $root): string
{
    $common = git($root, 'rev-parse', '--path-format=absolute', '--git-common-dir');

    if ($common === null) {
        return $root;
    }

    $candidate = basename($common) === '.git' ? dirname($common) : $common;

    return is_file("$candidate/composer.json") ? $candidate : $root;
}

/**
 * @return array<string, string> package name => absolute path
 */
function discover(string $root, string $primary): array
{
    $searchRoots = [
        "$root/packages",       // nested, this checkout
        "$primary/packages",    // nested, primary (a worktree has none of its own)
        dirname($primary),      // sibling, anchored on the primary clone
    ];

    $found = [];

    foreach ($searchRoots as $searchRoot) {
        foreach (glob("$searchRoot/*", GLOB_ONLYDIR) ?: [] as $dir) {
            $name = manifest($dir)['name'] ?? null;

            if (! is_string($name) || ! str_starts_with($name, 'seatplus/')) {
                continue;
            }

            // dirname($primary) also contains core itself, and would contain any
            // core worktree parked there — never path-repo the root package.
            if ($name === ROOT_PACKAGE) {
                continue;
            }

            $found[$name] ??= realpath($dir) ?: $dir;
        }
    }

    ksort($found);

    return $found;
}

/**
 * Composer derives a version from the checked-out branch: `5.x` -> `5.x-dev`
 * (numeric, satisfies `^5.0`), but `main` / `feat/foo` -> `dev-main` /
 * `dev-feat/foo`, which satisfy NO caret constraint at all. That is what blocks
 * esi-schema on `main`, and it blocks any package worked on from a feature
 * branch. Pin a numeric dev version derived from the nearest reachable tag so a
 * non-numeric branch stays resolvable.
 *
 * @param array<string, mixed> $manifest
 */
function versionOverride(string $dir, array $manifest): ?string
{
    if (isset($manifest['version'])) {
        return null;                                  // package states its own
    }

    $branch = git($dir, 'rev-parse', '--abbrev-ref', 'HEAD');

    if ($branch === null || $branch === 'HEAD') {
        return null;                                  // detached — leave to composer
    }

    if (isset($manifest['extra']['branch-alias']['dev-'.$branch])) {
        return null;                                  // upstream already aliases it
    }

    if (preg_match('/^v?\d+(\.\d+)*(\.x)?$/', $branch) === 1) {
        return null;                                  // 5.x / 4.1 -> already numeric
    }

    $tag = git($dir, 'describe', '--tags', '--abbrev=0');

    if ($tag === null || preg_match('/^v?(\d+)\.(\d+)\./', $tag, $m) !== 1) {
        return null;                                  // nothing to derive from
    }

    return "{$m[1]}.{$m[2]}.x-dev";
}

/**
 * @param array<string, string> $packages
 */
function report(array $packages): void
{
    foreach ($packages as $name => $dir) {
        $branch = git($dir, 'rev-parse', '--abbrev-ref', 'HEAD') ?? '?';
        $version = versionOverride($dir, manifest($dir) ?? []);
        $suffix = $version === null ? '' : "  [pinned $version]";

        echo "  $name  ($branch)  $dir$suffix\n";
    }
}

$primary = primaryRoot($root);

if ($mode === 'off') {
    if (! is_file($file)) {
        echo "Already off — no composer.local.json.\n";
        exit(0);
    }

    rename($file, $parked);
    echo "Local packages OFF — composer.local.json parked as composer.local.json.off\n";
    echo "Run `composer update` to resolve seatplus/* from Packagist. `local:on` regenerates it.\n";
    exit(0);
}

if ($mode === 'path') {
    $wanted = $argv[2] ?? '';
    $packages = discover($root, $primary);

    if (! isset($packages[$wanted])) {
        fwrite(STDERR, "No local checkout found for '$wanted'.\n");
        exit(1);
    }

    echo $packages[$wanted], "\n";
    exit(0);
}

if ($mode === 'status') {
    echo 'checkout:  ', $root, "\n";
    echo 'primary:   ', $primary, "\n";
    echo 'workspace: ', dirname($primary), "\n";
    echo 'override:  ', is_file($file) ? $file : '(none)', "\n\n";

    $packages = discover($root, $primary);
    $packages === [] ? print("no seatplus/* checkouts discovered\n") : report($packages);
    exit(0);
}

// ---- on ---------------------------------------------------------------------

$packages = discover($root, $primary);

if ($packages === []) {
    // NEVER delete an existing override just because discovery came up empty.
    // A wrong anchor (odd cwd, non-git copy, a container without the workspace
    // mounted) is a realistic cause, and silently unlinking a working config is
    // how the previous version of this script destroyed hand-written overrides.
    fwrite(STDERR, "No seatplus/* checkouts found. Looked in:\n");
    fwrite(STDERR, "  $root/packages/*\n  $primary/packages/*\n  ".dirname($primary)."/*\n");
    fwrite(STDERR, is_file($file)
        ? "\nExisting composer.local.json left UNTOUCHED. Use `composer run local:off` to disable it deliberately.\n"
        : "\nNothing to link — staying on Packagist.\n");
    exit(1);
}

$repositories = [];

foreach ($packages as $name => $dir) {
    $repository = ['type' => 'path', 'url' => $dir];
    $version = versionOverride($dir, manifest($dir) ?? []);

    if ($version !== null) {
        $repository['options'] = ['versions' => [$name => $version]];
    }

    $repositories[] = $repository;
}

file_put_contents(
    $file,
    json_encode(['repositories' => $repositories], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
);

is_file($parked) && unlink($parked);

echo count($repositories)." local package repo(s) written to composer.local.json:\n";
report($packages);
echo "\nRun `composer update` to use them.\n";
