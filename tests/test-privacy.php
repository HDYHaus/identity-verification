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
	'trustgate_identity_hash'       => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
	'trustgate_consent_at'          => '2026-09-30 11:59:00',
	'trustgate_consent_text'        => 'I consent to identity verification.',
	'trustgate_consent_version'     => '2026-09-30',
);

/** @var array<string, string> */
$trustgate_test_settings = array(
	'consent_text'         => 'I agree to the configured identity check.',
	'provider_privacy_url' => 'https://provider.example/privacy',
	'provider_terms_url'   => '',
);

$trustgate_test_policy_content = '';

/** @var array<string, mixed> */
$trustgate_test_options = array();

function __( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function esc_html__( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function esc_html( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( string $url ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $url;
}

function esc_url_raw( string $url ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return false !== filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
}

function sanitize_textarea_field( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( strip_tags( $text ) );
}

function get_option( string $option_name, mixed $default = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $option_name );
	global $trustgate_test_settings;

	return array() !== $trustgate_test_settings ? $trustgate_test_settings : $default;
}

function wp_kses_post( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function wpautop( string $text, bool $br = true ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $br );
	return $text;
}

function wp_add_privacy_policy_content( string $plugin_name, string $policy_text ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $plugin_name );
	global $trustgate_test_policy_content;
	$trustgate_test_policy_content = $policy_text;
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

function update_option( string $option_name, mixed $value, bool $autoload = true ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $autoload );
	global $trustgate_test_options;
	$trustgate_test_options[ $option_name ] = $value;
	return true;
}

require_once dirname( __DIR__ ) . '/includes/Registration/registration-controller.php';
require_once dirname( __DIR__ ) . '/includes/Privacy/privacy.php';

use HDYHaus\TrustGateRegistration\Privacy\Privacy;
use HDYHaus\TrustGateRegistration\Registration\RegistrationController;

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

$privacy = new Privacy( 'trustgate_settings' );
$privacy->register();
$privacy->add_policy_content();

trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['admin_init'] ), 'Privacy policy guidance is registered.' );
trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['delete_user'] ), 'Identity retention is registered for account deletion.' );
trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['wp_privacy_personal_data_exporters'] ), 'The exporter is registered.' );
trustgate_privacy_assert_same( true, isset( $trustgate_test_hooks['wp_privacy_personal_data_erasers'] ), 'The eraser is registered.' );
trustgate_privacy_assert_same( true, str_contains( $trustgate_test_policy_content, 'I agree to the configured identity check.' ), 'Privacy guidance includes the configured consent wording.' );
trustgate_privacy_assert_same( true, str_contains( $trustgate_test_policy_content, 'https://provider.example/privacy' ), 'Privacy guidance includes the configured provider policy.' );
trustgate_privacy_assert_same( false, str_contains( $trustgate_test_policy_content, 'Terms / Consent' ), 'Privacy guidance omits an empty provider link.' );

$export = $privacy->export_user_data( 'person@example.com' );
trustgate_privacy_assert_same( true, $export['done'], 'The exporter completes in one page.' );
trustgate_privacy_assert_same( 'trustgate-registration', $export['data'][0]['group_id'], 'The export uses the TrustGate group.' );
trustgate_privacy_assert_same( 10, count( $export['data'][0]['data'] ), 'Every stored verification field is exported.' );

$erasure = $privacy->erase_user_data( 'person@example.com' );
trustgate_privacy_assert_same( true, $erasure['items_removed'], 'Erasable verification fields are removed.' );
trustgate_privacy_assert_same( true, $erasure['items_retained'], 'The replay-prevention hash is retained.' );
trustgate_privacy_assert_same( 2, count( $erasure['messages'] ), 'Both retained hashes are disclosed to the requester.' );
trustgate_privacy_assert_same(
	array(
		'trustgate_reference_hash' => 'one-way-hash',
		'trustgate_identity_hash'  => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
	),
	$trustgate_test_user_meta,
	'Only one-way hashes remain.'
);
trustgate_privacy_assert_same(
	'retained',
	$trustgate_test_options[ RegistrationController::IDENTITY_OPTION_PREFIX . $trustgate_test_user_meta['trustgate_identity_hash'] ] ?? '',
	'Privacy erasure removes the user link from the permanent identity record.'
);

$export_after_erasure = $privacy->export_user_data( 'person@example.com' );
trustgate_privacy_assert_same( 2, count( $export_after_erasure['data'][0]['data'] ), 'The retained hashes remain exportable.' );

$trustgate_test_user_meta['trustgate_identity_hash'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$privacy->retain_identity_after_user_deletion( 42 );
trustgate_privacy_assert_same(
	'retained',
	$trustgate_test_options[ RegistrationController::IDENTITY_OPTION_PREFIX . $trustgate_test_user_meta['trustgate_identity_hash'] ] ?? '',
	'Account deletion retains an anonymous identity uniqueness record.'
);

$missing_user = $privacy->export_user_data( 'missing@example.com' );
trustgate_privacy_assert_same( array(), $missing_user['data'], 'An unknown email exports no data.' );

fwrite( STDOUT, "Privacy integration tests passed.\n" );
