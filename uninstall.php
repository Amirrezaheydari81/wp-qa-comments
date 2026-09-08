<?php
/**
 * Uninstall WP Q&A Comments.
 *
 * Fired when the plugin is deleted via WordPress admin.
 *
 * @package WP_QA_Comments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'wpqa_settings', array() );
$delete   = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $delete ) {
	return;
}

global $wpdb;

$table = $wpdb->prefix . 'wpqa_comments';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'wpqa_settings' );
delete_option( 'wpqa_db_version' );

// Clean leftover import report transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpqa_import_report_%' OR option_name LIKE '_transient_timeout_wpqa_import_report_%'"
);
