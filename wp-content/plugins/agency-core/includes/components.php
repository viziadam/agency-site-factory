<?php
/**
 * Component schemas, compatibility normalization and data migrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_get_component_registry() {
	if ( function_exists( 'agency_theme_get_component_registry' ) ) {
		return agency_theme_get_component_registry();
	}

	$text_fields = array(
		'eyebrow'      => array( 'label' => __( 'Eyebrow', 'agency-core' ), 'type' => 'text' ),
		'title'        => array( 'label' => __( 'Title', 'agency-core' ), 'type' => 'text' ),
		'text'         => array( 'label' => __( 'Text', 'agency-core' ), 'type' => 'textarea' ),
		'button_label' => array( 'label' => __( 'Button label', 'agency-core' ), 'type' => 'text' ),
		'button_url'   => array( 'label' => __( 'Button URL', 'agency-core' ), 'type' => 'url' ),
		'image_url'    => array( 'label' => __( 'Image URL', 'agency-core' ), 'type' => 'image_url' ),
	);
	$list_fields = array(
		'title' => array( 'label' => __( 'Title', 'agency-core' ), 'type' => 'text' ),
		'text'  => array( 'label' => __( 'Text', 'agency-core' ), 'type' => 'textarea' ),
		'limit' => array( 'label' => __( 'Item limit', 'agency-core' ), 'type' => 'number', 'default' => 6 ),
	);

	$registry = array(
		'hero' => array(
			'label' => __( 'Hero', 'agency-core' ), 'editor_label' => __( 'Hero section', 'agency-core' ), 'default_variant' => 'split', 'version' => 2,
			'fields' => $text_fields, 'default_data' => array( 'title' => __( 'A clear, compelling headline', 'agency-core' ) ),
			'variants' => array(
				'split' => array( 'label' => __( 'Split', 'agency-core' ), 'template' => 'template-parts/hero/hero-split' ),
				'centered' => array( 'label' => __( 'Centered', 'agency-core' ), 'template' => 'template-parts/hero/hero-centered' ),
				'image-card' => array( 'label' => __( 'Image card', 'agency-core' ), 'template' => 'template-parts/hero/hero-image-card' ),
				'minimal' => array( 'label' => __( 'Minimal', 'agency-core' ), 'template' => 'template-parts/hero/hero-minimal' ),
			),
		),
		'services' => array(
			'label' => __( 'Services', 'agency-core' ), 'editor_label' => __( 'Services section', 'agency-core' ), 'default_variant' => 'grid', 'version' => 2,
			'fields' => $list_fields, 'default_data' => array( 'title' => __( 'Services', 'agency-core' ), 'limit' => 6 ),
			'variants' => array(
				'grid' => array( 'label' => __( 'Grid', 'agency-core' ), 'template' => 'template-parts/services/services-grid' ),
				'cards' => array( 'label' => __( 'Editorial cards', 'agency-core' ), 'template' => 'template-parts/services/services-cards' ),
				'icon-list' => array( 'label' => __( 'Icon list', 'agency-core' ), 'template' => 'template-parts/services/services-icon-list' ),
				'featured' => array( 'label' => __( 'Featured service', 'agency-core' ), 'template' => 'template-parts/services/services-featured' ),
			),
		),
		'cta' => array(
			'label' => __( 'Call to action', 'agency-core' ), 'editor_label' => __( 'CTA section', 'agency-core' ), 'default_variant' => 'simple', 'version' => 2,
			'fields' => $text_fields, 'default_data' => array( 'title' => __( 'Ready to take the next step?', 'agency-core' ) ),
			'variants' => array(
				'simple' => array( 'label' => __( 'Simple', 'agency-core' ), 'template' => 'template-parts/cta/cta-simple' ),
				'boxed' => array( 'label' => __( 'Boxed', 'agency-core' ), 'template' => 'template-parts/cta/cta-boxed' ),
				'full-width' => array( 'label' => __( 'Full width', 'agency-core' ), 'template' => 'template-parts/cta/cta-full-width' ),
				'booking' => array( 'label' => __( 'Booking', 'agency-core' ), 'template' => 'template-parts/cta/cta-booking' ),
			),
		),
		'faq' => array(
			'label' => __( 'FAQ', 'agency-core' ), 'editor_label' => __( 'FAQ section', 'agency-core' ), 'default_variant' => 'accordion', 'version' => 2,
			'fields' => $list_fields, 'default_data' => array( 'title' => __( 'Frequently asked questions', 'agency-core' ), 'limit' => 8 ),
			'variants' => array( 'accordion' => array( 'label' => __( 'Accordion', 'agency-core' ), 'template' => 'template-parts/faq/faq-accordion' ) ),
		),
		'contact' => array(
			'label' => __( 'Contact', 'agency-core' ), 'editor_label' => __( 'Contact section', 'agency-core' ), 'default_variant' => 'section', 'version' => 2,
			'fields' => array(
				'title' => array( 'label' => __( 'Title', 'agency-core' ), 'type' => 'text' ),
				'text' => array( 'label' => __( 'Text', 'agency-core' ), 'type' => 'textarea' ),
				'phone' => array( 'label' => __( 'Phone override', 'agency-core' ), 'type' => 'text' ),
				'email' => array( 'label' => __( 'Email override', 'agency-core' ), 'type' => 'email' ),
				'address' => array( 'label' => __( 'Address override', 'agency-core' ), 'type' => 'text' ),
				'booking_url' => array( 'label' => __( 'Booking URL override', 'agency-core' ), 'type' => 'url' ),
			),
			'default_data' => array( 'title' => __( 'Contact', 'agency-core' ) ),
			'variants' => array( 'section' => array( 'label' => __( 'Default', 'agency-core' ), 'template' => 'template-parts/contact/contact-section' ) ),
		),
		'blog' => array(
			'label' => __( 'Blog', 'agency-core' ), 'editor_label' => __( 'Blog section', 'agency-core' ), 'default_variant' => 'grid', 'version' => 2,
			'fields' => $list_fields, 'default_data' => array( 'title' => __( 'Latest articles', 'agency-core' ), 'limit' => 6 ),
			'variants' => array( 'grid' => array( 'label' => __( 'Grid', 'agency-core' ), 'template' => 'template-parts/blog/blog-grid' ) ),
		),
		'header' => array(
			'label' => __( 'Header', 'agency-core' ), 'editor_label' => __( 'Site header', 'agency-core' ), 'default_variant' => 'default', 'version' => 2,
			'section_allowed' => false, 'fields' => array(), 'default_data' => array(),
			'variants' => array(
				'default' => array( 'label' => __( 'Default', 'agency-core' ), 'template' => 'template-parts/header/header-default' ),
				'centered' => array( 'label' => __( 'Centered', 'agency-core' ), 'template' => 'template-parts/header/header-centered' ),
				'compact' => array( 'label' => __( 'Compact', 'agency-core' ), 'template' => 'template-parts/header/header-compact' ),
				'split' => array( 'label' => __( 'Split with CTA', 'agency-core' ), 'template' => 'template-parts/header/header-split' ),
			),
		),
	);

	return apply_filters( 'agency_core_component_registry', $registry );
}

function agency_core_normalize_section( $section ) {
	if ( ! is_array( $section ) ) {
		return array();
	}

	$component = isset( $section['component'] ) ? sanitize_text_field( $section['component'] ) : '';
	$variant   = isset( $section['variant'] ) ? sanitize_key( $section['variant'] ) : '';

	if ( ! $variant && str_contains( $component, '/' ) ) {
		$parts     = explode( '/', $component, 2 );
		$component = sanitize_key( $parts[0] );
		$template  = $parts[1] ?? '';
		$variant   = str_starts_with( $template, $component . '-' ) ? substr( $template, strlen( $component ) + 1 ) : $template;
		$variant   = sanitize_key( $variant );
	} else {
		$component = sanitize_key( $component );
	}

	$registry = agency_core_get_component_registry();
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

function agency_core_sanitize_unknown_component_value( $value ) {
	if ( is_array( $value ) ) {
		$clean = array();
		foreach ( $value as $key => $item ) {
			$clean[ sanitize_key( (string) $key ) ] = agency_core_sanitize_unknown_component_value( $item );
		}
		return $clean;
	}
	return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
}

function agency_core_sanitize_component_data( $component, $variant, $data ) {
	$registry = agency_core_get_component_registry();
	if ( ! isset( $registry[ $component ]['variants'][ $variant ] ) || ! is_array( $data ) ) {
		return array();
	}

	$fields = array_merge(
		$registry[ $component ]['fields'] ?? array(),
		$registry[ $component ]['variants'][ $variant ]['fields'] ?? array()
	);
	$clean = array();

	foreach ( $data as $key => $value ) {
		$key = sanitize_key( (string) $key );
		if ( ! isset( $fields[ $key ] ) ) {
			$clean[ $key ] = agency_core_sanitize_unknown_component_value( $value );
			continue;
		}

		switch ( $fields[ $key ]['type'] ) {
			case 'number':
			case 'image_id':
				$clean[ $key ] = absint( $value );
				break;
			case 'checkbox':
				$clean[ $key ] = (bool) $value;
				break;
			case 'email':
				$clean[ $key ] = sanitize_email( $value );
				break;
			case 'url':
			case 'image_url':
				$clean[ $key ] = esc_url_raw( $value );
				break;
			case 'select':
				$options = $fields[ $key ]['options'] ?? array();
				$is_list = array() === $options || array_keys( $options ) === range( 0, count( $options ) - 1 );
				$values  = $is_list ? array_column( $options, 'value' ) : array_keys( $options );
				$clean[ $key ] = in_array( $value, $values, true ) ? sanitize_text_field( $value ) : '';
				break;
			case 'repeater':
				$clean[ $key ] = is_array( $value ) ? agency_core_sanitize_unknown_component_value( $value ) : array();
				break;
			case 'textarea':
				$clean[ $key ] = wp_kses_post( $value );
				break;
			default:
				$clean[ $key ] = sanitize_text_field( $value );
		}
	}

	foreach ( $fields as $key => $field ) {
		if ( ! array_key_exists( $key, $clean ) && isset( $field['default'] ) ) {
			$clean[ $key ] = $field['default'];
		}
	}
	return $clean;
}

function agency_core_maybe_run_migrations() {
	$installed = absint( get_option( 'agency_core_schema_version', 0 ) );
	if ( $installed >= AGENCY_CORE_SCHEMA_VERSION ) {
		return;
	}

	$page_ids = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_agency_sections',
		)
	);
	foreach ( $page_ids as $page_id ) {
		$raw      = get_post_meta( $page_id, '_agency_sections', true );
		$sections = json_decode( $raw, true );
		if ( is_array( $sections ) ) {
			update_post_meta( $page_id, '_agency_sections', agency_core_sanitize_sections_meta( $sections ) );
		}
	}
	update_option( 'agency_core_schema_version', AGENCY_CORE_SCHEMA_VERSION, false );
}
add_action( 'admin_init', 'agency_core_maybe_run_migrations' );
