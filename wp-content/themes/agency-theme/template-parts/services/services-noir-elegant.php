<?php
/** Noir elegant services component. */
$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_showcase_get_services( $data );
?>
<section class="agency-section agency-showcase agency-showcase-services agency-showcase-services--noir">
	<div class="agency-container">
		<div class="agency-noir-head"><span><?php esc_html_e( 'Signature services', 'agency-theme' ); ?></span><?php agency_theme_showcase_section_header( $title, $text ); ?></div>
		<div class="agency-noir-service-grid">
			<?php foreach ( $services as $service ) : ?>
				<article class="agency-noir-service-card">
					<?php if ( $service['image_url'] ) : ?><img src="<?php echo esc_url( $service['image_url'] ); ?>" alt="<?php echo esc_attr( $service['title'] ); ?>" loading="lazy"><?php endif; ?>
					<div><h3><?php echo esc_html( $service['title'] ); ?></h3><?php if ( $service['text'] ) : ?><p><?php echo esc_html( $service['text'] ); ?></p><?php endif; ?><div class="agency-noir-service-card__footer"><?php if ( $service['price'] ) : ?><strong><?php echo esc_html( $service['price'] ); ?></strong><?php endif; ?><a href="<?php echo esc_url( $service['url'] ); ?>"><?php esc_html_e( 'Details', 'agency-theme' ); ?></a></div></div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>