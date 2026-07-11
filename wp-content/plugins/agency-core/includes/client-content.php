<?php
/**
 * Client-manageable services and works content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_content_manage_capability() {
	agency_core_client_admin_bootstrap_session();
	return current_user_can( 'manage_options' ) || current_user_can( 'manage_agency_portal' );
}

function agency_core_register_showcase_meta() {
	$meta_keys = array(
		'agency_service' => array(
			'_agency_price'          => 'string',
			'_agency_price_currency' => 'string',
			'_agency_duration'       => 'string',
			'_agency_image_url'      => 'string',
		),
		'agency_portfolio' => array(
			'_agency_project_client' => 'string',
			'_agency_project_year'   => 'string',
			'_agency_project_type'   => 'string',
			'_agency_image_url'      => 'string',
		),
	);

	foreach ( $meta_keys as $post_type => $keys ) {
		foreach ( $keys as $key => $type ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' ) || agency_core_content_manage_capability();
					},
				)
			);
		}
	}
}
add_action( 'init', 'agency_core_register_showcase_meta', 20 );

function agency_core_content_portal_tabs( $tabs ) {
	if ( ! agency_core_content_manage_capability() ) {
		return $tabs;
	}

	$tabs['services'] = array(
		'label'    => __( 'Services', 'agency-core' ),
		'callback' => 'agency_core_client_portal_services',
		'order'    => 35,
	);
	$tabs['works'] = array(
		'label'    => __( 'Works', 'agency-core' ),
		'callback' => 'agency_core_client_portal_works',
		'order'    => 40,
	);

	return $tabs;
}
add_filter( 'agency_client_portal_tabs', 'agency_core_content_portal_tabs' );

function agency_core_content_query( $post_type ) {
	return get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);
}

function agency_core_content_get_edit_post( $post_type ) {
	$edit_id = absint( $_GET['edit_id'] ?? 0 );
	if ( ! $edit_id ) {
		return null;
	}
	$post = get_post( $edit_id );
	if ( ! $post || $post_type !== $post->post_type ) {
		return null;
	}
	return $post;
}

function agency_core_content_status_options() {
	return array(
		'publish' => __( 'Published', 'agency-core' ),
		'draft'   => __( 'Draft', 'agency-core' ),
		'pending' => __( 'Pending review', 'agency-core' ),
		'private' => __( 'Private', 'agency-core' ),
	);
}

function agency_core_content_render_status_badge( $status ) {
	$options = agency_core_content_status_options();
	$label   = $options[ $status ] ?? $status;
	echo '<span class="agency-content-status agency-content-status--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

function agency_core_client_portal_services() {
	if ( ! agency_core_content_manage_capability() ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 );
	}

	$editing = agency_core_content_get_edit_post( 'agency_service' );
	$rows    = agency_core_content_query( 'agency_service' );
	$base    = agency_core_client_admin_url( 'services' );
	?>
	<div class="agency-client-panel agency-content-panel">
		<div class="agency-content-panel__header">
			<div>
				<h2><?php esc_html_e( 'Services', 'agency-core' ); ?></h2>
				<p><?php esc_html_e( 'Manage the service cards shown on the website. Prices, descriptions and order are editable here.', 'agency-core' ); ?></p>
			</div>
			<?php if ( $editing ) : ?>
				<a class="agency-client-button agency-client-button--ghost" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Add new service', 'agency-core' ); ?></a>
			<?php endif; ?>
		</div>
		<?php agency_core_content_render_service_form( $editing ); ?>
	</div>

	<div class="agency-client-panel agency-content-panel">
		<div class="agency-content-panel__header">
			<h2><?php esc_html_e( 'Existing services', 'agency-core' ); ?></h2>
			<span><?php echo absint( count( $rows ) ); ?> <?php esc_html_e( 'items', 'agency-core' ); ?></span>
		</div>
		<?php agency_core_content_render_services_table( $rows, $base ); ?>
	</div>
	<?php
}

function agency_core_content_render_service_form( $post = null ) {
	$post_id  = $post ? absint( $post->ID ) : 0;
	$action   = agency_core_client_admin_action_url( 'agency_core_portal_service_save', 'services' );
	$currency = $post_id ? get_post_meta( $post_id, '_agency_price_currency', true ) : 'HUF';
	$status   = $post ? $post->post_status : 'publish';
	?>
	<form class="agency-content-form" method="post" action="<?php echo esc_url( $action ); ?>">
		<input type="hidden" name="agency_portal_action" value="agency_core_portal_service_save">
		<input type="hidden" name="item_id" value="<?php echo absint( $post_id ); ?>">
		<?php wp_nonce_field( 'agency_core_portal_service_save' ); ?>

		<div class="agency-content-grid">
			<label class="agency-content-field agency-content-field--wide">
				<span><?php esc_html_e( 'Service title', 'agency-core' ); ?></span>
				<input required name="title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>">
			</label>
			<label><span><?php esc_html_e( 'Price', 'agency-core' ); ?></span><input type="number" min="0" step="1" name="price" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_price', true ) : '' ); ?>"></label>
			<label><span><?php esc_html_e( 'Currency', 'agency-core' ); ?></span><input maxlength="8" name="currency" value="<?php echo esc_attr( $currency ?: 'HUF' ); ?>"></label>
			<label><span><?php esc_html_e( 'Duration / badge', 'agency-core' ); ?></span><input name="duration" placeholder="60 perc" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_duration', true ) : '' ); ?>"></label>
			<label><span><?php esc_html_e( 'Order', 'agency-core' ); ?></span><input type="number" name="menu_order" value="<?php echo esc_attr( $post ? $post->menu_order : 0 ); ?>"></label>
			<label><span><?php esc_html_e( 'Status', 'agency-core' ); ?></span><select name="status"><?php foreach ( agency_core_content_status_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="agency-content-field--wide"><span><?php esc_html_e( 'Image URL', 'agency-core' ); ?></span><input type="url" name="image_url" placeholder="https://..." value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_image_url', true ) : '' ); ?>"></label>
			<label class="agency-content-field--wide"><span><?php esc_html_e( 'Short description', 'agency-core' ); ?></span><textarea required name="excerpt" rows="3"><?php echo esc_textarea( $post ? $post->post_excerpt : '' ); ?></textarea></label>
			<label class="agency-content-field--wide"><span><?php esc_html_e( 'Long description', 'agency-core' ); ?></span><textarea name="content" rows="6"><?php echo esc_textarea( $post ? $post->post_content : '' ); ?></textarea></label>
		</div>
		<div class="agency-content-actions"><button class="agency-client-button"><?php echo $post ? esc_html__( 'Save service', 'agency-core' ) : esc_html__( 'Create service', 'agency-core' ); ?></button><?php if ( $post ) : ?><button class="agency-content-delete" name="delete_item" value="1" onclick="return confirm('<?php echo esc_js( __( 'Move this service to Trash?', 'agency-core' ) ); ?>')"><?php esc_html_e( 'Delete', 'agency-core' ); ?></button><?php endif; ?></div>
	</form>
	<?php
}

function agency_core_content_render_services_table( $rows, $base ) {
	if ( ! $rows ) { echo '<p class="agency-client-empty">' . esc_html__( 'No services yet.', 'agency-core' ) . '</p>'; return; }
	?>
	<div class="agency-content-table"><table><thead><tr><th><?php esc_html_e( 'Service', 'agency-core' ); ?></th><th><?php esc_html_e( 'Price', 'agency-core' ); ?></th><th><?php esc_html_e( 'Status', 'agency-core' ); ?></th><th><?php esc_html_e( 'Actions', 'agency-core' ); ?></th></tr></thead><tbody>
	<?php foreach ( $rows as $row ) : $price = get_post_meta( $row->ID, '_agency_price', true ); $currency = get_post_meta( $row->ID, '_agency_price_currency', true ) ?: 'HUF'; ?>
		<tr><td><strong><?php echo esc_html( get_the_title( $row ) ); ?></strong><br><small><?php echo esc_html( wp_trim_words( $row->post_excerpt ?: $row->post_content, 18 ) ); ?></small></td><td><?php echo '' !== $price ? esc_html( number_format_i18n( (float) $price, 0 ) . ' ' . $currency ) : '—'; ?></td><td><?php agency_core_content_render_status_badge( $row->post_status ); ?></td><td><a class="agency-client-button agency-client-button--ghost" href="<?php echo esc_url( add_query_arg( 'edit_id', absint( $row->ID ), $base ) ); ?>"><?php esc_html_e( 'Edit', 'agency-core' ); ?></a></td></tr>
	<?php endforeach; ?></tbody></table></div>
	<?php
}

function agency_core_client_portal_works() {
	if ( ! agency_core_content_manage_capability() ) { wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 ); }
	$editing = agency_core_content_get_edit_post( 'agency_portfolio' );
	$rows = agency_core_content_query( 'agency_portfolio' );
	$base = agency_core_client_admin_url( 'works' );
	?>
	<div class="agency-client-panel agency-content-panel"><div class="agency-content-panel__header"><div><h2><?php esc_html_e( 'Works', 'agency-core' ); ?></h2><p><?php esc_html_e( 'Manage references, portfolio entries and before/after work cards shown on the website.', 'agency-core' ); ?></p></div><?php if ( $editing ) : ?><a class="agency-client-button agency-client-button--ghost" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Add new work', 'agency-core' ); ?></a><?php endif; ?></div><?php agency_core_content_render_work_form( $editing ); ?></div>
	<div class="agency-client-panel agency-content-panel"><div class="agency-content-panel__header"><h2><?php esc_html_e( 'Existing works', 'agency-core' ); ?></h2><span><?php echo absint( count( $rows ) ); ?> <?php esc_html_e( 'items', 'agency-core' ); ?></span></div><?php agency_core_content_render_works_table( $rows, $base ); ?></div>
	<?php
}

function agency_core_content_render_work_form( $post = null ) {
	$post_id = $post ? absint( $post->ID ) : 0;
	$action  = agency_core_client_admin_action_url( 'agency_core_portal_work_save', 'works' );
	$status  = $post ? $post->post_status : 'publish';
	?>
	<form class="agency-content-form" method="post" action="<?php echo esc_url( $action ); ?>">
		<input type="hidden" name="agency_portal_action" value="agency_core_portal_work_save"><input type="hidden" name="item_id" value="<?php echo absint( $post_id ); ?>"><?php wp_nonce_field( 'agency_core_portal_work_save' ); ?>
		<div class="agency-content-grid">
			<label class="agency-content-field agency-content-field--wide"><span><?php esc_html_e( 'Work title', 'agency-core' ); ?></span><input required name="title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>"></label>
			<label><span><?php esc_html_e( 'Project type', 'agency-core' ); ?></span><input name="project_type" placeholder="Color, cut, branding..." value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_project_type', true ) : '' ); ?>"></label>
			<label><span><?php esc_html_e( 'Client / model', 'agency-core' ); ?></span><input name="project_client" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_project_client', true ) : '' ); ?>"></label>
			<label><span><?php esc_html_e( 'Year', 'agency-core' ); ?></span><input name="project_year" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_project_year', true ) : wp_date( 'Y' ) ); ?>"></label>
			<label><span><?php esc_html_e( 'Order', 'agency-core' ); ?></span><input type="number" name="menu_order" value="<?php echo esc_attr( $post ? $post->menu_order : 0 ); ?>"></label>
			<label><span><?php esc_html_e( 'Status', 'agency-core' ); ?></span><select name="status"><?php foreach ( agency_core_content_status_options() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="agency-content-field--wide"><span><?php esc_html_e( 'Image URL', 'agency-core' ); ?></span><input type="url" name="image_url" placeholder="https://..." value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, '_agency_image_url', true ) : '' ); ?>"></label>
			<label class="agency-content-field--wide"><span><?php esc_html_e( 'Short description', 'agency-core' ); ?></span><textarea required name="excerpt" rows="3"><?php echo esc_textarea( $post ? $post->post_excerpt : '' ); ?></textarea></label>
			<label class="agency-content-field--wide"><span><?php esc_html_e( 'Detailed story', 'agency-core' ); ?></span><textarea name="content" rows="6"><?php echo esc_textarea( $post ? $post->post_content : '' ); ?></textarea></label>
		</div>
		<div class="agency-content-actions"><button class="agency-client-button"><?php echo $post ? esc_html__( 'Save work', 'agency-core' ) : esc_html__( 'Create work', 'agency-core' ); ?></button><?php if ( $post ) : ?><button class="agency-content-delete" name="delete_item" value="1" onclick="return confirm('<?php echo esc_js( __( 'Move this work to Trash?', 'agency-core' ) ); ?>')"><?php esc_html_e( 'Delete', 'agency-core' ); ?></button><?php endif; ?></div>
	</form>
	<?php
}

function agency_core_content_render_works_table( $rows, $base ) {
	if ( ! $rows ) { echo '<p class="agency-client-empty">' . esc_html__( 'No works yet.', 'agency-core' ) . '</p>'; return; }
	?>
	<div class="agency-content-table"><table><thead><tr><th><?php esc_html_e( 'Work', 'agency-core' ); ?></th><th><?php esc_html_e( 'Type', 'agency-core' ); ?></th><th><?php esc_html_e( 'Status', 'agency-core' ); ?></th><th><?php esc_html_e( 'Actions', 'agency-core' ); ?></th></tr></thead><tbody>
	<?php foreach ( $rows as $row ) : ?><tr><td><strong><?php echo esc_html( get_the_title( $row ) ); ?></strong><br><small><?php echo esc_html( wp_trim_words( $row->post_excerpt ?: $row->post_content, 18 ) ); ?></small></td><td><?php echo esc_html( get_post_meta( $row->ID, '_agency_project_type', true ) ?: '—' ); ?></td><td><?php agency_core_content_render_status_badge( $row->post_status ); ?></td><td><a class="agency-client-button agency-client-button--ghost" href="<?php echo esc_url( add_query_arg( 'edit_id', absint( $row->ID ), $base ) ); ?>"><?php esc_html_e( 'Edit', 'agency-core' ); ?></a></td></tr><?php endforeach; ?>
	</tbody></table></div>
	<?php
}

function agency_core_content_save_post_from_portal( $post_type, $nonce_action, $redirect_tab ) {
	if ( ! agency_core_content_manage_capability() ) { wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 ); }
	check_admin_referer( $nonce_action );
	$item_id = absint( $_POST['item_id'] ?? 0 );
	$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
	$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'publish' ) );
	$status = in_array( $status, array_keys( agency_core_content_status_options() ), true ) ? $status : 'draft';
	if ( ! $title ) { wp_safe_redirect( add_query_arg( 'content_status', 'missing-title', agency_core_client_admin_url( $redirect_tab ) ) ); exit; }
	if ( ! $item_id ) {
		$item_id = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_excerpt' => sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ?? '' ) ), 'post_content' => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ), 'menu_order' => intval( $_POST['menu_order'] ?? 0 ) ) );
	} else {
		$post = get_post( $item_id );
		if ( ! $post || $post_type !== $post->post_type ) { wp_safe_redirect( add_query_arg( 'content_status', 'missing', agency_core_client_admin_url( $redirect_tab ) ) ); exit; }
		if ( ! empty( $_POST['delete_item'] ) ) { wp_trash_post( $item_id ); if ( function_exists( 'agency_core_audit_log' ) ) { agency_core_audit_log( 'content', 'trashed', array( 'post_type' => $post_type, 'post_id' => $item_id ) ); } wp_safe_redirect( add_query_arg( 'content_status', 'deleted', agency_core_client_admin_url( $redirect_tab ) ) ); exit; }
		wp_update_post( array( 'ID' => $item_id, 'post_status' => $status, 'post_title' => $title, 'post_excerpt' => sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ?? '' ) ), 'post_content' => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ), 'menu_order' => intval( $_POST['menu_order'] ?? 0 ) ) );
	}
	if ( is_wp_error( $item_id ) || ! $item_id ) { wp_safe_redirect( add_query_arg( 'content_status', 'error', agency_core_client_admin_url( $redirect_tab ) ) ); exit; }
	update_post_meta( $item_id, '_agency_image_url', esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) ) );
	if ( 'agency_service' === $post_type ) {
		$price = wp_unslash( $_POST['price'] ?? '' );
		update_post_meta( $item_id, '_agency_price', is_numeric( $price ) ? (string) (float) $price : '' );
		update_post_meta( $item_id, '_agency_price_currency', strtoupper( substr( sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'HUF' ) ), 0, 8 ) ) );
		update_post_meta( $item_id, '_agency_duration', sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) ) );
	}
	if ( 'agency_portfolio' === $post_type ) {
		update_post_meta( $item_id, '_agency_project_client', sanitize_text_field( wp_unslash( $_POST['project_client'] ?? '' ) ) );
		update_post_meta( $item_id, '_agency_project_year', sanitize_text_field( wp_unslash( $_POST['project_year'] ?? '' ) ) );
		update_post_meta( $item_id, '_agency_project_type', sanitize_text_field( wp_unslash( $_POST['project_type'] ?? '' ) ) );
	}
	if ( function_exists( 'agency_core_audit_log' ) ) { agency_core_audit_log( 'content', 'saved', array( 'post_type' => $post_type, 'post_id' => absint( $item_id ) ) ); }
	wp_safe_redirect( add_query_arg( 'content_status', 'saved', agency_core_client_admin_url( $redirect_tab ) ) ); exit;
}

function agency_core_portal_service_save() { agency_core_content_save_post_from_portal( 'agency_service', 'agency_core_portal_service_save', 'services' ); }
add_action( 'agency_client_portal_action_agency_core_portal_service_save', 'agency_core_portal_service_save' );
function agency_core_portal_work_save() { agency_core_content_save_post_from_portal( 'agency_portfolio', 'agency_core_portal_work_save', 'works' ); }
add_action( 'agency_client_portal_action_agency_core_portal_work_save', 'agency_core_portal_work_save' );