<?php
/**
 * Rate Limiter
 *
 * Counts sends per address, per form and per day. Before 2026-11 this class
 * queried a created_at column the qaf_rate_limits table never had, so it
 * counted nothing; it now keeps one row per send in qaf_rate_events (made by
 * the schema upgrade in QAF_Activator). It no longer decides anything on its
 * own: QAF_Spam_Rate_Check turns its counts into a held entry, and only the
 * daily ceiling is ever refused (see class-qaf-spam-verdict.php).
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Rate_Limiter class
 */
class QAF_Rate_Limiter {

    /**
     * Database table name
     *
     * @var string
     */
    protected $table;

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
        $this->table = ( isset( $wpdb->prefix ) ? $wpdb->prefix : 'wp_' ) . 'qaf_rate_events';
    }

    /**
     * Limits for a form: its own settings, else the defaults.
     *
     * @param array $form_settings The form's settings.
     * @return array max_submissions, window_seconds, global_max.
     */
    public function limits_for( $form_settings ) {
        $form_settings = is_array( $form_settings ) ? $form_settings : array();
        return array(
            'max_submissions' => isset( $form_settings['rate_limit_max'] ) ? max( 1, intval( $form_settings['rate_limit_max'] ) ) : $this->defaults['max_submissions'],
            'window_seconds'  => isset( $form_settings['rate_limit_window'] ) ? max( 60, intval( $form_settings['rate_limit_window'] ) ) : $this->defaults['window_seconds'],
            'global_max'      => isset( $form_settings['rate_limit_global'] ) ? max( 1, intval( $form_settings['rate_limit_global'] ) ) : $this->defaults['global_max'],
        );
    }

    /**
     * Count this send and say where it stands.
     *
     * @param int    $form_id Form id.
     * @param string $ip      Address.
     * @param array  $limits  From limits_for().
     * @param bool   $record  False to look without counting (an answer to a question).
     * @return array count (sends in the window, this one included when recorded), daily (sends today before this one).
     */
    public function assess( $form_id, $ip, $limits, $record = true ) {
        $count = $this->count_since( $ip, (int) $form_id, time() - (int) $limits['window_seconds'] );
        $daily = $this->count_since( $ip, null, time() - DAY_IN_SECONDS );
        if ( $record ) {
            $this->record_event( $ip, (int) $form_id );
            $count++;
        }
        return array( 'count' => $count, 'daily' => $daily );
    }

    /**
     * The old shape, kept for any code outside QAF that called it: true, or a
     * WP_Error when over a limit. QAF itself uses assess().
     *
     * @param QAF_Form        $form    Form object.
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    public function check( $form, $request ) {
        $limits = $this->limits_for( $form->settings );
        $a      = $this->assess( $form->id, $this->get_client_ip(), $limits );
        if ( $a['daily'] >= $limits['global_max'] ) {
            return new WP_Error( 'daily_limit_exceeded', __( 'You have reached the maximum number of submissions for today.', 'quill-accessible-forms' ), array( 'status' => 429 ) );
        }
        if ( $a['count'] > $limits['max_submissions'] ) {
            return new WP_Error( 'rate_limit_exceeded', __( 'Too many submissions. Please try again later.', 'quill-accessible-forms' ), array( 'status' => 429 ) );
        }
        return true;
    }

    /**
     * Sends since a time.
     *
     * @param string   $ip_address IP address.
     * @param int|null $form_id    Form ID (null for all forms).
     * @param int      $since      Unix time.
     * @return int
     */
    protected function count_since( $ip_address, $form_id, $since ) {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', $since );

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
     * Record a send
     *
     * @param string $ip_address IP address.
     * @param int    $form_id    Form ID.
     */
    protected function record_event( $ip_address, $form_id ) {
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
     * Get client IP: REMOTE_ADDR, unless the site says it is behind a
     * proxy it trusts (the qaf_trust_proxy_headers filter). Forwarded
     * headers are not trusted by default, because anyone can send them.
     *
     * @return string IP address.
     */
    private function get_client_ip() {
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

    /**
     * Check if IP is whitelisted
     *
     * @param string $ip_address IP address.
     * @return bool True if whitelisted.
     */
    public function is_whitelisted( $ip_address ) {
        if ( ! class_exists( 'QAF_Settings' ) ) {
            return false;
        }
        $whitelist = QAF_Settings::get_instance()->get( 'rate_limit_whitelist', array() );

        return in_array( $ip_address, (array) $whitelist, true );
    }

    /**
     * Check if IP is blacklisted
     *
     * @param string $ip_address IP address.
     * @return bool True if blacklisted.
     */
    public function is_blacklisted( $ip_address ) {
        if ( ! class_exists( 'QAF_Settings' ) ) {
            return false;
        }
        $blacklist = QAF_Settings::get_instance()->get( 'rate_limit_blacklist', array() );

        return in_array( $ip_address, (array) $blacklist, true );
    }
}
