<?php
/**
 * The Grid Index — Comment template.
 *
 * Themed replacement for WordPress's default comment rendering, scoped to
 * .gi-comments so styles don't leak. Loaded automatically by
 * comments_template() — no other wiring needed.
 *
 * @package The_Grid_Index
 * @since   1.10.20
 */

defined( 'ABSPATH' ) || exit;

// If the post is password-protected and the visitor hasn't entered the password.
if ( post_password_required() ) return;

$gip_comment_count = (int) get_comments_number();
?>

<section id="comments" class="gi-comments" aria-label="<?php esc_attr_e( 'Comments', 'the-grid-index' ); ?>">

	<header class="gi-comments__head">
		<h2 class="gi-comments__title">
			<?php
			if ( $gip_comment_count === 0 ) {
				esc_html_e( 'Discussion', 'the-grid-index' );
			} else {
				printf(
					esc_html(
						/* translators: %s: comment count */
						_n( '%s response', '%s responses', $gip_comment_count, 'the-grid-index' )
					),
					esc_html( number_format_i18n( $gip_comment_count ) )
				);
			}
			?>
		</h2>
		<p class="gi-comments__sub">
			<?php esc_html_e( 'Add to the record. Comments are moderated.', 'the-grid-index' ); ?>
		</p>
	</header>

	<?php if ( have_comments() ) : ?>
		<ol class="gi-comments__list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 44,
				'callback'    => 'gip_comment_callback',
			) );
			?>
		</ol>

		<?php
		$prev = get_previous_comments_link( esc_html__( '← Older', 'the-grid-index' ) );
		$next = get_next_comments_link( esc_html__( 'Newer →', 'the-grid-index' ) );
		if ( $prev || $next ) : ?>
			<nav class="gi-comments__pagination" aria-label="<?php esc_attr_e( 'Comment pagination', 'the-grid-index' ); ?>">
				<div class="gi-comments__pagination-prev"><?php echo $prev; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="gi-comments__pagination-next"><?php echo $next; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="gi-comments__closed"><?php esc_html_e( 'Comments are closed on this story.', 'the-grid-index' ); ?></p>
	<?php endif; ?>

	<?php
	if ( comments_open() ) {
		$current_user = wp_get_current_user();
		$user_logged  = is_user_logged_in();
		$req          = get_option( 'require_name_email' );
		$req_attr     = $req ? ' required' : '';
		$req_mark     = $req ? ' <span class="gi-comments__req" aria-hidden="true">*</span>' : '';

		comment_form( array(
			'class_form'           => 'gi-comments__form',
			'class_submit'         => 'gi-btn gi-btn--primary gi-comments__submit',
			'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
			'submit_field'         => '<div class="gi-comments__actions">%1$s %2$s</div>',
			'title_reply'          => esc_html__( 'Leave a Reply', 'the-grid-index' ),
			'title_reply_to'       => esc_html__( 'Reply to %s', 'the-grid-index' ),
			'title_reply_before'   => '<h3 id="reply-title" class="gi-comments__form-title">',
			'title_reply_after'    => '</h3>',
			'cancel_reply_before'  => ' <small class="gi-comments__cancel">',
			'cancel_reply_after'   => '</small>',
			'cancel_reply_link'    => esc_html__( 'cancel', 'the-grid-index' ),
			'label_submit'         => esc_html__( 'Post Comment', 'the-grid-index' ),
			'logged_in_as'         => '<p class="gi-comments__logged">' . sprintf(
				/* translators: 1: user display name, 2: edit profile URL, 3: logout URL */
				wp_kses(
					__( 'Posting as <strong>%1$s</strong>. <a href="%2$s">Edit profile</a> · <a href="%3$s">Log out</a>', 'the-grid-index' ),
					array( 'strong' => array(), 'a' => array( 'href' => array() ) )
				),
				esc_html( $user_logged ? $current_user->display_name : '' ),
				esc_url( get_edit_profile_url() ),
				esc_url( wp_logout_url( apply_filters( 'the_permalink', get_permalink() ) ) )
			) . '</p>',
			'comment_notes_before' => '<p class="gi-comments__notes">' . esc_html__( 'Your email is never published. Required fields are marked with *.', 'the-grid-index' ) . '</p>',
			'comment_notes_after'  => '',
			'comment_field'        => '<div class="gi-comments__field gi-comments__field--full">'
				. '<label for="comment" class="gi-comments__label">' . esc_html__( 'Comment', 'the-grid-index' ) . ' <span class="gi-comments__req" aria-hidden="true">*</span></label>'
				. '<textarea id="comment" name="comment" class="gi-input gi-textarea gi-comments__textarea" cols="45" rows="6" maxlength="65525" required placeholder="' . esc_attr__( 'Write your reply…', 'the-grid-index' ) . '"></textarea>'
				. '</div>',
			'fields'               => array(
				'author' => '<div class="gi-comments__field"><label for="author" class="gi-comments__label">' . esc_html__( 'Name', 'the-grid-index' ) . $req_mark . '</label>'
					. '<input id="author" name="author" type="text" class="gi-input gi-comments__input" value="' . esc_attr( wp_get_current_commenter()['comment_author'] ?? '' ) . '" maxlength="245"' . $req_attr . ' /></div>',
				'email'  => '<div class="gi-comments__field"><label for="email" class="gi-comments__label">' . esc_html__( 'Email', 'the-grid-index' ) . $req_mark . '</label>'
					. '<input id="email" name="email" type="email" class="gi-input gi-comments__input" value="' . esc_attr( wp_get_current_commenter()['comment_author_email'] ?? '' ) . '" maxlength="100" aria-describedby="email-notes"' . $req_attr . ' /></div>',
				'url'    => '<div class="gi-comments__field"><label for="url" class="gi-comments__label">' . esc_html__( 'Website', 'the-grid-index' ) . '</label>'
					. '<input id="url" name="url" type="url" class="gi-input gi-comments__input" value="' . esc_attr( wp_get_current_commenter()['comment_author_url'] ?? '' ) . '" maxlength="200" /></div>',
			),
		) );
	}
	?>

</section>

<?php
/**
 * Per-comment renderer.
 */
if ( ! function_exists( 'gip_comment_callback' ) ) :
function gip_comment_callback( $comment, $args, $depth ) {
	$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
	?>
	<<?php echo esc_attr( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( 'gi-comment' ); ?>>
		<article class="gi-comment__inner">
			<header class="gi-comment__head">
				<div class="gi-comment__avatar"><?php echo get_avatar( $comment, $args['avatar_size'] ); ?></div>
				<div class="gi-comment__byline">
					<span class="gi-comment__author"><?php echo get_comment_author_link(); // phpcs:ignore ?></span>
					<span class="gi-comment__time">
						<a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
							<?php
							/* translators: %s: human-readable time */
							printf( esc_html__( '%s ago', 'the-grid-index' ),
								esc_html( human_time_diff( get_comment_time( 'U' ), current_time( 'timestamp' ) ) )
							);
							?>
						</a>
					</span>
				</div>
			</header>

			<div class="gi-comment__body">
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<p class="gi-comment__pending"><em><?php esc_html_e( 'Awaiting moderation.', 'the-grid-index' ); ?></em></p>
				<?php endif; ?>
				<?php comment_text(); ?>
			</div>

			<footer class="gi-comment__foot">
				<?php
				comment_reply_link( array_merge( $args, array(
					'add_below'  => 'comment',
					'depth'      => $depth,
					'max_depth'  => $args['max_depth'],
					'reply_text' => esc_html__( 'Reply ↩', 'the-grid-index' ),
					'before'     => '<span class="gi-comment__reply">',
					'after'      => '</span>',
				) ) );
				edit_comment_link( esc_html__( 'Edit', 'the-grid-index' ), '<span class="gi-comment__edit">', '</span>' );
				?>
			</footer>
		</article>
	<?php
	// Note: WP closes the </li> automatically (or whatever tag).
}
endif;
