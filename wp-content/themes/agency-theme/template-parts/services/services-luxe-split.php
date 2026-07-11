<?php
/** Luxe split services component. */
$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_showcase_get_services( $data );
$featured = $services ? array_shift( $services ) : null;
?>
<section class="agency-section agency-showcase agency-showcase-services agency-showcase-services--luxe-split">
	<div class="agency-container agency-luxe-split">
		<div class="agency-luxe-split__intro">
			<?php agency_theme_showcase_section_header( $title, $text ); ?>
			<?php if ( $featured ) : ?>
				<article class="agency-luxe-featured-service"><?php if ( $featured['image_url'] ) : ?><img src="<?php echo esc_url( $featured['image_url'] ); ?>" alt="<?php echo esc_attr( $featured['title'] ); ?>" loading="lazy"><?php endif; ?><h3><?php echo esc_html( $featured['title'] ); ?></h3><p><?php echo esc_html( $featured['text'] ); ?></p><?php if ( $featured['price'] ) : ?><strong><?php echo esc_html( $featured['price'] ); ?></strong><?php endif; ?></article>
			<?php endif; ?>
		</div>
		<div class="agency-luxe-split__list">
			<?php foreach ( $services as $service ) : ?>
				<article><h3><?php echo esc_html( $service['title'] ); ?></h3><p><?php echo esc_html( $service['text'] ); ?></p><div><span><?php echo esc_html( $service['duration'] ?: __( 'Personal consultation', 'agency-theme' ) ); ?></span><?php if ( $service['price'] ) : ?><strong><?php echo esc_html( $service['price'] ); ?></strong><?php endif; ?></div></article>
			<?php endforeach; ?>
		</div>
	</div>
</section>