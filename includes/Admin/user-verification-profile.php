<?php
/**
 * Read-only verification details on WordPress user profiles.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Admin;

use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays stored HDYHaus Identity Verification records to administrators.
 */
final class UserVerificationProfile {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
	}

	/**
	 * Render a read-only verification record.
	 *
	 * @param WP_User $user User being viewed.
	 */
	public function render( WP_User $user ): void {
		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}

		$values = array(
			'status'          => (string) get_user_meta( $user->ID, 'trustgate_verification_status', true ),
			'verified_at'     => (string) get_user_meta( $user->ID, 'trustgate_verified_at', true ),
			'provider'        => (string) get_user_meta( $user->ID, 'trustgate_provider', true ),
			'reference'       => (string) get_user_meta( $user->ID, 'trustgate_reference', true ),
			'reference_hash'  => (string) get_user_meta( $user->ID, 'trustgate_reference_hash', true ),
			'identity_hash'   => (string) get_user_meta( $user->ID, 'trustgate_identity_hash', true ),
			'consent_at'      => (string) get_user_meta( $user->ID, 'trustgate_consent_at', true ),
			'consent_text'    => (string) get_user_meta( $user->ID, 'trustgate_consent_text', true ),
			'consent_version' => (string) get_user_meta( $user->ID, 'trustgate_consent_version', true ),
		);

		$has_record = array_filter( $values, static fn ( string $value ): bool => '' !== $value );
		?>
		<h2><?php esc_html_e( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Record status', 'hdyhaus-identity-verification' ); ?></th>
				<td>
					<?php if ( empty( $has_record ) ) : ?>
						<?php esc_html_e( 'No HDYHaus Identity Verification verification record.', 'hdyhaus-identity-verification' ); ?>
					<?php elseif ( '' === $values['status'] && ( '' !== $values['reference_hash'] || '' !== $values['identity_hash'] ) ) : ?>
						<?php esc_html_e( 'Verification data erased; replay-prevention record retained.', 'hdyhaus-identity-verification' ); ?>
					<?php else : ?>
						<strong><?php esc_html_e( 'Verified', 'hdyhaus-identity-verification' ); ?></strong>
					<?php endif; ?>
				</td>
			</tr>
			<?php $this->render_row( __( 'Verification status', 'hdyhaus-identity-verification' ), $values['status'] ); ?>
			<?php $this->render_row( __( 'Provider', 'hdyhaus-identity-verification' ), $values['provider'] ); ?>
			<?php $this->render_row( __( 'Verified at', 'hdyhaus-identity-verification' ), $values['verified_at'] ); ?>
			<?php $this->render_row( __( 'Consent accepted at', 'hdyhaus-identity-verification' ), $values['consent_at'] ); ?>
			<?php $this->render_row( __( 'Consent wording', 'hdyhaus-identity-verification' ), $values['consent_text'] ); ?>
			<?php $this->render_row( __( 'Provider session reference', 'hdyhaus-identity-verification' ), $values['reference'] ); ?>
			<?php $this->render_row( __( 'Consent record ID', 'hdyhaus-identity-verification' ), $values['consent_version'] ); ?>
			<?php if ( '' !== $values['reference_hash'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Replay protection', 'hdyhaus-identity-verification' ); ?></th>
					<td><?php esc_html_e( 'Retained', 'hdyhaus-identity-verification' ); ?></td>
				</tr>
			<?php endif; ?>
			<?php if ( '' !== $values['identity_hash'] ) : ?>
				<tr>
					<th><?php esc_html_e( 'Identity uniqueness', 'hdyhaus-identity-verification' ); ?></th>
					<td><?php esc_html_e( 'Retained', 'hdyhaus-identity-verification' ); ?></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Render one non-empty detail row.
	 *
	 * @param string $label Row label.
	 * @param string $value Stored value.
	 */
	private function render_row( string $label, string $value ): void {
		if ( '' === $value ) {
			return;
		}
		?>
		<tr>
			<th><?php echo esc_html( $label ); ?></th>
			<td><?php echo nl2br( esc_html( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
		</tr>
		<?php
	}
}
