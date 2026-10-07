<?php
/**
 * Prembly verification provider.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Providers;

use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prembly provider adapter.
 */
final class PremblyProvider implements VerificationProvider {
	/**
	 * Default SDK session endpoint.
	 */
	private const DEFAULT_STATUS_ENDPOINT = 'https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{id}/';

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Constructor.
	 *
	 * @param string $option_name Settings option name.
	 */
	public function __construct( string $option_name ) {
		$this->option_name = $option_name;
	}

	/**
	 * Get provider slug.
	 */
	public function get_slug(): string {
		return 'prembly';
	}

	/**
	 * Get provider label.
	 */
	public function get_label(): string {
		return __( 'Prembly', 'hdyhaus-identity-verification' );
	}

	/**
	 * Get frontend-safe provider config.
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_config(): array {
		$settings = $this->get_settings();
		$mode     = isset( $settings['mode'] ) ? (string) $settings['mode'] : 'test';
		$key      = 'live' === $mode ? ( $settings['live_public_key'] ?? '' ) : ( $settings['test_public_key'] ?? '' );
		$config   = $this->get_environment_value( $settings, $mode, 'configuration_id', 'configuration_id' );

		return array(
			'mode'            => $mode,
			'isTest'          => 'test' === $mode,
			'publicKey'       => (string) $key,
			'configurationId' => $config,
		);
	}

	/**
	 * Confirm whether the verification is approved.
	 *
	 * @param string               $reference Provider reference.
	 * @param array<string, mixed> $context Verification context.
	 * @return array{verified: bool, status: string, reference: string, identity_hash?: string, raw?: array<string, mixed>}
	 */
	public function confirm_verification( string $reference, array $context = array() ): array {
		$reference = sanitize_text_field( $reference );

		if ( '' === $reference ) {
			return $this->build_result( false, 'missing_reference', $reference );
		}

		return $this->request_status( $reference, $this->get_settings(), $context );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {
		$settings = get_option( $this->option_name, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Request verification status from Prembly.
	 *
	 * @param string               $reference Verification reference.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array<string, mixed> $context Verification context.
	 * @return array{verified: bool, status: string, reference: string, identity_hash?: string, raw?: array<string, mixed>}
	 */
	private function request_status( string $reference, array $settings, array $context ): array {
		$url     = str_replace( '{id}', rawurlencode( $reference ), self::DEFAULT_STATUS_ENDPOINT );
		$headers = array(
			'Accept' => 'application/json',
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 20,
		);

		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $this->build_result( false, 'request_error', $reference );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return $this->build_result( false, 'http_' . $code, $reference );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return $this->build_result( false, 'invalid_response', $reference );
		}

		return $this->normalize_status( $body, $reference, $settings, $context );
	}

	/**
	 * Normalize Prembly status data into HDYHaus Identity Verification status.
	 *
	 * @param array<string, mixed> $body Response body.
	 * @param string               $fallback_reference Fallback reference.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array<string, mixed> $context Verification context.
	 * @return array{verified: bool, status: string, reference: string, identity_hash?: string, raw?: array<string, mixed>}
	 */
	private function normalize_status( array $body, string $fallback_reference, array $settings, array $context ): array {
		$data                  = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		$verification          = isset( $data['verification'] ) && is_array( $data['verification'] ) ? $data['verification'] : array();
		$verification_response = isset( $data['verification_response'] ) && is_array( $data['verification_response'] ) ? $data['verification_response'] : array();
		$widget_info           = isset( $data['widget_info'] ) && is_array( $data['widget_info'] ) ? $data['widget_info'] : array();
		$widget_config         = isset( $data['widget_config'] ) && is_array( $data['widget_config'] ) ? $data['widget_config'] : array();
		$metadata              = isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array();
		$status                = strtoupper(
			$this->first_string(
				array(
					$data['verification_status'] ?? null,
					$data['status'] ?? null,
					$verification['status'] ?? null,
					$verification_response['status'] ?? null,
					$body['verification_status'] ?? null,
					$body['status'] ?? null,
				)
			)
		);
		$response_reference    = $this->first_string(
			array(
				$data['session_id'] ?? null,
				$data['id'] ?? null,
				$widget_info['session_id'] ?? null,
				$body['session_id'] ?? null,
			)
		);
		$reference             = sanitize_text_field(
			$this->first_string(
				array(
					$response_reference,
					$fallback_reference,
				)
			)
		);
		$mode                  = isset( $settings['mode'] ) && 'live' === $settings['mode'] ? 'live' : 'test';
		$configured_widget     = $this->get_environment_value( $settings, $mode, 'configuration_id', 'configuration_id' );
		$response_widget       = $this->first_string(
			array(
				$data['widget_id'] ?? null,
				$widget_info['widget_id'] ?? null,
				$widget_info['id'] ?? null,
				$widget_config['id'] ?? null,
			)
		);

		if ( '' === $configured_widget ) {
			return $this->build_result( false, 'configuration_missing', $reference, $body );
		}

		if ( '' === $response_widget ) {
			return $this->build_result( false, 'widget_missing', $reference, $body );
		}

		if ( ! hash_equals( $configured_widget, $response_widget ) ) {
			return $this->build_result( false, 'widget_mismatch', $reference, $body );
		}

		$expected_email = isset( $context['email'] ) ? sanitize_email( (string) $context['email'] ) : '';
		$response_email = sanitize_email(
			$this->first_string(
				array(
					$data['end_user_email'] ?? null,
					$data['email'] ?? null,
					$metadata['email'] ?? null,
				)
			)
		);

		if ( '' === $expected_email || '' === $response_email ) {
			return $this->build_result( false, 'email_missing', $reference, $body );
		}

		if ( ! hash_equals( strtolower( $expected_email ), strtolower( $response_email ) ) ) {
			return $this->build_result( false, 'email_mismatch', $reference, $body );
		}

		if ( '' === $response_reference || ! hash_equals( $fallback_reference, $response_reference ) ) {
			return $this->build_result( false, 'reference_mismatch', $reference, $body );
		}

		if ( in_array( $status, array( 'COMPLETED', 'VERIFIED', 'SUCCESS', 'SUCCESSFUL', 'APPROVED' ), true ) ) {
			$identity_hash = $this->build_identity_hash( $data );

			if ( '' === $identity_hash ) {
				return $this->build_result( false, 'identity_missing', $reference, $body );
			}

			return $this->build_result( true, 'verified', $reference, $body, $identity_hash );
		}

		if ( in_array( $status, array( 'CREATED', 'INITIATED', 'IN_PROGRESS', 'PENDING', 'PROCESSING' ), true ) ) {
			return $this->build_result( false, 'pending', $reference, $body );
		}

		if ( in_array( $status, array( 'CANCELLED', 'FAILED', 'NOT-VERIFIED', 'NOT_VERIFIED', 'REJECTED' ), true ) ) {
			return $this->build_result( false, 'not_verified', $reference, $body );
		}

		return $this->build_result( false, 'unknown_status', $reference, $body );
	}

	/**
	 * Get an environment-specific setting with a legacy fallback.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @param string               $mode Active environment.
	 * @param string               $key Setting suffix.
	 * @param string               $legacy_key Legacy setting key.
	 */
	private function get_environment_value( array $settings, string $mode, string $key, string $legacy_key ): string {
		$environment_key = sprintf( '%s_%s', 'live' === $mode ? 'live' : 'test', $key );

		if ( isset( $settings[ $environment_key ] ) ) {
			return (string) $settings[ $environment_key ];
		}

		return isset( $settings[ $legacy_key ] ) ? (string) $settings[ $legacy_key ] : '';
	}

	/**
	 * Return the first non-empty string in a list.
	 *
	 * @param array<int, mixed> $values Candidate values.
	 */
	private function first_string( array $values ): string {
		foreach ( $values as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}

	/**
	 * Build a site-specific fingerprint without retaining identity document data.
	 *
	 * @param array<string, mixed> $data Prembly session data.
	 */
	private function build_identity_hash( array $data ): string {
		$addon_results     = isset( $data['addon_results'] ) && is_array( $data['addon_results'] ) ? $data['addon_results'] : array();
		$document_response = isset( $addon_results['document_verification_response'] ) && is_array( $addon_results['document_verification_response'] ) ? $addon_results['document_verification_response'] : array();
		$document_data     = isset( $document_response['data'] ) && is_array( $document_response['data'] ) ? $document_response['data'] : array();
		$metadata          = isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array();
		$sdk_details       = isset( $metadata['sdk_verification_details'] ) && is_array( $metadata['sdk_verification_details'] ) ? $metadata['sdk_verification_details'] : array();
		$document_details  = isset( $sdk_details['document'] ) && is_array( $sdk_details['document'] ) ? $sdk_details['document'] : array();
		$document_payload  = isset( $document_details['payload'] ) && is_array( $document_details['payload'] ) ? $document_details['payload'] : array();
		$document_number   = $this->normalize_identity_part(
			$this->first_string(
				array(
					$document_data['document_number'] ?? null,
					$document_data['documentNumber'] ?? null,
					$document_data['id_number'] ?? null,
				)
			)
		);
		$document_type     = $this->normalize_identity_part(
			$this->first_string(
				array(
					$document_data['document_type'] ?? null,
					$document_data['documentType'] ?? null,
					$document_payload['doc_type'] ?? null,
				)
			)
		);
		$document_country  = $this->normalize_identity_part(
			$this->first_string(
				array(
					$document_data['document_country'] ?? null,
					$document_data['documentCountry'] ?? null,
					$document_data['issuing_country'] ?? null,
					$document_payload['doc_country'] ?? null,
				)
			)
		);

		if ( '' === $document_number || '' === $document_type || '' === $document_country ) {
			return '';
		}

		$secret = $this->get_identity_secret();

		if ( '' === $secret ) {
			return '';
		}

		return hash_hmac(
			'sha256',
			implode( '|', array( $this->get_slug(), $document_country, $document_type, $document_number ) ),
			$secret
		);
	}

	/**
	 * Read the persistent key, allowing concurrent requests to share one winner.
	 */
	private function get_identity_secret(): string {
		$option_name = 'trustgate_identity_secret';
		$secret      = get_option( $option_name, false );

		if ( false === $secret ) {
			try {
				$candidate = bin2hex( random_bytes( 32 ) );
			} catch ( \Exception $exception ) {
				return '';
			}

			add_option( $option_name, $candidate, '', false );
			$secret = get_option( $option_name, false );
		}

		return is_string( $secret ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $secret ) ? $secret : '';
	}

	/**
	 * Normalize an identity component before hashing it.
	 *
	 * @param string $value Identity component.
	 */
	private function normalize_identity_part( string $value ): string {
		$normalized = preg_replace( '/[^A-Z0-9]/', '', strtoupper( trim( $value ) ) );

		return is_string( $normalized ) ? $normalized : '';
	}

	/**
	 * Build normalized result.
	 *
	 * @param bool                 $verified Whether verification is approved.
	 * @param string               $status Normalized status.
	 * @param string               $reference Verification reference.
	 * @param array<string, mixed> $raw Raw response body.
	 * @param string               $identity_hash Site-specific identity fingerprint.
	 * @return array{verified: bool, status: string, reference: string, identity_hash?: string, raw?: array<string, mixed>}
	 */
	private function build_result( bool $verified, string $status, string $reference, array $raw = array(), string $identity_hash = '' ): array {
		$result = array(
			'verified'  => $verified,
			'status'    => $status,
			'reference' => $reference,
		);

		if ( array() !== $raw ) {
			$result['raw'] = $raw;
		}

		if ( '' !== $identity_hash ) {
			$result['identity_hash'] = $identity_hash;
		}

		return $result;
	}
}
