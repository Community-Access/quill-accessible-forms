<?php
/**
 * Spam routes in qaf/v1.
 *
 * - GET spam/pow (public): a proof-of-work challenge for a form page, when
 *   ALTCHA is turned on. Its cost doubles with each wrong answer from the
 *   same address in the last hour.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_REST class.
 */
class QAF_Spam_REST {

	const NAMESPACE = 'qaf/v1';

	/** @var QAF_Spam_Settings|null */
	private $settings;

	/** @var QAF_Spam_Store|null */
	private $store;

	/**
	 * @param QAF_Spam_Settings|null $settings Settings (tests).
	 * @param QAF_Spam_Store|null    $store    Store (tests).
	 */
	public function __construct( $settings = null, $store = null ) {
		$this->settings = $settings;
		$this->store    = $store;
	}

	private function settings() {
		return $this->settings ?: new QAF_Spam_Settings();
	}

	private function store() {
		return $this->store ?: new QAF_Spam_Transient_Store();
	}

	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/spam/pow', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'pow' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * A proof-of-work challenge.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public function pow( $request ) {
		$settings = $this->settings();
		if ( ! $settings->enabled( 'altcha' ) ) {
			return new WP_Error( 'qaf_pow_off', __( 'Proof of work is not turned on for this site.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
		}
		$store = $this->store();
		$check = new QAF_Spam_Altcha_Check( $settings, $store, new QAF_Spam_Failures( $store ) );
		return $check->challenge_for( self::client_ip() );
	}

	/**
	 * The visitor's address, as the submit route reads it.
	 *
	 * @return string
	 */
	public static function client_ip() {
		if ( apply_filters( 'qaf_trust_proxy_headers', false ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}
}
