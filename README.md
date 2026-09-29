# TrustGate Registration

TrustGate Registration is a WordPress plugin for verified account registration. It is designed around a provider adapter model, with Prembly as the first supported identity verification provider.

## Goals

- Verify identity during core WordPress registration.
- Block account creation until verification succeeds.
- Keep provider-specific logic behind adapters.
- Store normalized verification status in WordPress user meta.
- Stay WordPress.org-ready with GPL licensing, translatable strings, sanitized input, escaped output, and WPCS checks.

## Current Status

This repository is in early development. The plugin includes the bootstrap, settings page, registration form hooks, provider interface, Prembly widget integration, server-side verification status confirmation, and one-time registration attempt protection.

On September 26, 2026, the sandbox flow completed an end-to-end smoke test on the HTTPS development site: Prembly completed document verification, WordPress created the verified Subscriber account, and a separate registration submission without verification was blocked. Production rollout still requires the broader test matrix and compliance work listed in the setup guide.

## Prembly Settings

The Prembly provider uses the widget key and configuration ID in the browser to launch the widget. After completion, WordPress retrieves the returned SDK session and confirms its status, widget, and available email data before allowing registration.

- `Sandbox Widget Key` / `Live Widget Key`: copied from the matching saved widget's **SDK Setup > Integration** panel. Do not use the API Integrations public key here.
- `Sandbox Configuration ID` / `Live Configuration ID`: copied from the matching SDK Setup widget.
- `Status Endpoint`: optional override. Defaults to `https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{id}/`.

TrustGate has its own top-level WordPress admin menu with a global Registration tab and one tab per verification provider. Enable Prembly from its tab to require KYC during registration. Only one provider can be active at a time; disabling every provider restores normal WordPress registration without deleting saved widget settings. Sandbox and Live widget settings are stored independently, and Prembly-specific advanced settings remain on the Prembly tab.

Prembly support confirmed that the SDK session endpoint uses the session ID in the URL and does not require a Secret API Key, Organisation ID, or App ID. TrustGate therefore sends no authentication headers to that endpoint.

The optional Success Redirect URL must be on the WordPress site's allowed hosts. It runs after WordPress successfully creates the verified account, not immediately after the Prembly widget completes. TrustGate adds a completion marker and prepends an account-created notice reminding the user to check their email for the password setup link.

See [Prembly setup](docs/prembly-setup.md) for the recommended sandbox widget configuration and a field-by-field dashboard mapping.

## Provider Adapters

Adapters implement `VerificationProvider` and register through the `trustgate_registration_providers` filter. TrustGate creates a settings tab and exclusive activation checkbox for every registered adapter. Providers can add fields with `trustgate_registration_register_provider_settings` and sanitize them with `trustgate_registration_sanitize_provider_settings`; the registration controller continues to receive only the resolved active adapter.

## Development

```bash
composer install
composer lint
composer phpcs
```

## Local Development

The intended Local by Flywheel site is:

- Site name: `TrustGate Registration`
- Router mode: `localhost`
- Development URL: `http://localhost:10043`
- Plugin folder: `wp-content/plugins/trustgate-registration`

## License

GPL-2.0-or-later.
