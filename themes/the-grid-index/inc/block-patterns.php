<?php
/**
 * Block patterns for The Grid Index.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

function gip_register_pattern_categories() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'the-grid-index', array(
			'label' => esc_html__( 'The Grid Index', 'the-grid-index' ),
		) );
	}
}
add_action( 'init', 'gip_register_pattern_categories' );

function gip_register_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern( 'the-grid-index/hero-lead', array(
		'title'       => esc_html__( 'Hero Lead Story', 'the-grid-index' ),
		'description' => esc_html__( 'Large lead story with headline, deck, and image.', 'the-grid-index' ),
		'categories'  => array( 'the-grid-index', 'featured' ),
		'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:heading {"level":1,"fontSize":"xx-large"} --><h1 class="wp-block-heading has-xx-large-font-size">' . esc_html__( 'Today\'s lead story headline goes here', 'the-grid-index' ) . '</h1><!-- /wp:heading --><!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">' . esc_html__( 'A short editorial deck that summarises the story in one or two sentences.', 'the-grid-index' ) . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
	) );

	register_block_pattern( 'the-grid-index/category-band', array(
		'title'       => esc_html__( 'Category Band', 'the-grid-index' ),
		'description' => esc_html__( 'A horizontal band of three category cards.', 'the-grid-index' ),
		'categories'  => array( 'the-grid-index' ),
		'content'     => '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'AI', 'the-grid-index' ) . '</h3><!-- /wp:heading --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'Tech', 'the-grid-index' ) . '</h3><!-- /wp:heading --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'Startups', 'the-grid-index' ) . '</h3><!-- /wp:heading --></div><!-- /wp:column --></div><!-- /wp:columns -->',
	) );

	/* ============================================================
	 * Premium editorial patterns built from native Grid Index blocks.
	 * ============================================================ */

	$patterns = array(
		'bloomberg-hero' => array(
			'title' => __( 'Bloomberg Hero', 'the-grid-index' ),
			'desc'  => __( 'Cinematic hero + signals rail.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:grid-index/breaking-ticker /-->' .
				'<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">' .
					'<!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%">' .
						'<!-- wp:grid-index/hero {"count":5} /-->' .
						'<!-- wp:grid-index/top-stories {"count":6} /-->' .
					'</div><!-- /wp:column -->' .
					'<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">' .
						'<!-- wp:grid-index/intelligence-rail {"count":8,"bg":"panel"} /-->' .
						'<!-- wp:grid-index/source-cluster {"count":8} /-->' .
					'</div><!-- /wp:column -->' .
				'</div><!-- /wp:columns -->',
		),
		'semafor-rail' => array(
			'title' => __( 'Semafor Rail', 'the-grid-index' ),
			'desc'  => __( 'Editor picks + intelligence rail.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">' .
					'<!-- wp:column {"width":"66%"} --><div class="wp-block-column" style="flex-basis:66%">' .
						'<!-- wp:grid-index/editorial-picks {"count":4,"card":"editorial"} /-->' .
						'<!-- wp:grid-index/latest-feed {"count":12,"density":"compact"} /-->' .
					'</div><!-- /wp:column -->' .
					'<!-- wp:column {"width":"34%"} --><div class="wp-block-column" style="flex-basis:34%">' .
						'<!-- wp:grid-index/signals-panel {"count":6,"bg":"panel"} /-->' .
						'<!-- wp:grid-index/newsletter-cta {"bg":"accent"} /-->' .
					'</div><!-- /wp:column -->' .
				'</div><!-- /wp:columns -->',
		),
		'reuters-grid' => array(
			'title' => __( 'Reuters Grid', 'the-grid-index' ),
			'desc'  => __( 'Top stories grid + latest feed.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:grid-index/top-stories {"count":9,"card":"editorial","align":"wide"} /-->' .
				'<!-- wp:grid-index/latest-feed {"count":15,"density":"compact","align":"wide"} /-->',
		),
		'signal-dashboard' => array(
			'title' => __( 'Signal Dashboard', 'the-grid-index' ),
			'desc'  => __( 'Signals + trending + sources.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">' .
					'<!-- wp:column --><div class="wp-block-column">' .
						'<!-- wp:grid-index/signals-panel {"count":6} /-->' .
					'</div><!-- /wp:column -->' .
					'<!-- wp:column --><div class="wp-block-column">' .
						'<!-- wp:grid-index/trending-stories {"count":6} /-->' .
					'</div><!-- /wp:column -->' .
					'<!-- wp:column --><div class="wp-block-column">' .
						'<!-- wp:grid-index/source-cluster {"count":10} /-->' .
					'</div><!-- /wp:column -->' .
				'</div><!-- /wp:columns -->',
		),
		'ai-topic-dashboard' => array(
			'title' => __( 'AI Topic Dashboard', 'the-grid-index' ),
			'desc'  => __( 'AI-focused dashboard layout.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:grid-index/hero {"count":4} /-->' .
				'<!-- wp:grid-index/ai-summary {"bg":"panel"} /-->' .
				'<!-- wp:grid-index/category-band {"count":6} /-->',
		),
		'breaking-layout' => array(
			'title' => __( 'Breaking Layout', 'the-grid-index' ),
			'desc'  => __( 'Breaking ticker + live slider.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:grid-index/breaking-ticker /-->' .
				'<!-- wp:grid-index/live-slider {"count":6} /-->' .
				'<!-- wp:grid-index/signals-panel {"count":6} /-->',
		),
		'feature-story-stack' => array(
			'title' => __( 'Feature Story Stack', 'the-grid-index' ),
			'desc'  => __( 'Feature-driven editorial stack.', 'the-grid-index' ),
			'content' =>
				'<!-- wp:grid-index/editorial-picks {"count":3,"card":"cinematic"} /-->' .
				'<!-- wp:grid-index/top-stories {"count":6} /-->' .
				'<!-- wp:grid-index/latest-feed {"count":10,"density":"compact"} /-->',
		),
	);

	foreach ( $patterns as $slug => $p ) {
		register_block_pattern( 'the-grid-index/' . $slug, array(
			'title'       => $p['title'],
			'description' => $p['desc'],
			'categories'  => array( 'the-grid-index' ),
			'content'     => $p['content'],
		) );
	}
}
add_action( 'init', 'gip_register_block_patterns' );
