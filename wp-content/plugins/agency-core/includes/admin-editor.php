<?php
/**
 * No-build Gutenberg document sidebar for page sections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_enqueue_section_editor( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'page' !== $screen->post_type || ! $screen->is_block_editor() ) {
		return;
	}

	wp_enqueue_style(
		'agency-core-editor',
		plugins_url( 'assets/css/admin.css', AGENCY_CORE_FILE ),
		array( 'wp-components' ),
		AGENCY_CORE_VERSION
	);
	wp_enqueue_script(
		'agency-core-section-editor',
		plugins_url( 'assets/js/section-editor.js', AGENCY_CORE_FILE ),
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch' ),
		AGENCY_CORE_VERSION,
		true
	);
	wp_localize_script(
		'agency-core-section-editor',
		'AgencyCoreEditor',
		array(
			'components' => agency_core_get_component_registry(),
			'postId'     => get_the_ID(),
			'previewPath' => '/agency/v1/preview-sections',
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'debounceMs' => 400,
			'strings'    => array(
				'panelTitle'  => __( 'Agency sections', 'agency-core' ),
				'addSection'  => __( 'Add section', 'agency-core' ),
				'remove'      => __( 'Remove', 'agency-core' ),
				'moveUp'      => __( 'Move up', 'agency-core' ),
				'moveDown'    => __( 'Move down', 'agency-core' ),
				'component'   => __( 'Component', 'agency-core' ),
				'variant'     => __( 'Variant', 'agency-core' ),
				'invalidJson' => __( 'The stored section JSON is invalid. Add a section to replace it safely.', 'agency-core' ),
				'empty'       => __( 'No modular sections yet. The normal page content is used until you add one.', 'agency-core' ),
				'previewTitle' => __( 'Agency live preview', 'agency-core' ),
				'previewLoading' => __( 'A PHP preview frissítése…', 'agency-core' ),
				'previewError' => __( 'A live preview most nem tölthető be.', 'agency-core' ),
				'repeaterHelp' => __( 'A repeater mező JSON tömböt vár. Az érték csak érvényes JSON esetén frissül.', 'agency-core' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'agency_core_enqueue_section_editor' );
