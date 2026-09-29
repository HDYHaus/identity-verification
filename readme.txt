=== TrustGate Registration ===
Contributors: hdyhaus
Tags: identity verification, kyc, registration, security, prembly
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verify a person's identity before WordPress creates their account, without replacing the native registration flow.

== Description ==

TrustGate Registration adds identity verification to the default WordPress registration page. When a provider is enabled, WordPress creates the account only after the provider confirms a successful verification.

TrustGate is a verification gate, not a membership suite. It does not replace the registration form, add payment processing, restrict content, or create membership levels.

= Features =

* Adds identity verification to `wp-login.php?action=register`.
* Confirms the result server-side before allowing account creation.
* Binds each verification to the submitted email, configured widget, and one-time registration attempt.
* Prevents a completed verification session from being reused for another account.
* Keeps Sandbox and Live Prembly credentials separate.
* Supports an optional same-site success redirect with email activation instructions.
* Stores normalized verification metadata in the WordPress user account.
* Integrates with WordPress personal data export and erasure tools.
* Uses a provider adapter architecture, with Prembly as the first provider.

TrustGate supports identity verification and KYC workflows. Installing it does not by itself make a website compliant with any law or regulatory framework. Site owners remain responsible for their verification policy, lawful basis, privacy notice, retention period, and provider configuration.

== External Service: Prembly ==

This plugin connects to Prembly, an external identity verification service, when Prembly is enabled by a site administrator.

The Prembly browser SDK is loaded from `https://js.prembly.com/v1/inline/widget-v3.js` on the default WordPress registration page. When a registrant selects Verify Identity, TrustGate sends their first name, last name, email address, a temporary user reference, the configured Widget Key, and Configuration ID to Prembly. Prembly's verification interface may collect identity document images, selfies, biometric information, device information, and IP-derived location information according to the checks selected by the site administrator.

After the verification interface reports completion, the site's WordPress server requests the corresponding session from `https://backend.prembly.com/api/v1/checker-widget/sdk/sessions/{session_id}/`. TrustGate uses that response to confirm the session status, email address, widget configuration, and session ID. The complete Prembly report remains available in the site owner's Prembly dashboard; TrustGate stores only normalized verification metadata in WordPress.

This service is provided by Prembly Inc:

* Prembly: https://prembly.com/
* Prembly Terms of Use: https://prembly.com/terms
* Prembly Privacy Policy: https://prembly.com/Policy

== Installation ==

1. Install and activate TrustGate Registration.
2. In WordPress, enable **Settings > General > Anyone can register**.
3. In Prembly, create separate SDK Setup widgets for Sandbox and Production as needed.
4. In WordPress, open **TrustGate > Settings > Prembly**.
5. Add the Widget Key and Configuration ID for the appropriate environment.
6. Choose Sandbox or Live and select **Enable Prembly**.
7. Add identity verification information to the site's privacy policy. Suggested text is available under **Settings > Privacy**.
8. Test the full registration and password-setup email flow before opening registration to visitors.

No Prembly Secret API Key, Organisation ID, or App ID is required for the SDK session confirmation used by this plugin.

== Frequently Asked Questions ==

= Does TrustGate replace the WordPress registration form? =

No. Version 0.9.0 integrates with the default WordPress registration screen. It does not currently add verification to WooCommerce, BuddyPress, MemberPress, Ultimate Member, or other custom registration forms.

= What happens when Prembly is disabled? =

TrustGate registers no verification hooks or Prembly scripts. Normal WordPress registration continues, and saved Prembly settings are preserved.

= Does this plugin send data to a third party? =

Yes, when Prembly is enabled and a registrant starts verification. See the **External Service: Prembly** section for the exact service, data flow, and legal links.

= Are secret API keys required or exposed? =

No. This integration uses Prembly's Widget Key and Configuration ID in the browser. The session confirmation endpoint uses the session ID and does not require the Secret API Key, Organisation ID, or App ID.

= Does TrustGate store identity documents or selfies in WordPress? =

No. TrustGate stores the verification result, time, provider slug, Prembly session reference, a one-way replay-prevention hash, and consent time as user metadata. Documents, selfies, biometric information, and detailed reports are processed by Prembly and are not copied into WordPress by this plugin.

= Can verification metadata be exported or erased? =

Yes. TrustGate integrates with **Tools > Export Personal Data** and **Tools > Erase Personal Data**. Erasure retains only a one-way session-reference hash to prevent reuse of a completed verification. Site owners should establish an appropriate retention policy and confirm any separate deletion obligations in Prembly.

= Does this make my site KYC compliant? =

No plugin can guarantee legal or regulatory compliance. TrustGate provides an identity-verification gate that can support a site's KYC workflow. Obtain appropriate legal advice for the site's countries, audience, and use case.

== Changelog ==

= 0.9.0 =

* Require the Prembly session ID, widget configuration, and end-user email to match before registration.
* Add explicit registrant consent before opening identity verification.
* Add WordPress privacy policy guidance and personal data export and erasure support.
* Correct the Prembly external-service disclosure and current credential requirements.
* Add provider response fixture tests and repeatable release packaging.

= 0.1.6 =

* Use Prembly's session-ID-only status lookup and remove unused API key, Organisation ID, and App ID requirements.

= 0.1.5 =

* Apply a same-site success redirect with email activation instructions.

= 0.1.4 =

* Add exclusive provider tabs, provider resolution, and provider-specific advanced settings.

= 0.1.3 =

* Add a top-level TrustGate admin area, Plugins screen settings link, and separate Sandbox and Live credentials.

= 0.1.2 =

* Use Prembly's widget-specific key and current V3 browser SDK.

= 0.1.1 =

* Support Prembly V3 SDK session callbacks and server-side session confirmation.

= 0.1.0 =

* Initial development scaffold.
