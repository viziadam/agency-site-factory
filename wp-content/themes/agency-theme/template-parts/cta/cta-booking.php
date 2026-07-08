<?php
/**
 * Booking CTA variant.
 *
 * @package Agency_Theme
 */

$data         = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title        = $data['title'] ?? '';
$text         = $data['text'] ?? '';
$button_label = $data['button_label'] ?? __( 'Book now', 'agency-theme' );
$button_url   = $data['button_url'] ?? '';
if ( ! $button_url && function_exists( 'agency_core_get_setting' ) ) {
	$button_url = agency_core_get_setting( 'booking_url' );
}
?>
<section class="agency-section agency-cta-section agency-cta-section--booking">
	<div class="agency-container">
		<div class="agency-cta-booking">
			<div class="agency-cta-booking__mark" aria-hidden="true">&star;</div>
			<div>
				<p class="agency-eyebrow"><?php esc_html_e( 'Online booking', 'agency-theme' ); ?></p>
				<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
				<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
			</div>
			<?php if ( $button_label && $button_url ) : ?><a class="agency-button agency-button--primary" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_label ); ?></a><?php endif; ?>
		</div>
	</div>
</section>

