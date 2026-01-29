<?php
/**
 * Settings class
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Settings class
 * 
 * Handles plugin settings management.
 */
class BAF_Settings {

    /**
     * Option name
     *
     * @var string
     */
    const OPTION_NAME = 'baf_settings';

    /**
     * Settings instance
     *
     * @var BAF_Settings
     */
    private static $instance = null;

    /**
     * Cached settings
     *
     * @var array
     */
    private $settings = array();

    /**
     * Get settings instance
     *
     * @return BAF_Settings
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
    }

    /**
     * Load settings from database
     */
    private function load_settings() {
        $saved = get_option( self::OPTION_NAME, array() );
        $this->settings = wp_parse_args( $saved, $this->get_defaults() );
    }

    /**
     * Get default settings
     *
     * @return array Default settings.
     */
    public function get_defaults() {
        return array(
            // General
            'default_form_status'      => 'draft',
            'license_key'              => '',
            
            // Security
            'enable_honeypot'          => true,
            'enable_csrf'              => true,
            'enable_rate_limiting'     => true,
            'rate_limit_submissions'   => 10,
            'rate_limit_period'        => 'minute',
            
            // Captcha
            'captcha_provider'         => 'none', // none, recaptcha_v2, recaptcha_v3, hcaptcha, turnstile
            'recaptcha_site_key'       => '',
            'recaptcha_secret_key'     => '',
            'recaptcha_v3_threshold'   => 0.5,
            'hcaptcha_site_key'        => '',
            'hcaptcha_secret_key'      => '',
            'turnstile_site_key'       => '',
            'turnstile_secret_key'     => '',
            
            // Email Security
            'block_disposable_emails'  => true,
            'block_free_emails'        => false,
            
            // Accessibility
            'enforce_wcag'             => true,
            'default_focus_style'      => 'outline', // outline, background, both
            'focus_color'              => '#005fcc',
            'respect_reduced_motion'   => true,
            'enable_skip_links'        => true,
            'announce_errors'          => true,
            'error_announcement_delay' => 150, // ms
            'high_contrast_support'    => true,
            'enable_keyboard_hints'    => true,
            
            // File Uploads
            'max_upload_size'          => 10, // MB
            'allowed_file_types'       => 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,csv',
            'upload_path'              => 'baf-uploads',
            'secure_uploads'           => true,
            
            // Currency
            'default_currency'         => 'USD',
            'currency_position'        => 'before', // before, after
            'thousand_separator'       => ',',
            'decimal_separator'        => '.',
            'decimal_places'           => 2,
            
            // Date/Time
            'date_format'              => 'Y-m-d',
            'time_format'              => 'H:i',
            'week_starts_on'           => 0, // 0 = Sunday
            
            // Save & Continue
            'draft_expiration_days'    => 30,
            'auto_save_interval'       => 30, // seconds
            
            // Notifications
            'admin_email'              => get_option( 'admin_email' ),
            'from_name'                => get_option( 'blogname' ),
            'from_email'               => get_option( 'admin_email' ),
            'email_format'             => 'html', // html, text
            
            // API
            'enable_rest_api'          => true,
            'api_rate_limit'           => 100, // requests per minute
            'api_authentication'       => 'key', // key, oauth, none
            
            // Webhooks
            'enable_webhooks'          => true,
            'webhook_timeout'          => 30, // seconds
            'webhook_retry_attempts'   => 3,
            
            // Integrations
            'google_sheets_enabled'    => false,
            'google_client_id'         => '',
            'google_client_secret'     => '',
            'google_maps_api_key'      => '',
            'stripe_enabled'           => false,
            'stripe_mode'              => 'test', // test, live
            'stripe_test_publishable'  => '',
            'stripe_test_secret'       => '',
            'stripe_live_publishable'  => '',
            'stripe_live_secret'       => '',
            'paypal_enabled'           => false,
            'paypal_mode'              => 'sandbox', // sandbox, live
            'paypal_client_id'         => '',
            'paypal_client_secret'     => '',
            
            // Performance
            'enable_caching'           => true,
            'cache_duration'           => 3600, // seconds
            'lazy_load_entries'        => true,
            'assets_loading'           => 'conditional', // conditional, always
            
            // Privacy
            'anonymize_ip'             => false,
            'ip_retention_days'        => 365,
            'gdpr_enabled'             => false,
            'privacy_policy_url'       => '',
            
            // Debug
            'debug_mode'               => false,
            'log_errors'               => true,
            'log_submissions'          => false,
        );
    }

    /**
     * Get a setting value
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed Setting value.
     */
    public function get( $key, $default = null ) {
        if ( isset( $this->settings[ $key ] ) ) {
            return $this->settings[ $key ];
        }

        if ( $default !== null ) {
            return $default;
        }

        $defaults = $this->get_defaults();
        return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
    }

    /**
     * Set a setting value
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     */
    public function set( $key, $value ) {
        $this->settings[ $key ] = $value;
    }

    /**
     * Save all settings
     *
     * @return bool True on success.
     */
    public function save() {
        return update_option( self::OPTION_NAME, $this->settings );
    }

    /**
     * Get all settings
     *
     * @return array All settings.
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Update multiple settings
     *
     * @param array $settings Settings to update.
     * @return bool True on success.
     */
    public function update( $settings ) {
        foreach ( $settings as $key => $value ) {
            if ( array_key_exists( $key, $this->get_defaults() ) ) {
                $this->settings[ $key ] = $this->sanitize_setting( $key, $value );
            }
        }

        return $this->save();
    }

    /**
     * Sanitize a setting value
     *
     * @param string $key   Setting key.
     * @param mixed  $value Raw value.
     * @return mixed Sanitized value.
     */
    private function sanitize_setting( $key, $value ) {
        // Boolean settings
        $booleans = array(
            'enable_honeypot', 'enable_csrf', 'enable_rate_limiting',
            'block_disposable_emails', 'block_free_emails',
            'enforce_wcag', 'respect_reduced_motion', 'enable_skip_links',
            'announce_errors', 'high_contrast_support', 'enable_keyboard_hints',
            'secure_uploads', 'enable_rest_api', 'enable_webhooks',
            'google_sheets_enabled', 'stripe_enabled', 'paypal_enabled',
            'enable_caching', 'lazy_load_entries', 'anonymize_ip',
            'gdpr_enabled', 'debug_mode', 'log_errors', 'log_submissions',
        );

        if ( in_array( $key, $booleans, true ) ) {
            return (bool) $value;
        }

        // Integer settings
        $integers = array(
            'rate_limit_submissions', 'max_upload_size', 'decimal_places',
            'week_starts_on', 'draft_expiration_days', 'auto_save_interval',
            'api_rate_limit', 'webhook_timeout', 'webhook_retry_attempts',
            'cache_duration', 'ip_retention_days', 'error_announcement_delay',
        );

        if ( in_array( $key, $integers, true ) ) {
            return absint( $value );
        }

        // Float settings
        if ( $key === 'recaptcha_v3_threshold' ) {
            return max( 0, min( 1, floatval( $value ) ) );
        }

        // Email
        if ( in_array( $key, array( 'admin_email', 'from_email' ), true ) ) {
            return sanitize_email( $value );
        }

        // URL
        if ( $key === 'privacy_policy_url' ) {
            return esc_url_raw( $value );
        }

        // Enum settings
        $enums = array(
            'default_form_status'   => array( 'draft', 'active', 'inactive' ),
            'rate_limit_period'     => array( 'minute', 'hour', 'day' ),
            'captcha_provider'      => array( 'none', 'recaptcha_v2', 'recaptcha_v3', 'hcaptcha', 'turnstile' ),
            'default_focus_style'   => array( 'outline', 'background', 'both' ),
            'currency_position'     => array( 'before', 'after' ),
            'email_format'          => array( 'html', 'text' ),
            'api_authentication'    => array( 'key', 'oauth', 'none' ),
            'stripe_mode'           => array( 'test', 'live' ),
            'paypal_mode'           => array( 'sandbox', 'live' ),
            'assets_loading'        => array( 'conditional', 'always' ),
        );

        if ( isset( $enums[ $key ] ) ) {
            return in_array( $value, $enums[ $key ], true ) ? $value : $this->get_defaults()[ $key ];
        }

        // Default: sanitize as text
        return sanitize_text_field( $value );
    }

    /**
     * Reset settings to defaults
     *
     * @return bool True on success.
     */
    public function reset() {
        $this->settings = $this->get_defaults();
        return $this->save();
    }

    /**
     * Export settings to JSON
     *
     * @return string JSON string.
     */
    public function export() {
        // Remove sensitive data
        $export = $this->settings;
        $sensitive = array(
            'license_key',
            'recaptcha_secret_key', 
            'hcaptcha_secret_key',
            'turnstile_secret_key',
            'google_client_secret',
            'stripe_test_secret',
            'stripe_live_secret',
            'paypal_client_secret',
        );

        foreach ( $sensitive as $key ) {
            if ( isset( $export[ $key ] ) && ! empty( $export[ $key ] ) ) {
                $export[ $key ] = '***REDACTED***';
            }
        }

        return wp_json_encode( $export, JSON_PRETTY_PRINT );
    }

    /**
     * Import settings from JSON
     *
     * @param string $json JSON string.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function import( $json ) {
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Invalid JSON format.', 'bits-accessible-forms' ) );
        }

        // Don't import redacted values
        $current = $this->settings;
        foreach ( $data as $key => $value ) {
            if ( $value === '***REDACTED***' && isset( $current[ $key ] ) ) {
                $data[ $key ] = $current[ $key ];
            }
        }

        return $this->update( $data );
    }

    /**
     * Get settings for a specific section
     *
     * @param string $section Section name.
     * @return array Section settings.
     */
    public function get_section( $section ) {
        $sections = array(
            'general'       => array( 'default_form_status', 'license_key' ),
            'security'      => array( 'enable_honeypot', 'enable_csrf', 'enable_rate_limiting', 'rate_limit_submissions', 'rate_limit_period' ),
            'captcha'       => array( 'captcha_provider', 'recaptcha_site_key', 'recaptcha_secret_key', 'recaptcha_v3_threshold', 'hcaptcha_site_key', 'hcaptcha_secret_key', 'turnstile_site_key', 'turnstile_secret_key' ),
            'accessibility' => array( 'enforce_wcag', 'default_focus_style', 'focus_color', 'respect_reduced_motion', 'enable_skip_links', 'announce_errors', 'error_announcement_delay', 'high_contrast_support', 'enable_keyboard_hints' ),
            'uploads'       => array( 'max_upload_size', 'allowed_file_types', 'upload_path', 'secure_uploads' ),
            'currency'      => array( 'default_currency', 'currency_position', 'thousand_separator', 'decimal_separator', 'decimal_places' ),
            'datetime'      => array( 'date_format', 'time_format', 'week_starts_on' ),
            'save_continue' => array( 'draft_expiration_days', 'auto_save_interval' ),
            'notifications' => array( 'admin_email', 'from_name', 'from_email', 'email_format' ),
            'api'           => array( 'enable_rest_api', 'api_rate_limit', 'api_authentication' ),
            'webhooks'      => array( 'enable_webhooks', 'webhook_timeout', 'webhook_retry_attempts' ),
            'stripe'        => array( 'stripe_enabled', 'stripe_mode', 'stripe_test_publishable', 'stripe_test_secret', 'stripe_live_publishable', 'stripe_live_secret' ),
            'paypal'        => array( 'paypal_enabled', 'paypal_mode', 'paypal_client_id', 'paypal_client_secret' ),
            'google'        => array( 'google_sheets_enabled', 'google_client_id', 'google_client_secret', 'google_maps_api_key' ),
            'performance'   => array( 'enable_caching', 'cache_duration', 'lazy_load_entries', 'assets_loading' ),
            'privacy'       => array( 'anonymize_ip', 'ip_retention_days', 'gdpr_enabled', 'privacy_policy_url' ),
            'debug'         => array( 'debug_mode', 'log_errors', 'log_submissions' ),
        );

        if ( ! isset( $sections[ $section ] ) ) {
            return array();
        }

        $result = array();
        foreach ( $sections[ $section ] as $key ) {
            $result[ $key ] = $this->get( $key );
        }

        return $result;
    }

    /**
     * Get all available currencies
     *
     * @return array Currencies.
     */
    public static function get_currencies() {
        return array(
            'USD' => array( 'name' => 'US Dollar', 'symbol' => '$' ),
            'EUR' => array( 'name' => 'Euro', 'symbol' => '€' ),
            'GBP' => array( 'name' => 'British Pound', 'symbol' => '£' ),
            'CAD' => array( 'name' => 'Canadian Dollar', 'symbol' => 'C$' ),
            'AUD' => array( 'name' => 'Australian Dollar', 'symbol' => 'A$' ),
            'JPY' => array( 'name' => 'Japanese Yen', 'symbol' => '¥' ),
            'CNY' => array( 'name' => 'Chinese Yuan', 'symbol' => '¥' ),
            'INR' => array( 'name' => 'Indian Rupee', 'symbol' => '₹' ),
            'BRL' => array( 'name' => 'Brazilian Real', 'symbol' => 'R$' ),
            'MXN' => array( 'name' => 'Mexican Peso', 'symbol' => '$' ),
            'CHF' => array( 'name' => 'Swiss Franc', 'symbol' => 'CHF' ),
            'SEK' => array( 'name' => 'Swedish Krona', 'symbol' => 'kr' ),
            'NOK' => array( 'name' => 'Norwegian Krone', 'symbol' => 'kr' ),
            'DKK' => array( 'name' => 'Danish Krone', 'symbol' => 'kr' ),
            'NZD' => array( 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$' ),
            'SGD' => array( 'name' => 'Singapore Dollar', 'symbol' => 'S$' ),
            'HKD' => array( 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$' ),
            'KRW' => array( 'name' => 'South Korean Won', 'symbol' => '₩' ),
            'ZAR' => array( 'name' => 'South African Rand', 'symbol' => 'R' ),
            'RUB' => array( 'name' => 'Russian Ruble', 'symbol' => '₽' ),
        );
    }
}
