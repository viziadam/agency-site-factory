# Modular Client Admin

## Access

The client-facing administration is available at:

`https://your-domain.example/admin/`

Agency Core renders this as a standalone portal request. It is not displayed through the active theme, the Gutenberg page canvas or the normal website shell. Older system pages that used the `[agency_client_admin]` shortcode remain harmless for backward compatibility, but `/admin/` is intercepted before theme rendering.

The portal login creates a dedicated `agency_portal_session` access-token cookie scoped to `/admin/`. It does not create a normal WordPress auth cookie, so a portal manager is not automatically logged into wp-admin or the public website account area. Client managers are redirected away from wp-admin if they somehow reach it.

On first installation a user named `agencyadmin` is created with a cryptographically random password. The password is displayed once under **Agency Kit → Client Admin** and expires from the notice automatically. It is deliberately not a universal hard-coded password.

WordPress administrators can create or update the portal username/password on the same screen, or manage multiple users under **Users** using the **Agency Client Admin** role.

## Included portal pages

- Overview.
- Bookings: statistics, customer details, approve/reject workflow.
- Booking settings: hours, working days and exceptions.
- Visitors & interactions: 7/30/90-day reports, all visited pages, booking clicks, completed bookings and conversion.
- Website settings: brand, contact details, colors and booking URL.
- Account/password reset.

## Privacy and data

First-party page views and click interactions are recorded only after analytics consent. Data contains the page path/title, event name, timestamp and a salted session hash; it does not store visitor email, name or raw IP address. A completed booking is recorded as an aggregate operational conversion without copying customer data.

## Adding a module page

An active module can add a portal page without editing Agency Core:

```php
add_filter(
    'agency_client_portal_tabs',
    static function ( $tabs ) {
        $tabs['example'] = array(
            'label'    => 'Example',
            'callback' => 'render_example_portal_page',
            'order'    => 50,
        );
        return $tabs;
    }
);
```

The callback must escape output, enforce any module-specific authorization and use nonce-protected handlers for changes.

For state-changing portal actions, post back to the standalone portal action URL:

```php
<form method="post" action="<?php echo esc_url( agency_core_client_admin_action_url( 'agency_example_save', 'example' ) ); ?>">
    <input type="hidden" name="agency_portal_action" value="agency_example_save">
    <?php wp_nonce_field( 'agency_example_save' ); ?>
</form>
```

Then register the handler:

```php
add_action( 'agency_client_portal_action_agency_example_save', 'agency_example_save' );
```

Keep an `admin_post_*` fallback only when the same operation should remain available from wp-admin.

Custom buttons or links can be tracked after consent with:

```html
<button data-agency-track="quote_request">Request a quote</button>
```

The event appears automatically under Visitors & interactions.
