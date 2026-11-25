<?php
/**
 * Administrator view and recovery for verified registration attempts.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Admin;

use HDYHaus\TrustGateRegistration\Registration\RegistrationController;
use HDYHaus\TrustGateRegistration\Registration\VerificationAttemptStore;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays unresolved verified attempts and provides audited account recovery.
 */
final class VerificationAttempts {
	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'hdyhaus-verification-attempts';

	/**
	 * Attempt repository.
	 *
	 * @var VerificationAttemptStore
	 */
	private VerificationAttemptStore $store;

	/**
	 * Constructor.
	 *
	 * @param VerificationAttemptStore $store Attempt repository.
	 */
	public function __construct( VerificationAttemptStore $store ) {
		$this->store = $store;
	}

	/**
	 * Register admin hooks.
	 */
	public function register(): void {
		// Register after the parent menu is added by SettingsPage.
		add_action( 'admin_menu', array( $this, 'add_page' ), 99 );
		add_action( 'admin_post_trustgate_recover_registration', array( $this, 'recover_registration' ) );
		add_action( 'trustgate_prune_verification_attempts', array( $this->store, 'prune_expired' ) );
		if ( ! wp_next_scheduled( 'trustgate_prune_verification_attempts' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'trustgate_prune_verification_attempts' );
		}
	}

	/**
	 * Add the attempts screen under Identity Verification.
	 */
	public function add_page(): void {
		add_submenu_page(
			'hdyhaus-identity-verification',
			__( 'Registration Attempts', 'hdyhaus-identity-verification' ),
			__( 'Registration Attempts', 'hdyhaus-identity-verification' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render unresolved attempts.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$attempts = $this->store->get_unresolved();
		$notice   = isset( $_GET['trustgate_attempt_result'] ) ? sanitize_key( wp_unslash( $_GET['trustgate_attempt_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Verified Registration Attempts', 'hdyhaus-identity-verification' ); ?></h1>
			<p><?php esc_html_e( 'These provider-verified attempts have not been linked to a WordPress account. Pending records expire after seven days. Identity conflicts require administrator review.', 'hdyhaus-identity-verification' ); ?></p>
			<?php if ( 'created' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'The account was created and the verified attempt was linked. WordPress sent a password setup email.', 'hdyhaus-identity-verification' ); ?></p></div>
			<?php elseif ( '' !== $notice ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->result_message( $notice ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( empty( $attempts ) ) : ?>
				<p><?php esc_html_e( 'There are no unresolved verified registration attempts.', 'hdyhaus-identity-verification' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Verified', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Registrant', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Email', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Environment', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Status / reason', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Recovery', 'hdyhaus-identity-verification' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $attempts as $attempt ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $attempt['verified_at'] ); ?></td>
							<td><?php echo esc_html( trim( (string) $attempt['first_name'] . ' ' . (string) $attempt['last_name'] ) ); ?><br /><code><?php echo esc_html( (string) $attempt['username'] ); ?></code></td>
							<td><details><summary><?php echo esc_html( $this->mask_email( (string) $attempt['email'] ) ); ?></summary><?php echo esc_html( (string) $attempt['email'] ); ?></details></td>
							<td><?php echo esc_html( 'sandbox' === $attempt['environment'] ? __( 'Sandbox', 'hdyhaus-identity-verification' ) : __( 'Live', 'hdyhaus-identity-verification' ) ); ?></td>
							<td><?php echo esc_html( (string) $attempt['status'] ); ?>
							<?php
							if ( '' !== (string) $attempt['failure_code'] ) :
								?>
								<br /><code><?php echo esc_html( (string) $attempt['failure_code'] ); ?></code><?php endif; ?>
								<?php
								if ( '' !== (string) $attempt['provider_reference'] ) :
									?>
									<details><summary><?php esc_html_e( 'Provider session', 'hdyhaus-identity-verification' ); ?></summary><code><?php echo esc_html( (string) $attempt['provider_reference'] ); ?></code></details><?php endif; ?></td>
							<td><?php $this->render_recovery_form( $attempt ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle an administrator-requested recovery.
	 */
	public function recover_registration(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to recover registration attempts.', 'hdyhaus-identity-verification' ) );
		}

		check_admin_referer( 'trustgate_recover_registration' );
		$id       = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$username = isset( $_POST['recovery_username'] ) ? sanitize_user( wp_unslash( $_POST['recovery_username'] ), true ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$confirm  = isset( $_POST['confirm_identity'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['confirm_identity'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$attempt  = $this->store->get_by_id( $id );

		if ( empty( $attempt ) || ! empty( $attempt['user_id'] ) || ! in_array( $attempt['status'], array( 'verified_pending_registration', 'registration_failed', 'identity_conflict' ), true ) || strtotime( (string) $attempt['expires_at'] . ' UTC' ) < time() || ! $confirm ) {
			$this->redirect_with_result( 'invalid_attempt' );
		}

		if ( email_exists( (string) $attempt['email'] ) ) {
			$this->store->update_by_id(
				$id,
				array(
					'status'       => 'registration_failed',
					'failure_code' => 'account_email_exists',
				)
			);
			$this->redirect_with_result( 'email_exists' );
		}

		if ( '' === $username || username_exists( $username ) ) {
			$this->store->update_by_id(
				$id,
				array(
					'status'       => 'registration_failed',
					'failure_code' => 'account_username_unavailable',
				)
			);
			$this->redirect_with_result( 'username_unavailable' );
		}

		$identity_hash = (string) $attempt['identity_hash'];
		if (
			$this->store->find_user_for_identity( $identity_hash ) > 0 ||
			$this->store->find_user_for_reference( (string) $attempt['reference_hash'] ) > 0
		) {
			$this->redirect_with_result( 'identity_linked' );
		}

		if ( ! $this->claim_identity_for_recovery( $attempt ) ) {
			$this->redirect_with_result( 'identity_conflict' );
		}

		$role = $this->get_safe_registration_role();
		$user = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => (string) $attempt['email'],
				'user_pass'  => wp_generate_password( 40, true, true ),
				'role'       => $role,
				'first_name' => (string) $attempt['first_name'],
				'last_name'  => (string) $attempt['last_name'],
			)
		);

		if ( $user instanceof WP_Error ) {
			$this->release_recovery_reservation( $attempt );
			$code = sanitize_key( (string) array_key_first( $user->errors ) );
			$this->store->update_by_id(
				$id,
				array(
					'status'       => 'registration_failed',
					'failure_code' => $code,
				)
			);
			$this->redirect_with_result( 'creation_failed' );
		}

		$user_id = (int) $user;
		$this->save_user_verification_meta( $user_id, $attempt );
		update_option( RegistrationController::IDENTITY_OPTION_PREFIX . $identity_hash, 'user:' . $user_id, false );
		$this->store->update_by_id(
			$id,
			array(
				'status'            => 'account_created',
				'failure_code'      => '',
				'user_id'           => $user_id,
				'username'          => $username,
				'recovered_by'      => get_current_user_id(),
				'recovered_at'      => gmdate( 'Y-m-d H:i:s' ),
				'expires_at'        => gmdate( 'Y-m-d H:i:s', time() + 90 * DAY_IN_SECONDS ),
				'resume_token_hash' => '',
				'resume_expires_at' => null,
			)
		);

		wp_new_user_notification( $user_id, null, 'user' );
		do_action( 'trustgate_registration_recovered', $user_id, $id, get_current_user_id() );
		$this->redirect_with_result( 'created' );
	}

	/**
	 * Render a guarded administrator recovery form.
	 *
	 * @param array<string, mixed> $attempt Attempt row.
	 */
	private function render_recovery_form( array $attempt ): void {
		if ( ! in_array( $attempt['status'], array( 'verified_pending_registration', 'registration_failed', 'identity_conflict' ), true ) ) {
			esc_html_e( 'Review required', 'hdyhaus-identity-verification' );
			return;
		}
		?>
		<details><summary><?php esc_html_e( 'Review / recover', 'hdyhaus-identity-verification' ); ?></summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="trustgate_recover_registration" />
				<input type="hidden" name="attempt_id" value="<?php echo esc_attr( (string) $attempt['id'] ); ?>" />
				<?php wp_nonce_field( 'trustgate_recover_registration' ); ?>
				<p><label for="trustgate-recovery-username-<?php echo esc_attr( (string) $attempt['id'] ); ?>"><?php esc_html_e( 'Username', 'hdyhaus-identity-verification' ); ?></label><br /><input required type="text" name="recovery_username" id="trustgate-recovery-username-<?php echo esc_attr( (string) $attempt['id'] ); ?>" value="<?php echo esc_attr( (string) $attempt['username'] ); ?>" /></p>
				<p><label><input type="checkbox" name="confirm_identity" value="1" required /> <?php esc_html_e( 'I reviewed this attempt and confirm that no existing account should be used for this person.', 'hdyhaus-identity-verification' ); ?></label></p>
				<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Create account and send password setup email', 'hdyhaus-identity-verification' ); ?></button></p>
			</form>
		</details>
		<?php
	}

	/**
	 * Claim the identity after checking for an existing WordPress account.
	 *
	 * @param array<string, mixed> $attempt Attempt row.
	 */
	private function claim_identity_for_recovery( array $attempt ): bool {
		$identity_hash = (string) $attempt['identity_hash'];
		$option_name   = RegistrationController::IDENTITY_OPTION_PREFIX . $identity_hash;
		$attempt_hash  = (string) $attempt['attempt_hash'];
		$value         = get_option( $option_name, '' );
		$parts         = is_string( $value ) ? explode( ':', $value, 3 ) : array();

		if ( 3 === count( $parts ) && 'reserved' === $parts[0] ) {
			if ( hash_equals( $parts[1], $attempt_hash ) ) {
				return true;
			}
			if ( (int) $parts[2] >= time() ) {
				return false;
			}
		}

		if ( '' === $value || false === $value ) {
			return add_option( $option_name, $this->build_reservation( $attempt_hash ), '', false );
		}

		if ( is_string( $value ) && str_starts_with( $value, 'user:' ) && get_userdata( (int) substr( $value, 5 ) ) ) {
			return false;
		}

		return update_option( $option_name, $this->build_reservation( $attempt_hash ), false );
	}

	/**
	 * Release a reservation when WordPress rejects account creation.
	 *
	 * @param array<string, mixed> $attempt Attempt row.
	 */
	private function release_recovery_reservation( array $attempt ): void {
		$identity_hash = (string) $attempt['identity_hash'];
		$option_name   = RegistrationController::IDENTITY_OPTION_PREFIX . $identity_hash;
		$value         = get_option( $option_name, '' );
		$parts         = is_string( $value ) ? explode( ':', $value, 3 ) : array();

		if ( 3 === count( $parts ) && 'reserved' === $parts[0] && hash_equals( $parts[1], (string) $attempt['attempt_hash'] ) ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Build an identity reservation using the stored attempt-token hash.
	 *
	 * @param string $attempt_hash SHA-256 token hash.
	 */
	private function build_reservation( string $attempt_hash ): string {
		return sprintf( 'reserved:%s:%d', $attempt_hash, time() + VerificationAttemptStore::ATTEMPT_TTL );
	}

	/**
	 * Save the existing verification and consent record on the recovered user.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $attempt Attempt row.
	 */
	private function save_user_verification_meta( int $user_id, array $attempt ): void {
		update_user_meta( $user_id, 'trustgate_verified', '1' );
		update_user_meta( $user_id, 'trustgate_verification_status', 'verified' );
		update_user_meta( $user_id, 'trustgate_verified_at', (string) $attempt['verified_at'] );
		update_user_meta( $user_id, 'trustgate_provider', (string) $attempt['provider'] );
		update_user_meta( $user_id, 'trustgate_reference', (string) $attempt['provider_reference'] );
		update_user_meta( $user_id, 'trustgate_reference_hash', (string) $attempt['reference_hash'] );
		update_user_meta( $user_id, 'trustgate_identity_hash', (string) $attempt['identity_hash'] );
		update_user_meta( $user_id, 'trustgate_consent_at', (string) $attempt['consent_at'] );
		update_user_meta( $user_id, 'trustgate_consent_text', (string) $attempt['consent_text'] );
		update_user_meta( $user_id, 'trustgate_consent_version', (string) $attempt['consent_version'] );
	}

	/**
	 * Resolve a non-privileged account role for recovery.
	 */
	private function get_safe_registration_role(): string {
		$role        = sanitize_key( (string) get_option( 'default_role', 'subscriber' ) );
		$role_object = get_role( $role );

		if ( ! $role_object || array_intersect( array_keys( $role_object->capabilities ), array( 'manage_options', 'edit_users', 'promote_users', 'delete_users' ) ) ) {
			return 'subscriber';
		}

		return $role;
	}

	/**
	 * Mask an email for the table overview.
	 *
	 * @param string $email Email address.
	 */
	private function mask_email( string $email ): string {
		$parts = explode( '@', $email, 2 );
		if ( 2 !== count( $parts ) ) {
			return '•••';
		}

		return substr( $parts[0], 0, 1 ) . '•••@' . $parts[1];
	}

	/**
	 * Redirect back to the attempt list.
	 *
	 * @param string $result Safe notice code.
	 */
	private function redirect_with_result( string $result ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => self::PAGE_SLUG,
					'trustgate_attempt_result' => sanitize_key( $result ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get safe administrator feedback.
	 *
	 * @param string $result Result code.
	 */
	private function result_message( string $result ): string {
		$messages = array(
			'invalid_attempt'      => __( 'This attempt is expired, already linked, or needs additional review.', 'hdyhaus-identity-verification' ),
			'email_exists'         => __( 'An account already uses this email. Use WordPress account recovery instead of creating a duplicate.', 'hdyhaus-identity-verification' ),
			'username_unavailable' => __( 'Choose an unused username and retry recovery.', 'hdyhaus-identity-verification' ),
			'identity_linked'      => __( 'This verified identity is already linked to a WordPress account.', 'hdyhaus-identity-verification' ),
			'identity_conflict'    => __( 'Another active attempt currently holds this identity. Review it before retrying.', 'hdyhaus-identity-verification' ),
			'creation_failed'      => __( 'WordPress could not create the account. The attempt remains available for review.', 'hdyhaus-identity-verification' ),
		);

		return $messages[ $result ] ?? __( 'The recovery action could not be completed.', 'hdyhaus-identity-verification' );
	}
}
