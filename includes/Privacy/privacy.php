<?php
/**
 * WordPress privacy tools integration.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Privacy;

use HDYHaus\TrustGateRegistration\Registration\RegistrationController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers privacy policy guidance and personal data handlers.
 */
final class Privacy {
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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_action( 'delete_user', array( $this, 'retain_identity_after_user_deletion' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Add suggested text to the WordPress privacy policy guide.
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$settings    = get_option( $this->option_name, array() );
		$settings    = is_array( $settings ) ? $settings : array();
		$consent     = isset( $settings['consent_text'] ) ? sanitize_textarea_field( (string) $settings['consent_text'] ) : '';
		$consent     = '' !== $consent ? $consent : RegistrationController::get_default_consent_text();
		$privacy_url = array_key_exists( 'provider_privacy_url', $settings ) ? esc_url_raw( (string) $settings['provider_privacy_url'] ) : RegistrationController::DEFAULT_PROVIDER_PRIVACY_URL;
		$terms_url   = array_key_exists( 'provider_terms_url', $settings ) ? esc_url_raw( (string) $settings['provider_terms_url'] ) : RegistrationController::DEFAULT_PROVIDER_TERMS_URL;

		$content  = '<p>' . esc_html__( 'When identity verification is enabled, the registrant\'s name and email address are sent to Prembly when the registrant starts verification. Prembly may then collect and process identity document images, selfies, biometric information, device information, and IP-derived location information to perform the checks configured by the site owner.', 'hdyhaus-identity-verification' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The registration form requires agreement to this statement:', 'hdyhaus-identity-verification' ) . ' &ldquo;' . esc_html( $consent ) . '&rdquo;</p>';
		$content .= '<p>' . esc_html__( 'This site stores whether verification succeeded, the normalized verification status, the verification provider, the provider session reference, one-way reference and identity hashes, the verification time, and the consent time, wording, and version. The one-way hashes may be retained after privacy erasure or account deletion to prevent reuse of a completed verification or identity. The site owner determines how long other information is retained.', 'hdyhaus-identity-verification' ) . '</p>';

		$links = array();

		if ( '' !== $privacy_url ) {
			$links[] = '<a href="' . esc_url( $privacy_url ) . '">' . esc_html__( 'Verification Provider Privacy Policy', 'hdyhaus-identity-verification' ) . '</a>';
		}

		if ( '' !== $terms_url ) {
			$links[] = '<a href="' . esc_url( $terms_url ) . '">' . esc_html__( 'Verification Provider Terms / Consent', 'hdyhaus-identity-verification' ) . '</a>';
		}

		if ( ! empty( $links ) ) {
			$content .= '<p>' . implode( ' | ', $links ) . '</p>';
		}

		wp_add_privacy_policy_content( __( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ), wp_kses_post( wpautop( $content, false ) ) );
	}

	/**
	 * Register the HDYHaus Identity Verification personal data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['hdyhaus-identity-verification'] = array(
			'exporter_friendly_name' => __( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ),
			'callback'               => array( $this, 'export_user_data' ),
		);

		return $exporters;
	}

	/**
	 * Register the HDYHaus Identity Verification personal data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['hdyhaus-identity-verification'] = array(
			'eraser_friendly_name' => __( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);

		return $erasers;
	}

	/**
	 * Export HDYHaus Identity Verification metadata for a user email.
	 *
	 * @param string $email_address User email address.
	 * @return array<string, mixed>
	 */
	public function export_user_data( string $email_address ): array {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$labels = $this->get_meta_labels();
		$data   = array();

		foreach ( $labels as $key => $label ) {
			$value = get_user_meta( $user->ID, $key, true );

			if ( '' !== $value ) {
				$data[] = array(
					'name'  => $label,
					'value' => (string) $value,
				);
			}
		}

		return array(
			'data' => empty( $data ) ? array() : array(
				array(
					'group_id'    => 'hdyhaus-identity-verification',
					'group_label' => __( 'Identity Verification', 'hdyhaus-identity-verification' ),
					'item_id'     => 'hdyhaus-identity-verification-' . $user->ID,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	/**
	 * Erase HDYHaus Identity Verification metadata for a user email.
	 *
	 * @param string $email_address User email address.
	 * @return array<string, mixed>
	 */
	public function erase_user_data( string $email_address ): array {
		$user           = get_user_by( 'email', $email_address );
		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		if ( $user ) {
			$keys = array_diff( array_keys( $this->get_meta_labels() ), array( 'trustgate_reference_hash', 'trustgate_identity_hash' ) );

			foreach ( $keys as $key ) {
				$items_removed = delete_user_meta( $user->ID, $key ) || $items_removed;
			}

			$items_retained = '' !== get_user_meta( $user->ID, 'trustgate_reference_hash', true );

			if ( $items_retained ) {
				$messages[] = __( 'A one-way verification reference hash was retained to prevent a completed verification from being reused.', 'hdyhaus-identity-verification' );
			}

			$identity_hash = (string) get_user_meta( $user->ID, 'trustgate_identity_hash', true );

			if ( '' !== $identity_hash ) {
				$items_retained = true;
				$messages[]     = __( 'A one-way identity hash was retained to prevent the same verified identity from creating another account.', 'hdyhaus-identity-verification' );
				update_option( RegistrationController::IDENTITY_OPTION_PREFIX . $identity_hash, 'retained', false );
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Remove the user link while retaining identity uniqueness after deletion.
	 *
	 * @param int $user_id User being deleted.
	 */
	public function retain_identity_after_user_deletion( int $user_id ): void {
		$identity_hash = (string) get_user_meta( $user_id, 'trustgate_identity_hash', true );

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $identity_hash ) ) {
			return;
		}

		update_option( RegistrationController::IDENTITY_OPTION_PREFIX . $identity_hash, 'retained', false );
	}

	/**
	 * Get stored metadata labels.
	 *
	 * @return array<string, string>
	 */
	private function get_meta_labels(): array {
		return array(
			'trustgate_verified'            => __( 'Verification successful', 'hdyhaus-identity-verification' ),
			'trustgate_verification_status' => __( 'Verification status', 'hdyhaus-identity-verification' ),
			'trustgate_verified_at'         => __( 'Verification time', 'hdyhaus-identity-verification' ),
			'trustgate_provider'            => __( 'Verification provider', 'hdyhaus-identity-verification' ),
			'trustgate_reference'           => __( 'Verification reference', 'hdyhaus-identity-verification' ),
			'trustgate_reference_hash'      => __( 'Verification reference hash', 'hdyhaus-identity-verification' ),
			'trustgate_identity_hash'       => __( 'Identity uniqueness hash', 'hdyhaus-identity-verification' ),
			'trustgate_consent_at'          => __( 'Verification consent time', 'hdyhaus-identity-verification' ),
			'trustgate_consent_text'        => __( 'Verification consent wording', 'hdyhaus-identity-verification' ),
			'trustgate_consent_version'     => __( 'Verification consent version', 'hdyhaus-identity-verification' ),
		);
	}
}
