<?php
/**
 * Legal admin menu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_menu() {
	add_submenu_page( 'agency-core-settings', __( 'Legal', 'agency-module-legal' ), __( 'Legal', 'agency-module-legal' ), 'manage_options', 'agency-module-legal', 'agency_legal_admin_page' );
}
add_action( 'admin_menu', 'agency_legal_menu', 30 );
