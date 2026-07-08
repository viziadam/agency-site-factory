<?php
/**
 * Split hero component.
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
$brand_initial = function_exists( 'mb_substr' ) ? mb_substr( agency_theme_brand_name(), 0, 1 ) : substr( agency_theme_brand_name(), 0, 1 );
?>
<section class="agency-section agency-hero agency-hero--split">
	<div class="agency-container agency-hero__grid">
		<div class="agency-hero__content">
			<?php if ( $eyebrow ) : ?><p class="agency-eyebrow"><?php echo esc_html( $eyebrow ); ?></p><?php endif; ?>
			<?php if ( $title ) : ?><h1><?php echo esc_html( $title ); ?></h1><?php endif; ?>
			<?php if ( $text ) : ?><div class="agency-hero__text"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
			<?php if ( $button_label && $button_url ) : ?>
				<a class="agency-button agency-button--primary" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button_label ); ?></a>
			<?php endif; ?>
		</div>
		<div class="agency-hero__visual">
			<?php if ( $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="eager">
			<?php else : ?>
				<div class="agency-hero__monogram" aria-hidden="true"><?php echo esc_html( $brand_initial ); ?></div>
			<?php endif; ?>
		</div>
	</div>
</section>
