<?php
/**
 * Pear & Porcelain child theme.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'pear-porcelain', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
} );

// "After" state: preload the body font (small, used by every line of text). The heading font is large
// (about 190 KB) and uses font-display: swap, so it is left to load normally instead of competing with the hero image.
add_action( 'wp_head', function () {
	if ( function_exists( 'pp_mode' ) && pp_mode() === 'after' ) {
		$base = get_stylesheet_directory_uri() . '/assets/fonts/';
		foreach ( array( 'Manrope-VariableFont_wght-latin.woff2' ) as $f ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $base . $f ) );
		}
	}
}, 2 );

add_action( 'after_setup_theme', function () {
	register_nav_menus( array( 'primary' => 'Primary' ) );
} );
