<?php
/**
 * Rate Limiter
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Rate_Limiter class
 * 
 * Implements rate limiting for form submissions.
 */
class BAF_Rate_Limiter {

    /**
     * Database table name
     *
     * @var string
     */
    private $table;

    /**
     * Default limits
     *
     * @var array
     */
    private $defaults = array(
        'max_submissions'  => 5,     // Max submissions per window
        'window_seconds'   => 3600,  // 1 hour window
        'global_max'       => 100,   // Max total submissions per IP per day
    );

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'baf_rate_limits';
    }

    /**
     * Check rate limit
     *
     * @param BAF_Form        $form    Form object.
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True if within limits, WP_Error if exceeded.
     */
    public function check( $form, $request ) {
        $ip_address = $this->get_client_ip();
        $form_id = $form->id;

        // Get form-specific limits or use defaults
        $limits = $this->get_limits( $form );

        // Check form-specific rate
        $form_submissions = $this->get_submission_count( $ip_address, $form_id, $limits['window_seconds'] );

        if ( $form_submissions >= $limits['max_submissions'] ) {
            $retry_after = $this->get_retry_after( $ip_address, $form_id, $limits['window_seconds'] );
            
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __( 'Too many submissions. Please try again in %d minutes.', 'bits-accessible-forms' ),
                    ceil( $retry_after / 60 )
                ),
                array( 
                    'status' => 429,
                    'retry_after' => $retry_after,
                )
            );
        }

        // Check global daily limit
        $daily_submissions = $this->get_submission_count( $ip_address, null, DAY_IN_SECONDS );

        if ( $daily_submissions >= $limits['global_max'] ) {
            return new WP_Error(
                'daily_limit_exceeded',
                __( 'You have reached the maximum number of submissions for today.', 'bits-accessible-forms' ),
                array( 'status' => 429 )
            );
        }

        // Record this submission attempt
        $this->record_submission( $ip_address, $form_id );

        return true;
    }

    /**
     * Get limits for a form
     *
     * @param BAF_Form $form Form object.
     * @return array Limits.
     */
    private function get_limits( $form ) {
        $form_settings = $form->settings;
        
        return array(
            'max_submissions' => isset( $form_settings['rate_limit_max'] ) 
                ? intval( $form_settings['rate_limit_max'] ) 
                : $this->defaults['max_submissions'],
            'window_seconds'  => isset( $form_settings['rate_limit_window'] ) 
                ? intval( $form_settings['rate_limit_window'] ) 
                : $this->defaults['window_seconds'],
            'global_max'      => isset( $form_settings['rate_limit_global'] ) 
                ? intval( $form_settings['rate_limit_global'] ) 
                : $this->defaults['global_max'],
        );
    }

    /**
     * Get submission count
     *
     * @param string   $ip_address IP address.
     * @param int|null $form_id    Form ID (null for all forms).
     * @param int      $window     Time window in seconds.
     * @return int Submission count.
     */
    private function get_submission_count( $ip_address, $form_id, $window ) {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $window );

        if ( $form_id ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE ip_address = %s AND form_id = %d AND created_at > %s",
                $ip_address,
                $form_id,
                $cutoff
            ) );
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE ip_address = %s AND created_at > %s",
            $ip_address,
            $cutoff
        ) );
    }

    /**
     * Get seconds until retry is allowed
     *
     * @param string $ip_address IP address.
     * @param int    $form_id    Form ID.
     * @param int    $window     Time window in seconds.
     * @return int Seconds until retry.
     */
    private function get_retry_after( $ip_address, $form_id, $window ) {
        global $wpdb;

        $oldest = $wpdb->get_var( $wpdb->prepare(
            "SELECT MIN(created_at) FROM {$this->table} 
             WHERE ip_address = %s AND form_id = %d 
             AND created_at > %s",
            $ip_address,
            $form_id,
            gmdate( 'Y-m-d H:i:s', time() - $window )
        ) );

        if ( $oldest ) {
            $oldest_time = strtotime( $oldest );
            return max( 0, $window - ( time() - $oldest_time ) );
        }

        return 0;
    }

    /**
     * Record a submission
     *
     * @param string $ip_address IP address.
     * @param int    $form_id    Form ID.
     */
    private function record_submission( $ip_address, $form_id ) {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            array(
                'ip_address' => $ip_address,
                'form_id'    => $form_id,
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%s', '%d', '%s' )
        );
    }

    /**
     * Clean up old records
     *
     * @param int $older_than Seconds to keep records.
     */
    public function cleanup( $older_than = DAY_IN_SECONDS ) {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $older_than );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < %s",
            $cutoff
        ) );
    }

    /**
     * Get client IP
     *
     * @return string IP address.
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
        }

        return $ip;
    }

    /**
     * Check if IP is whitelisted
     *
     * @param string $ip_address IP address.
     * @return bool True if whitelisted.
     */
    public function is_whitelisted( $ip_address ) {
        $settings = new BAF_Settings();
        $whitelist = $settings->get( 'rate_limit_whitelist', array() );

        return in_array( $ip_address, $whitelist, true );
    }

    /**
     * Check if IP is blacklisted
     *
     * @param string $ip_address IP address.
     * @return bool True if blacklisted.
     */
    public function is_blacklisted( $ip_address ) {
        $settings = new BAF_Settings();
        $blacklist = $settings->get( 'rate_limit_blacklist', array() );

        return in_array( $ip_address, $blacklist, true );
    }
}
