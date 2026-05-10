<?php
/**
 * The Grid Index — Footer developer credit.
 *
 * Adds a small "Theme by Grid Index" credit line below the existing
 * footer. Uses wp_footer (which fires after the closing </footer> tag
 * in most layouts) so we don't have to edit inc/site-chrome.php.
 *
 * @package The_Grid_Index
 * @since   1.10.37
 */

defined( 'ABSPATH' ) || exit;

function gip_footer_credit_enqueue() {
	if ( is_admin() ) return;
	$css = '
		.gip-footer-credit {
			max-width: var(--gi-max, 1280px);
			margin: 0 auto;
			padding: 14px 28px 28px;
			text-align: center;
			font-size: 11.5px;
			letter-spacing: .08em;
			text-transform: uppercase;
			color: var(--gi-text-dim, #94a3b8);
			border-top: 1px solid var(--gi-rule, rgba(255,255,255,.06));
		}
		.gip-footer-credit a {
			color: var(--gi-text-dim, #94a3b8);
			text-decoration: none;
			font-weight: 600;
			margin-left: 4px;
			transition: color .15s ease;
		}
		.gip-footer-credit a:hover {
			color: var(--gi-accent, #14b8a6);
		}
		.gip-footer-credit__dot {
			color: var(--gi-accent, #14b8a6);
		}
	';
	wp_add_inline_style( 'the-grid-index', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_footer_credit_enqueue', 20 );

function gip_footer_credit_render() {
	if ( is_admin() ) return;
	?>
	<div class="gip-footer-credit" aria-hidden="false">
		<span><?php esc_html_e( 'Theme by', 'the-grid-index' ); ?></span>
		<a href="https://thegridindex.com" rel="noopener" target="_blank">Grid Index<span class="gip-footer-credit__dot">.</span></a>
	</div>
	<?php
}
add_action( 'wp_footer', 'gip_footer_credit_render', 99 );
