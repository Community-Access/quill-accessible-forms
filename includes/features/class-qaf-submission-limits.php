<?php
/**
 * Submission Limits Feature
 *
 * Control maximum entries, per-user/IP/email limits, time-period restrictions,
 * and conditional limits based on field values.
 *
 * @package QAF
 * @since 1.0.0
 */

namespace QAF\Features;

use QAF\Core\Form;
use QAF\Core\Entry;

/**
 * Class Submission_Limits
 *
 * Manages submission limits and restrictions.
 */
class Submission_Limits {

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
        // Check limits before showing form
        add_filter( 'qaf_can_show_form', [ $this, 'check_form_limits' ], 10, 2 );

        // Check limits before submission
        add_filter( 'qaf_pre_submission', [ $this, 'validate_limits' ], 5, 2 );

        // Track submissions by email
        add_action( 'qaf_entry_created', [ $this, 'track_submission' ], 10, 2 );

        // Schedule cleanup
        add_action( 'qaf_daily_tasks', [ $this, 'cleanup_tracking' ] );
    }

    /**
     * Check form-level limits before showing form.
     *
     * @param bool $can_show Whether form can be shown.
     * @param Form $form     Form object.
     * @return bool|array False or array with message if limited.
     */
    public function check_form_limits( bool $can_show, Form $form ): bool|array {
        if ( ! $can_show ) {
            return $can_show;
        }

        // Check total submission limit
        if ( $this->is_total_limit_reached( $form ) ) {
            return [
                'limited' => true,
                'message' => $form->get_setting( 'limit_reached_message' ) 
                    ?: __( 'This form is no longer accepting submissions.', 'jexi-forms' ),
            ];
        }

        // Check date restrictions
        $date_check = $this->check_date_restrictions( $form );
        if ( $date_check !== true ) {
            return [
                'limited' => true,
                'message' => $date_check,
            ];
        }

        // Check user limit
        if ( is_user_logged_in() && $this->is_user_limit_reached( $form, get_current_user_id() ) ) {
            return [
                'limited' => true,
                'message' => $form->get_setting( 'user_limit_message' )
                    ?: __( 'You have already submitted this form.', 'jexi-forms' ),
            ];
        }

        return true;
    }

    /**
     * Validate limits before form submission.
     *
     * @param array $data Submission data.
     * @param Form  $form Form object.
     * @return array Modified data with potential errors.
     */
    public function validate_limits( array $data, Form $form ): array {
        $errors = [];

        // Re-check total limit (could have changed since form was loaded)
        if ( $this->is_total_limit_reached( $form ) ) {
            $errors['_limit'] = $form->get_setting( 'limit_reached_message' )
                ?: __( 'This form is no longer accepting submissions.', 'jexi-forms' );
        }

        // Check IP limit
        $ip = $this->get_client_ip();
        if ( $this->is_ip_limit_reached( $form, $ip ) ) {
            $errors['_ip_limit'] = $form->get_setting( 'ip_limit_message' )
                ?: __( 'Submission limit reached from this location.', 'jexi-forms' );
        }

        // Check user limit
        if ( is_user_logged_in() && $this->is_user_limit_reached( $form, get_current_user_id() ) ) {
            $errors['_user_limit'] = $form->get_setting( 'user_limit_message' )
                ?: __( 'You have already submitted this form.', 'jexi-forms' );
        }

        // Check email limit
        $email_field = $form->get_setting( 'email_limit_field' );
        if ( $email_field && isset( $data['values'][ $email_field ] ) ) {
            $email = sanitize_email( $data['values'][ $email_field ] );
            if ( $email && $this->is_email_limit_reached( $form, $email ) ) {
                $errors[ $email_field ] = $form->get_setting( 'email_limit_message' )
                    ?: __( 'This email has already been used for a submission.', 'jexi-forms' );
            }
        }

        // Check field value limits (e.g., max signups per option)
        $field_limits = $form->get_setting( 'field_value_limits' );
        if ( ! empty( $field_limits ) ) {
            $field_errors = $this->check_field_value_limits( $form, $data['values'], $field_limits );
            $errors = array_merge( $errors, $field_errors );
        }

        if ( ! empty( $errors ) ) {
            $data['errors'] = array_merge( $data['errors'] ?? [], $errors );
        }

        return $data;
    }

    /**
     * Check if total submission limit is reached.
     *
     * @param Form $form Form object.
     * @return bool True if limit reached.
     */
    public function is_total_limit_reached( Form $form ): bool {
        $limit = (int) $form->get_setting( 'entry_limit' );
        
        if ( ! $limit ) {
            return false;
        }

        $count = $this->get_entry_count( $form->get_id() );
        
        return $count >= $limit;
    }

    /**
     * Check if per-user limit is reached.
     *
     * @param Form $form    Form object.
     * @param int  $user_id User ID.
     * @return bool True if limit reached.
     */
    public function is_user_limit_reached( Form $form, int $user_id ): bool {
        $limit = (int) $form->get_setting( 'user_entry_limit' );
        
        if ( ! $limit || ! $user_id ) {
            return false;
        }

        $time_period = $form->get_setting( 'user_limit_period' );
        $count       = $this->get_user_entry_count( $form->get_id(), $user_id, $time_period );

        return $count >= $limit;
    }

    /**
     * Check if per-IP limit is reached.
     *
     * @param Form   $form Form object.
     * @param string $ip   IP address.
     * @return bool True if limit reached.
     */
    public function is_ip_limit_reached( Form $form, string $ip ): bool {
        $limit = (int) $form->get_setting( 'ip_entry_limit' );
        
        if ( ! $limit ) {
            return false;
        }

        $time_period = $form->get_setting( 'ip_limit_period' ) ?: 'hour';
        $count       = $this->get_ip_entry_count( $form->get_id(), $ip, $time_period );

        return $count >= $limit;
    }

    /**
     * Check if per-email limit is reached.
     *
     * @param Form   $form  Form object.
     * @param string $email Email address.
     * @return bool True if limit reached.
     */
    public function is_email_limit_reached( Form $form, string $email ): bool {
        $limit = (int) $form->get_setting( 'email_entry_limit' );
        
        if ( ! $limit || ! $email ) {
            return false;
        }

        $time_period = $form->get_setting( 'email_limit_period' );
        $count       = $this->get_email_entry_count( $form->get_id(), $email, $time_period );

        return $count >= $limit;
    }

    /**
     * Check date restrictions.
     *
     * @param Form $form Form object.
     * @return bool|string True if OK, or message if restricted.
     */
    public function check_date_restrictions( Form $form ): bool|string {
        $now = current_time( 'timestamp' );

        // Start date
        $start_date = $form->get_setting( 'schedule_start' );
        if ( $start_date && strtotime( $start_date ) > $now ) {
            $message = $form->get_setting( 'not_started_message' );
            if ( ! $message ) {
                $message = sprintf(
                    /* translators: %s: start date */
                    __( 'This form will open on %s.', 'jexi-forms' ),
                    date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start_date ) )
                );
            }
            return $message;
        }

        // End date
        $end_date = $form->get_setting( 'schedule_end' );
        if ( $end_date && strtotime( $end_date ) < $now ) {
            return $form->get_setting( 'expired_message' )
                ?: __( 'This form is no longer accepting submissions.', 'jexi-forms' );
        }

        // Day of week restrictions
        $allowed_days = $form->get_setting( 'allowed_days' );
        if ( ! empty( $allowed_days ) ) {
            $current_day = strtolower( date( 'l' ) );
            if ( ! in_array( $current_day, (array) $allowed_days, true ) ) {
                return $form->get_setting( 'day_restricted_message' )
                    ?: __( 'Submissions are not accepted on this day.', 'jexi-forms' );
            }
        }

        // Time of day restrictions
        $start_time = $form->get_setting( 'daily_start_time' );
        $end_time   = $form->get_setting( 'daily_end_time' );
        
        if ( $start_time && $end_time ) {
            $current_time = current_time( 'H:i' );
            if ( $current_time < $start_time || $current_time > $end_time ) {
                return $form->get_setting( 'time_restricted_message' )
                    ?: sprintf(
                        /* translators: 1: start time, 2: end time */
                        __( 'Submissions are only accepted between %1$s and %2$s.', 'jexi-forms' ),
                        $start_time,
                        $end_time
                    );
            }
        }

        return true;
    }

    /**
     * Check field value limits.
     *
     * @param Form  $form   Form object.
     * @param array $values Field values.
     * @param array $limits Limit configuration.
     * @return array Errors keyed by field.
     */
    private function check_field_value_limits( Form $form, array $values, array $limits ): array {
        $errors  = [];
        $form_id = $form->get_id();

        foreach ( $limits as $limit_config ) {
            $field_id = $limit_config['field'] ?? '';
            $limit    = (int) ( $limit_config['limit'] ?? 0 );
            $per      = $limit_config['per'] ?? 'value'; // 'value' or 'total'

            if ( ! $field_id || ! $limit || ! isset( $values[ $field_id ] ) ) {
                continue;
            }

            $submitted_values = (array) $values[ $field_id ];

            foreach ( $submitted_values as $value ) {
                $count = $this->get_field_value_count( $form_id, $field_id, $value );

                if ( $count >= $limit ) {
                    $field   = $form->get_field( $field_id );
                    $message = $limit_config['message'] ?? sprintf(
                        /* translators: %s: option name */
                        __( '"%s" has reached its submission limit.', 'jexi-forms' ),
                        $value
                    );

                    $errors[ $field_id ] = $message;
                    break; // One error per field is enough
                }
            }
        }

        return $errors;
    }

    /**
     * Get total entry count for a form.
     *
     * @param int $form_id Form ID.
     * @return int Count.
     */
    private function get_entry_count( int $form_id ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entries';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND status = 'active'",
                $form_id
            )
        );
    }

    /**
     * Get entry count for a user.
     *
     * @param int         $form_id     Form ID.
     * @param int         $user_id     User ID.
     * @param string|null $time_period Time period (hour, day, week, month, year, null for all time).
     * @return int Count.
     */
    private function get_user_entry_count( int $form_id, int $user_id, ?string $time_period = null ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entries';
        $where = "form_id = %d AND user_id = %d AND status = 'active'";
        $args  = [ $form_id, $user_id ];

        if ( $time_period ) {
            $where .= ' AND date_created >= %s';
            $args[] = $this->get_period_start( $time_period );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE {$where}",
                $args
            )
        );
    }

    /**
     * Get entry count for an IP address.
     *
     * @param int    $form_id     Form ID.
     * @param string $ip          IP address.
     * @param string $time_period Time period.
     * @return int Count.
     */
    private function get_ip_entry_count( int $form_id, string $ip, string $time_period = 'hour' ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entries';
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE form_id = %d AND ip_address = %s AND status = 'active' AND date_created >= %s",
                $form_id,
                $ip,
                $this->get_period_start( $time_period )
            )
        );
    }

    /**
     * Get entry count for an email address.
     *
     * @param int         $form_id     Form ID.
     * @param string      $email       Email address.
     * @param string|null $time_period Time period.
     * @return int Count.
     */
    private function get_email_entry_count( int $form_id, string $email, ?string $time_period = null ): int {
        global $wpdb;

        $tracking_table = $wpdb->prefix . 'qaf_submission_tracking';

        $where = "form_id = %d AND email = %s";
        $args  = [ $form_id, $email ];

        if ( $time_period ) {
            $where .= ' AND submitted_at >= %s';
            $args[] = $this->get_period_start( $time_period );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tracking_table} WHERE {$where}",
                $args
            )
        );
    }

    /**
     * Get count of entries with a specific field value.
     *
     * @param int    $form_id  Form ID.
     * @param string $field_id Field ID.
     * @param string $value    Field value.
     * @return int Count.
     */
    private function get_field_value_count( int $form_id, string $field_id, string $value ): int {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'qaf_entries';
        $meta_table    = $wpdb->prefix . 'qaf_entry_meta';

        // For simple values stored in meta
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$meta_table} m
                INNER JOIN {$entries_table} e ON m.entry_id = e.id
                WHERE e.form_id = %d AND e.status = 'active' 
                AND m.meta_key = %s AND m.meta_value = %s",
                $form_id,
                $field_id,
                $value
            )
        );
    }

    /**
     * Get start datetime for a time period.
     *
     * @param string $period Period (hour, day, week, month, year).
     * @return string MySQL datetime.
     */
    private function get_period_start( string $period ): string {
        $now = current_time( 'timestamp' );

        switch ( $period ) {
            case 'hour':
                $timestamp = strtotime( '-1 hour', $now );
                break;
            case 'day':
                $timestamp = strtotime( 'today midnight' );
                break;
            case 'week':
                $timestamp = strtotime( 'monday this week midnight' );
                break;
            case 'month':
                $timestamp = strtotime( 'first day of this month midnight' );
                break;
            case 'year':
                $timestamp = strtotime( 'first day of January midnight' );
                break;
            default:
                $timestamp = 0;
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Track submission (for email tracking).
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function track_submission( Entry $entry, Form $form ): void {
        global $wpdb;

        // Get email from configured field
        $email_field = $form->get_setting( 'email_limit_field' );
        if ( ! $email_field ) {
            return;
        }

        $email = sanitize_email( $entry->get_field_value( $email_field ) );
        if ( ! $email ) {
            return;
        }

        $tracking_table = $wpdb->prefix . 'qaf_submission_tracking';

        // Create table if it doesn't exist (in case activation hook didn't run)
        $this->maybe_create_tracking_table();

        $wpdb->insert(
            $tracking_table,
            [
                'form_id'      => $form->get_id(),
                'entry_id'     => $entry->get_id(),
                'email'        => $email,
                'ip_address'   => $this->get_client_ip(),
                'user_id'      => get_current_user_id() ?: null,
                'submitted_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * Create tracking table if needed.
     *
     * @return void
     */
    private function maybe_create_tracking_table(): void {
        global $wpdb;

        $tracking_table  = $wpdb->prefix . 'qaf_submission_tracking';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tracking_table}'" ) === $tracking_table ) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$tracking_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id bigint(20) UNSIGNED NOT NULL,
            entry_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            submitted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY form_email (form_id, email),
            KEY form_ip (form_id, ip_address),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Cleanup old tracking records.
     *
     * @return void
     */
    public function cleanup_tracking(): void {
        global $wpdb;

        $tracking_table = $wpdb->prefix . 'qaf_submission_tracking';
        $retention_days = apply_filters( 'qaf_tracking_retention_days', 90 );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$tracking_table} WHERE submitted_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) )
            )
        );
    }

    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    private function get_client_ip(): string {
        $ip_keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = $_SERVER[ $key ];
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get remaining slots for a form.
     *
     * @param Form $form Form object.
     * @return int|null Remaining slots or null if unlimited.
     */
    public function get_remaining_slots( Form $form ): ?int {
        $limit = (int) $form->get_setting( 'entry_limit' );
        
        if ( ! $limit ) {
            return null;
        }

        $current = $this->get_entry_count( $form->get_id() );
        
        return max( 0, $limit - $current );
    }

    /**
     * Get remaining submissions for user.
     *
     * @param Form     $form    Form object.
     * @param int|null $user_id User ID (defaults to current user).
     * @return int|null Remaining submissions or null if unlimited.
     */
    public function get_user_remaining( Form $form, ?int $user_id = null ): ?int {
        $limit = (int) $form->get_setting( 'user_entry_limit' );
        
        if ( ! $limit ) {
            return null;
        }

        $user_id = $user_id ?? get_current_user_id();
        
        if ( ! $user_id ) {
            return null;
        }

        $time_period = $form->get_setting( 'user_limit_period' );
        $current     = $this->get_user_entry_count( $form->get_id(), $user_id, $time_period );

        return max( 0, $limit - $current );
    }

    /**
     * Check if form is currently open based on schedule.
     *
     * @param Form $form Form object.
     * @return bool True if open.
     */
    public function is_form_open( Form $form ): bool {
        return $this->check_date_restrictions( $form ) === true;
    }

    /**
     * Get form schedule info.
     *
     * @param Form $form Form object.
     * @return array Schedule information.
     */
    public function get_schedule_info( Form $form ): array {
        return [
            'start'        => $form->get_setting( 'schedule_start' ),
            'end'          => $form->get_setting( 'schedule_end' ),
            'is_open'      => $this->is_form_open( $form ),
            'entry_limit'  => (int) $form->get_setting( 'entry_limit' ) ?: null,
            'current_count' => $this->get_entry_count( $form->get_id() ),
            'remaining'    => $this->get_remaining_slots( $form ),
        ];
    }

    /**
     * Create limit table on activation.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $tracking_table  = $wpdb->prefix . 'qaf_submission_tracking';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$tracking_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id bigint(20) UNSIGNED NOT NULL,
            entry_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            submitted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY form_email (form_id, email),
            KEY form_ip (form_id, ip_address),
            KEY submitted_at (submitted_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
