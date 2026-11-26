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
 * Displays recent registration attempts and provides audited recovery for eligible verified users.
 */
final class VerificationAttempts {
	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'hdyhaus-verification-attempts';

	/**
	 * Number of attempts shown per page.
	 */
	private const PAGE_SIZE = 25;

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
	 * Render recent attempts with filters and pagination.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filter       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$valid_filter = in_array( $filter, array( 'all', 'successful', 'failed', 'other' ), true ) ? $filter : 'all';
		$paged        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total        = $this->store->count_attempts( $valid_filter );
		$total_pages  = (int) ceil( $total / self::PAGE_SIZE );
		$paged        = min( $paged, max( 1, $total_pages ) );
		$attempts     = $this->store->get_attempts( $valid_filter, $paged, self::PAGE_SIZE );
		$notice       = isset( $_GET['trustgate_attempt_result'] ) ? sanitize_key( wp_unslash( $_GET['trustgate_attempt_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Registration Attempts', 'hdyhaus-identity-verification' ); ?></h1>
			<?php
			$unlinked_days = $this->store->get_unlinked_retention_days();
			$linked_days   = $this->store->get_linked_retention_days();
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: unlinked retention in days, 2: linked retention in days. */
						__( 'Review recent verification attempts and their WordPress account status. Successful shows created accounts; Failed shows verification or registration errors; Other includes unfinished, cancelled, or verified attempts without an account. Unlinked attempts are retained for up to %1$d days and account-linked records for up to %2$d days. Change these periods in Identity Verification → Registration settings.', 'hdyhaus-identity-verification' ),
						$unlinked_days,
						$linked_days
					)
				)
			);
			?>
			<?php if ( 'created' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'The account was created and the verified attempt was linked. WordPress sent a password setup email.', 'hdyhaus-identity-verification' ); ?></p></div>
			<?php elseif ( '' !== $notice ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $this->result_message( $notice ) ); ?></p></div>
			<?php endif; ?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tablenav top">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<label for="trustgate-attempt-status"><?php esc_html_e( 'Show', 'hdyhaus-identity-verification' ); ?></label>
				<select name="status" id="trustgate-attempt-status">
					<option value="all" <?php selected( $valid_filter, 'all' ); ?>><?php esc_html_e( 'All attempts', 'hdyhaus-identity-verification' ); ?></option>
					<option value="successful" <?php selected( $valid_filter, 'successful' ); ?>><?php esc_html_e( 'Successful (account created)', 'hdyhaus-identity-verification' ); ?></option>
					<option value="failed" <?php selected( $valid_filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'hdyhaus-identity-verification' ); ?></option>
					<option value="other" <?php selected( $valid_filter, 'other' ); ?>><?php esc_html_e( 'Other / pending', 'hdyhaus-identity-verification' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'hdyhaus-identity-verification' ); ?></button>
				<?php // translators: %s: number of registration attempts. ?>
				<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s attempt', '%s attempts', $total, 'hdyhaus-identity-verification' ), number_format_i18n( $total ) ) ); ?></span>
			</form>
			<?php if ( empty( $attempts ) ) : ?>
				<p><?php esc_html_e( 'There are no attempts matching this filter.', 'hdyhaus-identity-verification' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Started / verified', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Registrant', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Email', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Environment', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Status / reason', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'WordPress account', 'hdyhaus-identity-verification' ); ?></th>
						<th><?php esc_html_e( 'Recovery', 'hdyhaus-identity-verification' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $attempts as $attempt ) : ?>
						<tr>
							<td><?php echo esc_html( $this->format_site_datetime( '' !== (string) $attempt['verified_at'] ? (string) $attempt['verified_at'] : (string) $attempt['created_at'] ) ); ?></td>
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
							<td><?php $this->render_linked_user( $attempt ); ?></td>
							<td><?php $this->render_recovery_form( $attempt ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $total_pages > 1 ) : ?>
					<?php
					$base_url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
					$base_url = add_query_arg( 'status', $valid_filter, $base_url );
					$base_url = add_query_arg( 'paged', '%#%', $base_url );
					$pages    = paginate_links(
						array(
							'base'      => $base_url,
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'type'      => 'list',
							'prev_text' => __( 'Previous', 'hdyhaus-identity-verification' ),
							'next_text' => __( 'Next', 'hdyhaus-identity-verification' ),
						)
					);
					?>
					<nav class="tablenav bottom" aria-label="<?php esc_attr_e( 'Registration attempt pages', 'hdyhaus-identity-verification' ); ?>">
						<?php echo wp_kses_post( $pages ); ?>
					</nav>
				<?php endif; ?>
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
				'expires_at'        => gmdate( 'Y-m-d H:i:s', time() + $this->store->get_linked_retention_days() * DAY_IN_SECONDS ),
				'resume_token_hash' => '',
				'resume_expires_at' => null,
			)
		);

		wp_new_user_notification( $user_id, null, 'user' );
		do_action( 'trustgate_registration_recovered', $user_id, $id, get_current_user_id() );
		$this->redirect_with_result( 'created' );
	}

	/**
	 * Render the WordPress account linked to an attempt, if one exists.
	 *
	 * @param array<string, mixed> $attempt Attempt row.
	 */
	private function render_linked_user( array $attempt ): void {
		$user_id = absint( $attempt['user_id'] ?? 0 );
		if ( 0 === $user_id ) {
			esc_html_e( 'Not linked', 'hdyhaus-identity-verification' );
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			// translators: %d: WordPress user ID.
			echo esc_html( sprintf( __( 'User #%d not found', 'hdyhaus-identity-verification' ), $user_id ) );
			return;
		}

		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( get_edit_user_link( $user_id ) ),
			esc_html( $user->user_login )
		);
	}

	/**
	 * Convert a stored UTC timestamp to the WordPress site's configured timezone.
	 *
	 * @param string $datetime UTC datetime stored in the attempts table.
	 */
	private function format_site_datetime( string $datetime ): string {
		$timestamp = strtotime( $datetime . ' UTC' );

		if ( false === $timestamp ) {
			return $datetime;
		}

		$date_format = (string) get_option( 'date_format', 'Y-m-d' );
		$time_format = (string) get_option( 'time_format', 'H:i:s' );

		return wp_date( $date_format . ' ' . $time_format, $timestamp, wp_timezone() );
	}

	/**
	 * Render a guarded administrator recovery form.
	 *
	 * @param array<string, mixed> $attempt Attempt row.
	 */
	private function render_recovery_form( array $attempt ): void {
		if ( ! empty( $attempt['user_id'] ) ) {
			esc_html_e( 'Account created and linked.', 'hdyhaus-identity-verification' );
			return;
		}

		if ( ! in_array( $attempt['status'], array( 'verified_pending_registration', 'registration_failed', 'identity_conflict' ), true ) ) {
			esc_html_e( 'This attempt is not eligible for account recovery.', 'hdyhaus-identity-verification' );
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
