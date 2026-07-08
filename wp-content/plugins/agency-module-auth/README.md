# Agency Module: Auth 1.12

Frontend customer authentication built on WordPress users. Public shortcodes and Agency section IDs are backward-compatible:

- `[agency_auth]`, `[agency_auth_login]`, `[agency_auth_register]`
- `[agency_account]`, `[agency_auth_account]`
- Agency sections: `auth/login`, `auth/register`, `auth/account`

## Developer structure

- `bootstrap/plugin.php` loads the module.
- `includes/services/` contains settings, verification, role setup and component registry.
- `includes/frontend/` contains shortcode rendering, menu integration and form handlers.
- `includes/admin/` contains the wp-admin dashboard and save actions.
- `includes/repositories/`, `includes/migrations/`, `includes/rest/`, `templates/frontend/` and `templates/admin/` are reserved extension points for future Auth features.
- `assets/css/auth.css` is the canonical stylesheet. `assets/auth.css` remains as a legacy path.

## Security checklist

All state-changing forms use WordPress nonces. Registration, login, reset and resend are IP-rate limited through Agency Core. Verification tokens are random, stored only as password hashes and expire. Customers receive the `agency_customer` role and cannot access wp-admin.

Uninstall does not delete users or user metadata.
