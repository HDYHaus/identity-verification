<?php
/**
 * Sumsub WebSDK verification provider.
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
 * Issues applicant-scoped SDK tokens and confirms results over signed API calls.
 */
final class SumsubProvider implements VerificationProvider {
	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Sanitized outcome of the last API request, without response data.
	 *
	 * @var string
	 */
	private string $request_status = 'request_failed';

	/**
	 * Redacted API error description for administrator troubleshooting.
	 *
	 * @var string
	 */
	private string $request_error = '';

	/**
	 * Constructor.
	 *
	 * @param string $option_name Settings option name.
	 */
	public function __construct( string $option_name ) {
		$this->option_name = $option_name;
	}

	/** Get the provider slug. */
	public function get_slug(): string {
		return 'sumsub';
	}

	/** Get the provider label. */
	public function get_label(): string {
		return __( 'Sumsub', 'hdyhaus-identity-verification' );
	}

	/**
	 * Get frontend configuration without app credentials.
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_config(): array {
		return array(
			'slug'   => 'sumsub',
			'isTest' => 'test' === $this->environment()['mode'],
		);
	}

	/**
	 * Bind approval to the current credentials, environment, and level.
	 */
	public function get_configuration_hash(): string {
		return hash( 'sha256', implode( '|', $this->environment() ) );
	}

	/**
	 * Issue an access token for this registration attempt only.
	 *
	 * @param array<string, string> $context Registration attempt and registrant.
	 * @return array<string, mixed>
	 */
	public function start_verification( array $context ): array {
		$user_id = $this->external_user_id( $context );
		if ( '' === $user_id ) {
			return array( 'status' => 'invalid_attempt' );
		}
		$data = $this->request(
			'POST',
			'/resources/accessTokens/sdk',
			array(
				'userId'               => $user_id,
				'levelName'            => $this->environment()['level'],
				'ttlInSecs'            => 600,
				'applicantIdentifiers' => array( 'email' => $context['email'] ),
			)
		);
		if ( ! isset( $data['token'], $data['userId'] ) || ! is_string( $data['token'] ) || '' === $data['token'] || $user_id !== $data['userId'] ) {
			return array( 'status' => 'token_request_failed' );
		}
		return array(
			'token'  => $data['token'],
			'status' => 'ready',
		);
	}

	/**
	 * Confirm the applicant belongs to this attempt and has a completed approval.
	 *
	 * @param string               $reference Applicant ID supplied by the SDK.
	 * @param array<string, mixed> $context Registration context.
	 * @return array<string, mixed>
	 */
	public function confirm_verification( string $reference, array $context = array() ): array {
		$result = array(
			'verified'  => false,
			'status'    => 'invalid_reference',
			'reference' => $reference,
		);
		if ( 1 !== preg_match( '/^[a-f0-9]{24}$/', $reference ) ) {
			return $result;
		}
		$external_id = $this->external_user_id( $context );
		$data        = $this->request( 'GET', '/resources/applicants/' . rawurlencode( $reference ) . '/one' );
		if ( array() === $data && 'api_http_404' === $this->request_status && '' !== $external_id ) {
			// Resolve only this server-bound applicant when the SDK reference is unavailable.
			$data = $this->request( 'GET', '/resources/applicants/-;externalUserId=' . rawurlencode( $external_id ) . '/one' );
			if ( isset( $data['id'] ) && is_string( $data['id'] ) && 1 === preg_match( '/^[a-f0-9]{24}$/', $data['id'] ) ) {
				$reference           = $data['id'];
				$result['reference'] = $reference;
			}
		}

		if ( array() === $data ) {
			$result['status']     = $this->request_status;
			$result['diagnostic'] = 'Applicant suffix: ' . substr( $reference, -4 ) . '; expected external suffix: ' . substr( $external_id, -4 ) . '. ' . $this->request_error;
			return $result;
		}
		foreach ( array(
			'applicant_id_mismatch' => ( $data['id'] ?? '' ) === $reference,
			'applicant_mismatch'    => '' !== $external_id && ( $data['externalUserId'] ?? '' ) === $external_id,
			'applicant_type'        => ( $data['type'] ?? '' ) === 'individual',
			'applicant_inactive'    => empty( $data['deleted'] ) && empty( $data['deactivated'] ),
		) as $status => $matches ) {
			if ( ! $matches ) {
				$result['status'] = $status;
				return $result;
			}
		}
		$result['status'] = 'email_mismatch';
		if ( ! isset( $data['email'] ) || ! is_string( $data['email'] ) || strtolower( $data['email'] ) !== strtolower( $context['email'] ?? '' ) ) {
			return $result;
		}
		$review           = isset( $data['review'] ) && is_array( $data['review'] ) ? $data['review'] : array();
		$result['status'] = 'level_mismatch';
		if ( ( $review['levelName'] ?? '' ) !== $this->environment()['level'] ) {
			return $result;
		}
		// Read the authoritative current decision separately from applicant metadata.
		$review           = $this->request( 'GET', '/resources/applicants/' . rawurlencode( $reference ) . '/status' );
		$result['status'] = 'review_unavailable';
		if ( ! isset( $review['reviewStatus'] ) || ( $review['levelName'] ?? '' ) !== $this->environment()['level'] ) {
			return $result;
		}
		$result['status'] = 'pending';
		if ( 'completed' !== ( $review['reviewStatus'] ?? '' ) ) {
			return $result;
		}
		$result['status'] = 'not_verified';
		if ( 'GREEN' !== ( $review['reviewResult']['reviewAnswer'] ?? '' ) ) {
			return $result;
		}
		$result['status'] = 'identity_missing';
		$identity_hash    = $this->identity_hash( $data );
		if ( '' === $identity_hash ) {
			return $result;
		}
		return array(
			'verified'      => true,
			'status'        => 'verified',
			'reference'     => $reference,
			'identity_hash' => $identity_hash,
		);
	}

	/**
	 * Read only credentials for the selected environment; never fall back.
	 *
	 * @return array<string, string>
	 */
	private function environment(): array {
		$settings = get_option( $this->option_name, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$mode     = 'live' === ( $settings['sumsub_mode'] ?? '' ) ? 'live' : 'test';
		$values   = array( 'mode' => $mode );
		foreach ( array( 'app_token', 'secret_key', 'level' ) as $key ) {
			$values[ $key ] = isset( $settings[ 'sumsub_' . $mode . '_' . $key ] ) && is_string( $settings[ 'sumsub_' . $mode . '_' . $key ] ) ? $settings[ 'sumsub_' . $mode . '_' . $key ] : '';
		}
		return $values;
	}

	/**
	 * Derive an opaque applicant identifier from a server-issued attempt.
	 *
	 * @param array<string, mixed> $context Registration context.
	 */
	private function external_user_id( array $context ): string {
		foreach ( array( 'token', 'email', 'first_name', 'last_name' ) as $key ) {
			if ( ! isset( $context[ $key ] ) || ! is_string( $context[ $key ] ) || '' === $context[ $key ] ) {
				return '';
			}
		}
		return 'hdyhaus_' . hash( 'sha256', implode( '|', array( $context['token'], strtolower( $context['email'] ), $context['first_name'], $context['last_name'], $this->get_configuration_hash() ) ) );
	}

	/**
	 * Sign exact bytes and send credentials only to the fixed Sumsub API host.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path API path.
	 * @param array<string, mixed> $payload JSON request payload.
	 * @return array<string, mixed>
	 */
	private function request( string $method, string $path, array $payload = array() ): array {
		$this->request_error  = '';
		$this->request_status = 'configuration_missing';
		$environment          = $this->environment();
		if ( '' === $environment['app_token'] || '' === $environment['secret_key'] || '' === $environment['level'] ) {
			return array();
		}
		// Sumsub marks Sandbox app tokens with this prefix. Reject cross-mode keys.
		$this->request_status = 'environment_mismatch';
		if ( str_starts_with( $environment['app_token'], 'sbx:' ) !== ( 'test' === $environment['mode'] ) ) {
			return array();
		}
		$this->request_status = 'request_failed';
		$body                 = array() !== $payload ? wp_json_encode( $payload ) : '';
		if ( ! is_string( $body ) ) {
			return array();
		}
		$timestamp = (string) time();
		$response  = wp_safe_remote_request(
			'https://api.sumsub.com' . $path,
			array(
				'method'              => $method,
				'body'                => $body,
				'timeout'             => 20,
				'redirection'         => 0,
				'limit_response_size' => 1048576,
				'headers'             => array(
					'Accept'           => 'application/json',
					'Content-Type'     => 'application/json',
					'X-App-Token'      => $environment['app_token'],
					'X-App-Access-Ts'  => $timestamp,
					'X-App-Access-Sig' => hash_hmac( 'sha256', $timestamp . $method . $path . $body, $environment['secret_key'] ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->request_status = 'api_http_' . abs( (int) $code );
			$error                = json_decode( wp_remote_retrieve_body( $response ), true );
			$description          = is_array( $error ) && is_string( $error['description'] ?? null ) ? sanitize_text_field( $error['description'] ) : '';
			$description          = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|(?:prd|sbx):[^\s]+|[A-Z0-9_\-]{24,}/i', '[redacted]', $description );
			$this->request_error  = is_string( $description ) ? substr( $description, 0, 240 ) : '';

			return array();
		}
		$this->request_status = 'invalid_response';
		$data                 = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Fingerprint the verified document without persisting the source data.
	 *
	 * @param array<string, mixed> $data Applicant response.
	 */
	private function identity_hash( array $data ): string {
		$documents = $data['info']['idDocs'] ?? array();
		if ( ! is_array( $documents ) ) {
			return '';
		}
		foreach ( $documents as $document ) {
			if ( ! is_array( $document ) || ! in_array( $document['idDocType'] ?? '', array( 'PASSPORT', 'ID_CARD', 'DRIVERS' ), true ) ) {
				continue;
			}
			$parts = array( 'sumsub', $this->environment()['mode'] );
			foreach ( array( 'country', 'idDocType', 'number' ) as $key ) {
				$value   = $document[ $key ] ?? '';
				$parts[] = is_string( $value ) ? preg_replace( '/[^A-Z0-9]/', '', strtoupper( $value ) ) : '';
			}
			if ( in_array( '', $parts, true ) || in_array( null, $parts, true ) ) {
				continue;
			}
			$secret = get_option( 'trustgate_identity_secret', false );
			if ( false === $secret ) {
				try {
					$candidate = bin2hex( random_bytes( 32 ) );
				} catch ( \Exception $exception ) {
					return '';
				}
				add_option( 'trustgate_identity_secret', $candidate, '', false );
				$secret = get_option( 'trustgate_identity_secret', false );
			}
			return is_string( $secret ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $secret ) ? hash_hmac( 'sha256', implode( '|', $parts ), $secret ) : '';
		}
		return '';
	}
}
