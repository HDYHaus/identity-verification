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

fwrite( STDOUT, "Prembly provider tests passed.\n" );
