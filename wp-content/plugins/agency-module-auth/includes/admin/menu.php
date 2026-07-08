<?php
/**
 * Auth admin menu registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', static fn() => add_submenu_page( 'agency-core-settings', __( 'Auth', 'agency-module-auth' ), __( 'Auth', 'agency-module-auth' ), 'manage_options', 'agency-module-auth', 'agency_auth_admin_page' ), 30 );
