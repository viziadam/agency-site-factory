<?php
/**
 * Front page.
 *
 * @package Agency_Theme
 */

get_header();

$sections = agency_theme_get_sections( get_queried_object_id() );

if ( ! $sections ) {
	$sections = array(
		array(
			'component' => 'hero',
			'variant'   => 'split',
			'data'      => array(
				'eyebrow'      => __( 'Agency Starter', 'agency-theme' ),
				'title'        => __( 'Modern service website', 'agency-theme' ),
				'text'         => __( 'A reusable modular website system, ready for your next brand.', 'agency-theme' ),
				'button_label' => __( 'Contact', 'agency-theme' ),
				'button_url'   => home_url( '/contact/' ),
			),
		),
		array( 'component' => 'services', 'variant' => 'grid', 'data' => array( 'title' => __( 'Services', 'agency-theme' ) ) ),
		array( 'component' => 'faq', 'variant' => 'accordion', 'data' => array( 'title' => __( 'Frequently asked questions', 'agency-theme' ) ) ),
		array(
			'component' => 'cta',
			'variant'   => 'simple',
			'data'      => array(
				'title'        => __( 'Ready to get started?', 'agency-theme' ),
				'text'         => __( 'Tell us what you would like to build.', 'agency-theme' ),
				'button_label' => __( 'Get in touch', 'agency-theme' ),
				'button_url'   => home_url( '/contact/' ),
			),
		),
	);
}

agency_theme_render_sections( $sections );
get_footer();
