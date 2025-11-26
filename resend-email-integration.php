<?php
/**
 * Plugin Name: Resend Email Integration
 * Plugin URI: https://github.com/resend/resend-wordpress
 * Description: Integrates WordPress email functionality with Resend using the official Resend PHP SDK. Replaces wp_mail behavior to send all emails through Resend.
 * Version: 1.0.0
 * Author: Resend
 * Author URI: https://resend.com
 * Text Domain: resend-email-integration
 * Domain Path: /languages
 * Requires PHP: 8.1
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * @package ResendEmailIntegration
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent multiple plugin loads.
if ( defined( 'RESEND_EMAIL_INTEGRATION_LOADED' ) ) {
	return;
}
define( 'RESEND_EMAIL_INTEGRATION_LOADED', true );

// Define plugin constants.
define( 'RESEND_EMAIL_INTEGRATION_VERSION', '1.0.0' );
define( 'RESEND_EMAIL_INTEGRATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RESEND_EMAIL_INTEGRATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RESEND_EMAIL_INTEGRATION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Prevent multiple includes.
if ( class_exists( 'Resend_Email_Integration\Resend_Plugin' ) ) {
	return;
}

// Check for Composer autoloader.
$autoloader_path = RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoloader_path ) ) {
	// Show admin notice if Composer dependencies are missing.
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Resend Email Integration:', 'resend-email-integration' ); ?></strong>
					<?php esc_html_e( 'Composer dependencies are missing. Please run `composer install` in the plugin directory.', 'resend-email-integration' ); ?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

// Load Composer autoloader only if Resend class doesn't already exist.
// Check if Resend class exists OR if the Resend.php file has already been included.
$resend_class_file = RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'vendor/resend/resend-php/src/Resend.php';
$resend_file_included = false;
if ( file_exists( $resend_class_file ) ) {
	$resend_file_normalized = realpath( $resend_class_file );
	$included_files = array_map( 'realpath', get_included_files() );
	$included_files = array_filter( $included_files );
	$resend_file_included = $resend_file_normalized && in_array( $resend_file_normalized, $included_files, true );
}

if ( file_exists( $autoloader_path ) && ! class_exists( 'Resend', false ) && ! $resend_file_included ) {
	// Use require_once which should prevent duplicate includes in the same execution context.
	// The 'false' parameter in class_exists prevents autoloading, which could cause issues.
	require_once $autoloader_path;
}

// Manually require plugin classes to ensure they're loaded.
// The autoloader will handle Resend SDK classes, but we need our plugin classes loaded explicitly.
if ( ! class_exists( 'Resend_Email_Integration\Resend_Compat' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-compat.php';
}
if ( ! class_exists( 'Resend_Email_Integration\Resend_Client_Helper' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-client-helper.php';
}
if ( ! class_exists( 'Resend_Email_Integration\Resend_DB' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-db.php';
}
if ( ! class_exists( 'Resend_Email_Integration\Resend_Plugin' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-plugin.php';
}
if ( ! class_exists( 'Resend_Email_Integration\Resend_Admin_Settings' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-admin-settings.php';
}
if ( ! class_exists( 'Resend_Email_Integration\Resend_Mailer' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-mailer.php';
}
if ( ! class_exists( 'Resend_Email_Integration\Resend_Logs_Page' ) ) {
	require_once RESEND_EMAIL_INTEGRATION_PLUGIN_DIR . 'includes/class-resend-logs-page.php';
}

// Register activation and deactivation hooks early (before plugin initialization).
register_activation_hook( __FILE__, array( 'Resend_Email_Integration\Resend_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Resend_Email_Integration\Resend_Plugin', 'deactivate' ) );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function run_resend_email_integration() {
	$plugin = new Resend_Email_Integration\Resend_Plugin();
	$plugin->run();
}

// Initialize the plugin.
run_resend_email_integration();

