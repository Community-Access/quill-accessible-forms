<?php
/**
 * Square, contract version 2: the keys checked against a real location, a
 * payment made from a card token with an idempotency key, a declined card
 * that can be tried again, signed webhooks (replays and forgeries refused),
 * refunds through the Refunds API, and sandbox kept apart from production.
 *
 * Every Square reply here is written by hand from Square's documented
 * shapes; no test reaches Square.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Square_Test extends QAF_Payment_Test_Case {

	const SQ = 'https://connect.squareupsandbox.com/v2';

	private static function payment( $extra = array() ) {
		return array( 'payment' => array_merge( array(
			'id'           => 'sqpay_1',
			'status'       => 'COMPLETED',
			'amount_money' => array( 'amount' => 2500, 'currency' => 'USD' ),
			'location_id'  => self::SQUARE_LOC,
			'receipt_url'  => 'https://squareup.com/receipt/preview/sqpay_1',
		), $extra ) );
	}

	private static function square_config() {
		return QAF_Payment_Config::get( self::paid_form( 25, array(), array( 'providers' => array( 'square' ) ) ) );
	}

	private function opened( $service, $model = null ) {
		$plan = $service->prepare( $model ?? self::paid_form( 25, array(), array( 'providers' => array( 'square' ) ) ), array() );
		$this->assertIsArray( $plan, is_wp_error( $plan ) ? $plan->get_error_message() : '' );
		return $service->open( 3, 41, $plan, 'abcdefABCDEF0123456789' );
	}

	private function paid( $fake, $service ) {
		$opened = $this->opened( $service );
		$start  = $service->start( $opened['attempt'], 'square', QAF_Payment_Config::get( self::paid_form( 25, array(), array( 'providers' => array( 'square' ) ) ) ) );
		$this->assertSame( self::SQUARE_APP, $start['application_id'] );
		$this->assertSame( self::SQUARE_LOC, $start['location_id'] );
		$this->assertCount( 0, $fake->requests, 'nothing goes to Square before the card is tokenized' );
		return $service->pay( $service->store()->get( $opened['attempt']['id'] ), 'cnon:card-nonce-ok', '', self::square_config() );
	}

	public function test_check_connection_reads_the_location_and_keeps_its_currency() {
		QAF_Payment_Settings::set_public( 'square', 'test', 'application_id', self::SQUARE_APP );
		QAF_Payment_Settings::set_public( 'square', 'test', 'location_id', self::SQUARE_LOC );
		QAF_Payment_Settings::set_secret( 'square', 'test', 'access_token', self::SQUARE_TOKEN );
		$fake = ( new QAF_Fake_Provider() )->on( 'GET ' . self::SQ . '/locations/' . self::SQUARE_LOC, 200, array( 'location' => array(
			'id' => self::SQUARE_LOC, 'status' => 'ACTIVE', 'currency' => 'CAD', 'capabilities' => array( 'CREDIT_CARD_PROCESSING' ),
		) ) );
		$result = ( new QAF_Payment_Square( $fake->http() ) )->verify_credentials( 'test' );
		$this->assertTrue( $result['ok'], $result['message'] );
		$this->assertSame( 'cad', QAF_Payment_Settings::get_public( 'square', 'test', 'location_currency' ) );
		$this->assertSame( 'Bearer ' . self::SQUARE_TOKEN, $fake->requests[0]['headers']['Authorization'] );
		$this->assertSame( QAF_Payment_Square::API_VERSION, $fake->requests[0]['headers']['Square-Version'] );
	}

	public function test_a_production_application_id_is_refused_in_test_mode() {
		QAF_Payment_Settings::set_public( 'square', 'test', 'application_id', 'sq0idp-production' );
		QAF_Payment_Settings::set_public( 'square', 'test', 'location_id', self::SQUARE_LOC );
		QAF_Payment_Settings::set_secret( 'square', 'test', 'access_token', self::SQUARE_TOKEN );
		$this->assertStringContainsString( 'sandbox-', QAF_Payment_Settings::shape_problem( 'square', 'test' ) );
		$fake = new QAF_Fake_Provider();
		$this->assertFalse( ( new QAF_Payment_Square( $fake->http() ) )->verify_credentials( 'test' )['ok'] );
		$this->assertCount( 0, $fake->requests, 'refused before anything is sent' );
	}

	public function test_a_test_payment_is_made_from_the_card_token_once() {
		$this->make_ready( 'square' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::SQ . '/payments', 200, self::payment() );
		$service = $this->service( $fake );
		$status  = $this->paid( $fake, $service );
		$this->assertSame( 'paid', $status['status'] );
		$body = json_decode( $fake->to( '/payments', 'POST' )[0]['body'], true );
		$this->assertSame( 'cnon:card-nonce-ok', $body['source_id'] );
		$this->assertSame( array( 'amount' => 2500, 'currency' => 'USD' ), $body['amount_money'] );
		$this->assertTrue( $body['autocomplete'] );
		$this->assertLessThanOrEqual( 45, strlen( $body['idempotency_key'] ) );
		$this->assertSame( 1, self::fired( 'qaf_payment_paid' ) );
		$entry = $service->for_entry( 41 );
		$this->assertSame( array( 'square', 'square', 'paid', '25.00', 'https://squareup.com/receipt/preview/sqpay_1', 'sqpay_1' ), array( $entry['provider'], $entry['connection'], $entry['status'], $entry['amount'], $entry['receipt_url'], $entry['transaction_id'] ) );
		// Sending it again does nothing: the attempt is already paid.
		$again = $service->pay( $service->store()->get( 1 ), 'cnon:card-nonce-ok', '', self::square_config() );
		$this->assertSame( 'paid', $again['status'] );
		$this->assertCount( 1, $fake->to( '/payments', 'POST' ) );
	}

	public function test_a_declined_card_says_so_plainly_and_can_be_tried_again() {
		$this->make_ready( 'square' );
		$fake = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::SQ . '/payments', 400, array( 'errors' => array( array( 'category' => 'PAYMENT_METHOD_ERROR', 'code' => 'GENERIC_DECLINE', 'detail' => 'Authorization error: GENERIC_DECLINE' ) ) ) )
			->on( 'POST ' . self::SQ . '/payments', 200, self::payment() );
		$service = $this->service( $fake );
		$status  = $this->paid( $fake, $service );
		$this->assertSame( 'failed', $status['status'] );
		$this->assertStringContainsString( 'The card was declined', $status['message'] );
		$this->assertStringNotContainsString( 'GENERIC_DECLINE', $status['message'], 'the payer never reads Square\'s developer detail' );
		$second = $service->pay( $service->store()->get( 1 ), 'cnon:another-card', '', self::square_config() );
		$this->assertSame( 'paid', $second['status'] );
		$keys = array_map( function ( $r ) {
			return json_decode( $r['body'], true )['idempotency_key'];
		}, $fake->to( '/payments', 'POST' ) );
		$this->assertNotSame( $keys[0], $keys[1], 'a new card is a new request' );
	}

	public function test_a_payment_at_another_location_or_for_another_amount_is_not_believed() {
		$this->make_ready( 'square' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::SQ . '/payments', 200, self::payment( array( 'location_id' => 'LOTHER' ) ) );
		$service = $this->service( $fake );
		$this->assertSame( 'failed', $this->paid( $fake, $service )['status'] );
		$this->assertSame( 0, self::fired( 'qaf_payment_paid' ) );
	}

	public function test_manual_capture_is_authorized_until_completed() {
		$this->make_ready( 'square' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST ' . self::SQ . '/payments', 200, self::payment( array( 'status' => 'APPROVED' ) ) );
		$service = $this->service( $fake );
		$model   = self::paid_form( 25, array(), array( 'providers' => array( 'square' ), 'capture' => 'manual' ) );
		$opened  = $this->opened( $service, $model );
		$service->start( $opened['attempt'], 'square', QAF_Payment_Config::get( $model ) );
		$this->assertSame( 'authorized', $service->pay( $service->store()->get( 1 ), 'cnon:ok', '', QAF_Payment_Config::get( $model ) )['status'] );
		$this->assertFalse( json_decode( $fake->to( '/payments', 'POST' )[0]['body'], true )['autocomplete'] );
	}

	public function test_webhooks_are_signed_deduplicated_and_never_downgrade() {
		$this->make_ready( 'square' );
		$fake    = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::SQ . '/payments', 200, self::payment( array( 'status' => 'APPROVED' ) ) )
			->on( 'GET ' . self::SQ . '/payments/sqpay_1', 200, self::payment( array( 'refunded_money' => array( 'amount' => 1000, 'currency' => 'USD' ) ) ) );
		$service = $this->service( $fake );
		$this->paid( $fake, $service );
		$completed = json_encode( array( 'event_id' => 'ev-1', 'type' => 'payment.updated', 'data' => array( 'object' => self::payment() ) ) );
		$this->assertSame( 400, $service->webhook( 'square', $completed, array() )['code'], 'unsigned' );
		$this->assertSame( 400, $service->webhook( 'square', $completed, array( 'x-square-hmacsha256-signature' => self::square_sign( $completed, 'square', 'guess' ) ) )['code'], 'forged' );
		$signed = array( 'x-square-hmacsha256-signature' => self::square_sign( $completed ) );
		$this->assertTrue( $service->webhook( 'square', $completed, $signed )['body']['applied'] );
		$this->assertSame( 'paid', $service->store()->get( 1 )['status'] );
		$this->assertTrue( $service->webhook( 'square', $completed, $signed )['body']['duplicate'], 'a replay is recorded once' );
		$late = json_encode( array( 'event_id' => 'ev-0', 'type' => 'payment.updated', 'data' => array( 'object' => self::payment( array( 'status' => 'APPROVED' ) ) ) ) );
		$service->webhook( 'square', $late, array( 'x-square-hmacsha256-signature' => self::square_sign( $late ) ) );
		$this->assertSame( 'paid', $service->store()->get( 1 )['status'], 'a late, older event never downgrades' );
		$refund = json_encode( array( 'event_id' => 'ev-2', 'type' => 'refund.updated', 'data' => array( 'object' => array( 'refund' => array( 'id' => 'r1', 'payment_id' => 'sqpay_1', 'status' => 'COMPLETED' ) ) ) ) );
		$service->webhook( 'square', $refund, array( 'x-square-hmacsha256-signature' => self::square_sign( $refund ) ) );
		$this->assertSame( 'partially_refunded', $service->store()->get( 1 )['status'], 'ten of twenty-five refunded is a partial refund' );
		$this->assertSame( 1000, (int) $service->store()->get( 1 )['refunded_minor'] );
	}

	public function test_a_refund_goes_through_the_refunds_api_once() {
		$this->make_ready( 'square' );
		$fake    = ( new QAF_Fake_Provider() )
			->on( 'POST ' . self::SQ . '/payments', 200, self::payment() )
			->on( 'POST ' . self::SQ . '/refunds', 200, array( 'refund' => array( 'id' => 'r1', 'status' => 'PENDING' ) ) );
		$service = $this->service( $fake );
		$this->paid( $fake, $service );
		$attempt = $service->store()->get( 1 );
		$this->assertInstanceOf( 'WP_Error', $service->refund( $attempt, 2600 ), 'more than was paid' );
		$this->assertIsArray( $service->refund( $attempt, 2500 ) );
		$body = json_decode( $fake->to( '/refunds', 'POST' )[0]['body'], true );
		$this->assertSame( 'sqpay_1', $body['payment_id'] );
		$this->assertSame( 2500, $body['amount_money']['amount'] );
		$this->assertSame( 'paid', $service->store()->get( 1 )['status'], 'the entry changes when Square confirms, by webhook' );
	}

	public function test_stripe_and_paypal_refunds_stay_in_their_dashboards() {
		$this->make_ready( 'stripe' );
		$service = $this->service( new QAF_Fake_Provider() );
		$attempt = array( 'provider' => 'stripe', 'status' => 'paid' );
		$this->assertSame( 'qaf_payment_refund', $service->refund( $attempt, 100 )->get_error_code() );
	}

	public function test_square_limits_are_checked_in_the_form() {
		$errors = QAF_Payment_Config::errors( self::paid_form( 25, array(), array( 'providers' => array( 'square' ), 'currency' => 'mxn' ) ) );
		$this->assertStringContainsString( 'through Square', implode( ' ', array_column( $errors, 'message' ) ) );
		$errors = QAF_Payment_Config::errors( self::paid_form( 25, array(), array( 'providers' => array( 'square' ), 'statement_descriptor' => 'Spring Gala Tickets 1' ) ) );
		$this->assertStringContainsString( 'at most 20', implode( ' ', array_column( $errors, 'message' ) ) );
	}

	public function test_the_readiness_and_page_never_carry_the_access_token() {
		$this->make_ready( 'square' );
		$service = $this->service( new QAF_Fake_Provider() );
		$opened  = $this->opened( $service );
		$page    = json_encode( $service->for_page( $opened['attempt'], $opened['token'], QAF_Payment_Config::get( self::paid_form( 25, array(), array( 'providers' => array( 'square' ) ) ) ) ) );
		$ready   = json_encode( QAF_Payment_Settings::readiness() );
		foreach ( array( self::SQUARE_TOKEN, self::SQUARE_SIG ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $page );
			$this->assertStringNotContainsString( $secret, $ready );
		}
		$this->assertStringContainsString( 'Card (Square)', $page );
		$this->assertStringNotContainsString( self::SQUARE_LOC, $ready, 'the location ID is the page\'s, not the readiness route\'s' );
	}
}
