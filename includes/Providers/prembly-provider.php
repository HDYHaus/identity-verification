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
	 * The first implementation intentionally fails closed until the exact
	 * Prembly status endpoint payload is implemented with account credentials.
	 *
	 * @param string $reference Provider reference.
	 * @return array{verified: bool, status: string, reference: string, raw?: array<string, mixed>}
	 */
	public function confirm_verification( string $reference ): array {
		return array(
			'verified'  => false,
			'status'    => 'not_implemented',
			'reference' => sanitize_text_field( $reference ),
		);
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
}
