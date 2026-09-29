<?php
/**
 * Admin settings page.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Admin;

use HDYHaus\TrustGateRegistration\Contracts\VerificationProvider;
use HDYHaus\TrustGateRegistration\Providers\ProviderRegistry;

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
	 * Verification provider registry.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @param string           $option_name Settings option name.
	 * @param ProviderRegistry $registry Verification provider registry.
	 */
	public function __construct( string $option_name, ProviderRegistry $registry ) {
		$this->option_name = $option_name;
		$this->registry    = $registry;
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

		$this->register_prembly_fields();
		$this->register_external_provider_fields();
		$this->register_registration_fields();
	}

	/**
	 * Register Prembly fields.
	 */
	private function register_prembly_fields(): void {
		$page = self::PAGE_SLUG . '-prembly';

		add_settings_section(
			'trustgate_registration_prembly',
			__( 'Prembly', 'trustgate-registration' ),
			array( $this, 'render_prembly_section' ),
			$page
		);

		$fields = array(
			'provider'              => __( 'Enable Prembly', 'trustgate-registration' ),
			'mode'                  => __( 'Active environment', 'trustgate-registration' ),
			'test_public_key'       => __( 'Sandbox Widget Key', 'trustgate-registration' ),
			'test_configuration_id' => __( 'Sandbox Configuration ID', 'trustgate-registration' ),
			'live_public_key'       => __( 'Live Widget Key', 'trustgate-registration' ),
			'live_configuration_id' => __( 'Live Configuration ID', 'trustgate-registration' ),
		);

		$this->add_fields( $page, 'trustgate_registration_prembly', $fields, 'prembly' );

		add_settings_section(
			'trustgate_registration_prembly_advanced',
			__( 'Advanced Prembly Settings', 'trustgate-registration' ),
			array( $this, 'render_prembly_advanced_section' ),
			$page
		);

		$this->add_fields(
			$page,
			'trustgate_registration_prembly_advanced',
			array(
				'status_endpoint' => __( 'Status Endpoint Override', 'trustgate-registration' ),
			)
		);
	}

	/**
	 * Register activation fields and extension hooks for additional providers.
	 */
	private function register_external_provider_fields(): void {
		foreach ( $this->registry->all() as $slug => $provider ) {
			if ( 'prembly' === $slug ) {
				continue;
			}

			$page    = self::PAGE_SLUG . '-' . $slug;
			$section = 'trustgate_registration_provider_' . $slug;

			add_settings_section(
				$section,
				$provider->get_label(),
				'__return_empty_string',
				$page
			);

			$this->add_fields(
				$page,
				$section,
				array(
					'provider' => sprintf(
						/* translators: %s: provider name */
						__( 'Enable %s', 'trustgate-registration' ),
						$provider->get_label()
					),
				),
				$slug
			);

			/**
			 * Fires while an external provider's settings tab is being registered.
			 *
			 * @param string $slug Provider slug.
			 * @param string $page Settings API page identifier.
			 * @param string $option_name TrustGate option name.
			 */
			do_action( 'trustgate_registration_register_provider_settings', $slug, $page, $this->option_name );
		}
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
	 * Register a list of fields.
	 *
	 * @param string                $page Page identifier.
	 * @param string                $section Section identifier.
	 * @param array<string, string> $fields Field labels keyed by setting name.
	 * @param string                $provider_slug Provider slug for activation controls.
	 */
	private function add_fields( string $page, string $section, array $fields, string $provider_slug = '' ): void {
		foreach ( $fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'render_field' ),
				$page,
				$section,
				array(
					'key'           => $field,
					'label'         => $label,
					'provider_slug' => $provider_slug,
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
		$tab      = isset( $settings['settings_tab'] ) && is_string( $settings['settings_tab'] )
			? sanitize_key( $settings['settings_tab'] )
			: '';

		if ( 'registration' === $tab ) {
			if ( array_key_exists( 'success_redirect', $settings ) ) {
				$clean['success_redirect'] = esc_url_raw( (string) $settings['success_redirect'] );
			}

			return $clean;
		}

		$tab_provider = $this->registry->get( $tab );

		if ( null === $tab_provider ) {
			return $clean;
		}

		if ( array_key_exists( 'provider', $settings ) ) {
			$submitted_provider = is_string( $settings['provider'] ) ? sanitize_key( $settings['provider'] ) : '';

			if ( $tab === $submitted_provider ) {
				$clean['provider'] = $tab;
			} else {
				add_settings_error(
					$this->option_name,
					'trustgate_provider_conflict',
					__( 'TrustGate rejected conflicting provider settings. Only one provider can be enabled.', 'trustgate-registration' ),
					'error'
				);
			}
		} else {
			$current_provider = $this->registry->get_active_slug( $current );

			if ( '' === $current_provider || $tab === $current_provider ) {
				$clean['provider'] = '';
			}
		}

		if ( 'prembly' !== $tab ) {
			/**
			 * Filters sanitized settings submitted from an external provider tab.
			 *
			 * @param array<string, string> $clean Preserved and sanitized settings.
			 * @param array<string, mixed>  $settings Submitted settings.
			 * @param string                $tab Provider slug.
			 * @param VerificationProvider  $tab_provider Provider instance.
			 */
			return apply_filters( 'trustgate_registration_sanitize_provider_settings', $clean, $settings, $tab, $tab_provider );
		}

		$text_fields = array(
			'test_public_key',
			'test_configuration_id',
			'live_public_key',
			'live_configuration_id',
		);

		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $settings ) ) {
				$clean[ $field ] = sanitize_text_field( (string) $settings[ $field ] );
			}
		}

		if ( array_key_exists( 'mode', $settings ) ) {
			$clean['mode'] = 'live' === $settings['mode'] ? 'live' : 'test';
		}

		if ( array_key_exists( 'status_endpoint', $settings ) ) {
			$clean['status_endpoint'] = esc_url_raw( (string) $settings['status_endpoint'] );
		}

		return $clean;
	}

	/**
	 * Render the Prembly section description.
	 */
	public function render_prembly_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Keep Sandbox and Live widget settings separate. Changing the active environment never overwrites the other environment.', 'trustgate-registration' )
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
	 * Render the advanced Prembly section description.
	 */
	public function render_prembly_advanced_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Leave this value blank unless Prembly support supplies a different session endpoint.', 'trustgate-registration' )
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
			$settings      = $this->get_settings();
			$provider_slug = sanitize_key( $args['provider_slug'] ?? '' );
			$provider      = $this->registry->get( $provider_slug );
			$is_enabled    = $provider_slug === $this->registry->get_active_slug( $settings );

			if ( null === $provider ) {
				return;
			}
			?>
			<label for="trustgate-provider-<?php echo esc_attr( $provider_slug ); ?>">
				<input
					type="checkbox"
					id="trustgate-provider-<?php echo esc_attr( $provider_slug ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $provider_slug ); ?>"
					<?php checked( $is_enabled ); ?>
				/>
				<?php
				printf(
					/* translators: %s: provider name */
					esc_html__( 'Use %s for identity verification during registration', 'trustgate-registration' ),
					esc_html( $provider->get_label() )
				);
				?>
			</label>
			<p class="description"><?php esc_html_e( 'Enabling this provider automatically disables any other active provider. Unchecking it allows normal WordPress registration.', 'trustgate-registration' ); ?></p>
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
			<p class="description"><?php esc_html_e( 'Live mode uses only the Live widget settings below.', 'trustgate-registration' ); ?></p>
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
			'live_public_key'       => __( 'Prembly SDK Setup > Integration > Widget Key while Production is selected.', 'trustgate-registration' ),
			'live_configuration_id' => __( 'Prembly SDK Setup > Copy Config ID for the Live widget.', 'trustgate-registration' ),
			'status_endpoint'       => __( 'Optional. Leave blank to use Prembly\'s current session-ID-only SDK endpoint. Use {id} for the session ID.', 'trustgate-registration' ),
			'success_redirect'      => __( 'Optional same-site URL visited after WordPress successfully creates the verified account.', 'trustgate-registration' ),
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
		$settings = $this->get_settings();

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
	 * Get saved plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {
		$settings = get_option( $this->option_name, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Render the TrustGate admin page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings        = $this->get_settings();
		$active_provider = $this->registry->resolve( $settings );
		$tabs            = $this->get_tabs();
		$tab             = $this->get_current_tab( $tabs );
		$status_label    = null !== $active_provider ? $active_provider->get_label() : __( 'Disabled', 'trustgate-registration' );
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
						/* translators: %s: active provider name or disabled status */
						esc_html__( 'Verification: %s', 'trustgate-registration' ),
						esc_html( $status_label )
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

			<?php $this->render_status_notice( $tab, $active_provider ); ?>

			<form method="post" action="options.php" class="trustgate-admin__form">
				<?php
				settings_fields( 'trustgate_registration' );
				?>
				<input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[settings_tab]" value="<?php echo esc_attr( $tab ); ?>" />
				<?php
				do_settings_sections( self::PAGE_SLUG . '-' . $tab );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render provider status and environment notices.
	 *
	 * @param string                    $tab Current tab.
	 * @param VerificationProvider|null $active_provider Active provider.
	 */
	private function render_status_notice( string $tab, ?VerificationProvider $active_provider ): void {
		if ( null === $active_provider ) {
			?>
			<div class="notice notice-warning inline trustgate-admin__notice">
				<p>
					<strong><?php esc_html_e( 'Identity verification is disabled.', 'trustgate-registration' ); ?></strong>
					<?php esc_html_e( ' Visitors can register through the normal WordPress registration flow without verification.', 'trustgate-registration' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		if ( null === $this->registry->get( $tab ) ) {
			return;
		}

		if ( $tab !== $active_provider->get_slug() ) {
			?>
			<div class="notice notice-info inline trustgate-admin__notice">
				<p>
					<?php
					printf(
						/* translators: %s: active provider name */
						esc_html__( 'This provider is inactive. %s currently handles registration verification.', 'trustgate-registration' ),
						esc_html( $active_provider->get_label() )
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		if ( 'prembly' !== $tab ) {
			return;
		}

		$mode  = $this->get_display_value( 'mode' );
		$mode  = 'live' === $mode ? 'live' : 'test';
		$label = 'live' === $mode ? __( 'Live / Production', 'trustgate-registration' ) : __( 'Sandbox / Test', 'trustgate-registration' );

		$required = array(
			'public_key'       => __( 'Widget Key', 'trustgate-registration' ),
			'configuration_id' => __( 'Configuration ID', 'trustgate-registration' ),
		);

		$missing_required = array();

		foreach ( $required as $key => $field_label ) {
			if ( '' === $this->get_display_value( $mode . '_' . $key ) ) {
				$missing_required[] = $field_label;
			}
		}

		$notice_class = empty( $missing_required ) ? 'notice-success' : 'notice-warning';
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> inline trustgate-admin__notice">
			<p>
				<strong><?php esc_html_e( 'Active environment:', 'trustgate-registration' ); ?></strong>
				<?php echo esc_html( $label ); ?>
				<?php if ( ! empty( $missing_required ) ) : ?>
					<?php
					printf(
						/* translators: %s: comma-separated list of missing fields */
						esc_html__( ' - missing required widget settings: %s.', 'trustgate-registration' ),
						esc_html( implode( ', ', $missing_required ) )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( ' - configuration complete.', 'trustgate-registration' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get all settings tabs.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs(): array {
		$tabs = array();

		foreach ( $this->registry->all() as $slug => $provider ) {
			$tabs[ $slug ] = $provider->get_label();
		}

		$tabs['registration'] = __( 'Registration', 'trustgate-registration' );

		return $tabs;
	}

	/**
	 * Get the selected settings tab.
	 *
	 * @param array<string, string> $tabs Available tabs.
	 */
	private function get_current_tab( array $tabs ): string {
		$default = (string) array_key_first( $tabs );
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return isset( $tabs[ $tab ] ) ? $tab : $default;
	}
}
