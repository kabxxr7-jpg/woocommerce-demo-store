<?php
/**
 * Plugin Name: Pear & Porcelain – security hardening
 * Description: The WordPress-level half of the hardening checklist (docs/SECURITY.md). The server-level half
 *              (headers, blocked files, no PHP in uploads) is in the .htaccess written by scripts/state-after.sh.
 *              Active in the "after" state only, so tests/security.sh can show each item failing first.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( pp_mode() !== 'after' ) {
	return;
}

// S1. Admin and login only from the local address. On the public address (through the tunnel)
//     wp-login.php and wp-admin answer 404, so there is nothing to guess passwords against.
//     admin-ajax.php and admin-post.php stay reachable: WooCommerce and forms use them.
function pp_block_public_admin() {
	if ( ! defined( 'PP_PUBLIC_REQUEST' ) || ! PP_PUBLIC_REQUEST || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	$script = basename( $_SERVER['SCRIPT_NAME'] ?? '' );
	if ( in_array( $script, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
		return;
	}
	status_header( 404 );
	nocache_headers();
	exit( 'Not found' );
}
add_action( 'login_init', 'pp_block_public_admin', 0 );
add_action( 'admin_init', 'pp_block_public_admin', 0 );

// S2. No user enumeration: ?author=N and the public users REST endpoint are closed.
add_action( 'template_redirect', function () {
	if ( isset( $_GET['author'] ) || is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 0 );
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! current_user_can( 'list_users' ) ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
	}
	return $endpoints;
} );
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );
add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => $name === 'users' ? false : $provider, 10, 2 );

// S3. XML-RPC off (also refused by .htaccess) and no application passwords.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', fn() => array() );
add_filter( 'wp_is_application_passwords_available', '__return_false' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

// S4. Do not advertise versions.
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'the_generator', '__return_empty_string' );
add_action( 'wp_head', function () {
	remove_action( 'wp_head', array( $GLOBALS['woocommerce'] ?? null, 'generator' ) );
}, 0 );
add_filter( 'get_the_generator_html', '__return_empty_string' );
add_filter( 'get_the_generator_xhtml', '__return_empty_string' );

// S5. Same message for a wrong user name and a wrong password.
add_filter( 'login_errors', fn() => 'Wrong user name or password.' );

// S6. Security headers (CSP, HSTS, nosniff, frame options ...) are set in .htaccess, so that cached pages
//     and static files get them too. See scripts/wp/htaccess-after.txt.
