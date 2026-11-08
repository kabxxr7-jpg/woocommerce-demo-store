<?php
/**
 * Plugin Name: Pear & Porcelain – demo store core
 * Description: Keeps the public demo honest and safe: demo labels, no outgoing email, short order retention, Server-Timing.
 * Version: 1.0.0
 *
 * This store is fictional. Nothing is for sale, no money moves, and visitors are asked not to type real details.
 */

defined( 'ABSPATH' ) || exit;

/** Which state the store is in: 'before' (slow on purpose) or 'after' (fixed). Set by scripts/state-*.sh. */
function pp_mode() {
	return get_option( 'pp_mode', 'after' ) === 'before' ? 'before' : 'after';
}

/* -------------------------------------------------------------------------
 * 1. Demo labels
 * ---------------------------------------------------------------------- */

const PP_DEMO_TEXT = 'Demo store: the shop and its products are fictional. Nothing is for sale and no payment is taken. Please do not enter real personal details.';

// A bar at the very top of every page, and a reminder above the checkout form.
add_action( 'wp_body_open', function () {
	echo '<div class="pp-demo-bar" role="note"><strong>Demo store</strong> · fictional products · no real orders or payments · test checkout only</div>';
}, 1 );

add_filter( 'render_block', function ( $html, $block ) {
	if ( in_array( $block['blockName'], array( 'woocommerce/checkout', 'woocommerce/classic-shortcode' ), true ) && is_checkout() && ! is_order_received_page() ) {
		$note = '<div class="pp-demo-checkout" role="note"><strong>Test checkout.</strong> ' . esc_html( PP_DEMO_TEXT ) .
			' Use made-up details such as “Ada Example, 1 Test Street”. Orders are anonymised after 24 hours.</div>';
		return $note . $html;
	}
	return $html;
}, 99, 2 ); // after WooCommerce adds data-block-name to the block's own wrapper (priority 10), or the checkout never mounts

/* -------------------------------------------------------------------------
 * 2. No outgoing email. Visitors can type any address at checkout; the demo must never mail it.
 * ---------------------------------------------------------------------- */
add_filter( 'pre_wp_mail', function ( $return, $atts ) {
	$to = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'];
	error_log( '[pp-store] email suppressed (demo store): subject="' . ( $atts['subject'] ?? '' ) . '" to=' . count( explode( ',', $to ) ) . ' recipient(s)' );
	return true; // report success so WooCommerce carries on, but nothing is sent
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 3. Payments: only "Cash on delivery", relabelled as a test method.
 * ---------------------------------------------------------------------- */
add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
	return array_intersect_key( $gateways, array( 'cod' => true ) );
} );

// When free delivery applies, offer only that (otherwise the paid rate is pre-selected).
add_filter( 'woocommerce_package_rates', function ( $rates ) {
	$free = array_filter( $rates, fn( $r ) => $r->get_method_id() === 'free_shipping' );
	return $free ?: $rates;
}, 100 );

/* -------------------------------------------------------------------------
 * 4. Server-Timing header: total PHP time and number of database queries for this request.
 *    Lets anyone compare the before and after states in the browser's Network tab.
 * ---------------------------------------------------------------------- */
add_action( 'send_headers', function () {
	if ( is_admin() || headers_sent() ) {
		return;
	}
	ob_start( function ( $buffer ) {
		global $wpdb, $timestart;
		if ( ! headers_sent() ) {
			$ms = isset( $timestart ) ? round( ( microtime( true ) - $timestart ) * 1000, 1 ) : 0;
			header( sprintf( 'Server-Timing: wp;dur=%s;desc="PHP", db;desc="%d queries"', $ms, (int) $wpdb->num_queries ), false );
			header( 'X-Store-State: ' . pp_mode() );
		}
		return $buffer;
	} );
} );

/* -------------------------------------------------------------------------
 * 5. Contact page form: Contact Form 7 when present (mail is suppressed above).
 * ---------------------------------------------------------------------- */
add_shortcode( 'pp_contact_form', function () {
	$id = (int) get_option( 'pp_cf7_form_id' );
	if ( $id && shortcode_exists( 'contact-form-7' ) ) {
		return do_shortcode( '[contact-form-7 id="' . $id . '"]' );
	}
	return '<p><em>The contact form is switched off in this state of the demo.</em></p>';
} );

/* -------------------------------------------------------------------------
 * 6. Delete visitor test orders after 24 hours (WooCommerce's own retention settings do not cover
 *    "processing" orders, which is where cash-on-delivery orders sit).
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! wp_installing() && ! wp_next_scheduled( 'pp_purge_test_orders' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'pp_purge_test_orders' );
	}
} );
add_action( 'pp_purge_test_orders', function () {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return;
	}
	$old = wc_get_orders( array(
		'limit'        => 200,
		'date_created' => '<' . ( time() - DAY_IN_SECONDS ),
		'status'       => array_keys( wc_get_order_statuses() ),
		'return'       => 'objects',
	) );
	foreach ( $old as $order ) {
		if ( $order->get_meta( '_pp_seed_order' ) ) {
			continue; // the store's own fictional history stays
		}
		$order->delete( true );
	}
} );

/* -------------------------------------------------------------------------
 * 7. Meta description (search result snippet): product short description, category description, or the tagline.
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	$text = '';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		$text    = $product ? $product->get_short_description() : '';
	} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
		$text = term_description() ?: 'Fictional porcelain ' . single_term_title( '', false ) . ' in the Pear & Porcelain demo store.';
	} elseif ( is_front_page() || ( function_exists( 'is_shop' ) && is_shop() ) ) {
		$text = 'Pear & Porcelain is a fictional porcelain shop: a working WooCommerce demo with a test checkout, measured speed work, security hardening and GA4 tracking.';
	} elseif ( is_singular() ) {
		$text = get_the_excerpt();
	}
	$text = trim( wp_strip_all_tags( $text ) );
	if ( $text ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_trim_words( $text, 30, '…' ) ) );
	}
}, 1 );
