<?php
/**
 * Legal settings save handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-legal' ) );
	}
	check_admin_referer( 'agency_legal_save' );
	$old = agency_legal_settings();
	foreach ( array( 'registration_text', 'booking_text', 'contact_text', 'newsletter_text' ) as $key ) {
		$old[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
	}
	foreach ( array( 'privacy_template', 'cookie_template', 'imprint_template' ) as $key ) {
		$old[ $key ] = wp_kses_post( wp_unslash( $_POST[ $key ] ?? '' ) );
	}
	$old['auto_create_required_pages'] = ! empty( $_POST['auto_create_required_pages'] );
	$old['auto_add_footer_legal_links'] = ! empty( $_POST['auto_add_footer_legal_links'] );
	$old['create_imprint'] = ! empty( $_POST['create_imprint'] );
	$old['cookie_banner'] = ! empty( $_POST['cookie_banner'] );
	$old['page_status'] = 'publish' === ( $_POST['page_status'] ?? '' ) ? 'publish' : 'draft';
	update_option( 'agency_module_legal_settings', $old, false );
	if ( isset( $_POST['generate'] ) ) {
		agency_legal_generate_pages();
	}
	wp_safe_redirect( admin_url( 'admin.php?page=agency-module-legal&updated=1' ) );
	exit;
}
add_action( 'admin_post_agency_legal_save', 'agency_legal_save' );
