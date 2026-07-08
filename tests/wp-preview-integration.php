<?php
/**
 * Run with: wp eval-file tests/wp-preview-integration.php
 *
 * Exercises authenticated preview rendering, invalid input rejection,
 * unsaved-state isolation, and save-to-frontend parity. Restores test meta.
 */
$admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
if ( ! $admin_ids ) {
	echo wp_json_encode( array( 'error' => 'No administrator user found.' ) );
	return;
}

wp_set_current_user( (int) $admin_ids[0] );
do_action( 'rest_api_init', rest_get_server() );

$front_id       = (int) get_option( 'page_on_front' );
$saved_raw      = get_post_meta( $front_id, '_agency_sections', true );
$saved_sections = json_decode( $saved_raw, true );
$preview        = agency_core_normalize_sections( $saved_sections );

$preview[0]['variant']       = 'minimal';
$preview[0]['data']['title'] = 'PREVIEW UNSAVED HERO';
$preview[1]['variant']       = 'cards';
$preview[1]['data']['title'] = 'PREVIEW SERVICES CARDS';
$preview[3]['variant']       = 'full-width';
$preview[3]['data']['title'] = 'PREVIEW CTA FULL WIDTH';
$preview = array( $preview[3], $preview[0], $preview[1], $preview[2] );

$request = new WP_REST_Request( 'POST', '/agency/v1/preview-sections' );
$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$request->set_body_params(
	array(
		'post_id'  => $front_id,
		'sections' => $preview,
	)
);
$response = rest_do_request( $request );
$data     = $response->get_data();
$html     = $data['html'] ?? '';

$invalid_request = new WP_REST_Request( 'POST', '/agency/v1/preview-sections' );
$invalid_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$invalid_request->set_body_params(
	array(
		'post_id'  => $front_id,
		'sections' => array(
			array(
				'component' => 'not-allowed',
				'variant'   => 'php-file',
				'data'      => array(),
			),
		),
	)
);
$invalid_response = rest_do_request( $invalid_request );

$bad_nonce_request = new WP_REST_Request( 'POST', '/agency/v1/preview-sections' );
$bad_nonce_request->set_header( 'X-WP-Nonce', 'invalid-preview-nonce' );
$bad_nonce_request->set_body_params( array( 'post_id' => $front_id, 'sections' => $preview ) );
$bad_nonce_response = rest_do_request( $bad_nonce_request );

$valid_nonce = wp_create_nonce( 'wp_rest' );
wp_set_current_user( 0 );
$anonymous_request = new WP_REST_Request( 'POST', '/agency/v1/preview-sections' );
$anonymous_request->set_header( 'X-WP-Nonce', $valid_nonce );
$anonymous_request->set_body_params( array( 'post_id' => $front_id, 'sections' => $preview ) );
$anonymous_response = rest_do_request( $anonymous_request );
wp_set_current_user( (int) $admin_ids[0] );

$test_page = get_page_by_path( 'sample-page', OBJECT, 'page' );
$save_test = array( 'available' => false );
if ( $test_page ) {
	$test_id       = (int) $test_page->ID;
	$had_meta      = metadata_exists( 'post', $test_id, '_agency_sections' );
	$original_meta = get_post_meta( $test_id, '_agency_sections', true );
	update_post_meta( $test_id, '_agency_sections', agency_core_sanitize_sections_meta( $preview ) );

	$frontend_response = wp_remote_get( get_permalink( $test_id ), array( 'timeout' => 15 ) );
	$frontend_html     = is_wp_error( $frontend_response ) ? '' : wp_remote_retrieve_body( $frontend_response );
	$save_test         = array(
		'available'               => true,
		'http_status'             => is_wp_error( $frontend_response ) ? 0 : wp_remote_retrieve_response_code( $frontend_response ),
		'hero_matches'            => str_contains( $frontend_html, 'PREVIEW UNSAVED HERO' ) && str_contains( $frontend_html, 'agency-hero--minimal' ),
		'services_matches'        => str_contains( $frontend_html, 'PREVIEW SERVICES CARDS' ) && str_contains( $frontend_html, 'agency-services--cards' ),
		'cta_matches'             => str_contains( $frontend_html, 'PREVIEW CTA FULL WIDTH' ) && str_contains( $frontend_html, 'agency-cta-full' ),
		'saved_meta_is_normalized' => str_contains( get_post_meta( $test_id, '_agency_sections', true ), '"variant":"minimal"' ),
	);

	if ( $had_meta ) {
		update_post_meta( $test_id, '_agency_sections', $original_meta );
	} else {
		delete_post_meta( $test_id, '_agency_sections' );
	}
	$save_test['original_restored'] = $had_meta
		? $original_meta === get_post_meta( $test_id, '_agency_sections', true )
		: ! metadata_exists( 'post', $test_id, '_agency_sections' );
}

$cta_position      = strpos( $html, 'PREVIEW CTA FULL WIDTH' );
$hero_position     = strpos( $html, 'PREVIEW UNSAVED HERO' );
$services_position = strpos( $html, 'PREVIEW SERVICES CARDS' );

echo wp_json_encode(
	array(
		'route_status'              => $response->get_status(),
		'full_document'             => str_starts_with( $html, '<!doctype html>' ),
		'section_count'             => $data['section_count'] ?? 0,
		'php_templates_rendered'    => str_contains( $html, 'agency-hero--minimal' ) && str_contains( $html, 'agency-services--cards' ) && str_contains( $html, 'agency-cta-full' ),
		'modified_values_rendered'  => str_contains( $html, 'PREVIEW UNSAVED HERO' ) && str_contains( $html, 'PREVIEW SERVICES CARDS' ) && str_contains( $html, 'PREVIEW CTA FULL WIDTH' ),
		'reordered_output'          => false !== $cta_position && $cta_position < $hero_position && $hero_position < $services_position,
		'header_rendered'           => str_contains( $html, 'agency-site-header--split' ),
		'footer_rendered'           => str_contains( $html, 'agency-site-footer' ),
		'preview_label_rendered'    => str_contains( $html, 'Agency live preview' ),
		'frontend_assets_returned'  => count( $data['assets']['styles'] ?? array() ) >= 7 && count( $data['assets']['scripts'] ?? array() ) >= 1,
		'design_tokens_returned'    => str_contains( $data['assets']['token_css'] ?? '', '--agency-color-primary' ),
		'unsaved_meta_unchanged'    => $saved_raw === get_post_meta( $front_id, '_agency_sections', true ),
		'invalid_payload_status'    => $invalid_response->get_status(),
		'invalid_payload_code'      => $invalid_response->get_data()['code'] ?? '',
		'bad_nonce_status'          => $bad_nonce_response->get_status(),
		'anonymous_status'          => $anonymous_response->get_status(),
		'save_then_frontend_test'   => $save_test,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
