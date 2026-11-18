<?php
/**
 * A form's payment configuration (payment contract version 2).
 *
 * The configuration lives in the form model at `extensions.payments`, and a
 * form's single `payment` field says where its amount comes from in
 * `props.amount`: a fixed positive number, "donation", or "#id" of a total,
 * price, calculation, number or currency field. QUILL checks the same rules
 * before it publishes; the plugin checks them again on publication and again
 * at checkout, because a form can reach the site from anywhere.
 *
 * Version 2 adds Square, and lets a form name the site's payment
 * connections (QAF_Payment_Connections): `connections` offers several to the
 * payer, in order; `rules` and `otherwise` pick one from the answers
 * (QAF_Payment_Routing). A form uses one way or the other, never both.
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
	const CONTRACT_VERSION = 2;

	/** The model key: `model.extensions.payments`. */
	const EXTENSION_KEY = 'payments';

	/** Providers, in the order a new form offers them. */
	const PROVIDERS = array( 'stripe', 'paypal', 'square' );

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

	/** Square's statement_description_identifier limit. */
	const DESCRIPTOR_MAX_SQUARE = 20;

	/** The kinds of condition a rule may have. */
	const RULE_KINDS = array( 'amount_over', 'amount_under', 'field', 'currency', 'from', 'until' );

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
		$refs      = self::references( $model );
		$providers = array();
		foreach ( $refs as $ref ) {
			$providers[] = QAF_Payment_Settings::provider_of( $ref );
		}
		$providers = $providers ? array_values( array_unique( $providers ) ) : array( 'stripe' );
		$rules     = array();
		foreach ( (array) ( $raw['rules'] ?? array() ) as $rule ) {
			if ( is_array( $rule ) && is_array( $rule['when'] ?? null ) && QAF_Payment_Connections::valid( $rule['use'] ?? null ) ) {
				$rules[] = array( 'when' => $rule['when'], 'use' => $rule['use'] );
			}
		}
		$by_rule = array_key_exists( 'rules', $raw ) || array_key_exists( 'otherwise', $raw );
		return array(
			'providers'            => $providers,
			// The connections the payer may choose from; with rules, the one the rules pick.
			'connections'          => $by_rule ? array() : ( $refs ? $refs : array( 'stripe' ) ),
			'rules'                => $rules,
			'otherwise'            => QAF_Payment_Connections::valid( $raw['otherwise'] ?? null ) ? $raw['otherwise'] : '',
			'by_rule'              => $by_rule,
			'currency'             => strtolower( (string) ( $raw['currency'] ?? 'usd' ) ),
			'mode'                 => (string) ( $raw['mode'] ?? 'payment' ),
			'capture'              => (string) ( $raw['capture'] ?? 'automatic' ),
			'test_mode'            => ( $raw['test_mode'] ?? true ) !== false,
			'statement_descriptor' => is_string( $raw['statement_descriptor'] ?? null ) ? $raw['statement_descriptor'] : '',
		);
	}

	/**
	 * Every connection a form's payment can go to, first mention first:
	 * its `connections`, or its rules' and `otherwise`, or (a version 1 form)
	 * each listed provider's default connection.
	 *
	 * @param array|null $model Model.
	 * @return array References.
	 */
	public static function references( $model ) {
		$raw = self::raw( $model );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$refs = array();
		if ( array_key_exists( 'rules', $raw ) || array_key_exists( 'otherwise', $raw ) ) {
			foreach ( (array) ( $raw['rules'] ?? array() ) as $rule ) {
				$refs[] = is_array( $rule ) ? ( $rule['use'] ?? null ) : null;
			}
			$refs[] = $raw['otherwise'] ?? null;
		} elseif ( array_key_exists( 'connections', $raw ) ) {
			$refs = (array) $raw['connections'];
		} else {
			$refs = (array) ( $raw['providers'] ?? array() );
		}
		$out = array();
		foreach ( $refs as $ref ) {
			if ( QAF_Payment_Connections::valid( $ref ) && ! in_array( $ref, $out, true ) ) {
				$out[] = $ref;
			}
		}
		return $out;
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
			self::routing_errors( $raw, $base, $err );
			self::square_errors( $model, $raw, $base, $err );
		}
		foreach ( array_slice( $fields, 1 ) as $entry ) {
			$err( $entry['path'], __( 'Only one payment field is allowed per form; this is the second.', 'quill-accessible-forms' ) );
		}
		if ( $fields && ! self::references( $model ) ) {
			$err( $base, __( 'A form with a payment field needs a payments block naming at least one provider or connection.', 'quill-accessible-forms' ) );
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
						$err( $base . '/providers/' . $i, sprintf( __( '"%s" is not a payment provider (stripe, paypal, square).', 'quill-accessible-forms' ), is_scalar( $p ) ? $p : gettype( $p ) ) );
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
	 * Rules for `connections`, `rules` and `otherwise`.
	 *
	 * @param array    $raw  Block.
	 * @param string   $base Path.
	 * @param callable $err  Reporter.
	 */
	private static function routing_errors( $raw, $base, $err ) {
		$bad = __( 'is not a connection: use stripe, paypal or square, or one of them, a colon and the connection\'s short name.', 'quill-accessible-forms' );
		if ( array_key_exists( 'connections', $raw ) ) {
			$list = $raw['connections'];
			if ( ! is_array( $list ) || ! $list ) {
				$err( $base . '/connections', __( 'connections must be a list naming at least one.', 'quill-accessible-forms' ) );
			} else {
				$seen = array();
				foreach ( array_values( $list ) as $i => $ref ) {
					if ( ! QAF_Payment_Connections::valid( $ref ) ) {
						$err( $base . '/connections/' . $i, '"' . ( is_scalar( $ref ) ? $ref : gettype( $ref ) ) . '" ' . $bad );
					} elseif ( isset( $seen[ $ref ] ) ) {
						/* translators: %s: connection. */
						$err( $base . '/connections/' . $i, sprintf( __( 'The connection "%s" is listed twice.', 'quill-accessible-forms' ), $ref ) );
					} else {
						$seen[ $ref ] = true;
					}
				}
			}
		}
		$by_rule = array_key_exists( 'rules', $raw ) || array_key_exists( 'otherwise', $raw );
		if ( ! $by_rule ) {
			return;
		}
		if ( array_key_exists( 'connections', $raw ) ) {
			$err( $base . '/rules', __( 'Choose the connection by the payer\'s selection or by rule, not both.', 'quill-accessible-forms' ) );
		}
		if ( ! QAF_Payment_Connections::valid( $raw['otherwise'] ?? null ) ) {
			$err( $base . '/otherwise', __( 'Rules need an otherwise: the connection that takes every payment no rule catches.', 'quill-accessible-forms' ) );
		}
		$rules = $raw['rules'] ?? array();
		if ( ! is_array( $rules ) ) {
			$err( $base . '/rules', __( 'rules must be a list.', 'quill-accessible-forms' ) );
			return;
		}
		foreach ( array_values( $rules ) as $i => $rule ) {
			$path = $base . '/rules/' . $i;
			$when = is_array( $rule ) ? ( $rule['when'] ?? null ) : null;
			if ( ! is_array( $when ) || ! $when || array_diff( array_keys( $when ), array_merge( self::RULE_KINDS, array( 'equals' ) ) ) ) {
				$err( $path . '/when', __( 'A rule\'s when is one of amount_over, amount_under, field and equals, currency, or from and until.', 'quill-accessible-forms' ) );
			} elseif ( ! QAF_Payment_Routing::when_valid( $when ) ) {
				$err( $path . '/when', __( 'This rule\'s condition is not one the site can check: an amount is a plain decimal, a date is YYYY-MM-DD, a currency is three letters, and a field rule needs equals.', 'quill-accessible-forms' ) );
			}
			if ( ! QAF_Payment_Connections::valid( is_array( $rule ) ? ( $rule['use'] ?? null ) : null ) ) {
				$err( $path . '/use', __( 'Each rule\'s use names a connection.', 'quill-accessible-forms' ) );
			}
		}
	}

	/**
	 * Square's own limits, when the form can pay through Square.
	 *
	 * @param array    $model Model.
	 * @param array    $raw   Block.
	 * @param string   $base  Path.
	 * @param callable $err   Reporter.
	 */
	private static function square_errors( $model, $raw, $base, $err ) {
		$squares = array();
		foreach ( self::references( $model ) as $ref ) {
			if ( QAF_Payment_Settings::provider_of( $ref ) === 'square' ) {
				$squares[] = $ref;
			}
		}
		if ( ! $squares ) {
			return;
		}
		$currency = is_string( $raw['currency'] ?? null ) ? strtolower( $raw['currency'] ) : 'usd';
		if ( preg_match( '/^[a-z]{3}$/', $currency ) && ! QAF_Payment_Money::is_supported( 'square', $currency ) ) {
			/* translators: %s: currency code. */
			$err( $base . '/currency', sprintf( __( '%s is not a currency this site takes through Square.', 'quill-accessible-forms' ), strtoupper( $currency ) ) );
		} else {
			// A Square location charges only in its own country's currency, which Check Connection read.
			$env = QAF_Payment_Settings::mode();
			foreach ( $squares as $ref ) {
				$at = QAF_Payment_Settings::get_public( $ref, $env, 'location_currency' );
				if ( $at !== '' && $at !== $currency ) {
					/* translators: 1: connection name, 2: currency code, 3: currency code. */
					$err( $base . '/currency', sprintf( __( '%1$s takes %2$s only, and this form charges %3$s.', 'quill-accessible-forms' ), QAF_Payment_Connections::name( $ref ), strtoupper( $at ), strtoupper( $currency ) ) );
				}
			}
		}
		$d = $raw['statement_descriptor'] ?? '';
		if ( is_string( $d ) && strlen( $d ) > self::DESCRIPTOR_MAX_SQUARE ) {
			$err( $base . '/statement_descriptor', __( 'Square takes a statement descriptor of at most 20 characters.', 'quill-accessible-forms' ) );
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
		$names = array( 'stripe' => 'Stripe', 'paypal' => 'PayPal', 'square' => 'Square' );
		return $names[ QAF_Payment_Settings::provider_of( $provider ) ] ?? 'Stripe';
	}
}
