<?php
/**
 * PayPal through a fake transport: Orders v2 created and captured on the
 * server, amounts checked, and webhooks verified by PayPal's own call with
 * the event's exact bytes.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_PayPal_Gateway_Test extends QAF_Payment_Test_Case {

	const SANDBOX = 'https://api-m.sandbox.paypal.com';

	private function attempt( $extra = array() ) {
		return array_merge( array(
			'id' => 9, 'form_id' => 3, 'entry_id' => 41, 'environment' => 'test', 'status' => 'pending',
			'amount_minor' => 2500, 'currency' => 'usd', 'capture' => 'automatic', 'provider' => 'paypal',
			'provider_ref' => 'ORDER1', 'capture_ref' => '', 'tries' => 0, 'refunded_minor' => 0, 'receipt_url' => '',
		), $extra );
	}

	private function fake() {
		return ( new QAF_Fake_Provider() )->on( 'POST ' . self::SANDBOX . '/v1/oauth2/token', 200, array( 'access_token' => 'A21-token', 'expires_in' => 3600 ) );
	}

	private static function captured( $value = '25.00', $status = 'COMPLETED', $extra = array() ) {
		return array( 'id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => array( array( 'payments' => array_merge( array(
			'captures' => array( array( 'id' => 'CAP1', 'status' => $status, 'amount' => array( 'currency_code' => 'USD', 'value' => $value ) ) ),
		), $extra ) ) ) );
	}

	public function test_an_order_carries_the_amount_as_decimal_text() {
		$this->make_ready( 'paypal' );
		$fake = $this->fake()->on( 'POST ' . self::SANDBOX . '/v2/checkout/orders', 201, array( 'id' => 'ORDER1', 'status' => 'CREATED' ) );
		$out  = ( new QAF_Payment_PayPal( $fake->http() ) )->start( $this->attempt( array( 'provider_ref' => '' ) ), 'BITS EVENT' );
		$this->assertTrue( $out['ok'] );
		$this->assertSame( 'ORDER1', $out['ref'] );
		$req  = $fake->to( '/v2/checkout/orders' )[0];
		$body = json_decode( $req['body'], true );
		$this->assertSame( 'CAPTURE', $body['intent'] );
		$this->assertSame( array( 'currency_code' => 'USD', 'value' => '25.00' ), $body['purchase_units'][0]['amount'] );
		$this->assertSame( 'qaf-9', $body['purchase_units'][0]['custom_id'] );
		$this->assertSame( 'BITS EVENT', $body['purchase_units'][0]['soft_descriptor'] );
		$this->assertSame( 'qaf-order-9-0', $req['headers']['PayPal-Request-Id'] );
		$this->assertSame( 'Bearer A21-token', $req['headers']['Authorization'] );
		$token = $fake->to( '/v1/oauth2/token' )[0];
		$this->assertSame( 'Basic ' . base64_encode( self::PAYPAL_ID . 'test:' . self::PAYPAL_SEC . 'test' ), $token['headers']['Authorization'] );
	}

	public function test_manual_capture_creates_an_authorize_order() {
		$this->make_ready( 'paypal' );
		$fake = $this->fake()->on( 'POST ' . self::SANDBOX . '/v2/checkout/orders', 201, array( 'id' => 'ORDER1' ) );
		( new QAF_Payment_PayPal( $fake->http() ) )->start( $this->attempt( array( 'capture' => 'manual' ) ) );
		$this->assertSame( 'AUTHORIZE', json_decode( $fake->to( '/v2/checkout/orders' )[0]['body'], true )['intent'] );
	}

	public function test_capture_is_checked_against_the_attempt() {
		$this->make_ready( 'paypal' );
		$fake = $this->fake()->on( 'POST ' . self::SANDBOX . '/v2/checkout/orders/ORDER1/capture', 201, self::captured() );
		$out  = ( new QAF_Payment_PayPal( $fake->http() ) )->finish( $this->attempt() );
		$this->assertSame( 'paid', $out['status'] );
		$this->assertSame( 'CAP1', $out['capture_ref'] );
		$this->assertSame( 'qaf-finish-9-ORDER1', $fake->to( '/capture' )[0]['headers']['PayPal-Request-Id'] );

		$fake = $this->fake()->on( 'POST ' . self::SANDBOX . '/v2/checkout/orders/ORDER1/capture', 201, self::captured( '0.01' ) );
		$this->assertFalse( ( new QAF_Payment_PayPal( $fake->http() ) )->finish( $this->attempt() )['ok'], 'another amount is not this payment' );
	}

	public function test_a_declined_capture_is_failed_and_can_be_retried() {
		$this->make_ready( 'paypal' );
		$fake = $this->fake()->on( 'POST ' . self::SANDBOX . '/v2/checkout/orders/ORDER1/capture', 422, array( 'name' => 'UNPROCESSABLE_ENTITY', 'details' => array( array( 'issue' => 'INSTRUMENT_DECLINED' ) ) ) );
		$out  = ( new QAF_Payment_PayPal( $fake->http() ) )->finish( $this->attempt() );
		$this->assertSame( 'failed', $out['status'] );
		$this->assertStringContainsString( 'Try again', $out['message'] );
	}

	public function test_an_already_captured_order_is_read_back_not_captured_again() {
		$this->make_ready( 'paypal' );
		$fake = $this->fake()
			->on( 'POST ' . self::SANDBOX . '/v2/checkout/orders/ORDER1/capture', 422, array( 'details' => array( array( 'issue' => 'ORDER_ALREADY_CAPTURED' ) ) ) )
			->on( 'GET ' . self::SANDBOX . '/v2/checkout/orders/ORDER1', 200, self::captured() );
		$this->assertSame( 'paid', ( new QAF_Payment_PayPal( $fake->http() ) )->finish( $this->attempt() )['status'] );
	}

	public function test_refunds_on_the_order_are_partial_or_full() {
		$this->make_ready( 'paypal' );
		$refund = array( 'refunds' => array( array( 'status' => 'COMPLETED', 'amount' => array( 'value' => '10.00', 'currency_code' => 'USD' ) ) ) );
		$fake   = $this->fake()->on( 'GET ' . self::SANDBOX . '/v2/checkout/orders/ORDER1', 200, self::captured( '25.00', 'PARTIALLY_REFUNDED', $refund ) );
		$this->assertSame( 'partially_refunded', ( new QAF_Payment_PayPal( $fake->http() ) )->fetch( $this->attempt() )['status'] );
	}

	public function test_webhooks_are_verified_by_paypal_with_the_exact_bytes() {
		$this->make_ready( 'paypal' );
		$payload = '{"id":"WH-EVT-1","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP1","status":"COMPLETED","custom_id":"qaf-9","amount":{"currency_code":"USD","value":"25.00"},"supplementary_data":{"related_ids":{"order_id":"ORDER1"}}}}';
		$headers = array( 'paypal-auth-algo' => 'SHA256withRSA', 'paypal-cert-url' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT', 'paypal-transmission-id' => 't1', 'paypal-transmission-sig' => 'sig', 'paypal-transmission-time' => '2026-11-07T00:00:00Z' );
		$fake    = $this->fake()->on( 'POST ' . self::SANDBOX . '/v1/notifications/verify-webhook-signature', 200, array( 'verification_status' => 'SUCCESS' ) );
		$out     = ( new QAF_Payment_PayPal( $fake->http() ) )->read_webhook( $payload, $headers );
		$this->assertTrue( $out['ok'] );
		$this->assertSame( 'paid', $out['status'] );
		$this->assertSame( 9, $out['attempt_id'] );
		$this->assertSame( 'ORDER1', $out['order_ref'] );
		$sent = $fake->to( 'verify-webhook-signature' )[0]['body'];
		$this->assertStringEndsWithShim( ',"webhook_event":' . $payload . '}', $sent );
		$this->assertSame( self::PAYPAL_HOOK . 'test', json_decode( $sent, true )['webhook_id'] );

		$fake = $this->fake()->on( 'POST ' . self::SANDBOX . '/v1/notifications/verify-webhook-signature', 200, array( 'verification_status' => 'FAILURE' ) );
		$this->assertFalse( ( new QAF_Payment_PayPal( $fake->http() ) )->read_webhook( $payload, $headers )['ok'] );
		unset( $headers['paypal-transmission-sig'] );
		$this->assertFalse( ( new QAF_Payment_PayPal( $this->fake()->http() ) )->read_webhook( $payload, $headers )['ok'], 'no signature headers' );
	}

	public function test_a_refund_event_finds_its_capture_and_the_running_total() {
		$this->make_ready( 'paypal' );
		$payload = json_encode( array( 'id' => 'WH-EVT-2', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => array(
			'id' => 'REF1', 'amount' => array( 'currency_code' => 'USD', 'value' => '5.00' ),
			'seller_payable_breakdown' => array( 'total_refunded_amount' => array( 'value' => '15.00', 'currency_code' => 'USD' ) ),
			'links' => array( array( 'rel' => 'up', 'href' => 'https://api.sandbox.paypal.com/v2/payments/captures/CAP1' ) ),
		) ) );
		$headers = array( 'paypal-auth-algo' => 'a', 'paypal-cert-url' => 'b', 'paypal-transmission-id' => 'c', 'paypal-transmission-sig' => 'd', 'paypal-transmission-time' => 'e' );
		$fake    = $this->fake()->on( 'POST ' . self::SANDBOX . '/v1/notifications/verify-webhook-signature', 200, array( 'verification_status' => 'SUCCESS' ) );
		$out     = ( new QAF_Payment_PayPal( $fake->http() ) )->read_webhook( $payload, $headers );
		$this->assertSame( 'refund', $out['status'] );
		$this->assertSame( 'CAP1', $out['capture_ref'] );
		$this->assertSame( '15.00', $out['refunded_value'] );
	}

	public function test_approval_alone_is_ignored() {
		$this->make_ready( 'paypal' );
		$payload = '{"id":"WH-EVT-3","event_type":"CHECKOUT.ORDER.APPROVED","resource":{"id":"ORDER1"}}';
		$headers = array( 'paypal-auth-algo' => 'a', 'paypal-cert-url' => 'b', 'paypal-transmission-id' => 'c', 'paypal-transmission-sig' => 'd', 'paypal-transmission-time' => 'e' );
		$fake    = $this->fake()->on( 'POST ' . self::SANDBOX . '/v1/notifications/verify-webhook-signature', 200, array( 'verification_status' => 'SUCCESS' ) );
		$this->assertTrue( ( new QAF_Payment_PayPal( $fake->http() ) )->read_webhook( $payload, $headers )['ignore'] );
	}

	public function test_the_webhook_check_needs_this_sites_address_and_events() {
		$this->make_ready( 'paypal' );
		$good = array( 'id' => 'WH', 'url' => 'https://example.test/wp-json/qaf/v1/payments/webhook/paypal', 'event_types' => array_map( function ( $n ) {
			return array( 'name' => $n );
		}, QAF_Payment_PayPal::EVENTS ) );
		$fake = $this->fake()->on( 'GET ' . self::SANDBOX . '/v1/notifications/webhooks/', 200, $good );
		$this->assertTrue( ( new QAF_Payment_PayPal( $fake->http() ) )->verify_webhook( 'test' )['ok'] );
		$fake = $this->fake()->on( 'GET ' . self::SANDBOX . '/v1/notifications/webhooks/', 200, array( 'url' => 'https://elsewhere.test/hook' ) + $good );
		$this->assertFalse( ( new QAF_Payment_PayPal( $fake->http() ) )->verify_webhook( 'test' )['ok'] );
		$fake = $this->fake()->on( 'GET ' . self::SANDBOX . '/v1/notifications/webhooks/', 200, array( 'event_types' => array( array( 'name' => 'PAYMENT.CAPTURE.COMPLETED' ) ) ) + $good );
		$this->assertFalse( ( new QAF_Payment_PayPal( $fake->http() ) )->verify_webhook( 'test' )['ok'] );
	}

	public function test_live_uses_the_live_api() {
		$this->assertSame( 'https://api-m.paypal.com', QAF_Payment_PayPal::base( 'live' ) );
		$this->assertSame( self::SANDBOX, QAF_Payment_PayPal::base( 'test' ) );
	}

	private function assertStringEndsWithShim( $suffix, $s ) {
		$this->assertSame( $suffix, substr( $s, -strlen( $suffix ) ) );
	}
}
