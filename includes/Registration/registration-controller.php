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
	 * Prefix for permanent identity uniqueness records.
	 */
	public const IDENTITY_OPTION_PREFIX = 'trustgate_identity_';

	/**
	 * Default provider privacy-policy URL.
	 */
	public const DEFAULT_PROVIDER_PRIVACY_URL = 'https://prembly.com/Policy';

	/**
	 * Default provider terms URL.
	 */
	public const DEFAULT_PROVIDER_TERMS_URL = 'https://prembly.com/terms';

	/**
	 * Get the migration-safe default consent wording.
	 */
	public static function get_default_consent_text(): string {
		return __( 'I consent to Prembly processing my name, email, identity document, and biometric information to verify my identity.', 'hdyhaus-identity-verification' );
	}

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
		add_filter( 'registration_redirect', array( $this, 'filter_registration_redirect' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_success_assets' ) );
		add_filter( 'the_content', array( $this, 'prepend_registration_success_notice' ) );
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
			'hdyhaus-identity-verification',
			TRUSTGATE_REGISTRATION_URL . 'assets/js/registration.js',
			array( 'jquery', 'trustgate-prembly-widget' ),
			TRUSTGATE_REGISTRATION_VERSION,
			true
		);
		wp_localize_script(
			'hdyhaus-identity-verification',
			'trustgateRegistration',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'trustgate_registration' ),
				'provider' => $this->provider->get_public_config(),
				'i18n'     => array(
					'verify'            => __( 'Verify Identity', 'hdyhaus-identity-verification' ),
					'verified'          => __( 'Identity verified. You can finish registration.', 'hdyhaus-identity-verification' ),
					'failed'            => __( 'We could not confirm your identity verification. Please try again.', 'hdyhaus-identity-verification' ),
					'working'           => __( 'Checking verification...', 'hdyhaus-identity-verification' ),
					'invalid'           => __( 'Please enter your email, first name, and last name before verifying.', 'hdyhaus-identity-verification' ),
					'consent'           => __( 'Please confirm that you consent to identity verification.', 'hdyhaus-identity-verification' ),
					'unready'           => __( 'Identity verification is not configured yet.', 'hdyhaus-identity-verification' ),
					'duplicateIdentity' => __( 'This identity is already associated with an account. Please sign in or contact the site administrator.', 'hdyhaus-identity-verification' ),
				),
			)
		);
		wp_enqueue_style(
			'hdyhaus-identity-verification',
			TRUSTGATE_REGISTRATION_URL . 'assets/css/registration.css',
			array(),
			TRUSTGATE_REGISTRATION_VERSION
		);
	}

	/**
	 * Render extra registration fields.
	 */
	public function render_registration_fields(): void {
		$valid_form = $this->has_registration_nonce();
		$first_name = $valid_form && isset( $_POST['trustgate_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by has_registration_nonce().
		$last_name  = $valid_form && isset( $_POST['trustgate_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by has_registration_nonce().
		$token      = $this->get_or_create_attempt_token();
		$consent    = $valid_form && isset( $_POST['trustgate_consent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['trustgate_consent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by has_registration_nonce().
		$privacy    = get_privacy_policy_url();
		$links      = array_filter(
			array(
				array(
					'url'   => $this->get_provider_privacy_url(),
					'label' => sprintf(
						/* translators: %s: verification provider name */
						__( '%s Privacy Policy', 'hdyhaus-identity-verification' ),
						$this->provider->get_label()
					),
				),
				array(
					'url'   => $this->get_provider_terms_url(),
					'label' => sprintf(
						/* translators: %s: verification provider name */
						__( '%s Terms / Consent', 'hdyhaus-identity-verification' ),
						$this->provider->get_label()
					),
				),
				array(
					'url'   => $privacy,
					'label' => __( 'Website Privacy Policy', 'hdyhaus-identity-verification' ),
				),
				array(
					'url'   => $this->get_site_terms_url(),
					'label' => __( 'Website Terms / Disclaimer', 'hdyhaus-identity-verification' ),
				),
			),
			static fn ( array $link ): bool => '' !== $link['url']
		);
		?>
		<?php wp_nonce_field( 'trustgate_registration_form', 'trustgate_registration_form_nonce' ); ?>
		<p>
			<label for="trustgate_first_name"><?php esc_html_e( 'First Name', 'hdyhaus-identity-verification' ); ?></label>
			<input type="text" name="trustgate_first_name" id="trustgate_first_name" class="input" value="<?php echo esc_attr( $first_name ); ?>" autocomplete="given-name" />
		</p>
		<p>
			<label for="trustgate_last_name"><?php esc_html_e( 'Last Name', 'hdyhaus-identity-verification' ); ?></label>
			<input type="text" name="trustgate_last_name" id="trustgate_last_name" class="input" value="<?php echo esc_attr( $last_name ); ?>" autocomplete="family-name" />
		</p>
		<p class="trustgate-verification-control">
			<label class="trustgate-consent" for="trustgate_consent">
				<input type="checkbox" name="trustgate_consent" id="trustgate_consent" value="1" <?php checked( $consent ); ?> required />
				<?php echo esc_html( $this->get_consent_text() ); ?>
			</label>
			<?php if ( ! empty( $links ) ) : ?>
				<span class="trustgate-privacy-links">
					<?php foreach ( array_values( $links ) as $index => $link ) : ?>
						<?php if ( 0 < $index ) : ?>
							<span aria-hidden="true"> | </span>
						<?php endif; ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $link['url'] !== $privacy ? ' target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $link['label'] ); ?></a>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
			<input type="hidden" name="trustgate_attempt_token" id="trustgate_attempt_token" value="<?php echo esc_attr( $token ); ?>" />
			<input type="hidden" name="trustgate_reference" id="trustgate_reference" value="" />
			<button type="button" class="button button-secondary" id="trustgate_verify_button">
				<?php esc_html_e( 'Verify Identity', 'hdyhaus-identity-verification' ); ?>
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

		if ( ! $this->has_registration_nonce() ) {
			$errors->add( 'trustgate_invalid_nonce', __( 'Please reload the registration page and try again.', 'hdyhaus-identity-verification' ) );
			return $errors;
		}

		$first_name = isset( $_POST['trustgate_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_name  = isset( $_POST['trustgate_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reference  = isset( $_POST['trustgate_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token      = isset( $_POST['trustgate_attempt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_attempt_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$consent    = isset( $_POST['trustgate_consent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['trustgate_consent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $first_name ) {
			$errors->add( 'trustgate_first_name_required', __( 'Please enter your first name.', 'hdyhaus-identity-verification' ) );
		}

		if ( '' === $last_name ) {
			$errors->add( 'trustgate_last_name_required', __( 'Please enter your last name.', 'hdyhaus-identity-verification' ) );
		}

		if ( ! $consent ) {
			$errors->add( 'trustgate_consent_required', __( 'Please consent to identity verification before registering.', 'hdyhaus-identity-verification' ) );
		}

		if ( ! $this->is_valid_attempt( $token, $reference, $user_email, $first_name, $last_name ) ) {
			$errors->add( 'trustgate_verification_required', __( 'Please complete identity verification before registering.', 'hdyhaus-identity-verification' ) );
		}

		return $errors;
	}

	/**
	 * Use the configured same-site destination after successful registration.
	 *
	 * WordPress writes this value to its redirect_to registration field and
	 * redirects to it only after register_new_user() succeeds.
	 *
	 * @param string       $redirect_to Existing registration redirect URL.
	 * @param int|WP_Error $errors User ID or registration errors.
	 */
	public function filter_registration_redirect( string $redirect_to, int|WP_Error $errors ): string {
		unset( $errors );

		if ( '' !== $redirect_to ) {
			return $redirect_to;
		}

		$settings = get_option( $this->option_name, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$redirect = isset( $settings['success_redirect'] ) ? (string) $settings['success_redirect'] : '';

		$redirect = wp_validate_redirect( $redirect, '' );

		return '' !== $redirect ? add_query_arg( 'trustgate_registration', 'complete', $redirect ) : '';
	}

	/**
	 * Enqueue the success notice styles only on the redirect destination.
	 */
	public function enqueue_success_assets(): void {
		if ( ! $this->is_success_request() ) {
			return;
		}

		wp_enqueue_style(
			'hdyhaus-identity-verification-success',
			TRUSTGATE_REGISTRATION_URL . 'assets/css/success.css',
			array(),
			TRUSTGATE_REGISTRATION_VERSION
		);
	}

	/**
	 * Prepend account activation instructions to the redirect page.
	 *
	 * @param string $content Page content.
	 */
	public function prepend_registration_success_notice( string $content ): string {
		if ( ! $this->is_success_request() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$notice = sprintf(
			'<div class="hdyhaus-identity-verification-success" role="status"><h2>%1$s</h2><p>%2$s</p><p><a class="wp-element-button" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Registration complete', 'hdyhaus-identity-verification' ),
			esc_html__( 'Your account has been created. Check your email for the link to set your password, then sign in.', 'hdyhaus-identity-verification' ),
			esc_url( wp_login_url() ),
			esc_html__( 'Go to login', 'hdyhaus-identity-verification' )
		);

		return $notice . $content;
	}

	/**
	 * Determine whether this request is the post-registration destination.
	 */
	private function is_success_request(): bool {
		$status = isset( $_GET['trustgate_registration'] ) ? sanitize_key( wp_unslash( $_GET['trustgate_registration'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'complete' === $status;
	}

	/**
	 * Store registration metadata.
	 *
	 * @param int $user_id User ID.
	 */
	public function store_user_meta( int $user_id ): void {
		if ( ! $this->has_registration_nonce() ) {
			return;
		}
		$first_name = isset( $_POST['trustgate_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_name  = isset( $_POST['trustgate_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reference  = isset( $_POST['trustgate_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_reference'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token      = isset( $_POST['trustgate_attempt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_attempt_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user       = get_userdata( $user_id );

		if ( ! $user || ! $this->is_valid_attempt( $token, $reference, $user->user_email, $first_name, $last_name ) ) {
			return;
		}

		$attempt = $this->get_attempt( $token );

		if ( '' !== $first_name ) {
			update_user_meta( $user_id, 'first_name', $first_name );
		}

		if ( '' !== $last_name ) {
			update_user_meta( $user_id, 'last_name', $last_name );
		}

		update_user_meta( $user_id, 'trustgate_verified', '1' );
		update_user_meta( $user_id, 'trustgate_verification_status', (string) ( $attempt['verification_status'] ?? 'verified' ) );
		update_user_meta( $user_id, 'trustgate_verified_at', gmdate( 'c' ) );
		update_user_meta( $user_id, 'trustgate_provider', $this->provider->get_slug() );
		update_user_meta( $user_id, 'trustgate_reference', $reference );
		update_user_meta( $user_id, 'trustgate_reference_hash', hash( 'sha256', $reference ) );
		update_user_meta( $user_id, 'trustgate_identity_hash', (string) $attempt['identity_hash'] );
		update_user_meta( $user_id, 'trustgate_consent_at', (string) ( $attempt['consent_at'] ?? gmdate( 'c' ) ) );
		update_user_meta( $user_id, 'trustgate_consent_text', (string) ( $attempt['consent_text'] ?? $this->get_consent_text() ) );
		update_user_meta( $user_id, 'trustgate_consent_version', (string) ( $attempt['consent_version'] ?? $this->get_consent_version() ) );
		$this->finalize_identity( (string) $attempt['identity_hash'], $token, $user_id );

		delete_transient( $this->get_attempt_key( $token ) );
	}

	/**
	 * Confirm provider verification over AJAX.
	 */
	public function confirm_verification(): void {
		check_ajax_referer( 'trustgate_registration', 'nonce' );

		$reference  = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['firstName'] ) ? sanitize_text_field( wp_unslash( $_POST['firstName'] ) ) : '';
		$last_name  = isset( $_POST['lastName'] ) ? sanitize_text_field( wp_unslash( $_POST['lastName'] ) ) : '';
		$consent    = isset( $_POST['consent'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['consent'] ) );
		$attempt    = $this->get_attempt( $token );

		if ( '' === $reference || ! is_email( $email ) || '' === $first_name || '' === $last_name || ! $consent || 'issued' !== ( $attempt['status'] ?? '' ) ) {
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
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
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
		$identity_hash      = isset( $result['identity_hash'] ) ? sanitize_text_field( (string) $result['identity_hash'] ) : '';
		$reservation_key    = $this->get_reference_key( $verified_reference );
		$reservation        = get_transient( $reservation_key );

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $identity_hash ) ) {
			wp_send_json_error(
				array(
					'status' => 'identity_missing',
				),
				400
			);
		}

		if ( ! $this->reserve_identity( $identity_hash, $token ) ) {
			wp_send_json_error(
				array(
					'status' => 'identity_unavailable',
				),
				409
			);
		}

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
				'status'              => 'verified',
				'verification_status' => sanitize_key( (string) $result['status'] ),
				'reference'           => $verified_reference,
				'email_hash'          => $this->hash_email( $email ),
				'first_name_hash'     => $this->hash_name( $first_name ),
				'last_name_hash'      => $this->hash_name( $last_name ),
				'identity_hash'       => $identity_hash,
				'consent_at'          => gmdate( 'c' ),
				'consent_text'        => $this->get_consent_text(),
				'consent_version'     => $this->get_consent_version(),
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
		$posted_token = $this->has_registration_nonce() && isset( $_POST['trustgate_attempt_token'] ) ? sanitize_text_field( wp_unslash( $_POST['trustgate_attempt_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by has_registration_nonce().

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
	 * Validate the public registration form before consuming its fields.
	 */
	private function has_registration_nonce(): bool {
		return isset( $_POST['trustgate_registration_form_nonce'] ) && is_string( $_POST['trustgate_registration_form_nonce'] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trustgate_registration_form_nonce'] ) ), 'trustgate_registration_form' );
	}

	/**
	 * Get the consent wording displayed to and accepted by the registrant.
	 */
	private function get_consent_text(): string {
		$settings = $this->get_settings();
		$text     = isset( $settings['consent_text'] ) ? sanitize_textarea_field( (string) $settings['consent_text'] ) : '';

		return '' !== $text ? $text : self::get_default_consent_text();
	}

	/**
	 * Get the configured provider privacy-policy URL.
	 */
	private function get_provider_privacy_url(): string {
		return $this->get_configured_url( 'provider_privacy_url', self::DEFAULT_PROVIDER_PRIVACY_URL );
	}

	/**
	 * Get the configured provider terms or consent URL.
	 */
	private function get_provider_terms_url(): string {
		return $this->get_configured_url( 'provider_terms_url', self::DEFAULT_PROVIDER_TERMS_URL );
	}

	/**
	 * Resolve the website terms from a published page or a custom URL.
	 */
	private function get_site_terms_url(): string {
		$settings = $this->get_settings();
		$page_id  = (int) ( $settings['site_terms_page_id'] ?? 0 );
		$page     = $page_id > 0 ? get_post( $page_id ) : null;

		if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status ) {
			return (string) get_permalink( $page_id );
		}

		return $this->get_configured_url( 'site_terms_url', '' );
	}

	/**
	 * Derive a stable version from the complete disclosure accepted by the user.
	 */
	private function get_consent_version(): string {
		return hash(
			'sha256',
			implode(
				"\n",
				array(
					$this->provider->get_slug(),
					$this->get_consent_text(),
					$this->get_provider_privacy_url(),
					$this->get_provider_terms_url(),
					get_privacy_policy_url(),
					$this->get_site_terms_url(),
				)
			)
		);
	}

	/**
	 * Get one configured URL, distinguishing an intentionally empty value from
	 * an option that predates the setting.
	 *
	 * @param string $key Setting key.
	 * @param string $fallback_url Default URL for older options.
	 */
	private function get_configured_url( string $key, string $fallback_url ): string {
		$settings = $this->get_settings();

		return array_key_exists( $key, $settings ) ? esc_url_raw( (string) $settings[ $key ] ) : $fallback_url;
	}

	/**
	 * Get saved plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {
		$settings = get_option( $this->option_name, array() );

		return is_array( $settings ) ? $settings : array();
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
	 * @param string $first_name Registration first name.
	 * @param string $last_name Registration last name.
	 */
	private function is_valid_attempt( string $token, string $reference, string $email, string $first_name, string $last_name ): bool {
		$attempt = $this->get_attempt( $token );

		if (
			'verified' !== ( $attempt['status'] ?? '' ) ||
			'' === $reference ||
			! isset( $attempt['reference'], $attempt['email_hash'], $attempt['first_name_hash'], $attempt['last_name_hash'], $attempt['identity_hash'] ) ||
			! hash_equals( $attempt['reference'], $reference ) ||
			! hash_equals( $attempt['email_hash'], $this->hash_email( $email ) ) ||
			! hash_equals( $attempt['first_name_hash'], $this->hash_name( $first_name ) ) ||
			! hash_equals( $attempt['last_name_hash'], $this->hash_name( $last_name ) ) ||
			! $this->identity_reservation_matches( $attempt['identity_hash'], $token )
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
		$reference_hash = hash( 'sha256', $reference );
		$user_ids       = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => 'trustgate_reference_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $reference_hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( array() !== $user_ids ) {
			return true;
		}

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
	 * Reserve an identity fingerprint for one registration attempt.
	 *
	 * @param string $identity_hash Site-specific identity fingerprint.
	 * @param string $token Registration attempt token.
	 */
	private function reserve_identity( string $identity_hash, string $token ): bool {
		$option_name = self::IDENTITY_OPTION_PREFIX . $identity_hash;
		$value       = get_option( $option_name, '' );

		if ( '' === $value || false === $value ) {
			return add_option( $option_name, $this->build_identity_reservation( $token ), '', false );
		}

		if ( $this->identity_reservation_matches( $identity_hash, $token ) ) {
			return true;
		}

		$parts = is_string( $value ) ? explode( ':', $value, 3 ) : array();

		if ( 3 === count( $parts ) && 'reserved' === $parts[0] && (int) $parts[2] < time() ) {
			delete_option( $option_name );

			return add_option( $option_name, $this->build_identity_reservation( $token ), '', false );
		}

		return false;
	}

	/**
	 * Check whether an identity reservation belongs to an attempt.
	 *
	 * @param string $identity_hash Site-specific identity fingerprint.
	 * @param string $token Registration attempt token.
	 */
	private function identity_reservation_matches( string $identity_hash, string $token ): bool {
		$value = get_option( self::IDENTITY_OPTION_PREFIX . $identity_hash, '' );
		$parts = is_string( $value ) ? explode( ':', $value, 3 ) : array();

		return 3 === count( $parts )
			&& 'reserved' === $parts[0]
			&& hash_equals( $parts[1], hash( 'sha256', $token ) )
			&& (int) $parts[2] >= time();
	}

	/**
	 * Convert an attempt reservation into a permanent uniqueness record.
	 *
	 * @param string $identity_hash Site-specific identity fingerprint.
	 * @param string $token Registration attempt token.
	 * @param int    $user_id Registered user ID.
	 */
	private function finalize_identity( string $identity_hash, string $token, int $user_id ): void {
		if ( ! $this->identity_reservation_matches( $identity_hash, $token ) ) {
			return;
		}

		update_option( self::IDENTITY_OPTION_PREFIX . $identity_hash, 'user:' . $user_id, false );
	}

	/**
	 * Build an expiring identity reservation value.
	 *
	 * @param string $token Registration attempt token.
	 */
	private function build_identity_reservation( string $token ): string {
		return sprintf( 'reserved:%s:%d', hash( 'sha256', $token ), time() + self::ATTEMPT_TTL );
	}

	/**
	 * Hash an email for attempt binding.
	 *
	 * @param string $email Email address.
	 */
	private function hash_email( string $email ): string {
		return hash( 'sha256', strtolower( trim( $email ) ) );
	}

	/**
	 * Hash a name for registration-attempt binding.
	 *
	 * @param string $name Name value.
	 */
	private function hash_name( string $name ): string {
		return hash( 'sha256', strtolower( trim( $name ) ) );
	}
}
