#!/usr/bin/env bash
#
# Runs the checks of the package from anywhere.
#
# Natively when PHP and Composer are at hand (CI, a machine that works on the package); through
# DDEV when they are not, and the package is mounted inside the container of a host application
# (the usual case on a machine that develops the host, not the package).
#
#   ./scripts/check.sh all          everything the CI runs
#   ./scripts/check.sh verify       the five gates, with the guards
#   ./scripts/check.sh env          which environment would run, and nothing else
#
# The scripts of composer.json are what actually run: this file only decides **where**, so the
# two never drift. Override the guessing with:
#
#   FF_ENV=native|ddev        which environment to use
#   FF_DDEV_DIR=<dir>         the DDEV project that holds the package
#   FF_CONTAINER_PATH=<path>  where the package sits inside that container
#
set -euo pipefail

BOLD=$'\033[1m'
GREEN=$'\033[0;32m'
YELLOW=$'\033[0;33m'
RED=$'\033[0;31m'
CYAN=$'\033[0;36m'
RESET=$'\033[0m'

ok() { printf '%s✓%s %s\n' "$GREEN" "$RESET" "$*"; }
info() { printf '%s→%s %s\n' "$CYAN" "$RESET" "$*"; }
warn() { printf '%s⚠%s  %s\n' "$YELLOW" "$RESET" "$*"; }
fail() {
    printf '%s✗%s %s\n' "$RED" "$RESET" "$*" >&2
    exit 1
}

PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ---------------------------------------------------------------------------
# Environment
# ---------------------------------------------------------------------------

# The composer script a command runs: one place, so the commands of the package stay the only
# source of truth.
composer_script_for() {
    case "$1" in
        all) echo "check:all" ;;
        check) echo "check" ;;
        verify) echo "verify" ;;
        lint) echo "lint" ;;
        lint:fix) echo "lint:fix" ;;
        phpstan) echo "phpstan" ;;
        test) echo "test" ;;
        debug) echo "check:debug" ;;
        coverage) echo "docs:coverage" ;;
        comments) echo "docs:comments" ;;
        *) echo "" ;;
    esac
}

has() { command -v "$1" >/dev/null 2>&1; }

# The DDEV project that holds the package: the one declared, the folder the package lives in,
# or any ancestor that carries a DDEV configuration.
find_ddev_project() {
    if [ -n "${FF_DDEV_DIR:-}" ]; then
        [ -f "$FF_DDEV_DIR/.ddev/config.yaml" ] || fail "FF_DDEV_DIR=$FF_DDEV_DIR is not a DDEV project."
        echo "$FF_DDEV_DIR"
        return
    fi

    local dir="$PACKAGE_DIR"
    while [ "$dir" != "/" ]; do
        if [ -f "$dir/.ddev/config.yaml" ]; then
            echo "$dir"
            return
        fi
        dir="$(dirname "$dir")"
    done

    # The package kept beside the host project it is developed with. `dirname` and not `..`: the
    # package is usually reached through a symlink, and the kernel would follow the link instead
    # of the folder the user typed — landing in the wrong neighbourhood.
    local logical_parent physical_parent
    logical_parent="$(dirname "$PACKAGE_DIR")"
    physical_parent="$(dirname "$(cd "$PACKAGE_DIR" && pwd -P)")"

    local sibling
    for sibling in "$logical_parent/platform" "$physical_parent/platform"; do
        if [ -f "$sibling/.ddev/config.yaml" ]; then
            (cd "$sibling" && pwd)
            return
        fi
    done

    echo ""
}

# Where the package sits inside the container: the declared path, the host project that requires
# it, or the folder beside the project.
find_container_path() {
    local project="$1"
    local candidates=""

    [ -n "${FF_CONTAINER_PATH:-}" ] && candidates="$FF_CONTAINER_PATH"

    if [ -d "$project/vendor/robyconte/filament-flow" ]; then
        candidates="$candidates
vendor/robyconte/filament-flow"
    fi

    candidates="$candidates
/var/www/filament-flow"

    if [ -d "$project/packages/robyconte/filament-flow" ]; then
        candidates="$candidates
/var/www/html/packages/robyconte/filament-flow"
    fi

    local candidate
    for candidate in $candidates; do
        if (cd "$project" && ddev exec test -d "$candidate" >/dev/null 2>&1); then
            echo "$candidate"
            return
        fi
    done

    echo ""
}

ENV_KIND=""
ENV_DDEV_DIR=""
ENV_CONTAINER_PATH=""

resolve_environment() {
    local forced="${FF_ENV:-}"

    if [ "$forced" != "ddev" ] && has php && has composer; then
        ENV_KIND="native"
        ENV_DDEV_DIR=""
        ENV_CONTAINER_PATH="$PACKAGE_DIR"
        return
    fi

    if [ "$forced" = "native" ]; then
        fail "FF_ENV=native, but php and composer are not both on the PATH."
    fi

    has ddev || fail "Neither php/composer nor ddev are available: install one of the two, or run this from a machine that has PHP."

    ENV_DDEV_DIR="$(find_ddev_project)"
    [ -n "$ENV_DDEV_DIR" ] || fail "No DDEV project found around $PACKAGE_DIR. Set FF_DDEV_DIR to the project that holds the package."

    ENV_CONTAINER_PATH="$(find_container_path "$ENV_DDEV_DIR")"
    [ -n "$ENV_CONTAINER_PATH" ] || fail "The package is not reachable inside the container of $ENV_DDEV_DIR. Set FF_CONTAINER_PATH to where it sits there."

    ENV_KIND="ddev"
}

describe_environment() {
    if [ "$ENV_KIND" = "native" ]; then
        info "environment: native (php $(php -r 'echo PHP_VERSION;'))"
        return
    fi

    info "environment: ddev ($ENV_DDEV_DIR → $ENV_CONTAINER_PATH)"
}

# ---------------------------------------------------------------------------
# Running
# ---------------------------------------------------------------------------

run_command() {
    local command="$1"

    if [ "$ENV_KIND" = "native" ]; then
        (cd "$PACKAGE_DIR" && eval "$command")
        return
    fi

    (cd "$ENV_DDEV_DIR" && ddev exec bash -c "cd '$ENV_CONTAINER_PATH' && $command")
}

run_composer_script() {
    local script="$1"

    describe_environment
    info "composer $script"
    echo

    run_command "composer $script"
}

# ---------------------------------------------------------------------------
# Commands
# ---------------------------------------------------------------------------

cmd_help() {
    echo ""
    printf '%sfilament-flow — the checks%s\n' "$BOLD" "$RESET"
    printf '%s────────────────────────────────────────────────────────────%s\n' "$CYAN" "$RESET"
    printf '%sCHECKS%s\n' "$BOLD" "$RESET"
    echo "  all          lint + phpstan + debug leftovers + docs coverage + tests"
    echo "  verify       the five gates of CI, with the guards, coloured"
    echo "  check        the fast gate: lint + phpstan + debug leftovers"
    echo ""
    printf '%sSINGLE CHECKS%s\n' "$BOLD" "$RESET"
    echo "  lint         code style, read only (Pint)"
    echo "  lint:fix     code style, fixing what it can"
    echo "  phpstan      static analysis"
    echo "  test         the test suite (PHPUnit)"
    echo "  debug        debug leftovers (dd, dump, ray, …)"
    echo "  coverage     which public classes the docs name"
    echo "  comments     that the doc blocks hold comments only"
    echo ""
    printf '%sENVIRONMENT%s\n' "$BOLD" "$RESET"
    echo "  env          which environment would run the checks, and nothing else"
    echo "  help         this page"
    echo ""
    echo "Options through the environment:"
    echo "  FF_ENV=native|ddev   FF_DDEV_DIR=<dir>   FF_CONTAINER_PATH=<path>"
    echo ""
}

cmd_env() {
    resolve_environment
    describe_environment
    echo "  package:  $PACKAGE_DIR"
    echo "  runs in:  $ENV_CONTAINER_PATH"
}

main() {
    local name="${1:-help}"
    shift || true

    case "$name" in
        help|-h|--help) cmd_help; return 0 ;;
        env) cmd_env; return 0 ;;
    esac

    local script
    script="$(composer_script_for "$name")"

    if [ -z "$script" ]; then
        fail "Unknown command: $name. Run: ./scripts/check.sh help"
    fi

    resolve_environment
    run_composer_script "$script"
}

main "$@"
