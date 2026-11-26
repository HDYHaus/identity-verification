# Release verification: 1.0.0

Release: [v1.0.0](https://github.com/HDYHaus/identity-verification/releases/tag/v1.0.0)  
Source commit: `4e1dad50eff54cf7da5a9ea2e657a07a61c41dcb`

## Automated and package checks

- `composer test`, `composer lint`, and `composer phpcs` passed before release.
- Pull request CI passed on PHP 8.1 and PHP 8.5.
- The versioned release ZIP was installed on `dev.hdyhaus.com` through the normal WordPress plugin uploader. WordPress Plugin Check completed with no errors.
- Registration-attempt retention settings were saved with custom values (14 days unlinked, 120 days linked), then restored to the defaults (7 and 90 days) and confirmed on the development site.
- Release artifact: `hdyhaus-identity-verification-1.0.0.zip` (SHA-256 `d07e636ba23090851d2a8a8fa8852242dc60f6a09ab9888227cff946958b61a`).

## Registration recovery checks

On the development site, an administrator used **Registration Attempts** to recover an eligible verified-but-unlinked attempt. The recovery created and linked the account and sent the password-setup email. Repeating the submission for the same email did not create a duplicate account; the attempt record was updated. Email logging showed the verification-resume and password-setup messages. A failed verification was shown as not eligible for account recovery.

## Live verification

The site owner reported a successful Live-mode identity verification and account creation on the development site after installing v1.0.0. This document records that observed result as owner-reported; no additional Live identity submission was made for this release note update.

## Landing page

The public plugin page at https://hdyhaus.com/wp-plugins/identity-verification/ now shows v1.0.0, links to the stable v1.0.0 ZIP/release notes, and describes attempt review, account recovery, and configurable retention. It was checked at 1440 px desktop and 390 px mobile widths; the stylesheet parsed and neither layout had horizontal overflow. The existing design and embedded stylesheet were preserved.
