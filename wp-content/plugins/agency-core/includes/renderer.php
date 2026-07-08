<?php
/**
 * Frontend render bridge.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_render_theme_component( $component, $data = array() ) {
	$normalized = agency_core_normalize_section( array( 'component' => $component, 'data' => $data ) );
	if ( ! $normalized ) {
		return '';
	}

	$registry      = agency_core_get_component_registry();
	$template_path = $registry[ $normalized['component'] ]['variants'][ $normalized['variant'] ]['template'];
	$template      = locate_template( $template_path . '.php' );
	if ( ! $template ) {
		return '';
	}

	ob_start();
	get_template_part( $template_path, null, array( 'data' => $data ) );
	return ob_get_clean();
}

function agency_core_render_cards( $post_type, $limit = 6, $class = 'agency-grid agency-grid--three' ) {
	$query = new WP_Query(
		array(
			'post_type'      => sanitize_key( $post_type ),
			'posts_per_page' => max( 1, min( 24, absint( $limit ) ) ),
			'post_status'    => 'publish',
		)
	);
	ob_start();
	echo '<div class="' . esc_attr( $class ) . '">';
	while ( $query->have_posts() ) {
		$query->the_post();
		echo '<article class="agency-card"><h3>' . esc_html( get_the_title() ) . '</h3><div class="agency-muted">' . wp_kses_post( wpautop( has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 24 ) ) ) . '</div></article>';
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
}
