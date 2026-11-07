<?php
/**
 * The one place the payments code reaches Stripe or PayPal.
 *
 * Every provider call goes through request(), which uses the WordPress HTTP
 * API with certificate checks on, and returns the status and decoded JSON.
 * Tests hand the gateways a fake transport instead, so no test reaches a
 * provider. Nothing here logs a request or a reply: both can carry keys.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Payment_HTTP class.
 */
class QAF_Payment_HTTP {

	/**
	 * A replacement transport, for tests: function( $method, $url, $args ) returning
	 * array( 'status' => int, 'body' => string ).
	 *
	 * @var callable|null
	 */
	private $transport;

	/**
	 * Constructor.
	 *
	 * @param callable|null $transport Replacement transport.
	 */
	public function __construct( $transport = null ) {
		$this->transport = $transport;
	}

	/**
	 * Send a request.
	 *
	 * @param string $method  GET or POST.
	 * @param string $url     HTTPS URL.
	 * @param array  $headers Headers.
	 * @param mixed  $body    Body: an array for a form-encoded body, or a JSON string.
	 * @return array array( 'status' => int, 'json' => array|null, 'error' => string ).
	 */
	public function request( $method, $url, $headers = array(), $body = null ) {
		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => 20,
			'redirection' => 0,
			'sslverify'   => true,
		);
		if ( $body !== null ) {
			$args['body'] = $body;
		}
		if ( $this->transport ) {
			$raw = call_user_func( $this->transport, $method, $url, $args );
			return self::decode( (int) ( $raw['status'] ?? 0 ), (string) ( $raw['body'] ?? '' ), (string) ( $raw['error'] ?? '' ) );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'json'   => null,
				'error'  => __( 'The payment provider could not be reached.', 'quill-accessible-forms' ),
			);
		}
		return self::decode( (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ), '' );
	}

	/**
	 * Decode a reply.
	 *
	 * @param int    $status Status.
	 * @param string $body   Body.
	 * @param string $error  Transport error.
	 * @return array Reply.
	 */
	private static function decode( $status, $body, $error ) {
		$json = json_decode( $body, true );
		return array(
			'status' => $status,
			'json'   => is_array( $json ) ? $json : null,
			'error'  => $error,
		);
	}
}
