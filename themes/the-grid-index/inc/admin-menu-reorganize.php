<?php
/**
 * The Grid Index — Admin menu organization (page migration approach).
 *
 * Previous versions tried to keep Theme Options/Ticker/Slider registered
 * under themes.php (Appearance) and force the sidebar highlight via
 * parent_file/submenu_file filters. That approach failed because
 * WordPress's menu render runs before our filters can intercept.
 *
 * This version takes the cleaner approach: unregister the existing
 * page handlers from themes.php, then re-register them under the
 * gridindex parent. The sidebar highlight follows naturally because
 * the URL is now genuinely under gridindex.
 *
 * URL changes:
 *   - Theme Options: themes.php?page=gridindex-theme-options
 *                  → admin.php?page=gridindex-theme-options
 *   - Ticker:        themes.php?page=gip-ticker
 *                  → admin.php?page=gip-ticker
 *   - Slider:        themes.php?page=gip-slider
 *                  → admin.php?page=gip-slider
 *
 * Old URLs redirect to the new ones (handled below) so any bookmarks
 * or links from this conversation still work.
 *
 * @package The_Grid_Index
 * @since   1.10.60
 */

defined( 'ABSPATH' ) || exit;

/**
 * Strip the old themes.php registrations and re-register under gridindex.
 *
 * Runs at priority 9999 to fire AFTER theme-options.php (which registers
 * via add_theme_page at default priority) and after layout-builder.php
 * (which registers the gridindex parent at priority 8).
 */
function gip_admin_menu_migrate() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	global $submenu;

	// Pull every gridindex-related entry out of the themes.php submenu
	// list so they no longer show or resolve under Appearance.
	if ( isset( $submenu['themes.php'] ) ) {
		foreach ( $submenu['themes.php'] as $key => $item ) {
			if ( ! isset( $item[2] ) ) continue;
			if ( in_array( $item[2], array(
				'gridindex-theme-options',
				'gip-ticker',
				'gip-slider',
			), true ) ) {
				unset( $submenu['themes.php'][ $key ] );
			}
		}
	}

	// Remove any stale "Theme Options" link entry from the gridindex
	// parent (registered in inc/layout-builder.php with the old URL).
	if ( isset( $submenu['gridindex'] ) ) {
		foreach ( $submenu['gridindex'] as $key => $item ) {
			if ( isset( $item[2] ) && $item[2] === 'themes.php?page=gridindex-theme-options' ) {
				unset( $submenu['gridindex'][ $key ] );
			}
		}
	}

	// Re-register Theme Options under gridindex parent. The callback
	// 'gridindex_render_options_page' is defined in inc/theme-options.php
	// and is callable from anywhere. Using the SAME slug as before
	// (gridindex-theme-options) so internal links keep working.
	if ( function_exists( 'gridindex_render_options_page' ) ) {
		add_submenu_page(
			'gridindex',
			__( 'Visual Settings', 'the-grid-index' ),
			__( 'Visual Settings', 'the-grid-index' ),
			'manage_options',
			'gridindex-theme-options',
			'gridindex_render_options_page'
		);
	}

	// Re-register Ticker under gridindex parent.
	if ( function_exists( 'gip_ticker_render_admin_page' ) ) {
		add_submenu_page(
			'gridindex',
			__( 'Breaking News Ticker', 'the-grid-index' ),
			__( 'Ticker', 'the-grid-index' ),
			'manage_options',
			'gip-ticker',
			'gip_ticker_render_admin_page'
		);
	}

	// Re-register Slider under gridindex parent.
	if ( function_exists( 'gip_slider_render_admin_page' ) ) {
		add_submenu_page(
			'gridindex',
			__( 'Homepage Featured Slider', 'the-grid-index' ),
			__( 'Slider', 'the-grid-index' ),
			'manage_options',
			'gip-slider',
			'gip_slider_render_admin_page'
		);
	}
}
add_action( 'admin_menu', 'gip_admin_menu_migrate', 9999 );

/**
 * Backwards-compat redirect: if anything links to the OLD themes.php
 * URLs, send users to the new admin.php URLs so they still land on
 * the right page.
 */
function gip_admin_menu_redirect_old_urls() {
	if ( ! is_admin() ) return;
	if ( ! isset( $_SERVER['SCRIPT_NAME'] ) ) return;

	$is_themes_page = strpos( $_SERVER['SCRIPT_NAME'], '/wp-admin/themes.php' ) !== false;
	if ( ! $is_themes_page ) return;

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! in_array( $page, array(
		'gridindex-theme-options',
		'gip-ticker',
		'gip-slider',
	), true ) ) {
		return;
	}

	$qs = $_GET;
	unset( $qs['page'] );
	$new_url = add_query_arg( array_merge( array( 'page' => $page ), $qs ), admin_url( 'admin.php' ) );
	wp_safe_redirect( $new_url );
	exit;
}
add_action( 'admin_init', 'gip_admin_menu_redirect_old_urls' );

/**
 * The Theme Options page enqueues its own CSS/JS in inc/theme-options.php
 * keyed to the hook returned by add_theme_page(). Since we re-registered
 * the page under the gridindex parent, the new hook suffix won't match
 * the original enqueue gate. Re-enqueue the same assets for the new hook.
 *
 * Same for the gi-options-host body class.
 */
function gip_visual_settings_assets( $hook ) {
	// Match against the slug we used in our re-registration (page=gridindex-theme-options).
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page !== 'gridindex-theme-options' ) return;

	wp_enqueue_media();
	wp_enqueue_script( 'jquery-ui-sortable' );

	$ver = defined( 'GIP_VERSION' ) ? GIP_VERSION : '1.0.0';
	$css_path = get_template_directory() . '/assets/admin/theme-options.css';
	$js_path  = get_template_directory() . '/assets/admin/theme-options.js';
	$ver_css  = file_exists( $css_path ) ? $ver . '.' . filemtime( $css_path ) : $ver;
	$ver_js   = file_exists( $js_path )  ? $ver . '.' . filemtime( $js_path )  : $ver;

	wp_enqueue_style(
		'gip-options',
		get_template_directory_uri() . '/assets/admin/theme-options.css',
		array(),
		$ver_css
	);
	wp_enqueue_script(
		'gip-options',
		get_template_directory_uri() . '/assets/admin/theme-options.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		$ver_js,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'gip_visual_settings_assets' );

/**
 * Add the gi-options-host body class for the new hook suffix so any
 * CSS scoped to it still applies.
 */
add_filter( 'admin_body_class', function( $classes ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page === 'gridindex-theme-options' ) {
		$classes .= ' gi-options-host';
	}
	return $classes;
} );
