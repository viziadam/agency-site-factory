<?php
/**
 * Legal frontend links.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_links_html() {
	$s = agency_legal_settings();
	if ( empty( $s['auto_add_footer_legal_links'] ) || empty( $s['page_ids'] ) ) {
		return '';
	}
	$html = '<nav class="agency-legal-links" aria-label="' . esc_attr__( 'Legal', 'agency-module-legal' ) . '">';
	foreach ( $s['page_ids'] as $id ) {
		if ( 'publish' === get_post_status( $id ) ) {
			$html .= '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a> ';
		}
	}
	return $html . '</nav>';
}

function agency_legal_footer_links() {
	echo wp_kses_post( agency_legal_links_html() );
}
add_action( 'wp_footer', 'agency_legal_footer_links', 30 );
add_shortcode( 'agency_legal_links', 'agency_legal_links_html' );
