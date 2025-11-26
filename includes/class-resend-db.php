<?php
/**
 * Database helper class for Resend Email Integration.
 *
 * Handles email detail caching in local database.
 *
 * @package ResendEmailIntegration
 */

namespace Resend_Email_Integration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resend_DB
 *
 * Handles database operations for email caching.
 */
class Resend_DB {

	/**
	 * Get the table name for email cache.
	 *
	 * @return string Table name with WordPress prefix.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'resend_email_cache';
	}

	/**
	 * Create the email cache table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id varchar(36) NOT NULL,
			email_to text,
			email_from varchar(255),
			subject text,
			html_content longtext,
			text_content longtext,
			cc text,
			bcc text,
			reply_to text,
			created_at datetime,
			last_event varchar(50),
			scheduled_at datetime NULL,
			cached_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY cached_at (cached_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get email details from cache.
	 *
	 * @param string $email_id Email ID to retrieve.
	 * @return array|null Email data array or null if not found.
	 */
	public static function get_email( $email_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$email_id = sanitize_text_field( $email_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table_name ) . '` WHERE id = %s',
				$email_id
			),
			ARRAY_A
		);

		if ( $result ) {
			// Convert database format to API-like format.
			// Preserve NULL values for html/text (don't convert to empty string).
			// Use wp_unslash to remove any slashes added during storage.
			$html = isset( $result['html_content'] ) ? wp_unslash( $result['html_content'] ) : null;
			$text = isset( $result['text_content'] ) ? wp_unslash( $result['text_content'] ) : null;
			
			return array(
				'id'         => $result['id'],
				'to'         => ! empty( $result['email_to'] ) ? json_decode( $result['email_to'], true ) : array(),
				'from'       => $result['email_from'] ?? '',
				'subject'    => $result['subject'] ?? '',
				'html'       => $html,
				'text'       => $text,
				'cc'         => ! empty( $result['cc'] ) ? json_decode( $result['cc'], true ) : array(),
				'bcc'        => ! empty( $result['bcc'] ) ? json_decode( $result['bcc'], true ) : array(),
				'reply_to'   => ! empty( $result['reply_to'] ) ? json_decode( $result['reply_to'], true ) : array(),
				'created_at' => $result['created_at'] ?? '',
				'last_event' => $result['last_event'] ?? '',
				'scheduled_at' => $result['scheduled_at'] ?? null,
			);
		}

		return null;
	}

	/**
	 * Save email details to cache.
	 *
	 * @param array $email_data Email data from API.
	 * @return bool True on success, false on failure.
	 */
	public static function save_email( $email_data ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// Extract and prepare data.
		$id = sanitize_text_field( $email_data['id'] ?? '' );
		if ( empty( $id ) ) {
			return false;
		}

		$to = isset( $email_data['to'] ) && is_array( $email_data['to'] ) ? wp_json_encode( $email_data['to'] ) : '';
		$from = sanitize_text_field( $email_data['from'] ?? '' );
		$subject = sanitize_text_field( $email_data['subject'] ?? '' );
		
		// Handle html_content: preserve NULL if not provided, otherwise store raw HTML.
		// We don't sanitize HTML here because:
		// 1. It will be displayed in an isolated iframe (safe)
		// 2. Email HTML often contains <style> tags and other elements that wp_kses_post() would strip
		// 3. We want to preserve the exact HTML structure for proper rendering
		$html_content = null;
		if ( isset( $email_data['html'] ) && $email_data['html'] !== null ) {
			// Store raw HTML - WordPress will escape it when inserting into database via $wpdb->prepare
			// But we need to use wp_slash to handle quotes properly for database storage
			$html_content = wp_slash( $email_data['html'] );
		}
		
		// Handle text_content: preserve NULL if not provided, otherwise sanitize.
		$text_content = null;
		if ( isset( $email_data['text'] ) && $email_data['text'] !== null ) {
			$text_content = sanitize_textarea_field( $email_data['text'] );
		}
		
		$cc = isset( $email_data['cc'] ) && is_array( $email_data['cc'] ) ? wp_json_encode( $email_data['cc'] ) : '';
		$bcc = isset( $email_data['bcc'] ) && is_array( $email_data['bcc'] ) ? wp_json_encode( $email_data['bcc'] ) : '';
		$reply_to = isset( $email_data['reply_to'] ) && is_array( $email_data['reply_to'] ) ? wp_json_encode( $email_data['reply_to'] ) : '';
		
		// Handle created_at: Convert Resend date format (UTC) to MySQL datetime format.
		// Store as UTC in database, WordPress will convert to local timezone when displaying.
		$created_at = '';
		if ( ! empty( $email_data['created_at'] ) ) {
			// Parse the UTC date string directly (strtotime handles ISO 8601 and timezone offsets).
			$timestamp = strtotime( $email_data['created_at'] );
			
			if ( false !== $timestamp && $timestamp > 0 ) {
				// Store as UTC datetime in MySQL format.
				$created_at = gmdate( 'Y-m-d H:i:s', $timestamp );
			} else {
				// Fallback: sanitize as-is.
				$created_at = sanitize_text_field( $email_data['created_at'] );
			}
		}
		
		$last_event = sanitize_text_field( $email_data['last_event'] ?? '' );
		$scheduled_at = ! empty( $email_data['scheduled_at'] ) ? sanitize_text_field( $email_data['scheduled_at'] ) : null;

		// Check if email already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . esc_sql( $table_name ) . '` WHERE id = %s', $id ) );

		if ( $existing ) {
			// Update existing record.
			// For html_content and text_content, only update if new value is provided (not NULL).
			$update_data = array(
				'email_to'     => $to,
				'email_from'    => $from,
				'subject'      => $subject,
				'cc'           => $cc,
				'bcc'          => $bcc,
				'reply_to'     => $reply_to,
				'created_at'   => $created_at,
				'last_event'   => $last_event,
				'scheduled_at' => $scheduled_at,
				'cached_at'    => current_time( 'mysql', true ),
			);

			$update_format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

			// Only update html_content and text_content if they're not NULL.
			if ( $html_content !== null ) {
				$update_data['html_content'] = $html_content;
				$update_format[] = '%s';
			}
			if ( $text_content !== null ) {
				$update_data['text_content'] = $text_content;
				$update_format[] = '%s';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table_name,
				$update_data,
				array( 'id' => $id ),
				$update_format,
				array( '%s' )
			);
		} else {
			// Insert new record.
			$insert_data = array(
				'id'           => $id,
				'email_to'     => $to,
				'email_from'   => $from,
				'subject'      => $subject,
				'html_content' => $html_content,
				'text_content' => $text_content,
				'cc'           => $cc,
				'bcc'          => $bcc,
				'reply_to'     => $reply_to,
				'created_at'   => $created_at,
				'last_event'   => $last_event,
				'scheduled_at' => $scheduled_at,
				'cached_at'    => current_time( 'mysql', true ),
			);

			$insert_format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $table_name, $insert_data, $insert_format );
		}

		return false !== $result;
	}

	/**
	 * Delete old cached emails (optional cleanup method).
	 *
	 * @param int $days_old Number of days old to delete (default 90).
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_emails( $days_old = 90 ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . esc_sql( $table_name ) . '` WHERE cached_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$days_old
			)
		);

		return $result;
	}

	/**
	 * Check if email has full details (html and text content).
	 *
	 * @param string $email_id Email ID to check.
	 * @return bool True if email has full details, false otherwise.
	 * 
	 * Note: NULL means "not fetched yet", empty string means "fetched but no content".
	 * We consider it "has full details" if html_content is not NULL (even if empty string).
	 */
	public static function has_full_details( $email_id ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$email_id = sanitize_text_field( $email_id );

		// Check if html_content is not NULL (NULL means not fetched, empty string means fetched but no HTML).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT html_content IS NOT NULL FROM `' . esc_sql( $table_name ) . '` WHERE id = %s',
				$email_id
			)
		);

		return (bool) $result;
	}

	/**
	 * Get emails from database with pagination.
	 *
	 * @param int $limit Number of emails to fetch.
	 * @param int $offset Offset for pagination.
	 * @return array Array with 'emails' and 'total' count.
	 */
	public static function get_emails( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table_name ) . '`' );

		// Get emails ordered by created_at DESC.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table_name ) . '` ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$limit,
				$offset
			),
			ARRAY_A
		);

		$emails = array();
		if ( $results ) {
			foreach ( $results as $result ) {
				// Convert database format to API-like format.
				$emails[] = array(
					'id'         => $result['id'],
					'to'         => ! empty( $result['email_to'] ) ? json_decode( $result['email_to'], true ) : array(),
					'from'       => $result['email_from'] ?? '',
					'subject'    => $result['subject'] ?? '',
					'html'       => $result['html_content'] ?? '',
					'text'       => $result['text_content'] ?? '',
					'cc'         => ! empty( $result['cc'] ) ? json_decode( $result['cc'], true ) : array(),
					'bcc'        => ! empty( $result['bcc'] ) ? json_decode( $result['bcc'], true ) : array(),
					'reply_to'   => ! empty( $result['reply_to'] ) ? json_decode( $result['reply_to'], true ) : array(),
					'created_at' => $result['created_at'] ?? '',
					'last_event' => $result['last_event'] ?? '',
					'scheduled_at' => $result['scheduled_at'] ?? null,
				);
			}
		}

		return array(
			'emails' => $emails,
			'total'  => $total,
		);
	}

	/**
	 * Bulk save emails (for import).
	 *
	 * @param array $emails_array Array of email data arrays.
	 * @return int Number of emails saved.
	 */
	public static function bulk_save_emails( $emails_array ) {
		if ( ! is_array( $emails_array ) || empty( $emails_array ) ) {
			return 0;
		}

		$saved = 0;
		foreach ( $emails_array as $email_data ) {
			if ( self::save_email( $email_data ) ) {
				$saved++;
			}
		}

		return $saved;
	}

	/**
	 * Drop the email cache table (for uninstall).
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table_name ) . '`' );
	}
}

