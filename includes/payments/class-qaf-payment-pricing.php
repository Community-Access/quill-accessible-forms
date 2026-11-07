<?php
/**
 * What a submission owes, worked out on the server.
 *
 * The page shows totals as the visitor types, but nothing the browser sends
 * about money is believed: not a total, not a price, not a calculation, not a
 * coupon's discount, not the currency and not a "paid" flag. This class takes
 * the visitor's own answers (which product, how many, which coupon code, how
 * much to give) and recomputes everything else from the form model.
 *
 * Calculated fields (price, calculation, total, coupon) are evaluated with
 * the model's expression language over the server's own values, repeatedly
 * until they settle, so a total that names a calculation that names a price
 * sees the server's price and never the submitted one.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Pricing class.
 */
class QAF_Payment_Pricing {

	/** Field types whose value the server works out itself. */
	const COMPUTED = array( 'price', 'calculation', 'total', 'coupon' );

	/**
	 * What this submission owes.
	 *
	 * @param array $model  Form model.
	 * @param array $values The visitor's sanitized answers, by field ID.
	 * @param array $extra  Expression context (today, user).
	 * @return array|WP_Error array( 'due' => bool, 'minor' => int, 'currency' => string,
	 *                        'values' => answers with computed fields replaced ), or an error
	 *                        whose data has 'status' 400 and 'errors' by field ID.
	 */
	public static function evaluate( $model, $values, $extra = array() ) {
		$config   = QAF_Payment_Config::get( $model );
		$currency = $config['currency'];
		$field    = QAF_Payment_Config::payment_field( $model );
		$result   = array(
			'due'      => false,
			'minor'    => 0,
			'currency' => $currency,
			'values'   => $values,
		);
		if ( ! $field ) {
			return $result;
		}
		$errors   = array();
		$computed = self::compute_values( $model, is_array( $values ) ? $values : array(), $currency, $extra, $errors );
		if ( $errors ) {
			return self::error( $errors );
		}
		$result['values'] = $computed;
		if ( ! QAF_Form_Model::is_shown( $model, $field['id'], $computed, $extra ) ) {
			return $result;
		}

		$props  = is_array( $field['props'] ?? null ) ? $field['props'] : array();
		$amount = $props['amount'] ?? null;
		$fields = QAF_Form_Model::fields( $model );
		$minor  = null;

		if ( QAF_Payment_Config::positive_number( $amount ) ) {
			$minor = QAF_Payment_Money::to_minor( $amount, $currency );
		} elseif ( $amount === 'donation' ) {
			$minor = self::entered( $values[ $field['id'] ] ?? '', $currency, $field['id'], $errors );
			if ( $minor !== null ) {
				$low  = isset( $props['donation_min'] ) ? QAF_Payment_Money::to_minor( $props['donation_min'], $currency ) : null;
				$high = isset( $props['donation_max'] ) ? QAF_Payment_Money::to_minor( $props['donation_max'], $currency ) : null;
				if ( $low !== null && $minor < $low ) {
					/* translators: %s: smallest amount with currency, such as 5.00 USD. */
					$errors[ $field['id'] ] = sprintf( __( 'The smallest gift this form takes is %s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $low, $currency ) );
				} elseif ( $high !== null && $minor > $high ) {
					/* translators: %s: largest amount with currency. */
					$errors[ $field['id'] ] = sprintf( __( 'The largest gift this form takes is %s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $high, $currency ) );
				}
			}
		} elseif ( is_string( $amount ) && strpos( $amount, '#' ) === 0 ) {
			$ref    = substr( $amount, 1 );
			$target = $fields[ $ref ] ?? null;
			$type   = $target['type'] ?? '';
			if ( in_array( $type, array( 'number', 'currency' ), true ) ) {
				// The visitor's own figure, like a donation: read exactly, never rounded.
				$minor = self::entered( $values[ $ref ] ?? '', $currency, $ref, $errors );
			} elseif ( in_array( $type, QAF_Payment_Config::AMOUNT_FIELD_TYPES, true ) ) {
				$minor = QAF_Payment_Money::to_minor( $computed[ $ref ] ?? 0, $currency, true );
			}
		}

		if ( $errors ) {
			return self::error( $errors );
		}
		if ( $minor === null ) {
			return new WP_Error( 'qaf_payment_amount', __( 'This form cannot work out what to charge. The site owner needs to check its payment field.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
		}
		if ( $minor > QAF_Payment_Money::MAX_MINOR ) {
			return self::error( array( $field['id'] => __( 'That is more than a single payment can be.', 'quill-accessible-forms' ) ) );
		}
		if ( $minor <= 0 ) {
			return $result;
		}
		$result['due']   = true;
		$result['minor'] = $minor;
		return $result;
	}

	/**
	 * An amount the visitor typed, read exactly.
	 *
	 * @param mixed  $value    Answer.
	 * @param string $currency Currency.
	 * @param string $id       Field ID, for the error.
	 * @param array  $errors   Errors, by field ID.
	 * @return int|null Minor units.
	 */
	private static function entered( $value, $currency, $id, &$errors ) {
		$text  = is_scalar( $value ) ? str_replace( array( ',', ' ' ), '', trim( (string) $value ) ) : '';
		$text  = ltrim( $text, '$' );
		$minor = $text === '' ? null : QAF_Payment_Money::to_minor( $text, $currency );
		if ( $minor === null || $minor <= 0 ) {
			$errors[ $id ] = QAF_Payment_Money::exponent( $currency ) === 0
				? __( 'Enter a whole amount greater than zero, such as 2000.', 'quill-accessible-forms' )
				: __( 'Enter an amount greater than zero, such as 20 or 20.50.', 'quill-accessible-forms' );
			return null;
		}
		return $minor;
	}

	/**
	 * An error that names the fields at fault.
	 *
	 * @param array $errors Messages by field ID.
	 * @return WP_Error Error.
	 */
	private static function error( $errors ) {
		return new WP_Error(
			'qaf_payment_fields',
			__( 'Please correct the payment details before sending the form.', 'quill-accessible-forms' ),
			array( 'status' => 400, 'errors' => $errors )
		);
	}

	/**
	 * The answers with every computed field replaced by the server's value.
	 *
	 * @param array  $model    Model.
	 * @param array  $values   Answers.
	 * @param string $currency Currency.
	 * @param array  $extra    Context.
	 * @param array  $errors   Errors, by field ID.
	 * @return array Values.
	 */
	public static function compute_values( $model, $values, $currency, $extra, &$errors ) {
		$fields = QAF_Form_Model::fields( $model );
		$out    = $values;
		foreach ( $fields as $id => $f ) {
			if ( in_array( $f['type'] ?? '', self::COMPUTED, true ) ) {
				$out[ $id ] = 0;
			}
		}
		$passes = count( $fields ) + 1;
		for ( $pass = 0; $pass < $passes; $pass++ ) {
			$changed = false;
			$local   = array();
			$sub     = self::subtotal( $model, $fields, $out, $values, $currency, $extra, $local );
			foreach ( $fields as $id => $f ) {
				$type = $f['type'] ?? '';
				if ( ! in_array( $type, self::COMPUTED, true ) ) {
					continue;
				}
				$props = is_array( $f['props'] ?? null ) ? $f['props'] : array();
				if ( $type === 'coupon' ) {
					$minor = self::coupon_discount( $f, $values[ $id ] ?? '', $sub, $currency, $extra, $local );
					$value = $minor === null ? 0 : self::major_number( $minor, $currency );
				} elseif ( ! empty( $f['calculation'] ) && is_string( $f['calculation'] ) ) {
					$value = self::calculate( $f['calculation'], $model, $out, $extra, $id, $local );
				} elseif ( $type === 'price' ) {
					$value = self::number( $props['base_price'] ?? 0 );
				} elseif ( $type === 'total' ) {
					$minor = self::commerce_total( $model, $fields, $f, $out, $values, $sub, $currency, $extra, $local );
					$value = self::major_number( $minor, $currency );
				} else {
					$value = 0;
				}
				if ( $out[ $id ] !== $value ) {
					$out[ $id ] = $value;
					$changed    = true;
				}
			}
			if ( ! $changed ) {
				$errors = array_merge( $errors, $local );
				return $out;
			}
		}
		$errors = array_merge( $errors, $local );
		return $out;
	}

	/**
	 * Evaluate a calculation; a broken expression is the form's fault.
	 *
	 * @param string $expression Expression.
	 * @param array  $model      Model.
	 * @param array  $values     Values.
	 * @param array  $extra      Context.
	 * @param string $id         Field ID.
	 * @param array  $errors     Errors.
	 * @return float|int Number.
	 */
	private static function calculate( $expression, $model, $values, $extra, $id, &$errors ) {
		try {
			$ctx = QAF_Form_Model::context( $model, $values, $extra );
			return self::number( QAF_Expression::evaluate( $expression, $ctx ) );
		} catch ( Exception $e ) {
			$errors[ $id ] = __( 'This total cannot be worked out. The site owner needs to check its calculation.', 'quill-accessible-forms' );
			return 0;
		}
	}

	/**
	 * A number from whatever an expression or a setting gave.
	 *
	 * @param mixed $v Value.
	 * @return float|int Number.
	 */
	private static function number( $v ) {
		if ( is_int( $v ) || ( is_float( $v ) && is_finite( $v ) ) ) {
			return $v;
		}
		if ( is_string( $v ) && is_numeric( trim( $v ) ) ) {
			return trim( $v ) + 0;
		}
		return 0;
	}

	/**
	 * Minor units as a number for expressions: an int when whole.
	 *
	 * @param int    $minor    Minor units.
	 * @param string $currency Currency.
	 * @return float|int Number.
	 */
	private static function major_number( $minor, $currency ) {
		$text = QAF_Payment_Money::to_major( $minor, $currency );
		$num  = $text + 0;
		return ( is_float( $num ) && floor( $num ) == $num ) ? (int) $num : $num; // phpcs:ignore Universal.Operators.StrictComparisons
	}

	/**
	 * The products and prices chosen, before discounts, shipping and tax.
	 *
	 * @param array  $model    Model.
	 * @param array  $fields   Fields by ID.
	 * @param array  $computed Server values.
	 * @param array  $answers  Visitor's answers.
	 * @param string $currency Currency.
	 * @param array  $extra    Context.
	 * @param array  $errors   Errors.
	 * @return int Minor units.
	 */
	private static function subtotal( $model, $fields, $computed, $answers, $currency, $extra, &$errors ) {
		$sum = 0;
		foreach ( $fields as $id => $f ) {
			$type = $f['type'] ?? '';
			if ( ! in_array( $type, array( 'product', 'price' ), true ) || ! QAF_Form_Model::is_shown( $model, $id, $computed, $extra ) ) {
				continue;
			}
			if ( $type === 'product' ) {
				$unit = self::product_price( $f, $answers[ $id ] ?? '', $currency, $errors );
			} else {
				$unit = QAF_Payment_Money::to_minor( $computed[ $id ] ?? 0, $currency, true );
			}
			$qty  = self::quantity_for( $model, $fields, $id, $answers, $computed, $extra, $errors );
			$sum += max( 0, (int) $unit ) * $qty;
		}
		return $sum;
	}

	/**
	 * The price of the choice (or choices) made in a product field.
	 *
	 * @param array  $field    Product field.
	 * @param mixed  $answer   Chosen value or values.
	 * @param string $currency Currency.
	 * @param array  $errors   Errors.
	 * @return int Minor units.
	 */
	private static function product_price( $field, $answer, $currency, &$errors ) {
		$chosen = is_array( $answer ) ? $answer : ( ( $answer === '' || $answer === null ) ? array() : array( $answer ) );
		$prices = array();
		foreach ( $field['choices'] ?? array() as $choice ) {
			if ( isset( $choice['value'] ) ) {
				$prices[ (string) $choice['value'] ] = $choice['price'] ?? 0;
			}
		}
		$sum = 0;
		foreach ( $chosen as $value ) {
			$key = is_scalar( $value ) ? (string) $value : '';
			if ( ! array_key_exists( $key, $prices ) ) {
				$errors[ $field['id'] ] = __( 'Choose one of the listed options.', 'quill-accessible-forms' );
				continue;
			}
			$sum += (int) QAF_Payment_Money::to_minor( $prices[ $key ], $currency, true );
		}
		return $sum;
	}

	/**
	 * How many of a product or price: its linked quantity field, else one.
	 *
	 * @param array  $model    Model.
	 * @param array  $fields   Fields.
	 * @param string $id       Product or price field ID.
	 * @param array  $answers  Answers.
	 * @param array  $computed Server values.
	 * @param array  $extra    Context.
	 * @param array  $errors   Errors.
	 * @return int Quantity.
	 */
	private static function quantity_for( $model, $fields, $id, $answers, $computed, $extra, &$errors ) {
		foreach ( $fields as $qid => $q ) {
			if ( ( $q['type'] ?? '' ) !== 'quantity' || ( $q['props']['linked_product'] ?? '' ) !== $id ) {
				continue;
			}
			if ( ! QAF_Form_Model::is_shown( $model, $qid, $computed, $extra ) ) {
				return 0;
			}
			$raw = $answers[ $qid ] ?? ( $q['default'] ?? 1 );
			$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
			if ( ! preg_match( '/^\d{1,6}$/', $raw ) ) {
				$errors[ $qid ] = __( 'Enter a whole number.', 'quill-accessible-forms' );
				return 0;
			}
			$n   = (int) $raw;
			$min = $q['validation']['min'] ?? ( $q['props']['min'] ?? null );
			$max = $q['validation']['max'] ?? ( $q['props']['max'] ?? null );
			if ( is_numeric( $min ) && $n < $min ) {
				/* translators: %s: the smallest quantity. */
				$errors[ $qid ] = sprintf( __( 'Enter at least %s.', 'quill-accessible-forms' ), $min );
			} elseif ( is_numeric( $max ) && $n > $max ) {
				/* translators: %s: the largest quantity. */
				$errors[ $qid ] = sprintf( __( 'Enter no more than %s.', 'quill-accessible-forms' ), $max );
			}
			return $n;
		}
		return 1;
	}

	/**
	 * A coupon code's discount on a subtotal, by the field's own code list.
	 *
	 * Codes are `props.coupons`: code, discount_type (percentage or fixed),
	 * discount_value, and optional min_purchase, max_discount and expires
	 * (a Y-m-d date). An empty code is no discount; a code that is not in
	 * the list, or has expired, is an error on the field.
	 *
	 * @param array  $field    Coupon field.
	 * @param mixed  $code     Entered code.
	 * @param int    $subtotal Minor units.
	 * @param string $currency Currency.
	 * @param array  $extra    Context (today).
	 * @param array  $errors   Errors.
	 * @return int|null Minor units off, or null.
	 */
	public static function coupon_discount( $field, $code, $subtotal, $currency, $extra, &$errors ) {
		$code = is_scalar( $code ) ? trim( (string) $code ) : '';
		if ( $code === '' ) {
			return 0;
		}
		$props = is_array( $field['props'] ?? null ) ? $field['props'] : array();
		$exact = ! empty( $props['case_sensitive'] ) || ! empty( $props['case_sensitive_codes'] );
		foreach ( (array) ( $props['coupons'] ?? array() ) as $c ) {
			if ( ! is_array( $c ) || ! isset( $c['code'] ) ) {
				continue;
			}
			$match = $exact ? ( (string) $c['code'] === $code ) : ( strtolower( (string) $c['code'] ) === strtolower( $code ) );
			if ( ! $match ) {
				continue;
			}
			$today = $extra['today'] ?? gmdate( 'Y-m-d' );
			if ( ! empty( $c['expires'] ) && is_string( $c['expires'] ) && $c['expires'] < $today ) {
				$errors[ $field['id'] ] = __( 'That code has expired.', 'quill-accessible-forms' );
				return null;
			}
			$min = isset( $c['min_purchase'] ) ? QAF_Payment_Money::to_minor( $c['min_purchase'], $currency, true ) : null;
			if ( $min !== null && $subtotal < $min ) {
				/* translators: %s: amount with currency. */
				$errors[ $field['id'] ] = sprintf( __( 'That code needs an order of at least %s.', 'quill-accessible-forms' ), QAF_Payment_Money::spoken( $min, $currency ) );
				return null;
			}
			if ( ( $c['discount_type'] ?? 'percentage' ) === 'fixed' ) {
				$off = (int) QAF_Payment_Money::to_minor( $c['discount_value'] ?? 0, $currency, true );
			} else {
				$off = (int) QAF_Payment_Money::percent_of( $subtotal, $c['discount_value'] ?? 0 );
			}
			$cap = isset( $c['max_discount'] ) ? QAF_Payment_Money::to_minor( $c['max_discount'], $currency, true ) : null;
			if ( $cap !== null && $cap > 0 ) {
				$off = min( $off, $cap );
			}
			return max( 0, min( $off, $subtotal ) );
		}
		$errors[ $field['id'] ] = __( 'That code is not one this form takes.', 'quill-accessible-forms' );
		return null;
	}

	/**
	 * A total with no calculation of its own: products and prices, less
	 * coupons, plus the total field's shipping and tax settings.
	 *
	 * @param array  $model    Model.
	 * @param array  $fields   Fields.
	 * @param array  $total    The total field.
	 * @param array  $computed Server values.
	 * @param array  $answers  Answers.
	 * @param int    $subtotal Minor units.
	 * @param string $currency Currency.
	 * @param array  $extra    Context.
	 * @param array  $errors   Errors.
	 * @return int Minor units.
	 */
	private static function commerce_total( $model, $fields, $total, $computed, $answers, $subtotal, $currency, $extra, &$errors ) {
		$discount = 0;
		foreach ( $fields as $id => $f ) {
			if ( ( $f['type'] ?? '' ) === 'coupon' && QAF_Form_Model::is_shown( $model, $id, $computed, $extra ) ) {
				$discount += (int) self::coupon_discount( $f, $answers[ $id ] ?? '', $subtotal, $currency, $extra, $errors );
			}
		}
		$amount = max( 0, $subtotal - $discount );
		$props  = is_array( $total['props'] ?? null ) ? $total['props'] : array();
		$ship   = isset( $props['shipping_amount'] ) ? (int) QAF_Payment_Money::to_minor( $props['shipping_amount'], $currency, true ) : 0;
		$free   = isset( $props['free_shipping_min'] ) ? (int) QAF_Payment_Money::to_minor( $props['free_shipping_min'], $currency, true ) : 0;
		if ( $ship > 0 && ! ( $free > 0 && $subtotal >= $free ) ) {
			$amount += $ship;
		}
		if ( ! empty( $props['tax_rate'] ) && empty( $props['tax_included'] ) ) {
			$amount += (int) QAF_Payment_Money::percent_of( max( 0, $subtotal - $discount ), $props['tax_rate'] );
		}
		return $amount;
	}
}
