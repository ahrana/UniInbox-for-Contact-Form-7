<?php
/**
 * Settings view: one form, five tabs.
 *
 * @package UniInbox_For_CF7
 *
 * @var array     $settings Current settings.
 * @var array     $usage    AI calls today (used, cap).
 * @var WP_Post[] $forms    Contact Form 7 forms.
 */

defined( 'ABSPATH' ) || exit;

$uicf7_opt = UICF7_Settings::OPTION;

/**
 * Field name inside the settings array.
 *
 * @param string $key Setting key.
 * @return string
 */
$uicf7_name = function ( $key ) use ( $uicf7_opt ) {
	return $uicf7_opt . '[' . $key . ']';
};

/**
 * Password input for a secret plus a "remove" checkbox. Saved values are never printed.
 *
 * @param string $key   Setting key.
 * @param string $id    Input ID.
 * @param array  $attrs Extra data attributes.
 * @return void
 */
$uicf7_secret = function ( $key, $id, $attrs = array() ) use ( $uicf7_name ) {
	$has = UICF7_Settings::has_secret( $key );
	echo '<div class="uicf7-secret">';
	printf(
		'<input type="password" id="%1$s" name="%2$s" class="uicf7-input" value="" autocomplete="new-password" spellcheck="false" placeholder="%3$s"',
		esc_attr( $id ),
		esc_attr( $uicf7_name( $key ) ),
		esc_attr( $has ? __( '•••••••••• saved (leave blank to keep)', 'uniinbox-for-contact-form-7' ) : __( 'Paste your key', 'uniinbox-for-contact-form-7' ) )
	);
	foreach ( $attrs as $attr => $value ) {
		echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
	}
	echo '>';
	if ( $has ) {
		printf(
			'<label class="uicf7-secret__clear"><input type="checkbox" name="%1$s" value="1"> %2$s</label>',
			esc_attr( $uicf7_name( 'clear_' . $key ) ),
			esc_html__( 'Remove saved key', 'uniinbox-for-contact-form-7' )
		);
	}
	echo '</div>';
};

$uicf7_tabs = array(
	'ai'         => array( __( 'AI & automation', 'uniinbox-for-contact-form-7' ), 'lightbulb' ),
	'categories' => array( __( 'Categories', 'uniinbox-for-contact-form-7' ), 'tag' ),
	'replies'    => array( __( 'Replies', 'uniinbox-for-contact-form-7' ), 'email-alt' ),
	'alerts'     => array( __( 'Alerts', 'uniinbox-for-contact-form-7' ), 'bell' ),
	'data'       => array( __( 'Data & privacy', 'uniinbox-for-contact-form-7' ), 'shield' ),
);

$uicf7_meta       = UICF7_Settings::provider_meta();
$uicf7_categories = UICF7_Settings::category_list();
$uicf7_templates  = UICF7_Settings::templates();
$uicf7_locked     = UICF7_Settings::locked_categories();
$uicf7_export     = wp_nonce_url( admin_url( 'admin-post.php?action=uicf7_export' ), 'uicf7_export' );

/**
 * Print one category row.
 *
 * @param string $index Row index (or the __INDEX__ placeholder).
 * @param array  $cat   Category row.
 * @param bool   $locked Whether the row cannot be removed.
 * @return void
 */
$uicf7_cat_row = function ( $index, array $cat, $locked ) use ( $uicf7_opt ) {
	$base = $uicf7_opt . '[categories][' . $index . ']';
	?>
	<div class="uicf7-cat-row<?php echo esc_attr( $locked ? ' is-locked' : '' ); ?>" data-row>
		<input type="hidden" name="<?php echo esc_attr( $base . '[slug]' ); ?>" value="<?php echo esc_attr( $cat['slug'] ); ?>">
		<label class="uicf7-color" title="<?php esc_attr_e( 'Color', 'uniinbox-for-contact-form-7' ); ?>">
			<span class="screen-reader-text"><?php esc_html_e( 'Color', 'uniinbox-for-contact-form-7' ); ?></span>
			<input type="color" name="<?php echo esc_attr( $base . '[color]' ); ?>" value="<?php echo esc_attr( $cat['color'] ); ?>">
		</label>
		<div class="uicf7-cat-row__fields">
			<label class="screen-reader-text" for="<?php echo esc_attr( 'uicf7-cat-label-' . $index ); ?>"><?php esc_html_e( 'Category name', 'uniinbox-for-contact-form-7' ); ?></label>
			<input type="text" id="<?php echo esc_attr( 'uicf7-cat-label-' . $index ); ?>" class="uicf7-input uicf7-cat-row__name" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $cat['label'] ); ?>" maxlength="40" placeholder="<?php esc_attr_e( 'Category name', 'uniinbox-for-contact-form-7' ); ?>" <?php echo esc_attr( $locked ? '' : 'required' ); ?>>
			<label class="screen-reader-text" for="<?php echo esc_attr( 'uicf7-cat-desc-' . $index ); ?>"><?php esc_html_e( 'What belongs here', 'uniinbox-for-contact-form-7' ); ?></label>
			<input type="text" id="<?php echo esc_attr( 'uicf7-cat-desc-' . $index ); ?>" class="uicf7-input" name="<?php echo esc_attr( $base . '[description]' ); ?>" value="<?php echo esc_attr( $cat['description'] ); ?>" maxlength="200" placeholder="<?php esc_attr_e( 'What belongs here? This guides the AI.', 'uniinbox-for-contact-form-7' ); ?>">
		</div>
		<?php if ( 'spam' === $cat['slug'] ) : ?>
			<span class="uicf7-cat-row__alert uicf7-muted"><?php esc_html_e( 'No alerts', 'uniinbox-for-contact-form-7' ); ?></span>
		<?php else : ?>
			<label class="uicf7-mini-toggle uicf7-cat-row__alert">
				<input type="checkbox" name="<?php echo esc_attr( $base . '[alert]' ); ?>" value="1" <?php checked( ! empty( $cat['alert'] ) ); ?>>
				<span class="uicf7-toggle__track" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Alert', 'uniinbox-for-contact-form-7' ); ?></span>
			</label>
		<?php endif; ?>
		<?php if ( $locked ) : ?>
			<span class="uicf7-icon-btn is-locked" title="<?php esc_attr_e( 'Built-in category', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'lock' ), UICF7_UI::allowed_html() ); ?><span class="screen-reader-text"><?php esc_html_e( 'Built-in category', 'uniinbox-for-contact-form-7' ); ?></span></span>
		<?php else : ?>
			<button type="button" class="uicf7-icon-btn" data-remove aria-label="<?php esc_attr_e( 'Remove category', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'trash' ), UICF7_UI::allowed_html() ); ?></button>
		<?php endif; ?>
	</div>
	<?php
};

/**
 * Print one template row.
 *
 * @param string $index Row index (or the __INDEX__ placeholder).
 * @param array  $tpl   Template row.
 * @return void
 */
$uicf7_tpl_row = function ( $index, array $tpl ) use ( $uicf7_opt ) {
	$base = $uicf7_opt . '[templates][' . $index . ']';
	?>
	<div class="uicf7-tpl-row" data-row>
		<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $tpl['id'] ); ?>">
		<div class="uicf7-tpl-row__head">
			<label class="screen-reader-text" for="<?php echo esc_attr( 'uicf7-tpl-name-' . $index ); ?>"><?php esc_html_e( 'Template name', 'uniinbox-for-contact-form-7' ); ?></label>
			<input type="text" id="<?php echo esc_attr( 'uicf7-tpl-name-' . $index ); ?>" class="uicf7-input" name="<?php echo esc_attr( $base . '[name]' ); ?>" value="<?php echo esc_attr( $tpl['name'] ); ?>" maxlength="60" placeholder="<?php esc_attr_e( 'Template name', 'uniinbox-for-contact-form-7' ); ?>">
			<button type="button" class="uicf7-icon-btn" data-remove aria-label="<?php esc_attr_e( 'Remove template', 'uniinbox-for-contact-form-7' ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'trash' ), UICF7_UI::allowed_html() ); ?></button>
		</div>
		<label class="screen-reader-text" for="<?php echo esc_attr( 'uicf7-tpl-body-' . $index ); ?>"><?php esc_html_e( 'Template text', 'uniinbox-for-contact-form-7' ); ?></label>
		<textarea id="<?php echo esc_attr( 'uicf7-tpl-body-' . $index ); ?>" class="uicf7-input" name="<?php echo esc_attr( $base . '[body]' ); ?>" rows="5" maxlength="5000" placeholder="<?php esc_attr_e( 'Hi {first_name}, …', 'uniinbox-for-contact-form-7' ); ?>"><?php echo esc_textarea( $tpl['body'] ); ?></textarea>
	</div>
	<?php
};
?>
<div class="wrap uicf7-app uicf7-settings">
	<header class="uicf7-head">
		<div class="uicf7-head__text">
			<h1 class="uicf7-head__title"><?php esc_html_e( 'Settings', 'uniinbox-for-contact-form-7' ); ?></h1>
			<p class="uicf7-head__sub"><?php esc_html_e( 'Configure AI, categories, replies and alerts for your inbox.', 'uniinbox-for-contact-form-7' ); ?></p>
		</div>
		<div class="uicf7-head__actions">
			<a class="uicf7-btn" href="<?php echo esc_url( UICF7_List_Table::base_url() ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'arrow-left-alt2' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Back to inbox', 'uniinbox-for-contact-form-7' ); ?></a>
		</div>
	</header>
	<hr class="wp-header-end">
	<?php settings_errors(); ?>

	<form method="post" action="options.php" id="uicf7-settings-form" class="uicf7-settings-layout" novalidate>
		<?php settings_fields( 'uicf7_settings_group' ); ?>
		<input type="hidden" name="<?php echo esc_attr( $uicf7_name( 'categories_present' ) ); ?>" value="1">
		<input type="hidden" name="<?php echo esc_attr( $uicf7_name( 'templates_present' ) ); ?>" value="1">

		<nav class="uicf7-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'uniinbox-for-contact-form-7' ); ?>">
			<?php foreach ( $uicf7_tabs as $uicf7_slug => $uicf7_tab ) : ?>
				<a href="#<?php echo esc_attr( $uicf7_slug ); ?>" role="tab" class="uicf7-tab" id="uicf7-tab-<?php echo esc_attr( $uicf7_slug ); ?>" aria-controls="uicf7-panel-<?php echo esc_attr( $uicf7_slug ); ?>" data-tab="<?php echo esc_attr( $uicf7_slug ); ?>">
					<?php echo wp_kses( UICF7_UI::icon( $uicf7_tab[1] ), UICF7_UI::allowed_html() ); ?>
					<span><?php echo esc_html( $uicf7_tab[0] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="uicf7-panels">

			<?php /* ---------------------------------------------------------------- AI */ ?>
			<section class="uicf7-panel" id="uicf7-panel-ai" role="tabpanel" aria-labelledby="uicf7-tab-ai" data-panel="ai">
				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'AI analysis', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'Sort every new message into a category, rank its priority, summarize it and draft a reply. You use your own provider account; several have free plans.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-stack">
						<?php
						echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'ai_enabled' ), ! empty( $settings['ai_enabled'] ), __( 'Enable AI analysis', 'uniinbox-for-contact-form-7' ), __( 'When on, message text is sent to the provider you choose below.', 'uniinbox-for-contact-form-7' ), 'uicf7-ai-enabled' ), UICF7_UI::allowed_html() );
						echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'auto_analyze' ), ! empty( $settings['auto_analyze'] ), __( 'Analyze new messages automatically', 'uniinbox-for-contact-form-7' ), __( 'Runs in the background a few seconds after each submission. Turn off to analyze only when you click "Analyze with AI".', 'uniinbox-for-contact-form-7' ) ), UICF7_UI::allowed_html() );
						?>
					</div>
				</div>

				<div class="uicf7-card uicf7-section" data-needs-ai>
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Provider', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'No paid plan? Pick one marked "Free plan". Free plans have daily limits; when one is reached, messages wait and are analyzed later.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>

					<div class="uicf7-providers" role="radiogroup" aria-label="<?php esc_attr_e( 'AI provider', 'uniinbox-for-contact-form-7' ); ?>">
						<?php foreach ( $uicf7_meta as $uicf7_slug => $uicf7_info ) : ?>
							<label class="uicf7-provider">
								<input type="radio" name="<?php echo esc_attr( $uicf7_name( 'provider' ) ); ?>" value="<?php echo esc_attr( $uicf7_slug ); ?>" <?php checked( $settings['provider'], $uicf7_slug ); ?>>
								<span class="uicf7-provider__body">
									<span class="uicf7-provider__name"><?php echo esc_html( $uicf7_info['label'] ); ?></span>
									<?php if ( ! empty( $uicf7_info['builtin'] ) ) : ?>
										<span class="uicf7-chip is-builtin"><?php esc_html_e( 'Built into WordPress', 'uniinbox-for-contact-form-7' ); ?></span>
									<?php else : ?>
										<span class="uicf7-chip <?php echo esc_attr( ! empty( $uicf7_info['free'] ) ? 'is-free' : 'is-paid' ); ?>"><?php echo esc_html( ! empty( $uicf7_info['free'] ) ? __( 'Free plan', 'uniinbox-for-contact-form-7' ) : __( 'Paid', 'uniinbox-for-contact-form-7' ) ); ?></span>
									<?php endif; ?>
									<?php if ( UICF7_Settings::has_secret( 'api_key_' . $uicf7_slug ) ) : ?>
										<span class="uicf7-provider__saved"><?php echo wp_kses( UICF7_UI::icon( 'yes' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Key saved', 'uniinbox-for-contact-form-7' ); ?></span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<?php foreach ( $uicf7_meta as $uicf7_slug => $uicf7_info ) : ?>
						<div class="uicf7-provider-config" data-provider="<?php echo esc_attr( $uicf7_slug ); ?>">
							<p class="uicf7-help uicf7-provider-config__note">
								<?php echo esc_html( UICF7_Settings::provider_note( $uicf7_slug ) ); ?>
								<?php if ( ! empty( $uicf7_info['builtin'] ) ) : ?>
									<a href="<?php echo esc_url( $uicf7_info['signup'] ); ?>"><?php esc_html_e( 'Open Connectors', 'uniinbox-for-contact-form-7' ); ?></a>
								<?php else : ?>
									<a href="<?php echo esc_url( $uicf7_info['signup'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ! empty( $uicf7_info['free'] ) ? __( 'Get a free key', 'uniinbox-for-contact-form-7' ) : __( 'Get a key', 'uniinbox-for-contact-form-7' ) ); ?> <span aria-hidden="true">↗</span></a>
								<?php endif; ?>
							</p>
							<?php if ( empty( $uicf7_info['builtin'] ) ) : ?>
							<div class="uicf7-grid-2">
								<div class="uicf7-field">
									<label for="uicf7-key-<?php echo esc_attr( $uicf7_slug ); ?>"><?php esc_html_e( 'API key', 'uniinbox-for-contact-form-7' ); ?></label>
									<?php $uicf7_secret( 'api_key_' . $uicf7_slug, 'uicf7-key-' . $uicf7_slug, array( 'data-key-for' => $uicf7_slug ) ); ?>
								</div>
								<div class="uicf7-field">
									<label for="uicf7-model-<?php echo esc_attr( $uicf7_slug ); ?>"><?php esc_html_e( 'Model', 'uniinbox-for-contact-form-7' ); ?></label>
									<input type="text" id="uicf7-model-<?php echo esc_attr( $uicf7_slug ); ?>" class="uicf7-input" data-model-for="<?php echo esc_attr( $uicf7_slug ); ?>" name="<?php echo esc_attr( $uicf7_name( 'model_' . $uicf7_slug ) ); ?>" value="<?php echo esc_attr( $settings[ 'model_' . $uicf7_slug ] ); ?>" spellcheck="false">
									<p class="uicf7-help">
										<?php
										/* translators: %s: default model name. */
										echo esc_html( sprintf( __( 'Default: %s. Model names change often; copy a current one from the provider if you see "model not found".', 'uniinbox-for-contact-form-7' ), $uicf7_info['model'] ) );
										?>
									</p>
								</div>
							</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="uicf7-test">
						<button type="button" class="uicf7-btn" data-test="ai"><?php echo wp_kses( UICF7_UI::icon( 'controls-play' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Test connection', 'uniinbox-for-contact-form-7' ); ?></button>
						<span class="uicf7-test__result" role="status" aria-live="polite"></span>
					</div>
				</div>

				<div class="uicf7-card uicf7-section" data-needs-ai>
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Reply drafts', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'Help the AI write replies that sound like your business.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-field">
						<label for="uicf7-business"><?php esc_html_e( 'About your business', 'uniinbox-for-contact-form-7' ); ?></label>
						<textarea id="uicf7-business" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'business_context' ) ); ?>" rows="4" maxlength="1500" placeholder="<?php esc_attr_e( 'Example: We build WordPress plugins. Support hours are Sunday to Thursday, 10am to 6pm. We reply within one business day. Refunds within 14 days.', 'uniinbox-for-contact-form-7' ); ?>"><?php echo esc_textarea( $settings['business_context'] ); ?></textarea>
						<p class="uicf7-help"><?php esc_html_e( 'Services, opening hours, policies, links you often share. The AI only uses what you write here and never invents prices or promises.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-grid-2">
						<div class="uicf7-field">
							<label for="uicf7-tone"><?php esc_html_e( 'Tone', 'uniinbox-for-contact-form-7' ); ?></label>
							<select id="uicf7-tone" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'tone' ) ); ?>">
								<option value="friendly" <?php selected( $settings['tone'], 'friendly' ); ?>><?php esc_html_e( 'Friendly', 'uniinbox-for-contact-form-7' ); ?></option>
								<option value="professional" <?php selected( $settings['tone'], 'professional' ); ?>><?php esc_html_e( 'Professional', 'uniinbox-for-contact-form-7' ); ?></option>
								<option value="concise" <?php selected( $settings['tone'], 'concise' ); ?>><?php esc_html_e( 'Short and direct', 'uniinbox-for-contact-form-7' ); ?></option>
							</select>
						</div>
						<div class="uicf7-field">
							<label for="uicf7-language"><?php esc_html_e( 'Reply language', 'uniinbox-for-contact-form-7' ); ?></label>
							<select id="uicf7-language" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'reply_language' ) ); ?>">
								<option value="sender" <?php selected( $settings['reply_language'], 'sender' ); ?>><?php esc_html_e( 'Same language as the sender', 'uniinbox-for-contact-form-7' ); ?></option>
								<option value="site" <?php selected( $settings['reply_language'], 'site' ); ?>><?php esc_html_e( 'Always the site language', 'uniinbox-for-contact-form-7' ); ?></option>
							</select>
						</div>
					</div>
				</div>

				<div class="uicf7-card uicf7-section" data-needs-ai>
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Usage limits', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'Stops a spam wave from using up your free quota or running up a bill.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-usage">
						<?php
						$uicf7_pct = $usage['cap'] > 0 ? min( 100, (int) round( 100 * $usage['used'] / $usage['cap'] ) ) : 0;
						?>
						<div class="uicf7-usage__text">
							<strong><?php echo esc_html( number_format_i18n( $usage['used'] ) ); ?></strong>
							<?php
							if ( $usage['cap'] > 0 ) {
								/* translators: %s: daily limit. */
								echo esc_html( sprintf( __( 'of %s AI calls used today', 'uniinbox-for-contact-form-7' ), number_format_i18n( $usage['cap'] ) ) );
							} else {
								esc_html_e( 'AI calls today (no limit)', 'uniinbox-for-contact-form-7' );
							}
							?>
						</div>
						<?php if ( $usage['cap'] > 0 ) : ?>
							<div class="uicf7-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $uicf7_pct ); ?>"><span style="width:<?php echo esc_attr( (string) $uicf7_pct ); ?>%"></span></div>
						<?php endif; ?>
					</div>
					<div class="uicf7-grid-3">
						<div class="uicf7-field">
							<label for="uicf7-daily-cap"><?php esc_html_e( 'Daily AI limit', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="number" id="uicf7-daily-cap" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'daily_cap' ) ); ?>" min="0" max="5000" value="<?php echo esc_attr( (string) $settings['daily_cap'] ); ?>">
							<p class="uicf7-help"><?php esc_html_e( '0 means no limit.', 'uniinbox-for-contact-form-7' ); ?></p>
						</div>
						<div class="uicf7-field">
							<label for="uicf7-min-chars"><?php esc_html_e( 'Skip messages shorter than', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="number" id="uicf7-min-chars" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'min_chars' ) ); ?>" min="0" max="500" value="<?php echo esc_attr( (string) $settings['min_chars'] ); ?>">
							<p class="uicf7-help"><?php esc_html_e( 'Characters.', 'uniinbox-for-contact-form-7' ); ?></p>
						</div>
						<div class="uicf7-field">
							<label for="uicf7-max-links"><?php esc_html_e( 'Skip messages with more links than', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="number" id="uicf7-max-links" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'max_links' ) ); ?>" min="0" max="50" value="<?php echo esc_attr( (string) $settings['max_links'] ); ?>">
							<p class="uicf7-help"><?php esc_html_e( 'Skipped messages are still saved. 0 turns this off.', 'uniinbox-for-contact-form-7' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<?php /* -------------------------------------------------------- Categories */ ?>
			<section class="uicf7-panel" id="uicf7-panel-categories" role="tabpanel" aria-labelledby="uicf7-tab-categories" data-panel="categories" hidden>
				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Categories', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'The AI sorts each message into one of these. Describe what belongs in each category so it chooses well. Turn on "Alert" to be notified about new messages in that category.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>

					<div class="uicf7-cat-head" aria-hidden="true">
						<span><?php esc_html_e( 'Color', 'uniinbox-for-contact-form-7' ); ?></span>
						<span><?php esc_html_e( 'Name and description', 'uniinbox-for-contact-form-7' ); ?></span>
						<span><?php esc_html_e( 'Alerts', 'uniinbox-for-contact-form-7' ); ?></span>
						<span></span>
					</div>
					<div class="uicf7-repeater" data-repeater="categories" data-max="<?php echo esc_attr( (string) UICF7_Settings::MAX_CATEGORIES ); ?>" data-next="<?php echo esc_attr( (string) count( $uicf7_categories ) ); ?>">
						<?php
						foreach ( $uicf7_categories as $uicf7_i => $uicf7_cat ) {
							$uicf7_cat_row( (string) $uicf7_i, $uicf7_cat, in_array( $uicf7_cat['slug'], $uicf7_locked, true ) );
						}
						?>
					</div>
					<template id="uicf7-tpl-categories">
						<?php
						$uicf7_cat_row(
							'__INDEX__',
							array(
								'slug'        => '',
								'label'       => '',
								'color'       => '#6366f1',
								'description' => '',
								'alert'       => 0,
							),
							false
						);
						?>
					</template>
					<button type="button" class="uicf7-btn uicf7-btn--dashed" data-add="categories"><?php echo wp_kses( UICF7_UI::icon( 'plus-alt2' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Add category', 'uniinbox-for-contact-form-7' ); ?></button>
					<p class="uicf7-help"><?php esc_html_e( '"Spam" and "Other" are built in and cannot be removed. Renaming a category keeps its messages. Removing one leaves its messages in place with the old name.', 'uniinbox-for-contact-form-7' ); ?></p>
				</div>
			</section>

			<?php /* ----------------------------------------------------------- Replies */ ?>
			<section class="uicf7-panel" id="uicf7-panel-replies" role="tabpanel" aria-labelledby="uicf7-tab-replies" data-panel="replies" hidden>
				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Sender', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'How your replies appear in the recipient\'s inbox. Emails are sent with WordPress mail; an SMTP plugin improves delivery.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-grid-2">
						<div class="uicf7-field">
							<label for="uicf7-from-name"><?php esc_html_e( 'From name', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="text" id="uicf7-from-name" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'from_name' ) ); ?>" value="<?php echo esc_attr( $settings['from_name'] ); ?>" maxlength="80" placeholder="<?php echo esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>">
						</div>
						<div class="uicf7-field">
							<label for="uicf7-reply-to"><?php esc_html_e( 'Reply-to address', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="email" id="uicf7-reply-to" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'reply_to' ) ); ?>" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" placeholder="<?php esc_attr_e( 'Your own email address', 'uniinbox-for-contact-form-7' ); ?>">
							<p class="uicf7-help"><?php esc_html_e( 'Where answers to your replies go. Leave empty to use the email of whoever sends the reply.', 'uniinbox-for-contact-form-7' ); ?></p>
						</div>
					</div>
				</div>

				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Signature', 'uniinbox-for-contact-form-7' ); ?></h2>
					</div>
					<div class="uicf7-field">
						<label for="uicf7-signature" class="screen-reader-text"><?php esc_html_e( 'Signature', 'uniinbox-for-contact-form-7' ); ?></label>
						<textarea id="uicf7-signature" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'signature' ) ); ?>" rows="4" maxlength="1000" placeholder="<?php esc_attr_e( "Rana\nSupport team, Example Inc.\nexample.com", 'uniinbox-for-contact-form-7' ); ?>"><?php echo esc_textarea( $settings['signature'] ); ?></textarea>
						<p class="uicf7-help"><?php esc_html_e( 'Added below your replies. Leave empty to sign with the site name.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-stack">
						<?php
						echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'use_signature' ), ! empty( $settings['use_signature'] ), __( 'Add the signature by default', 'uniinbox-for-contact-form-7' ) ), UICF7_UI::allowed_html() );
						echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'quote_original' ), ! empty( $settings['quote_original'] ), __( 'Include the original message by default', 'uniinbox-for-contact-form-7' ) ), UICF7_UI::allowed_html() );
						?>
					</div>
				</div>

				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Reply templates', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p>
							<?php esc_html_e( 'Reusable answers you can insert from the reply editor. Placeholders:', 'uniinbox-for-contact-form-7' ); ?>
							<?php foreach ( array( '{name}', '{first_name}', '{email}', '{subject}', '{form}', '{site}' ) as $uicf7_ph ) : ?>
								<code><?php echo esc_html( $uicf7_ph ); ?></code>
							<?php endforeach; ?>
						</p>
					</div>
					<div class="uicf7-repeater uicf7-repeater--templates" data-repeater="templates" data-max="<?php echo esc_attr( (string) UICF7_Settings::MAX_TEMPLATES ); ?>" data-next="<?php echo esc_attr( (string) count( $uicf7_templates ) ); ?>">
						<?php
						foreach ( $uicf7_templates as $uicf7_i => $uicf7_tpl ) {
							$uicf7_tpl_row( (string) $uicf7_i, $uicf7_tpl );
						}
						?>
					</div>
					<template id="uicf7-tpl-templates">
						<?php
						$uicf7_tpl_row(
							'__INDEX__',
							array(
								'id'   => '',
								'name' => '',
								'body' => '',
							)
						);
						?>
					</template>
					<button type="button" class="uicf7-btn uicf7-btn--dashed" data-add="templates"><?php echo wp_kses( UICF7_UI::icon( 'plus-alt2' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Add template', 'uniinbox-for-contact-form-7' ); ?></button>
				</div>
			</section>

			<?php /* ------------------------------------------------------------ Alerts */ ?>
			<section class="uicf7-panel" id="uicf7-panel-alerts" role="tabpanel" aria-labelledby="uicf7-tab-alerts" data-panel="alerts" hidden>
				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'When to alert', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p>
							<?php esc_html_e( 'After AI analysis, new messages in categories with "Alert" turned on trigger a notification. Alerts include the summary and a link, never the full message.', 'uniinbox-for-contact-form-7' ); ?>
							<a href="#categories" data-goto="categories"><?php esc_html_e( 'Choose categories', 'uniinbox-for-contact-form-7' ); ?></a>
						</p>
					</div>
					<div class="uicf7-field uicf7-field--inline">
						<label for="uicf7-min-urgency"><?php esc_html_e( 'Minimum priority', 'uniinbox-for-contact-form-7' ); ?></label>
						<select id="uicf7-min-urgency" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'notify_min_urgency' ) ); ?>">
							<?php foreach ( UICF7_Settings::urgencies() as $uicf7_slug => $uicf7_label ) : ?>
								<option value="<?php echo esc_attr( $uicf7_slug ); ?>" <?php selected( $settings['notify_min_urgency'], $uicf7_slug ); ?>><?php echo esc_html( $uicf7_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Channels', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'Use any combination.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<div class="uicf7-channel">
						<span class="uicf7-channel__icon"><?php echo wp_kses( UICF7_UI::icon( 'email' ), UICF7_UI::allowed_html() ); ?></span>
						<div class="uicf7-field">
							<label for="uicf7-notify-email"><?php esc_html_e( 'Email', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="text" id="uicf7-notify-email" class="uicf7-input" data-alert="email" name="<?php echo esc_attr( $uicf7_name( 'notify_email' ) ); ?>" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" placeholder="you@example.com, team@example.com">
						</div>
					</div>
					<div class="uicf7-channel">
						<span class="uicf7-channel__icon"><?php echo wp_kses( UICF7_UI::icon( 'format-chat' ), UICF7_UI::allowed_html() ); ?></span>
						<div class="uicf7-field">
							<label for="uicf7-slack"><?php esc_html_e( 'Slack incoming webhook', 'uniinbox-for-contact-form-7' ); ?></label>
							<input type="url" id="uicf7-slack" class="uicf7-input" data-alert="slack" name="<?php echo esc_attr( $uicf7_name( 'slack_webhook' ) ); ?>" value="<?php echo esc_attr( $settings['slack_webhook'] ); ?>" placeholder="https://hooks.slack.com/services/…">
						</div>
					</div>
					<div class="uicf7-channel">
						<span class="uicf7-channel__icon"><?php echo wp_kses( UICF7_UI::icon( 'megaphone' ), UICF7_UI::allowed_html() ); ?></span>
						<div class="uicf7-grid-2">
							<div class="uicf7-field">
								<label for="uicf7-tg-token"><?php esc_html_e( 'Telegram bot token', 'uniinbox-for-contact-form-7' ); ?></label>
								<?php $uicf7_secret( 'telegram_token', 'uicf7-tg-token', array( 'data-alert' => 'token' ) ); ?>
							</div>
							<div class="uicf7-field">
								<label for="uicf7-tg-chat"><?php esc_html_e( 'Telegram chat ID', 'uniinbox-for-contact-form-7' ); ?></label>
								<input type="text" id="uicf7-tg-chat" class="uicf7-input" data-alert="chat" name="<?php echo esc_attr( $uicf7_name( 'telegram_chat_id' ) ); ?>" value="<?php echo esc_attr( $settings['telegram_chat_id'] ); ?>" placeholder="-1001234567890">
								<p class="uicf7-help"><?php esc_html_e( 'Create a bot with @BotFather, send it a message, then use your numeric chat ID or @channelname.', 'uniinbox-for-contact-form-7' ); ?></p>
							</div>
						</div>
					</div>
					<div class="uicf7-test">
						<button type="button" class="uicf7-btn" data-test="alert"><?php echo wp_kses( UICF7_UI::icon( 'controls-play' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Send test alert', 'uniinbox-for-contact-form-7' ); ?></button>
						<span class="uicf7-test__result" role="status" aria-live="polite"></span>
					</div>
				</div>

				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Weekly digest', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'A short email every week: new messages by category, unread count and high-priority messages still waiting.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<?php echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'digest_enabled' ), ! empty( $settings['digest_enabled'] ), __( 'Send me a weekly digest', 'uniinbox-for-contact-form-7' ) ), UICF7_UI::allowed_html() ); ?>
					<div class="uicf7-field">
						<label for="uicf7-digest-email"><?php esc_html_e( 'Send to', 'uniinbox-for-contact-form-7' ); ?></label>
						<input type="email" id="uicf7-digest-email" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'digest_email' ) ); ?>" value="<?php echo esc_attr( $settings['digest_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>">
					</div>
				</div>
			</section>

			<?php /* -------------------------------------------------------------- Data */ ?>
			<section class="uicf7-panel" id="uicf7-panel-data" role="tabpanel" aria-labelledby="uicf7-tab-data" data-panel="data" hidden>
				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Privacy', 'uniinbox-for-contact-form-7' ); ?></h2>
					</div>
					<?php echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'redact_pii' ), ! empty( $settings['redact_pii'] ), __( 'Remove email addresses and phone numbers before sending text to AI', 'uniinbox-for-contact-form-7' ), __( 'Recommended, especially with free plans.', 'uniinbox-for-contact-form-7' ) ), UICF7_UI::allowed_html() ); ?>
				</div>

				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Forms to skip', 'uniinbox-for-contact-form-7' ); ?></h2>
						<p><?php esc_html_e( 'Submissions from ticked forms are not saved in the inbox. Use this for forms that collect sensitive data.', 'uniinbox-for-contact-form-7' ); ?></p>
					</div>
					<?php if ( empty( $forms ) ) : ?>
						<p class="uicf7-help"><?php esc_html_e( 'No Contact Form 7 forms found. All forms are saved by default.', 'uniinbox-for-contact-form-7' ); ?></p>
					<?php else : ?>
						<div class="uicf7-checks">
							<?php foreach ( $forms as $uicf7_form ) : ?>
								<label class="uicf7-check">
									<input type="checkbox" name="<?php echo esc_attr( $uicf7_name( 'excluded_forms' ) ); ?>[]" value="<?php echo esc_attr( (string) $uicf7_form->ID ); ?>" <?php checked( in_array( (int) $uicf7_form->ID, array_map( 'intval', (array) $settings['excluded_forms'] ), true ) ); ?>>
									<span><?php echo esc_html( $uicf7_form->post_title ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="uicf7-card uicf7-section">
					<div class="uicf7-section__head">
						<h2><?php esc_html_e( 'Storage', 'uniinbox-for-contact-form-7' ); ?></h2>
					</div>
					<div class="uicf7-field uicf7-field--inline">
						<label for="uicf7-retention"><?php esc_html_e( 'Delete messages after', 'uniinbox-for-contact-form-7' ); ?></label>
						<div class="uicf7-input-group">
							<input type="number" id="uicf7-retention" class="uicf7-input" name="<?php echo esc_attr( $uicf7_name( 'retention_days' ) ); ?>" min="0" max="3650" value="<?php echo esc_attr( (string) $settings['retention_days'] ); ?>">
							<span><?php esc_html_e( 'days', 'uniinbox-for-contact-form-7' ); ?></span>
						</div>
					</div>
					<p class="uicf7-help"><?php esc_html_e( '0 keeps messages until you delete them. Replies and notes are deleted with their message.', 'uniinbox-for-contact-form-7' ); ?></p>
					<?php echo wp_kses( UICF7_UI::toggle( $uicf7_name( 'delete_on_uninstall' ), ! empty( $settings['delete_on_uninstall'] ), __( 'Delete all messages and settings when the plugin is deleted', 'uniinbox-for-contact-form-7' ) ), UICF7_UI::allowed_html() ); ?>
					<div class="uicf7-test">
						<a class="uicf7-btn" href="<?php echo esc_url( $uicf7_export ); ?>"><?php echo wp_kses( UICF7_UI::icon( 'download' ), UICF7_UI::allowed_html() ); ?><?php esc_html_e( 'Export all messages (CSV)', 'uniinbox-for-contact-form-7' ); ?></a>
						<a class="uicf7-link" href="<?php echo esc_url( admin_url( 'export-personal-data.php' ) ); ?>"><?php esc_html_e( 'Export or erase one person\'s data', 'uniinbox-for-contact-form-7' ); ?></a>
					</div>
				</div>
			</section>
		</div>

		<div class="uicf7-savebar">
			<span class="uicf7-savebar__status" aria-live="polite"></span>
			<button type="submit" class="uicf7-btn uicf7-btn--primary" id="uicf7-save"><?php esc_html_e( 'Save changes', 'uniinbox-for-contact-form-7' ); ?></button>
		</div>
	</form>
</div>
