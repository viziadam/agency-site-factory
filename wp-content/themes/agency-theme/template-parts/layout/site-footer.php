<?php
/**
 * Site footer component.
 *
 * @package Agency_Theme
 */

$phone = function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'phone' ) : '';
$email = function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'email' ) : '';
?>
<footer class="agency-site-footer">
	<div class="agency-container agency-site-footer__grid">
		<div>
			<p class="agency-site-footer__brand"><?php echo esc_html( agency_theme_brand_name() ); ?></p>
			<p class="agency-muted"><?php esc_html_e( 'A modular foundation for excellent service websites.', 'agency-theme' ); ?></p>
		</div>
		<nav aria-label="<?php esc_attr_e( 'Footer navigation', 'agency-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'agency-footer-menu',
					'fallback_cb'    => false,
					'depth'          => 1,
				)
			);
			?>
		</nav>
		<address>
			<?php if ( $phone ) : ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
			<?php if ( $email ) : ?><a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a><?php endif; ?>
		</address>
	</div>
	<div class="agency-container agency-site-footer__legal">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( agency_theme_brand_name() ); ?></p>
	</div>
</footer>

