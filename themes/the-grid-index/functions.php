<?php
/**
 * The Grid Index functions.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GIP_VERSION' ) ) {
	// Pull version from style.css header so it stays in sync with the
	// theme's actual version string. Falls back to a safe default if
	// wp_get_theme() can't resolve (very early bootstrap edge cases).
	$gip_theme_obj = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
	$gip_theme_ver = ( $gip_theme_obj && $gip_theme_obj->exists() ) ? $gip_theme_obj->get( 'Version' ) : '';
	define( 'GIP_VERSION', $gip_theme_ver ? $gip_theme_ver : '1.0.0' );
	unset( $gip_theme_obj, $gip_theme_ver );
}

if ( ! function_exists( 'gip_setup' ) ) :
	function gip_setup() {
		load_theme_textdomain( 'the-grid-index', get_template_directory() . '/languages' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/editor-style.css' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'custom-logo', array( 'height' => 64, 'width' => 240, 'flex-height' => true, 'flex-width' => true ) );
		add_theme_support( 'custom-line-height' );
		add_theme_support( 'custom-spacing' );
		add_theme_support( 'custom-units' );

		register_nav_menus( array(
			'primary' => esc_html__( 'Primary Menu', 'the-grid-index' ),
			'footer'  => esc_html__( 'Footer Menu', 'the-grid-index' ),
		) );

		add_image_size( 'gip-hero', 1600, 900, true );
		add_image_size( 'gip-card', 800, 500, true );
		add_image_size( 'gip-thumb', 400, 250, true );
	}
endif;
add_action( 'after_setup_theme', 'gip_setup' );

/**
 * Enqueue front-end assets.
 */
function gip_enqueue_assets() {
	// Cache-bust per release + per file mtime so LiteSpeed/Hostinger never serves stale CSS.
	$ver = GIP_VERSION;
	$css_path = get_template_directory() . '/assets/css/gridindex.css';
	if ( file_exists( $css_path ) ) {
		$ver = GIP_VERSION . '.' . filemtime( $css_path );
	}

	wp_enqueue_style( 'the-grid-index', get_stylesheet_uri(), array(), $ver );
	wp_enqueue_style(
		'the-grid-index-layout',
		get_template_directory_uri() . '/assets/css/gridindex.css',
		array( 'the-grid-index' ),
		$ver
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// Live Deck JS — load on the front page whenever a live deck might render.
	// `true` defers to footer so DOM is ready when the IIFE runs.
	if ( is_front_page() || is_home() ) {
		$js_path = get_template_directory() . '/assets/js/live-deck.js';
		$js_ver  = file_exists( $js_path ) ? GIP_VERSION . '.' . filemtime( $js_path ) : GIP_VERSION;
		wp_enqueue_script(
			'gip-live-deck',
			get_template_directory_uri() . '/assets/js/live-deck.js',
			array(),
			$js_ver,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gip_enqueue_assets' );

/**
 * Tag the body so our scoped CSS reliably applies on every front-end view.
 */
function gip_body_class( $classes ) {
	$classes[] = 'gridindex-theme';
	return $classes;
}
add_filter( 'body_class', 'gip_body_class' );

/**
 * Force the editorial PHP front-page template on the homepage.
 *
 * Some hosting environments (LiteSpeed cache, FSE bugs, plugin conflicts)
 * cause the FSE block templates to be skipped, dropping the site into a
 * raw index.php loop with no styling. This filter guarantees the editorial
 * homepage template is used whenever index/home is being rendered.
 */
function gip_force_front_template( $template ) {
	if ( is_admin() ) {
		return $template;
	}
	if ( is_front_page() || is_home() ) {
		$front = get_template_directory() . '/front-page.php';
		if ( file_exists( $front ) ) {
			return $front;
		}
	}
	return $template;
}
add_filter( 'template_include', 'gip_force_front_template', 99 );

/**
 * The Grid Index widget policy (v1.10.11):
 * - Homepage and article layouts are owned by Grid Index → Layout Builder
 *   and Grid Index Theme Options. Widgets are NOT part of the workflow.
 * - The Appearance → Widgets submenu is hidden by default.
 * - No widget areas are registered, so no default widgets render anywhere.
 * - Default core widgets (Recent Posts, Recent Comments, Archives, Categories,
 *   Meta, etc.) are unregistered as a belt-and-braces measure.
 */
function gip_widgets_init() {
	// Intentionally no register_sidebar() calls.
	// Footer / ad / newsletter slots are handled by Theme Options + templates.
}
add_action( 'widgets_init', 'gip_widgets_init' );

/** Unregister default core widgets — Grid Index does not use them. */
function gip_unregister_default_widgets() {
	$kill = array(
		'WP_Widget_Recent_Posts',
		'WP_Widget_Recent_Comments',
		'WP_Widget_Archives',
		'WP_Widget_Categories',
		'WP_Widget_Meta',
		'WP_Widget_Calendar',
		'WP_Widget_Pages',
		'WP_Widget_Tag_Cloud',
		'WP_Widget_RSS',
		'WP_Widget_Search',
		'WP_Widget_Links',
	);
	foreach ( $kill as $w ) {
		if ( class_exists( $w ) ) unregister_widget( $w );
	}
}
add_action( 'widgets_init', 'gip_unregister_default_widgets', 99 );

/** Hide Appearance → Widgets from the admin sidebar. */
add_action( 'admin_menu', function() {
	remove_submenu_page( 'themes.php', 'widgets.php' );
}, 999 );

/** Disable the block-editor widgets screen and Customizer widgets panel. */
add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
add_filter( 'use_widgets_block_editor', '__return_false' );
add_action( 'customize_register', function( $wp_customize ) {
	foreach ( array( 'widgets', 'sidebars_widgets' ) as $panel_id ) {
		if ( $wp_customize->get_panel( $panel_id ) ) $wp_customize->remove_panel( $panel_id );
	}
}, 20 );

/** If widgets.php is opened directly, show a Grid Index notice. */
add_action( 'admin_notices', function() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->id !== 'widgets' ) return;
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'The Grid Index', 'the-grid-index' ) . '</strong> ' .
		esc_html__( 'uses Theme Options and Layout Builder instead of classic widgets.', 'the-grid-index' ) .
		' <a href="' . esc_url( admin_url( 'themes.php?page=gridindex-theme-options' ) ) . '">' .
		esc_html__( 'Open Theme Options →', 'the-grid-index' ) . '</a></p></div>';
} );


/**
 * Safe detection of the optional Grid Index Control plugin.
 *
 * @return bool
 */
function gip_has_control_plugin() {
	return class_exists( '\\GridIndexControl\\Plugin' );
}

/**
 * Render fallback latest-posts grid when the plugin is not active.
 * Used by template parts via do_action( 'gip_render_homepage_feed' ).
 */
require_once get_template_directory() . '/inc/fallbacks.php';
require_once get_template_directory() . '/inc/plugin-recommendation.php';
require_once get_template_directory() . '/inc/onboarding.php';
require_once get_template_directory() . '/inc/block-patterns.php';
require_once get_template_directory() . '/inc/source-meta.php';
require_once get_template_directory() . '/inc/live-deck.php';
require_once get_template_directory() . '/inc/theme-options.php';
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/frontend-bridge.php';
require_once get_template_directory() . '/inc/layout-builder.php';
require_once get_template_directory() . '/inc/story-dossier.php';
require_once get_template_directory() . '/inc/site-chrome.php';

/* ── The Grid Index 1.10.12 → 1.10.17 patch modules ─────────────── */
$gip_patch_modules = array(
	'/inc/fallback-images.php',     // 1.10.16 image cleaner (replaces 1.10.13)
	'/inc/breaking-ticker.php',     // 1.10.14
	'/inc/featured-slider.php',     // 1.10.15
	'/inc/admin-toggle-polish.php', // 1.10.17
	'/inc/card-helpers.php',        // 1.10.18 — Top Stories thumbnails
	'/inc/archive-cards.php',       // 1.10.20 — Editorial card grid on category/tag/archive pages
	'/inc/single-polish.php',       // 1.10.20 — Hero image quality + comment-section CSS
	'/inc/aggregator-bridge.php',   // 1.10.24 — Map Aggregator/Feedzy meta keys to gip canonical keys
	'/inc/latest-thumb-fallback.php', // 1.10.25 — Branded fallback for empty Latest-feed thumbs
	'/inc/admin-dark-overlay.php',  // 1.10.26 — Dark editorial UI for Slider + Ticker admin pages
	'/inc/hero-quality.php',        // 1.10.28 — Slider hero quality + Regen tool + Hide imageless on homepage
	'/inc/homepage-dedup.php',      // 1.10.30 — One-post-per-homepage rule, no repeats across sections
	'/inc/logo-bridge.php',         // 1.10.32 — Bridge Theme Options logo into the WP custom_logo system
	'/inc/header-wordmark.php',     // 1.10.34 — Show wordmark text next to logo when configured
	'/inc/footer-credit.php',       // 1.10.37 — Developer credit link below footer
	'/inc/admin-bar-support.php',   // 1.10.40 — Support button in admin top bar
	'/inc/admin-page-background.php', // 1.10.45 — Dark canvas on Grid Index admin pages only
	'/inc/imported-truncation.php', // 1.10.42 — Trim RSS-imported article body to 3 paragraphs + CTA
	'/inc/comments-polish.php',     // 1.10.42 — Tighter comment form (no doubled border, smaller textarea)
	'/inc/admin-menu-reorganize.php', // 1.10.43 — Consolidate Grid Index admin pages under one parent
	'/inc/visual-settings-rename.php', // 1.10.57 — Rename "Theme Options" page heading to "Visual Settings"
	'/inc/knowledge-base.php',      // 1.10.63 — Knowledge Base / help docs page
	'/inc/meta-description.php',    // 1.10.71 — Meta description tag for SEO
);
foreach ( $gip_patch_modules as $gip_mod ) {
	$gip_path = get_template_directory() . $gip_mod;
	if ( file_exists( $gip_path ) ) { require_once $gip_path; }
}

/* Late admin_menu cleanup (1.10.12) — keep only one Grid Index entry */
add_action( 'admin_menu', function () {
	global $submenu;
	if ( isset( $submenu['themes.php'] ) ) {
		foreach ( $submenu['themes.php'] as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === 'gip-theme-options' ) {
				$submenu['themes.php'][ $key ][0] = 'Grid Index Options';
			}
		}
	}
	remove_menu_page( 'grid-index' );
}, 999 );

/* Enqueue branded fallback CSS (1.10.13) */
add_action( 'wp_enqueue_scripts', function () {
	$rel = '/assets/fallbacks/fallback.css';
	if ( file_exists( get_template_directory() . $rel ) ) {
		wp_enqueue_style(
			'gip-fallback-images',
			get_template_directory_uri() . $rel,
			array(),
			GIP_VERSION
		);
	}
}, 20 );
