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
# Idempotency uses a pidfile + `kill -0`, not a process-name match: this container has
# no pgrep/pkill/ps, and Horizon retitles its own process (so it isn't findable as
# "artisan horizon"). Pidfiles live in /tmp (container-ephemeral) so a container
# restart starts clean with no stale-PID false positives.
set -uo pipefail

cd /workspace || exit 0
mkdir -p storage/logs

pidfile() { echo "/tmp/dev-service-$1.pid"; }

is_running() {
    local f
    f=$(pidfile "$1")
    [ -f "$f" ] && kill -0 "$(cat "$f" 2>/dev/null)" 2>/dev/null
}

start_one() {
    local name="$1"
    shift
    if is_running "$name"; then
        echo "[$name] already running (pid $(cat "$(pidfile "$name")"))"
        return
    fi
    echo "[$name] starting → storage/logs/$name.log"
    nohup "$@" >>"storage/logs/$name.log" 2>&1 &
    echo $! >"$(pidfile "$name")"
}

stop_one() {
    local name="$1" f pid
    # Horizon: ask it to drain the current job and shut its whole tree down gracefully.
    if [ "$name" = horizon ]; then
        php artisan horizon:terminate >/dev/null 2>&1
    fi
    f=$(pidfile "$name")
    pid=$(cat "$f" 2>/dev/null)
    if [ -n "${pid:-}" ]; then
        kill "$pid" 2>/dev/null
    fi
    rm -f "$f"
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
