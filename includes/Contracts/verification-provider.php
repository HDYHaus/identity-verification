<?php
/**
 * Verification provider contract.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes the provider behavior TrustGate needs.
 */
interface VerificationProvider {
	/**
	 * Get the provider machine name.
	 */
	public function get_slug(): string;

	/**
	 * Get the provider display name.
	 */
	public function get_label(): string;

	/**
	 * Get public configuration safe for frontend JavaScript.
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_config(): array;

	/**
	 * Confirm whether a provider reference represents an approved verification.
	 *
	 * @param string               $reference Provider reference or session identifier.
	 * @param array<string, mixed> $context Verification context.
	 * @return array{verified: bool, status: string, reference: string, identity_hash?: string, raw?: array<string, mixed>}
	 */
	public function confirm_verification( string $reference, array $context = array() ): array;
}
