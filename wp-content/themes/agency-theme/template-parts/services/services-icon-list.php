<?php
/**
 * Service icon list variant.
 *
 * @package Agency_Theme
 */

$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_get_service_items( $data );
?>
<section class="agency-section agency-services agency-services--icon-list">
	<div class="agency-container agency-services-icon-layout">
		<header class="agency-section-heading">
			<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
		</header>
		<div class="agency-services-icon-list">
			<?php foreach ( $services as $service ) : ?>
				<article class="agency-service-icon">
					<span class="agency-service-icon__mark" aria-hidden="true">&plus;</span>
					<div>
						<h3><?php echo esc_html( $service['title'] ?? '' ); ?></h3>
						<?php if ( ! empty( $service['text'] ) ) : ?><p class="agency-muted"><?php echo esc_html( wp_strip_all_tags( $service['text'] ) ); ?></p><?php endif; ?>
					</div>
					<?php if ( ! empty( $service['url'] ) ) : ?><a class="agency-text-link" href="<?php echo esc_url( $service['url'] ); ?>"><?php esc_html_e( 'Details', 'agency-theme' ); ?></a><?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

