#!/usr/bin/env bash
# Background dev services in the dev container: the Horizon queue worker + the
# scheduler. Run by devcontainer "postStartCommand" on every container start.
#
#   dev-services.sh [start]    start any not-already-running service (idempotent)
#   dev-services.sh restart    restart all — REQUIRED to pick up changed job/schedule
#                              code (a queue worker caches code in memory at boot)
#   dev-services.sh stop       stop all
#
# Not started here: `php artisan serve` and `npm run dev` are run on demand (keeping
# Vite from writing public/hot, which would flip the app into dev mode under the
# build-based browser tests).
#
# Pidfiles are PER-USER (they include the uid). postStartCommand may run as root while
# a developer runs this manually as 'dev'; per-user paths mean each owner only ever
# reads/writes/removes its own pidfile, so neither hits "operation not permitted" on the
# other's file. (/tmp is container-ephemeral → no stale PIDs across container restarts.)
set -uo pipefail

cd /workspace || exit 0
mkdir -p storage/logs

pidfile() { echo "/tmp/dev-service-$1.$(id -u).pid"; }

is_running() {
    local f
    f=$(pidfile "$1")
    [ -f "$f" ] && kill -0 "$(cat "$f" 2>/dev/null)" 2>/dev/null
}

# Kill our own processes whose command line matches a pattern. This image has no
# pgrep/pkill/ps, so scan /proc directly. Used to catch untracked schedule:work orphans;
# Horizon retitles its process (not matchable) and is stopped via horizon:terminate.
kill_matching() {
    local pattern="$1" d p cmd
    for d in /proc/[0-9]*; do
        p=${d#/proc/}
        [ "$p" = "$$" ] && continue
        cmd=$(tr '\0' ' ' <"$d/cmdline" 2>/dev/null) || continue
        # Only the PHP worker itself (cmdline begins with the php binary) — never a
        # shell/tool that merely mentions the pattern (which would kill the caller).
        case "$cmd" in
            php\ *|*/php\ *) : ;;
            *) continue ;;
        esac
        case "$cmd" in
            *"$pattern"*) kill "$p" 2>/dev/null || true ;;
        esac
    done
}

start_one() {
    local name="$1"
    shift
    if is_running "$name"; then
        echo "[$name] already running (pid $(cat "$(pidfile "$name")" 2>/dev/null))"
        return
    fi
    echo "[$name] starting → storage/logs/$name.log"
    nohup "$@" >>"storage/logs/$name.log" 2>&1 &
    echo $! >"$(pidfile "$name")" 2>/dev/null || true
}

stop_one() {
    local name="$1" f pid
    # Horizon: drain the current job and shut its whole tree down gracefully (Redis
    # signal — works regardless of which user owns the master process).
    if [ "$name" = horizon ]; then
        php artisan horizon:terminate >/dev/null 2>&1 || true
    fi
    # schedule:work has no graceful-stop command; kill any of our instances directly.
    if [ "$name" = schedule ]; then
        kill_matching 'artisan schedule:work'
    fi
    f=$(pidfile "$name")
    pid=$(cat "$f" 2>/dev/null || true)
    [ -n "${pid:-}" ] && kill "$pid" 2>/dev/null || true
    rm -f "$f" 2>/dev/null || true
    echo "[$name] stopped"
}

start_all() {
    start_one horizon  php artisan horizon
    start_one schedule php artisan schedule:work
}

stop_all() {
    stop_one horizon
    stop_one schedule
}

case "${1:-start}" in
    start)   start_all ;;
    stop)    stop_all ;;
    restart) stop_all; sleep 2; start_all ;;
    *) echo "usage: $0 [start|stop|restart]" >&2; exit 2 ;;
esac
