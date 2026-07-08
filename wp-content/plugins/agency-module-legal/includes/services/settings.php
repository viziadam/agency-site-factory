<?php
/**
 * Legal settings and consent text helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_defaults() {
	return array(
		'registration_text' => __( 'I have read and accept the privacy policy.', 'agency-module-legal' ),
		'booking_text'      => __( 'I consent to the processing of my data for managing this booking.', 'agency-module-legal' ),
		'contact_text'      => __( 'I consent to being contacted regarding my request.', 'agency-module-legal' ),
		'newsletter_text'   => __( 'I consent to receiving newsletter messages.', 'agency-module-legal' ),
		'privacy_template'  => __( '<h2>Privacy notice template</h2><p>This editable starter text must be reviewed for the client and jurisdiction. It is not legal advice.</p><p>Controller: {{company}}; address: {{address}}; email: {{email}}.</p>', 'agency-module-legal' ),
		'cookie_template'   => __( '<h2>Cookie notice template</h2><p>This site may use functional and measurement cookies. Review and adapt this text before publication.</p>', 'agency-module-legal' ),
		'imprint_template'  => __( '<h2>Imprint template</h2><p>Business: {{company}}; address: {{address}}; email: {{email}}; phone: {{phone}}.</p>', 'agency-module-legal' ),
		'page_ids'          => array(),
		'auto_create_required_pages' => 1,
		'auto_add_footer_legal_links' => 1,
		'create_imprint'    => 1,
		'page_status'       => 'publish',
		'cookie_banner'     => 1,
	);
}

function agency_legal_settings() {
	return wp_parse_args( get_option( 'agency_module_legal_settings', array() ), agency_legal_defaults() );
}

function agency_legal_get_checkbox_text( $context ) {
	$key = sanitize_key( $context ) . '_text';
	$s   = agency_legal_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

function agency_legal_template_values( $template ) {
	$values = array(
		'{{company}}' => function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'brand_name', get_bloginfo( 'name' ) ) : get_bloginfo( 'name' ),
		'{{address}}' => function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'address' ) : '',
		'{{email}}'   => function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'email', get_option( 'admin_email' ) ) : get_option( 'admin_email' ),
		'{{phone}}'   => function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'phone' ) : '',
	);
	return strtr( $template, array_map( 'esc_html', $values ) );
}

function agency_legal_setup_page_content( $key ) {
	$map = array( 'privacy' => 'privacy_template', 'cookie' => 'cookie_template', 'imprint' => 'imprint_template' );
	$s   = agency_legal_settings();
	return isset( $map[ $key ] ) ? wp_kses_post( agency_legal_template_values( $s[ $map[ $key ] ] ) ) : '';
}
