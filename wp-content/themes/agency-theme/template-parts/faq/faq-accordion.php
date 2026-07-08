<?php
/**
 * FAQ accordion component.
 *
 * @package Agency_Theme
 */

$data  = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$title = $data['title'] ?? __( 'Frequently asked questions', 'agency-theme' );
$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
$limit = isset( $data['limit'] ) ? absint( $data['limit'] ) : 8;

if ( ! $items && post_type_exists( 'agency_faq' ) ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'agency_faq',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
		)
	);
	foreach ( $query->posts as $faq ) {
		$items[] = array( 'question' => get_the_title( $faq ), 'answer' => apply_filters( 'the_content', $faq->post_content ) );
	}
	wp_reset_postdata();
}
?>
<section class="agency-section agency-faq">
	<div class="agency-container agency-container--narrow">
		<?php if ( $title ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
		<div class="agency-faq__list">
			<?php foreach ( $items as $index => $item ) : ?>
				<details class="agency-faq__item"<?php echo 0 === $index ? ' open' : ''; ?>>
					<summary><?php echo esc_html( $item['question'] ?? '' ); ?></summary>
					<div class="agency-faq__answer"><?php echo wp_kses_post( wpautop( $item['answer'] ?? '' ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

