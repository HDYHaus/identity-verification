<?php
/**
 * User verification profile tests.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

/** @var array<string, callable> */
$trustgate_test_hooks = array();

/** @var array<string, string> */
$trustgate_test_user_meta = array();

$trustgate_test_can_list_users = true;

class WP_User {
	public int $ID = 42;
}

function add_action( string $hook, callable $callback ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_hooks;
	$trustgate_test_hooks[ $hook ] = $callback;
}

function current_user_can( string $capability ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_can_list_users;
	return 'list_users' === $capability && $trustgate_test_can_list_users;
}

function get_user_meta( int $user_id, string $key, bool $single = false ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $user_id, $single );
	global $trustgate_test_user_meta;
	return $trustgate_test_user_meta[ $key ] ?? '';
}

function __( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function esc_html_e( string $text ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

require_once dirname( __DIR__ ) . '/includes/Admin/user-verification-profile.php';

use HDYHaus\TrustGateRegistration\Admin\UserVerificationProfile;

function trustgate_profile_assert_contains( string $needle, string $haystack, string $message ): void {
	if ( str_contains( $haystack, $needle ) ) {
		return;
	}

	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

$profile = new UserVerificationProfile();
$profile->register();

trustgate_profile_assert_contains( 'render', $trustgate_test_hooks['show_user_profile'][1] ?? '', 'Own-profile hook is registered.' );
trustgate_profile_assert_contains( 'render', $trustgate_test_hooks['edit_user_profile'][1] ?? '', 'User-edit hook is registered.' );

$trustgate_test_user_meta = array(
	'trustgate_verification_status' => 'verified',
	'trustgate_verified_at'         => '2026-10-01T07:30:00+00:00',
	'trustgate_provider'            => 'prembly',
	'trustgate_reference'           => 'session_123',
	'trustgate_reference_hash'      => 'secret-hash',
	'trustgate_identity_hash'       => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
	'trustgate_consent_at'          => '2026-10-01T07:29:00+00:00',
	'trustgate_consent_text'        => 'I consent to identity verification.',
	'trustgate_consent_version'     => 'consent-version-id',
);

ob_start();
$profile->render( new WP_User() );
$output = (string) ob_get_clean();

trustgate_profile_assert_contains( 'HDYHaus Identity Verification', $output, 'Profile section is rendered.' );
trustgate_profile_assert_contains( 'session_123', $output, 'Provider session reference is shown.' );
trustgate_profile_assert_contains( 'I consent to identity verification.', $output, 'Consent wording is shown.' );
trustgate_profile_assert_contains( 'Identity uniqueness', $output, 'Identity uniqueness retention is shown.' );

if ( str_contains( $output, 'secret-hash' ) ) {
	fwrite( STDERR, "FAIL: Replay-prevention hash must not be exposed.\n" );
	exit( 1 );
}

if ( str_contains( $output, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ) ) {
	fwrite( STDERR, "FAIL: Identity uniqueness hash must not be exposed.\n" );
	exit( 1 );
}

$trustgate_test_user_meta = array(
	'trustgate_reference_hash' => 'secret-hash',
	'trustgate_identity_hash'  => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
);
ob_start();
$profile->render( new WP_User() );
$erased_output = (string) ob_get_clean();
trustgate_profile_assert_contains( 'Verification data erased; replay-prevention record retained.', $erased_output, 'Erased record state is shown.' );

$trustgate_test_can_list_users = false;
ob_start();
$profile->render( new WP_User() );
$restricted_output = (string) ob_get_clean();

if ( '' !== $restricted_output ) {
	fwrite( STDERR, "FAIL: Verification details must be restricted to user-list administrators.\n" );
	exit( 1 );
}

fwrite( STDOUT, "User verification profile tests passed.\n" );
