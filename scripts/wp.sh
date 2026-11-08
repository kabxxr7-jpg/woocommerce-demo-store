#!/usr/bin/env bash
# Run a command inside the WordPress container as the web server user, with the admin details from .env.
#   scripts/wp.sh wp plugin list
#   scripts/wp.sh bash /scripts/install.sh
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; source .env; set +a
exec docker compose exec -T -u www-data \
  -e WP_ADMIN_USER="$WP_ADMIN_USER" -e WP_ADMIN_PASSWORD="$WP_ADMIN_PASSWORD" -e HOME=/tmp \
  wordpress "$@"
