#!/usr/bin/env bash
#
# orca-web-worktree.sh — point this core checkout at a specific `web` worktree so
# `npm run dev` live-serves that worktree's edits (Orca "repoint per worktree"
# model; see the "Working in Orca" section of CLAUDE.md).
#
# Core resolves the frontend through the `vendor/seatplus/web` symlink; Vite
# watches + republishes from there. Repointing that symlink (and the
# composer.local.json path repo, so a later `composer install` keeps it) is all
# that's needed — no `composer update`, so it works offline.
#
#   ./orca-web-worktree.sh <path-to-web-worktree>   # serve that worktree
#   ./orca-web-worktree.sh reset                     # restore the workspace checkout of seatplus/web
#   ./orca-web-worktree.sh status                    # show current target
#
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
link="$root/vendor/seatplus/web"
local_json="$root/composer.local.json"
# Ask the generator where seatplus/web actually lives — it knows both the sibling
# workspace layout (default) and the nested packages/* fallback, so `reset` stays
# correct without hardcoding either.
default_target="$(php "$root/local-packages.php" path seatplus/web 2>/dev/null || true)"

usage() { grep '^#' "$0" | grep -v '^#!' | sed 's/^# \{0,1\}//'; exit "${1:-0}"; }

show_status() {
    if [[ -L "$link" ]]; then
        echo "vendor/seatplus/web -> $(readlink "$link")  (resolves: $(readlink -f "$link" 2>/dev/null || echo '??'))"
    else
        echo "vendor/seatplus/web is not a symlink (run 'composer install' or 'composer run local:on')"
    fi
}

# Rewrite the "web" path-repo url in composer.local.json, if that file + entry
# exist, so the repoint survives a later composer install. Best-effort.
update_local_json() {
    local target="$1"
    [[ -f "$local_json" ]] || return 0
    php -r '
        $f = $argv[1]; $target = $argv[2];
        $j = json_decode(file_get_contents($f), true);
        if (!is_array($j) || empty($j["repositories"])) { exit(0); }
        $dir = dirname($f);
        $changed = false;
        foreach ($j["repositories"] as &$r) {
            if (($r["type"] ?? "") !== "path") { continue; }
            $url = $r["url"] ?? "";
            // Identify the web path repo by the package it points at (robust
            // across re-repoints), not by url string matching.
            $abs = ($url !== "" && $url[0] === "/") ? $url : "$dir/$url";
            $cj = "$abs/composer.json";
            if (!is_file($cj)) { continue; }
            $name = json_decode(file_get_contents($cj), true)["name"] ?? "";
            if ($name === "seatplus/web" && $url !== $target) {
                $r["url"] = $target; $changed = true;
            }
        }
        unset($r);
        if ($changed) {
            file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            fwrite(STDERR, "updated composer.local.json web url -> $target\n");
        }
    ' "$local_json" "$target" || echo "  (composer.local.json untouched — edit manually if needed)"
}

repoint() {
    local target="$1"
    ln -sfn "$target" "$link"
    echo "✔ vendor/seatplus/web -> $target"
    update_local_json "$target"
    echo
    echo "Now (re)start core's dev server so it watches the new target:"
    echo "    npm run dev"
    echo "Edits in $target/resources/js will republish + HMR into core."
}

cmd="${1:-}"
case "$cmd" in
    ""|-h|--help) usage 0 ;;
    status) show_status ;;
    reset)
        [[ -n "$default_target" ]] || {
            echo "error: no local seatplus/web checkout found — run 'composer run local:on' first" >&2
            exit 1
        }
        # Absolute link, matching the absolute urls local-packages.php emits. A
        # later `composer install` re-normalises it to composer's own form.
        ln -sfn "$default_target" "$link"
        echo "✔ reset: vendor/seatplus/web -> $default_target"
        update_local_json "$default_target"
        ;;
    *)
        [[ -e "$cmd" ]] || { echo "error: worktree path '$cmd' does not exist" >&2; exit 1; }
        target="$(cd "$cmd" && pwd)"
        if [[ ! -f "$target/composer.json" ]] || ! grep -q '"seatplus/web"' "$target/composer.json"; then
            echo "error: '$target' does not look like the seatplus/web package (no matching composer.json)" >&2
            exit 1
        fi
        repoint "$target"
        ;;
esac
