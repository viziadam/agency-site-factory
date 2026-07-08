<?php
/**
 * Legal module bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENCY_LEGAL_VERSION', '1.12.0' );
define( 'AGENCY_LEGAL_FILE', dirname( __DIR__ ) . '/agency-module-legal.php' );
define( 'AGENCY_LEGAL_PATH', dirname( __DIR__ ) . '/' );

$agency_legal_files = array(
	'includes/services/settings.php',
	'includes/services/page-generator.php',
	'includes/admin/page.php',
	'includes/admin/actions.php',
	'includes/frontend/legal-links.php',
	'includes/frontend/cookie-banner.php',
	'includes/admin/menu.php',
	'includes/services/component-registry.php',
	'includes/services/activation.php',
);

foreach ( $agency_legal_files as $agency_legal_file ) {
	require_once AGENCY_LEGAL_PATH . $agency_legal_file;
}

register_activation_hook( AGENCY_LEGAL_FILE, 'agency_legal_activate' );
