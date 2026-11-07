<?php
/**
 * PayPal: Orders v2, capture or authorization on the server, and webhooks
 * checked with PayPal's verify-webhook-signature call.
 *
 * The page's PayPal buttons only ask the site to create an order and, once
 * the payer approves it, to capture (or authorize) it. Approval on its own
 * proves nothing: the entry changes only on the site's own capture reply, or
 * on a webhook PayPal itself has vouched for.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_PayPal class.
 */
class QAF_Payment_PayPal {

	const API_TEST = 'https://api-m.sandbox.paypal.com';
	const API_LIVE = 'https://api-m.paypal.com';

	/** The events the webhook asks for. */
	const EVENTS = array(
		'PAYMENT.CAPTURE.COMPLETED',
		'PAYMENT.CAPTURE.PENDING',
		'PAYMENT.CAPTURE.DENIED',
		'PAYMENT.CAPTURE.DECLINED',
		'PAYMENT.CAPTURE.REFUNDED',
		'PAYMENT.CAPTURE.REVERSED',
		'PAYMENT.AUTHORIZATION.CREATED',
		'PAYMENT.AUTHORIZATION.VOIDED',
	);

	/**
	 * Transport.
	 *
	 * @var QAF_Payment_HTTP
	 */
	private $http;

	/**
	 * Access tokens for this request only; never stored.
	 *
	 * @var array
	 */
	private $tokens = array();

	/**
	 * Constructor.
	 *
	 * @param QAF_Payment_HTTP|null $http Transport.
	 */
	public function __construct( $http = null ) {
		$this->http = $http ? $http : new QAF_Payment_HTTP();
	}

	/**
	 * API base for an environment.
	 *
	 * @param string $env Environment.
	 * @return string URL.
	 */
	public static function base( $env ) {
		return $env === 'live' ? self::API_LIVE : self::API_TEST;
	}

	/**
	 * An OAuth access token for an environment.
	 *
	 * @param string $env Environment.
	 * @return string Token, or ''.
	 */
	private function token( $env ) {
		if ( isset( $this->tokens[ $env ] ) ) {
			return $this->tokens[ $env ];
		}
		$id     = QAF_Payment_Settings::get_public( 'paypal', $env, 'client_id' );
		$secret = QAF_Payment_Settings::get_secret( 'paypal', $env, 'secret' );
		if ( $id === '' || $secret === '' ) {
			return '';
		}
		$reply = $this->http->request(
			'POST',
			self::base( $env ) . '/v1/oauth2/token',
			array(
				'Authorization' => 'Basic ' . base64_encode( $id . ':' . $secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			array( 'grant_type' => 'client_credentials' )
		);
		$token = ( $reply['status'] === 200 && is_string( $reply['json']['access_token'] ?? null ) ) ? $reply['json']['access_token'] : '';
		if ( $token !== '' ) {
			$this->tokens[ $env ] = $token;
		}
		return $token;
	}

	/**
	 * Call the REST API with JSON.
	 *
	 * @param string $env    Environment.
	 * @param string $method Method.
	 * @param string $path   Path.
	 * @param mixed  $body   Array (encoded here) or a JSON string sent as it is.
	 * @param string $req_id PayPal-Request-Id, for idempotency.
	 * @return array Reply.
	 */
	private function call( $env, $method, $path, $body = null, $req_id = '' ) {
		$token = $this->token( $env );
		if ( $token === '' ) {
			return array( 'status' => 401, 'json' => null, 'error' => __( 'PayPal did not accept the client ID and secret.', 'quill-accessible-forms' ) );
		}
		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Prefer'        => 'return=representation',
		);
		if ( $req_id !== '' ) {
			$headers['PayPal-Request-Id'] = $req_id;
		}
		if ( is_array( $body ) ) {
			$body = wp_json_encode( $body );
		}
		return $this->http->request( $method, self::base( $env ) . $path, $headers, $body );
	}

	/**
	 * A safe message from a PayPal error reply.
	 *
	 * @param array $reply Reply.
	 * @return string Message.
	 */
	private static function message( $reply ) {
		$issue = $reply['json']['details'][0]['issue'] ?? '';
		if ( in_array( $issue, array( 'INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED' ), true ) ) {
			return __( 'PayPal could not take payment with that method. Try again, or choose another way to pay.', 'quill-accessible-forms' );
		}
		if ( $reply['error'] !== '' ) {
			return $reply['error'];
		}
		return __( 'PayPal did not accept the request.', 'quill-accessible-forms' );
	}

	/**
	 * Check the client ID and secret by asking PayPal for a token in this environment.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok', 'message' ).
	 */
	public function verify_credentials( $env ) {
		$problem = QAF_Payment_Settings::shape_problem( 'paypal', $env );
		if ( $problem !== '' ) {
			return array( 'ok' => false, 'message' => $problem );
		}
		unset( $this->tokens[ $env ] );
		if ( $this->token( $env ) === '' ) {
			/* translators: %s: sandbox or live. */
			return array( 'ok' => false, 'message' => sprintf( __( 'PayPal did not accept the client ID and secret for %s.', 'quill-accessible-forms' ), $env === 'live' ? 'live' : 'sandbox' ) );
		}
		return array( 'ok' => true, 'message' => __( 'PayPal accepted the client ID and secret.', 'quill-accessible-forms' ) );
	}

	/**
	 * Check the stored webhook ID: it must exist in this environment, point at
	 * this site, and ask for the events the site needs.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok', 'message' ).
	 */
	public function verify_webhook( $env ) {
		$id = QAF_Payment_Settings::get_secret( 'paypal', $env, 'webhook_id' );
		if ( $id === '' ) {
			return array( 'ok' => false, 'message' => __( 'No webhook ID is saved.', 'quill-accessible-forms' ) );
		}
		$reply = $this->call( $env, 'GET', '/v1/notifications/webhooks/' . rawurlencode( $id ) );
		if ( $reply['status'] !== 200 || ! is_array( $reply['json'] ) ) {
			return array( 'ok' => false, 'message' => __( 'PayPal does not know that webhook ID in this environment.', 'quill-accessible-forms' ) );
		}
		if ( untrailingslashit( (string) ( $reply['json']['url'] ?? '' ) ) !== untrailingslashit( QAF_Payment_Settings::webhook_url( 'paypal' ) ) ) {
			return array( 'ok' => false, 'message' => __( 'That PayPal webhook sends its events to a different address.', 'quill-accessible-forms' ) );
		}
		$names = array();
		foreach ( (array) ( $reply['json']['event_types'] ?? array() ) as $t ) {
			$names[] = is_array( $t ) ? ( $t['name'] ?? '' ) : '';
		}
		if ( ! in_array( '*', $names, true ) && array_diff( self::EVENTS, $names ) ) {
			return array( 'ok' => false, 'message' => __( 'That PayPal webhook does not ask for every payment event the site needs.', 'quill-accessible-forms' ) );
		}
		return array( 'ok' => true, 'message' => __( 'The PayPal webhook is set up for this site.', 'quill-accessible-forms' ) );
	}

	/**
	 * Create the webhook on PayPal and keep its ID.
	 *
	 * @param string $env Environment.
	 * @return array array( 'ok', 'message' ).
	 */
	public function create_webhook( $env ) {
		$types = array();
		foreach ( self::EVENTS as $name ) {
			$types[] = array( 'name' => $name );
		}
		$reply = $this->call( $env, 'POST', '/v1/notifications/webhooks', array( 'url' => QAF_Payment_Settings::webhook_url( 'paypal' ), 'event_types' => $types ) );
		if ( $reply['status'] !== 201 || ! is_string( $reply['json']['id'] ?? null ) ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		QAF_Payment_Settings::set_secret( 'paypal', $env, 'webhook_id', $reply['json']['id'] );
		return array( 'ok' => true, 'message' => __( 'PayPal will send payment events to this site.', 'quill-accessible-forms' ) );
	}

	/**
	 * Create the attempt's order. Each try has its own request ID, so a
	 * repeated request returns the same order instead of making another.
	 *
	 * @param array  $attempt    Attempt.
	 * @param string $descriptor Soft descriptor, or ''.
	 * @return array array( 'ok', 'ref', 'status', 'message' ).
	 */
	public function start( $attempt, $descriptor = '' ) {
		$unit = array(
			'reference_id' => 'qaf-' . $attempt['id'],
			'custom_id'    => 'qaf-' . $attempt['id'],
			'amount'       => array(
				'currency_code' => strtoupper( $attempt['currency'] ),
				'value'         => QAF_Payment_Money::to_major( $attempt['amount_minor'], $attempt['currency'] ),
			),
		);
		if ( $descriptor !== '' ) {
			$unit['soft_descriptor'] = $descriptor;
		}
		$reply = $this->call(
			$attempt['environment'],
			'POST',
			'/v2/checkout/orders',
			array(
				'intent'         => $attempt['capture'] === 'manual' ? 'AUTHORIZE' : 'CAPTURE',
				'purchase_units' => array( $unit ),
			),
			'qaf-order-' . $attempt['id'] . '-' . (int) $attempt['tries']
		);
		$order = $reply['json'];
		if ( ! in_array( $reply['status'], array( 200, 201 ), true ) || ! is_string( $order['id'] ?? null ) ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		return array( 'ok' => true, 'ref' => $order['id'], 'status' => 'pending', 'message' => '' );
	}

	/**
	 * Capture (or authorize) an approved order, and check the result is the
	 * attempt's amount and currency.
	 *
	 * @param array $attempt Attempt.
	 * @return array array( 'ok', 'status', 'capture_ref', 'message' ).
	 */
	public function finish( $attempt ) {
		$manual = $attempt['capture'] === 'manual';
		$reply  = $this->call(
			$attempt['environment'],
			'POST',
			'/v2/checkout/orders/' . rawurlencode( $attempt['provider_ref'] ) . ( $manual ? '/authorize' : '/capture' ),
			'{}',
			'qaf-finish-' . $attempt['id'] . '-' . $attempt['provider_ref']
		);
		if ( $reply['status'] === 422 ) {
			$issue = $reply['json']['details'][0]['issue'] ?? '';
			if ( $issue === 'ORDER_ALREADY_CAPTURED' || $issue === 'ORDER_ALREADY_AUTHORIZED' ) {
				return $this->fetch( $attempt );
			}
			return array( 'ok' => true, 'status' => 'failed', 'capture_ref' => '', 'message' => self::message( $reply ) );
		}
		if ( ! in_array( $reply['status'], array( 200, 201 ), true ) || ! is_array( $reply['json'] ) ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		return $this->read_order( $reply['json'], $attempt );
	}

	/**
	 * Read an order back from PayPal.
	 *
	 * @param array $attempt Attempt.
	 * @return array Result as finish().
	 */
	public function fetch( $attempt ) {
		if ( $attempt['provider_ref'] === '' ) {
			return array( 'ok' => false, 'message' => __( 'No payment has been started.', 'quill-accessible-forms' ) );
		}
		$reply = $this->call( $attempt['environment'], 'GET', '/v2/checkout/orders/' . rawurlencode( $attempt['provider_ref'] ) );
		if ( $reply['status'] !== 200 || ! is_array( $reply['json'] ) ) {
			return array( 'ok' => false, 'message' => self::message( $reply ) );
		}
		return $this->read_order( $reply['json'], $attempt );
	}

	/**
	 * The contract state of an order, checked against the attempt.
	 *
	 * @param array $order   Order.
	 * @param array $attempt Attempt.
	 * @return array Result.
	 */
	private function read_order( $order, $attempt ) {
		if ( ( $order['id'] ?? '' ) !== $attempt['provider_ref'] ) {
			return array( 'ok' => false, 'message' => __( 'PayPal returned a different order.', 'quill-accessible-forms' ) );
		}
		$unit     = $order['purchase_units'][0] ?? array();
		$payments = $unit['payments'] ?? array();
		$item     = $payments['captures'][0] ?? ( $payments['authorizations'][0] ?? null );
		$state    = 'pending';
		$ref      = '';
		$refunded = 0;
		if ( is_array( $item ) ) {
			$amount = $item['amount'] ?? array();
			if ( strtolower( (string) ( $amount['currency_code'] ?? '' ) ) !== strtolower( $attempt['currency'] )
				|| QAF_Payment_Money::to_minor( $amount['value'] ?? '', $attempt['currency'] ) !== (int) $attempt['amount_minor'] ) {
				return array( 'ok' => false, 'message' => __( 'The payment does not match what this form asked for.', 'quill-accessible-forms' ) );
			}
			$ref   = (string) ( $item['id'] ?? '' );
			$state = self::item_state( (string) ( $item['status'] ?? '' ), isset( $payments['captures'] ) );
			foreach ( (array) ( $payments['refunds'] ?? array() ) as $refund ) {
				if ( ( $refund['status'] ?? '' ) === 'COMPLETED' ) {
					$refunded += (int) QAF_Payment_Money::to_minor( $refund['amount']['value'] ?? 0, $attempt['currency'] );
				}
			}
			if ( $refunded > 0 && in_array( $state, array( 'paid', 'refunded', 'partially_refunded' ), true ) ) {
				$state = $refunded >= (int) $attempt['amount_minor'] ? 'refunded' : 'partially_refunded';
			}
		} elseif ( ( $order['status'] ?? '' ) === 'VOIDED' ) {
			$state = 'cancelled';
		}
		return array(
			'ok'             => true,
			'status'         => $state,
			'capture_ref'    => $ref,
			'refunded_minor' => $refunded,
			'receipt_url'    => '',
			'message'        => $state === 'failed' ? __( 'PayPal declined the payment.', 'quill-accessible-forms' ) : '',
		);
	}

	/**
	 * The contract state of a capture or an authorization.
	 *
	 * @param string $status  PayPal status.
	 * @param bool   $capture A capture (else an authorization).
	 * @return string State.
	 */
	private static function item_state( $status, $capture ) {
		if ( $capture ) {
			$map = array(
				'COMPLETED'          => 'paid',
				'PENDING'            => 'pending',
				'DECLINED'           => 'failed',
				'FAILED'             => 'failed',
				'REFUNDED'           => 'refunded',
				'PARTIALLY_REFUNDED' => 'partially_refunded',
			);
		} else {
			$map = array(
				'CREATED'  => 'authorized',
				'PENDING'  => 'pending',
				'CAPTURED' => 'paid',
				'DENIED'   => 'failed',
				'VOIDED'   => 'cancelled',
				'EXPIRED'  => 'cancelled',
			);
		}
		return $map[ $status ] ?? 'pending';
	}

	/**
	 * Read a webhook delivery: PayPal verifies it, then this says what it means.
	 *
	 * @param string $payload Raw body.
	 * @param array  $headers Headers, lower-case names.
	 * @return array As QAF_Payment_Stripe::read_webhook(), plus 'attempt_id' and 'order_ref'.
	 */
	public function read_webhook( $payload, $headers ) {
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || ! is_string( $event['id'] ?? null ) ) {
			return array( 'ok' => false, 'code' => 400 );
		}
		$fields = array( 'auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time' );
		$sent   = array();
		foreach ( $fields as $f ) {
			$v = (string) ( $headers[ 'paypal-' . str_replace( '_', '-', $f ) ] ?? '' );
			if ( $v === '' ) {
				return array( 'ok' => false, 'code' => 400 );
			}
			$sent[ $f ] = $v;
		}
		$env = '';
		foreach ( QAF_Payment_Settings::ENVIRONMENTS as $candidate ) {
			$webhook_id = QAF_Payment_Settings::get_secret( 'paypal', $candidate, 'webhook_id' );
			if ( $webhook_id === '' ) {
				continue;
			}
			// The event goes back exactly as it came: re-encoding it can change
			// its bytes, and PayPal checks the signature over the bytes.
			$body = substr( wp_json_encode( $sent + array( 'webhook_id' => $webhook_id ) ), 0, -1 ) . ',"webhook_event":' . $payload . '}';
			$reply = $this->call( $candidate, 'POST', '/v1/notifications/verify-webhook-signature', $body );
			if ( $reply['status'] === 200 && ( $reply['json']['verification_status'] ?? '' ) === 'SUCCESS' ) {
				$env = $candidate;
				break;
			}
		}
		if ( $env === '' ) {
			return array( 'ok' => false, 'code' => 400 );
		}
		$type     = (string) ( $event['event_type'] ?? '' );
		$resource = is_array( $event['resource'] ?? null ) ? $event['resource'] : array();
		$custom   = (string) ( $resource['custom_id'] ?? '' );
		$out      = array(
			'ok'             => true,
			'event_id'       => $event['id'],
			'env'            => $env,
			'attempt_id'     => preg_match( '/^qaf-(\d+)$/', $custom, $m ) ? (int) $m[1] : 0,
			'order_ref'      => (string) ( $resource['supplementary_data']['related_ids']['order_id'] ?? '' ),
			'ref'            => '',
			'status'         => '',
			'amount_minor'   => null,
			'currency'       => strtolower( (string) ( $resource['amount']['currency_code'] ?? '' ) ),
			'amount_value'   => (string) ( $resource['amount']['value'] ?? '' ),
			'refunded_value' => '',
			'receipt_url'    => '',
			'capture_ref'    => (string) ( $resource['id'] ?? '' ),
			'ignore'         => false,
		);
		$map = array(
			'PAYMENT.CAPTURE.COMPLETED'     => 'paid',
			'PAYMENT.CAPTURE.PENDING'       => 'pending',
			'PAYMENT.CAPTURE.DENIED'        => 'failed',
			'PAYMENT.CAPTURE.DECLINED'      => 'failed',
			'PAYMENT.CAPTURE.REVERSED'      => 'refunded',
			'PAYMENT.AUTHORIZATION.CREATED' => 'authorized',
			'PAYMENT.AUTHORIZATION.VOIDED'  => 'cancelled',
		);
		if ( isset( $map[ $type ] ) ) {
			$out['status'] = $map[ $type ];
		} elseif ( $type === 'PAYMENT.CAPTURE.REFUNDED' ) {
			// The resource is the refund; the capture it belongs to is its "up" link.
			$out['status']      = 'refund';
			$out['capture_ref'] = '';
			foreach ( (array) ( $resource['links'] ?? array() ) as $link ) {
				if ( ( $link['rel'] ?? '' ) === 'up' && preg_match( '#/captures/([A-Za-z0-9]+)$#', (string) ( $link['href'] ?? '' ), $m ) ) {
					$out['capture_ref'] = $m[1];
				}
			}
			$out['refunded_value'] = (string) ( $resource['seller_payable_breakdown']['total_refunded_amount']['value'] ?? '' );
		} else {
			$out['ignore'] = true;
		}
		return $out;
	}
}
