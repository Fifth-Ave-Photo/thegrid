<?php
/**
 * Grid Index Story Dossier
 *
 * Premium single-post layout used by both the classic single.php
 * template and the FSE templates/single.html. Renders headline, meta,
 * source attribution, two-column body with sticky intelligence rail,
 * related stories, and an admin debug strip — all in the dark
 * Grid Index editorial style.
 *
 * Exposed as:
 *   - PHP function : gip_render_story_dossier( $post_id )
 *   - Block        : grid-index/story-dossier (server-rendered)
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gip_render_story_dossier' ) ) :
function gip_render_story_dossier( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) return '';

	$post = get_post( $post_id );
	if ( ! $post ) return '';

	$src_url     = function_exists( 'gip_get_source_url' )  ? gip_get_source_url( $post_id )  : '';
	$src_name    = function_exists( 'gip_get_source_name' ) ? gip_get_source_name( $post_id ) : '';
	$is_imported = function_exists( 'gip_is_imported_rss' ) ? gip_is_imported_rss( $post_id ) : (bool) $src_url;
	$show_cta    = function_exists( 'gridindex_get_option' ) ? (int) gridindex_get_option( 'single_show_source_cta', 1 ) : 1;
	$hide_comm   = function_exists( 'gridindex_get_option' ) ? (int) gridindex_get_option( 'hide_rss_comments', 1 )      : 1;

	$cats     = get_the_category( $post_id );
	$cat      = ! empty( $cats ) ? $cats[0] : null;
	$pub_iso  = get_the_date( 'c', $post_id );
	$mod_iso  = get_the_modified_date( 'c', $post_id );
	$pub_h    = get_the_date( '', $post_id );
	$mod_h    = get_the_modified_date( '', $post_id );
	$ago      = human_time_diff( get_post_time( 'U', false, $post_id ), current_time( 'timestamp' ) );
	$summary  = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ?: $post->post_content ), 42 );

	ob_start();
	?>
	<div class="gi-shell gi-dossier<?php echo $is_imported ? ' gi-dossier--imported' : ''; ?>" data-post="<?php echo (int) $post_id; ?>">

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<!-- gi-dossier: post=<?php echo (int) $post_id; ?> imported=<?php echo $is_imported ? 'yes' : 'no'; ?> source=<?php echo $src_url ? 'yes' : 'no'; ?> -->
		<?php endif; ?>

		<nav class="gi-dossier__topnav" aria-label="<?php esc_attr_e( 'Article navigation', 'the-grid-index' ); ?>">
			<a class="gi-dossier__back" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span aria-hidden="true">←</span> <?php esc_html_e( 'Grid Index', 'the-grid-index' ); ?>
			</a>
			<?php if ( $cat ) : ?>
				<span class="gi-dossier__crumb-sep" aria-hidden="true">/</span>
				<a class="gi-dossier__crumb" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endif; ?>
			<span class="gi-dossier__crumb-sep" aria-hidden="true">/</span>
			<span class="gi-dossier__crumb is-current"><?php esc_html_e( 'Story', 'the-grid-index' ); ?></span>
		</nav>

		<div class="gi-dossier__grid">

			<article class="gi-dossier__main" itemscope itemtype="https://schema.org/NewsArticle">

				<header class="gi-dossier__hero">
					<?php if ( has_post_thumbnail( $post_id ) ) : ?>
						<figure class="gi-dossier__media" style="aspect-ratio:16/9;">
							<?php echo get_the_post_thumbnail( $post_id, 'gip-hero', array( 'loading' => 'eager', 'decoding' => 'async', 'itemprop' => 'image' ) ); ?>
						</figure>
					<?php endif; ?>
					<div class="gi-dossier__hero-body">
						<div class="gi-dossier__chips">
							<?php if ( function_exists( 'gip_render_signal_badge' ) ) gip_render_signal_badge( $post_id ); ?>
							<?php if ( $is_imported ) : ?>
								<span class="gi-badge gi-badge--imported"><?php esc_html_e( 'Imported', 'the-grid-index' ); ?></span>
							<?php endif; ?>
							<?php if ( $cat ) : ?>
								<a class="gi-kicker" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
							<?php endif; ?>
							<?php if ( $src_name ) : ?>
								<span class="gi-card__source" itemprop="sourceOrganization"><?php echo esc_html( $src_name ); ?></span>
							<?php endif; ?>
						</div>
						<h1 class="gi-dossier__title" itemprop="headline"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
						<?php if ( $summary ) : ?>
							<p class="gi-dossier__deck" itemprop="description"><?php echo esc_html( $summary ); ?></p>
						<?php endif; ?>
						<dl class="gi-dossier__meta">
							<div><dt><?php esc_html_e( 'Published', 'the-grid-index' ); ?></dt>
								<dd><time datetime="<?php echo esc_attr( $pub_iso ); ?>" itemprop="datePublished"><?php echo esc_html( $pub_h ); ?> · <?php echo esc_html( $ago ); ?> <?php esc_html_e( 'ago', 'the-grid-index' ); ?></time></dd></div>
							<?php if ( $mod_iso !== $pub_iso ) : ?>
								<div><dt><?php esc_html_e( 'Updated', 'the-grid-index' ); ?></dt>
									<dd><time datetime="<?php echo esc_attr( $mod_iso ); ?>" itemprop="dateModified"><?php echo esc_html( $mod_h ); ?></time></dd></div>
							<?php endif; ?>
						</dl>
					</div>
				</header>

				<?php if ( $is_imported && $src_url && $show_cta ) : ?>
					<aside class="gi-source-cta gi-source-cta--top" role="complementary">
						<div>
							<span class="gi-source-cta__eyebrow"><?php esc_html_e( 'Original reporting', 'the-grid-index' ); ?></span>
							<p class="gi-source-cta__title">
								<?php
								/* translators: %s: source name */
								printf( esc_html__( 'This story was imported from %s via RSS.', 'the-grid-index' ), '<strong>' . esc_html( $src_name ?: __( 'source', 'the-grid-index' ) ) . '</strong>' );
								?>
							</p>
						</div>
						<a class="gi-btn gi-btn--primary" href="<?php echo esc_url( $src_url ); ?>" target="_blank" rel="noopener noreferrer nofollow">
							<?php
							/* translators: %s: source name */
							printf( esc_html__( 'Read full story at %s', 'the-grid-index' ), esc_html( $src_name ?: __( 'source', 'the-grid-index' ) ) );
							?> <span aria-hidden="true">↗</span>
						</a>
					</aside>
				<?php elseif ( $is_imported && ! $src_url && current_user_can( 'manage_options' ) ) : ?>
					<aside class="gi-admin-warn">
						<strong><?php esc_html_e( 'Admin warning:', 'the-grid-index' ); ?></strong>
						<?php esc_html_e( 'Imported post missing _gridindex_source_url meta.', 'the-grid-index' ); ?>
					</aside>
				<?php endif; ?>

				<div class="gi-dossier__body" itemprop="articleBody">
					<?php echo apply_filters( 'the_content', $post->post_content ); ?>
				</div>

				<?php /* v1.10.5: removed duplicate "Continue reading" CTA — only the top "Original reporting" module renders. */ ?>

				<?php
				/* Related stories — same category, exclude current */
				if ( $cat ) :
					$rel_q = new WP_Query( array(
						'cat' => $cat->term_id, 'posts_per_page' => 4,
						'post__not_in' => array( $post_id ),
						'no_found_rows' => true, 'ignore_sticky_posts' => true,
					) );
					if ( $rel_q->have_posts() ) : ?>
						<section class="gi-dossier__related">
							<header class="gi-section__head">
								<h2 class="gi-section__title"><?php esc_html_e( 'Related Signals', 'the-grid-index' ); ?></h2>
								<a class="gi-mono" href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( sprintf( __( 'More in %s →', 'the-grid-index' ), $cat->name ) ); ?></a>
							</header>
							<div class="gi-secondary">
								<?php while ( $rel_q->have_posts() ) : $rel_q->the_post(); ?>
									<article class="gi-card">
										<?php if ( function_exists( 'gip_card_thumb' ) ) gip_card_thumb(); ?>
										<div class="gi-card__body">
											<div class="gi-card__meta">
												<?php if ( function_exists( 'gip_render_signal_badge' ) ) gip_render_signal_badge( get_the_ID() ); ?>
												<?php $cc = get_the_category(); if ( ! empty( $cc ) ) echo '<span class="gi-kicker">' . esc_html( $cc[0]->name ) . '</span>'; ?>
											</div>
											<h3 class="gi-card__title">
												<?php if ( function_exists( 'gip_card_title_link' ) ) gip_card_title_link();
												else echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'; ?>
											</h3>
											<div class="gi-card__foot">
												<?php if ( function_exists( 'gip_render_card_meta_line' ) ) gip_render_card_meta_line(); ?>
												<?php if ( function_exists( 'gip_render_source_button' ) ) gip_render_source_button(); ?>
											</div>
										</div>
									</article>
								<?php endwhile; ?>
							</div>
						</section>
					<?php endif;
					wp_reset_postdata();
				endif;
				?>

				<?php
				if ( ! ( $is_imported && $hide_comm ) && comments_open( $post_id ) && ! post_password_required( $post_id ) ) {
					echo '<div class="gi-dossier__comments">';
					comments_template();
					echo '</div>';
				}
				?>
			</article>

			<aside class="gi-dossier__rail" role="complementary" aria-label="<?php esc_attr_e( 'Story intelligence', 'the-grid-index' ); ?>">

				<?php if ( $src_name || $src_url ) : ?>
					<section class="gi-rail__block gi-rail__source">
						<h2 class="gi-rail__title"><?php esc_html_e( 'Source', 'the-grid-index' ); ?></h2>
						<div class="gi-rail__source-name"><?php echo esc_html( $src_name ?: __( 'External source', 'the-grid-index' ) ); ?></div>
						<?php if ( $src_url ) : ?>
							<a class="gi-btn gi-btn--ghost" href="<?php echo esc_url( $src_url ); ?>" target="_blank" rel="noopener noreferrer nofollow">
								<?php esc_html_e( 'Read original', 'the-grid-index' ); ?> <span aria-hidden="true">↗</span>
							</a>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="gi-rail__block">
					<h2 class="gi-rail__title"><?php esc_html_e( 'Story Metadata', 'the-grid-index' ); ?></h2>
					<dl class="gi-rail__dl">
						<div><dt><?php esc_html_e( 'Status', 'the-grid-index' ); ?></dt><dd><?php echo $is_imported ? esc_html__( 'Imported · RSS', 'the-grid-index' ) : esc_html__( 'Native', 'the-grid-index' ); ?></dd></div>
						<?php if ( $cat ) : ?>
							<div><dt><?php esc_html_e( 'Category', 'the-grid-index' ); ?></dt><dd><a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></dd></div>
						<?php endif; ?>
						<div><dt><?php esc_html_e( 'Published', 'the-grid-index' ); ?></dt><dd><?php echo esc_html( $pub_h ); ?></dd></div>
						<?php if ( $mod_iso !== $pub_iso ) : ?>
							<div><dt><?php esc_html_e( 'Updated', 'the-grid-index' ); ?></dt><dd><?php echo esc_html( $mod_h ); ?></dd></div>
						<?php endif; ?>
					</dl>
				</section>

				<?php if ( $cat ) :
					$same_q = new WP_Query( array(
						'cat' => $cat->term_id, 'posts_per_page' => 6,
						'post__not_in' => array( $post_id ),
						'no_found_rows' => true, 'ignore_sticky_posts' => true,
					) );
					if ( $same_q->have_posts() ) : ?>
						<section class="gi-rail__block">
							<h2 class="gi-rail__title"><?php echo esc_html( sprintf( __( 'More in %s', 'the-grid-index' ), $cat->name ) ); ?></h2>
							<ol class="gi-rail__list">
								<?php $i = 1; while ( $same_q->have_posts() ) : $same_q->the_post(); ?>
									<li>
										<span class="gi-rail__num"><?php echo esc_html( str_pad( $i++, 2, '0', STR_PAD_LEFT ) ); ?></span>
										<div>
											<a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
											<div class="gi-rail__sub"><?php echo esc_html( human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'the-grid-index' ); ?></div>
										</div>
									</li>
								<?php endwhile; ?>
							</ol>
						</section>
					<?php endif;
					wp_reset_postdata();
				endif; ?>

				<?php
				$recent_q = new WP_Query( array(
					'posts_per_page' => 6, 'post__not_in' => array( $post_id ),
					'no_found_rows' => true, 'ignore_sticky_posts' => true,
				) );
				if ( $recent_q->have_posts() ) : ?>
					<section class="gi-rail__block">
						<h2 class="gi-rail__title"><?php esc_html_e( 'Most Recent Signals', 'the-grid-index' ); ?></h2>
						<ol class="gi-rail__list">
							<?php $i = 1; while ( $recent_q->have_posts() ) : $recent_q->the_post(); ?>
								<li>
									<span class="gi-rail__num"><?php echo esc_html( str_pad( $i++, 2, '0', STR_PAD_LEFT ) ); ?></span>
									<div>
										<a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
										<div class="gi-rail__sub"><?php echo esc_html( human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'the-grid-index' ); ?></div>
									</div>
								</li>
							<?php endwhile; ?>
						</ol>
					</section>
				<?php endif; wp_reset_postdata(); ?>

				<a class="gi-rail__home" href="<?php echo esc_url( home_url( '/' ) ); ?>">← <?php esc_html_e( 'Back to homepage', 'the-grid-index' ); ?></a>
			</aside>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
endif;

/* ============================================================
 * Block category — kept for editor compatibility if the renderer
 * is ever invoked from a custom plugin or external block.
 * ============================================================ */
add_filter( 'block_categories_all', function( $cats ) {
	foreach ( $cats as $c ) {
		if ( isset( $c['slug'] ) && $c['slug'] === 'grid-index' ) return $cats;
	}
	$cats[] = array(
		'slug'  => 'grid-index',
		'title' => __( 'The Grid Index', 'the-grid-index' ),
	);
	return $cats;
} );
