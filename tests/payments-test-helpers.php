<?php
/**
 * Shared set-up for the payment tests: the payment classes, the model
 * classes they use, WordPress stand-ins, a recording fake transport, and
 * forms to pay with.
 *
 * Every provider reply in these tests is written by hand from the
 * provider's documented shapes. No test reaches Stripe or PayPal, and no
 * test uses a real key: the keys below are placeholders with the right
 * prefixes.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
require_once QAF_PLUGIN_DIR . 'includes/payments/class-qaf-payments.php';
require_once __DIR__ . '/mocks/payments-mocks.php';

/**
 * A transport that records requests and answers from a script.
 */
class QAF_Fake_Provider {
	/** @var array Requests made: method, url, headers, body. */
	public $requests = array();
	/** @var array Replies by "METHOD path" prefix, each a list used in turn (the last repeats). */
	public $replies = array();

	public function on( $method_and_url, $status, $body ) {
		$this->replies[ $method_and_url ][] = array( 'status' => $status, 'body' => is_string( $body ) ? $body : json_encode( $body ) );
		return $this;
	}

	public function __invoke( $method, $url, $args ) {
		$this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $args['headers'], 'body' => $args['body'] ?? null );
		$best = null;
		foreach ( $this->replies as $key => $list ) {
			list( $m, $prefix ) = explode( ' ', $key, 2 );
			if ( $m === $method && strpos( $url, $prefix ) === 0 && ( $best === null || strlen( $prefix ) > strlen( $best[1] ) ) ) {
				$best = array( $key, $prefix );
			}
		}
		if ( $best === null ) {
			return array( 'status' => 404, 'body' => '{}' );
		}
		$list = &$this->replies[ $best[0] ];
		return count( $list ) > 1 ? array_shift( $list ) : $list[0];
	}

	public function http() {
		return new QAF_Payment_HTTP( $this );
	}

	/** Requests whose URL contains a text. */
	public function to( $fragment, $method = null ) {
		return array_values( array_filter( $this->requests, function ( $r ) use ( $fragment, $method ) {
			return strpos( $r['url'], $fragment ) !== false && ( $method === null || $r['method'] === $method );
		} ) );
	}
}

/**
 * Base class for the payment tests.
 */
abstract class QAF_Payment_Test_Case extends QAF_Unit_Test_Case {

	const STRIPE_PK   = 'pk_test_placeholder000000000000';
	const STRIPE_SK   = 'sk_test_placeholder000000000000';
	const STRIPE_WH   = 'whsec_placeholder0000000000000000';
	const PAYPAL_ID   = 'sandbox-client-id-placeholder';
	const PAYPAL_SEC  = 'sandbox-secret-placeholder';
	const PAYPAL_HOOK = 'WH-PLACEHOLDER-0001';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['qaf_test_options'] = array();
		$GLOBALS['qaf_test_actions'] = array();
		$GLOBALS['qaf_test_caps']    = array();
		$GLOBALS['qaf_test_routes']  = array();
		QAF_Payment_Service::set_instance( null );
	}

	/** Give a provider test keys and mark them checked, as Check Connection would. */
	protected function make_ready( $provider, $env = 'test', $webhook = true ) {
		if ( $provider === 'stripe' ) {
			QAF_Payment_Settings::set_public( 'stripe', $env, 'publishable_key', $env === 'test' ? self::STRIPE_PK : 'pk_live_placeholder000000000000' );
			QAF_Payment_Settings::set_secret( 'stripe', $env, 'secret_key', $env === 'test' ? self::STRIPE_SK : 'sk_live_placeholder000000000000' );
			if ( $webhook ) {
				QAF_Payment_Settings::set_secret( 'stripe', $env, 'webhook_secret', $env === 'test' ? self::STRIPE_WH : 'whsec_liveplaceholder00000000000' );
			}
		} else {
			QAF_Payment_Settings::set_public( 'paypal', $env, 'client_id', self::PAYPAL_ID . $env );
			QAF_Payment_Settings::set_secret( 'paypal', $env, 'secret', self::PAYPAL_SEC . $env );
			if ( $webhook ) {
				QAF_Payment_Settings::set_secret( 'paypal', $env, 'webhook_id', self::PAYPAL_HOOK . $env );
			}
		}
		QAF_Payment_Settings::record_check( $provider, $env, 'credentials', true, 'ok' );
		if ( $webhook ) {
			QAF_Payment_Settings::record_check( $provider, $env, 'webhook', true, 'ok' );
		}
	}

	/** A one-page form with a payment field and a payments block. */
	protected static function paid_form( $amount = 25, $extra_items = array(), $config = array(), $props = array() ) {
		$items   = $extra_items;
		$items[] = array( 'id' => 'pay', 'type' => 'payment', 'label' => 'Payment', 'props' => array_merge( array( 'amount' => $amount ), $props ) );
		return array(
			'qaf'        => 1,
			'title'      => 'Paid form',
			'pages'      => array( array( 'id' => 'p1', 'items' => $items ) ),
			'extensions' => array(
				'payments' => array_merge( array(
					'providers' => array( 'stripe', 'paypal' ),
					'currency'  => 'usd',
					'mode'      => $amount === 'donation' ? 'donation' : 'payment',
					'capture'   => 'automatic',
					'test_mode' => true,
				), $config ),
			),
		);
	}

	/** A shop form: two products, a linked quantity, a coupon and a total. */
	protected static function shop_form( $total_props = array(), $config = array() ) {
		return self::paid_form( '#total', array(
			array( 'id' => 'shirt', 'type' => 'product', 'label' => 'T-shirt', 'choices' => array(
				array( 'value' => 's', 'label' => 'Small', 'price' => 15 ),
				array( 'value' => 'l', 'label' => 'Large', 'price' => 17.5 ),
			) ),
			array( 'id' => 'qty', 'type' => 'quantity', 'label' => 'How many', 'validation' => array( 'min' => 1, 'max' => 10 ), 'props' => array( 'linked_product' => 'shirt' ) ),
			array( 'id' => 'code', 'type' => 'coupon', 'label' => 'Coupon', 'props' => array( 'coupons' => array(
				array( 'code' => 'TEN', 'discount_type' => 'percentage', 'discount_value' => 10 ),
				array( 'code' => 'FIVE', 'discount_type' => 'fixed', 'discount_value' => 5 ),
				array( 'code' => 'OLD', 'discount_type' => 'fixed', 'discount_value' => 5, 'expires' => '2020-01-01' ),
			) ) ),
			array( 'id' => 'total', 'type' => 'total', 'label' => 'Order total', 'props' => $total_props ),
		), $config );
	}

	/** Names of the hooks fired so far. */
	protected static function fired( $tag ) {
		return count( array_filter( $GLOBALS['qaf_test_actions'], function ( $a ) use ( $tag ) {
			return $a[0] === $tag;
		} ) );
	}

	/** A service with a memory store and fake gateways. */
	protected function service( $fake, $now = null ) {
		$service = new QAF_Payment_Service( new QAF_Payment_Memory_Store(), array(
			'stripe' => new QAF_Payment_Stripe( $fake->http(), $now ),
			'paypal' => new QAF_Payment_PayPal( $fake->http() ),
		) );
		QAF_Payment_Service::set_instance( $service );
		return $service;
	}

	/** A Stripe-Signature header for a payload. */
	protected static function stripe_sign( $payload, $secret = self::STRIPE_WH, $t = null ) {
		$t = $t ?? time();
		return 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $payload, $secret );
	}

	/** A Stripe event body. */
	protected static function stripe_event( $id, $type, $object, $livemode = false ) {
		return json_encode( array( 'id' => $id, 'type' => $type, 'livemode' => $livemode, 'data' => array( 'object' => $object ) ) );
	}
}
