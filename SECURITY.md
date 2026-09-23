# Security Policy

TrustGate Registration handles identity-verification state and must fail closed.

Please do not open public issues for vulnerabilities. Report security concerns privately to the repository maintainers.

Security expectations:

- Provider callbacks and webhooks must be verified server-side.
- Widget callbacks are never trusted as proof by themselves.
- Secret API keys must remain server-side.
- Registration must be blocked when verification state is missing, expired, or ambiguous.
