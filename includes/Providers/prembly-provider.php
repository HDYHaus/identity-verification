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
		return __( 'Prembly', 'trustgate-registration' );
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
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
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
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
	 */
	private function request_status( string $reference, array $settings, array $context ): array {
		$endpoint = isset( $settings['status_endpoint'] ) && '' !== $settings['status_endpoint']
			? (string) $settings['status_endpoint']
			: self::DEFAULT_STATUS_ENDPOINT;
		$url      = str_replace( '{id}', rawurlencode( $reference ), $endpoint );
		$headers  = array(
			'Accept' => 'application/json',
		);

		$args = array(
			'headers' => $headers,
			'method'  => 'GET',
			'timeout' => 20,
		);

		$response = wp_remote_request( $url, $args );

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
	 * Normalize Prembly status data into TrustGate status.
	 *
	 * @param array<string, mixed> $body Response body.
	 * @param string               $fallback_reference Fallback reference.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param array<string, mixed> $context Verification context.
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
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
		$reference             = sanitize_text_field(
			$this->first_string(
				array(
					$data['session_id'] ?? null,
					$data['id'] ?? null,
					$widget_info['session_id'] ?? null,
					$body['session_id'] ?? null,
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

		if ( '' !== $configured_widget && '' !== $response_widget && ! hash_equals( $configured_widget, $response_widget ) ) {
			return $this->build_result( false, 'widget_mismatch', $reference, $body );
		}

		$expected_email = isset( $context['email'] ) ? sanitize_email( (string) $context['email'] ) : '';
		$response_email = sanitize_email( $this->first_string( array( $data['email'] ?? null, $metadata['email'] ?? null ) ) );

		if ( '' !== $expected_email && '' !== $response_email && ! hash_equals( strtolower( $expected_email ), strtolower( $response_email ) ) ) {
			return $this->build_result( false, 'email_mismatch', $reference, $body );
		}

		if ( in_array( $status, array( 'COMPLETED', 'VERIFIED', 'SUCCESS', 'SUCCESSFUL', 'APPROVED' ), true ) ) {
			return $this->build_result( true, 'verified', $reference, $body );
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
	 * Build normalized result.
	 *
	 * @param bool                 $verified Whether verification is approved.
	 * @param string               $status Normalized status.
	 * @param string               $reference Verification reference.
	 * @param array<string, mixed> $raw Raw response body.
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
	 */
	private function build_result( bool $verified, string $status, string $reference, array $raw = array() ): array {
		$result = array(
			'verified'  => $verified,
			'status'    => $status,
			'reference' => $reference,
		);

		if ( array() !== $raw ) {
			$result['raw'] = $raw;
		}

		return $result;
	}
}
