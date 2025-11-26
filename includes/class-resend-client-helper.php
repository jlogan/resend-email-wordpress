<?php
/**
 * Resend Client Helper class.
 *
 * Provides wrapper methods for initializing and using the Resend SDK client.
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
 * Class Resend_Client_Helper
 *
 * Helper class for Resend SDK client operations.
 */
class Resend_Client_Helper {

	/**
	 * Transient key for caching verified domains.
	 *
	 * @var string
	 */
	const VERIFIED_DOMAINS_CACHE_KEY = 'resend_verified_domains';

	/**
	 * Cache expiration time for verified domains (5 minutes).
	 *
	 * @var int
	 */
	const VERIFIED_DOMAINS_CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Get the Resend client instance.
	 *
	 * @return Resend|null Resend client instance or null if unavailable.
	 */
	public static function get_client() {
		// Check if environment is supported.
		if ( ! Resend_Compat::can_use_resend() ) {
			return null;
		}

		// Get API key from options.
		$api_key = get_option( 'resend_api_key', '' );

		if ( empty( $api_key ) ) {
			return null;
		}

		try {
			// Initialize Resend client.
			// Note: Resend class is in global namespace.
			// Expected: Resend::client($api_key)
			return \Resend::client( $api_key );
		} catch ( \Exception $e ) {
			// Log error but don't expose details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Resend Email Integration: Failed to initialize client - ' . $e->getMessage() );
			}
			return null;
		}
	}

	/**
	 * Get list of verified domains from Resend API.
	 *
	 * @param bool $force_refresh Force refresh of cached data.
	 * @return array Array of verified domain names.
	 */
	public static function get_verified_domains( $force_refresh = false ) {
		// Check if environment is supported.
		if ( ! Resend_Compat::can_use_resend() ) {
			return array();
		}

		// Try to get from cache first (unless forcing refresh).
		if ( ! $force_refresh ) {
			$cached = get_transient( self::VERIFIED_DOMAINS_CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$client = self::get_client();
		if ( ! $client ) {
			return array();
		}

		try {
			// Call domains->list() API.
			// Note: Verify exact method signature and response structure in Resend PHP SDK documentation.
			// Expected: $client->domains->list() or similar
			// Response structure: { "object": "list", "data": [{ "name": "example.com", "status": "verified", ... }] }
			$response = $client->domains->list();

			$verified_domains = array();

			// Extract verified domains from response.
			// Note: Adjust response structure access based on actual SDK response format.
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				foreach ( $response['data'] as $domain ) {
					// Check if domain status is "verified".
					// Note: Verify exact status string - may be "verified", "not_started", "pending", etc.
					if ( isset( $domain['status'] ) && 'verified' === $domain['status'] ) {
						if ( isset( $domain['name'] ) ) {
							$verified_domains[] = $domain['name'];
						}
					}
				}
			} elseif ( is_object( $response ) && isset( $response->data ) ) {
				// Handle object response format.
				foreach ( $response->data as $domain ) {
					if ( isset( $domain->status ) && 'verified' === $domain->status ) {
						if ( isset( $domain->name ) ) {
							$verified_domains[] = $domain->name;
						}
					}
				}
			}

			// Cache the result.
			set_transient( self::VERIFIED_DOMAINS_CACHE_KEY, $verified_domains, self::VERIFIED_DOMAINS_CACHE_EXPIRATION );

			return $verified_domains;
		} catch ( \Exception $e ) {
			// Log error but don't expose details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Resend Email Integration: Failed to fetch verified domains - ' . $e->getMessage() );
			}
			return array();
		}
	}

	/**
	 * Validate that an email address uses a verified domain.
	 *
	 * @param string $email Email address to validate.
	 * @return bool True if domain is verified, false otherwise.
	 */
	public static function validate_from_email_domain( $email ) {
		if ( empty( $email ) || ! is_email( $email ) ) {
			return false;
		}

		// Extract domain from email.
		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		$domain = strtolower( trim( $parts[1] ) );

		// Get verified domains.
		$verified_domains = self::get_verified_domains();

		// Check if domain is in verified list.
		return in_array( $domain, $verified_domains, true );
	}

	/**
	 * Clear the verified domains cache.
	 *
	 * @return void
	 */
	public static function clear_verified_domains_cache() {
		delete_transient( self::VERIFIED_DOMAINS_CACHE_KEY );
	}
}

