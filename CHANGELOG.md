# Changelog

## 1.12.0

- Reworked `/admin/` into a standalone client administration surface that bypasses the active theme and WordPress page template rendering.
- Added a dedicated `agency_portal_session` access-token cookie backed by server-side hashed sessions; portal login no longer creates a normal WordPress auth cookie.
- Added a portal action dispatcher so module forms can run inside `/admin/` without requiring wp-admin or `admin-post.php` access.
- Updated Booking and Analytics portal forms to use standalone portal actions while keeping legacy wp-admin/admin-post fallbacks.
- Hardened portal credential updates so existing administrator accounts are not demoted when reused as portal users.

## 1.11.0

- Refactored feature modules into a shared developer-friendly layout with `bootstrap`, focused `includes/*` layers, templates and canonical asset folders.
- Split Auth and Legal from monolithic plugin files into services, frontend and admin files while keeping shortcodes, admin-post actions and Agency section IDs backward-compatible.
- Moved Booking to the same canonical module structure with compatibility wrappers for old include paths.
- Added `docs/EXTENDING.md` and refreshed module README files.
- Added a module structure contract test so future modules keep the same layout.

## 1.10.0

- Unified, full-screen `/admin/` client portal with a dedicated login and WordPress-managed credentials.
- Secure random initial `agencyadmin` password shown once in wp-admin, plus credential reset controls.
- Filter-based modular portal navigation for Booking, Analytics and future modules.
- First-party, consent-aware page-view and interaction analytics with page-by-page reporting.
- Booking button click and completed-booking conversion reporting.
- Frontend website identity/contact settings and Booking availability management.

## 1.9.0

- Four-step service, date, time and customer-details booking wizard with live availability calendar.
- Service-specific durations and prices in the booking flow.
- Dedicated frontend business portal with restricted Booking Manager role and branded sign-in.
- Booking approval/rejection, operational statistics and calendar settings outside wp-admin.
- One-click manager invitation and automatic portal page provisioning.
- Double opt-in Newsletter flow with consent retention, unsubscribe and safe CSV export.
- Consent-aware GA4 loader and responsive Legal analytics-consent banner.

## 1.8.0

- Rebuilt Booking around a versioned 1.1 database schema and WordPress-timezone availability engine.
- Added responsive date/slot picker, REST availability endpoint, concurrency lock and approved/pending blocking rules.
- Added configurable weekdays, hours, closed dates, special openings, duration, access and approval mode.
- Added client dashboard statistics, upcoming calendar, filters, complete status actions, notes, resend and CSV export.
- Added canonical Auth/Booking/Legal shortcodes and a shared Agency section render pipeline.
- Expanded Auth dashboard, page assignments, dynamic navigation and responsive account/forms.
- Expanded Legal page publication settings, missing-page generation, footer repair and portable links shortcode.
- Expanded Email health with test/delivery timestamps and wp_mail failure capture.
- Added detailed module health for dependencies, pages, shortcodes, display, email, admin UI and database.
- Added transactional booking availability tests and a deployment checklist.

## 1.7.0

- Added idempotent module setup orchestration with dependency enforcement, page provisioning and explicit shortcode repair.
- Booking now auto-enables Auth and Legal; dependent modules cannot be disabled while Booking is active.
- Added Auth, Booking and Legal Agency section components backed by the same shortcode render pipeline.
- Added setup status/actions to Agency Kit and Factory Manager, plus WP-CLI setup/status commands.
- Added email health state, automatic booking URL wiring, optional menu/footer integration and non-destructive setup tests.

## 1.6.0

- Added version-aware Factory Manager update checks, mandatory current dry-run, full backups and rollback guidance.
- Added module version/migration fields to manifests and Agency Kit/Factory Manager status views.
- Added shared module lifecycle, settings, dbDelta migrations, audit log, rate limiting, secure REST helper and non-destructive data policy.
- Added encrypted Email Service settings with wp_mail fallback, Brevo provider and admin test email.
- Replaced Auth placeholder with frontend registration/login/logout/reset, expiring verification, resend, customer role and brute-force controls.
- Replaced Booking placeholder with indexed custom table, guest/verified access, frontend form/list, managed statuses, admin UI and emails.
- Replaced Legal placeholder with editable consent texts, safe draft-page generation and footer integration.

## 1.5.0

- Added a localhost-only Agency Factory Manager with project list, create/edit wizard and validated target handling.
- Added UI-triggered dry-run, install/framework update and module update operations backed by the existing CLI engine.
- Added manifest, runtime, desired/installed/active module and operation-log views.
- Added automatic LocalWP WP-CLI and per-site php.ini discovery.
- Added CSRF protection, loopback access control, shell-free process execution and sensitive log redaction.
- Added a double-click Windows launcher and complete Factory Manager documentation.
- Added a read-only WP-CLI/database preflight so offline LocalWP sites fail before backups or file changes.

## 1.4.0

- Added validated client project configs and a LocalWP-compatible generator with dry-run and timestamped backups.
- Added fixed project manifests, Project/Modules admin screens and WP-CLI project/module commands.
- Added six independently activatable feature-module placeholder plugins.
- Added config-to-Agency-Settings application and module update tooling.
- Added project factory contract tests and client-site workflow documentation.

## 1.3.0

- Authenticated `POST /agency/v1/preview-sections` REST endpoint.
- `edit_post` capability, REST nonce és registry-alapú payload validáció.
- Teljes, iframe-be tölthető PHP preview dokumentum.
- Ugyanaz a header/footer, `agency_theme_render_sections()`, template part és CSS/token pipeline, mint a frontenden.
- 400 ms debounce, kérésmegszakítás és érthető iframe hibaállapot.
- A preview nem ment automatikusan; a Gutenberg meta marad a mentés forrása.
- Registryből generált text, textarea, URL, image, select, checkbox, number és repeater editor mezők.
- Központosított frontend style/script/token asset API.

## 1.2.0

- Központi `agency_theme_get_component_registry()` component/variant engedélylista.
- Backward-compatible legacy section normalizálás és schema version 3 migráció.
- Gutenberg component- és variant-választó, részletes section kártyák.
- Informatív Agency sections placeholder a Gutenberg vásznon.
- Hero: split, centered, image-card és minimal variáns.
- Services: grid, cards, icon-list és featured variáns.
- CTA: simple, boxed, full-width és booking variáns.
- Header: default, centered, compact és split variáns.
- Beauty és Consulting blueprint átállítva az új formátumra.
- Új variáns CSS kizárólag a meglévő design tokenekkel.

## 1.1.0

- No-build Gutenberg Agency sections dokumentumpanel.
- Validált komponens-registry és schema version 2 migráció.
- Manifest-alapú, több blueprintet kezelő importer.
- Új Consulting Firm blueprint.
- Biztonságos helyi médiaimport és kiemelt kép hozzárendelés.
- WP-CLI blueprint listázás és import.
- Strukturált cím, geo, nyitvatartás, Service/Offer és fejlettebb Article schema.
- Yoast, Rank Math, AIOSEO és SEOPress duplikált schema védelem.
- Szolgáltatás ár/pénznem admin meta.
- Fordítási POT katalógusok.
- DDEV setup, GitHub CI, contract/idempotencia tesztek és QA checklist.
- Akadálymentességi fókuszállapotok és reduced-motion támogatás.
- Javítva a perjelet tartalmazó komponenskulcsok renderelése.

## 1.0.0

- Első Agency Theme és Agency Core MVP.
