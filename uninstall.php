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
delete_option( 'trustgate_attempt_schema_version' );
wp_clear_scheduled_hook( 'trustgate_prune_verification_attempts' );

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

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'hdyhaus_verification_attempts' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove the plugin-owned attempt table on uninstall.
