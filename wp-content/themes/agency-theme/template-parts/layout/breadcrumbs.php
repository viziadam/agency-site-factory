<?php
/**
 * Lightweight breadcrumbs.
 *
 * @package Agency_Theme
 */

if ( is_front_page() ) {
	return;
}
?>
<nav class="agency-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'agency-theme' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'agency-theme' ); ?></a>
	<span aria-hidden="true">/</span>
	<span aria-current="page"><?php echo esc_html( wp_get_document_title() ); ?></span>
</nav>
