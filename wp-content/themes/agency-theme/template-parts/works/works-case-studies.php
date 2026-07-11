<?php
/** Case studies works component. */
$data  = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title = $data['title'] ?? __( 'Works', 'agency-theme' );
$text  = $data['text'] ?? '';
$works = agency_theme_showcase_get_works( $data );
?>
<section class="agency-section agency-showcase agency-showcase-works agency-showcase-works--cases">
	<div class="agency-container agency-case-list">
		<?php agency_theme_showcase_section_header( $title, $text ); ?>
		<?php foreach ( $works as $work ) : ?><article class="agency-case-study"><div class="agency-case-study__meta"><span><?php echo esc_html( $work['year'] ?: wp_date( 'Y' ) ); ?></span><?php if ( $work['type'] ) : ?><strong><?php echo esc_html( $work['type'] ); ?></strong><?php endif; ?></div><div><h3><?php echo esc_html( $work['title'] ); ?></h3><p><?php echo esc_html( $work['text'] ); ?></p></div><?php if ( $work['image_url'] ) : ?><img src="<?php echo esc_url( $work['image_url'] ); ?>" alt="<?php echo esc_attr( $work['title'] ); ?>" loading="lazy"><?php endif; ?></article><?php endforeach; ?>
	</div>
</section>