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

/** @var array<string, string> */
$trustgate_test_settings = array(
	'consent_text'         => 'I agree to identity verification for this registration.',
	'provider_privacy_url' => 'https://provider.example/privacy',
	'provider_terms_url'   => 'https://provider.example/consent',
	'site_terms_url'       => 'https://site.example/terms',
);

/** @var array<string, mixed> */
$trustgate_test_options = array();

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

function sanitize_textarea_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( strip_tags( $value ) );
}

function esc_url_raw( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return false !== filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
}

function get_privacy_policy_url(): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return 'https://site.example/privacy';
}

function get_post( int $page_id ): ?object {
	return match ( $page_id ) {
		12 => (object) array( 'post_type' => 'page', 'post_status' => 'publish' ),
		13 => (object) array( 'post_type' => 'page', 'post_status' => 'draft' ),
		default => null,
	};
}

function get_permalink( int $page_id ): string {
	return 'https://site.example/page-' . $page_id;
}

function get_option( string $option_name, mixed $default = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_options, $trustgate_test_settings;

	if ( 'trustgate_settings' === $option_name ) {
		return $trustgate_test_settings;
	}

	return $trustgate_test_options[ $option_name ] ?? $default;
}

function add_option( string $option_name, mixed $value, string $deprecated = '', bool $autoload = true ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $deprecated, $autoload );
	global $trustgate_test_options;

	if ( array_key_exists( $option_name, $trustgate_test_options ) ) {
		return false;
	}

	$trustgate_test_options[ $option_name ] = $value;
	return true;
}

function update_option( string $option_name, mixed $value, bool $autoload = true ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $autoload );
	global $trustgate_test_options;
	$trustgate_test_options[ $option_name ] = $value;
	return true;
}

function delete_option( string $option_name ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_options;

	if ( ! array_key_exists( $option_name, $trustgate_test_options ) ) {
		return false;
	}

	unset( $trustgate_test_options[ $option_name ] );
	return true;
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

function wp_verify_nonce( string $nonce, string $action ): int|false {
	return 'valid-form-nonce' === $nonce && 'trustgate_registration_form' === $action ? 1 : false;
}

final class WP_Error {
	public array $errors = array();
	public function add( string $code, string $message ): void {
		$this->errors[ $code ] = $message;
	}
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
			'verified'      => true,
			'status'        => 'verified',
			'reference'     => $reference,
			'identity_hash' => hash( 'sha256', 'identity-1' ),
			'raw'           => array( 'sensitive' => 'must not be stored' ),
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
trustgate_registration_assert_same( hash( 'sha256', 'identity-1' ), $attempt['identity_hash'] ?? '', 'The identity fingerprint is preserved in the attempt.' );
trustgate_registration_assert_same(
	hash(
		'sha256',
		implode(
			"\n",
			array(
				'prembly',
				'I agree to identity verification for this registration.',
				'https://provider.example/privacy',
				'https://provider.example/consent',
				'https://site.example/privacy',
				'https://site.example/terms',
			)
		)
	),
	$attempt['consent_version'] ?? '',
	'The accepted consent version fingerprints the complete disclosure.'
);
trustgate_registration_assert_same(
	'I agree to identity verification for this registration.',
	$attempt['consent_text'] ?? '',
	'The configured consent wording is preserved in the attempt.'
);
trustgate_registration_assert_same( false, isset( $attempt['raw'] ), 'The raw provider response is not stored in the attempt.' );

$_POST = array(
	'trustgate_first_name'    => 'Maria',
	'trustgate_registration_form_nonce' => 'valid-form-nonce',
	'trustgate_consent'       => '1',
	'trustgate_last_name'     => 'Job',
	'trustgate_reference'     => $reference,
	'trustgate_attempt_token' => $token,
);

$valid_post = $_POST;
foreach ( array( null, 'invalid', array( 'invalid' ) ) as $nonce ) {
	$_POST = $valid_post;
	if ( null === $nonce ) {
		unset( $_POST['trustgate_registration_form_nonce'] );
	} else {
		$_POST['trustgate_registration_form_nonce'] = $nonce;
	}
	$errors = $controller->validate_registration( new WP_Error(), 'person', 'person@example.com' );
	trustgate_registration_assert_same( true, isset( $errors->errors['trustgate_invalid_nonce'] ), 'Missing, invalid, and malformed nonces reject registration.' );
	$controller->store_user_meta( 42 );
	trustgate_registration_assert_same( array(), $trustgate_test_user_meta, 'Invalid nonces cannot write metadata.' );
}
$_POST = $valid_post;
trustgate_registration_assert_same( array(), $controller->validate_registration( new WP_Error(), 'person', 'person@example.com' )->errors, 'A verified public registration with a valid nonce passes.' );
$controller->store_user_meta( 42 );

trustgate_registration_assert_same( 'verified', $trustgate_test_user_meta['trustgate_verification_status'] ?? '', 'The normalized status is stored for the user.' );
trustgate_registration_assert_same( $attempt['consent_at'], $trustgate_test_user_meta['trustgate_consent_at'] ?? '', 'The accepted consent time is stored for the user.' );
trustgate_registration_assert_same( $attempt['consent_text'], $trustgate_test_user_meta['trustgate_consent_text'] ?? '', 'The accepted consent wording is stored for the user.' );
trustgate_registration_assert_same( $attempt['consent_version'], $trustgate_test_user_meta['trustgate_consent_version'] ?? '', 'The accepted consent version is stored for the user.' );
trustgate_registration_assert_same( $attempt['identity_hash'], $trustgate_test_user_meta['trustgate_identity_hash'] ?? '', 'The identity fingerprint is stored for the user.' );
trustgate_registration_assert_same( false, isset( $trustgate_test_user_meta['raw'] ), 'The raw provider response is not stored for the user.' );
trustgate_registration_assert_same( false, isset( $trustgate_test_transients[ $attempt_key ] ), 'The completed attempt is deleted after registration.' );
trustgate_registration_assert_same(
	'user:42',
	$trustgate_test_options[ RegistrationController::IDENTITY_OPTION_PREFIX . hash( 'sha256', 'identity-1' ) ] ?? '',
	'The identity fingerprint becomes a permanent uniqueness record.'
);

trustgate_registration_assert_same( true, isset( $controller->validate_registration( new WP_Error(), 'person', 'person@example.com' )->errors['trustgate_verification_required'] ), 'A consumed verification attempt cannot register again.' );
$saved_meta = $trustgate_test_user_meta;
$controller->store_user_meta( 42 );
trustgate_registration_assert_same( $saved_meta, $trustgate_test_user_meta, 'A consumed attempt cannot rewrite verification metadata.' );

$second_token       = 'second-attempt-token';
$second_attempt_key = 'trustgate_attempt_' . hash( 'sha256', $second_token );
$trustgate_test_transients[ $second_attempt_key ] = array( 'status' => 'issued' );
$_POST = array(
	'reference' => 'sdk_session_456',
	'token'     => $second_token,
	'email'     => 'other@example.com',
	'firstName' => 'Maria',
	'lastName'  => 'Job',
	'consent'   => '1',
);

try {
	$controller->confirm_verification();
} catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'identity_unavailable', $response->payload['status'] ?? '', 'The same identity cannot verify a second account.' );
}

$terms_method   = new ReflectionMethod( RegistrationController::class, 'get_site_terms_url' );
$version_method = new ReflectionMethod( RegistrationController::class, 'get_consent_version' );
$custom_version = $version_method->invoke( $controller );
$trustgate_test_settings['site_terms_page_id'] = '12';
trustgate_registration_assert_same( 'https://site.example/page-12', $terms_method->invoke( $controller ), 'The published page takes precedence over the custom URL.' );
trustgate_registration_assert_same( false, $custom_version === $version_method->invoke( $controller ), 'Changing the website terms changes the consent fingerprint.' );
$trustgate_test_settings['site_terms_page_id'] = '13';
trustgate_registration_assert_same( 'https://site.example/terms', $terms_method->invoke( $controller ), 'An unpublished page is not linked; the custom URL is used.' );
$trustgate_test_settings['site_terms_page_id'] = '0';
$trustgate_test_settings['site_terms_url'] = '';
trustgate_registration_assert_same( '', $terms_method->invoke( $controller ), 'No website terms link is supplied when neither source is configured.' );

fwrite( STDOUT, "Registration metadata tests passed.\n" );
