<?php
/**
 * Email service compatibility helpers.
 *
 * Keeps the test recipient field persistent without changing the public email
 * sending API used by feature modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_email_test_recipient() {
	$stored = sanitize_email( get_option( 'agency_core_email_test_recipient', '' ) );

	return $stored ?: get_option( 'admin_email' );
}

function agency_core_capture_email_test_recipient() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'agency_core_email_settings' ) ) {
		return;
	}

	$recipient = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );

	if ( is_email( $recipient ) ) {
		update_option( 'agency_core_email_test_recipient', $recipient, false );
	}
}
add_action( 'admin_post_agency_core_save_email', 'agency_core_capture_email_test_recipient', 1 );

function agency_core_email_test_recipient_admin_email_filter( $pre_option, $option = '', $default_value = false ) {
	if ( ! is_admin() ) {
		return $pre_option;
	}

	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	if ( 'agency-core-email' !== $page ) {
		return $pre_option;
	}

	$stored = sanitize_email( get_option( 'agency_core_email_test_recipient', '' ) );

	return is_email( $stored ) ? $stored : $pre_option;
}
add_filter( 'pre_option_admin_email', 'agency_core_email_test_recipient_admin_email_filter', 10, 3 );
