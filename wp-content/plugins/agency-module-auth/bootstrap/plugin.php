<?php
/**
 * Auth module bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENCY_AUTH_VERSION', '1.12.0' );
define( 'AGENCY_AUTH_FILE', dirname( __DIR__ ) . '/agency-module-auth.php' );
define( 'AGENCY_AUTH_PATH', dirname( __DIR__ ) . '/' );

$agency_auth_files = array(
	'includes/services/settings.php',
	'includes/services/verification.php',
	'includes/frontend/shortcodes.php',
	'includes/frontend/handlers.php',
	'includes/admin/page.php',
	'includes/admin/actions.php',
	'includes/admin/menu.php',
	'includes/services/component-registry.php',
	'includes/services/roles.php',
);

foreach ( $agency_auth_files as $agency_auth_file ) {
	require_once AGENCY_AUTH_PATH . $agency_auth_file;
}

register_activation_hook( AGENCY_AUTH_FILE, 'agency_auth_plugin_activate' );
