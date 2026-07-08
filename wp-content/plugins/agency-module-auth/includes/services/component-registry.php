<?php
/**
 * Auth Agency section integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_component_registry( $registry ) {
	$registry['auth'] = array(
		'label' => __( 'Authentication', 'agency-module-auth' ), 'editor_label' => __( 'Authentication', 'agency-module-auth' ), 'default_variant' => 'login', 'version' => 1,
		'fields' => array( 'title' => array( 'label' => __( 'Title', 'agency-module-auth' ), 'type' => 'text' ) ), 'default_data' => array(),
		'variants' => array(
			'login'    => array( 'label' => __( 'Login', 'agency-module-auth' ), 'template' => 'agency-module/auth-login' ),
			'register' => array( 'label' => __( 'Registration', 'agency-module-auth' ), 'template' => 'agency-module/auth-register' ),
			'account'  => array( 'label' => __( 'Account', 'agency-module-auth' ), 'template' => 'agency-module/auth-account' ),
		),
	);
	return $registry;
}
add_filter( 'agency_theme_component_registry', 'agency_auth_component_registry' );
add_filter( 'agency_core_component_registry', 'agency_auth_component_registry' );
add_filter(
	'agency_theme_render_module_component',
	static function ( $handled, $component, $variant ) {
		if ( 'auth' !== $component ) {
			return $handled;
		}
		echo do_shortcode( 'account' === $variant ? '[agency_account]' : '[agency_auth mode="' . esc_attr( $variant ) . '"]' );
		return true;
	},
	10,
	3
);
