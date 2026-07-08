<?php
/**
 * Legal Agency section integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_component_registry( $registry ) {
	$registry['legal'] = array(
		'label' => __( 'Legal', 'agency-module-legal' ), 'editor_label' => __( 'Legal links', 'agency-module-legal' ), 'default_variant' => 'links', 'version' => 1,
		'fields' => array(), 'default_data' => array(),
		'variants' => array( 'links' => array( 'label' => __( 'Legal links', 'agency-module-legal' ), 'template' => 'agency-module/legal-links' ) ),
	);
	return $registry;
}
add_filter( 'agency_theme_component_registry', 'agency_legal_component_registry' );
add_filter( 'agency_core_component_registry', 'agency_legal_component_registry' );
add_filter( 'agency_theme_render_module_component', static function ( $handled, $component ) { if ( 'legal' === $component ) { echo do_shortcode( '[agency_legal_links]' ); return true; } return $handled; }, 10, 2 );
