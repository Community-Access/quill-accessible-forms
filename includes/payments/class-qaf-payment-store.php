<?php
/**
 * Where payment attempts and provider events are kept.
 *
 * An attempt is one entry's payment: what is owed (worked out on the server),
 * in which environment, through which provider, and its state. Two things
 * make it safe under retries and concurrency: a state change is written only
 * if the state is still the one it was read as (compare and set), and every
 * provider event ID is recorded once under a unique key, so a replayed event
 * finds itself already handled.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage for payment attempts.
 */
interface QAF_Payment_Store {

	/**
	 * Add an attempt.
	 *
	 * @param array $row Columns.
	 * @return int New ID.
	 */
	public function insert( $row );

	/**
	 * One attempt by ID.
	 *
	 * @param int $id ID.
	 * @return array|null Row.
	 */
	public function get( $id );

	/**
	 * Find one attempt by a column's value.
	 *
	 * @param string $column token_hash, submission, provider_ref, capture_ref or entry_id.
	 * @param string $value  Value.
	 * @return array|null Row, the latest when several match.
	 */
	public function find( $column, $value );

	/**
	 * Change an attempt, only if its status is still the expected one.
	 *
	 * @param int         $id       ID.
	 * @param array       $fields   Columns to change.
	 * @param string|null $expected Status it must still have, or null for any.
	 * @return bool Whether the row changed.
	 */
	public function update( $id, $fields, $expected = null );

	/**
	 * Record a provider event ID once.
	 *
	 * @param string $provider Provider.
	 * @param string $event_id Event ID.
	 * @return bool True the first time, false for a replay.
	 */
	public function record_event( $provider, $event_id );

	/**
	 * Recent attempts, newest first.
	 *
	 * @param array $args form_id, status, limit, offset.
	 * @return array Rows.
	 */
	public function recent( $args = array() );
}

/**
 * The store in the site's database.
 */
class QAF_Payment_DB_Store implements QAF_Payment_Store {

	/** Schema version of the two tables. */
	const DB_VERSION = '1';

	/** Columns find() may search. */
	const FINDABLE = array( 'token_hash', 'submission', 'provider_ref', 'capture_ref', 'entry_id' );

	/**
	 * Attempts table name.
	 *
	 * @return string Table.
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'qaf_payments';
	}

	/**
	 * Events table name.
	 *
	 * @return string Table.
	 */
	private function events() {
		global $wpdb;
		return $wpdb->prefix . 'qaf_payment_events';
	}

	/**
	 * Create or upgrade the tables. Runs on activation, and on admin_init for
	 * a site whose files were replaced without activating again.
	 */
	public static function maybe_install() {
		if ( get_option( 'qaf_payments_db_version' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$charset  = $wpdb->get_charset_collate();
		$payments = $wpdb->prefix . 'qaf_payments';
		$events   = $wpdb->prefix . 'qaf_payment_events';
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE $payments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			entry_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			submission varchar(100) NOT NULL DEFAULT '',
			provider varchar(20) NOT NULL DEFAULT '',
			environment varchar(10) NOT NULL,
			status varchar(20) NOT NULL,
			amount_minor bigint(20) NOT NULL,
			currency char(3) NOT NULL,
			capture varchar(10) NOT NULL DEFAULT 'automatic',
			provider_ref varchar(100) NOT NULL DEFAULT '',
			capture_ref varchar(100) NOT NULL DEFAULT '',
			refunded_minor bigint(20) NOT NULL DEFAULT 0,
			receipt_url varchar(500) NOT NULL DEFAULT '',
			tries int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY entry_id (entry_id),
			KEY form_status (form_id, status),
			KEY submission (submission),
			KEY provider_ref (provider_ref),
			KEY capture_ref (capture_ref)
		) $charset;" );
		dbDelta( "CREATE TABLE $events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(20) NOT NULL,
			event_id varchar(100) NOT NULL,
			received_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_event (provider, event_id)
		) $charset;" );
		update_option( 'qaf_payments_db_version', self::DB_VERSION, false );
	}

	/** {@inheritdoc} */
	public function insert( $row ) {
		global $wpdb;
		$now                = current_time( 'mysql', true );
		$row['created_at']  = $now;
		$row['updated_at']  = $now;
		$wpdb->insert( $this->table(), $row );
		return (int) $wpdb->insert_id;
	}

	/** {@inheritdoc} */
	public function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/** {@inheritdoc} */
	public function find( $column, $value ) {
		global $wpdb;
		if ( ! in_array( $column, self::FINDABLE, true ) || $value === '' || $value === null ) {
			return null;
		}
		// The column name comes from the allow-list above, never from a request.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE {$column} = %s ORDER BY id DESC LIMIT 1", (string) $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/** {@inheritdoc} */
	public function update( $id, $fields, $expected = null ) {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql', true );
		$where                = array( 'id' => (int) $id );
		if ( $expected !== null ) {
			$where['status'] = $expected;
		}
		return (int) $wpdb->update( $this->table(), $fields, $where ) > 0;
	}

	/** {@inheritdoc} */
	public function record_event( $provider, $event_id ) {
		global $wpdb;
		// INSERT IGNORE against the unique key: two deliveries racing each
		// other cannot both be the first.
		$done = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$this->events()} (provider, event_id, received_at) VALUES (%s, %s, %s)", $provider, $event_id, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $done === 1;
	}

	/** {@inheritdoc} */
	public function recent( $args = array() ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], QAF_Payment_States::ALL, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		$params[] = max( 1, min( 500, (int) ( $args['limit'] ?? 50 ) ) );
		$params[] = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$sql      = "SELECT * FROM {$this->table()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
