<?php
/**
 * Page template.
 *
 * @package Agency_Theme
 */

get_header();

while ( have_posts() ) :
	the_post();
	$sections = agency_theme_get_sections( get_the_ID() );
	if ( $sections ) {
		agency_theme_render_sections( $sections );
	} else {
		?>
		<article <?php post_class( 'agency-section agency-entry' ); ?>>
			<div class="agency-container agency-container--narrow">
				<?php get_template_part( 'template-parts/layout/breadcrumbs' ); ?>
				<h1><?php the_title(); ?></h1>
				<div class="agency-prose"><?php the_content(); ?></div>
			</div>
		</article>
		<?php
	}
endwhile;

get_footer();

