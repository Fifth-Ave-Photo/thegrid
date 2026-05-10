<?php
/**
 * The Grid Index — Aggregator meta bridge.
 *
 * The theme's gip_get_source_url() / gip_get_source_name() helpers in
 * inc/source-meta.php already check half a dozen common meta-key names
 * (gi_source_url, _gridindex_original_url, _source_url, source_url, etc).
 * But the Aggregator / Feedzy / WP RSS Aggregator plugins use their own
 * keys, so posts they import never light up the "Read at Source" CTA.
 *
 * This module hooks the existing `gip_source_url` and `gip_source_name`
 * filters and falls back to the keys those plugins typically write,
 * making the button appear on legacy Aggregator posts without any
 * re-import or DB migration.
 *
 * @package The_Grid_Index
 * @since   1.10.24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Candidate meta keys various RSS aggregator plugins use for the source URL.
 */
function gip_aggregator_bridge_url_keys() {
	return array(
		// WP RSS Aggregator / Feedzy / Aggregator
		'wprss_item_permalink',
		'feedzy_item_url',
		'_feedzy_item_url',
		'aggregator_source_url',
		'aggregator_item_url',
		'_aggregator_source_url',
		// Generic
		'rss_source_url',
		'_rss_source_url',
		'permalink',
		'_permalink',
		'item_url',
		'_item_url',
		'source',
	);
}

/**
 * Candidate meta keys for the source name.
 */
function gip_aggregator_bridge_name_keys() {
	return array(
		'wprss_feed_name',
		'feedzy_item_source',
		'_feedzy_item_source',
		'aggregator_source_name',
		'_aggregator_source_name',
		'rss_source_name',
		'_rss_source_name',
		'feed_name',
		'_feed_name',
		'source_label',
	);
}

/**
 * Source URL filter — when the theme's built-in lookups return nothing,
 * try the aggregator-specific keys.
 */
function gip_aggregator_bridge_source_url( $url, $post_id ) {
	if ( $url ) return $url;
	if ( ! $post_id ) return $url;

	foreach ( gip_aggregator_bridge_url_keys() as $key ) {
		$candidate = get_post_meta( $post_id, $key, true );
		if ( $candidate && filter_var( $candidate, FILTER_VALIDATE_URL ) ) {
			return $candidate;
		}
	}
	return $url;
}
add_filter( 'gip_source_url', 'gip_aggregator_bridge_source_url', 10, 2 );

/**
 * Source name filter — same idea for source name.
 */
function gip_aggregator_bridge_source_name( $name, $post_id ) {
	if ( $name ) return $name;
	if ( ! $post_id ) return $name;

	foreach ( gip_aggregator_bridge_name_keys() as $key ) {
		$candidate = get_post_meta( $post_id, $key, true );
		if ( $candidate ) {
			return sanitize_text_field( $candidate );
		}
	}
	return $name;
}
add_filter( 'gip_source_name', 'gip_aggregator_bridge_source_name', 10, 2 );

/**
 * Admin diagnostic: surface raw post meta on imported posts so we can see
 * exactly which keys the Aggregator plugin used for any given post. Only
 * shown to admins, only on single posts, and only when ?gip-debug=1 is in
 * the URL — so it's invisible by default.
 */
function gip_aggregator_bridge_debug_meta() {
	if ( empty( $_GET['gip-debug'] ) ) return;
	if ( ! is_singular( 'post' ) ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;

	$post_id = get_the_ID();
	$meta = get_post_meta( $post_id );
	echo '<div style="position:fixed;bottom:20px;left:20px;max-width:500px;background:#0b0f14;color:#cbd5e1;padding:16px;border-radius:8px;border:1px solid #14b8a6;font:11px/1.5 ui-monospace,Menlo,monospace;z-index:99999;max-height:60vh;overflow:auto;">';
	echo '<strong style="color:#14b8a6;">Post #' . (int) $post_id . ' meta:</strong><br>';
	foreach ( $meta as $key => $vals ) {
		$val = is_array( $vals ) ? implode( ' | ', $vals ) : (string) $vals;
		if ( strlen( $val ) > 120 ) $val = substr( $val, 0, 120 ) . '...';
		echo '<div><span style="color:#94a3b8;">' . esc_html( $key ) . ':</span> ' . esc_html( $val ) . '</div>';
	}
	echo '</div>';
}
add_action( 'wp_footer', 'gip_aggregator_bridge_debug_meta' );
