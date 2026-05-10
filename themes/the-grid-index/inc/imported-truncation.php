<?php
/**
 * The Grid Index — Imported article truncation.
 *
 * RSS-imported posts arrive with the full feed body (often 1500+ words)
 * which makes single pages enormously long and creates copyright exposure
 * since the content is being republished verbatim. Editorial aggregators
 * (Drudge, Memeorandum, Techmeme variants) instead show a short excerpt
 * with a clear CTA back to the source.
 *
 * This module:
 *   - Hooks the_content
 *   - Detects single-post views of RSS-imported posts (via the
 *     _gridindex_source_url meta or gip_is_imported_rss helper)
 *   - Trims the rendered body to roughly the first 3 paragraphs
 *   - Appends a "Continue reading at [Source] →" link to the
 *     truncation point (separate from the larger CTA box that
 *     story-dossier already renders)
 *
 * Original content stays untouched in the database. Only display is
 * filtered. To opt a single post out, add custom field
 * gip_show_full_content = 1 to that post.
 *
 * @package The_Grid_Index
 * @since   1.10.42
 */

defined( 'ABSPATH' ) || exit;

const GIP_TRUNCATE_PARAGRAPHS = 3;

/**
 * Truncate the_content for RSS-imported single posts.
 */
function gip_truncate_imported_content( $content ) {
	// Bail outside the main content render.
	if ( is_admin() ) return $content;
	if ( ! is_singular( 'post' ) ) return $content;
	if ( ! in_the_loop() ) return $content;

	$post_id = get_the_ID();
	if ( ! $post_id ) return $content;

	// Only trim posts marked as imported.
	$is_imported = function_exists( 'gip_is_imported_rss' )
		? gip_is_imported_rss( $post_id )
		: (bool) get_post_meta( $post_id, '_gridindex_source_url', true );
	if ( ! $is_imported ) return $content;

	// Per-post opt-out: set custom field gip_show_full_content = 1
	if ( get_post_meta( $post_id, 'gip_show_full_content', true ) ) return $content;

	$truncated = gip_trim_to_n_paragraphs( $content, GIP_TRUNCATE_PARAGRAPHS );
	if ( $truncated === $content ) return $content; // already short, no change

	// Append a small inline CTA pointing back to the source.
	$source_url  = (string) get_post_meta( $post_id, '_gridindex_source_url', true );
	$source_name = (string) get_post_meta( $post_id, '_gridindex_source_name', true );
	if ( ! $source_name ) $source_name = 'the original source';

	if ( $source_url ) {
		$truncated .= sprintf(
			'<p class="gip-truncated-cta"><a href="%1$s" rel="noopener" target="_blank">%2$s</a></p>',
			esc_url( $source_url ),
			sprintf(
				/* translators: %s: source name */
				esc_html__( 'Continue reading at %s →', 'the-grid-index' ),
				esc_html( $source_name )
			)
		);
	}

	return $truncated;
}
add_filter( 'the_content', 'gip_truncate_imported_content', 8 );

/**
 * Take HTML and return only the first N top-level <p> blocks.
 * Falls back to a wp_trim_words style cut if the content has no
 * <p> structure (rare for feed content but possible).
 */
function gip_trim_to_n_paragraphs( $html, $n ) {
	if ( ! $html || ! is_string( $html ) ) return $html;

	// Quick path: pattern-match top-level <p>...</p> blocks. This is
	// good enough for feed-imported content which is overwhelmingly
	// flat <p> sequences with embedded <a>/<em>/<strong>.
	if ( preg_match_all( '#<p\b[^>]*>.*?</p>#is', $html, $matches ) ) {
		$paragraphs = $matches[0];
		// Skip leading empty/whitespace-only paragraphs that sometimes
		// arrive from feed content.
		$paragraphs = array_values( array_filter( $paragraphs, function( $p ) {
			$txt = trim( wp_strip_all_tags( $p ) );
			return $txt !== '';
		} ) );
		if ( count( $paragraphs ) <= $n ) {
			// Already short enough — return original (caller will short-circuit).
			return $html;
		}
		return implode( "\n", array_slice( $paragraphs, 0, $n ) );
	}

	// Fallback: word-count based trim
	$words = 80;
	return wpautop( wp_trim_words( wp_strip_all_tags( $html ), $words, '&hellip;' ) );
}

/**
 * Style the inline truncation CTA so it reads as deliberate editorial
 * chrome rather than a default link. Attached to the main theme
 * stylesheet handle for proper asset queue integration.
 */
function gip_truncate_cta_styles() {
	if ( is_admin() ) return;
	if ( ! is_singular( 'post' ) ) return;

	$css = '
		.gip-truncated-cta {
			margin-top: 28px !important;
			padding-top: 20px;
			border-top: 1px solid var(--gi-border, #334155);
			font: 600 13px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			letter-spacing: 0.02em;
		}
		.gip-truncated-cta a {
			color: var(--gi-accent, #14b8a6) !important;
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 10px 16px;
			border: 1px solid var(--gi-accent, #14b8a6);
			border-radius: 6px;
			transition: background .15s ease, border-color .15s ease;
		}
		.gip-truncated-cta a:hover {
			background: rgba(20, 184, 166, 0.12);
			border-color: var(--gi-accent-hover, #0d9488);
		}
	';
	wp_add_inline_style( 'the-grid-index', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_truncate_cta_styles', 20 );
