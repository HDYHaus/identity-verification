<?php
/**
 * Uninstall cleanup.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'trustgate_registration_settings' );
