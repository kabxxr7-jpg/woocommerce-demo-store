#!/usr/bin/env bash
# Runs INSIDE the wordpress container as www-data (scripts/wp.sh bash /scripts/install.sh).
# Installs WordPress and WooCommerce and seeds the fictional catalogue. Safe to re-run: it starts from an empty database.
set -euo pipefail
cd /var/www/html
wp() { command wp --path=/var/www/html "$@"; }

# Wait for the image's entrypoint to finish copying WordPress into the volume.
for i in $(seq 1 60); do [[ -f wp-includes/version.php && -f wp-config.php ]] && break; sleep 1; done

echo "--> fresh database"
wp db reset --yes --quiet
wp core install --url="https://${STORE_PUBLIC_HOST}" --title="Pear & Porcelain" \
  --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
  --admin_email="owner@pear-porcelain.example" --skip-email --quiet
wp option update blogdescription "A fictional porcelain shop: a working WooCommerce demo" --quiet
wp option update permalink_structure '/%postname%/' --quiet
wp option update timezone_string 'UTC' --quiet
wp option update default_comment_status closed --quiet
wp option update users_can_register 0 --quiet
wp post delete $(wp post list --post_type=post,page --format=ids) --force --quiet 2>/dev/null || true
wp plugin delete akismet hello --quiet 2>/dev/null || true
for t in $(wp theme list --field=name); do
  [[ "$t" == twentytwentyfive || "$t" == pear-porcelain ]] || wp theme delete "$t" --quiet || true
done

echo "--> theme and WooCommerce"
wp theme is-installed twentytwentyfive || wp theme install twentytwentyfive --quiet
wp theme activate pear-porcelain --quiet
wp plugin install woocommerce --activate --quiet
wp rewrite flush --quiet

echo "--> catalogue, pages and settings"
# Seed in the "before" state so the photos get ordinary JPEG sizes, as on a store nobody has optimised yet.
wp option update pp_mode before --quiet
wp eval-file /scripts/seed.php
wp rewrite flush --quiet
for t in _wc_activation_redirect; do wp transient delete "$t" >/dev/null 2>&1 || true; done
echo "--> installed: WordPress $(wp core version), WooCommerce $(wp plugin get woocommerce --field=version)"
