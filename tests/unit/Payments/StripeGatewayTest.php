<?php
/**
 * Stripe through a fake transport: Payment Intents created on the server
 * with idempotency keys, statuses read from Stripe (not the browser), and
 * webhook signatures checked.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Stripe_Gateway_Test extends QAF_Payment_Test_Case {

	private function attempt( $extra = array() ) {
		return array_merge( array(
			'id' => 7, 'form_id' => 3, 'entry_id' => 41, 'environment' => 'test', 'status' => 'pending',
			'amount_minor' => 2500, 'currency' => 'usd', 'capture' => 'automatic', 'provider' => 'stripe',
			'provider_ref' => '', 'capture_ref' => '', 'tries' => 0, 'refunded_minor' => 0, 'receipt_url' => '',
		), $extra );
	}

	private static function pi( $extra = array() ) {
		return array_merge( array( 'id' => 'pi_123', 'object' => 'payment_intent', 'amount' => 2500, 'currency' => 'usd', 'livemode' => false, 'status' => 'requires_payment_method', 'client_secret' => 'pi_123_secret_abc' ), $extra );
	}

	public function test_an_intent_is_created_on_the_server_for_the_attempts_amount() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'POST https://api.stripe.com/v1/payment_intents', 200, self::pi() );
		$out  = ( new QAF_Payment_Stripe( $fake->http() ) )->start( $this->attempt(), 'BITS EVENT' );
		$this->assertTrue( $out['ok'] );
		$this->assertSame( 'pi_123_secret_abc', $out['client_secret'] );
		$req = $fake->requests[0];
		$this->assertSame( '2500', $req['body']['amount'] );
		$this->assertSame( 'usd', $req['body']['currency'] );
		$this->assertSame( 'automatic', $req['body']['capture_method'] );
		$this->assertSame( 'BITS EVENT', $req['body']['statement_descriptor_suffix'] );
		$this->assertSame( 'qaf-attempt-7-0', $req['headers']['Idempotency-Key'] );
		$this->assertSame( 'Bearer ' . self::STRIPE_SK, $req['headers']['Authorization'] );
		$this->assertSame( QAF_Payment_Stripe::API_VERSION, $req['headers']['Stripe-Version'] );
	}

	public function test_manual_capture_asks_for_an_authorization() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'POST https://api.stripe.com/v1/payment_intents', 200, self::pi() );
		( new QAF_Payment_Stripe( $fake->http() ) )->start( $this->attempt( array( 'capture' => 'manual' ) ) );
		$this->assertSame( 'manual', $fake->requests[0]['body']['capture_method'] );
	}

	public function test_an_existing_intent_is_resumed_not_duplicated() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET https://api.stripe.com/v1/payment_intents/pi_123', 200, self::pi() );
		$out  = ( new QAF_Payment_Stripe( $fake->http() ) )->start( $this->attempt( array( 'provider_ref' => 'pi_123' ) ) );
		$this->assertTrue( $out['ok'] );
		$this->assertCount( 0, $fake->to( '/payment_intents', 'POST' ) );
	}

	public function test_an_intent_already_paid_is_reported_not_replaced() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET https://api.stripe.com/v1/payment_intents/pi_123', 200, self::pi( array( 'status' => 'succeeded' ) ) );
		$out  = ( new QAF_Payment_Stripe( $fake->http() ) )->start( $this->attempt( array( 'provider_ref' => 'pi_123' ) ) );
		$this->assertSame( 'paid', $out['status'] );
		$this->assertCount( 0, $fake->to( '/payment_intents', 'POST' ), 'a paid webhook may still be on its way' );
	}

	public function test_an_intent_for_another_amount_or_environment_is_refused() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'POST https://api.stripe.com/v1/payment_intents', 200, self::pi( array( 'amount' => 1 ) ) );
		$this->assertFalse( ( new QAF_Payment_Stripe( $fake->http() ) )->start( $this->attempt() )['ok'] );
		$fake = ( new QAF_Fake_Provider() )->on( 'POST https://api.stripe.com/v1/payment_intents', 200, self::pi( array( 'livemode' => true ) ) );
		$this->assertFalse( ( new QAF_Payment_Stripe( $fake->http() ) )->start( $this->attempt() )['ok'] );
	}

	public function test_statuses_come_from_stripe() {
		$this->assertSame( 'paid', QAF_Payment_Stripe::state( array( 'status' => 'succeeded' ) ) );
		$this->assertSame( 'authorized', QAF_Payment_Stripe::state( array( 'status' => 'requires_capture' ) ) );
		$this->assertSame( 'pending', QAF_Payment_Stripe::state( array( 'status' => 'requires_action' ) ) );
		$this->assertSame( 'pending', QAF_Payment_Stripe::state( array( 'status' => 'processing' ) ) );
		$this->assertSame( 'failed', QAF_Payment_Stripe::state( array( 'status' => 'requires_payment_method', 'last_payment_error' => array( 'message' => 'Declined' ) ) ) );
		$this->assertSame( 'cancelled', QAF_Payment_Stripe::state( array( 'status' => 'canceled' ) ) );
		$this->assertSame( 'partially_refunded', QAF_Payment_Stripe::state( array( 'status' => 'succeeded', 'latest_charge' => array( 'amount' => 2500, 'amount_refunded' => 500 ) ) ) );
		$this->assertSame( 'refunded', QAF_Payment_Stripe::state( array( 'status' => 'succeeded', 'latest_charge' => array( 'amount' => 2500, 'amount_refunded' => 2500 ) ) ) );
	}

	public function test_fetch_reads_the_receipt_and_refunds() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET https://api.stripe.com/v1/payment_intents/pi_123', 200, self::pi( array(
			'status'        => 'succeeded',
			'latest_charge' => array( 'id' => 'ch_1', 'amount' => 2500, 'amount_refunded' => 1000, 'receipt_url' => 'https://pay.stripe.com/receipts/x' ),
		) ) );
		$out = ( new QAF_Payment_Stripe( $fake->http() ) )->fetch( $this->attempt( array( 'provider_ref' => 'pi_123' ) ) );
		$this->assertSame( 'partially_refunded', $out['status'] );
		$this->assertSame( 'https://pay.stripe.com/receipts/x', $out['receipt_url'] );
		$this->assertSame( 1000, $out['refunded_minor'] );
		$this->assertStringContainsString( 'expand%5B%5D=latest_charge', $fake->requests[0]['url'] );
	}

	public function test_credentials_are_checked_against_the_environment() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET https://api.stripe.com/v1/balance', 200, array( 'object' => 'balance', 'livemode' => false ) );
		$this->assertTrue( ( new QAF_Payment_Stripe( $fake->http() ) )->verify_credentials( 'test' )['ok'] );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET https://api.stripe.com/v1/balance', 200, array( 'object' => 'balance', 'livemode' => true ) );
		$this->assertFalse( ( new QAF_Payment_Stripe( $fake->http() ) )->verify_credentials( 'test' )['ok'] );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET https://api.stripe.com/v1/balance', 401, array( 'error' => array( 'message' => 'Invalid API Key provided: sk_test_****' ) ) );
		$out  = ( new QAF_Payment_Stripe( $fake->http() ) )->verify_credentials( 'test' );
		$this->assertFalse( $out['ok'] );
		$this->assertStringNotContainsString( 'sk_test', $out['message'], 'a provider error is not passed on word for word' );
	}

	public function test_create_webhook_keeps_the_signing_secret_once() {
		$this->make_ready( 'stripe', 'test', false );
		$fake = ( new QAF_Fake_Provider() )->on( 'POST https://api.stripe.com/v1/webhook_endpoints', 200, array( 'id' => 'we_1', 'secret' => self::STRIPE_WH, 'livemode' => false ) );
		$out  = ( new QAF_Payment_Stripe( $fake->http() ) )->create_webhook( 'test' );
		$this->assertTrue( $out['ok'] );
		$this->assertSame( self::STRIPE_WH, QAF_Payment_Settings::get_secret( 'stripe', 'test', 'webhook_secret' ) );
		$this->assertSame( 'https://example.test/wp-json/qaf/v1/payments/webhook/stripe', $fake->requests[0]['body']['url'] );
		$this->assertContains( 'charge.refunded', array_values( array_filter( $fake->requests[0]['body'], 'is_string' ) ) );
		$this->assertStringNotContainsString( self::STRIPE_WH, $out['message'] );
	}

	public function test_signatures_are_checked_with_the_signing_secret_and_a_time_limit() {
		$now     = 1_800_000_000;
		$stripe  = new QAF_Payment_Stripe( null, function () use ( $now ) {
			return $now;
		} );
		$payload = '{"id":"evt_1"}';
		$this->assertTrue( $stripe->signature_valid( $payload, self::stripe_sign( $payload, self::STRIPE_WH, $now ), self::STRIPE_WH ) );
		$this->assertFalse( $stripe->signature_valid( $payload . ' ', self::stripe_sign( $payload, self::STRIPE_WH, $now ), self::STRIPE_WH ), 'the body changed' );
		$this->assertFalse( $stripe->signature_valid( $payload, self::stripe_sign( $payload, 'whsec_wrong', $now ), self::STRIPE_WH ) );
		$this->assertFalse( $stripe->signature_valid( $payload, self::stripe_sign( $payload, self::STRIPE_WH, $now - 301 ), self::STRIPE_WH ), 'too old: a replay' );
		$this->assertFalse( $stripe->signature_valid( $payload, '', self::STRIPE_WH ) );
		$this->assertFalse( $stripe->signature_valid( $payload, 't=abc,v1=00', self::STRIPE_WH ) );
		$this->assertTrue( $stripe->signature_valid( $payload, 't=' . $now . ',v1=00,' . substr( self::stripe_sign( $payload, self::STRIPE_WH, $now ), strlen( 't=' . $now . ',' ) ), self::STRIPE_WH ), 'any v1 may match (secret rolling)' );
	}

	public function test_a_webhook_names_its_environment_by_the_secret_that_verified_it() {
		$this->make_ready( 'stripe', 'test' );
		$this->make_ready( 'stripe', 'live' );
		$stripe  = new QAF_Payment_Stripe( ( new QAF_Fake_Provider() )->http() );
		$payload = self::stripe_event( 'evt_1', 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded' ) ) );
		$out     = $stripe->read_webhook( $payload, array( 'stripe-signature' => self::stripe_sign( $payload ) ) );
		$this->assertTrue( $out['ok'] );
		$this->assertSame( 'test', $out['env'] );
		$this->assertSame( 'paid', $out['status'] );
		$this->assertSame( 2500, $out['amount_minor'] );
		$liar = self::stripe_event( 'evt_2', 'payment_intent.succeeded', self::pi(), true );
		$this->assertFalse( $stripe->read_webhook( $liar, array( 'stripe-signature' => self::stripe_sign( $liar ) ) )['ok'], 'signed with the test secret, claiming live' );
	}

	public function test_a_signed_event_verifies_a_pasted_signing_secret() {
		$this->make_ready( 'stripe', 'test', false );
		QAF_Payment_Settings::set_secret( 'stripe', 'test', 'webhook_secret', self::STRIPE_WH );
		$this->assertFalse( QAF_Payment_Settings::webhook_ready( 'stripe', 'test' ) );
		$payload = self::stripe_event( 'evt_ping', 'payment_intent.created', self::pi() );
		( new QAF_Payment_Stripe() )->read_webhook( $payload, array( 'stripe-signature' => self::stripe_sign( $payload ) ) );
		$this->assertTrue( QAF_Payment_Settings::webhook_ready( 'stripe', 'test' ) );
	}

	public function test_a_refund_event_reports_partial_and_full_refunds() {
		$this->make_ready( 'stripe' );
		$stripe = new QAF_Payment_Stripe();
		$charge = array( 'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_123', 'amount' => 2500, 'amount_refunded' => 500, 'currency' => 'usd' );
		$p      = self::stripe_event( 'evt_r1', 'charge.refunded', $charge );
		$out    = $stripe->read_webhook( $p, array( 'stripe-signature' => self::stripe_sign( $p ) ) );
		$this->assertSame( 'partially_refunded', $out['status'] );
		$this->assertSame( 'pi_123', $out['ref'] );
		$p   = self::stripe_event( 'evt_r2', 'charge.refunded', array( 'amount_refunded' => 2500 ) + $charge );
		$this->assertSame( 'refunded', $stripe->read_webhook( $p, array( 'stripe-signature' => self::stripe_sign( $p ) ) )['status'] );
	}
}
