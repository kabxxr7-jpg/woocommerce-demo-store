#!/usr/bin/env bash
# Full measurement: before state -> Lighthouse x3 + security checks -> after state -> Lighthouse x3 + security checks.
set -euo pipefail
cd "$(dirname "$0")/.."
scripts/state-before.sh
rm -rf reports/lighthouse/before; node scripts/lighthouse.mjs before
tests/security.sh > reports/security-before.txt 2>&1 || true
scripts/state-after.sh
for p in / /shop/ /product/raspberry-garden-teapot/; do curl -s -o /dev/null "http://127.0.0.1:8081$p"; done
rm -rf reports/lighthouse/after; node scripts/lighthouse.mjs after
tests/security.sh > reports/security-after.txt 2>&1 || true
python3 scripts/speed-report.py > /dev/null
echo "measure-all done"
