# Agency Module: Booking 1.12

Production appointment booking for WordPress 6+ and PHP 8+.

## Public compatibility

- `[agency_booking_form]`: responsive service, date and live available-slot selector.
- `[agency_booking_customer_list]`: signed-in customer's appointment cards.
- Legacy aliases `[agency_booking]` and `[agency_booking_list]` remain supported.
- Legacy manager shortcodes `[agency_booking_manager_login]` and `[agency_booking_manager_portal]` delegate to the shared `/admin/` client portal when Agency Core is active.
- REST routes stay unchanged: `/wp-json/agency/v1/booking-slots` and `/wp-json/agency/v1/booking-calendar`.
- Agency sections: `booking/form` and `booking/customer-list`.

## Developer structure

- `bootstrap/plugin.php` is the module loader.
- `includes/repositories/bookings.php` contains persistence, settings, migration, roles, availability and email helpers.
- `includes/frontend/booking-shortcodes.php` renders the customer booking UI and registers REST availability endpoints.
- `includes/frontend/client-portal.php` adds booking tabs to the shared `/admin/` client portal.
- `includes/admin/bookings-admin.php` contains wp-admin booking screens, approve/reject actions, settings save and CSV export.
- `includes/services/component-registry.php` registers assets and Agency section integration.
- `includes/data.php`, `includes/frontend.php`, `includes/admin.php`, `includes/portal.php` and `includes/integrations.php` remain compatibility wrappers.
- `assets/css/` and `assets/js/` are canonical for new frontend/admin assets; legacy asset files remain in `assets/`.

Data lives in `{prefix}agency_bookings` schema 1.1.0 with date/status/email/user/service indexes. Deactivation or uninstall does not delete bookings.
