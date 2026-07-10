#!/usr/bin/env bash
# Background dev services in the dev container: the Horizon queue worker + the
# scheduler. Run by devcontainer "postStartCommand" on every container start.
#
#   dev-services.sh [start]    start a supervisor that keeps every service running,
#                              restarting any that stop (idempotent)
#   dev-services.sh restart    restart all — REQUIRED to pick up changed job/schedule
#                              code (a queue worker caches code in memory at boot)
#   dev-services.sh stop       stop the supervisor, then all services
#
# `start` launches a lightweight bash supervisor (dev-services.sh __supervise) that
# every SUPERVISE_INTERVAL seconds re-checks each service and restarts it if the
# process has died — so a crashed Horizon/scheduler comes back on its own (with the
# current code). `stop` kills the supervisor first so it can't revive what it stops.
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

# Resolve our own absolute path before cd, so the supervisor can re-exec us
# regardless of the caller's working directory.
SELF=$(cd "$(dirname "$0")" && pwd)/$(basename "$0")

# How often the supervisor re-checks the services (seconds).
SUPERVISE_INTERVAL="${SUPERVISE_INTERVAL:-10}"

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

# The supervisor loop: keep every service running, restarting any that stopped.
# Runs in the foreground (backgrounded by start_supervisor via nohup). start_one is
# only called when a service is down, so a healthy loop is quiet.
supervise_loop() {
    echo "[supervisor] watching horizon + schedule every ${SUPERVISE_INTERVAL}s (pid $$)"
    while :; do
        is_running horizon  || start_one horizon  php artisan horizon
        is_running schedule || start_one schedule php artisan schedule:work
        sleep "$SUPERVISE_INTERVAL"
    done
}

start_supervisor() {
    if is_running supervisor; then
        echo "[supervisor] already running (pid $(cat "$(pidfile supervisor)" 2>/dev/null))"
        return
    fi
    echo "[supervisor] starting → storage/logs/supervisor.log"
    nohup bash "$SELF" __supervise >>"storage/logs/supervisor.log" 2>&1 &
    echo $! >"$(pidfile supervisor)" 2>/dev/null || true
}

# Stop the supervisor (so it won't revive services) but leave the services it
# spawned running — those are separate processes stopped by stop_all.
stop_supervisor() {
    local f pid
    f=$(pidfile supervisor)
    pid=$(cat "$f" 2>/dev/null || true)
    [ -n "${pid:-}" ] && kill "$pid" 2>/dev/null || true
    rm -f "$f" 2>/dev/null || true
    echo "[supervisor] stopped"
}

case "${1:-start}" in
    start)       start_supervisor ;;
    stop)        stop_supervisor; stop_all ;;
    restart)     stop_supervisor; stop_all; sleep 2; start_supervisor ;;
    __supervise) supervise_loop ;;
    *) echo "usage: $0 [start|stop|restart]" >&2; exit 2 ;;
esac
