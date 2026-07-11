<?php
/** Luxe mosaic works component. */
$data  = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title = $data['title'] ?? __( 'Works', 'agency-theme' );
$text  = $data['text'] ?? '';
$works = agency_theme_showcase_get_works( $data );
?>
<section class="agency-section agency-showcase agency-showcase-works agency-showcase-works--mosaic">
	<div class="agency-container">
		<?php agency_theme_showcase_section_header( $title, $text, 'agency-section-heading--split' ); ?>
		<div class="agency-works-mosaic"><?php foreach ( $works as $index => $work ) : ?><a class="agency-mosaic-work <?php echo 0 === $index || 3 === $index ? 'is-large' : ''; ?>" href="<?php echo esc_url( $work['url'] ); ?>"><?php if ( $work['image_url'] ) : ?><img src="<?php echo esc_url( $work['image_url'] ); ?>" alt="<?php echo esc_attr( $work['title'] ); ?>" loading="lazy"><?php endif; ?><span><?php echo esc_html( $work['type'] ?: __( 'Work', 'agency-theme' ) ); ?></span><strong><?php echo esc_html( $work['title'] ); ?></strong></a><?php endforeach; ?></div>
	</div>
</section>