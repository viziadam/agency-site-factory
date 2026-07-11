<?php
/** Price-menu services component. */
$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_showcase_get_services( $data );
$button   = $data['button_label'] ?? __( 'View details', 'agency-theme' );
?>
<section class="agency-section agency-showcase agency-showcase-services agency-showcase-services--price-menu">
	<div class="agency-container">
		<?php agency_theme_showcase_section_header( $title, $text ); ?>
		<?php if ( $services ) : ?>
			<div class="agency-price-menu">
				<?php foreach ( $services as $index => $service ) : ?>
					<article class="agency-price-menu__item">
						<span class="agency-price-menu__number"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<div><h3><?php echo esc_html( $service['title'] ); ?></h3><?php if ( $service['text'] ) : ?><p><?php echo esc_html( $service['text'] ); ?></p><?php endif; ?></div>
						<div class="agency-price-menu__meta"><?php if ( ! empty( $service['duration'] ) ) : ?><small><?php echo esc_html( $service['duration'] ); ?></small><?php endif; ?><?php if ( ! empty( $service['price'] ) ) : ?><strong><?php echo esc_html( $service['price'] ); ?></strong><?php endif; ?></div>
						<a href="<?php echo esc_url( $service['url'] ); ?>" aria-label="<?php echo esc_attr( $button . ': ' . $service['title'] ); ?>">→</a>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="agency-muted"><?php esc_html_e( 'Services will appear here after they are added.', 'agency-theme' ); ?></p>
		<?php endif; ?>
	</div>
</section>