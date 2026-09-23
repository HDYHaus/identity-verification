=== TrustGate Registration ===
Contributors: hdyhaus
Tags: kyc, identity verification, registration, prembly
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verify people during WordPress registration with pluggable identity verification providers.

== Description ==

TrustGate Registration is a WordPress plugin for identity-verified account registration. It is designed around provider adapters, starting with Prembly.

The first production goal is to block default WordPress registration until a visitor has completed identity verification.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/trustgate-registration`.
2. Activate TrustGate Registration.
3. Go to Settings > TrustGate Registration.
4. Configure the Prembly public key, secret key, configuration ID, and optional App ID.

== Frequently Asked Questions ==

= Does this plugin send data to third parties? =

Yes. When configured with a provider such as Prembly, registration details required for verification may be sent to that provider.

= Are Prembly secret keys exposed to visitors? =

No. The public widget key is sent to the browser, but the secret API key is used only by WordPress for server-side status confirmation.

= Is this production ready? =

Not yet. The first scaffold is for development and integration work.

== Changelog ==

= 0.1.0 =

Initial scaffold.
