<?php
/**
 * Manifest-based, idempotent blueprint importer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_get_blueprints() {
	$base       = AGENCY_CORE_PATH . 'blueprints/';
	$blueprints = array();

	foreach ( (array) scandir( $base ) as $directory ) {
		if ( '.' === $directory || '..' === $directory || sanitize_key( $directory ) !== $directory ) {
			continue;
		}
		$manifest = agency_core_read_json_file( $base . $directory . '/manifest.json' );
		if ( is_wp_error( $manifest ) || ( $manifest['slug'] ?? '' ) !== $directory ) {
			continue;
		}
		$blueprints[ $directory ] = $manifest;
	}
	return $blueprints;
}

function agency_core_render_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'agency-core' ) );
	}
	$blueprints = agency_core_get_blueprints();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import Blueprint', 'agency-core' ); ?></h1>
		<p><?php esc_html_e( 'Choose a site recipe. Matching slugs are updated, so every blueprint can be imported repeatedly without duplicates.', 'agency-core' ); ?></p>
		<?php if ( isset( $_GET['agency_import'] ) ) : ?>
			<?php $status = sanitize_key( wp_unslash( $_GET['agency_import'] ) ); ?>
			<div class="notice notice-<?php echo 'success' === $status ? 'success' : 'error'; ?> is-dismissible"><p>
				<?php echo 'success' === $status ? esc_html__( 'Blueprint imported successfully.', 'agency-core' ) : esc_html__( 'The blueprint import failed. Verify its manifest and JSON files.', 'agency-core' ); ?>
			</p></div>
		<?php endif; ?>
		<div class="agency-core-blueprints">
			<?php foreach ( $blueprints as $slug => $manifest ) : ?>
				<div class="card">
					<h2><?php echo esc_html( $manifest['name'] ?? $slug ); ?></h2>
					<p><?php echo esc_html( $manifest['description'] ?? '' ); ?></p>
					<p><code><?php echo esc_html( $slug ); ?></code> · v<?php echo esc_html( $manifest['version'] ?? '1.0.0' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="agency_core_import_blueprint">
						<input type="hidden" name="blueprint" value="<?php echo esc_attr( $slug ); ?>">
						<?php wp_nonce_field( 'agency_core_import_blueprint_' . $slug, 'agency_core_import_nonce' ); ?>
						<?php submit_button( sprintf( __( 'Import %s', 'agency-core' ), $manifest['name'] ?? $slug ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

function agency_core_handle_blueprint_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to import blueprints.', 'agency-core' ) );
	}
	$blueprint = isset( $_POST['blueprint'] ) ? sanitize_key( wp_unslash( $_POST['blueprint'] ) ) : '';
	check_admin_referer( 'agency_core_import_blueprint_' . $blueprint, 'agency_core_import_nonce' );

	$result = agency_core_import_blueprint( $blueprint );
	$status = is_wp_error( $result ) ? 'error' : 'success';
	wp_safe_redirect(
		add_query_arg(
			array( 'agency_import' => $status, 'blueprint' => $blueprint ),
			admin_url( 'admin.php?page=agency-core-import' )
		)
	);
	exit;
}
add_action( 'admin_post_agency_core_import_blueprint', 'agency_core_handle_blueprint_import' );

function agency_core_import_blueprint( $blueprint ) {
	$blueprints = agency_core_get_blueprints();
	$blueprint  = sanitize_key( $blueprint );
	if ( ! isset( $blueprints[ $blueprint ] ) ) {
		return new WP_Error( 'agency_core_unknown_blueprint', __( 'Unknown blueprint.', 'agency-core' ) );
	}

	$manifest  = $blueprints[ $blueprint ];
	$directory = AGENCY_CORE_PATH . 'blueprints/' . $blueprint . '/';
	$file_map  = isset( $manifest['files'] ) && is_array( $manifest['files'] ) ? $manifest['files'] : array();
	$data      = array();

	foreach ( $file_map as $data_key => $filename ) {
		if ( sanitize_file_name( $filename ) !== $filename ) {
			return new WP_Error( 'agency_core_invalid_filename', __( 'The blueprint manifest contains an invalid filename.', 'agency-core' ) );
		}
		$data[ $data_key ] = agency_core_read_json_file( $directory . $filename );
		if ( is_wp_error( $data[ $data_key ] ) ) {
			return $data[ $data_key ];
		}
	}
	if ( empty( $data['config'] ) || empty( $data['pages'] ) ) {
		return new WP_Error( 'agency_core_incomplete_blueprint', __( 'The blueprint must define config and pages files.', 'agency-core' ) );
	}

	update_option( 'agency_core_settings', agency_core_sanitize_settings( $data['config'] ) );

	$page_ids = array();
	foreach ( $data['pages'] as $page ) {
		$post_id = agency_core_upsert_content( $page, 'page', $directory, $blueprint );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$page_ids[ sanitize_key( $page['slug'] ?? '' ) ] = $post_id;
		if ( isset( $page['sections'] ) ) {
			update_post_meta( $post_id, '_agency_sections', agency_core_sanitize_sections_meta( $page['sections'] ) );
		}
	}

	$home_slug = sanitize_key( $manifest['front_page'] ?? '' );
	$blog_slug = sanitize_key( $manifest['posts_page'] ?? '' );
	if ( ! empty( $page_ids[ $home_slug ] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_ids[ $home_slug ] );
	}
	if ( ! empty( $page_ids[ $blog_slug ] ) ) {
		update_option( 'page_for_posts', $page_ids[ $blog_slug ] );
	}

	$content_types = isset( $manifest['content_types'] ) && is_array( $manifest['content_types'] ) ? $manifest['content_types'] : array();
	foreach ( $content_types as $data_key => $post_type ) {
		if ( empty( $data[ $data_key ] ) || ! post_type_exists( $post_type ) ) {
			continue;
		}
		foreach ( $data[ $data_key ] as $item ) {
			$result = agency_core_upsert_content( $item, sanitize_key( $post_type ), $directory, $blueprint );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
	}

	$menu_result = agency_core_upsert_primary_menu( $page_ids, $data['pages'], $manifest['menu_name'] ?? 'Agency Primary' );
	if ( is_wp_error( $menu_result ) ) {
		return $menu_result;
	}
	update_option( 'agency_core_last_blueprint', $blueprint, false );
	flush_rewrite_rules();
	return array( 'pages' => $page_ids, 'menu_id' => $menu_result );
}

function agency_core_upsert_content( $item, $post_type, $blueprint_directory = '', $blueprint = '' ) {
	$slug = sanitize_title( $item['slug'] ?? $item['title'] ?? '' );
	if ( ! $slug || ( ! post_type_exists( $post_type ) && 'page' !== $post_type ) ) {
		return new WP_Error( 'agency_core_invalid_item', __( 'A blueprint item is missing a valid title or post type.', 'agency-core' ) );
	}

	$existing = get_page_by_path( $slug, OBJECT, $post_type );
	$postarr   = array(
		'post_type'    => $post_type,
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => sanitize_text_field( $item['title'] ?? '' ),
		'post_content' => wp_kses_post( $item['content'] ?? '' ),
		'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ?? '' ),
		'menu_order'   => isset( $item['menu_order'] ) ? absint( $item['menu_order'] ) : 0,
	);
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$post_id       = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
	}
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	if ( isset( $item['price'] ) ) {
		update_post_meta( $post_id, '_agency_price', sanitize_text_field( $item['price'] ) );
	}
	if ( isset( $item['price_currency'] ) ) {
		update_post_meta( $post_id, '_agency_price_currency', sanitize_text_field( $item['price_currency'] ) );
	}
	if ( ! empty( $item['featured_image'] ) && $blueprint_directory ) {
		$image_result = agency_core_import_local_image( $post_id, $item['featured_image'], $blueprint_directory, $blueprint );
		if ( is_wp_error( $image_result ) ) {
			return $image_result;
		}
	}
	return $post_id;
}

function agency_core_import_local_image( $post_id, $relative_file, $blueprint_directory, $blueprint ) {
	$relative_file = ltrim( wp_normalize_path( sanitize_text_field( $relative_file ) ), '/' );
	if ( str_contains( $relative_file, '..' ) ) {
		return new WP_Error( 'agency_core_invalid_image_path', __( 'Invalid blueprint image path.', 'agency-core' ) );
	}
	$source = realpath( $blueprint_directory . $relative_file );
	$base   = realpath( $blueprint_directory );
	if ( ! $source || ! $base || ! str_starts_with( wp_normalize_path( $source ), trailingslashit( wp_normalize_path( $base ) ) ) ) {
		return new WP_Error( 'agency_core_missing_image', __( 'A blueprint image is missing.', 'agency-core' ) );
	}

	$fingerprint = sanitize_key( $blueprint ) . ':' . $relative_file;
	if ( $fingerprint === get_post_meta( $post_id, '_agency_blueprint_image', true ) && has_post_thumbnail( $post_id ) ) {
		return get_post_thumbnail_id( $post_id );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$temp_file = wp_tempnam( basename( $source ) );
	if ( ! $temp_file || ! copy( $source, $temp_file ) ) {
		return new WP_Error( 'agency_core_image_copy_failed', __( 'The blueprint image could not be staged.', 'agency-core' ) );
	}
	$file_array = array( 'name' => sanitize_file_name( basename( $source ) ), 'tmp_name' => $temp_file );
	$attachment = media_handle_sideload( $file_array, $post_id, get_the_title( $post_id ) );
	if ( is_wp_error( $attachment ) ) {
		@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $attachment;
	}
	set_post_thumbnail( $post_id, $attachment );
	update_post_meta( $post_id, '_agency_blueprint_image', $fingerprint );
	return $attachment;
}

function agency_core_upsert_primary_menu( $page_ids, $pages, $menu_name = 'Agency Primary' ) {
	$menu_name = sanitize_text_field( $menu_name );
	$menu      = wp_get_nav_menu_object( $menu_name );
	$menu_id   = $menu ? $menu->term_id : wp_create_nav_menu( $menu_name );

	if ( is_wp_error( $menu_id ) ) {
		return $menu_id;
	}

	$existing_items = wp_get_nav_menu_items( $menu_id );
	$by_object_id   = array();
	foreach ( (array) $existing_items as $existing_item ) {
		$by_object_id[ (int) $existing_item->object_id ] = (int) $existing_item->ID;
	}

	foreach ( $pages as $page ) {
		$slug = sanitize_key( $page['slug'] ?? '' );
		if ( empty( $page_ids[ $slug ] ) || isset( $page['in_menu'] ) && ! $page['in_menu'] ) {
			continue;
		}
		$page_id = $page_ids[ $slug ];
		wp_update_nav_menu_item(
			$menu_id,
			$by_object_id[ $page_id ] ?? 0,
			array(
				'menu-item-title'     => sanitize_text_field( $page['title'] ?? '' ),
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => isset( $page['menu_order'] ) ? absint( $page['menu_order'] ) + 1 : 0,
			)
		);
	}

	$locations  = get_theme_mod( 'nav_menu_locations', array() );
	$registered = get_registered_nav_menus();
	if ( isset( $registered['primary'] ) ) {
		$locations['primary'] = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
	return $menu_id;
}

