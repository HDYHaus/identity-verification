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
	 * Default verification status endpoint.
	 */
	private const DEFAULT_STATUS_ENDPOINT = 'https://api.prembly.com/verification/{id}/status';

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

		return array(
			'mode'            => $mode,
			'isTest'          => 'test' === $mode,
			'publicKey'       => (string) $key,
			'configurationId' => (string) ( $settings['configuration_id'] ?? '' ),
		);
	}

	/**
	 * Confirm whether the verification is approved.
	 *
	 * @param string $reference Provider reference.
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
	 */
	public function confirm_verification( string $reference ): array {
		$reference = sanitize_text_field( $reference );

		if ( '' === $reference ) {
			return $this->build_result( false, 'missing_reference', $reference );
		}

		$settings   = $this->get_settings();
		$secret_key = isset( $settings['secret_key'] ) ? (string) $settings['secret_key'] : '';

		if ( '' === $secret_key ) {
			return $this->build_result( false, 'missing_secret_key', $reference );
		}

		$response = $this->request_status( $reference, $secret_key, $settings, 'POST' );

		if ( 'method_not_allowed' === $response['status'] ) {
			$response = $this->request_status( $reference, $secret_key, $settings, 'GET' );
		}

		return $response;
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
	 * @param string               $secret_key Secret API key.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param string               $method HTTP method.
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
	 */
	private function request_status( string $reference, string $secret_key, array $settings, string $method ): array {
		$endpoint = isset( $settings['status_endpoint'] ) && '' !== $settings['status_endpoint']
			? (string) $settings['status_endpoint']
			: self::DEFAULT_STATUS_ENDPOINT;
		$url      = str_replace( '{id}', rawurlencode( $reference ), $endpoint );
		$headers  = array(
			'Accept'    => 'application/json',
			'x-api-key' => $secret_key,
		);
		$app_id   = isset( $settings['app_id'] ) ? (string) $settings['app_id'] : '';

		if ( '' !== $app_id ) {
			$headers['app-id'] = $app_id;
			$headers['app_id'] = $app_id;
		}

		$args = array(
			'headers' => $headers,
			'method'  => $method,
			'timeout' => 20,
		);

		if ( 'POST' === $method ) {
			$args['body']                    = wp_json_encode(
				array(
					'reference' => $reference,
				)
			);
			$args['headers']['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $this->build_result( false, 'request_error', $reference );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 405 === $code ) {
			return $this->build_result( false, 'method_not_allowed', $reference );
		}

		if ( $code < 200 || $code >= 300 ) {
			return $this->build_result( false, 'http_' . $code, $reference );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return $this->build_result( false, 'invalid_response', $reference );
		}

		return $this->normalize_status( $body, $reference );
	}

	/**
	 * Normalize Prembly status data into TrustGate status.
	 *
	 * @param array<string, mixed> $body Response body.
	 * @param string               $fallback_reference Fallback reference.
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
	 */
	private function normalize_status( array $body, string $fallback_reference ): array {
		$data                = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		$verification        = isset( $body['verification'] ) && is_array( $body['verification'] ) ? $body['verification'] : array();
		$response_code       = (string) ( $data['response_code'] ?? $body['response_code'] ?? '' );
		$verification_status = strtoupper( (string) ( $data['verification_status'] ?? $verification['status'] ?? '' ) );
		$reference           = sanitize_text_field( (string) ( $data['reference'] ?? $verification['reference'] ?? $fallback_reference ) );

		if ( '00' === $response_code && 'VERIFIED' === $verification_status ) {
			return $this->build_result( true, 'verified', $reference, $body );
		}

		if ( 'PENDING' === $verification_status ) {
			return $this->build_result( false, 'pending', $reference, $body );
		}

		if ( 'NOT-VERIFIED' === $verification_status || 'NOT_VERIFIED' === $verification_status ) {
			return $this->build_result( false, 'not_verified', $reference, $body );
		}

		if ( '00' !== $response_code && '' !== $response_code ) {
			return $this->build_result( false, 'response_code_' . sanitize_key( $response_code ), $reference, $body );
		}

		return $this->build_result( false, 'unverified', $reference, $body );
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
