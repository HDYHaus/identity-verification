# TrustGate Registration

TrustGate Registration is a WordPress plugin for verified account registration. It is designed around a provider adapter model, with Prembly as the first supported identity verification provider.

## Goals

- Verify identity during core WordPress registration.
- Block account creation until verification succeeds.
- Keep provider-specific logic behind adapters.
- Store normalized verification status in WordPress user meta.
- Stay WordPress.org-ready with GPL licensing, translatable strings, sanitized input, escaped output, and WPCS checks.

## Current Status

This repository is in early development. The plugin includes the bootstrap, settings page, registration form hooks, provider interface, Prembly widget integration, server-side verification status confirmation, and one-time registration attempt protection. End-to-end registration testing must still be completed before production use.

## Prembly Settings

The Prembly provider uses the public key and configuration ID in the browser to launch the widget. After completion, WordPress retrieves the returned SDK session and confirms its status, widget, and available email data before allowing registration.

- `Test Public Key` / `Live Public Key`: Prembly widget public keys.
- `Secret API Key`: sent only from WordPress to Prembly for authenticated session lookups.
- `Organisation ID`: paired with the secret key as `x-organisation-id` when available.
- `App ID`: optional compatibility header for older Identitypass accounts.
- `Status Endpoint`: optional override. Defaults to `https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{id}/`.

See [Prembly setup](docs/prembly-setup.md) for the recommended sandbox widget configuration and a field-by-field dashboard mapping.

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
