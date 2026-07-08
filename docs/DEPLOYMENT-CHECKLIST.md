# Production deployment checklist

## Hosting

- WordPress 6.0 or newer and PHP 8.0 or newer.
- HTTPS enabled and WordPress Address/Site Address set to the production domain.
- WordPress timezone, locale and permalink structure configured.
- Agency Core, Agency Theme and required production modules installed and active.

## Module setup

1. Open **Agency Kit → Modules**.
2. Enable Booking; confirm Auth and Legal are automatically active.
3. Run **Apply full setup** for Auth, Legal and Booking.
4. Confirm every health item except Email is green.
5. Open generated pages with View page and inspect their content/design.
6. Confirm the homepage booking CTA, dynamic Auth links and footer Legal links.

## Booking

- Configure duration, hours, weekdays, closed and special open dates.
- Decide whether pending bookings block slots.
- Choose manual or automatic approval.
- Verify guest/login/verified-email rules.
- Make a real test booking and process every status in Agency Kit → Booking.
- Confirm the same approved slot disappears from the frontend selector.

## Email

- Configure sender name/email and reply-to under **Agency Kit → Email Service**.
- Use `wp_mail` only when the host's transactional mail is correctly configured.
- For Brevo, save the API key in the masked field and send a test message.
- Confirm the health table reports a successful test and no last error.
- Test Auth verification plus Booking customer/admin/status messages.

## Legal and security

- Replace starter legal text with client/jurisdiction-specific reviewed content.
- Publish the required pages and verify footer/checkbox links.
- Never copy LocalWP credentials, paths or test secrets to production.
- Keep backups before framework/module updates.
- Review Activity Log after setup and after the first production workflow.

## Smoke test

- Front page, booking, login, registration, account and legal pages return HTTP 200.
- No PHP fatal, browser console error or mixed content.
- Customer cannot enter wp-admin.
- Booking calendar returns only valid future slots.
- Existing client pages and records remain unchanged after a second setup run.
