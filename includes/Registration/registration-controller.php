<?php
/**
 * Registration verification hooks.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Registration;

use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds verification controls to core WordPress registration.
 */
final class RegistrationController {
	/**
	 * Verification attempt lifetime.
	 */
	private const ATTEMPT_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Pending reference reservation lifetime.
	 */
	private const REFERENCE_TTL = DAY_IN_SECONDS;

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Verification provider.
	 *
	 * @var VerificationProvider
	 */
	private VerificationProvider $provider;

	/**
	 * Constructor.
	 *
	 * @param string               $option_name Settings option name.
	 * @param VerificationProvider $provider Verification provider.
	 */
	public function __construct( string $option_name, VerificationProvider $provider ) {
		$this->option_name = $option_name;
		$this->provider    = $provider;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_registration_assets' ) );
		add_action( 'register_form', array( $this, 'render_registration_fields' ) );
		add_filter( 'registration_errors', array( $this, 'validate_registration' ), 10, 3 );
		add_action( 'user_register', array( $this, 'store_user_meta' ), 10, 1 );
		add_action( 'wp_ajax_nopriv_trustgate_confirm_verification', array( $this, 'confirm_verification' ) );
		add_action( 'wp_ajax_trustgate_confirm_verification', array( $this, 'confirm_verification' ) );
	}

	/**
	 * Enqueue registration assets only on the registration screen.
	 */
	public function enqueue_registration_assets(): void {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'register' !== $action ) {
			return;
		}

		wp_enqueue_script( 'trustgate-prembly-widget', 'https://js.prembly.com/v1/inline/widget-v3.js', array(), '3.0.0', true );
		wp_enqueue_script(
			'trustgate-registration',
			TRUSTGATE_REGISTRATION_URL . 'assets/js/registration.js',
			array( 'jquery', 'trustgate-prembly-widget' ),
			TRUSTGATE_REGISTRATION_VERSION,
			true
		);
		wp_localize_script(
			'trustgate-registration',
			'trustgateRegistration',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'trustgate_registration' ),
				'provider' => $this->provider->get_public_config(),
				'i18n'     => array(
					'verify'   => __( 'Verify Identity', 'trustgate-registration' ),
					'verified' => __( 'Identity verified. You can finish registration.', 'trustgate-registration' ),
					'failed'   => __( 'We could not confirm your identity verification. Please try again.', 'trustgate-registration' ),
					'working'  => __( 'Checking verification...', 'trustgate-registration' ),
					'invalid'  => __( 'Please enter your email, first name, and last name before verifying.', 'trustgate-registration' ),
					'unready'  => __( 'Identity verification is not configured yet.', 'trustgate-registration' ),
				),
			)
		);
		wp_enqueue_style(
			'trustgate-registration',
			TRUSTGATE_REGISTRATION_URL . 'assets/css/registration.css',
			array(),
			TRUSTGATE_REGISTRATION_VERSION
		);
	}

	/**
	 * Render extra registration fields.
	 */
	public function render_registration_fields(): void {
		$first_name = isset( $_POST['trustgate_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_name  = isset( $_POST['trustgate_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token      = $this->get_or_create_attempt_token();
		?>
		<p>
			<label for="trustgate_first_name"><?php esc_html_e( 'First Name', 'trustgate-registration' ); ?></label>
			<input type="text" name="trustgate_first_name" id="trustgate_first_name" class="input" value="<?php echo esc_attr( $first_name ); ?>" autocomplete="given-name" />
		</p>
		<p>
			<label for="trustgate_last_name"><?php esc_html_e( 'Last Name', 'trustgate-registration' ); ?></label>
			<input type="text" name="trustgate_last_name" id="trustgate_last_name" class="input" value="<?php echo esc_attr( $last_name ); ?>" autocomplete="family-name" />
		</p>
		<p class="trustgate-verification-control">
			<input type="hidden" name="trustgate_attempt_token" id="trustgate_attempt_token" value="<?php echo esc_attr( $token ); ?>" />
			<input type="hidden" name="trustgate_reference" id="trustgate_reference" value="" />
			<button type="button" class="button button-secondary" id="trustgate_verify_button">
				<?php esc_html_e( 'Verify Identity', 'trustgate-registration' ); ?>
			</button>
			<span id="trustgate_verification_status" role="status" aria-live="polite"></span>
		</p>
		<?php
	}

	/**
	 * Validate registration.
	 *
	 * @param WP_Error $errors Registration errors.
	 * @param string   $sanitized_user_login User login.
	 * @param string   $user_email User email.
	 * @return WP_Error
	 */
	public function validate_registration( WP_Error $errors, string $sanitized_user_login, string $user_email ): WP_Error {
		unset( $sanitized_user_login );

		$first_name = isset( $_POST['trustgate_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_name  = isset( $_POST['trustgate_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reference  = isset( $_POST['trustgate_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token      = isset( $_POST['trustgate_attempt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_attempt_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $first_name ) {
			$errors->add( 'trustgate_first_name_required', __( 'Please enter your first name.', 'trustgate-registration' ) );
		}

		if ( '' === $last_name ) {
			$errors->add( 'trustgate_last_name_required', __( 'Please enter your last name.', 'trustgate-registration' ) );
		}

		if ( ! $this->is_valid_attempt( $token, $reference, $user_email ) ) {
			$errors->add( 'trustgate_verification_required', __( 'Please complete identity verification before registering.', 'trustgate-registration' ) );
		}

		return $errors;
	}

	/**
	 * Store registration metadata.
	 *
	 * @param int $user_id User ID.
	 */
	public function store_user_meta( int $user_id ): void {
		$first_name = isset( $_POST['trustgate_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_name  = isset( $_POST['trustgate_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reference  = isset( $_POST['trustgate_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token      = isset( $_POST['trustgate_attempt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_attempt_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user       = get_userdata( $user_id );

		if ( ! $user || ! $this->is_valid_attempt( $token, $reference, $user->user_email ) ) {
			return;
		}

		if ( '' !== $first_name ) {
			update_user_meta( $user_id, 'first_name', $first_name );
		}

		if ( '' !== $last_name ) {
			update_user_meta( $user_id, 'last_name', $last_name );
		}

		update_user_meta( $user_id, 'trustgate_verified', '1' );
		update_user_meta( $user_id, 'trustgate_verified_at', gmdate( 'c' ) );
		update_user_meta( $user_id, 'trustgate_provider', $this->provider->get_slug() );
		update_user_meta( $user_id, 'trustgate_reference', $reference );

		delete_transient( $this->get_attempt_key( $token ) );
	}

	/**
	 * Confirm provider verification over AJAX.
	 */
	public function confirm_verification(): void {
		check_ajax_referer( 'trustgate_registration', 'nonce' );

		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$attempt   = $this->get_attempt( $token );

		if ( '' === $reference || ! is_email( $email ) || 'issued' !== ( $attempt['status'] ?? '' ) ) {
			wp_send_json_error(
				array(
					'status' => 'invalid_attempt',
				),
				400
			);
		}

		$result = $this->provider->confirm_verification(
			$reference,
			array(
				'email' => $email,
			)
		);

		if ( ! $result['verified'] ) {
			wp_send_json_error(
				array(
					'status' => $result['status'],
				),
				400
			);
		}

		$verified_reference = sanitize_text_field( (string) $result['reference'] );
		$reservation_key    = $this->get_reference_key( $verified_reference );
		$reservation        = get_transient( $reservation_key );

		if (
			$this->is_registered_reference( $verified_reference ) ||
			( is_string( $reservation ) && ! hash_equals( $reservation, $token ) )
		) {
			wp_send_json_error(
				array(
					'status' => 'reference_unavailable',
				),
				409
			);
		}

		set_transient( $reservation_key, $token, self::REFERENCE_TTL );
		set_transient(
			$this->get_attempt_key( $token ),
			array(
				'status'     => 'verified',
				'reference'  => $verified_reference,
				'email_hash' => $this->hash_email( $email ),
			),
			self::ATTEMPT_TTL
		);

		wp_send_json_success(
			array(
				'reference' => $verified_reference,
				'status'    => $result['status'],
			)
		);
	}

	/**
	 * Reuse a valid posted attempt token or issue a new one.
	 */
	private function get_or_create_attempt_token(): string {
		$posted_token = isset( $_POST['trustgate_attempt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_attempt_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( array() !== $this->get_attempt( $posted_token ) ) {
			return $posted_token;
		}

		$token = wp_generate_password( 32, false, false );

		set_transient(
			$this->get_attempt_key( $token ),
			array(
				'status' => 'issued',
			),
			self::ATTEMPT_TTL
		);

		return $token;
	}

	/**
	 * Get an attempt from transient storage.
	 *
	 * @param string $token Attempt token.
	 * @return array<string, string>
	 */
	private function get_attempt( string $token ): array {
		if ( '' === $token ) {
			return array();
		}

		$attempt = get_transient( $this->get_attempt_key( $token ) );

		return is_array( $attempt ) ? $attempt : array();
	}

	/**
	 * Determine whether an attempt matches the submitted registration.
	 *
	 * @param string $token Attempt token.
	 * @param string $reference Verification reference.
	 * @param string $email Registration email.
	 */
	private function is_valid_attempt( string $token, string $reference, string $email ): bool {
		$attempt = $this->get_attempt( $token );

		if (
			'verified' !== ( $attempt['status'] ?? '' ) ||
			'' === $reference ||
			! isset( $attempt['reference'], $attempt['email_hash'] ) ||
			! hash_equals( $attempt['reference'], $reference ) ||
			! hash_equals( $attempt['email_hash'], $this->hash_email( $email ) )
		) {
			return false;
		}

		return ! $this->is_registered_reference( $reference );
	}

	/**
	 * Check whether a verification reference already belongs to a user.
	 *
	 * @param string $reference Verification reference.
	 */
	private function is_registered_reference( string $reference ): bool {
		$user_ids = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => 'trustgate_reference', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $reference, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return array() !== $user_ids;
	}

	/**
	 * Build a transient key for an attempt token.
	 *
	 * @param string $token Attempt token.
	 */
	private function get_attempt_key( string $token ): string {
		return 'trustgate_attempt_' . hash( 'sha256', $token );
	}

	/**
	 * Build a transient key for a verification reference.
	 *
	 * @param string $reference Verification reference.
	 */
	private function get_reference_key( string $reference ): string {
		return 'trustgate_reference_' . hash( 'sha256', $reference );
	}

	/**
	 * Hash an email for attempt binding.
	 *
	 * @param string $email Email address.
	 */
	private function hash_email( string $email ): string {
		return hash( 'sha256', strtolower( trim( $email ) ) );
	}
}
