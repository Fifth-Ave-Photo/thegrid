<?php
/**
 * Fallback renderers for when the Grid Index Control plugin is not active.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a basic latest-posts grid as a fallback for plugin-driven sections.
 */
function gip_render_homepage_feed_fallback() {
	if ( gip_has_control_plugin() ) {
		// Plugin will render its own widget via the same hook.
		return;
	}

	$query = new WP_Query( array(
		'post_type'           => 'post',
		'posts_per_page'      => 9,
		'ignore_sticky_posts' => true,
	) );

	if ( ! $query->have_posts() ) {
		echo '<p>' . esc_html__( 'No posts yet.', 'the-grid-index' ) . '</p>';
		return;
	}

	echo '<div class="gip-fallback-grid">';
	while ( $query->have_posts() ) {
		$query->the_post();
		echo '<article class="gip-card">';
		if ( has_post_thumbnail() ) {
			echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( null, 'gip-card', array( 'loading' => 'lazy' ) ) . '</a>';
		}
		echo '<h3 class="gip-card__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
		echo '<p class="gip-card__excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), 24 ) ) . '</p>';
		echo '</article>';
	}
	wp_reset_postdata();
	echo '</div>';
}
add_action( 'gip_render_homepage_feed', 'gip_render_homepage_feed_fallback', 20 );

/**
 * Render a quiet placeholder for the breaking ticker when no plugin is present.
 */
function gip_render_ticker_fallback() {
	if ( gip_has_control_plugin() ) {
		return;
	}
	// No upsell messaging — render nothing if no live ticker source is available.
	return;
}
add_action( 'gip_render_ticker', 'gip_render_ticker_fallback', 20 );
