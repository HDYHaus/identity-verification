<?php
/**
 * Main plugin coordinator.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration;

use HDYHaus\TrustGateRegistration\Admin\SettingsPage;
use HDYHaus\TrustGateRegistration\Providers\PremblyProvider;
use HDYHaus\TrustGateRegistration\Providers\ProviderRegistry;
use HDYHaus\TrustGateRegistration\Registration\RegistrationController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers TrustGate services with WordPress.
 */
final class Plugin {
	/**
	 * Settings option name.
	 */
	public const OPTION_NAME = 'trustgate_registration_settings';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$providers = apply_filters(
			'trustgate_registration_providers',
			array( new PremblyProvider( self::OPTION_NAME ) )
		);
		$providers = is_array( $providers ) ? $providers : array();
		$providers = array_values(
			array_filter(
				$providers,
				static fn( mixed $provider ): bool => $provider instanceof \HDYHaus\TrustGateRegistration\Contracts\VerificationProvider
			)
		);
		$registry  = new ProviderRegistry( $providers, 'prembly' );
		$settings  = new SettingsPage( self::OPTION_NAME, $registry );

		$settings->register();

		$saved_settings = get_option( self::OPTION_NAME, array() );
		$saved_settings = is_array( $saved_settings ) ? $saved_settings : array();
		$provider       = $registry->resolve( $saved_settings );

		if ( null !== $provider ) {
			$registration = new RegistrationController( self::OPTION_NAME, $provider );
			$registration->register();
		}
	}
}
