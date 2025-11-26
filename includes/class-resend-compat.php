<?php
/**
 * Compatibility checks for Resend Email Integration plugin.
 *
 * @package ResendEmailIntegration
 */

namespace Resend_Email_Integration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resend_Compat
 *
 * Handles PHP version checks and SDK availability checks.
 */
class Resend_Compat {

	/**
	 * Minimum required PHP version.
	 *
	 * @var string
	 */
	const MIN_PHP_VERSION = '8.1';

	/**
	 * Check if the environment is supported.
	 *
	 * @return bool True if PHP version is sufficient.
	 */
	public static function is_supported_environment() {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
	}

	/**
	 * Check if Resend SDK classes are available.
	 *
	 * @return bool True if SDK classes exist.
	 */
	public static function is_sdk_available() {
		// The Resend class is in the global namespace, not Resend\Resend.
		return class_exists( 'Resend' );
	}

	/**
	 * Check if Resend can be used (environment + SDK available).
	 *
	 * @return bool True if both environment and SDK are available.
	 */
	public static function can_use_resend() {
		return self::is_supported_environment() && self::is_sdk_available();
	}

	/**
	 * Display admin notices for compatibility issues.
	 *
	 * @return void
	 */
	public static function display_admin_notices() {
		// Only show notices in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Check PHP version.
		if ( ! self::is_supported_environment() ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Resend Email Integration:', 'resend-email-integration' ); ?></strong>
					<?php
					printf(
						/* translators: %s: Minimum PHP version */
						esc_html__( 'This plugin requires PHP %s or higher. You are running PHP %s.', 'resend-email-integration' ),
						esc_html( self::MIN_PHP_VERSION ),
						esc_html( PHP_VERSION )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Check SDK availability.
		if ( ! self::is_sdk_available() ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Resend Email Integration:', 'resend-email-integration' ); ?></strong>
					<?php esc_html_e( 'The Resend PHP SDK is not available. Please ensure Composer dependencies are installed.', 'resend-email-integration' ); ?>
				</p>
			</div>
			<?php
		}
	}
}

