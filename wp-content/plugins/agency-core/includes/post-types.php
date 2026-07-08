<?php
/**
 * Custom post types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_register_post_types() {
	$post_types = array(
		'agency_service'     => array( 'Services', 'Service', 'services', 'dashicons-admin-tools' ),
		'agency_faq'         => array( 'FAQs', 'FAQ', 'faqs', 'dashicons-editor-help' ),
		'agency_testimonial' => array( 'Testimonials', 'Testimonial', 'testimonials', 'dashicons-format-quote' ),
		'agency_portfolio'   => array( 'Portfolio', 'Portfolio item', 'portfolio', 'dashicons-portfolio' ),
		'agency_price_item'  => array( 'Price items', 'Price item', 'prices', 'dashicons-tag' ),
	);

	foreach ( $post_types as $post_type => $config ) {
		register_post_type(
			$post_type,
			array(
				'labels' => array(
					'name'          => __( $config[0], 'agency-core' ),
					'singular_name' => __( $config[1], 'agency-core' ),
					'add_new_item'  => sprintf( __( 'Add new %s', 'agency-core' ), strtolower( $config[1] ) ),
					'edit_item'     => sprintf( __( 'Edit %s', 'agency-core' ), strtolower( $config[1] ) ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => true,
				'menu_icon'    => $config[3],
				'rewrite'      => array( 'slug' => $config[2] ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			)
		);
	}
}
add_action( 'init', 'agency_core_register_post_types' );

