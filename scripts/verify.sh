#!/usr/bin/env bash
# Runs the five checks of the package and prints their real outcomes.
# A script on file, and not a chain of commands: inside a chain the `$?` is
# expanded by an outer shell, and every outcome becomes zero by construction.

# The root of the package, wherever it is mounted.
cd "$(dirname "$0")/.." || exit 1

status() {
    local label="$1"
    shift
    "$@" > /tmp/check.out 2>&1
    local code=$?
    printf '%-14s %s\n' "$label" "$code"
    if [ "$code" -ne 0 ]; then
        tail -5 /tmp/check.out
    fi
    return "$code"
}

failed=0
status "pint"    vendor/bin/pint --test || failed=1
status "phpstan" vendor/bin/phpstan analyse --no-progress || failed=1
status "debug"   php scripts/no-debug-leftovers.php || failed=1
status "phpunit" vendor/bin/phpunit --no-coverage || failed=1
status "docs"    npm run docs:build || failed=1

echo
echo "--- the guards"
php scripts/docs-coverage.php > /dev/null 2>&1 && echo "docs-coverage: the coverage reads" || echo "docs-coverage: does not run"
# The coverage gate has to pass when every class is named, and fail when it is not:
# the second case is tried by hand, adding a class and taking it back right after.
php scripts/docs-coverage.php --fail > /dev/null 2>&1 && echo "check:docs: 0 (right when the coverage is complete)" || echo "check:docs: non-zero (some class is not named)"

echo
echo "overall outcome: $failed (0 = all green)"
exit "$failed"
