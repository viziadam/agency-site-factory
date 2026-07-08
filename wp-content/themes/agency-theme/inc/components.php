<?php
/**
 * Central component and variant registry.
 *
 * @package Agency_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_theme_get_component_registry() {
	$hero_fields = array(
		'eyebrow'      => array( 'label' => __( 'Eyebrow', 'agency-theme' ), 'type' => 'text' ),
		'title'        => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text', 'required' => true ),
		'text'         => array( 'label' => __( 'Text', 'agency-theme' ), 'type' => 'textarea' ),
		'button_label' => array( 'label' => __( 'Button label', 'agency-theme' ), 'type' => 'text' ),
		'button_url'   => array( 'label' => __( 'Button URL', 'agency-theme' ), 'type' => 'url' ),
		'image_url'    => array( 'label' => __( 'Image URL', 'agency-theme' ), 'type' => 'image_url' ),
	);
	$service_fields = array(
		'title' => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text' ),
		'text'  => array( 'label' => __( 'Text', 'agency-theme' ), 'type' => 'textarea' ),
		'limit' => array( 'label' => __( 'Item limit', 'agency-theme' ), 'type' => 'number', 'default' => 6 ),
	);
	$cta_fields = array(
		'title'        => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text', 'required' => true ),
		'text'         => array( 'label' => __( 'Text', 'agency-theme' ), 'type' => 'textarea' ),
		'button_label' => array( 'label' => __( 'Button label', 'agency-theme' ), 'type' => 'text' ),
		'button_url'   => array( 'label' => __( 'Button URL', 'agency-theme' ), 'type' => 'url' ),
	);

	$registry = array(
		'hero' => array(
			'label'           => __( 'Hero', 'agency-theme' ),
			'editor_label'    => __( 'Hero section', 'agency-theme' ),
			'default_variant' => 'split',
			'version'         => 2,
			'fields'          => $hero_fields,
			'default_data'    => array(
				'title'        => __( 'A clear, compelling headline', 'agency-theme' ),
				'button_label' => __( 'Get started', 'agency-theme' ),
				'button_url'   => '/',
			),
			'variants'        => array(
				'split'      => array( 'label' => __( 'Split', 'agency-theme' ), 'template' => 'template-parts/hero/hero-split' ),
				'centered'   => array( 'label' => __( 'Centered', 'agency-theme' ), 'template' => 'template-parts/hero/hero-centered' ),
				'image-card' => array( 'label' => __( 'Image card', 'agency-theme' ), 'template' => 'template-parts/hero/hero-image-card' ),
				'minimal'    => array( 'label' => __( 'Minimal', 'agency-theme' ), 'template' => 'template-parts/hero/hero-minimal' ),
			),
		),
		'services' => array(
			'label'           => __( 'Services', 'agency-theme' ),
			'editor_label'    => __( 'Services section', 'agency-theme' ),
			'default_variant' => 'grid',
			'version'         => 2,
			'fields'          => $service_fields,
			'default_data'    => array( 'title' => __( 'Services', 'agency-theme' ), 'limit' => 6 ),
			'variants'        => array(
				'grid'      => array( 'label' => __( 'Grid', 'agency-theme' ), 'template' => 'template-parts/services/services-grid' ),
				'cards'     => array( 'label' => __( 'Editorial cards', 'agency-theme' ), 'template' => 'template-parts/services/services-cards' ),
				'icon-list' => array( 'label' => __( 'Icon list', 'agency-theme' ), 'template' => 'template-parts/services/services-icon-list' ),
				'featured'  => array( 'label' => __( 'Featured service', 'agency-theme' ), 'template' => 'template-parts/services/services-featured' ),
			),
		),
		'cta' => array(
			'label'           => __( 'Call to action', 'agency-theme' ),
			'editor_label'    => __( 'CTA section', 'agency-theme' ),
			'default_variant' => 'simple',
			'version'         => 2,
			'fields'          => $cta_fields,
			'default_data'    => array(
				'title'        => __( 'Ready to take the next step?', 'agency-theme' ),
				'button_label' => __( 'Contact us', 'agency-theme' ),
				'button_url'   => '/contact/',
			),
			'variants'        => array(
				'simple'     => array( 'label' => __( 'Simple', 'agency-theme' ), 'template' => 'template-parts/cta/cta-simple' ),
				'boxed'      => array( 'label' => __( 'Boxed', 'agency-theme' ), 'template' => 'template-parts/cta/cta-boxed' ),
				'full-width' => array( 'label' => __( 'Full width', 'agency-theme' ), 'template' => 'template-parts/cta/cta-full-width' ),
				'booking'    => array( 'label' => __( 'Booking', 'agency-theme' ), 'template' => 'template-parts/cta/cta-booking' ),
			),
		),
		'faq' => array(
			'label'           => __( 'FAQ', 'agency-theme' ),
			'editor_label'    => __( 'FAQ section', 'agency-theme' ),
			'default_variant' => 'accordion',
			'version'         => 2,
			'fields'          => array(
				'title' => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text' ),
				'limit' => array( 'label' => __( 'Item limit', 'agency-theme' ), 'type' => 'number', 'default' => 8 ),
			),
			'default_data'    => array( 'title' => __( 'Frequently asked questions', 'agency-theme' ), 'limit' => 8 ),
			'variants'        => array(
				'accordion' => array( 'label' => __( 'Accordion', 'agency-theme' ), 'template' => 'template-parts/faq/faq-accordion' ),
			),
		),
		'contact' => array(
			'label'           => __( 'Contact', 'agency-theme' ),
			'editor_label'    => __( 'Contact section', 'agency-theme' ),
			'default_variant' => 'section',
			'version'         => 2,
			'fields'          => array(
				'title'       => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text' ),
				'text'        => array( 'label' => __( 'Text', 'agency-theme' ), 'type' => 'textarea' ),
				'phone'       => array( 'label' => __( 'Phone override', 'agency-theme' ), 'type' => 'text' ),
				'email'       => array( 'label' => __( 'Email override', 'agency-theme' ), 'type' => 'email' ),
				'address'     => array( 'label' => __( 'Address override', 'agency-theme' ), 'type' => 'text' ),
				'booking_url' => array( 'label' => __( 'Booking URL override', 'agency-theme' ), 'type' => 'url' ),
			),
			'default_data'    => array( 'title' => __( 'Contact', 'agency-theme' ) ),
			'variants'        => array(
				'section' => array( 'label' => __( 'Default', 'agency-theme' ), 'template' => 'template-parts/contact/contact-section' ),
			),
		),
		'blog' => array(
			'label'           => __( 'Blog', 'agency-theme' ),
			'editor_label'    => __( 'Blog section', 'agency-theme' ),
			'default_variant' => 'grid',
			'version'         => 2,
			'fields'          => array(
				'title' => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text' ),
				'limit' => array( 'label' => __( 'Item limit', 'agency-theme' ), 'type' => 'number', 'default' => 6 ),
			),
			'default_data'    => array( 'title' => __( 'Latest articles', 'agency-theme' ), 'limit' => 6 ),
			'variants'        => array(
				'grid' => array( 'label' => __( 'Grid', 'agency-theme' ), 'template' => 'template-parts/blog/blog-grid' ),
			),
		),
		'header' => array(
			'label'           => __( 'Header', 'agency-theme' ),
			'editor_label'    => __( 'Site header', 'agency-theme' ),
			'default_variant' => 'default',
			'version'         => 2,
			'section_allowed' => false,
			'fields'          => array(),
			'default_data'    => array(),
			'variants'        => array(
				'default'  => array( 'label' => __( 'Default', 'agency-theme' ), 'template' => 'template-parts/header/header-default' ),
				'centered' => array( 'label' => __( 'Centered', 'agency-theme' ), 'template' => 'template-parts/header/header-centered' ),
				'compact'  => array( 'label' => __( 'Compact', 'agency-theme' ), 'template' => 'template-parts/header/header-compact' ),
				'split'    => array( 'label' => __( 'Split with CTA', 'agency-theme' ), 'template' => 'template-parts/header/header-split' ),
			),
		),
	);

	return apply_filters( 'agency_theme_component_registry', $registry );
}

function agency_theme_normalize_section( $section ) {
	if ( ! is_array( $section ) ) {
		return array();
	}

	$component = isset( $section['component'] ) ? sanitize_text_field( $section['component'] ) : '';
	$variant   = isset( $section['variant'] ) ? sanitize_key( $section['variant'] ) : '';

	if ( ! $variant && str_contains( $component, '/' ) ) {
		$parts      = explode( '/', $component, 2 );
		$component  = sanitize_key( $parts[0] );
		$template   = $parts[1] ?? '';
		$variant    = str_starts_with( $template, $component . '-' ) ? substr( $template, strlen( $component ) + 1 ) : $template;
		$variant    = sanitize_key( $variant );
	} else {
		$component = sanitize_key( $component );
	}

	$registry = agency_theme_get_component_registry();
	if ( ! isset( $registry[ $component ]['variants'][ $variant ] ) || false === ( $registry[ $component ]['section_allowed'] ?? true ) ) {
		return array();
	}

	return array(
		'component' => $component,
		'variant'   => $variant,
		'version'   => absint( $registry[ $component ]['version'] ?? 1 ),
		'data'      => isset( $section['data'] ) && is_array( $section['data'] ) ? $section['data'] : array(),
	);
}

function agency_theme_get_service_items( $data ) {
	$limit    = isset( $data['limit'] ) ? absint( $data['limit'] ) : 6;
	$services = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

	if ( ! $services && post_type_exists( 'agency_service' ) ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'agency_service',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
			)
		);
		foreach ( $query->posts as $service ) {
			$services[] = array(
				'title' => get_the_title( $service ),
				'text'  => has_excerpt( $service ) ? get_the_excerpt( $service ) : wp_trim_words( $service->post_content, 24 ),
				'url'   => get_permalink( $service ),
			);
		}
		wp_reset_postdata();
	}

	return $services;
}

function agency_theme_get_header_variant() {
	$variant = function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'header_variant', 'default' ) : 'default';
	$variant = sanitize_key( $variant );
	$header  = agency_theme_get_component_registry()['header'];

	return isset( $header['variants'][ $variant ] ) ? $variant : $header['default_variant'];
}
