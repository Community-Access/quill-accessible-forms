<?php
/**
 * Contract: "Use decimal arithmetic and provider currency precision rules,
 * not binary floating point, to turn major units into provider amounts."
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_Money_Test extends QAF_Payment_Test_Case {

	public function test_major_units_become_minor_units_by_their_digits() {
		$this->assertSame( 2500, QAF_Payment_Money::to_minor( 25, 'usd' ) );
		$this->assertSame( 1999, QAF_Payment_Money::to_minor( 19.99, 'usd' ) );
		$this->assertSame( 1999, QAF_Payment_Money::to_minor( '19.99', 'USD' ) );
		$this->assertSame( 30, QAF_Payment_Money::to_minor( 0.1 + 0.2, 'usd' ), '0.1 + 0.2 is 30 cents, not 30.000000000000004' );
		$this->assertSame( 115, QAF_Payment_Money::to_minor( 1.15, 'usd' ), '1.15 * 100 in floating point is 114.99999999999999' );
		$this->assertSame( 50, QAF_Payment_Money::to_minor( '.5', 'usd' ) );
	}

	public function test_currency_precision_is_the_providers() {
		$this->assertSame( 2000, QAF_Payment_Money::to_minor( 2000, 'jpy' ), 'yen have no minor unit' );
		$this->assertNull( QAF_Payment_Money::to_minor( '2000.5', 'jpy' ) );
		$this->assertSame( 1234, QAF_Payment_Money::to_minor( '1.234', 'kwd' ), 'three decimals' );
		$this->assertSame( '1.234', QAF_Payment_Money::to_major( 1234, 'kwd' ) );
		$this->assertSame( '2000', QAF_Payment_Money::to_major( 2000, 'jpy' ) );
		$this->assertSame( '0.05', QAF_Payment_Money::to_major( 5, 'usd' ) );
		$this->assertSame( '25.00 USD', QAF_Payment_Money::spoken( 2500, 'usd' ) );
	}

	public function test_more_decimals_than_the_currency_is_refused_not_changed() {
		$this->assertNull( QAF_Payment_Money::to_minor( '10.005', 'usd' ) );
		$this->assertSame( 1001, QAF_Payment_Money::to_minor( '10.005', 'usd', true ), 'only when asked, rounded half up' );
		$this->assertSame( 1000, QAF_Payment_Money::to_minor( '10.004', 'usd', true ) );
		$this->assertSame( 1000, QAF_Payment_Money::to_minor( '10.000', 'usd' ), 'trailing zeros are not extra precision' );
	}

	public function test_text_that_is_not_a_plain_number_is_refused() {
		foreach ( array( '1e3', '12,50', 'ten', '', ' ', '0x10', true, null, array(), INF, NAN ) as $bad ) {
			$this->assertNull( QAF_Payment_Money::to_minor( $bad, 'usd' ), var_export( $bad, true ) );
		}
	}

	public function test_percentages_are_integer_arithmetic_rounded_half_up() {
		$this->assertSame( 350, QAF_Payment_Money::percent_of( 3500, 10 ) );
		$this->assertSame( 438, QAF_Payment_Money::percent_of( 3500, 12.5 ) );
		$this->assertSame( 1, QAF_Payment_Money::percent_of( 5, 10 ), '0.5 rounds up' );
	}

	public function test_each_provider_has_its_own_currency_list() {
		$this->assertTrue( QAF_Payment_Money::is_supported( 'stripe', 'INR' ) );
		$this->assertFalse( QAF_Payment_Money::is_supported( 'paypal', 'inr' ) );
		$this->assertTrue( QAF_Payment_Money::is_supported( 'paypal', 'usd' ) );
		$this->assertFalse( QAF_Payment_Money::is_supported( 'square', 'usd' ) );
	}
}
