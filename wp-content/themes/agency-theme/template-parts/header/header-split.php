<?php
/**
 * Split header with CTA variant.
 *
 * @package Agency_Theme
 */

$booking_url = function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'booking_url' ) : '';
?>
<header class="agency-site-header agency-site-header--split">
	<div class="agency-container agency-site-header__inner">
		<div class="agency-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="agency-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( agency_theme_brand_name() ); ?></a>
			<?php endif; ?>
		</div>
		<nav class="agency-navigation" aria-label="<?php esc_attr_e( 'Primary navigation', 'agency-theme' ); ?>">
			<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'agency-menu', 'fallback_cb' => false, 'depth' => 1 ) ); ?>
		</nav>
		<?php if ( $booking_url ) : ?>
			<a class="agency-button agency-button--primary agency-header-cta" href="<?php echo esc_url( $booking_url ); ?>"><?php esc_html_e( 'Book now', 'agency-theme' ); ?></a>
		<?php endif; ?>
	</div>
</header>

