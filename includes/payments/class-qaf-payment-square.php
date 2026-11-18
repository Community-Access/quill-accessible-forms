<?php
/**
 * Square: the Web Payments SDK card field, the Payments API, and signed webhooks.
 *
 * Talks to Square's REST API directly (no SDK), pinned to one API version.
 * The page gets the connection's application ID and location ID, which is
 * what the Web Payments SDK needs to draw its card field and turn a card into
 * a one-time token; the card number goes from the payer's browser to Square
 * and never through this site. The site then makes the payment with that
 * token (`POST /v2/payments`), with an idempotency key made from the attempt,
 * so a double click, a reload or two tabs cannot charge twice.
 *
 * Webhooks (`payment.updated`, `refund.updated`) are verified with the
 * subscription's signature key: an HMAC-SHA256 of the notification URL and
 * the exact body, Base64, in `x-square-hmacsha256-signature`. Refunds go
 * through the Refunds API, always in the connection that took the payment.
 *
 * One instance serves one connection (`square` or `square:downtown`).
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Square class.
 */
class QAF_Payment_Square {

	const API_LIVE    = 'https://connect.squareup.com/v2';
	const API_TEST    = 'https://connect.squareupsandbox.com/v2';
	const API_VERSION = '2024-10-17';

	/** The events the webhook subscription asks for. */
	const EVENTS = array( 'payment.updated', 'refund.updated' );

	/** Square's idempotency keys are at most 45 characters. */
	const KEY_MAX = 45;

	/**
	 * Transport.
	 *
	 * @var QAF_Payment_HTTP
	 */
	private $http;

	/**
	 * The connection this instance serves.
	 *
	 * @var string
	 */
	private $ref;

	/**
	 * Constructor.
	 *
	 * @param QAF_Payment_HTTP|null $http Transport.
	 * @param string                $ref  Connection reference.
	 */
	public function __construct( $http = null, $ref = 'square' ) {
		$this->http = $http ? $http : new QAF_Payment_HTTP();
		$this->ref  = $ref;
	}

	/**
	 * Call Square.
	 *
	 * @param string     $env    Environment.
	 * @param string     $method Method.
	 * @param string     $path   Path under /v2.
	 * @param array|null $body   JSON body.
	 * @return array Reply.
	 */
	private function call( $env, $method, $path, $body = null ) {
		$headers = array(
			'Authorization'  => 'Bearer ' . QAF_Payment_Settings::get_secret( $this->ref, $env, 'access_token' ),
			'Square-Version' => self::API_VERSION,
			'Accept'         => 'application/json',
		);
		if ( $body !== null ) {
			$headers['Content-Type'] = 'application/json';
		}
		$base = $env === 'live' ? self::API_LIVE : self::API_TEST;
		return $this->http->request( $method, $base . $path, $headers, $body === null ? null : wp_json_encode( $body ) );
	}

	/**
	 * A payer-safe message from a Square error reply. Square's own detail is
	 * written for developers, so a card problem gets a plain sentence instead.
	 *
	 * @param array $reply Reply.
	 * @return string Message.
	 */
	private static function message( $reply ) {
		$errors = is_array( $reply['json']['errors'] ?? null ) ? $reply['json']['errors'] : array();
		$first  = is_array( $errors[0] ?? null ) ? $errors[0] : array();
		$code   = (string) ( $first['code'] ?? '' );
		if ( ( $first['category'] ?? '' ) === 'PAYMENT_METHOD_ERROR' ) {
			$card = array(
				'CVV_FAILURE'               => __( 'The card\'s security code was not right. Please check it and try again.', 'quill-accessible-forms' ),
				'ADDRESS_VERIFICATION_FAILURE' => __( 'The card\'s postal code was not right. Please check it and try again.', 'quill-accessible-forms' ),
				'INVALID_EXPIRATION'        => __( 'The card\'s expiry date was not right. Please check it and try again.', 'quill-accessible-forms' ),
				'INSUFFICIENT_FUNDS'        => __( 'The card was declined for insufficient funds. You can try another card.', 'quill-accessible-forms' ),
			);
			return $card[ $code ] ?? __( 'The card was declined. You can check it, or try another card.', 'quill-accessible-forms' );
		}
		if ( $reply['status'] === 401 ) {
			return __( 'Square did not accept the access token.', 'quill-accessible-forms' );
		}
		return $reply['error'] !== '' ? $reply['error'] : __( 'Square did not accept the request.', 'quill-accessible-forms' );
	}

	/**
	 * An idempotency key Square accepts: at most 45 characters.
	 *
	 * @param string $what    What the request does.
	 * @param array  $attempt Attempt.
	 * @return string Key.
	 */
	private static function key( $what, $attempt ) {
		return substr( 'qaf-' . $what . '-' . $attempt['id'] . '-' . (int) $attempt['tries'], 0, self::KEY_MAX );
	}

	/**
	 * Check the keys: Square must accept the access token and know the
	 * location, which must be active and able to take card payments. The
	 * location's currency is kept, because a Square location charges only in
	 * its own country's currency.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok' => bool, 'message' => string ).
	 */
	public function verify_credentials( $env ) {
		$problem = QAF_Payment_Settings::shape_problem( $this->ref, $env );
		if ( $problem !== '' ) {
			return array( 'ok' => false, 'message' => $problem );
		}
		$location_id = QAF_Payment_Settings::get_public( $this->ref, $env, 'location_id' );
		$reply       = $this->call( $env, 'GET', '/locations/' . rawurlencode( $location_id ) );
		$location    = $reply['json']['location'] ?? null;
		if ( $reply['status'] !== 200 || ! is_array( $location ) ) {
			return array( 'ok' => false, 'message' => $reply['status'] === 404 ? __( 'Square does not know that location ID for this access token.', 'quill-accessible-forms' ) : self::message( $reply ) );
		}
		if ( ( $location['status'] ?? '' ) !== 'ACTIVE' ) {
			return array( 'ok' => false, 'message' => __( 'That Square location is not active.', 'quill-accessible-forms' ) );
		}
		if ( ! in_array( 'CREDIT_CARD_PROCESSING', (array) ( $location['capabilities'] ?? array() ), true ) ) {
			return array( 'ok' => false, 'message' => __( 'That Square location cannot take card payments yet.', 'quill-accessible-forms' ) );
		}
		$currency = strtolower( (string) ( $location['currency'] ?? '' ) );
		QAF_Payment_Settings::set_public( $this->ref, $env, 'location_currency', $currency );
		/* translators: %s: currency code. */
		return array( 'ok' => true, 'message' => sprintf( __( 'Square accepted the keys. This location takes %s.', 'quill-accessible-forms' ), strtoupper( $currency ) ) );
	}

	/**
	 * Subscribe this connection's webhook URL to payment and refund events,
	 * and keep the subscription's signature key.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok' => bool, 'message' => string ).
	 */
	public function create_webhook( $env ) {
		$body  = array(
			'idempotency_key' => substr( 'qaf-hook-' . hash( 'sha256', $this->ref . $env . QAF_Payment_Settings::webhook_url( $this->ref ) ), 0, self::KEY_MAX ),
			'subscription'    => array(
				'name'             => 'QUILL Accessible Forms',
				'event_types'      => self::EVENTS,
				'notification_url' => QAF_Payment_Settings::webhook_url( $this->ref ),
				'api_version'      => self::API_VERSION,
			),
		);
		$reply = $this->call( $env, 'POST', '/webhooks/subscriptions', $body );
		$sub   = $reply['json']['subscription'] ?? null;
		if ( $reply['status'] !== 200 || ! is_array( $sub ) || ! is_string( $sub['signature_key'] ?? null ) || $sub['signature_key'] === '' ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		QAF_Payment_Settings::set_secret( $this->ref, $env, 'signature_key', $sub['signature_key'] );
		QAF_Payment_Settings::set_secret( $this->ref, $env, 'subscription_id', (string) ( $sub['id'] ?? '' ) );
		return array( 'ok' => true, 'message' => __( 'Square will send payment and refund events to this site.', 'quill-accessible-forms' ) );
	}

	/**
	 * The page's part of starting: no call to Square yet, because Square
	 * makes the payment from a card token the page has not made yet.
	 *
	 * @param array  $attempt    Attempt.
	 * @param string $descriptor Statement descriptor (used by pay()).
	 * @return array array( 'ok', 'ref', 'status', 'application_id', 'location_id', 'message' ).
	 */
	public function start( $attempt, $descriptor = '' ) {
		$env = $attempt['environment'];
		return array(
			'ok'             => true,
			'ref'            => '',
			'status'         => 'pending',
			'application_id' => QAF_Payment_Settings::get_public( $this->ref, $env, 'application_id' ),
			'location_id'    => QAF_Payment_Settings::get_public( $this->ref, $env, 'location_id' ),
			'message'        => '',
		);
	}

	/**
	 * Make the payment with the card token from the Web Payments SDK.
	 *
	 * @param array  $attempt      Attempt.
	 * @param string $source_id    The SDK's one-time token.
	 * @param string $verification The SDK's buyer verification token, or ''.
	 * @param string $descriptor   Statement descriptor, or ''.
	 * @return array array( 'ok', 'ref', 'status', 'receipt_url', 'message' ).
	 */
	public function pay( $attempt, $source_id, $verification = '', $descriptor = '' ) {
		$env  = $attempt['environment'];
		$body = array(
			'idempotency_key' => self::key( 'pay', $attempt ),
			'source_id'       => (string) $source_id,
			'amount_money'    => array(
				'amount'   => (int) $attempt['amount_minor'],
				'currency' => strtoupper( $attempt['currency'] ),
			),
			'location_id'     => QAF_Payment_Settings::get_public( $this->ref, $env, 'location_id' ),
			'autocomplete'    => $attempt['capture'] !== 'manual',
			'reference_id'    => 'qaf-' . $attempt['id'],
			'note'            => 'QUILL Accessible Forms: form ' . (int) $attempt['form_id'] . ', entry ' . (int) $attempt['entry_id'],
		);
		if ( $verification !== '' ) {
			$body['verification_token'] = $verification;
		}
		if ( $descriptor !== '' ) {
			$body['statement_description_identifier'] = substr( $descriptor, 0, QAF_Payment_Config::DESCRIPTOR_MAX_SQUARE );
		}
		$reply   = $this->call( $env, 'POST', '/payments', $body );
		$payment = $reply['json']['payment'] ?? null;
		if ( $reply['status'] !== 200 || ! is_array( $payment ) || ! is_string( $payment['id'] ?? null ) ) {
			return array( 'ok' => false, 'status' => 'failed', 'message' => self::message( $reply ) );
		}
		$problem = $this->matches( $payment, $attempt );
		if ( $problem !== '' ) {
			return array( 'ok' => false, 'status' => 'failed', 'message' => $problem );
		}
		return array(
			'ok'          => true,
			'ref'         => $payment['id'],
			'status'      => self::state( $payment ),
			'receipt_url' => self::https( $payment['receipt_url'] ?? '' ),
			'message'     => '',
		);
	}

	/**
	 * Why a payment does not belong to an attempt, or ''.
	 *
	 * @param array $payment Payment.
	 * @param array $attempt Attempt.
	 * @return string Problem.
	 */
	private function matches( $payment, $attempt ) {
		$money = is_array( $payment['amount_money'] ?? null ) ? $payment['amount_money'] : array();
		if ( (int) ( $money['amount'] ?? -1 ) !== (int) $attempt['amount_minor'] || strtolower( (string) ( $money['currency'] ?? '' ) ) !== strtolower( $attempt['currency'] ) ) {
			return __( 'The payment does not match what this form asked for.', 'quill-accessible-forms' );
		}
		$location = QAF_Payment_Settings::get_public( $this->ref, $attempt['environment'], 'location_id' );
		if ( (string) ( $payment['location_id'] ?? '' ) !== $location ) {
			return __( 'The payment was taken at another Square location.', 'quill-accessible-forms' );
		}
		return '';
	}

	/**
	 * The contract state of a Square payment.
	 *
	 * @param array $payment Payment.
	 * @return string State.
	 */
	public static function state( $payment ) {
		switch ( $payment['status'] ?? '' ) {
			case 'COMPLETED':
				$refunded = (int) ( $payment['refunded_money']['amount'] ?? 0 );
				if ( $refunded <= 0 ) {
					return 'paid';
				}
				return $refunded >= (int) ( $payment['amount_money']['amount'] ?? 0 ) ? 'refunded' : 'partially_refunded';
			case 'APPROVED':
				return 'authorized';
			case 'CANCELED':
				return 'cancelled';
			case 'FAILED':
				return 'failed';
			default:
				return 'pending';
		}
	}

	/**
	 * Read the payment from Square, as the site's own check.
	 *
	 * @param array $attempt Attempt.
	 * @return array array( 'ok', 'status', 'receipt_url', 'refunded_minor', 'message' ).
	 */
	public function fetch( $attempt ) {
		if ( $attempt['provider_ref'] === '' ) {
			return array( 'ok' => false, 'message' => __( 'No payment has been started.', 'quill-accessible-forms' ) );
		}
		$reply   = $this->call( $attempt['environment'], 'GET', '/payments/' . rawurlencode( $attempt['provider_ref'] ) );
		$payment = $reply['json']['payment'] ?? null;
		if ( $reply['status'] !== 200 || ! is_array( $payment ) || ( $payment['id'] ?? '' ) !== $attempt['provider_ref'] ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		$problem = $this->matches( $payment, $attempt );
		if ( $problem !== '' ) {
			return array( 'ok' => false, 'message' => $problem );
		}
		return array(
			'ok'             => true,
			'status'         => self::state( $payment ),
			'receipt_url'    => self::https( $payment['receipt_url'] ?? '' ),
			'refunded_minor' => (int) ( $payment['refunded_money']['amount'] ?? 0 ),
			'message'        => '',
		);
	}

	/**
	 * Cancel an authorized (not yet completed) payment.
	 *
	 * @param array $attempt Attempt.
	 * @return array array( 'ok', 'message' ).
	 */
	public function cancel( $attempt ) {
		$reply = $this->call( $attempt['environment'], 'POST', '/payments/' . rawurlencode( $attempt['provider_ref'] ) . '/cancel', new stdClass() );
		$ok    = $reply['status'] === 200 && ( $reply['json']['payment']['status'] ?? '' ) === 'CANCELED';
		return array( 'ok' => $ok, 'message' => $ok ? '' : self::message( $reply ) );
	}

	/**
	 * Refund some or all of a payment, through the connection that took it.
	 *
	 * @param array $attempt Attempt.
	 * @param int   $minor   Amount to refund now, minor units.
	 * @return array array( 'ok', 'message' ).
	 */
	public function refund( $attempt, $minor ) {
		$minor = (int) $minor;
		$left  = (int) $attempt['amount_minor'] - (int) $attempt['refunded_minor'];
		if ( $attempt['provider_ref'] === '' || $minor <= 0 || $minor > $left ) {
			return array( 'ok' => false, 'message' => __( 'That is more than is left to refund.', 'quill-accessible-forms' ) );
		}
		$body  = array(
			// The total refunded so far is in the key, so the same refund sent twice is made once.
			'idempotency_key' => substr( 'qaf-refund-' . $attempt['id'] . '-' . (int) $attempt['refunded_minor'] . '-' . $minor, 0, self::KEY_MAX ),
			'payment_id'      => $attempt['provider_ref'],
			'amount_money'    => array(
				'amount'   => $minor,
				'currency' => strtoupper( $attempt['currency'] ),
			),
		);
		$reply = $this->call( $attempt['environment'], 'POST', '/refunds', $body );
		$ok    = $reply['status'] === 200 && in_array( $reply['json']['refund']['status'] ?? '', array( 'PENDING', 'COMPLETED' ), true );
		return array( 'ok' => $ok, 'message' => $ok ? __( 'Square is making the refund. The entry changes when Square confirms it.', 'quill-accessible-forms' ) : self::message( $reply ) );
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
	 * Check Square's signature on a delivery.
	 *
	 * @param string $payload Raw body, exactly as received.
	 * @param string $header  x-square-hmacsha256-signature.
	 * @param string $key     Signature key.
	 * @param string $url     The notification URL Square was given.
	 * @return bool Valid.
	 */
	public static function signature_valid( $payload, $header, $key, $url ) {
		if ( $key === '' || ! is_string( $header ) || $header === '' ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Square's signature format.
		$expected = base64_encode( hash_hmac( 'sha256', $url . $payload, $key, true ) );
		return hash_equals( $expected, $header );
	}

	/**
	 * Read a webhook delivery: verify it, then say what it means.
	 *
	 * The signature is tried with each environment's signature key; the one
	 * that verifies is the event's environment.
	 *
	 * @param string $payload Raw body.
	 * @param array  $headers Headers, lower-case names.
	 * @return array Like QAF_Payment_Stripe::read_webhook().
	 */
	public function read_webhook( $payload, $headers ) {
		$header = (string) ( $headers['x-square-hmacsha256-signature'] ?? '' );
		$url    = QAF_Payment_Settings::webhook_url( $this->ref );
		$env    = '';
		foreach ( QAF_Payment_Settings::ENVIRONMENTS as $candidate ) {
			if ( self::signature_valid( $payload, $header, QAF_Payment_Settings::get_secret( $this->ref, $candidate, 'signature_key' ), $url ) ) {
				$env = $candidate;
				break;
			}
		}
		$event = json_decode( $payload, true );
		if ( $env === '' || ! is_array( $event ) || ! is_string( $event['event_id'] ?? null ) ) {
			return array( 'ok' => false, 'code' => 400 );
		}
		if ( ! QAF_Payment_Settings::verified( $this->ref, $env, 'webhook' ) ) {
			QAF_Payment_Settings::record_check( $this->ref, $env, 'webhook', true, __( 'A signed event from Square arrived and was verified.', 'quill-accessible-forms' ) );
		}
		$type   = (string) ( $event['type'] ?? '' );
		$object = is_array( $event['data']['object'] ?? null ) ? $event['data']['object'] : array();
		$out    = array(
			'ok'             => true,
			'event_id'       => $event['event_id'],
			'env'            => $env,
			'ref'            => '',
			'status'         => '',
			'amount_minor'   => null,
			'currency'       => '',
			'refunded_minor' => null,
			'receipt_url'    => '',
			'location_id'    => '',
			'ignore'         => true,
		);
		if ( $type === 'payment.updated' && is_array( $object['payment'] ?? null ) ) {
			$p                     = $object['payment'];
			$out['ref']            = (string) ( $p['id'] ?? '' );
			$out['status']         = self::state( $p );
			$out['amount_minor']   = (int) ( $p['amount_money']['amount'] ?? -1 );
			$out['currency']       = strtolower( (string) ( $p['amount_money']['currency'] ?? '' ) );
			$out['refunded_minor'] = (int) ( $p['refunded_money']['amount'] ?? 0 );
			$out['receipt_url']    = self::https( $p['receipt_url'] ?? '' );
			$out['location_id']    = (string) ( $p['location_id'] ?? '' );
			$out['ignore']         = $out['ref'] === '';
		} elseif ( $type === 'refund.updated' && is_array( $object['refund'] ?? null ) ) {
			// One refund, not the running total: the site reads the payment back.
			$out['ref']    = (string) ( $object['refund']['payment_id'] ?? '' );
			$out['status'] = 'refetch';
			$out['ignore'] = $out['ref'] === '';
		}
		return $out;
	}
}
