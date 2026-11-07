<?php
/**
 * A form's payment configuration (payment contract version 1).
 *
 * The configuration lives in the form model at `extensions.payments`, and a
 * form's single `payment` field says where its amount comes from in
 * `props.amount`: a fixed positive number, "donation", or "#id" of a total,
 * price, calculation, number or currency field. QUILL checks the same rules
 * before it publishes; the plugin checks them again on publication and again
 * at checkout, because a form can reach the site from anywhere.
 *
 * Nothing in this block is secret. Provider keys never belong in a model.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Config class.
 */
class QAF_Payment_Config {

	/** The contract this plugin implements. */
	const CONTRACT_VERSION = 1;

	/** The model key: `model.extensions.payments`. */
	const EXTENSION_KEY = 'payments';

	/** Providers, in the order a new form offers them. */
	const PROVIDERS = array( 'stripe', 'paypal' );

	/** One-time payment, or a donation the giver sets. */
	const MODES = array( 'payment', 'donation' );

	/** Charge at once, or authorize and capture later. */
	const CAPTURES = array( 'automatic', 'manual' );

	/** Field types an `#id` amount may name. */
	const AMOUNT_FIELD_TYPES = array( 'total', 'price', 'calculation', 'number', 'currency' );

	/** Stripe's limit, the stricter of the two providers. */
	const DESCRIPTOR_MAX = 22;

	/** Stripe's lower limit for a full statement descriptor. */
	const DESCRIPTOR_MIN = 5;

	/**
	 * The raw payments block, or null when the form has none.
	 *
	 * @param array|null $model Model.
	 * @return mixed Block.
	 */
	public static function raw( $model ) {
		if ( ! is_array( $model ) || ! isset( $model['extensions'] ) || ! is_array( $model['extensions'] ) ) {
			return null;
		}
		return $model['extensions'][ self::EXTENSION_KEY ] ?? null;
	}

	/**
	 * The form's payment field (item array), or null.
	 *
	 * @param array|null $model Model.
	 * @return array|null Field.
	 */
	public static function payment_field( $model ) {
		$found = self::payment_fields( $model );
		return $found ? $found[0]['item'] : null;
	}

	/**
	 * Every payment field, as model item entries.
	 *
	 * @param array|null $model Model.
	 * @return array Entries ('item', 'path', 'page').
	 */
	public static function payment_fields( $model ) {
		if ( ! is_array( $model ) || ! isset( $model['pages'] ) ) {
			return array();
		}
		$out = array();
		foreach ( QAF_Form_Model::items( $model ) as $entry ) {
			if ( ( $entry['item']['type'] ?? '' ) === 'payment' ) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Whether this form takes payment under the contract.
	 *
	 * @param array|null $model Model.
	 * @return bool Takes payment.
	 */
	public static function applies( $model ) {
		return (bool) self::payment_fields( $model );
	}

	/**
	 * The configuration with defaults filled in. Call errors() first: this
	 * reader never fails and never reports.
	 *
	 * @param array|null $model Model.
	 * @return array providers, currency, mode, capture, test_mode, statement_descriptor.
	 */
	public static function get( $model ) {
		$raw       = self::raw( $model );
		$raw       = is_array( $raw ) ? $raw : array();
		$providers = array_values( array_intersect( (array) ( $raw['providers'] ?? array() ), self::PROVIDERS ) );
		return array(
			'providers'            => $providers ? $providers : array( 'stripe' ),
			'currency'             => strtolower( (string) ( $raw['currency'] ?? 'usd' ) ),
			'mode'                 => (string) ( $raw['mode'] ?? 'payment' ),
			'capture'              => (string) ( $raw['capture'] ?? 'automatic' ),
			'test_mode'            => ( $raw['test_mode'] ?? true ) !== false,
			'statement_descriptor' => is_string( $raw['statement_descriptor'] ?? null ) ? $raw['statement_descriptor'] : '',
		);
	}

	/**
	 * Every payment problem in a model; empty when it is fine or takes no payment.
	 *
	 * @param array|null $model Model.
	 * @return array List of array( 'path' => ..., 'message' => ... ).
	 */
	public static function errors( $model ) {
		$errors = array();
		$err    = function ( $path, $message ) use ( &$errors ) {
			$errors[] = array( 'path' => $path, 'message' => $message );
		};
		$base = '/extensions/' . self::EXTENSION_KEY;
		$raw  = self::raw( $model );
		if ( is_array( $model ) && isset( $model['extensions'] ) && is_array( $model['extensions'] )
			&& array_key_exists( self::EXTENSION_KEY, $model['extensions'] ) && ! self::is_object( $raw ) ) {
			$err( $base, __( 'The payments block must be a JSON object.', 'quill-accessible-forms' ) );
			$raw = null;
		}
		$fields = self::payment_fields( $model );
		if ( ! self::is_object( $raw ) && ! $fields ) {
			return $errors;
		}
		if ( self::is_object( $raw ) ) {
			self::config_errors( $raw, $base, $err );
		}
		foreach ( array_slice( $fields, 1 ) as $entry ) {
			$err( $entry['path'], __( 'Only one payment field is allowed per form; this is the second.', 'quill-accessible-forms' ) );
		}
		if ( $fields && ! ( self::is_object( $raw ) && is_array( $raw['providers'] ?? null ) && $raw['providers'] ) ) {
			$err( $base, __( 'A form with a payment field needs a payments block naming at least one provider.', 'quill-accessible-forms' ) );
		}
		$currency = is_string( $raw['currency'] ?? null ) ? strtolower( $raw['currency'] ) : 'usd';
		$by_id    = array();
		foreach ( QAF_Form_Model::items( $model ) as $entry ) {
			if ( isset( $entry['item']['id'] ) ) {
				$by_id[ $entry['item']['id'] ] = $entry['item'];
			}
		}
		foreach ( $fields as $entry ) {
			self::amount_errors( $entry['item'], $entry['path'], $by_id, $currency, $err );
		}
		return $errors;
	}

	/**
	 * A JSON object decoded as an associative array (not a list).
	 *
	 * @param mixed $v Value.
	 * @return bool Object.
	 */
	private static function is_object( $v ) {
		return is_array( $v ) && ( $v === array() || array_keys( $v ) !== range( 0, count( $v ) - 1 ) );
	}

	/**
	 * Rules for the block itself.
	 *
	 * @param array    $raw  Block.
	 * @param string   $base Path.
	 * @param callable $err  Reporter.
	 */
	private static function config_errors( $raw, $base, $err ) {
		$providers = $raw['providers'] ?? null;
		if ( array_key_exists( 'providers', $raw ) ) {
			if ( ! is_array( $providers ) || ! $providers ) {
				$err( $base . '/providers', __( 'providers must be a list naming at least one provider.', 'quill-accessible-forms' ) );
			} else {
				$seen = array();
				foreach ( array_values( $providers ) as $i => $p ) {
					if ( ! is_string( $p ) || ! in_array( $p, self::PROVIDERS, true ) ) {
						/* translators: %s: the provider name as written. */
						$err( $base . '/providers/' . $i, sprintf( __( '"%s" is not a payment provider (stripe, paypal).', 'quill-accessible-forms' ), is_scalar( $p ) ? $p : gettype( $p ) ) );
					} elseif ( isset( $seen[ $p ] ) ) {
						/* translators: %s: provider. */
						$err( $base . '/providers/' . $i, sprintf( __( 'The provider "%s" is listed twice.', 'quill-accessible-forms' ), $p ) );
					} else {
						$seen[ $p ] = true;
					}
				}
			}
		}
		if ( array_key_exists( 'currency', $raw ) ) {
			$currency = $raw['currency'];
			if ( ! is_string( $currency ) || ! preg_match( '/^[a-zA-Z]{3}$/', $currency ) ) {
				$err( $base . '/currency', __( 'currency must be a three-letter ISO 4217 code, such as usd.', 'quill-accessible-forms' ) );
			} else {
				foreach ( (array) $providers as $p ) {
					if ( is_string( $p ) && in_array( $p, self::PROVIDERS, true ) && ! QAF_Payment_Money::is_supported( $p, $currency ) ) {
						/* translators: 1: currency code, 2: provider. */
						$err( $base . '/currency', sprintf( __( '%1$s is not a currency this site takes through %2$s.', 'quill-accessible-forms' ), strtoupper( $currency ), self::provider_name( $p ) ) );
					}
				}
			}
		}
		if ( array_key_exists( 'mode', $raw ) && ! in_array( $raw['mode'], self::MODES, true ) ) {
			$err( $base . '/mode', __( 'mode must be payment or donation.', 'quill-accessible-forms' ) );
		}
		if ( array_key_exists( 'capture', $raw ) && ! in_array( $raw['capture'], self::CAPTURES, true ) ) {
			$err( $base . '/capture', __( 'capture must be automatic or manual.', 'quill-accessible-forms' ) );
		}
		if ( array_key_exists( 'test_mode', $raw ) && ! is_bool( $raw['test_mode'] ) ) {
			$err( $base . '/test_mode', __( 'test_mode must be true or false.', 'quill-accessible-forms' ) );
		}
		if ( array_key_exists( 'statement_descriptor', $raw ) ) {
			$d = $raw['statement_descriptor'];
			if ( ! is_string( $d ) ) {
				$err( $base . '/statement_descriptor', __( 'The statement descriptor must be text.', 'quill-accessible-forms' ) );
			} elseif ( $d !== '' ) {
				$len = strlen( $d );
				if ( $len > self::DESCRIPTOR_MAX ) {
					$err( $base . '/statement_descriptor', __( 'The statement descriptor is over 22 characters.', 'quill-accessible-forms' ) );
				} elseif ( $len < self::DESCRIPTOR_MIN ) {
					$err( $base . '/statement_descriptor', __( 'The statement descriptor needs at least 5 characters.', 'quill-accessible-forms' ) );
				} elseif ( preg_match( '/[<>\\\\\'"*]/', $d ) || ! preg_match( '/^[\x20-\x7E]+$/', $d ) ) {
					$err( $base . '/statement_descriptor', __( 'The statement descriptor uses plain letters, digits and spaces only, without < > \\ \' " or *.', 'quill-accessible-forms' ) );
				} elseif ( ! preg_match( '/[A-Za-z]/', $d ) ) {
					$err( $base . '/statement_descriptor', __( 'The statement descriptor needs at least one letter.', 'quill-accessible-forms' ) );
				}
			}
		}
	}

	/**
	 * A positive finite number (not a boolean).
	 *
	 * @param mixed $v Value.
	 * @return bool Positive.
	 */
	public static function positive_number( $v ) {
		return ( is_int( $v ) || ( is_float( $v ) && is_finite( $v ) ) ) && $v > 0;
	}

	/**
	 * Rules for the payment field's amount.
	 *
	 * @param array    $item     Field.
	 * @param string   $path     Path.
	 * @param array    $by_id    Items by ID.
	 * @param string   $currency Currency.
	 * @param callable $err      Reporter.
	 */
	private static function amount_errors( $item, $path, $by_id, $currency, $err ) {
		$props    = is_array( $item['props'] ?? null ) ? $item['props'] : array();
		$amount   = $props['amount'] ?? null;
		$donation = ( $amount === 'donation' );
		foreach ( array( 'donation_min', 'donation_max' ) as $key ) {
			if ( array_key_exists( $key, $props ) ) {
				if ( ! $donation ) {
					/* translators: %s: property name. */
					$err( $path . '/props/' . $key, sprintf( __( '%s only applies to a donation amount.', 'quill-accessible-forms' ), $key ) );
				} elseif ( ! self::positive_number( $props[ $key ] ) ) {
					/* translators: %s: property name. */
					$err( $path . '/props/' . $key, sprintf( __( '%s must be a number greater than zero.', 'quill-accessible-forms' ), $key ) );
				} elseif ( QAF_Payment_Money::to_minor( $props[ $key ], $currency ) === null ) {
					/* translators: %s: property name. */
					$err( $path . '/props/' . $key, sprintf( __( '%s has more decimal places than the currency allows.', 'quill-accessible-forms' ), $key ) );
				}
			}
		}
		if ( $donation && self::positive_number( $props['donation_min'] ?? null ) && self::positive_number( $props['donation_max'] ?? null )
			&& $props['donation_min'] > $props['donation_max'] ) {
			$err( $path . '/props/donation_min', __( 'The smallest donation is larger than the largest.', 'quill-accessible-forms' ) );
		}
		if ( $amount === null ) {
			$err( $path . '/props/amount', __( 'A payment field needs an amount: a fixed number, "#id" of an amount field, or "donation".', 'quill-accessible-forms' ) );
			return;
		}
		if ( self::positive_number( $amount ) ) {
			$minor = QAF_Payment_Money::to_minor( $amount, $currency );
			if ( $minor === null ) {
				$err( $path . '/props/amount', __( 'The amount has more decimal places than the currency allows.', 'quill-accessible-forms' ) );
			} elseif ( $minor > QAF_Payment_Money::MAX_MINOR ) {
				$err( $path . '/props/amount', __( 'The amount is larger than a single payment can be.', 'quill-accessible-forms' ) );
			}
			return;
		}
		if ( ! is_string( $amount ) ) {
			$err( $path . '/props/amount', __( 'The amount must be a number greater than zero, or text.', 'quill-accessible-forms' ) );
			return;
		}
		if ( $donation ) {
			return;
		}
		if ( strpos( $amount, '#' ) === 0 ) {
			$ref    = substr( $amount, 1 );
			$target = $by_id[ $ref ] ?? null;
			if ( $target === null ) {
				/* translators: %s: field ID. */
				$err( $path . '/props/amount', sprintf( __( 'The amount names #%s, which is not in this form.', 'quill-accessible-forms' ), $ref ) );
			} elseif ( ! in_array( $target['type'] ?? '', self::AMOUNT_FIELD_TYPES, true ) ) {
				/* translators: %s: field ID. */
				$err( $path . '/props/amount', sprintf( __( 'The amount names #%s, which is not a total, price, calculation, number or currency field.', 'quill-accessible-forms' ), $ref ) );
			}
			return;
		}
		$err( $path . '/props/amount', __( 'The amount must be a number greater than zero, "#id", or "donation".', 'quill-accessible-forms' ) );
	}

	/**
	 * A provider's display name.
	 *
	 * @param string $provider Provider key.
	 * @return string Name.
	 */
	public static function provider_name( $provider ) {
		return $provider === 'paypal' ? 'PayPal' : 'Stripe';
	}
}
