<?php
/**
 * "Before" state: fills the database the way a few years of normal use does. wp eval-file /scripts/db-bloat.php
 * Every row added here is made up and marked, so nothing real is mixed in.
 *   - 40 old revisions of every product and page
 *   - 15,000 expired transients (cached API answers nobody cleaned up)
 *   - autoloaded options left behind by plugins that were removed long ago (about 2.5 MB loaded on every request)
 *   - 2,000 spam comments and 1,000 in the trash
 *   - 20,000 orphaned post meta rows
 *   - 25,000 finished WooCommerce background jobs (Action Scheduler) and their logs
 */
global $wpdb;
if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = 'pp_bloat_old_slider_cache'" ) ) {
	WP_CLI::success( 'Database already in the before state (not bloated twice)' );
	return;
}
wp_suspend_cache_addition( true );
$t0 = microtime( true );

// Revisions: insert directly (wp_save_post_revision skips unchanged content).
$targets = get_posts( array( 'post_type' => array( 'product', 'page' ), 'numberposts' => -1, 'post_status' => 'publish' ) );
foreach ( $targets as $p ) {
	$rows = array();
	for ( $i = 40; $i >= 1; $i-- ) {
		$date    = gmdate( 'Y-m-d H:i:s', time() - $i * 86400 * 9 );
		$content = $p->post_content . "\n<!-- draft change $i: " . str_repeat( 'Lorem ipsum dolor sit amet. ', 20 ) . ' -->';
		$rows[]  = $wpdb->prepare( '(%d,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s)', $p->post_author, $date, $date, $content, $p->post_title, '', 'inherit', 'closed', "{$p->ID}-revision-v1", $p->ID, 'revision' );
	}
	$wpdb->query( "INSERT INTO {$wpdb->posts} (post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,ping_status,post_name,post_parent,post_type) VALUES " . implode( ',', $rows ) );
}

// Expired transients, inserted in batches.
$batch = array();
for ( $i = 0; $i < 15000; $i++ ) {
	$key     = "pp_bloat_feed_cache_$i";
	$batch[] = $wpdb->prepare( '(%s,%s,%s)', "_transient_timeout_$key", (string) ( time() - 86400 * ( 1 + $i % 300 ) ), 'off' );
	$batch[] = $wpdb->prepare( '(%s,%s,%s)', "_transient_$key", str_repeat( 'x', 300 ), 'off' );
	if ( count( $batch ) >= 1000 ) {
		$wpdb->query( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES " . implode( ',', $batch ) );
		$batch = array();
	}
}

// Autoloaded leftovers from removed plugins (names made up, sizes typical of page builders and sliders).
$leftovers = array( 'pp_bloat_old_slider_cache' => 900, 'pp_bloat_old_builder_css' => 700, 'pp_bloat_old_seo_redirects' => 400, 'pp_bloat_old_social_counts' => 250, 'pp_bloat_old_popup_stats' => 250 );
foreach ( $leftovers as $name => $kb ) {
	$value = array();
	for ( $i = 0; $i < $kb; $i++ ) {
		$value[ "k$i" ] = str_repeat( chr( 97 + $i % 26 ), 1000 );
	}
	$wpdb->replace( $wpdb->options, array( 'option_name' => $name, 'option_value' => maybe_serialize( $value ), 'autoload' => 'on' ) );
}

// Spam and trashed comments on products.
$ids  = wp_list_pluck( $targets, 'ID' );
$rows = array();
for ( $i = 0; $i < 3000; $i++ ) {
	$rows[] = $wpdb->prepare( '(%d,%s,%s,%s,%s,%s,%s,%s)', $ids[ $i % count( $ids ) ], 'Spam Bot ' . $i, "bot$i@spam.example", '203.0.113.' . ( $i % 250 ), gmdate( 'Y-m-d H:i:s', time() - $i * 600 ), 'Cheap pills and links ' . str_repeat( 'http://spam.example/ ', 8 ), $i < 2000 ? 'spam' : 'trash', 'comment' );
	if ( count( $rows ) >= 500 ) {
		$wpdb->query( "INSERT INTO {$wpdb->comments} (comment_post_ID,comment_author,comment_author_email,comment_author_IP,comment_date_gmt,comment_content,comment_approved,comment_type) VALUES " . implode( ',', $rows ) );
		$rows = array();
	}
}

// Orphaned post meta (post IDs that no longer exist).
$rows = array();
for ( $i = 0; $i < 20000; $i++ ) {
	$rows[] = $wpdb->prepare( '(%d,%s,%s)', 900000 + $i % 4000, '_pp_bloat_old_builder_data_' . ( $i % 5 ), str_repeat( 'y', 200 ) );
	if ( count( $rows ) >= 1000 ) {
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $rows ) );
		$rows = array();
	}
}

// Finished background jobs (Action Scheduler keeps them for 30 days by default; stores with many orders pile them up).
$as = $wpdb->prefix . 'actionscheduler_actions';
$al = $wpdb->prefix . 'actionscheduler_logs';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$as'" ) === $as ) {
	for ( $b = 0; $b < 25; $b++ ) {
		$rows = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$d      = gmdate( 'Y-m-d H:i:s', time() - ( $b * 1000 + $i ) * 120 );
			$rows[] = $wpdb->prepare( "(%s,%s,%s,%s,%s,%s,%d,%s)", 'pp_bloat_old_job', 'complete', $d, $d, '[]', 'O:30:"ActionScheduler_SimpleSchedule":0:{}', 0, $d );
		}
		$wpdb->query( "INSERT INTO $as (hook,status,scheduled_date_gmt,scheduled_date_local,args,schedule,group_id,last_attempt_gmt) VALUES " . implode( ',', $rows ) );
		$first = (int) $wpdb->insert_id;
		$logs  = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$logs[] = $wpdb->prepare( '(%d,%s,%s,%s)', $first + $i, 'action complete via WP Cron', gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ) );
		}
		$wpdb->query( "INSERT INTO $al (action_id,message,log_date_gmt,log_date_local) VALUES " . implode( ',', $logs ) );
	}
}

WP_CLI::success( sprintf( 'Database bloated in %.1f s', microtime( true ) - $t0 ) );
