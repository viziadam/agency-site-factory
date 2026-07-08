<?php
/**
 * Booking frontend forms, REST availability and submission.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_notice() {
	$status = sanitize_key( wp_unslash( $_GET['booking'] ?? '' ) );
	$messages = array(
		'received' => array( 'success', __( 'Your booking request was received. Please check your email.', 'agency-module-booking' ) ),
		'verified' => array( 'success', __( 'Your guest booking was verified.', 'agency-module-booking' ) ),
		'error'    => array( 'error', __( 'The booking could not be completed. Please review the form.', 'agency-module-booking' ) ),
	);
	if ( ! isset( $messages[ $status ] ) ) {
		return '';
	}
	return '<div class="agency-booking-notice agency-booking-notice--' . esc_attr( $messages[ $status ][0] ) . '" role="status">' . esc_html( $messages[ $status ][1] ) . '</div>';
}

function agency_booking_form() {
	$settings = agency_booking_settings();
	if ( empty( $settings['booking_enabled'] ) ) {
		return '<div class="agency-booking-notice">' . esc_html__( 'Online booking is temporarily unavailable.', 'agency-module-booking' ) . '</div>';
	}
	if ( ! empty( $settings['login_required'] ) && ! is_user_logged_in() ) {
		return '<div class="agency-booking-notice">' . esc_html__( 'Sign in before booking.', 'agency-module-booking' ) . '</div>';
	}
	if ( ! empty( $settings['require_verified_email'] ) && is_user_logged_in() && function_exists( 'agency_auth_is_verified' ) && ! agency_auth_is_verified( get_current_user_id() ) ) {
		return '<div class="agency-booking-notice">' . esc_html__( 'Verify your email before booking.', 'agency-module-booking' ) . '</div>';
	}
	$user     = wp_get_current_user();
	$services = get_posts( array( 'post_type' => 'agency_service', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	$gdpr     = $settings['gdpr_text'] ?: ( function_exists( 'agency_legal_get_checkbox_text' ) ? agency_legal_get_checkbox_text( 'booking' ) : __( 'I consent to data processing for this booking.', 'agency-module-booking' ) );
	ob_start();
	echo wp_kses_post( agency_booking_notice() );
	?>
	<form class="agency-booking-form agency-booking-wizard" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-slots-url="<?php echo esc_url( rest_url( 'agency/v1/booking-slots' ) ); ?>" data-calendar-url="<?php echo esc_url( rest_url( 'agency/v1/booking-calendar' ) ); ?>">
		<input type="hidden" name="action" value="agency_booking_create">
		<input type="hidden" name="start_at" value="">
		<input type="hidden" name="service_id" value="">
		<input type="hidden" name="booking_date" value="">
		<?php wp_nonce_field( 'agency_booking_create' ); ?>
		<nav class="agency-booking-steps" aria-label="<?php esc_attr_e( 'Booking progress', 'agency-module-booking' ); ?>">
			<?php foreach ( array( 1 => __( 'Service', 'agency-module-booking' ), 2 => __( 'Date', 'agency-module-booking' ), 3 => __( 'Time', 'agency-module-booking' ), 4 => __( 'Your details', 'agency-module-booking' ) ) as $number => $label ) : ?><button type="button" data-go-step="<?php echo absint( $number ); ?>" <?php echo 1 === $number ? 'class="is-active"' : 'disabled'; ?>><span><?php echo absint( $number ); ?></span><?php echo esc_html( $label ); ?></button><?php endforeach; ?>
		</nav>
		<div class="agency-booking-wizard-body">
			<div class="agency-booking-stage">
				<section class="agency-booking-panel is-active" data-step="1"><span class="agency-booking-eyebrow"><?php esc_html_e( 'First step', 'agency-module-booking' ); ?></span><h2><?php esc_html_e( 'How can we help?', 'agency-module-booking' ); ?></h2><p><?php esc_html_e( 'Choose a service. Duration and price are shown for every option.', 'agency-module-booking' ); ?></p><div class="agency-booking-services"><?php foreach ( $services as $service ) : $price = agency_booking_service_price( $service->ID ); $duration = agency_booking_service_duration( $service->ID ); ?><button type="button" class="agency-booking-service" data-service-id="<?php echo absint( $service->ID ); ?>" data-service-title="<?php echo esc_attr( get_the_title( $service ) ); ?>" data-duration="<?php echo absint( $duration ); ?>" data-price="<?php echo esc_attr( $price['value'] ); ?>" data-currency="<?php echo esc_attr( $price['currency'] ); ?>"><span><strong><?php echo esc_html( get_the_title( $service ) ); ?></strong><small><?php echo esc_html( get_the_excerpt( $service ) ); ?></small></span><span class="agency-booking-service-meta"><small><?php echo absint( $duration ); ?> <?php esc_html_e( 'min', 'agency-module-booking' ); ?></small><strong><?php echo esc_html( number_format_i18n( $price['value'], 0 ) . ' ' . $price['currency'] ); ?></strong></span></button><?php endforeach; ?></div><?php if ( ! $services ) : ?><div class="agency-booking-notice agency-booking-notice--error"><?php esc_html_e( 'No bookable services are published yet.', 'agency-module-booking' ); ?></div><?php endif; ?></section>
				<section class="agency-booking-panel" data-step="2"><span class="agency-booking-eyebrow"><?php esc_html_e( 'Second step', 'agency-module-booking' ); ?></span><h2><?php esc_html_e( 'Choose a day', 'agency-module-booking' ); ?></h2><p><?php esc_html_e( 'The calendar shows how many starting times remain available.', 'agency-module-booking' ); ?></p><div class="agency-booking-calendar"><header><button type="button" data-calendar-prev aria-label="<?php esc_attr_e( 'Previous month', 'agency-module-booking' ); ?>">‹</button><strong data-calendar-title></strong><button type="button" data-calendar-next aria-label="<?php esc_attr_e( 'Next month', 'agency-module-booking' ); ?>">›</button></header><div class="agency-booking-calendar-weekdays"></div><div class="agency-booking-calendar-grid" aria-live="polite"></div></div><button type="button" class="agency-booking-back" data-back-step="1"><?php esc_html_e( 'Back', 'agency-module-booking' ); ?></button></section>
				<section class="agency-booking-panel" data-step="3"><span class="agency-booking-eyebrow"><?php esc_html_e( 'Third step', 'agency-module-booking' ); ?></span><h2><?php esc_html_e( 'Choose a time', 'agency-module-booking' ); ?></h2><div class="agency-booking-slot-legend"><span><?php esc_html_e( 'Available', 'agency-module-booking' ); ?></span></div><p class="agency-booking-slots-message"></p><div class="agency-booking-slot-grid" aria-live="polite"></div><button type="button" class="agency-booking-back" data-back-step="2"><?php esc_html_e( 'Back', 'agency-module-booking' ); ?></button></section>
				<section class="agency-booking-panel" data-step="4"><span class="agency-booking-eyebrow"><?php esc_html_e( 'Final step', 'agency-module-booking' ); ?></span><h2><?php esc_html_e( 'Your details', 'agency-module-booking' ); ?></h2><div class="agency-booking-grid">
					<label class="agency-booking-field"><span><?php esc_html_e( 'Name', 'agency-module-booking' ); ?></span><input required name="name" autocomplete="name" value="<?php echo esc_attr( $user->exists() ? $user->display_name : '' ); ?>"></label>
					<label class="agency-booking-field"><span><?php esc_html_e( 'Email', 'agency-module-booking' ); ?></span><input required type="email" name="email" autocomplete="email" value="<?php echo esc_attr( $user->exists() ? $user->user_email : '' ); ?>"></label>
					<label class="agency-booking-field"><span><?php esc_html_e( 'Phone', 'agency-module-booking' ); ?></span><input required name="phone" autocomplete="tel"></label>
					<label class="agency-booking-field agency-booking-field--wide"><span><?php esc_html_e( 'Note', 'agency-module-booking' ); ?></span><textarea name="note" rows="4"></textarea></label>
				</div><label class="agency-booking-consent"><input required type="checkbox" name="privacy" value="1"><span><?php echo esc_html( $gdpr ); ?></span></label><div class="agency-booking-final-actions"><button type="button" class="agency-booking-back" data-back-step="3"><?php esc_html_e( 'Back', 'agency-module-booking' ); ?></button><button class="agency-booking-submit" type="submit"><?php esc_html_e( 'Confirm booking', 'agency-module-booking' ); ?></button></div></section>
			</div>
			<aside class="agency-booking-summary"><span><?php esc_html_e( 'Your booking', 'agency-module-booking' ); ?></span><h2><?php esc_html_e( 'Summary', 'agency-module-booking' ); ?></h2><?php foreach ( array( 'service' => __( 'Service', 'agency-module-booking' ), 'date' => __( 'Date', 'agency-module-booking' ), 'time' => __( 'Time', 'agency-module-booking' ), 'duration' => __( 'Duration', 'agency-module-booking' ), 'price' => __( 'Price', 'agency-module-booking' ) ) as $key => $label ) : ?><div><small><?php echo esc_html( $label ); ?></small><strong data-summary-<?php echo esc_attr( $key ); ?>>—</strong></div><?php endforeach; ?><p><?php esc_html_e( 'Prices are informational; any change will be agreed before service.', 'agency-module-booking' ); ?></p></aside>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

function agency_booking_customer_list() {
	if ( ! is_user_logged_in() ) {
		return '<div class="agency-booking-notice">' . esc_html__( 'Sign in to view your bookings.', 'agency-module-booking' ) . '</div>';
	}
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . agency_booking_table() . ' WHERE user_id=%d ORDER BY start_at DESC LIMIT 100', get_current_user_id() ) );
	ob_start();
	?><section class="agency-booking-account"><h2><?php esc_html_e( 'My bookings', 'agency-module-booking' ); ?></h2><div class="agency-booking-account-grid"><?php if ( ! $rows ) : ?><p><?php esc_html_e( 'You have no bookings yet.', 'agency-module-booking' ); ?></p><?php endif; foreach ( $rows as $row ) : $local = get_date_from_gmt( $row->start_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ); ?><article class="agency-booking-card"><span class="agency-booking-status agency-booking-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( ucfirst( $row->status ) ); ?></span><h3><?php echo esc_html( get_the_title( $row->service_id ) ); ?></h3><p><?php echo esc_html( $local ); ?></p><small>#<?php echo absint( $row->id ); ?></small></article><?php endforeach; ?></div></section><?php
	return ob_get_clean();
}

function agency_booking_list_shortcode() {
	return agency_booking_customer_list();
}

add_shortcode( 'agency_booking', 'agency_booking_form' );
add_shortcode( 'agency_booking_form', 'agency_booking_form' );
add_shortcode( 'agency_booking_list', 'agency_booking_customer_list' );
add_shortcode( 'agency_booking_customer_list', 'agency_booking_customer_list' );

function agency_booking_register_rest() {
	register_rest_route(
		'agency/v1',
		'/booking-slots',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'args'                => array(
				'date'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static fn( $value ) => 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ),
				'service_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'booking_slots', 120, HOUR_IN_SECONDS ) ) {
					return new WP_Error( 'agency_booking_rate_limit', __( 'Too many availability requests.', 'agency-module-booking' ), array( 'status' => 429 ) );
				}
				$slots = agency_booking_get_available_slots( $request['date'], $request['service_id'] );
				return rest_ensure_response( array( 'date' => $request['date'], 'slots' => $slots, 'timezone' => wp_timezone_string() ) );
			},
		)
	);
	register_rest_route(
		'agency/v1',
		'/booking-calendar',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => '__return_true',
			'args'                => array(
				'month'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static fn( $value ) => 1 === preg_match( '/^\d{4}-\d{2}$/', $value ) ),
				'service_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
			),
			'callback'            => 'agency_booking_calendar_response',
		)
	);
}
add_action( 'rest_api_init', 'agency_booking_register_rest' );

function agency_booking_calendar_response( WP_REST_Request $request ) {
	if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'booking_calendar', 60, HOUR_IN_SECONDS ) ) {
		return new WP_Error( 'agency_booking_rate_limit', __( 'Too many calendar requests.', 'agency-module-booking' ), array( 'status' => 429 ) );
	}
	$month      = (string) $request['month'];
	$service_id = absint( $request['service_id'] );
	$first      = DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', wp_timezone() );
	$today      = new DateTimeImmutable( 'today', wp_timezone() );
	if ( ! $first || ! $service_id || 'agency_service' !== get_post_type( $service_id ) ) {
		return new WP_Error( 'agency_booking_calendar_invalid', __( 'Invalid month or service.', 'agency-module-booking' ), array( 'status' => 400 ) );
	}
	$days = array();
	for ( $day = 1; $day <= (int) $first->format( 't' ); $day++ ) {
		$date  = $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'm' ), $day );
		$value = $date->format( 'Y-m-d' );
		$count = $date < $today ? 0 : count( agency_booking_get_available_slots( $value, $service_id ) );
		$days[] = array( 'day' => $day, 'date' => $value, 'available' => $count, 'disabled' => $date < $today || 0 === $count );
	}
	return rest_ensure_response(
		array(
			'month'         => $month,
			'label'         => wp_date( 'F Y', $first->getTimestamp(), wp_timezone() ),
			'first_weekday' => (int) $first->format( 'N' ) - 1,
			'days'          => $days,
		)
	);
}

function agency_booking_create() {
	check_admin_referer( 'agency_booking_create' );
	$settings = agency_booking_settings();
	$redirect = wp_get_referer() ?: home_url( '/' );
	if ( empty( $settings['booking_enabled'] ) || ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'booking_create', 6, HOUR_IN_SECONDS ) ) ) {
		wp_safe_redirect( add_query_arg( 'booking', 'error', $redirect ) ); exit;
	}
	if ( ! empty( $settings['login_required'] ) && ! is_user_logged_in() ) {
		wp_die( esc_html__( 'A customer account is required.', 'agency-module-booking' ), 403 );
	}
	if ( ! empty( $settings['require_verified_email'] ) && is_user_logged_in() && function_exists( 'agency_auth_is_verified' ) && ! agency_auth_is_verified( get_current_user_id() ) ) {
		wp_die( esc_html__( 'A verified account is required.', 'agency-module-booking' ), 403 );
	}
	if ( empty( $settings['guest_booking'] ) && ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Guest booking is disabled.', 'agency-module-booking' ), 403 );
	}
	$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$service_id = absint( $_POST['service_id'] ?? 0 );
	$start_raw  = sanitize_text_field( wp_unslash( $_POST['start_at'] ?? '' ) );
	$local_date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $start_raw, wp_timezone() );
	$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	if ( ! $email || ! $service_id || ! $local_date || ! $name || ! $phone || empty( $_POST['privacy'] ) || ! agency_booking_is_slot_available( $local_date, $service_id, $settings ) ) {
		wp_safe_redirect( add_query_arg( 'booking', 'error', $redirect ) ); exit;
	}
	global $wpdb;
	$duration    = agency_booking_service_duration( $service_id );
	$start_at    = gmdate( 'Y-m-d H:i:s', $local_date->getTimestamp() );
	$end_at      = gmdate( 'Y-m-d H:i:s', $local_date->modify( "+{$duration} minutes" )->getTimestamp() );
	$lock_name   = 'agency_booking_day_' . md5( $local_date->format( 'Y-m-d' ) );
	$locked      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock_name ) );
	if ( 1 !== $locked || ! agency_booking_is_slot_available( $local_date, $service_id, $settings ) ) {
		if ( $locked ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
		wp_safe_redirect( add_query_arg( 'booking', 'error', $redirect ) ); exit;
	}
	$status      = 'auto' === $settings['confirmation_mode'] && is_user_logged_in() ? 'approved' : 'pending';
	$guest_token = is_user_logged_in() ? '' : bin2hex( random_bytes( 24 ) );
	$now         = current_time( 'mysql', true );
	$inserted    = $wpdb->insert(
		agency_booking_table(),
		array(
			'user_id' => get_current_user_id(), 'service_id' => $service_id, 'start_at' => $start_at, 'end_at' => $end_at,
			'name' => $name, 'email' => $email, 'phone' => $phone, 'customer_note' => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
			'admin_note' => '', 'status' => $status, 'verification_hash' => $guest_token ? wp_hash_password( $guest_token ) : '', 'created_at' => $now, 'updated_at' => $now,
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( ! $inserted ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		wp_safe_redirect( add_query_arg( 'booking', 'error', $redirect ) ); exit;
	}
	$booking = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . agency_booking_table() . ' WHERE id=%d', $wpdb->insert_id ) );
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	$verification_message = '';
	if ( $guest_token ) {
		$verify_url = add_query_arg( array( 'agency_booking_verify' => $booking->id, 'token' => rawurlencode( $guest_token ) ), home_url( '/' ) );
		$verification_message = '<p><a href="' . esc_url( $verify_url ) . '">' . esc_html__( 'Verify this guest booking', 'agency-module-booking' ) . '</a></p>';
	}
	agency_booking_send_email( 'received', $booking, '', $verification_message );
	agency_booking_send_email( 'admin', $booking );
	if ( 'approved' === $status ) {
		agency_booking_send_email( 'approved', $booking );
	}
	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'booking', 'created', array( 'booking_id' => $booking->id, 'status' => $status ) );
	}
	if ( function_exists( 'agency_core_record_event' ) ) {
		agency_core_record_event( 'conversion', 'booking_completed', wp_parse_url( $redirect, PHP_URL_PATH ) ?: '/idopontfoglalas/', get_the_title( $service_id ), array( 'service_id' => $service_id ) );
	}
	$thank_you = $settings['thank_you_url'] ?: $redirect;
	wp_safe_redirect( add_query_arg( 'booking', 'received', $thank_you ) ); exit;
}
add_action( 'admin_post_nopriv_agency_booking_create', 'agency_booking_create' );
add_action( 'admin_post_agency_booking_create', 'agency_booking_create' );

function agency_booking_verify_guest() {
	if ( empty( $_GET['agency_booking_verify'] ) || empty( $_GET['token'] ) ) {
		return;
	}
	global $wpdb;
	$id    = absint( $_GET['agency_booking_verify'] );
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
	$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . agency_booking_table() . ' WHERE id=%d', $id ) );
	if ( ! $row || ! $row->verification_hash || ! wp_check_password( $token, $row->verification_hash ) ) {
		wp_die( esc_html__( 'Invalid or expired booking verification link.', 'agency-module-booking' ), 403 );
	}
	$update = array( 'verification_hash' => '', 'updated_at' => current_time( 'mysql', true ) );
	if ( 'auto' === agency_booking_settings()['confirmation_mode'] ) {
		$conflict = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . agency_booking_table() . " WHERE start_at < %s AND end_at > %s AND status='approved' AND id<>%d", $row->end_at, $row->start_at, $row->id ) );
		if ( ! $conflict ) {
			$update['status'] = 'approved';
			$row->status      = 'approved';
		}
	}
	$wpdb->update( agency_booking_table(), $update, array( 'id' => $id ) );
	if ( 'approved' === $row->status ) {
		agency_booking_send_email( 'approved', $row );
	}
	wp_safe_redirect( add_query_arg( 'booking', 'verified', home_url( '/' ) ) ); exit;
}
add_action( 'init', 'agency_booking_verify_guest' );
