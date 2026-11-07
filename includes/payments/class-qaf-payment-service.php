<?php
/**
 * The payment flow, from a sent form to a settled entry.
 *
 * 1. prepare(): when a form is sent, check its payment settings again, check
 *    the site can take payment in the form's environment, and work out the
 *    amount on the server. Nothing is saved if any of that fails.
 * 2. open(): after the entry is saved, record an attempt (pending) and give
 *    the page a one-time token for it. The token is the only handle the
 *    page has; the site keeps only its hash.
 * 3. start(): the payer picks a provider; the site creates (or resumes) the
 *    Payment Intent or the PayPal order for the attempt's own amount.
 * 4. finish() / refresh(): the site asks the provider what happened, and
 *    webhook() hears it from the provider directly. Either way the change
 *    goes through settle(), which applies only the transitions
 *    QAF_Payment_States allows, as compare-and-set writes.
 *
 * The first time an attempt becomes paid (or authorized) settle() fires
 * `qaf_payment_paid` (or `qaf_payment_authorized`) exactly once, however many
 * times the news arrives. That is the hook for fulfilment.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_Service class.
 */
class QAF_Payment_Service {

	/**
	 * Store.
	 *
	 * @var QAF_Payment_Store
	 */
	private $store;

	/**
	 * Gateways by provider.
	 *
	 * @var array
	 */
	private $gateways;

	/**
	 * The shared service.
	 *
	 * @var QAF_Payment_Service|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param QAF_Payment_Store|null $store    Store.
	 * @param array|null             $gateways 'stripe' and 'paypal' gateways.
	 */
	public function __construct( $store = null, $gateways = null ) {
		$this->store    = $store ? $store : new QAF_Payment_DB_Store();
		$this->gateways = $gateways ? $gateways : array(
			'stripe' => new QAF_Payment_Stripe(),
			'paypal' => new QAF_Payment_PayPal(),
		);
	}

	/**
	 * The shared service.
	 *
	 * @return QAF_Payment_Service Service.
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Replace the shared service (tests).
	 *
	 * @param QAF_Payment_Service|null $service Service.
	 */
	public static function set_instance( $service ) {
		self::$instance = $service;
	}

	/**
	 * The store.
	 *
	 * @return QAF_Payment_Store Store.
	 */
	public function store() {
		return $this->store;
	}

	/**
	 * A gateway.
	 *
	 * @param string $provider Provider.
	 * @return QAF_Payment_Stripe|QAF_Payment_PayPal Gateway.
	 */
	public function gateway( $provider ) {
		return $this->gateways[ $provider ];
	}

	/**
	 * What a payer is told when the site cannot take payment. The reason is
	 * for the site owner (Verify Payment Setup, the admin screen), not the payer.
	 *
	 * @return WP_Error Error.
	 */
	private static function unavailable() {
		return new WP_Error(
			'qaf_payment_unavailable',
			__( 'This form cannot take payment right now, so your answers were not sent. Please try again later or contact the site owner.', 'quill-accessible-forms' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * The providers that can take this form's payment now, in the form's order.
	 *
	 * @param array $config Payment configuration.
	 * @return array Providers.
	 */
	public static function available( $config ) {
		$env = QAF_Payment_Settings::mode();
		$out = array();
		foreach ( $config['providers'] as $p ) {
			if ( QAF_Payment_Settings::ready( $p, $env ) ) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * Check a sent form and work out what it owes, before anything is saved.
	 *
	 * @param array $model  Form model.
	 * @param array $values Sanitized answers.
	 * @param array $extra  Expression context.
	 * @return array|WP_Error Plan from QAF_Payment_Pricing::evaluate(), plus 'config'.
	 */
	public function prepare( $model, $values, $extra = array() ) {
		if ( QAF_Payment_Config::errors( $model ) ) {
			return self::unavailable();
		}
		$config = QAF_Payment_Config::get( $model );
		$plan   = QAF_Payment_Pricing::evaluate( $model, $values, $extra );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$plan['config'] = $config;
		if ( ! $plan['due'] ) {
			return $plan;
		}
		// A test form on a live site, or a live form on a test site, is refused:
		// neither may quietly become the other.
		if ( $config['test_mode'] !== QAF_Payment_Settings::is_test() || ! self::available( $config ) ) {
			return self::unavailable();
		}
		return $plan;
	}

	/**
	 * An attempt already opened for this browser's submission, so sending the
	 * same form twice (a double click, a slow network) reuses the entry
	 * rather than making a second one.
	 *
	 * @param int    $form_id    Form ID.
	 * @param string $submission The page's submission key.
	 * @return array|null Attempt.
	 */
	public function existing( $form_id, $submission ) {
		$submission = self::submission_key( $submission );
		if ( $submission === '' ) {
			return null;
		}
		$attempt = $this->store->find( 'submission', $form_id . ':' . $submission );
		return ( $attempt && (int) $attempt['form_id'] === (int) $form_id ) ? $attempt : null;
	}

	/**
	 * A submission key as the page made it: 16 to 64 letters and digits.
	 *
	 * @param mixed $key Key.
	 * @return string Key, or ''.
	 */
	public static function submission_key( $key ) {
		return ( is_string( $key ) && preg_match( '/^[A-Za-z0-9]{16,64}$/', $key ) ) ? $key : '';
	}

	/**
	 * Open the attempt for a saved entry.
	 *
	 * @param int    $form_id    Form ID.
	 * @param int    $entry_id   Entry ID.
	 * @param array  $plan       From prepare().
	 * @param string $submission The page's submission key.
	 * @return array array( 'attempt' => row, 'token' => one-time token ).
	 */
	public function open( $form_id, $entry_id, $plan, $submission = '' ) {
		$token = bin2hex( random_bytes( 24 ) );
		$key   = self::submission_key( $submission );
		$row   = array(
			'form_id'      => (int) $form_id,
			'entry_id'     => (int) $entry_id,
			'token_hash'   => hash( 'sha256', $token ),
			'submission'   => $key === '' ? '' : $form_id . ':' . $key,
			'provider'     => '',
			'environment'  => QAF_Payment_Settings::mode(),
			'status'       => QAF_Payment_States::PENDING,
			'amount_minor' => (int) $plan['minor'],
			'currency'     => strtolower( $plan['currency'] ),
			'capture'      => $plan['config']['capture'] === 'manual' ? 'manual' : 'automatic',
		);
		$id = $this->store->insert( $row );
		return array( 'attempt' => $this->store->get( $id ), 'token' => $token );
	}

	/**
	 * A new token for an existing attempt (a resent submission gets a fresh
	 * one; the old token stops working).
	 *
	 * @param array $attempt Attempt.
	 * @return string Token.
	 */
	public function reissue( $attempt ) {
		$token = bin2hex( random_bytes( 24 ) );
		$this->store->update( $attempt['id'], array( 'token_hash' => hash( 'sha256', $token ) ) );
		return $token;
	}

	/**
	 * The attempt a token opens.
	 *
	 * @param mixed $token Token.
	 * @return array|null Attempt.
	 */
	public function by_token( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) {
			return null;
		}
		$hash    = hash( 'sha256', $token );
		$attempt = $this->store->find( 'token_hash', $hash );
		return ( $attempt && hash_equals( (string) $attempt['token_hash'], $hash ) ) ? $attempt : null;
	}

	/**
	 * What the page gets to run the checkout. Public keys only.
	 *
	 * @param array  $attempt Attempt.
	 * @param string $token   Token.
	 * @param array  $config  Payment configuration.
	 * @return array Payment block of the submit reply.
	 */
	public function for_page( $attempt, $token, $config ) {
		$env       = $attempt['environment'];
		$providers = array();
		foreach ( self::available( $config ) as $p ) {
			$providers[] = array(
				'id'   => $p,
				'name' => QAF_Payment_Config::provider_name( $p ),
				'key'  => $p === 'stripe'
					? QAF_Payment_Settings::get_public( 'stripe', $env, 'publishable_key' )
					: QAF_Payment_Settings::get_public( 'paypal', $env, 'client_id' ),
			);
		}
		return array(
			'token'     => $token,
			'amount'    => QAF_Payment_Money::to_major( $attempt['amount_minor'], $attempt['currency'] ),
			'currency'  => strtoupper( $attempt['currency'] ),
			'spoken'    => QAF_Payment_Money::spoken( $attempt['amount_minor'], $attempt['currency'] ),
			'test_mode' => $env === 'test',
			'capture'   => $attempt['capture'],
			'providers' => $providers,
			'status'    => $attempt['status'],
		);
	}

	/**
	 * The page-facing state of an attempt.
	 *
	 * @param array  $attempt Attempt.
	 * @param string $message Extra message.
	 * @return array Status.
	 */
	public static function status_for_page( $attempt, $message = '' ) {
		$state = $attempt['status'];
		$texts = array(
			'paid'       => __( 'Payment complete. Thank you.', 'quill-accessible-forms' ),
			'authorized' => __( 'Payment authorized. You will be charged when the site owner confirms.', 'quill-accessible-forms' ),
			'pending'    => __( 'Your payment is not complete yet.', 'quill-accessible-forms' ),
			'failed'     => __( 'The payment did not go through. You can try again.', 'quill-accessible-forms' ),
			'cancelled'  => __( 'The payment was cancelled. You can try again.', 'quill-accessible-forms' ),
		);
		$text = $texts[ $state ] ?? QAF_Payment_States::label( $state );
		if ( $message !== '' && in_array( $state, array( 'failed', 'pending', 'cancelled' ), true ) ) {
			$text .= ' ' . $message;
		}
		return array(
			'status'      => $state,
			'label'       => QAF_Payment_States::label( $state ),
			'message'     => $text,
			'done'        => in_array( $state, array( 'paid', 'authorized', 'partially_refunded', 'refunded' ), true ),
			'receipt_url' => (string) $attempt['receipt_url'],
		);
	}

	/**
	 * The form's payment configuration for an attempt, read from the form as
	 * it is now, and checked again.
	 *
	 * @param array $attempt Attempt.
	 * @return array|WP_Error Configuration.
	 */
	private function config_for( $attempt ) {
		$form  = new QAF_Form( (int) $attempt['form_id'] );
		$model = ( isset( $form->config['form_model'] ) && is_array( $form->config['form_model'] ) ) ? $form->config['form_model'] : null;
		if ( ! $form->id || ! $model || QAF_Payment_Config::errors( $model ) || ! QAF_Payment_Config::applies( $model ) ) {
			return self::unavailable();
		}
		return QAF_Payment_Config::get( $model );
	}

	/**
	 * Start (or resume) payment with a provider.
	 *
	 * @param array  $attempt  Attempt.
	 * @param string $provider Provider.
	 * @param array  $config   Payment configuration (config_for() when null).
	 * @return array|WP_Error For Stripe: client_secret; for PayPal: order_id; plus 'status'.
	 */
	public function start( $attempt, $provider, $config = null ) {
		$config = $config === null ? $this->config_for( $attempt ) : $config;
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		if ( ! in_array( $provider, $config['providers'], true ) ) {
			return new WP_Error( 'qaf_payment_provider', __( 'This form does not take that way of paying.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
		}
		if ( $attempt['environment'] !== QAF_Payment_Settings::mode() || ! QAF_Payment_Settings::ready( $provider, $attempt['environment'] ) ) {
			return self::unavailable();
		}
		if ( ! in_array( $attempt['status'], array( 'pending', 'failed', 'cancelled' ), true ) ) {
			return array( 'status' => self::status_for_page( $attempt ) );
		}
		if ( $attempt['provider_ref'] !== '' ) {
			// A Stripe intent is resumed while it can still be paid. Anything
			// else starts afresh, with a new idempotency key for the new try.
			$reuse = $provider === 'stripe' && $attempt['provider'] === 'stripe' && $attempt['status'] !== 'cancelled';
			if ( ! $reuse && $attempt['provider'] === 'stripe' ) {
				// Leaving a Stripe intent behind: first make sure it was not
				// paid meanwhile, then cancel it, so it can never be confirmed
				// later beside the new payment.
				$check = $this->refresh( $attempt );
				if ( ! is_wp_error( $check ) && $check['done'] ) {
					return array( 'status' => $check );
				}
				$attempt = $this->store->get( $attempt['id'] );
				if ( $attempt['status'] !== 'cancelled' ) {
					$cancel = $this->gateway( 'stripe' )->cancel( $attempt );
					if ( empty( $cancel['ok'] ) ) {
						return new WP_Error( 'qaf_payment_start', __( 'The card payment you started could not be stopped, so another way of paying cannot be offered. Please finish the card payment, or try again in a moment.', 'quill-accessible-forms' ), array( 'status' => 409 ) );
					}
				}
			}
			if ( ! $reuse ) {
				$this->store->update( $attempt['id'], array( 'tries' => (int) $attempt['tries'] + 1 ) );
				$attempt                 = $this->store->get( $attempt['id'] );
				$attempt['provider_ref'] = '';
			}
		}
		$result = $this->gateway( $provider )->start( $attempt, $config['statement_descriptor'] );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'qaf_payment_start', $result['message'], array( 'status' => 502 ) );
		}
		$this->store->update( $attempt['id'], array( 'provider' => $provider, 'provider_ref' => $result['ref'], 'capture_ref' => '' ) );
		$attempt = $this->settle( $this->store->get( $attempt['id'] ), $result['status'] === 'failed' ? 'pending' : $result['status'] );
		$out     = array( 'status' => self::status_for_page( $attempt ) );
		if ( $provider === 'stripe' ) {
			$out['client_secret'] = $result['client_secret'];
		} else {
			$out['order_id'] = $result['ref'];
		}
		return $out;
	}

	/**
	 * Capture (or authorize) an approved PayPal order.
	 *
	 * @param array $attempt Attempt.
	 * @return array|WP_Error Page status.
	 */
	public function finish( $attempt ) {
		if ( $attempt['provider'] !== 'paypal' || $attempt['provider_ref'] === '' ) {
			return new WP_Error( 'qaf_payment_state', __( 'There is no PayPal payment to finish.', 'quill-accessible-forms' ), array( 'status' => 409 ) );
		}
		$result = $this->gateway( 'paypal' )->finish( $attempt );
		return $this->apply_result( $attempt, $result );
	}

	/**
	 * Ask the provider what happened to an attempt.
	 *
	 * @param array $attempt Attempt.
	 * @return array|WP_Error Page status.
	 */
	public function refresh( $attempt ) {
		if ( $attempt['provider'] === '' || $attempt['provider_ref'] === '' ) {
			return self::status_for_page( $attempt );
		}
		$result = $this->gateway( $attempt['provider'] )->fetch( $attempt );
		return $this->apply_result( $attempt, $result );
	}

	/**
	 * Apply a gateway's verified reading.
	 *
	 * @param array $attempt Attempt.
	 * @param array $result  Gateway result.
	 * @return array|WP_Error Page status.
	 */
	private function apply_result( $attempt, $result ) {
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'qaf_payment_check', $result['message'] ?? '', array( 'status' => 502 ) );
		}
		$fields = array();
		foreach ( array( 'receipt_url', 'capture_ref', 'refunded_minor' ) as $k ) {
			if ( isset( $result[ $k ] ) && $result[ $k ] !== '' ) {
				$fields[ $k ] = $result[ $k ];
			}
		}
		$attempt = $this->settle( $attempt, $result['status'], $fields );
		return self::status_for_page( $attempt, (string) ( $result['message'] ?? '' ) );
	}

	/**
	 * Move an attempt to a state, if the move is allowed, atomically.
	 *
	 * @param array  $attempt Attempt.
	 * @param string $state   New state.
	 * @param array  $fields  Other columns to write with it.
	 * @return array The attempt as it now is.
	 */
	public function settle( $attempt, $state, $fields = array() ) {
		for ( $try = 0; $try < 3; $try++ ) {
			$from = $attempt['status'];
			if ( ! QAF_Payment_States::can( $from, $state ) ) {
				return $attempt;
			}
			$write = $fields;
			if ( $from !== $state ) {
				$write['status'] = $state;
			}
			if ( ! $write ) {
				return $attempt;
			}
			if ( $this->store->update( $attempt['id'], $write, $from ) ) {
				$now = $this->store->get( $attempt['id'] );
				if ( $from !== $state ) {
					$this->announce( $now, $from );
				}
				return $now;
			}
			// Someone else changed it first: read it again and decide again.
			$attempt = $this->store->get( $attempt['id'] );
		}
		return $attempt;
	}

	/**
	 * Fire the fulfilment hooks for a state change. settle() calls this only
	 * for the write that won, so each hook fires once per change.
	 *
	 * @param array  $attempt Attempt after the change.
	 * @param string $from    State before.
	 */
	private function announce( $attempt, $from ) {
		/**
		 * Fires once when an entry's payment changes state.
		 *
		 * @param array  $attempt The payment attempt (no secrets in it).
		 * @param string $from    The state it left.
		 */
		do_action( 'qaf_payment_status_changed', $attempt, $from );
		if ( $attempt['status'] === 'paid' && $from !== 'paid' ) {
			/**
			 * Fires once when an entry's payment is complete: the place for fulfilment.
			 *
			 * @param array $attempt The payment attempt.
			 */
			do_action( 'qaf_payment_paid', $attempt );
		} elseif ( $attempt['status'] === 'authorized' ) {
			do_action( 'qaf_payment_authorized', $attempt );
		}
	}

	/**
	 * Handle a webhook delivery.
	 *
	 * @param string $provider stripe or paypal.
	 * @param string $payload  Raw body.
	 * @param array  $headers  Headers, lower-case names.
	 * @return array array( 'code' => HTTP status, 'body' => reply ).
	 */
	public function webhook( $provider, $payload, $headers ) {
		if ( ! isset( $this->gateways[ $provider ] ) ) {
			return array( 'code' => 404, 'body' => array( 'received' => false ) );
		}
		$event = $this->gateway( $provider )->read_webhook( $payload, $headers );
		if ( empty( $event['ok'] ) ) {
			return array( 'code' => 400, 'body' => array( 'received' => false ) );
		}
		if ( ! $this->store->record_event( $provider, $event['event_id'] ) ) {
			return array( 'code' => 200, 'body' => array( 'received' => true, 'duplicate' => true ) );
		}
		if ( ! empty( $event['ignore'] ) ) {
			return array( 'code' => 200, 'body' => array( 'received' => true ) );
		}
		$attempt = $this->locate( $provider, $event );
		if ( ! $attempt || $attempt['environment'] !== $event['env'] ) {
			return array( 'code' => 200, 'body' => array( 'received' => true, 'applied' => false ) );
		}
		$applied = $provider === 'stripe' ? $this->apply_stripe( $attempt, $event ) : $this->apply_paypal( $attempt, $event );
		return array( 'code' => 200, 'body' => array( 'received' => true, 'applied' => $applied ) );
	}

	/**
	 * The attempt a verified event is about.
	 *
	 * @param string $provider Provider.
	 * @param array  $event    Event.
	 * @return array|null Attempt.
	 */
	private function locate( $provider, $event ) {
		if ( $provider === 'stripe' ) {
			$a = $this->store->find( 'provider_ref', $event['ref'] );
			return ( $a && $a['provider'] === 'stripe' ) ? $a : null;
		}
		$a = null;
		if ( ! empty( $event['attempt_id'] ) ) {
			$a = $this->store->get( $event['attempt_id'] );
		}
		if ( ! $a && $event['order_ref'] !== '' ) {
			$a = $this->store->find( 'provider_ref', $event['order_ref'] );
		}
		if ( ! $a && $event['capture_ref'] !== '' ) {
			$a = $this->store->find( 'capture_ref', $event['capture_ref'] );
		}
		if ( ! $a || $a['provider'] !== 'paypal' ) {
			return null;
		}
		// An event naming another order than the attempt's is not about it.
		if ( $event['order_ref'] !== '' && $event['order_ref'] !== $a['provider_ref'] ) {
			return null;
		}
		return $a;
	}

	/**
	 * Apply a Stripe event, after checking amount and currency.
	 *
	 * @param array $attempt Attempt.
	 * @param array $event   Event.
	 * @return bool Applied.
	 */
	private function apply_stripe( $attempt, $event ) {
		if ( $event['status'] === 'refetch' ) {
			$result = $this->refresh( $attempt );
			return ! is_wp_error( $result );
		}
		if ( $event['amount_minor'] !== (int) $attempt['amount_minor'] || $event['currency'] !== strtolower( $attempt['currency'] ) ) {
			return false;
		}
		$fields = array();
		foreach ( array( 'receipt_url', 'capture_ref' ) as $k ) {
			if ( $event[ $k ] !== '' ) {
				$fields[ $k ] = $event[ $k ];
			}
		}
		if ( $event['refunded_minor'] !== null ) {
			$fields['refunded_minor'] = $event['refunded_minor'];
		}
		$before = $attempt['status'];
		$after  = $this->settle( $attempt, $event['status'], $fields );
		return $after['status'] === $event['status'] && ( $before !== $after['status'] || $fields );
	}

	/**
	 * Apply a PayPal event, after checking amount and currency.
	 *
	 * @param array $attempt Attempt.
	 * @param array $event   Event.
	 * @return bool Applied.
	 */
	private function apply_paypal( $attempt, $event ) {
		$currency = strtolower( $attempt['currency'] );
		if ( $event['status'] === 'refund' ) {
			if ( $event['capture_ref'] !== '' && $attempt['capture_ref'] !== '' && $event['capture_ref'] !== $attempt['capture_ref'] ) {
				return false;
			}
			$total = $event['refunded_value'] !== '' ? QAF_Payment_Money::to_minor( $event['refunded_value'], $currency ) : null;
			if ( $total === null ) {
				// No running total in the event: read the order back instead.
				return ! is_wp_error( $this->refresh( $attempt ) );
			}
			$state = $total >= (int) $attempt['amount_minor'] ? 'refunded' : 'partially_refunded';
			$after = $this->settle( $attempt, $state, array( 'refunded_minor' => $total ) );
			return $after['status'] === $state;
		}
		if ( $event['currency'] !== $currency || QAF_Payment_Money::to_minor( $event['amount_value'], $currency ) !== (int) $attempt['amount_minor'] ) {
			return false;
		}
		$fields = $event['capture_ref'] !== '' ? array( 'capture_ref' => $event['capture_ref'] ) : array();
		$after  = $this->settle( $attempt, $event['status'], $fields );
		return $after['status'] === $event['status'];
	}

	/**
	 * The contract's payment object for an entry, or null when it has none
	 * (which means unknown, never paid).
	 *
	 * @param int $entry_id Entry ID.
	 * @return array|null provider, status, amount, currency, receipt_url, transaction_id.
	 */
	public function for_entry( $entry_id ) {
		$a = $this->store->find( 'entry_id', (string) (int) $entry_id );
		if ( ! $a ) {
			return null;
		}
		return array(
			'provider'       => (string) $a['provider'],
			'status'         => (string) $a['status'],
			'amount'         => QAF_Payment_Money::to_major( $a['amount_minor'], $a['currency'] ),
			'currency'       => strtoupper( $a['currency'] ),
			'receipt_url'    => (string) $a['receipt_url'],
			'transaction_id' => (string) ( $a['capture_ref'] !== '' && $a['provider'] === 'paypal' ? $a['capture_ref'] : $a['provider_ref'] ),
		);
	}
}
