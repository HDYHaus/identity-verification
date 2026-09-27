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
 * Registers the TrustGate admin area.
 */
final class SettingsPage {
	/**
	 * Page slug.
	 */
	private const PAGE_SLUG = 'trustgate-registration';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TRUSTGATE_REGISTRATION_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Add the top-level TrustGate admin page.
	 */
	public function add_page(): void {
		add_menu_page(
			__( 'TrustGate Registration', 'trustgate-registration' ),
			__( 'TrustGate', 'trustgate-registration' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'TrustGate Settings', 'trustgate-registration' ),
			__( 'Settings', 'trustgate-registration' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a direct settings link on the Plugins screen.
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'trustgate-registration' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enqueue admin styles only on TrustGate screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'trustgate-registration-admin',
			TRUSTGATE_REGISTRATION_URL . 'assets/css/admin.css',
			array(),
			TRUSTGATE_REGISTRATION_VERSION
		);
	}

	/**
	 * Register settings and tab-specific fields.
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

		$this->register_provider_fields();
		$this->register_registration_fields();
		$this->register_advanced_fields();
	}

	/**
	 * Register provider fields.
	 */
	private function register_provider_fields(): void {
		$page = self::PAGE_SLUG . '-provider';

		add_settings_section(
			'trustgate_registration_provider',
			__( 'Provider', 'trustgate-registration' ),
			array( $this, 'render_provider_section' ),
			$page
		);

		$fields = array(
			'provider'              => __( 'Verification provider', 'trustgate-registration' ),
			'mode'                  => __( 'Active environment', 'trustgate-registration' ),
			'test_public_key'       => __( 'Sandbox Widget Key', 'trustgate-registration' ),
			'test_configuration_id' => __( 'Sandbox Configuration ID', 'trustgate-registration' ),
			'test_secret_key'       => __( 'Sandbox Secret API Key', 'trustgate-registration' ),
			'test_organisation_id'  => __( 'Sandbox Organisation ID', 'trustgate-registration' ),
			'live_public_key'       => __( 'Live Widget Key', 'trustgate-registration' ),
			'live_configuration_id' => __( 'Live Configuration ID', 'trustgate-registration' ),
			'live_secret_key'       => __( 'Live Secret API Key', 'trustgate-registration' ),
			'live_organisation_id'  => __( 'Live Organisation ID', 'trustgate-registration' ),
		);

		$this->add_fields( $page, 'trustgate_registration_provider', $fields );
	}

	/**
	 * Register registration fields.
	 */
	private function register_registration_fields(): void {
		$page = self::PAGE_SLUG . '-registration';

		add_settings_section(
			'trustgate_registration_flow',
			__( 'Registration', 'trustgate-registration' ),
			array( $this, 'render_registration_section' ),
			$page
		);

		$this->add_fields(
			$page,
			'trustgate_registration_flow',
			array(
				'success_redirect' => __( 'Success Redirect URL', 'trustgate-registration' ),
			)
		);
	}

	/**
	 * Register advanced fields.
	 */
	private function register_advanced_fields(): void {
		$page = self::PAGE_SLUG . '-advanced';

		add_settings_section(
			'trustgate_registration_advanced',
			__( 'Advanced', 'trustgate-registration' ),
			array( $this, 'render_advanced_section' ),
			$page
		);

		$this->add_fields(
			$page,
			'trustgate_registration_advanced',
			array(
				'app_id'          => __( 'Legacy App ID', 'trustgate-registration' ),
				'status_endpoint' => __( 'Status Endpoint Override', 'trustgate-registration' ),
			)
		);
	}

	/**
	 * Register a list of fields.
	 *
	 * @param string                $page Page identifier.
	 * @param string                $section Section identifier.
	 * @param array<string, string> $fields Field labels keyed by setting name.
	 */
	private function add_fields( string $page, string $section, array $fields ): void {
		foreach ( $fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'render_field' ),
				$page,
				$section,
				array(
					'key'   => $field,
					'label' => $label,
				)
			);
		}
	}

	/**
	 * Sanitize settings while preserving fields on other tabs.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string, string>
	 */
	public function sanitize_settings( mixed $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$current  = get_option( $this->option_name, array() );
		$current  = is_array( $current ) ? $current : array();
		$clean    = array_map( 'strval', $current );

		$text_fields = array(
			'test_public_key',
			'test_configuration_id',
			'test_secret_key',
			'test_organisation_id',
			'live_public_key',
			'live_configuration_id',
			'live_secret_key',
			'live_organisation_id',
			'app_id',
		);

		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $settings ) ) {
				$clean[ $field ] = sanitize_text_field( (string) $settings[ $field ] );
			}
		}

		if ( array_key_exists( 'provider', $settings ) ) {
			$clean['provider'] = 'prembly';
		}

		if ( array_key_exists( 'mode', $settings ) ) {
			$clean['mode'] = 'live' === $settings['mode'] ? 'live' : 'test';
		}

		foreach ( array( 'status_endpoint', 'success_redirect' ) as $field ) {
			if ( array_key_exists( $field, $settings ) ) {
				$clean[ $field ] = esc_url_raw( (string) $settings[ $field ] );
			}
		}

		if ( array_key_exists( 'test_secret_key', $settings ) ) {
			unset( $clean['secret_key'], $clean['organisation_id'], $clean['configuration_id'] );
		}

		return $clean;
	}

	/**
	 * Render the provider section description.
	 */
	public function render_provider_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Keep Sandbox and Live credentials separate. Changing the active environment never overwrites the other environment.', 'trustgate-registration' )
		);
	}

	/**
	 * Render the registration section description.
	 */
	public function render_registration_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Control what happens after WordPress creates a verified account.', 'trustgate-registration' )
		);
	}

	/**
	 * Render the advanced section description.
	 */
	public function render_advanced_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Leave these values blank unless Prembly support or a legacy integration specifically requires them.', 'trustgate-registration' )
		);
	}

	/**
	 * Render a settings field.
	 *
	 * @param array<string, string> $args Field args.
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'];
		$value = $this->get_display_value( $key );
		$name  = sprintf( '%s[%s]', $this->option_name, $key );

		if ( 'provider' === $key ) {
			?>
			<select name="<?php echo esc_attr( $name ); ?>">
				<option value="prembly" selected><?php esc_html_e( 'Prembly', 'trustgate-registration' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Additional providers will appear here as their adapters are added.', 'trustgate-registration' ); ?></p>
			<?php
			return;
		}

		if ( 'mode' === $key ) {
			?>
			<select name="<?php echo esc_attr( $name ); ?>">
				<option value="test" <?php selected( 'test', '' !== $value ? $value : 'test' ); ?>>
					<?php esc_html_e( 'Sandbox / Test', 'trustgate-registration' ); ?>
				</option>
				<option value="live" <?php selected( 'live', $value ); ?>>
					<?php esc_html_e( 'Live / Production', 'trustgate-registration' ); ?>
				</option>
			</select>
			<p class="description"><?php esc_html_e( 'Live mode uses only the Live credentials below.', 'trustgate-registration' ); ?></p>
			<?php
			return;
		}

		$type = in_array( $key, array( 'status_endpoint', 'success_redirect' ), true ) ? 'url' : 'text';

		if ( str_contains( $key, 'secret_key' ) ) {
			$type = 'password';
		}

		$descriptions = array(
			'test_public_key'       => __( 'Prembly SDK Setup > Integration > Widget Key while Sandbox is selected.', 'trustgate-registration' ),
			'test_configuration_id' => __( 'Prembly SDK Setup > Copy Config ID for the Sandbox widget.', 'trustgate-registration' ),
			'test_secret_key'       => __( 'Prembly API Integrations > Secret Key while Sandbox is selected. Used only for server-side confirmation.', 'trustgate-registration' ),
			'test_organisation_id'  => __( 'Required with the Sandbox secret key for authenticated SDK session lookups.', 'trustgate-registration' ),
			'live_public_key'       => __( 'Prembly SDK Setup > Integration > Widget Key while Production is selected.', 'trustgate-registration' ),
			'live_configuration_id' => __( 'Prembly SDK Setup > Copy Config ID for the Live widget.', 'trustgate-registration' ),
			'live_secret_key'       => __( 'Prembly API Integrations > Secret Key while Production is selected. Used only for server-side confirmation.', 'trustgate-registration' ),
			'live_organisation_id'  => __( 'Required with the Live secret key for authenticated SDK session lookups.', 'trustgate-registration' ),
			'app_id'                => __( 'Optional. Used only by older Identitypass endpoints that explicitly require an App ID.', 'trustgate-registration' ),
			'status_endpoint'       => __( 'Optional. Leave blank to use Prembly\'s current SDK session endpoint. Use {id} for the session ID.', 'trustgate-registration' ),
			'success_redirect'      => __( 'Optional. The page to visit after a verified account is registered.', 'trustgate-registration' ),
		);
		$description  = $descriptions[ $key ] ?? '';
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			<?php echo 'password' === $type ? ' autocomplete="new-password"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		/>
		<?php if ( '' !== $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get a saved value with fallbacks for settings created before 0.1.3.
	 *
	 * @param string $key Setting key.
	 */
	private function get_display_value( string $key ): string {
		$settings = get_option( $this->option_name, array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( isset( $settings[ $key ] ) ) {
			return (string) $settings[ $key ];
		}

		$legacy_keys = array(
			'test_secret_key'       => 'secret_key',
			'test_organisation_id'  => 'organisation_id',
			'test_configuration_id' => 'configuration_id',
		);

		$legacy_key = $legacy_keys[ $key ] ?? '';

		return '' !== $legacy_key && isset( $settings[ $legacy_key ] ) ? (string) $settings[ $legacy_key ] : '';
	}

	/**
	 * Render the TrustGate admin page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab  = $this->get_current_tab();
		$tabs = array(
			'provider'     => __( 'Provider', 'trustgate-registration' ),
			'registration' => __( 'Registration', 'trustgate-registration' ),
			'advanced'     => __( 'Advanced', 'trustgate-registration' ),
		);
		?>
		<div class="wrap trustgate-admin">
			<div class="trustgate-admin__header">
				<div>
					<h1><?php esc_html_e( 'TrustGate Registration', 'trustgate-registration' ); ?></h1>
					<p><?php esc_html_e( 'Identity verification for WordPress account registration.', 'trustgate-registration' ); ?></p>
				</div>
				<span class="trustgate-admin__provider">
					<?php
					printf(
						/* translators: %s: provider name */
						esc_html__( 'Provider: %s', 'trustgate-registration' ),
						esc_html( $this->provider->get_label() )
					);
					?>
				</span>
			</div>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'TrustGate settings', 'trustgate-registration' ); ?>">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a
						class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab_key ) ); ?>"
					>
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php $this->render_environment_notice( $tab ); ?>

			<form method="post" action="options.php" class="trustgate-admin__form">
				<?php
				settings_fields( 'trustgate_registration' );
				do_settings_sections( self::PAGE_SLUG . '-' . $tab );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the active environment notice on the provider tab.
	 *
	 * @param string $tab Current tab.
	 */
	private function render_environment_notice( string $tab ): void {
		if ( 'provider' !== $tab ) {
			return;
		}

		$mode     = $this->get_display_value( 'mode' );
		$mode     = 'live' === $mode ? 'live' : 'test';
		$label    = 'live' === $mode ? __( 'Live / Production', 'trustgate-registration' ) : __( 'Sandbox / Test', 'trustgate-registration' );
		$required = array(
			'public_key'       => __( 'Widget Key', 'trustgate-registration' ),
			'configuration_id' => __( 'Configuration ID', 'trustgate-registration' ),
			'secret_key'       => __( 'Secret API Key', 'trustgate-registration' ),
			'organisation_id'  => __( 'Organisation ID', 'trustgate-registration' ),
		);
		$missing  = array();

		foreach ( $required as $key => $field_label ) {
			if ( '' === $this->get_display_value( $mode . '_' . $key ) ) {
				$missing[] = $field_label;
			}
		}

		$notice_class = empty( $missing ) ? 'notice-success' : 'notice-warning';
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> inline trustgate-admin__notice">
			<p>
				<strong><?php esc_html_e( 'Active environment:', 'trustgate-registration' ); ?></strong>
				<?php echo esc_html( $label ); ?>
				<?php if ( empty( $missing ) ) : ?>
					<?php esc_html_e( ' - configuration complete.', 'trustgate-registration' ); ?>
				<?php else : ?>
					<?php
					printf(
						/* translators: %s: comma-separated list of missing fields */
						esc_html__( ' - missing: %s.', 'trustgate-registration' ),
						esc_html( implode( ', ', $missing ) )
					);
					?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the selected settings tab.
	 */
	private function get_current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'provider'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $tab, array( 'provider', 'registration', 'advanced' ), true ) ? $tab : 'provider';
	}
}
