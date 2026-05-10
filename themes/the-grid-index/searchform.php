<?php
/**
 * Search form template.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

$gip_search_id = 'gip-searchform-' . wp_unique_id();
?>
<form role="search" method="get" class="gi-mast__searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $gip_search_id ); ?>"><?php esc_html_e( 'Search for:', 'the-grid-index' ); ?></label>
	<input type="search"
	       id="<?php echo esc_attr( $gip_search_id ); ?>"
	       name="s"
	       placeholder="<?php esc_attr_e( 'Search The Grid Index…', 'the-grid-index' ); ?>"
	       value="<?php echo esc_attr( get_search_query() ); ?>" />
	<button type="submit"><?php esc_html_e( 'Search', 'the-grid-index' ); ?></button>
</form>
