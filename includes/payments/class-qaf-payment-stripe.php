<?php
/**
 * Stripe: Payment Intents, the Payment Element, and signed webhooks.
 *
 * Talks to Stripe's REST API directly (no SDK), pinned to one API version.
 * The secret key never leaves the server; the page gets the publishable key
 * and one Payment Intent's client secret, which is what the Payment Element
 * needs and all it can use.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Stripe class.
 */
class QAF_Payment_Stripe {

	const API         = 'https://api.stripe.com/v1';
	const API_VERSION = '2024-06-20';

	/** How old a signed webhook may be, in seconds (Stripe's own default). */
	const TOLERANCE = 300;

	/** The events the webhook endpoint asks for. */
	const EVENTS = array(
		'payment_intent.succeeded',
		'payment_intent.processing',
		'payment_intent.payment_failed',
		'payment_intent.canceled',
		'payment_intent.amount_capturable_updated',
		'charge.refunded',
		'charge.refund.updated',
	);

	/**
	 * Transport.
	 *
	 * @var QAF_Payment_HTTP
	 */
	private $http;

	/**
	 * Clock, for tests.
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * Constructor.
	 *
	 * @param QAF_Payment_HTTP|null $http Transport.
	 * @param callable|null         $now  Clock returning a Unix time.
	 */
	public function __construct( $http = null, $now = null ) {
		$this->http = $http ? $http : new QAF_Payment_HTTP();
		$this->now  = $now ? $now : 'time';
	}

	/**
	 * Call Stripe.
	 *
	 * @param string $env     Environment.
	 * @param string $method  Method.
	 * @param string $path    Path under /v1.
	 * @param array  $body    Form fields.
	 * @param string $idem    Idempotency key.
	 * @return array Reply.
	 */
	private function call( $env, $method, $path, $body = null, $idem = '' ) {
		$headers = array(
			'Authorization'  => 'Bearer ' . QAF_Payment_Settings::get_secret( 'stripe', $env, 'secret_key' ),
			'Stripe-Version' => self::API_VERSION,
		);
		if ( $method === 'POST' ) {
			$headers['Content-Type'] = 'application/x-www-form-urlencoded';
		}
		if ( $idem !== '' ) {
			$headers['Idempotency-Key'] = $idem;
		}
		return $this->http->request( $method, self::API . $path, $headers, $body );
	}

	/**
	 * A safe message from a Stripe error reply: Stripe's own message for a
	 * card problem (written for the payer), a general one otherwise.
	 *
	 * @param array $reply Reply.
	 * @return string Message.
	 */
	private static function message( $reply ) {
		$error = $reply['json']['error'] ?? array();
		if ( ( $error['type'] ?? '' ) === 'card_error' && is_string( $error['message'] ?? null ) ) {
			return $error['message'];
		}
		return $reply['error'] !== '' ? $reply['error'] : __( 'Stripe did not accept the request.', 'quill-accessible-forms' );
	}

	/**
	 * Check the keys: the publishable and secret key must be this
	 * environment's, and Stripe must accept the secret key and agree on the
	 * environment.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok' => bool, 'message' => string ).
	 */
	public function verify_credentials( $env ) {
		$problem = QAF_Payment_Settings::shape_problem( 'stripe', $env );
		if ( $problem !== '' ) {
			return array( 'ok' => false, 'message' => $problem );
		}
		$reply = $this->call( $env, 'GET', '/balance' );
		if ( $reply['status'] !== 200 || ! is_array( $reply['json'] ) ) {
			return array( 'ok' => false, 'message' => $reply['status'] === 401 ? __( 'Stripe did not accept the secret key.', 'quill-accessible-forms' ) : self::message( $reply ) );
		}
		if ( ( $reply['json']['livemode'] ?? null ) !== ( $env === 'live' ) ) {
			return array( 'ok' => false, 'message' => __( 'Stripe says this key belongs to the other environment.', 'quill-accessible-forms' ) );
		}
		return array( 'ok' => true, 'message' => __( 'Stripe accepted the keys.', 'quill-accessible-forms' ) );
	}

	/**
	 * Create the webhook endpoint on Stripe and keep its signing secret,
	 * which Stripe shows only once, at creation.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok' => bool, 'message' => string ).
	 */
	public function create_webhook( $env ) {
		$body = array( 'url' => QAF_Payment_Settings::webhook_url( 'stripe' ), 'description' => 'QUILL Accessible Forms' );
		foreach ( self::EVENTS as $i => $event ) {
			$body[ 'enabled_events[' . $i . ']' ] = $event;
		}
		$reply = $this->call( $env, 'POST', '/webhook_endpoints', $body );
		$json  = $reply['json'];
		if ( $reply['status'] !== 200 || ! is_string( $json['secret'] ?? null ) || strpos( $json['secret'], 'whsec_' ) !== 0 ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		if ( ( $json['livemode'] ?? null ) !== ( $env === 'live' ) ) {
			return array( 'ok' => false, 'message' => __( 'Stripe created the endpoint in the other environment.', 'quill-accessible-forms' ) );
		}
		QAF_Payment_Settings::set_secret( 'stripe', $env, 'webhook_secret', $json['secret'] );
		return array( 'ok' => true, 'message' => __( 'Stripe will send payment events to this site.', 'quill-accessible-forms' ) );
	}

	/**
	 * Start (or resume) the attempt's Payment Intent.
	 *
	 * An attempt that already has a usable intent gets the same one back, and
	 * the creation call carries an idempotency key made from the attempt, so
	 * a double click, a reload or two tabs cannot make a second charge.
	 *
	 * @param array  $attempt    Attempt.
	 * @param string $descriptor Statement descriptor, or ''.
	 * @return array array( 'ok', 'ref', 'client_secret', 'status', 'message' ).
	 */
	public function start( $attempt, $descriptor = '' ) {
		$env = $attempt['environment'];
		if ( $attempt['provider_ref'] !== '' && strpos( $attempt['provider_ref'], 'pi_' ) === 0 ) {
			$reply = $this->call( $env, 'GET', '/payment_intents/' . rawurlencode( $attempt['provider_ref'] ) );
			$pi    = $reply['json'];
			// Anything but a cancelled intent is this attempt's payment, paid or
			// not: never make a second one beside it.
			if ( $reply['status'] === 200 && is_array( $pi ) && ( $pi['status'] ?? '' ) !== 'canceled' ) {
				return $this->started( $pi, $attempt );
			}
		}
		$body = array(
			'amount'                              => (string) (int) $attempt['amount_minor'],
			'currency'                            => strtolower( $attempt['currency'] ),
			'capture_method'                      => $attempt['capture'] === 'manual' ? 'manual' : 'automatic',
			'automatic_payment_methods[enabled]'  => 'true',
			'metadata[qaf_attempt]'               => (string) $attempt['id'],
			'metadata[qaf_form]'                  => (string) $attempt['form_id'],
			'metadata[qaf_entry]'                 => (string) $attempt['entry_id'],
		);
		if ( $descriptor !== '' ) {
			// Card payments take a suffix to the account's own descriptor.
			$body['statement_descriptor_suffix'] = $descriptor;
		}
		$reply = $this->call( $env, 'POST', '/payment_intents', $body, 'qaf-attempt-' . $attempt['id'] . '-' . (int) $attempt['tries'] );
		$pi    = $reply['json'];
		if ( $reply['status'] !== 200 || ! is_array( $pi ) || strpos( (string) ( $pi['id'] ?? '' ), 'pi_' ) !== 0 ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		return $this->started( $pi, $attempt );
	}

	/**
	 * Cancel the attempt's intent, so it can no longer be confirmed.
	 *
	 * @param array $attempt Attempt.
	 * @return array array( 'ok', 'message' ).
	 */
	public function cancel( $attempt ) {
		$reply = $this->call( $attempt['environment'], 'POST', '/payment_intents/' . rawurlencode( $attempt['provider_ref'] ) . '/cancel', array(), 'qaf-cancel-' . $attempt['id'] . '-' . $attempt['provider_ref'] );
		$ok    = $reply['status'] === 200 && ( $reply['json']['status'] ?? '' ) === 'canceled';
		return array( 'ok' => $ok, 'message' => $ok ? '' : self::message( $reply ) );
	}

	/**
	 * The page's part of a started intent, after checking it is ours.
	 *
	 * @param array $pi      Payment Intent.
	 * @param array $attempt Attempt.
	 * @return array Result.
	 */
	private function started( $pi, $attempt ) {
		$check = $this->matches( $pi, $attempt );
		if ( $check !== '' ) {
			return array( 'ok' => false, 'message' => $check );
		}
		return array(
			'ok'            => true,
			'ref'           => $pi['id'],
			'client_secret' => (string) ( $pi['client_secret'] ?? '' ),
			'status'        => self::state( $pi ),
			'message'       => '',
		);
	}

	/**
	 * Why an intent does not belong to an attempt, or ''.
	 *
	 * @param array $pi      Payment Intent.
	 * @param array $attempt Attempt.
	 * @return string Problem.
	 */
	private function matches( $pi, $attempt ) {
		if ( (int) ( $pi['amount'] ?? -1 ) !== (int) $attempt['amount_minor'] || strtolower( (string) ( $pi['currency'] ?? '' ) ) !== strtolower( $attempt['currency'] ) ) {
			return __( 'The payment does not match what this form asked for.', 'quill-accessible-forms' );
		}
		if ( ( $pi['livemode'] ?? null ) !== ( $attempt['environment'] === 'live' ) ) {
			return __( 'The payment was made in the other environment.', 'quill-accessible-forms' );
		}
		return '';
	}

	/**
	 * The contract state of a Payment Intent.
	 *
	 * @param array $pi Payment Intent.
	 * @return string State.
	 */
	public static function state( $pi ) {
		switch ( $pi['status'] ?? '' ) {
			case 'succeeded':
				$charge = is_array( $pi['latest_charge'] ?? null ) ? $pi['latest_charge'] : null;
				return $charge ? self::refund_state( $charge, 'paid' ) : 'paid';
			case 'requires_capture':
				return 'authorized';
			case 'canceled':
				return 'cancelled';
			case 'requires_payment_method':
				return empty( $pi['last_payment_error'] ) ? 'pending' : 'failed';
			default:
				return 'pending';
		}
	}

	/**
	 * Paid, partly refunded or refunded, from a charge.
	 *
	 * @param array  $charge  Charge.
	 * @param string $default State when nothing is refunded.
	 * @return string State.
	 */
	private static function refund_state( $charge, $default ) {
		$refunded = (int) ( $charge['amount_refunded'] ?? 0 );
		if ( $refunded <= 0 ) {
			return $default;
		}
		return $refunded >= (int) ( $charge['amount'] ?? 0 ) ? 'refunded' : 'partially_refunded';
	}

	/**
	 * Read the intent from Stripe, as the site's own check of a payment
	 * (never the browser's word for it).
	 *
	 * @param array $attempt Attempt.
	 * @return array array( 'ok', 'status', 'receipt_url', 'capture_ref', 'refunded_minor', 'message' ).
	 */
	public function fetch( $attempt ) {
		if ( $attempt['provider_ref'] === '' ) {
			return array( 'ok' => false, 'message' => __( 'No payment has been started.', 'quill-accessible-forms' ) );
		}
		$reply = $this->call( $attempt['environment'], 'GET', '/payment_intents/' . rawurlencode( $attempt['provider_ref'] ) . '?expand%5B%5D=latest_charge' );
		$pi    = $reply['json'];
		if ( $reply['status'] !== 200 || ! is_array( $pi ) || ( $pi['id'] ?? '' ) !== $attempt['provider_ref'] ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		$problem = $this->matches( $pi, $attempt );
		if ( $problem !== '' ) {
			return array( 'ok' => false, 'message' => $problem );
		}
		$charge = is_array( $pi['latest_charge'] ?? null ) ? $pi['latest_charge'] : array();
		$error  = $pi['last_payment_error']['message'] ?? '';
		return array(
			'ok'             => true,
			'status'         => self::state( $pi ),
			'receipt_url'    => self::https( $charge['receipt_url'] ?? '' ),
			'capture_ref'    => (string) ( $charge['id'] ?? '' ),
			'refunded_minor' => (int) ( $charge['amount_refunded'] ?? 0 ),
			'message'        => is_string( $error ) ? $error : '',
		);
	}

	/**
	 * An https URL, or ''.
	 *
	 * @param mixed $url URL.
	 * @return string URL.
	 */
	private static function https( $url ) {
		return ( is_string( $url ) && strpos( $url, 'https://' ) === 0 ) ? $url : '';
	}

	/**
	 * Check a Stripe-Signature header against a signing secret.
	 *
	 * @param string $payload Raw body, exactly as received.
	 * @param string $header  Stripe-Signature header.
	 * @param string $secret  whsec_ secret.
	 * @return bool Valid and recent.
	 */
	public function signature_valid( $payload, $header, $secret ) {
		if ( $secret === '' || ! is_string( $header ) || $header === '' ) {
			return false;
		}
		$t      = null;
		$sigs   = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( count( $kv ) !== 2 ) {
				continue;
			}
			if ( $kv[0] === 't' && ctype_digit( $kv[1] ) ) {
				$t = (int) $kv[1];
			} elseif ( $kv[0] === 'v1' ) {
				$sigs[] = $kv[1];
			}
		}
		if ( $t === null || ! $sigs || abs( call_user_func( $this->now ) - $t ) > self::TOLERANCE ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
		foreach ( $sigs as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Read a webhook delivery: verify it, then say what it means.
	 *
	 * The signature is tried with each environment's signing secret; the one
	 * that verifies is the event's environment, and the event's own livemode
	 * must agree with it.
	 *
	 * @param string $payload Raw body.
	 * @param array  $headers Headers, lower-case names.
	 * @return array array( 'ok' => false, 'code' => 400 ) for a bad delivery, or
	 *               array( 'ok' => true, 'event_id', 'env', 'ref', 'status', 'amount_minor',
	 *               'currency', 'refunded_minor', 'receipt_url', 'capture_ref', 'ignore' ).
	 */
	public function read_webhook( $payload, $headers ) {
		$header = (string) ( $headers['stripe-signature'] ?? '' );
		$env    = '';
		foreach ( QAF_Payment_Settings::ENVIRONMENTS as $candidate ) {
			if ( $this->signature_valid( $payload, $header, QAF_Payment_Settings::get_secret( 'stripe', $candidate, 'webhook_secret' ) ) ) {
				$env = $candidate;
				break;
			}
		}
		if ( $env === '' ) {
			return array( 'ok' => false, 'code' => 400 );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || ! is_string( $event['id'] ?? null ) || ( $event['livemode'] ?? null ) !== ( $env === 'live' ) ) {
			return array( 'ok' => false, 'code' => 400 );
		}
		// A correctly signed event proves the signing secret is the right one.
		if ( ! QAF_Payment_Settings::verified( 'stripe', $env, 'webhook' ) ) {
			QAF_Payment_Settings::record_check( 'stripe', $env, 'webhook', true, __( 'A signed event from Stripe arrived and was verified.', 'quill-accessible-forms' ) );
		}
		$type   = (string) ( $event['type'] ?? '' );
		$object = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();
		$out    = array(
			'ok'             => true,
			'event_id'       => $event['id'],
			'env'            => $env,
			'ref'            => '',
			'status'         => '',
			'amount_minor'   => null,
			'currency'       => '',
			'refunded_minor' => null,
			'receipt_url'    => '',
			'capture_ref'    => '',
			'ignore'         => true,
		);
		$map = array(
			'payment_intent.succeeded'                 => 'paid',
			'payment_intent.processing'                => 'pending',
			'payment_intent.payment_failed'            => 'failed',
			'payment_intent.canceled'                  => 'cancelled',
			'payment_intent.amount_capturable_updated' => 'authorized',
		);
		if ( isset( $map[ $type ] ) ) {
			$out['ref']          = (string) ( $object['id'] ?? '' );
			$out['status']       = $map[ $type ];
			$out['amount_minor'] = (int) ( $object['amount'] ?? -1 );
			$out['currency']     = strtolower( (string) ( $object['currency'] ?? '' ) );
			$out['capture_ref']  = is_string( $object['latest_charge'] ?? null ) ? $object['latest_charge'] : '';
			$out['ignore']       = false;
		} elseif ( $type === 'charge.refund.updated' ) {
			// A refund object, not a charge: the site reads the payment back
			// from Stripe rather than guessing the total refunded from one refund.
			$out['ref']    = (string) ( $object['payment_intent'] ?? '' );
			$out['status'] = 'refetch';
			$out['ignore'] = $out['ref'] === '';
		} elseif ( $type === 'charge.refunded' ) {
			$charge                = $object;
			$out['ref']            = (string) ( $charge['payment_intent'] ?? '' );
			$out['amount_minor']   = (int) ( $charge['amount'] ?? -1 );
			$out['currency']       = strtolower( (string) ( $charge['currency'] ?? '' ) );
			$out['refunded_minor'] = (int) ( $charge['amount_refunded'] ?? 0 );
			$out['status']         = self::refund_state( $charge, 'paid' );
			$out['receipt_url']    = self::https( $charge['receipt_url'] ?? '' );
			$out['capture_ref']    = (string) ( $charge['id'] ?? '' );
			$out['ignore']         = $out['ref'] === '';
		}
		return $out;
	}
}
