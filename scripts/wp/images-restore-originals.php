<?php
/**
 * "Before" state: point every image back at its original upload (the 3000 px JPEG) and forget the WebP copies,
 * so `wp media regenerate` makes ordinary JPEG sizes again. wp eval-file /scripts/images-restore-originals.php
 */
$n = 0;
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	$meta = wp_get_attachment_metadata( $id );
	if ( empty( $meta['original_image'] ) ) {
		continue;
	}
	$dir      = dirname( get_attached_file( $id, true ) );
	$original = $dir . '/' . $meta['original_image'];
	if ( ! file_exists( $original ) ) {
		continue;
	}
	// delete the generated WebP files (the scaled copy and its sizes)
	@unlink( get_attached_file( $id, true ) );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $s ) {
		@unlink( $dir . '/' . $s['file'] );
	}
	update_attached_file( $id, $original );
	wp_update_post( array( 'ID' => $id, 'post_mime_type' => 'image/jpeg' ) );
	$size = getimagesize( $original );
	wp_update_attachment_metadata( $id, array( 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $original ), 'sizes' => array() ) );
	$n++;
}
WP_CLI::success( "$n images pointed back at their original JPEG" );
