<?php
/**
 * Grid Index source-attribution helpers.
 *
 * Exposes a single source of truth for the original publisher of an
 * imported RSS post. Reads the canonical post-meta keys written by
 * the Grid Index Control / WP-Sync importer, with safe fallbacks so
 * older posts and non-imported posts still behave sensibly.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical meta keys.
 */
if ( ! defined( 'GIP_META_SOURCE_URL' ) )  define( 'GIP_META_SOURCE_URL', '_gridindex_source_url' );
if ( ! defined( 'GIP_META_SOURCE_NAME' ) ) define( 'GIP_META_SOURCE_NAME', '_gridindex_source_name' );

/**
 * Resolve the original source URL for a post.
 *
 * Order of resolution:
 *  1. _gridindex_source_url meta
 *  2. legacy _source_url / source_url meta (older importers)
 *  3. filter `gip_source_url`
 */
function gip_get_source_url( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) return '';
	$url = get_post_meta( $post_id, GIP_META_SOURCE_URL, true );
	if ( ! $url ) $url = get_post_meta( $post_id, 'gi_source_url', true );
	if ( ! $url ) $url = get_post_meta( $post_id, '_gridindex_original_url', true );
	if ( ! $url ) $url = get_post_meta( $post_id, 'original_url', true );
	if ( ! $url ) $url = get_post_meta( $post_id, '_source_url', true );
	if ( ! $url ) $url = get_post_meta( $post_id, 'source_url', true );
	$url = apply_filters( 'gip_source_url', $url, $post_id );
	return $url ? esc_url_raw( $url ) : '';
}

/**
 * Resolve the source name (e.g. "TechCrunch") for a post.
 */
function gip_get_source_name( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) return '';
	$name = get_post_meta( $post_id, GIP_META_SOURCE_NAME, true );
	if ( ! $name ) $name = get_post_meta( $post_id, 'gi_source_name', true );
	if ( ! $name ) $name = get_post_meta( $post_id, '_source_name', true );
	if ( ! $name ) $name = get_post_meta( $post_id, 'source_name', true );
	if ( ! $name ) $name = get_post_meta( $post_id, 'gi_first_reported_source', true );
	if ( ! $name ) {
		$src = gip_get_source_url( $post_id );
		if ( $src ) {
			$host = wp_parse_url( $src, PHP_URL_HOST );
			if ( $host ) $name = preg_replace( '/^www\./', '', $host );
		}
	}
	return apply_filters( 'gip_source_name', $name, $post_id );
}

/**
 * Is this an imported RSS post (i.e. it has an external source URL)?
 */
function gip_is_imported_rss( $post_id = 0 ) {
	return (bool) gip_get_source_url( $post_id );
}

/**
 * Resolve the click target for a post's card title / image / card chrome.
 *
 * Policy (v1.10.3+): cards ALWAYS open the internal WordPress post page.
 * Only the explicit "Read at [Source]" CTA opens the external source URL.
 * The legacy `article_click_behavior` option is ignored to enforce this.
 */
function gip_resolve_card_link( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	return get_permalink( $post_id );
}

/**
 * Card title/image link target — always internal, so never new-tab.
 */
function gip_card_link_target( $post_id = 0 ) {
	return '';
}

/**
 * Admin-only debug comment for card link audit.
 */
function gip_card_debug_comment( $post_id = 0 ) {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$post_id = $post_id ?: get_the_ID();
	$perm    = get_permalink( $post_id );
	$src     = gip_get_source_url( $post_id );
	echo "\n<!-- internal permalink: " . esc_html( $perm ) . " -->";
	echo "\n<!-- source url: " . esc_html( $src ?: '(none)' ) . " -->";
	echo "\n<!-- card click target: internal -->\n";
}

/**
 * Render the always-visible "Read at [Source]" button for cards.
 */
function gip_render_source_button( $post_id = 0, $class = 'gi-source-btn' ) {
	$post_id = $post_id ?: get_the_ID();
	$show    = function_exists( 'gridindex_get_option' )
		? (int) gridindex_get_option( 'card_show_read_src', 1 )
		: 1;
	if ( ! $show ) return;
	$url  = gip_get_source_url( $post_id );
	$name = gip_get_source_name( $post_id );

	if ( $url ) {
		$label = $name
			? sprintf( /* translators: %s: source name */ __( 'Read at %s', 'the-grid-index' ), $name )
			: __( 'Read at source', 'the-grid-index' );
		printf(
			'<a class="%1$s %1$s--source" href="%2$s" target="_blank" rel="noopener noreferrer nofollow">%3$s <span aria-hidden="true">→</span></a>',
			esc_attr( $class ),
			esc_url( $url ),
			esc_html( $label )
		);
	} else {
		printf(
			'<a class="%1$s" href="%2$s">%3$s <span aria-hidden="true">→</span></a>',
			esc_attr( $class ),
			esc_url( get_permalink( $post_id ) ),
			esc_html__( 'Read', 'the-grid-index' )
		);
	}
}

/**
 * Render a small source/time meta line for cards.
 */
function gip_render_card_meta_line( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	gip_card_debug_comment( $post_id );
	$name = gip_get_source_name( $post_id );
	$ago  = human_time_diff( get_the_time( 'U', $post_id ), current_time( 'timestamp' ) );
	echo '<span class="gi-mono">';
	if ( $name ) echo '<span class="gi-card__source">' . esc_html( $name ) . '</span> · ';
	echo esc_html( $ago ) . ' ' . esc_html__( 'ago', 'the-grid-index' );
	echo '</span>';
}

/**
 * Auto-close comments on imported RSS posts when option enabled.
 */
function gip_filter_comments_open( $open, $post_id ) {
	if ( ! $open ) return $open;
	$hide = function_exists( 'gridindex_get_option' )
		? (int) gridindex_get_option( 'hide_rss_comments', 1 )
		: 1;
	if ( $hide && gip_is_imported_rss( $post_id ) ) return false;
	return $open;
}
add_filter( 'comments_open', 'gip_filter_comments_open', 20, 2 );
add_filter( 'pings_open',    'gip_filter_comments_open', 20, 2 );
