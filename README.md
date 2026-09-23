# TrustGate Registration

TrustGate Registration is a WordPress plugin for verified account registration. It is designed around a provider adapter model, with Prembly as the first supported identity verification provider.

## Goals

- Verify identity during core WordPress registration.
- Block account creation until verification succeeds.
- Keep provider-specific logic behind adapters.
- Store normalized verification status in WordPress user meta.
- Stay WordPress.org-ready with GPL licensing, translatable strings, sanitized input, escaped output, and WPCS checks.

## Current Status

This repository is in early development. The first scaffold includes the plugin bootstrap, settings page, registration form hooks, provider interface, and Prembly adapter placeholder. The Prembly server-side status confirmation still needs to be completed before this can be used for production registration gating.

## Development

```bash
composer install
composer lint
composer phpcs
```

## Local Development

The intended Local by Flywheel site is:

- Site name: `TrustGate Registration`
- Domain: `trustgate-registration.local`
- Plugin folder: `wp-content/plugins/trustgate-registration`

## License

GPL-2.0-or-later.
