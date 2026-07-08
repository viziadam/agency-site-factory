<?php
/**
 * Single post template.
 *
 * @package Agency_Theme
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'agency-section agency-entry' ); ?>>
		<div class="agency-container agency-container--narrow">
			<?php get_template_part( 'template-parts/layout/breadcrumbs' ); ?>
			<p class="agency-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="agency-entry__image"><?php the_post_thumbnail( 'agency-hero' ); ?></figure>
			<?php endif; ?>
			<div class="agency-prose"><?php the_content(); ?></div>
		</div>
	</article>
	<?php get_template_part( 'template-parts/blog/related-posts', null, array( 'data' => array( 'post_id' => get_the_ID() ) ) ); ?>
	<?php
endwhile;

get_footer();

