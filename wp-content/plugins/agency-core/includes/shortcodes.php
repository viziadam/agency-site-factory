<?php
/**
 * Public shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_services_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'title' => 'Services', 'limit' => 6 ), $atts, 'agency_services_grid' );
	$themed = agency_core_render_theme_component( 'services/services-grid', array( 'title' => sanitize_text_field( $atts['title'] ), 'limit' => absint( $atts['limit'] ) ) );
	return $themed ?: agency_core_render_cards( 'agency_service', $atts['limit'] );
}
add_shortcode( 'agency_services_grid', 'agency_core_services_shortcode' );

function agency_core_faq_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'title' => 'Frequently asked questions', 'limit' => 8 ), $atts, 'agency_faq' );
	$themed = agency_core_render_theme_component( 'faq/faq-accordion', array( 'title' => sanitize_text_field( $atts['title'] ), 'limit' => absint( $atts['limit'] ) ) );
	return $themed ?: agency_core_render_cards( 'agency_faq', $atts['limit'] );
}
add_shortcode( 'agency_faq', 'agency_core_faq_shortcode' );

function agency_core_testimonials_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'limit' => 3 ), $atts, 'agency_testimonials' );
	$query = new WP_Query( array( 'post_type' => 'agency_testimonial', 'posts_per_page' => absint( $atts['limit'] ), 'post_status' => 'publish' ) );
	ob_start();
	echo '<div class="agency-grid agency-grid--three agency-testimonials">';
	while ( $query->have_posts() ) {
		$query->the_post();
		echo '<article class="agency-card agency-testimonial"><blockquote>' . wp_kses_post( wpautop( get_the_content() ) ) . '<cite>' . esc_html( get_the_title() ) . '</cite></blockquote></article>';
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'agency_testimonials', 'agency_core_testimonials_shortcode' );

function agency_core_cta_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'title'        => 'Ready to get started?',
			'text'         => '',
			'button_label' => 'Contact',
			'button_url'   => home_url( '/contact/' ),
		),
		$atts,
		'agency_cta'
	);
	$data = array(
		'title'        => sanitize_text_field( $atts['title'] ),
		'text'         => sanitize_textarea_field( $atts['text'] ),
		'button_label' => sanitize_text_field( $atts['button_label'] ),
		'button_url'   => esc_url_raw( $atts['button_url'] ),
	);
	$themed = agency_core_render_theme_component( 'cta/cta-simple', $data );
	if ( $themed ) {
		return $themed;
	}
	return '<div class="agency-cta"><div><h2>' . esc_html( $data['title'] ) . '</h2><p>' . esc_html( $data['text'] ) . '</p></div><a class="agency-button agency-button--primary" href="' . esc_url( $data['button_url'] ) . '">' . esc_html( $data['button_label'] ) . '</a></div>';
}
add_shortcode( 'agency_cta', 'agency_core_cta_shortcode' );

