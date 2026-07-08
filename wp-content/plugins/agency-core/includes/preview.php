<?php
/**
 * Authenticated PHP-rendered live preview endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_register_preview_route() {
	register_rest_route(
		'agency/v1',
		'/preview-sections',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'agency_core_preview_sections',
			'permission_callback' => 'agency_core_preview_permission',
			'args'                => array(
				'post_id'  => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'sections' => array( 'required' => true, 'type' => 'array' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'agency_core_register_preview_route' );

function agency_core_preview_permission( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'post_id' ) );
	$nonce   = sanitize_text_field( $request->get_header( 'X-WP-Nonce' ) );

	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'agency_preview_auth', __( 'You must be logged in to use the Agency preview.', 'agency-core' ), array( 'status' => 401 ) );
	}
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'agency_preview_nonce', __( 'The Agency preview security token is invalid.', 'agency-core' ), array( 'status' => 403 ) );
	}
	if ( ! $post_id || 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'agency_preview_permission', __( 'You cannot preview sections for this page.', 'agency-core' ), array( 'status' => 403 ) );
	}
	return true;
}

function agency_core_preview_sections( WP_REST_Request $request ) {
	$post_id  = absint( $request->get_param( 'post_id' ) );
	$sections = $request->get_param( 'sections' );
	$valid    = agency_core_validate_sections( $sections );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	if ( ! function_exists( 'agency_theme_render_sections' ) || ! function_exists( 'agency_theme_get_frontend_styles' ) ) {
		return new WP_Error( 'agency_preview_theme', __( 'Agency Theme must be active to render the live preview.', 'agency-core' ), array( 'status' => 409 ) );
	}

	$normalized = agency_core_normalize_sections( $sections );
	$assets     = array(
		'styles'    => array_values( agency_theme_get_frontend_styles() ),
		'scripts'   => array_values( agency_theme_get_frontend_scripts() ),
		'token_css' => agency_theme_get_dynamic_token_css(),
	);
	$assets['styles'][] = add_query_arg( 'ver', AGENCY_CORE_VERSION, plugins_url( 'assets/css/preview.css', AGENCY_CORE_FILE ) );
	$html = agency_core_build_preview_document( $post_id, $normalized, $assets );

	return rest_ensure_response(
		array(
			'html'       => $html,
			'assets'     => $assets,
			'sections'   => $normalized,
			'section_count' => count( $normalized ),
		)
	);
}

function agency_core_build_preview_document( $post_id, $sections, $assets ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$previous_post = $GLOBALS['post'] ?? null;
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );

	ob_start();
	get_template_part( 'template-parts/layout/site-header' );
	echo '<main id="main-content" class="agency-site-main agency-preview-main">';
	agency_theme_render_sections( $sections, 'editor_preview' );
	echo '</main>';
	get_template_part( 'template-parts/layout/site-footer' );
	$content = ob_get_clean();

	wp_reset_postdata();
	$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	$style_tags = '';
	foreach ( $assets['styles'] as $style ) {
		$style_tags .= '<link rel="stylesheet" href="' . esc_url( $style ) . '">';
	}
	if ( $assets['token_css'] ) {
		$style_tags .= '<style id="agency-preview-tokens">' . wp_strip_all_tags( $assets['token_css'] ) . '</style>';
	}

	$script_tags = '';
	foreach ( $assets['scripts'] as $script ) {
		$script_tags .= '<script src="' . esc_url( $script ) . '"></script>';
	}

	return '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( get_the_title( $post ) ) . ' — Agency live preview</title>' . $style_tags . '</head><body class="agency-preview-body page page-id-' . absint( $post_id ) . '"><div class="agency-preview-toolbar" role="status">' . esc_html__( 'Agency live preview — mentés előtt nem publikus', 'agency-core' ) . '</div>' . $content . $script_tags . '</body></html>';
}

