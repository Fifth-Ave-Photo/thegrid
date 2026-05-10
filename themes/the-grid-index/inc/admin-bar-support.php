<?php
/**
 * The Grid Index — Admin Bar Support button.
 *
 * Adds a "Support" button to the WordPress admin top bar, visible on
 * every admin page (and on the front-end when the admin bar is shown
 * to logged-in users). Links to https://thegridindex.com/, opens in
 * a new tab, styled with a small lifebuoy icon and the theme's teal
 * accent so it reads as a deliberate brand element rather than a
 * stock WP nag.
 *
 * Anchored to the right side of the admin bar (parent: top-secondary)
 * so it sits near the "Howdy, user" block.
 *
 * @package The_Grid_Index
 * @since   1.10.40
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Support node on the admin bar.
 */
function gip_admin_bar_support_button( $wp_admin_bar ) {
	if ( ! is_admin_bar_showing() ) return;

	$wp_admin_bar->add_node( array(
		'id'     => 'gip-support',
		'parent' => 'top-secondary',
		'title'  => '<span class="ab-icon dashicons dashicons-editor-help" style="font-family:dashicons;line-height:32px;"></span><span class="ab-label">Support</span>',
		'href'   => 'https://thegridindex.com/',
		'meta'   => array(
			'target' => '_blank',
			'rel'    => 'noopener',
			'class'  => 'gip-support-link',
			'title'  => __( 'Get support and documentation at thegridindex.com', 'the-grid-index' ),
		),
	) );
}
add_action( 'admin_bar_menu', 'gip_admin_bar_support_button', 100 );

/**
 * Inline CSS so the Support button visually anchors to the theme's
 * teal accent rather than blending into the default admin bar.
 *
 * Registered through wp_add_inline_style on the admin-bar handle so
 * the styles are part of the asset queue (works with caching plugins
 * that minify CSS and complies with WP.org submission guidelines).
 */
function gip_admin_bar_support_styles() {
	if ( ! is_admin_bar_showing() ) return;

	$css = '
		#wpadminbar #wp-admin-bar-gip-support > .ab-item {
			color: #14B8A6 !important;
			font-weight: 600;
		}
		#wpadminbar #wp-admin-bar-gip-support > .ab-item .ab-icon {
			color: #14B8A6 !important;
		}
		#wpadminbar #wp-admin-bar-gip-support > .ab-item .ab-icon::before {
			content: "\f223" !important;
			color: #14B8A6 !important;
			top: 2px !important;
			font: 400 20px/1 dashicons !important;
			vertical-align: top;
		}
		#wpadminbar #wp-admin-bar-gip-support:hover > .ab-item,
		#wpadminbar #wp-admin-bar-gip-support > .ab-item:focus {
			background: rgba(20, 184, 166, 0.12) !important;
			color: #2dd4bf !important;
		}
		#wpadminbar #wp-admin-bar-gip-support:hover > .ab-item .ab-icon::before {
			color: #2dd4bf !important;
		}
	';

	// Attach to the admin-bar style handle which is always loaded when
	// the admin bar is showing (both front-end and back-end).
	wp_add_inline_style( 'admin-bar', $css );
}
add_action( 'admin_enqueue_scripts', 'gip_admin_bar_support_styles' );
add_action( 'wp_enqueue_scripts',    'gip_admin_bar_support_styles' );
