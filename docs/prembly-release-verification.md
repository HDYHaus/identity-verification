# Prembly-only setup release verification

Release candidate: `0.9.10-rc.1`, based on stable `0.9.9` (`origin/main`).

This release contains the Prembly settings guidance, customer documentation, landing-page recovery reference, and related tests. It does not include the Sumsub adapter or changes to registration verification behavior.

The earlier mixed-branch beta 9 prerelease was withdrawn as a draft. The Prembly commits were reverted from the Sumsub branch without rewriting its history.

Automated tests, all PHP syntax checks, WordPress coding standards, and whitespace checks passed on the isolated branch. A source search found no Sumsub references in plugin code, browser assets, customer readme, or test configuration. Installation and official Plugin Check results will be recorded after testing the published release ZIP. The landing-page design was already restored and verified at desktop and mobile widths; no further live page edit is needed for this branch correction.
