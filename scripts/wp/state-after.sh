#!/usr/bin/env bash
# INSIDE the container: put the store in the fast, hardened "after" state. Idempotent.
set -euo pipefail
cd /var/www/html
wp() { command wp --path=/var/www/html "$@"; }

echo "--> backup first (database), as on a real store"
mkdir -p /tmp/pp-backups
wp db export "/tmp/pp-backups/before-cleanup-$(date +%Y%m%d-%H%M%S).sql" --quiet && touch /tmp/pp-backup-done

echo "--> plugins: remove what the store does not need, keep the form plugin but load it only on /contact/"
wp option update pp_mode after --quiet
for p in ml-slider font-awesome; do
  wp plugin is-installed "$p" && wp plugin deactivate "$p" --uninstall --quiet || true
  wp plugin delete "$p" --quiet 2>/dev/null || true
done
wp plugin is-installed contact-form-7 || wp plugin install contact-form-7 --activate --quiet
wp plugin activate contact-form-7 --quiet
wp eval-file /scripts/after-content.php

echo "--> database cleanup"
wp eval-file /scripts/db-cleanup.php

echo "--> images: regenerate every size as WebP"
wp media regenerate --yes --quiet 2>&1 | tail -2

echo "--> object cache (Redis) and page cache (WP Super Cache)"
wp plugin install redis-cache wp-super-cache limit-login-attempts-reloaded --activate --quiet
wp redis enable --force --quiet || wp redis enable --force
bash /scripts/wpsc-enable.sh

echo "--> server rules: compression, browser caching, security headers and blocks"
cp /scripts/htaccess-after.txt .htaccess

wp cache flush --quiet
echo "--> after state ready"
