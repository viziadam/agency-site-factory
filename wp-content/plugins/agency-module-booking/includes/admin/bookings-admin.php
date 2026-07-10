<?php
/**
 * Booking client administration and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_admin_menu() {
	add_submenu_page( 'agency-core-settings', __( 'Booking', 'agency-module-booking' ), __( 'Booking', 'agency-module-booking' ), 'manage_options', 'agency-module-booking', 'agency_booking_admin_page' );
	add_submenu_page( 'agency-core-settings', __( 'Booking Settings', 'agency-module-booking' ), __( 'Booking Settings', 'agency-module-booking' ), 'manage_options', 'agency-module-booking-settings', 'agency_booking_settings_page' );
}
add_action( 'admin_menu', 'agency_booking_admin_menu', 30 );

function agency_booking_admin_stats() {
	global $wpdb;

	if ( ! agency_booking_ensure_schema() ) {
		return array(
			'today'   => 0,
			'week'    => 0,
			'month'   => 0,
			'pending' => 0,
		);
	}

	$table = agency_booking_table();
	$now   = current_time( 'mysql', true );
	$today_local = new DateTimeImmutable( 'today', wp_timezone() );
	$tomorrow_local = $today_local->modify( '+1 day' );
	$today_start = gmdate( 'Y-m-d H:i:s', $today_local->getTimestamp() );
	$today_end   = gmdate( 'Y-m-d H:i:s', $tomorrow_local->getTimestamp() );
	$week  = gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS );
	$month = gmdate( 'Y-m-d H:i:s', time() + MONTH_IN_SECONDS );
	return array(
		'today'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE start_at >= %s AND start_at < %s", $today_start, $today_end ) ),
		'week'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE start_at >= %s AND start_at < %s", $now, $week ) ),
		'month'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE start_at >= %s AND start_at < %s", $now, $month ) ),
		'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='pending'" ),
	);
}

function agency_booking_admin_query() {
	global $wpdb;

	if ( ! agency_booking_ensure_schema() ) {
		return array();
	}

	$status = sanitize_key( wp_unslash( $_GET['booking_status'] ?? '' ) );
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$date   = sanitize_text_field( wp_unslash( $_GET['booking_date'] ?? '' ) );
	$where  = '1=1';
	$args   = array();
	if ( in_array( $status, array( 'pending', 'approved', 'rejected', 'cancelled', 'completed' ), true ) ) {
		$where .= ' AND status=%s'; $args[] = $status;
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		$local_start = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
		$local_end   = $local_start->modify( '+1 day' );
		$where      .= ' AND start_at >= %s AND start_at < %s';
		$args[]      = gmdate( 'Y-m-d H:i:s', $local_start->getTimestamp() );
		$args[]      = gmdate( 'Y-m-d H:i:s', $local_end->getTimestamp() );
	}
	if ( $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s)';
		array_push( $args, $like, $like, $like );
	}
	$sql = 'SELECT * FROM ' . agency_booking_table() . " WHERE {$where} ORDER BY start_at DESC LIMIT 300";
	return $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
}

function agency_booking_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-booking' ) );
	}
	$stats  = agency_booking_admin_stats();
	$rows   = agency_booking_admin_query();
	$status = sanitize_key( wp_unslash( $_GET['booking_status'] ?? '' ) );
	$date   = sanitize_text_field( wp_unslash( $_GET['booking_date'] ?? '' ) );
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	?>
	<div class="wrap agency-admin-booking">
		<div class="agency-admin-heading"><div><h1><?php esc_html_e( 'Booking dashboard', 'agency-module-booking' ); ?></h1><p><?php esc_html_e( 'Manage appointments, customer communication and availability.', 'agency-module-booking' ); ?></p></div><div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=agency-module-booking-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'agency-module-booking' ); ?></a> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agency_booking_export' ), 'agency_booking_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'agency-module-booking' ); ?></a></div></div>
		<?php if ( function_exists( 'agency_core_render_module_setup_summary' ) ) { agency_core_render_module_setup_summary( 'booking' ); } ?>
		<div class="agency-admin-stats"><?php foreach ( array( 'today' => __( 'Today', 'agency-module-booking' ), 'week' => __( 'Next 7 days', 'agency-module-booking' ), 'month' => __( 'Next 30 days', 'agency-module-booking' ), 'pending' => __( 'Pending', 'agency-module-booking' ) ) as $key => $label ) : ?><article><span><?php echo esc_html( $label ); ?></span><strong><?php echo absint( $stats[ $key ] ); ?></strong></article><?php endforeach; ?></div>
		<form class="agency-admin-filters" method="get"><input type="hidden" name="page" value="agency-module-booking"><select name="booking_status"><option value=""><?php esc_html_e( 'All statuses', 'agency-module-booking' ); ?></option><?php foreach ( array( 'pending', 'approved', 'rejected', 'cancelled', 'completed' ) as $item ) : ?><option value="<?php echo esc_attr( $item ); ?>" <?php selected( $status, $item ); ?>><?php echo esc_html( ucfirst( $item ) ); ?></option><?php endforeach; ?></select><input type="date" name="booking_date" value="<?php echo esc_attr( $date ); ?>"><input name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, email or phone', 'agency-module-booking' ); ?>"><button class="button button-primary"><?php esc_html_e( 'Filter', 'agency-module-booking' ); ?></button></form>
		<div class="agency-admin-calendar"><h2><?php esc_html_e( 'Upcoming calendar', 'agency-module-booking' ); ?></h2><div class="agency-admin-calendar-grid"><?php
		$upcoming = array_filter( $rows, static fn( $row ) => strtotime( $row->start_at . ' UTC' ) >= time() && in_array( $row->status, array( 'pending', 'approved' ), true ) );
		foreach ( array_slice( $upcoming, 0, 14 ) as $row ) : ?><article><time><?php echo esc_html( get_date_from_gmt( $row->start_at, 'D, M j · H:i' ) ); ?></time><strong><?php echo esc_html( $row->name ); ?></strong><span><?php echo esc_html( get_the_title( $row->service_id ) ); ?></span><em class="agency-admin-status agency-admin-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></em></article><?php endforeach; if ( ! $upcoming ) : ?><p><?php esc_html_e( 'No upcoming bookings.', 'agency-module-booking' ); ?></p><?php endif; ?></div></div>
		<h2><?php esc_html_e( 'Booking list', 'agency-module-booking' ); ?></h2>
		<div class="agency-admin-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Appointment', 'agency-module-booking' ); ?></th><th><?php esc_html_e( 'Customer', 'agency-module-booking' ); ?></th><th><?php esc_html_e( 'Details', 'agency-module-booking' ); ?></th><th><?php esc_html_e( 'Status and action', 'agency-module-booking' ); ?></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="4"><?php esc_html_e( 'No bookings found.', 'agency-module-booking' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?><tr><td><strong><?php echo esc_html( get_date_from_gmt( $row->start_at, get_option( 'date_format' ) ) ); ?></strong><br><?php echo esc_html( get_date_from_gmt( $row->start_at, get_option( 'time_format' ) ) ); ?><br><small>#<?php echo absint( $row->id ); ?></small></td><td><strong><?php echo esc_html( $row->name ); ?></strong><br><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a><br><?php echo esc_html( $row->phone ); ?></td><td><?php echo esc_html( get_the_title( $row->service_id ) ); ?><?php if ( $row->customer_note ) : ?><details><summary><?php esc_html_e( 'Customer note', 'agency-module-booking' ); ?></summary><p><?php echo esc_html( $row->customer_note ); ?></p></details><?php endif; ?></td><td><span class="agency-admin-status agency-admin-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_booking_status"><input type="hidden" name="booking_id" value="<?php echo absint( $row->id ); ?>"><?php wp_nonce_field( 'agency_booking_status_' . $row->id ); ?><textarea name="admin_note" rows="2" placeholder="<?php esc_attr_e( 'Admin note', 'agency-module-booking' ); ?>"><?php echo esc_textarea( $row->admin_note ); ?></textarea><div class="agency-admin-actions"><?php foreach ( array( 'approved' => 'Approve', 'rejected' => 'Reject', 'cancelled' => 'Cancel', 'completed' => 'Complete' ) as $value => $label ) : ?><button class="button <?php echo 'approved' === $value ? 'button-primary' : ''; ?>" name="booking_action" value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></button><?php endforeach; ?><button class="button" name="booking_action" value="resend"><?php esc_html_e( 'Resend email', 'agency-module-booking' ); ?></button></div></form></td></tr><?php endforeach; ?>
		</tbody></table></div>
	</div>
	<?php
}

function agency_booking_change_status() {
	if ( ! agency_booking_can_manage() ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-booking' ) );
	}
	$id     = absint( $_POST['booking_id'] ?? 0 );
	$action = sanitize_key( wp_unslash( $_POST['booking_action'] ?? '' ) );
	check_admin_referer( 'agency_booking_status_' . $id );
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . agency_booking_table() . ' WHERE id=%d', $id ) );
	if ( ! $row ) {
		wp_die( esc_html__( 'Booking not found.', 'agency-module-booking' ), 404 );
	}
	if ( 'resend' === $action ) {
		agency_booking_send_email( $row->status, $row );
	} elseif ( in_array( $action, array( 'approved', 'rejected', 'cancelled', 'completed' ), true ) ) {
		if ( 'approved' === $action ) {
			$conflict = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . agency_booking_table() . " WHERE start_at < %s AND end_at > %s AND status='approved' AND id<>%d", $row->end_at, $row->start_at, $row->id ) );
			if ( $conflict ) {
				wp_die( esc_html__( 'This slot already has an approved booking.', 'agency-module-booking' ), 409 );
			}
		}
		$wpdb->update(
			agency_booking_table(),
			array( 'status' => $action, 'admin_note' => sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ?? '' ) ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		$row->status = $action;
		agency_booking_send_email( $action, $row );
		if ( function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log( 'booking', 'status_changed', array( 'booking_id' => $id, 'status' => $action ) );
		}
	}
	$return = esc_url_raw( wp_unslash( $_POST['return_url'] ?? '' ) );
	wp_safe_redirect( $return ? add_query_arg( 'updated', 1, $return ) : admin_url( 'admin.php?page=agency-module-booking&updated=1' ) ); exit;
}
add_action( 'admin_post_agency_booking_status', 'agency_booking_change_status' );
add_action( 'agency_client_portal_action_agency_booking_status', 'agency_booking_change_status' );

function agency_booking_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-booking' ) );
	}
	$s = agency_booking_settings();
	?>
	<div class="wrap agency-admin-booking"><h1><?php esc_html_e( 'Booking settings', 'agency-module-booking' ); ?></h1><p><?php esc_html_e( 'Times are interpreted using the WordPress timezone:', 'agency-module-booking' ); ?> <code><?php echo esc_html( wp_timezone_string() ); ?></code></p>
	<div class="agency-admin-calendar"><h2><?php esc_html_e( 'Business portal access', 'agency-module-booking' ); ?></h2><p><?php esc_html_e( 'Create a restricted manager account. The user receives a password setup email and manages bookings on the frontend, without wp-admin access.', 'agency-module-booking' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="action" value="agency_booking_create_manager"><?php wp_nonce_field( 'agency_booking_create_manager' ); ?><input required type="text" name="manager_name" placeholder="<?php esc_attr_e( 'Manager name', 'agency-module-booking' ); ?>"><input required type="email" name="manager_email" placeholder="manager@example.com"><button class="button"><?php esc_html_e( 'Create manager and send invite', 'agency-module-booking' ); ?></button><?php $manager_url = function_exists( 'agency_core_client_admin_url' ) ? agency_core_client_admin_url() : ''; if ( $manager_url ) : ?><a class="button button-primary" href="<?php echo esc_url( $manager_url ); ?>" target="_blank"><?php esc_html_e( 'Open manager login', 'agency-module-booking' ); ?></a><?php endif; ?></form></div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_booking_save_settings"><?php wp_nonce_field( 'agency_booking_save_settings' ); ?>
		<h2><?php esc_html_e( 'Availability and access', 'agency-module-booking' ); ?></h2><table class="form-table">
		<tr><th><?php esc_html_e( 'Booking mode', 'agency-module-booking' ); ?></th><td><?php foreach ( array( 'booking_enabled' => 'Online booking enabled', 'guest_booking' => 'Guest booking enabled', 'login_required' => 'Login required', 'require_verified_email' => 'Verified email required', 'pending_blocks_slot' => 'Pending booking blocks its slot' ) as $key => $label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $s[ $key ] ); ?>> <?php echo esc_html( $label ); ?></label><br><?php endforeach; ?></td></tr>
		<tr><th><?php esc_html_e( 'Approval', 'agency-module-booking' ); ?></th><td><select name="confirmation_mode"><option value="manual" <?php selected( $s['confirmation_mode'], 'manual' ); ?>>Manual approval</option><option value="auto" <?php selected( $s['confirmation_mode'], 'auto' ); ?>>Auto approve</option></select></td></tr>
		<tr><th><?php esc_html_e( 'Slot duration', 'agency-module-booking' ); ?></th><td><select name="slot_duration"><?php foreach ( array( 15, 30, 45, 60, 90, 120 ) as $minutes ) : ?><option value="<?php echo absint( $minutes ); ?>" <?php selected( absint( $s['slot_duration'] ), $minutes ); ?>><?php echo absint( $minutes ); ?> min</option><?php endforeach; ?></select> <label>Custom <input type="number" min="5" max="1440" name="custom_slot_duration" value=""></label></td></tr>
		<tr><th><?php esc_html_e( 'Daily hours', 'agency-module-booking' ); ?></th><td><input type="time" name="day_start" value="<?php echo esc_attr( $s['day_start'] ); ?>"> – <input type="time" name="day_end" value="<?php echo esc_attr( $s['day_end'] ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Active weekdays', 'agency-module-booking' ); ?></th><td><?php $active = array_map( 'absint', explode( ',', $s['active_weekdays'] ) ); foreach ( array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun' ) as $day => $label ) : ?><label><input type="checkbox" name="active_weekdays[]" value="<?php echo absint( $day ); ?>" <?php checked( in_array( $day, $active, true ) ); ?>> <?php echo esc_html( $label ); ?></label> <?php endforeach; ?></td></tr>
		<tr><th><?php esc_html_e( 'Closed dates', 'agency-module-booking' ); ?></th><td><textarea class="large-text" rows="3" name="closed_dates"><?php echo esc_textarea( $s['closed_dates'] ); ?></textarea><p class="description">YYYY-MM-DD, comma or newline separated.</p></td></tr>
		<tr><th><?php esc_html_e( 'Special open dates', 'agency-module-booking' ); ?></th><td><textarea class="large-text" rows="3" name="special_open_dates"><?php echo esc_textarea( $s['special_open_dates'] ); ?></textarea><p class="description">Overrides weekday and closed-date rules.</p></td></tr>
		</table>
		<h2><?php esc_html_e( 'Display and pages', 'agency-module-booking' ); ?></h2><table class="form-table"><tr><th><?php esc_html_e( 'Automatic display', 'agency-module-booking' ); ?></th><td><?php foreach ( array( 'auto_create_required_pages' => 'Create required pages', 'auto_add_pages_to_menu' => 'Add booking page to primary menu', 'auto_add_booking_cta' => 'Add or repair homepage booking CTA' ) as $key => $label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $s[ $key ] ); ?>> <?php echo esc_html( $label ); ?></label><br><?php endforeach; ?></td></tr><tr><th>Thank-you URL</th><td><input class="regular-text" type="url" name="thank_you_url" value="<?php echo esc_attr( $s['thank_you_url'] ); ?>"></td></tr></table>
		<h2><?php esc_html_e( 'Email and GDPR', 'agency-module-booking' ); ?></h2><table class="form-table"><tr><th>Admin email</th><td><input class="regular-text" type="email" name="admin_email" value="<?php echo esc_attr( $s['admin_email'] ); ?>"></td></tr><tr><th>Reply-to override</th><td><input class="regular-text" type="email" name="reply_to" value="<?php echo esc_attr( $s['reply_to'] ); ?>"></td></tr><tr><th>GDPR text override</th><td><input class="large-text" name="gdpr_text" value="<?php echo esc_attr( $s['gdpr_text'] ); ?>"><p class="description">Leave empty to use Agency Legal.</p></td></tr>
		<?php foreach ( array( 'email_received' => 'Booking received', 'email_admin' => 'Admin notification', 'email_approved' => 'Approved', 'email_rejected' => 'Rejected', 'email_cancelled' => 'Cancelled', 'email_completed' => 'Completed' ) as $key => $label ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><textarea class="large-text code" rows="4" name="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $s[ $key ] ); ?></textarea></td></tr><?php endforeach; ?></table>
		<?php submit_button( __( 'Save booking settings', 'agency-module-booking' ) ); ?>
	</form></div>
	<?php
}

function agency_booking_save_settings() {
	if ( ! agency_booking_can_manage() ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-booking' ) );
	}
	check_admin_referer( 'agency_booking_save_settings' );
	$old = agency_booking_settings();
	$is_portal = ! empty( $_POST['return_url'] );
	foreach ( array( 'booking_enabled', 'guest_booking', 'login_required', 'require_verified_email', 'pending_blocks_slot', 'auto_create_required_pages', 'auto_add_pages_to_menu', 'auto_add_booking_cta' ) as $key ) {
		if ( ! $is_portal || array_key_exists( $key, $_POST ) ) {
			$old[ $key ] = ! empty( $_POST[ $key ] );
		}
	}
	if ( isset( $_POST['confirmation_mode'] ) ) {
		$old['confirmation_mode'] = 'auto' === $_POST['confirmation_mode'] ? 'auto' : 'manual';
	}
	if ( isset( $_POST['slot_duration'] ) ) {
		$custom = absint( $_POST['custom_slot_duration'] ?? 0 );
		$old['slot_duration'] = max( 5, min( 1440, $custom ?: absint( $_POST['slot_duration'] ) ) );
	}
	foreach ( array( 'day_start', 'day_end' ) as $key ) {
		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		$old[ $key ] = preg_match( '/^\d{2}:\d{2}$/', $value ) ? $value : agency_booking_defaults()[ $key ];
	}
	$weekdays = array_values( array_intersect( range( 0, 6 ), array_map( 'absint', (array) ( $_POST['active_weekdays'] ?? array() ) ) ) );
	$old['active_weekdays'] = implode( ',', $weekdays );
	$old['closed_dates'] = implode( "\n", agency_booking_date_list( wp_unslash( $_POST['closed_dates'] ?? '' ) ) );
	$old['special_open_dates'] = implode( "\n", agency_booking_date_list( wp_unslash( $_POST['special_open_dates'] ?? '' ) ) );
	foreach ( array( 'admin_email', 'reply_to' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			$old[ $key ] = sanitize_email( wp_unslash( $_POST[ $key ] ) );
		}
	}
	if ( isset( $_POST['gdpr_text'] ) ) {
		$old['gdpr_text'] = sanitize_text_field( wp_unslash( $_POST['gdpr_text'] ) );
	}
	if ( isset( $_POST['thank_you_url'] ) ) {
		$old['thank_you_url'] = esc_url_raw( wp_unslash( $_POST['thank_you_url'] ) );
	}
	foreach ( array( 'email_received', 'email_admin', 'email_approved', 'email_rejected', 'email_cancelled', 'email_completed' ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			$old[ $key ] = wp_kses_post( wp_unslash( $_POST[ $key ] ) );
		}
	}
	update_option( 'agency_module_booking_settings', $old, false );
	if ( function_exists( 'agency_core_apply_module_setup' ) ) {
		agency_core_apply_module_setup( 'booking' );
	}
	$return = esc_url_raw( wp_unslash( $_POST['return_url'] ?? '' ) );
	wp_safe_redirect( $return ? add_query_arg( array( 'portal_tab' => 'calendar', 'updated' => 1 ), $return ) : admin_url( 'admin.php?page=agency-module-booking-settings&updated=1' ) ); exit;
}
add_action( 'admin_post_agency_booking_save_settings', 'agency_booking_save_settings' );
add_action( 'agency_client_portal_action_agency_booking_save_settings', 'agency_booking_save_settings' );

function agency_booking_create_manager() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-booking' ) );
	}
	check_admin_referer( 'agency_booking_create_manager' );
	$email = sanitize_email( wp_unslash( $_POST['manager_email'] ?? '' ) );
	$name  = sanitize_text_field( wp_unslash( $_POST['manager_name'] ?? '' ) );
	if ( ! is_email( $email ) || email_exists( $email ) ) {
		wp_safe_redirect( add_query_arg( 'manager', 'invalid', admin_url( 'admin.php?page=agency-module-booking-settings' ) ) );
		exit;
	}
	$base     = sanitize_user( strstr( $email, '@', true ), true ) ?: 'booking-manager';
	$username = $base;
	for ( $index = 2; username_exists( $username ); $index++ ) {
		$username = $base . $index;
	}
	$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'display_name' => $name ?: $username, 'user_pass' => wp_generate_password( 32 ), 'role' => 'agency_booking_manager' ) );
	if ( ! is_wp_error( $user_id ) ) {
		retrieve_password( $username );
		if ( function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log( 'booking', 'manager_created', array( 'user_id' => $user_id ) );
		}
	}
	wp_safe_redirect( add_query_arg( 'manager', is_wp_error( $user_id ) ? 'error' : 'created', admin_url( 'admin.php?page=agency-module-booking-settings' ) ) );
	exit;
}
add_action( 'admin_post_agency_booking_create_manager', 'agency_booking_create_manager' );

function agency_booking_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-booking' ) );
	}
	check_admin_referer( 'agency_booking_export' );
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . agency_booking_table() . ' ORDER BY start_at DESC', ARRAY_A );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="agency-bookings-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$output = fopen( 'php://output', 'w' );
	fputs( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'ID', 'Start UTC', 'End UTC', 'Status', 'Service', 'Name', 'Email', 'Phone', 'Customer note', 'Admin note' ) );
	foreach ( $rows as $row ) {
		$values = array( $row['id'], $row['start_at'], $row['end_at'], $row['status'], get_the_title( $row['service_id'] ), $row['name'], $row['email'], $row['phone'], $row['customer_note'], $row['admin_note'] );
		$values = array_map( static fn( $value ) => preg_match( '/^[=+\-@]/', (string) $value ) ? "'" . $value : $value, $values );
		fputcsv( $output, $values );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_agency_booking_export', 'agency_booking_export_csv' );
