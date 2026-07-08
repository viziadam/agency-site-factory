# Accessibility, performance and visual QA

## Implemented safeguards

- Semantic header, navigation, main, section, article and footer landmarks.
- Keyboard skip link and visible `:focus-visible` styles.
- Native `<details>/<summary>` FAQ interaction without JavaScript dependency.
- `prefers-reduced-motion` handling.
- Responsive images through WordPress image functions and registered image sizes.
- No webfont, CDN or frontend framework dependency.
- Escaped template output and allow-listed component paths.
- Schema automatically disabled when a supported SEO plugin already owns JSON-LD.

## Release checklist

Run this against every imported blueprint and every supported browser:

1. Navigate the full page using only Tab, Shift+Tab, Enter and Space.
2. Test at 320, 768, 1024 and 1440 CSS pixels.
3. Run Lighthouse mobile audits; target Accessibility ≥ 95 and Performance ≥ 90 on cached local content.
4. Run axe/WAVE and resolve every critical or serious issue.
5. Verify heading order, landmark names and form labels with a screen reader.
6. Validate the rendered JSON-LD in Schema.org Validator and Google Rich Results Test.
7. Import the same blueprint twice and run `tests/wp-cli-idempotency.sh`.
8. Capture reference screenshots for home, service, article, archive, contact and 404 pages.

Visual regression automation is intentionally not bundled into the runtime. Add Playwright screenshot baselines in CI once the production browser matrix and canonical demo content are fixed.

