<?php
/**
 * Shared plugin helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_get_setting( $key, $default = '' ) {
	$settings = get_option( 'agency_core_settings', array() );
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $default;
}

function agency_core_read_json_file( $path ) {
	if ( ! is_string( $path ) || ! str_starts_with( wp_normalize_path( $path ), wp_normalize_path( AGENCY_CORE_PATH . 'blueprints/' ) ) || ! is_readable( $path ) ) {
		return new WP_Error( 'agency_core_file_error', __( 'The blueprint file could not be read.', 'agency-core' ) );
	}

	$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$data     = json_decode( $contents, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
		return new WP_Error( 'agency_core_json_error', __( 'The blueprint contains invalid JSON.', 'agency-core' ) );
	}

	return $data;
}

function agency_core_validate_sections( $sections ) {
	if ( ! is_array( $sections ) ) {
		return new WP_Error( 'agency_sections_not_array', __( 'Sections must be an array.', 'agency-core' ), array( 'status' => 400 ) );
	}
	if ( count( $sections ) > 50 ) {
		return new WP_Error( 'agency_sections_limit', __( 'A preview can contain at most 50 sections.', 'agency-core' ), array( 'status' => 400 ) );
	}

	foreach ( $sections as $index => $section ) {
		if ( ! is_array( $section ) || ! isset( $section['data'] ) || ! is_array( $section['data'] ) ) {
			return new WP_Error(
				'agency_section_shape',
				sprintf( __( 'Section %d has an invalid structure.', 'agency-core' ), $index + 1 ),
				array( 'status' => 400, 'section_index' => $index )
			);
		}
		if ( ! agency_core_normalize_section( $section ) ) {
			return new WP_Error(
				'agency_section_not_allowed',
				sprintf( __( 'Section %d contains an unknown component or variant.', 'agency-core' ), $index + 1 ),
				array( 'status' => 400, 'section_index' => $index )
			);
		}
	}
	return true;
}

function agency_core_normalize_sections( $sections ) {
	if ( ! is_array( $sections ) ) {
		return array();
	}
	$clean = array();
	foreach ( $sections as $section ) {
		$normalized = agency_core_normalize_section( $section );
		if ( ! $normalized ) {
			continue;
		}
		$clean[] = array(
			'component' => $normalized['component'],
			'variant'   => $normalized['variant'],
			'version'   => $normalized['version'],
			'data'      => agency_core_sanitize_component_data( $normalized['component'], $normalized['variant'], $normalized['data'] ),
		);
	}
	return $clean;
}

function agency_core_clean_sections( $sections ) {
	return agency_core_normalize_sections( $sections );
}
