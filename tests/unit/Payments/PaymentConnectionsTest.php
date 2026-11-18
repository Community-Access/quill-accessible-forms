<?php
/**
 * Contract version 2: more than one connection per provider, chosen by the
 * payer or by the form's rules, each with its own keys, its own webhook,
 * its own entries and refunds; and a connection a form uses is never removed.
 *
 * "Done when: a form with two Stripe connections, a PayPal and a Square
 * routes test payments by each kind of rule, and refunds return to the
 * account that took them" (QUILL's complete.md), over fake transports.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Connections_Test extends QAF_Payment_Test_Case {

	const FUND = array(
		'id'      => 'fund',
		'type'    => 'radio',
		'label'   => 'Fund',
		'choices' => array(
			array( 'value' => 'building', 'label' => 'Building' ),
			array( 'value' => 'general', 'label' => 'General' ),
		),
	);

	private static function routed_form( $rules, $otherwise = 'stripe:events' ) {
		return self::paid_form( 'donation', array( self::FUND ), array(
			'providers' => array( 'stripe', 'paypal', 'square' ),
			'rules'     => $rules,
			'otherwise' => $otherwise,
		) );
	}

	private function all_ready() {
		foreach ( array( 'stripe:events', 'stripe:large-gifts', 'paypal', 'square:building' ) as $ref ) {
			$this->make_ready( $ref );
		}
	}

	private static function offered( $plan ) {
		return is_wp_error( $plan ) ? $plan->get_error_code() : $plan['offered'];
	}

	public function test_named_connections_are_listed_after_each_providers_main_one() {
		$this->assertSame( 'stripe:events', QAF_Payment_Connections::add( 'stripe', 'events', 'Stripe: Events' ) );
		$this->assertInstanceOf( 'WP_Error', QAF_Payment_Connections::add( 'stripe', 'events', 'Again' ) );
		$this->assertInstanceOf( 'WP_Error', QAF_Payment_Connections::add( 'stripe', 'Not OK!', 'Bad' ) );
		$this->assertInstanceOf( 'WP_Error', QAF_Payment_Connections::add( 'venmo', 'x', 'Bad' ) );
		$ids = array_column( QAF_Payment_Connections::all(), 'id' );
		$this->assertSame( array( 'stripe', 'paypal', 'square', 'stripe:events' ), $ids );
		$this->assertSame( 'Stripe: Events', QAF_Payment_Connections::name( 'stripe:events' ) );
		$this->assertFalse( QAF_Payment_Connections::exists( 'stripe:nobody' ) );
	}

	public function test_each_connection_keeps_its_own_keys_and_checks() {
		$this->make_ready( 'stripe:events' );
		$this->assertTrue( QAF_Payment_Settings::ready( 'stripe:events', 'test' ) );
		$this->assertFalse( QAF_Payment_Settings::ready( 'stripe', 'test' ), 'the main Stripe connection is not touched' );
		$this->assertSame( 'QAF_STRIPE_EVENTS_TEST_SECRET_KEY', QAF_Payment_Settings::constant_name( 'stripe:events', 'test', 'secret_key' ) );
		$this->assertSame( 'https://example.test/wp-json/qaf/v1/payments/webhook/stripe~events', QAF_Payment_Settings::webhook_url( 'stripe:events' ) );
		$row = array_values( array_filter( QAF_Payment_Settings::readiness()['connections'], function ( $c ) {
			return $c['id'] === 'stripe:events';
		} ) )[0];
		$this->assertSame( array( 'id' => 'stripe:events', 'name' => 'Stripe: events', 'test_mode' => true, 'configured' => true, 'webhook_ready' => true ), $row );
	}

	public function test_by_selection_the_payer_sees_only_ready_connections_in_order() {
		$this->make_ready( 'stripe:events' );
		$this->make_ready( 'square:building' );
		QAF_Payment_Connections::add( 'paypal', 'unused', 'PayPal: Unused' );
		$service = $this->service( new QAF_Fake_Provider() );
		$model   = self::paid_form( 25, array(), array( 'connections' => array( 'paypal:unused', 'stripe:events', 'square:building' ) ) );
		unset( $model['extensions']['payments']['providers'] );
		$plan = $service->prepare( $model, array() );
		$this->assertSame( array( 'stripe:events', 'square:building' ), $plan['offered'] );
		$opened = $service->open( 3, 41, $plan, 'abcdefABCDEF0123456789' );
		$page   = $service->for_page( $opened['attempt'], $opened['token'], QAF_Payment_Config::get( $model ) );
		$this->assertSame( array( 'Stripe: events', 'Square: building' ), array_column( $page['providers'], 'name' ) );
		$this->assertSame( 'qaf_payment_provider', $service->start( $opened['attempt'], 'paypal:unused', QAF_Payment_Config::get( $model ) )->get_error_code() );
	}

	public function test_rules_route_by_amount_choice_currency_and_date_with_a_fallback() {
		$this->all_ready();
		$service = $this->service( new QAF_Fake_Provider() );
		$config  = QAF_Payment_Config::get( self::routed_form( array() ) );
		$rules   = array(
			array( 'when' => array( 'amount_over' => '500' ), 'use' => 'stripe:large-gifts' ),
			array( 'when' => array( 'field' => 'fund', 'equals' => 'Building' ), 'use' => 'square:building' ),
			array( 'when' => array( 'currency' => 'usd', ), 'use' => 'paypal' ),
		);
		$model = self::routed_form( $rules );
		$this->assertSame( array(), QAF_Payment_Config::errors( $model ) );
		$big   = $service->prepare( $model, array( 'pay' => '600', 'fund' => 'building' ) );
		$this->assertSame( array( 'stripe:large-gifts' ), self::offered( $big ), 'over 500, whatever the fund' );
		$fund  = $service->prepare( $model, array( 'pay' => '40', 'fund' => 'building' ) );
		$this->assertSame( array( 'square:building' ), self::offered( $fund ), 'by the choice, written as the payer reads it' );
		$other = $service->prepare( $model, array( 'pay' => '40', 'fund' => 'general' ) );
		$this->assertSame( array( 'paypal' ), self::offered( $other ), 'by currency' );
		$plan   = array( 'minor' => 4000, 'currency' => 'usd', 'values' => array() );
		$window = array_merge( $config, array( 'by_rule' => true, 'rules' => array( array( 'when' => array( 'from' => '2026-12-01', 'until' => '2026-12-31' ), 'use' => 'stripe:large-gifts' ) ), 'otherwise' => 'stripe:events' ) );
		$this->assertSame( array( 'stripe:large-gifts' ), QAF_Payment_Routing::offered( $window, $model, $plan, null, '2026-12-31' ), 'the last day is in' );
		$this->assertSame( array( 'stripe:events' ), QAF_Payment_Routing::offered( $window, $model, $plan, null, '2027-01-01' ), 'after it, the fallback' );
	}

	public function test_a_rule_whose_connection_is_down_falls_through_and_none_left_means_no_payment() {
		$this->all_ready();
		$model = self::routed_form( array( array( 'when' => array( 'amount_over' => '500' ), 'use' => 'stripe:large-gifts' ) ) );
		$plan  = array( 'minor' => 60000, 'currency' => 'usd', 'values' => array() );
		$down  = function ( $ref ) {
			return $ref !== 'stripe:large-gifts';
		};
		$this->assertSame( array( 'stripe:events' ), QAF_Payment_Routing::offered( QAF_Payment_Config::get( $model ), $model, $plan, $down ) );
		$none = function () {
			return false;
		};
		$this->assertSame( array(), QAF_Payment_Routing::offered( QAF_Payment_Config::get( $model ), $model, $plan, $none ) );
	}

	public function test_amounts_are_compared_exactly_in_minor_units() {
		$model = self::routed_form( array( array( 'when' => array( 'amount_over' => '500' ), 'use' => 'stripe:large-gifts' ) ) );
		$yes   = function () {
			return true;
		};
		$at    = array( 'minor' => 50000, 'currency' => 'usd', 'values' => array() );
		$above = array( 'minor' => 50001, 'currency' => 'usd', 'values' => array() );
		$this->assertSame( array( 'stripe:events' ), QAF_Payment_Routing::offered( QAF_Payment_Config::get( $model ), $model, $at, $yes ), '500.00 is not over 500' );
		$this->assertSame( array( 'stripe:large-gifts' ), QAF_Payment_Routing::offered( QAF_Payment_Config::get( $model ), $model, $above, $yes ) );
	}

	public function test_rules_and_connections_are_checked_in_the_form() {
		$paths = function ( $config ) {
			return array_column( QAF_Payment_Config::errors( self::paid_form( 25, array(), $config ) ), 'message', 'path' );
		};
		$e = $paths( array( 'rules' => array( array( 'when' => array( 'amount_over' => '5' ), 'use' => 'stripe' ) ) ) );
		$this->assertArrayHasKey( '/extensions/payments/otherwise', $e, 'rules need an otherwise' );
		$e = $paths( array( 'connections' => array( 'stripe' ), 'rules' => array(), 'otherwise' => 'stripe' ) );
		$this->assertArrayHasKey( '/extensions/payments/rules', $e, 'never both' );
		$e = $paths( array( 'rules' => array( array( 'when' => array( 'from' => '2026-02-30' ), 'use' => 'stripe' ) ), 'otherwise' => 'stripe' ) );
		$this->assertArrayHasKey( '/extensions/payments/rules/0/when', $e, 'a day that does not exist' );
		$e = $paths( array( 'connections' => array( 'stripe:a', 'stripe:a', 'venmo' ) ) );
		$this->assertArrayHasKey( '/extensions/payments/connections/1', $e );
		$this->assertArrayHasKey( '/extensions/payments/connections/2', $e );
	}

	public function test_webhooks_and_entries_belong_to_the_connection_that_took_the_payment() {
		$this->make_ready( 'stripe:events' );
		$this->make_ready( 'stripe:large-gifts' );
		$fake    = ( new QAF_Fake_Provider() )->on( 'POST https://api.stripe.com/v1/payment_intents', 200, array( 'id' => 'pi_9', 'amount' => 2500, 'currency' => 'usd', 'livemode' => false, 'status' => 'requires_payment_method', 'client_secret' => 's' ) );
		$service = $this->service( $fake );
		$model   = self::paid_form( 25, array(), array( 'connections' => array( 'stripe:events', 'stripe:large-gifts' ) ) );
		$opened  = $service->open( 3, 41, $service->prepare( $model, array() ), 'abcdefABCDEF0123456789' );
		$service->start( $opened['attempt'], 'stripe:large-gifts', QAF_Payment_Config::get( $model ) );
		$event = self::stripe_event( 'evt_9', 'payment_intent.succeeded', array( 'id' => 'pi_9', 'amount' => 2500, 'currency' => 'usd', 'status' => 'succeeded' ) );
		$sig   = array( 'stripe-signature' => self::stripe_sign( $event ) );
		$this->assertFalse( $service->webhook( 'stripe~events', $event, $sig )['body']['applied'], 'the other Stripe account\'s webhook does not settle it' );
		$this->assertTrue( $service->webhook( 'stripe~large-gifts', $event, $sig )['body']['applied'] );
		$entry = $service->for_entry( 41 );
		$this->assertSame( array( 'stripe', 'stripe:large-gifts', 'Stripe: large-gifts', 'paid' ), array( $entry['provider'], $entry['connection'], $entry['connection_name'], $entry['status'] ) );
	}

	public function test_a_refund_returns_to_the_square_account_that_took_it() {
		$this->make_ready( 'square' );
		$this->make_ready( 'square:building' );
		QAF_Payment_Settings::set_secret( 'square:building', 'test', 'access_token', 'EAAA-building-token' );
		QAF_Payment_Settings::record_check( 'square:building', 'test', 'credentials', true, 'ok' );
		$pay  = array( 'payment' => array( 'id' => 'sq_b', 'status' => 'COMPLETED', 'amount_money' => array( 'amount' => 4000, 'currency' => 'USD' ), 'location_id' => self::SQUARE_LOC ) );
		$fake = ( new QAF_Fake_Provider() )
			->on( 'POST https://connect.squareupsandbox.com/v2/payments', 200, $pay )
			->on( 'POST https://connect.squareupsandbox.com/v2/refunds', 200, array( 'refund' => array( 'id' => 'r', 'status' => 'PENDING' ) ) );
		$service = $this->service( $fake );
		$model   = self::routed_form( array( array( 'when' => array( 'field' => 'fund', 'equals' => 'building' ), 'use' => 'square:building' ) ), 'square' );
		$plan    = $service->prepare( $model, array( 'pay' => '40', 'fund' => 'building' ) );
		$opened  = $service->open( 3, 41, $plan, 'abcdefABCDEF0123456789' );
		$config  = QAF_Payment_Config::get( $model );
		$service->start( $opened['attempt'], 'square:building', $config );
		$this->assertSame( 'paid', $service->pay( $service->store()->get( 1 ), 'cnon:ok', '', $config )['status'] );
		$service->refund( $service->store()->get( 1 ), 4000 );
		foreach ( $fake->requests as $r ) {
			$this->assertSame( 'Bearer EAAA-building-token', $r['headers']['Authorization'], 'every call, the refund too, in the building fund\'s account' );
		}
	}

	public function test_a_connection_a_form_uses_is_never_removed_and_the_forms_are_named() {
		$this->make_ready( 'stripe:events' );
		$model = self::paid_form( 25, array(), array( 'connections' => array( 'stripe:events' ) ) );
		QAF_Payment_Connections::$forms_source = function () use ( $model ) {
			return array( array( 'id' => 7, 'title' => 'Gala tickets', 'model' => $model ) );
		};
		$refused = QAF_Payment_Connections::remove( 'stripe:events' );
		$this->assertInstanceOf( 'WP_Error', $refused );
		$this->assertStringContainsString( 'Gala tickets', $refused->get_error_message() );
		$this->assertTrue( QAF_Payment_Settings::ready( 'stripe:events', 'test' ), 'nothing was removed' );
		$this->assertSame( array( array( 'id' => 7, 'title' => 'Gala tickets' ) ), QAF_Payment_Connections::forms_using( 'stripe:events' ) );
		QAF_Payment_Connections::$forms_source = function () {
			return array();
		};
		$this->assertTrue( QAF_Payment_Connections::remove( 'stripe:events' ) );
		$this->assertFalse( QAF_Payment_Connections::exists( 'stripe:events' ) );
		$this->assertFalse( QAF_Payment_Settings::has_secret( 'stripe:events', 'test', 'secret_key' ), 'its keys go with it' );
		$this->assertInstanceOf( 'WP_Error', QAF_Payment_Connections::remove( 'stripe' ), 'a provider\'s main connection stays' );
		QAF_Payment_Connections::$forms_source = null;
	}
}
