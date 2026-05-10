<?php
/**
 * The Grid Index — Meta description for SEO.
 *
 * Outputs a <meta name="description"> tag in the document head, derived
 * from the most appropriate source for each page type:
 *
 *   - Single post:  excerpt (or generated from content)
 *   - Page:         excerpt (or generated from content)
 *   - Category:     category description
 *   - Tag:          tag description
 *   - Search:       generic "Search results for [query]"
 *   - 404:          generic
 *   - Front page:   site tagline (blogdescription option)
 *
 * Defers to any active SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress)
 * by checking for their telltale filters/functions and skipping output
 * so we don't duplicate.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

function gip_meta_description() {
	// Defer to any active SEO plugin.
	if (
		defined( 'WPSEO_VERSION' )                  // Yoast SEO
		|| defined( 'RANK_MATH_VERSION' )           // Rank Math
		|| defined( 'AIOSEO_VERSION' )              // All in One SEO
		|| defined( 'SEOPRESS_VERSION' )            // SEOPress
		|| function_exists( 'rank_math' )
	) {
		return;
	}

	$desc = '';

	if ( is_front_page() || is_home() ) {
		$desc = get_bloginfo( 'description', 'display' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post ) {
			if ( ! empty( $post->post_excerpt ) ) {
				$desc = $post->post_excerpt;
			} else {
				$desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '...' );
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term ) {
			$desc = ! empty( $term->description )
				? wp_strip_all_tags( $term->description )
				: sprintf(
					/* translators: %s: term name */
					esc_html__( 'Stories and analysis from %s on The Grid Index.', 'the-grid-index' ),
					$term->name
				);
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();
		if ( $author ) {
			$desc = get_the_author_meta( 'description', $author->ID );
			if ( empty( $desc ) ) {
				$desc = sprintf(
					/* translators: %s: author display name */
					esc_html__( 'Stories by %s on The Grid Index.', 'the-grid-index' ),
					$author->display_name
				);
			}
		}
	} elseif ( is_search() ) {
		$desc = sprintf(
			/* translators: %s: search query */
			esc_html__( 'Search results for "%s" on The Grid Index.', 'the-grid-index' ),
			get_search_query()
		);
	} elseif ( is_404() ) {
		$desc = esc_html__( 'Page not found on The Grid Index.', 'the-grid-index' );
	} elseif ( is_archive() ) {
		$desc = get_the_archive_description();
		if ( empty( $desc ) ) {
			$desc = get_bloginfo( 'description', 'display' );
		}
		$desc = wp_strip_all_tags( $desc );
	}

	// Fallback to site tagline.
	if ( empty( $desc ) ) {
		$desc = get_bloginfo( 'description', 'display' );
	}

	// Trim, normalise, hard-cap at 160 chars (typical SERP cap).
	$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
	if ( strlen( $desc ) > 160 ) {
		$desc = mb_substr( $desc, 0, 157 ) . '...';
	}

	if ( $desc !== '' ) {
		echo "\t" . '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'gip_meta_description', 2 );
