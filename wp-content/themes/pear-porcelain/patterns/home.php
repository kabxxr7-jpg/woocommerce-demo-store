<?php
/**
 * Title: Pear & Porcelain home page
 * Slug: pear-porcelain/home
 * Inserter: no
 *
 * Built in PHP so the hero and category tiles can use the attachment IDs created by the seed script
 * (WordPress then adds srcset, sizes and, for the first image, fetchpriority="high" by itself).
 */
$hero_id  = (int) get_option( 'pp_hero_attachment_id' );
$hero_url = $hero_id ? wp_get_attachment_image_url( $hero_id, 'full' ) : '';
$tiles    = array(
	array( 'Bowls', 'bowls', 'PP-005' ),
	array( 'Teapots & jugs', 'teapots-jugs', 'PP-013' ),
	array( 'Plates', 'plates', 'PP-019' ),
	array( 'Vases', 'vases', 'PP-022' ),
);
$slider   = ( function_exists( 'pp_mode' ) && pp_mode() === 'before' ) ? (int) get_option( 'pp_slider_id' ) : 0;
if ( $slider ) : ?>
<!-- wp:html -->
<?php echo do_shortcode( '[metaslider id="' . $slider . '"]' ); // shortcodes are not run inside block templates, so run it here ?>
<!-- /wp:html -->
<?php else : ?>
<!-- wp:cover {"url":"<?php echo esc_url( $hero_url ); ?>","id":<?php echo $hero_id; ?>,"dimRatio":40,"overlayColor":"contrast","minHeight":620,"contentPosition":"center left","isDark":true,"align":"full","className":"pp-hero","style":{"spacing":{"padding":{"left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-cover alignfull is-dark has-custom-content-position is-position-center-left pp-hero" style="padding-right:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50);min-height:620px"><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-40 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-<?php echo $hero_id; ?>" alt="" src="<?php echo esc_url( $hero_url ); ?>" data-object-fit="cover"/><div class="wp-block-cover__inner-container">
<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.14em","fontWeight":"600"}},"fontSize":"small"} -->
<p class="has-small-font-size" style="font-weight:600;letter-spacing:0.14em;text-transform:uppercase">Fictional demo store</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(2.6rem, 6vw, 4.4rem)","lineHeight":"1.05"}}} -->
<h1 class="wp-block-heading" style="font-size:clamp(2.6rem, 6vw, 4.4rem);line-height:1.05">Old-world porcelain for everyday tables</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">Bowls, teapots and plates inspired by three centuries of Dutch, German and Chinese export porcelain. Browse, fill a cart and try the checkout: it runs in test mode.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-base-background-color has-text-color has-background wp-element-button" href="/shop/">Shop the collection</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","textColor":"base"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-base-color has-text-color wp-element-button" href="/about/">How this demo was built</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
<?php endif; ?>

<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained","contentSize":"1240px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
<!-- wp:heading {"textAlign":"left"} -->
<h2 class="wp-block-heading has-text-align-left">Shop by shape</h2>
<!-- /wp:heading -->
<!-- wp:columns {"isStackedOnMobile":false,"className":"pp-category-row","style":{"spacing":{"blockGap":{"left":"1rem"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile pp-category-row">
<?php foreach ( $tiles as $t ) :
	$pid = wc_get_product_id_by_sku( $t[2] );
	$img = $pid ? (int) get_post_thumbnail_id( $pid ) : 0;
	$url = $img ? wp_get_attachment_image_url( $img, 'large' ) : '';
	$link = home_url( '/product-category/' . $t[1] . '/' );
	?>
<!-- wp:column {"className":"pp-category-tile"} -->
<div class="wp-block-column pp-category-tile">
<!-- wp:image {"id":<?php echo $img; ?>,"sizeSlug":"large","linkDestination":"custom"} -->
<figure class="wp-block-image size-large"><a href="<?php echo esc_url( $link ); ?>"><img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $t[0] ); ?>" class="wp-image-<?php echo $img; ?>" sizes="(max-width: 781px) 25vw, 290px" loading="lazy"/></a></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}}} -->
<p style="font-weight:600"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $t[0] ); ?></a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<?php endforeach; ?>
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"layout":{"type":"constrained","contentSize":"1240px"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
<!-- wp:heading -->
<h2 class="wp-block-heading">Collectors' favourites</h2>
<!-- /wp:heading -->
<!-- wp:woocommerce/product-collection {"queryId":11,"query":{"perPage":8,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"date","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":true,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/featured","hideControls":["inherit"],"queryContextIncludes":["collection"]} -->
<div class="wp-block-woocommerce-product-collection">
<!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"imageSizing":"thumbnail","isDescendentOfQueryLoop":true} -->
<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->
<!-- wp:post-title {"textAlign":"left","level":3,"isLink":true,"style":{"spacing":{"margin":{"bottom":"0.4rem","top":"0.8rem"}}},"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"textAlign":"left","fontSize":"small"} /-->
<!-- wp:woocommerce/product-button {"textAlign":"left","isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"var:preset|spacing|60"}}},"backgroundColor":"accent-5","layout":{"type":"constrained","contentSize":"1240px"}} -->
<div class="wp-block-group alignfull has-accent-5-background-color has-background" style="margin-top:var(--wp--preset--spacing--60);padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)">
<!-- wp:columns {"className":"pp-badges"} -->
<div class="wp-block-columns pp-badges">
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} --><h3 class="wp-block-heading has-large-font-size">Fast on a phone</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Measured with Lighthouse before and after the speed work: page cache, WebP images, deferred scripts, a cleaned database.</p><!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} --><h3 class="wp-block-heading has-large-font-size">Hardened</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Security headers, no user enumeration, no XML-RPC, no PHP in uploads, admin closed on the public address. Each item has a test.</p><!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"fontSize":"large"} --><h3 class="wp-block-heading has-large-font-size">Tracked properly</h3><!-- /wp:heading --><!-- wp:paragraph --><p>GA4 ecommerce events through Google Tag Manager, with consent first: view_item, add_to_cart, begin_checkout, purchase.</p><!-- /wp:paragraph --></div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
