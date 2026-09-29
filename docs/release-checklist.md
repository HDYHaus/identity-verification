# Release Checklist

## Automated Checks

- Update the plugin header, version constant, `readme.txt` stable tag, and changelog to the same version.
- Run `composer install`.
- Run `composer lint`.
- Run `composer phpcs`.
- Run `composer test`.
- Run `git diff --check`.
- Build the distributable with `./bin/build-release.sh` and run `unzip -t` on it.
- Install the ZIP on a clean WordPress site and run the official Plugin Check plugin with all categories enabled.

## Registration Checks

- Confirm normal WordPress registration works when every provider is disabled.
- Confirm Prembly assets are absent when Prembly is disabled.
- Confirm the consent checkbox is required before the widget opens and before registration succeeds.
- Confirm successful Sandbox and Production verifications create one Subscriber account.
- Confirm missing, cancelled, failed, pending, and expired verifications do not create an account.
- Confirm changed email, mismatched widget, mismatched session, and reused session references are rejected.
- Confirm changing the registration fields after verification clears the verified state.
- Confirm the success redirect remains on an allowed host and displays the password-setup email instructions.

## Compatibility Checks

- Test the oldest and newest supported WordPress versions.
- Test PHP 8.1 and the newest supported PHP release.
- Test with `WP_DEBUG` and `SCRIPT_DEBUG` enabled and review the PHP and browser logs.
- Test keyboard-only navigation, visible focus, screen-reader labels, status announcements, and mobile layout.
- Test a default theme and the supported HDY login branding plugin.
- Decide and document multisite support before version 1.0.

## Privacy and Publication

- Verify Prembly Terms and Privacy Policy links remain current.
- Review the site's privacy notice, lawful basis, retention period, and consent wording with appropriate counsel.
- Test WordPress personal data export and erasure for a verified user.
- Confirm the production Prembly widget permits the public registration domain.
- Validate `readme.txt` and prepare WordPress.org icon, banner, and screenshots.
- Confirm the `trustgate-registration` WordPress.org slug is available before submission.
