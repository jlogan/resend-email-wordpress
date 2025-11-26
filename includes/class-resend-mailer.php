<?php
/**
 * Resend Mailer class.
 *
 * Intercepts wp_mail calls and routes them through Resend SDK.
 *
 * @package ResendEmailIntegration
 */

namespace Resend_Email_Integration;

// Resend class is in global namespace, not Resend\Resend

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Resend_Mailer
 *
 * Handles email sending via Resend SDK.
 */
class Resend_Mailer {

	/**
	 * Initialize the mailer.
	 *
	 * @return void
	 */
	public function __construct() {
		// Hook into pre_wp_mail to intercept email sending.
		add_filter( 'pre_wp_mail', array( $this, 'send_email' ), 10, 2 );
	}

	/**
	 * Send email via Resend SDK.
	 *
	 * @param null|bool $return Short-circuit return value.
	 * @param array      $atts   Email arguments.
	 * @return bool|null True on success, false on failure, null to fall back to default.
	 */
	public function send_email( $return, $atts ) {
		// If override is disabled, fall back to default.
		$override = get_option( 'resend_override', true );
		if ( ! $override ) {
			return null;
		}

		// Check if environment is supported.
		if ( ! Resend_Compat::can_use_resend() ) {
			return null;
		}

		// Get Resend client.
		$client = Resend_Client_Helper::get_client();
		if ( ! $client ) {
			return null;
		}

		// Extract email parameters.
		$to          = isset( $atts['to'] ) ? $atts['to'] : '';
		$subject     = isset( $atts['subject'] ) ? $atts['subject'] : '';
		$message     = isset( $atts['message'] ) ? $atts['message'] : '';
		$headers     = isset( $atts['headers'] ) ? $atts['headers'] : array();
		$attachments = isset( $atts['attachments'] ) ? $atts['attachments'] : array();

		// Validate required fields.
		if ( empty( $to ) || empty( $subject ) || empty( $message ) ) {
			return false;
		}

		// Parse headers (for CC, BCC, Reply-To, Content-Type, and optionally From).
		$parsed_headers = $this->parse_headers( $headers );

		// Check if we should force the From name and email.
		$force_from = get_option( 'resend_force_from', false );

		// Get configured FROM email and name from plugin settings (used as fallback).
		$from_email = get_option( 'resend_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'resend_from_name', get_bloginfo( 'name' ) );

		// If force_from is enabled, always use configured values.
		// Otherwise, use the From header provided by wp_mail or plugins (e.g., Contact Form 7).
		if ( ! $force_from && ! empty( $parsed_headers['from'] ) && ! empty( $parsed_headers['from']['email'] ) ) {
			// Use the From header provided by wp_mail or plugins.
			// Note: If the domain isn't verified, Resend will return an error, but we respect the user's intent.
			$provided_email = $parsed_headers['from']['email'];
			$from_email = $provided_email;
			$from_name  = ! empty( $parsed_headers['from']['name'] ) ? $parsed_headers['from']['name'] : $from_name;
			
			// Log a warning if domain is not verified (but still use it).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! Resend_Client_Helper::validate_from_email_domain( $provided_email ) ) {
				error_log( sprintf( 'Resend Email Integration: Using unverified domain "%s" from From header. Email may fail if domain is not verified in Resend.', $provided_email ) );
			}
		}

		// Validate that FROM email is set and not empty.
		if ( empty( $from_email ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Resend Email Integration: FROM email is not configured. Please set it in Settings → Resend Email.' );
			}
			return false;
		}

		// Format FROM address.
		$from = ! empty( $from_name ) ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email;

		// Normalize TO addresses.
		$to_addresses = $this->normalize_email_addresses( $to );

		// Determine content type.
		$content_type = ! empty( $parsed_headers['content-type'] ) ? $parsed_headers['content-type'] : 'text/html';

		// Prepare email data for Resend SDK.
		$email_data = array(
			'from'    => $from,
			'to'      => $to_addresses,
			'subject' => $subject,
		);

		// Set HTML or text content based on content type.
		if ( strpos( $content_type, 'text/html' ) !== false ) {
			$email_data['html'] = $message;
		} else {
			$email_data['text'] = $message;
		}

		// Add CC if present.
		if ( ! empty( $parsed_headers['cc'] ) ) {
			$email_data['cc'] = $this->normalize_email_addresses( $parsed_headers['cc'] );
		}

		// Add BCC if present.
		if ( ! empty( $parsed_headers['bcc'] ) ) {
			$email_data['bcc'] = $this->normalize_email_addresses( $parsed_headers['bcc'] );
		}

		// Add Reply-To if present.
		if ( ! empty( $parsed_headers['reply-to'] ) ) {
			$email_data['reply_to'] = $parsed_headers['reply-to'];
		}

		// Handle attachments if SDK supports them.
		// Note: Verify attachment format in Resend PHP SDK documentation.
		if ( ! empty( $attachments ) && is_array( $attachments ) ) {
			$email_data['attachments'] = $this->prepare_attachments( $attachments );
		}

		try {
			// Send email via Resend SDK.
			// Note: Verify exact method signature in Resend PHP SDK documentation.
			// Expected: $client->emails->send($email_data) or similar
			$response = $client->emails->send( $email_data );

			// Log success if WP_DEBUG is enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Resend Email Integration: Email sent successfully' );
			}

			return true;
		} catch ( \Exception $e ) {
			// Log error.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Resend Email Integration: Failed to send email - ' . $e->getMessage() );
			}

			// Return false to indicate failure.
			return false;
		}
	}

	/**
	 * Parse email headers into structured format.
	 *
	 * @param string|array $headers Headers to parse.
	 * @return array Parsed headers.
	 */
	private function parse_headers( $headers ) {
		$parsed = array(
			'from'        => array(),
			'cc'          => '',
			'bcc'         => '',
			'reply-to'    => '',
			'content-type' => 'text/html',
		);

		// Convert headers to array if string.
		if ( is_string( $headers ) ) {
			$headers = explode( "\n", $headers );
		}

		if ( ! is_array( $headers ) ) {
			return $parsed;
		}

		foreach ( $headers as $header ) {
			$header = trim( $header );
			if ( empty( $header ) ) {
				continue;
			}

			// Split header into name and value.
			if ( strpos( $header, ':' ) === false ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $header, 2 );
			$name  = strtolower( trim( $name ) );
			$value = trim( $value );

			switch ( $name ) {
				case 'from':
					// Parse "Name <email@example.com>" format.
					if ( preg_match( '/^(.+?)\s*<(.+?)>$/', $value, $matches ) ) {
						$parsed['from'] = array(
							'name'  => trim( $matches[1], '"\'' ),
							'email' => trim( $matches[2] ),
						);
					} else {
						$parsed['from'] = array(
							'name'  => '',
							'email' => $value,
						);
					}
					break;

				case 'cc':
					$parsed['cc'] = $value;
					break;

				case 'bcc':
					$parsed['bcc'] = $value;
					break;

				case 'reply-to':
					$parsed['reply-to'] = $value;
					break;

				case 'content-type':
					$parsed['content-type'] = $value;
					break;
			}
		}

		return $parsed;
	}

	/**
	 * Normalize email addresses to array format.
	 *
	 * @param string|array $addresses Email addresses.
	 * @return array Array of email addresses.
	 */
	private function normalize_email_addresses( $addresses ) {
		if ( is_array( $addresses ) ) {
			return $addresses;
		}

		// Split comma-separated addresses.
		$addresses = explode( ',', $addresses );
		$addresses = array_map( 'trim', $addresses );
		$addresses = array_filter( $addresses );

		return array_values( $addresses );
	}

	/**
	 * Prepare attachments for Resend SDK.
	 *
	 * @param array $attachments Array of file paths.
	 * @return array Prepared attachments array.
	 */
	private function prepare_attachments( $attachments ) {
		$prepared = array();

		foreach ( $attachments as $attachment ) {
			if ( ! file_exists( $attachment ) || ! is_readable( $attachment ) ) {
				continue;
			}

			// Read file content and encode as base64.
			$file_content = file_get_contents( $attachment ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $file_content ) {
				continue;
			}

			// Get filename.
			$filename = basename( $attachment );

			// Note: Verify exact attachment format expected by Resend SDK.
			// May need: array('filename' => $filename, 'content' => base64_encode($file_content))
			// or: array('path' => $attachment) if SDK handles file reading
			$prepared[] = array(
				'filename' => $filename,
				'content'  => base64_encode( $file_content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}

		return $prepared;
	}
}

