<?php
/**
 * Extra wp-config settings for the Pear & Porcelain demo store (loaded by the official image's wp-config.php).
 *
 * One install, two addresses:
 *  - https://shop.kabiirlabs.com  (public, through a Cloudflare tunnel that connects to 127.0.0.1:8081)
 *  - http://127.0.0.1:8081        (local, for building, testing and the admin)
 * The address WordPress uses is picked from an allow-list, never taken blindly from the Host header.
 */

$pp_public = getenv( 'STORE_PUBLIC_HOST' ) ?: 'shop.kabiirlabs.com';
$pp_locals = array_filter( array_map( 'trim', explode( ',', getenv( 'STORE_LOCAL_HOSTS' ) ?: '127.0.0.1:8081,localhost:8081' ) ) );
$pp_host   = strtolower( $_SERVER['HTTP_HOST'] ?? '' );

if ( $pp_host === $pp_public ) {
	// cloudflared terminates TLS and forwards plain HTTP; tell WordPress the visitor is on HTTPS.
	$_SERVER['HTTPS'] = 'on';
	define( 'PP_PUBLIC_REQUEST', true );
	$pp_base = 'https://' . $pp_public;
} elseif ( in_array( $pp_host, $pp_locals, true ) ) {
	define( 'PP_PUBLIC_REQUEST', false );
	$pp_base = 'http://' . $pp_host;
} else {
	// WP-CLI, cron, or an unknown Host header: use the public address.
	define( 'PP_PUBLIC_REQUEST', php_sapi_name() !== 'cli' );
	$pp_base = 'https://' . $pp_public;
	if ( php_sapi_name() !== 'cli' && $pp_host !== '' ) {
		// Unknown Host header (host header injection, scanners hitting the IP): refuse.
		http_response_code( 421 );
		exit( 'Misdirected request' );
	}
}
define( 'WP_HOME', $pp_base );
define( 'WP_SITEURL', $pp_base );
define( 'PP_PUBLIC_HOST', $pp_public );

// Hardening (see docs/SECURITY.md for the whole checklist and how each item is tested).
define( 'DISALLOW_FILE_EDIT', true );          // no theme/plugin code editor in wp-admin
// WP_DEBUG comes from the official image's wp-config.php (off unless WORDPRESS_DEBUG is set).
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define( 'DISABLE_WP_CRON', true );           // the cron container runs due events every minute
define( 'AUTOMATIC_UPDATER_DISABLED', false );

// Tracking IDs (not secret). Empty = no tag is printed.
define( 'PP_GTM_ID', getenv( 'GTM_ID' ) ?: '' );
define( 'PP_GA4_ID', getenv( 'GA4_ID' ) ?: '' );

// Speed settings that stay the same in both states. The before/after differences are done by
// scripts/state-before.sh and scripts/state-after.sh, which switch WordPress options and plugins.
define( 'WP_REDIS_HOST', 'redis' );
define( 'WP_REDIS_PREFIX', 'pp:' );
define( 'WP_REDIS_DISABLE_BANNERS', true );
define( 'WP_REDIS_DISABLE_COMMENT', true );

// Page cache drop-in (WP Super Cache, "after" state only). On only when the drop-in is present,
// so switching back to the "before" state is just removing the plugin and its drop-in.
defined( 'WP_CACHE' ) || define( 'WP_CACHE', file_exists( '/var/www/html/wp-content/advanced-cache.php' ) );
defined( 'WPCACHEHOME' ) || define( 'WPCACHEHOME', '/var/www/html/wp-content/plugins/wp-super-cache/' );
