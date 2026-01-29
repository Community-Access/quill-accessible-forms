<?php
/**
 * Security Handler
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Security class
 * 
 * Main security controller for form submissions.
 */
class BAF_Security {

    /**
     * Honeypot handler
     *
     * @var BAF_Honeypot
     */
    private $honeypot;

    /**
     * Rate limiter
     *
     * @var BAF_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Captcha handler
     *
     * @var BAF_Captcha
     */
    private $captcha;

    /**
     * Constructor
     */
    public function __construct() {
        $this->honeypot = new BAF_Honeypot();
        $this->rate_limiter = new BAF_Rate_Limiter();
        $this->captcha = new BAF_Captcha();
    }

    /**
     * Validate a form submission
     *
     * @param BAF_Form        $form    Form object.
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function validate_submission( $form, $request ) {
        // Check nonce (if applicable)
        if ( ! $this->verify_nonce( $request ) ) {
            $this->log_security_event( 'nonce_failure', $form->id, $request );
            return new WP_Error( 'security_nonce_invalid', __( 'Security check failed. Please refresh and try again.', 'bits-accessible-forms' ), array( 'status' => 403 ) );
        }

        // Check honeypot
        $honeypot_check = $this->honeypot->validate( $request );
        if ( is_wp_error( $honeypot_check ) ) {
            $this->log_security_event( 'honeypot_triggered', $form->id, $request );
            return $honeypot_check;
        }

        // Check rate limiting
        $rate_check = $this->rate_limiter->check( $form, $request );
        if ( is_wp_error( $rate_check ) ) {
            $this->log_security_event( 'rate_limit_exceeded', $form->id, $request );
            return $rate_check;
        }

        // Check captcha if enabled
        $form_settings = $form->settings;
        if ( ! empty( $form_settings['captcha_enabled'] ) ) {
            $captcha_check = $this->captcha->validate( $request );
            if ( is_wp_error( $captcha_check ) ) {
                $this->log_security_event( 'captcha_failed', $form->id, $request );
                return $captcha_check;
            }
        }

        // Check for suspicious patterns
        $pattern_check = $this->check_suspicious_patterns( $request );
        if ( is_wp_error( $pattern_check ) ) {
            $this->log_security_event( 'suspicious_pattern', $form->id, $request );
            return $pattern_check;
        }

        // Check time-based submission (too fast = bot)
        $time_check = $this->check_submission_timing( $request );
        if ( is_wp_error( $time_check ) ) {
            $this->log_security_event( 'too_fast_submission', $form->id, $request );
            return $time_check;
        }

        return true;
    }

    /**
     * Verify nonce
     *
     * @param WP_REST_Request $request Request object.
     * @return bool True if valid.
     */
    private function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        // If nonce header is present, verify it
        if ( ! empty( $nonce ) ) {
            return wp_verify_nonce( $nonce, 'wp_rest' );
        }

        // For public submissions without nonce, check token
        $token = $request->get_param( '_baf_token' );
        if ( ! empty( $token ) ) {
            return wp_verify_nonce( $token, 'baf_submit_form' );
        }

        // Allow submissions without nonce for public forms
        return true;
    }

    /**
     * Check for suspicious patterns in submission
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True if clean.
     */
    private function check_suspicious_patterns( $request ) {
        $fields = $request->get_param( 'fields' ) ?: array();
        $suspicious_patterns = array(
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/data:text\/html/i',
            '/\[url=/i',
            '/\[link=/i',
            '/http[s]?:\/\/[^\s]+\.(ru|cn|tk|ml|ga|cf|gq)/i',
        );

        foreach ( $fields as $field_value ) {
            if ( is_string( $field_value ) ) {
                foreach ( $suspicious_patterns as $pattern ) {
                    if ( preg_match( $pattern, $field_value ) ) {
                        return new WP_Error( 
                            'security_suspicious_content', 
                            __( 'Your submission contains suspicious content.', 'bits-accessible-forms' ), 
                            array( 'status' => 400 ) 
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check submission timing
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True if timing is acceptable.
     */
    private function check_submission_timing( $request ) {
        $form_loaded_time = $request->get_param( '_baf_form_loaded' );
        
        if ( empty( $form_loaded_time ) ) {
            return true; // Can't check without timestamp
        }

        $time_diff = time() - intval( $form_loaded_time );
        
        // Less than 2 seconds is suspicious
        if ( $time_diff < 2 ) {
            return new WP_Error( 
                'security_too_fast', 
                __( 'Please take more time to fill out the form.', 'bits-accessible-forms' ), 
                array( 'status' => 400 ) 
            );
        }

        return true;
    }

    /**
     * Log security event
     *
     * @param string          $event_type Event type.
     * @param int             $form_id    Form ID.
     * @param WP_REST_Request $request    Request object.
     */
    private function log_security_event( $event_type, $form_id, $request ) {
        $log_data = array(
            'event_type' => $event_type,
            'form_id'    => $form_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
            'timestamp'  => current_time( 'mysql' ),
        );

        /**
         * Fires when a security event is logged
         *
         * @param array $log_data Security log data.
         */
        do_action( 'baf_security_event', $log_data );

        // Store in transient for admin review
        $logs = get_transient( 'baf_security_logs' ) ?: array();
        $logs[] = $log_data;
        
        // Keep only last 100 entries
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, -100 );
        }

        set_transient( 'baf_security_logs', $logs, DAY_IN_SECONDS );
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
     * Get security logs
     *
     * @return array Security logs.
     */
    public function get_logs() {
        return get_transient( 'baf_security_logs' ) ?: array();
    }

    /**
     * Clear security logs
     */
    public function clear_logs() {
        delete_transient( 'baf_security_logs' );
    }

    /**
     * Generate form token
     *
     * @param int $form_id Form ID.
     * @return string Token.
     */
    public function generate_token( $form_id ) {
        return wp_create_nonce( 'baf_submit_form_' . $form_id );
    }
}
