<?php
/**
 * What the spam tests need from WordPress when the WordPress test suite is
 * not installed, plus a memory store and a rate limiter that counts in
 * memory. Nothing here reaches a network.
 *
 * @package QAF
 */

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return $nonce === 'good-' . $action ? 1 : false;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return 'en_US';
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $what = '' ) {
		return 'charset' === $what ? 'UTF-8' : 'Example';
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		throw new RuntimeException( 'A test tried to reach the network: ' . $url );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $r ) {
		return is_array( $r ) && isset( $r['body'] ) ? $r['body'] : '';
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $r ) {
		return is_array( $r ) && isset( $r['response']['code'] ) ? $r['response']['code'] : 0;
	}
}

/**
 * Short-lived records in memory, with a clock a test can move.
 */
class QAF_Spam_Memory_Store implements QAF_Spam_Store {

	/** @var array */
	public $rows = array();

	/** @var int Seconds added to time(), so a test can let a question expire. */
	public $offset = 0;

	public function now() {
		return time() + $this->offset;
	}

	public function get( $key ) {
		if ( ! isset( $this->rows[ $key ] ) || $this->rows[ $key ]['expires'] < $this->now() ) {
			return null;
		}
		return $this->rows[ $key ]['value'];
	}

	public function set( $key, $value, $ttl ) {
		$this->rows[ $key ] = array( 'value' => $value, 'expires' => $this->now() + max( 1, (int) $ttl ) );
	}

	public function delete( $key ) {
		unset( $this->rows[ $key ] );
	}
}

/**
 * The rate limiter with its counting in memory instead of in a table.
 */
class QAF_Memory_Rate_Limiter extends QAF_Rate_Limiter {

	/** @var array Each send: ip, form_id, time. */
	public $events = array();

	/** @var string[] Addresses on the site's allow list. */
	public $allowed = array();

	public function __construct() {
	}

	protected function count_since( $ip, $form_id, $since ) {
		return count( array_filter( $this->events, function ( $e ) use ( $ip, $form_id, $since ) {
			return $e['ip'] === $ip && ( ! $form_id || $e['form_id'] === $form_id ) && $e['time'] > $since;
		} ) );
	}

	protected function record_event( $ip, $form_id ) {
		$this->events[] = array( 'ip' => $ip, 'form_id' => (int) $form_id, 'time' => time() );
	}

	public function is_whitelisted( $ip ) {
		return in_array( $ip, $this->allowed, true );
	}
}

/**
 * A CAPTCHA that answers whatever the test says.
 */
class QAF_Fake_Captcha {
	public $answer;

	public function __construct( $answer ) {
		$this->answer = $answer;
	}

	public function assess( $request ) {
		return $this->answer;
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'POST, PUT, PATCH';
		const DELETABLE = 'DELETE';
	}
}
