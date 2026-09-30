<?php
/**
 * Lightweight WordPress privacy integration tests.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

/** @var array<string, array<int, mixed>> */
$trustgate_test_hooks = array();

/** @var array<string, string> */
$trustgate_test_user_meta = array(
	'trustgate_verified'            => '1',
	'trustgate_verification_status' => 'verified',
	'trustgate_verified_at'         => '2026-09-30 12:00:00',
	'trustgate_provider'            => 'prembly',
	'trustgate_reference'           => 'sdk_session_123',
	'trustgate_reference_hash'      => 'one-way-hash',
	'trustgate_consent_at'          => '2026-09-30 11:59:00',
	'trustgate_consent_text'        => 'I consent to identity verification.',
	'trustgate_consent_version'     => '2026-09-30',
);

function __( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function esc_html__( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function wp_kses_post( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function wpautop( string $text, bool $br = true ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $br );
	return $text;
}

function add_action( string $hook, callable $callback ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_hooks;
	$trustgate_test_hooks[ $hook ] = $callback;
}

function add_filter( string $hook, callable $callback ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_hooks;
	$trustgate_test_hooks[ $hook ] = $callback;
}

function get_user_by( string $field, string $value ): object|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	if ( 'email' === $field && 'person@example.com' === $value ) {
		return (object) array( 'ID' => 42 );
	}

	return false;
}

function get_user_meta( int $user_id, string $key, bool $single = false ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $user_id, $single );
	global $trustgate_test_user_meta;

	return $trustgate_test_user_meta[ $key ] ?? '';
}

function delete_user_meta( int $user_id, string $key ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $user_id );
	global $trustgate_test_user_meta;

	if ( ! array_key_exists( $key, $trustgate_test_user_meta ) ) {
		return false;
	}

	unset( $trustgate_test_user_meta[ $key ] );
	return true;
}

require_once dirname( __DIR__ ) . '/includes/Privacy/privacy.php';

use HDYHaus\TrustGateRegistration\Privacy\Privacy;

/**
 * Fail the test run when an assertion does not match.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual Actual value.
 * @param string $message Assertion description.
 */
function trustgate_privacy_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, sprintf( "FAIL: %s\nExpected: %s\nActual: %s\n", $message, var_export( $expected, true ), var_export( $actual, true ) ) );
	exit( 1 );
}

$privacy = new Privacy();
$privacy->register();

trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['admin_init'] ), 'Privacy policy guidance is registered.' );
trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['wp_privacy_personal_data_exporters'] ), 'The exporter is registered.' );
trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['wp_privacy_personal_data_erasers'] ), 'The eraser is registered.' );

$export = $privacy->export_user_data( 'person@example.com' );
trustgate_privacy_assert_same( true, $export['done'], 'The exporter completes in one page.' );
trustgate_privacy_assert_same( 'trustgate-registration', $export['data'][0]['group_id'], 'The export uses the TrustGate group.' );
trustgate_privacy_assert_same( 9, count( $export['data'][0]['data'] ), 'Every stored verification field is exported.' );

$erasure = $privacy->erase_user_data( 'person@example.com' );
trustgate_privacy_assert_same( true, $erasure['items_removed'], 'Erasable verification fields are removed.' );
trustgate_privacy_assert_same( true, $erasure['items_retained'], 'The replay-prevention hash is retained.' );
trustgate_privacy_assert_same( 1, count( $erasure['messages'] ), 'The retained hash is disclosed to the requester.' );
trustgate_privacy_assert_same( array( 'trustgate_reference_hash' => 'one-way-hash' ), $trustgate_test_user_meta, 'Only the one-way hash remains.' );

$export_after_erasure = $privacy->export_user_data( 'person@example.com' );
trustgate_privacy_assert_same( 1, count( $export_after_erasure['data'][0]['data'] ), 'The retained hash remains exportable.' );

$missing_user = $privacy->export_user_data( 'missing@example.com' );
trustgate_privacy_assert_same( array(), $missing_user['data'], 'An unknown email exports no data.' );

fwrite( STDOUT, "Privacy integration tests passed.\n" );
