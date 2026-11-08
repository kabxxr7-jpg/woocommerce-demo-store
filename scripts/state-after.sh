#!/usr/bin/env bash
# Put the store in the fast, hardened "after" state, then warm the page cache.
set -euo pipefail
cd "$(dirname "$0")/.."
scripts/wp.sh bash /scripts/state-after.sh
scripts/wp.sh cat /tmp/pp-db-cleanup-report.txt > reports/db-cleanup.txt 2>/dev/null || true
for p in / /shop/ /product-category/bowls/ /about/ /contact/; do curl -s -o /dev/null http://127.0.0.1:8081$p; done
echo "after state ready: http://127.0.0.1:8081/"
