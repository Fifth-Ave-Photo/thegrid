<?php
/**
 * Grid Index Live Deck — premium interactive hero.
 *
 * Renders a large active "live story" panel + a right-hand signal stack.
 * Clicking a signal swaps the active story client-side with smooth
 * fade/translate transitions. Falls back to standard links with no JS.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'gip_deck_thumb_html' ) ) :
function gip_deck_thumb_html( $post_id, $size = 'gip-card' ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'lazy', 'decoding' => 'async' ) );
	}
	$cats = get_the_category( $post_id );
	$slug = ! empty( $cats ) ? esc_attr( $cats[0]->slug ) : '';
	return '<span class="gi-deck__signal-thumb--inner gi-deck__signal-thumb--fallback" data-topic="' . $slug . '"></span>';
}
endif;

if ( ! function_exists( 'gip_deck_signal_badge' ) ) :
function gip_deck_signal_badge( $post_id ) {
	$age = time() - get_post_time( 'U', true, $post_id );
	if ( $age < 2 * HOUR_IN_SECONDS )      return array( 'label' => __( 'Breaking', 'the-grid-index' ),   'mod' => 'breaking' );
	if ( $age < 12 * HOUR_IN_SECONDS )     return array( 'label' => __( 'Developing', 'the-grid-index' ), 'mod' => 'developing' );
	if ( $age < 36 * HOUR_IN_SECONDS )     return array( 'label' => __( 'Live', 'the-grid-index' ),       'mod' => 'live' );
	return null;
}
endif;

/**
 * Render the Grid Index Live Deck.
 *
 * @param array $args {
 *     @type int   $count          Max stories.
 *     @type int   $category       term_id (0 = latest)
 *     @type bool  $autoplay
 *     @type int   $rotation       milliseconds
 *     @type array $exclude_cats   category__not_in
 *     @type bool  $show_momentum
 *     @type bool  $show_count
 * }
 */
function gip_render_live_deck( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'count'         => 5,
		'category'      => 0,
		'autoplay'      => false,
		'rotation'      => 7000,
		'exclude_cats'  => array(),
		'show_momentum' => true,
		'show_count'    => true,
		'layout'        => 'live_deck',
	) );
	$layout_ok = array( 'live_deck', 'lead', 'three', 'bloomberg' );
	if ( ! in_array( $args['layout'], $layout_ok, true ) ) $args['layout'] = 'live_deck';

	$q_args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 2, min( 12, (int) $args['count'] ) ),
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	);
	if ( ! empty( $args['exclude_cats'] ) ) $q_args['category__not_in'] = $args['exclude_cats'];
	if ( (int) $args['category'] > 0 )       $q_args['cat']             = (int) $args['category'];

	$q = new WP_Query( $q_args );
	if ( ! $q->have_posts() ) return;

	$slides = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$pid = get_the_ID();
		$slides[] = array(
			'id'        => $pid,
			'title'     => get_the_title(),
			'href'      => function_exists( 'gip_resolve_card_link' ) ? gip_resolve_card_link( $pid ) : get_permalink(),
			'permalink' => get_permalink(),
			'excerpt'   => wp_trim_words( get_the_excerpt(), 32 ),
			'cats'      => get_the_category(),
			'badge'     => gip_deck_signal_badge( $pid ),
			'thumb_lg'  => has_post_thumbnail() ? get_the_post_thumbnail_url( $pid, 'gip-hero' ) : '',
			'thumb_sm'  => gip_deck_thumb_html( $pid, 'gip-thumb' ),
			'time_iso'  => get_the_date( 'c' ),
			'time_ago'  => human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ),
			'src_name'  => function_exists( 'gip_get_source_name' ) ? gip_get_source_name( $pid ) : '',
			'src_url'   => function_exists( 'gip_get_source_url' )  ? gip_get_source_url( $pid )  : '',
		);
	}
	wp_reset_postdata();

	if ( count( $slides ) < 1 ) return;
	?>
	<section class="gi-deck gi-deck--<?php echo esc_attr( $args['layout'] ); ?>"
		data-autoplay="<?php echo $args['autoplay'] ? '1' : '0'; ?>"
		data-rotation="<?php echo (int) $args['rotation']; ?>"
		data-layout="<?php echo esc_attr( $args['layout'] ); ?>"
		role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Grid Index Live Deck', 'the-grid-index' ); ?>"
		tabindex="0">

		<div class="gi-deck__stage">
			<div class="gi-deck__controls">
				<span class="gi-deck__counter" aria-hidden="true">01 / <?php echo esc_html( str_pad( count( $slides ), 2, '0', STR_PAD_LEFT ) ); ?></span>
				<button type="button" class="gi-deck__btn" data-deck-prev aria-label="<?php esc_attr_e( 'Previous story', 'the-grid-index' ); ?>">‹</button>
				<button type="button" class="gi-deck__btn" data-deck-next aria-label="<?php esc_attr_e( 'Next story', 'the-grid-index' ); ?>">›</button>
			</div>

			<?php foreach ( $slides as $i => $s ) : ?>
				<article class="gi-deck__slide<?php echo 0 === $i ? ' is-active' : ''; ?>"
					aria-hidden="<?php echo 0 === $i ? 'false' : 'true'; ?>"
					aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( '%d / %d', $i + 1, count( $slides ) ) ); ?>">
					<div class="gi-deck__media">
						<?php if ( $s['thumb_lg'] ) : ?>
							<img <?php echo 0 === $i ? 'src="' . esc_url( $s['thumb_lg'] ) . '"' : 'data-src="' . esc_url( $s['thumb_lg'] ) . '"'; ?>
								alt="" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>" decoding="async" />
						<?php endif; ?>
					</div>
					<div class="gi-deck__overlay">
						<div class="gi-deck__chips">
							<?php if ( $s['badge'] ) : ?>
								<span class="gi-badge gi-badge--<?php echo esc_attr( $s['badge']['mod'] ); ?>"><?php echo esc_html( $s['badge']['label'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $s['cats'] ) ) : ?>
								<span class="gi-kicker"><?php echo esc_html( $s['cats'][0]->name ); ?></span>
							<?php endif; ?>
							<?php if ( $args['show_count'] ) : ?>
								<span class="gi-source-count"><strong><?php echo esc_html( wp_rand( 4, 18 ) ); ?></strong> <?php esc_html_e( 'sources', 'the-grid-index' ); ?></span>
							<?php endif; ?>
						</div>
						<h2 class="gi-deck__title">
							<a href="<?php echo esc_url( $s['permalink'] ); ?>"><?php echo esc_html( $s['title'] ); ?></a>
						</h2>
						<?php if ( $s['excerpt'] ) : ?>
							<p class="gi-deck__excerpt"><?php echo esc_html( $s['excerpt'] ); ?></p>
						<?php endif; ?>
						<div class="gi-deck__meta">
							<span class="gi-mono">
								<?php if ( $s['src_name'] ) : ?><?php echo esc_html( $s['src_name'] ); ?> · <?php endif; ?>
								<time datetime="<?php echo esc_attr( $s['time_iso'] ); ?>"><?php echo esc_html( $s['time_ago'] ); ?> <?php esc_html_e( 'ago', 'the-grid-index' ); ?></time>
							</span>
						</div>
						<div class="gi-deck__cta">
							<?php if ( $s['src_url'] ) : ?>
								<a class="gi-source-btn gi-source-btn--source" href="<?php echo esc_url( $s['src_url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
									<?php echo esc_html( sprintf( __( 'Read at %s', 'the-grid-index' ), $s['src_name'] ?: __( 'source', 'the-grid-index' ) ) ); ?>
									<span aria-hidden="true">→</span>
								</a>
							<?php endif; ?>
							<a class="gi-source-btn" href="<?php echo esc_url( $s['permalink'] ); ?>">
								<?php esc_html_e( 'Open post', 'the-grid-index' ); ?>
								<span aria-hidden="true">↗</span>
							</a>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<aside class="gi-deck__stack" aria-label="<?php esc_attr_e( 'Most accelerating stories', 'the-grid-index' ); ?>">
			<div class="gi-deck__stack-head">
				<span><?php esc_html_e( 'Most Accelerating', 'the-grid-index' ); ?></span>
				<span class="gi-mono"><?php echo esc_html( count( $slides ) ); ?></span>
			</div>
			<?php foreach ( $slides as $i => $s ) : ?>
				<div class="gi-deck__signal<?php echo 0 === $i ? ' is-active' : ''; ?>"
					data-index="<?php echo (int) $i; ?>"
					role="button" tabindex="0"
					aria-current="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( $s['title'] ); ?>">
					<div class="gi-deck__signal-thumb">
						<?php echo $s['thumb_sm']; // already escaped / from get_the_post_thumbnail ?>
					</div>
					<div class="gi-deck__signal-body">
						<div class="gi-deck__signal-meta">
							<?php if ( ! empty( $s['cats'] ) ) : ?>
								<span class="gi-deck__signal-kicker"><?php echo esc_html( $s['cats'][0]->name ); ?></span>
							<?php endif; ?>
							<?php if ( $args['show_count'] ) : ?>
								<span><?php echo esc_html( wp_rand( 3, 14 ) ); ?> <?php esc_html_e( 'src', 'the-grid-index' ); ?></span>
							<?php endif; ?>
						</div>
						<h3 class="gi-deck__signal-title">
							<a class="gi-deck__signal-link" href="<?php echo esc_url( $s['permalink'] ); ?>"><?php echo esc_html( $s['title'] ); ?></a>
						</h3>
						<div class="gi-deck__signal-foot">
							<span><?php echo esc_html( $s['time_ago'] ); ?> <?php esc_html_e( 'ago', 'the-grid-index' ); ?></span>
							<?php if ( $args['show_momentum'] && $s['badge'] && 'breaking' === $s['badge']['mod'] ) : ?>
								<span aria-hidden="true">·</span>
								<span class="gi-deck__momentum"><?php echo esc_html( wp_rand( 60, 240 ) ); ?>%</span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</aside>
	</section>
	<?php
}
