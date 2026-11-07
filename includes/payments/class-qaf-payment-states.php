<?php
/**
 * The payment states of an entry, and which changes between them are allowed.
 *
 * Provider events arrive late, twice, and out of order. The table below is
 * what keeps a late "payment failed" from undoing a payment that succeeded,
 * and a late "succeeded" from undoing a refund: a change not listed is
 * ignored, not applied.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_States class.
 */
class QAF_Payment_States {

	const PENDING            = 'pending';
	const AUTHORIZED         = 'authorized';
	const PAID               = 'paid';
	const FAILED             = 'failed';
	const CANCELLED          = 'cancelled';
	const PARTIALLY_REFUNDED = 'partially_refunded';
	const REFUNDED           = 'refunded';

	/** Every state the contract names. */
	const ALL = array( 'pending', 'authorized', 'paid', 'failed', 'cancelled', 'partially_refunded', 'refunded' );

	/**
	 * From each state, the states it may move to.
	 *
	 * Failed and cancelled may go back to pending, because the visitor can
	 * try again; nothing goes back to pending from authorized, paid or a refund.
	 */
	const ALLOWED = array(
		'pending'            => array( 'authorized', 'paid', 'failed', 'cancelled' ),
		'failed'             => array( 'pending', 'authorized', 'paid', 'cancelled' ),
		'cancelled'          => array( 'pending', 'authorized', 'paid' ),
		'authorized'         => array( 'paid', 'cancelled' ),
		'paid'               => array( 'partially_refunded', 'refunded' ),
		'partially_refunded' => array( 'partially_refunded', 'refunded' ),
		'refunded'           => array(),
	);

	/**
	 * Whether a change is allowed. Staying put is always allowed (and is a no-op).
	 *
	 * @param string $from Current state.
	 * @param string $to   New state.
	 * @return bool Allowed.
	 */
	public static function can( $from, $to ) {
		if ( ! in_array( $to, self::ALL, true ) ) {
			return false;
		}
		if ( $from === $to ) {
			return true;
		}
		return in_array( $to, self::ALLOWED[ $from ] ?? array(), true );
	}

	/**
	 * What a person reads for a state.
	 *
	 * @param string $state State.
	 * @return string Label.
	 */
	public static function label( $state ) {
		$labels = array(
			'pending'            => __( 'Waiting for payment', 'quill-accessible-forms' ),
			'authorized'         => __( 'Authorized, not yet captured', 'quill-accessible-forms' ),
			'paid'               => __( 'Paid', 'quill-accessible-forms' ),
			'failed'             => __( 'Payment failed', 'quill-accessible-forms' ),
			'cancelled'          => __( 'Cancelled', 'quill-accessible-forms' ),
			'partially_refunded' => __( 'Partly refunded', 'quill-accessible-forms' ),
			'refunded'           => __( 'Refunded', 'quill-accessible-forms' ),
		);
		return $labels[ $state ] ?? $state;
	}
}
