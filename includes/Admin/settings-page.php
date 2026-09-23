<?php
/**
 * Admin settings page.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Admin;

use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the TrustGate settings page.
 */
final class SettingsPage {
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
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'TrustGate Registration', 'trustgate-registration' ),
			__( 'TrustGate Registration', 'trustgate-registration' ),
			'manage_options',
			'trustgate-registration',
			array( $this, 'render' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {
		register_setting(
			'trustgate_registration',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'trustgate_registration_provider',
			__( 'Provider', 'trustgate-registration' ),
			'__return_false',
			'trustgate-registration'
		);

		$fields = array(
			'mode'             => __( 'Mode', 'trustgate-registration' ),
			'test_public_key'  => __( 'Test Public Key', 'trustgate-registration' ),
			'live_public_key'  => __( 'Live Public Key', 'trustgate-registration' ),
			'configuration_id' => __( 'Configuration ID', 'trustgate-registration' ),
			'success_redirect' => __( 'Success Redirect URL', 'trustgate-registration' ),
		);

		foreach ( $fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'render_field' ),
				'trustgate-registration',
				'trustgate_registration_provider',
				array(
					'key'   => $field,
					'label' => $label,
				)
			);
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string, string>
	 */
	public function sanitize_settings( mixed $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$mode     = isset( $settings['mode'] ) && 'live' === $settings['mode'] ? 'live' : 'test';

		return array(
			'mode'             => $mode,
			'test_public_key'  => sanitize_text_field( (string) ( $settings['test_public_key'] ?? '' ) ),
			'live_public_key'  => sanitize_text_field( (string) ( $settings['live_public_key'] ?? '' ) ),
			'configuration_id' => sanitize_text_field( (string) ( $settings['configuration_id'] ?? '' ) ),
			'success_redirect' => esc_url_raw( (string) ( $settings['success_redirect'] ?? '' ) ),
		);
	}

	/**
	 * Render a settings field.
	 *
	 * @param array<string, string> $args Field args.
	 */
	public function render_field( array $args ): void {
		$settings = get_option( $this->option_name, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$key      = $args['key'];
		$value    = (string) ( $settings[ $key ] ?? '' );
		$name     = sprintf( '%s[%s]', $this->option_name, $key );

		if ( 'mode' === $key ) {
			?>
			<select name="<?php echo esc_attr( $name ); ?>">
					<option value="test" <?php selected( 'test', '' !== $value ? $value : 'test' ); ?>>
					<?php esc_html_e( 'Test', 'trustgate-registration' ); ?>
				</option>
				<option value="live" <?php selected( 'live', $value ); ?>>
					<?php esc_html_e( 'Live', 'trustgate-registration' ); ?>
				</option>
			</select>
			<?php
			return;
		}

		$type = 'success_redirect' === $key ? 'url' : 'text';
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TrustGate Registration', 'trustgate-registration' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: provider name */
					esc_html__( 'Current provider: %s.', 'trustgate-registration' ),
					esc_html( $this->provider->get_label() )
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'trustgate_registration' );
				do_settings_sections( 'trustgate-registration' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
