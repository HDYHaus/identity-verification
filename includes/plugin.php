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
		$provider     = new PremblyProvider( self::OPTION_NAME );
		$settings     = new SettingsPage( self::OPTION_NAME, $provider );
		$registration = new RegistrationController( self::OPTION_NAME, $provider );

		$settings->register();
		$registration->register();
	}
}
