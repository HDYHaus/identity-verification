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
use HDYHaus\TrustGateRegistration\Registration\RegistrationController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the HDYHaus Identity Verification admin area.
 */
final class SettingsPage {
	/**
	 * Page slug.
	 */
	private const PAGE_SLUG = 'hdyhaus-identity-verification';

	/**
	 * Global registration settings page slug.
	 */
	private const REGISTRATION_SLUG = 'hdyhaus-identity-verification-flow';

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
		add_action( 'admin_post_trustgate_registration_save', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TRUSTGATE_REGISTRATION_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Save HDYHaus Identity Verification settings from the plugin admin page.
	 */
	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage HDYHaus Identity Verification settings.', 'hdyhaus-identity-verification' ) );
		}

		check_admin_referer( 'trustgate_registration_save', 'trustgate_registration_nonce' );

		$submitted = isset( $_POST[ $this->option_name ] ) && is_array( $_POST[ $this->option_name ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- update_option applies the registered sanitizer.
			? wp_unslash( $_POST[ $this->option_name ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- update_option applies the registered sanitizer.
			: array();

		// Avoid update_option() falling through to add_option() and sanitizing a new value twice.
		if ( null === get_option( $this->option_name, null ) ) {
			add_option( $this->option_name, $submitted, '', false );
		} else {
			update_option( $this->option_name, $submitted, false );
		}

		$tabs = $this->get_tabs();
		$tab  = isset( $submitted['settings_tab'] ) && is_string( $submitted['settings_tab'] )
			? sanitize_key( $submitted['settings_tab'] )
			: (string) array_key_first( $tabs );
		$tab  = isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => 'registration' === $tab ? self::REGISTRATION_SLUG : self::PAGE_SLUG,
					'tab'                      => $tab,
					'trustgate-settings-saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Add the top-level HDYHaus Identity Verification admin page.
	 */
	public function add_page(): void {
		add_menu_page(
			__( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ),
			__( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'HDYHaus Identity Verification Settings', 'hdyhaus-identity-verification' ),
			__( 'Settings', 'hdyhaus-identity-verification' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'HDYHaus Identity Verification Settings', 'hdyhaus-identity-verification' ),
			__( 'Registration', 'hdyhaus-identity-verification' ),
			'manage_options',
			self::REGISTRATION_SLUG,
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
			esc_html__( 'Settings', 'hdyhaus-identity-verification' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enqueue admin styles only on HDYHaus Identity Verification screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'toplevel_page_' . self::PAGE_SLUG, 'trustgate_page_' . self::REGISTRATION_SLUG ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'hdyhaus-identity-verification-admin',
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
			__( 'Prembly', 'hdyhaus-identity-verification' ),
			array( $this, 'render_prembly_section' ),
			$page
		);

		$fields = array(
			'provider'              => __( 'Enable Prembly', 'hdyhaus-identity-verification' ),
			'mode'                  => __( 'Active environment', 'hdyhaus-identity-verification' ),
			'test_public_key'       => __( 'Sandbox Widget Key', 'hdyhaus-identity-verification' ),
			'test_configuration_id' => __( 'Sandbox Configuration ID', 'hdyhaus-identity-verification' ),
			'live_public_key'       => __( 'Live Widget Key', 'hdyhaus-identity-verification' ),
			'live_configuration_id' => __( 'Live Configuration ID', 'hdyhaus-identity-verification' ),
		);

		$this->add_fields( $page, 'trustgate_registration_prembly', $fields, 'prembly' );
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
						__( 'Enable %s', 'hdyhaus-identity-verification' ),
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
			 * @param string $option_name HDYHaus Identity Verification option name.
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
			__( 'Registration', 'hdyhaus-identity-verification' ),
			array( $this, 'render_registration_section' ),
			$page
		);

		$this->add_fields(
			$page,
			'trustgate_registration_flow',
			array(
				'consent_text'         => __( 'Consent message', 'hdyhaus-identity-verification' ),
				'provider_privacy_url' => __( 'Verification Provider Privacy Policy URL', 'hdyhaus-identity-verification' ),
				'provider_terms_url'   => __( 'Verification Provider Terms / Consent URL', 'hdyhaus-identity-verification' ),
				'site_privacy_policy'  => __( 'Website Privacy Policy', 'hdyhaus-identity-verification' ),
				'site_terms_page_id'   => __( 'Website Terms / Disclaimer Page', 'hdyhaus-identity-verification' ),
				'site_terms_url'       => __( 'Website Terms / Disclaimer URL', 'hdyhaus-identity-verification' ),
				'success_redirect'     => __( 'Success Redirect URL', 'hdyhaus-identity-verification' ),
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
			$field_id = 'provider' === $field && '' !== $provider_slug
				? 'trustgate-provider-' . $provider_slug
				: 'trustgate-' . str_replace( '_', '-', $field );

			add_settings_field(
				$field,
				$label,
				array( $this, 'render_field' ),
				$page,
				$section,
				array(
					'key'           => $field,
					'label'         => $label,
					'label_for'     => 'site_privacy_policy' === $field ? '' : $field_id,
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
		unset( $clean['status_endpoint'] );
		$tab = isset( $settings['settings_tab'] ) && is_string( $settings['settings_tab'] )
			? sanitize_key( $settings['settings_tab'] )
			: '';

		if ( 'registration' === $tab ) {
			$clean['settings_tab'] = 'registration';

			if ( array_key_exists( 'consent_text', $settings ) ) {
					$consent_text = sanitize_textarea_field( (string) $settings['consent_text'] );

					$clean['consent_text'] = '' !== $consent_text ? $consent_text : RegistrationController::get_default_consent_text();
			}

			foreach ( array( 'provider_privacy_url', 'provider_terms_url', 'site_terms_url' ) as $url_field ) {
				if ( array_key_exists( $url_field, $settings ) ) {
					$clean[ $url_field ] = esc_url_raw( (string) $settings[ $url_field ] );
				}
			}

			if ( array_key_exists( 'site_terms_page_id', $settings ) ) {
				$page_id                     = absint( $settings['site_terms_page_id'] );
				$page                        = $page_id > 0 ? get_post( $page_id ) : null;
				$clean['site_terms_page_id'] = $page && 'page' === $page->post_type && 'publish' === $page->post_status ? (string) $page_id : '0';
			}

			if ( array_key_exists( 'success_redirect', $settings ) ) {
				$clean['success_redirect'] = esc_url_raw( (string) $settings['success_redirect'] );
			}

			return $clean;
		}

		$tab_provider = $this->registry->get( $tab );

		if ( null === $tab_provider ) {
			return $clean;
		}

		$clean['settings_tab'] = $tab;

		if ( array_key_exists( 'provider', $settings ) ) {
			$submitted_provider = is_string( $settings['provider'] ) ? sanitize_key( $settings['provider'] ) : '';

			if ( $tab === $submitted_provider ) {
				$clean['provider'] = $tab;
			} else {
				add_settings_error(
					$this->option_name,
					'trustgate_provider_conflict',
					__( 'HDYHaus Identity Verification rejected conflicting provider settings. Only one provider can be enabled.', 'hdyhaus-identity-verification' ),
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

		return $clean;
	}

	/**
	 * Render the Prembly section description.
	 */
	public function render_prembly_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Keep Sandbox and Live widget settings separate. Changing the active environment never overwrites the other environment.', 'hdyhaus-identity-verification' )
		);
	}

	/**
	 * Render the registration section description.
	 */
	public function render_registration_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'These settings apply to the active verification provider. Set the consent message, the provider\'s policy links, and your website\'s own policy links for registration. The consent message is plain text.', 'hdyhaus-identity-verification' )
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
		$id    = $args['label_for'];

		if ( 'site_privacy_policy' === $key ) {
			$privacy_url = get_privacy_policy_url();
			if ( '' !== $privacy_url ) {
				printf( '<a href="%s">%s</a>', esc_url( $privacy_url ), esc_html__( 'Website Privacy Policy', 'hdyhaus-identity-verification' ) );
			} else {
				esc_html_e( 'No WordPress Privacy Policy page is configured.', 'hdyhaus-identity-verification' );
			}
			printf( '<p class="description">%s <a href="%s">%s</a></p>', esc_html__( 'Recommended: choose your website\'s Privacy Policy page in WordPress. HDYHaus Identity Verification automatically includes its link on registration.', 'hdyhaus-identity-verification' ), esc_url( admin_url( 'options-privacy.php' ) ), esc_html__( 'Privacy Settings', 'hdyhaus-identity-verification' ) );
			return;
		}

		if ( 'site_terms_page_id' === $key ) {
			?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value="0"><?php esc_html_e( 'Use the custom URL below', 'hdyhaus-identity-verification' ); ?></option>
				<?php foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $page ) : ?>
					<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( (string) $page->ID, $value ); ?>><?php echo esc_html( $page->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Recommended: select a published page containing your website\'s registration terms or disclaimer. A selected page takes precedence over the custom URL below.', 'hdyhaus-identity-verification' ); ?></p>
			<?php
			return;
		}

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
					esc_html__( 'Use %s for identity verification during registration', 'hdyhaus-identity-verification' ),
					esc_html( $provider->get_label() )
				);
				?>
			</label>
			<p class="description"><?php esc_html_e( 'Enabling this provider automatically disables any other active provider. Unchecking it allows normal WordPress registration.', 'hdyhaus-identity-verification' ); ?></p>
			<?php
			return;
		}

		if ( 'mode' === $key ) {
			?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<option value="test" <?php selected( 'test', '' !== $value ? $value : 'test' ); ?>>
					<?php esc_html_e( 'Sandbox / Test', 'hdyhaus-identity-verification' ); ?>
				</option>
				<option value="live" <?php selected( 'live', $value ); ?>>
					<?php esc_html_e( 'Live / Production', 'hdyhaus-identity-verification' ); ?>
				</option>
			</select>
			<p class="description"><?php esc_html_e( 'Live mode uses only the Live widget settings below.', 'hdyhaus-identity-verification' ); ?></p>
			<?php
			return;
		}

		if ( 'consent_text' === $key ) {
			?>
			<textarea
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				rows="4"
				class="large-text"
			><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Plain-text statement shown beside the required consent checkbox. The exact accepted wording is stored with successful registrations.', 'hdyhaus-identity-verification' ); ?></p>
			<?php
			return;
		}

		$url_fields = array( 'provider_privacy_url', 'provider_terms_url', 'site_terms_url', 'success_redirect' );
		$type       = in_array( $key, $url_fields, true ) ? 'url' : 'text';

		if ( str_contains( $key, 'secret_key' ) ) {
			$type = 'password';
		}

		$descriptions = array(
			'test_public_key'       => __( 'Prembly SDK Setup > Integration > Widget Key while Sandbox is selected.', 'hdyhaus-identity-verification' ),
			'test_configuration_id' => __( 'Prembly SDK Setup > Copy Config ID for the Sandbox widget.', 'hdyhaus-identity-verification' ),
			'live_public_key'       => __( 'Prembly SDK Setup > Integration > Widget Key while Production is selected.', 'hdyhaus-identity-verification' ),
			'live_configuration_id' => __( 'Prembly SDK Setup > Copy Config ID for the Live widget.', 'hdyhaus-identity-verification' ),
			'provider_privacy_url'  => __( 'Recommended: enter the Privacy Policy URL of the verification provider you use. This link appears below the registration consent message.', 'hdyhaus-identity-verification' ),
			'provider_terms_url'    => __( 'Recommended: enter the terms or consent URL of the verification provider you use. This is the provider\'s policy, not your website\'s terms.', 'hdyhaus-identity-verification' ),
			'site_terms_url'        => __( 'Enter your website\'s terms or disclaimer URL when using a custom URL instead of a WordPress page. This link appears separately from the verification provider\'s policies.', 'hdyhaus-identity-verification' ),
			'success_redirect'      => __( 'Optional same-site URL visited after WordPress successfully creates the verified account.', 'hdyhaus-identity-verification' ),
		);
		$description  = $descriptions[ $key ] ?? '';
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			id="<?php echo esc_attr( $id ); ?>"
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

		$defaults = array(
			'consent_text'         => RegistrationController::get_default_consent_text(),
			'provider_privacy_url' => RegistrationController::DEFAULT_PROVIDER_PRIVACY_URL,
			'provider_terms_url'   => RegistrationController::DEFAULT_PROVIDER_TERMS_URL,
		);

		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
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
	 * Render the HDYHaus Identity Verification admin page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings        = $this->get_settings();
		$active_provider = $this->registry->resolve( $settings );
		$tabs            = $this->get_tabs();
		$tab             = $this->get_current_tab( $tabs );
		$status_label    = null !== $active_provider ? $active_provider->get_label() : __( 'Disabled', 'hdyhaus-identity-verification' );
		?>
		<div class="wrap trustgate-admin">
			<div class="trustgate-admin__header">
				<div>
					<h1><?php esc_html_e( 'HDYHaus Identity Verification', 'hdyhaus-identity-verification' ); ?></h1>
					<p><?php esc_html_e( 'Identity verification for WordPress account registration.', 'hdyhaus-identity-verification' ); ?></p>
				</div>
				<span class="trustgate-admin__provider">
					<?php
					printf(
						/* translators: %s: active provider name or disabled status */
						esc_html__( 'Verification: %s', 'hdyhaus-identity-verification' ),
						esc_html( $status_label )
					);
					?>
				</span>
			</div>

			<?php if ( 'registration' !== $tab ) : ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'HDYHaus Identity Verification settings', 'hdyhaus-identity-verification' ); ?>">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<?php
					if ( 'registration' === $tab_key ) {
						continue;
					}
					?>
					<a
						class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab_key ) ); ?>"
					>
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php endif; ?>

			<?php $this->render_status_notice( $tab, $active_provider ); ?>

			<?php if ( isset( $_GET['trustgate-settings-saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['trustgate-settings-saved'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'hdyhaus-identity-verification' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="trustgate-admin__form">
				<?php
				wp_nonce_field( 'trustgate_registration_save', 'trustgate_registration_nonce' );
				?>
				<input type="hidden" name="action" value="trustgate_registration_save" />
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
					<strong><?php esc_html_e( 'Identity verification is disabled.', 'hdyhaus-identity-verification' ); ?></strong>
					<?php esc_html_e( ' Visitors can register through the normal WordPress registration flow without verification.', 'hdyhaus-identity-verification' ); ?>
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
						esc_html__( 'This provider is inactive. %s currently handles registration verification.', 'hdyhaus-identity-verification' ),
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
		$label = 'live' === $mode ? __( 'Live / Production', 'hdyhaus-identity-verification' ) : __( 'Sandbox / Test', 'hdyhaus-identity-verification' );

		$required = array(
			'public_key'       => __( 'Widget Key', 'hdyhaus-identity-verification' ),
			'configuration_id' => __( 'Configuration ID', 'hdyhaus-identity-verification' ),
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
				<strong><?php esc_html_e( 'Active environment:', 'hdyhaus-identity-verification' ); ?></strong>
				<?php echo esc_html( $label ); ?>
				<?php if ( ! empty( $missing_required ) ) : ?>
					<?php
					printf(
						/* translators: %s: comma-separated list of missing fields */
						esc_html__( ' - missing required widget settings: %s.', 'hdyhaus-identity-verification' ),
						esc_html( implode( ', ', $missing_required ) )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( ' - configuration complete.', 'hdyhaus-identity-verification' ); ?>
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

		$tabs['registration'] = __( 'Registration', 'hdyhaus-identity-verification' );

		return $tabs;
	}

	/**
	 * Get the selected settings tab.
	 *
	 * @param array<string, string> $tabs Available tabs.
	 */
	private function get_current_tab( array $tabs ): string {
		if ( isset( $_GET['page'] ) && self::REGISTRATION_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'registration';
		}
		$default = (string) array_key_first( $tabs );
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return isset( $tabs[ $tab ] ) ? $tab : $default;
	}
}
