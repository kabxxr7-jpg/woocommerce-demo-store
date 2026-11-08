<?php
/**
 * Plugin Name: Pear & Porcelain – speed: before and after
 * Description: The "before" half recreates, on purpose, front-end problems that are common on real WooCommerce stores. The "after" half is the fix. Switched by the pp_mode option (scripts/state-before.sh, scripts/state-after.sh).
 * Version: 1.0.0
 *
 * Honest note: nothing in the "before" half is a real client's code. It was written for this demo to recreate
 * typical problems (a theme that loads web fonts and extra libraries on every page, full-size photos in
 * product grids, render-blocking scripts). The plugins used in the "before" state are real wordpress.org plugins.
 */

defined( 'ABSPATH' ) || exit;

if ( pp_mode() === 'before' ) {

	/* ---------------- BEFORE: what a slow store typically does ---------------- */

	// An old theme option that loads four Google Fonts families in many weights, render-blocking, on every page.
	add_action( 'wp_enqueue_scripts', function () {
		wp_enqueue_style( 'legacy-google-fonts', 'https://fonts.googleapis.com/css?family=Playfair+Display:400,400i,700,700i,900|Open+Sans:300,400,600,700,800|Lato:300,400,700,900|Montserrat:300,400,500,600,700', array(), null );
		// jQuery UI and its theme CSS loaded everywhere "for the quick-view popup" that the shop no longer uses.
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-tabs' );
		wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_style( 'legacy-jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', array(), null );
		// WooCommerce's cart fragments script on every page (an AJAX request on each page view).
		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	}, 20 );

	// Product photos printed at full size (3000 px) in grids and on product pages.
	add_filter( 'single_product_archive_thumbnail_size', fn() => 'full' );
	add_filter( 'woocommerce_gallery_image_size', fn() => 'full' );
	add_filter( 'woocommerce_gallery_thumbnail_size', fn() => 'full' );
	add_filter( 'render_block_data', function ( $block ) {
		if ( in_array( $block['blockName'], array( 'woocommerce/product-image', 'core/post-featured-image' ), true ) ) {
			$block['attrs']['imageSizing'] = 'single'; // WooCommerce block: use the large single image size everywhere
			$block['attrs']['sizeSlug']    = 'full';
		}
		return $block;
	} );
	add_filter( 'wp_get_attachment_image_attributes', function ( $attr ) {
		unset( $attr['srcset'], $attr['sizes'] ); // no responsive images
		$attr['loading'] = 'eager';             // no lazy loading
		return $attr;
	}, 99 );
	// Whatever size a template asks for, it gets the original upload (3000 px JPEG), as with a theme that
	// prints the full image and scales it down with CSS.
	add_filter( 'wp_get_attachment_image_src', function ( $image, $id, $size ) {
		if ( is_admin() || ! $image || $size === 'full' ) {
			return $image;
		}
		$meta = wp_get_attachment_metadata( $id );
		$url  = wp_get_attachment_url( $id );
		return ( $meta && $url ) ? array( $url, (int) $meta['width'], (int) $meta['height'], false ) : $image;
	}, 10, 3 );
	add_filter( 'wp_lazy_loading_enabled', '__return_false' );
	add_filter( 'wp_calculate_image_srcset', '__return_false' );
	add_filter( 'single_product_large_thumbnail_size', fn() => 'full' );

	// Old revisions are kept forever.
	add_filter( 'wp_revisions_to_keep', fn() => -1 );

	// Uploads are never scaled down (WordPress's default limit is 2560 px).
	add_filter( 'big_image_size_threshold', '__return_false' );

} else {

	/* ---------------- AFTER: the fixes ---------------- */

	// 1. Scripts: defer everything on the front end that is safe to defer; no jQuery Migrate; no emoji script.
	//    WordPress's own loading-strategy API is used (not a string replace on the tag), so WordPress itself
	//    refuses to defer a script whose inline "after" code or blocking dependents need it to run immediately.
	//    Not on cart, checkout and account pages: their block scripts need wp-api-fetch's inline set-up code
	//    (which WordPress delays together with a deferred script) before their first request. Measured pages are
	//    the home, shop and product pages; cart and checkout are never cached and are visited after them anyway.
	add_action( 'wp_enqueue_scripts', function () {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return;
		}
		$wp_scripts = wp_scripts();
		$handles    = $wp_scripts->queue;
		$seen       = array();
		while ( $handles ) {
			$h = array_pop( $handles );
			if ( isset( $seen[ $h ] ) || ! isset( $wp_scripts->registered[ $h ] ) ) {
				continue;
			}
			$seen[ $h ] = true;
			$handles    = array_merge( $handles, $wp_scripts->registered[ $h ]->deps );
			if ( $wp_scripts->registered[ $h ]->src && ! $wp_scripts->get_data( $h, 'strategy' ) ) {
				$wp_scripts->add_data( $h, 'strategy', 'defer' );
			}
		}
	}, 999 );
	add_action( 'wp_default_scripts', function ( $scripts ) {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
		}
	} );
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );

	// 2. Only load what a page uses: Contact Form 7 on the contact page, cart fragments only where a mini-cart needs it.
	add_filter( 'wpcf7_load_js', fn() => is_page( 'contact' ) );
	add_filter( 'wpcf7_load_css', fn() => is_page( 'contact' ) );
	add_action( 'wp_enqueue_scripts', function () {
		if ( function_exists( 'is_woocommerce' ) && ! is_cart() && ! is_checkout() ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
	}, 99 );

	// 3. Images: generated sizes are WebP; the first large image (the LCP element) loads first, the rest lazily.
	add_filter( 'image_editor_output_format', function ( $formats ) {
		$formats['image/jpeg'] = 'image/webp';
		return $formats;
	} );
	add_filter( 'wp_editor_set_quality', fn( $q, $mime ) => $mime === 'image/webp' ? 78 : $q, 10, 2 );
	add_filter( 'big_image_size_threshold', fn() => 2000 );
	add_filter( 'wp_omit_loading_attr_threshold', fn() => 1 ); // only the first image (the hero) loads eagerly
	add_action( 'wp_head', function () {
		if ( is_front_page() ) {
			$hero = (int) get_option( 'pp_hero_attachment_id' );
			if ( $hero ) {
				$srcset = wp_get_attachment_image_srcset( $hero, 'full' );
				$src    = wp_get_attachment_image_url( $hero, 'large' );
				printf( '<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="100vw" fetchpriority="high">' . "\n", esc_url( $src ), esc_attr( $srcset ) );
			}
		}
	}, 1 );

	// 3b. CSS: inline the many small block stylesheets into the page (one request instead of ~15 render-blocking
	//     ones), and do not load WooCommerce's classic shop CSS on the home page, which is built from blocks only.
	add_filter( 'styles_inline_size_limit', fn() => 60000 );
	// WordPress only inlines stylesheets that carry a 'path'; WooCommerce's block styles and the theme's
	// style.css do not, so give every local stylesheet its file path (WordPress keeps the size limit above).
	add_action( 'wp_head', function () {
		$styles = wp_styles();
		foreach ( $styles->queue as $handle ) {
			$dep = $styles->registered[ $handle ] ?? null;
			if ( ! $dep || ! is_string( $dep->src ) || $styles->get_data( $handle, 'path' ) ) {
				continue;
			}
			$src = strtok( $dep->src, '?' );
			$rel = str_starts_with( $src, home_url() ) ? substr( $src, strlen( home_url() ) ) : ( str_starts_with( $src, '/' ) ? $src : '' );
			if ( $rel && str_ends_with( $rel, '.css' ) && is_file( ABSPATH . ltrim( $rel, '/' ) ) ) {
				$styles->add_data( $handle, 'path', ABSPATH . ltrim( $rel, '/' ) );
			}
		}
	}, 0 ); // before wp_maybe_inline_styles (wp_head priority 1)
	add_action( 'wp_enqueue_scripts', function () {
		if ( is_front_page() ) {
			foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-blocktheme' ) as $h ) {
				wp_dequeue_style( $h );
			}
			// WooCommerce's classic shop script (and jQuery with it) is only needed by classic templates.
			foreach ( array( 'woocommerce', 'wc-jquery-blockui', 'wc-js-cookie', 'wc-add-to-cart' ) as $h ) {
				wp_dequeue_script( $h );
			}
		}
	}, 99 );

	// 4. Keep at most five revisions per post from now on (old ones are deleted by scripts/wp/db-cleanup.php).
	add_filter( 'wp_revisions_to_keep', fn() => 5 );

	// 5. WooCommerce background jobs: keep finished ones for 7 days instead of 30.
	add_filter( 'action_scheduler_retention_period', fn() => 7 * DAY_IN_SECONDS );

	// 6. The block editor's heartbeat is not needed on the front end.
	add_action( 'wp_enqueue_scripts', fn() => wp_deregister_script( 'heartbeat' ), 1 );
}
