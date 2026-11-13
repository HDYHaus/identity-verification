# Prembly-only setup release verification

Release candidate: `0.9.10-rc.2`, based on stable `0.9.9` (`origin/main`).

This release contains the Prembly settings guidance, customer documentation, landing-page recovery reference, and related tests. It does not include the Sumsub adapter or changes to registration verification behavior.

The earlier mixed-branch beta 9 prerelease was withdrawn as a draft. The Prembly commits were reverted from the Sumsub branch without rewriting its history.

Automated tests, all PHP syntax checks, WordPress coding standards, and whitespace checks passed on the isolated branch. A source search found no Sumsub references in plugin code, browser assets, customer readme, or test configuration. Installation and official Plugin Check results will be recorded after testing the published release ZIP. The landing-page design was already restored and verified at desktop and mobile widths; no further live page edit is needed for this branch correction.

## Candidate 1 results

Installed the published 0.9.10-rc.1 ZIP on dev.hdyhaus.com through the standard WordPress uploader. Confirmed the Prembly-only settings tab, the correct registration URL, and the saved Live configuration. Prembly is the active provider for this test.

Official Plugin Check 2.2.0 completed with all five categories and both errors and warnings enabled: one warning, `plugin_header_nonexistent_domain_path`. The stable 0.9.9 baseline declared a languages directory that was not packaged. Candidate 2 adds a placeholder PHP file so that directory is included. No verification logic was changed.

## Candidate 2 results

- Published release: https://github.com/HDYHaus/identity-verification/releases/tag/v0.9.10-rc.2
- Source commit: `c27010a` on `codex/prembly-setup-guidance`; review PR: https://github.com/HDYHaus/identity-verification/pull/15
- Full automated tests, PHP syntax checks, and WordPress coding standards passed. GitHub CI passed on PHP 8.1 and PHP 8.5.
- Release ZIP integrity and isolation checks passed. The packaged plugin coordinator, registration JavaScript, registration controller, privacy handler, and Prembly provider are byte-for-byte identical to stable main.
- The declared languages directory is now included. The ZIP excludes Git metadata, agent instructions, development documentation, and tests.
- Installed the published candidate ZIP through the normal WordPress uploader on dev.hdyhaus.com; WordPress reported a successful RC 1 to RC 2 update.
- Verified Prembly is the active provider, Live / Production configuration remains complete, and the settings display `https://dev.hdyhaus.com/wp-login.php?action=register`. No other provider tab is present.
- Official Plugin Check 2.2.0 completed on WordPress 7.1.4 with all five categories enabled and both errors and warnings selected: no findings. AI analysis and custom exclusions were disabled.
- No new live identity-verification session was performed. This correction was validated through automated tests, release-package comparisons, installed settings inspection, and official Plugin Check.

At the end of RC 2 testing, the Prembly-only PR was unmerged and no WordPress.org publication had been performed. Stable 0.9.10 publication was subsequently authorized by the owner.

## Stable 0.9.10 publication — November 13, 2026

- Approved Prembly-only PR #15 was merged into main as `e5cdebb576c5cdb6fc96e9d0337191d8f1dbd2ee`. Final stable-version CI passed on PHP 8.1 and PHP 8.5.
- GitHub stable release: https://github.com/HDYHaus/identity-verification/releases/tag/v0.9.10
- Installed the exact stable GitHub ZIP on dev.hdyhaus.com through the WordPress uploader and confirmed the saved Prembly Live configuration and displayed registration URL.
- Official Plugin Check completed on the installed stable package with all five categories and both errors and warnings enabled, AI analysis disabled, and custom exclusions disabled: no findings.
- WordPress.org SVN revision `3800088` published identical package contents to trunk and tags/0.9.10. Both include the revised Installation instructions in readme.txt.
- Public WordPress.org metadata reports version 0.9.10. Its rendered Installation tab includes the SDK Redirect URL, blank Webhook URL, and Production-first guidance.
- Downloaded the public WordPress.org ZIP and compared every file with the tested GitHub ZIP: all 19 file contents match. ZIP archive bytes differ because WordPress.org generates its own archive.
- Tested GitHub ZIP SHA-256: `39950a3f10c8ee9351baa3f0419a9e4a624a1ae56018ce2c78c3edf149aea59c`.
- Public WordPress.org ZIP SHA-256: `82d7f3de3658d4017077e9df18f9eebb88874afa0352f78ed7fa0dbfc469b699`.
- No new live identity-verification session was performed for this setup-guidance release.

The landing page version badge, both download links, and the release-notes link were updated from 0.9.9 to 0.9.10 using six literal version replacements in the current WordPress code-editor source. Its stylesheet was preserved byte-for-byte. Verified the public links and version badge, desktop two-column layout at 1440 px, mobile single-column layout at 390 px, and no horizontal overflow. The canonical page source is updated in docs/landing-page-current.html.
