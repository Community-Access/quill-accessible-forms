<?php
/**
 * Spam routes in qaf/v1.
 *
 * - GET spam/pow (public): a proof-of-work challenge for a form page, when
 *   ALTCHA is turned on. Its cost doubles with each wrong answer from the
 *   same address in the last hour.
 * - GET spam (people who can see entries): which checks are on, each
 *   provider's state, the limits, when questions are asked and this week's
 *   question numbers, for QUILL's Workbench Spam page (13a.5). No key is
 *   ever in the reply.
 *
 * Spam and Not Spam for one entry are entries/{id}/spam and
 * entries/{id}/not-spam, in QAF_REST_API.
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
		register_rest_route( self::NAMESPACE, '/spam', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'state' ),
			'permission_callback' => array( __CLASS__, 'can_view' ),
		) );
		register_rest_route( self::NAMESPACE, '/spam/pow', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'pow' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Who may read the spam state: people who can see entries.
	 *
	 * @return bool
	 */
	public static function can_view() {
		return current_user_can( 'qaf_view_entries' ) || current_user_can( 'manage_options' );
	}

	/**
	 * The spam state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function state( $request ) {
		$settings  = $this->settings();
		$store     = $this->store();
		$all       = $settings->all();
		$challenge = new QAF_Text_Challenge( $settings, $store, new QAF_Spam_Failures( $store ) );
		return array(
			'providers'  => QAF_Spam::registry( $settings, $store )->states(),
			'limits'     => array(
				'min_seconds'       => (int) $all['min_seconds'],
				'max_links'         => (int) $all['max_links'],
				'oopspam_threshold' => (int) $all['oopspam_threshold'],
				'altcha_max_number' => (int) $all['altcha_max_number'],
				'use_disallowed'    => (bool) $all['use_disallowed'],
				'use_moderation'    => (bool) $all['use_moderation'],
			),
			'questions'  => array(
				'mode'           => $all['challenge_mode'],
				'form_modes'     => (object) $all['form_modes'],
				'kinds'          => $all['challenge_kinds'],
				'site_questions' => count( $challenge->site_questions() ),
				'send_anyway_after' => QAF_Text_Challenge::SEND_ANYWAY_AFTER,
				'this_week'      => $challenge->stats(),
			),
			'oopspam_key_saved' => '' !== (string) $all['oopspam_api_key'],
		);
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
