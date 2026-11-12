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

The Prembly-only PR remains unmerged. No WordPress.org publication has been performed.
