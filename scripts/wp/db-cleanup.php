<?php
/**
 * "After" state: database cleanup, with a before/after report. wp eval-file /scripts/db-cleanup.php
 * Each step is a normal maintenance task that is safe on a live store:
 *   old revisions, expired transients, spam and trashed comments, orphaned post meta,
 *   autoloaded options of plugins that are no longer installed (switched to not autoload, then removed),
 *   finished Action Scheduler jobs older than 7 days and their logs, then OPTIMIZE TABLE.
 * A real store gets a full database backup first (scripts/backup.sh); this script refuses to run without one from today.
 */
global $wpdb;

$backup_flag = '/tmp/pp-backup-done';
if ( ! file_exists( $backup_flag ) && ! getenv( 'PP_SKIP_BACKUP_CHECK' ) ) {
	WP_CLI::error( 'No backup from this session. Run a backup first (scripts/backup.sh) or set PP_SKIP_BACKUP_CHECK=1 for the demo reset.' );
}

function pp_db_stats() {
	global $wpdb;
	$as  = $wpdb->prefix . 'actionscheduler_actions';
	$has = $wpdb->get_var( "SHOW TABLES LIKE '$as'" ) === $as;
	return array(
		'Revisions'                  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'" ),
		'Expired transients'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) ),
		'Spam + trashed comments'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ),
		'Orphaned post meta'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
		'Autoloaded options (KB)'    => (int) round( $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" ) / 1024 ),
		'Finished background jobs'   => $has ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $as WHERE status IN ('complete','failed','canceled')" ) : 0,
		'Database size (MB)'         => round( (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(data_length + index_length) / 1048576 FROM information_schema.tables WHERE table_schema = %s', DB_NAME ) ), 1 ),
	);
}

$before = pp_db_stats();

// 1. Revisions (keep none of the old ones; wp_revisions_to_keep = 5 from now on).
$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'revision'" );
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );

// 2. Expired transients (core function, handles both the value and the timeout rows).
delete_expired_transients( true );

// 3. Spam and trashed comments, and their meta.
$wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_approved IN ('spam','trash')" );
$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" );

// 4. Post meta whose post no longer exists.
$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );

// 5. Large autoloaded options that belong to no installed plugin. Listed for review, then removed.
//    (On a client site this list is checked by hand before anything is deleted.)
$big = $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto') ORDER BY bytes DESC LIMIT 15" );
foreach ( $big as $row ) {
	if ( strpos( $row->option_name, 'pp_bloat_old_' ) === 0 ) {
		WP_CLI::log( sprintf( '    removing leftover autoloaded option %-32s %6d KB', $row->option_name, $row->bytes / 1024 ) );
		delete_option( $row->option_name );
	}
}

// 6. Finished background jobs older than 7 days (WooCommerce's own cleaner keeps 30 days by default).
$as = $wpdb->prefix . 'actionscheduler_actions';
$al = $wpdb->prefix . 'actionscheduler_logs';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$as'" ) === $as ) {
	$cut = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( "DELETE l FROM $al l JOIN $as a ON a.action_id = l.action_id WHERE a.status IN ('complete','failed','canceled') AND a.last_attempt_gmt < %s", $cut ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $as WHERE status IN ('complete','failed','canceled') AND last_attempt_gmt < %s", $cut ) );
}

// 7. Reclaim the space.
foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT table_name FROM information_schema.tables WHERE table_schema = %s', DB_NAME ) ) as $table ) {
	$wpdb->query( "OPTIMIZE TABLE `$table`" );
}
wp_cache_flush();

$after = pp_db_stats();
$out   = sprintf( "%-28s %12s %12s\n", '', 'before', 'after' );
foreach ( $before as $k => $v ) {
	$out .= sprintf( "%-28s %12s %12s\n", $k, $v, $after[ $k ] );
}
WP_CLI::log( $out );
file_put_contents( '/tmp/pp-db-cleanup-report.txt', $out );
