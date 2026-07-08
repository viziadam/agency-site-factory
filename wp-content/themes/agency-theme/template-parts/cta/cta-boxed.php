<?php
/**
 * Boxed CTA variant.
 *
 * @package Agency_Theme
 */

$data         = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title        = $data['title'] ?? '';
$text         = $data['text'] ?? '';
$button_label = $data['button_label'] ?? '';
$button_url   = $data['button_url'] ?? '';
?>
<section class="agency-section agency-cta-section agency-cta-section--boxed">
	<div class="agency-container agency-container--narrow">
		<div class="agency-cta agency-cta--boxed">
			<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><div><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
			<?php if ( $button_label && $button_url ) : ?><a class="agency-button agency-button--secondary" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_label ); ?></a><?php endif; ?>
		</div>
	</div>
</section>

