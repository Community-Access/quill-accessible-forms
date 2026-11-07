<?php
/**
 * Contract, Model and publication: the payments block and the payment
 * field's amount are validated on publication (and again at checkout), and
 * nothing unsupported is silently changed.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model-converter.php';

class QAF_Payment_Config_Test extends QAF_Payment_Test_Case {

	private static function paths( $model ) {
		return array_column( QAF_Payment_Config::errors( $model ), 'message', 'path' );
	}

	public function test_a_complete_form_has_no_errors() {
		$this->assertSame( array(), QAF_Payment_Config::errors( self::paid_form() ) );
		$this->assertSame( array(), QAF_Payment_Config::errors( self::paid_form( 'donation', array(), array(), array( 'donation_min' => 5, 'donation_max' => 500 ) ) ) );
		$this->assertSame( array(), QAF_Payment_Config::errors( self::shop_form() ) );
	}

	public function test_a_form_without_payments_is_left_alone() {
		$model = array( 'pages' => array( array( 'id' => 'p', 'items' => array( array( 'id' => 'a', 'type' => 'text' ) ) ) ) );
		$this->assertSame( array(), QAF_Payment_Config::errors( $model ) );
		$this->assertFalse( QAF_Payment_Config::applies( $model ) );
	}

	public function test_providers_must_be_known_unique_and_present() {
		$e = self::paths( self::paid_form( 25, array(), array( 'providers' => array( 'stripe', 'stripe', 'square' ) ) ) );
		$this->assertArrayHasKey( '/extensions/payments/providers/1', $e );
		$this->assertArrayHasKey( '/extensions/payments/providers/2', $e );
		$e = self::paths( self::paid_form( 25, array(), array( 'providers' => array() ) ) );
		$this->assertArrayHasKey( '/extensions/payments/providers', $e );
	}

	public function test_a_currency_a_provider_does_not_take_is_refused() {
		$e = self::paths( self::paid_form( 25, array(), array( 'currency' => 'inr' ) ) );
		$this->assertStringContainsString( 'PayPal', $e['/extensions/payments/currency'] );
		$this->assertSame( array(), QAF_Payment_Config::errors( self::paid_form( 25, array(), array( 'currency' => 'inr', 'providers' => array( 'stripe' ) ) ) ) );
		$this->assertArrayHasKey( '/extensions/payments/currency', self::paths( self::paid_form( 25, array(), array( 'currency' => 'dollars' ) ) ) );
	}

	public function test_mode_capture_and_test_mode_have_fixed_values() {
		$e = self::paths( self::paid_form( 25, array(), array( 'mode' => 'subscription', 'capture' => 'later', 'test_mode' => 'yes' ) ) );
		$this->assertArrayHasKey( '/extensions/payments/mode', $e );
		$this->assertArrayHasKey( '/extensions/payments/capture', $e );
		$this->assertArrayHasKey( '/extensions/payments/test_mode', $e );
	}

	public function test_statement_descriptor_follows_provider_rules() {
		$ok = self::paid_form( 25, array(), array( 'statement_descriptor' => 'BITS DONATION' ) );
		$this->assertSame( array(), QAF_Payment_Config::errors( $ok ) );
		foreach ( array( 'ABCDEFGHIJKLMNOPQRSTUVW', 'ABC', 'BITS <club>', '12345', "Caf\xc3\xa9 Bits" ) as $bad ) {
			$this->assertArrayHasKey( '/extensions/payments/statement_descriptor', self::paths( self::paid_form( 25, array(), array( 'statement_descriptor' => $bad ) ) ), $bad );
		}
	}

	public function test_only_one_payment_field() {
		$model = self::paid_form( 25, array( array( 'id' => 'pay0', 'type' => 'payment', 'props' => array( 'amount' => 5 ) ) ) );
		$this->assertStringContainsString( 'Only one payment field', implode( ' ', self::paths( $model ) ) );
	}

	public function test_a_payment_field_needs_providers() {
		$model = self::paid_form();
		unset( $model['extensions'] );
		$this->assertArrayHasKey( '/extensions/payments', self::paths( $model ) );
	}

	public function test_amount_sources() {
		$this->assertArrayHasKey( '/pages/0/items/0/props/amount', self::paths( self::paid_form( 0 ) ) );
		$this->assertArrayHasKey( '/pages/0/items/0/props/amount', self::paths( self::paid_form( 10.005 ) ), 'more decimals than cents' );
		$this->assertArrayHasKey( '/pages/0/items/0/props/amount', self::paths( self::paid_form( '#nowhere' ) ) );
		$text = self::paid_form( '#name', array( array( 'id' => 'name', 'type' => 'text' ) ) );
		$this->assertArrayHasKey( '/pages/0/items/1/props/amount', self::paths( $text ) );
		$this->assertArrayHasKey( '/pages/0/items/0/props/amount', self::paths( self::paid_form( 'free' ) ) );
		$this->assertArrayHasKey( '/pages/0/items/0/props/amount', self::paths( self::paid_form( true ) ) );
		$this->assertSame( array(), QAF_Payment_Config::errors( self::paid_form( 2000, array(), array( 'currency' => 'jpy' ) ) ) );
	}

	public function test_donation_bounds() {
		$e = self::paths( self::paid_form( 'donation', array(), array(), array( 'donation_min' => 50, 'donation_max' => 5 ) ) );
		$this->assertArrayHasKey( '/pages/0/items/0/props/donation_min', $e );
		$e = self::paths( self::paid_form( 25, array(), array(), array( 'donation_min' => 5 ) ) );
		$this->assertStringContainsString( 'only applies to a donation', $e['/pages/0/items/0/props/donation_min'] );
		$e = self::paths( self::paid_form( 'donation', array(), array(), array( 'donation_max' => -1 ) ) );
		$this->assertArrayHasKey( '/pages/0/items/0/props/donation_max', $e );
	}

	public function test_publication_refuses_a_bad_payment_model() {
		require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-form.php';
		$model = QAF_Form_Model::create( 'Paid' );
		$model['pages'][0]['items'][] = array( 'id' => 'pay', 'type' => 'payment', 'label' => 'Pay', 'props' => array( 'amount' => '#missing' ) );
		$model['extensions'] = array( 'payments' => array( 'providers' => array( 'stripe' ), 'currency' => 'usd', 'mode' => 'payment', 'capture' => 'automatic', 'test_mode' => true ) );
		$this->assertSame( array(), QAF_Form_Model::validate( $model ), 'the base model is valid' );
		$form   = new QAF_Form();
		$result = $form->set_model( $model );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'qaf_invalid_payments', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}
}
