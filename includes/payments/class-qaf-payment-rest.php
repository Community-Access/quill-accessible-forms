<?php
/**
 * The payment routes under qaf/v1.
 *
 * - GET  payments/settings: readiness for QUILL (contract version 2). Site
 *   administrators, or anyone given qaf_manage_payments, only.
 * - POST payments/pay: a Square payment, from the card token Square's card
 *   field made. Token-gated like start.
 * - POST payments/refund: a Square refund, through the connection that took
 *   the payment. Payment managers only.
 * - POST payments/start, payments/finish, payments/status: the checkout on a
 *   published form. Open to visitors, but each needs the one-time token the
 *   site gave that visitor's own submission; without it they do nothing.
 * - POST payments/webhook/{connection}: provider events, one URL per
 *   connection (`stripe`, `stripe~events`). Open, because providers do not
 *   sign in; every delivery is verified with that connection's signature
 *   before anything is read from it.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_REST class.
 */
class QAF_Payment_REST {

	const NAMESPACE = 'qaf/v1';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/payments/settings', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'settings' ),
			'permission_callback' => array( __CLASS__, 'can_manage' ),
		) );
		$token = array(
			'token' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
		register_rest_route( self::NAMESPACE, '/payments/start', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start' ),
			'permission_callback' => '__return_true',
			'args'                => $token + array(
				'provider' => array(
					'required' => true,
					'type'     => 'string',
					'pattern'  => '^(stripe|paypal|square)(:[a-z0-9][a-z0-9-]{0,39})?$',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/payments/finish', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'finish' ),
			'permission_callback' => '__return_true',
			'args'                => $token,
		) );
		register_rest_route( self::NAMESPACE, '/payments/status', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'status' ),
			'permission_callback' => '__return_true',
			'args'                => $token,
		) );
		register_rest_route( self::NAMESPACE, '/payments/pay', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'pay' ),
			'permission_callback' => '__return_true',
			'args'                => $token + array(
				'source'       => array(
					'required' => true,
					'type'     => 'string',
				),
				'verification' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/payments/refund', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'refund' ),
			'permission_callback' => array( __CLASS__, 'can_manage' ),
			'args'                => array(
				'entry_id' => array(
					'required' => true,
					'type'     => 'integer',
				),
				'amount'   => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );
		register_rest_route( self::NAMESPACE, '/payments/webhook/(?P<provider>(?:stripe|paypal|square)(?:~[a-z0-9][a-z0-9-]{0,39})?)', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Whether the current user may see payment readiness and settings.
	 *
	 * @return bool Allowed.
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'qaf_manage_payments' );
	}

	/**
	 * GET payments/settings.
	 *
	 * @return array Readiness.
	 */
	public function settings() {
		return rest_ensure_response( QAF_Payment_Settings::readiness() );
	}

	/**
	 * The attempt a request's token opens, or the error to send.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Attempt.
	 */
	private function attempt( $request ) {
		$attempt = QAF_Payment_Service::instance()->by_token( (string) $request->get_param( 'token' ) );
		if ( ! $attempt ) {
			return new WP_Error( 'qaf_payment_token', __( 'This payment link is no longer valid. Please send the form again.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
		}
		return $attempt;
	}

	/**
	 * POST payments/start.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Reply.
	 */
	public function start( $request ) {
		$attempt = $this->attempt( $request );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		return rest_ensure_response( QAF_Payment_Service::instance()->start( $attempt, (string) $request->get_param( 'provider' ) ) );
	}

	/**
	 * POST payments/pay (Square, with the card field's token).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Reply.
	 */
	public function pay( $request ) {
		$attempt = $this->attempt( $request );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		return rest_ensure_response( QAF_Payment_Service::instance()->pay( $attempt, (string) $request->get_param( 'source' ), (string) $request->get_param( 'verification' ) ) );
	}

	/**
	 * POST payments/refund: amount in major units, as text ("12.50").
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Reply.
	 */
	public function refund( $request ) {
		$service = QAF_Payment_Service::instance();
		$attempt = $service->store()->find( 'entry_id', (string) (int) $request->get_param( 'entry_id' ) );
		if ( ! $attempt ) {
			return new WP_Error( 'qaf_payment_refund', __( 'That entry has no payment.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
		}
		$minor = QAF_Payment_Money::to_minor( (string) $request->get_param( 'amount' ), $attempt['currency'] );
		if ( $minor === null || $minor <= 0 ) {
			return new WP_Error( 'qaf_payment_refund', __( 'Give the amount to refund, such as 12.50.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( $service->refund( $attempt, $minor ) );
	}

	/**
	 * POST payments/finish (PayPal, after approval).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Reply.
	 */
	public function finish( $request ) {
		$attempt = $this->attempt( $request );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		return rest_ensure_response( QAF_Payment_Service::instance()->finish( $attempt ) );
	}

	/**
	 * POST payments/status: the site asks the provider, then answers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Reply.
	 */
	public function status( $request ) {
		$attempt = $this->attempt( $request );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		return rest_ensure_response( QAF_Payment_Service::instance()->refresh( $attempt ) );
	}

	/**
	 * Headers with lower-case, dashed names and single values.
	 *
	 * @param array $headers WordPress's header map.
	 * @return array Headers.
	 */
	public static function flat_headers( $headers ) {
		$out = array();
		foreach ( (array) $headers as $name => $value ) {
			$out[ str_replace( '_', '-', strtolower( (string) $name ) ) ] = is_array( $value ) ? (string) reset( $value ) : (string) $value;
		}
		return $out;
	}

	/**
	 * POST payments/webhook/{provider}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Reply.
	 */
	public function webhook( $request ) {
		$result = QAF_Payment_Service::instance()->webhook(
			(string) $request->get_param( 'provider' ),
			(string) $request->get_body(),
			self::flat_headers( $request->get_headers() )
		);
		return new WP_REST_Response( $result['body'], $result['code'] );
	}
}
