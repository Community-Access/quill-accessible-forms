<?php
/**
 * Entry Locking Feature
 *
 * Prevents concurrent editing of entries by multiple users,
 * similar to WordPress post locking.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Entry;

/**
 * Class Entry_Locking
 *
 * Manages entry edit locks to prevent conflicts.
 */
class Entry_Locking {

    /**
     * Lock duration in seconds.
     */
    private const LOCK_DURATION = 150; // 2.5 minutes

    /**
     * Heartbeat interval in seconds.
     */
    private const HEARTBEAT_INTERVAL = 60;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Check lock when loading entry for edit
        add_filter( 'baf_entry_can_edit', [ $this, 'check_lock' ], 10, 2 );

        // Acquire lock when starting edit
        add_action( 'baf_entry_edit_start', [ $this, 'acquire_lock' ], 10, 2 );

        // Release lock when done
        add_action( 'baf_entry_edit_end', [ $this, 'release_lock' ], 10, 1 );
        add_action( 'baf_entry_updated', [ $this, 'release_lock' ], 10, 1 );

        // Heartbeat for maintaining lock
        add_filter( 'heartbeat_received', [ $this, 'heartbeat_received' ], 10, 2 );
        add_filter( 'heartbeat_send', [ $this, 'heartbeat_send' ], 10, 2 );

        // AJAX handlers
        add_action( 'wp_ajax_baf_check_entry_lock', [ $this, 'ajax_check_lock' ] );
        add_action( 'wp_ajax_baf_acquire_entry_lock', [ $this, 'ajax_acquire_lock' ] );
        add_action( 'wp_ajax_baf_release_entry_lock', [ $this, 'ajax_release_lock' ] );
        add_action( 'wp_ajax_baf_takeover_entry', [ $this, 'ajax_takeover' ] );

        // Clean up expired locks
        add_action( 'baf_hourly_tasks', [ $this, 'cleanup_expired_locks' ] );

        // Enqueue scripts for admin
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Check if entry is locked.
     *
     * @param bool  $can_edit Current edit permission.
     * @param Entry $entry    Entry object.
     * @return bool|array True if can edit, or array with lock info.
     */
    public function check_lock( bool $can_edit, Entry $entry ): bool|array {
        if ( ! $can_edit ) {
            return false;
        }

        $lock = $this->get_lock( $entry->get_id() );

        if ( ! $lock ) {
            return true;
        }

        // Check if lock is expired
        if ( $this->is_lock_expired( $lock ) ) {
            $this->delete_lock( $entry->get_id() );
            return true;
        }

        // Check if current user owns the lock
        $current_user = get_current_user_id();
        
        if ( (int) $lock->user_id === $current_user ) {
            return true;
        }

        // Entry is locked by someone else
        $locker      = get_userdata( $lock->user_id );
        $locker_name = $locker ? $locker->display_name : __( 'Another user', 'jexi-forms' );

        return [
            'locked'      => true,
            'user_id'     => $lock->user_id,
            'user_name'   => $locker_name,
            'user_avatar' => get_avatar_url( $lock->user_id, [ 'size' => 64 ] ),
            'locked_at'   => $lock->locked_at,
            'message'     => sprintf(
                /* translators: %s: user name */
                __( '%s is currently editing this entry.', 'jexi-forms' ),
                $locker_name
            ),
        ];
    }

    /**
     * Acquire lock for entry.
     *
     * @param Entry $entry   Entry object.
     * @param int   $user_id User ID.
     * @return bool Success.
     */
    public function acquire_lock( Entry $entry, int $user_id = 0 ): bool {
        global $wpdb;

        $user_id  = $user_id ?: get_current_user_id();
        $entry_id = $entry->get_id();

        if ( ! $user_id ) {
            return false;
        }

        // Check for existing lock
        $existing = $this->get_lock( $entry_id );

        if ( $existing && ! $this->is_lock_expired( $existing ) && (int) $existing->user_id !== $user_id ) {
            return false; // Locked by someone else
        }

        $table = $wpdb->prefix . 'baf_entry_locks';

        // Delete any existing lock for this entry
        $wpdb->delete( $table, [ 'entry_id' => $entry_id ], [ '%d' ] );

        // Insert new lock
        $wpdb->insert(
            $table,
            [
                'entry_id'  => $entry_id,
                'user_id'   => $user_id,
                'locked_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s' ]
        );

        /**
         * Fires when entry lock is acquired.
         *
         * @param int   $entry_id Entry ID.
         * @param int   $user_id  User ID.
         * @param Entry $entry    Entry object.
         */
        do_action( 'baf_entry_lock_acquired', $entry_id, $user_id, $entry );

        return true;
    }

    /**
     * Release lock for entry.
     *
     * @param Entry|int $entry Entry object or ID.
     * @return bool Success.
     */
    public function release_lock( $entry ): bool {
        global $wpdb;

        $entry_id = $entry instanceof Entry ? $entry->get_id() : (int) $entry;
        $user_id  = get_current_user_id();
        $table    = $wpdb->prefix . 'baf_entry_locks';

        // Only release own lock (or admin can release any)
        $where = [ 'entry_id' => $entry_id ];
        
        if ( ! current_user_can( 'manage_options' ) ) {
            $where['user_id'] = $user_id;
        }

        $result = $wpdb->delete( $table, $where );

        if ( $result ) {
            /**
             * Fires when entry lock is released.
             *
             * @param int $entry_id Entry ID.
             * @param int $user_id  User ID who released.
             */
            do_action( 'baf_entry_lock_released', $entry_id, $user_id );
        }

        return $result !== false;
    }

    /**
     * Refresh lock timestamp.
     *
     * @param int $entry_id Entry ID.
     * @return bool Success.
     */
    public function refresh_lock( int $entry_id ): bool {
        global $wpdb;

        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'baf_entry_locks';

        $result = $wpdb->update(
            $table,
            [ 'locked_at' => current_time( 'mysql' ) ],
            [
                'entry_id' => $entry_id,
                'user_id'  => $user_id,
            ],
            [ '%s' ],
            [ '%d', '%d' ]
        );

        return $result !== false;
    }

    /**
     * Takeover entry lock (admin function).
     *
     * @param int $entry_id Entry ID.
     * @return bool Success.
     */
    public function takeover_lock( int $entry_id ): bool {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'baf_takeover_entries' ) ) {
            return false;
        }

        global $wpdb;

        $table      = $wpdb->prefix . 'baf_entry_locks';
        $user_id    = get_current_user_id();
        $old_lock   = $this->get_lock( $entry_id );
        $old_locker = $old_lock ? (int) $old_lock->user_id : 0;

        // Delete existing lock
        $wpdb->delete( $table, [ 'entry_id' => $entry_id ], [ '%d' ] );

        // Create new lock
        $wpdb->insert(
            $table,
            [
                'entry_id'  => $entry_id,
                'user_id'   => $user_id,
                'locked_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s' ]
        );

        /**
         * Fires when entry lock is taken over.
         *
         * @param int $entry_id   Entry ID.
         * @param int $new_user   New lock holder.
         * @param int $old_locker Previous lock holder.
         */
        do_action( 'baf_entry_lock_takeover', $entry_id, $user_id, $old_locker );

        // Optionally notify the previous locker
        if ( $old_locker && $old_locker !== $user_id ) {
            $this->notify_takeover( $entry_id, $old_locker, $user_id );
        }

        return true;
    }

    /**
     * Get lock for entry.
     *
     * @param int $entry_id Entry ID.
     * @return object|null Lock object or null.
     */
    public function get_lock( int $entry_id ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_entry_locks';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entry_id = %d",
                $entry_id
            )
        );
    }

    /**
     * Check if lock is expired.
     *
     * @param object $lock Lock object.
     * @return bool True if expired.
     */
    private function is_lock_expired( object $lock ): bool {
        $lock_time = strtotime( $lock->locked_at );
        $now       = current_time( 'timestamp' );
        $duration  = apply_filters( 'baf_entry_lock_duration', self::LOCK_DURATION );

        return ( $now - $lock_time ) > $duration;
    }

    /**
     * Delete lock.
     *
     * @param int $entry_id Entry ID.
     * @return void
     */
    private function delete_lock( int $entry_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_entry_locks';
        $wpdb->delete( $table, [ 'entry_id' => $entry_id ], [ '%d' ] );
    }

    /**
     * Handle heartbeat received.
     *
     * @param array $response Response data.
     * @param array $data     Request data.
     * @return array Modified response.
     */
    public function heartbeat_received( array $response, array $data ): array {
        if ( empty( $data['baf_entry_lock'] ) ) {
            return $response;
        }

        $entry_id = (int) $data['baf_entry_lock'];

        // Refresh the lock
        if ( $this->refresh_lock( $entry_id ) ) {
            $response['baf_entry_lock_refreshed'] = true;
        }

        // Check if still have lock
        $lock = $this->get_lock( $entry_id );
        
        if ( ! $lock || (int) $lock->user_id !== get_current_user_id() ) {
            $response['baf_entry_lock_lost'] = true;
        }

        return $response;
    }

    /**
     * Handle heartbeat send.
     *
     * @param array $response Response data.
     * @param array $data     Request data.
     * @return array Modified response.
     */
    public function heartbeat_send( array $response, array $data ): array {
        // Could add lock status info here if needed
        return $response;
    }

    /**
     * AJAX: Check entry lock.
     *
     * @return void
     */
    public function ajax_check_lock(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        $entry_id = (int) ( $_POST['entry_id'] ?? 0 );
        
        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry.', 'jexi-forms' ) ] );
        }

        $entry = Entry::get( $entry_id );
        
        if ( ! $entry ) {
            wp_send_json_error( [ 'message' => __( 'Entry not found.', 'jexi-forms' ) ] );
        }

        $result = $this->check_lock( true, $entry );

        if ( $result === true ) {
            wp_send_json_success( [ 'locked' => false ] );
        } elseif ( is_array( $result ) ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( [ 'message' => __( 'Cannot edit this entry.', 'jexi-forms' ) ] );
        }
    }

    /**
     * AJAX: Acquire entry lock.
     *
     * @return void
     */
    public function ajax_acquire_lock(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        $entry_id = (int) ( $_POST['entry_id'] ?? 0 );
        
        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry.', 'jexi-forms' ) ] );
        }

        $entry = Entry::get( $entry_id );
        
        if ( ! $entry ) {
            wp_send_json_error( [ 'message' => __( 'Entry not found.', 'jexi-forms' ) ] );
        }

        if ( $this->acquire_lock( $entry ) ) {
            wp_send_json_success( [ 
                'locked'   => true,
                'duration' => self::LOCK_DURATION,
            ] );
        }

        wp_send_json_error( [ 'message' => __( 'Could not acquire lock.', 'jexi-forms' ) ] );
    }

    /**
     * AJAX: Release entry lock.
     *
     * @return void
     */
    public function ajax_release_lock(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        $entry_id = (int) ( $_POST['entry_id'] ?? 0 );
        
        if ( $this->release_lock( $entry_id ) ) {
            wp_send_json_success();
        }

        wp_send_json_error( [ 'message' => __( 'Could not release lock.', 'jexi-forms' ) ] );
    }

    /**
     * AJAX: Takeover entry.
     *
     * @return void
     */
    public function ajax_takeover(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        $entry_id = (int) ( $_POST['entry_id'] ?? 0 );
        
        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry.', 'jexi-forms' ) ] );
        }

        if ( $this->takeover_lock( $entry_id ) ) {
            wp_send_json_success( [ 'message' => __( 'Lock acquired.', 'jexi-forms' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Could not take over entry.', 'jexi-forms' ) ] );
    }

    /**
     * Notify user that their lock was taken over.
     *
     * @param int $entry_id   Entry ID.
     * @param int $old_locker Previous lock holder.
     * @param int $new_locker New lock holder.
     * @return void
     */
    private function notify_takeover( int $entry_id, int $old_locker, int $new_locker ): void {
        $old_user = get_userdata( $old_locker );
        $new_user = get_userdata( $new_locker );

        if ( ! $old_user || ! $new_user ) {
            return;
        }

        $entry   = Entry::get( $entry_id );
        $subject = __( 'Form Entry Edit Takeover', 'jexi-forms' );
        
        $message = sprintf(
            /* translators: 1: new user name, 2: entry ID */
            __( '%1$s has taken over editing of entry #%2$d that you were working on. Your unsaved changes may be lost.', 'jexi-forms' ),
            $new_user->display_name,
            $entry_id
        );

        wp_mail( $old_user->user_email, $subject, $message );
    }

    /**
     * Clean up expired locks.
     *
     * @return void
     */
    public function cleanup_expired_locks(): void {
        global $wpdb;

        $table    = $wpdb->prefix . 'baf_entry_locks';
        $duration = apply_filters( 'baf_entry_lock_duration', self::LOCK_DURATION );
        $cutoff   = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $duration );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE locked_at < %s",
                $cutoff
            )
        );
    }

    /**
     * Enqueue scripts.
     *
     * @param string $hook Admin page hook.
     * @return void
     */
    public function enqueue_scripts( string $hook ): void {
        if ( strpos( $hook, 'baf-entry' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'baf-entry-locking',
            plugins_url( 'admin/js/baf-entry-locking.js', dirname( __DIR__ ) ),
            [ 'jquery', 'heartbeat' ],
            BAF_VERSION,
            true
        );

        wp_localize_script( 'baf-entry-locking', 'bafEntryLocking', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'baf_admin_nonce' ),
            'interval'  => self::HEARTBEAT_INTERVAL * 1000,
            'i18n'      => [
                'locked'   => __( 'This entry is being edited by %s.', 'jexi-forms' ),
                'takeover' => __( 'Take Over', 'jexi-forms' ),
                'preview'  => __( 'Preview', 'jexi-forms' ),
                'lockLost' => __( 'Your edit lock has been taken over. You can no longer save changes.', 'jexi-forms' ),
            ],
        ] );
    }

    /**
     * Create lock table on activation.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table           = $wpdb->prefix . 'baf_entry_locks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            locked_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY entry_id (entry_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
