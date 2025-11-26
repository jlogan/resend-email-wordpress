<?php
/**
 * Email Logs/History page for Resend Email Integration.
 *
 * @package ResendEmailIntegration
 */

namespace Resend_Email_Integration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resend_Logs_Page
 *
 * Handles the email history/logs viewer page.
 */
class Resend_Logs_Page {

	/**
	 * Initialize the logs page.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_logs_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_resend_get_email_details', array( $this, 'ajax_get_email_details' ) );
		add_action( 'wp_ajax_resend_import_emails', array( $this, 'ajax_import_emails' ) );
	}

	/**
	 * Add logs page to WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_logs_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Resend Email Logs', 'resend-email' ),
			__( 'Resend Email Logs', 'resend-email' ),
			'manage_options',
			'resend-email-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get pagination parameters.
		$limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 20; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit = min( $limit, 100 ); // Max 100 per page.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset = ( $page - 1 ) * $limit;

		// Fetch emails from database.
		$emails_data = $this->fetch_emails_from_db( $limit, $offset );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<button type="button" id="resend-import-emails-btn" class="page-title-action">
				<?php esc_html_e( 'Import Emails', 'resend-email' ); ?>
			</button>
			<span id="resend-import-status" style="margin-left: 10px;"></span>
			<hr class="wp-header-end">

			<?php
			// Display errors if any.
			if ( isset( $emails_data['error'] ) ) {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Error:', 'resend-email' ); ?></strong>
						<?php echo esc_html( $emails_data['error'] ); ?>
					</p>
				</div>
				<?php
			} else {
				// Display emails table.
				$this->render_emails_table( $emails_data, $page, $limit );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Fetch emails from local database.
	 *
	 * @param int $limit  Number of emails to fetch.
	 * @param int $offset Offset for pagination.
	 * @return array Emails data or error message.
	 */
	private function fetch_emails_from_db( $limit = 20, $offset = 0 ) {
		$result = Resend_DB::get_emails( $limit, $offset );

		return array(
			'emails'   => $result['emails'],
			'total'    => $result['total'],
			'has_more' => ( $offset + $limit ) < $result['total'],
		);
	}

	/**
	 * Fetch emails from Resend API (for import).
	 *
	 * @param int $limit Number of emails to fetch (max 100).
	 * @return array Emails data or error message.
	 */
	private function fetch_emails_from_api( $limit = 100 ) {
		// Check if environment is supported.
		if ( ! Resend_Compat::can_use_resend() ) {
			return array(
				'error' => __( 'Resend SDK is not available. Please check your PHP version and Composer dependencies.', 'resend-email' ),
			);
		}

		// Get Resend client.
		$client = Resend_Client_Helper::get_client();
		if ( ! $client ) {
			return array(
				'error' => __( 'Resend API key is missing or invalid. Please configure it in Settings → Resend Email.', 'resend-email' ),
			);
		}

		try {
			// Prepare parameters for API call.
			// Fetch up to 100 emails for import.
			$limit = min( $limit, 100 );
			$params = array( 'limit' => $limit );

			$response = $client->emails->list( $params );

			// Parse response.
			// The SDK returns a \Resend\Collection object which extends Resource.
			// Collection has a $data property that contains the array of emails.
			$emails = array();
			$has_more = false;

			// Handle Resend\Collection object (which extends Resource).
			// Resource uses magic methods to access attributes, so $response->data works.
			if ( $response instanceof \Resend\Collection || ( is_object( $response ) && method_exists( $response, 'getAttribute' ) ) ) {
				// Access the data property via magic method.
				$emails = $response->data ?? array();
				$emails = is_array( $emails ) ? $emails : array();
				$has_more = isset( $response->has_more ) ? (bool) $response->has_more : false;
			} elseif ( is_array( $response ) ) {
				// Fallback for array response.
				$emails = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
				$has_more = isset( $response['has_more'] ) ? (bool) $response['has_more'] : false;
			} elseif ( is_object( $response ) ) {
				// Fallback for generic object response.
				$emails = isset( $response->data ) && is_array( $response->data ) ? $response->data : array();
				$has_more = isset( $response->has_more ) ? (bool) $response->has_more : false;
			}

			// Debug logging if enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Resend Email Integration: Response type: ' . gettype( $response ) );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Resend Email Integration: Response class: ' . ( is_object( $response ) ? get_class( $response ) : 'N/A' ) );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Resend Email Integration: Emails count: ' . count( $emails ) );
			}

			return array(
				'emails'   => $emails,
				'has_more' => $has_more,
			);
		} catch ( \Exception $e ) {
			// Log error.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Resend Email Integration: Failed to fetch emails - ' . $e->getMessage() );
			}

			return array(
				'error' => sprintf(
					/* translators: %s: Error message */
					__( 'Failed to fetch emails: %s', 'resend-email' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Render emails table.
	 *
	 * @param array $emails_data Emails data from database.
	 * @param int   $page        Current page number.
	 * @param int   $limit       Items per page.
	 * @return void
	 */
	private function render_emails_table( $emails_data, $page = 1, $limit = 20 ) {
		$emails = isset( $emails_data['emails'] ) ? $emails_data['emails'] : array();
		$total = isset( $emails_data['total'] ) ? $emails_data['total'] : 0;
		$has_more = isset( $emails_data['has_more'] ) ? $emails_data['has_more'] : false;
		$has_emails = ! empty( $emails ) && is_array( $emails );

		// Calculate pagination.
		$total_pages = ceil( $total / $limit );
		$base_url = admin_url( 'options-general.php?page=resend-email-logs&limit=' . $limit );

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Date/Time', 'resend-email' ); ?></th>
					<th scope="col"><?php esc_html_e( 'To', 'resend-email' ); ?></th>
					<th scope="col"><?php esc_html_e( 'From', 'resend-email' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Subject', 'resend-email' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'resend-email' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message ID', 'resend-email' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $has_emails ) : ?>
					<?php foreach ( $emails as $email ) : ?>
					<?php
					// Extract email data (from database, always array).
					$id = $email['id'] ?? '';
					$to = $email['to'] ?? array();
					$from = $email['from'] ?? '';
					$subject = $email['subject'] ?? '';
					$created_at = $email['created_at'] ?? '';
					$last_event = $email['last_event'] ?? '';

					// Format TO addresses.
					$to_display = is_array( $to ) ? implode( ', ', $to ) : $to;

					// Parse FROM field to extract name (remove quotes and email).
					$from_display = $from;
					if ( ! empty( $from ) ) {
						// Check if FROM is in format "Name <email@domain.com>" or just email.
						if ( preg_match( '/^(.+?)\s*<(.+?)>$/', $from, $matches ) ) {
							// Extract name and remove quotes.
							$from_display = trim( $matches[1], ' "\'' );
						} elseif ( preg_match( '/^"(.+?)"$/', $from, $matches ) ) {
							// Just quoted name.
							$from_display = $matches[1];
						}
					}

					// Format date/time - Dates in database are stored as UTC.
					$date_display = '';
					if ( ! empty( $created_at ) ) {
						// Check if it's a MySQL datetime format (from database) or ISO 8601 format (from API).
						if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created_at ) ) {
							// MySQL datetime format from database - get_date_from_gmt() converts UTC to site timezone.
							// Format directly using WordPress date format options.
							$date_display = get_date_from_gmt( $created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
							if ( empty( $date_display ) ) {
								$date_display = $created_at;
							}
						} else {
							// ISO 8601 format from API (includes timezone info like "2025-11-25T19:08:15.753197+00:00").
							// Parse as UTC timestamp, then convert to site timezone.
							$timestamp = strtotime( $created_at );
							if ( false !== $timestamp && $timestamp > 0 ) {
								// wp_date() converts from UTC to WordPress timezone.
								$date_display = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
							} else {
								// Fallback: show raw date if parsing fails.
								$date_display = $created_at;
							}
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $date_display ); ?></td>
						<td><?php echo esc_html( $to_display ); ?></td>
						<td><?php echo esc_html( $from_display ); ?></td>
						<td><?php echo esc_html( $subject ); ?></td>
						<td>
							<span class="resend-status resend-status-<?php echo esc_attr( strtolower( $last_event ) ); ?>">
								<?php echo esc_html( ucfirst( $last_event ) ); ?>
							</span>
						</td>
						<td>
							<code>
								<a href="#" 
									class="resend-email-detail-link" 
									data-email-id="<?php echo esc_attr( $id ); ?>"
									title="<?php esc_attr_e( 'Click to view email details', 'resend-email' ); ?>">
									<?php echo esc_html( $id ); ?>
								</a>
							</code>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 20px;">
							<?php esc_html_e( 'No emails found. Click "Import Emails" to sync emails from Resend.', 'resend-email' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		// Pagination controls.
		if ( $total_pages > 1 ) :
			?>
			<div class="tablenav bottom" style="margin-top: 20px; padding: 10px 0; border-top: 1px solid #ddd;">
				<div class="alignleft actions">
					<?php if ( $page > 1 ) : ?>
						<a href="<?php echo esc_url( $base_url . '&paged=1' ); ?>" class="button">
							<?php esc_html_e( 'First Page', 'resend-email' ); ?>
						</a>
						<a href="<?php echo esc_url( $base_url . '&paged=' . ( $page - 1 ) ); ?>" class="button">
							<?php esc_html_e( 'Previous Page', 'resend-email' ); ?>
						</a>
					<?php endif; ?>
					
					<span style="margin: 0 10px;">
						<?php
						/* translators: %1$d: Current page, %2$d: Total pages */
						printf( esc_html__( 'Page %1$d of %2$d', 'resend-email' ), esc_html( $page ), esc_html( $total_pages ) );
						?>
					</span>
					
					<?php if ( $page < $total_pages ) : ?>
						<a href="<?php echo esc_url( $base_url . '&paged=' . ( $page + 1 ) ); ?>" class="button">
							<?php esc_html_e( 'Next Page', 'resend-email' ); ?>
						</a>
						<a href="<?php echo esc_url( $base_url . '&paged=' . $total_pages ); ?>" class="button">
							<?php esc_html_e( 'Last Page', 'resend-email' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endif;
		?>

		<!-- Email Details Modal -->
		<div id="resend-email-detail-modal" class="resend-modal" style="display: none;">
			<div class="resend-modal-content">
				<div class="resend-modal-header">
					<h2><?php esc_html_e( 'Email Details', 'resend-email' ); ?></h2>
					<span class="resend-modal-close">&times;</span>
				</div>
				<div class="resend-modal-body" id="resend-email-detail-content">
					<p><?php esc_html_e( 'Loading...', 'resend-email' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for importing emails from Resend API.
	 *
	 * @return void
	 */
	public function ajax_import_emails() {
		// Check nonce.
		check_ajax_referer( 'resend_import_emails', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'resend-email' ) ) );
		}

		// Check if SDK is available.
		if ( ! Resend_Compat::can_use_resend() ) {
			wp_send_json_error( array( 'message' => __( 'Resend SDK is not available.', 'resend-email' ) ) );
		}

		// Get Resend client.
		$client = Resend_Client_Helper::get_client();
		if ( ! $client ) {
			wp_send_json_error( array( 'message' => __( 'Resend API key is missing or invalid.', 'resend-email' ) ) );
		}

		try {
			// Fetch last 100 emails from API.
			$emails_data = $this->fetch_emails_from_api( 100 );

			if ( isset( $emails_data['error'] ) ) {
				wp_send_json_error( array( 'message' => $emails_data['error'] ) );
				return;
			}

			$emails = $emails_data['emails'] ?? array();
			if ( empty( $emails ) ) {
				wp_send_json_success( array( 'message' => __( 'No emails found to import.', 'resend-email' ), 'imported' => 0 ) );
				return;
			}

			// Convert API response to database format.
			$emails_to_save = array();
			foreach ( $emails as $email ) {
				// Handle both array and object formats.
				if ( is_array( $email ) ) {
					$email_data = $email;
				} else {
					// Object (Resource object with magic methods).
					$email_data = array(
						'id'         => $email->id ?? '',
						'to'         => $email->to ?? array(),
						'from'       => $email->from ?? '',
						'subject'    => $email->subject ?? '',
						'cc'         => $email->cc ?? array(),
						'bcc'        => $email->bcc ?? array(),
						'reply_to'   => $email->reply_to ?? array(),
						'created_at' => $email->created_at ?? '',
						'last_event' => $email->last_event ?? '',
						'scheduled_at' => $email->scheduled_at ?? null,
						// Don't include html/text in initial import - will be fetched when viewing details.
						'html'       => null,
						'text'       => null,
					);
				}

				// Only save if we have an ID.
				if ( ! empty( $email_data['id'] ) ) {
					$emails_to_save[] = $email_data;
				}
			}

			// Bulk save to database.
			$imported = Resend_DB::bulk_save_emails( $emails_to_save );

			wp_send_json_success(
				array(
					'message'  => sprintf(
						/* translators: %d: Number of emails imported */
						_n( '%d email imported successfully.', '%d emails imported successfully.', $imported, 'resend-email' ),
						$imported
					),
					'imported' => $imported,
				)
			);
		} catch ( \Exception $e ) {
			/* translators: %s: Error message */
			wp_send_json_error( array( 'message' => sprintf( __( 'Failed to import emails: %s', 'resend-email' ), $e->getMessage() ) ) );
		}
	}

	/**
	 * AJAX handler for retrieving email details.
	 *
	 * @return void
	 */
	public function ajax_get_email_details() {
		// Check nonce.
		check_ajax_referer( 'resend_get_email_details', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'resend-email' ) ) );
		}

		// Get email ID from POST data.
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';

		if ( empty( $email_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Email ID is required.', 'resend-email' ) ) );
		}

		// First, check if email exists in database and if it has HTML content.
		$email_data = Resend_DB::get_email( $email_id );
		
		// Check if we have HTML content in the database.
		$has_html_content = false;
		if ( $email_data && isset( $email_data['html'] ) ) {
			// Check if HTML is not null and not empty string.
			$has_html_content = ( $email_data['html'] !== null && $email_data['html'] !== '' );
		}

		// If we have HTML content in database, return it immediately (no API call needed).
		if ( $has_html_content && $email_data ) {
			wp_send_json_success( array( 'email' => $email_data, 'cached' => true ) );
			return;
		}

		// Need to fetch full details from API.
		// Check if SDK is available.
		if ( ! Resend_Compat::can_use_resend() ) {
			wp_send_json_error( array( 'message' => __( 'Resend SDK is not available.', 'resend-email' ) ) );
		}

		// Get Resend client.
		$client = Resend_Client_Helper::get_client();
		if ( ! $client ) {
			wp_send_json_error( array( 'message' => __( 'Resend API key is missing or invalid.', 'resend-email' ) ) );
		}

		try {
			// Retrieve email details using emails->get() method.
			$response = $client->emails->get( $email_id );

			// Parse response.
			$full_email_data = array();

			if ( is_array( $response ) ) {
				$full_email_data = $response;
				// Convert null to empty string for html/text to indicate "fetched but no content".
				if ( isset( $full_email_data['html'] ) && $full_email_data['html'] === null ) {
					$full_email_data['html'] = '';
				}
				if ( isset( $full_email_data['text'] ) && $full_email_data['text'] === null ) {
					$full_email_data['text'] = '';
				}
			} elseif ( is_object( $response ) ) {
				// Handle Resource object with magic methods.
				// Directly access properties via magic methods (Resource objects use __get).
				// HTML and text are only available from the retrieve-email endpoint.
				$html = $response->html ?? null;
				$text = $response->text ?? null;
				
				// Convert null to empty string for storage (NULL means not fetched, empty string means fetched but no content).
				if ( $html === null ) {
					$html = '';
				}
				if ( $text === null ) {
					$text = '';
				}
				
				$full_email_data = array(
					'id'         => $response->id ?? '',
					'to'         => $response->to ?? array(),
					'from'       => $response->from ?? '',
					'subject'    => $response->subject ?? '',
					'html'       => $html,
					'text'       => $text,
					'cc'         => $response->cc ?? array(),
					'bcc'        => $response->bcc ?? array(),
					'reply_to'   => $response->reply_to ?? array(),
					'created_at' => $response->created_at ?? '',
					'last_event' => $response->last_event ?? '',
					'scheduled_at' => $response->scheduled_at ?? null,
				);
				
				// Debug logging.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$html_length = ( $html && $html !== '' ) ? strlen( $html ) : 0;
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'Resend Email Integration: Retrieved email details for %s - HTML length: %d, Text length: %d', $email_id, $html_length, $text ? strlen( $text ) : 0 ) );
				}
			}

			// Merge with existing data if we had partial data.
			// array_merge will overwrite $email_data values with $full_email_data values.
			if ( $email_data ) {
				$full_email_data = array_merge( $email_data, $full_email_data );
			}

			// Save/update to database with full details (including HTML/text).
			if ( ! empty( $full_email_data['id'] ) ) {
				Resend_DB::save_email( $full_email_data );
			}

			// Now retrieve the updated data from database to ensure consistency.
			$saved_email_data = Resend_DB::get_email( $email_id );
			if ( $saved_email_data ) {
				// Return data from database (not API response) for consistency.
				wp_send_json_success( array( 'email' => $saved_email_data, 'cached' => false ) );
			} else {
				// Fallback to API response if database retrieval fails.
				wp_send_json_success( array( 'email' => $full_email_data, 'cached' => false ) );
			}
		} catch ( \Exception $e ) {
			/* translators: %s: Error message */
			wp_send_json_error( array( 'message' => sprintf( __( 'Failed to retrieve email: %s', 'resend-email' ), $e->getMessage() ) ) );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our logs page.
		if ( 'settings_page_resend-email-logs' !== $hook ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'resend-email-admin',
			RESEND_EMAIL_INTEGRATION_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RESEND_EMAIL_INTEGRATION_VERSION
		);

		// Enqueue JS.
		wp_enqueue_script(
			'resend-email-admin',
			RESEND_EMAIL_INTEGRATION_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			RESEND_EMAIL_INTEGRATION_VERSION,
			true
		);

		// Localize script with AJAX URL and nonces.
		wp_localize_script(
			'resend-email-admin',
			'resendEmailIntegration',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'resend_get_email_details' ),
				'importNonce' => wp_create_nonce( 'resend_import_emails' ),
				'strings' => array(
					'loading'     => __( 'Loading...', 'resend-email' ),
					'error'       => __( 'An error occurred.', 'resend-email' ),
					'importing'   => __( 'Importing emails...', 'resend-email' ),
					'importBtn'   => __( 'Import Emails', 'resend-email' ),
				),
			)
		);
	}
}

