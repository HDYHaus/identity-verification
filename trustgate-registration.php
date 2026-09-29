<?php
/**
 * Plugin Name: TrustGate Registration
 * Plugin URI: https://github.com/HDYHaus/trustgate-registration
 * Description: Verify people during WordPress registration with pluggable identity verification providers. Prembly support is the first provider.
 * Version: 0.1.6
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: HDYHaus
 * Author URI: https://github.com/HDYHaus
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trustgate-registration
 * Domain Path: /languages
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRUSTGATE_REGISTRATION_VERSION', '0.1.6' );
define( 'TRUSTGATE_REGISTRATION_FILE', __FILE__ );
define( 'TRUSTGATE_REGISTRATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'TRUSTGATE_REGISTRATION_URL', plugin_dir_url( __FILE__ ) );

require_once TRUSTGATE_REGISTRATION_PATH . 'includes/Contracts/verification-provider.php';
require_once TRUSTGATE_REGISTRATION_PATH . 'includes/Providers/prembly-provider.php';
require_once TRUSTGATE_REGISTRATION_PATH . 'includes/Providers/provider-registry.php';
require_once TRUSTGATE_REGISTRATION_PATH . 'includes/Admin/settings-page.php';
require_once TRUSTGATE_REGISTRATION_PATH . 'includes/Registration/registration-controller.php';
require_once TRUSTGATE_REGISTRATION_PATH . 'includes/plugin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain(
			'trustgate-registration',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		$plugin = new HDYHaus\TrustGateRegistration\Plugin();
		$plugin->register();
	}
);
