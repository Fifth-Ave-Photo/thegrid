<?php
/**
 * Grid Index Theme Options — native Settings API admin page.
 *
 * Lives under Appearance → Grid Index Theme Options.
 * Presentation-only. RSS, AI, cron, and source health belong in the
 * Grid Index Control plugin — never here.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * Defaults + helper
 * ============================================================ */

if ( ! function_exists( 'gridindex_get_default_options' ) ) :
function gridindex_get_default_options() {
	return array(
		// General
		'frontend_design_mode' => 'dark',          // dark|light|system — Grid Index Dark Intelligence is default
		'color_mode'         => 'dark',            // dark|light|system (admin badge)
		'accent_color'       => '#14B8A6',
		'editorial_density'  => 'comfortable',     // compact|comfortable
		'site_width'         => 'full',            // full|boxed
		'sticky_header'      => 1,

		// Header
		'logo_id'            => 0,
		'logo_id_retina'     => 0,
		'wordmark'           => '',
		'tagline'            => '',
		'header_style'       => 'classic',         // classic|terminal|magazine
		'menu_alignment'     => 'left',            // left|center|right
		'show_search'        => 1,
		'show_date_strip'    => 1,
		'mobile_menu_style'  => 'drawer',          // drawer|fullscreen|dropdown

		// Design (typography + surfaces)
		'font_heading'       => 'serif-editorial', // serif-editorial|sans-modern|mono-terminal
		'font_body'          => 'sans-modern',     // serif-editorial|sans-modern|mono-terminal
		'bg_color'           => '',                // hex; empty = use mode default
		'card_color'         => '',                // hex; empty = use mode default
		'border_style'       => 'subtle',          // subtle|hairline|bold|none

		// Cards (extended)
		'card_image_ratio'   => '16x9',            // 16x9|4x3|1x1|3x2|portrait
		'card_show_category' => 1,

		// Archive / Category pages
		'archive_layout'         => 'intelligence', // intelligence|grid|list
		'archive_posts_per_page' => 12,
		'archive_show_hero'      => 1,
		'archive_show_rail'      => 1,
		'archive_show_filters'   => 1,
		'archive_pagination_style' => 'numbered',   // numbered|loadmore|prevnext

		// Performance / Advanced (extended)
		'debug_mode'         => 0,

		// Homepage
		'hero_layout'        => 'live_deck',       // live_deck|lead|three|bloomberg
		'hero_category'      => 0,                 // term_id (0 = latest)
		'hero_count'         => 5,
		'hero_autoplay'      => 0,
		'hero_rotation'      => 7000,              // ms
		'hero_show_momentum' => 1,
		'hero_show_count'    => 1,
		'enable_ticker'      => 1,
		'enable_latest_rail' => 1,
		'enable_cat_bands'   => 1,
		'hide_empty'         => 1,
		'home_categories'    => array(),           // array of term_ids
		'section_order'      => 'hero,ticker,latest,bands',

		// Cards
		'card_style'         => 'editorial',       // minimal|editorial|signal
		'card_show_source'   => 1,
		'card_show_logo'     => 1,
		'card_show_date'     => 1,
		'card_excerpt_len'   => 28,
		'card_show_read_src' => 1,
		'card_image_fallback'=> 'gradient',        // gradient|topic-color|plain

		// Article
		'article_attribution'=> 1,
		'article_related'    => 1,
		'article_author_date'=> 1,
		'article_taxonomy'   => 1,
		'article_orig_btn'   => 1,
		'article_sidebar'    => 'right',           // none|right|left

		// Source attribution / RSS behavior
		'article_click_behavior' => 'source',      // source | internal | internal_cta
		'card_show_source_cta'   => 1,             // show "Read at [Source]" on cards
		'single_show_source_cta' => 1,             // show prominent CTA on single posts
		'hide_rss_comments'      => 1,             // disable comments on imported RSS posts

		// Footer
		'footer_layout'      => 'columns',         // simple|columns|magazine
		'footer_text'        => '',
		'footer_social'      => array(
			'twitter'   => '',
			'facebook'  => '',
			'linkedin'  => '',
			'youtube'   => '',
			'rss'       => '',
		),
		'footer_newsletter'  => 0,
		'footer_copyright'   => '',

		// Ads
		'ads_enabled'        => 0,
		'ad_header'          => '',
		'ad_in_feed'         => '',
		'ad_sidebar'         => '',
		'ad_article_body'    => '',

		// Advanced
		'custom_css'         => '',
		'disable_animations' => 0,
		'lazy_loading'       => 1,
		'cache_buster'       => '',

		// Homepage Sections (managed via dedicated tab)
		'home_sections_enabled' => 1,
		'home_section_cats'     => array(),  // array of term_ids — drives display + order
		'home_posts_per_cat'    => 6,
		'home_hide_empty'       => 1,
		'home_show_dashboard'   => 1,
		'home_section_layout'   => 'grid',   // large-card | grid | compact-list
		'home_title_overrides'  => array(),  // term_id => string

		// Advanced — exclusion
		'exclude_uncategorized' => 1,        // exclude WP default "Uncategorized" from public homepage
	);
}
endif;

if ( ! function_exists( 'gridindex_get_option' ) ) :
/**
 * Helper: get a single Grid Index theme option with default fallback.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function gridindex_get_option( $key, $default = null ) {
	static $cache = null;
	if ( null === $cache ) {
		$defaults = gridindex_get_default_options();
		$saved    = get_option( 'gridindex_theme_options', array() );
		if ( ! is_array( $saved ) ) $saved = array();
		$cache = array_merge( $defaults, $saved );
	}
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}
	return $default;
}
endif;

/* ============================================================
 * Register option + sanitize
 * ============================================================ */

function gridindex_register_settings() {
	register_setting(
		'gridindex_theme_options_group',
		'gridindex_theme_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'gridindex_sanitize_options',
			'default'           => gridindex_get_default_options(),
		)
	);
}
add_action( 'admin_init', 'gridindex_register_settings' );

function gridindex_sanitize_options( $input ) {
	update_option( 'gridindex_theme_options_saved_at', time(), false );
	$d   = gridindex_get_default_options();
	$out = $d;

	if ( ! is_array( $input ) ) return $out;

	$choice = function( $v, $allowed, $fallback ) {
		return in_array( $v, $allowed, true ) ? $v : $fallback;
	};
	$bool = function( $v ) { return ! empty( $v ) ? 1 : 0; };

	// General
	$out['frontend_design_mode'] = $choice( $input['frontend_design_mode'] ?? '', array( 'dark', 'light', 'system' ), $d['frontend_design_mode'] );
	$out['color_mode']        = $choice( $input['color_mode'] ?? '', array( 'dark', 'light', 'system' ), $d['color_mode'] );
	$out['accent_color']      = sanitize_hex_color( $input['accent_color'] ?? '' ) ?: $d['accent_color'];
	$out['editorial_density'] = $choice( $input['editorial_density'] ?? '', array( 'compact', 'comfortable' ), $d['editorial_density'] );
	$out['site_width']        = $choice( $input['site_width'] ?? '', array( 'full', 'boxed' ), $d['site_width'] );
	$out['sticky_header']     = $bool( $input['sticky_header'] ?? 0 );

	// Header
	$out['logo_id']           = absint( $input['logo_id'] ?? 0 );
	$out['logo_id_retina']    = absint( $input['logo_id_retina'] ?? 0 );
	$out['wordmark']          = sanitize_text_field( $input['wordmark'] ?? '' );
	$out['tagline']           = sanitize_text_field( $input['tagline'] ?? '' );
	$out['header_style']      = $choice( $input['header_style'] ?? '', array( 'classic', 'terminal', 'magazine' ), $d['header_style'] );
	$out['menu_alignment']    = $choice( $input['menu_alignment'] ?? '', array( 'left', 'center', 'right' ), $d['menu_alignment'] );
	$out['show_search']       = $bool( $input['show_search'] ?? 0 );
	$out['show_date_strip']   = $bool( $input['show_date_strip'] ?? 0 );
	$out['mobile_menu_style'] = $choice( $input['mobile_menu_style'] ?? '', array( 'drawer', 'fullscreen', 'dropdown' ), $d['mobile_menu_style'] );

	// Design
	$out['font_heading']      = $choice( $input['font_heading'] ?? '', array( 'serif-editorial', 'sans-modern', 'mono-terminal' ), $d['font_heading'] );
	$out['font_body']         = $choice( $input['font_body'] ?? '', array( 'serif-editorial', 'sans-modern', 'mono-terminal' ), $d['font_body'] );
	$out['bg_color']          = sanitize_hex_color( $input['bg_color'] ?? '' ) ?: '';
	$out['card_color']        = sanitize_hex_color( $input['card_color'] ?? '' ) ?: '';
	$out['border_style']      = $choice( $input['border_style'] ?? '', array( 'subtle', 'hairline', 'bold', 'none' ), $d['border_style'] );

	// Homepage
	$out['hero_layout']        = $choice( $input['hero_layout'] ?? '', array( 'live_deck', 'lead', 'three', 'bloomberg' ), $d['hero_layout'] );
	$out['hero_category']      = absint( $input['hero_category'] ?? 0 );
	$out['hero_count']         = max( 1, min( 12, absint( $input['hero_count'] ?? 5 ) ) );
	$out['hero_autoplay']      = $bool( $input['hero_autoplay'] ?? 0 );
	$out['hero_rotation']      = max( 3000, min( 30000, absint( $input['hero_rotation'] ?? 7000 ) ) );
	$out['hero_show_momentum'] = $bool( $input['hero_show_momentum'] ?? 0 );
	$out['hero_show_count']    = $bool( $input['hero_show_count'] ?? 0 );
	$out['enable_ticker']      = $bool( $input['enable_ticker'] ?? 0 );
	$out['enable_latest_rail'] = $bool( $input['enable_latest_rail'] ?? 0 );
	$out['enable_cat_bands']   = $bool( $input['enable_cat_bands'] ?? 0 );
	$out['hide_empty']         = $bool( $input['hide_empty'] ?? 0 );
	$out['home_categories']    = array_values( array_filter( array_map( 'absint', (array) ( $input['home_categories'] ?? array() ) ) ) );
	$order_raw = sanitize_text_field( $input['section_order'] ?? '' );
	$order     = array_filter( array_map( 'trim', explode( ',', $order_raw ) ), function( $v ) {
		return in_array( $v, array( 'hero', 'ticker', 'latest', 'bands' ), true );
	} );
	$out['section_order'] = $order ? implode( ',', $order ) : $d['section_order'];

	// Cards
	$out['card_style']         = $choice( $input['card_style'] ?? '', array( 'minimal', 'editorial', 'signal' ), $d['card_style'] );
	$out['card_show_source']   = $bool( $input['card_show_source'] ?? 0 );
	$out['card_show_logo']     = $bool( $input['card_show_logo'] ?? 0 );
	$out['card_show_date']     = $bool( $input['card_show_date'] ?? 0 );
	$out['card_show_category'] = $bool( $input['card_show_category'] ?? 0 );
	$out['card_excerpt_len']   = max( 0, min( 80, absint( $input['card_excerpt_len'] ?? 28 ) ) );
	$out['card_show_read_src'] = $bool( $input['card_show_read_src'] ?? 0 );
	$out['card_image_fallback']= $choice( $input['card_image_fallback'] ?? '', array( 'gradient', 'topic-color', 'plain' ), $d['card_image_fallback'] );
	$out['card_image_ratio']   = $choice( $input['card_image_ratio'] ?? '', array( '16x9', '4x3', '1x1', '3x2', 'portrait' ), $d['card_image_ratio'] );

	// Archive
	$out['archive_layout']         = $choice( $input['archive_layout'] ?? '', array( 'intelligence', 'grid', 'list' ), $d['archive_layout'] );
	$out['archive_posts_per_page'] = max( 3, min( 60, absint( $input['archive_posts_per_page'] ?? 12 ) ) );
	$out['archive_show_hero']      = $bool( $input['archive_show_hero'] ?? 0 );
	$out['archive_show_rail']      = $bool( $input['archive_show_rail'] ?? 0 );
	$out['archive_show_filters']   = $bool( $input['archive_show_filters'] ?? 0 );
	$out['archive_pagination_style'] = $choice( $input['archive_pagination_style'] ?? '', array( 'numbered', 'loadmore', 'prevnext' ), $d['archive_pagination_style'] );

	// Article
	$out['article_attribution'] = $bool( $input['article_attribution'] ?? 0 );
	$out['article_related']     = $bool( $input['article_related'] ?? 0 );
	$out['article_author_date'] = $bool( $input['article_author_date'] ?? 0 );
	$out['article_taxonomy']    = $bool( $input['article_taxonomy'] ?? 0 );
	$out['article_orig_btn']    = $bool( $input['article_orig_btn'] ?? 0 );
	$out['article_sidebar']     = $choice( $input['article_sidebar'] ?? '', array( 'none', 'right', 'left' ), $d['article_sidebar'] );
	$out['article_click_behavior'] = $choice( $input['article_click_behavior'] ?? '', array( 'source', 'internal', 'internal_cta' ), $d['article_click_behavior'] );
	$out['card_show_source_cta']   = $bool( $input['card_show_source_cta'] ?? 0 );
	$out['single_show_source_cta'] = $bool( $input['single_show_source_cta'] ?? 0 );
	$out['hide_rss_comments']      = $bool( $input['hide_rss_comments'] ?? 0 );

	// Footer
	$out['footer_layout']     = $choice( $input['footer_layout'] ?? '', array( 'simple', 'columns', 'magazine' ), $d['footer_layout'] );
	$out['footer_text']       = sanitize_text_field( $input['footer_text'] ?? '' );
	$social = array();
	foreach ( array( 'twitter', 'facebook', 'linkedin', 'youtube', 'rss' ) as $net ) {
		$social[ $net ] = esc_url_raw( $input['footer_social'][ $net ] ?? '' );
	}
	$out['footer_social']     = $social;
	$out['footer_newsletter'] = $bool( $input['footer_newsletter'] ?? 0 );
	$out['footer_copyright']  = sanitize_text_field( $input['footer_copyright'] ?? '' );

	// Ads — preserve safe HTML
	$out['ads_enabled']     = $bool( $input['ads_enabled'] ?? 0 );
	$out['ad_header']       = wp_kses_post( $input['ad_header'] ?? '' );
	$out['ad_in_feed']      = wp_kses_post( $input['ad_in_feed'] ?? '' );
	$out['ad_sidebar']      = wp_kses_post( $input['ad_sidebar'] ?? '' );
	$out['ad_article_body'] = wp_kses_post( $input['ad_article_body'] ?? '' );

	// Advanced
	$out['custom_css']         = wp_strip_all_tags( $input['custom_css'] ?? '' );
	$out['disable_animations'] = $bool( $input['disable_animations'] ?? 0 );
	$out['lazy_loading']       = $bool( $input['lazy_loading'] ?? 0 );
	$out['cache_buster']       = sanitize_text_field( $input['cache_buster'] ?? '' );
	$out['exclude_uncategorized'] = $bool( $input['exclude_uncategorized'] ?? 0 );
	$out['debug_mode']         = $bool( $input['debug_mode'] ?? 0 );

	// Homepage Sections
	$out['home_sections_enabled'] = $bool( $input['home_sections_enabled'] ?? 0 );
	$cats = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['home_section_cats'] ?? array() ) ) ) ) );
	$out['home_section_cats']     = $cats;
	$out['home_posts_per_cat']    = max( 1, min( 24, absint( $input['home_posts_per_cat'] ?? 6 ) ) );
	$out['home_hide_empty']       = $bool( $input['home_hide_empty'] ?? 0 );
	$out['home_show_dashboard']   = $bool( $input['home_show_dashboard'] ?? 0 );
	$out['home_section_layout']   = $choice( $input['home_section_layout'] ?? '', array( 'large-card', 'grid', 'compact-list' ), $d['home_section_layout'] );
	$titles = array();
	if ( ! empty( $input['home_title_overrides'] ) && is_array( $input['home_title_overrides'] ) ) {
		foreach ( $input['home_title_overrides'] as $tid => $title ) {
			$tid = absint( $tid );
			$t   = sanitize_text_field( (string) $title );
			if ( $tid && '' !== $t ) $titles[ $tid ] = $t;
		}
	}
	$out['home_title_overrides'] = $titles;

	// Mirror to dedicated top-level options for easy programmatic access.
	update_option( 'gridindex_homepage_categories',          $cats );
	update_option( 'gridindex_homepage_category_order',      $cats );
	update_option( 'gridindex_homepage_posts_per_category',  $out['home_posts_per_cat'] );
	update_option( 'gridindex_hide_empty_homepage_sections', (int) $out['home_hide_empty'] );

	add_settings_error( 'gridindex_theme_options', 'saved', __( 'Grid Index theme options saved.', 'the-grid-index' ), 'updated' );
	return $out;
}

/* ============================================================
 * Admin page — premium custom UI
 * ============================================================ */

function gridindex_add_options_page() {
	$hook = add_theme_page(
		__( 'Grid Index Theme Options', 'the-grid-index' ),
		__( 'Grid Index Theme Options', 'the-grid-index' ),
		'manage_options',
		'gridindex-theme-options',
		'gridindex_render_options_page'
	);
	add_action( "admin_enqueue_scripts", function( $screen_hook ) use ( $hook ) {
		if ( $screen_hook !== $hook ) return;
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		$ver = defined( 'GIP_VERSION' ) ? GIP_VERSION : '1.0.0';
		$css = get_template_directory() . '/assets/admin/theme-options.css';
		$js  = get_template_directory() . '/assets/admin/theme-options.js';
		if ( file_exists( $css ) ) $ver_css = $ver . '.' . filemtime( $css ); else $ver_css = $ver;
		if ( file_exists( $js  ) ) $ver_js  = $ver . '.' . filemtime( $js  ); else $ver_js  = $ver;
		wp_enqueue_style( 'gip-options', get_template_directory_uri() . '/assets/admin/theme-options.css', array(), $ver_css );
		wp_enqueue_script( 'gip-options', get_template_directory_uri() . '/assets/admin/theme-options.js', array( 'jquery', 'jquery-ui-sortable' ), $ver_js, true );
	} );
	// Add wpcontent host class so we can null padding
	add_filter( 'admin_body_class', function( $c ) use ( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->id === $hook ) $c .= ' gi-options-host';
		return $c;
	} );
}
add_action( 'admin_menu', 'gridindex_add_options_page' );

/**
 * Handle Reset action.
 */
function gridindex_handle_reset() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'the-grid-index' ) );
	}
	check_admin_referer( 'gridindex_reset_options' );
	delete_option( 'gridindex_theme_options' );
	wp_safe_redirect( add_query_arg(
		array( 'page' => 'gridindex-theme-options', 'reset' => '1' ),
		admin_url( 'themes.php' )
	) );
	exit;
}
add_action( 'admin_post_gridindex_reset_options', 'gridindex_handle_reset' );

/* ---------- Tiny field helpers (premium UI) ---------- */

function gip_ui_field( $label, $desc = '', $full = false ) {
	echo '<div class="gi-field' . ( $full ? ' gi-field--full' : '' ) . '">';
	if ( $label ) echo '<span class="gi-field__label">' . esc_html( $label ) . '</span>';
	if ( $desc )  echo '<p class="gi-field__desc">' . esc_html( $desc ) . '</p>';
}
function gip_ui_field_close() { echo '</div>'; }

function gip_ui_input( $name, $value, $opt, $type = 'text', $extra = '' ) {
	printf(
		'<input type="%s" class="gi-input" name="%s[%s]" value="%s" %s />',
		esc_attr( $type ), esc_attr( $opt ), esc_attr( $name ), esc_attr( $value ), $extra
	);
}
function gip_ui_select( $name, $current, $choices, $opt ) {
	echo '<select class="gi-select" name="' . esc_attr( $opt ) . '[' . esc_attr( $name ) . ']">';
	foreach ( $choices as $val => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $current, $val, false ), esc_html( $label ) );
	}
	echo '</select>';
}
function gip_ui_textarea( $name, $value, $opt, $rows = 6 ) {
	printf( '<textarea class="gi-textarea" rows="%d" name="%s[%s]">%s</textarea>',
		(int) $rows, esc_attr( $opt ), esc_attr( $name ), esc_textarea( $value ) );
}
function gip_ui_switch( $name, $current, $opt, $label = '' ) {
	echo '<label class="gi-switch">';
	printf( '<input type="checkbox" name="%s[%s]" value="1" %s />',
		esc_attr( $opt ), esc_attr( $name ), checked( 1, (int) $current, false ) );
	echo '<span class="gi-switch__track"></span>';
	if ( $label ) echo '<span class="gi-switch__label">' . esc_html( $label ) . '</span>';
	echo '</label>';
}
/**
 * Visual radio card.
 * $items: array( value => array( 'title'=>, 'desc'=>, 'preview'=>HTML, 'mod'=>extra-class ) )
 */
function gip_ui_radio_cards( $name, $current, $items, $opt ) {
	echo '<div class="gi-radio-grid">';
	foreach ( $items as $val => $it ) {
		$mod = isset( $it['mod'] ) ? ' gi-radio--' . sanitize_html_class( $it['mod'] ) : '';
		printf(
			'<label class="gi-radio%s"><input type="radio" name="%s[%s]" value="%s" %s />',
			esc_attr( $mod ), esc_attr( $opt ), esc_attr( $name ), esc_attr( $val ),
			checked( $current, $val, false )
		);
		echo '<span class="gi-radio__card">';
		if ( ! empty( $it['preview'] ) ) {
			echo '<span class="gi-radio__preview">' . wp_kses_post( $it['preview'] ) . '</span>';
		}
		echo '<span class="gi-radio__title">' . esc_html( $it['title'] ) . '</span>';
		if ( ! empty( $it['desc'] ) ) {
			echo '<span class="gi-radio__desc">' . esc_html( $it['desc'] ) . '</span>';
		}
		echo '</span></label>';
	}
	echo '</div>';
}

/* ---------- Page renderer ---------- */

function gridindex_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$tabs = array(
		'general'        => __( 'General', 'the-grid-index' ),
		'design'         => __( 'Design', 'the-grid-index' ),
		'header'         => __( 'Header', 'the-grid-index' ),
		'homepage'       => __( 'Homepage', 'the-grid-index' ),
		'home_sections'  => __( 'Homepage Sections', 'the-grid-index' ),
		'cards'          => __( 'Cards', 'the-grid-index' ),
		'article'        => __( 'Article Page', 'the-grid-index' ),
		'archive'        => __( 'Archive / Category', 'the-grid-index' ),
		'footer'         => __( 'Footer', 'the-grid-index' ),
		'ads'            => __( 'Ads', 'the-grid-index' ),
		'advanced'       => __( 'Performance / Advanced', 'the-grid-index' ),
		'home_debug'     => __( 'Debug', 'the-grid-index' ),
	);
	$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( ! isset( $tabs[ $active ] ) ) $active = 'general';

	$o          = wp_parse_args( get_option( 'gridindex_theme_options', array() ), gridindex_get_default_options() );
	$opt_name   = 'gridindex_theme_options';
	$reset_url  = wp_nonce_url( admin_url( 'admin-post.php?action=gridindex_reset_options' ), 'gridindex_reset_options' );
	$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
	if ( is_array( $categories ) && gridindex_should_exclude_uncategorized() ) {
		$ex_ids = gridindex_get_uncategorized_ids();
		$categories = array_values( array_filter( $categories, function( $t ) use ( $ex_ids ) {
			return ! is_wp_error( $t ) && isset( $t->term_id ) && ! in_array( (int) $t->term_id, $ex_ids, true ) && $t->slug !== 'uncategorized';
		} ) );
	}
	$mode_class = 'gi-mode-' . ( $o['color_mode'] === 'dark' ? 'dark' : 'light' );
	$mode_label = ucfirst( $o['color_mode'] );
	$saved      = ! empty( $_GET['settings-updated'] );
	$reset      = ! empty( $_GET['reset'] );
	?>
	<div class="wrap gridindex-theme-options <?php echo esc_attr( $mode_class ); ?>">

		<div class="gi-hero">
			<div class="gi-hero__inner">
				<div class="gi-hero__brand">
					<span class="gi-hero__eyebrow"><?php esc_html_e( 'The Grid Index', 'the-grid-index' ); ?></span>
					<h1 class="gi-hero__title"><?php esc_html_e( 'Theme Options', 'the-grid-index' ); ?></h1>
					<p class="gi-hero__sub"><?php esc_html_e( 'Editorial controls for The Grid homepage, categories, article layouts, and visual presentation.', 'the-grid-index' ); ?></p>
				</div>
				<div class="gi-hero__meta">
					<span class="gi-badge <?php echo $o['color_mode']==='dark' ? 'gi-badge--dark' : ''; ?>">
						<?php printf( esc_html__( 'Mode: %s', 'the-grid-index' ), esc_html( $mode_label ) ); ?>
					</span>
					<span class="gi-badge"><?php printf( esc_html__( 'v%s', 'the-grid-index' ), esc_html( defined( 'GIP_VERSION' ) ? GIP_VERSION : '1.0.0' ) ); ?></span>
					<div class="gi-actions">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener" class="gi-btn"><?php esc_html_e( 'Live Preview ↗', 'the-grid-index' ); ?></a>
						<a href="<?php echo esc_url( $reset_url ); ?>"
						   onclick="return confirm('<?php echo esc_js( __( 'Reset all Grid Index theme options to defaults? This cannot be undone.', 'the-grid-index' ) ); ?>');"
						   class="gi-btn gi-btn--danger"><?php esc_html_e( 'Reset', 'the-grid-index' ); ?></a>
						<button type="button" class="gi-btn gi-btn--primary" data-save><?php esc_html_e( 'Save Changes', 'the-grid-index' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<div class="gi-shell">

			<aside class="gi-side" aria-label="<?php esc_attr_e( 'Sections', 'the-grid-index' ); ?>">
				<div class="gi-side__title"><?php esc_html_e( 'Sections', 'the-grid-index' ); ?></div>
				<?php foreach ( $tabs as $slug => $label ) :
					$href = esc_url( admin_url( 'themes.php?page=gridindex-theme-options&tab=' . $slug ) );
					$cls  = $active === $slug ? 'is-active' : '';
				?>
					<a href="<?php echo $href; ?>" data-tab="<?php echo esc_attr( $slug ); ?>" class="<?php echo esc_attr( $cls ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</a>
				<?php endforeach; ?>
			</aside>

			<div class="gi-main">
				<?php if ( $saved ) : ?>
					<div class="gi-notice"><?php esc_html_e( 'Settings saved.', 'the-grid-index' ); ?></div>
				<?php elseif ( $reset ) : ?>
					<div class="gi-notice gi-notice--reset"><?php esc_html_e( 'Theme options reset to defaults.', 'the-grid-index' ); ?></div>
				<?php endif; ?>

				<form method="post" action="options.php" id="gridindex-options-form">
					<?php settings_fields( 'gridindex_theme_options_group' ); ?>

					<?php
					// Render hidden inputs for the inactive tabs to preserve their values.
					gridindex_render_hidden_for_other_tabs( $active, $o, $opt_name );

					// Render only the active panel
					$tab_renderers = array(
						'general'       => 'gridindex_tab_general',
						'design'        => 'gridindex_tab_design',
						'header'        => 'gridindex_tab_header',
						'homepage'      => 'gridindex_tab_homepage',
						'home_sections' => 'gridindex_tab_home_sections',
						'cards'         => 'gridindex_tab_cards',
						'article'       => 'gridindex_tab_article',
						'archive'       => 'gridindex_tab_archive',
						'footer'        => 'gridindex_tab_footer',
						'ads'           => 'gridindex_tab_ads',
						'advanced'      => 'gridindex_tab_advanced',
						'home_debug'    => 'gridindex_tab_home_debug',
					);
					echo '<div class="gi-panel" data-panel="' . esc_attr( $active ) . '">';
					$cb = isset( $tab_renderers[ $active ] ) ? $tab_renderers[ $active ] : '';
					if ( $cb && function_exists( $cb ) ) {
						$ref = new ReflectionFunction( $cb );
						if ( $ref->getNumberOfParameters() >= 3 ) {
							call_user_func( $cb, $o, $opt_name, $categories );
						} else {
							call_user_func( $cb, $o, $opt_name );
						}
					} else {
						echo '<div class="gi-card"><div class="gi-card__head"><h2 class="gi-card__title">' . esc_html( $tabs[ $active ] ?? $active ) . '</h2></div><div class="gi-card__body"><p>' . esc_html__( 'This section is available but has no controls yet.', 'the-grid-index' ) . '</p></div></div>';
					}
					echo '</div>';
					?>

					<div class="gi-savebar">
						<span class="gi-savebar__hint"><?php esc_html_e( 'Saved using the WordPress Settings API · Nonce protected.', 'the-grid-index' ); ?></span>
						<div class="gi-actions">
							<a href="<?php echo esc_url( $reset_url ); ?>"
							   onclick="return confirm('<?php echo esc_js( __( 'Reset all options to defaults?', 'the-grid-index' ) ); ?>');"
							   class="gi-btn gi-btn--danger"><?php esc_html_e( 'Reset', 'the-grid-index' ); ?></a>
							<?php if ( 'home_debug' !== $active ) : ?>
								<button type="submit" class="gi-btn gi-btn--primary"><?php esc_html_e( 'Save Changes', 'the-grid-index' ); ?></button>
							<?php endif; ?>
						</div>
					</div>
				</form>

				<?php
				$css_path = get_template_directory() . '/assets/css/gridindex.css';
				$adm_css  = get_template_directory() . '/assets/admin/theme-options.css';
				$adm_js   = get_template_directory() . '/assets/admin/theme-options.js';
				$last_saved = (int) get_option( 'gridindex_theme_options_saved_at', 0 );
				?>
				<details class="gi-card" style="margin-top:18px;">
					<summary><?php esc_html_e( 'Admin debug', 'the-grid-index' ); ?></summary>
					<div class="gi-card__body">
						<p><strong><?php esc_html_e( 'Current tab:', 'the-grid-index' ); ?></strong> <code><?php echo esc_html( $active ); ?></code></p>
						<p><strong><?php esc_html_e( 'Active admin CSS:', 'the-grid-index' ); ?></strong> <code><?php echo esc_html( file_exists( $adm_css ) ? 'theme-options.css ✓ (mtime ' . filemtime( $adm_css ) . ')' : 'missing' ); ?></code></p>
						<p><strong><?php esc_html_e( 'Active admin JS:', 'the-grid-index' ); ?></strong> <code><?php echo esc_html( file_exists( $adm_js )  ? 'theme-options.js ✓ (mtime ' . filemtime( $adm_js ) . ')'   : 'missing' ); ?></code></p>
						<p><strong><?php esc_html_e( 'Frontend CSS:', 'the-grid-index' ); ?></strong> <code><?php echo esc_html( file_exists( $css_path ) ? 'gridindex.css ✓ (mtime ' . filemtime( $css_path ) . ')' : 'missing' ); ?></code></p>
						<p><strong><?php esc_html_e( 'Last saved:', 'the-grid-index' ); ?></strong> <code><?php echo esc_html( $last_saved ? date_i18n( 'Y-m-d H:i:s', $last_saved ) : '—' ); ?></code></p>
						<p><strong><?php esc_html_e( 'Loaded option array:', 'the-grid-index' ); ?></strong></p>
						<pre style="max-height:280px;overflow:auto;background:var(--gi-bg);padding:10px;border:1px solid var(--gi-border);border-radius:6px;font-size:11.5px;"><?php echo esc_html( wp_json_encode( $o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
					</div>
				</details>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Preserve values for tabs not currently rendered.
 */
function gridindex_render_hidden_for_other_tabs( $active, $o, $name ) {
	$tab_keys = array(
		'general'       => array( 'frontend_design_mode', 'color_mode', 'accent_color', 'editorial_density', 'site_width', 'sticky_header' ),
		'design'        => array( 'font_heading', 'font_body', 'bg_color', 'card_color', 'border_style', 'accent_color' ),
		'header'        => array( 'logo_id', 'logo_id_retina', 'wordmark', 'tagline', 'header_style', 'menu_alignment', 'show_search', 'show_date_strip', 'mobile_menu_style' ),
		'homepage'      => array( 'hero_layout', 'hero_category', 'hero_count', 'hero_autoplay', 'hero_rotation', 'hero_show_momentum', 'hero_show_count', 'enable_ticker', 'enable_latest_rail', 'enable_cat_bands', 'hide_empty', 'home_categories', 'section_order' ),
		'home_sections' => array( 'home_sections_enabled', 'home_section_cats', 'home_posts_per_cat', 'home_hide_empty', 'home_show_dashboard', 'home_section_layout', 'home_title_overrides' ),
		'cards'         => array( 'card_style', 'card_show_source', 'card_show_logo', 'card_show_date', 'card_show_category', 'card_excerpt_len', 'card_show_read_src', 'card_image_fallback', 'card_image_ratio' ),
		'article'       => array( 'article_attribution', 'article_related', 'article_author_date', 'article_taxonomy', 'article_orig_btn', 'article_sidebar', 'article_click_behavior', 'card_show_source_cta', 'single_show_source_cta', 'hide_rss_comments' ),
		'archive'       => array( 'archive_layout', 'archive_posts_per_page', 'archive_show_hero', 'archive_show_rail', 'archive_show_filters', 'archive_pagination_style' ),
		'footer'        => array( 'footer_layout', 'footer_text', 'footer_social', 'footer_newsletter', 'footer_copyright' ),
		'ads'           => array( 'ads_enabled', 'ad_header', 'ad_in_feed', 'ad_sidebar', 'ad_article_body' ),
		'advanced'      => array( 'custom_css', 'disable_animations', 'lazy_loading', 'cache_buster', 'exclude_uncategorized', 'debug_mode' ),
	);
	foreach ( $tab_keys as $tab => $keys ) {
		if ( $tab === $active ) continue;
		foreach ( $keys as $k ) {
			$v = $o[ $k ] ?? '';
			if ( is_array( $v ) ) {
				if ( $k === 'footer_social' ) {
					foreach ( $v as $net => $url ) {
						printf( '<input type="hidden" name="%s[footer_social][%s]" value="%s" />', esc_attr( $name ), esc_attr( $net ), esc_attr( $url ) );
					}
				} elseif ( $k === 'home_title_overrides' ) {
					foreach ( $v as $tid => $title ) {
						printf( '<input type="hidden" name="%s[home_title_overrides][%s]" value="%s" />', esc_attr( $name ), esc_attr( $tid ), esc_attr( $title ) );
					}
				} else {
					foreach ( $v as $sub ) {
						printf( '<input type="hidden" name="%s[%s][]" value="%s" />', esc_attr( $name ), esc_attr( $k ), esc_attr( $sub ) );
					}
				}
			} else {
				printf( '<input type="hidden" name="%s[%s]" value="%s" />', esc_attr( $name ), esc_attr( $k ), esc_attr( $v ) );
			}
		}
	}
}

/* ============================================================
 * Tab renderers (premium UI)
 * ============================================================ */

function gridindex_tab_general( $o, $n ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Default frontend design mode', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Controls the public homepage shell. Grid Index Dark Intelligence is the signature look. Light Editorial is a polished version of the same layout.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'frontend_design_mode', $o['frontend_design_mode'] ?? 'dark', array(
				'dark'   => array( 'title' => __( 'Grid Index Dark Intelligence', 'the-grid-index' ), 'desc' => __( '#080d14 charcoal, teal accent, signal badges.', 'the-grid-index' ),
					'mod' => 'mode-dark', 'preview' => '<span class="gi-pv-mode"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b2"></span></span>' ),
				'light'  => array( 'title' => __( 'Light Editorial', 'the-grid-index' ), 'desc' => __( 'Polished light variant of the same Grid Index layout.', 'the-grid-index' ),
					'mod' => 'mode-light', 'preview' => '<span class="gi-pv-mode"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b2"></span></span>' ),
				'system' => array( 'title' => __( 'System', 'the-grid-index' ), 'desc' => __( 'Match visitor OS preference.', 'the-grid-index' ),
					'mod' => 'mode-system', 'preview' => '<span class="gi-pv-mode"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b2"></span></span>' ),
			), $n ); ?>
		</div>
	</div>


	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Appearance mode', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Default color mode for new visitors.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'color_mode', $o['color_mode'], array(
				'light'  => array( 'title' => __( 'Light', 'the-grid-index' ),  'desc' => __( 'Newspaper white.', 'the-grid-index' ),
					'mod' => 'mode-light', 'preview' => '<span class="gi-pv-mode"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b2"></span></span>' ),
				'dark'   => array( 'title' => __( 'Dark', 'the-grid-index' ),   'desc' => __( 'Editorial dark.', 'the-grid-index' ),
					'mod' => 'mode-dark',  'preview' => '<span class="gi-pv-mode"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b2"></span></span>' ),
				'system' => array( 'title' => __( 'System', 'the-grid-index' ), 'desc' => __( 'Match OS setting.', 'the-grid-index' ),
					'mod' => 'mode-system','preview' => '<span class="gi-pv-mode"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b2"></span></span>' ),
			), $n ); ?>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Editorial density', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'How tightly the homepage and feeds breathe.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'editorial_density', $o['editorial_density'], array(
				'comfortable' => array( 'title' => __( 'Comfortable', 'the-grid-index' ), 'desc' => __( 'Generous spacing.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-density"><span class="row"></span><span class="row"></span><span class="row"></span></span>' ),
				'compact'     => array( 'title' => __( 'Compact', 'the-grid-index' ), 'desc' => __( 'Denser layout.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-density compact"><span class="row"></span><span class="row"></span><span class="row"></span><span class="row"></span><span class="row"></span></span>' ),
			), $n ); ?>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Layout & accent', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Site width', 'the-grid-index' ), __( 'Full uses the entire viewport. Boxed constrains to ~1280px.', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'site_width', $o['site_width'], array( 'full' => __( 'Full width', 'the-grid-index' ), 'boxed' => __( 'Boxed', 'the-grid-index' ) ), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Accent color', 'the-grid-index' ), __( 'Used for links, focus rings, and section accents.', 'the-grid-index' ) ); ?>
					<div class="gi-color-row">
						<input type="color" class="gi-input gi-color" name="<?php echo esc_attr( $n ); ?>[accent_color]" value="<?php echo esc_attr( $o['accent_color'] ); ?>" />
						<input type="text" class="gi-input" style="max-width:160px;" value="<?php echo esc_attr( $o['accent_color'] ); ?>" readonly />
					</div>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Sticky header', 'the-grid-index' ), __( 'Keep the masthead pinned while scrolling.', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'sticky_header', $o['sticky_header'], $n, __( 'Pin the masthead to the top', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'UI preview', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Live preview of admin UI components in the current admin theme.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-uiprev">
				<div class="gi-uiprev__cell">
					<span class="gi-uiprev__label"><?php esc_html_e( 'Buttons', 'the-grid-index' ); ?></span>
					<div class="gi-uiprev__row">
						<button type="button" class="gi-btn gi-btn--primary"><?php esc_html_e( 'Save Changes', 'the-grid-index' ); ?></button>
						<button type="button" class="gi-btn"><?php esc_html_e( 'Secondary', 'the-grid-index' ); ?></button>
						<button type="button" class="gi-btn gi-btn--danger"><?php esc_html_e( 'Reset', 'the-grid-index' ); ?></button>
					</div>
				</div>
				<div class="gi-uiprev__cell">
					<span class="gi-uiprev__label"><?php esc_html_e( 'Toggles', 'the-grid-index' ); ?></span>
					<div class="gi-uiprev__row">
						<label class="gi-switch"><input type="checkbox" disabled /><span class="gi-switch__track"></span><span class="gi-switch__label"><?php esc_html_e( 'Off state', 'the-grid-index' ); ?></span></label>
					</div>
					<div class="gi-uiprev__row">
						<label class="gi-switch"><input type="checkbox" checked disabled /><span class="gi-switch__track"></span><span class="gi-switch__label"><?php esc_html_e( 'On state', 'the-grid-index' ); ?></span></label>
					</div>
				</div>
				<div class="gi-uiprev__cell">
					<span class="gi-uiprev__label"><?php esc_html_e( 'Input', 'the-grid-index' ); ?></span>
					<input type="text" class="gi-input" placeholder="<?php esc_attr_e( 'Type something…', 'the-grid-index' ); ?>" />
				</div>
				<div class="gi-uiprev__cell gi-uiprev__cell--selected">
					<span class="gi-uiprev__label"><?php esc_html_e( 'Selected card', 'the-grid-index' ); ?></span>
					<strong style="color:var(--gi-text);font-size:14px;"><?php esc_html_e( 'Active selection', 'the-grid-index' ); ?></strong>
					<p style="margin:0;color:var(--gi-muted);font-size:12.5px;"><?php esc_html_e( 'Teal border + glow indicates the chosen option.', 'the-grid-index' ); ?></p>
				</div>
				<div class="gi-uiprev__cell">
					<span class="gi-uiprev__label"><?php esc_html_e( 'Badges', 'the-grid-index' ); ?></span>
					<div class="gi-uiprev__row">
						<span class="gi-badge"><?php esc_html_e( 'Default', 'the-grid-index' ); ?></span>
						<span class="gi-badge gi-badge--success"><?php esc_html_e( 'Success', 'the-grid-index' ); ?></span>
						<span class="gi-badge gi-badge--warning"><?php esc_html_e( 'Warning', 'the-grid-index' ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_header( $o, $n ) {
	$logo_url = $o['logo_id'] ? wp_get_attachment_image_url( (int) $o['logo_id'], 'medium' ) : '';
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Brand', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Logo, wordmark, and tagline.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Logo', 'the-grid-index' ), __( 'PNG / SVG recommended.', 'the-grid-index' ), true ); ?>
					<input type="hidden" id="gip-logo-id" name="<?php echo esc_attr( $n ); ?>[logo_id]" value="<?php echo esc_attr( $o['logo_id'] ); ?>" />
					<div class="gi-logo">
						<div class="gi-logo__preview" id="gip-logo-preview">
							<?php if ( $logo_url ) : ?><img src="<?php echo esc_url( $logo_url ); ?>" alt="" /><?php endif; ?>
						</div>
						<div class="gi-logo__buttons">
							<button type="button" class="gi-btn" id="gip-upload-logo"><?php esc_html_e( 'Choose / Upload Logo', 'the-grid-index' ); ?></button>
							<button type="button" class="gi-btn gi-btn--danger" id="gip-remove-logo" style="<?php echo $logo_url ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'the-grid-index' ); ?></button>
						</div>
					</div>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Wordmark text', 'the-grid-index' ), __( 'Leave blank to use site title.', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'wordmark', $o['wordmark'], $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Tagline', 'the-grid-index' ), __( 'Optional secondary text shown next to the wordmark.', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'tagline', $o['tagline'], $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Retina logo (2x) — attachment ID', 'the-grid-index' ), __( 'Optional. Paste the attachment ID of a 2x logo. Use the Media Library to find IDs.', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'logo_id_retina', $o['logo_id_retina'] ?? 0, $n, 'number', 'min="0" step="1"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Site icon / favicon', 'the-grid-index' ), '', true ); ?>
					<a class="gi-btn" href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Open WordPress Site Icon settings ↗', 'the-grid-index' ); ?></a>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Header style', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'header_style', $o['header_style'], array(
				'classic'  => array( 'title' => __( 'Classic', 'the-grid-index' ),  'desc' => __( 'Clean newspaper masthead.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="l" style="height:7px;width:50%"></span><span class="l2" style="width:80%"></span></span>' ),
				'terminal' => array( 'title' => __( 'Terminal', 'the-grid-index' ), 'desc' => __( 'Bloomberg-style data bar.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="img" style="height:8px"></span><span class="l" style="width:60%"></span><span class="l2"></span></span>' ),
				'magazine' => array( 'title' => __( 'Magazine', 'the-grid-index' ), 'desc' => __( 'Bold display masthead.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="l" style="height:12px;width:70%"></span><span class="l2" style="width:50%"></span></span>' ),
			), $n ); ?>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Navigation', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Menu alignment', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'menu_alignment', $o['menu_alignment'], array( 'left' => __( 'Left', 'the-grid-index' ), 'center' => __( 'Center', 'the-grid-index' ), 'right' => __( 'Right', 'the-grid-index' ) ), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Mobile menu style', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'mobile_menu_style', $o['mobile_menu_style'] ?? 'drawer', array(
						'drawer'     => __( 'Drawer (slide-in)', 'the-grid-index' ),
						'fullscreen' => __( 'Fullscreen overlay', 'the-grid-index' ),
						'dropdown'   => __( 'Dropdown', 'the-grid-index' ),
					), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Manage menu locations', 'the-grid-index' ), '', true ); ?>
					<a class="gi-btn" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Open Appearance → Menus ↗', 'the-grid-index' ); ?></a>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Header toggles', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'show_search', $o['show_search'], $n ); ?>
							<div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Show search icon', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Site search in the header bar.', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'show_date_strip', $o['show_date_strip'], $n ); ?>
							<div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Date / weather strip', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Slim row above the masthead.', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_homepage( $o, $n, $categories ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Hero layout', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Top-of-homepage lead presentation.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'hero_layout', $o['hero_layout'], array(
				'live_deck' => array( 'title' => __( 'Live Deck', 'the-grid-index' ), 'desc' => __( 'Cinematic active story + signal stack. The Grid Index signature.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-hero gi-pv-hero--bloomberg"><span class="a"></span><span class="x"></span><span class="x"></span><span class="x"></span></span>' ),
				'lead'      => array( 'title' => __( '1 lead story', 'the-grid-index' ), 'desc' => __( 'Single dominant story.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-hero gi-pv-hero--lead"><span class="x"></span></span>' ),
				'three'     => array( 'title' => __( '3-card editorial', 'the-grid-index' ), 'desc' => __( 'Three equal cards.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-hero gi-pv-hero--three"><span class="x"></span><span class="x"></span><span class="x"></span></span>' ),
				'bloomberg' => array( 'title' => __( 'Bloomberg grid', 'the-grid-index' ), 'desc' => __( 'Lead + side stack.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-hero gi-pv-hero--bloomberg"><span class="a"></span><span class="x"></span><span class="x"></span></span>' ),
			), $n ); ?>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Live Deck behavior', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Applies when Hero layout is set to Live Deck.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Auto-rotate', 'the-grid-index' ), __( 'Smoothly cycle through stories. Pauses on hover. Disabled when prefers-reduced-motion.', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'hero_autoplay', $o['hero_autoplay'] ?? 0, $n, __( 'Enable autoplay', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Rotation interval (ms)', 'the-grid-index' ), __( 'Time between stories. 7000 = 7 seconds.', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'hero_rotation', $o['hero_rotation'] ?? 7000, $n, 'number', 'min="3000" max="30000" step="500"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Signal-stack chips', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'hero_show_count', $o['hero_show_count'] ?? 1, $n ); ?>
							<div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Source count', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Show "N sources" chip.', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'hero_show_momentum', $o['hero_show_momentum'] ?? 1, $n ); ?>
							<div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Momentum score', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Show heat % on accelerating items.', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<?php /* Spacer to keep next card layout consistent */ ?>
	<div style="display:none"></div>

	<?php /* Original card continues below */ ?>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Hero source', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Hero category source', 'the-grid-index' ), __( 'Pull hero stories from this category. Leave on Latest for any.', 'the-grid-index' ) ); ?>
					<select class="gi-select" name="<?php echo esc_attr( $n ); ?>[hero_category]">
						<option value="0"><?php esc_html_e( 'Latest (any category)', 'the-grid-index' ); ?></option>
						<?php foreach ( $categories as $c ) : if ( is_wp_error( $c ) || ! isset( $c->term_id ) ) continue; ?>
							<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( (int) $o['hero_category'], (int) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Number of hero stories', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'hero_count', $o['hero_count'], $n, 'number', 'min="1" max="12" step="1"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Section toggles', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'enable_ticker', $o['enable_ticker'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Breaking ticker', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Live headlines bar.', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'enable_latest_rail', $o['enable_latest_rail'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Latest rail', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Right-side intelligence column.', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'enable_cat_bands', $o['enable_cat_bands'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Category bands', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Per-category sections below the hero.', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'hide_empty', $o['hide_empty'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Hide empty sections', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Skip sections with no posts.', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>

				<?php
				// Preserve legacy keys quietly so they don't get clobbered.
				foreach ( (array) $o['home_categories'] as $cid ) {
					printf( '<input type="hidden" name="%s[home_categories][]" value="%d" />', esc_attr( $n ), (int) $cid );
				}
				?>
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[section_order]" value="<?php echo esc_attr( $o['section_order'] ); ?>" />
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_cards( $o, $n ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Card style', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'How story cards look across feeds and sections.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'card_style', $o['card_style'], array(
				'minimal'   => array( 'title' => __( 'Minimal', 'the-grid-index' ), 'desc' => __( 'Headline + meta, no image.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="l" style="height:7px;width:80%"></span><span class="l2" style="width:55%"></span><span class="l2" style="width:35%"></span></span>' ),
				'editorial' => array( 'title' => __( 'Editorial', 'the-grid-index' ), 'desc' => __( 'Image + serif headline.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="img"></span><span class="l"></span><span class="l2"></span></span>' ),
				'signal'    => array( 'title' => __( 'Signal', 'the-grid-index' ), 'desc' => __( 'Compact list with badges.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card" style="gap:3px"><span class="l" style="width:90%"></span><span class="l2"></span><span class="l2"></span><span class="l2"></span></span>' ),
			), $n ); ?>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Card details', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Image aspect ratio', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'card_image_ratio', $o['card_image_ratio'] ?? '16x9', array(
						'16x9'     => __( '16:9 (widescreen)', 'the-grid-index' ),
						'4x3'      => __( '4:3 (classic)', 'the-grid-index' ),
						'3x2'      => __( '3:2 (editorial)', 'the-grid-index' ),
						'1x1'      => __( '1:1 (square)', 'the-grid-index' ),
						'portrait' => __( '3:4 (portrait)', 'the-grid-index' ),
					), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Image fallback style', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'card_image_fallback', $o['card_image_fallback'], array( 'gradient' => __( 'Gradient', 'the-grid-index' ), 'topic-color' => __( 'Topic color', 'the-grid-index' ), 'plain' => __( 'Plain', 'the-grid-index' ) ), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Excerpt length (words)', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'card_excerpt_len', $o['card_excerpt_len'], $n, 'number', 'min="0" max="80"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Card display toggles', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'card_show_source', $o['card_show_source'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Source name', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'card_show_logo', $o['card_show_logo'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Source logo', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'card_show_date', $o['card_show_date'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Publish date', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'card_show_category', $o['card_show_category'] ?? 1, $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Category badge', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'card_show_read_src', $o['card_show_read_src'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( '"Read source" button', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_article( $o, $n ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Source attribution & RSS behavior', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Controls how imported RSS posts link to their original publishers.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'article_click_behavior', $o['article_click_behavior'], array(
				'source'       => array( 'title' => __( 'Open original source directly', 'the-grid-index' ), 'desc' => __( 'Card title and image link straight to the publisher (recommended for RSS aggregators).', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="img"></span><span class="l"></span><span class="l2" style="background:#14B8A6"></span></span>' ),
				'internal'     => array( 'title' => __( 'Open internal article page', 'the-grid-index' ), 'desc' => __( 'Click goes to the WP article. Source still shown via the Read button.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="img"></span><span class="l"></span><span class="l2"></span></span>' ),
				'internal_cta' => array( 'title' => __( 'Internal page with prominent source CTA', 'the-grid-index' ), 'desc' => __( 'Open internal page that puts a large "Read at source" CTA above content.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="img"></span><span class="l"></span><span class="l2" style="width:80%;background:#14B8A6"></span></span>' ),
			), $n ); ?>

			<div class="gi-grid" style="margin-top:14px">
				<?php gip_ui_field( __( 'Card / single toggles', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'card_show_source_cta', $o['card_show_source_cta'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Show source CTA on cards', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'single_show_source_cta', $o['single_show_source_cta'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Show source CTA on single posts', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'hide_rss_comments', $o['hide_rss_comments'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Hide comments on imported RSS posts', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Article layout', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Sidebar layout', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'article_sidebar', $o['article_sidebar'], array( 'right' => __( 'Right', 'the-grid-index' ), 'left' => __( 'Left', 'the-grid-index' ), 'none' => __( 'None', 'the-grid-index' ) ), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Article toggles', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'article_attribution', $o['article_attribution'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Source attribution box', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'article_related', $o['article_related'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Related stories', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'article_author_date', $o['article_author_date'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Author / date', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'article_taxonomy', $o['article_taxonomy'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Tags / categories', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'article_orig_btn', $o['article_orig_btn'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Original source button', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_footer( $o, $n ) {
	$social = is_array( $o['footer_social'] ) ? $o['footer_social'] : array();
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Footer layout', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Footer layout', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'footer_layout', $o['footer_layout'], array( 'simple' => __( 'Simple', 'the-grid-index' ), 'columns' => __( 'Columns', 'the-grid-index' ), 'magazine' => __( 'Magazine', 'the-grid-index' ) ), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Footer logo / text', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'footer_text', $o['footer_text'], $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Copyright text', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'footer_copyright', $o['footer_copyright'], $n, 'text', 'placeholder="© ' . esc_attr( gmdate( 'Y' ) ) . '"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Newsletter block', 'the-grid-index' ), '', true ); ?>
					<?php gip_ui_switch( 'footer_newsletter', $o['footer_newsletter'], $n, __( 'Show newsletter signup block', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Social links', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php foreach ( array( 'twitter' => 'Twitter / X', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'rss' => 'RSS' ) as $key => $label ) : ?>
					<?php gip_ui_field( $label ); ?>
						<input type="url" class="gi-input" name="<?php echo esc_attr( $n ); ?>[footer_social][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $social[ $key ] ?? '' ); ?>" placeholder="https://" />
					<?php gip_ui_field_close(); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_ads( $o, $n ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Monetization', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Master switch and HTML for each ad slot.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_field( __( 'Enable ad slots', 'the-grid-index' ), '', true ); ?>
				<?php gip_ui_switch( 'ads_enabled', $o['ads_enabled'], $n, __( 'Render ad markup site-wide', 'the-grid-index' ) ); ?>
			<?php gip_ui_field_close(); ?>
		</div>
	</div>

	<?php foreach ( array(
		'ad_header'       => array( __( 'Header ad', 'the-grid-index' ), __( 'Rendered after the masthead.', 'the-grid-index' ) ),
		'ad_in_feed'      => array( __( 'In-feed ad', 'the-grid-index' ), __( 'Rendered between feed items.', 'the-grid-index' ) ),
		'ad_sidebar'      => array( __( 'Sidebar ad', 'the-grid-index' ), __( 'Rendered at top of intelligence rail.', 'the-grid-index' ) ),
		'ad_article_body' => array( __( 'Article body ad', 'the-grid-index' ), __( 'Rendered mid-article.', 'the-grid-index' ) ),
	) as $key => $info ) : ?>
		<div class="gi-card" style="margin-top:18px;">
			<div class="gi-card__head">
				<h2 class="gi-card__title"><?php echo esc_html( $info[0] ); ?></h2>
				<p class="gi-card__sub"><?php echo esc_html( $info[1] ); ?> <?php esc_html_e( 'HTML allowed; <script> tags stripped for safety.', 'the-grid-index' ); ?></p>
			</div>
			<div class="gi-card__body">
				<?php gip_ui_textarea( $key, $o[ $key ], $n, 5 ); ?>
			</div>
		</div>
	<?php endforeach;
}

function gridindex_tab_advanced( $o, $n ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Performance', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Lazy-load images', 'the-grid-index' ), __( 'Native browser lazy loading.', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'lazy_loading', $o['lazy_loading'], $n, __( 'Enable lazy loading', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Disable animations', 'the-grid-index' ), __( 'Removes all theme transitions.', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'disable_animations', $o['disable_animations'], $n, __( 'Disable theme animations', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Cache-busting version', 'the-grid-index' ), __( 'Optional. Appended to enqueued asset URLs to bust LiteSpeed/Hostinger cache.', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'cache_buster', $o['cache_buster'], $n, 'text', 'placeholder="e.g. 2026-05"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Exclude Uncategorized from public homepage', 'the-grid-index' ), __( 'Hides the WordPress default "Uncategorized" category from hero, ticker, latest, bands, sections, and the rail.', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'exclude_uncategorized', $o['exclude_uncategorized'] ?? 1, $n, __( 'Exclude Uncategorized', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Debug mode', 'the-grid-index' ), __( 'Shows a sticky debug strip at the bottom of the front-end for logged-in admins (template, hero layout, CSS, JS, asset versions).', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'debug_mode', $o['debug_mode'] ?? 0, $n, __( 'Show admin debug strip on the frontend', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Custom CSS', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Appended to the front-end stylesheet.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_textarea( 'custom_css', $o['custom_css'], $n, 10 ); ?>
		</div>
	</div>
	<?php
}

/* ============================================================
 * Homepage Sections — premium card-based UI
 * ============================================================ */

/* ============================================================
 * Design tab
 * ============================================================ */
function gridindex_tab_design( $o, $n ) {
	$font_choices = array(
		'serif-editorial' => __( 'Serif editorial (Source Serif / Playfair)', 'the-grid-index' ),
		'sans-modern'     => __( 'Modern sans (Inter / Helvetica)', 'the-grid-index' ),
		'mono-terminal'   => __( 'Mono terminal (JetBrains / IBM Plex Mono)', 'the-grid-index' ),
	);
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Mode & accent', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Quick controls duplicated from General for convenience.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Color mode', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'color_mode', $o['color_mode'], array(
						'dark' => __( 'Dark', 'the-grid-index' ),
						'light' => __( 'Light', 'the-grid-index' ),
						'system' => __( 'System', 'the-grid-index' ),
					), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Accent color', 'the-grid-index' ) ); ?>
					<input type="color" class="gi-input gi-color" name="<?php echo esc_attr( $n ); ?>[accent_color]" value="<?php echo esc_attr( $o['accent_color'] ); ?>" />
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Typography', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Pick a heading and body font family. Loaded from system / Google fallbacks.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Heading font', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'font_heading', $o['font_heading'] ?? 'serif-editorial', $font_choices, $n ); ?>
				<?php gip_ui_field_close(); ?>
				<?php gip_ui_field( __( 'Body font', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'font_body', $o['font_body'] ?? 'sans-modern', $font_choices, $n ); ?>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Surfaces & borders', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Override the page background and card surface, and set border treatment. Leave colors blank to inherit from the active mode.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Background color', 'the-grid-index' ) ); ?>
					<input type="color" class="gi-input gi-color" name="<?php echo esc_attr( $n ); ?>[bg_color]" value="<?php echo esc_attr( $o['bg_color'] ?: '#0b0f14' ); ?>" />
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Card color', 'the-grid-index' ) ); ?>
					<input type="color" class="gi-input gi-color" name="<?php echo esc_attr( $n ); ?>[card_color]" value="<?php echo esc_attr( $o['card_color'] ?: '#101723' ); ?>" />
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Border style', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'border_style', $o['border_style'] ?? 'subtle', array(
						'subtle'   => __( 'Subtle (default)', 'the-grid-index' ),
						'hairline' => __( 'Hairline', 'the-grid-index' ),
						'bold'     => __( 'Bold', 'the-grid-index' ),
						'none'     => __( 'None', 'the-grid-index' ),
					), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Editorial density', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'editorial_density', $o['editorial_density'], array(
						'comfortable' => __( 'Comfortable', 'the-grid-index' ),
						'compact'     => __( 'Compact', 'the-grid-index' ),
					), $n ); ?>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>
	<?php
}

/* ============================================================
 * Archive / Category tab
 * ============================================================ */
function gridindex_tab_archive( $o, $n ) {
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Archive layout', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Controls category, tag, author, and search archive pages.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<?php gip_ui_radio_cards( 'archive_layout', $o['archive_layout'] ?? 'intelligence', array(
				'intelligence' => array( 'title' => __( 'Intelligence', 'the-grid-index' ), 'desc' => __( 'Hero + grid + right rail. Premium signature.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-hero gi-pv-hero--bloomberg"><span class="a"></span><span class="x"></span><span class="x"></span></span>' ),
				'grid'         => array( 'title' => __( 'Grid', 'the-grid-index' ), 'desc' => __( 'Clean editorial grid, no hero or rail.', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-hero gi-pv-hero--three"><span class="x"></span><span class="x"></span><span class="x"></span></span>' ),
				'list'         => array( 'title' => __( 'List', 'the-grid-index' ), 'desc' => __( 'Dense vertical list (Bloomberg/FT terminal feel).', 'the-grid-index' ),
					'preview' => '<span class="gi-pv-card"><span class="l"></span><span class="l2"></span><span class="l2"></span><span class="l2"></span></span>' ),
			), $n ); ?>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Archive options', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Posts per page', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'archive_posts_per_page', $o['archive_posts_per_page'] ?? 12, $n, 'number', 'min="3" max="60"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Pagination style', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'archive_pagination_style', $o['archive_pagination_style'] ?? 'numbered', array(
						'numbered' => __( 'Numbered', 'the-grid-index' ),
						'prevnext' => __( 'Previous / Next', 'the-grid-index' ),
						'loadmore' => __( 'Load more', 'the-grid-index' ),
					), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Toggles', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'archive_show_hero', $o['archive_show_hero'] ?? 1, $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Category hero', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'archive_show_rail', $o['archive_show_rail'] ?? 1, $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Right intelligence rail', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'archive_show_filters', $o['archive_show_filters'] ?? 1, $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Signal filters', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>
	<?php
}

function gridindex_tab_home_sections( $o, $n, $categories ) {
	$selected_ids = array_map( 'intval', (array) $o['home_section_cats'] );
	$titles       = (array) $o['home_title_overrides'];

	$by_id = array();
	foreach ( $categories as $c ) {
		if ( ! is_wp_error( $c ) && isset( $c->term_id ) ) $by_id[ (int) $c->term_id ] = $c;
	}
	$ordered = array();
	foreach ( $selected_ids as $id ) if ( isset( $by_id[ $id ] ) ) $ordered[ $id ] = $by_id[ $id ];
	foreach ( $by_id as $id => $c ) if ( ! isset( $ordered[ $id ] ) ) $ordered[ $id ] = $c;
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Master controls', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<div class="gi-grid">
				<?php gip_ui_field( __( 'Enable category sections', 'the-grid-index' ), __( 'Master switch for the section list below.', 'the-grid-index' ), true ); ?>
					<?php gip_ui_switch( 'home_sections_enabled', $o['home_sections_enabled'], $n, __( 'Render category sections on the homepage', 'the-grid-index' ) ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Default section layout', 'the-grid-index' ) ); ?>
					<?php gip_ui_select( 'home_section_layout', $o['home_section_layout'], array( 'large-card' => __( 'Large card', 'the-grid-index' ), 'grid' => __( 'Grid', 'the-grid-index' ), 'compact-list' => __( 'Compact list', 'the-grid-index' ) ), $n ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Posts per category', 'the-grid-index' ) ); ?>
					<?php gip_ui_input( 'home_posts_per_cat', $o['home_posts_per_cat'], $n, 'number', 'min="1" max="24"' ); ?>
				<?php gip_ui_field_close(); ?>

				<?php gip_ui_field( __( 'Section behavior', 'the-grid-index' ), '', true ); ?>
					<div class="gi-toggle-row">
						<div class="gi-toggle-card"><?php gip_ui_switch( 'home_hide_empty', $o['home_hide_empty'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Hide empty categories', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Skip sections with zero posts.', 'the-grid-index' ); ?></span></div></div>
						<div class="gi-toggle-card"><?php gip_ui_switch( 'home_show_dashboard', $o['home_show_dashboard'], $n ); ?><div class="gi-toggle-card__body"><span class="gi-toggle-card__title"><?php esc_html_e( 'Show "Open Dashboard" link', 'the-grid-index' ); ?></span><span class="gi-toggle-card__desc"><?php esc_html_e( 'Per-section deep link.', 'the-grid-index' ); ?></span></div></div>
					</div>
				<?php gip_ui_field_close(); ?>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Categories & order', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Toggle, reorder via drag, and override titles. Empty categories are hidden automatically when enabled above.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-section-toolbar">
				<div class="gi-search">
					<input type="search" class="gi-input" id="gi-cat-search" placeholder="<?php esc_attr_e( 'Search categories…', 'the-grid-index' ); ?>" />
				</div>
				<button type="button" class="gi-btn" id="gi-cat-add-all"><?php esc_html_e( 'Add all visible', 'the-grid-index' ); ?></button>
				<button type="button" class="gi-btn" id="gi-cat-hide-empty"><?php esc_html_e( 'Untick empty', 'the-grid-index' ); ?></button>
				<button type="button" class="gi-btn gi-btn--danger" id="gi-cat-clear"><?php esc_html_e( 'Clear all', 'the-grid-index' ); ?></button>
			</div>

			<div class="gi-cat-list" id="gi-cat-list">
				<?php foreach ( $ordered as $id => $c ) :
					$count = (int) $c->count;
					$on    = in_array( (int) $id, $selected_ids, true );
				?>
					<div class="gi-cat <?php echo $on ? 'is-on' : ''; ?>"
					     data-id="<?php echo esc_attr( $id ); ?>"
					     data-name="<?php echo esc_attr( $c->name ); ?>"
					     data-slug="<?php echo esc_attr( $c->slug ); ?>"
					     data-count="<?php echo esc_attr( $count ); ?>">
						<div class="gi-cat__handle" title="<?php esc_attr_e( 'Drag to reorder', 'the-grid-index' ); ?>">⋮⋮</div>
						<div class="gi-cat__main">
							<div class="gi-cat__name">
								<label class="gi-switch">
									<input type="checkbox" class="gi-cat-on" name="<?php echo esc_attr( $n ); ?>[home_section_cats][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $on ); ?> />
									<span class="gi-switch__track"></span>
								</label>
								<span><?php echo esc_html( $c->name ); ?></span>
								<span class="gi-cat__slug"><?php echo esc_html( $c->slug ); ?></span>
							</div>
							<div class="gi-cat__count <?php echo $count === 0 ? 'is-zero' : ''; ?>">
								<?php
								/* translators: %s: post count */
								printf( esc_html( _n( '%s post', '%s posts', $count, 'the-grid-index' ) ), esc_html( number_format_i18n( $count ) ) );
								?>
							</div>
						</div>
						<div class="gi-cat__controls">
							<input type="text"
							       class="gi-input"
							       name="<?php echo esc_attr( $n ); ?>[home_title_overrides][<?php echo esc_attr( $id ); ?>]"
							       value="<?php echo esc_attr( $titles[ $id ] ?? '' ); ?>"
							       placeholder="<?php echo esc_attr( $c->name ); ?>" />
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}

/* ============================================================
 * Debug tab — diagnostic cards
 * ============================================================ */

function gridindex_tab_home_debug( $o, $n, $categories ) {
	$ids = gridindex_get_homepage_category_ids();
	$by_id = array();
	foreach ( $categories as $c ) {
		if ( ! is_wp_error( $c ) && isset( $c->term_id ) ) $by_id[ (int) $c->term_id ] = $c;
	}
	$per       = (int) gridindex_get_option( 'home_posts_per_cat' );
	$hide      = (bool) gridindex_get_option( 'home_hide_empty' );
	$layout    = gridindex_get_option( 'home_section_layout' );
	$tpl_path  = get_template_directory() . '/front-page.php';
	$tpl_ok    = file_exists( $tpl_path );
	$css_path  = get_template_directory() . '/assets/css/gridindex.css';
	$css_ok    = file_exists( $css_path );
	$css_ver   = $css_ok ? filemtime( $css_path ) : '—';
	?>
	<div class="gi-card">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'System status', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Quick diagnostics for templates, assets, and homepage configuration.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<div class="gi-diag">
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Active homepage template', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value <?php echo $tpl_ok ? 'is-good' : 'is-bad'; ?>"><?php echo esc_html( $tpl_ok ? 'front-page.php ✓' : 'missing' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Editorial CSS', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value <?php echo $css_ok ? 'is-good' : 'is-bad'; ?>">assets/css/gridindex.css <?php echo esc_html( $css_ok ? '✓' : '✗' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'CSS file mtime', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><?php echo esc_html( (string) $css_ver ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Theme version', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><?php echo esc_html( defined( 'GIP_VERSION' ) ? GIP_VERSION : '—' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Cache buster', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><?php echo esc_html( $o['cache_buster'] ?: '—' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Sections enabled', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value <?php echo $o['home_sections_enabled'] ? 'is-good' : 'is-warn'; ?>"><?php echo $o['home_sections_enabled'] ? esc_html__( 'Yes', 'the-grid-index' ) : esc_html__( 'No', 'the-grid-index' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Selected category IDs', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><?php echo esc_html( $ids ? implode( ', ', $ids ) : '—' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Hide empty', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><?php echo $hide ? 'yes' : 'no'; ?></div>
				</div>
				<?php
				$ex_on   = gridindex_should_exclude_uncategorized();
				$ex_ids  = gridindex_get_uncategorized_ids();
				$default_cat = (int) get_option( 'default_category' );
				$avail = count( $by_id );
				?>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Uncategorized excluded', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value <?php echo $ex_on ? 'is-good' : 'is-warn'; ?>"><?php echo $ex_on ? esc_html__( 'yes', 'the-grid-index' ) : esc_html__( 'no', 'the-grid-index' ); ?></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Default category ID', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><code><?php echo esc_html( (string) $default_cat ); ?></code></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Excluded term IDs', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><code><?php echo esc_html( $ex_ids ? implode( ', ', $ex_ids ) : '—' ); ?></code></div>
				</div>
				<div class="gi-diag__item">
					<div class="gi-diag__label"><?php esc_html_e( 'Categories available for homepage', 'the-grid-index' ); ?></div>
					<div class="gi-diag__value"><?php echo esc_html( (string) $avail ); ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'Per-category resolution', 'the-grid-index' ); ?></h2>
			<p class="gi-card__sub"><?php esc_html_e( 'Live WP_Query args and visibility per selected category.', 'the-grid-index' ); ?></p>
		</div>
		<div class="gi-card__body">
			<table class="gi-diag-table">
				<thead>
					<tr>
						<th>ID</th><th><?php esc_html_e( 'Name', 'the-grid-index' ); ?></th><th><?php esc_html_e( 'Slug', 'the-grid-index' ); ?></th>
						<th><?php esc_html_e( 'Posts', 'the-grid-index' ); ?></th>
						<th><?php esc_html_e( 'Visibility', 'the-grid-index' ); ?></th>
						<th><?php esc_html_e( 'WP_Query args', 'the-grid-index' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $ids ) : ?>
						<tr><td colspan="6"><em><?php esc_html_e( 'No categories selected — homepage will fall back to latest posts.', 'the-grid-index' ); ?></em></td></tr>
					<?php else : foreach ( $ids as $id ) :
						$c = $by_id[ $id ] ?? null;
						if ( ! $c ) continue;
						$args = gridindex_homepage_query_args( (int) $id );
						$q    = new WP_Query( $args );
						$has  = $q->have_posts();
						wp_reset_postdata();
						$visible = $has || ! $hide;
					?>
						<tr>
							<td><code><?php echo (int) $id; ?></code></td>
							<td><?php echo esc_html( $c->name ); ?></td>
							<td><code><?php echo esc_html( $c->slug ); ?></code></td>
							<td><?php echo (int) $q->found_posts; ?></td>
							<td><?php echo $visible
								? '<span class="gi-diag__value is-good">● ' . esc_html__( 'Visible', 'the-grid-index' ) . '</span>'
								: '<span class="gi-diag__value is-warn">● ' . esc_html__( 'Hidden (empty)', 'the-grid-index' ) . '</span>'; ?></td>
							<td><pre><?php echo esc_html( wp_json_encode( $args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="gi-card" style="margin-top:18px;">
		<div class="gi-card__head">
			<h2 class="gi-card__title"><?php esc_html_e( 'All theme option values', 'the-grid-index' ); ?></h2>
		</div>
		<div class="gi-card__body">
			<pre style="max-height:340px;overflow:auto;background:var(--gi-surface-2);padding:14px;border-radius:6px;font-size:12px;"><?php echo esc_html( wp_json_encode( $o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		</div>
	</div>
	<?php
}

/* ============================================================
 * Frontend — Homepage Sections renderer
 * ============================================================ */

/**
 * Should we exclude WP "Uncategorized" from the public homepage?
 */
function gridindex_should_exclude_uncategorized() {
	return (bool) gridindex_get_option( 'exclude_uncategorized', 1 );
}

/**
 * Resolve the term IDs that should be treated as "Uncategorized".
 * Combines: WP default category (option `default_category`) + any term with slug `uncategorized`.
 *
 * @return int[]
 */
function gridindex_get_uncategorized_ids() {
	$ids = array();
	$default = (int) get_option( 'default_category' );
	if ( $default > 0 ) $ids[] = $default;
	$by_slug = get_term_by( 'slug', 'uncategorized', 'category' );
	if ( $by_slug && ! is_wp_error( $by_slug ) ) $ids[] = (int) $by_slug->term_id;
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Get configured homepage category IDs (in order), with Uncategorized stripped.
 */
function gridindex_get_homepage_category_ids() {
	$ids = get_option( 'gridindex_homepage_category_order', null );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		$ids = (array) gridindex_get_option( 'home_section_cats', array() );
	}
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	if ( gridindex_should_exclude_uncategorized() ) {
		$ex = gridindex_get_uncategorized_ids();
		if ( $ex ) $ids = array_values( array_diff( $ids, $ex ) );
	}
	return $ids;
}

function gridindex_homepage_query_args( $cat_id, $exclude_post_ids = array() ) {
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'category__in'        => array( (int) $cat_id ),
		'posts_per_page'      => max( 1, (int) gridindex_get_option( 'home_posts_per_cat' ) ),
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => false,
	);
	if ( gridindex_should_exclude_uncategorized() ) {
		$ex = gridindex_get_uncategorized_ids();
		if ( $ex ) $args['category__not_in'] = $ex;
	}
	if ( ! empty( $exclude_post_ids ) ) {
		$args['post__not_in'] = array_map( 'intval', $exclude_post_ids );
	}
	return $args;
}

/**
 * Render all configured homepage category sections.
 * Hooked into `gip_render_homepage_feed` (priority 10, before fallback at 20).
 */
function gridindex_render_homepage_sections() {
	if ( ! gridindex_get_option( 'home_sections_enabled' ) ) return;

	$ids = gridindex_get_homepage_category_ids();

	// No categories chosen → render latest-posts fallback.
	if ( empty( $ids ) ) {
		gridindex_render_homepage_latest_fallback();
		return;
	}

	$layout    = sanitize_html_class( gridindex_get_option( 'home_section_layout' ) );
	$hide      = (bool) gridindex_get_option( 'home_hide_empty' );
	$show_dash = (bool) gridindex_get_option( 'home_show_dashboard' );
	$titles    = (array) gridindex_get_option( 'home_title_overrides' );
	$rendered_ids = array();
	$any_section  = false;

	foreach ( $ids as $cat_id ) {
		$term = get_term( (int) $cat_id, 'category' );
		if ( ! $term || is_wp_error( $term ) ) continue;

		$args  = gridindex_homepage_query_args( (int) $cat_id, $rendered_ids );
		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			if ( $hide ) { wp_reset_postdata(); continue; }
		}

		$any_section = true;
		$title = isset( $titles[ $cat_id ] ) && '' !== $titles[ $cat_id ] ? $titles[ $cat_id ] : $term->name;
		?>
		<section class="gip-home-section gip-home-section--<?php echo esc_attr( $layout ); ?>" data-category="<?php echo esc_attr( $term->slug ); ?>">
			<header class="gip-home-section__head">
				<h2 class="gip-home-section__title"><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $title ); ?></a></h2>
				<?php if ( $show_dash ) : ?>
					<a class="gip-home-section__dashboard" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php esc_html_e( 'Open Dashboard →', 'the-grid-index' ); ?></a>
				<?php endif; ?>
			</header>
			<?php if ( $query->have_posts() ) : ?>
				<div class="gip-home-section__list gip-home-section__list--<?php echo esc_attr( $layout ); ?>">
					<?php while ( $query->have_posts() ) : $query->the_post();
						$pid = get_the_ID();
						$rendered_ids[] = $pid; ?>
						<article class="gip-home-card">
							<?php if ( has_post_thumbnail() && 'compact-list' !== $layout ) : ?>
								<a class="gip-home-card__thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'gip-card', array( 'loading' => 'lazy' ) ); ?></a>
							<?php endif; ?>
							<h3 class="gip-home-card__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
							<?php if ( 'compact-list' !== $layout ) : ?>
								<p class="gip-home-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), (int) gridindex_get_option( 'card_excerpt_len' ) ) ); ?></p>
							<?php endif; ?>
						</article>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<p class="gip-home-section__empty"><?php esc_html_e( 'No stories yet in this category.', 'the-grid-index' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		wp_reset_postdata();
	}

	// Edge case: every selected section was empty + hide-empty on → render fallback.
	if ( ! $any_section ) {
		gridindex_render_homepage_latest_fallback();
	}
}
add_action( 'gip_render_homepage_feed', 'gridindex_render_homepage_sections', 5 );

/**
 * Latest-posts fallback (no categories selected, or all empty).
 */
function gridindex_render_homepage_latest_fallback() {
	$q = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, (int) gridindex_get_option( 'home_posts_per_cat' ) ),
		'ignore_sticky_posts' => true,
	) );
	if ( ! $q->have_posts() ) { wp_reset_postdata(); return; }
	echo '<section class="gip-home-section gip-home-section--latest">';
	echo '<header class="gip-home-section__head"><h2 class="gip-home-section__title">' . esc_html__( 'Latest', 'the-grid-index' ) . '</h2></header>';
	echo '<div class="gip-home-section__list gip-home-section__list--grid">';
	while ( $q->have_posts() ) { $q->the_post(); ?>
		<article class="gip-home-card">
			<?php if ( has_post_thumbnail() ) : ?>
				<a class="gip-home-card__thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'gip-card', array( 'loading' => 'lazy' ) ); ?></a>
			<?php endif; ?>
			<h3 class="gip-home-card__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
			<p class="gip-home-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), (int) gridindex_get_option( 'card_excerpt_len' ) ) ); ?></p>
		</article>
	<?php }
	echo '</div></section>';
	wp_reset_postdata();
}

/**
 * Disable the legacy fallback grid when sections are enabled — we render our own.
 */
function gridindex_disable_legacy_fallback() {
	if ( gridindex_get_option( 'home_sections_enabled' ) ) {
		remove_action( 'gip_render_homepage_feed', 'gip_render_homepage_feed_fallback', 20 );
	}
}
add_action( 'wp', 'gridindex_disable_legacy_fallback' );

/**
 * Homepage feed renderer — wraps the do_action dispatcher so external
 * code can invoke the homepage feed sections programmatically.
 */
function gridindex_homepage_feed_shortcode() {
	ob_start();
	do_action( 'gip_render_homepage_feed' );
	return ob_get_clean();
}
