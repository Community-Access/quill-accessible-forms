<?php
/**
 * Money without binary floating point.
 *
 * Every amount the payments code sends to a provider is an integer count of
 * the currency's smallest unit ("minor units": cents for USD, yen for JPY,
 * fils for KWD). Amounts arrive in major units, as the form model writes
 * them ("25.00", 25, 19.99), and are turned into minor units by reading their
 * decimal digits as text, never by multiplying a float by 100.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Money class.
 */
class QAF_Payment_Money {

	/**
	 * Currencies with no minor unit, as Stripe and PayPal treat them.
	 */
	const ZERO_DECIMAL = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );

	/**
	 * Currencies with three decimal places.
	 */
	const THREE_DECIMAL = array( 'bhd', 'jod', 'kwd', 'omr', 'tnd' );

	/**
	 * The largest amount either provider takes in one payment, in minor units
	 * (Stripe's eight-digit ceiling, the stricter of the two).
	 */
	const MAX_MINOR = 99999999;

	/**
	 * Currencies each provider takes through this plugin. A currency outside
	 * the list is refused when the form is published and again at checkout;
	 * it is never changed into another. Stripe's own list is longer than this
	 * one; these are the ones the plugin has precision rules for.
	 */
	const PROVIDER_CURRENCIES = array(
		'stripe' => array(
			'aed', 'aud', 'brl', 'cad', 'chf', 'czk', 'dkk', 'eur', 'gbp', 'hkd', 'huf', 'ils', 'inr',
			'jpy', 'krw', 'mxn', 'myr', 'nok', 'nzd', 'php', 'pln', 'ron', 'sek', 'sgd', 'thb', 'usd', 'zar',
		),
		'paypal' => array(
			'aud', 'brl', 'cad', 'chf', 'czk', 'dkk', 'eur', 'gbp', 'hkd', 'huf', 'ils', 'jpy', 'mxn',
			'myr', 'nok', 'nzd', 'php', 'pln', 'sek', 'sgd', 'thb', 'usd',
		),
	);

	/**
	 * Decimal places of a currency.
	 *
	 * @param string $currency ISO 4217 code, any case.
	 * @return int 0, 2 or 3.
	 */
	public static function exponent( $currency ) {
		$c = strtolower( (string) $currency );
		if ( in_array( $c, self::ZERO_DECIMAL, true ) ) {
			return 0;
		}
		if ( in_array( $c, self::THREE_DECIMAL, true ) ) {
			return 3;
		}
		return 2;
	}

	/**
	 * Whether a provider takes payment in a currency through this plugin.
	 *
	 * @param string $provider stripe or paypal.
	 * @param string $currency Code.
	 * @return bool Supported.
	 */
	public static function is_supported( $provider, $currency ) {
		return is_string( $currency )
			&& isset( self::PROVIDER_CURRENCIES[ $provider ] )
			&& in_array( strtolower( $currency ), self::PROVIDER_CURRENCIES[ $provider ], true );
	}

	/**
	 * A number or numeric text as plain decimal text: "25", "19.99", "0.3".
	 *
	 * A float is written with ten decimals and its trailing zeros dropped, so
	 * 0.1 + 0.2 reads as "0.3" rather than "0.30000000000000004".
	 *
	 * @param mixed $value Number or text.
	 * @return string|null Decimal text, or null when it is not a plain number.
	 */
	public static function decimal_text( $value ) {
		if ( is_bool( $value ) || $value === null ) {
			return null;
		}
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_float( $value ) ) {
			if ( ! is_finite( $value ) ) {
				return null;
			}
			$text = sprintf( '%.10F', $value );
			if ( strpos( $text, '.' ) !== false ) {
				$text = rtrim( rtrim( $text, '0' ), '.' );
			}
			return $text === '-0' ? '0' : $text;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$text = trim( $value );
		if ( ! preg_match( '/^-?\d+(\.\d+)?$/', $text ) && ! preg_match( '/^-?\.\d+$/', $text ) ) {
			return null;
		}
		if ( $text[0] === '.' ) {
			$text = '0' . $text;
		} elseif ( strpos( $text, '-.' ) === 0 ) {
			$text = '-0' . substr( $text, 1 );
		}
		return $text;
	}

	/**
	 * Major units to minor units.
	 *
	 * @param mixed  $value    Amount in major units.
	 * @param string $currency Currency code.
	 * @param bool   $round    Round half up to the currency's precision; when
	 *                         false, more decimals than the currency has is
	 *                         refused rather than silently changed.
	 * @return int|null Minor units, or null when the amount is not usable.
	 */
	public static function to_minor( $value, $currency, $round = false ) {
		$text = self::decimal_text( $value );
		if ( $text === null ) {
			return null;
		}
		$negative = false;
		if ( $text[0] === '-' ) {
			$negative = true;
			$text     = substr( $text, 1 );
		}
		$exp   = self::exponent( $currency );
		$parts = explode( '.', $text, 2 );
		$whole = ltrim( $parts[0], '0' );
		$frac  = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';
		$carry = 0;
		if ( strlen( $frac ) > $exp ) {
			if ( ! $round ) {
				return null;
			}
			$carry = ( (int) $frac[ $exp ] >= 5 ) ? 1 : 0;
			$frac  = substr( $frac, 0, $exp );
		}
		$frac   = str_pad( $frac, $exp, '0' );
		$digits = ltrim( $whole . $frac, '0' );
		if ( strlen( $digits ) > 12 ) {
			return null;
		}
		$minor = (int) ( $digits === '' ? '0' : $digits ) + $carry;
		return $negative ? -$minor : $minor;
	}

	/**
	 * Minor units to major-unit text with the currency's decimals: "25.00".
	 *
	 * @param int    $minor    Minor units.
	 * @param string $currency Currency code.
	 * @return string Decimal text.
	 */
	public static function to_major( $minor, $currency ) {
		$minor    = (int) $minor;
		$exp      = self::exponent( $currency );
		$negative = $minor < 0;
		$digits   = (string) abs( $minor );
		if ( $exp === 0 ) {
			return ( $negative ? '-' : '' ) . $digits;
		}
		$digits = str_pad( $digits, $exp + 1, '0', STR_PAD_LEFT );
		return ( $negative ? '-' : '' ) . substr( $digits, 0, -$exp ) . '.' . substr( $digits, -$exp );
	}

	/**
	 * A percentage of an amount, rounded half up, in integer arithmetic.
	 *
	 * @param int   $minor   Minor units.
	 * @param mixed $percent Percentage, up to two decimals (12.5 is 12.5%).
	 * @return int|null Minor units, or null for an unusable percentage.
	 */
	public static function percent_of( $minor, $percent ) {
		$basis = self::to_minor( $percent, 'usd', true );
		if ( $basis === null || $basis < 0 ) {
			return null;
		}
		return intdiv( (int) $minor * $basis + 5000, 10000 );
	}

	/**
	 * How a person hears an amount: "25.00 USD".
	 *
	 * @param int    $minor    Minor units.
	 * @param string $currency Currency code.
	 * @return string Text.
	 */
	public static function spoken( $minor, $currency ) {
		return self::to_major( $minor, $currency ) . ' ' . strtoupper( (string) $currency );
	}
}
