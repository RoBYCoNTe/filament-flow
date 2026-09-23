#!/usr/bin/env bash
# Verifica i cinque controlli del pacchetto e stampa gli esiti veri.
# Uno script su file, e non una catena di comandi: dentro una catena il `$?` viene
# espanso da una shell esterna, e ogni esito diventa zero per costruzione.

# La radice del pacchetto, ovunque sia montato.
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
echo "--- i guard"
php scripts/docs-coverage.php > /dev/null 2>&1 && echo "docs-coverage: legge la copertura" || echo "docs-coverage: non gira"
# Il cancello della copertura deve passare quando ogni classe e' nominata, e fallire quando no:
# il secondo caso e' provato a mano, aggiungendo una classe e togliendola subito dopo.
php scripts/docs-coverage.php --fail > /dev/null 2>&1 && echo "check:docs: 0 (giusto se la copertura e' completa)" || echo "check:docs: non-zero (qualche classe non e' nominata)"

echo
echo "esito complessivo: $failed (0 = tutto verde)"
exit "$failed"
