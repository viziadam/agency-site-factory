<?php
/**
 * Auth wp-admin form handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_admin_user_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-auth' ) );
	}
	$user_id = absint( $_POST['user_id'] ?? 0 );
	$action  = sanitize_key( $_POST['user_action'] ?? '' );
	check_admin_referer( 'agency_auth_user_' . $user_id );
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'agency_customer', (array) $user->roles, true ) ) {
		wp_die( esc_html__( 'Customer not found.', 'agency-module-auth' ), 404 );
	}
	if ( 'resend' === $action ) {
		agency_auth_send_verification( $user_id );
	} elseif ( 'block' === $action ) {
		update_user_meta( $user_id, '_agency_auth_blocked', '1' );
	} elseif ( 'unblock' === $action ) {
		delete_user_meta( $user_id, '_agency_auth_blocked' );
	}
	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'auth', 'admin_' . $action, array( 'customer_user_id' => $user_id ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=agency-module-auth&updated=1' ) );
	exit;
}
add_action( 'admin_post_agency_auth_user_action', 'agency_auth_admin_user_action' );

function agency_auth_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-auth' ) );
	}
	check_admin_referer( 'agency_auth_save' );
	$page_ids = array();
	foreach ( array( 'login', 'register', 'account', 'password', 'verification' ) as $key ) {
		$page_ids[ $key ] = absint( $_POST['page_ids'][ $key ] ?? 0 );
	}
	$s = array_merge( agency_auth_settings(), array( 'registration_enabled' => ! empty( $_POST['registration_enabled'] ), 'require_verification' => ! empty( $_POST['require_verification'] ), 'auto_create_required_pages' => ! empty( $_POST['auto_create_required_pages'] ), 'auto_add_auth_links' => ! empty( $_POST['auto_add_auth_links'] ), 'auto_add_pages_to_menu' => ! empty( $_POST['auto_add_pages_to_menu'] ), 'page_ids' => $page_ids, 'account_page' => $page_ids['account'], 'token_lifetime' => max( 900, absint( $_POST['token_lifetime'] ?? 86400 ) ), 'login_redirect' => esc_url_raw( $_POST['login_redirect'] ?? home_url( '/' ) ), 'email_subject' => sanitize_text_field( wp_unslash( $_POST['email_subject'] ?? '' ) ), 'email_body' => wp_kses_post( wp_unslash( $_POST['email_body'] ?? '' ) ) ) );
	update_option( 'agency_module_auth_settings', $s, false );
	if ( function_exists( 'agency_core_sync_manifest_modules' ) ) {
		agency_core_sync_manifest_modules();
	}
	wp_safe_redirect( admin_url( 'admin.php?page=agency-module-auth&updated=1' ) );
	exit;
}
add_action( 'admin_post_agency_auth_save', 'agency_auth_save' );
