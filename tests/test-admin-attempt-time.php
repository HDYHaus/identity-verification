<?php
/**
 * Registration-attempt admin timestamp tests.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function get_option( string $option, mixed $default = false ): mixed { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$options = array(
		'date_format' => 'Y-m-d',
		'time_format' => 'H:i:s',
	);

	return $options[ $option ] ?? $default;
}

function wp_timezone(): DateTimeZone { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return new DateTimeZone( 'Europe/Tirane' );
}

function wp_date( string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return ( new DateTimeImmutable( '@' . ( $timestamp ?? time() ) ) )
		->setTimezone( $timezone ?? new DateTimeZone( 'UTC' ) )
		->format( $format );
}

require_once dirname( __DIR__ ) . '/includes/Admin/verification-attempts.php';

use HDYHaus\TrustGateRegistration\Admin\VerificationAttempts;

$page_reflection = new ReflectionClass( VerificationAttempts::class );
$page            = $page_reflection->newInstanceWithoutConstructor();
$method          = $page_reflection->getMethod( 'format_site_datetime' );
$method->setAccessible( true );
$formatted = $method->invoke( $page, '2026-11-26 09:50:00' );

if ( '2026-11-26 10:50:00' !== $formatted ) {
	fwrite( STDERR, "FAIL: UTC attempt timestamps are converted to the WordPress timezone. Got {$formatted}.\n" );
	exit( 1 );
}

echo "Registration-attempt timezone tests passed.\n";
