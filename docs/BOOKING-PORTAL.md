# Booking wizard and business portal

## Automatic setup

Enable Booking in **Agency Kit → Modules**, then run **Apply module setup**. The operation is idempotent and creates the public booking page, customer list, thank-you page, manager sign-in and business dashboard without deleting existing content.

## Public booking

The `[agency_booking_form]` shortcode renders a four-step wizard:

1. service, duration and price;
2. monthly availability calendar;
3. real-time available starting times;
4. customer and privacy details.

Availability uses the WordPress timezone, service-specific duration, working days, opening hours, closed dates, special open dates and existing pending/approved bookings. The MVP models one shared bookable calendar, so overlapping services block each other. Submission repeats the availability check under a day-level database lock to prevent double booking.

Set a service's price in the Agency Service price fields and its duration in the **Booking details** meta box.

## Business manager portal

In **Agency Kit → Booking Settings → Business portal access**, enter a manager name and email, then click **Create manager and send invite**. The user receives WordPress's secure password setup email and signs in at:

`/admin/`

The restricted `Booking Manager` role cannot use wp-admin. The unified frontend portal provides:

- today, 7-day, 30-day and pending statistics;
- booking list with customer contact data;
- approve and reject actions with nonce and capability checks;
- working days and daily hours;
- closed and special open dates.

Administrators can continue using the full wp-admin Booking dashboard for notes, CSV export, email templates and advanced settings.

## Production checklist

- Configure the WordPress timezone.
- Publish services and set duration/price.
- Configure working days and exceptions.
- Configure Agency Kit Email Service and send a successful test email.
- Publish Legal pages and verify the booking consent text.
- Create the manager account and test login in a private browser window.
- Submit, approve and reject one test booking.
- Use HTTPS and keep WordPress/plugins/PHP updated.
- Configure transactional email delivery and backups at the hosting provider.
