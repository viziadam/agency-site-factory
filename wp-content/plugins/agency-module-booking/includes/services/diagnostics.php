<?php
/**
 * Booking diagnostics and self-repair helpers.
 *
 * These helpers make the booking module easier to debug in local and production
 * environments. They do not change business rules; they only ensure the schema
 * is available and expose a clear health snapshot for admins.
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

	// If the version option says the DB is current while the physical table is
	// missing, force the migration to run again on this request.
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
	</div>
	<?php
}
