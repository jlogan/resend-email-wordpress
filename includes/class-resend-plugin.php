<?php
/**
 * Main plugin class.
 *
 * Coordinates all plugin components and manages initialization.
 *
 * @package ResendEmailIntegration
 */

namespace Resend_Email_Integration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resend_Plugin
 *
 * Main plugin coordinator class.
 */
class Resend_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Resend_Plugin
	 */
	private static $instance = null;

	/**
	 * Admin settings instance.
	 *
	 * @var Resend_Admin_Settings
	 */
	private $admin_settings;

	/**
	 * Mailer instance.
	 *
	 * @var Resend_Mailer
	 */
	private $mailer;

	/**
	 * Logs page instance.
	 *
	 * @var Resend_Logs_Page
	 */
	private $logs_page;

	/**
	 * Get plugin instance.
	 *
	 * @return Resend_Plugin Plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function run() {
		// Display compatibility notices.
		add_action( 'admin_notices', array( 'Resend_Email_Integration\Resend_Compat', 'display_admin_notices' ) );

		// Only proceed if environment is supported.
		if ( ! Resend_Compat::is_supported_environment() ) {
			return;
		}

		// Initialize admin components.
		$this->admin_settings = new Resend_Admin_Settings();
		$this->logs_page = new Resend_Logs_Page();

		// Initialize mailer only if SDK is available and override is enabled.
		if ( Resend_Compat::can_use_resend() ) {
			$override = get_option( 'resend_override', true );
			if ( $override ) {
				$this->mailer = new Resend_Mailer();
			}
		}
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		// Check PHP version directly (don't rely on class if autoloader hasn't run yet).
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			$plugin_basename = plugin_basename( dirname( dirname( __FILE__ ) ) . '/resend-email.php' );
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( $plugin_basename );
			}
			$message = sprintf(
				'Resend Email Integration requires PHP 8.1 or higher. You are running PHP %s.',
				PHP_VERSION
			);
			if ( function_exists( 'wp_die' ) ) {
				wp_die( esc_html( $message ) );
			} else {
				die( esc_html( $message ) );
			}
		}

		// Set default options if they don't exist.
		if ( false === get_option( 'resend_from_email' ) ) {
			$admin_email = get_option( 'admin_email' );
			if ( ! empty( $admin_email ) ) {
				update_option( 'resend_from_email', $admin_email );
			}
		}

		if ( false === get_option( 'resend_from_name' ) ) {
			$blog_name = get_bloginfo( 'name' );
			if ( empty( $blog_name ) ) {
				$blog_name = 'WordPress';
			}
			update_option( 'resend_from_name', $blog_name );
		}

		if ( false === get_option( 'resend_override' ) ) {
			update_option( 'resend_override', true );
		}

		// Create database table for email caching.
		if ( class_exists( 'Resend_Email_Integration\Resend_DB' ) ) {
			Resend_DB::create_table();
		}

		// Flush rewrite rules if needed.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clear any transients (class will be autoloaded if available).
		if ( class_exists( 'Resend_Email_Integration\Resend_Client_Helper' ) ) {
			Resend_Client_Helper::clear_verified_domains_cache();
		} else {
			// Fallback: delete transient directly.
			delete_transient( 'resend_verified_domains' );
		}

		// Drop database table created during activation.
		if ( class_exists( 'Resend_Email_Integration\Resend_DB' ) ) {
			Resend_DB::drop_table();
		} else {
			// Fallback: drop table directly if class not available.
			global $wpdb;
			$resend_email_integration_table_name = $wpdb->prefix . 'resend_email_cache';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $resend_email_integration_table_name ) . '`' );
		}

		// Flush rewrite rules if needed.
		flush_rewrite_rules();
	}
}

