<?php
/**
 * Standalone blueprint contract test; WordPress is not required.
 */

$base       = dirname( __DIR__ ) . '/wp-content/plugins/agency-core/blueprints';
$components = array(
	'hero'     => array( 'split', 'centered', 'image-card', 'minimal' ),
	'services' => array( 'grid', 'cards', 'icon-list', 'featured' ),
	'cta'      => array( 'simple', 'boxed', 'full-width', 'booking' ),
	'faq'      => array( 'accordion' ),
	'blog'     => array( 'grid' ),
	'contact'  => array( 'section' ),
);
$failures = array();

foreach ( glob( $base . '/*/manifest.json' ) as $manifest_file ) {
	$manifest = json_decode( file_get_contents( $manifest_file ), true );
	$folder   = basename( dirname( $manifest_file ) );
	if ( ! is_array( $manifest ) || ( $manifest['slug'] ?? '' ) !== $folder ) {
		$failures[] = $folder . ': invalid manifest or slug mismatch';
		continue;
	}
	foreach ( array( 'name', 'version', 'front_page', 'posts_page', 'files', 'content_types' ) as $required ) {
		if ( empty( $manifest[ $required ] ) ) {
			$failures[] = $folder . ': missing manifest key ' . $required;
		}
	}

	$data = array();
	foreach ( (array) ( $manifest['files'] ?? array() ) as $key => $filename ) {
		$path = dirname( $manifest_file ) . '/' . basename( $filename );
		if ( ! is_file( $path ) ) {
			$failures[] = $folder . ': missing ' . $filename;
			continue;
		}
		$data[ $key ] = json_decode( file_get_contents( $path ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data[ $key ] ) ) {
			$failures[] = $folder . ': invalid JSON in ' . $filename;
		}
	}

	$slugs = array();
	foreach ( (array) ( $data['pages'] ?? array() ) as $page ) {
		if ( empty( $page['title'] ) || empty( $page['slug'] ) ) {
			$failures[] = $folder . ': page missing title or slug';
		}
		if ( isset( $slugs[ $page['slug'] ] ) ) {
			$failures[] = $folder . ': duplicate page slug ' . $page['slug'];
		}
		$slugs[ $page['slug'] ] = true;
		foreach ( (array) ( $page['sections'] ?? array() ) as $section ) {
			$component = $section['component'] ?? '';
			$variant   = $section['variant'] ?? '';
			if ( ! $variant && str_contains( $component, '/' ) ) {
				$parts     = explode( '/', $component, 2 );
				$component = $parts[0];
				$variant   = str_starts_with( $parts[1], $component . '-' ) ? substr( $parts[1], strlen( $component ) + 1 ) : $parts[1];
			}
			if ( ! isset( $components[ $component ] ) || ! in_array( $variant, $components[ $component ], true ) ) {
				$failures[] = $folder . ': unknown component/variant ' . $component . '/' . $variant;
			}
		}
	}
	foreach ( array( $manifest['front_page'] ?? '', $manifest['posts_page'] ?? '' ) as $required_slug ) {
		if ( ! isset( $slugs[ $required_slug ] ) ) {
			$failures[] = $folder . ': configured page slug not found: ' . $required_slug;
		}
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}
echo 'Blueprint contracts OK' . PHP_EOL;
