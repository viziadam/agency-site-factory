<?php
/**
 * Services grid component.
 *
 * @package Agency_Theme
 */

$data     = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title    = $data['title'] ?? __( 'Services', 'agency-theme' );
$text     = $data['text'] ?? '';
$services = agency_theme_get_service_items( $data );
?>
<section class="agency-section agency-services">
	<div class="agency-container">
		<header class="agency-section-heading">
			<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
		</header>
		<?php if ( $services ) : ?>
			<div class="agency-grid agency-grid--three">
				<?php foreach ( $services as $service ) : ?>
					<?php get_template_part( 'template-parts/services/service-card', null, array( 'data' => $service ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="agency-muted"><?php esc_html_e( 'Services will appear here after they are added.', 'agency-theme' ); ?></p>
		<?php endif; ?>
	</div>
</section>
