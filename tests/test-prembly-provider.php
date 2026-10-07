<?php
/**
 * Lightweight Prembly response normalization tests.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function __( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function sanitize_text_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( $value );
}

function sanitize_email( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

$trustgate_test_secret = false;
$trustgate_test_secret_mode = 'normal';
$trustgate_test_secret_autoload = null;

/** @var array<string, mixed> */
$trustgate_test_settings = array();

/** @var array<string, mixed> */
$trustgate_test_response = array();

/** @var array{url: string, args: array<string, mixed>} */
$trustgate_test_request = array(
	'url'  => '',
	'args' => array(),
);

function get_option( string $option_name, mixed $default = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_secret;
	if ( 'trustgate_identity_secret' === $option_name ) {
		return $trustgate_test_secret;
	}

	global $trustgate_test_settings;

	return array() !== $trustgate_test_settings ? $trustgate_test_settings : $default;
}

function add_option( string $name, mixed $value, string $deprecated = '', bool $autoload = true ): bool {
	global $trustgate_test_secret, $trustgate_test_secret_mode, $trustgate_test_secret_autoload;
	$trustgate_test_secret_autoload = $autoload;
	if ( 'failure' === $trustgate_test_secret_mode ) {
		return false;
	}
	if ( 'race' === $trustgate_test_secret_mode ) {
		$trustgate_test_secret = str_repeat( 'b', 64 );
		return false;
	}
	if ( false !== $trustgate_test_secret ) {
		return false;
	}
	$trustgate_test_secret = $value;
	return true;
}

/**
 * Capture the outbound request for assertions.
 *
 * @param array<string, mixed> $args Request arguments.
 * @return array<string, mixed>
 */
function wp_safe_remote_get( string $url, array $args = array() ): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	global $trustgate_test_request, $trustgate_test_response;

	$trustgate_test_request = array(
		'url'  => $url,
		'args' => $args,
	);

	return $trustgate_test_response;
}

function is_wp_error( mixed $value ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return false;
}

/** @param array<string, mixed> $response */
function wp_remote_retrieve_response_code( array $response ): int { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return (int) ( $response['response']['code'] ?? 0 );
}

/** @param array<string, mixed> $response */
function wp_remote_retrieve_body( array $response ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return (string) ( $response['body'] ?? '' );
}

require_once dirname( __DIR__ ) . '/includes/Contracts/verification-provider.php';
require_once dirname( __DIR__ ) . '/includes/Providers/prembly-provider.php';

use HDYHaus\TrustGateRegistration\Providers\PremblyProvider;

/**
 * Fail the test run when an assertion does not match.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual Actual value.
 * @param string $message Assertion description.
 */
function trustgate_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, sprintf( "FAIL: %s\nExpected: %s\nActual: %s\n", $message, var_export( $expected, true ), var_export( $actual, true ) ) );
	exit( 1 );
}

$provider = new PremblyProvider( 'trustgate_registration_settings' );
$method   = new ReflectionMethod( $provider, 'normalize_status' );
$settings = array(
	'mode'                  => 'test',
	'test_configuration_id' => 'config_123',
);
$context  = array( 'email' => 'person@example.com' );
$body     = array(
	'data' => array(
		'session_id'     => 'sdk_session_123',
		'status'         => 'COMPLETED',
		'end_user_email' => 'person@example.com',
		'widget_config'  => array( 'id' => 'config_123' ),
		'addon_results'  => array(
			'document_verification_response' => array(
				'data' => array(
					'document_number' => 'A-123 456',
					'document_type'   => 'Passport',
				),
			),
		),
		'metadata'       => array(
			'sdk_verification_details' => array(
				'document' => array(
					'payload' => array(
						'doc_type'    => 'Passport',
						'doc_country' => 'ALB',
					),
				),
			),
		),
	),
);

/**
 * Normalize one fixture.
 *
 * @param array<string, mixed> $fixture Prembly response fixture.
 * @param array<string, mixed> $fixture_settings Plugin settings.
 * @param array<string, mixed> $fixture_context Verification context.
 * @return array<string, mixed>
 */
function trustgate_normalize_fixture( array $fixture, array $fixture_settings, array $fixture_context ): array {
	global $method, $provider;

	return $method->invoke( $provider, $fixture, 'sdk_session_123', $fixture_settings, $fixture_context );
}

$result = trustgate_normalize_fixture( $body, $settings, $context );
trustgate_assert_same( true, $result['verified'], 'A bound completed session is verified.' );
trustgate_assert_same( 'verified', $result['status'], 'A successful response has normalized status.' );
trustgate_assert_same(
	hash_hmac( 'sha256', 'prembly|ALB|PASSPORT|A123456', $trustgate_test_secret ),
	$result['identity_hash'] ?? '',
	'A successful document check returns only a site-specific identity fingerprint.'
);

trustgate_assert_same( false, $trustgate_test_secret_autoload, 'The secret is not autoloaded.' );
$initial_hash = $result['identity_hash'];
$another_provider = new PremblyProvider( 'trustgate_registration_settings' );
trustgate_assert_same( $initial_hash, $method->invoke( $another_provider, $body, 'sdk_session_123', $settings, $context )['identity_hash'], 'A new provider instance reuses the persisted key without authentication salts.' );
$trustgate_test_secret = false;
$trustgate_test_secret_mode = 'failure';
trustgate_assert_same( false, trustgate_normalize_fixture( $body, $settings, $context )['verified'], 'Secret persistence failure rejects verification.' );
$trustgate_test_secret_mode = 'race';
$race_result = trustgate_normalize_fixture( $body, $settings, $context );
trustgate_assert_same( hash_hmac( 'sha256', 'prembly|ALB|PASSPORT|A123456', str_repeat( 'b', 64 ) ), $race_result['identity_hash'], 'A concurrent initializer uses the winning persisted key.' );
$trustgate_test_secret_mode = 'normal';
$trustgate_test_secret = 'invalid';
trustgate_assert_same( false, trustgate_normalize_fixture( $body, $settings, $context )['verified'], 'A corrupt key fails closed instead of regenerating.' );
$trustgate_test_secret = str_repeat( 'b', 64 );

$trustgate_test_settings = $settings + array(
	'status_endpoint' => 'https://example.com/unsafe/{id}',
);
$trustgate_test_response = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( $body ),
);
$result                  = $provider->confirm_verification( 'sdk_session_123', $context );

trustgate_assert_same( true, $result['verified'], 'The safe HTTP request returns a verified result.' );
trustgate_assert_same(
	'https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/sdk_session_123/',
	$trustgate_test_request['url'],
	'A saved legacy endpoint override cannot change the request destination.'
);
trustgate_assert_same( 'application/json', $trustgate_test_request['args']['headers']['Accept'], 'The request accepts JSON.' );
trustgate_assert_same( 20, $trustgate_test_request['args']['timeout'], 'The request uses the expected timeout.' );

$fixture = $body;
unset( $fixture['data']['widget_config'] );
trustgate_assert_same( 'widget_missing', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A missing widget ID fails closed.' );

$fixture                                = $body;
$fixture['data']['widget_config']['id'] = 'config_other';
trustgate_assert_same( 'widget_mismatch', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A different widget ID fails closed.' );

$fixture = $body;
unset( $fixture['data']['end_user_email'] );
trustgate_assert_same( 'email_missing', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A missing email fails closed.' );

$fixture                           = $body;
$fixture['data']['end_user_email'] = 'other@example.com';
trustgate_assert_same( 'email_mismatch', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A different email fails closed.' );

$fixture                       = $body;
$fixture['data']['session_id'] = 'sdk_session_other';
trustgate_assert_same( 'reference_mismatch', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A different session ID fails closed.' );

$fixture                   = $body;
$fixture['data']['status'] = 'FAILED';
trustgate_assert_same( 'not_verified', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A failed session is rejected.' );

$fixture = $body;
unset( $fixture['data']['addon_results']['document_verification_response']['data']['document_number'] );
trustgate_assert_same( 'identity_missing', trustgate_normalize_fixture( $fixture, $settings, $context )['status'], 'A completed session without a stable document identity fails closed.' );

fwrite( STDOUT, "Prembly provider tests passed.\n" );
