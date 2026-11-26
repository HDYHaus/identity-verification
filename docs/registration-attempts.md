# Registration Attempts and Account Recovery

HDYHaus Identity Verification stores a temporary record for registration attempts so site administrators can diagnose failed signups and resolve a verified registration if WordPress account creation did not complete.

## Find and review an attempt

In WordPress, open **HDYHaus Identity Verification > Registration Attempts**. The page includes attempts from the configured verification environments, with a status filter and pagination. The list masks email addresses. Expand a row to inspect the available details, including the status/reason, provider session reference, and whether a WordPress account is linked.

Statuses distinguish successful linked account creation, verified-but-unlinked attempts, and verification or registration failures. The page is an operational record of the WordPress registration flow; it is not a Prembly report viewer. Provider reports remain available through the provider account, subject to its own retention and permissions.

## Recover a verified registration

Recovery is an administrator action on an eligible verified-but-unlinked attempt:

1. Review the attempt and confirm that it belongs to the person requesting help.
2. Confirm that no existing WordPress account should be used for this person. This confirmation is required to avoid creating a duplicate account.
3. Enter an available username and submit **Create account and send password setup email**.
4. WordPress creates and links the account, records the recovery action, and sends its password-setup email.

The person does not repeat identity verification. Recovery is not available for an unverified/failed, expired, already-linked, or replayed attempt. Resolve username or email conflicts first, then use the existing account if one is appropriate. The recovery path never treats a provider submission message alone as proof: the plugin must have a successful, server-confirmed verification record.

## Resuming registration

After a successful verification where account creation fails, the plugin records the attempt and emails the registrant a secure link to resume registration. The link can restore the submitted registration details and verified session without redoing the identity check. It expires after at most seven days and also becomes unusable when the attempt itself expires or is linked to an account. If the link is unavailable or has expired, the site administrator can review the attempt and use the recovery action when it remains eligible.

## Retention and stored fields

Site owners configure retention under **HDYHaus Identity Verification > Registration**:

| Record type | Default | Allowed range |
| --- | ---: | ---: |
| Unlinked attempts | 7 days | 1–365 days |
| Account-linked records | 90 days | 1–365 days |

When a retention setting changes, expiry is recalculated for records still retained in the corresponding category. Resume links remain capped at seven days. Expired attempts are removed during scheduled cleanup. One-way session-reference and identity hashes used to prevent replay are held separately from attempt records and may outlive attempt deletion, account deletion, or privacy erasure.

The attempt table contains the registration email, username and name, environment, status and failure reason, timestamps, consent information, provider/session reference, WordPress user link, and recovery audit fields as needed for the workflow. Email is masked in the administrator list, but stored in full in the WordPress database because it is needed to resume registration, create the account, and send email. The plugin does not copy identity documents, document numbers, selfies, biometric information, raw Prembly responses, or detailed provider reports into WordPress.

The retention settings are not a substitute for a site's privacy policy or legal review. Update the privacy notice to describe this temporary registration data and coordinate relevant deletion procedures with Prembly.
