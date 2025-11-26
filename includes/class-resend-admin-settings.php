<?php
/**
 * Admin Settings page for Resend Email Integration.
 *
 * @package ResendEmailIntegration
 */

namespace Resend_Email_Integration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resend_Admin_Settings
 *
 * Handles the admin settings page and test email functionality.
 */
class Resend_Admin_Settings {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'resend_email_options';

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_resend_test_api_key', array( $this, 'ajax_test_api_key' ) );
	}

	/**
	 * Add settings page to WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Resend Email Settings', 'resend-email' ),
			__( 'Resend Email', 'resend-email' ),
			'manage_options',
			'resend-email',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings using WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		// Register settings.
		register_setting(
			self::OPTION_GROUP,
			'resend_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'resend_from_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'default'           => get_option( 'admin_email' ),
				'validate_callback' => array( $this, 'validate_from_email' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'resend_from_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => get_bloginfo( 'name' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'resend_override',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
				'default'           => true,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'resend_force_from',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_boolean' ),
				'default'           => false,
			)
		);

		// Add settings sections.
		add_settings_section(
			'resend_email_main_section',
			__( 'Main Settings', 'resend-email' ),
			null,
			'resend-email'
		);

		// Add settings fields.
		add_settings_field(
			'resend_api_key',
			__( 'Resend API Key', 'resend-email' ),
			array( $this, 'render_api_key_field' ),
			'resend-email',
			'resend_email_main_section'
		);

		add_settings_field(
			'resend_from_email',
			__( 'Default From Email Address', 'resend-email' ),
			array( $this, 'render_from_email_field' ),
			'resend-email',
			'resend_email_main_section'
		);

		add_settings_field(
			'resend_from_name',
			__( 'Default From Name', 'resend-email' ),
			array( $this, 'render_from_name_field' ),
			'resend-email',
			'resend_email_main_section'
		);

		add_settings_field(
			'resend_force_from',
			__( 'Force From Name and Email', 'resend-email' ),
			array( $this, 'render_force_from_field' ),
			'resend-email',
			'resend_email_main_section'
		);

		add_settings_field(
			'resend_override',
			__( 'Enable Resend Override', 'resend-email' ),
			array( $this, 'render_override_field' ),
			'resend-email',
			'resend_email_main_section'
		);
	}

	/**
	 * Validate FROM email address against verified domains.
	 *
	 * @param string $value Email address to validate.
	 * @return string|WP_Error Validated email or WP_Error on failure.
	 */
	public function validate_from_email( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		// Basic email validation.
		if ( ! is_email( $value ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'resend-email' ) );
		}

		// Check if domain is verified (only if API key is set).
		$api_key = get_option( 'resend_api_key', '' );
		if ( ! empty( $api_key ) && Resend_Compat::can_use_resend() ) {
			// Clear cache to get fresh domain list.
			Resend_Client_Helper::clear_verified_domains_cache();
			if ( ! Resend_Client_Helper::validate_from_email_domain( $value ) ) {
				$verified_domains = Resend_Client_Helper::get_verified_domains();
				if ( empty( $verified_domains ) ) {
					return new \WP_Error(
						'no_verified_domains',
						__( 'No verified domains found in your Resend account. Please verify a domain in your Resend dashboard.', 'resend-email' )
					);
				} else {
					return new \WP_Error(
						'unverified_domain',
						sprintf(
							/* translators: %s: Comma-separated list of verified domains */
							__( 'The email domain must match one of your verified domains in Resend. Verified domains: %s', 'resend-email' ),
							esc_html( implode( ', ', $verified_domains ) )
						)
					);
				}
			}
		}

		return $value;
	}

	/**
	 * Sanitize boolean value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool Boolean value.
	 */
	public function sanitize_boolean( $value ) {
		return (bool) $value;
	}

	/**
	 * Render API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'resend_api_key', '' );
		?>
		<input type="password" 
			name="resend_api_key" 
			id="resend_api_key" 
			value="<?php echo esc_attr( $api_key ); ?>" 
			class="regular-text" 
			autocomplete="off" />
		<button type="button" 
			id="resend_test_api_key" 
			class="button button-secondary" 
			style="margin-left: 10px;">
			<?php esc_html_e( 'Test API Key', 'resend-email' ); ?>
		</button>
		<span id="resend_api_key_test_result" style="margin-left: 10px;"></span>
		<p class="description">
			<?php esc_html_e( 'Enter your Resend API key. You can find this in your Resend dashboard.', 'resend-email' ); ?>
		</p>
		<?php
	}

	/**
	 * Render FROM email field.
	 *
	 * @return void
	 */
	public function render_from_email_field() {
		$from_email = get_option( 'resend_from_email', get_option( 'admin_email' ) );
		$api_key    = get_option( 'resend_api_key', '' );

		// Split email into local part and domain.
		$email_local = '';
		$email_domain = '';
		if ( ! empty( $from_email ) && strpos( $from_email, '@' ) !== false ) {
			list( $email_local, $email_domain ) = explode( '@', $from_email, 2 );
		}

		// Get verified domains for dropdown.
		$verified_domains = array();
		if ( ! empty( $api_key ) && Resend_Compat::can_use_resend() ) {
			$verified_domains = Resend_Client_Helper::get_verified_domains();
		}

		// Hidden field to store the full email address.
		?>
		<input type="hidden" 
			name="resend_from_email" 
			id="resend_from_email" 
			value="<?php echo esc_attr( $from_email ); ?>" />
		
		<div class="resend-email-field-wrapper">
			<input type="text" 
				id="resend_from_email_local" 
				value="<?php echo esc_attr( $email_local ); ?>" 
				class="regular-text" 
				placeholder="<?php esc_attr_e( 'email', 'resend-email' ); ?>" />
			<span class="resend-email-at">@</span>
			<select id="resend_from_email_domain" 
				class="regular-text"
				<?php echo empty( $verified_domains ) ? 'disabled' : ''; ?>>
				<?php if ( empty( $verified_domains ) ) : ?>
					<option value=""><?php esc_html_e( 'No verified domains found', 'resend-email' ); ?></option>
				<?php else : ?>
					<?php foreach ( $verified_domains as $domain ) : ?>
						<option value="<?php echo esc_attr( $domain ); ?>" <?php selected( $email_domain, $domain ); ?>>
							<?php echo esc_html( $domain ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</div>
		
		<?php if ( ! empty( $verified_domains ) ) : ?>
			<p class="description">
				<?php esc_html_e( 'Select a verified domain from your Resend account.', 'resend-email' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'No verified domains found. Please verify a domain in your Resend dashboard and refresh this page.', 'resend-email' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render FROM name field.
	 *
	 * @return void
	 */
	public function render_from_name_field() {
		$from_name = get_option( 'resend_from_name', get_bloginfo( 'name' ) );
		?>
		<input type="text" 
			name="resend_from_name" 
			id="resend_from_name" 
			value="<?php echo esc_attr( $from_name ); ?>" 
			class="regular-text" />
		<p class="description">
			<?php esc_html_e( 'The display name for the sender.', 'resend-email' ); ?>
		</p>
		<?php
	}

	/**
	 * Render override toggle field.
	 *
	 * @return void
	 */
	public function render_override_field() {
		$override = get_option( 'resend_override', true );
		?>
		<label>
			<input type="checkbox" 
				name="resend_override" 
				id="resend_override" 
				value="1" 
				<?php checked( $override, true ); ?> />
			<?php esc_html_e( 'Enable Resend to handle all WordPress emails', 'resend-email' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, all wp_mail() calls will be routed through Resend. When disabled, WordPress will use its default email configuration.', 'resend-email' ); ?>
		</p>
		<?php
	}

	/**
	 * Render force from name and email field.
	 *
	 * @return void
	 */
	public function render_force_from_field() {
		$force_from = get_option( 'resend_force_from', false );
		?>
		<label>
			<input type="checkbox" 
				name="resend_force_from" 
				id="resend_force_from" 
				value="1" 
				<?php checked( $force_from, true ); ?> />
			<?php esc_html_e( 'Force the defined From Name and Email address for all WordPress emails', 'resend-email' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the From Name and Email Address defined above will be used for ALL emails sent through WordPress, overriding any From headers set by plugins or themes.', 'resend-email' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// Handle test email submission.
		$this->handle_test_email();

		// Check if user has permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show settings saved message.
		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error(
				'resend_email_messages',
				'resend_email_message',
				__( 'Settings saved.', 'resend-email' ),
				'success'
			);
		}

		settings_errors( 'resend_email_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'resend-email' );
				submit_button( __( 'Save Settings', 'resend-email' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Send Test Email', 'resend-email' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'resend_send_test_email', 'resend_test_email_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="resend_test_email_to"><?php esc_html_e( 'To Email', 'resend-email' ); ?></label>
						</th>
						<td>
							<input type="email" 
								name="resend_test_email_to" 
								id="resend_test_email_to" 
								class="regular-text" 
								value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" 
								required />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="resend_test_email_subject"><?php esc_html_e( 'Subject', 'resend-email' ); ?></label>
						</th>
						<td>
							<input type="text" 
								name="resend_test_email_subject" 
								id="resend_test_email_subject" 
								class="regular-text" 
								value="<?php esc_attr_e( 'Resend Test Email', 'resend-email' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="resend_test_email_message"><?php esc_html_e( 'Message', 'resend-email' ); ?></label>
						</th>
						<td>
							<textarea name="resend_test_email_message" 
								id="resend_test_email_message" 
								class="large-text" 
								rows="5"><?php echo esc_textarea( __( 'This is a test email sent using Resend Email Integration.', 'resend-email' ) ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Send Test Email', 'resend-email' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle test email submission.
	 *
	 * @return void
	 */
	private function handle_test_email() {
		// Check if test email form was submitted.
		if ( ! isset( $_POST['resend_test_email_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['resend_test_email_nonce'] ) ), 'resend_send_test_email' ) ) {
			add_settings_error(
				'resend_email_messages',
				'resend_email_message',
				__( 'Security check failed. Please try again.', 'resend-email' ),
				'error'
			);
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get form data.
		$to      = isset( $_POST['resend_test_email_to'] ) ? sanitize_email( wp_unslash( $_POST['resend_test_email_to'] ) ) : '';
		$subject = isset( $_POST['resend_test_email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['resend_test_email_subject'] ) ) : __( 'Resend Test Email', 'resend-email' );
		$message = isset( $_POST['resend_test_email_message'] ) ? wp_kses_post( wp_unslash( $_POST['resend_test_email_message'] ) ) : __( 'This is a test email sent using Resend Email Integration.', 'resend-email' );

		if ( empty( $to ) || ! is_email( $to ) ) {
			add_settings_error(
				'resend_email_messages',
				'resend_email_message',
				__( 'Please enter a valid email address.', 'resend-email' ),
				'error'
			);
			return;
		}

		// Send test email using wp_mail (which will be intercepted by Resend_Mailer if enabled).
		$result = wp_mail( $to, $subject, $message );

		if ( $result ) {
			add_settings_error(
				'resend_email_messages',
				'resend_email_message',
				__( 'Test email sent successfully!', 'resend-email' ),
				'success'
			);
		} else {
			add_settings_error(
				'resend_email_messages',
				'resend_email_message',
				__( 'Failed to send test email. Please check your settings and try again.', 'resend-email' ),
				'error'
			);
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @return void
	 */
	public function display_admin_notices() {
		// Only show on our settings page.
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_resend-email' !== $screen->id ) {
			return;
		}

		// Check for missing API key.
		$api_key = get_option( 'resend_api_key', '' );
		$override = get_option( 'resend_override', true );

		if ( empty( $api_key ) && $override ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'Resend Email Integration:', 'resend-email' ); ?></strong>
					<?php esc_html_e( 'API key is missing. Please enter your Resend API key to enable email sending.', 'resend-email' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * AJAX handler for testing API key.
	 *
	 * @return void
	 */
	public function ajax_test_api_key() {
		// Check nonce.
		check_ajax_referer( 'resend_test_api_key', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'resend-email' ) ) );
		}

		// Get API key from POST data.
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'API key is required.', 'resend-email' ) ) );
		}

		// Check if SDK is available.
		if ( ! Resend_Compat::can_use_resend() ) {
			wp_send_json_error( array( 'message' => __( 'Resend SDK is not available.', 'resend-email' ) ) );
		}

		try {
			// Create a temporary client with the provided API key.
			$client = \Resend::client( $api_key );

			// Test the API key by calling domains->list() endpoint.
			// This is a lightweight call that validates the API key.
			$response = $client->domains->list();

			// If we get here, the API key is valid.
			$message = __( 'API key is valid!', 'resend-email' );

			wp_send_json_success( array( 'message' => $message ) );
		} catch ( \Exception $e ) {
			/* translators: %s: Error message */
			wp_send_json_error( array( 'message' => sprintf( __( 'API key test failed: %s', 'resend-email' ), $e->getMessage() ) ) );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our settings page.
		if ( 'settings_page_resend-email' !== $hook ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'resend-email-admin',
			RESEND_EMAIL_INTEGRATION_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RESEND_EMAIL_INTEGRATION_VERSION
		);

		// Enqueue JS if needed.
		wp_enqueue_script(
			'resend-email-admin',
			RESEND_EMAIL_INTEGRATION_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			RESEND_EMAIL_INTEGRATION_VERSION,
			true
		);

		// Localize script with AJAX URL and nonce.
		wp_localize_script(
			'resend-email-admin',
			'resendEmailIntegration',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'resend_test_api_key' ),
				'strings' => array(
					'testApiKey'      => __( 'Test API Key', 'resend-email' ),
					'testing'         => __( 'Testing...', 'resend-email' ),
					'apiKeyRequired'  => __( 'Please enter an API key first.', 'resend-email' ),
					'error'           => __( 'An error occurred.', 'resend-email' ),
				),
			)
		);

		// Add inline script to handle email field combination.
		wp_add_inline_script(
			'resend-email-admin',
			'
			jQuery(document).ready(function($) {
				function updateFromEmail() {
					var local = $("#resend_from_email_local").val().trim();
					var domain = $("#resend_from_email_domain").val();
					if (local && domain) {
						$("#resend_from_email").val(local + "@" + domain);
					} else {
						$("#resend_from_email").val("");
					}
				}
				
				$("#resend_from_email_local, #resend_from_email_domain").on("input change", updateFromEmail);
				
				// Update on page load
				updateFromEmail();
				
				// Update before form submission
				$("form").on("submit", function() {
					updateFromEmail();
				});
			});
			'
		);
	}
}

