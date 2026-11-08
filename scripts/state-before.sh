#!/usr/bin/env bash
# Put the store in the slow, unhardened "before" state (on purpose, for measuring).
set -euo pipefail
cd "$(dirname "$0")/.."
scripts/wp.sh bash /scripts/state-before.sh
