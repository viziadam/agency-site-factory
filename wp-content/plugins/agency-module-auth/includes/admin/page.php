<?php
/**
 * Auth wp-admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-auth' ) );
	}
	$s = agency_auth_settings();
	$users = get_users( array( 'role' => 'agency_customer', 'number' => 100 ) );
	$counts = array( 'pending' => 0, 'verified' => 0, 'blocked' => 0 );
	foreach ( $users as $customer ) {
		if ( get_user_meta( $customer->ID, '_agency_auth_blocked', true ) ) {
			++$counts['blocked'];
		} elseif ( agency_auth_is_verified( $customer->ID ) ) {
			++$counts['verified'];
		} else {
			++$counts['pending'];
		}
	}
	?>
	<div class="wrap agency-admin-auth"><h1><?php esc_html_e( 'Auth dashboard', 'agency-module-auth' ); ?></h1><div class="agency-admin-auth-stats"><p><strong><?php echo count( $users ); ?></strong> Customers</p><p><strong><?php echo absint( $counts['pending'] ); ?></strong> Pending</p><p><strong><?php echo absint( $counts['verified'] ); ?></strong> Verified</p><p><strong><?php echo absint( $counts['blocked'] ); ?></strong> Blocked</p></div><?php if ( function_exists( 'agency_core_render_module_setup_summary' ) ) { agency_core_render_module_setup_summary( 'auth' ); } ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_auth_save"><?php wp_nonce_field( 'agency_auth_save' ); ?><table class="form-table">
	<tr><th><?php esc_html_e( 'Registration', 'agency-module-auth' ); ?></th><td><label><input type="checkbox" name="registration_enabled" value="1" <?php checked( $s['registration_enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'agency-module-auth' ); ?></label></td></tr><tr><th><?php esc_html_e( 'Require verification', 'agency-module-auth' ); ?></th><td><input type="checkbox" name="require_verification" value="1" <?php checked( $s['require_verification'] ); ?>></td></tr>
	<tr><th><?php esc_html_e( 'Automatic setup', 'agency-module-auth' ); ?></th><td><label><input type="checkbox" name="auto_create_required_pages" value="1" <?php checked( $s['auto_create_required_pages'] ?? true ); ?>> <?php esc_html_e( 'Create required pages', 'agency-module-auth' ); ?></label><br><label><input type="checkbox" name="auto_add_auth_links" value="1" <?php checked( $s['auto_add_auth_links'] ?? true ); ?>> <?php esc_html_e( 'Add dynamic login/register/account links', 'agency-module-auth' ); ?></label><br><label><input type="checkbox" name="auto_add_pages_to_menu" value="1" <?php checked( $s['auto_add_pages_to_menu'] ?? false ); ?>> <?php esc_html_e( 'Add login page to primary menu', 'agency-module-auth' ); ?></label></td></tr>
	<tr><th><?php esc_html_e( 'Page assignments', 'agency-module-auth' ); ?></th><td><?php foreach ( array( 'login' => 'Login', 'register' => 'Registration', 'account' => 'Account', 'password' => 'Password reset', 'verification' => 'Verification' ) as $key => $label ) : ?><label><?php echo esc_html( $label ); ?> <?php wp_dropdown_pages( array( 'name' => 'page_ids[' . $key . ']', 'selected' => absint( $s['page_ids'][ $key ] ?? 0 ), 'show_option_none' => '— Select —' ) ); ?></label><br><?php endforeach; ?></td></tr>
	<tr><th><?php esc_html_e( 'Token lifetime (seconds)', 'agency-module-auth' ); ?></th><td><input type="number" min="900" name="token_lifetime" value="<?php echo esc_attr( $s['token_lifetime'] ); ?>"></td></tr><tr><th><?php esc_html_e( 'Login redirect', 'agency-module-auth' ); ?></th><td><input class="regular-text" type="url" name="login_redirect" value="<?php echo esc_attr( $s['login_redirect'] ); ?>"></td></tr>
	<tr><th><?php esc_html_e( 'Email subject', 'agency-module-auth' ); ?></th><td><input class="large-text" name="email_subject" value="<?php echo esc_attr( $s['email_subject'] ); ?>"></td></tr><tr><th><?php esc_html_e( 'Email body', 'agency-module-auth' ); ?></th><td><textarea class="large-text" rows="5" name="email_body"><?php echo esc_textarea( $s['email_body'] ); ?></textarea></td></tr></table><?php submit_button(); ?></form>
	<h2><?php esc_html_e( 'Customer status', 'agency-module-auth' ); ?></h2><table class="widefat striped"><thead><tr><th>Email</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if ( ! $users ) : ?><tr><td colspan="3"><?php esc_html_e( 'No customer accounts yet.', 'agency-module-auth' ); ?></td></tr><?php endif; foreach ( $users as $user ) : $blocked = get_user_meta( $user->ID, '_agency_auth_blocked', true ); ?><tr><td><?php echo esc_html( $user->user_email ); ?></td><td><?php echo $blocked ? esc_html__( 'Blocked', 'agency-module-auth' ) : ( agency_auth_is_verified( $user->ID ) ? esc_html__( 'Verified', 'agency-module-auth' ) : esc_html__( 'Pending verification', 'agency-module-auth' ) ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_auth_user_action"><input type="hidden" name="user_id" value="<?php echo absint( $user->ID ); ?>"><?php wp_nonce_field( 'agency_auth_user_' . $user->ID ); ?><?php if ( ! agency_auth_is_verified( $user->ID ) ) : ?><button class="button" name="user_action" value="resend">Resend verification</button><?php endif; ?> <button class="button" name="user_action" value="<?php echo $blocked ? 'unblock' : 'block'; ?>"><?php echo $blocked ? esc_html__( 'Unblock', 'agency-module-auth' ) : esc_html__( 'Block', 'agency-module-auth' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php
}
