<?php
/**
 * Which connection takes a payment: the payer's choice, or the form's rules.
 *
 * Payment contract version 2. A form either offers its `connections` for
 * the payer to choose from (those not ready are left out), or picks one with
 * `rules`, checked in order on the server against the answers and the amount
 * the server worked out, then `otherwise`. A rule whose connection is not
 * ready right now is passed over, so a connection that is down sends its
 * payments on down the list rather than nowhere.
 *
 * A rule's `when` is one of:
 *
 * - `amount_over` / `amount_under`: a decimal text in major units, compared
 *   in minor units, exactly (never a float);
 * - `field` and `equals`: a choice answer, matched by value or by label;
 * - `currency`: three letters;
 * - `from` / `until`: YYYY-MM-DD, the site's own calendar day, both ends
 *   included, either left out.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Routing class.
 */
class QAF_Payment_Routing {

	/**
	 * Whether a rule's condition is one this site can check.
	 *
	 * @param array $when Condition.
	 * @return bool Valid.
	 */
	public static function when_valid( $when ) {
		foreach ( array( 'amount_over', 'amount_under' ) as $k ) {
			if ( array_key_exists( $k, $when ) ) {
				return QAF_Payment_Money::decimal_text( $when[ $k ] ) !== null && count( $when ) === 1;
			}
		}
		if ( array_key_exists( 'field', $when ) ) {
			return is_string( $when['field'] ) && $when['field'] !== '' && is_scalar( $when['equals'] ?? null ) && count( $when ) === 2;
		}
		if ( array_key_exists( 'currency', $when ) ) {
			return is_string( $when['currency'] ) && preg_match( '/^[A-Za-z]{3}$/', $when['currency'] ) && count( $when ) === 1;
		}
		$dates = array_intersect_key( $when, array( 'from' => 1, 'until' => 1 ) );
		if ( ! $dates || count( $dates ) !== count( $when ) ) {
			return false;
		}
		foreach ( $dates as $d ) {
			if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) || ! checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a rule's condition holds for this payment.
	 *
	 * @param array  $when     Condition.
	 * @param int    $minor    Amount owed, minor units.
	 * @param string $currency Currency.
	 * @param array  $values   Answers by field ID.
	 * @param array  $choices  Field ID => array( label => value ), for matching by label.
	 * @param string $today    YYYY-MM-DD.
	 * @return bool Matches.
	 */
	public static function matches( $when, $minor, $currency, $values, $choices, $today ) {
		if ( ! self::when_valid( $when ) ) {
			return false;
		}
		foreach ( array( 'amount_over', 'amount_under' ) as $k ) {
			if ( array_key_exists( $k, $when ) ) {
				$limit = QAF_Payment_Money::to_minor( $when[ $k ], $currency );
				if ( $limit === null ) {
					return false;
				}
				return $k === 'amount_over' ? $minor > $limit : $minor < $limit;
			}
		}
		if ( array_key_exists( 'field', $when ) ) {
			$want   = (string) $when['equals'];
			$labels = $choices[ $when['field'] ] ?? array();
			if ( isset( $labels[ $want ] ) ) {
				$want = (string) $labels[ $want ];
			}
			$answer = $values[ $when['field'] ] ?? null;
			foreach ( is_array( $answer ) ? $answer : array( $answer ) as $a ) {
				if ( is_scalar( $a ) && (string) $a === $want ) {
					return true;
				}
			}
			return false;
		}
		if ( array_key_exists( 'currency', $when ) ) {
			return strtolower( $when['currency'] ) === strtolower( $currency );
		}
		return ( ! isset( $when['from'] ) || $today >= $when['from'] ) && ( ! isset( $when['until'] ) || $today <= $when['until'] );
	}

	/**
	 * Each choice field's labels and the values they stand for.
	 *
	 * @param array $model Model.
	 * @return array Field ID => array( label => value ).
	 */
	public static function choice_labels( $model ) {
		$out = array();
		foreach ( QAF_Form_Model::items( $model ) as $entry ) {
			$item = $entry['item'];
			if ( isset( $item['id'] ) && is_array( $item['choices'] ?? null ) ) {
				foreach ( $item['choices'] as $c ) {
					if ( is_array( $c ) && isset( $c['label'], $c['value'] ) && is_scalar( $c['label'] ) && is_scalar( $c['value'] ) ) {
						$out[ $item['id'] ][ (string) $c['label'] ] = (string) $c['value'];
					}
				}
			}
		}
		return $out;
	}

	/**
	 * The connections that may take this payment now, in order.
	 *
	 * By selection, every listed connection that is ready; by rule, the one
	 * connection the first matching, ready rule names, else `otherwise` if it
	 * is ready, else none (the form then cannot take payment).
	 *
	 * @param array    $config   QAF_Payment_Config::get().
	 * @param array    $model    Model.
	 * @param array    $plan     QAF_Payment_Pricing::evaluate(): minor, currency, values.
	 * @param callable $ready    function( $ref ): bool, for tests; QAF_Payment_Settings::ready() otherwise.
	 * @param string   $today    YYYY-MM-DD; the site's today when ''.
	 * @return array References.
	 */
	public static function offered( $config, $model, $plan, $ready = null, $today = '' ) {
		$env   = QAF_Payment_Settings::mode();
		$ready = $ready ? $ready : function ( $ref ) use ( $env ) {
			return QAF_Payment_Connections::exists( $ref ) && QAF_Payment_Settings::ready( $ref, $env );
		};
		if ( empty( $config['by_rule'] ) ) {
			return array_values( array_filter( $config['connections'], $ready ) );
		}
		$today    = $today !== '' ? $today : ( function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' ) );
		$minor    = (int) ( $plan['minor'] ?? 0 );
		$currency = (string) ( $plan['currency'] ?? $config['currency'] );
		$values   = is_array( $plan['values'] ?? null ) ? $plan['values'] : array();
		$labels   = self::choice_labels( $model );
		foreach ( $config['rules'] as $rule ) {
			if ( self::matches( $rule['when'], $minor, $currency, $values, $labels, $today ) && call_user_func( $ready, $rule['use'] ) ) {
				return array( $rule['use'] );
			}
		}
		return ( $config['otherwise'] !== '' && call_user_func( $ready, $config['otherwise'] ) ) ? array( $config['otherwise'] ) : array();
	}
}
