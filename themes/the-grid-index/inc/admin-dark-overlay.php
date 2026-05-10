<?php
/**
 * The Grid Index — Slider & Ticker admin dark UI overlay.
 *
 * The slider (gip-slider) and ticker (gip-ticker) admin pages, registered
 * by inc/featured-slider.php and inc/breaking-ticker.php, render with
 * default WordPress admin chrome (light gray, .wrap, .form-table, .button).
 * Past attempts to edit those PHP files caused duplicate-function fatals,
 * so we leave them strictly alone and instead style their existing markup
 * via CSS only.
 *
 * This module:
 *   1. Detects when we're on the slider/ticker page
 *   2. Adds a body class so we can target without leaking styles elsewhere
 *   3. Enqueues an overlay stylesheet that paints the page dark editorial
 *
 * @package The_Grid_Index
 * @since   1.10.26
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tag the body on slider/ticker admin pages.
 *
 * Detection switched from hook-suffix to ?page= slug because the hook
 * suffix changes depending on which parent menu the page resolves
 * under (e.g. appearance_page_* vs gridindex_page_* vs admin_page_*),
 * and we want the overlay to apply regardless of parent.
 */
function gip_admin_dark_overlay_body_class( $classes ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$dark_pages = array( 'gip-slider', 'gip-ticker' );
	if ( in_array( $page, $dark_pages, true ) ) {
		$classes .= ' gip-dark-admin-page';
	}
	return $classes;
}
add_filter( 'admin_body_class', 'gip_admin_dark_overlay_body_class' );

/**
 * Enqueue the overlay stylesheet on slider/ticker pages.
 *
 * Same change: detect by ?page= slug rather than hook suffix so we
 * fire reliably regardless of where the menu item lives.
 */
function gip_admin_dark_overlay_enqueue( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$dark_pages = array( 'gip-slider', 'gip-ticker' );
	if ( ! in_array( $page, $dark_pages, true ) ) {
		return;
	}

	$css = '
	/* ============================================================
	 * Grid Index dark editorial overlay for Slider + Ticker admin
	 * Scoped to body.gip-dark-admin-page so it cannot leak into
	 * other wp-admin pages.
	 * ============================================================ */

	body.gip-dark-admin-page {
		--gi-bg:#0B0F14;
		--gi-surface:#111827;
		--gi-surface-2:#172033;
		--gi-card:#172033;
		--gi-card-soft:#1f2937;
		--gi-border:#334155;
		--gi-border-strong:#475569;
		--gi-text:#f8fafc;
		--gi-text-2:#e2e8f0;
		--gi-muted:#cbd5e1;
		--gi-faint:#94a3b8;
		--gi-accent:#14b8a6;
		--gi-accent-hover:#0d9488;
		--gi-accent-soft:rgba(20,184,166,.18);
		--gi-radius:12px;
		--gi-radius-sm:8px;
		background:var(--gi-bg) !important;
		color:var(--gi-text);
	}
	body.gip-dark-admin-page #wpcontent { background:var(--gi-bg); }
	body.gip-dark-admin-page #wpbody-content { background:var(--gi-bg); padding-bottom:80px; }

	/* Page wrap */
	body.gip-dark-admin-page .wrap {
		max-width:1200px; margin:0 auto; padding:32px 36px;
		color:var(--gi-text);
	}

	/* Headings */
	body.gip-dark-admin-page .wrap h1 {
		font:700 28px/1.15 Georgia,"Times New Roman",serif;
		color:var(--gi-text);
		margin:0 0 8px;
		letter-spacing:-.01em;
	}
	body.gip-dark-admin-page .wrap h2 {
		font:700 11.5px/1.3 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
		color:var(--gi-faint);
		text-transform:uppercase; letter-spacing:.16em;
		margin:24px 0 14px;
	}
	body.gip-dark-admin-page .wrap h3 {
		font:700 16px/1.3 Georgia,"Times New Roman",serif;
		color:var(--gi-text);
		margin:18px 0 10px;
	}

	/* Top descriptive paragraph */
	body.gip-dark-admin-page .wrap > p {
		color:var(--gi-muted);
		font-size:14px;
		max-width:780px;
		margin:0 0 24px;
	}

	/* Card-ify any direct-child div/section with notable content.
	   Slider/ticker render their content in plain .wrap divs and tables.
	   We treat tables, ordered/unordered lists, and form blocks as cards. */
	body.gip-dark-admin-page .wrap > div,
	body.gip-dark-admin-page .wrap > table,
	body.gip-dark-admin-page .wrap > ol,
	body.gip-dark-admin-page .wrap > ul {
		background:var(--gi-card);
		border:1px solid var(--gi-border);
		border-radius:var(--gi-radius);
		padding:20px 24px;
		margin:0 0 18px;
		box-shadow:0 1px 2px rgba(0,0,0,.4);
	}
	body.gip-dark-admin-page .wrap > ol,
	body.gip-dark-admin-page .wrap > ul {
		padding-left:44px;
	}

	/* Slider + Ticker pages render inner card divs with inline white
	   background. Override via high-specificity selectors plus important
	   so we win against the inline style attribute. */
	body.gip-dark-admin-page .card,
	body.gip-dark-admin-page .wrap .card {
		background:var(--gi-card-soft) !important;
		border:1px solid var(--gi-border) !important;
		border-radius:var(--gi-radius) !important;
		color:var(--gi-text-2) !important;
	}
	body.gip-dark-admin-page .card h2,
	body.gip-dark-admin-page .wrap .card h2 {
		color:var(--gi-faint) !important;
	}
	body.gip-dark-admin-page .card p,
	body.gip-dark-admin-page .card li,
	body.gip-dark-admin-page .card label,
	body.gip-dark-admin-page .card span,
	body.gip-dark-admin-page .card strong,
	body.gip-dark-admin-page .card div {
		color:var(--gi-text-2) !important;
	}
	body.gip-dark-admin-page .card strong { color:var(--gi-text) !important; }
	body.gip-dark-admin-page .card hr {
		border:0 !important;
		border-top:1px solid var(--gi-border) !important;
	}
	body.gip-dark-admin-page .card a {
		color:var(--gi-accent) !important;
	}
	body.gip-dark-admin-page .card a:hover {
		color:var(--gi-accent-hover) !important;
	}
	/* Inline-styled muted text on those pages (color:#64748b) — beat it */
	body.gip-dark-admin-page .card [style*="color:#64748b"],
	body.gip-dark-admin-page .card [style*="color: #64748b"] {
		color:var(--gi-faint) !important;
	}
	/* The h2 has color:#475569 inline — beat it */
	body.gip-dark-admin-page [style*="color:#475569"],
	body.gip-dark-admin-page [style*="color: #475569"] {
		color:var(--gi-faint) !important;
	}

	/* Lists */
	body.gip-dark-admin-page .wrap ol li,
	body.gip-dark-admin-page .wrap ul li {
		color:var(--gi-text-2);
		margin-bottom:10px;
		line-height:1.55;
	}
	body.gip-dark-admin-page .wrap ol li strong,
	body.gip-dark-admin-page .wrap ul li strong { color:var(--gi-text); }
	body.gip-dark-admin-page .wrap code {
		background:rgba(20,184,166,.12);
		color:var(--gi-accent);
		padding:1px 6px; border-radius:4px;
		font-size:12.5px;
	}

	/* Form table — make labels + fields editorial */
	body.gip-dark-admin-page .form-table {
		background:transparent;
		padding:0; box-shadow:none; border:0; border-radius:0;
	}
	body.gip-dark-admin-page .form-table th {
		color:var(--gi-text);
		font-weight:700;
		font-size:13.5px;
		padding:14px 16px 14px 0;
		width:200px;
	}
	body.gip-dark-admin-page .form-table td {
		padding:14px 0;
		color:var(--gi-text-2);
	}
	body.gip-dark-admin-page .form-table p.description {
		color:var(--gi-faint);
		font-size:12.5px;
		margin-top:6px;
	}

	/* Inputs */
	body.gip-dark-admin-page input[type="text"],
	body.gip-dark-admin-page input[type="url"],
	body.gip-dark-admin-page input[type="email"],
	body.gip-dark-admin-page input[type="number"],
	body.gip-dark-admin-page input[type="search"],
	body.gip-dark-admin-page select,
	body.gip-dark-admin-page textarea {
		background:#1f2937 !important;
		color:#f8fafc !important;
		border:1.5px solid var(--gi-border-strong) !important;
		border-radius:var(--gi-radius-sm) !important;
		padding:9px 12px !important;
		font-size:14px !important;
		box-shadow:none !important;
	}
	body.gip-dark-admin-page input[type="text"]:focus,
	body.gip-dark-admin-page input[type="url"]:focus,
	body.gip-dark-admin-page input[type="email"]:focus,
	body.gip-dark-admin-page input[type="number"]:focus,
	body.gip-dark-admin-page input[type="search"]:focus,
	body.gip-dark-admin-page select:focus,
	body.gip-dark-admin-page textarea:focus {
		outline:none !important;
		border-color:var(--gi-accent) !important;
		box-shadow:0 0 0 3px var(--gi-accent-soft) !important;
	}
	body.gip-dark-admin-page input::placeholder,
	body.gip-dark-admin-page textarea::placeholder { color:var(--gi-faint); }

	/* Checkboxes */
	body.gip-dark-admin-page input[type="checkbox"] {
		border-color:var(--gi-border-strong);
		background:#1f2937;
	}
	body.gip-dark-admin-page input[type="checkbox"]:checked {
		background:var(--gi-accent);
		border-color:var(--gi-accent);
	}
	body.gip-dark-admin-page input[type="checkbox"]:focus {
		box-shadow:0 0 0 3px var(--gi-accent-soft);
		border-color:var(--gi-accent);
	}

	/* Labels next to checkboxes in slider page */
	body.gip-dark-admin-page label { color:var(--gi-text-2); }

	/* Buttons — primary + secondary */
	body.gip-dark-admin-page .button,
	body.gip-dark-admin-page .button-secondary {
		background:transparent !important;
		color:var(--gi-text) !important;
		border:1.5px solid var(--gi-border-strong) !important;
		border-radius:var(--gi-radius-sm) !important;
		padding:8px 16px !important;
		font-weight:600 !important;
		font-size:13px !important;
		text-shadow:none !important;
		box-shadow:none !important;
		transition:background .15s, border-color .15s;
	}
	body.gip-dark-admin-page .button:hover,
	body.gip-dark-admin-page .button-secondary:hover {
		background:var(--gi-card-soft) !important;
		border-color:var(--gi-text-2) !important;
	}
	body.gip-dark-admin-page .button-primary {
		background:var(--gi-accent) !important;
		color:#0b0f14 !important;
		border:1.5px solid var(--gi-accent) !important;
		border-radius:var(--gi-radius-sm) !important;
		padding:8px 18px !important;
		font-weight:700 !important;
		text-shadow:none !important;
		box-shadow:none !important;
	}
	body.gip-dark-admin-page .button-primary:hover {
		background:var(--gi-accent-hover) !important;
		border-color:var(--gi-accent-hover) !important;
	}

	/* Links */
	body.gip-dark-admin-page .wrap a { color:var(--gi-accent); }
	body.gip-dark-admin-page .wrap a:hover { color:var(--gi-accent-hover); }

	/* WP notices */
	body.gip-dark-admin-page .notice {
		background:var(--gi-card-soft) !important;
		color:var(--gi-text) !important;
		border:1px solid var(--gi-border) !important;
		border-left-width:4px !important;
		border-radius:var(--gi-radius-sm) !important;
		box-shadow:none !important;
	}
	body.gip-dark-admin-page .notice-success { border-left-color:#10b981 !important; }
	body.gip-dark-admin-page .notice-warning { border-left-color:#f59e0b !important; }
	body.gip-dark-admin-page .notice-error   { border-left-color:#ef4444 !important; }
	body.gip-dark-admin-page .notice p { color:var(--gi-text-2); }

	/* Live preview lists / Currently pinned */
	body.gip-dark-admin-page .wrap ol li a,
	body.gip-dark-admin-page .wrap ul li a {
		color:var(--gi-accent);
		text-decoration:none;
	}
	body.gip-dark-admin-page .wrap ol li a:hover { text-decoration:underline; }

	/* Custom slides table on slider page */
	body.gip-dark-admin-page .widefat {
		background:var(--gi-card-soft) !important;
		border:1px solid var(--gi-border) !important;
		border-radius:var(--gi-radius-sm) !important;
		color:var(--gi-text-2) !important;
		box-shadow:none !important;
	}
	body.gip-dark-admin-page .widefat th,
	body.gip-dark-admin-page .widefat td {
		color:var(--gi-text-2) !important;
		border-color:var(--gi-border) !important;
	}
	body.gip-dark-admin-page .widefat thead th { color:var(--gi-text) !important; background:var(--gi-card) !important; }

	/* Live red dot toggle area */
	body.gip-dark-admin-page hr { border:0; border-top:1px solid var(--gi-border); margin:20px 0; }

	/* Constrain wide tables */
	body.gip-dark-admin-page table { color:var(--gi-text-2); }
	';

	wp_register_style( 'gip-dark-admin-overlay', false );
	wp_enqueue_style( 'gip-dark-admin-overlay' );
	wp_add_inline_style( 'gip-dark-admin-overlay', $css );
}
add_action( 'admin_enqueue_scripts', 'gip_admin_dark_overlay_enqueue' );
