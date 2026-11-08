#!/usr/bin/env bash
# INSIDE the container: switch on WP Super Cache in "simple" mode (PHP serves cached HTML before WordPress loads),
# with WooCommerce's cart, checkout and account pages, and any visitor with items in the cart, never cached.
set -euo pipefail
cd /var/www/html
P=wp-content/plugins/wp-super-cache
cp "$P/advanced-cache.php" wp-content/advanced-cache.php
cp "$P/wp-cache-config-sample.php" wp-content/wp-cache-config.php
# The sample file ends with a closing ?> tag, so the settings go into a new PHP block.
cat >> wp-content/wp-cache-config.php <<'PHP'
<?php
// ---- Pear & Porcelain settings (scripts/wp/wpsc-enable.sh) ----
$cache_enabled = true;
$super_cache_enabled = true;
$wp_cache_mod_rewrite = 0;          // simple mode: no rewrite rules needed
$cache_compression = 0;             // Apache compresses (mod_deflate)
$cache_max_time = 3600;
$wp_cache_not_logged_in = 2;        // never serve cached pages to logged-in users
$wp_cache_make_known_anon = 0;
$wp_cache_refresh_single_only = 0;
$wp_cache_clear_on_post_edit = 1;
$wp_cache_front_page_checks = 1;
$wp_cache_mfunc_enabled = 0;
$wp_supercache_304 = 0;
$wp_cache_slash_check = 1;        // permalinks end in "/"; without this only the home page is served from cache
$cache_rejected_uri = array( 'wp-.*\\.php', 'index\\.php', '/cart/', '/checkout/', '/my-account/', '/wp-json/', '/\\?add-to-cart=', '/\\?wc-ajax=' );
$wpsc_rejected_cookies = array( 'woocommerce_items_in_cart', 'wp_woocommerce_session_', 'pp_dl_pending' );
$cache_rejected_user_agent = array();
PHP
mkdir -p wp-content/cache
echo "--> WP Super Cache on (simple mode)"
