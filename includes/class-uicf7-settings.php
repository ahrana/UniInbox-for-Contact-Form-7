<?php
/**
 * Plugin settings: defaults, getters, categories, templates and sanitization.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings helper.
 */
class UICF7_Settings {

	const OPTION         = 'uicf7_settings';
	const MAX_CATEGORIES = 20;
	const MAX_TEMPLATES  = 30;
	const DEFAULT_COLOR  = '#64748b';

	/**
	 * Category slugs that always exist. "spam" drives the spam folder and
	 * "other" is the fallback when the AI answer matches nothing.
	 *
	 * @return string[]
	 */
	public static function locked_categories() {
		return array( 'spam', 'other' );
	}

	/**
	 * Built-in categories used until the site owner saves their own list.
	 *
	 * @return array[] Rows with slug, label, color, description, alert.
	 */
	public static function default_categories() {
		return array(
			array(
				'slug'        => 'lead',
				'label'       => __( 'Sales lead', 'uniinbox-for-contact-form-7' ),
				'color'       => '#16a34a',
				'description' => __( 'Someone interested in buying: a quote, pricing, a demo or availability.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 1,
			),
			array(
				'slug'        => 'support',
				'label'       => __( 'Support', 'uniinbox-for-contact-form-7' ),
				'color'       => '#2563eb',
				'description' => __( 'A customer needs help or reports a problem.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 1,
			),
			array(
				'slug'        => 'billing',
				'label'       => __( 'Billing', 'uniinbox-for-contact-form-7' ),
				'color'       => '#9333ea',
				'description' => __( 'Invoices, payments, refunds or subscriptions.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 1,
			),
			array(
				'slug'        => 'partnership',
				'label'       => __( 'Partnership', 'uniinbox-for-contact-form-7' ),
				'color'       => '#0891b2',
				'description' => __( 'Collaboration, sponsorship, reseller or affiliate proposals.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 0,
			),
			array(
				'slug'        => 'job',
				'label'       => __( 'Job inquiry', 'uniinbox-for-contact-form-7' ),
				'color'       => '#ca8a04',
				'description' => __( 'Job applications or questions about working here.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 0,
			),
			array(
				'slug'        => 'feedback',
				'label'       => __( 'Feedback', 'uniinbox-for-contact-form-7' ),
				'color'       => '#db2777',
				'description' => __( 'Suggestions, praise or complaints with no request for help.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 0,
			),
			array(
				'slug'        => 'spam',
				'label'       => __( 'Spam', 'uniinbox-for-contact-form-7' ),
				'color'       => '#dc2626',
				'description' => __( 'Unsolicited sales pitches, SEO or marketing offers, scams and gibberish.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 0,
			),
			array(
				'slug'        => 'other',
				'label'       => __( 'Other', 'uniinbox-for-contact-form-7' ),
				'color'       => self::DEFAULT_COLOR,
				'description' => __( 'Anything that fits no other category.', 'uniinbox-for-contact-form-7' ),
				'alert'       => 0,
			),
		);
	}

	/**
	 * Starter reply templates used until the site owner saves their own.
	 *
	 * @return array[] Rows with id, name, body.
	 */
	public static function default_templates() {
		return array(
			array(
				'id'   => 'thanks',
				'name' => __( 'Thanks, we will follow up', 'uniinbox-for-contact-form-7' ),
				'body' => __( "Hi {first_name},\n\nThank you for contacting {site}. We have received your message and a member of our team will get back to you shortly.\n\nBest regards,", 'uniinbox-for-contact-form-7' ),
			),
			array(
				'id'   => 'details',
				'name' => __( 'Ask for more details', 'uniinbox-for-contact-form-7' ),
				'body' => __( "Hi {first_name},\n\nThanks for reaching out. To help you as quickly as possible, could you share a few more details about what you need?\n\nBest regards,", 'uniinbox-for-contact-form-7' ),
			),
		);
	}

	/**
	 * Raw saved option (no defaults merged).
	 *
	 * @return array
	 */
	private static function raw() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * The full category list in display order.
	 *
	 * @return array[]
	 */
	public static function category_list() {
		$raw = self::raw();
		if ( ! empty( $raw['categories'] ) && is_array( $raw['categories'] ) ) {
			return $raw['categories'];
		}
		return self::default_categories();
	}

	/**
	 * Category labels keyed by slug.
	 *
	 * @return array<string,string>
	 */
	public static function categories() {
		$list = array();
		foreach ( self::category_list() as $row ) {
			$list[ $row['slug'] ] = $row['label'];
		}
		return $list;
	}

	/**
	 * One category row.
	 *
	 * @param string $slug Category slug.
	 * @return array|null
	 */
	public static function category( $slug ) {
		foreach ( self::category_list() as $row ) {
			if ( $row['slug'] === $slug ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Slugs of categories that trigger alerts.
	 *
	 * @return string[]
	 */
	public static function alert_categories() {
		$slugs = array();
		foreach ( self::category_list() as $row ) {
			if ( ! empty( $row['alert'] ) && 'spam' !== $row['slug'] ) {
				$slugs[] = $row['slug'];
			}
		}
		return $slugs;
	}

	/**
	 * Reply templates.
	 *
	 * @return array[]
	 */
	public static function templates() {
		$raw = self::raw();
		if ( array_key_exists( 'templates', $raw ) && is_array( $raw['templates'] ) ) {
			return $raw['templates'];
		}
		return self::default_templates();
	}

	/**
	 * Allowed urgency levels.
	 *
	 * @return array<string,string> slug => label.
	 */
	public static function urgencies() {
		return array(
			'high'   => __( 'High', 'uniinbox-for-contact-form-7' ),
			'medium' => __( 'Medium', 'uniinbox-for-contact-form-7' ),
			'low'    => __( 'Low', 'uniinbox-for-contact-form-7' ),
		);
	}

	/**
	 * Provider facts. Endpoints are fixed HTTPS URLs, never user input.
	 *
	 * Keys: label, free (has a free plan), model (default), endpoint
	 * (OpenAI-compatible chat completions URL, if applicable), json_mode
	 * (whether response_format json_object is safe to send), signup.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function provider_meta() {
		$meta = array();

		// WordPress 7.0+ AI Client: uses the AI service the site owner connected in Settings > Connectors.
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			$meta['wordpress'] = array(
				'label'   => 'WordPress AI',
				'free'    => false,
				'builtin' => true,
				'model'   => '',
				'signup'  => admin_url( 'options-connectors.php' ),
			);
		}

		return $meta + array(
			'gemini'     => array(
				'label'  => 'Google Gemini',
				'free'   => true,
				'model'  => 'gemini-3.8-flash',
				'signup' => 'https://aistudio.google.com/apikey',
			),
			'groq'       => array(
				'label'     => 'Groq',
				'free'      => true,
				'model'     => 'llama-3.3-70b-versatile',
				'endpoint'  => 'https://api.groq.com/openai/v1/chat/completions',
				'json_mode' => true,
				'signup'    => 'https://console.groq.com/keys',
			),
			'openrouter' => array(
				'label'     => 'OpenRouter',
				'free'      => true,
				'model'     => 'meta-llama/llama-3.3-70b-instruct:free',
				'endpoint'  => 'https://openrouter.ai/api/v1/chat/completions',
				'json_mode' => false,
				'signup'    => 'https://openrouter.ai/keys',
			),
			'cerebras'   => array(
				'label'     => 'Cerebras',
				'free'      => true,
				'model'     => 'llama3.1-8b',
				'endpoint'  => 'https://api.cerebras.ai/v1/chat/completions',
				'json_mode' => false,
				'signup'    => 'https://cloud.cerebras.ai/',
			),
			'mistral'    => array(
				'label'     => 'Mistral',
				'free'      => true,
				'model'     => 'mistral-small-latest',
				'endpoint'  => 'https://api.mistral.ai/v1/chat/completions',
				'json_mode' => true,
				'signup'    => 'https://console.mistral.ai/api-keys',
			),
			'openai'     => array(
				'label'     => 'OpenAI',
				'free'      => false,
				'model'     => 'gpt-4o-mini',
				'endpoint'  => 'https://api.openai.com/v1/chat/completions',
				'json_mode' => true,
				'signup'    => 'https://platform.openai.com/api-keys',
			),
			'anthropic'  => array(
				'label'  => 'Anthropic Claude',
				'free'   => false,
				'model'  => 'claude-haiku-5-5',
				'signup' => 'https://console.anthropic.com/settings/keys',
			),
		);
	}

	/**
	 * Default provider: the WordPress AI Client when the site has it (WordPress 7.0+),
	 * so the site owner's own AI connection is used and no key is stored by this plugin.
	 * Direct providers remain available as a fallback for older WordPress versions.
	 *
	 * @return string
	 */
	public static function default_provider() {
		return function_exists( 'wp_ai_client_prompt' ) ? 'wordpress' : 'groq';
	}

	/**
	 * Short plain-language note shown with each provider.
	 *
	 * @param string $slug Provider slug.
	 * @return string
	 */
	public static function provider_note( $slug ) {
		$notes = array(
			'wordpress'  => __( 'Uses the AI service connected to this site in Settings > Connectors (WordPress 7.0 or later). No key is needed here; any usage is billed by the service you connected there.', 'uniinbox-for-contact-form-7' ),
			'gemini'     => __( 'Free plan with a Google account, no card needed. Daily limits are low and change often. Google may use free-plan prompts to improve its products in some regions, so keep email and phone removal on.', 'uniinbox-for-contact-form-7' ),
			'groq'       => __( 'Free plan, no card needed, and very fast. Daily limits are modest; when one is hit, the plugin waits and tries again.', 'uniinbox-for-contact-form-7' ),
			'openrouter' => __( 'One free key for many models. Use a model name that ends in ":free". Free usage is tightly capped per day.', 'uniinbox-for-contact-form-7' ),
			'cerebras'   => __( 'Free plan, no card needed. Check the Cerebras site for model names included in the free plan.', 'uniinbox-for-contact-form-7' ),
			'mistral'    => __( 'Mistral\'s free "Experiment" plan asks you to allow your data to be used for training, a poor fit for private messages. Prefer another free provider or a paid Mistral plan.', 'uniinbox-for-contact-form-7' ),
			'openai'     => __( 'Paid. Needs billing set up with OpenAI.', 'uniinbox-for-contact-form-7' ),
			'anthropic'  => __( 'Paid. Needs billing set up with Anthropic.', 'uniinbox-for-contact-form-7' ),
		);
		return isset( $notes[ $slug ] ) ? $notes[ $slug ] : '';
	}

	/**
	 * Provider labels keyed by slug.
	 *
	 * @return array<string,string>
	 */
	public static function providers() {
		$list = array();
		foreach ( self::provider_meta() as $slug => $meta ) {
			$list[ $slug ] = $meta['label'];
		}
		return $list;
	}

	/**
	 * Secret setting keys (never printed back to the page).
	 *
	 * @return string[]
	 */
	public static function secret_keys() {
		$keys = array( 'telegram_token' );
		foreach ( array_keys( self::provider_meta() ) as $slug ) {
			$keys[] = 'api_key_' . $slug;
		}
		return $keys;
	}

	/**
	 * Default settings. Categories and templates are not listed here: until
	 * they are saved, category_list() and templates() return the built-ins.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$providers = array();
		foreach ( self::provider_meta() as $slug => $meta ) {
			$providers[ 'api_key_' . $slug ] = '';
			$providers[ 'model_' . $slug ]   = $meta['model'];
		}

		return $providers + array(
			'ai_enabled'          => 0,
			'auto_analyze'        => 1,
			'provider'            => self::default_provider(),
			'tone'                => 'friendly',
			'reply_language'      => 'sender',
			'business_context'    => '',
			'redact_pii'          => 1,
			'daily_cap'           => 200,
			'min_chars'           => 10,
			'max_links'           => 3,
			'excluded_forms'      => array(),
			'from_name'           => '',
			'reply_to'            => '',
			'signature'           => '',
			'use_signature'       => 1,
			'quote_original'      => 0,
			'notify_email'        => '',
			'slack_webhook'       => '',
			'telegram_token'      => '',
			'telegram_chat_id'    => '',
			'notify_min_urgency'  => 'high',
			'digest_enabled'      => 0,
			'digest_email'        => '',
			'retention_days'      => 0,
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		return array_merge( self::defaults(), self::raw() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Whether a secret has been saved.
	 *
	 * @param string $key Secret setting key.
	 * @return bool
	 */
	public static function has_secret( $key ) {
		return '' !== (string) self::get( $key, '' );
	}

	/**
	 * The API key of the active provider.
	 *
	 * @return string
	 */
	public static function active_api_key() {
		$provider = (string) self::get( 'provider', self::default_provider() );
		return (string) self::get( 'api_key_' . $provider, '' );
	}

	/**
	 * Whether AI analysis is switched on and configured.
	 *
	 * @return bool
	 */
	public static function ai_ready() {
		if ( ! self::get( 'ai_enabled' ) ) {
			return false;
		}
		if ( 'wordpress' === self::get( 'provider', self::default_provider() ) ) {
			return function_exists( 'wp_ai_client_prompt' ) && ( ! function_exists( 'wp_supports_ai' ) || wp_supports_ai() );
		}
		return '' !== self::active_api_key();
	}

	/**
	 * Whether a model name looks valid.
	 *
	 * @param string $model Model name.
	 * @return bool
	 */
	public static function valid_model( $model ) {
		return (bool) preg_match( '/^[A-Za-z0-9._:\/-]{1,100}$/', (string) $model );
	}

	/**
	 * Validate a Slack incoming webhook URL.
	 *
	 * @param string $raw Raw URL.
	 * @return string Valid URL or empty string.
	 */
	public static function sanitize_slack( $raw ) {
		$url = esc_url_raw( trim( (string) $raw ), array( 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		return ( isset( $parts['host'] ) && 'hooks.slack.com' === $parts['host'] ) ? $url : '';
	}

	/**
	 * Validate a Telegram bot token.
	 *
	 * @param string $raw Raw token.
	 * @return string Valid token or empty string.
	 */
	public static function sanitize_telegram_token( $raw ) {
		$token = trim( sanitize_text_field( (string) $raw ) );
		return preg_match( '/^\d{5,}:[A-Za-z0-9_-]{20,}$/', $token ) ? $token : '';
	}

	/**
	 * Validate a Telegram chat ID or @channel name.
	 *
	 * @param string $raw Raw value.
	 * @return string Valid value or empty string.
	 */
	public static function sanitize_telegram_chat( $raw ) {
		$chat = trim( sanitize_text_field( (string) $raw ) );
		return preg_match( '/^-?\d{1,20}$|^@[A-Za-z0-9_]{4,64}$/', $chat ) ? $chat : '';
	}

	/**
	 * Sanitize a comma separated list of email addresses.
	 *
	 * @param mixed $raw Raw string.
	 * @param int   $max Maximum number of addresses.
	 * @return string
	 */
	public static function sanitize_email_list( $raw, $max = 10 ) {
		$valid = array();
		foreach ( preg_split( '/[,;]/', (string) $raw ) as $email ) {
			$email = sanitize_email( trim( $email ) );
			if ( is_email( $email ) ) {
				$valid[] = strtolower( $email );
			}
		}
		return implode( ', ', array_slice( array_unique( $valid ), 0, $max ) );
	}

	/**
	 * Sanitize the settings array coming from the settings form.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$existing = self::all();
		$out      = self::defaults();

		foreach ( array( 'ai_enabled', 'auto_analyze', 'redact_pii', 'use_signature', 'quote_original', 'digest_enabled', 'delete_on_uninstall' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$provider        = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : self::default_provider();
		$out['provider'] = array_key_exists( $provider, self::provider_meta() ) ? $provider : self::default_provider();

		// Secrets: blank keeps the saved value, the "clear" box removes it.
		foreach ( self::secret_keys() as $secret ) {
			$clear = ! empty( $input[ 'clear_' . $secret ] );
			$value = isset( $input[ $secret ] ) ? trim( sanitize_text_field( $input[ $secret ] ) ) : '';
			if ( $clear ) {
				$out[ $secret ] = '';
			} elseif ( '' !== $value ) {
				$out[ $secret ] = $value;
			} else {
				$out[ $secret ] = (string) $existing[ $secret ];
			}
		}
		if ( '' !== $out['telegram_token'] ) {
			$out['telegram_token'] = self::sanitize_telegram_token( $out['telegram_token'] );
		}

		foreach ( self::provider_meta() as $slug => $meta ) {
			$model                   = isset( $input[ 'model_' . $slug ] ) ? trim( sanitize_text_field( $input[ 'model_' . $slug ] ) ) : '';
			$out[ 'model_' . $slug ] = self::valid_model( $model ) ? $model : $meta['model'];
		}

		$tone        = isset( $input['tone'] ) ? sanitize_key( $input['tone'] ) : 'friendly';
		$out['tone'] = in_array( $tone, array( 'friendly', 'professional', 'concise' ), true ) ? $tone : 'friendly';

		$language              = isset( $input['reply_language'] ) ? sanitize_key( $input['reply_language'] ) : 'sender';
		$out['reply_language'] = 'site' === $language ? 'site' : 'sender';

		$out['business_context'] = isset( $input['business_context'] ) ? mb_substr( sanitize_textarea_field( $input['business_context'] ), 0, 1500 ) : '';

		$out['daily_cap']      = self::clamp_int( isset( $input['daily_cap'] ) ? $input['daily_cap'] : 200, 0, 5000 );
		$out['min_chars']      = self::clamp_int( isset( $input['min_chars'] ) ? $input['min_chars'] : 10, 0, 500 );
		$out['max_links']      = self::clamp_int( isset( $input['max_links'] ) ? $input['max_links'] : 3, 0, 50 );
		$out['retention_days'] = self::clamp_int( isset( $input['retention_days'] ) ? $input['retention_days'] : 0, 0, 3650 );

		$excluded              = isset( $input['excluded_forms'] ) && is_array( $input['excluded_forms'] ) ? $input['excluded_forms'] : array();
		$out['excluded_forms'] = array_values( array_unique( array_filter( array_map( 'absint', $excluded ) ) ) );

		$from_name        = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '';
		$out['from_name'] = mb_substr( preg_replace( '/[\r\n<>"]/', '', $from_name ), 0, 80 );
		$reply_to         = isset( $input['reply_to'] ) ? sanitize_email( $input['reply_to'] ) : '';
		$out['reply_to']  = is_email( $reply_to ) ? $reply_to : '';
		$out['signature'] = isset( $input['signature'] ) ? mb_substr( sanitize_textarea_field( $input['signature'] ), 0, 1000 ) : '';

		$out['notify_email']     = self::sanitize_email_list( isset( $input['notify_email'] ) ? $input['notify_email'] : '' );
		$out['slack_webhook']    = self::sanitize_slack( isset( $input['slack_webhook'] ) ? $input['slack_webhook'] : '' );
		$out['telegram_chat_id'] = self::sanitize_telegram_chat( isset( $input['telegram_chat_id'] ) ? $input['telegram_chat_id'] : '' );

		$urgency                   = isset( $input['notify_min_urgency'] ) ? sanitize_key( $input['notify_min_urgency'] ) : 'high';
		$out['notify_min_urgency'] = array_key_exists( $urgency, self::urgencies() ) ? $urgency : 'high';

		$digest_email        = isset( $input['digest_email'] ) ? sanitize_email( $input['digest_email'] ) : '';
		$out['digest_email'] = is_email( $digest_email ) ? $digest_email : '';

		// Repeaters. A hidden "*_present" marker means the list was on the form,
		// so an empty list is a real "delete all" and not a missing field.
		if ( ! empty( $input['categories_present'] ) || isset( $input['categories'] ) ) {
			$out['categories'] = self::sanitize_categories( isset( $input['categories'] ) ? $input['categories'] : array() );
		} else {
			$out['categories'] = self::category_list();
		}

		if ( ! empty( $input['templates_present'] ) || isset( $input['templates'] ) ) {
			$out['templates'] = self::sanitize_templates( isset( $input['templates'] ) ? $input['templates'] : array() );
		} else {
			$out['templates'] = self::templates();
		}

		return $out;
	}

	/**
	 * Sanitize the category rows.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array[]
	 */
	public static function sanitize_categories( $rows ) {
		$defaults = array();
		foreach ( self::default_categories() as $row ) {
			$defaults[ $row['slug'] ] = $row;
		}
		$locked = self::locked_categories();
		$out    = array();
		$seen   = array();

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX_CATEGORIES ) {
				continue;
			}

			$label = isset( $row['label'] ) ? mb_substr( trim( sanitize_text_field( $row['label'] ) ), 0, 40 ) : '';
			$slug  = isset( $row['slug'] ) ? substr( sanitize_key( $row['slug'] ), 0, 30 ) : '';

			if ( '' === $slug ) {
				if ( '' === $label ) {
					continue;
				}
				$slug = substr( sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) ), 0, 30 );
				if ( '' === $slug ) {
					$slug = 'category';
				}
				$base   = substr( $slug, 0, 26 );
				$suffix = 2;
				while ( isset( $seen[ $slug ] ) || ( in_array( $slug, $locked, true ) ) ) {
					$slug = $base . '_' . $suffix;
					++$suffix;
				}
			}

			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}

			if ( '' === $label ) {
				if ( ! isset( $defaults[ $slug ] ) ) {
					continue;
				}
				$label = $defaults[ $slug ]['label'];
			}

			$color = sanitize_hex_color( isset( $row['color'] ) ? (string) $row['color'] : '' );

			$out[]         = array(
				'slug'        => $slug,
				'label'       => $label,
				'color'       => $color ? $color : self::DEFAULT_COLOR,
				'description' => isset( $row['description'] ) ? mb_substr( sanitize_text_field( $row['description'] ), 0, 200 ) : '',
				'alert'       => ( 'spam' !== $slug && ! empty( $row['alert'] ) ) ? 1 : 0,
			);
			$seen[ $slug ] = true;
		}

		foreach ( $locked as $slug ) {
			if ( ! isset( $seen[ $slug ] ) ) {
				$out[] = $defaults[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Sanitize the reply template rows.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array[]
	 */
	public static function sanitize_templates( $rows ) {
		$out  = array();
		$seen = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX_TEMPLATES ) {
				continue;
			}
			$name = isset( $row['name'] ) ? mb_substr( trim( sanitize_text_field( $row['name'] ) ), 0, 60 ) : '';
			$body = isset( $row['body'] ) ? mb_substr( trim( sanitize_textarea_field( $row['body'] ) ), 0, 5000 ) : '';
			if ( '' === $body ) {
				continue;
			}
			if ( '' === $name ) {
				$name = mb_substr( wp_strip_all_tags( $body ), 0, 40 );
			}
			$id = isset( $row['id'] ) ? substr( sanitize_key( $row['id'] ), 0, 40 ) : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 't' . substr( md5( $name . '|' . $body . '|' . count( $out ) ), 0, 10 );
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'id'   => $id,
				'name' => $name,
				'body' => $body,
			);
		}
		return $out;
	}

	/**
	 * Clamp a value to an integer range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private static function clamp_int( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}
}
