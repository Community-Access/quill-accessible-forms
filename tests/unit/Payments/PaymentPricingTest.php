<?php
/**
 * Contract: "Evaluate products, quantities, totals, coupons and references on
 * the server; never trust a submitted price, currency, total or
 * successful-payment flag." And: "A disabled or hidden checkout cannot bypass
 * a required payment."
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Pricing_Test extends QAF_Payment_Test_Case {

	private static function owe( $model, $values ) {
		return QAF_Payment_Pricing::evaluate( $model, $values, array( 'today' => '2026-11-07' ) );
	}

	public function test_a_fixed_amount_comes_from_the_model_whatever_is_sent() {
		$plan = self::owe( self::paid_form( 25 ), array( 'pay' => '0.01', 'amount' => '0.01', 'currency' => 'jpy', 'paid' => true ) );
		$this->assertTrue( $plan['due'] );
		$this->assertSame( 2500, $plan['minor'] );
		$this->assertSame( 'usd', $plan['currency'] );
	}

	public function test_a_donation_is_read_exactly_and_kept_within_its_bounds() {
		$model = self::paid_form( 'donation', array(), array(), array( 'donation_min' => 5, 'donation_max' => 500 ) );
		$this->assertSame( 2050, self::owe( $model, array( 'pay' => '20.50' ) )['minor'] );
		$this->assertSame( 2000, self::owe( $model, array( 'pay' => '$20' ) )['minor'] );
		foreach ( array( '4.99' => 'smallest', '500.01' => 'largest', '20.505' => 'greater than zero', 'lots' => 'greater than zero', '' => 'greater than zero', '-10' => 'greater than zero' ) as $given => $says ) {
			$e = self::owe( $model, array( 'pay' => $given ) );
			$this->assertInstanceOf( 'WP_Error', $e, (string) $given );
			$this->assertStringContainsString( $says, $e->get_error_data()['errors']['pay'], (string) $given );
			$this->assertSame( 400, $e->get_error_data()['status'] );
		}
	}

	public function test_a_total_is_products_times_quantities_less_coupons() {
		$model = self::shop_form();
		$this->assertSame( 3500, self::owe( $model, array( 'shirt' => 'l', 'qty' => '2' ) )['minor'] );
		$this->assertSame( 3150, self::owe( $model, array( 'shirt' => 'l', 'qty' => '2', 'code' => 'ten' ) )['minor'], 'codes ignore case unless the field says otherwise' );
		$this->assertSame( 3000, self::owe( $model, array( 'shirt' => 'l', 'qty' => '2', 'code' => 'FIVE' ) )['minor'] );
	}

	public function test_a_submitted_total_or_price_is_ignored() {
		$plan = self::owe( self::shop_form(), array( 'shirt' => 's', 'qty' => '1', 'total' => '0.50', 'price' => '0.01' ) );
		$this->assertSame( 1500, $plan['minor'] );
		$this->assertSame( 15, $plan['values']['total'], 'the entry keeps the site\'s total' );
	}

	public function test_shipping_and_tax_on_the_total_field() {
		$model = self::shop_form( array( 'shipping_amount' => 4, 'free_shipping_min' => 50, 'tax_rate' => 10 ) );
		$this->assertSame( 1500 + 400 + 150, self::owe( $model, array( 'shirt' => 's', 'qty' => '1' ) )['minor'] );
		$this->assertSame( 5250 + 525, self::owe( $model, array( 'shirt' => 'l', 'qty' => '3' ) )['minor'], 'free shipping from 50' );
	}

	public function test_tampered_choices_quantities_and_codes_are_errors_on_their_fields() {
		$model  = self::shop_form();
		$errors = self::owe( $model, array( 'shirt' => 'xxl', 'qty' => '1' ) )->get_error_data()['errors'];
		$this->assertArrayHasKey( 'shirt', $errors );
		$this->assertArrayHasKey( 'qty', self::owe( $model, array( 'shirt' => 's', 'qty' => '1.5' ) )->get_error_data()['errors'] );
		$this->assertArrayHasKey( 'qty', self::owe( $model, array( 'shirt' => 's', 'qty' => '11' ) )->get_error_data()['errors'] );
		$this->assertStringContainsString( 'not one this form takes', self::owe( $model, array( 'shirt' => 's', 'qty' => '1', 'code' => 'FREE' ) )->get_error_data()['errors']['code'] );
		$this->assertStringContainsString( 'expired', self::owe( $model, array( 'shirt' => 's', 'qty' => '1', 'code' => 'OLD' ) )->get_error_data()['errors']['code'] );
	}

	public function test_a_calculated_total_uses_the_servers_values_all_the_way_down() {
		// As in the conformance fixture: total = subtotal - coupon; subtotal = quantity * price.
		$model = self::paid_form( '#f_total', array(
			array( 'id' => 'f_quantity', 'type' => 'quantity', 'label' => 'How many' ),
			array( 'id' => 'f_price', 'type' => 'price', 'label' => 'Price each', 'props' => array( 'base_price' => 15 ) ),
			array( 'id' => 'f_calculation', 'type' => 'calculation', 'label' => 'Subtotal', 'calculation' => '#f_quantity * #f_price' ),
			array( 'id' => 'f_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'props' => array( 'coupons' => array( array( 'code' => 'FIVE', 'discount_type' => 'fixed', 'discount_value' => 5 ) ) ) ),
			array( 'id' => 'f_total', 'type' => 'total', 'label' => 'Total', 'calculation' => '#f_calculation - #f_coupon' ),
		) );
		$plan = self::owe( $model, array( 'f_quantity' => '3', 'f_price' => '1', 'f_calculation' => '1', 'f_total' => '1', 'f_coupon' => 'FIVE' ) );
		$this->assertSame( 4000, $plan['minor'], '3 x 15 - 5, whatever the page said' );
	}

	public function test_a_number_field_amount_is_the_payers_own_figure() {
		$model = self::paid_form( '#gift', array( array( 'id' => 'gift', 'type' => 'number', 'label' => 'Gift' ) ) );
		$this->assertSame( 1234, self::owe( $model, array( 'gift' => '12.34' ) )['minor'] );
		$this->assertArrayHasKey( 'gift', self::owe( $model, array( 'gift' => '12.345' ) )->get_error_data()['errors'] );
	}

	public function test_a_hidden_payment_field_owes_nothing_and_a_shown_one_cannot_be_skipped() {
		$model = self::paid_form( 25, array( array( 'id' => 'member', 'type' => 'radio', 'choices' => array( array( 'value' => 'yes' ), array( 'value' => 'no' ) ) ) ) );
		$model['pages'][0]['items'][1]['when'] = '#member is "no"';
		$this->assertFalse( self::owe( $model, array( 'member' => 'yes' ) )['due'], 'members do not pay' );
		$this->assertTrue( self::owe( $model, array( 'member' => 'no', 'pay' => 'skip' ) )['due'], 'the page cannot switch the payment off' );
	}

	public function test_a_zero_total_owes_nothing() {
		$plan = self::owe( self::shop_form(), array( 'shirt' => '', 'qty' => '1' ) );
		$this->assertFalse( $plan['due'] );
		$this->assertSame( 0, $plan['minor'] );
	}
}
