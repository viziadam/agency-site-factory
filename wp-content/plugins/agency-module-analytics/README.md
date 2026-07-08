# Agency Module: Analytics 1.12

Consent-aware first-party and GA4 analytics integration for Agency Site Factory.

## Developer structure

The module already exposes its public behavior from `agency-module-analytics.php`; new work should follow the shared feature-plugin layout:

- `bootstrap/` for future loader extraction.
- `includes/services/` for reporting, consent and provider services.
- `includes/repositories/` for event queries.
- `includes/admin/` for wp-admin settings.
- `includes/frontend/` for client portal and frontend consent behavior.
- `includes/rest/` for reporting or event APIs.
- `templates/admin/` and `templates/frontend/` for extracted markup.
- `assets/css/` and `assets/js/` for new assets.

Current public hooks, settings keys and the `/admin/` portal tab must remain backward-compatible.
