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
define( 'ARRAY_A', 'ARRAY_A' );

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

final class TrustGateTestDb {
	public string $prefix = 'wp_';
	public array $rows = array();
	public string $last_query = '';
	private string $prepared_query = '';

	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_query = $query;
		foreach ( $args as $value ) {
			$this->prepared_query = preg_replace_callback(
				'/%[sdi]/',
				static function ( array $matches ) use ( $value ): string {
					if ( 'i' === $matches[0][1] ) {
						return '`' . str_replace( '`', '``', (string) $value ) . '`';
					}

					return is_int( $value ) ? (string) $value : "'" . addslashes( (string) $value ) . "'";
				},
				$this->prepared_query,
				1
			) ?? $this->prepared_query;
		}
		return $this->prepared_query;
	}

	public function get_row( string $query, mixed $output = null ): array|null {
		unset( $output );
		if ( preg_match( "/attempt_hash = '([a-f0-9]+)'/", $query, $matches ) ) {
			return $this->rows[ $matches[1] ] ?? null;
		}
		if ( preg_match( "/resume_token_hash = '([a-f0-9]+)'/", $query, $matches ) ) {
			foreach ( $this->rows as $row ) {
				if ( ( $row['resume_token_hash'] ?? '' ) === $matches[1] ) {
					return $row;
				}
			}
		}
		return null;
	}

	public function get_results( string $query, mixed $output = null ): array {
		unset( $output );
		$this->last_query = $query;
		return array();
	}

	public function get_var( string $query ): int {
		$this->last_query = $query;
		return 0;
	}

	public function insert( string $table, array $data ): int|false {
		unset( $table );
		$this->rows[ $data['attempt_hash'] ] = $data;
		return 1;
	}

	public function update( string $table, array $data, array $where ): int|false {
		unset( $table );
		$key = $where['attempt_hash'] ?? '';
		if ( '' === $key && isset( $where['id'] ) ) {
			foreach ( $this->rows as $row_key => $row ) {
				if ( (int) ( $row['id'] ?? 1 ) === (int) $where['id'] ) {
					$key = $row_key;
					break;
				}
			}
		}
		if ( ! isset( $this->rows[ $key ] ) ) {
			return 0;
		}
		$this->rows[ $key ] = array_merge( $this->rows[ $key ], $data );
		return 1;
	}
}

$wpdb = new TrustGateTestDb();

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

function sanitize_user( string $value, bool $strict = false ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $strict );
	return preg_replace( '/[^a-zA-Z0-9_.@-]/', '', $value ) ?? '';
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

function wp_generate_password( int $length, bool $special_chars = true, bool $extra_special_chars = false ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $special_chars, $extra_special_chars );
	return str_repeat( 'x', $length );
}

function add_query_arg( string $key, string $value, string $url ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function wp_registration_url(): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return 'https://site.example/wp-login.php?action=register';
}

function wp_mail( string $to, string $subject, string $message ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $to, $subject, $message );
	return true;
}

function human_time_diff( int $from, int $to ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return (string) ( $to - $from ) . ' seconds';
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
require_once dirname( __DIR__ ) . '/includes/Registration/verification-attempt-store.php';
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
		return array( 'isTest' => true );
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

$attempt_store = new \HDYHaus\TrustGateRegistration\Registration\VerificationAttemptStore();
$attempt_store->get_attempts( 'successful', 2, 25 );
trustgate_registration_assert_same( true, str_contains( $wpdb->last_query, "status = 'account_created'" ), 'The successful filter includes only created accounts.' );
trustgate_registration_assert_same( true, str_contains( $wpdb->last_query, 'LIMIT 25 OFFSET 25' ), 'Attempt pagination applies the requested page and page size.' );
$attempt_store->count_attempts( 'failed' );
trustgate_registration_assert_same( true, str_contains( $wpdb->last_query, "'verification_failed', 'verification_error', 'registration_failed'" ), 'The failed filter includes provider and account-creation failures.' );

$confirmation_post = $_POST;

$started_token = 'started-attempt-token';
$started_key   = 'trustgate_attempt_' . hash( 'sha256', $started_token );
$trustgate_test_transients[ $started_key ] = array( 'status' => 'issued' );
$_POST = array(
	'nonce'     => 'valid-nonce',
	'token'     => $started_token,
	'status'    => 'verification_started',
	'email'     => 'started@example.com',
	'username'  => 'sandbox-started',
	'firstName' => 'Sandbox',
	'lastName'  => 'Started',
	'consent'   => '1',
);

try {
	$controller->log_verification_attempt();
} catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'verification_started', $response->payload['status'] ?? '', 'A verification start is accepted and logged.' );
}

$started_record = $wpdb->rows[ hash( 'sha256', $started_token ) ] ?? array();
trustgate_registration_assert_same( 'verification_started', $started_record['status'] ?? '', 'An unfinished provider session is durably recorded.' );
trustgate_registration_assert_same( 'sandbox', $started_record['environment'] ?? '', 'The start record retains its sandbox environment.' );
trustgate_registration_assert_same( 'started@example.com', $started_record['email'] ?? '', 'The start record retains the email needed to identify the registration.' );
trustgate_registration_assert_same( '', $started_record['identity_hash'] ?? '', 'A start record does not claim an unverified identity.' );

$_POST['status']  = 'verification_error';
$_POST['failure'] = 'E00';
try {
	$controller->log_verification_attempt();
} catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'verification_error', $response->payload['status'] ?? '', 'A provider-reported error updates the attempt state.' );
}
trustgate_registration_assert_same( 'verification_error', $wpdb->rows[ hash( 'sha256', $started_token ) ]['status'] ?? '', 'The provider-reported failure remains available for admin review.' );

$_POST = $confirmation_post;
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
$persistent_attempt = $wpdb->rows[ hash( 'sha256', $token ) ] ?? array();
trustgate_registration_assert_same( 'verified_pending_registration', $persistent_attempt['status'] ?? '', 'The verified registration attempt is durably recorded.' );
trustgate_registration_assert_same( 'person@example.com', $persistent_attempt['email'] ?? '', 'The pending attempt retains the email needed to resume registration.' );
trustgate_registration_assert_same( hash( 'sha256', str_repeat( 'x', 48 ) ), $persistent_attempt['resume_token_hash'] ?? '', 'Only a hash of the emailed resume token is stored.' );
unset( $trustgate_test_transients[ $attempt_key ] );

$_POST = array(
	'trustgate_first_name'    => 'Maria',
	'trustgate_registration_form_nonce' => 'valid-form-nonce',
	'trustgate_consent'       => '1',
	'trustgate_last_name'     => 'Job',
	'trustgate_reference'     => $reference,
	'trustgate_attempt_token' => str_repeat( 'x', 48 ),
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
trustgate_registration_assert_same( $persistent_attempt['consent_at'], $trustgate_test_user_meta['trustgate_consent_at'] ?? '', 'The accepted consent time is stored for the user.' );
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
trustgate_registration_assert_same( 'account_created', $wpdb->rows[ hash( 'sha256', $token ) ]['status'] ?? '', 'Completing registration from the emailed resume link links the persistent attempt to the account.' );

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

$privacy_method = new ReflectionMethod( RegistrationController::class, 'get_provider_privacy_url' );
$provider_terms_method = new ReflectionMethod( RegistrationController::class, 'get_provider_terms_url' );
$trustgate_test_settings['provider_privacy_url'] = 'https://prembly.com/Policy';
$trustgate_test_settings['provider_terms_url'] = 'https://prembly.com/terms';
trustgate_registration_assert_same( 'https://prembly.com/data-protection/', $privacy_method->invoke( $controller ), 'Saved legacy policy URLs resolve to the Data Protection Policy.' );
trustgate_registration_assert_same( '', $provider_terms_method->invoke( $controller ), 'The obsolete saved default terms link is omitted.' );
$trustgate_test_settings['provider_privacy_url'] = 'https://provider.example/privacy';
$trustgate_test_settings['provider_terms_url'] = 'https://provider.example/consent';
trustgate_registration_assert_same( 'https://provider.example/privacy', $privacy_method->invoke( $controller ), 'Custom privacy URLs are retained.' );
trustgate_registration_assert_same( 'https://provider.example/consent', $provider_terms_method->invoke( $controller ), 'Custom terms URLs are retained.' );
$trustgate_test_settings['provider_privacy_url'] = '';
trustgate_registration_assert_same( '', $privacy_method->invoke( $controller ), 'An intentionally empty privacy URL remains empty.' );
unset( $trustgate_test_settings['provider_privacy_url'], $trustgate_test_settings['provider_terms_url'] );
trustgate_registration_assert_same( 'https://prembly.com/data-protection/', $privacy_method->invoke( $controller ), 'Older settings without policy fields use the corrected default.' );
trustgate_registration_assert_same( '', $provider_terms_method->invoke( $controller ), 'Older settings without terms fields do not show an obsolete link.' );

fwrite( STDOUT, "Registration metadata tests passed.\n" );
