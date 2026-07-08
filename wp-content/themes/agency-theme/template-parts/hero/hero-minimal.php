<?php
/**
 * Minimal hero variant.
 *
 * @package Agency_Theme
 */

$data         = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$eyebrow      = $data['eyebrow'] ?? '';
$title        = $data['title'] ?? '';
$text         = $data['text'] ?? '';
$button_label = $data['button_label'] ?? '';
$button_url   = $data['button_url'] ?? '';
?>
<section class="agency-section agency-hero agency-hero--minimal">
	<div class="agency-container">
		<?php if ( $eyebrow ) : ?><p class="agency-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
		<div class="agency-hero-minimal__grid">
			<?php if ( $title ) : ?><h1><?php echo esc_html( $title ); ?></h1><?php endif; ?>
			<div>
				<?php if ( $text ) : ?><div class="agency-hero__text"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
				<?php if ( $button_label && $button_url ) : ?>
					<a class="agency-text-link" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_label ); ?> <span aria-hidden="true">&rarr;</span></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

