<?php
/** Editorial works grid component. */
$data  = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title = $data['title'] ?? __( 'Works', 'agency-theme' );
$text  = $data['text'] ?? '';
$works = agency_theme_showcase_get_works( $data );
?>
<section class="agency-section agency-showcase agency-showcase-works agency-showcase-works--editorial">
	<div class="agency-container">
		<?php agency_theme_showcase_section_header( $title, $text ); ?>
		<?php if ( $works ) : ?><div class="agency-works-editorial-grid"><?php foreach ( $works as $work ) : ?><article class="agency-work-card"><?php if ( $work['image_url'] ) : ?><a class="agency-work-card__image" href="<?php echo esc_url( $work['url'] ); ?>"><img src="<?php echo esc_url( $work['image_url'] ); ?>" alt="<?php echo esc_attr( $work['title'] ); ?>" loading="lazy"></a><?php endif; ?><div class="agency-work-card__body"><?php if ( $work['type'] ) : ?><span><?php echo esc_html( $work['type'] ); ?></span><?php endif; ?><h3><a href="<?php echo esc_url( $work['url'] ); ?>"><?php echo esc_html( $work['title'] ); ?></a></h3><p><?php echo esc_html( $work['text'] ); ?></p></div></article><?php endforeach; ?></div><?php else : ?><p class="agency-muted"><?php esc_html_e( 'Works will appear here after they are added.', 'agency-theme' ); ?></p><?php endif; ?>
	</div>
</section>