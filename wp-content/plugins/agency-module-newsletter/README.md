# Agency Module: Newsletter 1.12

Newsletter signup, confirmation and export skeleton for Agency Site Factory.

## Developer structure

New newsletter work should use the shared feature-plugin layout:

- `bootstrap/` for the module loader.
- `includes/services/` for subscription, confirmation and email workflows.
- `includes/repositories/` for subscriber storage.
- `includes/migrations/` for dbDelta schema changes.
- `includes/admin/` for wp-admin list/export screens.
- `includes/frontend/` for shortcodes and submit handlers.
- `includes/rest/` for future API endpoints.
- `templates/frontend/` and `templates/admin/` for markup.
- `assets/css/` and `assets/js/` for canonical assets.

Keep existing shortcode names, admin-post actions and database tables backward-compatible.
