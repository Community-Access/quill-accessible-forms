<?php
/**
 * CAPTCHA Handler
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Captcha class
 * 
 * Handles CAPTCHA validation for form submissions.
 * Supports multiple providers: reCAPTCHA v2, reCAPTCHA v3, hCaptcha, Turnstile.
 */
class BAF_Captcha {

    /**
     * Settings instance
     *
     * @var BAF_Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new BAF_Settings();
    }

    /**
     * Validate CAPTCHA response
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public function validate( $request ) {
        $provider = $this->settings->get( 'captcha_provider', 'recaptcha_v2' );

        switch ( $provider ) {
            case 'recaptcha_v2':
                return $this->validate_recaptcha_v2( $request );

            case 'recaptcha_v3':
                return $this->validate_recaptcha_v3( $request );

            case 'hcaptcha':
                return $this->validate_hcaptcha( $request );

            case 'turnstile':
                return $this->validate_turnstile( $request );

            case 'math':
                return $this->validate_math_captcha( $request );

            default:
                return true;
        }
    }

    /**
     * Validate reCAPTCHA v2
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error Validation result.
     */
    private function validate_recaptcha_v2( $request ) {
        $response_token = $request->get_param( 'g-recaptcha-response' );

        if ( empty( $response_token ) ) {
            return new WP_Error(
                'captcha_missing',
                __( 'Please complete the CAPTCHA.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        $secret_key = $this->settings->get( 'recaptcha_secret_key' );

        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $response_token,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'captcha_error',
                __( 'CAPTCHA verification failed. Please try again.', 'bits-accessible-forms' ),
                array( 'status' => 500 )
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) ) {
            return new WP_Error(
                'captcha_invalid',
                __( 'CAPTCHA verification failed.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Validate reCAPTCHA v3
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error Validation result.
     */
    private function validate_recaptcha_v3( $request ) {
        $response_token = $request->get_param( 'g-recaptcha-response' );

        if ( empty( $response_token ) ) {
            return new WP_Error(
                'captcha_missing',
                __( 'Security token missing.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        $secret_key = $this->settings->get( 'recaptcha_v3_secret_key' );
        $threshold = $this->settings->get( 'recaptcha_v3_threshold', 0.5 );

        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $response_token,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'captcha_error',
                __( 'Security verification failed. Please try again.', 'bits-accessible-forms' ),
                array( 'status' => 500 )
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) || $body['score'] < $threshold ) {
            return new WP_Error(
                'captcha_invalid',
                __( 'Security verification failed.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Validate hCaptcha
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error Validation result.
     */
    private function validate_hcaptcha( $request ) {
        $response_token = $request->get_param( 'h-captcha-response' );

        if ( empty( $response_token ) ) {
            return new WP_Error(
                'captcha_missing',
                __( 'Please complete the CAPTCHA.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        $secret_key = $this->settings->get( 'hcaptcha_secret_key' );

        $response = wp_remote_post( 'https://hcaptcha.com/siteverify', array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $response_token,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'captcha_error',
                __( 'CAPTCHA verification failed. Please try again.', 'bits-accessible-forms' ),
                array( 'status' => 500 )
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) ) {
            return new WP_Error(
                'captcha_invalid',
                __( 'CAPTCHA verification failed.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Validate Cloudflare Turnstile
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error Validation result.
     */
    private function validate_turnstile( $request ) {
        $response_token = $request->get_param( 'cf-turnstile-response' );

        if ( empty( $response_token ) ) {
            return new WP_Error(
                'captcha_missing',
                __( 'Please complete the security check.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        $secret_key = $this->settings->get( 'turnstile_secret_key' );

        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret'   => $secret_key,
                'response' => $response_token,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'captcha_error',
                __( 'Security verification failed. Please try again.', 'bits-accessible-forms' ),
                array( 'status' => 500 )
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) ) {
            return new WP_Error(
                'captcha_invalid',
                __( 'Security verification failed.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Validate math CAPTCHA
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error Validation result.
     */
    private function validate_math_captcha( $request ) {
        $answer = $request->get_param( 'baf_math_answer' );
        $expected_hash = $request->get_param( 'baf_math_hash' );

        if ( empty( $answer ) || empty( $expected_hash ) ) {
            return new WP_Error(
                'captcha_missing',
                __( 'Please solve the math problem.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        // Verify answer matches hash
        $calculated_hash = wp_hash( $answer . wp_salt() );

        if ( ! hash_equals( $expected_hash, $calculated_hash ) ) {
            return new WP_Error(
                'captcha_invalid',
                __( 'Incorrect answer. Please try again.', 'bits-accessible-forms' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Render CAPTCHA for form
     *
     * @param int $form_id Form ID.
     * @return string HTML output.
     */
    public function render( $form_id ) {
        $provider = $this->settings->get( 'captcha_provider', 'recaptcha_v2' );

        switch ( $provider ) {
            case 'recaptcha_v2':
                return $this->render_recaptcha_v2();

            case 'recaptcha_v3':
                return $this->render_recaptcha_v3( $form_id );

            case 'hcaptcha':
                return $this->render_hcaptcha();

            case 'turnstile':
                return $this->render_turnstile();

            case 'math':
                return $this->render_math_captcha();

            default:
                return '';
        }
    }

    /**
     * Render reCAPTCHA v2
     *
     * @return string HTML output.
     */
    private function render_recaptcha_v2() {
        $site_key = $this->settings->get( 'recaptcha_site_key' );

        return sprintf(
            '<div class="baf-captcha baf-recaptcha-v2" data-sitekey="%s"></div>',
            esc_attr( $site_key )
        );
    }

    /**
     * Render reCAPTCHA v3
     *
     * @param int $form_id Form ID.
     * @return string HTML output.
     */
    private function render_recaptcha_v3( $form_id ) {
        $site_key = $this->settings->get( 'recaptcha_v3_site_key' );

        return sprintf(
            '<input type="hidden" name="g-recaptcha-response" class="baf-recaptcha-v3-response" data-sitekey="%s" data-action="submit_form_%d" />',
            esc_attr( $site_key ),
            intval( $form_id )
        );
    }

    /**
     * Render hCaptcha
     *
     * @return string HTML output.
     */
    private function render_hcaptcha() {
        $site_key = $this->settings->get( 'hcaptcha_site_key' );

        return sprintf(
            '<div class="baf-captcha baf-hcaptcha h-captcha" data-sitekey="%s"></div>',
            esc_attr( $site_key )
        );
    }

    /**
     * Render Turnstile
     *
     * @return string HTML output.
     */
    private function render_turnstile() {
        $site_key = $this->settings->get( 'turnstile_site_key' );

        return sprintf(
            '<div class="baf-captcha baf-turnstile cf-turnstile" data-sitekey="%s"></div>',
            esc_attr( $site_key )
        );
    }

    /**
     * Render math CAPTCHA
     *
     * @return string HTML output.
     */
    private function render_math_captcha() {
        $num1 = wp_rand( 1, 10 );
        $num2 = wp_rand( 1, 10 );
        $answer = $num1 + $num2;
        $hash = wp_hash( $answer . wp_salt() );

        return sprintf(
            '<div class="baf-captcha baf-math-captcha">
                <label for="baf_math_answer">%s %d + %d = </label>
                <input type="text" name="baf_math_answer" id="baf_math_answer" required 
                       aria-label="%s" aria-describedby="baf_math_desc" autocomplete="off" />
                <span id="baf_math_desc" class="screen-reader-text">%s</span>
                <input type="hidden" name="baf_math_hash" value="%s" />
            </div>',
            esc_html__( 'Solve:', 'bits-accessible-forms' ),
            $num1,
            $num2,
            esc_attr( sprintf( __( 'What is %d plus %d?', 'bits-accessible-forms' ), $num1, $num2 ) ),
            esc_html__( 'Enter the sum of the two numbers shown.', 'bits-accessible-forms' ),
            esc_attr( $hash )
        );
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
     * Enqueue CAPTCHA scripts
     */
    public function enqueue_scripts() {
        $provider = $this->settings->get( 'captcha_provider', 'recaptcha_v2' );

        switch ( $provider ) {
            case 'recaptcha_v2':
            case 'recaptcha_v3':
                wp_enqueue_script(
                    'baf-recaptcha',
                    'https://www.google.com/recaptcha/api.js',
                    array(),
                    null,
                    true
                );
                break;

            case 'hcaptcha':
                wp_enqueue_script(
                    'baf-hcaptcha',
                    'https://js.hcaptcha.com/1/api.js',
                    array(),
                    null,
                    true
                );
                break;

            case 'turnstile':
                wp_enqueue_script(
                    'baf-turnstile',
                    'https://challenges.cloudflare.com/turnstile/v0/api.js',
                    array(),
                    null,
                    true
                );
                break;
        }
    }
}
