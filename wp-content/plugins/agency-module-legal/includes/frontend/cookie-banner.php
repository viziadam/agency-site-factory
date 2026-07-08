<?php
/**
 * Cookie banner and frontend assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_cookie_banner() {
	$s = agency_legal_settings();
	if ( empty( $s['cookie_banner'] ) ) {
		return;
	}
	$cookie_url = ! empty( $s['page_ids']['cookie'] ) ? get_permalink( $s['page_ids']['cookie'] ) : '';
	?><aside class="agency-cookie-banner" data-agency-cookie-banner hidden role="dialog" aria-label="<?php esc_attr_e( 'Cookie settings', 'agency-module-legal' ); ?>"><div><strong><?php esc_html_e( 'Your privacy matters', 'agency-module-legal' ); ?></strong><p><?php esc_html_e( 'Essential cookies keep the site working. Analytics is loaded only if you allow it.', 'agency-module-legal' ); ?><?php if ( $cookie_url ) : ?> <a href="<?php echo esc_url( $cookie_url ); ?>"><?php esc_html_e( 'Details', 'agency-module-legal' ); ?></a><?php endif; ?></p></div><div><button type="button" data-agency-consent="denied"><?php esc_html_e( 'Essential only', 'agency-module-legal' ); ?></button><button type="button" class="is-primary" data-agency-consent="granted"><?php esc_html_e( 'Allow analytics', 'agency-module-legal' ); ?></button></div></aside><?php
}
add_action( 'wp_footer', 'agency_legal_cookie_banner', 25 );
add_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'agency-module-legal', plugins_url( 'assets/css/legal.css', AGENCY_LEGAL_FILE ), array(), AGENCY_LEGAL_VERSION ); wp_enqueue_script( 'agency-module-legal', plugins_url( 'assets/js/legal.js', AGENCY_LEGAL_FILE ), array(), AGENCY_LEGAL_VERSION, true ); } );
