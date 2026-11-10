# Sumsub setup and Sandbox verification

Sumsub is an optional provider for native WordPress registration. Enable it under **Identity Verification > Settings > Sumsub**. Enabling Sumsub replaces the selected provider while preserving the other provider's settings.

## Configure Sandbox

1. Keep the Sumsub dashboard in **Sandbox mode**. Sandbox integration testing does not require activating a live trial or subscription.
2. Under **Configurations > Developers > App tokens**, generate an app token with these permissions: Create applicants, Create verification requests, View applicants, View approved applicants, and View applicant's personal data. Do not grant moderation, image download, user management, export, or company-settings permissions.
3. Save the app token and secret securely; the secret is shown only once.
4. Choose an individual verification level containing ID document verification and liveness, such as the existing `id-and-liveness` level. Copy its exact, case-sensitive name.
5. In WordPress's Sumsub tab, enter the **Sandbox App Token**, **Sandbox Secret Key**, and **Sandbox Verification Level**. Select **Sandbox / Test** and enable Sumsub. Blank credential fields preserve previously saved values; credentials are not echoed into settings HTML. Each credential shows “Credential saved” or “Not configured” to distinguish stored values from missing configuration.
6. Under **Identity Verification > Registration**, review Sumsub's consent wording and policy links. These are stored separately from Prembly's disclosure. Website policy links and the success redirect remain global.
7. Ensure the registration origin is allowed in Sumsub's WebSDK settings. Use HTTPS for camera access, except where the browser supports secure localhost development.
8. Enable WordPress registration under **Settings > General > Anyone can register**, then test `wp-login.php?action=register`.

## Verification flow

WordPress generates an opaque applicant identifier bound to the server-issued registration attempt, entered email and names, selected environment, app credentials, and verification level. After consent, the server sends that identifier and email to Sumsub to request a short-lived, applicant-scoped WebSDK token. The app secret stays on the server. Documents and selfies are collected directly by the Sumsub WebSDK.

Browser events only initiate signed server-to-server applicant and current review-status lookups. Registration requires a completed `GREEN` result, the expected applicant identifier, email, individual type, and verification level. Deleted and deactivated applicants cannot pass. Current review status must be completed with a GREEN answer; in-progress decisions remain blocked. The server derives a site-specific document fingerprint from verified `info.idDocs` attributes, then discards the response. Pending checks are polled every five seconds for up to five minutes; select Verify Identity again to check later while the attempt is still valid. Registration attempts expire after 30 minutes. Token refreshes do not extend the original attempt lifetime.

This implementation uses API lookups rather than accepting inbound webhooks. No webhook URL or webhook secret is required for this registration-only flow. Review decisions arriving after the attempt expires require a fresh registration attempt. It does not maintain ongoing user verification status after account creation.

Only normalized status, applicant reference, one-way hashes, and consent evidence enter the existing WordPress metadata/privacy flow. App credentials are stored in the site's settings option; protect the WordPress database and backups. Identity fingerprints are provider- and environment-specific, so a document used with Prembly is not automatically recognized as the same identity in Sumsub. Fingerprinting recognizes the same document, not every possible document belonging to one person.

## Sandbox acceptance checklist

- [x] Enter real Sandbox app credentials and confirm the SDK launches on the HTTPS development site.
- [x] Use Sumsub's test passport template and complete liveness; confirm the Sandbox GREEN decision creates one WordPress account.
- [ ] Simulate RED, pending, on-hold, and resubmission states; confirm registration remains blocked until a current GREEN decision.
- [x] Change the surname after server-confirmed approval; confirm the SDK result clears and account creation becomes disabled.
- [ ] Repeat the post-approval email edit manually (covered by automated regression tests).
- [ ] Try another attempt's applicant ID; confirm approval is rejected.
- [ ] Test expired attempts and SDK token refresh.
- [x] Reuse the official test document on a fresh approved attempt; confirm identity protection blocks a second account.
- [ ] Repeat consumed-applicant replay through the hosted UI (covered by automated tests).
- [ ] Confirm consent text, provider label, privacy export/erasure, and admin profile metadata.
- [ ] Switch back to Prembly; confirm its saved settings and registration flow still work.

## Production is a separate rollout

Sandbox approval is simulated; it is not evidence of successful real identity verification. Production requires the site's own Sumsub account, commercial activation, separately generated Production app token/secret, and Production verification level. Never paste Sandbox credentials into Live fields. Perform a real-document smoke test, review processing delays and failure handling, and confirm pricing before enabling public registration. No Production test has been completed for this implementation.

## Official references

- [WebSDK setup](https://docs.sumsub.com/docs/get-started-with-web-sdk)
- [App tokens](https://docs.sumsub.com/docs/app-tokens)
- [Request authentication](https://docs.sumsub.com/reference/authentication)
- [SDK access token](https://docs.sumsub.com/reference/generate-access-token)
- [Applicant data](https://docs.sumsub.com/reference/get-applicant-data)
- [WebSDK events](https://docs.sumsub.com/docs/websdk-messages)
- [Sandbox testing](https://docs.sumsub.com/docs/test-in-sandbox)

## Development smoke test — November 8, 2026

Tested on `https://dev.hdyhaus.com` using the `id-and-liveness` Sandbox level. The unverified registration was blocked. The official German passport template was uploaded and the site owner completed the liveness step. Sumsub approved the applicant, WordPress confirmed approval through signed API calls, and the `sumsub-sandbox-test` account (user ID 16) was created with the configured success redirect. The administrator profile showed provider `sumsub`, status `verified`, consent evidence, replay protection, and identity uniqueness retention.

This test exposed a review interpretation bug: the completed decision could retain a `reprocessing` flag. The adapter now uses the dedicated `/resources/applicants/{id}/status` endpoint and its documented completed/GREEN decision instead of treating that historical flag as a pending result. The Create Your Account button stays disabled until WordPress confirms verification. Regression tests cover both changes. The current development build is `0.10.0-beta.4`, including explicit saved-credential indicators and the provider-switch review fix.

Remaining checklist items above have automated coverage where noted in the test suite but have not all been repeated manually through the hosted UI. Production remains untested.

Automated failure checks passed for RED/RETRY decisions, expired approvals, consumed attempts, duplicate documents, mismatched applicants, and form changes during an in-flight confirmation. These are server/controller and browser-logic regression tests; the hosted browser could not restore the submitted registration POST for a manual replay check.

## Manual failure checks — November 9, 2026

On the development site, switching to Prembly Sandbox changed the registration disclosure to Prembly and made Sumsub inactive while retaining both configurations. Restoring Sumsub retained its Sandbox credentials and level; Prembly's original environment preference was restored. This verifies provider switching and disclosure selection, not a new end-to-end Prembly verification.

For a new synthetic Sumsub applicant, final RED rejection and retryable resubmission were simulated in the Sandbox dashboard. WordPress returned `not_verified` and kept account creation disabled in both cases. A long document-picker delay also exercised attempt expiry: the SDK displayed Verification expired and WordPress returned `invalid_attempt`, with registration still disabled. Sumsub refused to approve an applicant with incomplete required steps; the plugin's missing-document guard remains covered by automated tests. The duplicate-document check was subsequently completed on a fresh attempt: the same official test passport and liveness received Sumsub approval, while WordPress displayed “This identity is already associated with an account” and kept Create Your Account disabled. A post-approval surname edit was subsequently tested manually on November 10, as recorded below.

WordPress Plugin Check passed all selected categories with errors and warnings enabled on November 9, 2026. The first run caught an omitted empty translation directory; adding a tracked `languages/index.php` retained that directory in the package, and the corrected development package passed without errors or warnings.

## Manual form-change check — November 10, 2026

A fresh Sandbox applicant completed the official test passport and owner-operated liveness flow. To isolate this check from duplicate-document blocking, its approved Sandbox document metadata was changed to a distinct synthetic passport number (`SBXEDIT110`) through the Sumsub dashboard. This is a simulated test fixture, not a real identity or a production verification. WordPress then confirmed approval and enabled Create Your Account. Changing the registration surname from Edit Test to Changed Test immediately removed the SDK result and disabled Create Your Account. No additional WordPress account was created. The surname edit passed manually; email edits and stale-response/server-binding scenarios retain automated coverage.

## Review follow-up

Final code review found that a Sumsub approval could remain usable if the active provider changed to Prembly before account creation. Registration now rejects any provider-tagged attempt whose provider differs from the active provider. A controller regression test reproduces the switch and confirms rejection. Legacy Prembly attempts without a provider tag retain their existing behavior; Sumsub still requires its provider and configuration binding.

On November 10, beta 4 was installed on the development site and passed WordPress Plugin Check with all categories, errors and warnings enabled. The site owner activated the Sumsub Live trial; the dashboard showed 15 days and 50 individual checks remaining. Production credential setup and owner-operated Live testing are pending.
