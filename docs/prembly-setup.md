# Prembly Setup

Use this guide to configure a Prembly widget for identity verification during WordPress registration. Choose Sandbox or Production according to your needs. Sandbox is recommended for testing, but you can start with Production if you are ready for live checks. Dashboard labels and screen order may vary; the Brand Details screen shown in November 2026 includes both Redirect URL and Webhook URL.

Never add Prembly secret keys to source control, screenshots, support tickets, or public documentation.

## Recommended Widget

Before creating the widget, open **Identity Verification > Settings > Prembly** in WordPress. Copy the read-only **Prembly SDK Redirect URL** displayed there. It is generated for the current site; select the URL and copy it. If your installed version does not display this field, use your site's native WordPress registration URL as described under Brand Details below.

In the Prembly dashboard, select **Sandbox** or **Production**. Open **Integrations > SDK Setup**, choose the option to add a widget (such as **Add New SDK**), then choose **Manual Setup** if offered. Create a separate widget for each environment you use. You do not need to create a Sandbox widget before a Production widget.

The dashboard may show Brand Details first. Follow the field names below even if the order differs.

Use these settings:

1. Brand Details
   - Brand name: the site or community name. The development widget uses `HDYHaus Identity Verification`.
   - Short Description: `Verify your identity to complete account registration.`
   - Theme: choose a color that meets the site's contrast and brand requirements.
   - **Redirect URL:** paste the **Prembly SDK Redirect URL** copied from the plugin settings. This is the WordPress registration URL, normally `https://your-domain.com/wp-login.php?action=register`. Replace the example domain with your own; preserve any WordPress subdirectory or local development port. For example, WordPress installed at `https://example.com/community` uses `https://example.com/community/wp-login.php?action=register`. Use the plugin's displayed URL rather than constructing one if registration URL filters are in use.
   - This URL is separate from the plugin's optional **Success Redirect URL**. Prembly's field uses the registration page; the plugin's success redirect runs only after WordPress creates the account.
   - Webhook URL: leave blank until HDYHaus Identity Verification implements and documents a webhook receiver.
2. Services
   - Enable **Document Verification**.
   - Select **All Countries**.
   - Select **All Document Types**: Passport, Driver's License, and Government Issued Identity Card.
   - Leave Data Verification off for the baseline widget. It uses country-specific government data sources and can be added for supported markets later.
   - Leave Address Verification off unless proof of address is part of the site's registration policy.
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

The saved widget appears under **Integrations > SDK Setup**. Use **Copy Config ID** to copy its complete configuration ID. Open the widget's row menu and choose **Integration** to find its Widget Key.

## WordPress Settings

Open **Identity Verification > Settings** in WordPress and map the fields as follows:

Open the **Prembly** tab and select **Enable Prembly**. HDYHaus Identity Verification permits one active verification provider at a time. Clearing the checkbox disables identity verification and allows the normal WordPress registration flow, but it does not remove saved Prembly widget settings.

| HDYHaus Identity Verification field | Prembly source |
| --- | --- |
| Active environment | **Sandbox / Test** for a Sandbox widget, or **Live / Production** for a Production widget |
| Prembly SDK Redirect URL (read-only) | Copy this site-generated registration URL into **Redirect URL** on Prembly's **Brand Details** screen |
| Sandbox Widget Key | **SDK Setup > Integration > Widget Key** for a widget created while Sandbox is selected |
| Sandbox Configuration ID | **SDK Setup > Copy Config ID** for the Sandbox widget |
| Live Widget Key | **SDK Setup > Integration > Widget Key** for a widget created while Live/Production is selected |
| Live Configuration ID | **SDK Setup > Copy Config ID** for the Live widget |
| Consent message | Plain-text consent wording appropriate for the site's verification purpose and legal basis |
| Provider Privacy Policy URL | Prembly privacy policy or the applicable provider-specific policy URL; optional |
| Provider Terms / Consent URL | Prembly terms or another applicable provider consent document; optional |
| Success Redirect URL | Optional page to visit after verified WordPress registration |

HDYHaus Identity Verification stores Sandbox and Live widget settings independently. Prembly support confirmed that the fixed `GET https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{session_id}/` endpoint uses the session ID in the URL only. It does not require a Secret API Key, Organisation ID, or App ID. The Organisation ID remains an account-level UUID used by other Prembly APIs when a user belongs to more than one organisation; it is the same for Sandbox and Production.

The Success Redirect URL must be on an allowed WordPress host. WordPress uses it after the verified account has been created successfully; completing the Prembly widget alone does not redirect the visitor. HDYHaus Identity Verification appends `trustgate_registration=complete` and displays a notice telling the new user to check their email for the password setup link.

Consent wording and provider legal links are managed on the **Registration** tab. HDYHaus Identity Verification sanitizes the statement as plain text and the links as URLs. Empty provider links are omitted, while the site's WordPress Privacy Policy is appended automatically when configured. Changing the wording or any displayed policy URL produces a new stored consent-version fingerprint for subsequent successful registrations.

The Widget Key begins with `wdgt_`. Prembly's API Integrations public key begins with a different prefix and cannot initialize an SDK widget; using it produces an `Invalid widget ID or key` error.

## Local Camera Testing

The development site uses Local's `localhost` Router Mode because SSL certificate trust is unavailable on the current machine. Browsers treat `http://localhost` as a trustworthy local origin for camera access even though Local does not provide HTTPS in this mode.

After changing Local's Router Mode, use Local's **Fix it** action to update the WordPress `home` and `siteurl` values to the assigned localhost address. Confirm that the registration page, its asset URLs, and `admin-ajax.php` all use the same localhost origin. Localhost ports may differ between installations, so use the port shown by Local and update the Prembly Redirect URL accordingly.

## Why This Baseline

Document verification works across the broadest set of countries and identity documents. Face Scan plus Face Comparison adds evidence that the person registering is the person shown on the submitted document. Country-specific data checks, address checks, and PEP screening remain optional because they depend on geography, legal obligations, acceptable registration friction, and Prembly pricing.

## Production Checklist

- Repeat the successful Sandbox and Production smoke tests after every change to the Prembly adapter or browser SDK integration.
- Complete Prembly's business onboarding requirements and obtain live widget access.
- Use the production site's HTTPS registration URL for the Production widget; copy it from that site's Prembly settings. A Production widget can be configured first.
- Confirm the production domain is permitted by Prembly.
- Review the HDYHaus Identity Verification consent text and add the suggested disclosure under **Settings > Privacy** to the site's published privacy policy.
- Confirm retention, deletion, and data-subject request procedures with legal counsel and Prembly.
- Test successful, failed, cancelled, expired, and repeated verification attempts.
- Confirm the WordPress personal data exporter and eraser include HDYHaus Identity Verification verification metadata.
