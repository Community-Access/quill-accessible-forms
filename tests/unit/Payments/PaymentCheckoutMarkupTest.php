<?php
/**
 * The payment field on the published page: labels, hints and errors tied to
 * the input, required state announced, amounts in words, escaping, and the
 * checkout script loaded only from the providers' own addresses.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';
require_once QAF_PLUGIN_DIR . 'admin/class-qaf-payments-admin.php';

class QAF_Payment_Checkout_Markup_Test extends QAF_Payment_Test_Case {

	public function test_a_donation_input_is_labelled_described_and_required() {
		$model = self::paid_form( 'donation', array(), array(), array( 'donation_min' => 5, 'donation_max' => 500 ) );
		$html  = QAF_Payment_Checkout::render_field( array( 'id' => 'pay', 'label' => 'Gift' ), $model );
		$this->assertStringContainsString( '<label class="qaf-label" for="qaf-pay-pay">Gift, in USD', $html );
		$this->assertStringContainsString( 'id="qaf-pay-pay" name="fields[pay]" required aria-required="true"', $html );
		$this->assertStringContainsString( 'aria-describedby="qaf-pay-pay-hint qaf-pay-pay-error"', $html );
		$this->assertStringContainsString( 'id="qaf-pay-pay-hint">Give from 5.00 USD to 500.00 USD.', $html );
		$this->assertStringContainsString( 'class="qaf-field-error" id="qaf-pay-pay-error"', $html );
		$this->assertStringContainsString( 'inputmode="decimal"', $html );
	}

	public function test_a_fixed_amount_is_said_in_words_with_its_currency() {
		$html = QAF_Payment_Checkout::render_field( array( 'id' => 'pay', 'label' => 'Fee' ), self::paid_form( 25 ) );
		$this->assertStringContainsString( 'Amount due: 25.00 USD.', $html );
		$this->assertStringContainsString( 'card (through Stripe) or PayPal', $html );
		$this->assertStringNotContainsString( '<input', $html, 'nothing for the payer to type' );
	}

	public function test_a_total_names_the_field_it_comes_from() {
		$html = QAF_Payment_Checkout::render_field( array( 'id' => 'pay', 'label' => 'Pay' ), self::shop_form() );
		$this->assertStringContainsString( 'the amount in &quot;Order total&quot;', $html );
		$this->assertStringContainsString( 'works it out again', $html );
	}

	public function test_test_mode_is_said_in_words() {
		$this->assertStringContainsString( 'Test mode: no real money is taken.', QAF_Payment_Checkout::render_field( array( 'id' => 'pay' ), self::paid_form() ) );
		$this->assertStringNotContainsString( 'Test mode', QAF_Payment_Checkout::render_field( array( 'id' => 'pay' ), self::paid_form( 25, array(), array( 'test_mode' => false ) ) ) );
	}

	public function test_labels_are_escaped() {
		$html = QAF_Payment_Checkout::render_field( array( 'id' => 'pay', 'label' => '<script>x</script>' ), self::paid_form() );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_no_meaning_by_colour_and_no_inline_styles() {
		$html = QAF_Payment_Checkout::render_field( array( 'id' => 'pay' ), self::paid_form( 'donation' ) );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_the_submission_key_is_random_and_well_formed() {
		$a = QAF_Payment_Checkout::submission_input();
		$b = QAF_Payment_Checkout::submission_input();
		$this->assertNotSame( $a, $b );
		$this->assertMatchesRegularExpression( '/^<input type="hidden" name="qaf_submission" value="[a-f0-9]{32}" \/>$/', $a );
		preg_match( '/value="([a-f0-9]+)"/', $a, $m );
		$this->assertSame( $m[1], QAF_Payment_Service::submission_key( $m[1] ) );
	}

	public function test_scripts_come_from_the_plugin_and_stripe_only() {
		$GLOBALS['qaf_test_scripts'] = array();
		QAF_Payment_Checkout::enqueue( self::paid_form() );
		$this->assertSame( 'https://js.stripe.com/v3/', $GLOBALS['qaf_test_scripts']['qaf-stripe-js'] );
		$this->assertStringContainsString( 'public/js/qaf-payments.js', $GLOBALS['qaf_test_scripts']['qaf-payments'] );
		$GLOBALS['qaf_test_scripts'] = array();
		QAF_Payment_Checkout::enqueue( self::paid_form( 25, array(), array( 'providers' => array( 'paypal' ) ) ) );
		$this->assertArrayNotHasKey( 'qaf-stripe-js', $GLOBALS['qaf_test_scripts'], 'no Stripe.js on a PayPal-only form' );
		$this->assertSame( 'https://example.test/wp-json/qaf/v1/payments/', $GLOBALS['qaf_test_localized']['qafPayments']['restUrl'] );
	}

	public function test_every_checkout_sentence_is_translatable_text() {
		foreach ( QAF_Payment_Checkout::strings() as $key => $text ) {
			$this->assertNotSame( '', $text, $key );
		}
	}

	public function test_the_report_totals_are_in_words() {
		$lines = QAF_Payments_Admin::totals( array(
			array( 'currency' => 'usd', 'status' => 'paid', 'amount_minor' => 2500, 'refunded_minor' => 0 ),
			array( 'currency' => 'usd', 'status' => 'partially_refunded', 'amount_minor' => 2500, 'refunded_minor' => 1000 ),
			array( 'currency' => 'usd', 'status' => 'authorized', 'amount_minor' => 700, 'refunded_minor' => 0 ),
			array( 'currency' => 'usd', 'status' => 'failed', 'amount_minor' => 9900, 'refunded_minor' => 0 ),
		) );
		$this->assertSame( array( '2 paid: 50.00 USD taken, 10.00 USD refunded, 40.00 USD kept; 7.00 USD authorized and not yet captured.' ), $lines );
	}
}
