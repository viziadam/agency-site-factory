<?php
/**
 * Static contract for module setup orchestration.
 */

$root  = dirname( __DIR__ );
$core  = (string) file_get_contents( $root . '/wp-content/plugins/agency-core/includes/module-setup.php' );
$mods  = (string) file_get_contents( $root . '/wp-content/plugins/agency-core/includes/modules.php' );
$cli   = (string) file_get_contents( $root . '/wp-content/plugins/agency-core/includes/cli.php' );
$read_plugin = static function ( string $module ) use ( $root ): string {
	$base   = $root . "/wp-content/plugins/agency-module-{$module}";
	$source = '';
	$files  = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $files as $file ) {
		if ( 'php' === strtolower( $file->getExtension() ) ) {
			$source .= "\n" . (string) file_get_contents( $file->getPathname() );
		}
	}
	return $source;
};
$auth  = $read_plugin( 'auth' );
$book  = $read_plugin( 'booking' );
$legal = $read_plugin( 'legal' );
$failures = array();

foreach ( array( 'agency_core_get_module_setup_status', 'agency_core_apply_module_setup', 'agency_core_provision_module_page', 'agency_core_get_email_health', 'auto_create_required_pages', 'auto_add_pages_to_menu', 'auto_add_booking_cta', 'auto_add_auth_links', 'auto_add_footer_legal_links' ) as $contract ) {
	if ( ! str_contains( $core . $auth . $book . $legal, $contract ) ) {
		$failures[] = "Missing setup contract: {$contract}";
	}
}
foreach ( array( 'bejelentkezes', 'regisztracio', 'fiokom', 'jelszo-visszaallitas', 'email-megerosites', 'idopontfoglalas', 'foglalasaim', 'foglalas-koszonjuk', 'hirlevel', 'adatkezelesi-tajekoztato', 'cookie-tajekoztato' ) as $slug ) {
	if ( ! str_contains( $core, $slug ) ) {
		$failures[] = "Missing provisioned slug: {$slug}";
	}
}
if ( ! str_contains( $mods, "'auth', 'legal', 'email-service'" ) || ! str_contains( $mods, 'Disable Booking first' ) ) {
	$failures[] = 'Booking dependency enforcement contract is missing.';
}
if ( ! str_contains( $auth, "'login'" ) || ! str_contains( $auth, "'register'" ) || ! str_contains( $auth, "'account'" ) ) {
	$failures[] = 'Auth component variants are missing.';
}
if ( ! str_contains( $book, "'customer-list'" ) || ! str_contains( $book, 'agency_booking_manager_portal' ) || ! str_contains( $legal, "'links'" ) ) {
	$failures[] = 'Booking or Legal component variant missing.';
}
if ( ! str_contains( $cli, 'agency module setup' ) || ! str_contains( $cli, 'agency module status' ) ) {
	$failures[] = 'Module setup CLI commands are missing.';
}
if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}
echo 'Module setup contract passed: dependencies, pages, settings, components, health and CLI are present.' . PHP_EOL;
