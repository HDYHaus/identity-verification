# Prembly setup verification — 0.10.0-beta.9

Source commit: `99e4fe585866247bd8b3ee76b177feb79ff8644c`.

Release: https://github.com/HDYHaus/identity-verification/releases/tag/v0.10.0-beta.9

## Checks

- Full automated test suite passed, including the settings rendering check for a registration URL with a subdirectory, port, and escaped query parameters.
- WordPress coding standards and changed PHP syntax checks passed.
- Release ZIP was built from the pushed commit; archive integrity checks passed.
- Uploaded the published release artifact through the WordPress plugin installer on dev.hdyhaus.com. WordPress confirmed replacement of 0.10.0-beta.8 with 0.10.0-beta.9 and reported a successful update.
- Opened the installed Prembly settings and verified that the read-only SDK Redirect URL is `https://dev.hdyhaus.com/wp-login.php?action=register`.
- Verified that the environment choice, blank Webhook URL, and separate post-registration Success Redirect URL explanations appear on that screen.
- The existing active provider remains Sumsub and Prembly's saved environment remains Live / Production. No registration policy change was needed to inspect the new guidance.

This change was checked through automated tests and the installed settings UI. A new end-to-end identity-verification session was not run for this documentation/settings-description change.

## Landing page repair

Editing the page through the visual editor converted the original unwrapped style block into classic content and inserted paragraph and line-break tags into its CSS. Restored the CSS by removing that formatting and placing it inside an explicit Custom HTML block. Whitespace-normalized CSS matches the existing original stylesheet; content after the style block was preserved exactly during the repair.

Verified the public page at 1440 px and 390 px widths. The desktop hero uses its original two-column grid; the mobile hero stacks correctly. The page width matches the viewport, and the example registration URL remains within the mobile content area. The new setup instructions remain published.

The repaired page source is saved in `docs/landing-page-current.html`; future maintenance instructions are recorded in `AGENTS.md`.
