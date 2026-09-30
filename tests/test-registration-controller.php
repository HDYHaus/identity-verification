<?php
/**
 * Lightweight registration metadata tests.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

/** @var array<string, mixed> */
$trustgate_test_transients = array();

/** @var array<string, string> */
$trustgate_test_user_meta = array();

final class TrustGateJsonResponse extends RuntimeException {
	/**
	 * Response payload.
	 *
	 * @var array<string, mixed>
	 */
	public array $payload;

	/**
	 * @param array<string, mixed> $payload Response payload.
	 */
	public function __construct( array $payload ) {
		parent::__construct( 'JSON response sent.' );
		$this->payload = $payload;
	}
}

function __( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function wp_unslash( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $value;
}

function sanitize_text_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( $value );
}

function sanitize_email( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return strtolower( trim( $value ) );
}

function sanitize_key( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
}

function is_email( string $email ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function check_ajax_referer( string $action, string $query_arg ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $action, $query_arg );
}

/**
 * @param array<string, mixed> $data Response data.
 */
function wp_send_json_success( array $data ): never { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	throw new TrustGateJsonResponse( $data );
}

/**
 * @param array<string, mixed> $data Response data.
 */
function wp_send_json_error( array $data, int $status_code = 400 ): never { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $status_code );
	throw new TrustGateJsonResponse( $data );
}

function get_transient( string $key ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_transients;
	return $trustgate_test_transients[ $key ] ?? false;
}

function set_transient( string $key, mixed $value, int $expiration ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $expiration );
	global $trustgate_test_transients;
	$trustgate_test_transients[ $key ] = $value;
	return true;
}

function delete_transient( string $key ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_transients;
	unset( $trustgate_test_transients[ $key ] );
	return true;
}

/**
 * @param array<string, mixed> $args User query arguments.
 * @return array<int, int>
 */
function get_users( array $args ): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $args );
	return array();
}

function get_userdata( int $user_id ): object|false { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return 42 === $user_id ? (object) array( 'user_email' => 'person@example.com' ) : false;
}

function update_user_meta( int $user_id, string $key, mixed $value ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $user_id );
	global $trustgate_test_user_meta;
	$trustgate_test_user_meta[ $key ] = (string) $value;
	return true;
}

require_once dirname( __DIR__ ) . '/includes/Contracts/verification-provider.php';
require_once dirname( __DIR__ ) . '/includes/Registration/registration-controller.php';

use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;
use HDYHaus\TrustGateRegistration\Registration\RegistrationController;

final class TrustGateTestProvider implements VerificationProvider {
	public function get_slug(): string {
		return 'prembly';
	}

	public function get_label(): string {
		return 'Prembly';
	}

	public function get_public_config(): array {
		return array();
	}

	public function confirm_verification( string $reference, array $context = array() ): array {
		unset( $context );
		return array(
			'verified'  => true,
			'status'    => 'verified',
			'reference' => $reference,
			'raw'       => array( 'sensitive' => 'must not be stored' ),
		);
	}
}

/**
 * Fail the test run when an assertion does not match.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual Actual value.
 * @param string $message Assertion description.
 */
function trustgate_registration_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, sprintf( "FAIL: %s\nExpected: %s\nActual: %s\n", $message, var_export( $expected, true ), var_export( $actual, true ) ) );
	exit( 1 );
}

$token       = 'attempt-token';
$reference   = 'sdk_session_123';
$attempt_key = 'trustgate_attempt_' . hash( 'sha256', $token );

$trustgate_test_transients[ $attempt_key ] = array( 'status' => 'issued' );

$_POST = array(
	'reference' => $reference,
	'token'     => $token,
	'email'     => 'person@example.com',
	'firstName' => 'Maria',
	'lastName'  => 'Job',
	'consent'   => '1',
);

$controller = new RegistrationController( 'trustgate_settings', new TrustGateTestProvider() );

try {
	$controller->confirm_verification();
} catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'verified', $response->payload['status'] ?? '', 'The normalized status is returned to the browser.' );
}

$attempt = $trustgate_test_transients[ $attempt_key ];
trustgate_registration_assert_same( 'verified', $attempt['verification_status'] ?? '', 'The normalized status is preserved in the attempt.' );
trustgate_registration_assert_same( '2026-09-30', $attempt['consent_version'] ?? '', 'The accepted consent version is preserved in the attempt.' );
trustgate_registration_assert_same(
	'I consent to Prembly processing my name, email, identity document, and biometric information to verify my identity.',
	$attempt['consent_text'] ?? '',
	'The accepted consent wording is preserved in the attempt.'
);
trustgate_registration_assert_same( false, isset( $attempt['raw'] ), 'The raw provider response is not stored in the attempt.' );

$_POST = array(
	'trustgate_first_name'    => 'Maria',
	'trustgate_last_name'     => 'Job',
	'trustgate_reference'     => $reference,
	'trustgate_attempt_token' => $token,
);

$controller->store_user_meta( 42 );

trustgate_registration_assert_same( 'verified', $trustgate_test_user_meta['trustgate_verification_status'] ?? '', 'The normalized status is stored for the user.' );
trustgate_registration_assert_same( $attempt['consent_at'], $trustgate_test_user_meta['trustgate_consent_at'] ?? '', 'The accepted consent time is stored for the user.' );
trustgate_registration_assert_same( $attempt['consent_text'], $trustgate_test_user_meta['trustgate_consent_text'] ?? '', 'The accepted consent wording is stored for the user.' );
trustgate_registration_assert_same( $attempt['consent_version'], $trustgate_test_user_meta['trustgate_consent_version'] ?? '', 'The accepted consent version is stored for the user.' );
trustgate_registration_assert_same( false, isset( $trustgate_test_user_meta['raw'] ), 'The raw provider response is not stored for the user.' );
trustgate_registration_assert_same( false, isset( $trustgate_test_transients[ $attempt_key ] ), 'The completed attempt is deleted after registration.' );

fwrite( STDOUT, "Registration metadata tests passed.\n" );
