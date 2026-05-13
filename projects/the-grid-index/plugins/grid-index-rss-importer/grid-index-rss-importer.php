<?php
/**
 * Plugin Name:       Grid Index RSS Importer
 * Plugin URI:        https://github.com/fifthavenuephotographic/grid-index-rss-importer
 * Description:       Pull headlines from external RSS feeds into WordPress. Designed to pair with The Grid Index theme — imported posts are tagged with canonical source meta so the theme's "Read at Source" attribution lights up automatically. Works as a standalone importer if the theme isn't active.
 * Version:           1.0.61
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Fifth Avenue Photographic
 * Author URI:        https://fifthavenuephotographic.com/
 * Text Domain:       grid-index-rss-importer
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Grid_Index_RSS_Importer
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GRID_INDEX_RSS_IMPORTER_VERSION' ) ) {
	define( 'GRID_INDEX_RSS_IMPORTER_VERSION', '1.0.61' );
}

// v1.0.3: Removed the class_exists guard. If something else in the install
// (theme inc file, mu-plugin) was also defining this class earlier in the
// load chain, the plugin was silently no-oping. We now load unconditionally
// and rely on PHP to fatal loudly if there's a real conflict.

class Grid_Index_RSS_Importer {

	const OPTION_KEY    = 'gip_rss_importer_settings';
	const CRON_HOOK     = 'gip_rss_importer_cron';
	const META_GUID     = '_gip_rss_guid_hash';
	const NONCE_ACTION  = 'gip_rss_importer_save';
	const NONCE_NAME    = 'gip_rss_importer_nonce';
	const PAGE_SLUG     = 'gip-rss-importer';
	const RSS_CAT_SLUG  = 'rss';
	const RSS_CAT_NAME  = 'RSS';

	// v1.0.40 — Granular per-feed categories. Each catalog feed has a
	// `category` field (News/World/Tech/Business/Science) which maps to a
	// WP category term created on demand. Posts imported from that feed
	// get tagged with BOTH the RSS catch-all AND the granular term, so
	// themes that query Category:RSS keep working AND users get
	// per-source browsing.
	const GRANULAR_CATEGORIES = array(
		// catalog name => array( slug, display_name, description )
		'News'     => array( 'slug' => 'news',     'name' => 'News',     'desc' => 'General news from major outlets.' ),
		'World'    => array( 'slug' => 'world',    'name' => 'World',    'desc' => 'International news desks.' ),
		'Tech'     => array( 'slug' => 'tech',     'name' => 'Tech',     'desc' => 'Technology news and reviews.' ),
		'Business' => array( 'slug' => 'business', 'name' => 'Business', 'desc' => 'Business, finance, and markets.' ),
		'Science'  => array( 'slug' => 'science',  'name' => 'Science',  'desc' => 'Science and research news.' ),
	);

	// v1.0.13 — fetch tuning. WP defaults are too aggressive (5s timeout) and
	// the default User-Agent gets filtered by some publisher WAFs.
	const FETCH_TIMEOUT_SECONDS   = 15;
	const FETCH_RETRY_DELAY_SECS  = 2;
	const FETCH_ERROR_BACKOFF_SEC = 600; // 10 minutes — skip recently-failed feeds during cron runs.

	// v1.0.24 — Cap on active feeds. Catalog tab toggles enforce this, and
	// the save handler also clamps to it as a defense-in-depth.
	const MAX_ACTIVE_FEEDS = 15;

	// v1.0.34 — Per-feed intervals. Each saved feed can fetch on its own
	// cadence. The cron itself runs every 5 minutes (the shortest interval)
	// and per-feed logic decides whether each one is actually due.
	const VALID_INTERVALS = array( '5min', '15min', '30min', 'hourly' );
	const DEFAULT_INTERVAL = 'hourly';

	// Seconds-per-interval lookup, used by the cron-eligibility check.
	const INTERVAL_SECONDS = array(
		'5min'   => 300,
		'15min'  => 900,
		'30min'  => 1800,
		'hourly' => 3600,
	);

	// v1.0.26 — Live progress state, written by the import loop so the
	// admin UI can poll it. Stored as a transient (auto-expires after a
	// few minutes if a run dies mid-flight) rather than an option to
	// avoid polluting wp_options with stale rows.
	const PROGRESS_TRANSIENT = 'gip_rss_progress';
	const PROGRESS_TTL_SECS  = 600; // 10 minutes — longer than any sane import.

	// v1.0.38 — Persistent "seen GUIDs" ledger. A custom table that survives
	// post deletion (postmeta is wiped when posts are permanently deleted,
	// which used to cause deleted posts to re-import on the next fetch).
	// Table name (without prefix); accessed via $wpdb->prefix . SEEN_TABLE.
	const SEEN_TABLE = 'gip_seen_guids';

	/** @var Grid_Index_RSS_Importer|null */
	private static $instance = null;

	/** @var string Hook suffix returned by add_theme_page. */
	private $hook_suffix = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Register the menu via THREE different methods so at least one shows up
		// even if a plugin or platform filter strips out one of them.
		// v1.0.47 — Tools/Settings fallback registrations removed.
		// They were originally added as redundant safety nets when the main
		// menu was a top-level entry. Since v1.0.46 the main menu nests
		// under "Grid Index" (with a top-level fallback if the theme is
		// inactive), so the duplicate Tools/Settings entries were just
		// clutter — and worse, clicking through them produced a different
		// $hook_suffix that the CSS enqueue didn't recognize, rendering the
		// page completely unstyled. Single registration only.
		add_action( 'admin_menu',       array( $this, 'register_admin_page' ), 15 );

		add_action( 'admin_post_' . self::PAGE_SLUG . '_save',      array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_run',       array( $this, 'handle_run_now' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_force',     array( $this, 'handle_force_reimport' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_fetch_one', array( $this, 'handle_fetch_one_feed' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_restore',   array( $this, 'handle_restore_defaults' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_republish', array( $this, 'handle_republish_drafts' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_set_publish', array( $this, 'handle_set_publish' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_clear_feeds', array( $this, 'handle_clear_feeds' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_catalog_toggle', array( $this, 'handle_catalog_toggle' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_trim_to_cap', array( $this, 'handle_trim_to_cap' ) );
		add_action( 'admin_post_' . self::PAGE_SLUG . '_merge_dupes', array( $this, 'handle_merge_dupes' ) );
		add_action( 'wp_ajax_' . self::PAGE_SLUG . '_ajax_save',      array( $this, 'handle_ajax_save' ) );
		add_action( 'wp_ajax_' . self::PAGE_SLUG . '_ajax_progress',  array( $this, 'handle_ajax_progress' ) );
		add_action( 'wp_ajax_' . self::PAGE_SLUG . '_ajax_fetch_one', array( $this, 'handle_ajax_fetch_one' ) );
		add_action( self::CRON_HOOK,    array( $this, 'run_import' ) );
		add_filter( 'cron_schedules',   array( $this, 'register_cron_schedules' ) );
		add_action( 'init',             array( $this, 'maybe_reschedule_cron' ) );
		add_action( 'switch_theme',     array( $this, 'unschedule_cron' ) );
	}

	/**
	 * Activation hook callback — seed the default starter feeds if no
	 * settings exist yet. Safe to call repeatedly: it only writes feeds
	 * when the option is empty or has no feeds configured.
	 */
	public static function activate() {
		// Ensure the RSS category exists.
		self::ensure_rss_category();

		// v1.0.13 — Flush SimplePie's WordPress transient cache. Stale entries
		// from before fetch tuning was added (default UA, 5s timeout) can
		// cause the first post-update fetch to serve a cached error or empty
		// parse instead of actually re-fetching.
		self::flush_feed_cache();

		// v1.0.38 — Create the persistent seen-GUIDs ledger table.
		self::ensure_seen_table();

		// v1.0.38 — One-time migration: backfill the seen-GUIDs table from
		// existing postmeta so already-imported items can't re-import even if
		// their posts get permanently deleted. Marker means this runs once.
		$seen_marker = get_option( 'gip_rss_migration_v1_0_38_seen' );
		if ( ! $seen_marker ) {
			self::backfill_seen_from_postmeta();
			update_option( 'gip_rss_migration_v1_0_38_seen', time() );
		}

		// v1.0.40 — One-time migration: backfill the per-feed category field
		// on existing saved feeds by URL-matching against the catalog.
		// Existing custom (non-catalog) feeds get empty category — they
		// remain RSS-only until the user assigns one manually.
		$cat_marker = get_option( 'gip_rss_migration_v1_0_40_category' );
		if ( ! $cat_marker ) {
			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] ) ) {
				$instance = self::instance();
				$catalog  = $instance->get_catalog_feeds();
				$by_url   = array();
				foreach ( $catalog as $cf ) {
					if ( ! empty( $cf['url'] ) && ! empty( $cf['category'] ) ) {
						$by_url[ $cf['url'] ] = $cf['category'];
					}
				}
				$changed = false;
				foreach ( $existing['feeds'] as &$f ) {
					if ( empty( $f['url'] ) ) continue;
					if ( ! empty( $f['category'] ) ) continue; // already set
					if ( isset( $by_url[ $f['url'] ] ) ) {
						$f['category'] = $by_url[ $f['url'] ];
						$changed = true;
					}
				}
				unset( $f );
				if ( $changed ) {
					update_option( self::OPTION_KEY, $existing );
				}
			}
			update_option( 'gip_rss_migration_v1_0_40_category', time() );
		}

		// v1.0.41 — One-time migration: remove CNN's deprecated feed URL from
		// any active feed lists. CNN dropped RSS in 2024 and the URL we
		// previously shipped (rss.cnn.com/rss/edition.rss) returns empty
		// data, causing the red-dot silent-failure state users see. Marker
		// gates this to a single run per install.
		$cnn_marker = get_option( 'gip_rss_migration_v1_0_41_cnn' );
		if ( ! $cnn_marker ) {
			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] ) ) {
				$before = count( $existing['feeds'] );
				$existing['feeds'] = array_values( array_filter(
					$existing['feeds'],
					function( $f ) {
						return empty( $f['url'] ) || strpos( $f['url'], 'rss.cnn.com' ) === false;
					}
				) );
				if ( count( $existing['feeds'] ) !== $before ) {
					update_option( self::OPTION_KEY, $existing );
				}
			}
			update_option( 'gip_rss_migration_v1_0_41_cnn', time() );
		}

		// v1.0.49 — One-time migration: remove RSSHub bridge feeds (rsshub.app)
		// from any active feed lists. These were AP News and Reuters World
		// shipped via the community bridge; removed from the catalog for
		// WP.org submission compliance. Users who manually added their own
		// rsshub.app URLs after this migration runs are not affected.
		$rsshub_marker = get_option( 'gip_rss_migration_v1_0_49_rsshub' );
		if ( ! $rsshub_marker ) {
			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] ) ) {
				$before = count( $existing['feeds'] );
				$existing['feeds'] = array_values( array_filter(
					$existing['feeds'],
					function( $f ) {
						return empty( $f['url'] ) || strpos( $f['url'], 'rsshub.app' ) === false;
					}
				) );
				if ( count( $existing['feeds'] ) !== $before ) {
					update_option( self::OPTION_KEY, $existing );
				}
			}
			update_option( 'gip_rss_migration_v1_0_49_rsshub', time() );
		}

		// v1.0.27 — One-time migration: when the catalog was reshaped, The
		// Atlantic was dropped. Remove it from any active feed list it's
		// still in. Gated by a marker option so this runs at most once per
		// install. We check by version constant so future cleanups can use
		// this same pattern with a different marker.
		$migration_marker = get_option( 'gip_rss_migration_v1_0_27' );
		if ( ! $migration_marker ) {
			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] ) ) {
				$dropped = array( 'https://www.theatlantic.com/feed/all/' );
				$before  = count( $existing['feeds'] );
				$existing['feeds'] = array_values( array_filter( $existing['feeds'], function( $f ) use ( $dropped ) {
					return ! ( isset( $f['url'] ) && in_array( $f['url'], $dropped, true ) );
				} ) );
				if ( count( $existing['feeds'] ) !== $before ) {
					update_option( self::OPTION_KEY, $existing );
				}
			}
			update_option( 'gip_rss_migration_v1_0_27', time() );
		}

		// v1.0.28 — Defensive cap clamp. Anyone arriving here with more than
		// MAX_ACTIVE_FEEDS saved (because a pre-cap version saved them, or
		// because earlier cap enforcement only ran on save) gets trimmed to
		// the cap, keeping the first N in saved order. Marker means this
		// fires once per install.
		$cap_marker = get_option( 'gip_rss_migration_v1_0_28_cap' );
		if ( ! $cap_marker ) {
			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] )
				&& count( $existing['feeds'] ) > self::MAX_ACTIVE_FEEDS ) {
				$existing['feeds'] = array_slice( $existing['feeds'], 0, self::MAX_ACTIVE_FEEDS );
				update_option( self::OPTION_KEY, $existing );
			}
			update_option( 'gip_rss_migration_v1_0_28_cap', time() );
		}

		// v1.0.34 — Backfill per-feed intervals. Any saved feed without an
		// `interval` field gets the catalog recommendation (if the URL is in
		// the catalog) or DEFAULT_INTERVAL. Marker means this runs once.
		$interval_marker = get_option( 'gip_rss_migration_v1_0_34_intervals' );
		if ( ! $interval_marker ) {
			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] ) ) {
				$instance = self::instance();
				$changed  = false;
				foreach ( $existing['feeds'] as &$f ) {
					if ( empty( $f['url'] ) ) continue;
					if ( ! empty( $f['interval'] ) && in_array( $f['interval'], self::VALID_INTERVALS, true ) ) continue;
					$f['interval'] = $instance->get_recommended_interval_for_url( $f['url'] );
					$changed = true;
				}
				unset( $f ); // break the reference (PHP foreach-by-ref hygiene).
				if ( $changed ) {
					update_option( self::OPTION_KEY, $existing );
				}
			}
			update_option( 'gip_rss_migration_v1_0_34_intervals', time() );
		}

		// v1.0.29 — No more auto-seed of starter feeds on activation. Earlier
		// versions seeded the starter list whenever activate() ran with an
		// empty saved-feeds state. Users who *deliberately* deleted feeds and
		// then reinstalled the plugin (Deactivate → Delete → reinstall) had
		// their list silently rebuilt to the defaults — there is no way to
		// distinguish a genuine first install from a deliberate reset once
		// uninstall.php has wiped the option. Fresh installs now land on an
		// empty Feeds tab; the Catalog tab is the right place to populate.
		// The "Restore default feeds" button on the Feeds tab remains for
		// anyone who wants the starter list re-applied with one explicit
		// click.
	}

	/**
	 * Delete any SimplePie/WordPress feed cache transients. SimplePie keys
	 * its WP cache as feed_{md5(url)} and feed_mod_{md5(url)}; we wipe both
	 * scopes with a direct DB query because there are potentially many
	 * across the install (every plugin that calls fetch_feed leaves entries).
	 */
	public static function flush_feed_cache() {
		global $wpdb;
		if ( ! isset( $wpdb ) ) return;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_feed\\_%'
			    OR option_name LIKE '\\_transient\\_timeout\\_feed\\_%'"
		);
	}

	/**
	 * Make sure the dedicated "RSS" category exists. Returns its term_id.
	 * Idempotent — safe to call many times.
	 */
	public static function ensure_rss_category() {
		$term = get_term_by( 'slug', self::RSS_CAT_SLUG, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term(
			self::RSS_CAT_NAME,
			'category',
			array(
				'slug'        => self::RSS_CAT_SLUG,
				'description' => 'Auto-created bucket for posts pulled in by the Grid Index RSS Importer.',
			)
		);
		if ( is_wp_error( $created ) ) {
			// Race: another process created it. Re-fetch.
			$term = get_term_by( 'slug', self::RSS_CAT_SLUG, 'category' );
			return $term ? (int) $term->term_id : 0;
		}
		return (int) $created['term_id'];
	}

	/**
	 * v1.0.40 — Ensure a granular category term exists (News, World, Tech,
	 * Business, Science). Returns its term_id. Idempotent. Called on
	 * demand during import rather than on activation so we don't pollute
	 * the category list with terms the user might never use.
	 *
	 * @param string $cat_key Catalog category name (e.g. "News", "Tech")
	 * @return int term_id or 0 on failure
	 */
	public function ensure_granular_category( $cat_key ) {
		if ( empty( self::GRANULAR_CATEGORIES[ $cat_key ] ) ) {
			return 0;
		}
		$cfg  = self::GRANULAR_CATEGORIES[ $cat_key ];
		$term = get_term_by( 'slug', $cfg['slug'], 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $cfg['name'], 'category', array(
			'slug'        => $cfg['slug'],
			'description' => $cfg['desc'],
		) );
		if ( is_wp_error( $created ) ) {
			// Race or name collision: re-fetch by slug, then by name.
			$term = get_term_by( 'slug', $cfg['slug'], 'category' );
			if ( ! $term ) {
				$term = get_term_by( 'name', $cfg['name'], 'category' );
			}
			return $term ? (int) $term->term_id : 0;
		}
		return (int) $created['term_id'];
	}

	/**
	 * v1.0.38 — Create the persistent seen-GUIDs ledger table if it doesn't
	 * exist. Schema kept tiny: hash + first_seen + source_url. Uses dbDelta
	 * so future schema changes can be applied idempotently.
	 *
	 * The table survives WordPress post deletion (postmeta does NOT — when
	 * a post is permanently deleted, its meta rows go with it). Without
	 * this table, a deleted post would re-import on the next cron because
	 * the dedupe hash would be gone.
	 */
	public static function ensure_seen_table() {
		global $wpdb;
		$table   = $wpdb->prefix . self::SEEN_TABLE;
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			guid_hash CHAR(32) NOT NULL,
			first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			source_url VARCHAR(2048) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY guid_hash (guid_hash)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * v1.0.38 — Seed the seen-GUIDs ledger from existing postmeta. Runs
	 * once on activation of the v1.0.38 install so users upgrading from
	 * earlier versions don't see existing imports treated as "never seen"
	 * just because the table was empty when it was created.
	 */
	public static function backfill_seen_from_postmeta() {
		global $wpdb;
		$table = $wpdb->prefix . self::SEEN_TABLE;
		// Insert-ignore so duplicates (if backfill is somehow run twice)
		// don't error out on the UNIQUE key.
		$sql = $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (guid_hash, first_seen)
			 SELECT pm.meta_value, COALESCE(p.post_date_gmt, NOW())
			 FROM {$wpdb->postmeta} pm
			 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND pm.meta_value <> ''",
			self::META_GUID
		);
		$wpdb->query( $sql );
	}

	/**
	 * v1.0.38 — Has this GUID hash been seen before? Combines two sources:
	 *   1. The seen-GUIDs ledger (survives post deletion).
	 *   2. Existing postmeta — covers ANY status including trash. The
	 *      previous get_posts() lookup with status='any' excluded trash,
	 *      letting trashed posts re-import on the next fetch. We now scan
	 *      postmeta directly so trash is included.
	 * Returns true if either source has it.
	 */
	public function has_seen_guid( $guid_hash ) {
		global $wpdb;

		// Ledger check (persistent — covers permanently-deleted posts).
		$table = $wpdb->prefix . self::SEEN_TABLE;
		$hit = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE guid_hash = %s LIMIT 1",
			$guid_hash
		) );
		if ( $hit ) return true;

		// Postmeta check (covers trashed posts whose meta still exists).
		$pm = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = %s AND meta_value = %s LIMIT 1",
			self::META_GUID,
			$guid_hash
		) );
		return (bool) $pm;
	}

	/**
	 * v1.0.38 — Record a GUID hash in the ledger. Idempotent via the
	 * UNIQUE key (INSERT IGNORE silently skips dupes).
	 */
	public function record_seen_guid( $guid_hash, $source_url = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::SEEN_TABLE;
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (guid_hash, first_seen, source_url)
			 VALUES (%s, NOW(), %s)",
			$guid_hash,
			$source_url
		) );
	}

	/** Restore the curated starter feed list (overwrites current feeds). */
	public function handle_restore_defaults() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$settings          = $this->get_settings();
		$settings['feeds'] = $this->get_starter_feeds();
		$this->save_settings( $settings );

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( __( 'Default feed list restored.', 'grid-index-rss-importer' ) ),
			'gip_rss_type' => 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * v1.0.25 — Trim the saved feeds list down to MAX_ACTIVE_FEEDS by
	 * keeping the first N in saved order. Reached via the "Trim to 15"
	 * button on the over-cap warning in the Catalog tab.
	 *
	 * Saved-order is the simplest deterministic policy: whatever you added
	 * first stays, the most-recent extras get removed. Recoverable via the
	 * Catalog tab if you don't like the result.
	 */
	public function handle_trim_to_cap() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$s     = $this->get_settings();
		$feeds = is_array( $s['feeds'] ?? null ) ? $s['feeds'] : array();

		$before = count( $feeds );
		if ( $before <= self::MAX_ACTIVE_FEEDS ) {
			// Nothing to do.
			wp_safe_redirect( add_query_arg( array(
				'page'         => self::PAGE_SLUG,
				'gip_rss_msg'  => rawurlencode( __( 'Already at or below the cap. Nothing trimmed.', 'grid-index-rss-importer' ) ),
				'gip_rss_type' => 'success',
			), admin_url( 'admin.php' ) ) . '#catalog' );
			exit;
		}

		$kept    = array_slice( $feeds, 0, self::MAX_ACTIVE_FEEDS );
		$removed = $before - count( $kept );

		$s['feeds'] = $kept;
		$this->save_settings( $s );

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( sprintf(
				/* translators: 1: number removed, 2: cap */
				_n(
					'Trimmed %1$d feed. You now have %2$d active feeds.',
					'Trimmed %1$d feeds. You now have %2$d active feeds.',
					$removed, 'grid-index-rss-importer'
				),
				$removed,
				count( $kept )
			) ),
			'gip_rss_type' => 'success',
		), admin_url( 'admin.php' ) ) . '#catalog' );
		exit;
	}

	/**
	 * v1.0.31 — Shared helper. Finds duplicate groups in the RSS category.
	 * Used by both the read-only Diagnostics card and the merge handler so
	 * the two can never disagree about what counts as a duplicate.
	 *
	 * @param int $limit Max posts to scan (most recent first). Default 2000.
	 * @return array {
	 *     groups: array<string, array<object>> keyed by normalized title,
	 *             values are arrays of post rows (ID, post_title, post_date,
	 *             source_name, guid_hash) sorted ASC by ID (oldest first).
	 *     scan_count: int — how many RSS posts were scanned
	 *     rss_cat_id: int — the RSS category ID, 0 if not found
	 * }
	 */
	/**
	 * v1.0.42 — Lightweight summary for the Feeds-tab banner. Returns
	 * counts only (not the full groups), cached in a transient so we
	 * don't re-run the full grouping on every Feeds-tab page load.
	 *
	 *   groups     — number of duplicate groups
	 *   surplus    — extra posts that would be trashed if merged
	 *                (e.g. group of 5 = 4 surplus)
	 *
	 * Cache TTL is short (3 minutes) so a merge or fresh import is
	 * reflected without manual purge.
	 *
	 * @return array{groups:int, surplus:int}
	 */
	public function count_duplicate_summary() {
		$cache_key = 'gip_rss_dup_summary';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['groups'], $cached['surplus'] ) ) {
			return $cached;
		}

		$result   = $this->find_duplicate_groups( 2000 );
		$groups_n = count( $result['groups'] );
		$surplus  = 0;
		foreach ( $result['groups'] as $members ) {
			$surplus += max( 0, count( $members ) - 1 );
		}
		$summary = array( 'groups' => $groups_n, 'surplus' => $surplus );
		set_transient( $cache_key, $summary, 3 * MINUTE_IN_SECONDS );
		return $summary;
	}

	public function find_duplicate_groups( $limit = 2000 ) {
		global $wpdb;
		$rss_cat = get_category_by_slug( self::RSS_CAT_SLUG );
		$rss_cat_id = $rss_cat ? (int) $rss_cat->term_id : 0;
		if ( ! $rss_cat_id ) {
			return array( 'groups' => array(), 'scan_count' => 0, 'rss_cat_id' => 0 );
		}

		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_date,
			        srcname.meta_value AS source_name,
			        guidhash.meta_value AS guid_hash
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 LEFT JOIN {$wpdb->postmeta} srcname  ON srcname.post_id  = p.ID AND srcname.meta_key  = '_gridindex_source_name'
			 LEFT JOIN {$wpdb->postmeta} guidhash ON guidhash.post_id = p.ID AND guidhash.meta_key = %s
			 WHERE tt.term_id = %d
			   AND p.post_status IN ('publish','draft','pending')
			 ORDER BY p.post_date DESC
			 LIMIT %d",
			self::META_GUID,
			$rss_cat_id,
			(int) $limit
		);
		$rows = $wpdb->get_results( $sql );

		$by_norm_title = array();
		foreach ( $rows as $r ) {
			$norm = strtolower( $r->post_title );
			$norm = preg_replace( '/[^a-z0-9]+/u', ' ', $norm );
			$norm = trim( preg_replace( '/\s+/', ' ', $norm ) );
			if ( $norm === '' ) continue;
			$by_norm_title[ $norm ][] = $r;
		}

		$groups = array();
		foreach ( $by_norm_title as $norm => $members ) {
			if ( count( $members ) < 2 ) continue;
			// Sort each group ASC by ID — keep the oldest, trash the rest.
			usort( $members, function( $a, $b ) { return (int) $a->ID - (int) $b->ID; } );
			$groups[ $norm ] = $members;
		}

		// Sort groups by size desc so large dupes show first in UI.
		uasort( $groups, function( $a, $b ) { return count( $b ) - count( $a ); } );

		return array(
			'groups'     => $groups,
			'scan_count' => count( $rows ),
			'rss_cat_id' => $rss_cat_id,
		);
	}

	/**
	 * v1.0.31 — Merge duplicate groups by trashing all but the oldest in each
	 * group. Goes to Trash (not permanent delete) so anything mis-grouped is
	 * recoverable from Posts → Trash for 30 days.
	 *
	 * Uses wp_trash_post() rather than wp_delete_post() — same difference as
	 * clicking "Trash" in the post list vs. "Delete Permanently."
	 */
	public function handle_merge_dupes() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$result = $this->find_duplicate_groups( 2000 );
		$groups = $result['groups'];

		$trashed = 0;
		$failed  = 0;
		foreach ( $groups as $members ) {
			// $members is sorted ASC by ID; keep [0], trash the rest.
			for ( $i = 1, $n = count( $members ); $i < $n; $i++ ) {
				$post_id = (int) $members[ $i ]->ID;
				if ( $post_id <= 0 ) { $failed++; continue; }
				$ok = wp_trash_post( $post_id );
				if ( $ok ) {
					$trashed++;
				} else {
					$failed++;
				}
			}
		}

		if ( $trashed === 0 && $failed === 0 ) {
			$msg  = __( 'No duplicates found to merge.', 'grid-index-rss-importer' );
			$type = 'success';
		} else {
			$msg = sprintf(
				/* translators: 1: trashed count, 2: failed count */
				_n(
					'Merged duplicates: %1$d post moved to Trash.',
					'Merged duplicates: %1$d posts moved to Trash.',
					$trashed, 'grid-index-rss-importer'
				),
				$trashed
			);
			if ( $failed > 0 ) {
				$msg .= ' ' . sprintf(
					/* translators: %d failed count */
					esc_html__( '(%d failed — see WP error log.)', 'grid-index-rss-importer' ),
					$failed
				);
			}
			$msg .= ' ' . esc_html__( 'Restore any of them from Posts → Trash within 30 days.', 'grid-index-rss-importer' );
			$type = 'success';
		}

		// v1.0.42 — Invalidate the dup-summary cache so the Feeds-tab banner
		// reflects the merge immediately.
		delete_transient( 'gip_rss_dup_summary' );

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( $msg ),
			'gip_rss_type' => $type,
		), admin_url( 'admin.php' ) ) . '#diagnostics' );
		exit;
	}

	/**
	 * v1.0.24 — Toggle a catalog feed on or off. Adds the feed if it's not
	 * already in the saved list; removes it if it is. Enforces the
	 * MAX_ACTIVE_FEEDS cap when adding.
	 */
	public function handle_catalog_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$url = isset( $_REQUEST['feed_url'] ) ? esc_url_raw( wp_unslash( $_REQUEST['feed_url'] ) ) : '';
		if ( ! $url ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ) . '#catalog' );
			exit;
		}

		// Find this URL in the catalog so we know its display name.
		$catalog_match = null;
		foreach ( $this->get_catalog_feeds() as $cf ) {
			if ( $cf['url'] === $url ) { $catalog_match = $cf; break; }
		}
		if ( ! $catalog_match ) {
			wp_safe_redirect( add_query_arg( array(
				'page'         => self::PAGE_SLUG,
				'gip_rss_msg'  => rawurlencode( __( 'Unknown catalog feed.', 'grid-index-rss-importer' ) ),
				'gip_rss_type' => 'error',
			), admin_url( 'admin.php' ) ) . '#catalog' );
			exit;
		}

		$s     = $this->get_settings();
		$feeds = is_array( $s['feeds'] ?? null ) ? $s['feeds'] : array();

		// Is it already active?
		$active_idx = -1;
		foreach ( $feeds as $i => $f ) {
			if ( isset( $f['url'] ) && $f['url'] === $url ) { $active_idx = (int) $i; break; }
		}

		if ( $active_idx >= 0 ) {
			// Toggle OFF: remove from the active list.
			array_splice( $feeds, $active_idx, 1 );
			$msg  = sprintf(
				/* translators: %s feed name */
				__( '“%s” removed from your active feeds.', 'grid-index-rss-importer' ),
				$catalog_match['name']
			);
			$type = 'success';
		} else {
			// Toggle ON: enforce the cap.
			if ( count( $feeds ) >= self::MAX_ACTIVE_FEEDS ) {
				$msg  = sprintf(
					/* translators: %d feed cap */
					__( 'You already have %d active feeds (the maximum). Remove one before adding another.', 'grid-index-rss-importer' ),
					self::MAX_ACTIVE_FEEDS
				);
				$type = 'error';
			} else {
				$feeds[] = array(
					'url'      => $catalog_match['url'],
					'name'     => $catalog_match['name'],
					// v1.0.34 — use recommended interval from the catalog entry.
					'interval' => ! empty( $catalog_match['recommended_interval'] )
						&& in_array( $catalog_match['recommended_interval'], self::VALID_INTERVALS, true )
						? $catalog_match['recommended_interval']
						: self::DEFAULT_INTERVAL,
					// v1.0.40 — catalog category drives the granular post category.
					'category' => ! empty( $catalog_match['category'] ) && isset( self::GRANULAR_CATEGORIES[ $catalog_match['category'] ] )
						? $catalog_match['category']
						: '',
				);
				$msg  = sprintf(
					/* translators: %s feed name */
					__( '“%s” added to your active feeds.', 'grid-index-rss-importer' ),
					$catalog_match['name']
				);
				$type = 'success';
			}
		}

		$s['feeds'] = array_values( $feeds );
		$this->save_settings( $s );

		// v1.0.35 — preserve view=list if the toggle was made from list view,
		// so the user stays in their chosen view after the redirect.
		$redirect_args = array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( $msg ),
			'gip_rss_type' => $type,
		);
		if ( isset( $_REQUEST['view'] ) && $_REQUEST['view'] === 'list' ) {
			$redirect_args['view'] = 'list';
		}
		// v1.0.36 — When the toggle ADDED a feed (not removed, not error),
		// pass the feed's index in the redirect so the toast can offer a
		// "Fetch now" action targeting that specific feed.
		if ( $active_idx < 0 && $type === 'success' ) {
			$new_idx = count( $s['feeds'] ) - 1; // the newly added feed sits at the end
			$redirect_args['gip_rss_added_idx']  = (int) $new_idx;
			$redirect_args['gip_rss_added_name'] = rawurlencode( $catalog_match['name'] );
		}
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) . '#catalog' );
		exit;
	}

	/**
	 * Wipe the feeds array entirely. Keeps every other setting (post_status,
	 * frequency, image rules, etc.) — only the feeds list is cleared.
	 * Reached via the "Clear all feeds" button on the Feeds tab.
	 */
	public function handle_clear_feeds() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$s          = $this->get_settings();
		$count      = is_array( $s['feeds'] ?? null ) ? count( $s['feeds'] ) : 0;
		$s['feeds'] = array();
		$this->save_settings( $s );

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( sprintf(
				/* translators: %d: number of feeds removed */
				_n( '%d feed removed.', '%d feeds removed.', $count, 'grid-index-rss-importer' ),
				$count
			) ),
			'gip_rss_type' => 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * One-click "set status to Publish + republish drafts" handler.
	 * Triggered from the prominent banner button on the admin page.
	 *
	 * Does two things in one click:
	 *   1. Saves post_status='publish' to settings (so future imports publish).
	 *   2. Republishes every existing draft tagged with our GUID hash meta
	 *      (so the existing backlog of drafts is fixed too).
	 */
	public function handle_set_publish() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$s                = $this->get_settings();
		$s['post_status'] = 'publish';
		$this->save_settings( $s );

		// Republish all existing imported drafts, same logic as handle_republish_drafts.
		$drafts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => self::META_GUID, 'compare' => 'EXISTS' ),
			),
			'no_found_rows'  => true,
		) );
		$count = 0;
		foreach ( $drafts as $pid ) {
			$res = wp_update_post( array( 'ID' => (int) $pid, 'post_status' => 'publish' ), true );
			if ( ! is_wp_error( $res ) ) $count++;
		}

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( sprintf(
				/* translators: %d: number of drafts republished */
				_n(
					'Set to Publish. %d existing draft was published.',
					'Set to Publish. %d existing drafts were published.',
					$count, 'grid-index-rss-importer'
				),
				$count
			) ),
			'gip_rss_type' => 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Bulk-publish every existing draft post that was imported by this
	 * plugin. Identified by the presence of the GUID hash meta we set on
	 * every import. Useful after a settings change (e.g. post_status was
	 * "draft" early on, you flipped to "publish", but the existing drafts
	 * are still drafts).
	 */
	public function handle_republish_drafts() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$drafts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => self::META_GUID, 'compare' => 'EXISTS' ),
			),
			'no_found_rows'  => true,
		) );

		$count = 0;
		foreach ( $drafts as $pid ) {
			$res = wp_update_post( array( 'ID' => (int) $pid, 'post_status' => 'publish' ), true );
			if ( ! is_wp_error( $res ) ) $count++;
		}

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( sprintf(
				/* translators: %d: number of drafts republished */
				_n( '%d imported draft published.', '%d imported drafts published.', $count, 'grid-index-rss-importer' ),
				$count
			) ),
			'gip_rss_type' => 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	public function get_defaults() {
		return array(
			'feeds'           => array(),       // array of array( url, name, category )
			'post_status'     => 'publish',     // publish | draft | pending — default publish so posts appear immediately
			'frequency'       => 'hourly',      // gip_rss_15min | hourly | twicedaily | manual
			'image_mode'      => 'feed_first',  // feed_first | content_first | none
			'min_image_width' => 1000,          // skip imports with images smaller than this (px wide)
			'max_per_run'     => 10,            // safety cap per feed per run
			'last_run'        => 0,
			'last_log'        => '',
			// v1.0.37 — Preserve settings, feeds, and GUID dedupe meta if the
			// user deletes the plugin. v1.0.49 — Default changed to FALSE for
			// WP.org submission compliance. WordPress.org plugin guidelines
			// require that uninstall.php remove all plugin data by default;
			// data persistence must be opt-in, not opt-out. Users who want to
			// preserve their feed list across delete-and-reinstall can check
			// the box on the Settings tab BEFORE uninstalling.
			'keep_on_uninstall' => false,
		);
	}

	/**
	 * Curated starter feeds — populated automatically on activation, or
	 * restored manually via the "Restore default feeds" button on the
	 * settings page. Categories are 0 (default) so the user can map them
	 * to whatever taxonomy they want without our assumptions.
	 *
	 * @return array
	 */
	public function get_starter_feeds() {
		return array(
			// World & general news (verified working)
			array( 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml',  'name' => 'New York Times' ),
			// Google News top stories. Note: Google News links go through a
			// redirect (news.google.com/...) rather than directly to the
			// publisher, so the "Read at Source" attribution will point at
			// Google rather than the original outlet. Items also lack a
			// direct article image, so the min-image-width gate may skip
			// many entries depending on how much SimplePie can extract.
			array( 'url' => 'https://news.google.com/rss?hl=en-US&gl=US&ceid=US:en',     'name' => 'Google News' ),
			array( 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml',     'name' => 'NYT World' ),
			array( 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/Business.xml',  'name' => 'NYT Business' ),
			array( 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/Technology.xml','name' => 'NYT Technology' ),
			array( 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml',                'name' => 'BBC World' ),
			array( 'url' => 'https://feeds.npr.org/1001/rss.xml',                         'name' => 'NPR News' ),
			array( 'url' => 'https://www.aljazeera.com/xml/rss/all.xml',                  'name' => 'Al Jazeera' ),
			array( 'url' => 'https://www.theguardian.com/world/rss',                      'name' => 'The Guardian World' ),
			array( 'url' => 'https://www.theguardian.com/us-news/rss',                    'name' => 'The Guardian US' ),
			// AP via community bridge — AP retired their official RSS in 2020.
			array( 'url' => 'http://associated-press.s3-website-us-east-1.amazonaws.com/topnews.xml', 'name' => 'AP Top News' ),
			// Tech (full RSS, reliable)
			array( 'url' => 'https://techcrunch.com/feed/',                               'name' => 'TechCrunch' ),
			array( 'url' => 'https://www.theverge.com/rss/index.xml',                     'name' => 'The Verge' ),
			array( 'url' => 'https://feeds.arstechnica.com/arstechnica/index',            'name' => 'Ars Technica' ),
			array( 'url' => 'https://www.wired.com/feed/rss',                             'name' => 'Wired' ),
			array( 'url' => 'https://www.engadget.com/rss.xml',                           'name' => 'Engadget' ),
			array( 'url' => 'https://hnrss.org/frontpage',                                'name' => 'Hacker News' ),
			// AI-specific
			array( 'url' => 'https://openai.com/blog/rss.xml',                            'name' => 'OpenAI' ),
			array( 'url' => 'https://blog.google/technology/ai/rss/',                     'name' => 'Google AI' ),
			array( 'url' => 'https://huggingface.co/blog/feed.xml',                       'name' => 'Hugging Face' ),
		);
	}

	/**
	 * v1.0.27 — Catalog reshaped per user direction: news-heavy (USA + world
	 * + politics), less tech, AI/Culture sections removed entirely. Reuters
	 * intentionally excluded (RSS retired in 2020). Verified URLs as of
	 * May 2026 where possible; URLs from less-recently-checked publishers
	 * (CBS, ABC, AP) follow long-stable canonical patterns confirmed via
	 * 2026 third-party feed directories.
	 *
	 * @return array of arrays { url, name, category }
	 */
	public function get_catalog_feeds() {
		return array(
			// News (18) — major US national, world, and politics-heavy publishers.
			// v1.0.34 — recommended_interval values reflect publishing volume:
			//   5min   → wire services (Google News, AP, BBC World, Politico, Al Jazeera)
			//   15min  → high-volume newspapers (NYT, WaPo, Guardian, NPR, USA Today, etc.)
			//   30min  → tech sites (bursty but not minute-paced)
			//   hourly → business / weekly / slow desks (FT, HBR, Fast Co., Forbes, Bloomberg, WSJ)
			array( 'category' => 'News', 'name' => 'New York Times',         'recommended_interval' => '15min', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml' ),
			array( 'category' => 'News', 'name' => 'NYT World',              'recommended_interval' => '15min', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml' ),
			array( 'category' => 'News', 'name' => 'NYT Politics',           'recommended_interval' => '15min', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/Politics.xml' ),
			array( 'category' => 'News', 'name' => 'BBC World',              'recommended_interval' => '5min',  'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml' ),
			array( 'category' => 'News', 'name' => 'BBC US & Canada',        'recommended_interval' => '15min', 'url' => 'https://feeds.bbci.co.uk/news/world/us_and_canada/rss.xml' ),
			array( 'category' => 'News', 'name' => 'The Guardian World',     'recommended_interval' => '15min', 'url' => 'https://www.theguardian.com/world/rss' ),
			array( 'category' => 'News', 'name' => 'The Guardian US',        'recommended_interval' => '15min', 'url' => 'https://www.theguardian.com/us-news/rss' ),
			array( 'category' => 'News', 'name' => 'NPR News',               'recommended_interval' => '15min', 'url' => 'https://feeds.npr.org/1001/rss.xml' ),
			array( 'category' => 'News', 'name' => 'NPR Politics',           'recommended_interval' => '15min', 'url' => 'https://feeds.npr.org/1014/rss.xml' ),
			array( 'category' => 'News', 'name' => 'Al Jazeera',             'recommended_interval' => '5min',  'url' => 'https://www.aljazeera.com/xml/rss/all.xml' ),
			array( 'category' => 'News', 'name' => 'Google News',            'recommended_interval' => '5min',  'url' => 'https://news.google.com/rss?hl=en-US&gl=US&ceid=US:en' ),
			array( 'category' => 'News', 'name' => 'USA Today',              'recommended_interval' => '15min', 'url' => 'https://rssfeeds.usatoday.com/usatoday-NewsTopStories' ),
			array( 'category' => 'News', 'name' => 'Washington Post',        'recommended_interval' => '15min', 'url' => 'https://feeds.washingtonpost.com/rss/national' ),
			array( 'category' => 'News', 'name' => 'WaPo Politics',          'recommended_interval' => '15min', 'url' => 'https://feeds.washingtonpost.com/rss/politics' ),
			// v1.0.49 — AP News and Reuters World removed. Both publishers
			// retired their official RSS feeds; the catalog previously
			// shipped community bridge URLs via rsshub.app. For WP.org
			// submission compliance, the catalog should not point users at
			// third-party services they haven't been disclosed about, and
			// the bridges themselves are unreliable (volunteer-run, outside
			// the publisher's control). Users who specifically want either
			// source can add a bridge URL manually on the Feeds tab.
			array( 'category' => 'News', 'name' => 'ABC News',               'recommended_interval' => '15min', 'url' => 'https://abcnews.go.com/abcnews/topstories' ),
			array( 'category' => 'News', 'name' => 'CBS News',               'recommended_interval' => '15min', 'url' => 'https://www.cbsnews.com/latest/rss/main' ),
			array( 'category' => 'News', 'name' => 'Politico',               'recommended_interval' => '5min',  'url' => 'https://rss.politico.com/politics-news.xml' ),
			// v1.0.35 — News expansion (8 added).
			array( 'category' => 'News', 'name' => 'NBC News',               'recommended_interval' => '5min',  'url' => 'https://feeds.nbcnews.com/nbcnews/public/news' ),
			// CNN removed in v1.0.41 — CNN deprecated their RSS feeds in 2024.
			// The previous URL (rss.cnn.com/rss/edition.rss) still resolves
			// but returns an empty or stale feed. cnn.com/services/rss/ now
			// 302s to the homepage. No usable official replacement exists.
			array( 'category' => 'News', 'name' => 'The Hill',               'recommended_interval' => '15min', 'url' => 'https://thehill.com/news/feed/' ),
			array( 'category' => 'News', 'name' => 'ProPublica',             'recommended_interval' => 'hourly','url' => 'https://feeds.propublica.org/propublica/main' ),
			array( 'category' => 'News', 'name' => 'Time',                   'recommended_interval' => '30min', 'url' => 'https://time.com/feed/' ),
			array( 'category' => 'News', 'name' => 'Bloomberg Politics',     'recommended_interval' => '15min', 'url' => 'https://feeds.bloomberg.com/politics/news.rss' ),
			array( 'category' => 'News', 'name' => 'LA Times',               'recommended_interval' => '15min', 'url' => 'https://www.latimes.com/local/rss2.0.xml' ),

			// Tech (9) — bursty publishing, 30 min is plenty.
			array( 'category' => 'Tech', 'name' => 'TechCrunch',  'recommended_interval' => '30min', 'url' => 'https://techcrunch.com/feed/' ),
			array( 'category' => 'Tech', 'name' => 'The Verge',   'recommended_interval' => '30min', 'url' => 'https://www.theverge.com/rss/index.xml' ),
			array( 'category' => 'Tech', 'name' => 'Ars Technica','recommended_interval' => '30min', 'url' => 'https://feeds.arstechnica.com/arstechnica/index' ),
			array( 'category' => 'Tech', 'name' => 'Wired',       'recommended_interval' => '30min', 'url' => 'https://www.wired.com/feed/rss' ),
			array( 'category' => 'Tech', 'name' => 'Engadget',    'recommended_interval' => '30min', 'url' => 'https://www.engadget.com/rss.xml' ),
			array( 'category' => 'Tech', 'name' => 'Hacker News', 'recommended_interval' => '30min', 'url' => 'https://hnrss.org/frontpage' ),
			// v1.0.35 — Tech expansion (3 added).
			array( 'category' => 'Tech', 'name' => '9to5Mac',     'recommended_interval' => '30min', 'url' => 'https://9to5mac.com/feed/' ),
			array( 'category' => 'Tech', 'name' => 'MIT Tech Review', 'recommended_interval' => 'hourly','url' => 'https://www.technologyreview.com/feed/' ),
			array( 'category' => 'Tech', 'name' => 'ZDNet',       'recommended_interval' => '30min', 'url' => 'https://www.zdnet.com/news/rss.xml' ),

			// Business (8) — slower cadence, hourly is honest.
			array( 'category' => 'Business', 'name' => 'Bloomberg Technology',   'recommended_interval' => 'hourly', 'url' => 'https://feeds.bloomberg.com/technology/news.rss' ),
			array( 'category' => 'Business', 'name' => 'Financial Times',        'recommended_interval' => 'hourly', 'url' => 'https://www.ft.com/rss/home' ),
			array( 'category' => 'Business', 'name' => 'Harvard Business Review','recommended_interval' => 'hourly', 'url' => 'https://hbr.org/feed' ),
			array( 'category' => 'Business', 'name' => 'Fast Company',           'recommended_interval' => 'hourly', 'url' => 'https://www.fastcompany.com/latest/rss' ),
			array( 'category' => 'Business', 'name' => 'Forbes Innovation',      'recommended_interval' => 'hourly', 'url' => 'https://www.forbes.com/innovation/feed/' ),
			// WSJ feed publishes headlines + summaries, but article links
			// require a paid subscription to read in full.
			array( 'category' => 'Business', 'name' => 'WSJ Markets',          'recommended_interval' => 'hourly', 'url' => 'https://feeds.a.dj.com/rss/RSSMarketsMain.xml' ),
			// v1.0.35 — Business expansion (2 added).
			array( 'category' => 'Business', 'name' => 'MarketWatch',         'recommended_interval' => '30min', 'url' => 'https://feeds.marketwatch.com/marketwatch/topstories/' ),
			array( 'category' => 'Business', 'name' => 'CNBC Top News',       'recommended_interval' => '15min', 'url' => 'https://www.cnbc.com/id/100003114/device/rss/rss.html' ),

			// v1.0.35 — Science (3, new section).
			array( 'category' => 'Science', 'name' => 'Science Daily',       'recommended_interval' => 'hourly','url' => 'https://www.sciencedaily.com/rss/all.xml' ),
			array( 'category' => 'Science', 'name' => 'Ars Technica Science','recommended_interval' => 'hourly','url' => 'https://feeds.arstechnica.com/arstechnica/science' ),
			array( 'category' => 'Science', 'name' => 'NASA News',           'recommended_interval' => 'hourly','url' => 'https://www.nasa.gov/feed/' ),

			// v1.0.35 — World/Regional (4, new section) — international and Commonwealth desks.
			array( 'category' => 'World', 'name' => 'Deutsche Welle (EN)',   'recommended_interval' => '15min', 'url' => 'https://rss.dw.com/rdf/rss-en-all' ),
			array( 'category' => 'World', 'name' => 'France 24 (EN)',        'recommended_interval' => '15min', 'url' => 'https://www.france24.com/en/rss' ),
			array( 'category' => 'World', 'name' => 'CBC News (Canada)',     'recommended_interval' => '15min', 'url' => 'https://www.cbc.ca/cmlink/rss-topstories' ),
			array( 'category' => 'World', 'name' => 'ABC News (Australia)',  'recommended_interval' => '15min', 'url' => 'https://www.abc.net.au/news/feed/51120/rss.xml' ),
		);
	}

	/**
	 * v1.0.34 — Look up the recommended interval for a feed URL. Returns
	 * the catalog recommendation if known, otherwise DEFAULT_INTERVAL.
	 */
	public function get_recommended_interval_for_url( $url ) {
		foreach ( $this->get_catalog_feeds() as $cf ) {
			if ( $cf['url'] === $url && ! empty( $cf['recommended_interval'] ) ) {
				return $cf['recommended_interval'];
			}
		}
		return self::DEFAULT_INTERVAL;
	}

	/**
	 * v1.0.34 — Friendly label for an interval slug ("Every 5 min", etc).
	 */
	public function interval_label( $interval ) {
		switch ( $interval ) {
			case '5min':   return __( 'Every 5 min',   'grid-index-rss-importer' );
			case '15min':  return __( 'Every 15 min',  'grid-index-rss-importer' );
			case '30min':  return __( 'Every 30 min',  'grid-index-rss-importer' );
			case 'hourly': return __( 'Every hour',    'grid-index-rss-importer' );
		}
		return $interval;
	}

	public function get_settings() {
		$saved    = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $this->get_defaults() );

		// v1.0.13 — defensive normalization. If the saved post_status is anything
		// other than one of our three valid values (e.g. the option got written
		// with a legacy/empty/typo value, or a future schema change leaked in),
		// fall back to the documented default rather than letting wp_insert_post
		// silently treat it as something we didn't intend.
		$valid_status = array( 'publish', 'draft', 'pending' );
		if ( ! in_array( $settings['post_status'] ?? '', $valid_status, true ) ) {
			$settings['post_status'] = 'publish';
		}

		return $settings;
	}

	public function save_settings( array $settings ) {
		update_option( self::OPTION_KEY, $settings, false );
	}

	/**
	 * Resolve the active color mode from the global Grid Index theme options
	 * so this page tracks the same dark/light setting as Theme Options.
	 *
	 * @return string 'dark' or 'light'
	 */
	private function color_mode() {
		if ( function_exists( 'gridindex_get_option' ) ) {
			$mode = (string) gridindex_get_option( 'color_mode', 'dark' );
		} else {
			$opts = get_option( 'gridindex_theme_options', array() );
			$mode = isset( $opts['color_mode'] ) ? (string) $opts['color_mode'] : 'dark';
		}
		return ( $mode === 'light' ) ? 'light' : 'dark';
	}

	/* ---------------------------------------------------------------------
	 * Cron
	 * ------------------------------------------------------------------- */

	public function register_cron_schedules( $schedules ) {
		// v1.0.33 — Added 5min and 30min options for breaking-news-heavy sites.
		// See WP-Cron caveat warning in the Settings UI: actual execution
		// depends on site traffic for triggering wp-cron.php, so a 5-min
		// schedule is best paired with a real Linux cron job at the host.
		if ( ! isset( $schedules['gip_rss_5min'] ) ) {
			$schedules['gip_rss_5min'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 Minutes (Grid Index RSS)', 'grid-index-rss-importer' ),
			);
		}
		if ( ! isset( $schedules['gip_rss_15min'] ) ) {
			$schedules['gip_rss_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 Minutes (Grid Index RSS)', 'grid-index-rss-importer' ),
			);
		}
		if ( ! isset( $schedules['gip_rss_30min'] ) ) {
			$schedules['gip_rss_30min'] = array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 30 Minutes (Grid Index RSS)', 'grid-index-rss-importer' ),
			);
		}
		return $schedules;
	}

	public function maybe_reschedule_cron() {
		$s = $this->get_settings();
		$freq = $s['frequency'];

		if ( 'manual' === $freq ) {
			$this->unschedule_cron();
			return;
		}

		$valid = array( 'gip_rss_5min', 'gip_rss_15min', 'gip_rss_30min', 'hourly', 'twicedaily' );
		if ( ! in_array( $freq, $valid, true ) ) {
			$freq = 'hourly';
		}

		$next             = wp_next_scheduled( self::CRON_HOOK );
		$current_schedule = wp_get_schedule( self::CRON_HOOK );
		if ( $current_schedule !== $freq ) {
			$this->unschedule_cron();
			wp_schedule_event( time() + 60, $freq, self::CRON_HOOK );
		} elseif ( ! $next ) {
			wp_schedule_event( time() + 60, $freq, self::CRON_HOOK );
		}
	}

	public function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/* ---------------------------------------------------------------------
	 * Admin page registration + asset enqueue
	 * ------------------------------------------------------------------- */

	public function register_admin_page() {
		// v1.0.51 — Detect the theme's parent menu by walking $GLOBALS['menu']
		// directly, which is the authoritative menu structure WordPress uses
		// to render the sidebar. The previous check (v1.0.46–v1.0.50) looked
		// at $GLOBALS['admin_page_hooks'][$parent_slug], but that global is
		// keyed by HOOK SUFFIX (e.g., 'toplevel_page_gridindex'), not by raw
		// slug. The check therefore always returned false — even when The
		// Grid Index theme was active — and the plugin always took the
		// standalone-top-level branch. That's why menu position 25 wasn't
		// applying for users WITH the theme: they were silently in the
		// fallback branch, which itself was working, but they expected the
		// nested submenu placement.
		//
		// $GLOBALS['menu'] is an array of [position] => [title, cap, slug, ...]
		// entries — index 2 is the slug. We scan it for our parent slug.
		$parent_slug   = 'gridindex';
		$parent_exists = false;
		if ( isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ) {
			foreach ( $GLOBALS['menu'] as $entry ) {
				if ( isset( $entry[2] ) && $entry[2] === $parent_slug ) {
					$parent_exists = true;
					break;
				}
			}
		}

		if ( $parent_exists ) {
			$this->hook_suffix = add_submenu_page(
				$parent_slug,
				__( 'Grid RSS', 'grid-index-rss-importer' ),     // browser tab title
				__( 'Grid RSS', 'grid-index-rss-importer' ),     // menu label
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_admin_page' )
			);
		} else {
			// Standalone top-level menu fallback (theme inactive). v1.0.50 —
			// Position 25 puts it below Comments and above the Appearance/Plugins
			// block, which is the conventional placement for plugin admin pages.
			$this->hook_suffix = add_menu_page(
				__( 'Grid RSS', 'grid-index-rss-importer' ),
				__( 'Grid RSS', 'grid-index-rss-importer' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_admin_page' ),
				'dashicons-rss',
				25
			);
		}

		// Stylesheet enqueue. v1.0.50 — Load the BUNDLED copy of theme-options.css
		// from the plugin's own assets/admin/ first, so the UI renders correctly
		// even when The Grid Index theme is not the active theme (WP.org submission
		// requires the plugin to work standalone — depending on a specific theme
		// being active is grounds for rejection). The bundled copy is a snapshot;
		// if the theme IS active and has a newer version, we layer it on top so
		// theme updates can still customize the look. The original mistake was
		// using get_template_directory() which only resolves to The Grid Index
		// when it's the active theme — when a different theme was active, the
		// path returned someone else's theme directory, the file didn't exist,
		// the enqueue silently failed, and every admin page rendered as plain
		// WordPress admin without any of our card / pill / button styling.
		add_action( 'admin_enqueue_scripts', function( $screen_hook ) {
			if ( ! $this->is_importer_screen() ) return;

			$ver           = GRID_INDEX_RSS_IMPORTER_VERSION;
			$bundled_path  = plugin_dir_path( __FILE__ ) . 'assets/admin/theme-options.css';
			$bundled_url   = plugin_dir_url( __FILE__ ) . 'assets/admin/theme-options.css';

			// v1.0.51 — Defensive admin notice if the bundled CSS is missing.
			// On some hosts, the "Replace current plugin" upload path has been
			// observed to skip subdirectories — leaving the plugin's PHP files
			// in place but never extracting the assets/ folder. Without the
			// stylesheet, the entire admin UI renders unstyled. This notice
			// makes the failure visible instead of silent.
			if ( ! file_exists( $bundled_path ) ) {
				add_action( 'admin_notices', function() use ( $bundled_path ) {
					echo '<div class="notice notice-error"><p><strong>Grid Index RSS Importer:</strong> Bundled stylesheet missing at <code>' . esc_html( $bundled_path ) . '</code>. The plugin upload may have skipped the <code>assets/</code> folder. Please re-upload the plugin or extract manually.</p></div>';
				} );
				return;
			}

			// Always load the bundled copy as the base layer.
			wp_enqueue_style(
				'gip-rss-importer-shell',
				$bundled_url,
				array(),
				$ver . '.' . filemtime( $bundled_path )
			);

			// If The Grid Index theme is active AND has its own copy, layer it
			// on top so theme updates can override individual rules. Detected by
			// matching the active stylesheet directory name against the theme
			// slug — guarding against other themes that happen to ship a file
			// at the same path.
			$theme        = wp_get_theme();
			$is_grid_idx  = ( $theme->get_stylesheet() === 'the-grid-index' || $theme->get_template() === 'the-grid-index' );
			if ( $is_grid_idx ) {
				$theme_css = get_template_directory() . '/assets/admin/theme-options.css';
				if ( file_exists( $theme_css ) ) {
					wp_enqueue_style(
						'gip-rss-importer-shell-theme',
						get_template_directory_uri() . '/assets/admin/theme-options.css',
						array( 'gip-rss-importer-shell' ),
						$ver . '.' . filemtime( $theme_css )
					);
				}
			}
		} );

		// Drop wp-admin's left/right padding around our shell, like Theme Options does.
		add_filter( 'admin_body_class', function( $c ) {
			if ( $this->is_importer_screen() ) {
				$c .= ' gi-options-host';
			}
			return $c;
		} );
	}

	/* ---------------------------------------------------------------------
	 * Admin page render
	 * ------------------------------------------------------------------- */

	/**
	 * v1.0.47 — Detect whether the current admin request is rendering the
	 * Grid RSS page. Used by CSS enqueue and body-class hooks. Robust to
	 * different menu placements (top-level, Grid Index submenu, future
	 * relocations) because it matches by the `page` query arg rather than
	 * the captured hook suffix.
	 *
	 * Returns true when either:
	 *   - The current URL contains `?page=gip-rss-importer` (catches the
	 *     normal admin page load and admin-post.php / admin-ajax.php).
	 *   - get_current_screen() reports a screen ID that ends in our slug
	 *     (catches edge cases after late hook switches).
	 */
	private function is_importer_screen() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === self::PAGE_SLUG ) {
			return true;
		}
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && is_string( $screen->id ) ) {
				// Match anything like *_page_gip-rss-importer (top-level,
				// submenu, tools, options — all end in our slug).
				if ( substr( $screen->id, -strlen( self::PAGE_SLUG ) ) === self::PAGE_SLUG ) {
					return true;
				}
			}
		}
		return false;
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$s          = $this->get_settings();
		$mode       = $this->color_mode();
		$mode_class = 'gi-mode-' . $mode;
		$mode_label = ucfirst( $mode );
		$next_run   = wp_next_scheduled( self::CRON_HOOK );

		$msg  = isset( $_GET['gip_rss_msg'] )  ? sanitize_text_field( wp_unslash( $_GET['gip_rss_msg'] ) )  : '';
		$type = isset( $_GET['gip_rss_type'] ) ? sanitize_text_field( wp_unslash( $_GET['gip_rss_type'] ) ) : 'success';

		$save_url = admin_url( 'admin-post.php' );
		?>
		<div class="wrap gridindex-theme-options <?php echo esc_attr( $mode_class ); ?>">

			<div class="gi-hero">
				<div class="gi-hero__inner">
					<div class="gi-hero__brand">
						<span class="gi-hero__eyebrow"><?php esc_html_e( 'The Grid Index', 'grid-index-rss-importer' ); ?></span>
						<h1 class="gi-hero__title"><?php esc_html_e( 'Grid RSS', 'grid-index-rss-importer' ); ?></h1>
						<p class="gi-hero__sub"><?php esc_html_e( 'Pull headlines from external feeds into Grid Index. Imported posts are auto-tagged with their original source so the theme shows attribution and respects the "Hide comments on imported RSS" toggle.', 'grid-index-rss-importer' ); ?></p>
					</div>
					<div class="gi-hero__meta">
						<span class="gi-badge <?php echo $mode === 'dark' ? 'gi-badge--dark' : ''; ?>">
							<?php printf( esc_html__( 'Mode: %s', 'grid-index-rss-importer' ), esc_html( $mode_label ) ); ?>
						</span>
						<span class="gi-badge"><?php printf( esc_html__( 'v%s', 'grid-index-rss-importer' ), esc_html( GRID_INDEX_RSS_IMPORTER_VERSION ) ); ?></span>
						<?php
						// v1.0.53 — Theme pairing reference. v1.0.54 — Only show
						// when theme is INACTIVE. v1.0.59 briefly made it a link
						// to thegridindex.com; v1.0.60 reverts to the passive
						// muted indicator per direction — informational only, no
						// link, no CTA. The theme isn't on WordPress.org yet so
						// there's no useful destination to point at.
						$active_theme = wp_get_theme();
						$theme_active = ( $active_theme->get_stylesheet() === 'the-grid-index'
							|| $active_theme->get_template() === 'the-grid-index' );
						if ( ! $theme_active ) :
							$theme_pill_title = __( 'Designed to pair with The Grid Index theme. The plugin works standalone, but theme-specific features (Read at Source button, hide-comments toggle) only activate when the theme is.', 'grid-index-rss-importer' );
						?>
							<span class="gi-badge gi-badge--muted" title="<?php echo esc_attr( $theme_pill_title ); ?>">
								<?php esc_html_e( 'Theme: The Grid Index — not active', 'grid-index-rss-importer' ); ?>
							</span>
						<?php endif; ?>
						<?php
						$rss_term_badge = get_term_by( 'slug', self::RSS_CAT_SLUG, 'category' );
						if ( $rss_term_badge && ! is_wp_error( $rss_term_badge ) ) :
							$badge_link = admin_url( 'edit.php?category_name=' . self::RSS_CAT_SLUG );
						?>
							<a class="gi-badge" style="text-decoration:none;" href="<?php echo esc_url( $badge_link ); ?>">
								<?php
								printf(
									/* translators: 1: post count */
									esc_html__( 'Category: RSS (%d)', 'grid-index-rss-importer' ),
									(int) $rss_term_badge->count
								);
								?>
							</a>
						<?php endif; ?>
						<?php if ( $next_run && $s['frequency'] !== 'manual' ) : ?>
							<span class="gi-badge gi-badge--success">
								<?php
								printf(
									/* translators: %s: human time difference */
									esc_html__( 'Next run in %s', 'grid-index-rss-importer' ),
									esc_html( human_time_diff( time(), $next_run ) )
								);
								?>
							</span>
						<?php elseif ( $s['frequency'] === 'manual' ) : ?>
							<span class="gi-badge gi-badge--warning">
								<?php esc_html_e( 'Manual only', 'grid-index-rss-importer' ); ?>
							</span>
						<?php endif; ?>
						<?php
						// v1.0.48 — Exact date+time of the last completed import,
						// shown as a green pill alongside the existing pills.
						// Uses wp_date() so it respects the site's timezone and the
						// configured date/time format from Settings → General.
						if ( ! empty( $s['last_run'] ) ) :
							$last_run_fmt = wp_date(
								get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
								(int) $s['last_run']
							);
						?>
							<span class="gi-badge gi-badge--success" title="<?php
								/* translators: %s: relative time, e.g. "5 minutes ago" */
								printf( esc_attr__( '%s ago', 'grid-index-rss-importer' ), esc_attr( human_time_diff( (int) $s['last_run'], time() ) ) );
							?>">
								<?php
								printf(
									/* translators: %s: localized date+time */
									esc_html__( 'Last run: %s', 'grid-index-rss-importer' ),
									esc_html( $last_run_fmt )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<?php
			// v1.0.15 — Prominent status banner. Shows the current import
			// post_status front and center. If it's not 'publish', surface a
			// big one-click button that flips the setting AND republishes
			// existing drafts. We landed here because the buried Import
			// Settings dropdown was easy to miss.
			$current_status = $s['post_status'] ?? 'publish';
			?>
			<?php
			// v1.0.20 — Status indicator. When everything is fine (post_status
			// === 'publish'), this is a quiet inline pill, not a full-width
			// banner — the page shouldn't shout about a normal state. When
			// attention IS needed (draft/pending), keep the loud full-width
			// warning with the one-click fix button.
			$current_status = $s['post_status'] ?? 'publish';
			?>
			<?php if ( $current_status === 'publish' ) : ?>
				<div class="gip-status-pill gip-status-pill--ok" title="<?php esc_attr_e( 'Imported posts will be published immediately. Change in Settings → New post status.', 'grid-index-rss-importer' ); ?>">
					<?php esc_html_e( '✓ Publish mode', 'grid-index-rss-importer' ); ?>
				</div>
			<?php else : ?>
				<div class="gip-status-banner gip-status-banner--warn">
					<div class="gip-status-banner__msg">
						<strong><?php
						printf(
							/* translators: %s current post_status */
							esc_html__( 'Import status: %s.', 'grid-index-rss-importer' ),
							esc_html( ucfirst( $current_status ) )
						);
						?></strong>
						<?php esc_html_e( 'New imports are NOT publishing immediately. Click the button to fix this and publish any existing imported drafts.', 'grid-index-rss-importer' ); ?>
					</div>
					<form method="post" action="<?php echo esc_url( $save_url ); ?>" style="margin:0;">
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>_set_publish" />
						<button type="submit" class="button button-primary button-hero gip-status-banner__btn">
							<?php esc_html_e( 'Switch to Publish & fix existing drafts', 'grid-index-rss-importer' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>

			<div class="gi-shell" style="grid-template-columns:1fr;">

				<div class="gi-main">

					<?php if ( $msg ) :
						$notice_class    = ( $type === 'error' ) ? 'gi-notice gi-notice--reset' : 'gi-notice';
						// v1.0.36 — When the redirect came from a catalog ADD,
						// pass the new feed's index + name through to the toast
						// JS so it can render a "Fetch now" button.
						$added_idx_attr  = '';
						$added_name_attr = '';
						if ( isset( $_GET['gip_rss_added_idx'] ) && $_GET['gip_rss_added_idx'] !== '' ) {
							$added_idx_attr  = ' data-added-idx="' . esc_attr( (int) $_GET['gip_rss_added_idx'] ) . '"';
						}
						if ( isset( $_GET['gip_rss_added_name'] ) ) {
							$added_name_attr = ' data-added-name="' . esc_attr( sanitize_text_field( wp_unslash( $_GET['gip_rss_added_name'] ) ) ) . '"';
						}
					?>
						<div class="<?php echo esc_attr( $notice_class ); ?>"<?php echo $added_idx_attr . $added_name_attr; ?>><?php echo esc_html( $msg ); ?></div>
					<?php endif; ?>

					<nav class="gip-tabs" role="tablist" aria-label="<?php esc_attr_e( 'RSS Importer sections', 'grid-index-rss-importer' ); ?>">
						<button type="button" class="gip-tab is-active" data-tab="feeds" role="tab" aria-selected="true"><?php esc_html_e( 'Feeds', 'grid-index-rss-importer' ); ?></button>
						<button type="button" class="gip-tab" data-tab="catalog" role="tab"><?php esc_html_e( 'Catalog', 'grid-index-rss-importer' ); ?></button>
						<button type="button" class="gip-tab" data-tab="settings" role="tab"><?php esc_html_e( 'Settings', 'grid-index-rss-importer' ); ?></button>
						<button type="button" class="gip-tab" data-tab="diagnostics" role="tab"><?php esc_html_e( 'Diagnostics', 'grid-index-rss-importer' ); ?></button>
						<button type="button" class="gip-tab" data-tab="support" role="tab"><?php esc_html_e( 'Support', 'grid-index-rss-importer' ); ?></button>
					</nav>

					<?php // v1.0.26 — Hidden forms so the Run/Force buttons inside the main settings form (Feeds toolbar) can submit them via the HTML5 `form=` attribute. ?>
					<form id="gip-run-form" method="post" action="<?php echo esc_url( $save_url ); ?>" style="display:none;" data-gip-long-action="import">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>_run" />
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					</form>
					<form id="gip-force-form" method="post" action="<?php echo esc_url( $save_url ); ?>" style="display:none;" data-gip-long-action="force">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>_force" />
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					</form>

					<form method="post" action="<?php echo esc_url( $save_url ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>_save" />
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

						<!-- ============== FEEDS TAB ============== -->
						<div class="gip-tab-panel is-active" data-panel="feeds" role="tabpanel">
						<div class="gi-card">
							<div class="gi-card__head">
								<h2 class="gi-card__title"><?php esc_html_e( 'Feeds', 'grid-index-rss-importer' ); ?></h2>
								<p class="gi-card__sub"><?php
									$rss_term = get_term_by( 'slug', self::RSS_CAT_SLUG, 'category' );
									if ( $rss_term && ! is_wp_error( $rss_term ) ) {
										$cat_link = admin_url( 'edit.php?category_name=' . self::RSS_CAT_SLUG );
										printf(
											/* translators: 1: category link */
											wp_kses_post( __( 'All imported posts go into the dedicated %1$s category. Add one feed per row.', 'grid-index-rss-importer' ) ),
											'<a href="' . esc_url( $cat_link ) . '"><strong>' . esc_html( self::RSS_CAT_NAME ) . '</strong></a>'
										);
									} else {
										esc_html_e( 'Add one feed per row. Imported posts will be auto-tagged with their original source.', 'grid-index-rss-importer' );
									}
								?></p>
							</div>
							<div class="gi-card__body">

								<?php
								// v1.0.42 — Duplicate-detection banner. Only renders when
								// at least one duplicate group exists. The full detector
								// and merge tool live on the Diagnostics tab; this banner
								// surfaces the problem where the user is actually working.
								$dup_summary = $this->count_duplicate_summary();
								if ( $dup_summary['groups'] > 0 ) :
									$diag_url = esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ) . '#diagnostics' );
								?>
									<div class="gip-dup-banner" role="alert">
										<div class="gip-dup-banner__icon">⚠</div>
										<div class="gip-dup-banner__msg">
											<strong><?php
												printf(
													/* translators: 1: group count, 2: surplus count */
													esc_html(
														_n(
															'%1$d duplicate group found across your RSS posts (%2$d extra post can be merged).',
															'%1$d duplicate groups found across your RSS posts (%2$d extra posts can be merged).',
															$dup_summary['groups'], 'grid-index-rss-importer'
														)
													),
													(int) $dup_summary['groups'],
													(int) $dup_summary['surplus']
												);
											?></strong>
											<span class="gip-dup-banner__sub"><?php esc_html_e( 'Some feeds republish the same item with different GUIDs, or a force-reimport left extras behind. Review and merge them from the Diagnostics tab.', 'grid-index-rss-importer' ); ?></span>
										</div>
										<a class="gi-btn gi-btn--ghost gip-dup-banner__btn" href="<?php echo esc_url( $diag_url ); ?>" data-jump-to-dup="1">
											<?php esc_html_e( 'Review on Diagnostics', 'grid-index-rss-importer' ); ?>
										</a>
									</div>
								<?php endif; ?>

								<?php if ( empty( $s['feeds'] ) ) : ?>
									<div class="gip-empty-nudge">
										<strong><?php esc_html_e( 'No feeds yet.', 'grid-index-rss-importer' ); ?></strong>
										<?php esc_html_e( 'The fastest way to get started is the Catalog tab — pick from 30 curated, verified-working feeds. Or paste a feed URL into the row below.', 'grid-index-rss-importer' ); ?>
									</div>
								<?php endif; ?>

								<div class="gip-feeds-toolbar">
									<span class="gip-save-indicator" id="gip-save-indicator" aria-live="polite"></span>
									<button type="submit" form="gip-run-form" class="gi-btn gi-btn--primary"><?php esc_html_e( '↻ Import Now', 'grid-index-rss-importer' ); ?></button>
									<button type="submit" form="gip-force-form" class="gi-btn gi-btn--ghost"
										onclick="return confirm('<?php echo esc_js( __( 'Force re-import will DELETE existing copies of items published in the last 24 hours and re-fetch them fresh. The deletes are permanent. Continue?', 'grid-index-rss-importer' ) ); ?>');">
										<?php esc_html_e( '⟳ Force re-import 24h', 'grid-index-rss-importer' ); ?>
									</button>
									<span style="opacity:.4;">·</span>
									<button type="button" class="gi-btn" id="gip-rss-add-row-top">+ <?php esc_html_e( 'Add Feed', 'grid-index-rss-importer' ); ?></button>
								</div>

								<div id="gip-rss-feeds-list" class="gip-rss-feeds-list">
									<?php
									$feeds = ! empty( $s['feeds'] ) ? $s['feeds'] : array( array( 'url' => '', 'name' => '' ) );
									?>
									<div class="gip-rss-feeds-header">
										<span class="gip-rss-feeds-header__cell gip-rss-feeds-header__cell--status"></span>
										<span class="gip-rss-feeds-header__cell gip-rss-feeds-header__cell--url"><?php esc_html_e( 'Feed URL', 'grid-index-rss-importer' ); ?></span>
										<span class="gip-rss-feeds-header__cell gip-rss-feeds-header__cell--name"><?php esc_html_e( 'Source Name', 'grid-index-rss-importer' ); ?></span>
										<span class="gip-rss-feeds-header__cell gip-rss-feeds-header__cell--interval"><?php esc_html_e( 'Interval', 'grid-index-rss-importer' ); ?></span>
										<span class="gip-rss-feeds-header__cell gip-rss-feeds-header__cell--fetched"><?php esc_html_e( 'Last Fetched', 'grid-index-rss-importer' ); ?></span>
										<span class="gip-rss-feeds-header__cell gip-rss-feeds-header__cell--actions"></span>
									</div>
									<?php
									foreach ( $feeds as $i => $feed ) :
										$url           = isset( $feed['url'] )  ? $feed['url']  : '';
										$name          = isset( $feed['name'] ) ? $feed['name'] : '';
										$last_status   = $feed['last_status']   ?? '';
										$last_message  = $feed['last_message']  ?? '';
										$last_fetched  = (int) ( $feed['last_fetched'] ?? 0 );

										// Status dot color by state. 'never' = unknown/not yet run.
										$dot_class = 'gip-dot--never';
										if ( $last_status === 'ok' )      $dot_class = 'gip-dot--ok';
										if ( $last_status === 'all-dup' ) $dot_class = 'gip-dot--dup';
										if ( $last_status === 'empty' )   $dot_class = 'gip-dot--empty';
										if ( $last_status === 'error' )   $dot_class = 'gip-dot--error';

										// Build the tooltip / detail string lazily — only renders if we have anything.
										$detail_bits = array();
										if ( $last_status )  $detail_bits[] = strtoupper( $last_status );
										if ( $last_message ) $detail_bits[] = $last_message;
										if ( $last_fetched ) {
											$detail_bits[] = sprintf(
												/* translators: %s: human time difference */
												__( 'last fetch %s ago', 'grid-index-rss-importer' ),
												human_time_diff( $last_fetched, time() )
											);
										}
										$detail = implode( ' · ', $detail_bits );
										if ( ! $detail ) $detail = __( 'Never fetched', 'grid-index-rss-importer' );

										$fetch_url = wp_nonce_url(
											add_query_arg( array(
												'action'     => self::PAGE_SLUG . '_fetch_one',
												'feed_index' => (int) $i,
											), admin_url( 'admin-post.php' ) ),
											self::NONCE_ACTION,
											self::NONCE_NAME
										);
									?>
										<div class="gip-rss-feed-row">
											<span class="gip-rss-feed-row__cell gip-rss-feed-row__cell--status">
												<span class="gip-dot <?php echo esc_attr( $dot_class ); ?>"
												      title="<?php echo esc_attr( $detail ); ?>"
												      data-detail="<?php echo esc_attr( $detail ); ?>"
												      tabindex="0"
												      aria-label="<?php echo esc_attr( $detail ); ?>"></span>
											</span>
											<span class="gip-rss-feed-row__cell gip-rss-feed-row__cell--url">
												<input class="gi-input" type="url" name="feeds[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com/feed/" />
											</span>
											<span class="gip-rss-feed-row__cell gip-rss-feed-row__cell--name">
												<input class="gi-input" type="text" name="feeds[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Source name', 'grid-index-rss-importer' ); ?>" />
												<?php
												// v1.0.40 — per-feed category picker. Empty value = RSS only.
												$current_cat = isset( $feed['category'] ) && isset( self::GRANULAR_CATEGORIES[ $feed['category'] ] )
													? $feed['category'] : '';
												?>
												<select class="gi-select gip-rss-feed-row__cat" name="feeds[<?php echo (int) $i; ?>][category]" title="<?php esc_attr_e( 'Granular category for posts from this feed (in addition to RSS).', 'grid-index-rss-importer' ); ?>">
													<option value="" <?php selected( $current_cat, '' ); ?>><?php esc_html_e( '— RSS only —', 'grid-index-rss-importer' ); ?></option>
													<?php foreach ( self::GRANULAR_CATEGORIES as $key => $cfg ) : ?>
														<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_cat, $key ); ?>><?php echo esc_html( $cfg['name'] ); ?></option>
													<?php endforeach; ?>
												</select>
											</span>
											<span class="gip-rss-feed-row__cell gip-rss-feed-row__cell--interval">
												<?php
												// v1.0.34 — Per-feed interval picker.
												$current_interval = isset( $feed['interval'] ) && in_array( $feed['interval'], self::VALID_INTERVALS, true )
													? $feed['interval']
													: $this->get_recommended_interval_for_url( $url );
												?>
												<select class="gi-select" name="feeds[<?php echo (int) $i; ?>][interval]">
													<?php foreach ( self::VALID_INTERVALS as $iv ) : ?>
														<option value="<?php echo esc_attr( $iv ); ?>" <?php selected( $current_interval, $iv ); ?>><?php echo esc_html( $this->interval_label( $iv ) ); ?></option>
													<?php endforeach; ?>
												</select>
											</span>
											<span class="gip-rss-feed-row__cell gip-rss-feed-row__cell--fetched">
												<?php
												if ( $last_fetched ) {
													printf(
														/* translators: %s: human-readable time difference */
														'<span class="gip-rss-feed-row__fetched" title="%s">%s</span>',
														esc_attr( wp_date( 'M j, Y g:i a', $last_fetched ) ),
														sprintf(
															/* translators: %s: human-readable diff like "2 minutes" */
															esc_html__( '%s ago', 'grid-index-rss-importer' ),
															esc_html( human_time_diff( $last_fetched, time() ) )
														)
													);
												} else {
													echo '<span class="gip-rss-feed-row__fetched gip-rss-feed-row__fetched--never">' . esc_html__( 'Never', 'grid-index-rss-importer' ) . '</span>';
												}
												?>
											</span>
											<span class="gip-rss-feed-row__cell gip-rss-feed-row__cell--actions">
												<a href="<?php echo esc_url( $fetch_url ); ?>" class="gi-btn gi-btn--ghost gip-rss-feed-row__btn gip-fetch-link" data-gip-long-action="fetch" title="<?php esc_attr_e( 'Fetch this feed now', 'grid-index-rss-importer' ); ?>">↻</a>
												<button type="button" class="gi-btn gi-btn--ghost gip-rss-feed-row__btn gip-rss-remove-row" title="<?php esc_attr_e( 'Remove this feed', 'grid-index-rss-importer' ); ?>">×</button>
											</span>
											<div class="gip-rss-feed-row__detail" hidden><?php echo esc_html( $detail ); ?></div>
										</div>
									<?php endforeach; ?>
								</div>

								<div style="margin-top:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
									<button type="submit" class="gi-btn gi-btn--primary"><?php esc_html_e( 'Save Feeds', 'grid-index-rss-importer' ); ?></button>
									<span style="opacity:.5;">·</span>
									<a href="<?php echo esc_url( wp_nonce_url(
										add_query_arg( array( 'action' => self::PAGE_SLUG . '_restore' ), admin_url( 'admin-post.php' ) ),
										self::NONCE_ACTION,
										self::NONCE_NAME
									) ); ?>"
									   class="gi-btn gi-btn--ghost"
									   onclick="return confirm('<?php echo esc_js( __( 'Replace your current feed list with the default starter feeds (NYT, BBC, AP, Guardian, etc.)? Your current list will be overwritten.', 'grid-index-rss-importer' ) ); ?>');">
										<?php esc_html_e( '↻ Restore default feeds', 'grid-index-rss-importer' ); ?>
									</a>
									<span style="opacity:.5;">·</span>
									<a href="<?php echo esc_url( wp_nonce_url(
										add_query_arg( array( 'action' => self::PAGE_SLUG . '_republish' ), admin_url( 'admin-post.php' ) ),
										self::NONCE_ACTION,
										self::NONCE_NAME
									) ); ?>"
									   class="gi-btn gi-btn--ghost"
									   onclick="return confirm('<?php echo esc_js( __( 'Publish ALL existing draft posts that were imported by this RSS plugin? This is intended to repair posts that landed as drafts under earlier settings. Cannot be undone in bulk.', 'grid-index-rss-importer' ) ); ?>');">
										<?php esc_html_e( '⇪ Publish all RSS drafts', 'grid-index-rss-importer' ); ?>
									</a>
									<span style="opacity:.5;">·</span>
									<a href="<?php echo esc_url( wp_nonce_url(
										add_query_arg( array( 'action' => self::PAGE_SLUG . '_clear_feeds' ), admin_url( 'admin-post.php' ) ),
										self::NONCE_ACTION,
										self::NONCE_NAME
									) ); ?>"
									   class="gi-btn gi-btn--ghost"
									   style="color:#ef4444;"
									   onclick="return confirm('<?php echo esc_js( __( 'Remove ALL feeds from the importer? This wipes the entire feed list. Other settings (post status, frequency, image rules) are preserved. Already-imported posts are NOT deleted.', 'grid-index-rss-importer' ) ); ?>');">
										<?php esc_html_e( '🗑 Clear all feeds', 'grid-index-rss-importer' ); ?>
									</a>
									</div>
								</div>
							</div>
						</div><!-- /.gip-tab-panel feeds -->

						<!-- ============== SETTINGS TAB ============== -->
						<div class="gip-tab-panel" data-panel="settings" role="tabpanel" hidden>

							<!-- ============== IMPORT SETTINGS CARD ============== -->
							<div class="gi-card">
							<div class="gi-card__head">
								<h2 class="gi-card__title"><?php esc_html_e( 'Import Settings', 'grid-index-rss-importer' ); ?></h2>
								<p class="gi-card__sub"><?php esc_html_e( 'How and how often new items are pulled in.', 'grid-index-rss-importer' ); ?></p>
							</div>
							<div class="gi-card__body">
								<div class="gi-grid gi-grid--3">
									<div class="gi-field">
										<label class="gi-field__label" for="gip-rss-status"><?php esc_html_e( 'New post status', 'grid-index-rss-importer' ); ?></label>
										<select id="gip-rss-status" class="gi-select" name="post_status">
											<option value="publish" <?php selected( $s['post_status'], 'publish' ); ?>><?php esc_html_e( 'Publish immediately', 'grid-index-rss-importer' ); ?></option>
											<option value="draft"   <?php selected( $s['post_status'], 'draft' );   ?>><?php esc_html_e( 'Draft (review first)', 'grid-index-rss-importer' ); ?></option>
											<option value="pending" <?php selected( $s['post_status'], 'pending' ); ?>><?php esc_html_e( 'Pending review', 'grid-index-rss-importer' ); ?></option>
										</select>
										<p class="gi-field__desc"><?php esc_html_e( 'Status of newly imported posts.', 'grid-index-rss-importer' ); ?></p>
									</div>

									<div class="gi-field">
										<label class="gi-field__label" for="gip-rss-freq"><?php esc_html_e( 'Check frequency', 'grid-index-rss-importer' ); ?></label>
										<select id="gip-rss-freq" class="gi-select" name="frequency">
											<option value="gip_rss_5min"  <?php selected( $s['frequency'], 'gip_rss_5min' );  ?>><?php esc_html_e( 'Every 5 minutes', 'grid-index-rss-importer' ); ?></option>
											<option value="gip_rss_15min" <?php selected( $s['frequency'], 'gip_rss_15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'grid-index-rss-importer' ); ?></option>
											<option value="gip_rss_30min" <?php selected( $s['frequency'], 'gip_rss_30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'grid-index-rss-importer' ); ?></option>
											<option value="hourly"        <?php selected( $s['frequency'], 'hourly' );        ?>><?php esc_html_e( 'Hourly', 'grid-index-rss-importer' ); ?></option>
											<option value="twicedaily"    <?php selected( $s['frequency'], 'twicedaily' );    ?>><?php esc_html_e( 'Twice daily', 'grid-index-rss-importer' ); ?></option>
											<option value="manual"        <?php selected( $s['frequency'], 'manual' );        ?>><?php esc_html_e( 'Manual only', 'grid-index-rss-importer' ); ?></option>
										</select>
										<p class="gi-field__desc"><?php esc_html_e( 'How often the cron checks for feeds due to fetch. Each feed has its own interval (set on the Feeds tab); this is the polling rate. Match this to your fastest feed\'s interval — feeds with slower intervals will be skipped until they\'re due.', 'grid-index-rss-importer' ); ?></p>
										<?php if ( in_array( $s['frequency'], array( 'gip_rss_5min', 'gip_rss_15min' ), true ) ) : ?>
											<p class="gip-freq-warn">
												<strong><?php esc_html_e( 'Heads up:', 'grid-index-rss-importer' ); ?></strong>
												<?php esc_html_e( 'WP-Cron only fires when someone visits your site. For reliable 5–15 minute intervals, add a real cron job at your host that hits wp-cron.php every minute (Hostinger → Cron Jobs). Without that, short intervals can lag on quiet sites.', 'grid-index-rss-importer' ); ?>
											</p>
										<?php endif; ?>
									</div>

									<div class="gi-field">
										<label class="gi-field__label" for="gip-rss-image"><?php esc_html_e( 'Featured image', 'grid-index-rss-importer' ); ?></label>
										<select id="gip-rss-image" class="gi-select" name="image_mode">
											<option value="feed_first"    <?php selected( $s['image_mode'], 'feed_first' );    ?>><?php esc_html_e( 'Try feed image, then content', 'grid-index-rss-importer' ); ?></option>
											<option value="content_first" <?php selected( $s['image_mode'], 'content_first' ); ?>><?php esc_html_e( 'First image from content only', 'grid-index-rss-importer' ); ?></option>
											<option value="none"          <?php selected( $s['image_mode'], 'none' );          ?>><?php esc_html_e( 'No featured image', 'grid-index-rss-importer' ); ?></option>
										</select>
										<p class="gi-field__desc"><?php esc_html_e( 'Where to source the post thumbnail from.', 'grid-index-rss-importer' ); ?></p>
									</div>

									<div class="gi-field">
										<label class="gi-field__label" for="gip-rss-min-width"><?php esc_html_e( 'Minimum image width (px)', 'grid-index-rss-importer' ); ?></label>
										<input id="gip-rss-min-width" class="gi-input" type="number" name="min_image_width" value="<?php echo (int) ( $s['min_image_width'] ?? 1000 ); ?>" min="0" max="4000" step="50" />
										<p class="gi-field__desc"><?php esc_html_e( 'Skip imports whose source image is narrower than this. Defaults to 1000px so the homepage hero stays sharp. Set to 0 to disable the check.', 'grid-index-rss-importer' ); ?></p>
									</div>

									<div class="gi-field">
										<label class="gi-field__label" for="gip-rss-max"><?php esc_html_e( 'Max items per feed per run', 'grid-index-rss-importer' ); ?></label>
										<input id="gip-rss-max" class="gi-input" type="number" name="max_per_run" value="<?php echo (int) $s['max_per_run']; ?>" min="1" max="100" />
										<p class="gi-field__desc"><?php esc_html_e( 'Safety cap so a backlogged feed never floods your site.', 'grid-index-rss-importer' ); ?></p>
									</div>
							</div>

								<?php // v1.0.37 — Data persistence on uninstall ?>
								<div class="gip-uninstall-pref">
									<input type="hidden" name="keep_on_uninstall_present" value="1" />
									<label class="gip-uninstall-pref__label">
										<input type="checkbox" name="keep_on_uninstall" value="1" <?php checked( ! empty( $s['keep_on_uninstall'] ) ); ?> />
										<span>
											<strong><?php esc_html_e( 'Keep my data if I uninstall this plugin', 'grid-index-rss-importer' ); ?></strong>
											<span class="gip-uninstall-pref__desc">
												<?php esc_html_e( 'When enabled, deleting this plugin in WordPress preserves your feed list, settings, and dedupe history — reinstalling restores everything. Default OFF: a routine delete will wipe plugin data. Imported posts are kept either way regardless of this setting.', 'grid-index-rss-importer' ); ?>
											</span>
										</span>
									</label>
								</div>

								<div style="margin-top:18px; display:flex; gap:10px;">
									<button type="submit" class="gi-btn gi-btn--primary"><?php esc_html_e( 'Save Settings', 'grid-index-rss-importer' ); ?></button>
								</div>
							</div>
						</div>
					</form>
					</div><!-- /.gip-tab-panel settings -->

					<!-- ============== CATALOG TAB ============== -->
					<?php
					$catalog       = $this->get_catalog_feeds();
					$active_urls   = array();
					if ( ! empty( $s['feeds'] ) && is_array( $s['feeds'] ) ) {
						foreach ( $s['feeds'] as $f ) {
							if ( ! empty( $f['url'] ) ) $active_urls[ $f['url'] ] = true;
						}
					}
					$active_count = count( $s['feeds'] ?? array() );

					// v1.0.35 — Group catalog feeds. Sections render in a fixed
					// order with a virtual "Breaking" section at the top that
					// pulls every feed with recommended_interval=5min — fastest
					// publishers, surfaced for breaking-news use cases. The
					// breaking entries also still appear in their home category
					// so users can see them in context.
					$grouped  = array();
					$breaking = array();
					foreach ( $catalog as $cf ) {
						$grouped[ $cf['category'] ][] = $cf;
						if ( ( $cf['recommended_interval'] ?? '' ) === '5min' ) {
							$breaking[] = $cf;
						}
					}

					// Fixed display order. Categories not in this list fall to the end.
					$cat_order = array( 'News', 'World', 'Tech', 'Business', 'Science' );
					$ordered = array();
					if ( ! empty( $breaking ) ) {
						$ordered['Breaking News'] = $breaking;
					}
					foreach ( $cat_order as $cn ) {
						if ( isset( $grouped[ $cn ] ) ) {
							$ordered[ $cn ] = $grouped[ $cn ];
							unset( $grouped[ $cn ] );
						}
					}
					foreach ( $grouped as $cn => $list ) {
						$ordered[ $cn ] = $list; // any unrecognized categories at the end
					}

					// View toggle: ?view=list switches to compact rows; default cards.
					$view_mode = ( isset( $_GET['view'] ) && $_GET['view'] === 'list' ) ? 'list' : 'cards';
					$cards_url = esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ) . '#catalog' );
					$list_url  = esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'view' => 'list' ), admin_url( 'admin.php' ) ) . '#catalog' );
					?>
					<div class="gip-tab-panel" data-panel="catalog" role="tabpanel" hidden>
						<div class="gi-card">
							<div class="gi-card__head">
								<h2 class="gi-card__title"><?php esc_html_e( 'Catalog', 'grid-index-rss-importer' ); ?></h2>
								<p class="gi-card__sub">
									<?php
									$catalog_total = count( $catalog );
									if ( $active_count > self::MAX_ACTIVE_FEEDS ) {
										printf(
											/* translators: 1: catalog total, 2: active count, 3: max active */
											esc_html__( '%1$d curated, verified-working feeds. You currently have %2$d active feeds — over the limit of %3$d.', 'grid-index-rss-importer' ),
											(int) $catalog_total,
											(int) $active_count,
											(int) self::MAX_ACTIVE_FEEDS
										);
									} else {
										printf(
											/* translators: 1: catalog total, 2: active count, 3: max active */
											esc_html__( '%1$d curated, verified-working feeds. You currently have %2$d of %3$d active feeds.', 'grid-index-rss-importer' ),
											(int) $catalog_total,
											(int) $active_count,
											(int) self::MAX_ACTIVE_FEEDS
										);
									}
									?>
								</p>
							</div>

							<?php if ( $active_count > self::MAX_ACTIVE_FEEDS ) :
								$trim_url = wp_nonce_url(
									add_query_arg( array( 'action' => self::PAGE_SLUG . '_trim_to_cap' ), admin_url( 'admin-post.php' ) ),
									self::NONCE_ACTION,
									self::NONCE_NAME
								);
								$over_by = $active_count - self::MAX_ACTIVE_FEEDS;
							?>
								<div class="gip-catalog-warn">
									<div class="gip-catalog-warn__msg">
										<strong><?php esc_html_e( 'Over the cap.', 'grid-index-rss-importer' ); ?></strong>
										<?php
										printf(
											/* translators: 1: number over, 2: max active */
											esc_html(
												_n(
													'You have %1$d feed more than the %2$d-feed limit. Until you trim, the Catalog can\'t add new feeds — but you can still remove active ones.',
													'You have %1$d feeds more than the %2$d-feed limit. Until you trim, the Catalog can\'t add new feeds — but you can still remove active ones.',
													$over_by, 'grid-index-rss-importer'
												)
											),
											(int) $over_by,
											(int) self::MAX_ACTIVE_FEEDS
										);
										?>
									</div>
									<a href="<?php echo esc_url( $trim_url ); ?>" class="gi-btn gi-btn--primary gip-catalog-warn__btn"
									   onclick="return confirm('<?php
											printf(
												esc_attr__( 'Trim your feed list to %d? This keeps the first %d feeds in saved order and removes the rest. You can re-add any catalog feed afterward.', 'grid-index-rss-importer' ),
												(int) self::MAX_ACTIVE_FEEDS,
												(int) self::MAX_ACTIVE_FEEDS
											);
									?>');">
										<?php
										printf(
											/* translators: %d max active */
											esc_html__( 'Trim to %d', 'grid-index-rss-importer' ),
											(int) self::MAX_ACTIVE_FEEDS
										);
										?>
									</a>
								</div>
							<?php endif; ?>

							<div class="gi-card__body">

								<!-- v1.0.35 — View toggle (Cards / List). -->
								<div class="gip-catalog-viewbar">
									<a href="<?php echo esc_url( $cards_url ); ?>" class="gip-catalog-viewbar__btn<?php echo $view_mode === 'cards' ? ' is-active' : ''; ?>"><?php esc_html_e( 'Cards', 'grid-index-rss-importer' ); ?></a>
									<a href="<?php echo esc_url( $list_url ); ?>"  class="gip-catalog-viewbar__btn<?php echo $view_mode === 'list'  ? ' is-active' : ''; ?>"><?php esc_html_e( 'List', 'grid-index-rss-importer' ); ?></a>
								</div>

								<?php foreach ( $ordered as $cat_name => $cat_feeds ) : ?>
									<div class="gip-catalog-group">
										<h3 class="gip-catalog-group__title">
											<?php echo esc_html( $cat_name ); ?>
											<?php if ( $cat_name === 'Breaking News' ) : ?>
												<span class="gip-catalog-group__hint"><?php esc_html_e( '5-min cadence — wire services and high-volume desks', 'grid-index-rss-importer' ); ?></span>
											<?php endif; ?>
										</h3>

										<?php if ( $view_mode === 'list' ) : ?>
											<!-- LIST VIEW -->
											<div class="gip-catalog-list">
												<?php foreach ( $cat_feeds as $cf ) :
													$is_active   = isset( $active_urls[ $cf['url'] ] );
													$cap_reached = ! $is_active && $active_count >= self::MAX_ACTIVE_FEEDS;
													$toggle_url  = wp_nonce_url(
														add_query_arg( array(
															'action'   => self::PAGE_SLUG . '_catalog_toggle',
															'feed_url' => rawurlencode( $cf['url'] ),
															'view'     => 'list',
														), admin_url( 'admin-post.php' ) ),
														self::NONCE_ACTION,
														self::NONCE_NAME
													);
												?>
													<div class="gip-catalog-list-row<?php echo $is_active ? ' is-active' : ''; ?><?php echo $cap_reached ? ' is-disabled' : ''; ?>">
														<span class="gip-catalog-list-row__name"><?php echo esc_html( $cf['name'] ); ?></span>
														<span class="gip-catalog-list-row__host"><?php echo esc_html( wp_parse_url( $cf['url'], PHP_URL_HOST ) ); ?></span>
														<span class="gip-catalog-list-row__interval">⏱ <?php echo esc_html( $this->interval_label( $cf['recommended_interval'] ?? self::DEFAULT_INTERVAL ) ); ?></span>
														<span class="gip-catalog-list-row__action">
															<?php if ( $is_active ) : ?>
																<a href="<?php echo esc_url( $toggle_url ); ?>" class="gi-btn gi-btn--ghost gip-catalog-list-row__btn"><?php esc_html_e( '✓ Remove', 'grid-index-rss-importer' ); ?></a>
															<?php elseif ( $cap_reached ) : ?>
																<button type="button" class="gi-btn gi-btn--ghost gip-catalog-list-row__btn" disabled><?php esc_html_e( 'Cap reached', 'grid-index-rss-importer' ); ?></button>
															<?php else : ?>
																<a href="<?php echo esc_url( $toggle_url ); ?>" class="gi-btn gi-btn--primary gip-catalog-list-row__btn"><?php esc_html_e( '+ Add', 'grid-index-rss-importer' ); ?></a>
															<?php endif; ?>
														</span>
													</div>
												<?php endforeach; ?>
											</div>

										<?php else : ?>
											<!-- CARDS VIEW (original) -->
											<div class="gip-catalog-grid">
												<?php foreach ( $cat_feeds as $cf ) :
													$is_active   = isset( $active_urls[ $cf['url'] ] );
													$cap_reached = ! $is_active && $active_count >= self::MAX_ACTIVE_FEEDS;
													$toggle_url  = wp_nonce_url(
														add_query_arg( array(
															'action'   => self::PAGE_SLUG . '_catalog_toggle',
															'feed_url' => rawurlencode( $cf['url'] ),
														), admin_url( 'admin-post.php' ) ),
														self::NONCE_ACTION,
														self::NONCE_NAME
													);
												?>
													<div class="gip-catalog-card<?php echo $is_active ? ' is-active' : ''; ?><?php echo $cap_reached ? ' is-disabled' : ''; ?>">
														<div class="gip-catalog-card__head">
															<div class="gip-catalog-card__name"><?php echo esc_html( $cf['name'] ); ?></div>
															<?php if ( $is_active ) : ?>
																<span class="gip-catalog-card__badge"><?php esc_html_e( 'Active', 'grid-index-rss-importer' ); ?></span>
															<?php endif; ?>
														</div>
														<div class="gip-catalog-card__host"><?php echo esc_html( wp_parse_url( $cf['url'], PHP_URL_HOST ) ); ?></div>
														<?php if ( ! empty( $cf['recommended_interval'] ) ) : ?>
															<div class="gip-catalog-card__interval" title="<?php esc_attr_e( 'Recommended fetch interval based on this feed\'s typical publishing rate. You can override per-feed on the Feeds tab.', 'grid-index-rss-importer' ); ?>">
																⏱ <?php echo esc_html( $this->interval_label( $cf['recommended_interval'] ) ); ?>
															</div>
														<?php endif; ?>
														<?php if ( $cap_reached ) : ?>
															<button type="button" class="gi-btn gi-btn--ghost gip-catalog-card__btn" disabled title="<?php
																printf(
																	/* translators: %d: max active feeds */
																	esc_attr__( 'Cap reached (%d). Remove an active feed to add this one.', 'grid-index-rss-importer' ),
																	(int) self::MAX_ACTIVE_FEEDS
																);
															?>">
																<?php esc_html_e( 'Cap reached', 'grid-index-rss-importer' ); ?>
															</button>
														<?php else : ?>
															<a href="<?php echo esc_url( $toggle_url ); ?>" class="gi-btn <?php echo $is_active ? 'gi-btn--ghost' : 'gi-btn--primary'; ?> gip-catalog-card__btn">
																<?php echo $is_active
																	? esc_html__( '✓ Remove', 'grid-index-rss-importer' )
																	: esc_html__( '+ Add to feeds', 'grid-index-rss-importer' ); ?>
															</a>
														<?php endif; ?>
													</div>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div><!-- /.gip-tab-panel catalog -->

					<!-- ============== DIAGNOSTICS TAB ============== -->
					<div class="gip-tab-panel" data-panel="diagnostics" role="tabpanel" hidden>

						<!-- ============== LAST RUN LOG (moved from old Run tab in v1.0.28) ============== -->
						<?php if ( ! empty( $s['last_log'] ) ) : ?>
							<div class="gi-card">
								<div class="gi-card__head">
									<h2 class="gi-card__title"><?php esc_html_e( 'Last import log', 'grid-index-rss-importer' ); ?></h2>
									<p class="gi-card__sub">
										<?php
										if ( ! empty( $s['last_run'] ) ) {
											printf(
												/* translators: %s: human-readable time difference */
												esc_html__( 'Last run: %s ago.', 'grid-index-rss-importer' ),
												esc_html( human_time_diff( (int) $s['last_run'], time() ) )
											);
										}
										?>
									</p>
								</div>
								<div class="gi-card__body">
									<pre class="gip-rss-log"><?php echo esc_html( $s['last_log'] ); ?></pre>
								</div>
							</div>
						<?php endif; ?>

						<!-- ============== SILENT BREAKAGE DETECTOR (v1.0.41) ============== -->
						<?php
						$health_window_hours = 48;
						$health_cutoff_gmt   = gmdate( 'Y-m-d H:i:s', time() - $health_window_hours * HOUR_IN_SECONDS );
						$active_feeds        = is_array( $s['feeds'] ?? null ) ? $s['feeds'] : array();
						$health_rows         = array();
						if ( ! empty( $active_feeds ) ) {
							global $wpdb;
							foreach ( $active_feeds as $idx => $hf ) {
								if ( empty( $hf['url'] ) ) continue;
								$feed_name = isset( $hf['name'] ) && $hf['name'] !== '' ? $hf['name'] : $hf['url'];

								// Count posts imported from this feed in the last N hours.
								// Match on _gridindex_source_name (the canonical source meta
								// set on every import) — exactly equal, since users may have
								// multiple feeds with similar names.
								$count = (int) $wpdb->get_var( $wpdb->prepare(
									"SELECT COUNT(DISTINCT p.ID)
									 FROM {$wpdb->posts} p
									 INNER JOIN {$wpdb->postmeta} pm
									   ON pm.post_id = p.ID
									   AND pm.meta_key = '_gridindex_source_name'
									   AND pm.meta_value = %s
									 WHERE p.post_status IN ('publish','draft','pending')
									   AND p.post_date_gmt > %s",
									$feed_name,
									$health_cutoff_gmt
								) );

								$last_fetched = isset( $hf['last_fetched'] ) ? (int) $hf['last_fetched'] : 0;
								$fetched_recently = ( $last_fetched > 0 ) && ( ( time() - $last_fetched ) < 24 * HOUR_IN_SECONDS );

								// Verdict logic:
								//   OK       — fetched recently AND posts in window
								//   silent   — fetched recently AND zero posts in window  ← the bug we're surfacing
								//   stale    — never/not-recently fetched
								//   pending  — never fetched yet (last_fetched == 0)
								if ( $last_fetched === 0 ) {
									$verdict = 'pending';
								} elseif ( ! $fetched_recently ) {
									$verdict = 'stale';
								} elseif ( $count === 0 ) {
									$verdict = 'silent';
								} else {
									$verdict = 'ok';
								}

								$health_rows[] = array(
									'name'         => $feed_name,
									'url'          => $hf['url'],
									'last_fetched' => $last_fetched,
									'count'        => $count,
									'verdict'      => $verdict,
								);
							}
						}

						// Sort: silent first, then stale, then pending, then ok.
						$verdict_order = array( 'silent' => 0, 'stale' => 1, 'pending' => 2, 'ok' => 3 );
						usort( $health_rows, function( $a, $b ) use ( $verdict_order ) {
							return $verdict_order[ $a['verdict'] ] <=> $verdict_order[ $b['verdict'] ];
						} );

						$silent_n = 0; foreach ( $health_rows as $hr ) { if ( $hr['verdict'] === 'silent' ) $silent_n++; }
						?>
						<div class="gi-card">
							<div class="gi-card__head">
								<h2 class="gi-card__title"><?php esc_html_e( 'Feed health check', 'grid-index-rss-importer' ); ?></h2>
								<p class="gi-card__sub">
									<?php
									printf(
										/* translators: 1: window hours, 2: silent count */
										esc_html__( 'Feeds that fetch successfully but haven\'t imported any posts in the last %1$d hours are flagged "silent" — common when a publisher deprecates a feed (the URL still returns 200 but produces no items). %2$d silent feed(s) found.', 'grid-index-rss-importer' ),
										(int) $health_window_hours,
										(int) $silent_n
									);
									?>
								</p>
							</div>
							<div class="gi-card__body">
								<?php if ( empty( $health_rows ) ) : ?>
									<p class="gi-field__desc"><?php esc_html_e( 'No active feeds to check.', 'grid-index-rss-importer' ); ?></p>
								<?php else : ?>
									<div class="gip-health">
										<div class="gip-health__head">
											<span class="gip-health__cell gip-health__cell--name"><?php esc_html_e( 'Feed', 'grid-index-rss-importer' ); ?></span>
											<span class="gip-health__cell gip-health__cell--fetched"><?php esc_html_e( 'Last fetch', 'grid-index-rss-importer' ); ?></span>
											<span class="gip-health__cell gip-health__cell--count"><?php printf( esc_html__( 'Posts in %dh', 'grid-index-rss-importer' ), (int) $health_window_hours ); ?></span>
											<span class="gip-health__cell gip-health__cell--verdict"><?php esc_html_e( 'Status', 'grid-index-rss-importer' ); ?></span>
										</div>
										<?php foreach ( $health_rows as $hr ) :
											$verdict = $hr['verdict'];
											$verdict_label = array(
												'silent'  => __( '⚠ silent', 'grid-index-rss-importer' ),
												'stale'   => __( 'stale fetch', 'grid-index-rss-importer' ),
												'pending' => __( 'never fetched', 'grid-index-rss-importer' ),
												'ok'      => __( '✓ ok', 'grid-index-rss-importer' ),
											)[ $verdict ];
											$fetched_label = $hr['last_fetched']
												? sprintf( esc_html__( '%s ago', 'grid-index-rss-importer' ), esc_html( human_time_diff( $hr['last_fetched'], time() ) ) )
												: esc_html__( '—', 'grid-index-rss-importer' );
										?>
											<div class="gip-health__row gip-health__row--<?php echo esc_attr( $verdict ); ?>">
												<span class="gip-health__cell gip-health__cell--name">
													<strong><?php echo esc_html( $hr['name'] ); ?></strong>
													<span class="gip-health__url"><?php echo esc_html( $hr['url'] ); ?></span>
												</span>
												<span class="gip-health__cell gip-health__cell--fetched"><?php echo wp_kses_post( $fetched_label ); ?></span>
												<span class="gip-health__cell gip-health__cell--count"><?php echo (int) $hr['count']; ?></span>
												<span class="gip-health__cell gip-health__cell--verdict">
													<span class="gip-health__pill gip-health__pill--<?php echo esc_attr( $verdict ); ?>"><?php echo esc_html( $verdict_label ); ?></span>
												</span>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- ============== DIAGNOSTICS CARD ============== -->
						<?php
						$recent = get_posts( array(
							'post_type'      => 'post',
							'post_status'    => 'any',
							'meta_key'       => self::META_GUID,
							'posts_per_page' => 8,
							'orderby'        => 'date',
							'order'          => 'DESC',
							'fields'         => 'ids',
						) );
						?>
						<div class="gi-card">
						<div class="gi-card__head">
							<h2 class="gi-card__title"><?php esc_html_e( 'Recent imports — diagnostics', 'grid-index-rss-importer' ); ?></h2>
							<p class="gi-card__sub"><?php esc_html_e( 'The 8 most recent posts created by this importer, with the source meta the theme reads to render the "Read at Source" button.', 'grid-index-rss-importer' ); ?></p>
						</div>
						<div class="gi-card__body">
							<?php if ( empty( $recent ) ) : ?>
								<p class="gi-field__desc"><?php esc_html_e( 'No posts have been imported yet. Add a feed above and click Import Now.', 'grid-index-rss-importer' ); ?></p>
							<?php else : ?>
								<table class="widefat gip-diag-table">
									<thead>
										<tr>
											<th class="gip-diag-col-title"><?php esc_html_e( 'Post', 'grid-index-rss-importer' ); ?></th>
											<th class="gip-diag-col-url"><?php esc_html_e( 'Source URL', 'grid-index-rss-importer' ); ?></th>
											<th class="gip-diag-col-source"><?php esc_html_e( 'Source', 'grid-index-rss-importer' ); ?></th>
											<th class="gip-diag-col-status"><?php esc_html_e( 'Attribution', 'grid-index-rss-importer' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $recent as $rid ) :
											$src_url  = get_post_meta( $rid, '_gridindex_source_url', true );
											$src_name = get_post_meta( $rid, '_gridindex_source_name', true );
											$ok       = $src_url && $src_name;
										?>
											<tr>
												<td class="gip-diag-cell-title">
													<a href="<?php echo esc_url( get_edit_post_link( $rid ) ); ?>"><?php echo esc_html( get_the_title( $rid ) ); ?></a>
													<div class="gip-diag-date"><?php echo esc_html( get_the_date( 'M j, Y g:i a', $rid ) ); ?></div>
												</td>
												<td class="gip-diag-cell-url">
													<?php if ( $src_url ) : ?>
														<a href="<?php echo esc_url( $src_url ); ?>" target="_blank" rel="noopener" class="gip-diag-url" title="<?php echo esc_attr( $src_url ); ?>"><?php echo esc_html( $src_url ); ?></a>
													<?php else : ?>
														<span class="gip-diag-missing"><?php esc_html_e( '— missing —', 'grid-index-rss-importer' ); ?></span>
													<?php endif; ?>
												</td>
												<td class="gip-diag-cell-source">
													<?php echo $src_name ? esc_html( $src_name ) : '<span class="gip-diag-missing">— missing —</span>'; ?>
												</td>
												<td class="gip-diag-cell-status">
													<?php if ( $ok ) : ?>
														<span class="gip-diag-ok" title="<?php esc_attr_e( 'Source attribution meta is set. The theme will render the “Read at Source” button on this post.', 'grid-index-rss-importer' ); ?>">✓ <?php esc_html_e( 'OK', 'grid-index-rss-importer' ); ?></span>
													<?php else : ?>
														<span class="gip-diag-bad" title="<?php esc_attr_e( 'Source meta is missing. The theme cannot render the “Read at Source” button on this post.', 'grid-index-rss-importer' ); ?>">✗ <?php esc_html_e( 'Missing', 'grid-index-rss-importer' ); ?></span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p class="gi-field__desc" style="margin-top:12px;">
									<?php esc_html_e( 'If a row shows "missing" meta but you imported it via this page, another plugin is overwriting the meta after insert. Check Aggregator, WP All Import, or any RSS-related plugin and disable it for these posts.', 'grid-index-rss-importer' ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<!-- ============== DUPLICATE FINDER (v1.0.30) + MERGE BUTTON (v1.0.31) ============== -->
					<?php
					$dup_result = $this->find_duplicate_groups( 2000 );
					$dup_groups = $dup_result['groups'];
					$rss_cat_id = $dup_result['rss_cat_id'];

					$total_dup_posts   = 0;
					$total_dup_extras  = 0; // posts that would be removed if we kept 1 per group
					foreach ( $dup_groups as $g ) {
						$total_dup_posts  += count( $g );
						$total_dup_extras += count( $g ) - 1;
					}

					$merge_url = wp_nonce_url(
						add_query_arg( array( 'action' => self::PAGE_SLUG . '_merge_dupes' ), admin_url( 'admin-post.php' ) ),
						self::NONCE_ACTION,
						self::NONCE_NAME
					);
					?>
					<div class="gi-card" id="gip-dup-detector">
						<div class="gi-card__head">
							<h2 class="gi-card__title"><?php esc_html_e( 'Duplicate detector', 'grid-index-rss-importer' ); ?></h2>
							<p class="gi-card__sub">
								<?php
								if ( ! $rss_cat_id ) {
									esc_html_e( 'RSS category not found — nothing to scan.', 'grid-index-rss-importer' );
								} elseif ( empty( $dup_groups ) ) {
									esc_html_e( 'No duplicates found in the most recent 2,000 RSS posts.', 'grid-index-rss-importer' );
								} else {
									printf(
										/* translators: 1: groups, 2: total dup posts, 3: extras that could be removed */
										esc_html__( 'Found %1$d duplicate groups across %2$d posts. Merging keeps the oldest in each group and moves %3$d extras to Trash (recoverable for 30 days).', 'grid-index-rss-importer' ),
										count( $dup_groups ),
										(int) $total_dup_posts,
										(int) $total_dup_extras
									);
								}
								?>
							</p>
						</div>
						<?php if ( ! empty( $dup_groups ) ) : ?>
							<div class="gi-card__body">

								<div style="margin-bottom:14px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
									<a href="<?php echo esc_url( $merge_url ); ?>"
									   class="gi-btn gi-btn--primary"
									   onclick="return confirm('<?php
									   		printf(
									   			esc_attr__( 'Move %d duplicate posts to Trash? The oldest post in each duplicate group will be kept; the rest go to Trash where you can recover them from Posts → Trash within 30 days. Continue?', 'grid-index-rss-importer' ),
									   			(int) $total_dup_extras
									   		);
									   ?>');">
										<?php
										printf(
											/* translators: %d: extras count */
											esc_html__( '🗑 Merge duplicates — trash %d posts', 'grid-index-rss-importer' ),
											(int) $total_dup_extras
										);
										?>
									</a>
									<span style="opacity:.6; font-size:12px;"><?php esc_html_e( 'Reversible: posts go to Trash, recoverable for 30 days.', 'grid-index-rss-importer' ); ?></span>
								</div>

								<table class="widefat gip-diag-table">
									<thead>
										<tr>
											<th class="gip-diag-col-title"><?php esc_html_e( 'Title (normalized match)', 'grid-index-rss-importer' ); ?></th>
											<th style="width:60px; text-align:center;"><?php esc_html_e( 'Count', 'grid-index-rss-importer' ); ?></th>
											<th><?php esc_html_e( 'Posts (oldest first — oldest is kept on merge)', 'grid-index-rss-importer' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$shown = 0;
										foreach ( $dup_groups as $norm => $members ) :
											if ( $shown++ >= 25 ) break; // Cap visual list to first 25 groups.
											$first_title = $members[0]->post_title;
										?>
											<tr>
												<td class="gip-diag-cell-title">
													<strong><?php echo esc_html( $first_title ); ?></strong>
												</td>
												<td style="text-align:center; font-weight:700;">
													<?php echo (int) count( $members ); ?>
												</td>
												<td>
													<?php foreach ( $members as $mi => $m ) :
														$edit_url = get_edit_post_link( $m->ID );
														$src      = $m->source_name ? $m->source_name : __( '?', 'grid-index-rss-importer' );
														$guid     = $m->guid_hash   ? substr( $m->guid_hash, 0, 8 ) : '—';
														$date_h   = mysql2date( 'M j, g:i a', $m->post_date );
														$is_keep  = ( $mi === 0 );
													?>
														<div class="gip-diag-dup-row<?php echo $is_keep ? ' gip-diag-dup-row--keep' : ''; ?>">
															<?php if ( $is_keep ) : ?>
																<span class="gip-diag-dup-keep-badge" title="<?php esc_attr_e( 'This post will be kept on merge (oldest in group).', 'grid-index-rss-importer' ); ?>"><?php esc_html_e( 'KEEP', 'grid-index-rss-importer' ); ?></span>
															<?php else : ?>
																<span class="gip-diag-dup-trash-badge" title="<?php esc_attr_e( 'This post will be moved to Trash on merge.', 'grid-index-rss-importer' ); ?>"><?php esc_html_e( 'TRASH', 'grid-index-rss-importer' ); ?></span>
															<?php endif; ?>
															<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo (int) $m->ID; ?></a>
															<span class="gip-diag-dup-meta"><?php echo esc_html( $date_h ); ?> · <?php echo esc_html( $src ); ?> · <code title="<?php esc_attr_e( 'first 8 chars of GUID dedupe hash', 'grid-index-rss-importer' ); ?>"><?php echo esc_html( $guid ); ?></code></span>
														</div>
													<?php endforeach; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<?php if ( count( $dup_groups ) > 25 ) : ?>
									<p class="gi-field__desc" style="margin-top:10px;">
										<?php
										printf(
											/* translators: %d: groups truncated */
											esc_html__( 'Showing the 25 largest groups. %d more groups not shown — they\'ll still be merged when you click the button.', 'grid-index-rss-importer' ),
											count( $dup_groups ) - 25
										);
										?>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>

					</div><!-- /.gip-tab-panel diagnostics -->

					<!-- ============== SUPPORT TAB (v1.0.43) ============== -->
					<div class="gip-tab-panel" data-panel="support" role="tabpanel" hidden>

						<!-- v1.0.58: Project link banner at the top of the Support
						     tab. The previous placement (bottom of Credits card,
						     bottom of tab) was hard to find — user had to scroll
						     past the entire 15-entry FAQ to see it. This puts the
						     button as the FIRST thing visible when Support opens. -->
						<div class="gip-project-banner">
							<div class="gip-project-banner__copy">
								<span class="gip-project-banner__label"><?php esc_html_e( 'PROJECT HOME', 'grid-index-rss-importer' ); ?></span>
								<h3 class="gip-project-banner__title"><?php esc_html_e( 'The Grid Index', 'grid-index-rss-importer' ); ?></h3>
								<p class="gip-project-banner__desc">
									<?php esc_html_e( 'Documentation, the theme catalog, and project updates. This plugin is part of The Grid Index family by Fifth Avenue Photographic.', 'grid-index-rss-importer' ); ?>
								</p>
							</div>
							<div class="gip-project-banner__cta">
								<a class="gi-btn gi-btn--primary gip-project-banner__btn"
								   href="https://thegridindex.com/"
								   target="_blank"
								   rel="noopener noreferrer">
									<?php esc_html_e( 'Visit The Grid Index ↗', 'grid-index-rss-importer' ); ?>
								</a>
							</div>
						</div>

						<div class="gi-card">
							<div class="gi-card__head">
								<h2 class="gi-card__title"><?php esc_html_e( 'Support & knowledge base', 'grid-index-rss-importer' ); ?></h2>
								<p class="gi-card__sub">
									<?php
									printf(
										/* translators: %s plugin version */
										esc_html__( 'Common questions about the plugin. Content current as of v%s. If something here is out of date or contradicts what the plugin actually does, the plugin behavior is the source of truth — please flag the discrepancy.', 'grid-index-rss-importer' ),
										esc_html( GRID_INDEX_RSS_IMPORTER_VERSION )
									);
									?>
								</p>
							</div>
							<div class="gi-card__body">
								<div class="gip-faq-search">
									<input type="search" id="gip-faq-search" class="gi-input" placeholder="<?php esc_attr_e( 'Search the knowledge base…', 'grid-index-rss-importer' ); ?>" aria-label="<?php esc_attr_e( 'Search FAQ', 'grid-index-rss-importer' ); ?>" />
									<span class="gip-faq-search__hint" id="gip-faq-search-hint"></span>
								</div>

								<?php
								// FAQ data organized into sections. Each entry has a
								// unique slug (anchor + filter target), question, and
								// answer rendered as HTML (we trust the strings we
								// ship here; no user input).
								$faq_sections = array(
									array(
										'title' => __( 'Getting started', 'grid-index-rss-importer' ),
										'items' => array(
											array(
												'slug' => 'what-does-this-do',
												'q'    => __( 'What does this plugin actually do?', 'grid-index-rss-importer' ),
												'a'    => __( 'It pulls headlines from RSS feeds (NYT, BBC, TechCrunch, etc.) into your site as WordPress posts in the dedicated "RSS" category. Each post links back to the original source. The Catalog tab has 49 pre-verified feeds you can toggle on with one click; the Feeds tab lists what\'s currently active; Settings controls how often the cron checks and what the default post status is.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'add-custom-feed',
												'q'    => __( 'How do I add a feed that\'s not in the catalog?', 'grid-index-rss-importer' ),
												'a'    => __( 'On the Feeds tab, click "+ Add Feed", paste the RSS URL in the FEED URL column, give it a display name, pick an interval, and (optionally) pick a granular category. The form auto-saves about 800ms after you stop typing. Then click "Import Now" or wait for the next cron tick.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'where-do-posts-go',
												'q'    => __( 'What\'s the "RSS" category — can I rename it?', 'grid-index-rss-importer' ),
												'a'    => __( 'Every imported post is auto-tagged with the dedicated "RSS" category (slug: rss). Since v1.0.40, posts also get a granular category — News, World, Tech, Business, or Science — based on the feed\'s catalog entry. The "RSS" term itself is created on activation and not renamed by the plugin; if you rename or delete it in Posts → Categories, the plugin will recreate it on the next import.', 'grid-index-rss-importer' ),
											),
										),
									),
									array(
										'title' => __( 'Imports & scheduling', 'grid-index-rss-importer' ),
										'items' => array(
											array(
												'slug' => 'import-vs-force',
												'q'    => __( 'What\'s the difference between "Import Now" and "Force re-import 24h"?', 'grid-index-rss-importer' ),
												'a'    => __( '"Import Now" fetches every active feed and imports anything new — items already imported are skipped via the dedupe ledger. "Force re-import 24h" looks at items published in the last 24 hours, DELETES the existing copies of any matches, and re-imports them fresh. Useful when you\'ve changed image rules or post-status defaults and want existing recent items to pick up the new settings.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'per-feed-intervals',
												'q'    => __( 'How do per-feed intervals work?', 'grid-index-rss-importer' ),
												'a'    => __( 'Each feed has its own check interval (5 min / 15 min / 30 min / hourly) set on the Feeds tab. The global Settings frequency is the POLLING rate — how often the cron wakes up to check whether any feed is due. Match it to your fastest feed\'s interval. A feed set to 5 min while the cron polls hourly effectively runs hourly. Catalog feeds come with sensible defaults; you can override per-feed.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'drafts-not-publishing',
												'q'    => __( 'Why aren\'t my posts publishing — they\'re all drafts?', 'grid-index-rss-importer' ),
												'a'    => __( 'Settings → New post status defaults to "Publish immediately" since v1.0.14. If your posts are coming in as drafts, check the dropdown there. A historical bug (pre-1.0.14) caused publish requests to be silently demoted to draft because the feed\'s date was passed to WordPress without timezone normalization, making the post look "future-scheduled." If you see this on 1.0.14+, the green "Publish all RSS drafts" button on the Feeds tab flips any existing drafts to publish in one click.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'wp-cron-unreliable',
												'q'    => __( 'WP-Cron isn\'t reliable on my site — what do I do?', 'grid-index-rss-importer' ),
												'a'    => __( 'WP-Cron only fires when someone visits the site. On a quiet site, "Every 5 minutes" can actually run every 30+ minutes. For reliable short intervals, set up a real Linux cron job at your host that pings wp-cron.php every minute. On Hostinger: Cron Jobs → Add Cron Job → Command: wget -q -O - https://YOURSITE/wp-cron.php?doing_wp_cron > /dev/null 2>&1 → Schedule: every minute. Then in WordPress wp-config.php, add: define( \'DISABLE_WP_CRON\', true ); so internal WP-Cron stops fighting the real one.', 'grid-index-rss-importer' ),
											),
										),
									),
									array(
										'title' => __( 'Feed health & troubleshooting', 'grid-index-rss-importer' ),
										'items' => array(
											array(
												'slug' => 'red-dot',
												'q'    => __( 'Why are some feeds showing red dots?', 'grid-index-rss-importer' ),
												'a'    => __( 'A red dot in the leftmost column of the Feeds table means the most recent fetch returned an error (HTTP failure, timeout, or invalid XML). Hover the dot for the error message, or check Diagnostics → Last import log. Failed feeds are backed off for 10 minutes before being retried, so you won\'t hammer a broken feed every cron tick.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'green-zero-posts',
												'q'    => __( 'Why do some feeds show 0 posts even though they\'re green?', 'grid-index-rss-importer' ),
												'a'    => __( 'Green just means the HTTP fetch succeeded. The feed may have returned an empty or stale response — most often because the publisher deprecated their RSS without 404\'ing the URL (this is what happened with CNN before we dropped it in v1.0.41). The "Feed health check" card on the Diagnostics tab flags feeds that fetch successfully but haven\'t imported any posts in 48 hours. If a feed shows ⚠ silent for multiple days, the URL is probably dead.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'diagnostics-first-look',
												'q'    => __( 'The Diagnostics tab confuses me. What should I look at first?', 'grid-index-rss-importer' ),
												'a'    => __( 'Three things in order: (1) "Feed health check" — anything in red is silently broken. (2) "Duplicate detector" — extra copies of the same story across feeds, with a Merge button to trash duplicates while keeping the oldest. (3) "Last import log" — raw line-by-line output of the most recent run, useful for tracing specific failures.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'images-when',
												'q'    => __( 'When does a post get a featured image?', 'grid-index-rss-importer' ),
												'a'    => __( 'The plugin tries (in this order, depending on Settings → Featured image mode): feed image, then content image, then nothing. Specifically: enclosure tags, media:thumbnail / media:content, the first <img> in the description, and og:image lookups. If Minimum image width is set (default 1000px), images below that threshold are skipped — so the post may import without a featured image even if one was attached. Set width to 0 to disable the check.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'image-width-filter',
												'q'    => __( 'Can I exclude items below a certain image width?', 'grid-index-rss-importer' ),
												'a'    => __( 'Yes — Settings → Minimum image width. Default 1000px. The plugin reads each candidate image\'s dimensions before sideloading and skips anything narrower. Items without any usable image still import (no exclusion), they just don\'t get a featured image. Set to 0 to disable the check entirely.', 'grid-index-rss-importer' ),
											),
										),
									),
									array(
										'title' => __( 'Duplicates & deleted posts', 'grid-index-rss-importer' ),
										'items' => array(
											array(
												'slug' => 'deleted-comes-back',
												'q'    => __( 'Why did a deleted post come back?', 'grid-index-rss-importer' ),
												'a'    => __( 'It shouldn\'t, on v1.0.38 or later. Before 1.0.38, the dedupe lookup excluded trashed posts AND lost track of permanently-deleted posts entirely (because postmeta is wiped when a post is permanently deleted). v1.0.38 added a persistent seen-GUIDs ledger table that records every imported hash and survives post deletion. If you\'re on 1.0.38+ and seeing posts re-import after deletion, that\'s a bug — please report it with the post title and source feed.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'duplicate-merge',
												'q'    => __( 'How does the duplicate detector decide what to merge?', 'grid-index-rss-importer' ),
												'a'    => __( 'It scans the 2,000 most recent RSS posts, normalizes each title (lowercase, strip punctuation, collapse whitespace), and groups any with identical normalized titles. Groups with 2+ posts are reported. Merging keeps the OLDEST post in each group (lowest ID) and moves the rest to Trash (recoverable for 30 days, not permanently deleted). This is conservative — if titles differ even slightly between sources, they won\'t be flagged as dupes.', 'grid-index-rss-importer' ),
											),
										),
									),
									array(
										'title' => __( 'Maintenance', 'grid-index-rss-importer' ),
										'items' => array(
											array(
												'slug' => 'uninstall-data',
												'q'    => __( 'What happens if I uninstall the plugin?', 'grid-index-rss-importer' ),
												'a'    => __( 'Settings → Keep my data if I uninstall this plugin controls this (default ON since v1.0.37). When ON: feed list, settings, dedupe ledger, and per-post GUID meta are preserved across delete-and-reinstall. Imported posts themselves are NEVER deleted by uninstall regardless of the setting — that\'s your content. The cron schedule and SimplePie feed cache are always cleared on uninstall.', 'grid-index-rss-importer' ),
											),
											array(
												'slug' => 'restore-defaults',
												'q'    => __( 'How do I reset to the default feed list?', 'grid-index-rss-importer' ),
												'a'    => __( 'Feeds tab → "Restore default feeds" button at the bottom. This overwrites your current feed list with a curated 6-feed starter set (BBC World, Al Jazeera, Google News, NBC News, Science Daily, CBS News). Your existing feeds are removed but already-imported posts stay put. The dedupe ledger also stays — so re-importing won\'t create duplicates.', 'grid-index-rss-importer' ),
											),
										),
									),
								);

								foreach ( $faq_sections as $section ) :
								?>
									<div class="gip-faq-section">
										<h3 class="gip-faq-section__title"><?php echo esc_html( $section['title'] ); ?></h3>
										<?php foreach ( $section['items'] as $item ) : ?>
											<details class="gip-faq-item" id="faq-<?php echo esc_attr( $item['slug'] ); ?>" data-faq-q="<?php echo esc_attr( $item['q'] ); ?>" data-faq-a="<?php echo esc_attr( wp_strip_all_tags( $item['a'] ) ); ?>">
												<summary class="gip-faq-item__q"><?php echo esc_html( $item['q'] ); ?></summary>
												<div class="gip-faq-item__a"><?php echo wp_kses_post( wpautop( $item['a'] ) ); ?></div>
											</details>
										<?php endforeach; ?>
									</div>
								<?php endforeach; ?>

								<p class="gip-faq-foot">
									<?php esc_html_e( 'Still stuck? The Diagnostics tab\'s import log + feed health check together cover most issues. If a specific feed is misbehaving, copy its URL and last error message before asking for help.', 'grid-index-rss-importer' ); ?>
								</p>
							</div>
						</div>

						<!-- ============== CREDITS CARD (v1.0.55, restructured v1.0.56) ============== -->
						<div class="gi-card gip-support-card">
							<div class="gi-card__head">
								<h2 class="gi-card__title"><?php esc_html_e( 'Credits', 'grid-index-rss-importer' ); ?></h2>
								<p class="gi-card__sub">
									<?php esc_html_e( 'Who made this and what else is in the family.', 'grid-index-rss-importer' ); ?>
								</p>
							</div>
							<div class="gi-card__body">
								<div class="gip-credits">
									<div class="gip-credits__main">
										<p class="gip-credits__line gip-credits__line--lead">
											<?php
											printf(
												/* translators: %s: parent company name */
												esc_html__( 'Developed by %s.', 'grid-index-rss-importer' ),
												'<strong>Fifth Avenue Photographic</strong>'
											);
											?>
										</p>
										<p class="gip-credits__line">
											<?php
											printf(
												/* translators: %s: site link */
												esc_html__( 'Fifth Avenue Photographic is the parent company behind The Grid Index theme and this companion plugin. Project home: %s.', 'grid-index-rss-importer' ),
												'<a class="gip-credits__link" href="https://thegridindex.com/" target="_blank" rel="noopener noreferrer">thegridindex.com</a>'
											);
											?>
										</p>
									</div>

									<div class="gip-credits__products">
										<div class="gip-credits__product">
											<span class="gip-credits__product-label"><?php esc_html_e( 'Theme', 'grid-index-rss-importer' ); ?></span>
											<span class="gip-credits__product-name"><?php esc_html_e( 'The Grid Index', 'grid-index-rss-importer' ); ?></span>
											<span class="gip-credits__product-desc"><?php esc_html_e( 'Editorial WordPress theme for magazine-style sites.', 'grid-index-rss-importer' ); ?></span>
										</div>
										<div class="gip-credits__product">
											<span class="gip-credits__product-label"><?php esc_html_e( 'Plugin', 'grid-index-rss-importer' ); ?></span>
											<span class="gip-credits__product-name"><?php esc_html_e( 'Grid Index RSS Importer', 'grid-index-rss-importer' ); ?></span>
											<span class="gip-credits__product-desc"><?php esc_html_e( 'This plugin — pulls headlines from external RSS feeds.', 'grid-index-rss-importer' ); ?></span>
										</div>
									</div>

									<!-- v1.0.57: button restored. The button indicates the
									     plugin is part of The Grid Index family and gives
									     the user a clear visual entry point to the project. -->
									<div class="gip-credits__cta">
										<a class="gi-btn gi-btn--ghost gip-credits__btn"
										   href="https://thegridindex.com/"
										   target="_blank"
										   rel="noopener noreferrer">
											<?php esc_html_e( 'Visit The Grid Index ↗', 'grid-index-rss-importer' ); ?>
										</a>
										<span class="gip-credits__cta-hint">
											<?php esc_html_e( 'This plugin works with The Grid Index theme.', 'grid-index-rss-importer' ); ?>
										</span>
									</div>

									<p class="gip-credits__version">
										<?php
										printf(
											/* translators: %s: plugin version */
											esc_html__( 'You are running version %s.', 'grid-index-rss-importer' ),
											esc_html( GRID_INDEX_RSS_IMPORTER_VERSION )
										);
										?>
									</p>
								</div>
							</div>
						</div>
					</div><!-- /.gip-tab-panel support -->

				</div><!-- /.gi-main -->
			</div><!-- /.gi-shell -->
		</div><!-- /.wrap -->

		<style>
		/* RSS-importer-specific extras layered on top of theme-options.css */

		/* ---------- HERO REFINEMENT (v1.0.52, distinct treatment v1.0.57) ---------- */
		/* The wordmark now has a small teal accent rule sitting between the
		   eyebrow and the title, so the header reads as a structured wordmark
		   rather than two stacked text lines. Title size bumped, eyebrow more
		   declarative. Same colors and fonts — just a clearer silhouette. */
		.gridindex-theme-options .gi-hero__inner {
			gap:18px;
		}
		.gridindex-theme-options .gi-hero__brand {
			display:flex;
			flex-direction:column;
			gap:0;
		}
		.gridindex-theme-options .gi-hero__eyebrow {
			display:inline-flex;
			align-items:center;
			gap:10px;
			font-size:11px;
			font-weight:700;
			letter-spacing:.22em;
			text-transform:uppercase;
			color:var(--gi-accent);
			opacity:1;
			line-height:1;
			margin:0 0 14px;
		}
		/* The teal accent rule. Drawn with a pseudo-element so we don't add
		   extra markup. Short (32px), thin (2px), aligned to the eyebrow's
		   baseline area. Reads as an intentional wordmark element. */
		.gridindex-theme-options .gi-hero__eyebrow::after {
			content:"";
			display:inline-block;
			width:32px;
			height:2px;
			background:var(--gi-accent);
			border-radius:2px;
			opacity:.85;
		}
		.gridindex-theme-options .gi-hero__title {
			font-size:42px;
			line-height:1.05;
			letter-spacing:-.012em;
			margin:0;
			font-weight:600;
		}
		.gridindex-theme-options .gi-hero__sub {
			font-size:13.5px;
			line-height:1.6;
			opacity:.82;
			max-width:64ch;
			margin:14px 0 0;
		}
		/* Pills row */
		.gridindex-theme-options .gi-hero__meta {
			gap:8px;
			margin-top:18px;
		}
		.gridindex-theme-options .gi-hero__meta .gi-badge {
			padding:4px 10px;
			font-size:11px;
			letter-spacing:.04em;
			font-weight:600;
		}
		/* v1.0.53 — Muted badge variant for "theme not active" state. Lower
		   contrast than --success / --warning so it reads as an informational
		   note, not a problem to fix. v1.0.60 — Restored after the v1.0.59
		   CTA-button experiment was reverted. */
		.gridindex-theme-options .gi-badge.gi-badge--muted {
			color:var(--gi-faint, #94a3b8);
			border-color:rgba(148,163,184,.30);
			background:rgba(148,163,184,.06);
		}

		/* ---------- READABILITY OVERRIDES (v1.0.19) ---------- */
		/* The base theme-options stylesheet uses heavy opacity on labels and
		   muted text. On the dark importer card backgrounds those drop into
		   illegible territory. Bump them globally inside the importer screen. */
		.gridindex-theme-options .gi-card__title { opacity:1; font-size:18px; letter-spacing:.01em; }
		.gridindex-theme-options .gi-card__sub   { opacity:.85; font-size:13px; line-height:1.5; }
		.gridindex-theme-options .gi-field__label{ opacity:1; font-weight:600; font-size:13px; letter-spacing:.01em; }
		.gridindex-theme-options .gi-field__desc { opacity:.75; font-size:12px; line-height:1.5; }
		.gridindex-theme-options .gi-card__body  { padding-top:18px; padding-bottom:18px; }
		.gridindex-theme-options .gi-card        { margin-bottom:0; }
		.gridindex-theme-options .gi-grid--3     { gap:20px 24px; }

		/* ---------- STATUS PILL / BANNER (v1.0.20) ---------- */
		/* Quiet inline pill when everything's fine. Loud full-width banner
		   only when attention is needed (status != publish). */
		.gridindex-theme-options .gip-status-pill {
			display:inline-flex;
			align-items:center;
			gap:6px;
			padding:5px 12px;
			border-radius:999px;
			font:600 11px/1 inherit;
			letter-spacing:.04em;
			text-transform:uppercase;
			margin:14px 0 0 0;
			cursor:help;
		}
		.gridindex-theme-options .gip-status-pill--ok {
			background:rgba(16,185,129,.12);
			color:#10b981;
			border:1px solid rgba(16,185,129,.3);
		}
		.gridindex-theme-options .gip-status-banner--warn {
			margin:16px 0 0 0;
			padding:14px 18px;
			border-radius:8px;
			background:#fff3cd;
			border:1px solid #ffc107;
			color:#664d03;
			display:flex;
			align-items:center;
			justify-content:space-between;
			gap:16px;
			flex-wrap:wrap;
		}
		.gridindex-theme-options .gip-status-banner__msg { flex:1; min-width:240px; }
		.gridindex-theme-options .gip-status-banner__btn {
			background:#0c7a3d !important;
			border-color:#0c7a3d !important;
			color:#fff !important;
		}

		/* ---------- EMPTY STATE NUDGE (v1.0.29) ---------- */
		.gridindex-theme-options .gip-empty-nudge {
			padding:14px 16px;
			margin-bottom:14px;
			background:rgba(16,185,129,.08);
			border:1px solid rgba(16,185,129,.32);
			border-radius:var(--gi-radius-sm);
			font-size:13px;
			line-height:1.5;
		}
		.gridindex-theme-options .gip-empty-nudge strong {
			display:block;
			margin-bottom:4px;
		}

		/* ---------- FEEDS TOOLBAR ---------- */
		/* (rule moved to the v1.0.22 save-indicator block below — kept the
		   header here to preserve change-history readability.) */

		/* ---------- TABS ---------- */
		.gridindex-theme-options .gip-tabs {
			display:flex; gap:4px; flex-wrap:wrap;
			margin:0 0 22px 0;
			border-bottom:1px solid var(--gi-border);
		}
		.gridindex-theme-options .gip-tab {
			background:transparent;
			border:0;
			border-bottom:2px solid transparent;
			color:var(--gi-fg, inherit);
			opacity:.75;
			padding:11px 20px;
			font:600 14px/1 inherit;
			cursor:pointer;
			border-radius:6px 6px 0 0;
			transition:opacity .15s ease, border-color .15s ease, background .15s ease;
		}
		.gridindex-theme-options .gip-tab:hover { opacity:1; background:var(--gi-card-soft); }
		.gridindex-theme-options .gip-tab.is-active {
			opacity:1;
			border-bottom-color:var(--gi-accent, #10b981);
			background:var(--gi-card-soft);
		}
		.gridindex-theme-options .gip-tab-panel[hidden] { display:none !important; }

		/* ---------- COMPACT FEED TABLE ---------- */
		.gridindex-theme-options .gip-rss-feeds-list {
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			overflow:hidden;
			background:var(--gi-card-soft);
		}
		.gridindex-theme-options .gip-rss-feeds-header,
		.gridindex-theme-options .gip-rss-feed-row {
			display:grid;
			grid-template-columns: 32px minmax(0, 1.55fr) minmax(0, 0.9fr) 130px 110px auto;
			align-items:center;
			gap:10px;
			padding:6px 10px;
		}
		.gridindex-theme-options .gip-rss-feeds-header {
			background:rgba(255,255,255,.04);
			border-bottom:1px solid var(--gi-border);
			padding-top:10px; padding-bottom:10px;
			font:600 11px/1 inherit;
			text-transform:uppercase;
			letter-spacing:.08em;
			opacity:.85;
		}
		.gridindex-theme-options .gip-rss-feed-row {
			border-bottom:1px solid var(--gi-border);
			background:transparent;
			border-radius:0;
			margin:0;
		}
		.gridindex-theme-options .gip-rss-feed-row:last-child { border-bottom:0; }
		.gridindex-theme-options .gip-rss-feed-row:hover { background:rgba(255,255,255,.03); }
		.gridindex-theme-options .gip-rss-feed-row .gi-input {
			padding:5px 9px;
			font-size:13px;
			height:auto;
			line-height:1.4;
		}
		.gridindex-theme-options .gip-rss-feed-row .gi-select {
			padding:4px 8px;
			font-size:12px;
			height:auto;
			line-height:1.4;
		}
		/* v1.0.40 — Inline category picker beside Source Name. */
		.gridindex-theme-options .gip-rss-feed-row__cell--name {
			display:flex;
			gap:6px;
		}
		.gridindex-theme-options .gip-rss-feed-row__cell--name .gi-input {
			flex:1 1 auto;
			min-width:0;
		}
		.gridindex-theme-options .gip-rss-feed-row__cat {
			flex:0 0 95px;
			max-width:95px;
		}
		.gridindex-theme-options .gip-rss-feed-row__fetched {
			font-size:12px;
			opacity:.85;
			white-space:nowrap;
			overflow:hidden;
			text-overflow:ellipsis;
		}
		.gridindex-theme-options .gip-rss-feed-row__fetched--never {
			opacity:.45;
			font-style:italic;
		}
		.gridindex-theme-options .gip-rss-feed-row__cell--actions {
			display:flex; gap:4px; justify-content:flex-end;
		}
		.gridindex-theme-options .gip-rss-feed-row__btn {
			padding:4px 9px;
			font-size:12px;
			min-width:0;
			line-height:1;
		}
		.gridindex-theme-options .gip-rss-feed-row__detail {
			grid-column:1 / -1;
			padding:6px 10px 8px 42px;
			font:11px/1.4 inherit;
			color:var(--gi-fg, inherit);
			opacity:.65;
		}
		.gridindex-theme-options .gip-rss-feed-row.is-detail-open .gip-rss-feed-row__detail { display:block; }

		/* ---------- STATUS DOT ---------- */
		.gridindex-theme-options .gip-dot {
			display:inline-block;
			width:10px; height:10px;
			border-radius:50%;
			cursor:help;
			background:#64748b;        /* never */
			outline-offset:2px;
		}
		.gridindex-theme-options .gip-dot:focus-visible { outline:2px solid var(--gi-accent, #10b981); }
		.gridindex-theme-options .gip-dot--ok    { background:#10b981; }
		.gridindex-theme-options .gip-dot--dup   { background:#3b82f6; }
		.gridindex-theme-options .gip-dot--empty { background:#f59e0b; }
		.gridindex-theme-options .gip-dot--error { background:#ef4444; }
		.gridindex-theme-options .gip-dot--never { background:#64748b; opacity:.5; }

		/* ---------- LOG BOX (unchanged) ---------- */
		.gridindex-theme-options .gip-rss-feed-row {
			/* Reset for backward compat with cloned-row JS */
		}
		.gridindex-theme-options .gip-rss-log {
			background:#0b0f14;
			color:#cbd5e1;
			padding:14px 16px;
			border-radius:var(--gi-radius-sm);
			border:1px solid var(--gi-border);
			max-height:320px;
			overflow:auto;
			font:12px/1.55 ui-monospace,Menlo,Consolas,monospace;
			margin:0;
			white-space:pre-wrap;
			word-break:break-word;
		}
		.gridindex-theme-options.gi-mode-light .gip-rss-log {
			background:#0f172a;
			color:#e2e8f0;
		}

		/* ---------- FIELD READABILITY (v1.0.39) ---------- */
		/* WordPress admin's default input CSS forces dark text color
		   (#2c3338) on focused inputs. Against our dark-mode panel that
		   renders as invisible navy-on-navy. Override to keep the focused
		   field text legible regardless of theme mode.
		   Selector is specific enough (.gridindex-theme-options + input)
		   to beat WP's wp-admin/css/forms.css rules. */
		.gridindex-theme-options input[type="text"],
		.gridindex-theme-options input[type="url"],
		.gridindex-theme-options input[type="number"],
		.gridindex-theme-options input[type="email"],
		.gridindex-theme-options input[type="search"],
		.gridindex-theme-options input[type="password"],
		.gridindex-theme-options select,
		.gridindex-theme-options textarea {
			color: var(--gi-fg, #e6e9ee) !important;
			background-color: rgba(255,255,255,.04) !important;
			border-color: var(--gi-border, rgba(255,255,255,.12)) !important;
		}
		.gridindex-theme-options input[type="text"]:focus,
		.gridindex-theme-options input[type="url"]:focus,
		.gridindex-theme-options input[type="number"]:focus,
		.gridindex-theme-options input[type="email"]:focus,
		.gridindex-theme-options input[type="search"]:focus,
		.gridindex-theme-options input[type="password"]:focus,
		.gridindex-theme-options select:focus,
		.gridindex-theme-options textarea:focus {
			color: var(--gi-fg, #e6e9ee) !important;
			background-color: rgba(255,255,255,.07) !important;
			border-color: rgba(16,185,129,.6) !important;
			box-shadow: 0 0 0 2px rgba(16,185,129,.18) !important;
			outline: none;
		}
		/* Light-mode panels override the dark surface back to white. */
		.gridindex-theme-options.gi-mode-light input[type="text"],
		.gridindex-theme-options.gi-mode-light input[type="url"],
		.gridindex-theme-options.gi-mode-light input[type="number"],
		.gridindex-theme-options.gi-mode-light input[type="email"],
		.gridindex-theme-options.gi-mode-light input[type="search"],
		.gridindex-theme-options.gi-mode-light input[type="password"],
		.gridindex-theme-options.gi-mode-light select,
		.gridindex-theme-options.gi-mode-light textarea {
			color: #1a1f2b !important;
			background-color: #fff !important;
			border-color: rgba(0,0,0,.15) !important;
		}
		/* Native select dropdown option list respects the OS chrome, so we
		   only override <option> color to make sure the open list is legible
		   on browsers that inherit element colors into the option list. */
		.gridindex-theme-options select option { color: #1a1f2b; background: #fff; }

		/* ---------- SAVE INDICATOR (v1.0.22) ---------- */
		.gridindex-theme-options .gip-feeds-toolbar {
			display:flex;
			justify-content:flex-end;
			align-items:center;
			gap:12px;
			margin-bottom:12px;
		}
		.gridindex-theme-options .gip-save-indicator {
			font:600 12px/1 inherit;
			letter-spacing:.02em;
			opacity:0;
			transition:opacity .2s ease;
			min-height:14px;
		}
		.gridindex-theme-options .gip-save-indicator.is-saving { opacity:.75; color:inherit; }
		.gridindex-theme-options .gip-save-indicator.is-saved  { opacity:1;   color:#10b981; }
		.gridindex-theme-options .gip-save-indicator.is-error  { opacity:1;   color:#ef4444; }

		/* ---------- TOAST (v1.0.22) ---------- */
		/* Repurpose the existing .gi-notice as a top-right slide-in toast that
		   auto-dismisses, so fetch/save messages don't sit there indefinitely
		   eating vertical space. */
		.gridindex-theme-options .gi-notice.gip-toast {
			position:fixed;
			top:46px;
			right:20px;
			z-index:99999;
			max-width:420px;
			min-width:280px;
			padding:12px 18px;
			border-radius:8px;
			box-shadow:0 10px 30px rgba(0,0,0,.35);
			animation:gipToastIn .25s ease-out;
		}
		.gridindex-theme-options .gi-notice.gip-toast.is-leaving { animation:gipToastOut .35s ease-in forwards; }
		/* v1.0.36 — Multi-element toast (msg + Fetch now / Dismiss buttons) */
		.gridindex-theme-options .gi-notice.gip-toast .gip-toast__msg {
			margin-bottom:10px;
			font-size:13px;
			line-height:1.5;
		}
		.gridindex-theme-options .gi-notice.gip-toast .gip-toast__actions {
			display:flex;
			gap:8px;
		}
		.gridindex-theme-options .gi-notice.gip-toast .gip-toast__btn {
			padding:6px 12px;
			font-size:12px;
		}
		@keyframes gipToastIn  { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
		@keyframes gipToastOut { from { opacity:1; transform:translateY(0);    } to { opacity:0; transform:translateY(-12px); } }

		/* ---------- LONG-ACTION SPINNER (v1.0.23) ---------- */
		.gridindex-theme-options .gip-spin {
			display:inline-block;
			width:14px; height:14px;
			border:2px solid rgba(255,255,255,.25);
			border-top-color:#fff;
			border-radius:50%;
			vertical-align:-2px;
			margin-right:6px;
			animation:gipSpin .8s linear infinite;
		}
		.gridindex-theme-options .gip-spin--small {
			width:11px; height:11px; border-width:2px;
			margin-right:0;
		}
		@keyframes gipSpin { to { transform:rotate(360deg); } }
		.gridindex-theme-options button.is-loading,
		.gridindex-theme-options a.gip-fetch-link.is-loading {
			opacity:.85;
			cursor:wait;
		}
		.gridindex-theme-options button.is-loading[disabled] {
			/* Override the default disabled "looks dead" styling — the
			   button is "working" not "broken". */
			opacity:.9;
			background:var(--gi-accent, #10b981);
			color:#fff;
		}

		/* ---------- CATALOG TAB (v1.0.24) ---------- */
		/* v1.0.35 — View toggle (Cards / List). */
		.gridindex-theme-options .gip-catalog-viewbar {
			display:flex; gap:0;
			margin-bottom:18px;
			padding:3px;
			background:rgba(255,255,255,.05);
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			width:fit-content;
		}
		.gridindex-theme-options .gip-catalog-viewbar__btn {
			padding:6px 14px;
			font-size:12px;
			font-weight:600;
			text-decoration:none;
			color:inherit;
			border-radius:4px;
			opacity:.7;
		}
		.gridindex-theme-options .gip-catalog-viewbar__btn:hover { opacity:.95; }
		.gridindex-theme-options .gip-catalog-viewbar__btn.is-active {
			background:rgba(16,185,129,.20);
			color:#10b981;
			opacity:1;
		}
		/* v1.0.35 — Breaking-news section hint under group title. */
		.gridindex-theme-options .gip-catalog-group__hint {
			display:inline-block;
			margin-left:10px;
			font-weight:400;
			font-size:11px;
			opacity:.7;
			text-transform:none;
			letter-spacing:0;
		}
		/* v1.0.35 — List view rows. */
		.gridindex-theme-options .gip-catalog-list {
			display:flex;
			flex-direction:column;
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			overflow:hidden;
		}
		.gridindex-theme-options .gip-catalog-list-row {
			display:grid;
			grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.2fr) 110px 130px;
			gap:14px;
			align-items:center;
			padding:10px 14px;
			border-bottom:1px solid var(--gi-border);
			background:transparent;
			transition:background .12s ease;
		}
		.gridindex-theme-options .gip-catalog-list-row:last-child { border-bottom:0; }
		.gridindex-theme-options .gip-catalog-list-row:hover { background:rgba(255,255,255,.03); }
		.gridindex-theme-options .gip-catalog-list-row.is-active {
			background:rgba(16,185,129,.07);
		}
		.gridindex-theme-options .gip-catalog-list-row.is-disabled { opacity:.5; }
		.gridindex-theme-options .gip-catalog-list-row__name { font-weight:600; font-size:13px; }
		.gridindex-theme-options .gip-catalog-list-row__host {
			font:11px/1.3 ui-monospace,Menlo,Consolas,monospace;
			opacity:.7;
			overflow:hidden;
			text-overflow:ellipsis;
			white-space:nowrap;
		}
		.gridindex-theme-options .gip-catalog-list-row__interval { font-size:11px; opacity:.8; }
		.gridindex-theme-options .gip-catalog-list-row__action { text-align:right; }
		.gridindex-theme-options .gip-catalog-list-row__btn {
			padding:5px 12px;
			font-size:12px;
		}
		.gridindex-theme-options .gip-catalog-warn {
			margin:14px 18px 0 18px;
			padding:14px 16px;
			background:rgba(245,158,11,.12);
			border:1px solid rgba(245,158,11,.45);
			border-radius:var(--gi-radius-sm);
			display:flex;
			align-items:center;
			justify-content:space-between;
			gap:14px;
			flex-wrap:wrap;
		}
		.gridindex-theme-options .gip-catalog-warn__msg { flex:1; min-width:220px; font-size:13px; line-height:1.5; }
		.gridindex-theme-options .gip-catalog-warn__btn { white-space:nowrap; }
		.gridindex-theme-options .gip-catalog-group { margin-bottom:28px; }
		.gridindex-theme-options .gip-catalog-group:last-child { margin-bottom:0; }
		.gridindex-theme-options .gip-catalog-group__title {
			font:600 13px/1 inherit;
			text-transform:uppercase;
			letter-spacing:.08em;
			opacity:.7;
			margin:0 0 12px 0;
			padding-bottom:8px;
			border-bottom:1px solid var(--gi-border);
		}
		.gridindex-theme-options .gip-catalog-grid {
			display:grid;
			grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));
			gap:12px;
		}
		.gridindex-theme-options .gip-catalog-card {
			background:var(--gi-card-soft);
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			padding:14px;
			display:flex;
			flex-direction:column;
			gap:8px;
			transition:border-color .15s ease, background .15s ease;
		}
		.gridindex-theme-options .gip-catalog-card.is-active {
			border-color:rgba(16,185,129,.5);
			background:rgba(16,185,129,.06);
		}
		.gridindex-theme-options .gip-catalog-card.is-disabled {
			opacity:.5;
		}
		.gridindex-theme-options .gip-catalog-card__head {
			display:flex;
			justify-content:space-between;
			align-items:flex-start;
			gap:8px;
		}
		.gridindex-theme-options .gip-catalog-card__name {
			font:600 14px/1.3 inherit;
		}
		.gridindex-theme-options .gip-catalog-card__badge {
			font:600 10px/1 inherit;
			text-transform:uppercase;
			letter-spacing:.06em;
			padding:3px 7px;
			border-radius:999px;
			background:rgba(16,185,129,.18);
			color:#10b981;
			white-space:nowrap;
		}
		.gridindex-theme-options .gip-catalog-card__host {
			font:11px/1.4 ui-monospace,Menlo,Consolas,monospace;
			opacity:.55;
			word-break:break-all;
		}
		/* v1.0.34 — Recommended interval line on catalog cards. */
		.gridindex-theme-options .gip-catalog-card__interval {
			font-size:11px;
			opacity:.85;
			letter-spacing:.02em;
			color:inherit;
		}
		.gridindex-theme-options .gip-catalog-card__btn {
			margin-top:auto;
			padding:6px 10px;
			font-size:12px;
			text-align:center;
			text-decoration:none;
		}

		/* ---------- UNINSTALL PREF (v1.0.37) ---------- */
		.gridindex-theme-options .gip-uninstall-pref {
			margin-top:18px;
			padding:14px 16px;
			background:rgba(255,255,255,.04);
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
		}
		.gridindex-theme-options .gip-uninstall-pref__label {
			display:flex;
			align-items:flex-start;
			gap:12px;
			cursor:pointer;
		}
		.gridindex-theme-options .gip-uninstall-pref__label input[type=checkbox] {
			margin-top:3px;
			flex-shrink:0;
		}
		.gridindex-theme-options .gip-uninstall-pref__label strong {
			display:block;
			font-size:13px;
			margin-bottom:3px;
		}
		.gridindex-theme-options .gip-uninstall-pref__desc {
			display:block;
			font-size:12px;
			line-height:1.5;
			opacity:.85;
		}

		/* ---------- HIGH-FREQUENCY WARNING (v1.0.33) ---------- */
		.gridindex-theme-options .gip-freq-warn {
			margin-top:8px;
			padding:10px 12px;
			background:rgba(245,158,11,.12);
			border:1px solid rgba(245,158,11,.42);
			border-radius:var(--gi-radius-sm);
			font-size:12px;
			line-height:1.5;
		}

		/* ---------- DUPLICATE DETECTOR (v1.0.30) ---------- */
		.gridindex-theme-options .gip-diag-dup-row {
			padding:4px 0;
			font-size:13px;
			line-height:1.5;
		}
		.gridindex-theme-options .gip-diag-dup-row a {
			font-weight:700;
			padding-right:6px;
		}
		.gridindex-theme-options .gip-diag-dup-meta {
			/* v1.0.32 — readability fix: was opacity:.6, dropped the date / source /
			   hash text into hard-to-read territory on dark backgrounds. .85 keeps
			   it visually secondary to the post-ID link without making it faint. */
			opacity:.85;
		}
		.gridindex-theme-options .gip-diag-dup-meta code {
			background:rgba(255,255,255,.10);
			padding:2px 6px;
			border-radius:3px;
			/* v1.0.32 — was font-size:11px which was the smallest text on the page. */
			font-size:12px;
		}
		/* v1.0.31 — KEEP / TRASH badges in the duplicate group list. */
		.gridindex-theme-options .gip-diag-dup-keep-badge,
		.gridindex-theme-options .gip-diag-dup-trash-badge {
			display:inline-block;
			font:700 9px/1 inherit;
			letter-spacing:.08em;
			padding:3px 6px;
			border-radius:3px;
			margin-right:6px;
			vertical-align:1px;
		}
		.gridindex-theme-options .gip-diag-dup-keep-badge {
			background:rgba(16,185,129,.18);
			color:#10b981;
		}
		.gridindex-theme-options .gip-diag-dup-trash-badge {
			background:rgba(239,68,68,.18);
			color:#ef4444;
		}
		.gridindex-theme-options .gip-diag-dup-row--keep a {
			color:#10b981;
		}
		/* v1.0.32 — In the duplicate-detector card specifically, the small
		   caption text (.gi-field__desc) was hard to read at the global .75
		   opacity. Lift it without affecting the rest of the page. */
		.gridindex-theme-options .gip-tab-panel[data-panel="diagnostics"] .gi-field__desc { opacity:.9; }

		/* ---------- SUPPORT / FAQ TAB (v1.0.43) ---------- */
		.gridindex-theme-options .gip-faq-search {
			display:flex;
			align-items:center;
			gap:12px;
			margin-bottom:20px;
		}
		.gridindex-theme-options .gip-faq-search #gip-faq-search {
			flex:1 1 auto;
			max-width:420px;
		}
		.gridindex-theme-options .gip-faq-search__hint {
			font-size:12px;
			opacity:.7;
		}
		.gridindex-theme-options .gip-faq-section {
			margin-bottom:22px;
		}
		.gridindex-theme-options .gip-faq-section:last-child { margin-bottom:0; }
		.gridindex-theme-options .gip-faq-section__title {
			font-size:11px;
			font-weight:700;
			letter-spacing:.08em;
			text-transform:uppercase;
			opacity:.7;
			margin:0 0 10px;
			padding-bottom:6px;
			border-bottom:1px solid var(--gi-border);
		}
		.gridindex-theme-options .gip-faq-item {
			padding:12px 14px;
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			background:rgba(255,255,255,.03);
			margin-bottom:8px;
		}
		.gridindex-theme-options .gip-faq-item[open] {
			background:rgba(255,255,255,.05);
		}
		.gridindex-theme-options .gip-faq-item__q {
			font-weight:600;
			font-size:13px;
			cursor:pointer;
			list-style:none;
			position:relative;
			padding-right:24px;
		}
		.gridindex-theme-options .gip-faq-item__q::-webkit-details-marker { display:none; }
		.gridindex-theme-options .gip-faq-item__q::after {
			content:'+';
			position:absolute;
			right:0; top:50%;
			transform:translateY(-50%);
			font-size:18px;
			opacity:.6;
			transition:transform .15s ease;
		}
		.gridindex-theme-options .gip-faq-item[open] .gip-faq-item__q::after {
			content:'−';
		}
		.gridindex-theme-options .gip-faq-item__a {
			margin-top:10px;
			font-size:13px;
			line-height:1.6;
			opacity:.92;
		}
		.gridindex-theme-options .gip-faq-item__a p { margin:0 0 8px; }
		.gridindex-theme-options .gip-faq-item__a p:last-child { margin-bottom:0; }
		.gridindex-theme-options .gip-faq-item.is-hidden { display:none; }
		.gridindex-theme-options .gip-faq-section.is-hidden { display:none; }
		.gridindex-theme-options .gip-faq-foot {
			margin-top:24px;
			padding:12px 14px;
			background:rgba(16,185,129,.05);
			border:1px solid rgba(16,185,129,.20);
			border-radius:var(--gi-radius-sm);
			font-size:12px;
			line-height:1.6;
			opacity:.95;
		}

		/* ---------- SUPPORT TAB: Credits card (v1.0.55, restructured v1.0.56) ---------- */
		/* Visual separation between the FAQ card and the Credits card below it.
		   Same gi-card base — the variant class adds top margin so each card
		   breathes. */
		.gridindex-theme-options .gip-support-card { margin-top:20px; }

		/* Inline text link inside the Credits paragraph — small, tasteful, no
		   button styling. Inherits the theme accent color. */
		.gridindex-theme-options .gip-credits__link {
			color:var(--gi-accent);
			text-decoration:none;
			font-weight:600;
		}
		.gridindex-theme-options .gip-credits__link:hover { text-decoration:underline; }

		.gridindex-theme-options .gip-credits__main {
			margin-bottom:16px;
		}
		.gridindex-theme-options .gip-credits__line {
			margin:0 0 6px;
			font-size:13px;
			line-height:1.55;
		}
		.gridindex-theme-options .gip-credits__line--lead {
			font-size:14px;
		}
		.gridindex-theme-options .gip-credits__line:last-child { margin-bottom:0; }

		.gridindex-theme-options .gip-credits__products {
			display:grid;
			grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));
			gap:12px;
			margin:14px 0 0;
			padding:14px;
			background:rgba(255,255,255,.03);
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
		}
		.gridindex-theme-options .gip-credits__product {
			display:flex;
			flex-direction:column;
			gap:3px;
		}
		.gridindex-theme-options .gip-credits__product-label {
			font-size:10px;
			font-weight:700;
			letter-spacing:.12em;
			text-transform:uppercase;
			opacity:.6;
		}
		.gridindex-theme-options .gip-credits__product-name {
			font-size:14px;
			font-weight:600;
			letter-spacing:.005em;
		}
		.gridindex-theme-options .gip-credits__product-desc {
			font-size:12px;
			line-height:1.5;
			opacity:.75;
		}

		.gridindex-theme-options .gip-credits__version {
			margin:14px 0 0;
			padding-top:12px;
			border-top:1px solid var(--gi-border);
			font-size:11px;
			letter-spacing:.04em;
			opacity:.6;
		}

		/* v1.0.57 — Credits CTA. The button is the primary visual signal that
		   this plugin is part of The Grid Index family. Ghost-style so it's
		   clearly an action without screaming sales pitch. The hint line gives
		   short context next to the button. */
		.gridindex-theme-options .gip-credits__cta {
			display:flex;
			align-items:center;
			gap:14px;
			flex-wrap:wrap;
			margin:16px 0 0;
			padding:14px;
			background:rgba(20,184,166,.06);
			border:1px solid rgba(20,184,166,.25);
			border-radius:var(--gi-radius-sm);
		}
		.gridindex-theme-options .gip-credits__btn {
			flex:0 0 auto;
			white-space:nowrap;
			font-weight:600;
		}
		.gridindex-theme-options .gip-credits__cta-hint {
			flex:1 1 auto;
			font-size:12.5px;
			line-height:1.45;
			opacity:.85;
		}

		/* ---------- SUPPORT TAB: project banner (v1.0.58) ---------- */
		/* Top-of-tab project link banner. Goal: the button must be the obvious
		   first thing the user sees on the Support tab — not buried at the
		   bottom of a Credits card behind a 15-entry FAQ. Side-by-side layout
		   with the description on the left and a prominent primary-style
		   button on the right, anchored in a teal-tinted callout. */
		.gridindex-theme-options .gip-project-banner {
			display:flex;
			align-items:center;
			justify-content:space-between;
			gap:24px;
			flex-wrap:wrap;
			padding:20px 24px;
			margin-bottom:20px;
			background:linear-gradient(135deg, rgba(20,184,166,.12) 0%, rgba(20,184,166,.04) 100%);
			border:1px solid rgba(20,184,166,.35);
			border-radius:var(--gi-radius);
		}
		.gridindex-theme-options .gip-project-banner__copy {
			flex:1 1 320px;
			min-width:0;
		}
		.gridindex-theme-options .gip-project-banner__label {
			display:inline-block;
			font-size:10px;
			font-weight:700;
			letter-spacing:.18em;
			text-transform:uppercase;
			color:var(--gi-accent);
			opacity:1;
			margin-bottom:6px;
		}
		.gridindex-theme-options .gip-project-banner__title {
			margin:0 0 6px;
			font-size:22px;
			line-height:1.15;
			letter-spacing:-.005em;
			font-weight:600;
		}
		.gridindex-theme-options .gip-project-banner__desc {
			margin:0;
			font-size:13px;
			line-height:1.5;
			opacity:.85;
			max-width:64ch;
		}
		.gridindex-theme-options .gip-project-banner__cta {
			flex:0 0 auto;
		}
		.gridindex-theme-options .gip-project-banner__btn {
			white-space:nowrap;
			font-weight:600;
			padding:10px 18px;
			font-size:14px;
		}

		/* ---------- DUPLICATE BANNER on Feeds tab (v1.0.42) ---------- */
		.gridindex-theme-options .gip-dup-banner {
			display:flex;
			align-items:center;
			gap:14px;
			margin-bottom:14px;
			padding:12px 14px;
			background:rgba(239,68,68,.10);
			border:1px solid rgba(239,68,68,.45);
			border-radius:var(--gi-radius-sm);
			color:#fca5a5;
		}
		.gridindex-theme-options .gip-dup-banner__icon {
			font-size:18px;
			line-height:1;
			flex:0 0 auto;
		}
		.gridindex-theme-options .gip-dup-banner__msg {
			flex:1 1 auto;
			min-width:0;
			line-height:1.45;
		}
		.gridindex-theme-options .gip-dup-banner__msg strong { display:block; font-size:13px; color:#fecaca; }
		.gridindex-theme-options .gip-dup-banner__sub { display:block; margin-top:3px; font-size:12px; opacity:.85; }
		.gridindex-theme-options .gip-dup-banner__btn {
			flex:0 0 auto;
			padding:6px 12px;
			font-size:12px;
			border-color:rgba(239,68,68,.6);
			color:#fca5a5;
		}
		.gridindex-theme-options .gip-dup-banner__btn:hover {
			background:rgba(239,68,68,.15);
			color:#fecaca;
		}

		/* ---------- FEED HEALTH CHECK (v1.0.41) ---------- */
		.gridindex-theme-options .gip-health {
			display:flex;
			flex-direction:column;
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			overflow:hidden;
		}
		.gridindex-theme-options .gip-health__head,
		.gridindex-theme-options .gip-health__row {
			display:grid;
			grid-template-columns: minmax(0, 1.7fr) 110px 90px 130px;
			gap:14px;
			align-items:center;
			padding:10px 14px;
			border-bottom:1px solid var(--gi-border);
		}
		.gridindex-theme-options .gip-health__head {
			background:rgba(255,255,255,.04);
			font-size:11px;
			text-transform:uppercase;
			letter-spacing:.05em;
			opacity:.7;
		}
		.gridindex-theme-options .gip-health__row:last-child { border-bottom:0; }
		.gridindex-theme-options .gip-health__row--silent { background:rgba(239,68,68,.07); }
		.gridindex-theme-options .gip-health__row--stale  { background:rgba(245,158,11,.05); }
		.gridindex-theme-options .gip-health__row strong { font-weight:600; }
		.gridindex-theme-options .gip-health__url {
			display:block;
			margin-top:3px;
			font:11px/1.3 ui-monospace,Menlo,Consolas,monospace;
			opacity:.55;
			overflow:hidden;
			text-overflow:ellipsis;
			white-space:nowrap;
		}
		.gridindex-theme-options .gip-health__cell--fetched,
		.gridindex-theme-options .gip-health__cell--count { font-size:12px; opacity:.85; }
		.gridindex-theme-options .gip-health__pill {
			display:inline-block;
			padding:3px 9px;
			border-radius:11px;
			font-size:11px;
			font-weight:600;
			letter-spacing:.02em;
		}
		.gridindex-theme-options .gip-health__pill--silent  { background:rgba(239,68,68,.18); color:#fca5a5; }
		.gridindex-theme-options .gip-health__pill--stale   { background:rgba(245,158,11,.18); color:#fcd34d; }
		.gridindex-theme-options .gip-health__pill--pending { background:rgba(148,163,184,.15); color:#cbd5e1; }
		.gridindex-theme-options .gip-health__pill--ok      { background:rgba(16,185,129,.15); color:#6ee7b7; }

		/* ---------- DIAGNOSTICS TABLE ---------- */
		.gridindex-theme-options .gip-diag-table {
			width:100%;
			border-collapse:separate;
			border-spacing:0;
			background:transparent;
			border:1px solid var(--gi-border);
			border-radius:var(--gi-radius-sm);
			overflow:hidden;
			table-layout:fixed; /* lets us truncate URL cells with ellipsis */
		}
		.gridindex-theme-options .gip-diag-table thead th {
			background:rgba(255,255,255,.05);
			color:inherit;
			opacity:.9;
			font:600 11px/1 inherit;
			text-transform:uppercase;
			letter-spacing:.08em;
			padding:12px 14px;
			text-align:left;
			border-bottom:1px solid var(--gi-border);
		}
		.gridindex-theme-options .gip-diag-table tbody td {
			padding:12px 14px;
			vertical-align:top;
			font-size:13px;
			line-height:1.5;
			border-bottom:1px solid var(--gi-border);
		}
		.gridindex-theme-options .gip-diag-table tbody tr:last-child td { border-bottom:0; }
		.gridindex-theme-options .gip-diag-table tbody tr:nth-child(even) td { background:rgba(255,255,255,.02); }
		.gridindex-theme-options .gip-diag-col-title  { width:38%; }
		.gridindex-theme-options .gip-diag-col-url    { width:34%; }
		.gridindex-theme-options .gip-diag-col-source { width:14%; }
		.gridindex-theme-options .gip-diag-col-status { width:14%; }
		.gridindex-theme-options .gip-diag-cell-title a { font-weight:600; text-decoration:none; }
		.gridindex-theme-options .gip-diag-cell-title a:hover { text-decoration:underline; }
		.gridindex-theme-options .gip-diag-date {
			margin-top:3px;
			/* v1.0.32 — was 11px @ .55 opacity, hard to read. */
			font-size:12px;
			opacity:.8;
			letter-spacing:.02em;
		}
		.gridindex-theme-options .gip-diag-url {
			display:block;
			max-width:100%;
			overflow:hidden;
			text-overflow:ellipsis;
			white-space:nowrap;
			font:13px/1.4 ui-monospace,Menlo,Consolas,monospace;
			color:var(--gi-fg, inherit);
			/* v1.0.32 — bumped from .85 → 1.0; the ellipsised URL is the most
			   important content in the row, no reason to fade it. */
			opacity:1;
			text-decoration:none;
		}
		.gridindex-theme-options .gip-diag-url:hover { text-decoration:underline; }
		.gridindex-theme-options .gip-diag-missing {
			color:#ef4444;
			font-style:italic;
			font-size:12px;
		}
		.gridindex-theme-options .gip-diag-ok  { color:#10b981; font-weight:700; white-space:nowrap; }
		.gridindex-theme-options .gip-diag-bad { color:#ef4444; font-weight:700; white-space:nowrap; }
		</style>

		<script>
		// v1.0.22 — Config for AJAX auto-save and toast notifications.
		window.gipRssCfg = window.gipRssCfg || {
			ajaxUrl:         <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			saveAction:      <?php echo wp_json_encode( self::PAGE_SLUG . '_ajax_save' ); ?>,
			progressAction:  <?php echo wp_json_encode( self::PAGE_SLUG . '_ajax_progress' ); ?>,
			fetchOneAction:  <?php echo wp_json_encode( self::PAGE_SLUG . '_ajax_fetch_one' ); ?>,
			nonce:           <?php echo wp_json_encode( wp_create_nonce( self::NONCE_ACTION ) ); ?>,
			i18n: {
				saving:  <?php echo wp_json_encode( __( 'Saving…', 'grid-index-rss-importer' ) ); ?>,
				saved:   <?php echo wp_json_encode( __( 'Saved', 'grid-index-rss-importer' ) ); ?>,
				error:   <?php echo wp_json_encode( __( 'Save failed', 'grid-index-rss-importer' ) ); ?>
			}
		};
		(function(){
			/* ---------- Feed list add/remove ---------- */
			var list   = document.getElementById('gip-rss-feeds-list');
			var addBtn = document.getElementById('gip-rss-add-row-top');
			if ( list && addBtn ) {
				var reindex = function() {
					var rows = list.querySelectorAll('.gip-rss-feed-row');
					rows.forEach(function(row, i){
						row.querySelectorAll('input,select').forEach(function(el){
							if ( el.name ) {
								el.name = el.name.replace(/feeds\[\d+\]/, 'feeds[' + i + ']');
							}
						});
					});
				};

				addBtn.addEventListener('click', function(){
					var rows = list.querySelectorAll('.gip-rss-feed-row');
					if ( rows.length === 0 ) return;
					var newRow = rows[0].cloneNode(true);
					newRow.querySelectorAll('input').forEach(function(el){ el.value = ''; });
					newRow.querySelectorAll('select').forEach(function(el){ el.selectedIndex = 0; });
					// Reset status dot to 'never' on the cloned row.
					newRow.querySelectorAll('.gip-dot').forEach(function(el){
						el.className = 'gip-dot gip-dot--never';
						el.setAttribute('title', 'Never fetched');
						el.setAttribute('aria-label', 'Never fetched');
					});
					// v1.0.28 — reset the Last Fetched cell to "Never" on cloned row.
					newRow.querySelectorAll('.gip-rss-feed-row__fetched').forEach(function(el){
						el.className = 'gip-rss-feed-row__fetched gip-rss-feed-row__fetched--never';
						el.removeAttribute('title');
						el.textContent = 'Never';
					});
					newRow.classList.remove('is-detail-open');
					// v1.0.20 — insert at the top of the list (right after the
					// header row) so the new row is visible without scrolling.
					// Then focus the URL input so the user can start typing.
					var header = list.querySelector('.gip-rss-feeds-header');
					if ( header && header.nextSibling ) {
						list.insertBefore(newRow, header.nextSibling);
					} else {
						list.appendChild(newRow);
					}
					reindex();
					var firstInput = newRow.querySelector('input[type="url"], input');
					if ( firstInput ) firstInput.focus();
				});

				list.addEventListener('click', function(e){
					var t = e.target;
					if ( t && t.classList.contains('gip-rss-remove-row') ) {
						/* Click a status dot to toggle the detail row beneath it. */
						var rows = list.querySelectorAll('.gip-rss-feed-row');
						var row  = t.closest('.gip-rss-feed-row');

						// v1.0.21 — Persist the delete. Previously clicking × only
						// removed the row from the DOM; the deletion wasn't saved
						// until the user remembered to click "Save Feeds". If they
						// switched tabs or refreshed first, the row came back.
						// Now we remove the row AND submit the surrounding form so
						// the delete is committed server-side immediately.
						if ( rows.length <= 1 ) {
							// Last row — empty it instead of removing so the form
							// still has at least one row to display after save.
							row.querySelectorAll('input').forEach(function(el){ el.value = ''; });
							row.querySelectorAll('select').forEach(function(el){ el.selectedIndex = 0; });
						} else {
							row.remove();
						}
						reindex();

						// Submit the parent form, if any, to persist.
						var form = list.closest('form');
						if ( form ) form.submit();
					}
					/* Click a status dot to toggle the detail row beneath it. */
					if ( t && t.classList.contains('gip-dot') ) {
						var row = t.closest('.gip-rss-feed-row');
						if ( row ) {
							row.classList.toggle('is-detail-open');
							var detail = row.querySelector('.gip-rss-feed-row__detail');
							if ( detail ) detail.hidden = ! row.classList.contains('is-detail-open');
						}
					}
				});
			}

			/* ---------- Tabs ---------- */
			var tabs   = document.querySelectorAll('.gip-tab');
			var panels = document.querySelectorAll('.gip-tab-panel');
			if ( tabs.length && panels.length ) {
				var activate = function(name) {
					tabs.forEach(function(t){
						var on = t.getAttribute('data-tab') === name;
						t.classList.toggle('is-active', on);
						t.setAttribute('aria-selected', on ? 'true' : 'false');
					});
					panels.forEach(function(p){
						var on = p.getAttribute('data-panel') === name;
						p.classList.toggle('is-active', on);
						p.hidden = ! on;
					});
					// Keep URL hash in sync so refresh/share stays on the same tab.
					if ( history.replaceState ) {
						history.replaceState(null, '', '#' + name);
					}
				};
				tabs.forEach(function(t){
					t.addEventListener('click', function(){
						activate(t.getAttribute('data-tab'));
					});
				});
				/* Honor the URL hash on first load. */
				var initial = (window.location.hash || '').replace('#', '');
				var valid   = ['feeds','catalog','settings','diagnostics','support'];
				if ( valid.indexOf(initial) !== -1 ) {
					activate(initial);
				}

				/* v1.0.42 — Banner click handler. Clicking "Review on Diagnostics"
				   from the Feeds-tab duplicate banner switches to the Diagnostics
				   tab AND scrolls to the duplicate-detector card. Native <a href>
				   would only set the hash; we additionally need to activate the
				   panel before scrolling (the panel is hidden until then). */
				var jumpLinks = document.querySelectorAll('[data-jump-to-dup]');
				jumpLinks.forEach(function(link){
					link.addEventListener('click', function(e){
						e.preventDefault();
						activate('diagnostics');
						// Wait one tick for the panel to unhide before scrolling
						// — scrollIntoView on a hidden element is a no-op.
						setTimeout(function(){
							var target = document.getElementById('gip-dup-detector');
							if ( target ) {
								target.scrollIntoView({ behavior: 'smooth', block: 'start' });
							}
						}, 30);
					});
				});
			}

			/* ---------- FAQ search filter (v1.0.43) ---------- */
			/* Live client-side filter over the support tab's FAQ entries.
			   Reads data-faq-q and data-faq-a (set by PHP), case-insensitive
			   substring match, hides non-matching entries AND empty sections.
			   No external dependencies. */
			var faqInput = document.getElementById('gip-faq-search');
			var faqHint  = document.getElementById('gip-faq-search-hint');
			if ( faqInput ) {
				var faqItems    = Array.prototype.slice.call(document.querySelectorAll('.gip-faq-item'));
				var faqSections = Array.prototype.slice.call(document.querySelectorAll('.gip-faq-section'));
				var filterFaq = function() {
					var q = (faqInput.value || '').trim().toLowerCase();
					var visible = 0;
					faqItems.forEach(function(it){
						if ( q === '' ) {
							it.classList.remove('is-hidden');
							it.open = false;
							visible++;
							return;
						}
						var hay = ((it.getAttribute('data-faq-q') || '') + ' ' + (it.getAttribute('data-faq-a') || '')).toLowerCase();
						if ( hay.indexOf(q) !== -1 ) {
							it.classList.remove('is-hidden');
							it.open = true; // auto-open matched answers
							visible++;
						} else {
							it.classList.add('is-hidden');
						}
					});
					// Hide entire sections whose items are all hidden.
					faqSections.forEach(function(sec){
						var any = sec.querySelectorAll('.gip-faq-item:not(.is-hidden)').length > 0;
						sec.classList.toggle('is-hidden', !any);
					});
					if ( faqHint ) {
						faqHint.textContent = q === ''
							? ''
							: (visible + ' match' + (visible === 1 ? '' : 'es'));
					}
				};
				faqInput.addEventListener('input', filterFaq);
			}

			/* ---------- Auto-save (debounced) v1.0.22 ---------- */
			/* Listens for input/change on the main settings form. After 800ms of
			   idle, POSTs the whole form to admin-ajax.php and updates the inline
			   "Saved ✓ / Saving… / Save failed" indicator. No page reload. */
			var cfg    = window.gipRssCfg;
			var form   = document.querySelector('form input[name="action"][value$="_save"]');
			form       = form ? form.closest('form') : null;
			var ind    = document.getElementById('gip-save-indicator');
			if ( form && cfg && cfg.ajaxUrl ) {
				var saveTimer = null;
				var setIndicator = function(state, label) {
					if ( ! ind ) return;
					ind.textContent = label || '';
					ind.className   = 'gip-save-indicator' + (state ? ' is-' + state : '');
				};
				var doSave = function() {
					setIndicator('saving', cfg.i18n.saving);
					var fd = new FormData(form);
					// Swap the action to our AJAX endpoint + add the AJAX nonce.
					fd.set('action', cfg.saveAction);
					fd.set('_ajax_nonce', cfg.nonce);
					fetch(cfg.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						body: fd
					}).then(function(r){
						if ( ! r.ok ) throw new Error('http ' + r.status);
						return r.json();
					}).then(function(json){
						if ( json && json.success ) {
							setIndicator('saved', '✓ ' + cfg.i18n.saved);
							// Auto-fade the "Saved" label after a couple seconds.
							setTimeout(function(){ setIndicator('', ''); }, 2200);
						} else {
							setIndicator('error', cfg.i18n.error);
						}
					}).catch(function(){
						setIndicator('error', cfg.i18n.error);
					});
				};
				var schedule = function() {
					clearTimeout(saveTimer);
					saveTimer = setTimeout(doSave, 800);
				};
				form.addEventListener('input',  schedule);
				form.addEventListener('change', schedule);
			}

			/* ---------- Toast notifications v1.0.22 + Fetch now action v1.0.36 ---------- */
			/* The plugin already passes status messages back via the
			   gip_rss_msg URL param. Render the existing notice div as a
			   slide-in toast that auto-dismisses, instead of a static
			   banner the user has to find.
			   
			   v1.0.36: If the redirect carried data-added-idx (a catalog ADD
			   just happened), don't auto-dismiss — inject a "Fetch now"
			   action button so the user can immediately verify the new feed
			   works without leaving the Catalog tab. */
			var notice = document.querySelector('.gi-notice');
			if ( notice && notice.textContent.trim() ) {
				notice.classList.add('gip-toast');

				var addedIdx  = notice.getAttribute('data-added-idx');
				var addedName = notice.getAttribute('data-added-name') || '';
				var isAddToast = ( addedIdx !== null && addedIdx !== '' );

				if ( isAddToast ) {
					// Wrap the existing message text + add action buttons.
					var msg = notice.textContent;
					notice.textContent = '';
					var msgEl = document.createElement('div');
					msgEl.className = 'gip-toast__msg';
					msgEl.textContent = msg;
					notice.appendChild(msgEl);

					var actions = document.createElement('div');
					actions.className = 'gip-toast__actions';

					var fetchBtn = document.createElement('button');
					fetchBtn.type = 'button';
					fetchBtn.className = 'gi-btn gi-btn--primary gip-toast__btn';
					fetchBtn.textContent = '↻ Fetch now';

					var dismissBtn = document.createElement('button');
					dismissBtn.type = 'button';
					dismissBtn.className = 'gi-btn gi-btn--ghost gip-toast__btn';
					dismissBtn.textContent = 'Dismiss';

					actions.appendChild(fetchBtn);
					actions.appendChild(dismissBtn);
					notice.appendChild(actions);

					dismissBtn.addEventListener('click', function(){
						notice.classList.add('is-leaving');
						setTimeout(function(){ notice.style.display = 'none'; }, 400);
					});

					fetchBtn.addEventListener('click', function(){
						if ( fetchBtn.disabled ) return;
						fetchBtn.disabled = true;
						dismissBtn.disabled = true;
						fetchBtn.innerHTML = '<span class="gip-spin"></span> ' +
							(addedName ? 'Fetching: ' + escapeHtml(addedName) + '…' : 'Fetching…');
						// Use the existing progress poller — it'll pick up
						// transient writes from run_import() and update the
						// button text live ("Fetching 1 of 1 — Name…").
						startPolling(fetchBtn);

						var fd = new FormData();
						fd.set('action', cfg.fetchOneAction);
						fd.set('_ajax_nonce', cfg.nonce);
						fd.set('feed_index', addedIdx);

						fetch(cfg.ajaxUrl, {
							method: 'POST',
							credentials: 'same-origin',
							body: fd
						}).then(function(r){
							return r.ok ? r.json() : Promise.reject(new Error('http ' + r.status));
						}).then(function(json){
							// Stop progress polling immediately on success.
							if ( progressTimer ) { clearInterval(progressTimer); progressTimer = null; }
							if ( ! json || ! json.success || ! json.data ) {
								fetchBtn.innerHTML = '⚠ Fetch failed';
								return;
							}
							var d = json.data;
							var n = parseInt(d.last_imported, 10) || 0;
							var s = parseInt(d.last_skipped,  10) || 0;
							var e = parseInt(d.last_errors,   10) || 0;
							fetchBtn.innerHTML = '✓ ' + n + ' new, ' + s + ' already imported' + (e ? ', ' + e + ' errors' : '');
							fetchBtn.classList.remove('gi-btn--primary');
							fetchBtn.classList.add('gi-btn--ghost');
							// Auto-dismiss after a short read-time so user can move on.
							setTimeout(function(){
								notice.classList.add('is-leaving');
								setTimeout(function(){ notice.style.display = 'none'; }, 400);
							}, 4500);
						}).catch(function(){
							if ( progressTimer ) { clearInterval(progressTimer); progressTimer = null; }
							fetchBtn.innerHTML = '⚠ Fetch failed';
						});
					});

					// No auto-dismiss while a Fetch now button is offered.
				} else {
					// Normal toast: auto-dismiss after 5 seconds (errors stay visible longer).
					var ttl = notice.classList.contains('gi-notice--reset') ? 9000 : 5000;
					setTimeout(function(){ notice.classList.add('is-leaving'); }, ttl - 400);
					setTimeout(function(){ notice.style.display = 'none'; }, ttl);
				}
			}

			/* ---------- Long-action loading state v1.0.23 + progress polling v1.0.26 ---------- */
			/* Import Now, Force re-import, and per-row Fetch are synchronous
			   requests that can take 30+ seconds (often 2+ minutes). Disable
			   the button on submit, swap the label to a working state, pulse
			   a spinner, AND start polling the progress endpoint so the
			   label updates live with "Fetching 3 of 11 — TechCrunch". */
			var labelFor = {
				import: 'Importing feeds — this can take 30-60 seconds…',
				force:  'Re-importing last 24 hours — this can take a minute…',
				fetch:  'Fetching…'
			};

			var progressTimer = null;
			var progressLabel = null; // The button element we're updating.
			var pollProgress = function() {
				if ( ! cfg || ! cfg.ajaxUrl || ! progressLabel ) return;
				var url = cfg.ajaxUrl + '?action=' + encodeURIComponent(cfg.progressAction) + '&_ajax_nonce=' + encodeURIComponent(cfg.nonce);
				fetch(url, { credentials: 'same-origin' })
					.then(function(r){ return r.ok ? r.json() : null; })
					.then(function(json){
						if ( ! json || ! json.success || ! json.data ) return;
						var data = json.data;
						if ( data.idle ) return; // Run hasn't started writing progress yet.
						if ( data.state === 'done' ) {
							// Page reload is imminent; show 100% briefly.
							progressLabel.innerHTML = '<span class="gip-spin"></span> Finishing up…';
							return;
						}
						// Running: show "Fetching N of M — Name"
						var current = data.current || '';
						var step    = parseInt(data.step, 10)  || 0;
						var total   = parseInt(data.total, 10) || 0;
						if ( total > 0 ) {
							progressLabel.innerHTML = '<span class="gip-spin"></span> Fetching ' + step + ' of ' + total +
								(current ? ' — ' + escapeHtml(current) : '') + '…';
						}
					})
					.catch(function(){ /* swallow polling errors */ });
			};
			var escapeHtml = function(s){
				return String(s).replace(/[&<>"']/g, function(c){
					return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
				});
			};
			var startPolling = function(btn) {
				progressLabel = btn;
				if ( progressTimer ) clearInterval(progressTimer);
				// First poll right away, then every 1s.
				pollProgress();
				progressTimer = setInterval(pollProgress, 1000);
			};

			// Form-based actions (Import Now, Force re-import).
			document.querySelectorAll('form[data-gip-long-action]').forEach(function(form){
				form.addEventListener('submit', function(){
					var kind = form.getAttribute('data-gip-long-action');
					// The triggering button is usually inside the form. But for
					// hidden forms submitted via HTML5 `form="..."` attribute
					// (the Feeds-toolbar Import/Force buttons), the button lives
					// outside the form. Fall back to looking up buttons that
					// declare form="<this form's id>" in that case.
					var btn = form.querySelector('button[type="submit"]');
					if ( ! btn && form.id ) {
						btn = document.querySelector('button[form="' + form.id + '"]');
					}
					if ( ! btn ) return;
					btn.disabled = true;
					btn.classList.add('is-loading');
					btn.dataset.originalLabel = btn.textContent;
					btn.innerHTML = '<span class="gip-spin"></span> ' + (labelFor[kind] || 'Working…');
					// Also dim any other long-action submit buttons (form=… or in-form).
					document.querySelectorAll('button[type="submit"][form^="gip-"], form[data-gip-long-action] button[type="submit"]').forEach(function(otherBtn){
						if ( otherBtn !== btn ) otherBtn.disabled = true;
					});
					// Start live progress polling for full-import runs.
					if ( kind === 'import' || kind === 'force' ) {
						startPolling(btn);
					}
				});
			});
			// Link-based action (per-row Fetch).
			document.querySelectorAll('a.gip-fetch-link').forEach(function(link){
				link.addEventListener('click', function(){
					if ( link.classList.contains('is-loading') ) {
						// Prevent double-click queuing another fetch.
						return;
					}
					link.classList.add('is-loading');
					link.dataset.originalLabel = link.textContent;
					link.innerHTML = '<span class="gip-spin gip-spin--small"></span>';
					link.style.pointerEvents = 'none';
				});
			});
		})();
		</script>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Admin actions
	 * ------------------------------------------------------------------- */

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$this->save_from_input( wp_unslash( $_POST ) );

		$this->unschedule_cron();
		$this->maybe_reschedule_cron();

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( __( 'Settings saved.', 'grid-index-rss-importer' ) ),
			'gip_rss_type' => 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * v1.0.22 — AJAX save endpoint for auto-save while typing.
	 *
	 * Same payload and sanitization as handle_save(), but returns JSON
	 * instead of redirecting so the page doesn't reload between keystrokes.
	 * Hooked via wp_ajax_{action} so only logged-in users hit it; the
	 * permission check below restricts to manage_options.
	 */
	public function handle_ajax_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, '_ajax_nonce' );

		// The form posts a normal $_POST payload (same field names as the
		// manual save). wp_unslash to match WP's auto-magic-quotes handling.
		$this->save_from_input( wp_unslash( $_POST ) );

		// Don't bounce the cron schedule on every keystroke — that's wasteful
		// and 'frequency' rarely changes while typing into URL fields. The
		// next full Save Settings click (or activation) will reconcile it.

		wp_send_json_success( array( 'saved_at' => time() ) );
	}

	/**
	 * v1.0.26 — AJAX endpoint that returns the current import progress.
	 * Polled by the admin JS while an import is running so the spinner
	 * label can show "Fetching 3 of 11 — TechCrunch".
	 *
	 * Returns either a progress state object or { idle: true } if no
	 * import is in flight. Gated to manage_options.
	 */
	public function handle_ajax_progress() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		// Nonce check — same nonce we use for save; cheaper than a separate
		// nonce since both are admin-only short-lived polls.
		check_ajax_referer( self::NONCE_ACTION, '_ajax_nonce' );

		$state = $this->read_progress();
		if ( ! $state ) {
			wp_send_json_success( array( 'idle' => true ) );
		}
		wp_send_json_success( $state );
	}

	/**
	 * v1.0.36 — AJAX endpoint for non-reloading single-feed fetch. Called
	 * from the "Fetch now" action in the post-Catalog-add toast, so the
	 * user can immediately verify a newly-added feed works without
	 * leaving the Catalog tab.
	 *
	 * Reuses run_import() in single-feed mode. The progress transient is
	 * already written by the import loop, so the existing progress
	 * polling endpoint surfaces status without any extra work here.
	 */
	public function handle_ajax_fetch_one() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, '_ajax_nonce' );

		$idx = isset( $_POST['feed_index'] ) ? (int) $_POST['feed_index'] : -1;
		if ( $idx < 0 ) {
			wp_send_json_error( array( 'message' => 'bad_index' ), 400 );
		}

		// Validate the index points at a real feed before we kick off work.
		$s = $this->get_settings();
		if ( empty( $s['feeds'][ $idx ] ) || empty( $s['feeds'][ $idx ]['url'] ) ) {
			wp_send_json_error( array( 'message' => 'no_feed_at_index' ), 404 );
		}

		// Run import for just this one feed. $manual=true bypasses the
		// per-feed interval gating. The progress transient is updated by
		// the import loop so the JS poller picks it up.
		$result = $this->run_import( true, 0, $idx );

		// Reload latest feed state for the response so the toast can show
		// per-feed totals.
		$s2  = $this->get_settings();
		$row = $s2['feeds'][ $idx ] ?? array();

		wp_send_json_success( array(
			'feed_index'    => $idx,
			'feed_name'     => isset( $row['name'] ) ? $row['name'] : '',
			'totals'        => $result,
			'last_status'   => isset( $row['last_status'] )   ? $row['last_status']   : '',
			'last_message'  => isset( $row['last_message'] )  ? $row['last_message']  : '',
			'last_imported' => isset( $row['last_imported'] ) ? (int) $row['last_imported'] : 0,
			'last_skipped'  => isset( $row['last_skipped'] )  ? (int) $row['last_skipped']  : 0,
			'last_errors'   => isset( $row['last_errors'] )   ? (int) $row['last_errors']   : 0,
			'last_fetched'  => isset( $row['last_fetched'] )  ? (int) $row['last_fetched']  : 0,
		) );
	}

	/**
	 * Persist a submitted settings payload. Shared by handle_save() (full
	 * page submit) and handle_ajax_save() (auto-save while typing).
	 *
	 * @param array $in Raw $_POST-shaped input (already unslashed).
	 */
	private function save_from_input( $in ) {
		// Build a URL→status map from the existing settings so we can
		// preserve last_status/last_fetched fields across a save (the form
		// only posts URL + name, not status fields).
		$existing      = $this->get_settings();
		$status_by_url = array();
		if ( ! empty( $existing['feeds'] ) && is_array( $existing['feeds'] ) ) {
			foreach ( $existing['feeds'] as $existing_feed ) {
				if ( ! empty( $existing_feed['url'] ) ) {
					$status_by_url[ $existing_feed['url'] ] = array_intersect_key(
						$existing_feed,
						array_flip( array( 'last_status', 'last_message', 'last_imported', 'last_skipped', 'last_errors', 'last_fetched', 'category' ) )
					);
				}
			}
		}

		$feeds = array();
		if ( ! empty( $in['feeds'] ) && is_array( $in['feeds'] ) ) {
			foreach ( $in['feeds'] as $row ) {
				$url = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
				if ( ! $url ) continue;

				// v1.0.34 — Per-feed interval. Form value wins if valid;
				// otherwise fall back to the catalog recommendation for this
				// URL (if known); otherwise DEFAULT_INTERVAL.
				$posted_interval = isset( $row['interval'] ) ? sanitize_key( $row['interval'] ) : '';
				if ( in_array( $posted_interval, self::VALID_INTERVALS, true ) ) {
					$interval = $posted_interval;
				} else {
					$interval = $this->get_recommended_interval_for_url( $url );
				}

				// v1.0.40 — Per-feed granular category. Empty string is valid
				// (means "RSS only"). Otherwise must be one of the whitelist
				// keys (News/World/Tech/Business/Science).
				$posted_cat = isset( $row['category'] ) ? sanitize_text_field( $row['category'] ) : '';
				if ( $posted_cat !== '' && ! isset( self::GRANULAR_CATEGORIES[ $posted_cat ] ) ) {
					$posted_cat = '';
				}

				$feed_record = array(
					'url'      => $url,
					'name'     => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '',
					'interval' => $interval,
					'category' => $posted_cat,
				);
				if ( isset( $status_by_url[ $url ] ) ) {
					$feed_record = array_merge( $feed_record, $status_by_url[ $url ] );
					// Re-assert the freshly-validated interval and category after
					// the merge (status_by_url may carry older values we want
					// overridden by the form values).
					$feed_record['interval'] = $interval;
					$feed_record['category'] = $posted_cat;
				}
				$feeds[] = $feed_record;
			}
		}

		// v1.0.24 — Defense-in-depth: clamp to the active-feeds cap.
		// Catalog toggles already enforce this; this catches anyone editing
		// the Feeds tab directly past the limit.
		if ( count( $feeds ) > self::MAX_ACTIVE_FEEDS ) {
			$feeds = array_slice( $feeds, 0, self::MAX_ACTIVE_FEEDS );
		}

		$valid_status = array( 'publish', 'draft', 'pending' );
		$valid_freq   = array( 'gip_rss_5min', 'gip_rss_15min', 'gip_rss_30min', 'hourly', 'twicedaily', 'manual' );
		$valid_image  = array( 'feed_first', 'content_first', 'none' );

		$settings = $this->get_settings();
		$settings['feeds']       = $feeds;
		// v1.0.13 — invalid/missing post_status falls back to 'publish' to match
		// the documented default in get_defaults(). Previously fell back to 'draft',
		// which silently flipped imported posts to draft when the form value was
		// missing for any reason (e.g. a stripped POST or a legacy save shape).
		$settings['post_status'] = in_array( $in['post_status'] ?? '', $valid_status, true ) ? $in['post_status'] : 'publish';
		$settings['frequency']   = in_array( $in['frequency'] ?? '', $valid_freq, true )     ? $in['frequency']   : 'hourly';
		$settings['image_mode']      = in_array( $in['image_mode'] ?? '', $valid_image, true )   ? $in['image_mode']  : 'feed_first';
		$settings['min_image_width'] = max( 0, min( 4000, (int) ( $in['min_image_width'] ?? 1000 ) ) );
		$settings['max_per_run']     = max( 1, min( 100, (int) ( $in['max_per_run'] ?? 10 ) ) );

		// v1.0.37 — keep_on_uninstall. Unchecked checkboxes don't post a
		// value, so we explicitly read it as a presence check. The form
		// includes a hidden marker `keep_on_uninstall_present=1` to tell
		// us the checkbox WAS on the page (vs. an AJAX save from a panel
		// that doesn't include it) — without the marker we leave the
		// stored value alone.
		if ( isset( $in['keep_on_uninstall_present'] ) ) {
			$settings['keep_on_uninstall'] = ! empty( $in['keep_on_uninstall'] );
		}

		$this->save_settings( $settings );
	}

	public function handle_run_now() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$result = $this->run_import( true );

		$msg = sprintf(
			/* translators: 1: imported, 2: skipped, 3: errors */
			__( 'Import complete — %1$d new, %2$d skipped, %3$d errors.', 'grid-index-rss-importer' ),
			$result['imported'], $result['skipped'], $result['errors']
		);

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( $msg ),
			'gip_rss_type' => $result['errors'] ? 'error' : 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Force re-import of recent items (last 24h by default). Deletes the
	 * existing duplicate first, then imports a fresh copy. Useful after
	 * settings changes (e.g. you flipped post_status from draft to publish
	 * and want recent items to come back as published).
	 */
	public function handle_force_reimport() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$hours  = 24;
		$result = $this->run_import( true, $hours );

		$msg = sprintf(
			/* translators: 1: hours, 2: imported, 3: skipped, 4: errors */
			__( 'Force re-import (last %1$dh) complete — %2$d refreshed, %3$d skipped, %4$d errors.', 'grid-index-rss-importer' ),
			$hours, $result['imported'], $result['skipped'], $result['errors']
		);

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( $msg ),
			'gip_rss_type' => $result['errors'] ? 'error' : 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Fetch a single feed by its index in the feeds array. Used by the
	 * per-row "Fetch" button in the admin so you can test one feed at a
	 * time without waiting through all the others.
	 */
	public function handle_fetch_one_feed() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nope.' );
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$idx    = isset( $_REQUEST['feed_index'] ) ? (int) $_REQUEST['feed_index'] : -1;
		$result = $this->run_import( true, 0, $idx );

		$s    = $this->get_settings();
		$feed = $s['feeds'][ $idx ] ?? null;
		$name = $feed['name'] ?? ( $feed['url'] ?? 'feed' );

		$msg = sprintf(
			/* translators: 1: feed name, 2: imported, 3: skipped, 4: errors */
			__( '%1$s — %2$d new, %3$d skipped, %4$d errors.', 'grid-index-rss-importer' ),
			$name, $result['imported'], $result['skipped'], $result['errors']
		);

		wp_safe_redirect( add_query_arg( array(
			'page'         => self::PAGE_SLUG,
			'gip_rss_msg'  => rawurlencode( $msg ),
			'gip_rss_type' => $result['errors'] ? 'error' : 'success',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Importer
	 * ------------------------------------------------------------------- */

	/**
	 * Run an import across all configured feeds.
	 *
	 * @param bool $manual Whether this is a manual run (affects logging only).
	 * @return array { imported:int, skipped:int, errors:int }
	 */
	public function run_import( $manual = false, $force_reimport_hours = 0, $only_feed_index = null ) {
		$s      = $this->get_settings();
		$log    = array();
		$totals = array( 'imported' => 0, 'skipped' => 0, 'errors' => 0 );

		// If force-reimport mode is on, propagate it to import_item via the
		// settings array. Items older than $force_reimport_hours fall back to
		// normal dup-skip behavior (handled inside import_item).
		if ( $force_reimport_hours > 0 ) {
			$s['_force_reimport']       = true;
			$s['_force_reimport_hours'] = (int) $force_reimport_hours;
		}

		// v1.0.13 — Mark manual single-feed fetches so fetch_one_feed bypasses
		// the error backoff. The user pressing "Fetch" on a row in the admin
		// is an explicit retry signal that should always honor.
		if ( $only_feed_index !== null && $manual ) {
			$s['_manual_single_feed'] = true;
		}

		$scope_label = '';
		if ( $only_feed_index !== null ) {
			$scope_label = ' (single feed)';
		} elseif ( $force_reimport_hours > 0 ) {
			$scope_label = sprintf( ' (force re-import last %dh)', $force_reimport_hours );
		}

		$log[] = sprintf( '[%s] %s import started%s.',
			current_time( 'mysql' ),
			$manual ? 'Manual' : 'Scheduled',
			$scope_label
		);

		if ( empty( $s['feeds'] ) ) {
			$log[] = '  No feeds configured.';
			$this->store_log( $log );
			return $totals;
		}

		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		// v1.0.26 — Build the list of feeds we'll actually iterate (honoring
		// $only_feed_index) so the progress UI can show "3 of N" against the
		// real iteration count, not the configured total.
		// v1.0.34 — For CRON runs (not manual), also filter by per-feed
		// interval: only fetch a feed if at least `interval` seconds have
		// passed since its last_fetched. Manual Import Now still hits every
		// feed; single-feed mode ($only_feed_index) also bypasses the check.
		$iter_indices  = array();
		$skipped_count = 0;
		$now           = time();
		foreach ( $s['feeds'] as $idx => $feed_cfg ) {
			if ( $only_feed_index !== null && (int) $idx !== (int) $only_feed_index ) continue;
			if ( empty( $feed_cfg['url'] ) ) continue;

			// Per-feed interval gating, cron-only.
			if ( ! $manual && $only_feed_index === null ) {
				$interval     = isset( $feed_cfg['interval'] ) && in_array( $feed_cfg['interval'], self::VALID_INTERVALS, true )
					? $feed_cfg['interval'] : self::DEFAULT_INTERVAL;
				$interval_sec = self::INTERVAL_SECONDS[ $interval ];
				$last         = (int) ( $feed_cfg['last_fetched'] ?? 0 );
				if ( $last > 0 && ( $now - $last ) < $interval_sec ) {
					$skipped_count++;
					continue; // Not due yet.
				}
			}
			$iter_indices[] = (int) $idx;
		}
		if ( $skipped_count > 0 ) {
			$log[] = sprintf( '(skipped %d feeds not yet due for their interval)', $skipped_count );
		}
		$total      = count( $iter_indices );
		$step       = 0;
		$started_at = time();

		foreach ( $iter_indices as $idx ) {
			$feed_cfg = $s['feeds'][ $idx ];
			$url      = $feed_cfg['url'];
			$step++;

			// Write progress BEFORE the (potentially slow) fetch starts, so
			// pollers see "Fetching 3 of 11 — TechCrunch" the moment we begin.
			$this->write_progress( array(
				'step'        => $step,
				'total'       => $total,
				'current'     => isset( $feed_cfg['name'] ) && $feed_cfg['name'] !== '' ? $feed_cfg['name'] : $url,
				'started_at'  => $started_at,
				'updated_at'  => time(),
				'state'       => 'running',
			) );

			$log[] = '';
			$log[] = '> Feed: ' . $url;

			$feed_totals = $this->fetch_one_feed( $idx, $feed_cfg, $s, $log );
			$totals['imported'] += $feed_totals['imported'];
			$totals['skipped']  += $feed_totals['skipped'];
			$totals['errors']   += $feed_totals['errors'];
		}

		// Mark the run complete so the last poll picks it up before the
		// page reload — but only briefly; the page reload after redirect
		// will reset the UI to idle.
		$this->write_progress( array(
			'step'       => $total,
			'total'      => $total,
			'current'    => '',
			'started_at' => $started_at,
			'updated_at' => time(),
			'state'      => 'done',
		) );
		// Clear shortly after; 5s is enough for the last poll to read 'done'.
		// We can't sleep here (we're still serving the request), so we just
		// set a short TTL on the 'done' record.
		set_transient( self::PROGRESS_TRANSIENT, get_transient( self::PROGRESS_TRANSIENT ), 10 );

		$log[] = '';
		$log[] = sprintf( '[%s] Done. %d new, %d skipped, %d errors.',
			current_time( 'mysql' ),
			$totals['imported'], $totals['skipped'], $totals['errors']
		);

		// v1.0.42 — If we actually imported anything, the dup summary may
		// have changed; clear its cache so the Feeds banner reflects reality.
		if ( $totals['imported'] > 0 ) {
			delete_transient( 'gip_rss_dup_summary' );
		}

		$this->store_log( $log );
		return $totals;
	}

	/**
	 * Fetch a single feed and import its items. Updates the feed's
	 * last_status/last_fetched fields so the admin UI can show per-feed
	 * health at a glance. Receives $log by reference so per-item log
	 * lines append to the run log alongside other feeds.
	 *
	 * @param int   $idx       Index in $settings['feeds'] for this feed.
	 * @param array $feed_cfg  Feed config: url, name.
	 * @param array $settings  Effective settings for this run.
	 * @param array &$log      Run log lines (by reference).
	 * @return array { imported:int, skipped:int, errors:int }
	 */
	/**
	 * SimplePie tuning callback. Attached to `wp_feed_options` only during
	 * our own fetches via fetch_one_feed(); detached immediately after.
	 *
	 * Applies:
	 *   - 15s timeout (WP default is 5s — too aggressive for cold edges).
	 *   - Real-looking User-Agent that identifies us honestly without
	 *     getting filtered as a bot by lazy WAF rules.
	 *   - Disable SimplePie's HTTP/file cache so we never read a stale
	 *     12-hour-old copy on a fresh import run.
	 *
	 * @param SimplePie $simplepie SimplePie instance to configure.
	 * @param string    $url       Feed URL being fetched.
	 */
	public function tune_simplepie_for_fetch( $simplepie, $url ) {
		if ( ! is_object( $simplepie ) ) return;

		// Timeout. SimplePie ≥1.5 has set_timeout(); guarded for safety.
		if ( method_exists( $simplepie, 'set_timeout' ) ) {
			$simplepie->set_timeout( self::FETCH_TIMEOUT_SECONDS );
		}

		// User-Agent. Identify the importer honestly (so well-behaved
		// publishers can recognize us in their logs) but lead with a
		// Mozilla-shaped string so simple WAF rules don't reject us.
		if ( method_exists( $simplepie, 'set_useragent' ) ) {
			$site_url = home_url( '/' );
			$ua       = sprintf(
				'Mozilla/5.0 (compatible; GridIndexRSS/%s; +%s)',
				defined( 'GRID_INDEX_RSS_IMPORTER_VERSION' ) ? GRID_INDEX_RSS_IMPORTER_VERSION : '1.0',
				esc_url_raw( $site_url )
			);
			$simplepie->set_useragent( $ua );
		}

		// Cache off. set_cache_duration(0) post-fetch (existing code) doesn't
		// help if SimplePie already served a stale cached copy on the way in,
		// so we disable the cache layer entirely for our own fetches.
		if ( method_exists( $simplepie, 'enable_cache' ) ) {
			$simplepie->enable_cache( false );
		}
	}

	/**
	 * Wrap fetch_feed() with one retry on transient failure. On a hard
	 * WP_Error (timeout, DNS, 5xx), wait a couple seconds and try once
	 * more before giving up. Doubles total request time only when the
	 * first attempt failed — successful fetches are unaffected.
	 *
	 * @param string $url   Feed URL.
	 * @param array  &$log  Run log lines (by reference) for retry annotation.
	 * @return SimplePie|WP_Error
	 */
	private function fetch_with_retry( $url, &$log ) {
		$first = fetch_feed( $url );
		if ( ! is_wp_error( $first ) ) {
			return $first;
		}

		$log[] = sprintf(
			'  RETRY: first attempt failed (%s), retrying in %ds…',
			$first->get_error_message(),
			self::FETCH_RETRY_DELAY_SECS
		);

		// SimplePie may have stashed a partial parse in WP's transient
		// cache for this URL; clear it so the retry actually re-fetches.
		// SimplePie's WP cache key is sha1 of the URL.
		delete_transient( 'feed_' . md5( $url ) );
		delete_transient( 'feed_mod_' . md5( $url ) );

		sleep( self::FETCH_RETRY_DELAY_SECS );
		return fetch_feed( $url );
	}

	private function fetch_one_feed( $idx, $feed_cfg, $settings, &$log ) {
		$totals = array( 'imported' => 0, 'skipped' => 0, 'errors' => 0 );
		$url    = $feed_cfg['url'] ?? '';

		// v1.0.13 — Error backoff. If this feed errored recently (default 10min),
		// skip it for this scheduled run so one bad feed doesn't eat timeout
		// budget on every cron tick. Manual single-feed fetches and force-reimport
		// runs bypass the backoff so the user can still retry on demand.
		$is_manual_single_feed   = ! empty( $settings['_manual_single_feed'] );
		$is_force_reimport       = ! empty( $settings['_force_reimport'] );
		$bypass_backoff          = $is_manual_single_feed || $is_force_reimport;

		if ( ! $bypass_backoff
			&& isset( $feed_cfg['last_status'] ) && $feed_cfg['last_status'] === 'error'
			&& isset( $feed_cfg['last_fetched'] )
			&& ( time() - (int) $feed_cfg['last_fetched'] ) < self::FETCH_ERROR_BACKOFF_SEC
		) {
			$secs_left = self::FETCH_ERROR_BACKOFF_SEC - ( time() - (int) $feed_cfg['last_fetched'] );
			$log[]     = sprintf(
				'  SKIP: in error-backoff (%ds left). Last error: %s',
				$secs_left,
				isset( $feed_cfg['last_message'] ) ? $feed_cfg['last_message'] : 'unknown'
			);
			// Don't count as an error for this run — it's a deliberate skip.
			return $totals;
		}

		// v1.0.13 — Attach SimplePie tuning for this fetch only. Detached in
		// the finally block below regardless of outcome.
		add_filter( 'wp_feed_options', array( $this, 'tune_simplepie_for_fetch' ), 10, 2 );

		try {
			$feed = $this->fetch_with_retry( $url, $log );
		} finally {
			remove_filter( 'wp_feed_options', array( $this, 'tune_simplepie_for_fetch' ), 10 );
		}

		if ( is_wp_error( $feed ) ) {
			$err_msg = $feed->get_error_message();
			$totals['errors']++;
			$log[] = '  ERROR: ' . $err_msg;
			$this->update_feed_status( $idx, array(
				'last_status'   => 'error',
				'last_message'  => $err_msg,
				'last_imported' => 0,
				'last_skipped'  => 0,
				'last_errors'   => 1,
				'last_fetched'  => time(),
			) );
			return $totals;
		}

		$feed->set_cache_duration( 0 );
		$max       = (int) $settings['max_per_run'];
		$max_items = $feed->get_item_quantity( $max );
		$items     = $feed->get_items( 0, $max_items );

		if ( ! $items ) {
			$log[] = '  (no items)';
			$this->update_feed_status( $idx, array(
				'last_status'   => 'empty',
				'last_message'  => 'no items in feed',
				'last_imported' => 0,
				'last_skipped'  => 0,
				'last_errors'   => 0,
				'last_fetched'  => time(),
			) );
			return $totals;
		}

		$source_name = $feed_cfg['name'] ?? '';
		if ( ! $source_name ) $source_name = $feed->get_title();
		if ( ! $source_name ) {
			$host        = wp_parse_url( $url, PHP_URL_HOST );
			$source_name = $host ? preg_replace( '/^www\./', '', $host ) : 'RSS';
		}

		foreach ( $items as $item ) {
			$result = $this->import_item( $item, $feed_cfg, $source_name, $settings );
			$log[]  = '  ' . $result['log'];
			if ( $result['status'] === 'imported' )    $totals['imported']++;
			elseif ( $result['status'] === 'skipped' ) $totals['skipped']++;
			else                                       $totals['errors']++;
		}

		$status = 'ok';
		if ( $totals['imported'] === 0 && $totals['skipped'] > 0 ) $status = 'all-dup';
		if ( $totals['errors'] > 0 && $totals['imported'] === 0 )  $status = 'error';
		$msg = sprintf( '%d new, %d skipped', $totals['imported'], $totals['skipped'] );

		$this->update_feed_status( $idx, array(
			'last_status'   => $status,
			'last_message'  => $msg,
			'last_imported' => (int) $totals['imported'],
			'last_skipped'  => (int) $totals['skipped'],
			'last_errors'   => (int) $totals['errors'],
			'last_fetched'  => time(),
		) );

		return $totals;
	}

	/**
	 * Persist per-feed status fields back into the settings option.
	 * Re-reads settings each time to avoid clobbering concurrent updates
	 * (e.g. if two feeds finish near-simultaneously when the runtime
	 * eventually parallelizes).
	 */
	private function update_feed_status( $idx, array $status ) {
		$s = $this->get_settings();
		if ( ! isset( $s['feeds'][ $idx ] ) || ! is_array( $s['feeds'][ $idx ] ) ) return;
		$s['feeds'][ $idx ] = array_merge( $s['feeds'][ $idx ], $status );
		$this->save_settings( $s );
	}

	private function store_log( array $log ) {
		$s             = $this->get_settings();
		$s['last_run'] = time();
		$s['last_log'] = implode( "\n", $log );
		$this->save_settings( $s );
	}

	/**
	 * v1.0.26 — Write the current import progress to a transient so the
	 * admin UI can poll for it via AJAX. Called once before each feed in
	 * the import loop.
	 */
	private function write_progress( array $state ) {
		set_transient( self::PROGRESS_TRANSIENT, $state, self::PROGRESS_TTL_SECS );
	}

	/**
	 * v1.0.26 — Read current progress. Returns null when no import is
	 * running (transient absent). Public so the AJAX handler can call it.
	 */
	public function read_progress() {
		$state = get_transient( self::PROGRESS_TRANSIENT );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Import a single feed item.
	 *
	 * @param SimplePie_Item $item
	 * @param array          $feed_cfg
	 * @param string         $source_name
	 * @param array          $settings
	 * @return array { status: 'imported'|'skipped'|'error', log: string }
	 */
	private function import_item( $item, $feed_cfg, $source_name, $settings ) {
		$title = trim( wp_strip_all_tags( (string) $item->get_title() ) );
		if ( ! $title ) {
			return array( 'status' => 'skipped', 'log' => 'skip: no title' );
		}

		// Build the canonical identifier: prefer GUID, fall back to permalink.
		$guid_raw = $item->get_id();
		if ( ! $guid_raw ) $guid_raw = $item->get_permalink();
		if ( ! $guid_raw ) $guid_raw = $title;
		$guid_hash = md5( (string) $guid_raw );

		// v1.0.38 — Dedup check via the persistent seen-GUIDs ledger.
		// This catches BOTH cases the old get_posts() lookup missed:
		//   1. Trashed posts (post_status='any' excluded 'trash', so trashed
		//      items would re-import on the next fetch — wrong).
		//   2. Permanently-deleted posts (no postmeta record exists, so the
		//      old query returned 0 results and treated the item as new).
		// Force-reimport intentionally STILL honors the ledger so users
		// who deliberately deleted posts don't see them re-imported.
		$already_seen = $this->has_seen_guid( $guid_hash );

		// Still look up live (non-trashed) post IDs because force-reimport
		// needs to delete existing copies before re-creating fresh ones.
		$existing = $already_seen ? get_posts( array(
			'post_type'        => 'post',
			'post_status'      => array( 'publish', 'draft', 'pending' ),
			'meta_key'         => self::META_GUID,
			'meta_value'       => $guid_hash,
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		) ) : array();

		// Force-reimport mode: also gated by item recency so we never re-fetch
		// the entire archive. Only items published in the last N hours qualify.
		$force_reimport = ! empty( $settings['_force_reimport'] );
		$item_age_hours = null;
		if ( $force_reimport ) {
			$item_date_gmt = $item->get_date( 'Y-m-d H:i:s' );
			if ( $item_date_gmt ) {
				$item_age_hours = ( time() - strtotime( $item_date_gmt . ' UTC' ) ) / 3600;
			}
			$max_age = isset( $settings['_force_reimport_hours'] ) ? (int) $settings['_force_reimport_hours'] : 24;
			if ( $item_age_hours === null || $item_age_hours > $max_age ) {
				// Too old for force-reimport scope; fall through to normal dup behavior.
				$force_reimport = false;
			}
		}

		if ( $already_seen ) {
			if ( $force_reimport && ! empty( $existing ) ) {
				// Delete the old copy (and its featured image attachment) so
				// we can re-import fresh with current settings.
				foreach ( $existing as $old_id ) {
					$old_thumb = get_post_thumbnail_id( $old_id );
					wp_delete_post( $old_id, true ); // true = bypass trash
					if ( $old_thumb ) {
						wp_delete_attachment( $old_thumb, true );
					}
				}
				// Continue past the dup check — fall through to import.
			} else {
				// v1.0.38 — Skip whether $existing exists or not.
				// Empty $existing here means the post was permanently deleted
				// but we still have the seen-ledger record. Skipping respects
				// the user's deletion.
				$reason = empty( $existing ) ? 'skip (previously deleted, ledger blocks re-import): '
				                              : 'skip (dup): ';
				return array( 'status' => 'skipped', 'log' => $reason . $title );
			}
		}

		$permalink = esc_url_raw( (string) $item->get_permalink() );
		$content   = (string) $item->get_content();
		if ( ! $content ) $content = (string) $item->get_description();
		$content = wp_kses_post( $content );

		// v1.0.14 — Date handling fix.
		//
		// SimplePie's get_date('Y-m-d H:i:s') returns the item's date string
		// without timezone info, which we were then assigning to BOTH
		// post_date and post_date_gmt. That confused wp_insert_post:
		//   - If the resulting "GMT" timestamp parsed as future relative to
		//     site time, WP would either reschedule the post (status='future')
		//     OR — when post_date_gmt is malformed/inconsistent with post_date —
		//     silently fall back to status='draft'.
		// Use get_date('U') to get an unambiguous Unix timestamp, clamp to
		// the present if the feed claims a future date (some feeds publish
		// items dated hours ahead — common with daily-edition publications
		// timestamped at midnight EST etc.), then format both fields from
		// that single source of truth.
		$item_unix = (int) $item->get_date( 'U' );
		$now_unix  = time();
		if ( $item_unix <= 0 ) {
			$item_unix = $now_unix;
		}
		// Clamp future-dated items to now so wp_insert_post never auto-demotes
		// 'publish' to 'future' (or, in malformed cases, 'draft').
		if ( $item_unix > $now_unix ) {
			$item_unix = $now_unix;
		}
		$date_gmt = gmdate( 'Y-m-d H:i:s', $item_unix );

		// IMAGE-REQUIRED PRE-CHECK: per v1.0.7 policy, posts with no
		// extractable image are skipped entirely — no empty thumbnails
		// in the Latest feed, no half-rendered cards on the homepage.
		$image_url = '';
		if ( $settings['image_mode'] === 'feed_first' ) {
			$image_url = $this->extract_feed_image( $item );
			if ( ! $image_url ) $image_url = $this->extract_content_image( $content );
		} elseif ( $settings['image_mode'] === 'content_first' ) {
			$image_url = $this->extract_content_image( $content );
		}
		// Only enforce the no-image skip when the user actually wants images.
		if ( $settings['image_mode'] !== 'none' && ! $image_url ) {
			return array( 'status' => 'skipped', 'log' => 'skip (no image): ' . $title );
		}

		// IMAGE-DIMENSION PRE-CHECK (v1.0.8): download the image to a temp
		// file BEFORE inserting the post and measure it. If smaller than the
		// configured minimum width, skip the post entirely. The temp file is
		// reused for sideload below if we proceed, so we never double-download.
		$tmp_image_path = '';
		if ( $image_url && $settings['image_mode'] !== 'none' ) {
			$min_w = isset( $settings['min_image_width'] ) ? (int) $settings['min_image_width'] : 1000;
			if ( $min_w > 0 ) {
				if ( ! function_exists( 'download_url' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$tmp = download_url( $image_url, 15 );
				if ( is_wp_error( $tmp ) ) {
					return array( 'status' => 'skipped', 'log' => 'skip (image download failed): ' . $title );
				}
				$dims = @getimagesize( $tmp );
				if ( ! $dims || empty( $dims[0] ) ) {
					@unlink( $tmp );
					return array( 'status' => 'skipped', 'log' => 'skip (image unreadable): ' . $title );
				}
				if ( (int) $dims[0] < $min_w ) {
					@unlink( $tmp );
					return array( 'status' => 'skipped', 'log' => sprintf( 'skip (image too small %dx%d): %s', (int) $dims[0], (int) $dims[1], $title ) );
				}
				// Passed — keep the temp file for sideload.
				$tmp_image_path = $tmp;
			}
		}

		// Every imported post goes into the dedicated "RSS" category.
		$rss_cat_id = self::ensure_rss_category();

		// v1.0.40 — Plus the granular category from the feed config (News,
		// World, Tech, Business, Science) if set. Posts get both terms so
		// theme code that queries Category:RSS keeps working AND users can
		// browse per-source.
		$cat_ids = array();
		if ( $rss_cat_id ) $cat_ids[] = $rss_cat_id;
		$feed_cat_key = isset( $feed_cfg['category'] ) ? (string) $feed_cfg['category'] : '';
		if ( $feed_cat_key !== '' && isset( self::GRANULAR_CATEGORIES[ $feed_cat_key ] ) ) {
			$granular_id = $this->ensure_granular_category( $feed_cat_key );
			if ( $granular_id ) $cat_ids[] = $granular_id;
		}

		$postarr = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_status'   => $settings['post_status'],
			'post_type'     => 'post',
			'post_date_gmt' => $date_gmt,
			'post_date'     => get_date_from_gmt( $date_gmt ),
		);
		if ( $cat_ids ) {
			$postarr['post_category'] = $cat_ids;
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			if ( $tmp_image_path ) @unlink( $tmp_image_path );
			return array( 'status' => 'error', 'log' => 'error: ' . $post_id->get_error_message() );
		}

		// v1.0.14 — Post-insert status verification. If the user wants
		// 'publish' but WordPress (or another plugin's wp_insert_post_data
		// filter) demoted us to 'draft' or 'future', re-assert. We've
		// already eliminated the most common cause of demotion (future
		// post_date_gmt — see the date-handling block above) but other
		// plugins on the site may still intervene, and this catches it.
		$intended_status = $settings['post_status'];
		$actual_status   = get_post_status( $post_id );
		if ( $intended_status === 'publish' && $actual_status !== 'publish' ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			) );
		}

		// Belt-and-braces: re-assert the category in case any save_post
		// hook from another plugin reset it to Uncategorized.
		if ( $cat_ids ) {
			wp_set_post_categories( $post_id, $cat_ids, false );
		}

		// Canonical Grid Index source meta — lights up theme attribution.
		if ( $permalink )    update_post_meta( $post_id, '_gridindex_source_url',  $permalink );
		if ( $source_name )  update_post_meta( $post_id, '_gridindex_source_name', $source_name );
		update_post_meta( $post_id, self::META_GUID, $guid_hash );

		// v1.0.38 — Also record this GUID in the persistent ledger so it
		// stays blocked from re-import even if this post is later
		// permanently deleted.
		$this->record_seen_guid( $guid_hash, $permalink ?: '' );

		// Sideload — reusing the pre-downloaded temp file when we have one
		// so we don't make a second HTTP request for the same image.
		if ( $tmp_image_path ) {
			$this->sideload_featured_image_from_tmp( $tmp_image_path, $image_url, $post_id, $title );
		} elseif ( $image_url ) {
			$this->sideload_featured_image( $image_url, $post_id, $title );
		}

		return array( 'status' => 'imported', 'log' => 'import: ' . $title );
	}

	private function extract_feed_image( $item ) {
		$enclosure = $item->get_enclosure();
		if ( $enclosure ) {
			$link = $enclosure->get_link();
			if ( $link && $this->looks_like_image( $link ) ) return esc_url_raw( $link );
			$thumb = method_exists( $enclosure, 'get_thumbnail' ) ? $enclosure->get_thumbnail() : '';
			if ( $thumb ) return esc_url_raw( $thumb );
		}
		$ns = 'http://search.yahoo.com/mrss/';
		foreach ( array( 'thumbnail', 'content' ) as $tag ) {
			$nodes = $item->get_item_tags( $ns, $tag );
			if ( $nodes && isset( $nodes[0]['attribs']['']['url'] ) ) {
				return esc_url_raw( $nodes[0]['attribs']['']['url'] );
			}
		}
		return '';
	}

	private function extract_content_image( $html ) {
		if ( ! $html ) return '';
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$url = $m[1];
			if ( $this->looks_like_image( $url ) ) return esc_url_raw( $url );
		}
		return '';
	}

	private function looks_like_image( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) return false;
		return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $path );
	}

	private function sideload_featured_image( $url, $post_id, $desc ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 15 );
		if ( is_wp_error( $tmp ) ) return false;

		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$attach_id = media_handle_sideload( $file_array, $post_id, $desc );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
			return false;
		}

		set_post_thumbnail( $post_id, $attach_id );
		return $attach_id;
	}

	/**
	 * Sideload a featured image from a temp file we already downloaded
	 * (used when we pre-downloaded for dimension checking). Avoids a
	 * duplicate HTTP request to the same URL.
	 */
	private function sideload_featured_image_from_tmp( $tmp_path, $original_url, $post_id, $desc ) {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$basename = basename( wp_parse_url( $original_url, PHP_URL_PATH ) );
		if ( ! $basename ) $basename = 'rss-image-' . $post_id . '.jpg';

		$file_array = array(
			'name'     => $basename,
			'tmp_name' => $tmp_path,
		);

		$attach_id = media_handle_sideload( $file_array, $post_id, $desc );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp_path );
			return false;
		}

		set_post_thumbnail( $post_id, $attach_id );
		return $attach_id;
	}
}

Grid_Index_RSS_Importer::instance();

// Activation hook — seed the curated starter feed list on first install.
register_activation_hook( __FILE__, array( 'Grid_Index_RSS_Importer', 'activate' ) );
