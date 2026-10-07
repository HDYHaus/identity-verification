# Contributing

HDYHaus Identity Verification follows WordPress plugin conventions and WordPress Coding Standards.

Before opening a pull request:

```bash
composer install
composer lint
composer phpcs
```

Guidelines:

- Sanitize all input.
- Escape all output.
- Use nonces for state-changing admin and AJAX requests.
- Keep provider integrations behind adapter classes.
- Do not expose secret keys to frontend JavaScript.
- Keep customer-facing strings translatable with the `hdyhaus-identity-verification` text domain.
