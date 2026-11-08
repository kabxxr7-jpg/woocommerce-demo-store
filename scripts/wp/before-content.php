<?php
/**
 * "Before" content: the home page hero becomes a MetaSlider slideshow of three full-size photos,
 * and a Contact Form 7 form exists. wp eval-file /scripts/before-content.php
 */
// Contact form (Contact Form 7 loads its CSS and JS on every page by default).
pp_ensure_cf7_form();

// Slider with the hero painting and two product photos, at full size.
$old = get_option( 'pp_slider_id' );
if ( $old ) {
	wp_delete_post( (int) $old, true );
}
$slides = array( (int) get_option( 'pp_hero_attachment_id' ) );
foreach ( array( 'PP-013', 'PP-022' ) as $sku ) {
	$slides[] = (int) get_post_thumbnail_id( wc_get_product_id_by_sku( $sku ) );
}
$id = MetaSlider_Slideshows::create();
$settings = get_post_meta( $id, 'ml-slider_settings', true );
$settings = array_merge( (array) $settings, array( 'type' => 'flex', 'width' => 3000, 'height' => 1400, 'effect' => 'fade', 'links' => 'true', 'navigation' => 'true', 'fullWidth' => 'true', 'cropMultiply' => 1, 'smartCrop' => 'false', 'title' => 'Home hero' ) );
update_post_meta( $id, 'ml-slider_settings', $settings );
$slide = new MetaImageSlide();
$slide->create_slides( $id, array_map( fn( $a ) => array( 'id' => $a, 'type' => 'image' ), $slides ) );
update_option( 'pp_slider_id', $id );
WP_CLI::success( "Slider $id with " . count( $slides ) . ' slides' );

function pp_ensure_cf7_form() {
	$existing = (int) get_option( 'pp_cf7_form_id' );
	if ( $existing && get_post( $existing ) ) {
		return $existing;
	}
	$form = WPCF7_ContactForm::get_template( array( 'title' => 'Contact (demo, nothing is sent)' ) );
	$form->save();
	update_option( 'pp_cf7_form_id', $form->id() );
	return $form->id();
}
