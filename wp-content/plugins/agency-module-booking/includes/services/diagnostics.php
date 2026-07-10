<?php
/**
 * Booking diagnostics, self-repair helpers and hardened submit handling.
 *
 * These helpers make the booking module easier to debug in local and production
 * environments. They also make booking persistence independent from email
 * delivery, so a Brevo/wp_mail issue cannot prevent the database insert.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_debug_snapshot() {
	global $wpdb;

	$table_exists = function_exists( 'agency_booking_table_exists' ) && agency_booking_table_exists();
	$table        = function_exists( 'agency_booking_table' ) ? agency_booking_table() : '';
	$total        = 0;
	$latest       = null;

	if ( $table_exists && $table ) {
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$latest = $wpdb->get_row( "SELECT id,status,email,start_at,created_at FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A );
	}

	return array(
		'table'      => $table,
		'exists'     => $table_exists,
		'db_version' => (string) get_option( 'agency_module_booking_db_version', '0' ),
		'total'      => $total,
		'latest'     => $latest,
		'last_error' => $wpdb->last_error,
	);
}

function agency_booking_repair_schema_if_missing() {
	if ( ! function_exists( 'agency_booking_table_exists' ) || ! function_exists( 'agency_booking_migrate' ) ) {
		return;
	}

	if ( agency_booking_table_exists() ) {
		return;
	}

	update_option( 'agency_module_booking_db_version', '0', false );
	agency_booking_migrate();

	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log(
			'booking',
			'schema_repair',
			array(
				'table'  => agency_booking_table(),
				'exists' => agency_booking_table_exists() ? 1 : 0,
			)
		);
	}
}
add_action( 'init', 'agency_booking_repair_schema_if_missing', 5 );
add_action( 'admin_init', 'agency_booking_repair_schema_if_missing', 5 );

function agency_booking_log_submit_event( $event, $context = array() ) {
	if ( ! function_exists( 'agency_core_audit_log' ) ) {
		return;
	}

	$context = (array) $context;
	unset( $context['token'], $context['password'], $context['api_key'] );

	agency_core_audit_log( 'booking', $event, $context );
}

function agency_booking_submit_context() {
	return array(
		'user_id'    => get_current_user_id(),
		'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'service_id' => absint( $_POST['service_id'] ?? 0 ),
		'start_at'   => sanitize_text_field( wp_unslash( $_POST['start_at'] ?? '' ) ),
		'name_set'   => ! empty( $_POST['name'] ) ? 1 : 0,
		'phone_set'  => ! empty( $_POST['phone'] ) ? 1 : 0,
		'privacy'    => ! empty( $_POST['privacy'] ) ? 1 : 0,
		'referer'    => wp_get_referer() ?: '',
	);
}

function agency_booking_redirect_submit_error( $redirect, $reason, $context = array() ) {
	agency_booking_log_submit_event(
		'create_rejected',
		array_merge(
			agency_booking_submit_context(),
			(array) $context,
			array( 'reason' => sanitize_key( $reason ) )
		)
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'booking'        => 'error',
				'booking_reason' => sanitize_key( $reason ),
			),
			$redirect
		)
	);
	exit;
}

function agency_booking_parse_local_datetime( $value ) {
	$value = sanitize_text_field( (string) $value );
	$date  = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );

	if ( ! $date ) {
		return false;
	}

	$errors = DateTimeImmutable::getLastErrors();
	if ( is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) ) {
		return false;
	}

	return $date;
}

/**
 * Hardened booking create handler.
 *
 * This runs before the legacy handler and exits after processing. It keeps the
 * original public form action name, but makes the submit path observable and
 * avoids losing bookings when email delivery or MySQL named locks fail.
 */
function agency_booking_create_hardened() {
	$redirect = wp_get_referer() ?: home_url( '/' );

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'agency_booking_create' ) ) {
		agency_booking_redirect_submit_error( $redirect, 'invalid_nonce' );
	}

	agency_booking_log_submit_event( 'create_received', agency_booking_submit_context() );

	$settings = agency_booking_settings();

	if ( empty( $settings['booking_enabled'] ) ) {
		agency_booking_redirect_submit_error( $redirect, 'booking_disabled' );
	}

	if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'booking_create', 30, HOUR_IN_SECONDS ) ) {
		agency_booking_redirect_submit_error( $redirect, 'rate_limited' );
	}

	if ( ! empty( $settings['login_required'] ) && ! is_user_logged_in() ) {
		agency_booking_redirect_submit_error( $redirect, 'login_required' );
	}

	if ( ! empty( $settings['require_verified_email'] ) && is_user_logged_in() && function_exists( 'agency_auth_is_verified' ) && ! agency_auth_is_verified( get_current_user_id() ) ) {
		agency_booking_redirect_submit_error( $redirect, 'email_not_verified' );
	}

	if ( empty( $settings['guest_booking'] ) && ! is_user_logged_in() ) {
		agency_booking_redirect_submit_error( $redirect, 'guest_booking_disabled' );
	}

	$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$service_id = absint( $_POST['service_id'] ?? 0 );
	$start_raw  = sanitize_text_field( wp_unslash( $_POST['start_at'] ?? '' ) );
	$local_date = agency_booking_parse_local_datetime( $start_raw );
	$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

	if ( ! $email ) {
		agency_booking_redirect_submit_error( $redirect, 'missing_email' );
	}

	if ( ! $service_id || 'agency_service' !== get_post_type( $service_id ) ) {
		agency_booking_redirect_submit_error( $redirect, 'invalid_service', array( 'post_type' => $service_id ? get_post_type( $service_id ) : '' ) );
	}

	if ( ! $local_date ) {
		agency_booking_redirect_submit_error( $redirect, 'invalid_start_at', array( 'raw_start_at' => $start_raw ) );
	}

	if ( ! $name ) {
		agency_booking_redirect_submit_error( $redirect, 'missing_name' );
	}

	if ( ! $phone ) {
		agency_booking_redirect_submit_error( $redirect, 'missing_phone' );
	}

	if ( empty( $_POST['privacy'] ) ) {
		agency_booking_redirect_submit_error( $redirect, 'missing_privacy' );
	}

	if ( ! agency_booking_is_slot_available( $local_date, $service_id, $settings ) ) {
		agency_booking_redirect_submit_error(
			$redirect,
			'slot_unavailable_before_insert',
			array(
				'available_slots_for_day' => count( agency_booking_get_available_slots( $local_date->format( 'Y-m-d' ), $service_id, $settings ) ),
			)
		);
	}

	global $wpdb;

	$duration  = agency_booking_service_duration( $service_id );
	$start_at  = gmdate( 'Y-m-d H:i:s', $local_date->getTimestamp() );
	$end_at    = gmdate( 'Y-m-d H:i:s', $local_date->modify( "+{$duration} minutes" )->getTimestamp() );
	$lock_name = 'agency_booking_day_' . md5( $local_date->format( 'Y-m-d' ) );
	$locked    = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock_name ) );

	if ( '1' !== (string) $locked ) {
		agency_booking_log_submit_event(
			'lock_unavailable_continuing',
			array_merge(
				agency_booking_submit_context(),
				array(
					'lock_result' => is_null( $locked ) ? 'null' : (string) $locked,
				)
			)
		);
	}

	if ( ! agency_booking_is_slot_available( $local_date, $service_id, $settings ) ) {
		if ( '1' === (string) $locked ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}

		agency_booking_redirect_submit_error( $redirect, 'slot_unavailable_after_lock' );
	}

	$status      = 'auto' === $settings['confirmation_mode'] && is_user_logged_in() ? 'approved' : 'pending';
	$guest_token = is_user_logged_in() ? '' : bin2hex( random_bytes( 24 ) );
	$now         = current_time( 'mysql', true );

	$booking = agency_booking_insert(
		array(
			'user_id'           => get_current_user_id(),
			'service_id'        => $service_id,
			'start_at'          => $start_at,
			'end_at'            => $end_at,
			'name'              => $name,
			'email'             => $email,
			'phone'             => $phone,
			'customer_note'     => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
			'admin_note'        => '',
			'status'            => $status,
			'verification_hash' => $guest_token ? wp_hash_password( $guest_token ) : '',
			'created_at'        => $now,
			'updated_at'        => $now,
		)
	);

	if ( '1' === (string) $locked ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}

	if ( is_wp_error( $booking ) || ! $booking ) {
		agency_booking_redirect_submit_error(
			$redirect,
			'insert_failed',
			array(
				'error'         => is_wp_error( $booking ) ? $booking->get_error_message() : 'empty_booking_row',
				'wpdb_error'    => $wpdb->last_error,
				'booking_table' => agency_booking_table(),
			)
		);
	}

	agency_booking_log_submit_event(
		'created',
		array(
			'booking_id' => absint( $booking->id ),
			'status'     => $status,
			'email'      => $email,
			'service_id' => $service_id,
			'start_at'   => $start_at,
		)
	);

	$verification_message = '';
	if ( $guest_token ) {
		$verify_url = add_query_arg( array( 'agency_booking_verify' => $booking->id, 'token' => rawurlencode( $guest_token ) ), home_url( '/' ) );
		$verification_message = '<p><a href="' . esc_url( $verify_url ) . '">' . esc_html__( 'Verify this guest booking', 'agency-module-booking' ) . '</a></p>';
	}

	$email_results = array(
		'received' => agency_booking_send_email( 'received', $booking, '', $verification_message ),
		'admin'    => agency_booking_send_email( 'admin', $booking ),
	);

	if ( 'approved' === $status ) {
		$email_results['approved'] = agency_booking_send_email( 'approved', $booking );
	}

	foreach ( $email_results as $type => $result ) {
		if ( is_wp_error( $result ) ) {
			agency_booking_log_submit_event(
				'email_failed_after_insert',
				array(
					'booking_id' => absint( $booking->id ),
					'type'       => $type,
					'error'      => $result->get_error_message(),
				)
			);
		}
	}

	if ( function_exists( 'agency_core_record_event' ) ) {
		agency_core_record_event( 'conversion', 'booking_completed', wp_parse_url( $redirect, PHP_URL_PATH ) ?: '/idopontfoglalas/', get_the_title( $service_id ), array( 'service_id' => $service_id ) );
	}

	$thank_you = $settings['thank_you_url'] ?: $redirect;
	wp_safe_redirect( add_query_arg( 'booking', 'received', $thank_you ) );
	exit;
}
add_action( 'admin_post_nopriv_agency_booking_create', 'agency_booking_create_hardened', 1 );
add_action( 'admin_post_agency_booking_create', 'agency_booking_create_hardened', 1 );

function agency_booking_admin_debug_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	if ( ! in_array( $page, array( 'agency-module-booking', 'agency-module-booking-settings' ), true ) ) {
		return;
	}

	$snapshot = agency_booking_debug_snapshot();
	$class    = $snapshot['exists'] ? 'notice-info' : 'notice-error';
	?>
	<div class="notice <?php echo esc_attr( $class ); ?> inline">
		<p>
			<strong><?php esc_html_e( 'Booking database status:', 'agency-module-booking' ); ?></strong>
			<?php echo esc_html( $snapshot['exists'] ? __( 'table exists', 'agency-module-booking' ) : __( 'table is missing', 'agency-module-booking' ) ); ?>
			· <code><?php echo esc_html( $snapshot['table'] ); ?></code>
			· <?php esc_html_e( 'DB version:', 'agency-module-booking' ); ?> <code><?php echo esc_html( $snapshot['db_version'] ); ?></code>
			· <?php esc_html_e( 'Rows:', 'agency-module-booking' ); ?> <code><?php echo absint( $snapshot['total'] ); ?></code>
		</p>
		<?php if ( ! empty( $snapshot['latest'] ) ) : ?>
			<p>
				<?php esc_html_e( 'Latest booking:', 'agency-module-booking' ); ?>
				<code>#<?php echo absint( $snapshot['latest']['id'] ); ?></code>
				<?php echo esc_html( $snapshot['latest']['email'] ?? '' ); ?>
				<?php echo esc_html( $snapshot['latest']['status'] ?? '' ); ?>
				<?php echo esc_html( $snapshot['latest']['start_at'] ?? '' ); ?>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $snapshot['last_error'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Last DB error:', 'agency-module-booking' ); ?></strong> <code><?php echo esc_html( $snapshot['last_error'] ); ?></code></p>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'admin_notices', 'agency_booking_admin_debug_notice' );

function agency_booking_client_portal_debug_panel() {
	if ( ! function_exists( 'agency_booking_can_manage' ) || ! agency_booking_can_manage() ) {
		return;
	}

	$snapshot = agency_booking_debug_snapshot();
	?>
	<div class="agency-booking-notice agency-booking-notice--<?php echo $snapshot['exists'] ? 'success' : 'error'; ?>">
		<strong><?php esc_html_e( 'Booking DB:', 'agency-module-booking' ); ?></strong>
		<?php echo esc_html( $snapshot['exists'] ? __( 'OK', 'agency-module-booking' ) : __( 'Missing table', 'agency-module-booking' ) ); ?>
		· <?php esc_html_e( 'Rows:', 'agency-module-booking' ); ?> <?php echo absint( $snapshot['total'] ); ?>
		· <?php esc_html_e( 'Version:', 'agency-module-booking' ); ?> <?php echo esc_html( $snapshot['db_version'] ); ?>
		<?php if ( ! empty( $snapshot['latest'] ) ) : ?>
			· <?php esc_html_e( 'Latest:', 'agency-module-booking' ); ?> #<?php echo absint( $snapshot['latest']['id'] ); ?> <?php echo esc_html( $snapshot['latest']['email'] ?? '' ); ?>
		<?php endif; ?>
	</div>
	<?php
}
