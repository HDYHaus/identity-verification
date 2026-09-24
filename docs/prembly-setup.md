# Prembly Setup

This guide creates a reproducible Prembly sandbox widget for identity verification during WordPress registration. Dashboard labels reflect Prembly as observed on September 24, 2026.

Never add Prembly secret keys to source control, screenshots, support tickets, or public documentation.

## Recommended Widget

In the Prembly dashboard, make sure the environment selector is set to **Sandbox**. Open **Integrations > SDK Setup**, choose **Add New SDK**, then choose **Manual Setup**.

Use these settings:

1. Services
   - Enable **Document Verification**.
   - Select **All Countries**.
   - Select **All Document Types**: Passport, Driver's License, and Government Issued Identity Card.
   - Leave Data Verification off for the baseline widget. It uses country-specific government data sources and can be added for supported markets later.
   - Leave Address Verification off unless proof of address is part of the site's registration policy.
2. Brand Details
   - Brand name: the site or community name. The development widget uses `TrustGate Registration`.
   - Description: `Verify your identity to complete account registration.`
   - Theme: choose a color that meets the site's contrast and brand requirements.
   - Redirect URL: the WordPress registration URL. For the Local development site, this is `http://trustgate-registration.local/wp-login.php?action=register`.
   - Webhook URL: leave blank until TrustGate implements and documents a webhook receiver.
3. Security/Fraud Check
   - PEP screening: Off for the baseline identity check. Enable it only when the site's compliance policy requires AML or politically exposed person screening.
   - Face Scan: On.
   - Skip instructions: Off.
   - Face Comparison: On, so the selfie can be compared with the identity document.
   - Face Confidence Level: `80` for the initial sandbox configuration. Revisit this threshold using real test results and Prembly guidance before production.
4. Customization
   - Welcome message: `Verify your identity securely to finish creating your account.`
   - Logo: optional.
5. Review the summary and choose **Setup SDK**.

The saved widget appears under **Integrations > SDK Setup**. Use **Copy Config ID** to copy its complete configuration ID.

## WordPress Settings

Open **Settings > TrustGate Registration** in WordPress and map the fields as follows:

| TrustGate field | Prembly source |
| --- | --- |
| Mode | `Test` while the Prembly dashboard is in Sandbox mode |
| Test Public Key | **API Integrations > Public Key** while Sandbox is selected |
| Live Public Key | **API Integrations > Public Key** while Live/Production is selected |
| Secret API Key | **API Integrations > Secret Key** for the currently selected TrustGate mode |
| App ID | Leave blank unless Prembly support or a legacy Identitypass account explicitly provides one |
| Configuration ID | **SDK Setup > Copy Config ID** for the saved widget |
| Status Endpoint | Leave blank to use TrustGate's default Prembly status endpoint |
| Success Redirect URL | Optional page to visit after verified WordPress registration |

The current plugin has one Secret API Key field. Replace it with the matching production secret when switching TrustGate from Test to Live mode.

## Why This Baseline

Document verification works across the broadest set of countries and identity documents. Face Scan plus Face Comparison adds evidence that the person registering is the person shown on the submitted document. Country-specific data checks, address checks, and PEP screening remain optional because they depend on geography, legal obligations, acceptable registration friction, and Prembly pricing.

## Production Checklist

- Complete Prembly's business onboarding requirements and obtain live credentials.
- Replace the Local redirect URL with the production HTTPS registration URL.
- Confirm the production domain is permitted by Prembly.
- Publish privacy and consent language covering document and biometric processing.
- Confirm retention, deletion, and data-subject request procedures with legal counsel and Prembly.
- Test successful, failed, cancelled, expired, and repeated verification attempts.
- Complete TrustGate replay protection and end-to-end tests before enforcing verification on a production registration form.
