<?php
/**
 * Agency Theme bootstrap and rendering helpers.
 *
 * @package Agency_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/components.php';

function agency_theme_setup() {
	load_theme_textdomain( 'agency-theme', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'agency-theme' ),
			'footer'  => __( 'Footer menu', 'agency-theme' ),
		)
	);

	add_image_size( 'agency-card', 720, 480, true );
	add_image_size( 'agency-hero', 1200, 900, true );
}
add_action( 'after_setup_theme', 'agency_theme_setup' );

function agency_theme_get_frontend_styles() {
	$version = wp_get_theme()->get( 'Version' );
	$styles  = array();

	foreach ( array( 'tokens', 'base', 'layout', 'components', 'utilities' ) as $style ) {
		$styles[ 'agency-theme-' . $style ] = add_query_arg( 'ver', $version, get_template_directory_uri() . '/assets/css/' . $style . '.css' );
	}
	$styles['agency-theme-style'] = add_query_arg( 'ver', $version, get_stylesheet_uri() );

	return apply_filters( 'agency_theme_frontend_styles', $styles );
}

function agency_theme_get_frontend_scripts() {
	$version = wp_get_theme()->get( 'Version' );
	return apply_filters(
		'agency_theme_frontend_scripts',
		array(
			'agency-theme-main' => add_query_arg( 'ver', $version, get_template_directory_uri() . '/assets/js/main.js' ),
		)
	);
}

function agency_theme_enqueue_assets() {
	foreach ( agency_theme_get_frontend_styles() as $handle => $url ) {
		$dependencies = 'agency-theme-style' === $handle ? array( 'agency-theme-utilities' ) : array();
		wp_enqueue_style( $handle, $url, $dependencies, null );
	}
	foreach ( agency_theme_get_frontend_scripts() as $handle => $url ) {
		wp_enqueue_script( $handle, $url, array(), null, true );
	}
}
add_action( 'wp_enqueue_scripts', 'agency_theme_enqueue_assets' );

/**
 * Read and validate section JSON stored on a post.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function agency_theme_get_sections( $post_id ) {
	$raw = get_post_meta( $post_id, '_agency_sections', true );

	if ( is_array( $raw ) ) {
		$sections = $raw;
	} elseif ( is_string( $raw ) && '' !== $raw ) {
		$sections = json_decode( $raw, true );
	} else {
		return array();
	}

	return is_array( $sections ) ? $sections : array();
}

/**
 * Render an allow-listed list of component template parts.
 *
 * @param array  $sections Component definitions.
 * @param string $context  Render context: frontend or editor_preview.
 */
function agency_theme_render_sections( $sections, $context = 'frontend' ) {
	$registry = agency_theme_get_component_registry();
	$context  = in_array( $context, array( 'frontend', 'editor_preview' ), true ) ? $context : 'frontend';

	foreach ( (array) $sections as $section ) {
		$normalized = agency_theme_normalize_section( $section );
		if ( ! $normalized ) {
			continue;
		}

		$component = $normalized['component'];
		$variant   = $normalized['variant'];
		$template  = $registry[ $component ]['variants'][ $variant ]['template'];
		if ( apply_filters( 'agency_theme_render_module_component', false, $component, $variant, $normalized['data'], $context ) ) {
			continue;
		}
		get_template_part(
			$template,
			null,
			array(
				'data'      => $normalized['data'],
				'context'   => $context,
				'component' => $component,
				'variant'   => $variant,
			)
		);
	}
}

function agency_theme_brand_name() {
	if ( function_exists( 'agency_core_get_setting' ) ) {
		return agency_core_get_setting( 'brand_name', get_bloginfo( 'name' ) ?: 'Agency Starter' );
	}

	return get_bloginfo( 'name' ) ?: 'Agency Starter';
}

function agency_theme_get_dynamic_token_css() {
	if ( ! function_exists( 'agency_core_get_setting' ) ) {
		return '';
	}

	$colors = array(
		'--agency-color-primary'    => agency_core_get_setting( 'primary_color' ),
		'--agency-color-secondary'  => agency_core_get_setting( 'secondary_color' ),
		'--agency-color-background' => agency_core_get_setting( 'background_color' ),
	);
	$rules  = array();

	foreach ( $colors as $property => $value ) {
		if ( $value && sanitize_hex_color( $value ) ) {
			$rules[] = $property . ':' . sanitize_hex_color( $value );
		}
	}

	return $rules ? ':root{' . implode( ';', $rules ) . '}' : '';
}

function agency_theme_dynamic_tokens() {
	$css = agency_theme_get_dynamic_token_css();
	if ( $css ) {
		wp_add_inline_style( 'agency-theme-tokens', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'agency_theme_dynamic_tokens', 20 );
