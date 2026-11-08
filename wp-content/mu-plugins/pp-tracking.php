<?php
/**
 * Plugin Name: Pear & Porcelain – GA4 ecommerce dataLayer + Google Tag Manager
 * Description: Pushes GA4 ecommerce events (view_item_list, view_item, add_to_cart, remove_from_cart, view_cart,
 *              begin_checkout, purchase) to window.dataLayer, with Consent Mode v2. GTM is only requested after
 *              the visitor accepts analytics. The GA4 tags themselves live in the GTM container (gtm/container.json).
 * Version: 1.0.0
 *
 * Page-cache safe: events that depend on one visitor (add/remove from cart) are never printed into the HTML.
 * They wait in the visitor's WooCommerce session and the browser collects them from /wp-json/pp/v1/dl-queue.
 */

defined( 'ABSPATH' ) || exit;

const PP_DL_BRAND = 'Pear & Porcelain';

/** GA4 item array for a product. */
function pp_dl_item( $product, $qty = 1, $extra = array() ) {
	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product ) {
		return null;
	}
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$terms  = get_the_terms( $parent->get_id(), 'product_cat' );
	$item   = array(
		'item_id'       => $product->get_sku() ?: (string) $product->get_id(),
		'item_name'     => $product->get_name(),
		'item_brand'    => PP_DL_BRAND,
		'item_category' => ( $terms && ! is_wp_error( $terms ) ) ? html_entity_decode( $terms[0]->name ) : '',
		'price'         => round( (float) wc_get_price_to_display( $product ), 2 ),
		'quantity'      => (int) $qty,
	);
	return array_merge( $item, $extra );
}

/** Sum of price x quantity, rounded (GA4: value excludes tax and shipping). */
function pp_dl_value( $items ) {
	return round( array_sum( array_map( fn( $i ) => $i['price'] * $i['quantity'], $items ) ), 2 );
}

function pp_dl_cart_items() {
	$items = array();
	if ( ! WC()->cart ) {
		return $items;
	}
	foreach ( WC()->cart->get_cart() as $line ) {
		$item = pp_dl_item( $line['data'], $line['quantity'] );
		if ( $item ) {
			$items[] = $item;
		}
	}
	return $items;
}

/* -------------------------------------------------------------------------
 * Consent Mode v2 + GTM loader, first thing in <head>.
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	$gtm = PP_GTM_ID;
	?>
<script id="pp-consent">
(function () {
  var GTM_ID = <?php echo wp_json_encode( $gtm ); ?>, KEY = "pp_consent";
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag("consent", "default", { ad_storage: "denied", analytics_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" });
  var requested = false;
  function loadGtm() {
    if (requested || !GTM_ID) return;
    requested = true;
    window.dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });
    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtm.js?id=" + encodeURIComponent(GTM_ID);
    document.head.appendChild(s);
  }
  function apply(choice) {
    if (choice === "granted") { gtag("consent", "update", { analytics_storage: "granted" }); loadGtm(); }
  }
  // ?pp_ga_debug=1 marks this browser for GA4 DebugView for an hour: the flag is pushed to the dataLayer and the GTM
  // container sends it as debug_mode;
  // ?pp_ga_debug=0 clears it. Normal visitors never have the cookie.
  var dbg = /[?&]pp_ga_debug=([01])/.exec(location.search);
  if (dbg) document.cookie = "pp_ga_debug=" + (dbg[1] === "1" ? "true; max-age=3600" : "; max-age=0") + "; path=/; SameSite=Lax";
  if (/(?:^|; )pp_ga_debug=true/.test(document.cookie)) window.dataLayer.push({ pp_ga_debug: true });
  var stored = null;
  try { stored = localStorage.getItem(KEY); } catch (e) {}
  apply(stored);
  window.ppConsent = {
    stored: stored,
    set: function (choice) {
      try { localStorage.setItem(KEY, choice); } catch (e) {}
      this.stored = choice;
      apply(choice);
      var b = document.getElementById("pp-consent-banner");
      if (b) b.hidden = true;
    }
  };
})();
</script>
	<?php
}, 0 );

// The banner. Analytics only: this demo has no advertising tags, so ad_* consent stays denied.
add_action( 'wp_footer', function () {
	?>
<div id="pp-consent-banner" class="pp-consent" role="dialog" aria-label="Analytics choice" hidden>
  <p>This demo store counts visits and test purchases with Google Analytics, to show how the tracking works. No advertising, and nothing is shared with advertisers.</p>
  <div class="pp-consent__buttons">
    <button type="button" class="pp-consent__decline" onclick="ppConsent.set('denied')">Decline</button>
    <button type="button" class="pp-consent__accept" onclick="ppConsent.set('granted')">Accept analytics</button>
  </div>
</div>
<script>if (window.ppConsent && !window.ppConsent.stored) document.getElementById("pp-consent-banner").hidden = false;</script>
	<?php
}, 5 );

/* -------------------------------------------------------------------------
 * Events known when the page is built (same for every visitor, so safe to cache).
 * ---------------------------------------------------------------------- */
$GLOBALS['pp_dl_lists'] = array(); // list name => product ids, filled while product grids render

// Remember every product shown in a product grid, with the grid's name.
add_filter( 'render_block', function ( $html, $block, $instance ) {
	if ( $block['blockName'] !== 'woocommerce/product-button' && $block['blockName'] !== 'woocommerce/product-price' ) {
		return $html;
	}
	$id = $instance->context['postId'] ?? 0;
	if ( ! $id || get_post_type( $id ) !== 'product' || is_product() && (int) get_queried_object_id() === (int) $id ) {
		return $html;
	}
	$list = $instance->context['pp_list'] ?? null;
	if ( ! $list ) {
		$list = is_front_page() ? 'Home' : ( is_product() ? 'Related products' : ( is_product_category() ? 'Category: ' . single_term_title( '', false ) : ( is_search() ? 'Search results' : 'Shop' ) ) );
	}
	if ( ! in_array( $id, $GLOBALS['pp_dl_lists'][ $list ] ?? array(), true ) ) {
		$GLOBALS['pp_dl_lists'][ $list ][] = $id;
	}
	return $html;
}, 10, 3 );

function pp_dl_print( $event, $ecommerce ) {
	printf(
		"<script>window.dataLayer=window.dataLayer||[];dataLayer.push({ecommerce:null});dataLayer.push(%s);</script>\n",
		wp_json_encode( array( 'event' => $event, 'ecommerce' => $ecommerce ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
}

add_action( 'wp_footer', function () {
	$currency = get_woocommerce_currency();

	foreach ( $GLOBALS['pp_dl_lists'] as $list => $ids ) {
		$items = array();
		foreach ( $ids as $i => $id ) {
			$item = pp_dl_item( $id, 1, array( 'index' => $i, 'item_list_id' => sanitize_title( $list ), 'item_list_name' => $list ) );
			if ( $item ) {
				$items[] = $item;
			}
		}
		if ( $items ) {
			pp_dl_print( 'view_item_list', array( 'item_list_id' => sanitize_title( $list ), 'item_list_name' => $list, 'items' => $items ) );
		}
	}

	if ( is_product() ) {
		$item = pp_dl_item( get_queried_object_id() );
		if ( $item ) {
			pp_dl_print( 'view_item', array( 'currency' => $currency, 'value' => $item['price'], 'items' => array( $item ) ) );
		}
	}

	if ( is_cart() ) {
		$items = pp_dl_cart_items();
		if ( $items ) {
			pp_dl_print( 'view_cart', array( 'currency' => $currency, 'value' => pp_dl_value( $items ), 'items' => $items ) );
		}
	}

	if ( is_checkout() && ! is_order_received_page() ) {
		$items = pp_dl_cart_items();
		if ( $items ) {
			$coupons = WC()->cart ? implode( ',', WC()->cart->get_applied_coupons() ) : '';
			pp_dl_print( 'begin_checkout', array_filter( array( 'currency' => $currency, 'value' => pp_dl_value( $items ), 'coupon' => $coupons, 'items' => $items ) ) );
		}
	}

	if ( is_order_received_page() ) {
		pp_dl_purchase();
	}
}, 20 );

/** purchase: once per order, only for the visitor who holds the order key. */
function pp_dl_purchase() {
	$order_id = absint( get_query_var( 'order-received' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || $order->has_status( 'failed' ) ) {
		return;
	}
	if ( $order->get_meta( '_pp_ga4_purchase_sent' ) ) {
		echo "<!-- purchase already sent for this order: not sent again on reload -->\n";
		return;
	}
	$items = array();
	foreach ( $order->get_items() as $line ) {
		$product = $line->get_product();
		$qty     = $line->get_quantity();
		$item    = $product ? pp_dl_item( $product, $qty ) : null;
		if ( $item ) {
			$item['price'] = round( (float) $order->get_item_subtotal( $line, false, false ), 2 ); // price actually charged
			$items[]       = $item;
		}
	}
	pp_dl_print( 'purchase', array(
		'transaction_id' => (string) $order->get_order_number(),
		'currency'       => $order->get_currency(),
		'value'          => pp_dl_value( $items ),
		'tax'            => round( (float) $order->get_total_tax(), 2 ),
		'shipping'       => round( (float) $order->get_shipping_total(), 2 ),
		'coupon'         => implode( ',', $order->get_coupon_codes() ),
		'items'          => $items,
	) );
	$order->update_meta_data( '_pp_ga4_purchase_sent', gmdate( 'c' ) );
	$order->save();
}

/* -------------------------------------------------------------------------
 * Events from cart changes. They happen in a form post, an AJAX call or a Store API call, so they are queued in
 * the visitor's session and collected by the browser (never printed into a page that a cache could share).
 * ---------------------------------------------------------------------- */
function pp_dl_queue( $event, $ecommerce ) {
	if ( ! WC()->session ) {
		return;
	}
	$queue   = (array) WC()->session->get( 'pp_dl_queue', array() );
	$queue[] = array( 'event' => $event, 'ecommerce' => $ecommerce );
	WC()->session->set( 'pp_dl_queue', array_slice( $queue, -20 ) );
	if ( ! headers_sent() ) {
		wc_setcookie( 'pp_dl_pending', '1', 0, is_ssl(), false );
	}
}

add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty, $variation_id ) {
	$item = pp_dl_item( $variation_id ?: $product_id, $qty );
	if ( $item ) {
		pp_dl_queue( 'add_to_cart', array( 'currency' => get_woocommerce_currency(), 'value' => pp_dl_value( array( $item ) ), 'items' => array( $item ) ) );
	}
}, 10, 4 );

add_action( 'woocommerce_remove_cart_item', function ( $key, $cart ) {
	$line = $cart->cart_contents[ $key ] ?? null;
	if ( $line ) {
		$item = pp_dl_item( $line['data'], $line['quantity'] );
		pp_dl_queue( 'remove_from_cart', array( 'currency' => get_woocommerce_currency(), 'value' => pp_dl_value( array( $item ) ), 'items' => array( $item ) ) );
	}
}, 10, 2 );

// Quantity lowered to 0 in the cart counts as a removal too; a lower quantity sends remove_from_cart for the difference.
add_action( 'woocommerce_after_cart_item_quantity_update', function ( $key, $qty, $old_qty, $cart ) {
	$line = $cart->cart_contents[ $key ] ?? null;
	if ( ! $line || $qty === $old_qty ) {
		return;
	}
	$diff  = abs( $qty - $old_qty );
	$item  = pp_dl_item( $line['data'], $diff );
	$event = $qty > $old_qty ? 'add_to_cart' : 'remove_from_cart';
	pp_dl_queue( $event, array( 'currency' => get_woocommerce_currency(), 'value' => pp_dl_value( array( $item ) ), 'items' => array( $item ) ) );
}, 10, 4 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'pp/v1', '/dl-queue', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // only ever returns the caller's own session queue
		'callback'            => function () {
			nocache_headers();
			if ( ! WC()->session ) {
				return array();
			}
			$queue = (array) WC()->session->get( 'pp_dl_queue', array() );
			WC()->session->set( 'pp_dl_queue', array() );
			wc_setcookie( 'pp_dl_pending', '', time() - 3600, is_ssl(), false );
			return array_values( $queue );
		},
	) );
} );

// The WooCommerce session is not loaded for REST requests by default.
add_filter( 'woocommerce_is_rest_api_request', function ( $is ) {
	return ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/pp/v1/dl-queue' ) !== false ) ? false : $is;
} );

add_action( 'wp_footer', function () {
	$url = esc_url_raw( rest_url( 'pp/v1/dl-queue' ) );
	?>
<script id="pp-dl-queue">
(function () {
  var URL = <?php echo wp_json_encode( $url ); ?>, busy = false, again = false;
  function drain() {
    if (busy) { again = true; return; }
    if (document.cookie.indexOf("pp_dl_pending=1") === -1) return;
    busy = true;
    fetch(URL, { method: "POST", credentials: "same-origin", cache: "no-store" })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (events) {
        (events || []).forEach(function (e) { window.dataLayer.push({ ecommerce: null }); window.dataLayer.push(e); });
      })
      .catch(function () {})
      .then(function () { busy = false; if (again) { again = false; setTimeout(drain, 50); } });
  }
  window.ppDlDrain = drain;
  drain(); // events from a form post that led to this page
  // Cart changes made without a page load: classic AJAX buttons and the cart/checkout blocks (Store API).
  document.body.addEventListener("wc-blocks_added_to_cart", function () { setTimeout(drain, 50); });
  document.body.addEventListener("wc-blocks_removed_from_cart", function () { setTimeout(drain, 50); });
  if (window.jQuery) jQuery(document.body).on("added_to_cart removed_from_cart updated_cart_totals", function () { setTimeout(drain, 50); });
  // Store API calls made by blocks that do not fire DOM events: check after any cart request finishes.
  if (window.fetch && !window.fetch.ppWrapped) {
    var f = window.fetch;
    window.fetch = function (input) {
      var p = f.apply(this, arguments);
      var u = typeof input === "string" ? input : (input && input.url) || "";
      if (u.indexOf("/wc/store/") > -1 && u.indexOf("/cart") > -1) p.then(function () { setTimeout(drain, 50); }, function () {});
      return p;
    };
    window.fetch.ppWrapped = true;
  }
})();
</script>
	<?php
}, 30 );
