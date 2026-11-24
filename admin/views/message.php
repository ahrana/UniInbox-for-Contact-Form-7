<?php
/**
 * Single message view: message, conversation thread, composer and sidebar.
 *
 * @package UniInbox_For_CF7
 *
 * @var array   $message   Message row.
 * @var array[] $replies   Replies and notes, oldest first.
 * @var array   $neighbors Newer/older message IDs.
 */

defined( 'ABSPATH' ) || exit;

$uicf7_id        = (int) $message['id'];
$uicf7_extracted = json_decode( (string) $message['extracted'], true );
$uicf7_extracted = is_array( $uicf7_extracted ) ? $uicf7_extracted : array();
$uicf7_fields    = json_decode( (string) $message['field_data'], true );
$uicf7_fields    = is_array( $uicf7_fields ) ? $uicf7_fields : array();
$uicf7_name      = '' !== $message['sender_name'] ? $message['sender_name'] : ( '' !== $message['sender_email'] ? $message['sender_email'] : __( 'Unknown sender', 'uniinbox-for-contact-form-7' ) );
$uicf7_title     = '' !== $message['subject'] ? $message['subject'] : ( '' !== (string) $message['summary'] ? $message['summary'] : __( 'New message', 'uniinbox-for-contact-form-7' ) );
$uicf7_subject   = '' !== $message['subject'] ? 'Re: ' . $message['subject'] : __( 'Re: your message', 'uniinbox-for-contact-form-7' );
$uicf7_can_reply = (bool) is_email( $message['sender_email'] );
$uicf7_draft     = wp_kses( (string) $message['draft_reply'], UICF7_Mailer::allowed_html() );
$uicf7_ai_ready  = UICF7_Settings::ai_ready();
$uicf7_post_url  = admin_url( 'admin-post.php' );
$uicf7_reply_n   = 0;
foreach ( $replies as $uicf7_r ) {
	if ( 'reply' === $uicf7_r['type'] && 'sent' === $uicf7_r['status'] ) {
		++$uicf7_reply_n;
	}
}
$uicf7_labels  = array(
	'name'     => __( 'Name', 'uniinbox-for-contact-form-7' ),
	'company'  => __( 'Company', 'uniinbox-for-contact-form-7' ),
	'budget'   => __( 'Budget', 'uniinbox-for-contact-form-7' ),
	'deadline' => __( 'Deadline', 'uniinbox-for-contact-form-7' ),
	'topic'    => __( 'Topic', 'uniinbox-for-contact-form-7' ),
);
$uicf7_ai_text = array(
	'off'        => __( 'Not analyzed yet.', 'uniinbox-for-contact-form-7' ),
	'pending'    => __( 'Queued for AI analysis.', 'uniinbox-for-contact-form-7' ),
	'processing' => __( 'AI is analyzing this message.', 'uniinbox-for-contact-form-7' ),
	'done'       => __( 'Analyzed by AI.', 'uniinbox-for-contact-form-7' ),
	'failed'     => __( 'AI analysis failed.', 'uniinbox-for-contact-form-7' ),
	'skipped'    => __( 'AI analysis was skipped.', 'uniinbox-for-contact-form-7' ),
);

/**
 * Print the hidden fields every admin-post form on this screen needs.
 *
 * @param int $id Message ID.
 * @return void
 */
$uicf7_hidden = function ( $id ) {
	echo '<input type="hidden" name="action" value="uicf7_message_action">';
	echo '<input type="hidden" name="message" value="' . esc_attr( (string) $id ) . '">';
	wp_nonce_field( 'uicf7_message_' . $id );
};
?>
<div class="wrap uicf7-app uicf7-message-screen">
	<div class="uicf7-topbar">
		<a class="uicf7-btn uicf7-btn--ghost" href="<?php echo esc_url( UICF7_List_Table::base_url() ); ?>">
			<?php echo wp_kses( UICF7_UI::icon( 'arrow-left-alt2' ), UICF7_UI::allowed_html() ); ?>
			<?php esc_html_e( 'Inbox', 'uniinbox-for-contact-form-7' ); ?>
		</a>

		<div class="uicf7-topbar__actions">
			<button type="button" class="uicf7-btn uicf7-btn--icon uicf7-star<?php echo ! empty( $message['starred'] ) ? ' is-on' : ''; ?>" data-id="<?php echo esc_attr( (string) $uicf7_id ); ?>" aria-pressed="<?php echo ! empty( $message['starred'] ) ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Star message', 'uniinbox-for-contact-form-7' ); ?>">
				<?php echo wp_kses( UICF7_UI::icon( ! empty( $message['starred'] ) ? 'star-filled' : 'star-empty' ), UICF7_UI::allowed_html() ); ?>
			</button>

			<form method="post" action="<?php echo esc_url( $uicf7_post_url ); ?>" class="uicf7-inline-form">
				<?php $uicf7_hidden( $uicf7_id ); ?>
				<?php if ( 'spam' === $message['status'] ) : ?>
					<button type="submit" name="do" value="not_spam" class="uicf7-btn"><?php echo wp_kses( UICF7_UI::icon( 'undo' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Not spam', 'uniinbox-for-contact-form-7' ); ?></button>
				<?php else : ?>
					<button type="submit" name="do" value="unread" class="uicf7-btn"><?php echo wp_kses( UICF7_UI::icon( 'email' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Mark unread', 'uniinbox-for-contact-form-7' ); ?></button>
					<button type="submit" name="do" value="spam" class="uicf7-btn"><?php echo wp_kses( UICF7_UI::icon( 'dismiss' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Spam', 'uniinbox-for-contact-form-7' ); ?></button>
				<?php endif; ?>
				<button type="submit" name="do" value="delete" class="uicf7-btn uicf7-btn--danger uicf7-confirm-submit" data-confirm="<?php esc_attr_e( 'Delete this message permanently?', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'trash' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Delete', 'uniinbox-for-contact-form-7' ); ?></button>
			</form>

			<span class="uicf7-pager">
				<?php if ( $neighbors['newer'] ) : ?>
					<a class="uicf7-btn uicf7-btn--icon" href="<?php echo esc_url( UICF7_List_Table::message_url( $neighbors['newer'] ) ); ?>" aria-label="<?php esc_attr_e( 'Newer message', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Newer message', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'arrow-up-alt2' ), UICF7_UI::allowed_html() ); ?></a>
				<?php else : ?>
					<span class="uicf7-btn uicf7-btn--icon is-disabled" aria-hidden="true"><?php echo wp_kses( UICF7_UI::icon( 'arrow-up-alt2' ), UICF7_UI::allowed_html() ); ?></span>
				<?php endif; ?>
				<?php if ( $neighbors['older'] ) : ?>
					<a class="uicf7-btn uicf7-btn--icon" href="<?php echo esc_url( UICF7_List_Table::message_url( $neighbors['older'] ) ); ?>" aria-label="<?php esc_attr_e( 'Older message', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Older message', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'arrow-down-alt2' ), UICF7_UI::allowed_html() ); ?></a>
				<?php else : ?>
					<span class="uicf7-btn uicf7-btn--icon is-disabled" aria-hidden="true"><?php echo wp_kses( UICF7_UI::icon( 'arrow-down-alt2' ), UICF7_UI::allowed_html() ); ?></span>
				<?php endif; ?>
			</span>
		</div>
	</div>
	<hr class="wp-header-end">

	<div class="uicf7-msg-layout">
		<main class="uicf7-msg-main">

			<article class="uicf7-card uicf7-msg">
				<header class="uicf7-msg__head">
					<?php echo wp_kses( UICF7_UI::avatar( $message['sender_name'], $message['sender_email'], 'lg' ), UICF7_UI::allowed_html() ); ?>
					<div class="uicf7-msg__who">
						<h1 class="uicf7-msg__title"><?php echo esc_html( $uicf7_title ); ?></h1>
						<div class="uicf7-msg__meta">
							<strong><?php echo esc_html( $uicf7_name ); ?></strong>
							<?php if ( '' !== $message['sender_email'] && $message['sender_email'] !== $uicf7_name ) : ?>
								<span>&lt;<?php echo esc_html( $message['sender_email'] ); ?>&gt;</span>
							<?php endif; ?>
							<span class="uicf7-sep" aria-hidden="true">&middot;</span>
							<?php echo wp_kses( UICF7_UI::time_ago( $message['created_at'] ), UICF7_UI::allowed_html() ); ?>
						</div>
					</div>
					<div class="uicf7-msg__badges">
						<?php echo wp_kses( UICF7_UI::urgency_badge( $message['urgency'] ), UICF7_UI::allowed_html() ); ?>
						<?php echo wp_kses( UICF7_UI::category_badge( $message['category'] ), UICF7_UI::allowed_html() ); ?>
					</div>
				</header>

				<?php if ( '' !== (string) $message['summary'] ) : ?>
					<div class="uicf7-summary">
						<?php echo wp_kses( UICF7_UI::icon( 'lightbulb' ), UICF7_UI::allowed_html() ); ?>
						<div><span class="uicf7-summary__label"><?php esc_html_e( 'AI summary', 'uniinbox-for-contact-form-7' ); ?></span><?php echo esc_html( $message['summary'] ); ?></div>
					</div>
				<?php endif; ?>

				<div class="uicf7-msg__body"><?php echo wp_kses( nl2br( esc_html( UICF7_UI::message_text( $message ) ) ), array( 'br' => array() ) ); ?></div>

				<?php if ( ! empty( $uicf7_fields ) ) : ?>
					<details class="uicf7-fields">
						<summary>
							<?php
							/* translators: %d: number of form fields. */
							echo esc_html( sprintf( _n( '%d submitted field', 'All %d submitted fields', count( $uicf7_fields ), 'uniinbox-for-contact-form-7' ), count( $uicf7_fields ) ) );
							?>
						</summary>
						<table class="uicf7-kv">
							<?php foreach ( $uicf7_fields as $uicf7_key => $uicf7_value ) : ?>
								<tr>
									<th scope="row"><code><?php echo esc_html( (string) $uicf7_key ); ?></code></th>
									<td><?php echo wp_kses( nl2br( esc_html( is_scalar( $uicf7_value ) ? (string) $uicf7_value : wp_json_encode( $uicf7_value ) ) ), array( 'br' => array() ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					</details>
				<?php endif; ?>
			</article>

			<?php if ( ! empty( $replies ) ) : ?>
				<div class="uicf7-thread">
					<?php foreach ( $replies as $uicf7_reply ) : ?>
						<?php
						$uicf7_user   = get_userdata( (int) $uicf7_reply['user_id'] );
						$uicf7_author = $uicf7_user ? $uicf7_user->display_name : __( 'Someone', 'uniinbox-for-contact-form-7' );
						$uicf7_files  = json_decode( (string) $uicf7_reply['attachments'], true );
						$uicf7_files  = is_array( $uicf7_files ) ? $uicf7_files : array();
						$uicf7_note   = 'note' === $uicf7_reply['type'];
						?>
						<article class="uicf7-card uicf7-thread__item<?php echo esc_attr( $uicf7_note ? ' is-note' : ' is-reply' ); ?><?php echo esc_attr( 'failed' === $uicf7_reply['status'] ? ' is-failed' : '' ); ?>">
							<header class="uicf7-thread__head">
								<?php echo wp_kses( UICF7_UI::avatar( $uicf7_author, $uicf7_user ? $uicf7_user->user_email : '', 'sm' ), UICF7_UI::allowed_html() ); ?>
								<div class="uicf7-thread__who">
									<strong><?php echo esc_html( $uicf7_author ); ?></strong>
									<span class="uicf7-muted">
										<?php
										if ( $uicf7_note ) {
											esc_html_e( 'added an internal note', 'uniinbox-for-contact-form-7' );
										} elseif ( 'failed' === $uicf7_reply['status'] ) {
											esc_html_e( 'tried to send a reply (failed)', 'uniinbox-for-contact-form-7' );
										} else {
											/* translators: %s: recipient email address. */
											echo esc_html( sprintf( __( 'replied to %s', 'uniinbox-for-contact-form-7' ), $uicf7_reply['to_email'] ) );
										}
										?>
									</span>
								</div>
								<span class="uicf7-muted uicf7-thread__time"><?php echo wp_kses( UICF7_UI::time_ago( $uicf7_reply['created_at'] ), UICF7_UI::allowed_html() ); ?></span>
							</header>
							<?php if ( ! $uicf7_note && ( '' !== (string) $uicf7_reply['cc'] || '' !== (string) $uicf7_reply['bcc'] ) ) : ?>
								<p class="uicf7-thread__recipients uicf7-muted">
									<?php if ( '' !== (string) $uicf7_reply['cc'] ) : ?>
										<?php esc_html_e( 'CC:', 'uniinbox-for-contact-form-7' ); ?> <?php echo esc_html( $uicf7_reply['cc'] ); ?>
									<?php endif; ?>
									<?php if ( '' !== (string) $uicf7_reply['bcc'] ) : ?>
										<?php esc_html_e( 'BCC:', 'uniinbox-for-contact-form-7' ); ?> <?php echo esc_html( $uicf7_reply['bcc'] ); ?>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<?php if ( ! $uicf7_note && '' !== (string) $uicf7_reply['subject'] ) : ?>
								<p class="uicf7-thread__subject"><?php echo esc_html( $uicf7_reply['subject'] ); ?></p>
							<?php endif; ?>
							<div class="uicf7-thread__body"><?php echo wp_kses( $uicf7_reply['body'], UICF7_Mailer::allowed_html() + array( 'blockquote' => array() ) ); ?></div>
							<?php if ( ! empty( $uicf7_files ) ) : ?>
								<ul class="uicf7-files">
									<?php foreach ( $uicf7_files as $uicf7_file ) : ?>
										<?php $uicf7_url = isset( $uicf7_file['id'] ) ? wp_get_attachment_url( (int) $uicf7_file['id'] ) : ''; ?>
										<li class="uicf7-file">
											<?php echo wp_kses( UICF7_UI::icon( 'paperclip' ), UICF7_UI::allowed_html() ); ?>
											<?php if ( $uicf7_url ) : ?>
												<a href="<?php echo esc_url( $uicf7_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( isset( $uicf7_file['name'] ) ? $uicf7_file['name'] : '' ); ?></a>
											<?php else : ?>
												<span><?php echo esc_html( isset( $uicf7_file['name'] ) ? $uicf7_file['name'] : '' ); ?></span>
											<?php endif; ?>
											<?php if ( ! empty( $uicf7_file['size'] ) ) : ?>
												<span class="uicf7-muted"><?php echo esc_html( size_format( (int) $uicf7_file['size'] ) ); ?></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<span id="uicf7-thread-end"></span>

			<section class="uicf7-card uicf7-composer" id="uicf7-composer">
				<div class="uicf7-segmented" role="tablist" aria-label="<?php esc_attr_e( 'Composer mode', 'uniinbox-for-contact-form-7' ); ?>">
					<button type="button" role="tab" class="is-active" aria-selected="true" aria-controls="uicf7-reply-form" data-mode="reply"><?php echo wp_kses( UICF7_UI::icon( 'undo' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Reply', 'uniinbox-for-contact-form-7' ); ?></button>
					<button type="button" role="tab" aria-selected="false" aria-controls="uicf7-note-form" data-mode="note"><?php echo wp_kses( UICF7_UI::icon( 'edit' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Internal note', 'uniinbox-for-contact-form-7' ); ?></button>
				</div>

				<form method="post" action="<?php echo esc_url( $uicf7_post_url ); ?>" id="uicf7-reply-form" class="uicf7-compose" role="tabpanel">
					<?php $uicf7_hidden( $uicf7_id ); ?>

					<?php if ( ! $uicf7_can_reply ) : ?>
						<div class="uicf7-callout uicf7-callout--warn"><?php esc_html_e( 'This submission has no valid email address, so a reply cannot be emailed. You can still save notes.', 'uniinbox-for-contact-form-7' ); ?></div>
					<?php endif; ?>

					<div class="uicf7-compose__row">
						<span class="uicf7-compose__label"><?php esc_html_e( 'To', 'uniinbox-for-contact-form-7' ); ?></span>
						<span class="uicf7-recipient"><?php echo wp_kses( UICF7_UI::avatar( $message['sender_name'], $message['sender_email'], 'sm' ), UICF7_UI::allowed_html() ); ?><?php echo esc_html( $uicf7_can_reply ? $message['sender_email'] : __( 'No email address', 'uniinbox-for-contact-form-7' ) ); ?></span>
						<span class="uicf7-compose__more">
							<button type="button" class="uicf7-link" data-reveal="uicf7-cc-row"><?php esc_html_e( 'CC', 'uniinbox-for-contact-form-7' ); ?></button>
							<button type="button" class="uicf7-link" data-reveal="uicf7-bcc-row"><?php esc_html_e( 'BCC', 'uniinbox-for-contact-form-7' ); ?></button>
						</span>
					</div>
					<div class="uicf7-compose__row" id="uicf7-cc-row" hidden>
						<label class="uicf7-compose__label" for="uicf7-reply-cc"><?php esc_html_e( 'CC', 'uniinbox-for-contact-form-7' ); ?></label>
						<input type="text" id="uicf7-reply-cc" name="reply_cc" class="uicf7-compose__input" placeholder="<?php esc_attr_e( 'name@example.com, another@example.com', 'uniinbox-for-contact-form-7' ); ?>" autocomplete="off">
					</div>
					<div class="uicf7-compose__row" id="uicf7-bcc-row" hidden>
						<label class="uicf7-compose__label" for="uicf7-reply-bcc"><?php esc_html_e( 'BCC', 'uniinbox-for-contact-form-7' ); ?></label>
						<input type="text" id="uicf7-reply-bcc" name="reply_bcc" class="uicf7-compose__input" placeholder="<?php esc_attr_e( 'name@example.com', 'uniinbox-for-contact-form-7' ); ?>" autocomplete="off">
					</div>
					<div class="uicf7-compose__row">
						<label class="uicf7-compose__label" for="uicf7-reply-subject"><?php esc_html_e( 'Subject', 'uniinbox-for-contact-form-7' ); ?></label>
						<input type="text" id="uicf7-reply-subject" name="reply_subject" class="uicf7-compose__input" maxlength="200" value="<?php echo esc_attr( $uicf7_subject ); ?>">
					</div>

					<div class="uicf7-editor-wrap">
						<div class="uicf7-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'uniinbox-for-contact-form-7' ); ?>" hidden>
							<div class="uicf7-toolbar__group">
								<button type="button" class="uicf7-tool" data-cmd="bold" aria-label="<?php esc_attr_e( 'Bold', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Bold (Ctrl+B)', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-bold' ), UICF7_UI::allowed_html() ); ?></button>
								<button type="button" class="uicf7-tool" data-cmd="italic" aria-label="<?php esc_attr_e( 'Italic', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Italic (Ctrl+I)', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-italic' ), UICF7_UI::allowed_html() ); ?></button>
								<button type="button" class="uicf7-tool" data-cmd="underline" aria-label="<?php esc_attr_e( 'Underline', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Underline (Ctrl+U)', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-underline' ), UICF7_UI::allowed_html() ); ?></button>
							</div>
							<div class="uicf7-toolbar__group">
								<button type="button" class="uicf7-tool" data-cmd="insertUnorderedList" aria-label="<?php esc_attr_e( 'Bulleted list', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Bulleted list', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-ul' ), UICF7_UI::allowed_html() ); ?></button>
								<button type="button" class="uicf7-tool" data-cmd="insertOrderedList" aria-label="<?php esc_attr_e( 'Numbered list', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Numbered list', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-ol' ), UICF7_UI::allowed_html() ); ?></button>
								<button type="button" class="uicf7-tool" data-cmd="quote" aria-label="<?php esc_attr_e( 'Quote', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Quote', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-quote' ), UICF7_UI::allowed_html() ); ?></button>
								<button type="button" class="uicf7-tool" data-cmd="link" aria-label="<?php esc_attr_e( 'Insert link', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Insert link (Ctrl+K)', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'admin-links' ), UICF7_UI::allowed_html() ); ?></button>
								<button type="button" class="uicf7-tool" data-cmd="removeFormat" aria-label="<?php esc_attr_e( 'Clear formatting', 'uniinbox-for-contact-form-7' ); ?>" title="<?php esc_attr_e( 'Clear formatting', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'editor-removeformatting' ), UICF7_UI::allowed_html() ); ?></button>
							</div>
							<div class="uicf7-toolbar__group uicf7-toolbar__group--end">
								<details class="uicf7-menu">
									<summary class="uicf7-tool uicf7-tool--text"><?php echo wp_kses( UICF7_UI::icon( 'text-page' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Templates', 'uniinbox-for-contact-form-7' ); ?></summary>
									<div class="uicf7-menu__list" role="menu">
										<?php $uicf7_templates = UICF7_Settings::templates(); ?>
										<?php if ( empty( $uicf7_templates ) ) : ?>
											<span class="uicf7-menu__empty"><?php esc_html_e( 'No templates yet.', 'uniinbox-for-contact-form-7' ); ?></span>
										<?php endif; ?>
										<?php foreach ( $uicf7_templates as $uicf7_tpl ) : ?>
											<button type="button" role="menuitem" data-template="<?php echo esc_attr( $uicf7_tpl['id'] ); ?>"><?php echo esc_html( $uicf7_tpl['name'] ); ?></button>
										<?php endforeach; ?>
										<a class="uicf7-menu__footer" href="<?php echo esc_url( admin_url( 'admin.php?page=uicf7-settings#replies' ) ); ?>"><?php esc_html_e( 'Manage templates', 'uniinbox-for-contact-form-7' ); ?></a>
									</div>
								</details>
								<?php if ( $uicf7_ai_ready ) : ?>
									<details class="uicf7-menu">
										<summary class="uicf7-tool uicf7-tool--text uicf7-tool--ai"><?php echo wp_kses( UICF7_UI::icon( 'lightbulb' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'AI assist', 'uniinbox-for-contact-form-7' ); ?></summary>
										<div class="uicf7-menu__list" role="menu">
											<button type="button" role="menuitem" data-assist="regenerate"><?php esc_html_e( 'Write a new draft', 'uniinbox-for-contact-form-7' ); ?></button>
											<button type="button" role="menuitem" data-assist="shorter"><?php esc_html_e( 'Make it shorter', 'uniinbox-for-contact-form-7' ); ?></button>
											<button type="button" role="menuitem" data-assist="formal"><?php esc_html_e( 'More formal', 'uniinbox-for-contact-form-7' ); ?></button>
											<button type="button" role="menuitem" data-assist="friendly"><?php esc_html_e( 'Friendlier', 'uniinbox-for-contact-form-7' ); ?></button>
											<button type="button" role="menuitem" data-assist="fix"><?php esc_html_e( 'Fix spelling and grammar', 'uniinbox-for-contact-form-7' ); ?></button>
										</div>
									</details>
								<?php endif; ?>
							</div>
						</div>
						<div class="uicf7-linkbar" hidden>
							<label class="screen-reader-text" for="uicf7-link-url"><?php esc_html_e( 'Link address', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="url" id="uicf7-link-url" placeholder="https://" autocomplete="off">
							<button type="button" class="uicf7-btn uicf7-btn--primary uicf7-btn--sm" data-link="apply"><?php esc_html_e( 'Apply', 'uniinbox-for-contact-form-7' ); ?></button>
							<button type="button" class="uicf7-btn uicf7-btn--sm" data-link="cancel"><?php esc_html_e( 'Cancel', 'uniinbox-for-contact-form-7' ); ?></button>
						</div>
						<label class="screen-reader-text" for="uicf7-reply-html"><?php esc_html_e( 'Reply message', 'uniinbox-for-contact-form-7' ); ?></label>
						<textarea id="uicf7-reply-html" name="reply_html" class="uicf7-editor-source" rows="10"><?php echo esc_textarea( $uicf7_draft ); ?></textarea>
						<?php if ( '' !== $uicf7_draft && 'done' === $message['ai_status'] ) : ?>
							<p class="uicf7-ai-note"><?php echo wp_kses( UICF7_UI::icon( 'lightbulb' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Draft written by AI. Read and edit it before sending; nothing is sent automatically.', 'uniinbox-for-contact-form-7' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="uicf7-attachments">
						<input type="hidden" name="attachment_ids" value="">
						<ul class="uicf7-files" data-files></ul>
						<button type="button" class="uicf7-btn uicf7-btn--sm" data-attach><?php echo wp_kses( UICF7_UI::icon( 'paperclip' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Attach files', 'uniinbox-for-contact-form-7' ); ?></button>
						<span class="uicf7-muted uicf7-attachments__hint"><?php esc_html_e( 'Up to 5 files, 10 MB total. Files are added to your Media Library.', 'uniinbox-for-contact-form-7' ); ?></span>
					</div>

					<footer class="uicf7-compose__footer">
						<div class="uicf7-compose__options">
							<label><input type="checkbox" name="use_signature" value="1" <?php checked( 1, (int) UICF7_Settings::get( 'use_signature', 1 ) ); ?>> <?php esc_html_e( 'Add signature', 'uniinbox-for-contact-form-7' ); ?></label>
							<label><input type="checkbox" name="quote_original" value="1" <?php checked( 1, (int) UICF7_Settings::get( 'quote_original', 0 ) ); ?>> <?php esc_html_e( 'Include original message', 'uniinbox-for-contact-form-7' ); ?></label>
						</div>
						<div class="uicf7-compose__buttons">
							<button type="submit" name="do" value="save_draft" class="uicf7-btn"><?php esc_html_e( 'Save draft', 'uniinbox-for-contact-form-7' ); ?></button>
							<button type="submit" name="do" value="send_reply" class="uicf7-btn uicf7-btn--primary" data-send <?php disabled( ! $uicf7_can_reply ); ?>><?php echo wp_kses( UICF7_UI::icon( 'email-alt' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Send reply', 'uniinbox-for-contact-form-7' ); ?></button>
						</div>
					</footer>
				</form>

				<form method="post" action="<?php echo esc_url( $uicf7_post_url ); ?>" id="uicf7-note-form" class="uicf7-compose uicf7-compose--note" role="tabpanel" hidden>
					<?php $uicf7_hidden( $uicf7_id ); ?>
					<label class="screen-reader-text" for="uicf7-note-text"><?php esc_html_e( 'Internal note', 'uniinbox-for-contact-form-7' ); ?></label>
					<textarea id="uicf7-note-text" name="note_text" rows="4" maxlength="5000" placeholder="<?php esc_attr_e( 'Write a note for your team. Notes are never emailed to the sender.', 'uniinbox-for-contact-form-7' ); ?>"></textarea>
					<footer class="uicf7-compose__footer">
						<span class="uicf7-muted"><?php echo wp_kses( UICF7_UI::icon( 'lock' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Only visible to your team', 'uniinbox-for-contact-form-7' ); ?></span>
						<button type="submit" name="do" value="add_note" class="uicf7-btn uicf7-btn--note"><?php esc_html_e( 'Add note', 'uniinbox-for-contact-form-7' ); ?></button>
					</footer>
				</form>
			</section>
		</main>

		<aside class="uicf7-msg-side">
			<section class="uicf7-card uicf7-side-card">
				<h2 class="uicf7-side-card__title"><?php esc_html_e( 'Contact', 'uniinbox-for-contact-form-7' ); ?></h2>
				<div class="uicf7-contact">
					<?php echo wp_kses( UICF7_UI::avatar( $message['sender_name'], $message['sender_email'], 'md' ), UICF7_UI::allowed_html() ); ?>
					<div>
						<strong><?php echo esc_html( $uicf7_name ); ?></strong>
						<?php if ( $uicf7_can_reply ) : ?>
							<a href="<?php echo esc_url( 'mailto:' . $message['sender_email'] ); ?>"><?php echo esc_html( $message['sender_email'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( ! empty( $uicf7_extracted ) ) : ?>
					<dl class="uicf7-dl">
						<?php foreach ( $uicf7_labels as $uicf7_key => $uicf7_label ) : ?>
							<?php if ( isset( $uicf7_extracted[ $uicf7_key ] ) ) : ?>
								<dt><?php echo esc_html( $uicf7_label ); ?></dt>
								<dd><?php echo esc_html( (string) $uicf7_extracted[ $uicf7_key ] ); ?></dd>
							<?php endif; ?>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</section>

			<section class="uicf7-card uicf7-side-card">
				<h2 class="uicf7-side-card__title"><?php esc_html_e( 'AI insights', 'uniinbox-for-contact-form-7' ); ?></h2>
				<dl class="uicf7-dl">
					<dt><?php esc_html_e( 'Category', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo wp_kses( UICF7_UI::category_badge( $message['category'] ), UICF7_UI::allowed_html() ); ?></dd>
					<dt><?php esc_html_e( 'Priority', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo wp_kses( UICF7_UI::urgency_badge( $message['urgency'] ), UICF7_UI::allowed_html() ); ?></dd>
					<dt><?php esc_html_e( 'Status', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd>
						<?php echo esc_html( isset( $uicf7_ai_text[ $message['ai_status'] ] ) ? $uicf7_ai_text[ $message['ai_status'] ] : '' ); ?>
						<?php if ( '' !== $message['ai_note'] ) : ?>
							<span class="uicf7-muted uicf7-block"><?php echo esc_html( $message['ai_note'] ); ?></span>
						<?php endif; ?>
					</dd>
				</dl>
				<?php if ( $uicf7_ai_ready ) : ?>
					<form method="post" action="<?php echo esc_url( $uicf7_post_url ); ?>">
						<?php $uicf7_hidden( $uicf7_id ); ?>
						<button type="submit" name="do" value="reanalyze" class="uicf7-btn uicf7-btn--block"><?php echo wp_kses( UICF7_UI::icon( 'update' ), UICF7_UI::allowed_html() ); ?><?php echo esc_html( 'done' === $message['ai_status'] ? __( 'Analyze again', 'uniinbox-for-contact-form-7' ) : __( 'Analyze with AI', 'uniinbox-for-contact-form-7' ) ); ?></button>
					</form>
				<?php else : ?>
					<a class="uicf7-btn uicf7-btn--block" href="<?php echo esc_url( admin_url( 'admin.php?page=uicf7-settings' ) ); ?>"><?php esc_html_e( 'Set up AI', 'uniinbox-for-contact-form-7' ); ?></a>
				<?php endif; ?>
			</section>

			<section class="uicf7-card uicf7-side-card">
				<h2 class="uicf7-side-card__title"><?php esc_html_e( 'Details', 'uniinbox-for-contact-form-7' ); ?></h2>
				<dl class="uicf7-dl">
					<dt><?php esc_html_e( 'Form', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo esc_html( $message['form_title'] ); ?></dd>
					<dt><?php esc_html_e( 'Received', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo esc_html( UICF7_UI::datetime( $message['created_at'] ) ); ?></dd>
					<dt><?php esc_html_e( 'Replies sent', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $uicf7_reply_n ) ); ?></dd>
					<?php if ( ! empty( $message['replied_at'] ) ) : ?>
						<dt><?php esc_html_e( 'Last reply', 'uniinbox-for-contact-form-7' ); ?></dt>
						<dd><?php echo esc_html( UICF7_UI::datetime( $message['replied_at'] ) ); ?></dd>
					<?php endif; ?>
					<dt><?php esc_html_e( 'Message ID', 'uniinbox-for-contact-form-7' ); ?></dt>
					<dd>#<?php echo esc_html( (string) $uicf7_id ); ?></dd>
				</dl>
			</section>
		</aside>
	</div>
</div>
