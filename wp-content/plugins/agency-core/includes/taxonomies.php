<?php
/**
 * Custom taxonomies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_register_taxonomies() {
	$taxonomies = array(
		'agency_service_category' => array( 'Service categories', 'Service category', array( 'agency_service', 'agency_price_item' ), 'service-category' ),
		'agency_location'         => array( 'Locations', 'Location', array( 'agency_service', 'agency_portfolio' ), 'location' ),
		'agency_industry'         => array( 'Industries', 'Industry', array( 'agency_service', 'agency_portfolio', 'agency_testimonial' ), 'industry' ),
	);

	foreach ( $taxonomies as $taxonomy => $config ) {
		register_taxonomy(
			$taxonomy,
			$config[2],
			array(
				'labels' => array(
					'name'          => __( $config[0], 'agency-core' ),
					'singular_name' => __( $config[1], 'agency-core' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => $config[3] ),
			)
		);
	}
}
add_action( 'init', 'agency_core_register_taxonomies' );

