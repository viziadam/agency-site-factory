<?php
/**
 * Image card hero variant.
 *
 * @package Agency_Theme
 */

$data         = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$eyebrow      = $data['eyebrow'] ?? '';
$title        = $data['title'] ?? '';
$text         = $data['text'] ?? '';
$button_label = $data['button_label'] ?? '';
$button_url   = $data['button_url'] ?? '';
$image_url    = $data['image_url'] ?? '';
?>
<section class="agency-section agency-hero agency-hero--image-card">
	<div class="agency-container">
		<div class="agency-hero-card">
			<div class="agency-hero-card__content">
				<?php if ( $eyebrow ) : ?><p class="agency-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
				<?php if ( $title ) : ?><h1><?php echo esc_html( $title ); ?></h1><?php endif; ?>
				<?php if ( $text ) : ?><div class="agency-hero__text"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
				<?php if ( $button_label && $button_url ) : ?>
					<a class="agency-button agency-button--primary" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_label ); ?></a>
				<?php endif; ?>
			</div>
			<div class="agency-hero-card__media">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="eager">
				<?php else : ?>
					<div class="agency-hero-card__placeholder" aria-hidden="true"><?php echo esc_html( substr( agency_theme_brand_name(), 0, 1 ) ); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

