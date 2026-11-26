# HDYHaus Identity Verification

HDYHaus Identity Verification is a WordPress plugin for verified account registration. It is designed around a provider adapter model, with Prembly as the first supported identity verification provider.

## Goals

- Verify identity during core WordPress registration.
- Block account creation until verification succeeds.
- Keep provider-specific logic behind adapters.
- Store normalized verification status in WordPress user meta.
- Stay WordPress.org-ready with GPL licensing, translatable strings, sanitized input, escaped output, and WPCS checks.

## Current Status

Version 1.0.0 adds a durable registration-attempt record and an administrator recovery path for verified registrations where WordPress account creation did not complete. Site owners can review completed and failed attempts, filter the list, recover eligible verified attempts by creating the account and sending a password-setup email, and set retention for unlinked and account-linked records (defaults: 7 and 90 days). The release also includes the settings interface, native registration hooks, Prembly widget integration, fail-closed server-side session confirmation, explicit registrant consent, session and identity replay protection, WordPress privacy tools, and read-only verification details on administrator user-profile screens.

Identity fingerprints use a persistent site-specific plugin secret. Keep this option with database backups; WordPress authentication salt changes do not affect it. Version 0.9.8 preserves existing settings but does not migrate earlier development fingerprints. Retest using fresh verification records. When installing the renamed package over a development installation, deactivate the old TrustGate package before activating the new one.

The 1.0.0 release passed the automated PHP checks and CI, and the exact release ZIP passed WordPress Plugin Check on the development site. The owner also confirmed a successful Live-mode verification and account creation. See [1.0.0 release verification](docs/release-verification-1.0.0.md) for the scope and evidence.

## Prembly Settings

The Prembly provider uses the widget key and configuration ID in the browser to launch the widget. After completion, WordPress retrieves the returned SDK session and requires its status, session ID, widget configuration, and end-user email to match before allowing registration.

- `Sandbox Widget Key` / `Live Widget Key`: copied from the matching saved widget's **SDK Setup > Integration** panel. Do not use the API Integrations public key here.
- `Sandbox Configuration ID` / `Live Configuration ID`: copied from the matching SDK Setup widget.

HDYHaus Identity Verification has its own top-level WordPress admin menu. Open **HDYHaus Identity Verification > Registration** for global registration settings, or **HDYHaus Identity Verification > Settings** for verification provider tabs. Enable Prembly from its tab to require KYC during registration. Only one provider can be active at a time; disabling every provider restores normal WordPress registration without deleting saved widget settings. Sandbox and Live widget settings are stored independently.

The Registration page applies to the active provider and lets the site owner replace the plain-text consent message and separate verification provider privacy-policy and terms/consent URLs. These links are recommended. Existing sites use the documented Prembly wording and links until they are changed. HDYHaus Identity Verification automatically includes the website's WordPress Privacy Policy when configured under **Settings > Privacy**. For website terms or a disclaimer, choose a published WordPress page or enter a custom URL; a selected published page takes precedence. Successful accounts retain the exact accepted wording plus a version fingerprint derived from the complete disclosure, including the website terms link.

Registration attempts are available under **HDYHaus Identity Verification > Registration Attempts**. For eligibility rules, recovery steps, privacy details, and retention behavior, see [Registration attempts and account recovery](docs/registration-attempts.md).

Prembly support confirmed that the fixed SDK session endpoint at `https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{id}/` uses the session ID in the URL and does not require a Secret API Key, Organisation ID, or App ID. HDYHaus Identity Verification therefore sends no authentication headers to that endpoint.

The optional Success Redirect URL must be on the WordPress site's allowed hosts. It runs after WordPress successfully creates the verified account, not immediately after the Prembly widget completes. HDYHaus Identity Verification adds a completion marker and prepends an account-created notice reminding the user to check their email for the password setup link.

Before creating a Prembly widget, copy **Prembly SDK Redirect URL** from **Identity Verification > Settings > Prembly** into the dashboard's **Brand Details > Redirect URL**. This is the current site's registration URL, normally `https://your-domain.com/wp-login.php?action=register`, including any WordPress subdirectory or development port. Leave Prembly's **Webhook URL** blank; the plugin has no Prembly webhook receiver. Choose Sandbox or Production as needed. Sandbox is recommended for testing, and starting with Production is supported.

See [Prembly setup](docs/prembly-setup.md) for the recommended sandbox widget configuration and a field-by-field dashboard mapping.

## Verification Data

For a successfully created account, HDYHaus Identity Verification stores the normalized verification status, verification time, provider slug, Prembly session reference, site-specific one-way session and identity hashes, and the accepted consent time, wording, and disclosure-version fingerprint as WordPress metadata. The identity hash is derived with a site secret from the verified document country, type, and number; the underlying document data is never stored. HDYHaus Identity Verification does not copy identity documents, document numbers, selfies, biometric information, raw Prembly responses, or detailed provider reports into WordPress. The metadata is available through WordPress personal data export and erasure tools; one-way hashes are retained after privacy erasure or account deletion to prevent reuse of a completed verification or identity.

## Provider Adapters

Adapters implement `VerificationProvider` and register through the `trustgate_registration_providers` filter. HDYHaus Identity Verification creates a settings tab and exclusive activation checkbox for every registered adapter. Providers can add fields with `trustgate_registration_register_provider_settings` and sanitize them with `trustgate_registration_sanitize_provider_settings`; the registration controller continues to receive only the resolved active adapter.

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

- Site name: `HDYHaus Identity Verification`
- Router mode: `localhost`
- Development URL: `http://localhost:10043`
- Plugin folder: `wp-content/plugins/hdyhaus-identity-verification`

## License

GPL-2.0-or-later.
