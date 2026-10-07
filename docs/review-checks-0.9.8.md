# Review Checks: 0.9.8

Candidate: HDYHaus Identity Verification 0.9.8, checked October 7, 2026.

- PHP syntax checks, uncached WordPress Coding Standards, and all five test suites pass.
- Fingerprint tests cover persistent secrets, competing initialization, storage failure, and corrupt stored secrets.
- Registration tests cover missing, invalid, and malformed nonces, successful verified registration metadata, consumed-attempt rejection, and duplicate identities.
- Gitleaks found no exposed credentials in all 20 existing Git commits or the working tree before publication.
- The release ZIP installs and activates on the isolated WordPress 7.1.2 / PHP 8.4.4 development installation.
- Official Plugin Check completes with no reported issues, using its CLI runtime bootstrap and the new slug.
- Real WordPress database checks confirm secret persistence, exclusion from autoload, fingerprint stability across cache flushes and authentication salt changes, public form nonces, unverified registration rejection, unauthorized settings-save rejection, and administrator nonce rejection.
- Desktop and mobile browser checks cover renamed admin screens, settings save, form nonces, control boundaries, and unverified signup rejection. Listing screenshots use dummy provider settings.

## Remaining Manual Check

Repeat a successful live-provider verification and the password-setup email flow with fresh test identity records. This release did not make paid provider calls. Earlier development fingerprints are not migrated. The original 0.9.7 compatibility matrix is recorded separately in `submission-checks.md`; this release was checked locally on PHP 8.4.4.

## Submission

Upload `hdyhaus-identity-verification-0.9.8.zip` to the existing pending WordPress.org submission and reply in the existing review thread using `review-reply-0.9.8.txt`. Explicitly request `hdyhaus-identity-verification` as the new slug. Website changes are managed separately by the owner.
