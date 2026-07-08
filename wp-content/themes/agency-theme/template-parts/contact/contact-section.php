<?php
/**
 * Contact component.
 *
 * @package Agency_Theme
 */

$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title   = $data['title'] ?? __( 'Contact', 'agency-theme' );
$text    = $data['text'] ?? '';
$phone   = $data['phone'] ?? ( function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'phone' ) : '' );
$email   = $data['email'] ?? ( function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'email' ) : '' );
$address = $data['address'] ?? ( function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'address' ) : '' );
$booking = $data['booking_url'] ?? ( function_exists( 'agency_core_get_setting' ) ? agency_core_get_setting( 'booking_url' ) : '' );
?>
<section class="agency-section agency-contact">
	<div class="agency-container agency-contact__grid">
		<div>
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $text ) : ?><div class="agency-muted"><?php echo wp_kses_post( wpautop( $text ) ); ?></div><?php endif; ?>
		</div>
		<div class="agency-card agency-contact__details">
			<?php if ( $phone ) : ?><p><strong><?php esc_html_e( 'Phone', 'agency-theme' ); ?></strong><br><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p><?php endif; ?>
			<?php if ( $email ) : ?><p><strong><?php esc_html_e( 'Email', 'agency-theme' ); ?></strong><br><a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></p><?php endif; ?>
			<?php if ( $address ) : ?><p><strong><?php esc_html_e( 'Address', 'agency-theme' ); ?></strong><br><?php echo esc_html( $address ); ?></p><?php endif; ?>
			<?php if ( $booking ) : ?><a class="agency-button agency-button--primary" href="<?php echo esc_url( $booking ); ?>"><?php esc_html_e( 'Book now', 'agency-theme' ); ?></a><?php endif; ?>
		</div>
	</div>
</section>

