<?php
/**
 * Contract, Readiness route: "Only administrators or a dedicated
 * payment-management capability may read this route." Public checkout
 * routes do nothing without the submission's token; webhooks need a
 * provider signature.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Rest_Test extends QAF_Payment_Test_Case {

	public function test_routes_and_who_may_use_them() {
		( new QAF_Payment_REST() )->register_routes();
		$routes = $GLOBALS['qaf_test_routes'];
		$this->assertSame( 'GET', $routes['qaf/v1/payments/settings']['methods'] );
		$this->assertSame( array( 'QAF_Payment_REST', 'can_manage' ), $routes['qaf/v1/payments/settings']['permission_callback'] );
		foreach ( array( 'start', 'finish', 'status' ) as $r ) {
			$this->assertSame( 'POST', $routes[ 'qaf/v1/payments/' . $r ]['methods'] );
			$this->assertTrue( $routes[ 'qaf/v1/payments/' . $r ]['args']['token']['required'] );
		}
		$this->assertArrayHasKey( 'qaf/v1/payments/webhook/(?P<provider>stripe|paypal)', $routes );
		$this->assertCount( 5, $routes, 'no route writes payment settings: QUILL never sends keys' );
	}

	public function test_only_administrators_or_payment_managers_read_readiness() {
		$this->assertFalse( QAF_Payment_REST::can_manage() );
		$GLOBALS['qaf_test_caps'] = array( 'qaf_view_entries', 'qaf_edit_forms' );
		$this->assertFalse( QAF_Payment_REST::can_manage(), 'an editor of forms is not a payment manager' );
		$GLOBALS['qaf_test_caps'] = array( 'qaf_manage_payments' );
		$this->assertTrue( QAF_Payment_REST::can_manage() );
		$GLOBALS['qaf_test_caps'] = array( 'manage_options' );
		$this->assertTrue( QAF_Payment_REST::can_manage() );
	}

	public function test_the_settings_reply_is_the_contract_shape() {
		$this->make_ready( 'stripe' );
		$reply = ( new QAF_Payment_REST() )->settings();
		$this->assertSame( array( 'contract_version', 'test_mode', 'providers' ), array_keys( $reply ) );
		$this->assertTrue( $reply['providers']['stripe']['configured'] );
	}

	public function test_checkout_routes_need_the_token() {
		$this->service( new QAF_Fake_Provider() );
		$rest = new QAF_Payment_REST();
		foreach ( array( 'start', 'finish', 'status' ) as $route ) {
			$req = new WP_REST_Request( 'POST' );
			$req->set_param( 'token', str_repeat( '0', 48 ) );
			$req->set_param( 'provider', 'stripe' );
			$this->assertSame( 'qaf_payment_token', $rest->$route( $req )->get_error_code(), $route );
		}
	}

	public function test_a_webhook_without_a_valid_signature_is_400() {
		$this->make_ready( 'stripe' );
		$this->service( new QAF_Fake_Provider() );
		$req = new WP_REST_Request( 'POST' );
		$req->set_param( 'provider', 'stripe' );
		$req->set_body( '{"id":"evt_1","type":"payment_intent.succeeded","livemode":false}' );
		$req->set_header( 'Stripe-Signature', 't=1,v1=00' );
		$this->assertSame( 400, ( new QAF_Payment_REST() )->webhook( $req )->get_status() );
	}

	public function test_a_signed_webhook_reaches_the_service_through_the_route() {
		$this->make_ready( 'stripe' );
		$this->service( new QAF_Fake_Provider() );
		$body = self::stripe_event( 'evt_9', 'customer.created', array( 'id' => 'cus_1' ) );
		$req  = new WP_REST_Request( 'POST' );
		$req->set_param( 'provider', 'stripe' );
		$req->set_body( $body );
		$req->set_header( 'Stripe-Signature', self::stripe_sign( $body ) );
		$reply = ( new QAF_Payment_REST() )->webhook( $req );
		$this->assertSame( 200, $reply->get_status() );
		$this->assertSame( array( 'received' => true ), $reply->get_data() );
	}

	public function test_header_names_are_normalised() {
		$this->assertSame( array( 'stripe-signature' => 'x', 'paypal-transmission-id' => 'y' ), QAF_Payment_REST::flat_headers( array( 'stripe_signature' => array( 'x' ), 'PAYPAL_TRANSMISSION_ID' => 'y' ) ) );
	}
}
