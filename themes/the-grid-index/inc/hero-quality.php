<?php
/**
 * The Grid Index — Hero quality + image enforcement.
 *
 * Three jobs in one module (cohesive enough to keep together,
 * each one is small):
 *
 *   1. SLIDER HERO QUALITY — featured-slider.php asks for the WP "large"
 *      size (1024px max) for its hero image. The theme has a proper
 *      gip-hero size registered (1600x900 cropped). We filter the URL
 *      to use gip-hero whenever it exists, falling back to large only
 *      when gip-hero is missing.
 *
 *   2. REGENERATE THUMBNAILS — small admin tool under Tools menu that
 *      walks every attached image and regenerates all registered crop
 *      sizes. Needed because old imports (sideloaded by the RSS importer
 *      before sizes were registered, or before this module existed) do
 *      not have the gip-hero/gip-card/gip-thumb crops on disk. Without
 *      this they keep falling back to "large" forever.
 *
 *   3. HIDE IMAGELESS POSTS ON HOMEPAGE — pre_get_posts filter that
 *      requires _thumbnail_id on the homepage main query and on any
 *      query the layout-builder runs. Old imageless posts (and any
 *      hand-written posts where you forgot a featured image) are
 *      silently invisible on the homepage. They still appear on
 *      category/tag/archive pages, single permalinks, and the admin.
 *
 * @package The_Grid_Index
 * @since   1.10.28
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * 1. Slider hero quality
 * ------------------------------------------------------------------------- */

/**
 * Filter image src arrays: when something requests the "large" size for
 * an attachment that is the featured image of a post in the "featured-slider"
 * category, return the gip-hero (1600x900) variant instead.
 *
 * wp_get_attachment_image_src is the underlying function used by
 * get_the_post_thumbnail_url() and the_post_thumbnail() — filtering it
 * affects every consumer.
 */
function gip_hero_quality_filter_image_src( $image, $attachment_id, $size ) {
	// Only intercept the "large" size requests.
	if ( $size !== 'large' ) return $image;

	// Find which post this attachment is the featured image of. There may be
	// many — only proceed if any of them is in the featured-slider category.
	global $wpdb;
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 5",
		(int) $attachment_id
	) );

	if ( empty( $post_ids ) ) return $image;

	$slider_term = get_term_by( 'slug', 'featured-slider', 'category' );
	if ( ! $slider_term || is_wp_error( $slider_term ) ) return $image;

	$is_slider_post = false;
	foreach ( $post_ids as $pid ) {
		if ( has_term( $slider_term->term_id, 'category', (int) $pid ) ) {
			$is_slider_post = true;
			break;
		}
	}
	if ( ! $is_slider_post ) return $image;

	// Try gip-hero. Important: we have to NOT call wp_get_attachment_image_src
	// recursively (it would filter back to here), so use the metadata directly.
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $meta['sizes']['gip-hero'] ) ) return $image;

	$upload_dir = wp_get_upload_dir();
	$base_url   = trailingslashit( $upload_dir['baseurl'] );
	$file       = $meta['file'];
	$dir        = trailingslashit( dirname( $file ) );
	$filename   = $meta['sizes']['gip-hero']['file'];

	$image[0] = $base_url . $dir . $filename;
	$image[1] = (int) $meta['sizes']['gip-hero']['width'];
	$image[2] = (int) $meta['sizes']['gip-hero']['height'];
	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'gip_hero_quality_filter_image_src', 10, 3 );


/* ---------------------------------------------------------------------------
 * 3. Hide imageless posts on the homepage
 * ------------------------------------------------------------------------- */

/**
 * Add a meta_query to homepage queries that requires a non-empty
 * _thumbnail_id. Skips admin, single posts, archives, search, and feeds.
 *
 * Important: applies to ALL queries running while the homepage is being
 * rendered, including secondary WP_Query objects spun up by the layout
 * builder. The original version of this gated on is_main_query() which
 * meant the layout builder queries (Top Stories, Latest, Accelerating)
 * were never filtered — they are secondary queries.
 *
 * The is_home() / is_front_page() conditional reads the GLOBAL query
 * context, so it correctly identifies "we are rendering the homepage"
 * regardless of which sub-query is currently being filtered.
 */
function gip_hide_imageless_on_homepage( $query ) {
	if ( is_admin() ) return;
	if ( $query->is_singular() || $query->is_feed() ) return;

	// Only filter when the homepage is the current page being rendered.
	// is_home()/is_front_page() check the main global query, which still
	// reflects "we are on the homepage" even when secondary queries run.
	if ( ! ( is_home() || is_front_page() ) ) return;

	// Don't touch queries that explicitly opt out by setting
	// 'gip_skip_image_filter' => true in their args.
	if ( $query->get( 'gip_skip_image_filter' ) ) return;

	// CRITICAL: only filter regular posts. Nav menus, pages, attachments,
	// and CPTs do not have featured images, so requiring _thumbnail_id
	// EXISTS would silently empty them. Symptom of the missing guard:
	// header menu disappears on the homepage but works on every other
	// page (because is_home() is false elsewhere so this filter
	// is inert there).
	$post_type = $query->get( 'post_type' );
	if ( is_array( $post_type ) ) {
		if ( count( $post_type ) !== 1 || reset( $post_type ) !== 'post' ) return;
	} elseif ( $post_type && $post_type !== 'post' ) {
		return;
	}

	$existing = $query->get( 'meta_query' );
	if ( ! is_array( $existing ) ) $existing = array();
	$existing[] = array(
		'key'     => '_thumbnail_id',
		'compare' => 'EXISTS',
	);
	$query->set( 'meta_query', $existing );
}
add_action( 'pre_get_posts', 'gip_hide_imageless_on_homepage' );
