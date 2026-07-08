<?php
/**
 * Editorial service cards variant.
 *
 * @package Agency_Theme
 */

$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_get_service_items( $data );
?>
<section class="agency-section agency-services agency-services--cards">
	<div class="agency-container">
		<header class="agency-section-heading">
			<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
		</header>
		<div class="agency-services-cards">
			<?php foreach ( $services as $index => $service ) : ?>
				<article class="agency-service-editorial">
					<p class="agency-service-editorial__number"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></p>
					<div>
						<h3><?php echo esc_html( $service['title'] ?? '' ); ?></h3>
						<?php if ( ! empty( $service['text'] ) ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $service['text'] ) ); ?></div><?php endif; ?>
					</div>
					<?php if ( ! empty( $service['url'] ) ) : ?><a class="agency-service-editorial__link" href="<?php echo esc_url( $service['url'] ); ?>" aria-label="<?php echo esc_attr( $service['title'] ?? '' ); ?>">&rarr;</a><?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

