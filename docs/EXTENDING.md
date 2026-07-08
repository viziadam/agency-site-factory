# Extending Agency Site Factory

This guide describes the developer-friendly structure introduced in 1.10. The goal is that a new feature is easy to find in VS Code without changing public behavior on existing client sites.

## Feature plugin structure

Every feature plugin should use this layout:

```text
agency-module-example/
├── agency-module-example.php
├── bootstrap/
│   └── plugin.php
├── includes/
│   ├── services/
│   ├── repositories/
│   ├── migrations/
│   ├── admin/
│   ├── frontend/
│   └── rest/
├── templates/
│   ├── frontend/
│   └── admin/
└── assets/
    ├── css/
    └── js/
```

The root plugin file should keep only the WordPress plugin header and a `require_once __DIR__ . '/bootstrap/plugin.php';`. The bootstrap file defines constants and loads explicit files in dependency order.

## Add a new plugin

1. Create `wp-content/plugins/agency-module-{slug}/`.
2. Add the standard folder layout above.
3. Put the plugin header in `agency-module-{slug}.php`.
4. Put the loader in `bootstrap/plugin.php`.
5. Register public behavior from focused files: services first, then repositories/migrations, frontend/admin, REST and integrations.
6. Add the module to `AGENCY_FACTORY_MODULES` in `tools/lib/site-factory.php` only when the Factory Manager should install it.
7. Add module setup defaults to Agency Core only if the module creates required pages or menu entries.

Keep public shortcode names, REST namespaces, admin-post action names and option keys stable once released.

## Add a shortcode

1. Put rendering code in `includes/frontend/{feature}.php`.
2. Keep heavy data reads in `includes/repositories/`.
3. Escape all output with `esc_html()`, `esc_attr()`, `esc_url()` or `wp_kses_post()`.
4. Register with `add_shortcode( 'agency_{module}_{name}', 'callback' );`.
5. If replacing an old shortcode, keep the old shortcode as an alias.

## Add an Agency section

Register a component through both registries:

```php
add_filter( 'agency_theme_component_registry', 'agency_example_registry' );
add_filter( 'agency_core_component_registry', 'agency_example_registry' );
```

Then render it through `agency_theme_render_module_component` when the component belongs to a plugin. Do not load arbitrary PHP paths from user input. Component and variant values must come from a registry allowlist.

## Add a theme component

1. Add a template part under `wp-content/themes/agency-theme/template-parts/{component}/{component}-{variant}.php`.
2. Read component data from `$args['data']`.
3. Escape every output.
4. Register the component/variant in `wp-content/themes/agency-theme/inc/components.php`.
5. Add styles to `wp-content/themes/agency-theme/assets/css/components.css` or a focused CSS layer.

## Add a design variant

1. Add a new variant entry to the component registry.
2. Add `default_data` for fields editors should see immediately.
3. Create the matching template part.
4. Use design tokens such as `--agency-color-primary`, `--agency-color-secondary`, `--agency-color-background`, `--agency-color-text` and `--agency-font-heading`.
5. Keep section IDs backward-compatible. If a legacy section uses `hero/hero-split`, keep normalizing it to `component: hero`, `variant: split`.

## Write a migration

Put migrations under `includes/migrations/` or a focused service file if the module is still small. Use `dbDelta()` for schema changes and store a module DB version option, for example:

```php
agency_core_module_migrate(
	'example',
	'1.1.0',
	static function ( $from, $to ) {
		// dbDelta() and data backfills here.
	}
);
```

Migrations must be idempotent: running them twice should not duplicate rows or corrupt settings. Never delete client content during activation or update.

## Use the Email Service

Agency Core provides `agency_core_email_send()`:

```php
$result = agency_core_email_send(
	$recipient_email,
	__( 'Subject', 'agency-module-example' ),
	'<p>' . esc_html__( 'HTML message body.', 'agency-module-example' ) . '</p>'
);

if ( is_wp_error( $result ) ) {
	// Log or show a non-sensitive admin notice.
}
```

The service uses the configured provider under **Agency Kit → Email Service**, records delivery health and strips sensitive values from audit logs. Feature modules should call this helper before falling back to `wp_mail()`.

## Factory Manager compatibility

The Factory Manager copies entire plugin/theme directories, so internal file moves are safe as long as:

- the root plugin filenames remain unchanged;
- plugin headers still expose the version;
- module slugs stay the same;
- public options, shortcodes, routes and admin-post actions remain backward-compatible.

## Add a client admin portal page

Client-facing admin pages live in the standalone `/admin/` portal, not inside the website theme. Add a tab from a module:

```php
add_filter(
	'agency_client_portal_tabs',
	static function ( $tabs ) {
		$tabs['example'] = array(
			'label'    => __( 'Example', 'agency-module-example' ),
			'callback' => 'agency_example_client_portal',
			'order'    => 50,
		);
		return $tabs;
	}
);
```

Render escaped markup from the callback. For changes, post to `agency_core_client_admin_action_url()` and register an `agency_client_portal_action_{action}` handler. The portal request validates the dedicated `agency_portal_session` token cookie, sets the current user for capability checks, then runs the handler.

Do not rely on WordPress auth cookies in client portal pages. If a module also needs a wp-admin fallback, keep that as a separate `admin_post_*` handler.
