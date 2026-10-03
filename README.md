# TrustGate Registration

TrustGate Registration is a WordPress plugin for verified account registration. It is designed around a provider adapter model, with Prembly as the first supported identity verification provider.

## Goals

- Verify identity during core WordPress registration.
- Block account creation until verification succeeds.
- Keep provider-specific logic behind adapters.
- Store normalized verification status in WordPress user meta.
- Stay WordPress.org-ready with GPL licensing, translatable strings, sanitized input, escaped output, and WPCS checks.

## Current Status

Version 0.9.7 is the WordPress.org release candidate. The plugin includes the settings interface, native registration hooks, provider registry, Prembly widget integration, fail-closed server-side session confirmation, explicit registrant consent, session and identity replay protection, WordPress privacy tool integration, and read-only verification details on administrator user-profile screens.

Sandbox and Production flows have both completed end-to-end smoke tests on the HTTPS development site. Prembly completed document verification, WordPress created the verified Subscriber account, a submission without verification was blocked, and the configured post-registration redirect displayed the email activation instructions. See [the release checklist](docs/release-checklist.md) for the remaining clean-install and compatibility checks before a public 1.0 release.

The 0.9.7 submission candidate passed the official Plugin Check and clean-install integration checks on WordPress 6.4.12 / PHP 8.1 and WordPress 7.1.2 / PHP 8.4 and 8.5. See [submission check results](docs/submission-checks.md) for the exact checks and remaining final live-provider test.

## Prembly Settings

The Prembly provider uses the widget key and configuration ID in the browser to launch the widget. After completion, WordPress retrieves the returned SDK session and requires its status, session ID, widget configuration, and end-user email to match before allowing registration.

- `Sandbox Widget Key` / `Live Widget Key`: copied from the matching saved widget's **SDK Setup > Integration** panel. Do not use the API Integrations public key here.
- `Sandbox Configuration ID` / `Live Configuration ID`: copied from the matching SDK Setup widget.

TrustGate has its own top-level WordPress admin menu. Open **TrustGate > Registration** for global registration settings, or **TrustGate > Settings** for verification provider tabs. Enable Prembly from its tab to require KYC during registration. Only one provider can be active at a time; disabling every provider restores normal WordPress registration without deleting saved widget settings. Sandbox and Live widget settings are stored independently.

The Registration page applies to the active provider and lets the site owner replace the plain-text consent message and separate verification provider privacy-policy and terms/consent URLs. These links are recommended. Existing sites use the documented Prembly wording and links until they are changed. TrustGate automatically includes the website's WordPress Privacy Policy when configured under **Settings > Privacy**. For website terms or a disclaimer, choose a published WordPress page or enter a custom URL; a selected published page takes precedence. Successful accounts retain the exact accepted wording plus a version fingerprint derived from the complete disclosure, including the website terms link.

Prembly support confirmed that the fixed SDK session endpoint at `https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{id}/` uses the session ID in the URL and does not require a Secret API Key, Organisation ID, or App ID. TrustGate therefore sends no authentication headers to that endpoint.

The optional Success Redirect URL must be on the WordPress site's allowed hosts. It runs after WordPress successfully creates the verified account, not immediately after the Prembly widget completes. TrustGate adds a completion marker and prepends an account-created notice reminding the user to check their email for the password setup link.

See [Prembly setup](docs/prembly-setup.md) for the recommended sandbox widget configuration and a field-by-field dashboard mapping.

## Verification Data

For a successfully created account, TrustGate stores the normalized verification status, verification time, provider slug, Prembly session reference, site-specific one-way session and identity hashes, and the accepted consent time, wording, and disclosure-version fingerprint as WordPress metadata. The identity hash is derived with a site secret from the verified document country, type, and number; the underlying document data is never stored. TrustGate does not copy identity documents, document numbers, selfies, biometric information, raw Prembly responses, or detailed provider reports into WordPress. The metadata is available through WordPress personal data export and erasure tools; one-way hashes are retained after privacy erasure or account deletion to prevent reuse of a completed verification or identity.

## Provider Adapters

Adapters implement `VerificationProvider` and register through the `trustgate_registration_providers` filter. TrustGate creates a settings tab and exclusive activation checkbox for every registered adapter. Providers can add fields with `trustgate_registration_register_provider_settings` and sanitize them with `trustgate_registration_sanitize_provider_settings`; the registration controller continues to receive only the resolved active adapter.

## Development

```bash
composer install
composer lint
composer phpcs
composer test
./bin/build-release.sh
```

## Local Development

The intended Local by Flywheel site is:

- Site name: `TrustGate Registration`
- Router mode: `localhost`
- Development URL: `http://localhost:10043`
- Plugin folder: `wp-content/plugins/trustgate-registration`

## License

GPL-2.0-or-later.
