# Agency Module: Legal 1.12

Editable legal page templates, shared consent texts and analytics consent banner.

## Public compatibility

- `[agency_legal_links]`
- Agency section: `legal/links`
- Existing generated page metadata `_agency_legal_generated` is unchanged.

## Developer structure

- `bootstrap/plugin.php` loads the module.
- `includes/services/settings.php` owns defaults, consent text lookup and template token replacement.
- `includes/services/page-generator.php` creates Privacy, Cookie and Imprint pages without overwriting client-authored pages.
- `includes/frontend/legal-links.php` renders footer/legal shortcodes.
- `includes/frontend/cookie-banner.php` renders the consent banner and enqueues assets.
- `includes/admin/` contains the settings page, save handler and menu registration.
- `includes/services/component-registry.php` registers the Agency section.
- `assets/css/legal.css` and `assets/js/legal.js` are canonical asset paths; legacy files remain in `assets/`.

Templates are starter text only and must be reviewed for the client and jurisdiction. Uninstall does not remove legal pages or settings.
