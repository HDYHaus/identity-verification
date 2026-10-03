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

global $wpdb;

$trustgate_identity_options = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'trustgate_identity_' ) . '%'
	)
);

foreach ( $trustgate_identity_options as $trustgate_identity_option ) {
	delete_option( (string) $trustgate_identity_option );
}
