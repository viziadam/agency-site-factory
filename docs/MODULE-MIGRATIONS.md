# Module migration policy

- Agency Core shared service schema: `agency_module_services_db_version`.
- Booking schema: `agency_module_booking_db_version`, current `1.0.0`.
- Migrations run only when the installed version is lower than the code version.
- Tables are created or altered with WordPress `dbDelta`.
- Every completed migration is written to the shared activity log.
- Factory manifests include `module_versions` and `module_migrations`.
- No uninstall routine drops tables, users, pages, settings or runtime data.

Before updating a client site, Factory Manager requires a successful dry-run for the current source version and creates timestamped directory backups. If an update fails, follow the exact rollback directory list printed in its operation log.

