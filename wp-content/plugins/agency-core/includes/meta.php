<?php
/**
 * Registered metadata.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_sanitize_sections_meta( $value ) {
	$decoded = is_string( $value ) ? json_decode( wp_unslash( $value ), true ) : $value;
	if ( ! is_array( $decoded ) ) {
		return '[]';
	}
	return wp_json_encode( agency_core_clean_sections( $decoded ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function agency_core_register_meta() {
	register_post_meta(
		'',
		'_agency_sections',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'agency_core_sanitize_sections_meta',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'agency_core_register_meta' );

function agency_core_add_service_meta_box() {
	add_meta_box(
		'agency-core-service-details',
		__( 'Service details', 'agency-core' ),
		'agency_core_render_service_meta_box',
		'agency_service',
		'side'
	);
}
add_action( 'add_meta_boxes', 'agency_core_add_service_meta_box' );

function agency_core_render_service_meta_box( $post ) {
	wp_nonce_field( 'agency_core_save_service_meta', 'agency_core_service_nonce' );
	?>
	<p>
		<label for="agency-core-price"><?php esc_html_e( 'Price', 'agency-core' ); ?></label>
		<input class="widefat" id="agency-core-price" name="agency_core_price" type="number" min="0" step="0.01" value="<?php echo esc_attr( get_post_meta( $post->ID, '_agency_price', true ) ); ?>">
	</p>
	<p>
		<label for="agency-core-currency"><?php esc_html_e( 'Currency', 'agency-core' ); ?></label>
		<input class="widefat" id="agency-core-currency" name="agency_core_currency" maxlength="3" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, '_agency_price_currency', true ) ); ?>">
	</p>
	<?php
}

function agency_core_save_service_meta( $post_id ) {
	if ( ! isset( $_POST['agency_core_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agency_core_service_nonce'] ) ), 'agency_core_save_service_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$price    = isset( $_POST['agency_core_price'] ) ? wp_unslash( $_POST['agency_core_price'] ) : '';
	$currency = isset( $_POST['agency_core_currency'] ) ? wp_unslash( $_POST['agency_core_currency'] ) : '';
	update_post_meta( $post_id, '_agency_price', is_numeric( $price ) ? (string) (float) $price : '' );
	update_post_meta( $post_id, '_agency_price_currency', strtoupper( substr( sanitize_text_field( $currency ), 0, 3 ) ) );
}
add_action( 'save_post_agency_service', 'agency_core_save_service_meta' );
