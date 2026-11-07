<?php
/**
 * Contract: "Out-of-order events must not downgrade a paid or refunded entry
 * to pending. Manual authorization remains authorized until capture
 * succeeds. Partial refunds must not be represented as a complete refund."
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'payments-test-helpers.php';

class QAF_Payment_States_Test extends QAF_Payment_Test_Case {

	public function test_the_contract_names_seven_states() {
		$this->assertSame( array( 'pending', 'authorized', 'paid', 'failed', 'cancelled', 'partially_refunded', 'refunded' ), QAF_Payment_States::ALL );
	}

	public function test_nothing_goes_back_to_pending_once_money_moved() {
		foreach ( array( 'authorized', 'paid', 'partially_refunded', 'refunded' ) as $from ) {
			$this->assertFalse( QAF_Payment_States::can( $from, 'pending' ), $from );
			$this->assertFalse( QAF_Payment_States::can( $from, 'failed' ), $from );
		}
	}

	public function test_a_late_success_does_not_undo_a_refund() {
		$this->assertFalse( QAF_Payment_States::can( 'refunded', 'paid' ) );
		$this->assertFalse( QAF_Payment_States::can( 'partially_refunded', 'paid' ) );
		$this->assertFalse( QAF_Payment_States::can( 'refunded', 'partially_refunded' ) );
	}

	public function test_authorized_waits_for_capture_or_void() {
		$this->assertTrue( QAF_Payment_States::can( 'authorized', 'paid' ) );
		$this->assertTrue( QAF_Payment_States::can( 'authorized', 'cancelled' ) );
		$this->assertFalse( QAF_Payment_States::can( 'authorized', 'refunded' ) );
	}

	public function test_a_failed_or_cancelled_payment_can_be_tried_again() {
		$this->assertTrue( QAF_Payment_States::can( 'failed', 'pending' ) );
		$this->assertTrue( QAF_Payment_States::can( 'cancelled', 'pending' ) );
		$this->assertTrue( QAF_Payment_States::can( 'failed', 'paid' ), 'a retry that succeeds' );
	}

	public function test_unknown_states_are_never_entered() {
		$this->assertFalse( QAF_Payment_States::can( 'pending', 'succeeded' ) );
		$this->assertFalse( QAF_Payment_States::can( 'pending', '' ) );
	}

	public function test_every_state_has_words_not_only_a_code() {
		foreach ( QAF_Payment_States::ALL as $s ) {
			$this->assertNotSame( $s, QAF_Payment_States::label( $s ) );
		}
	}
}
