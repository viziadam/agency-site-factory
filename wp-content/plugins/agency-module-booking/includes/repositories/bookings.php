<?php
/**
 * Booking persistence, settings and availability rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_table() {
	global $wpdb;
	return $wpdb->prefix . 'agency_bookings';
}

function agency_booking_defaults() {
	return array(
		'booking_enabled'      => 1,
		'guest_booking'        => 1,
		'login_required'       => 0,
		'require_verified_email'=> 1,
		'confirmation_mode'    => 'manual',
		'pending_blocks_slot'  => 1,
		'slot_duration'        => 30,
		'day_start'            => '09:00',
		'day_end'              => '17:00',
		'active_weekdays'      => '1,2,3,4,5',
		'closed_dates'         => '',
		'special_open_dates'   => '',
		'admin_email'          => get_option( 'admin_email' ),
		'reply_to'             => '',
		'gdpr_text'            => '',
		'thank_you_url'        => '',
		'page_ids'             => array(),
		'auto_create_required_pages' => 1,
		'auto_add_pages_to_menu'     => 1,
		'auto_add_booking_cta'       => 1,
		'email_received_subject'     => __( 'Booking request #{{booking_id}} received', 'agency-module-booking' ),
		'email_received'       => __( '<p>We received your booking request for {{date}} at {{time}}.</p><p>Status: {{status}}</p>', 'agency-module-booking' ),
		'email_admin_subject'  => __( 'New booking request #{{booking_id}}', 'agency-module-booking' ),
		'email_admin'          => __( '<p>{{name}} requested {{service}} for {{date}} at {{time}}.</p><p>{{email}} · {{phone}}</p>', 'agency-module-booking' ),
		'email_approved'       => __( '<p>Your booking for {{date}} at {{time}} has been approved.</p>', 'agency-module-booking' ),
		'email_rejected'       => __( '<p>Your booking request for {{date}} at {{time}} was rejected.</p>', 'agency-module-booking' ),
		'email_cancelled'      => __( '<p>Your booking for {{date}} at {{time}} was cancelled.</p>', 'agency-module-booking' ),
		'email_completed'      => __( '<p>Your booking for {{date}} at {{time}} is complete. Thank you.</p>', 'agency-module-booking' ),
		'manager_page_id'      => 0,
		'manager_login_page_id'=> 0,
	);
}

function agency_booking_settings() {
	$stored   = (array) get_option( 'agency_module_booking_settings', array() );
	$settings = wp_parse_args( $stored, agency_booking_defaults() );
	// Compatibility with 1.7 setting names.
	if ( ! array_key_exists( 'day_start', $stored ) && isset( $stored['business_hours'] ) && preg_match( '/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $stored['business_hours'], $matches ) ) {
		$settings['day_start'] = $matches[1];
		$settings['day_end']   = $matches[2];
	}
	if ( ! array_key_exists( 'active_weekdays', $stored ) && isset( $stored['closed_days'] ) ) {
		$closed = array_map( 'absint', explode( ',', $stored['closed_days'] ) );
		$settings['active_weekdays'] = implode( ',', array_values( array_diff( range( 0, 6 ), $closed ) ) );
	}
	return $settings;
}

function agency_booking_migrate() {
	$installed = (string) get_option( 'agency_module_booking_db_version', '0' );
	if ( version_compare( $installed, AGENCY_BOOKING_DB_VERSION, '>=' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = agency_booking_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		service_id bigint(20) unsigned NOT NULL DEFAULT 0,
		start_at datetime NOT NULL,
		end_at datetime NULL,
		name varchar(190) NOT NULL,
		email varchar(190) NOT NULL,
		phone varchar(80) NOT NULL DEFAULT '',
		customer_note text NULL,
		admin_note text NULL,
		status varchar(24) NOT NULL DEFAULT 'pending',
		verification_hash varchar(255) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY start_at (start_at),
		KEY status (status),
		KEY email (email),
		KEY user_id (user_id),
		KEY service_id (service_id),
		KEY service_start (service_id,start_at),
		KEY status_start (status,start_at)
	) {$charset};" );
	if ( version_compare( $installed, '1.1.0', '<' ) ) {
		$wpdb->query( "UPDATE {$table} SET end_at = DATE_ADD(start_at, INTERVAL 30 MINUTE) WHERE end_at = '0000-00-00 00:00:00' OR end_at IS NULL" );
	}
	update_option( 'agency_module_booking_db_version', AGENCY_BOOKING_DB_VERSION, false );
	update_option( 'agency_booking_settings_18_migrated', 1, false );
	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'booking', 'migration', array( 'from' => $installed, 'to' => AGENCY_BOOKING_DB_VERSION ) );
	}
}
add_action( 'init', 'agency_booking_migrate', 6 );
add_action( 'admin_init', 'agency_booking_migrate' );

function agency_booking_activate() {
	agency_booking_migrate();
	agency_booking_register_manager_role();
	update_option( 'agency_booking_setup_pending', 1, false );
}

function agency_booking_register_manager_role() {
	$capabilities = array( 'read' => true, 'manage_agency_bookings' => true );
	$role = get_role( 'agency_booking_manager' );
	if ( ! $role ) {
		$role = add_role( 'agency_booking_manager', __( 'Booking Manager', 'agency-module-booking' ), $capabilities );
	}
	if ( $role ) {
		$role->add_cap( 'manage_agency_bookings' );
	}
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'manage_agency_bookings' );
	}
}
add_action( 'init', 'agency_booking_register_manager_role', 7 );

function agency_booking_can_manage() {
	return current_user_can( 'manage_options' ) || current_user_can( 'manage_agency_bookings' );
}

function agency_booking_service_duration( $service_id ) {
	$duration = absint( get_post_meta( $service_id, '_agency_booking_duration', true ) );
	return $duration ?: absint( agency_booking_settings()['slot_duration'] );
}

function agency_booking_service_price( $service_id ) {
	$price    = get_post_meta( $service_id, '_agency_price', true );
	$currency = get_post_meta( $service_id, '_agency_price_currency', true ) ?: 'HUF';
	return array( 'value' => is_numeric( $price ) ? (float) $price : 0, 'currency' => sanitize_text_field( $currency ) );
}

function agency_booking_service_meta_box() {
	add_meta_box( 'agency-booking-service-details', __( 'Booking details', 'agency-module-booking' ), 'agency_booking_render_service_meta_box', 'agency_service', 'side' );
}
add_action( 'add_meta_boxes', 'agency_booking_service_meta_box' );

function agency_booking_render_service_meta_box( $post ) {
	wp_nonce_field( 'agency_booking_service_meta', 'agency_booking_service_nonce' );
	?><p><label for="agency-booking-duration"><?php esc_html_e( 'Duration in minutes', 'agency-module-booking' ); ?></label><input class="widefat" id="agency-booking-duration" type="number" min="5" max="1440" name="agency_booking_duration" value="<?php echo esc_attr( agency_booking_service_duration( $post->ID ) ); ?>"></p><?php
}

function agency_booking_save_service_meta( $post_id ) {
	if ( ! isset( $_POST['agency_booking_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agency_booking_service_nonce'] ) ), 'agency_booking_service_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_agency_booking_duration', max( 5, min( 1440, absint( $_POST['agency_booking_duration'] ?? 30 ) ) ) );
}
add_action( 'save_post_agency_service', 'agency_booking_save_service_meta' );

function agency_booking_maybe_apply_setup() {
	if ( get_option( 'agency_booking_setup_pending' ) && function_exists( 'agency_core_apply_module_setup' ) ) {
		delete_option( 'agency_booking_setup_pending' );
		agency_core_apply_module_setup( 'booking' );
	}
}
add_action( 'admin_init', 'agency_booking_maybe_apply_setup', 20 );

function agency_booking_date_list( $value ) {
	$dates = preg_split( '/[\s,;]+/', (string) $value );
	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_text_field', $dates ),
				static fn( $date ) => 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date )
			)
		)
	);
}

function agency_booking_is_open_date( $date, $settings = null ) {
	$settings = $settings ?: agency_booking_settings();
	if ( in_array( $date, agency_booking_date_list( $settings['special_open_dates'] ), true ) ) {
		return true;
	}
	if ( in_array( $date, agency_booking_date_list( $settings['closed_dates'] ), true ) ) {
		return false;
	}
	$local = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	if ( ! $local ) {
		return false;
	}
	$active = array_map( 'absint', explode( ',', $settings['active_weekdays'] ) );
	return in_array( (int) $local->format( 'w' ), $active, true );
}

function agency_booking_blocking_statuses( $settings = null ) {
	$settings = $settings ?: agency_booking_settings();
	return ! empty( $settings['pending_blocks_slot'] ) ? array( 'pending', 'approved' ) : array( 'approved' );
}

function agency_booking_get_blocked_intervals( $date, $service_id, $settings = null ) {
	global $wpdb;
	$settings = $settings ?: agency_booking_settings();
	$start    = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
	$end      = $start->modify( '+1 day' );
	$statuses = agency_booking_blocking_statuses( $settings );
	$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$sql = $wpdb->prepare(
		'SELECT start_at,end_at FROM ' . agency_booking_table() . " WHERE start_at < %s AND end_at > %s AND status IN ({$in})",
		array_merge( array( gmdate( 'Y-m-d H:i:s', $end->getTimestamp() ), gmdate( 'Y-m-d H:i:s', $start->getTimestamp() ) ), $statuses )
	);
	return $wpdb->get_results( $sql );
}

function agency_booking_get_blocked_starts( $date, $service_id, $settings = null ) {
	return array_map( static fn( $row ) => (string) $row->start_at, agency_booking_get_blocked_intervals( $date, $service_id, $settings ) );
}

function agency_booking_get_available_slots( $date, $service_id = 0, $settings = null ) {
	$settings   = $settings ?: agency_booking_settings();
	$service_id = absint( $service_id );
	if ( empty( $settings['booking_enabled'] ) || ! agency_booking_is_open_date( $date, $settings ) ) {
		return array();
	}
	if ( ! preg_match( '/^\d{2}:\d{2}$/', $settings['day_start'] ) || ! preg_match( '/^\d{2}:\d{2}$/', $settings['day_end'] ) ) {
		return array();
	}
	$duration = $service_id ? agency_booking_service_duration( $service_id ) : max( 5, min( 1440, absint( $settings['slot_duration'] ) ) );
	$cursor   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $settings['day_start'], wp_timezone() );
	$closing  = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $settings['day_end'], wp_timezone() );
	if ( ! $cursor || ! $closing || $cursor >= $closing ) {
		return array();
	}
	$blocked = agency_booking_get_blocked_intervals( $date, $service_id, $settings );
	$slots   = array();
	$now     = new DateTimeImmutable( 'now', wp_timezone() );
	while ( $cursor->modify( "+{$duration} minutes" ) <= $closing ) {
		$utc       = gmdate( 'Y-m-d H:i:s', $cursor->getTimestamp() );
		$slot_end  = gmdate( 'Y-m-d H:i:s', $cursor->modify( "+{$duration} minutes" )->getTimestamp() );
		$overlaps  = array_filter( $blocked, static fn( $row ) => $utc < $row->end_at && $slot_end > $row->start_at );
		if ( $cursor > $now && ! $overlaps ) {
			$slots[] = array(
				'value' => $cursor->format( 'Y-m-d\TH:i' ),
				'label' => $cursor->format( get_option( 'time_format', 'H:i' ) ),
				'utc'   => $utc,
			);
		}
		$cursor = $cursor->modify( "+{$duration} minutes" );
	}
	return $slots;
}

function agency_booking_is_slot_available( DateTimeImmutable $local_date, $service_id, $settings = null ) {
	$value = $local_date->format( 'Y-m-d\TH:i' );
	foreach ( agency_booking_get_available_slots( $local_date->format( 'Y-m-d' ), $service_id, $settings ) as $slot ) {
		if ( $slot['value'] === $value ) {
			return true;
		}
	}
	return false;
}

function agency_booking_email_tokens( $booking ) {
	$local = new DateTimeImmutable( $booking->start_at, new DateTimeZone( 'UTC' ) );
	$local = $local->setTimezone( wp_timezone() );
	return array(
		'{{booking_id}}' => (string) $booking->id,
		'{{name}}'       => $booking->name,
		'{{email}}'      => $booking->email,
		'{{phone}}'      => $booking->phone,
		'{{service}}'    => get_the_title( $booking->service_id ),
		'{{date}}'       => wp_date( get_option( 'date_format' ), $local->getTimestamp(), wp_timezone() ),
		'{{time}}'       => wp_date( get_option( 'time_format' ), $local->getTimestamp(), wp_timezone() ),
		'{{status}}'     => $booking->status,
		'{{site_name}}'  => get_bloginfo( 'name' ),
	);
}

function agency_booking_render_email( $template, $booking ) {
	return strtr( (string) $template, array_map( 'esc_html', agency_booking_email_tokens( $booking ) ) );
}

function agency_booking_send_email( $type, $booking, $recipient = '', $append_html = '' ) {
	$settings  = agency_booking_settings();
	if ( 'pending' === $type ) {
		$type = 'received';
	}
	$recipient = $recipient ?: ( 'admin' === $type ? $settings['admin_email'] : $booking->email );
	$subject   = 'admin' === $type ? $settings['email_admin_subject'] : ( 'received' === $type ? $settings['email_received_subject'] : sprintf( __( 'Booking #%1$d: %2$s', 'agency-module-booking' ), $booking->id, $type ) );
	$template  = ( 'admin' === $type ? $settings['email_admin'] : ( 'received' === $type ? $settings['email_received'] : ( $settings[ 'email_' . $type ] ?? '' ) ) ) . $append_html;
	if ( ! $recipient || ! $template ) {
		return new WP_Error( 'agency_booking_email_config', __( 'Email recipient or template is missing.', 'agency-module-booking' ) );
	}
	$result = function_exists( 'agency_core_email_send' )
		? agency_core_email_send( $recipient, agency_booking_render_email( $subject, $booking ), agency_booking_render_email( $template, $booking ), is_email( $settings['reply_to'] ) ? array( 'Reply-To: ' . $settings['reply_to'] ) : array() )
		: wp_mail( $recipient, agency_booking_render_email( $subject, $booking ), agency_booking_render_email( $template, $booking ) );
	if ( is_wp_error( $result ) && function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'booking', 'email_failed', array( 'booking_id' => $booking->id, 'type' => $type, 'error' => $result->get_error_message() ) );
	}
	return $result;
}
