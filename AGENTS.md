# Plugin release and site editing workflow

- Keep Prembly setup releases isolated from the Sumsub development branch. Start the Prembly branch from current stable main and verify the release contains no Sumsub adapter or SDK changes.

- Push reviewed source changes to GitHub before deploying them to the development site.
- Build a versioned release ZIP from the pushed commit, publish it as a development prerelease when appropriate, and test that exact artifact on dev.hdyhaus.com through the normal WordPress plugin uploader. Do not edit deployed plugin PHP directly as a substitute for testing the release.
- Report automated checks and observed development-site results separately. Do not claim a full identity-verification smoke test unless one was performed.

# Landing page maintenance

- The plugin landing page is https://hdyhaus.com/wp-plugins/identity-verification/ (WordPress page 1285).
- The verified page source is saved in docs/landing-page-current.html. Read the live page before editing; the saved copy is a recovery reference, not permission to overwrite newer content.
- Preserve the existing design during content edits. Keep the CSS inside its explicit `<!-- wp:html -->` / `<!-- /wp:html -->` Custom HTML block. Do not let the visual/classic editor add `<p>` or `<br>` tags inside `<style>`.
- For targeted content changes, use the WordPress code editor and preserve all unrelated markup, classes, styles, links, and page settings.
- After saving, inspect the public page at desktop and mobile widths. Check that the CSS still parses, the layout and typography remain intact, the new text is present, and there is no horizontal overflow. Save screenshots as evidence and restore any temporary viewport override.
- Sandbox is recommended for testing; customers may configure Prembly Production first. Never describe Sandbox-first setup as mandatory.
