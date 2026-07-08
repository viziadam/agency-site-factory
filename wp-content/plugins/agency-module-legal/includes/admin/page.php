<?php
/**
 * Legal settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_legal_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-legal' ) );
	}
	$s = agency_legal_settings();
	?>
	<div class="wrap"><h1><?php esc_html_e( 'Legal Module', 'agency-module-legal' ); ?></h1>
	<?php if ( function_exists( 'agency_core_render_module_setup_summary' ) ) { agency_core_render_module_setup_summary( 'legal' ); } ?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="agency_core_module_setup"><input type="hidden" name="module" value="legal"><input type="hidden" name="setup_action" value="repair-display"><?php wp_nonce_field( 'agency_core_module_setup_legal' ); ?><button class="button"><?php esc_html_e( 'Repair footer legal links', 'agency-module-legal' ); ?></button></form>
	<p class="description"><?php esc_html_e( 'These are editable templates, not guaranteed legal advice. Have the final text reviewed by a qualified professional.', 'agency-module-legal' ); ?></p>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="agency_legal_save"><?php wp_nonce_field( 'agency_legal_save' ); ?>
	<h2><?php esc_html_e( 'Consent texts', 'agency-module-legal' ); ?></h2><table class="form-table">
	<tr><th><?php esc_html_e( 'Automatic setup', 'agency-module-legal' ); ?></th><td><label><input type="checkbox" name="auto_create_required_pages" value="1" <?php checked( $s['auto_create_required_pages'] ?? true ); ?>> Create missing pages</label><br><label><input type="checkbox" name="auto_add_footer_legal_links" value="1" <?php checked( $s['auto_add_footer_legal_links'] ); ?>> Add legal links to footer</label><br><label><input type="checkbox" name="cookie_banner" value="1" <?php checked( $s['cookie_banner'] ?? true ); ?>> Show analytics consent banner</label><br><label><input type="checkbox" name="create_imprint" value="1" <?php checked( $s['create_imprint'] ); ?>> Create optional imprint page</label><br><label>New page status <select name="page_status"><option value="publish" <?php selected( $s['page_status'], 'publish' ); ?>>Published</option><option value="draft" <?php selected( $s['page_status'], 'draft' ); ?>>Draft</option></select></label></td></tr>
	<?php foreach ( array( 'registration_text' => 'Registration', 'booking_text' => 'Booking', 'contact_text' => 'Contact', 'newsletter_text' => 'Newsletter' ) as $key => $label ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><input class="large-text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td></tr><?php endforeach; ?>
	</table><h2><?php esc_html_e( 'Page templates', 'agency-module-legal' ); ?></h2><table class="form-table">
	<?php foreach ( array( 'privacy_template' => 'Privacy', 'cookie_template' => 'Cookie', 'imprint_template' => 'Imprint' ) as $key => $label ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><textarea class="large-text code" rows="7" name="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $s[ $key ] ); ?></textarea></td></tr><?php endforeach; ?>
	</table><?php submit_button( __( 'Save settings', 'agency-module-legal' ), 'primary', 'save' ); ?> <?php submit_button( __( 'Save and generate missing pages', 'agency-module-legal' ), 'secondary', 'generate', false ); ?></form></div>
	<?php
}
