<?php
/**
 * Service card.
 *
 * @package Agency_Theme
 */

$data  = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title = $data['title'] ?? '';
$text  = $data['text'] ?? '';
$url   = $data['url'] ?? '';
?>
<article class="agency-card agency-service-card">
	<span class="agency-service-card__mark" aria-hidden="true"></span>
	<?php if ( $title ) : ?><h3><?php echo esc_html( $title ); ?></h3><?php endif; ?>
	<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
	<?php if ( $url ) : ?>
		<a class="agency-text-link" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Learn more', 'agency-theme' ); ?> <span aria-hidden="true">&rarr;</span></a>
	<?php endif; ?>
</article>

