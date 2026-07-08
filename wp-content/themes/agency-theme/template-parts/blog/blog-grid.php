<?php
/**
 * Blog grid component.
 *
 * @package Agency_Theme
 */

$data           = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title          = $data['title'] ?? '';
$use_main_query = ! empty( $data['use_main_query'] );
$query          = $use_main_query ? $GLOBALS['wp_query'] : new WP_Query(
	array(
		'post_type'      => 'post',
		'posts_per_page' => isset( $data['limit'] ) ? absint( $data['limit'] ) : 6,
		'post_status'    => 'publish',
	)
);
?>
<?php if ( $title ) : ?><h2 class="agency-blog-grid__title"><?php echo esc_html( $title ); ?></h2><?php endif; ?>
<div class="agency-grid agency-grid--three agency-blog-grid">
	<?php if ( $query->have_posts() ) : ?>
		<?php while ( $query->have_posts() ) : $query->the_post(); ?>
			<article <?php post_class( 'agency-card agency-post-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a class="agency-post-card__image" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'agency-card' ); ?></a>
				<?php endif; ?>
				<p class="agency-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p class="agency-muted"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p class="agency-muted"><?php esc_html_e( 'No posts found.', 'agency-theme' ); ?></p>
	<?php endif; ?>
</div>
<?php if ( ! $use_main_query ) { wp_reset_postdata(); } ?>
