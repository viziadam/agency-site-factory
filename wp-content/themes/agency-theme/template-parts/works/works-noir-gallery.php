<?php
/** Noir gallery works component. */
$data  = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title = $data['title'] ?? __( 'Works', 'agency-theme' );
$text  = $data['text'] ?? '';
$works = agency_theme_showcase_get_works( $data );
?>
<section class="agency-section agency-showcase agency-showcase-works agency-showcase-works--noir-gallery">
	<div class="agency-container">
		<div class="agency-noir-head agency-noir-head--light"><span><?php esc_html_e( 'Selected work', 'agency-theme' ); ?></span><?php agency_theme_showcase_section_header( $title, $text ); ?></div>
		<div class="agency-noir-gallery"><?php foreach ( $works as $index => $work ) : ?><article class="agency-noir-work <?php echo 0 === $index ? 'is-featured' : ''; ?>"><?php if ( $work['image_url'] ) : ?><img src="<?php echo esc_url( $work['image_url'] ); ?>" alt="<?php echo esc_attr( $work['title'] ); ?>" loading="lazy"><?php endif; ?><div><span><?php echo esc_html( $work['type'] ?: $work['year'] ); ?></span><h3><?php echo esc_html( $work['title'] ); ?></h3></div></article><?php endforeach; ?></div>
	</div>
</section>