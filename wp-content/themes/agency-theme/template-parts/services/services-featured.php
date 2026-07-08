<?php
/**
 * Featured service variant.
 *
 * @package Agency_Theme
 */

$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_get_service_items( $data );
$featured = $services ? array_shift( $services ) : array();
?>
<section class="agency-section agency-services agency-services--featured">
	<div class="agency-container">
		<header class="agency-section-heading">
			<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
		</header>
		<?php if ( $featured ) : ?>
			<article class="agency-service-featured">
				<p class="agency-eyebrow"><?php esc_html_e( 'Featured service', 'agency-theme' ); ?></p>
				<h3><?php echo esc_html( $featured['title'] ?? '' ); ?></h3>
				<?php if ( ! empty( $featured['text'] ) ) : ?><div><?php echo wp_kses_post( wpautop( $featured['text'] ) ); ?></div><?php endif; ?>
				<?php if ( ! empty( $featured['url'] ) ) : ?><a class="agency-button agency-button--secondary" href="<?php echo esc_url( $featured['url'] ); ?>"><?php esc_html_e( 'Learn more', 'agency-theme' ); ?></a><?php endif; ?>
			</article>
		<?php endif; ?>
		<?php if ( $services ) : ?>
			<div class="agency-grid agency-grid--three agency-services-featured__rest">
				<?php foreach ( $services as $service ) : ?>
					<?php get_template_part( 'template-parts/services/service-card', null, array( 'data' => $service ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

