<?php
/**
 * Single post — Grid Index Story Dossier (classic template fallback).
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) : the_post();
	if ( function_exists( 'gip_render_story_dossier' ) ) {
		echo '<main id="gi-main" role="main">' . gip_render_story_dossier( get_the_ID() ) . '</main>';
	} else {
		echo '<main><article>' . apply_filters( 'the_content', get_the_content() ) . '</article></main>';
	}
	wp_link_pages( array(
		'before' => '<nav class="gi-page__pages" aria-label="' . esc_attr__( 'Page', 'the-grid-index' ) . '"><span>' . esc_html__( 'Pages:', 'the-grid-index' ) . '</span>',
		'after'  => '</nav>',
	) );
endwhile;

get_footer();
