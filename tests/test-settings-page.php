<?php
/**
 * Lightweight settings sanitization tests.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

/** @var array<string, string> */
$trustgate_test_settings = array(
	'provider'              => 'prembly',
	'mode'                  => 'test',
	'test_public_key'       => 'wdgt_existing',
	'test_configuration_id' => 'config_existing',
);

/** @var array<int, string> */
$trustgate_test_settings_errors = array();

function __( string $text ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function sanitize_key( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
}

function sanitize_text_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( strip_tags( $value ) );
}

function sanitize_textarea_field( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( strip_tags( $value ) );
}

function esc_url_raw( string $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return false !== filter_var( $value, FILTER_VALIDATE_URL ) && in_array( parse_url( $value, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ? $value : '';
}

function get_option( string $option_name, mixed $default = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $option_name );
	global $trustgate_test_settings;

	return array() !== $trustgate_test_settings ? $trustgate_test_settings : $default;
}

function add_settings_error( string $setting, string $code, string $message, string $type ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $setting, $code, $type );
	global $trustgate_test_settings_errors;
	$trustgate_test_settings_errors[] = $message;
}

function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	unset( $hook_name, $args );
	return $value;
}

require_once dirname( __DIR__ ) . '/includes/Contracts/verification-provider.php';
require_once dirname( __DIR__ ) . '/includes/Providers/provider-registry.php';
require_once dirname( __DIR__ ) . '/includes/Registration/registration-controller.php';
require_once dirname( __DIR__ ) . '/includes/Admin/settings-page.php';

use HDYHaus\TrustGateRegistration\Admin\SettingsPage;
use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;
use HDYHaus\TrustGateRegistration\Providers\ProviderRegistry;
use HDYHaus\TrustGateRegistration\Registration\RegistrationController;

final class TrustGateSettingsTestProvider implements VerificationProvider {
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
		unset( $reference, $context );
		return array();
	}
}

/**
 * Fail the test run when an assertion does not match.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual Actual value.
 * @param string $message Assertion description.
 */
function trustgate_settings_assert_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, sprintf( "FAIL: %s\nExpected: %s\nActual: %s\n", $message, var_export( $expected, true ), var_export( $actual, true ) ) );
	exit( 1 );
}

$registry = new ProviderRegistry( array( new TrustGateSettingsTestProvider() ), 'prembly' );
$page     = new SettingsPage( 'trustgate_settings', $registry );
$clean    = $page->sanitize_settings(
	array(
		'settings_tab'        => 'registration',
		'consent_text'        => "  <strong>Custom consent</strong>\nfor this site.  ",
		'provider_privacy_url' => 'javascript:alert(1)',
		'provider_terms_url'   => 'https://provider.example/consent',
		'success_redirect'     => 'https://site.example/complete',
	)
);

trustgate_settings_assert_same( "Custom consent\nfor this site.", $clean['consent_text'], 'Consent wording is sanitized as plain text.' );
trustgate_settings_assert_same( '', $clean['provider_privacy_url'], 'Unsafe provider URLs are rejected.' );
trustgate_settings_assert_same( 'https://provider.example/consent', $clean['provider_terms_url'], 'A valid provider URL is retained.' );
trustgate_settings_assert_same( 'prembly', $clean['provider'], 'Saving the Registration tab preserves the active provider.' );
trustgate_settings_assert_same( 'wdgt_existing', $clean['test_public_key'], 'Saving the Registration tab preserves provider credentials.' );

$fallback = $page->sanitize_settings(
	array(
		'settings_tab' => 'registration',
		'consent_text' => '   ',
	)
);

trustgate_settings_assert_same( RegistrationController::get_default_consent_text(), $fallback['consent_text'], 'A blank consent message falls back to the safe default.' );

fwrite( STDOUT, "Settings sanitization tests passed.\n" );
