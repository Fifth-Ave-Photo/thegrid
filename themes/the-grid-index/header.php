<?php
/**
 * Header — Grid Index dark intelligence shell.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

$gi_mode = function_exists( 'gridindex_get_option' ) ? gridindex_get_option( 'frontend_design_mode', 'dark' ) : 'dark';
if ( ! in_array( $gi_mode, array( 'dark', 'light', 'system' ), true ) ) $gi_mode = 'dark';
$gi_body_class = 'gridindex-theme gi-fe-' . $gi_mode;
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-gi-mode="<?php echo esc_attr( $gi_mode ); ?>">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
</head>
<body <?php body_class( $gi_body_class ); ?>>
<?php wp_body_open(); ?>
<a id="top"></a>
<a class="skip-link screen-reader-text" href="#gi-main"><?php esc_html_e( 'Skip to content', 'the-grid-index' ); ?></a>
<?php
if ( function_exists( 'gip_render_site_header' ) ) {
	echo gip_render_site_header();
}
