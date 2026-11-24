<?php
/**
 * Small rendering helpers shared by the admin screens.
 *
 * Every helper returns escaped HTML.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * UI helpers.
 */
class UICF7_UI {

	/**
	 * Tags and attributes the helpers below may output. Used with wp_kses()
	 * wherever helper markup is echoed, so output is escaped at the point of use.
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function allowed_html() {
		if ( ! has_filter( 'safe_style_css', array( __CLASS__, 'safe_style_css' ) ) ) {
			add_filter( 'safe_style_css', array( __CLASS__, 'safe_style_css' ) );
		}
		$common = array(
			'class'       => true,
			'style'       => true,
			'title'       => true,
			'aria-hidden' => true,
			'aria-label'  => true,
		);
		return array(
			'span'   => $common,
			'time'   => array(
				'datetime' => true,
				'title'    => true,
			),
			'label'  => array(
				'class' => true,
				'for'   => true,
			),
			'input'  => array(
				'type'    => true,
				'id'      => true,
				'name'    => true,
				'value'   => true,
				'checked' => true,
				'class'   => true,
			),
			'strong' => array(),
			'small'  => array(),
		);
	}

	/**
	 * Allow the avatar's hue custom property through wp_kses() on WordPress
	 * versions before 6.1 (newer versions allow custom properties already).
	 *
	 * @param string[] $props Allowed CSS properties.
	 * @return string[]
	 */
	public static function safe_style_css( $props ) {
		$props[] = '--uicf7-hue';
		return $props;
	}

	/**
	 * One or two initials for an avatar.
	 *
	 * @param string $name  Name.
	 * @param string $email Email.
	 * @return string
	 */
	public static function initials( $name, $email = '' ) {
		$name = trim( (string) $name );
		if ( '' === $name ) {
			$name = (string) strtok( (string) $email, '@' );
		}
		if ( '' === $name ) {
			return '?';
		}
		$parts = preg_split( '/[\s._-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY );
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
		return mb_strtoupper( $first . $last );
	}

	/**
	 * Avatar with a stable color per sender.
	 *
	 * @param string $name  Name.
	 * @param string $email Email.
	 * @param string $size  sm|md|lg.
	 * @return string
	 */
	public static function avatar( $name, $email = '', $size = 'md' ) {
		$seed = strtolower( '' !== (string) $email ? (string) $email : (string) $name );
		$hue  = hexdec( substr( md5( $seed ), 0, 3 ) ) % 360;
		$size = in_array( $size, array( 'sm', 'md', 'lg' ), true ) ? $size : 'md';
		return sprintf(
			'<span class="uicf7-avatar uicf7-avatar--%1$s" style="--uicf7-hue:%2$d" aria-hidden="true">%3$s</span>',
			esc_attr( $size ),
			(int) $hue,
			esc_html( self::initials( $name, $email ) )
		);
	}

	/**
	 * Category pill with its color dot.
	 *
	 * @param string $slug Category slug.
	 * @return string
	 */
	public static function category_badge( $slug ) {
		$slug = (string) $slug;
		if ( '' === $slug ) {
			return '<span class="uicf7-muted" aria-label="' . esc_attr__( 'Not analyzed', 'uniinbox-for-contact-form-7' ) . '">&mdash;</span>';
		}
		$cat   = UICF7_Settings::category( $slug );
		$label = $cat ? $cat['label'] : ucfirst( str_replace( '_', ' ', $slug ) );
		$color = $cat ? $cat['color'] : UICF7_Settings::DEFAULT_COLOR;
		return sprintf(
			'<span class="uicf7-pill"><span class="uicf7-dot" style="background:%1$s"></span>%2$s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/**
	 * Priority badge.
	 *
	 * @param string $urgency high|medium|low.
	 * @return string
	 */
	public static function urgency_badge( $urgency ) {
		$urgencies = UICF7_Settings::urgencies();
		if ( empty( $urgency ) || ! isset( $urgencies[ $urgency ] ) ) {
			return '<span class="uicf7-muted" aria-label="' . esc_attr__( 'Not analyzed', 'uniinbox-for-contact-form-7' ) . '">&mdash;</span>';
		}
		return sprintf(
			'<span class="uicf7-badge uicf7-badge--%1$s">%2$s</span>',
			esc_attr( $urgency ),
			esc_html( $urgencies[ $urgency ] )
		);
	}

	/**
	 * Small status chip for the AI state.
	 *
	 * @param array $item Message row.
	 * @return string
	 */
	public static function ai_chip( array $item ) {
		switch ( $item['ai_status'] ) {
			case 'pending':
			case 'processing':
				$text  = __( 'Analyzing…', 'uniinbox-for-contact-form-7' );
				$class = 'is-pending';
				break;
			case 'failed':
				$text  = __( 'AI failed', 'uniinbox-for-contact-form-7' );
				$class = 'is-failed';
				break;
			default:
				return '';
		}
		return sprintf(
			'<span class="uicf7-chip %1$s" title="%2$s">%3$s</span>',
			esc_attr( $class ),
			esc_attr( (string) $item['ai_note'] ),
			esc_html( $text )
		);
	}

	/**
	 * The message text without the name, email and subject fields, which the
	 * screen already shows. A single remaining field is shown without a label.
	 *
	 * @param array $item Message row.
	 * @return string Plain text.
	 */
	public static function message_text( array $item ) {
		$fields = json_decode( (string) $item['field_data'], true );
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return (string) $item['body'];
		}
		$known = array_filter( array( (string) $item['sender_name'], (string) $item['sender_email'], (string) $item['subject'] ), 'strlen' );
		$rest  = array();
		foreach ( $fields as $key => $value ) {
			if ( is_scalar( $value ) && ! in_array( (string) $value, $known, true ) ) {
				$rest[ (string) $key ] = (string) $value;
			}
		}
		if ( empty( $rest ) ) {
			return (string) $item['body'];
		}
		if ( 1 === count( $rest ) ) {
			return reset( $rest );
		}
		$lines = array();
		foreach ( $rest as $key => $value ) {
			$label   = ucfirst( trim( str_replace( array( '-', '_' ), ' ', preg_replace( '/^your[-_]/i', '', $key ) ) ) );
			$lines[] = $label . ': ' . $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Formatted local date and time.
	 *
	 * @param string $mysql_gmt UTC datetime.
	 * @return string Plain text.
	 */
	public static function datetime( $mysql_gmt ) {
		$time = $mysql_gmt ? strtotime( $mysql_gmt . ' UTC' ) : false;
		return $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '';
	}

	/**
	 * "3 hours ago" with the full date on hover.
	 *
	 * @param string $mysql_gmt UTC datetime.
	 * @return string
	 */
	public static function time_ago( $mysql_gmt ) {
		$time = $mysql_gmt ? strtotime( $mysql_gmt . ' UTC' ) : false;
		if ( ! $time ) {
			return '';
		}
		if ( time() - $time > 7 * DAY_IN_SECONDS ) {
			$label = wp_date( get_option( 'date_format' ), $time );
		} else {
			/* translators: %s: human readable time difference, e.g. "3 hours". */
			$label = sprintf( __( '%s ago', 'uniinbox-for-contact-form-7' ), human_time_diff( $time, time() ) );
		}
		return sprintf(
			'<time datetime="%1$s" title="%2$s">%3$s</time>',
			esc_attr( gmdate( 'c', $time ) ),
			esc_attr( self::datetime( $mysql_gmt ) ),
			esc_html( $label )
		);
	}

	/**
	 * Dashicon element.
	 *
	 * @param string $name Dashicon name without the prefix.
	 * @return string
	 */
	public static function icon( $name ) {
		return '<span class="dashicons dashicons-' . esc_attr( $name ) . '" aria-hidden="true"></span>';
	}

	/**
	 * Toggle switch markup.
	 *
	 * @param string $name    Input name.
	 * @param bool   $checked Checked state.
	 * @param string $label   Label.
	 * @param string $help    Optional help text.
	 * @param string $id      Optional input ID.
	 * @return string
	 */
	public static function toggle( $name, $checked, $label, $help = '', $id = '' ) {
		$id = '' !== $id ? $id : 'uicf7-' . substr( md5( $name ), 0, 8 );
		return sprintf(
			'<label class="uicf7-toggle" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s><span class="uicf7-toggle__track" aria-hidden="true"></span><span class="uicf7-toggle__text"><strong>%4$s</strong>%5$s</span></label>',
			esc_attr( $id ),
			esc_attr( $name ),
			$checked ? ' checked' : '',
			esc_html( $label ),
			'' !== $help ? '<small>' . esc_html( $help ) . '</small>' : ''
		);
	}
}
