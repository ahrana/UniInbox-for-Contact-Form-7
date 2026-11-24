<?php
/**
 * Inbox list view.
 *
 * @package UniInbox_For_CF7
 *
 * @var UICF7_List_Table $table       List table instance.
 * @var array            $stats       Dashboard numbers.
 * @var int              $unprocessed Messages never analyzed.
 * @var array            $queue       Queue counts (due, waiting).
 */

defined( 'ABSPATH' ) || exit;

$uicf7_filters  = UICF7_List_Table::filters();
$uicf7_export   = wp_nonce_url( admin_url( 'admin-post.php?action=uicf7_export' ), 'uicf7_export' );
$uicf7_analyze  = wp_nonce_url( admin_url( 'admin-post.php?action=uicf7_analyze_unprocessed' ), 'uicf7_analyze_unprocessed' );
$uicf7_settings = admin_url( 'admin.php?page=uicf7-settings' );
$uicf7_cards    = array(
	array(
		'label' => __( 'Unread', 'uniinbox-for-contact-form-7' ),
		'value' => $stats['unread'],
		'icon'  => 'email',
		'tone'  => 'indigo',
		'url'   => add_query_arg( 'status', 'unread', UICF7_List_Table::base_url() ),
	),
	array(
		'label' => __( 'High priority waiting', 'uniinbox-for-contact-form-7' ),
		'value' => $stats['high_waiting'],
		'icon'  => 'flag',
		'tone'  => 'red',
		'url'   => add_query_arg( 'urgency', 'high', UICF7_List_Table::base_url() ),
	),
	array(
		'label' => __( 'Received, last 7 days', 'uniinbox-for-contact-form-7' ),
		'value' => $stats['last7'],
		'icon'  => 'chart-bar',
		'tone'  => 'blue',
		'url'   => '',
	),
	array(
		'label' => __( 'Replied, last 7 days', 'uniinbox-for-contact-form-7' ),
		'value' => $stats['replied7'],
		'icon'  => 'yes-alt',
		'tone'  => 'green',
		'url'   => add_query_arg( 'status', 'replied', UICF7_List_Table::base_url() ),
	),
);
?>
<div class="wrap uicf7-app uicf7-inbox">
	<header class="uicf7-head">
		<div class="uicf7-head__text">
			<h1 class="uicf7-head__title"><?php esc_html_e( 'Inbox', 'uniinbox-for-contact-form-7' ); ?></h1>
			<p class="uicf7-head__sub"><?php esc_html_e( 'Every Contact Form 7 message in one place, sorted for you.', 'uniinbox-for-contact-form-7' ); ?></p>
		</div>
		<div class="uicf7-head__actions">
			<?php if ( $unprocessed > 0 ) : ?>
				<a class="uicf7-btn uicf7-btn--primary" href="<?php echo esc_url( $uicf7_analyze ); ?>">
					<?php echo wp_kses( UICF7_UI::icon( 'lightbulb' ), UICF7_UI::allowed_html() ); ?>
					<?php
					/* translators: %s: number of messages. */
					echo esc_html( sprintf( _n( 'Analyze %s unprocessed', 'Analyze %s unprocessed', $unprocessed, 'uniinbox-for-contact-form-7' ), number_format_i18n( $unprocessed ) ) );
					?>
				</a>
			<?php endif; ?>
			<a class="uicf7-btn" href="<?php echo esc_url( $uicf7_export ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'download' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Export CSV', 'uniinbox-for-contact-form-7' ); ?></a>
			<a class="uicf7-btn uicf7-btn--icon" href="<?php echo esc_url( $uicf7_settings ); ?>" aria-label="<?php esc_attr_e( 'Settings', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'admin-generic' ), UICF7_UI::allowed_html() ); ?></a>
		</div>
	</header>
	<hr class="wp-header-end">

	<?php if ( ! UICF7_Settings::ai_ready() ) : ?>
		<div class="uicf7-callout">
			<?php echo wp_kses( UICF7_UI::icon( 'lightbulb' ), UICF7_UI::allowed_html() ); ?>
			<div>
				<strong><?php esc_html_e( 'Let AI sort your inbox', 'uniinbox-for-contact-form-7' ); ?></strong>
				<p><?php esc_html_e( 'Messages are being saved. Add a free AI key (Groq or Gemini, no card needed) to categorize, prioritize, summarize and draft replies automatically.', 'uniinbox-for-contact-form-7' ); ?></p>
			</div>
			<a class="uicf7-btn uicf7-btn--primary" href="<?php echo esc_url( $uicf7_settings ); ?>"><?php esc_html_e( 'Set up AI', 'uniinbox-for-contact-form-7' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="uicf7-stats">
		<?php foreach ( $uicf7_cards as $uicf7_card ) : ?>
			<?php $uicf7_tag = '' !== $uicf7_card['url'] ? 'a' : 'div'; ?>
			<<?php echo esc_attr( $uicf7_tag ); ?> class="uicf7-stat uicf7-stat--<?php echo esc_attr( $uicf7_card['tone'] ); ?>"
			<?php
			if ( '' !== $uicf7_card['url'] ) {
				echo ' href="' . esc_url( $uicf7_card['url'] ) . '"';
			}
			?>
			>
				<span class="uicf7-stat__icon"><?php echo wp_kses( UICF7_UI::icon( $uicf7_card['icon'] ), UICF7_UI::allowed_html() ); ?></span>
				<span class="uicf7-stat__value"><?php echo esc_html( number_format_i18n( $uicf7_card['value'] ) ); ?></span>
				<span class="uicf7-stat__label"><?php echo esc_html( $uicf7_card['label'] ); ?></span>
			</<?php echo esc_attr( $uicf7_tag ); ?>>
		<?php endforeach; ?>
	</div>

	<div class="uicf7-queue" role="status" aria-live="polite" data-due="<?php echo esc_attr( (string) $queue['due'] ); ?>" data-waiting="<?php echo esc_attr( (string) $queue['waiting'] ); ?>" hidden>
		<span class="uicf7-spinner" aria-hidden="true"></span>
		<span class="uicf7-queue__text"></span>
	</div>

	<div class="uicf7-card uicf7-table-card">
		<?php $table->views(); ?>
		<form method="get" class="uicf7-table-form">
			<input type="hidden" name="page" value="uicf7-inbox">
			<?php if ( '' !== $uicf7_filters['status'] ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $uicf7_filters['status'] ); ?>">
			<?php endif; ?>
			<?php
			$table->search_box( __( 'Search messages', 'uniinbox-for-contact-form-7' ), 'uicf7-search' );
			$table->display();
			?>
		</form>
	</div>
</div>
