<?php
/**
 * Plugin Name: Agency Core
 * Plugin URI: https://example.com/agency-core
 * Description: Data model, settings, blueprints, schema and rendering helpers for the Agency Site Factory.
 * Version: 1.12.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Agency Starter
 * Text Domain: agency-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENCY_CORE_VERSION', '1.12.0' );
define( 'AGENCY_CORE_SCHEMA_VERSION', 3 );
define( 'AGENCY_CORE_FILE', __FILE__ );
define( 'AGENCY_CORE_PATH', plugin_dir_path( __FILE__ ) );

$agency_core_includes = array(
	'helpers.php',
	'components.php',
	'post-types.php',
	'taxonomies.php',
	'meta.php',
	'settings.php',
	'module-services.php',
	'project.php',
	'modules.php',
	'module-setup.php',
	'client-admin.php',
	'admin-editor.php',
	'renderer.php',
	'preview.php',
	'schema.php',
	'importer.php',
	'shortcodes.php',
	'cli.php',
);

foreach ( $agency_core_includes as $agency_core_include ) {
	require_once AGENCY_CORE_PATH . 'includes/' . $agency_core_include;
}

function agency_core_activate() {
	agency_core_register_post_types();
	agency_core_register_taxonomies();
	agency_core_maybe_run_migrations();
	agency_core_client_admin_install();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'agency_core_activate' );

function agency_core_deactivate() {
	wp_clear_scheduled_hook( 'agency_core_cleanup_events' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'agency_core_deactivate' );

function agency_core_load_textdomain() {
	load_plugin_textdomain( 'agency-core', false, dirname( plugin_basename( AGENCY_CORE_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'agency_core_load_textdomain' );
