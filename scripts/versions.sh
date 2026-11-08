#!/usr/bin/env bash
# Print the exact versions running in the stack (for docs/VERSIONS.md).
set -euo pipefail
cd "$(dirname "$0")/.."
echo "| Component | Version |"
echo "|---|---|"
echo "| Date | $(date -u '+%Y-%m-%d') |"
echo "| WordPress image | $(docker compose images wordpress --format json 2>/dev/null | python3 -c 'import json,sys;d=json.load(sys.stdin);d=d[0] if isinstance(d,list) else d;print(d.get("Repository","")+":"+d.get("Tag",""))' 2>/dev/null) built from \`$(grep -m1 '^ARG WP_IMAGE' docker/wordpress/Dockerfile | cut -d= -f2)\` |"
echo "| PHP | $(scripts/wp.sh php -r 'echo PHP_VERSION;') |"
echo "| WordPress | $(scripts/wp.sh wp core version) |"
echo "| MariaDB | $(docker compose exec -T db mariadb --version | awk '{print $3,$4,$5}') |"
echo "| Redis | $(docker compose exec -T redis redis-server --version | awk '{print $3}' | cut -d= -f2) |"
scripts/wp.sh wp plugin list --fields=name,version,status --format=csv | tail -n +2 | while IFS=, read -r n v s; do echo "| plugin: $n | $v ($s) |"; done
echo "| theme: twentytwentyfive (parent) | $(scripts/wp.sh wp theme get twentytwentyfive --field=version) |"
echo "| WP-CLI | $(scripts/wp.sh wp --version | awk '{print $2}') |"
