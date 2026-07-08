<?php
/**
 * Legal activation and module setup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_activate() {
	update_option( 'agency_legal_setup_pending', 1, false );
}

function agency_legal_maybe_apply_setup() {
	if ( get_option( 'agency_legal_setup_pending' ) && function_exists( 'agency_core_apply_module_setup' ) ) {
		delete_option( 'agency_legal_setup_pending' );
		agency_core_apply_module_setup( 'legal' );
	}
}
add_action( 'admin_init', 'agency_legal_maybe_apply_setup', 20 );
