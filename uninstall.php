<?php
/**
 * Uninstall handler for Resend Email Integration plugin.
 *
 * This file is executed when the plugin is uninstalled.
 *
 * @package ResendEmailIntegration
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'resend_api_key' );
delete_option( 'resend_from_email' );
delete_option( 'resend_from_name' );
delete_option( 'resend_override' );

// Delete transients.
delete_transient( 'resend_verified_domains' );

// Drop email cache table.
global $wpdb;
$table_name = $wpdb->prefix . 'resend_email_cache';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Clear any scheduled events if any were added in the future.
// wp_clear_scheduled_hook( 'resend_email_integration_cron' );

