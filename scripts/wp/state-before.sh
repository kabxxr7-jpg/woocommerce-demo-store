#!/usr/bin/env bash
# INSIDE the container: put the store in the slow, unhardened "before" state. Idempotent.
set -euo pipefail
cd /var/www/html
wp() { command wp --path=/var/www/html "$@"; }

echo "--> remove the fixes: page cache, object cache, login limiter"
wp redis disable >/dev/null 2>&1 || true
for p in wp-super-cache redis-cache limit-login-attempts-reloaded; do
  wp plugin is-installed "$p" && wp plugin deactivate "$p" --uninstall --quiet || true
  wp plugin delete "$p" --quiet 2>/dev/null || true
done
rm -f wp-content/advanced-cache.php wp-content/object-cache.php wp-content/wp-cache-config.php
# WP Super Cache writes WP_CACHE and WPCACHEHOME into wp-config.php on activation; take them out again.
sed -i -e '/WP_CACHE/d' -e '/WPCACHEHOME/d' wp-config.php 2>/dev/null || true
rm -rf wp-content/cache

echo "--> the plugins a typical slow store has: a slider, a form plugin, an icon plugin"
wp plugin install ml-slider contact-form-7 font-awesome --activate --quiet
wp eval-file /scripts/before-content.php

echo "--> slow settings"
wp option update pp_mode before --quiet

echo "--> images: back to the original JPEG uploads, ordinary JPEG sizes"
wp eval-file /scripts/images-restore-originals.php
wp media regenerate --yes --quiet 2>&1 | tail -1
cp /scripts/htaccess-before.txt .htaccess

echo "--> database: years of normal use"
wp eval-file /scripts/db-bloat.php
wp cache flush --quiet
echo "--> before state ready"
