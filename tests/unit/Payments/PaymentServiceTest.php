<?php
/**
 * The flow end to end with fake providers: checks before anything is saved,
 * one attempt per submission, idempotent start, server-checked results,
 * webhooks deduplicated and applied atomically, fulfilment fired once, and
 * the entry's payment object.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Service_Test extends QAF_Payment_Test_Case {

	const STRIPE = 'https://api.stripe.com/v1';
	const PAYPAL = 'https://api-m.sandbox.paypal.com';

	private static function pi( $extra = array() ) {
		return array_merge( array( 'id' => 'pi_1', 'amount' => 2500, 'currency' => 'usd', 'livemode' => false, 'status' => 'requires_payment_method', 'client_secret' => 'pi_1_secret_x' ), $extra );
	}

	private function opened( $service, $model = null, $values = array() ) {
		$model = $model ?? self::paid_form();
		$plan  = $service->prepare( $model, $values );
		$this->assertIsArray( $plan );
		return $service->open( 3, 41, $plan, 'abcdefABCDEF0123456789' );
	}

	private function deliver( $service, $type, $object, $id, $livemode = false ) {
		$p = self::stripe_event( $id, $type, $object, $livemode );
		return $service->webhook( 'stripe', $p, array( 'stripe-signature' => self::stripe_sign( $p ) ) );
	}

	public function test_prepare_refuses_when_the_site_cannot_take_the_payment() {
		$service = $this->service( new QAF_Fake_Provider() );
		$this->assertSame( 'qaf_payment_unavailable', $service->prepare( self::paid_form(), array() )->get_error_code(), 'nothing is ready' );
		$this->make_ready( 'stripe' );
		$this->assertIsArray( $service->prepare( self::paid_form(), array() ) );
		$this->assertSame( 'qaf_payment_unavailable', $service->prepare( self::paid_form( 25, array(), array( 'test_mode' => false ) ), array() )->get_error_code(), 'a live form on a test site' );
		QAF_Payment_Settings::set_mode( 'live' );
		$this->assertSame( 'qaf_payment_unavailable', $service->prepare( self::paid_form(), array() )->get_error_code(), 'a test form on a live site' );
	}

	public function test_prepare_checks_the_configuration_again() {
		$this->make_ready( 'stripe' );
		$service = $this->service( new QAF_Fake_Provider() );
		$bad     = self::paid_form( 25, array(), array( 'capture' => 'later' ) );
		$this->assertInstanceOf( 'WP_Error', $service->prepare( $bad, array() ) );
	}

	public function test_a_webhook_not_ready_is_not_ready() {
		$this->make_ready( 'stripe', 'test', false );
		$this->assertInstanceOf( 'WP_Error', $this->service( new QAF_Fake_Provider() )->prepare( self::paid_form(), array() ) );
	}

	public function test_the_page_gets_a_token_and_public_keys_only() {
		$this->make_ready( 'stripe' );
		$this->make_ready( 'paypal' );
		$service = $this->service( new QAF_Fake_Provider() );
		$opened  = $this->opened( $service );
		$page    = $service->for_page( $opened['attempt'], $opened['token'], QAF_Payment_Config::get( self::paid_form() ) );
		$this->assertSame( '25.00', $page['amount'] );
		$this->assertSame( 'USD', $page['currency'] );
		$this->assertSame( array( 'stripe', 'paypal' ), array_column( $page['providers'], 'id' ) );
		$json = json_encode( $page );
		foreach ( array( self::STRIPE_SK, self::STRIPE_WH, self::PAYPAL_SEC, self::PAYPAL_HOOK ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $json );
		}
		$this->assertStringContainsString( self::STRIPE_PK, $json, 'the publishable key is for the page' );
		$this->assertNotSame( $opened['token'], $opened['attempt']['token_hash'], 'the site keeps only a hash' );
		$this->assertSame( $opened['attempt']['id'], $service->by_token( $opened['token'] )['id'] );
		$this->assertNull( $service->by_token( str_repeat( 'a', 48 ) ) );
		$this->assertNull( $service->by_token( 'not-a-token' ) );
	}

	public function test_sending_the_same_page_twice_reuses_the_attempt() {
		$this->make_ready( 'stripe' );
		$service = $this->service( new QAF_Fake_Provider() );
		$opened  = $this->opened( $service );
		$again   = $service->existing( 3, 'abcdefABCDEF0123456789' );
		$this->assertSame( $opened['attempt']['id'], $again['id'] );
		$this->assertNull( $service->existing( 4, 'abcdefABCDEF0123456789' ), 'another form' );
		$this->assertNull( $service->existing( 3, 'short' ) );
		$token = $service->reissue( $again );
		$this->assertNull( $service->by_token( $opened['token'] ), 'the old token stops working' );
		$this->assertSame( $again['id'], $service->by_token( $token )['id'] );
	}

	public function test_starting_twice_resumes_the_same_intent() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() )
			->on( 'GET ' . self::STRIPE . '/payment_intents/pi_1', 200, self::pi() );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$config  = QAF_Payment_Config::get( self::paid_form() );
		$first   = $service->start( $opened['attempt'], 'stripe', $config );
		$second  = $service->start( $service->store()->get( $opened['attempt']['id'] ), 'stripe', $config );
		$this->assertSame( 'pi_1_secret_x', $first['client_secret'] );
		$this->assertSame( 'pi_1_secret_x', $second['client_secret'] );
		$this->assertCount( 1, $fake->to( '/payment_intents', 'POST' ), 'one intent, one possible charge' );
	}

	public function test_a_provider_the_form_does_not_offer_is_refused() {
		$this->make_ready( 'stripe' );
		$this->make_ready( 'paypal' );
		$service = $this->service( new QAF_Fake_Provider() );
		$opened  = $this->opened( $service );
		$config  = QAF_Payment_Config::get( self::paid_form( 25, array(), array( 'providers' => array( 'stripe' ) ) ) );
		$this->assertSame( 'qaf_payment_provider', $service->start( $opened['attempt'], 'paypal', $config )->get_error_code() );
	}

	public function test_a_paid_attempt_is_not_started_again() {
		$this->make_ready( 'stripe' );
		$fake    = new QAF_Fake_Provider();
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$service->settle( $opened['attempt'], 'paid' );
		$out = $service->start( $service->store()->get( $opened['attempt']['id'] ), 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$this->assertTrue( $out['status']['done'] );
		$this->assertCount( 0, $fake->requests );
	}

	public function test_only_the_provider_can_say_a_payment_succeeded() {
		$this->make_ready( 'stripe' );
		$fake = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() )
			->on( 'GET ' . self::STRIPE . '/payment_intents/pi_1', 200, self::pi( array( 'status' => 'requires_action' ) ) );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service, null, array( 'pay' => '{"status":"succeeded"}', 'paid' => true ) );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$status = $service->refresh( $service->store()->get( $opened['attempt']['id'] ) );
		$this->assertSame( 'pending', $status['status'] );
		$this->assertFalse( $status['done'] );
		$this->assertSame( 0, self::fired( 'qaf_payment_paid' ) );
	}

	public function test_a_webhook_marks_paid_once_however_often_it_arrives() {
		$this->make_ready( 'stripe' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$paid = self::pi( array( 'status' => 'succeeded', 'latest_charge' => 'ch_1' ) );
		$this->assertSame( 200, $this->deliver( $service, 'payment_intent.succeeded', $paid, 'evt_1' )['code'] );
		$this->assertTrue( $this->deliver( $service, 'payment_intent.succeeded', $paid, 'evt_1' )['body']['duplicate'], 'a replayed event' );
		$this->deliver( $service, 'payment_intent.succeeded', $paid, 'evt_1b' );
		$this->assertSame( 'paid', $service->store()->get( $opened['attempt']['id'] )['status'] );
		$this->assertSame( 1, self::fired( 'qaf_payment_paid' ), 'fulfilment runs once' );
	}

	public function test_out_of_order_events_do_not_downgrade() {
		$this->make_ready( 'stripe' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$this->deliver( $service, 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded' ) ), 'evt_2' );
		$this->deliver( $service, 'payment_intent.processing', self::pi( array( 'status' => 'processing' ) ), 'evt_1' );
		$this->deliver( $service, 'payment_intent.payment_failed', self::pi(), 'evt_0' );
		$this->assertSame( 'paid', $service->store()->get( $opened['attempt']['id'] )['status'] );
		$charge = array( 'id' => 'ch_1', 'payment_intent' => 'pi_1', 'amount' => 2500, 'currency' => 'usd' );
		$this->deliver( $service, 'charge.refunded', $charge + array( 'amount_refunded' => 2500 ), 'evt_4' );
		$this->deliver( $service, 'charge.refunded', $charge + array( 'amount_refunded' => 1000 ), 'evt_3' );
		$this->deliver( $service, 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded' ) ), 'evt_5' );
		$row = $service->store()->get( $opened['attempt']['id'] );
		$this->assertSame( 'refunded', $row['status'], 'a late partial refund or success does not undo a full refund' );
	}

	public function test_a_partial_refund_is_not_a_full_refund() {
		$this->make_ready( 'stripe' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$this->deliver( $service, 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded' ) ), 'evt_1' );
		$this->deliver( $service, 'charge.refunded', array( 'id' => 'ch_1', 'payment_intent' => 'pi_1', 'amount' => 2500, 'amount_refunded' => 500, 'currency' => 'usd' ), 'evt_2' );
		$row = $service->store()->get( $opened['attempt']['id'] );
		$this->assertSame( 'partially_refunded', $row['status'] );
		$this->assertSame( 500, $row['refunded_minor'] );
		$this->assertSame( 'partially_refunded', $service->for_entry( 41 )['status'] );
	}

	public function test_events_for_another_amount_currency_or_environment_are_not_applied() {
		$this->make_ready( 'stripe', 'test' );
		$this->make_ready( 'stripe', 'live' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$this->assertFalse( $this->deliver( $service, 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded', 'amount' => 1 ) ), 'evt_a' )['body']['applied'] );
		$this->assertFalse( $this->deliver( $service, 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded', 'currency' => 'eur' ) ), 'evt_b' )['body']['applied'] );
		$live = self::stripe_event( 'evt_c', 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded', 'livemode' => true ) ), true );
		$out  = $service->webhook( 'stripe', $live, array( 'stripe-signature' => self::stripe_sign( $live, 'whsec_liveplaceholder00000000000' ) ) );
		$this->assertFalse( $out['body']['applied'], 'a live event for a test attempt' );
		$this->assertSame( 'pending', $service->store()->get( $opened['attempt']['id'] )['status'] );
	}

	public function test_an_unsigned_or_forged_webhook_is_refused() {
		$this->make_ready( 'stripe' );
		$service = $this->service( new QAF_Fake_Provider() );
		$p       = self::stripe_event( 'evt_x', 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded' ) ) );
		$this->assertSame( 400, $service->webhook( 'stripe', $p, array() )['code'] );
		$this->assertSame( 400, $service->webhook( 'stripe', $p, array( 'stripe-signature' => self::stripe_sign( $p, 'whsec_guess' ) ) )['code'] );
		$this->assertSame( 404, $service->webhook( 'square', $p, array() )['code'] );
	}

	public function test_manual_capture_stays_authorized_until_captured() {
		$this->make_ready( 'stripe' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() );
		$service = $this->service( $fake );
		$model   = self::paid_form( 25, array(), array( 'capture' => 'manual' ) );
		$opened  = $this->opened( $service, $model );
		$this->assertSame( 'manual', $opened['attempt']['capture'] );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( $model ) );
		$this->deliver( $service, 'payment_intent.amount_capturable_updated', self::pi( array( 'status' => 'requires_capture' ) ), 'evt_1' );
		$this->assertSame( 'authorized', $service->store()->get( $opened['attempt']['id'] )['status'] );
		$this->assertSame( 0, self::fired( 'qaf_payment_paid' ) );
		$this->assertSame( 1, self::fired( 'qaf_payment_authorized' ) );
		$this->deliver( $service, 'payment_intent.processing', self::pi( array( 'status' => 'processing' ) ), 'evt_2' );
		$this->assertSame( 'authorized', $service->store()->get( $opened['attempt']['id'] )['status'] );
		$this->deliver( $service, 'payment_intent.succeeded', self::pi( array( 'status' => 'succeeded' ) ), 'evt_3' );
		$this->assertSame( 'paid', $service->store()->get( $opened['attempt']['id'] )['status'] );
	}

	public function test_a_failed_card_keeps_an_intelligible_state_and_a_retry() {
		$this->make_ready( 'stripe' );
		$failed = self::pi( array( 'last_payment_error' => array( 'message' => 'Your card was declined.' ) ) );
		$fake   = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() )
			->on( 'GET ' . self::STRIPE . '/payment_intents/pi_1', 200, $failed );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$service->start( $opened['attempt'], 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$status = $service->refresh( $service->store()->get( $opened['attempt']['id'] ) );
		$this->assertSame( 'failed', $status['status'] );
		$this->assertStringContainsString( 'try again', $status['message'] );
		$this->assertStringContainsString( 'Your card was declined.', $status['message'] );
		$again = $service->start( $service->store()->get( $opened['attempt']['id'] ), 'stripe', QAF_Payment_Config::get( self::paid_form() ) );
		$this->assertSame( 'pending', $again['status']['status'], 'retrying is allowed from failed' );
		$this->assertCount( 1, $fake->to( '/payment_intents', 'POST' ), 'the same intent takes the retry' );
	}

	public function test_switching_from_stripe_to_paypal_cancels_the_intent_first() {
		$this->make_ready( 'stripe' );
		$this->make_ready( 'paypal' );
		$fake = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::STRIPE . '/payment_intents', 200, self::pi() )
			->on( 'GET ' . self::STRIPE . '/payment_intents/pi_1', 200, self::pi() )
			->on( 'POST ' . self::STRIPE . '/payment_intents/pi_1/cancel', 200, self::pi( array( 'status' => 'canceled' ) ) )
			->on( 'POST ' . self::PAYPAL . '/v1/oauth2/token', 200, array( 'access_token' => 'tok' ) )
			->on( 'POST ' . self::PAYPAL . '/v2/checkout/orders', 201, array( 'id' => 'ORDER1' ) );
		$service = $this->service( $fake );
		$opened  = $this->opened( $service );
		$config  = QAF_Payment_Config::get( self::paid_form() );
		$service->start( $opened['attempt'], 'stripe', $config );
		$out = $service->start( $service->store()->get( $opened['attempt']['id'] ), 'paypal', $config );
		$this->assertSame( 'ORDER1', $out['order_id'] );
		$this->assertCount( 1, $fake->to( '/pi_1/cancel' ) );
		$row = $service->store()->get( $opened['attempt']['id'] );
		$this->assertSame( 'paypal', $row['provider'] );
		$this->assertSame( 'ORDER1', $row['provider_ref'] );
		$this->assertSame( 'qaf-order-' . $row['id'] . '-1', $fake->to( '/v2/checkout/orders' )[0]['headers']['PayPal-Request-Id'] );
	}

	public function test_paypal_end_to_end_with_a_capture_and_a_late_webhook() {
		$this->make_ready( 'paypal' );
		$capture = array( 'id' => 'ORDER1', 'status' => 'COMPLETED', 'purchase_units' => array( array( 'payments' => array( 'captures' => array( array( 'id' => 'CAP1', 'status' => 'COMPLETED', 'amount' => array( 'currency_code' => 'USD', 'value' => '25.00' ) ) ) ) ) ) );
		$fake    = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::PAYPAL . '/v1/oauth2/token', 200, array( 'access_token' => 'tok' ) )
			->on( 'POST ' . self::PAYPAL . '/v2/checkout/orders', 201, array( 'id' => 'ORDER1' ) )
			->on( 'POST ' . self::PAYPAL . '/v2/checkout/orders/ORDER1/capture', 201, $capture )
			->on( 'POST ' . self::PAYPAL . '/v1/notifications/verify-webhook-signature', 200, array( 'verification_status' => 'SUCCESS' ) );
		$service = $this->service( $fake );
		$model   = self::paid_form( 25, array(), array( 'providers' => array( 'paypal' ) ) );
		$opened  = $this->opened( $service, $model );
		$service->start( $opened['attempt'], 'paypal', QAF_Payment_Config::get( $model ) );
		$status = $service->finish( $service->store()->get( $opened['attempt']['id'] ) );
		$this->assertSame( 'paid', $status['status'] );
		$headers = array( 'paypal-auth-algo' => 'a', 'paypal-cert-url' => 'b', 'paypal-transmission-id' => 'c', 'paypal-transmission-sig' => 'd', 'paypal-transmission-time' => 'e' );
		$event   = json_encode( array( 'id' => 'WH-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => array( 'id' => 'CAP1', 'custom_id' => 'qaf-' . $opened['attempt']['id'], 'amount' => array( 'currency_code' => 'USD', 'value' => '25.00' ), 'supplementary_data' => array( 'related_ids' => array( 'order_id' => 'ORDER1' ) ) ) ) );
		$this->assertSame( 200, $service->webhook( 'paypal', $event, $headers )['code'] );
		$this->assertSame( 1, self::fired( 'qaf_payment_paid' ) );
		$this->assertSame( array(
			'provider'       => 'paypal',
			'status'         => 'paid',
			'amount'         => '25.00',
			'currency'       => 'USD',
			'receipt_url'    => '',
			'transaction_id' => 'CAP1',
		), $service->for_entry( 41 ) );
		$refund = json_encode( array( 'id' => 'WH-2', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => array( 'id' => 'R1', 'amount' => array( 'currency_code' => 'USD', 'value' => '25.00' ), 'seller_payable_breakdown' => array( 'total_refunded_amount' => array( 'value' => '25.00' ) ), 'links' => array( array( 'rel' => 'up', 'href' => 'https://x/v2/payments/captures/CAP1' ) ) ) ) );
		$service->webhook( 'paypal', $refund, $headers );
		$this->assertSame( 'refunded', $service->for_entry( 41 )['status'] );
	}

	public function test_an_entry_without_a_payment_has_no_payment_object() {
		$service = $this->service( new QAF_Fake_Provider() );
		$this->assertNull( $service->for_entry( 99 ) );
	}
}
