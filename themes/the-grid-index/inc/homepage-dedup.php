<?php
/**
 * The Grid Index — Homepage deduplication.
 *
 * Stops the same post from appearing multiple times on the homepage.
 * Real news sites do this: once a story has been featured prominently
 * (in the slider or the sidebar accelerating stack), lower sections
 * skip it. The result feels editorial, not algorithmic.
 *
 * Priority order on the homepage (each section excludes IDs from those
 * above it in this list):
 *
 *   1. Featured Slider — top hero, always shows what is marked.
 *   2. Most Accelerating sidebar — secondary stories.
 *   3. Source Intelligence rail — entity-level signal stack.
 *   4. Accelerating Stories row — momentum cards.
 *   5. Top Stories grid — main 3+3.
 *   6. Topic Dashboard — desk lists.
 *   7. Latest feed — fallback "everything else".
 *
 * Strategy:
 *   - On homepage load, pre-seed the "already shown" list with the
 *     slider's IDs (slider uses get_posts with suppress_filters so it
 *     does not fire pre_get_posts and we have to fetch its IDs ourselves).
 *   - Hook pre_get_posts to inject a post__not_in clause into every
 *     secondary query running on the homepage.
 *   - Hook the_posts (which fires AFTER each WP_Query fetches results)
 *     to register the IDs from each section so the next one excludes
 *     them.
 *
 * Outside the homepage: this module is inert. Category, tag, archive,
 * search, single-post, admin queries — all untouched.
 *
 * @package The_Grid_Index
 * @since   1.10.30
 */

defined( 'ABSPATH' ) || exit;

class GIP_Homepage_Dedup {

	/** @var int[] Post IDs already shown on this homepage render. */
	private static $shown = array();

	/** @var bool Whether we have seeded the slider IDs yet. */
	private static $seeded = false;

	/** @var int Count of secondary queries we've filtered so far. */
	private static $query_count = 0;

	/**
	 * After this many secondary queries have been filtered, stop applying
	 * dedup. Set conservatively to cover only the four big main-column
	 * sections — Most Accelerating, Accelerating Stories, Top Stories,
	 * Latest. Anything past that (Topic Dashboard's per-category lists,
	 * Source Intel rail, Trending Entities) gets full content even if
	 * stories repeat from above. Repetition in those secondary slots is
	 * acceptable; starvation (rail showing 3 of 8 expected items) is not.
	 */
	const MAIN_COLUMN_QUERY_BUDGET = 4;

	/**
	 * Are we currently rendering the homepage? This is checked many
	 * times per request, so we memoize the answer.
	 */
	private static $is_homepage_cache = null;

	private static function is_homepage() {
		if ( self::$is_homepage_cache !== null ) return self::$is_homepage_cache;
		if ( is_admin() ) {
			self::$is_homepage_cache = false;
			return false;
		}
		// is_home() / is_front_page() reflect the global main query and stay
		// true for the duration of the homepage render even while secondary
		// queries are running.
		self::$is_homepage_cache = ( is_home() || is_front_page() );
		return self::$is_homepage_cache;
	}

	/**
	 * Seed the slider IDs. Runs lazily on first secondary-query filter
	 * to make sure get_term_by + get_posts work correctly (taxonomy is
	 * registered, etc).
	 */
	private static function seed_slider_ids() {
		if ( self::$seeded ) return;
		self::$seeded = true;

		// Read the slider settings to know how many slides we are showing.
		$limit = 8;
		if ( function_exists( 'gip_slider_get_settings' ) ) {
			$s = gip_slider_get_settings();
			$limit = isset( $s['limit'] ) ? max( 1, (int) $s['limit'] ) : 8;
		}

		$term = get_term_by( 'slug', 'featured-slider', 'category' );
		if ( ! $term || is_wp_error( $term ) ) return;

		// Use a direct DB query rather than get_posts to avoid recursion
		// into our own pre_get_posts filter while we are inside it.
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			 WHERE tr.term_taxonomy_id = %d
			 AND p.post_status = 'publish'
			 AND p.post_type = 'post'
			 ORDER BY p.post_date DESC
			 LIMIT %d",
			(int) $term->term_taxonomy_id,
			(int) $limit
		) );
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) self::$shown[ (int) $id ] = true;
		}
	}

	/**
	 * Inject post__not_in into homepage secondary queries.
	 */
	public static function exclude_shown( $query ) {
		if ( ! self::is_homepage() ) return;
		// Skip the main query (the homepage doesn't render its own posts
		// loop in this theme — but if it does someday, dedup would still
		// be wrong: the main query runs first and would exclude nothing,
		// then everything else would inherit. Just safer to skip it.)
		if ( $query->is_main_query() ) return;
		// Skip explicit opt-outs (e.g. counts, ticker) by setting
		// 'gip_skip_dedup' => true in args.
		if ( $query->get( 'gip_skip_dedup' ) ) return;

		// CRITICAL: only dedup queries for regular posts. Nav menus,
		// pages, attachments, and other CPTs share the same ID space
		// as posts — applying post__not_in to a nav_menu_item query
		// can wipe out menu items whose IDs collide with already-shown
		// post IDs. Symptom: header menu disappears on the homepage
		// but works fine on every other page.
		$post_type = $query->get( 'post_type' );
		// post_type can be a string, an array, or empty (defaulting to 'post').
		if ( is_array( $post_type ) ) {
			// Multi-type query — dedup only if it's exclusively 'post'.
			if ( count( $post_type ) !== 1 || reset( $post_type ) !== 'post' ) return;
		} elseif ( $post_type && $post_type !== 'post' ) {
			return;
		}

		// Track query count. Once we're past the main column's budget,
		// allow rail-style sections to surface stories freely (they're
		// summaries / signal rails, not main editorial flow).
		self::$query_count++;
		if ( self::$query_count > self::MAIN_COLUMN_QUERY_BUDGET ) return;

		self::seed_slider_ids();

		if ( empty( self::$shown ) ) return;

		$existing = $query->get( 'post__not_in' );
		if ( ! is_array( $existing ) ) $existing = array();
		$query->set( 'post__not_in', array_unique( array_merge( $existing, array_keys( self::$shown ) ) ) );
	}

	/**
	 * Register IDs from each query AFTER it fetches results, so the next
	 * query excludes them. Only tracks regular posts — same reasoning as
	 * exclude_shown: we don't want nav menu item IDs polluting the
	 * shown list.
	 */
	public static function register_shown( $posts ) {
		if ( ! self::is_homepage() ) return $posts;
		if ( empty( $posts ) ) return $posts;
		foreach ( $posts as $post ) {
			if ( isset( $post->ID ) && isset( $post->post_type ) && $post->post_type === 'post' ) {
				self::$shown[ (int) $post->ID ] = true;
			}
		}
		return $posts;
	}
}

add_action( 'pre_get_posts', array( 'GIP_Homepage_Dedup', 'exclude_shown' ), 50 );
add_filter( 'the_posts',     array( 'GIP_Homepage_Dedup', 'register_shown' ), 10, 1 );
