# Module setup orchestration

Agency Site Factory 1.12 turns an active production module into a usable site feature. The same setup service is used by Agency Kit, Factory Manager and WP-CLI.

## User interface

In the client site open **Agency Kit → Modules**. Each production module shows dependencies, page state, email warnings and links to generated pages. **Apply module setup** creates only missing pages. **Repair module pages** requires confirmation and only appends a missing required shortcode; it never replaces existing page content.

The Factory Manager project dashboard exposes the same Apply, Repair and status actions. Keep the LocalWP site running because those buttons call WordPress through the detected LocalWP PHP and WP-CLI runtime.

## Dependencies

Booking requires Auth and Legal. Enabling Booking auto-enables both installed plugins. Auth or Legal cannot be disabled until Booking is disabled. Booking and Auth use the shared Email Service; `wp_mail` remains a fallback, but setup stays in warning state until sender configuration and a test email succeed.

## Pages and automatic placement

- Auth: `/bejelentkezes/`, `/regisztracio/`, `/fiokom/`, `/jelszo-visszaallitas/`, `/email-megerosites/`
- Booking: `/idopontfoglalas/`, `/foglalasaim/`, `/foglalas-koszonjuk/`; business management is added modularly to the shared `/admin/` portal.
- Legal: `/adatkezelesi-tajekoztato/`, `/cookie-tajekoztato/`, optional `/impresszum/`

Module settings control `auto_create_required_pages`, `auto_add_pages_to_menu`, `auto_add_booking_cta`, `auto_add_auth_links` and `auto_add_footer_legal_links`. Booking sets an empty/default Agency Kit booking URL to `/idopontfoglalas/`, repairs an existing booking CTA or appends one to the front page.

## CLI fallback

```text
wp agency module status
wp agency module setup booking
wp agency module setup all
wp agency module setup booking --repair
wp agency module setup booking --repair-display
```

Setup is idempotent. It never deletes pages, bookings, users, media or settings, and it does not overwrite existing client content.
