<?php
/**
 * Services and works component variants.
 *
 * @package Agency_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_theme_showcase_extend_registry( $registry ) {
	if ( isset( $registry['services'] ) ) {
		$registry['services']['fields']['show_price']   = array( 'label' => __( 'Show prices', 'agency-theme' ), 'type' => 'checkbox', 'default' => 1 );
		$registry['services']['fields']['button_label'] = array( 'label' => __( 'Button label', 'agency-theme' ), 'type' => 'text', 'default' => __( 'View details', 'agency-theme' ) );
		$registry['services']['variants']['price-menu'] = array( 'label' => __( 'Price menu', 'agency-theme' ), 'template' => 'template-parts/services/services-price-menu' );
		$registry['services']['variants']['noir-elegant'] = array( 'label' => __( 'Noir elegant', 'agency-theme' ), 'template' => 'template-parts/services/services-noir-elegant' );
		$registry['services']['variants']['luxe-split'] = array( 'label' => __( 'Luxe split', 'agency-theme' ), 'template' => 'template-parts/services/services-luxe-split' );
	}

	$registry['works'] = array(
		'label'           => __( 'Works', 'agency-theme' ),
		'editor_label'    => __( 'Works / portfolio section', 'agency-theme' ),
		'default_variant' => 'editorial-grid',
		'version'         => 1,
		'fields'          => array(
			'title'        => array( 'label' => __( 'Title', 'agency-theme' ), 'type' => 'text' ),
			'text'         => array( 'label' => __( 'Text', 'agency-theme' ), 'type' => 'textarea' ),
			'limit'        => array( 'label' => __( 'Item limit', 'agency-theme' ), 'type' => 'number', 'default' => 6 ),
			'button_label' => array( 'label' => __( 'Button label', 'agency-theme' ), 'type' => 'text', 'default' => __( 'View work', 'agency-theme' ) ),
		),
		'default_data'    => array( 'title' => __( 'Works', 'agency-theme' ), 'limit' => 6 ),
		'variants'        => array(
			'editorial-grid' => array( 'label' => __( 'Editorial grid', 'agency-theme' ), 'template' => 'template-parts/works/works-editorial-grid' ),
			'noir-gallery'   => array( 'label' => __( 'Noir gallery', 'agency-theme' ), 'template' => 'template-parts/works/works-noir-gallery' ),
			'luxe-mosaic'    => array( 'label' => __( 'Luxe mosaic', 'agency-theme' ), 'template' => 'template-parts/works/works-luxe-mosaic' ),
			'case-studies'   => array( 'label' => __( 'Case studies', 'agency-theme' ), 'template' => 'template-parts/works/works-case-studies' ),
		),
	);

	return $registry;
}
add_filter( 'agency_theme_component_registry', 'agency_theme_showcase_extend_registry', 20 );

function agency_theme_showcase_price( $post_id ) {
	$price    = get_post_meta( $post_id, '_agency_price', true );
	$currency = get_post_meta( $post_id, '_agency_price_currency', true ) ?: 'HUF';
	if ( '' === $price || ! is_numeric( $price ) ) {
		return '';
	}
	return number_format_i18n( (float) $price, 0 ) . ' ' . $currency;
}

function agency_theme_showcase_image_url( $post_id, $size = 'agency-card' ) {
	$image = get_post_meta( $post_id, '_agency_image_url', true );
	if ( $image ) {
		return esc_url_raw( $image );
	}
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, $size );
	}
	return '';
}

function agency_theme_showcase_get_services( $data = array() ) {
	$limit = isset( $data['limit'] ) ? max( 1, absint( $data['limit'] ) ) : 6;
	$query = new WP_Query(
		array(
			'post_type'      => 'agency_service',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		)
	);
	$items = array();
	foreach ( $query->posts as $service ) {
		$items[] = array(
			'id'        => $service->ID,
			'title'     => get_the_title( $service ),
			'text'      => has_excerpt( $service ) ? get_the_excerpt( $service ) : wp_trim_words( $service->post_content, 28 ),
			'content'   => $service->post_content,
			'url'       => get_permalink( $service ),
			'price'     => agency_theme_showcase_price( $service->ID ),
			'duration'  => get_post_meta( $service->ID, '_agency_duration', true ),
			'image_url' => agency_theme_showcase_image_url( $service->ID ),
		);
	}
	wp_reset_postdata();
	return $items;
}

function agency_theme_showcase_get_works( $data = array() ) {
	$limit = isset( $data['limit'] ) ? max( 1, absint( $data['limit'] ) ) : 6;
	$query = new WP_Query(
		array(
			'post_type'      => 'agency_portfolio',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		)
	);
	$items = array();
	foreach ( $query->posts as $work ) {
		$items[] = array(
			'id'        => $work->ID,
			'title'     => get_the_title( $work ),
			'text'      => has_excerpt( $work ) ? get_the_excerpt( $work ) : wp_trim_words( $work->post_content, 26 ),
			'content'   => $work->post_content,
			'url'       => get_permalink( $work ),
			'image_url' => agency_theme_showcase_image_url( $work->ID, 'agency-hero' ),
			'type'      => get_post_meta( $work->ID, '_agency_project_type', true ),
			'client'    => get_post_meta( $work->ID, '_agency_project_client', true ),
			'year'      => get_post_meta( $work->ID, '_agency_project_year', true ),
		);
	}
	wp_reset_postdata();
	return $items;
}

function agency_theme_showcase_section_header( $title, $text = '', $class = '' ) {
	if ( ! $title && ! $text ) {
		return;
	}
	?>
	<header class="agency-section-heading <?php echo esc_attr( $class ); ?>">
		<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
		<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
	</header>
	<?php
}

function agency_theme_showcase_frontend_styles( $styles ) {
	$version = wp_get_theme()->get( 'Version' );
	$styles['agency-theme-showcase'] = add_query_arg( 'ver', $version, get_template_directory_uri() . '/assets/css/showcase.css' );
	return $styles;
}
add_filter( 'agency_theme_frontend_styles', 'agency_theme_showcase_frontend_styles' );