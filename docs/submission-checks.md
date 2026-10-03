# WordPress.org Submission Checks

Candidate: TrustGate Registration 0.9.7. Checks completed on October 3, 2026.

## Completed

- GitHub CI passed on PHP 8.1 and 8.5 for commit `699a003`.
- Official Plugin Check 2.1.0 passed on a clean native WordPress 7.1.2 / PHP 8.4.4 installation, with its CLI runtime bootstrap loaded and all nonexperimental checks enabled. A second pass included low-severity errors and warnings; neither pass reported issues.
- Real WordPress integration checks passed on WordPress 6.4.12 / PHP 8.1.34 and WordPress 7.1.2 / PHP 8.5.10 in isolated Playground installs, and WordPress 7.1.2 / PHP 8.4.4 with SQLite using native PHP.
- Clean activation leaves ordinary registration available and does not enqueue Prembly scripts while the provider is disabled.
- Registration saves preserve provider activation and credentials after cache flushes.
- Website terms page selection, the automatic WordPress Privacy Policy link, and fallback to a custom URL when the selected page is unpublished were verified.
- Missing, pending, failed, and expired attempts block registration. External success redirects are rejected.
- WordPress's registered personal-data exporter and eraser operate on real user metadata. Erasure removes consent details and retains the disclosed identity/session replay protection.
- Chrome browser checks passed for the dedicated Registration screen, save and reload, page-selection persistence, keyboard focus, mobile control boundaries, website terms link, and rejection of an unverified signup. No JavaScript page errors were reported during those checks.
- Listing FAQ navigation and recommended-link wording were corrected. Single-site support and unverified multisite support are documented explicitly.
- Listing screenshots were refreshed with dummy settings. Icons and banners already exist in `.wordpress-org/`.
- Build excludes development dependencies, tests, build scripts, contributor/security documents, and development configuration. ZIP integrity passed.

## Remaining Before Submission

- Site owner repeats the successful live-provider registration and password-setup email flow on the HTTPS test site with the final candidate.
- Confirm the new website policy links and layout on the site's actual login branding. Browser checks above used the default WordPress login screen and a default theme, not the site's branding plugin.
- Rebuild and rerun Plugin Check if that live test requires code changes.

## Limits

The compatibility checks exercised WordPress settings, rendering, registration rejection, and privacy integration; they did not make paid provider calls. Browser keyboard checks confirmed focusability, not a full screen-reader audit. Multisite is not claimed as supported. Name/slug acceptance is left to WordPress.org's submission review.
