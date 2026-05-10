<?php
/**
 * The Grid Index — WordPress Customizer integration
 *
 * Surfaces the Live Deck / Hero slider settings inside
 * Appearance → Customize so site owners can tweak the
 * homepage hero without leaving the native WP UI.
 *
 * All controls bind to the same `gridindex_theme_options`
 * option used by the full Theme Options screen, so changes
 * stay in sync across both UIs.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gip_customize_get' ) ) {
	/**
	 * Read a single key from the gridindex_theme_options array.
	 */
	function gip_customize_get( $key, $default = '' ) {
		if ( function_exists( 'gridindex_get_option' ) ) {
			$v = gridindex_get_option( $key, $default );
			return null === $v ? $default : $v;
		}
		$opts = get_option( 'gridindex_theme_options', array() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}
}

/**
 * Register the Grid Index panel + Live Deck section in the Customizer.
 */
function gip_customize_register( $wp_customize ) {

	// ---- Panel ----------------------------------------------------------
	$wp_customize->add_panel( 'gridindex_panel', array(
		'title'       => __( 'Grid Index', 'the-grid-index' ),
		'description' => __( 'Live newsroom controls for The Grid Index. Mirrors the full Theme Options screen.', 'the-grid-index' ),
		'priority'    => 30,
	) );

	// ---- Live Deck / Hero slider section --------------------------------
	$wp_customize->add_section( 'gridindex_live_deck', array(
		'title'       => __( 'Live Deck (Hero Slider)', 'the-grid-index' ),
		'description' => __( 'Cinematic homepage hero. Controls the active story, rotation, and signal stack.', 'the-grid-index' ),
		'panel'       => 'gridindex_panel',
		'priority'    => 10,
	) );

	$base   = 'gridindex_theme_options';
	$option = array(
		'type'              => 'option',
		'capability'        => 'manage_options',
		'transport'         => 'refresh',
		'sanitize_callback' => 'sanitize_text_field',
	);

	// Helper to add a setting that targets gridindex_theme_options[$key].
	// sanitize_callback is set explicitly at the call site (not just merged
	// from $option) so that static analyzers see it without resolving the
	// array_merge expression.
	$add = function ( $key, $args ) use ( $wp_customize, $base, $option ) {
		$merged = array_merge( $option, $args );
		if ( empty( $merged['sanitize_callback'] ) ) {
			$merged['sanitize_callback'] = 'sanitize_text_field';
		}
		$wp_customize->add_setting( $base . '[' . $key . ']', array(
			'type'              => $merged['type'],
			'capability'        => $merged['capability'],
			'transport'         => $merged['transport'],
			'default'           => isset( $merged['default'] ) ? $merged['default'] : '',
			'sanitize_callback' => $merged['sanitize_callback'],
		) );
	};

	// Hero layout
	$add( 'hero_layout', array(
		'default'           => 'live_deck',
		'sanitize_callback' => function ( $v ) {
			$ok = array( 'live_deck', 'lead', 'three', 'bloomberg' );
			return in_array( $v, $ok, true ) ? $v : 'live_deck';
		},
	) );
	$wp_customize->add_control( 'gridindex_hero_layout', array(
		'label'    => __( 'Hero layout', 'the-grid-index' ),
		'section'  => 'gridindex_live_deck',
		'settings' => $base . '[hero_layout]',
		'type'     => 'select',
		'choices'  => array(
			'live_deck' => __( 'Live Deck — cinematic active story + signal stack', 'the-grid-index' ),
			'lead'      => __( 'Lead — single hero card', 'the-grid-index' ),
			'three'     => __( '1 + 2 — lead + two secondaries', 'the-grid-index' ),
			'bloomberg' => __( 'Bloomberg — dense terminal grid', 'the-grid-index' ),
		),
	) );

	// Hero category
	$cats = array( 0 => __( 'Latest across all categories', 'the-grid-index' ) );
	foreach ( get_categories( array( 'hide_empty' => false ) ) as $c ) {
		$cats[ (int) $c->term_id ] = $c->name;
	}
	$add( 'hero_category', array(
		'default'           => 0,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'gridindex_hero_category', array(
		'label'    => __( 'Source category', 'the-grid-index' ),
		'section'  => 'gridindex_live_deck',
		'settings' => $base . '[hero_category]',
		'type'     => 'select',
		'choices'  => $cats,
	) );

	// Hero slide count
	$add( 'hero_count', array(
		'default'           => 5,
		'sanitize_callback' => function ( $v ) { return max( 3, min( 10, (int) $v ) ); },
	) );
	$wp_customize->add_control( 'gridindex_hero_count', array(
		'label'       => __( 'Number of slides', 'the-grid-index' ),
		'description' => __( 'Between 3 and 10.', 'the-grid-index' ),
		'section'     => 'gridindex_live_deck',
		'settings'    => $base . '[hero_count]',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 3, 'max' => 10, 'step' => 1 ),
	) );

	// Autoplay
	$add( 'hero_autoplay', array(
		'default'           => 0,
		'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
	) );
	$wp_customize->add_control( 'gridindex_hero_autoplay', array(
		'label'    => __( 'Autoplay rotation', 'the-grid-index' ),
		'section'  => 'gridindex_live_deck',
		'settings' => $base . '[hero_autoplay]',
		'type'     => 'checkbox',
	) );

	// Rotation interval
	$add( 'hero_rotation', array(
		'default'           => 7000,
		'sanitize_callback' => function ( $v ) { return max( 3000, min( 30000, (int) $v ) ); },
	) );
	$wp_customize->add_control( 'gridindex_hero_rotation', array(
		'label'       => __( 'Rotation interval (ms)', 'the-grid-index' ),
		'description' => __( '3000–30000. Only used when autoplay is on.', 'the-grid-index' ),
		'section'     => 'gridindex_live_deck',
		'settings'    => $base . '[hero_rotation]',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 3000, 'max' => 30000, 'step' => 500 ),
	) );

	// Show momentum badge
	$add( 'hero_show_momentum', array(
		'default'           => 1,
		'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
	) );
	$wp_customize->add_control( 'gridindex_hero_show_momentum', array(
		'label'    => __( 'Show momentum badge', 'the-grid-index' ),
		'section'  => 'gridindex_live_deck',
		'settings' => $base . '[hero_show_momentum]',
		'type'     => 'checkbox',
	) );

	// Show source count
	$add( 'hero_show_count', array(
		'default'           => 1,
		'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
	) );
	$wp_customize->add_control( 'gridindex_hero_show_count', array(
		'label'    => __( 'Show source count badge', 'the-grid-index' ),
		'section'  => 'gridindex_live_deck',
		'settings' => $base . '[hero_show_count]',
		'type'     => 'checkbox',
	) );

	// Ticker
	$add( 'enable_ticker', array(
		'default'           => 1,
		'sanitize_callback' => function ( $v ) { return $v ? 1 : 0; },
	) );
	$wp_customize->add_control( 'gridindex_enable_ticker', array(
		'label'    => __( 'Enable headline ticker', 'the-grid-index' ),
		'section'  => 'gridindex_live_deck',
		'settings' => $base . '[enable_ticker]',
		'type'     => 'checkbox',
	) );

	// ---- Helpful link to full Theme Options page ------------------------
	$wp_customize->add_setting( 'gridindex_full_options_link', array(
		'sanitize_callback' => '__return_null',
		'capability'        => 'manage_options',
	) );
	if ( class_exists( 'WP_Customize_Control' ) ) {
		$wp_customize->add_control( new WP_Customize_Control( $wp_customize, 'gridindex_full_options_link', array(
			'section'     => 'gridindex_live_deck',
			'settings'    => 'gridindex_full_options_link',
			'type'        => 'hidden',
			'description' => sprintf(
				/* translators: %s: URL */
				__( 'Need cards, footer, or RSS controls? Open the <a href="%s">full Grid Index Theme Options</a>.', 'the-grid-index' ),
				esc_url( admin_url( 'themes.php?page=gridindex-theme-options' ) )
			),
		) ) );
	}
}
add_action( 'customize_register', 'gip_customize_register' );

/**
 * Bump the saved-at timestamp whenever the Customizer saves so any
 * cache-busting tied to gridindex_theme_options_saved_at picks it up.
 */
function gip_customize_save_after() {
	update_option( 'gridindex_theme_options_saved_at', time() );
	// Bump cache-buster so any front-end caches (LiteSpeed, browser) drop stale homepage HTML/CSS.
	update_option( 'gridindex_cache_buster_auto', (string) time() );
	// Best-effort page-cache purges.
	if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
	if ( function_exists( 'litespeed_purge_all' ) ) litespeed_purge_all();
	do_action( 'litespeed_purge_all' );
	do_action( 'gridindex_options_saved' );
}
add_action( 'customize_save_after', 'gip_customize_save_after' );
