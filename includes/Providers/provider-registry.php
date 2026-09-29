<?php
/**
 * Verification provider registry.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Providers;

use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores available providers and resolves the single active provider.
 */
final class ProviderRegistry {
	/**
	 * Registered providers.
	 *
	 * @var array<string, VerificationProvider>
	 */
	private array $providers = array();

	/**
	 * Provider used by settings saved before provider disabling was supported.
	 *
	 * @var string
	 */
	private string $legacy_default_slug;

	/**
	 * Constructor.
	 *
	 * @param array<int, VerificationProvider> $providers Providers to register.
	 * @param string                           $legacy_default_slug Legacy default provider slug.
	 */
	public function __construct( array $providers, string $legacy_default_slug = '' ) {
		foreach ( $providers as $provider ) {
			$slug = sanitize_key( $provider->get_slug() );

			if ( '' !== $slug ) {
				$this->providers[ $slug ] = $provider;
			}
		}

		$this->legacy_default_slug = sanitize_key( $legacy_default_slug );
	}

	/**
	 * Get every registered provider.
	 *
	 * @return array<string, VerificationProvider>
	 */
	public function all(): array {
		return $this->providers;
	}

	/**
	 * Get a provider by slug.
	 *
	 * @param string $slug Provider slug.
	 */
	public function get( string $slug ): ?VerificationProvider {
		$slug = sanitize_key( $slug );

		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Resolve the single active provider from saved settings.
	 *
	 * Settings created before 0.1.4 did not always contain a provider key. A
	 * non-empty legacy option therefore continues to use Prembly until an
	 * administrator explicitly enables or disables a provider.
	 *
	 * @param array<string, mixed> $settings Saved plugin settings.
	 */
	public function resolve( array $settings ): ?VerificationProvider {
		$slug = $this->get_active_slug( $settings );

		return '' !== $slug ? $this->get( $slug ) : null;
	}

	/**
	 * Get the active provider slug.
	 *
	 * @param array<string, mixed> $settings Saved plugin settings.
	 */
	public function get_active_slug( array $settings ): string {
		if ( array_key_exists( 'provider', $settings ) ) {
			$provider = $settings['provider'];

			if ( ! is_string( $provider ) ) {
				return '';
			}

			$slug = sanitize_key( $provider );

			return isset( $this->providers[ $slug ] ) ? $slug : '';
		}

		if ( array() !== $settings && isset( $this->providers[ $this->legacy_default_slug ] ) ) {
			return $this->legacy_default_slug;
		}

		return '';
	}
}
