<?php
/**
 * Not found template.
 *
 * @package Agency_Theme
 */

get_header();
?>
<section class="agency-section agency-empty-state">
	<div class="agency-container agency-container--narrow">
		<p class="agency-eyebrow"><?php esc_html_e( 'Error 404', 'agency-theme' ); ?></p>
		<h1><?php esc_html_e( 'This page has wandered off.', 'agency-theme' ); ?></h1>
		<p class="agency-muted"><?php esc_html_e( 'The address may have changed, or the page may no longer exist.', 'agency-theme' ); ?></p>
		<a class="agency-button agency-button--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to home', 'agency-theme' ); ?>
		</a>
	</div>
</section>
<?php
get_footer();

