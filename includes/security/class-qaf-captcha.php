<?php
/**
 * CAPTCHA Handler
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Captcha class
 * 
 * Handles CAPTCHA validation for form submissions.
 * Supports multiple providers: reCAPTCHA v2, reCAPTCHA v3, hCaptcha, Turnstile.
 */
class QAF_Captcha {

    /**
     * Settings instance
     *
     * @var QAF_Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = QAF_Settings::get_instance();
    }

    /**
     * Validate CAPTCHA response (the old shape: true or WP_Error).
     *
     * QAF's own submit path uses assess() instead, through
     * QAF_Spam_Captcha_Check, so a failed behavioural check holds the entry
     * rather than refusing it.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public function validate( $request ) {
        $a = $this->assess( $request );
        if ( in_array( $a['outcome'], array( 'pass', 'none' ), true ) ) {
            return true;
        }
        $codes = array(
            'missing'     => array( 'captcha_missing', 400 ),
            'wrong'       => array( 'captcha_invalid', 400 ),
            'failed'      => array( 'captcha_invalid', 400 ),
            'low_score'   => array( 'captcha_invalid', 400 ),
            'unreachable' => array( 'captcha_error', 500 ),
        );
        $c = isset( $codes[ $a['outcome'] ] ) ? $codes[ $a['outcome'] ] : array( 'captcha_invalid', 400 );
        return new WP_Error( $c[0], $a['message'], array( 'status' => $c[1] ) );
    }

    /**
     * What the CAPTCHA says, without deciding anything.
     *
     * @param WP_REST_Request $request Request object.
     * @return array provider, outcome (pass, none, missing, wrong, failed,
     *               low_score, unreachable), message, and for reCAPTCHA v3
     *               score and threshold.
     */
    public function assess( $request ) {
        $provider = $this->settings->get( 'captcha_provider', 'recaptcha_v2' );
        $out      = array( 'provider' => $provider, 'outcome' => 'none', 'message' => '' );

        $services = array(
            'recaptcha_v2' => array( 'g-recaptcha-response', 'recaptcha_secret_key', 'https://www.google.com/recaptcha/api/siteverify' ),
            'recaptcha_v3' => array( 'g-recaptcha-response', 'recaptcha_v3_secret_key', 'https://www.google.com/recaptcha/api/siteverify' ),
            'hcaptcha'     => array( 'h-captcha-response', 'hcaptcha_secret_key', 'https://hcaptcha.com/siteverify' ),
            'turnstile'    => array( 'cf-turnstile-response', 'turnstile_secret_key', 'https://challenges.cloudflare.com/turnstile/v0/siteverify' ),
        );

        if ( 'math' === $provider ) {
            return array_merge( $out, $this->assess_math_captcha( $request ) );
        }
        if ( ! isset( $services[ $provider ] ) ) {
            return $out;
        }

        list( $param, $secret_setting, $url ) = $services[ $provider ];
        $response_token = $request->get_param( $param );

        if ( empty( $response_token ) ) {
            $out['outcome'] = 'missing';
            $out['message'] = 'turnstile' === $provider || 'recaptcha_v3' === $provider
                ? __( 'Security token missing.', 'quill-accessible-forms' )
                : __( 'Please complete the CAPTCHA.', 'quill-accessible-forms' );
            return $out;
        }

        $response = wp_remote_post( $url, array(
            'body' => array(
                'secret'   => $this->settings->get( $secret_setting ),
                'response' => $response_token,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            $out['outcome'] = 'unreachable';
            $out['message'] = __( 'CAPTCHA verification failed. Please try again.', 'quill-accessible-forms' );
            return $out;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) ) {
            $out['outcome'] = 'failed';
            $out['message'] = __( 'CAPTCHA verification failed.', 'quill-accessible-forms' );
            return $out;
        }

        if ( 'recaptcha_v3' === $provider ) {
            $threshold        = (float) $this->settings->get( 'recaptcha_v3_threshold', 0.5 );
            $score            = isset( $body['score'] ) ? (float) $body['score'] : 0.0;
            $out['score']     = $score;
            $out['threshold'] = $threshold;
            if ( $score < $threshold ) {
                $out['outcome'] = 'low_score';
                $out['message'] = __( 'Security verification failed.', 'quill-accessible-forms' );
                return $out;
            }
        }

        $out['outcome'] = 'pass';
        return $out;
    }

    /**
     * The maths question's answer.
     *
     * @param WP_REST_Request $request Request object.
     * @return array outcome and message.
     */
    private function assess_math_captcha( $request ) {
        $answer = $request->get_param( 'qaf_math_answer' );
        $expected_hash = $request->get_param( 'qaf_math_hash' );

        if ( empty( $answer ) || empty( $expected_hash ) ) {
            return array( 'outcome' => 'missing', 'message' => __( 'Please solve the math problem.', 'quill-accessible-forms' ) );
        }

        $calculated_hash = wp_hash( trim( (string) $answer ) . wp_salt() );

        if ( ! hash_equals( (string) $expected_hash, $calculated_hash ) ) {
            return array( 'outcome' => 'wrong', 'message' => __( 'Incorrect answer. Please try again.', 'quill-accessible-forms' ) );
        }

        return array( 'outcome' => 'pass', 'message' => '' );
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
            '<div class="qaf-captcha qaf-recaptcha-v2" data-sitekey="%s"></div>',
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
            '<input type="hidden" name="g-recaptcha-response" class="qaf-recaptcha-v3-response" data-sitekey="%s" data-action="submit_form_%d" />',
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
            '<div class="qaf-captcha qaf-hcaptcha h-captcha" data-sitekey="%s"></div>',
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
            '<div class="qaf-captcha qaf-turnstile cf-turnstile" data-sitekey="%s"></div>',
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
            '<div class="qaf-captcha qaf-math-captcha">
                <label for="qaf_math_answer">%s %d + %d = </label>
                <input type="text" name="qaf_math_answer" id="qaf_math_answer" required 
                       aria-label="%s" aria-describedby="qaf_math_desc" autocomplete="off" />
                <span id="qaf_math_desc" class="screen-reader-text">%s</span>
                <input type="hidden" name="qaf_math_hash" value="%s" />
            </div>',
            esc_html__( 'Solve:', 'quill-accessible-forms' ),
            $num1,
            $num2,
            esc_attr( sprintf( __( 'What is %d plus %d?', 'quill-accessible-forms' ), $num1, $num2 ) ),
            esc_html__( 'Enter the sum of the two numbers shown.', 'quill-accessible-forms' ),
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
                    'qaf-recaptcha',
                    'https://www.google.com/recaptcha/api.js',
                    array(),
                    null,
                    true
                );
                break;

            case 'hcaptcha':
                wp_enqueue_script(
                    'qaf-hcaptcha',
                    'https://js.hcaptcha.com/1/api.js',
                    array(),
                    null,
                    true
                );
                break;

            case 'turnstile':
                wp_enqueue_script(
                    'qaf-turnstile',
                    'https://challenges.cloudflare.com/turnstile/v0/api.js',
                    array(),
                    null,
                    true
                );
                break;
        }
    }
}
