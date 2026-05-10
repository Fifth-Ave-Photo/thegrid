<?php
/**
 * Site header & footer as server-rendered blocks.
 *
 * Lets FSE template parts (parts/header.html, parts/footer.html) emit
 * the full premium Grid Index header/footer that classic templates get
 * via header.php / footer.php — without duplicating markup.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gip_render_site_header' ) ) :
function gip_render_site_header( $args = array() ) {
	/**
	 * Ticker policy (v1.10.5):
	 * - Never render the in-header ticker globally.
	 * - The homepage Live Deck / Layout Builder owns the single breaking ticker.
	 * - Other templates (single, category, archive, search, 404) get NO ticker.
	 * The `ticker` block attribute is preserved for back-compat but ignored
	 * unless explicitly forced via the `gip_force_header_ticker` filter.
	 */
	$requested   = isset( $args['ticker'] ) ? (bool) $args['ticker'] : false;
	$show_ticker = (bool) apply_filters( 'gip_force_header_ticker', false, $requested, $args );
	echo "<!-- gip header ticker: " . ( $show_ticker ? 'on' : 'off (owned by homepage layout)' ) . " -->\n";
	$wordmark = function_exists( 'gridindex_get_option' ) ? gridindex_get_option( 'wordmark', '' ) : '';
	$title    = $wordmark !== '' ? $wordmark : get_bloginfo( 'name' );
	$tagline  = function_exists( 'gridindex_get_option' ) ? gridindex_get_option( 'tagline', '' ) : '';
	if ( $tagline === '' ) $tagline = get_bloginfo( 'description', 'display' );

	ob_start(); ?>
	<header class="gi-mast" role="banner">
		<div class="gi-mast__inner">
			<a class="gi-mast__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php
				if ( has_custom_logo() ) {
					$logo_id = get_theme_mod( 'custom_logo' );
					echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'gi-mast__logo', 'alt' => esc_attr( $title ) ) );
				} else {
					echo '<span class="gi-mast__title">' . esc_html( $title ) . '<span class="gi-mast__dot">.</span></span>';
				}
				if ( $tagline ) echo '<span class="gi-mast__tagline">' . esc_html( $tagline ) . '</span>';
				?>
			</a>
			<nav class="gi-mast__nav" aria-label="<?php esc_attr_e( 'Primary', 'the-grid-index' ); ?>">
				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'gi-mast__menu',
						'depth'          => 1,
						'fallback_cb'    => '__return_empty_string',
						'items_wrap'     => '%3$s',
					) );
				} else {
					$gi_ex = ( function_exists( 'gridindex_should_exclude_uncategorized' ) && gridindex_should_exclude_uncategorized() ) ? gridindex_get_uncategorized_ids() : array();
					$slugs = array( 'ai', 'tech', 'startups', 'cybersecurity' );
					$cats = array();
					foreach ( $slugs as $slug ) { $t = get_category_by_slug( $slug ); if ( $t ) $cats[] = $t; }
					if ( empty( $cats ) ) $cats = get_categories( array( 'number' => 6, 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true, 'exclude' => $gi_ex ) );
					foreach ( $cats as $c ) {
						echo '<a href="' . esc_url( get_term_link( $c ) ) . '">' . esc_html( $c->name ) . '</a>';
					}
				}
				?>
			</nav>
			<div class="gi-mast__meta">
				<button type="button" class="gi-mast__search" aria-label="<?php esc_attr_e( 'Search', 'the-grid-index' ); ?>" onclick="document.getElementById('gi-mast-search')?.classList.toggle('is-open');">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
				</button>
				<span class="gi-mast__pulse" title="<?php esc_attr_e( 'Live', 'the-grid-index' ); ?>"><span class="gi-mast__pulse-dot"></span><?php esc_html_e( 'Live', 'the-grid-index' ); ?></span>
				<span class="gi-mono gi-mast__date"><?php echo esc_html( date_i18n( 'D · M j · H:i' ) ); ?></span>
			</div>
		</div>
		<div class="gi-mast__searchwrap"><?php get_search_form(); ?></div>
		<?php if ( $show_ticker ) :
			$tq = new WP_Query( array( 'posts_per_page' => 8, 'no_found_rows' => true, 'ignore_sticky_posts' => true ) );
			if ( $tq->have_posts() ) : ?>
				<div class="gi-ticker gi-ticker--inhead" role="region" aria-label="<?php esc_attr_e( 'Breaking', 'the-grid-index' ); ?>">
					<span class="gi-ticker__label"><?php esc_html_e( 'Breaking', 'the-grid-index' ); ?></span>
					<div class="gi-ticker__track"><div class="gi-ticker__items">
						<?php while ( $tq->have_posts() ) { $tq->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'; }
						$tq->rewind_posts();
						while ( $tq->have_posts() ) { $tq->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'; } ?>
					</div></div>
				</div>
			<?php endif; wp_reset_postdata();
		endif; ?>
	</header>
	<?php
	return ob_get_clean();
}
endif;

if ( ! function_exists( 'gip_render_site_footer' ) ) :
function gip_render_site_footer() {
	$wordmark = function_exists( 'gridindex_get_option' ) ? gridindex_get_option( 'wordmark', '' ) : '';
	$title    = $wordmark !== '' ? $wordmark : get_bloginfo( 'name' );
	$tagline  = function_exists( 'gridindex_get_option' ) ? gridindex_get_option( 'tagline', '' ) : '';
	if ( $tagline === '' ) $tagline = get_bloginfo( 'description', 'display' );
	$year     = date_i18n( 'Y' );
	$slugs    = array( 'ai', 'tech', 'startups', 'cybersecurity' );

	ob_start(); ?>
	<footer class="gi-foot" role="contentinfo">
		<div class="gi-foot__inner">
			<div class="gi-foot__col gi-foot__col--brand">
				<a class="gi-foot__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $title ); ?><span class="gi-mast__dot">.</span></a>
				<p class="gi-foot__about"><?php echo esc_html( $tagline ?: __( 'Real-time intelligence across AI, Tech, Startups, and Cybersecurity. One signal cut from every source.', 'the-grid-index' ) ); ?></p>
				<div class="gi-foot__social">
					<a href="#" aria-label="X / Twitter">𝕏</a>
					<a href="#" aria-label="LinkedIn">in</a>
					<a href="#" aria-label="RSS"><?php echo esc_html( '⛌' ); ?></a>
				</div>
			</div>

			<div class="gi-foot__col">
				<h3 class="gi-foot__title"><?php esc_html_e( 'Topics', 'the-grid-index' ); ?></h3>
				<ul class="gi-foot__list">
					<?php foreach ( $slugs as $slug ) :
						$t = get_category_by_slug( $slug );
						if ( ! $t ) continue; ?>
						<li><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?> <span><?php echo (int) $t->count; ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="gi-foot__col">
				<h3 class="gi-foot__title"><?php esc_html_e( 'Grid Index', 'the-grid-index' ); ?></h3>
				<ul class="gi-foot__list">
					<?php
					if ( has_nav_menu( 'footer' ) ) {
						wp_nav_menu( array(
							'theme_location' => 'footer',
							'container'      => false,
							'menu_class'     => '',
							'depth'          => 1,
							'fallback_cb'    => '__return_empty_string',
							'items_wrap'     => '%3$s',
						) );
					} else { ?>
						<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Homepage', 'the-grid-index' ); ?></a></li>
						<li><a href="<?php echo esc_url( get_privacy_policy_url() ?: home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'the-grid-index' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'the-grid-index' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'the-grid-index' ); ?></a></li>
					<?php } ?>
				</ul>
			</div>

			<div class="gi-foot__col">
				<h3 class="gi-foot__title"><?php esc_html_e( 'Daily Brief', 'the-grid-index' ); ?></h3>
				<p class="gi-foot__about"><?php esc_html_e( 'The signal cut from every source — one brief, every morning.', 'the-grid-index' ); ?></p>
				<form class="gi-foot__form" method="post" action="#">
					<input type="email" required placeholder="you@domain.com" />
					<button type="submit"><?php esc_html_e( 'Subscribe →', 'the-grid-index' ); ?></button>
				</form>
			</div>
		</div>

		<div class="gi-foot__bar">
			<div class="gi-foot__copy">© <?php echo esc_html( $year ); ?> <?php echo esc_html( $title ); ?>. <?php esc_html_e( 'All rights reserved.', 'the-grid-index' ); ?></div>
			<a class="gi-foot__top" href="#top">↑ <?php esc_html_e( 'Back to top', 'the-grid-index' ); ?></a>
		</div>
	</footer>
	<?php
	return ob_get_clean();
}
endif;

/* Site header/footer renderers are called directly from header.php / footer.php */
