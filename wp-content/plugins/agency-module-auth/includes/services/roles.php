<?php
/**
 * Auth roles and activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_activate() {
	if ( ! get_role( 'agency_customer' ) ) {
		add_role( 'agency_customer', __( 'Agency Customer', 'agency-module-auth' ), array( 'read' => true, 'agency_customer' => true ) );
	}
}

function agency_auth_plugin_activate() {
	agency_auth_activate();
	update_option( 'agency_auth_setup_pending', 1, false );
}

function agency_auth_maybe_apply_setup() {
	if ( get_option( 'agency_auth_setup_pending' ) && function_exists( 'agency_core_apply_module_setup' ) ) {
		delete_option( 'agency_auth_setup_pending' );
		agency_core_apply_module_setup( 'auth' );
	}
}
add_action( 'init', 'agency_auth_activate', 7 );
add_action( 'admin_init', 'agency_auth_maybe_apply_setup', 20 );
