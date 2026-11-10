#!/usr/bin/env bash
#
# Prints the notes of a release: the section of CHANGELOG.md that names the version.
#
# It fails when the section is missing or empty, so a version never goes out without its notes:
# the release workflow reads them from here and publishes them as they are.
#
#   scripts/release-notes.sh 0.5.6

set -euo pipefail

version="${1:?usage: release-notes.sh <version, without the v>}"
changelog="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/CHANGELOG.md"

# The heading is matched as text, not as a pattern: a version is full of dots.
notes="$(awk -v heading="## [$version]" 'index($0, heading) == 1 { found = 1; next } /^## / { found = 0 } found' "$changelog")"

if [[ -z "$(printf '%s' "$notes" | tr -d '[:space:]')" ]]; then
    echo "CHANGELOG.md has no notes for version ${version}: add a \"## [${version}] - <date>\" section." >&2
    exit 1
fi

printf '%s\n' "$notes"
