<?php
/**
 * Auth settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_defaults() {
	return array(
		'registration_enabled'     => 1,
		'require_verification'     => 1,
		'token_lifetime'           => 86400,
		'login_redirect'           => home_url( '/fiokom/' ),
		'account_page'             => 0,
		'page_ids'                 => array(),
		'auto_create_required_pages' => 1,
		'auto_add_auth_links'      => 1,
		'auto_add_pages_to_menu'   => 0,
		'email_subject'            => __( 'Verify your account', 'agency-module-auth' ),
		'email_body'               => __( '<p>Welcome! Verify your email address here: {{verification_url}}</p>', 'agency-module-auth' ),
	);
}

function agency_auth_settings() {
	return wp_parse_args( get_option( 'agency_module_auth_settings', array() ), agency_auth_defaults() );
}
