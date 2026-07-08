<?php
/**
 * Legal page generation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_generate_pages() {
	$s     = agency_legal_settings();
	$pages = array(
		'privacy' => array( 'slug' => 'adatkezelesi-tajekoztato', 'title' => __( 'Privacy Policy', 'agency-module-legal' ), 'template' => 'privacy_template' ),
		'cookie'  => array( 'slug' => 'cookie-tajekoztato', 'title' => __( 'Cookie Policy', 'agency-module-legal' ), 'template' => 'cookie_template' ),
		'imprint' => array( 'slug' => 'impresszum', 'title' => __( 'Imprint', 'agency-module-legal' ), 'template' => 'imprint_template' ),
	);
	foreach ( $pages as $key => $page ) {
		$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
		if ( $existing && ! get_post_meta( $existing->ID, '_agency_legal_generated', true ) ) {
			$s['page_ids'][ $key ] = $existing->ID;
			continue;
		}
		if ( $existing ) {
			$s['page_ids'][ $key ] = $existing->ID;
			continue;
		}
		$post = array(
			'post_type'    => 'page',
			'post_status'  => in_array( $s['page_status'], array( 'draft', 'publish' ), true ) ? $s['page_status'] : 'draft',
			'post_name'    => $page['slug'],
			'post_title'   => $page['title'],
			'post_content' => wp_kses_post( agency_legal_template_values( $s[ $page['template'] ] ) ),
		);
		$id = wp_insert_post( wp_slash( $post ), true );
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_agency_legal_generated', '1' );
			$s['page_ids'][ $key ] = $id;
		}
	}
	update_option( 'agency_module_legal_settings', $s, false );
	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'legal', 'pages_generated', array( 'page_ids' => $s['page_ids'] ) );
	}
}
