# Changelog

## 0.1.5

- Apply the configured same-site redirect after WordPress creates a verified account and show email activation instructions.
- Clarify the difference between widget configuration and authenticated Prembly session confirmation.

## 0.1.4

- Replace the provider dropdown with exclusive provider tabs and enable checkboxes.
- Add a provider registry that resolves at most one active verification adapter.
- Allow normal WordPress registration when no provider is enabled.
- Move Prembly-specific advanced settings into the Prembly tab.
- Preserve existing Prembly activation and credentials during upgrade.

## 0.1.1

- Support Prembly's V3 SDK callback and session identifier.
- Confirm sandbox and live widget completions through the SDK session endpoint.
- Add optional authenticated session lookup with a Prembly Organisation ID.
- Show normalized Prembly failure codes while TrustGate is in Test mode.

## 0.1.0

- Initial plugin scaffold.
- Add provider adapter contract.
- Add Prembly provider placeholder.
- Add WordPress registration hooks.
- Bind verified Prembly references to short-lived, one-time registration attempts.
- Reject reused verification references and unverified user metadata writes.
- Add settings page shell.
- Add WPCS and CI scaffolding.
