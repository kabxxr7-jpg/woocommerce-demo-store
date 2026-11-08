<?php
/**
 * "After" content: the slider is gone (the home page shows one optimised hero image instead);
 * the contact form stays. wp eval-file /scripts/after-content.php
 */
delete_option( 'pp_slider_id' );
$existing = (int) get_option( 'pp_cf7_form_id' );
if ( ! $existing || ! get_post( $existing ) ) {
	$form = WPCF7_ContactForm::get_template( array( 'title' => 'Contact (demo, nothing is sent)' ) );
	$form->save();
	update_option( 'pp_cf7_form_id', $form->id() );
}
WP_CLI::success( 'After content ready' );
