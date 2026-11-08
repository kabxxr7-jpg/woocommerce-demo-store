<?php
/**
 * Seeds the fictional Pear & Porcelain store. Run inside the container: wp eval-file /scripts/seed.php
 * Product photos are public-domain (CC0) objects from the Rijksmuseum; products, prices and the shop are made up.
 */

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$catalog = json_decode( file_get_contents( ( getenv( 'PP_SEED_DIR' ) ?: '/seed' ) . '/catalog.json' ), true );

/* ---------- WooCommerce settings ---------- */
$opts = array(
	'woocommerce_store_address'               => '1 Example Lane',
	'woocommerce_store_city'                  => 'Springfield',
	'woocommerce_default_country'             => 'US:OR',
	'woocommerce_store_postcode'              => '97477',
	'woocommerce_currency'                    => 'USD',
	'woocommerce_calc_taxes'                  => 'no',
	'woocommerce_coming_soon'                 => 'no',
	'woocommerce_store_pages_only'            => 'no',
	'woocommerce_allow_tracking'              => 'no',
	'woocommerce_show_marketplace_suggestions' => 'no',
	'woocommerce_enable_guest_checkout'       => 'yes',
	'woocommerce_enable_checkout_login_reminder' => 'no',
	'woocommerce_enable_signup_and_login_from_checkout' => 'no',
	'woocommerce_enable_myaccount_registration' => 'no',
	'woocommerce_ship_to_countries'           => '',
	'woocommerce_enable_reviews'              => 'no',
	'woocommerce_demo_store'                  => 'no', // our own demo bar is used instead
	'woocommerce_weight_unit'                 => 'lbs',
	'woocommerce_dimension_unit'              => 'in',
	// Privacy for a public demo: anything a visitor types is removed quickly (see also pp-00-store-core.php).
	'woocommerce_trash_pending_orders'        => array( 'number' => 1, 'unit' => 'days' ),
	'woocommerce_trash_failed_orders'         => array( 'number' => 1, 'unit' => 'days' ),
	'woocommerce_trash_cancelled_orders'      => array( 'number' => 1, 'unit' => 'days' ),
	'woocommerce_anonymize_completed_orders'  => array( 'number' => 1, 'unit' => 'days' ),
);
foreach ( $opts as $k => $v ) {
	update_option( $k, $v );
}
update_option( 'woocommerce_cod_settings', array(
	'enabled'            => 'yes',
	'title'              => 'Test payment (no money is taken)',
	'description'        => 'This is a demo store. Placing an order creates a test order only: nothing is charged, shipped or emailed.',
	'instructions'       => 'Test order received. Nothing will be charged or shipped: this is a demo store.',
	'enable_for_methods' => array(),
	'enable_for_virtual' => 'yes',
) );
foreach ( array( 'bacs', 'cheque', 'paypal' ) as $g ) {
	update_option( "woocommerce_{$g}_settings", array( 'enabled' => 'no' ) );
}

// Shipping: flat $6, free from $100, one zone for everywhere.
$zone = new WC_Shipping_Zone( 0 );
foreach ( $zone->get_shipping_methods() as $m ) {
	$zone->delete_shipping_method( $m->instance_id );
}
$flat = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'title' => 'Standard delivery (demo)', 'tax_status' => 'none', 'cost' => '6' ) );
$free = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$free}_settings", array( 'title' => 'Free delivery over $100 (demo)', 'requires' => 'min_amount', 'min_amount' => '100' ) );
$zone->save();

// WooCommerce pages (shop, cart, checkout, my account).
WC_Install::create_pages();

/* ---------- Images ---------- */
add_filter( 'big_image_size_threshold', '__return_false' ); // keep the 3000 px originals as "full"

function pp_import_image( $file, $title, $alt, $caption ) {
	$tmp = wp_tempnam( basename( $file ) );
	copy( $file, $tmp );
	$id = media_handle_sideload( array( 'name' => sanitize_title( $title ) . '.jpg', 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	wp_update_post( array( 'ID' => $id, 'post_excerpt' => $caption ) );
	return $id;
}

$hero = $catalog['hero'];
$hero_id = pp_import_image( ( getenv( 'PP_IMAGES_DIR' ) ?: '/seed-images' ) . '/hero.jpg', 'Still life with apples in a Delft blue bowl', 'Painted still life: apples in a blue-and-white Delft bowl on a dark table',
	'Willem de Zwart, Still Life with Apples in a Delft Blue Bowl (Rijksmuseum, public domain, CC0)' );
update_option( 'pp_hero_attachment_id', $hero_id );

/* ---------- Categories and products ---------- */
$cat_ids = array();
foreach ( array( 'Bowls' => 'bowls', 'Cups & Saucers' => 'cups-saucers', 'Teapots & Jugs' => 'teapots-jugs', 'Plates' => 'plates', 'Vases' => 'vases' ) as $name => $slug ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
	$cat_ids[ $name ] = (int) $t['term_id'];
}
$uncat = get_term_by( 'slug', 'uncategorized', 'product_cat' );
if ( $uncat ) {
	update_option( 'default_product_cat', $cat_ids['Bowls'] );
	wp_delete_term( $uncat->term_id, 'product_cat' );
}

$featured_skus = array( 'PP-003', 'PP-005', 'PP-006', 'PP-011', 'PP-013', 'PP-019', 'PP-022', 'PP-024' );
$first_in_cat  = array();
foreach ( $catalog['products'] as $i => $p ) {
	$img = $p['image'];
	$src = preg_replace( '/,\s*(anonymous|Meissener|Porseleinfabriek|Porceleinfabriek|Weesper|Koninklijke|Théodore|Ernest|N\.V\.).*$/u', '', $img['source_title'] );
	$image_id = pp_import_image( ( getenv( 'PP_IMAGES_DIR' ) ?: '/seed-images' ) . "/{$p['sku']}.jpg", $p['name'], $p['name'] . ' – ' . strtolower( $p['short'] ),
		sprintf( 'Photo: %s, Rijksmuseum (public domain, CC0).', $img['source_title'] ) );

	$prod = new WC_Product_Simple();
	$prod->set_name( $p['name'] );
	$prod->set_sku( $p['sku'] );
	$prod->set_regular_price( (string) $p['price'] );
	if ( $p['sale_price'] ) {
		$prod->set_sale_price( (string) $p['sale_price'] );
	}
	$prod->set_short_description( $p['short'] );
	$prod->set_description( sprintf(
		"<p>%s</p>\n<p>Each piece in our (entirely fictional) range is inspired by an object in a museum collection. This one takes its look from <em>%s</em>.</p>\n<ul><li>Dishwasher safe on a gentle cycle</li><li>Lead-free glaze</li><li>Ships in a recycled-card box</li></ul>\n<p><small>Demo product: not for sale. Photo: %s, Rijksmuseum, Amsterdam, public domain (CC0). <a href=\"%s\" rel=\"nofollow noopener\">Source</a>.</small></p>",
		esc_html( $p['short'] ), esc_html( $src ), esc_html( $img['source_title'] ), esc_url( $img['source_page'] )
	) );
	$prod->set_category_ids( array( $cat_ids[ $p['category'] ] ) );
	$prod->set_image_id( $image_id );
	$prod->set_manage_stock( true );
	$prod->set_stock_quantity( 40 + ( $i * 7 ) % 30 );
	$prod->set_weight( (string) ( 1 + ( $i % 4 ) * 0.5 ) );
	$prod->set_featured( in_array( $p['sku'], $featured_skus, true ) );
	$prod->set_status( 'publish' );
	$id = $prod->save();
	if ( ! isset( $first_in_cat[ $p['category'] ] ) ) {
		$first_in_cat[ $p['category'] ] = $image_id;
	}
	WP_CLI::log( sprintf( '    %s  %-34s $%s', $p['sku'], $p['name'], $p['price'] ) );
}
foreach ( $first_in_cat as $cat => $image_id ) {
	update_term_meta( $cat_ids[ $cat ], 'thumbnail_id', $image_id );
}

/* ---------- Content pages ---------- */
function pp_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	$data     = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content );
	if ( $existing ) {
		$data['ID'] = $existing->ID;
	}
	return wp_insert_post( $data );
}

pp_page( 'about', 'About this demo', file_get_contents( ( getenv( 'PP_SCRIPTS_DIR' ) ?: '/scripts' ) . '/content/about.html' ) );
pp_page( 'contact', 'Contact', "<!-- wp:paragraph -->\n<p>This is a demo store, so messages from this form are not sent anywhere: the form shows that Contact Form 7 works and that its scripts load only on this page.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[pp_contact_form]\n<!-- /wp:shortcode -->" );
$privacy = pp_page( 'privacy-policy', 'Privacy', file_get_contents( ( getenv( 'PP_SCRIPTS_DIR' ) ?: '/scripts' ) . '/content/privacy.html' ) );
update_option( 'wp_page_for_privacy_policy', $privacy );

update_option( 'show_on_front', 'posts' ); // the block theme's front-page.html template is used for "/"
WP_CLI::success( 'Catalogue seeded: ' . count( $catalog['products'] ) . ' products, hero image ' . $hero_id );
