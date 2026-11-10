<?php
/**
 * Short-lived server-side records for the spam checks: questions and their
 * answers (13a.6), used proof-of-work solutions, and failure counts. Kept
 * in transients by default, so nothing here needs a table, and every record
 * carries its own expiry.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Spam_Store interface.
 */
interface QAF_Spam_Store {

	/**
	 * @param string $key Key.
	 * @return mixed|null The value, or null when there is none or it expired.
	 */
	public function get( $key );

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Seconds to keep it.
	 */
	public function set( $key, $value, $ttl );

	/**
	 * @param string $key Key.
	 */
	public function delete( $key );
}

/**
 * QAF_Spam_Transient_Store class: the store a live site uses.
 */
class QAF_Spam_Transient_Store implements QAF_Spam_Store {

	private static function name( $key ) {
		return 'qaf_sp_' . md5( (string) $key );
	}

	public function get( $key ) {
		$v = get_transient( self::name( $key ) );
		if ( ! is_array( $v ) || ! isset( $v['expires'] ) || $v['expires'] < time() ) {
			return null;
		}
		return $v['value'];
	}

	public function set( $key, $value, $ttl ) {
		$ttl = max( 1, (int) $ttl );
		set_transient( self::name( $key ), array( 'value' => $value, 'expires' => time() + $ttl ), $ttl );
	}

	public function delete( $key ) {
		delete_transient( self::name( $key ) );
	}
}

/**
 * QAF_Spam_Failures class: wrong answers from one address in the last hour.
 * Each one makes the next proof of work longer and the rate limit tighter
 * (13a.6), while a person sees the same gentle question.
 */
class QAF_Spam_Failures {

	const TTL = 3600;

	/** @var QAF_Spam_Store */
	private $store;

	public function __construct( QAF_Spam_Store $store ) {
		$this->store = $store;
	}

	private static function key( $ip ) {
		return 'fails:' . md5( (string) $ip );
	}

	/**
	 * @param string $ip Address.
	 * @return int
	 */
	public function count( $ip ) {
		return (int) $this->store->get( self::key( $ip ) );
	}

	/**
	 * @param string $ip Address.
	 * @return int The new count.
	 */
	public function add( $ip ) {
		$n = $this->count( $ip ) + 1;
		$this->store->set( self::key( $ip ), $n, self::TTL );
		return $n;
	}
}
