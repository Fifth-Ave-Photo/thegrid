<?php
/**
 * The Grid Index — Admin Toggle Polish
 *
 * Globally enqueues admin CSS that fixes the cramped ON/OFF pill toggles
 * across every Grid Index admin screen (Categories & order, Ticker,
 * Slider, Theme Options) — and any third-party screen using the same
 * toggle conventions.
 *
 * Apply: drop this file into the theme and add to functions.php:
 *   require_once get_template_directory() . '/inc/admin-toggle-polish.php';
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_enqueue_scripts', function () {
	$rel = '/assets/admin/admin-toggle.css';
	$abs = get_template_directory() . $rel;
	$ver = file_exists( $abs ) ? filemtime( $abs ) : '1.10.17';

	wp_enqueue_style(
		'gip-admin-toggle-polish',
		get_template_directory_uri() . $rel,
		array(),
		$ver
	);
}, 999 );
