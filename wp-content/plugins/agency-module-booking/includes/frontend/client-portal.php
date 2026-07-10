<?php
/**
 * Standalone client portal integration for booking managers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_manager_login_shortcode() {
	if ( function_exists( 'agency_core_client_admin_shortcode' ) ) {
		return agency_core_client_admin_shortcode();
	}
	$settings = agency_booking_settings();
	$portal   = $settings['manager_page_id'] ? get_permalink( $settings['manager_page_id'] ) : home_url( '/foglalas-admin/' );
	if ( agency_booking_can_manage() ) {
		return '<div class="agency-manager-login"><h2>' . esc_html__( 'You are signed in', 'agency-module-booking' ) . '</h2><a class="agency-manager-button" href="' . esc_url( $portal ) . '">' . esc_html__( 'Open booking dashboard', 'agency-module-booking' ) . '</a></div>';
	}
	ob_start();
	?>
	<section class="agency-manager-login">
		<div class="agency-manager-login-brand"><span><?php esc_html_e( 'Agency Booking', 'agency-module-booking' ); ?></span><h1><?php esc_html_e( 'Business portal', 'agency-module-booking' ); ?></h1><p><?php esc_html_e( 'Sign in to manage appointments and availability.', 'agency-module-booking' ); ?></p></div>
		<?php if ( isset( $_GET['manager_login'] ) ) : ?><div class="agency-booking-notice agency-booking-notice--error"><?php esc_html_e( 'The email or password is incorrect.', 'agency-module-booking' ); ?></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="agency_booking_manager_login"><input type="hidden" name="redirect_to" value="<?php echo esc_url( $portal ); ?>"><?php wp_nonce_field( 'agency_booking_manager_login' ); ?>
			<label><span><?php esc_html_e( 'Email or username', 'agency-module-booking' ); ?></span><input required name="log" autocomplete="username"></label>
			<label><span><?php esc_html_e( 'Password', 'agency-module-booking' ); ?></span><input required type="password" name="pwd" autocomplete="current-password"></label>
			<label class="agency-manager-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Keep me signed in', 'agency-module-booking' ); ?></label>
			<button class="agency-manager-button"><?php esc_html_e( 'Sign in securely', 'agency-module-booking' ); ?></button>
			<a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Forgot password?', 'agency-module-booking' ); ?></a>
		</form>
	</section>
	<?php
	return ob_get_clean();
}

function agency_booking_manager_login() {
	check_admin_referer( 'agency_booking_manager_login' );
	$redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? home_url( '/foglalas-admin/' ) ) );
	$user = wp_signon(
		array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
			'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ),
			'remember'      => ! empty( $_POST['remember'] ),
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) || ! user_can( $user, 'manage_agency_bookings' ) ) {
		if ( ! is_wp_error( $user ) ) {
			wp_logout();
		}
		wp_safe_redirect( add_query_arg( 'manager_login', 'error', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_nopriv_agency_booking_manager_login', 'agency_booking_manager_login' );

function agency_booking_manager_portal_shortcode() {
	if ( function_exists( 'agency_core_client_admin_shortcode' ) ) {
		return agency_core_client_admin_shortcode();
	}
	$settings = agency_booking_settings();
	if ( ! agency_booking_can_manage() ) {
		$login = $settings['manager_login_page_id'] ? get_permalink( $settings['manager_login_page_id'] ) : home_url( '/foglalas-admin-bejelentkezes/' );
		return '<div class="agency-manager-gate"><h2>' . esc_html__( 'Booking management', 'agency-module-booking' ) . '</h2><p>' . esc_html__( 'A booking manager account is required.', 'agency-module-booking' ) . '</p><a class="agency-manager-button" href="' . esc_url( $login ) . '">' . esc_html__( 'Manager sign in', 'agency-module-booking' ) . '</a></div>';
	}
	$tab   = sanitize_key( wp_unslash( $_GET['portal_tab'] ?? 'dashboard' ) );
	$stats = agency_booking_admin_stats();
	$rows  = agency_booking_admin_query();
	$base  = get_permalink();
	ob_start();
	?>
	<section class="agency-manager-portal">
		<header class="agency-manager-topbar"><div><span><?php esc_html_e( 'Agency Booking', 'agency-module-booking' ); ?></span><h1><?php esc_html_e( 'Business dashboard', 'agency-module-booking' ); ?></h1></div><div><strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong><a href="<?php echo esc_url( wp_logout_url( $settings['manager_login_page_id'] ? get_permalink( $settings['manager_login_page_id'] ) : home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'agency-module-booking' ); ?></a></div></header>
		<nav class="agency-manager-nav"><?php foreach ( array( 'dashboard' => __( 'Overview', 'agency-module-booking' ), 'bookings' => __( 'Bookings', 'agency-module-booking' ), 'calendar' => __( 'Calendar settings', 'agency-module-booking' ) ) as $key => $label ) : ?><a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'portal_tab', $key, $base ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?></nav>
		<?php if ( function_exists( 'agency_booking_client_portal_debug_panel' ) ) { agency_booking_client_portal_debug_panel(); } ?>
		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="agency-booking-notice agency-booking-notice--success"><?php esc_html_e( 'Changes saved successfully.', 'agency-module-booking' ); ?></div><?php endif; ?>
		<?php if ( 'dashboard' === $tab ) : ?>
			<div class="agency-manager-stats"><?php foreach ( array( 'today' => __( 'Today', 'agency-module-booking' ), 'week' => __( 'Next 7 days', 'agency-module-booking' ), 'month' => __( 'Next 30 days', 'agency-module-booking' ), 'pending' => __( 'Waiting for approval', 'agency-module-booking' ) ) as $key => $label ) : ?><article><span><?php echo esc_html( $label ); ?></span><strong><?php echo absint( $stats[ $key ] ); ?></strong></article><?php endforeach; ?></div>
			<div class="agency-manager-panel"><div class="agency-manager-panel-title"><h2><?php esc_html_e( 'Upcoming appointments', 'agency-module-booking' ); ?></h2><a href="<?php echo esc_url( add_query_arg( 'portal_tab', 'bookings', $base ) ); ?>"><?php esc_html_e( 'View all', 'agency-module-booking' ); ?></a></div><?php agency_booking_portal_rows( array_slice( $rows, 0, 8 ), $base ); ?></div>
		<?php elseif ( 'bookings' === $tab ) : ?>
			<div class="agency-manager-panel"><div class="agency-manager-panel-title"><h2><?php esc_html_e( 'All bookings', 'agency-module-booking' ); ?></h2><span><?php echo absint( count( $rows ) ); ?> <?php esc_html_e( 'records', 'agency-module-booking' ); ?></span></div><?php agency_booking_portal_rows( $rows, $base ); ?></div>
		<?php else : ?>
			<?php agency_booking_portal_settings( $settings, $base ); ?>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

function agency_booking_portal_rows( $rows, $return_url ) {
	if ( ! $rows ) {
		echo '<p class="agency-manager-empty">' . esc_html__( 'No bookings found.', 'agency-module-booking' ) . '</p>';
		return;
	}
	echo '<div class="agency-manager-bookings">';
	foreach ( $rows as $row ) {
		$action_url = function_exists( 'agency_core_client_admin_action_url' ) ? agency_core_client_admin_action_url( 'agency_booking_status', 'bookings' ) : admin_url( 'admin-post.php' );
		?>
		<article>
			<div class="agency-manager-date"><strong><?php echo esc_html( get_date_from_gmt( $row->start_at, 'j' ) ); ?></strong><span><?php echo esc_html( get_date_from_gmt( $row->start_at, 'M' ) ); ?></span></div>
			<div class="agency-manager-booking-main">
				<span class="agency-booking-status agency-booking-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span>
				<h3><?php echo esc_html( $row->name ); ?> · <?php echo esc_html( get_the_title( $row->service_id ) ); ?></h3>
				<p><?php echo esc_html( get_date_from_gmt( $row->start_at, get_option( 'time_format' ) ) ); ?> · <a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a> · <?php echo esc_html( $row->phone ); ?></p>
			</div>
			<form class="agency-manager-actions" method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="action" value="agency_booking_status">
				<input type="hidden" name="agency_portal_action" value="agency_booking_status">
				<input type="hidden" name="booking_id" value="<?php echo absint( $row->id ); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>">
				<?php wp_nonce_field( 'agency_booking_status_' . $row->id ); ?>
				<button name="booking_action" value="approved"><?php esc_html_e( 'Approve', 'agency-module-booking' ); ?></button>
				<button name="booking_action" value="rejected" class="is-danger"><?php esc_html_e( 'Reject', 'agency-module-booking' ); ?></button>
			</form>
		</article>
		<?php
	}
	echo '</div>';
}

function agency_booking_portal_settings( $settings, $return_url ) {
	$active     = array_map( 'absint', explode( ',', $settings['active_weekdays'] ) );
	$action_url = function_exists( 'agency_core_client_admin_action_url' ) ? agency_core_client_admin_action_url( 'agency_booking_save_settings', 'booking-calendar' ) : admin_url( 'admin-post.php' );
	?>
	<div class="agency-manager-panel"><div class="agency-manager-panel-title"><div><h2><?php esc_html_e( 'Availability', 'agency-module-booking' ); ?></h2><p><?php esc_html_e( 'Set opening hours, working days and exceptions.', 'agency-module-booking' ); ?></p></div></div>
	<form class="agency-manager-settings" method="post" action="<?php echo esc_url( $action_url ); ?>"><input type="hidden" name="action" value="agency_booking_save_settings"><input type="hidden" name="agency_portal_action" value="agency_booking_save_settings"><input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>"><?php wp_nonce_field( 'agency_booking_save_settings' ); ?>
		<input type="hidden" name="booking_enabled" value="1"><input type="hidden" name="guest_booking" value="<?php echo empty( $settings['guest_booking'] ) ? 0 : 1; ?>"><input type="hidden" name="pending_blocks_slot" value="<?php echo empty( $settings['pending_blocks_slot'] ) ? 0 : 1; ?>"><input type="hidden" name="confirmation_mode" value="<?php echo esc_attr( $settings['confirmation_mode'] ); ?>"><input type="hidden" name="slot_duration" value="<?php echo absint( $settings['slot_duration'] ); ?>">
		<div class="agency-manager-field-row"><label><span><?php esc_html_e( 'Opening time', 'agency-module-booking' ); ?></span><input type="time" name="day_start" value="<?php echo esc_attr( $settings['day_start'] ); ?>"></label><label><span><?php esc_html_e( 'Closing time', 'agency-module-booking' ); ?></span><input type="time" name="day_end" value="<?php echo esc_attr( $settings['day_end'] ); ?>"></label></div>
		<fieldset><legend><?php esc_html_e( 'Working days', 'agency-module-booking' ); ?></legend><div class="agency-manager-days"><?php foreach ( array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun' ) as $day => $label ) : ?><label><input type="checkbox" name="active_weekdays[]" value="<?php echo absint( $day ); ?>" <?php checked( in_array( $day, $active, true ) ); ?>><span><?php echo esc_html( $label ); ?></span></label><?php endforeach; ?></div></fieldset>
		<label><span><?php esc_html_e( 'Closed dates', 'agency-module-booking' ); ?></span><textarea name="closed_dates" rows="4" placeholder="2026-12-24"><?php echo esc_textarea( $settings['closed_dates'] ); ?></textarea><small><?php esc_html_e( 'One YYYY-MM-DD date per line.', 'agency-module-booking' ); ?></small></label>
		<label><span><?php esc_html_e( 'Special open dates', 'agency-module-booking' ); ?></span><textarea name="special_open_dates" rows="4"><?php echo esc_textarea( $settings['special_open_dates'] ); ?></textarea></label>
		<button class="agency-manager-button"><?php esc_html_e( 'Save calendar settings', 'agency-module-booking' ); ?></button>
	</form></div>
	<?php
}

add_shortcode( 'agency_booking_manager_login', 'agency_booking_manager_login_shortcode' );
add_shortcode( 'agency_booking_manager_portal', 'agency_booking_manager_portal_shortcode' );

function agency_booking_client_portal_tabs( $tabs ) {
	$tabs['bookings'] = array( 'label' => __( 'Bookings', 'agency-module-booking' ), 'callback' => 'agency_booking_client_portal_bookings', 'order' => 20 );
	$tabs['booking-calendar'] = array( 'label' => __( 'Booking settings', 'agency-module-booking' ), 'callback' => 'agency_booking_client_portal_calendar', 'order' => 30 );
	return $tabs;
}
add_filter( 'agency_client_portal_tabs', 'agency_booking_client_portal_tabs' );

function agency_booking_client_portal_assets() {
	echo '<link rel="stylesheet" href="' . esc_url( add_query_arg( 'ver', AGENCY_BOOKING_VERSION, plugins_url( 'assets/css/booking.css', AGENCY_BOOKING_FILE ) ) ) . '">' . "\n";
	echo '<link rel="stylesheet" href="' . esc_url( add_query_arg( 'ver', AGENCY_BOOKING_VERSION, plugins_url( 'assets/css/admin.css', AGENCY_BOOKING_FILE ) ) ) . '">' . "\n";
}
add_action( 'agency_client_portal_head', 'agency_booking_client_portal_assets' );

function agency_booking_client_portal_bookings() {
	$stats = agency_booking_admin_stats();
	$rows  = agency_booking_admin_query();
	$base  = function_exists( 'agency_core_client_admin_url' ) ? agency_core_client_admin_url( 'bookings' ) : get_permalink();
	if ( function_exists( 'agency_booking_client_portal_debug_panel' ) ) {
		agency_booking_client_portal_debug_panel();
	}
	?><div class="agency-manager-stats"><?php foreach ( array( 'today' => __( 'Today', 'agency-module-booking' ), 'week' => __( 'Next 7 days', 'agency-module-booking' ), 'month' => __( 'Next 30 days', 'agency-module-booking' ), 'pending' => __( 'Waiting for approval', 'agency-module-booking' ) ) as $key => $label ) : ?><article><span><?php echo esc_html( $label ); ?></span><strong><?php echo absint( $stats[ $key ] ); ?></strong></article><?php endforeach; ?></div><div class="agency-manager-panel"><div class="agency-manager-panel-title"><h2><?php esc_html_e( 'All bookings', 'agency-module-booking' ); ?></h2><span><?php echo absint( count( $rows ) ); ?> <?php esc_html_e( 'records', 'agency-module-booking' ); ?></span></div><?php agency_booking_portal_rows( $rows, $base ); ?></div><?php
}

function agency_booking_client_portal_calendar() {
	$base = function_exists( 'agency_core_client_admin_url' ) ? agency_core_client_admin_url( 'booking-calendar' ) : get_permalink();
	agency_booking_portal_settings( agency_booking_settings(), $base );
}

function agency_booking_manager_admin_redirect() {
	global $pagenow;
	if ( ! is_admin() || wp_doing_ajax() || 'admin-post.php' === $pagenow || ! agency_booking_can_manage() || current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( function_exists( 'agency_core_client_admin_url' ) ) {
		wp_safe_redirect( agency_core_client_admin_url( 'bookings' ) );
		exit;
	}
}
add_action( 'admin_init', 'agency_booking_manager_admin_redirect', 2 );
add_filter( 'show_admin_bar', static fn( $show ) => agency_booking_can_manage() && ! current_user_can( 'manage_options' ) ? false : $show );

function agency_booking_redirect_legacy_portal_pages() {
	if ( ! function_exists( 'agency_core_client_admin_url' ) ) {
		return;
	}
	$settings = agency_booking_settings();
	$legacy   = array_filter( array( absint( $settings['manager_page_id'] ), absint( $settings['manager_login_page_id'] ) ) );
	if ( $legacy && is_page( $legacy ) ) {
		wp_safe_redirect( agency_core_client_admin_url(), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'agency_booking_redirect_legacy_portal_pages', 2 );
