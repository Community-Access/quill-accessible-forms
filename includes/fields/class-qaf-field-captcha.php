<?php
/**
 * CAPTCHA Field - Accessible anti-spam verification
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Captcha class
 */
class QAF_Field_Captcha extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'captcha';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'captcha_type'       => 'math',
            'recaptcha_site_key' => '',
            'recaptcha_version'  => 'v2',
            'recaptcha_theme'    => 'light',
            'recaptcha_size'     => 'normal',
            'hcaptcha_site_key'  => '',
            'turnstile_site_key' => '',
            'math_min'           => 1,
            'math_max'           => 10,
            'math_operation'     => 'add',
            'honeypot_enabled'   => true,
            'conditional'        => array(),
            'css_class'          => '',
        );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'captcha_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'CAPTCHA Type', 'quill-accessible-forms' ),
                    'desc'    => __( 'Math challenges are most accessible. reCAPTCHA v3 is invisible.', 'quill-accessible-forms' ),
                    'options' => array(
                        'math'        => __( 'Math Challenge (Accessible)', 'quill-accessible-forms' ),
                        'recaptcha'   => __( 'Google reCAPTCHA', 'quill-accessible-forms' ),
                        'hcaptcha'    => __( 'hCaptcha', 'quill-accessible-forms' ),
                        'turnstile'   => __( 'Cloudflare Turnstile', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'math' => array(
                'math_min' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Number', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 100,
                ),
                'math_max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Number', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 100,
                ),
                'math_operation' => array(
                    'type'    => 'select',
                    'label'   => __( 'Operation', 'quill-accessible-forms' ),
                    'options' => array(
                        'add'      => __( 'Addition (+)', 'quill-accessible-forms' ),
                        'subtract' => __( 'Subtraction (-)', 'quill-accessible-forms' ),
                        'multiply' => __( 'Multiplication (×)', 'quill-accessible-forms' ),
                        'random'   => __( 'Random operation', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'recaptcha' => array(
                'recaptcha_site_key' => array(
                    'type'  => 'text',
                    'label' => __( 'reCAPTCHA Site Key', 'quill-accessible-forms' ),
                ),
                'recaptcha_version' => array(
                    'type'    => 'select',
                    'label'   => __( 'reCAPTCHA Version', 'quill-accessible-forms' ),
                    'options' => array(
                        'v2'          => __( 'v2 Checkbox', 'quill-accessible-forms' ),
                        'v2_invisible' => __( 'v2 Invisible', 'quill-accessible-forms' ),
                        'v3'          => __( 'v3 (Invisible)', 'quill-accessible-forms' ),
                    ),
                ),
                'recaptcha_theme' => array(
                    'type'    => 'select',
                    'label'   => __( 'Theme', 'quill-accessible-forms' ),
                    'options' => array(
                        'light' => __( 'Light', 'quill-accessible-forms' ),
                        'dark'  => __( 'Dark', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'hcaptcha' => array(
                'hcaptcha_site_key' => array(
                    'type'  => 'text',
                    'label' => __( 'hCaptcha Site Key', 'quill-accessible-forms' ),
                ),
            ),
            'turnstile' => array(
                'turnstile_site_key' => array(
                    'type'  => 'text',
                    'label' => __( 'Turnstile Site Key', 'quill-accessible-forms' ),
                ),
            ),
            'extra' => array(
                'honeypot_enabled' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable Honeypot', 'quill-accessible-forms' ),
                    'desc'  => __( 'Adds invisible spam trap field.', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the CAPTCHA field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $captcha_type = $this->config['captcha_type'] ?? 'math';

        switch ( $captcha_type ) {
            case 'recaptcha':
                return $this->render_recaptcha();
            case 'hcaptcha':
                return $this->render_hcaptcha();
            case 'turnstile':
                return $this->render_turnstile();
            case 'math':
            default:
                return $this->render_math();
        }
    }

    /**
     * Render accessible math CAPTCHA
     *
     * @return string HTML.
     */
    protected function render_math() {
        $html_id = $this->get_html_id();
        $name    = 'qaf_field_' . $this->get_id();
        $min     = (int) $this->config['math_min'];
        $max     = (int) $this->config['math_max'];
        $operation = $this->config['math_operation'] ?? 'add';

        // Generate challenge
        $challenge = $this->generate_math_challenge( $min, $max, $operation );
        
        // Store expected answer in session/transient
        $token = wp_generate_uuid4();
        set_transient( 'qaf_captcha_' . $token, $challenge['answer'], HOUR_IN_SECONDS );

        $wrapper_class = array( 'qaf-field-captcha', 'qaf-captcha-math' );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Honeypot (invisible spam field)
        if ( ! empty( $this->config['honeypot_enabled'] ) ) {
            $html .= $this->render_honeypot();
        }

        // Label with math question
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
        $html .= '<span class="qaf-label-text">';
        $html .= sprintf(
            /* translators: Math challenge question */
            esc_html__( 'Anti-spam: What is %s?', 'quill-accessible-forms' ),
            $challenge['question']
        );
        $html .= '</span>';
        $html .= '<span class="qaf-required-indicator" aria-hidden="true">*</span>';
        $html .= '</label>';

        // Description for screen readers
        $html .= '<p id="' . esc_attr( $html_id ) . '-desc" class="screen-reader-text">';
        $html .= esc_html__( 'Please solve this simple math problem to verify you are human.', 'quill-accessible-forms' );
        $html .= '</p>';

        // Input for answer
        $html .= '<input type="text" id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' class="qaf-captcha-input"';
        $html .= ' inputmode="numeric"';
        $html .= ' pattern="[0-9]*"';
        $html .= ' autocomplete="off"';
        $html .= ' aria-required="true"';
        $html .= ' aria-describedby="' . esc_attr( $html_id ) . '-desc">';

        // Hidden token
        $html .= '<input type="hidden" name="' . esc_attr( $name ) . '_token" value="' . esc_attr( $token ) . '">';

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate math challenge
     *
     * @param int    $min       Minimum number.
     * @param int    $max       Maximum number.
     * @param string $operation Operation type.
     * @return array Question and answer.
     */
    protected function generate_math_challenge( $min, $max, $operation ) {
        $num1 = wp_rand( $min, $max );
        $num2 = wp_rand( $min, $max );

        // Select operation
        if ( $operation === 'random' ) {
            $ops = array( 'add', 'subtract', 'multiply' );
            $operation = $ops[ wp_rand( 0, 2 ) ];
        }

        switch ( $operation ) {
            case 'subtract':
                // Ensure positive result
                if ( $num1 < $num2 ) {
                    list( $num1, $num2 ) = array( $num2, $num1 );
                }
                $question = $num1 . ' − ' . $num2;
                $answer = $num1 - $num2;
                break;

            case 'multiply':
                // Use smaller numbers for multiplication
                $num1 = wp_rand( 1, min( 10, $max ) );
                $num2 = wp_rand( 1, min( 10, $max ) );
                $question = $num1 . ' × ' . $num2;
                $answer = $num1 * $num2;
                break;

            case 'add':
            default:
                $question = $num1 . ' + ' . $num2;
                $answer = $num1 + $num2;
                break;
        }

        return array(
            'question' => $question,
            'answer'   => $answer,
        );
    }

    /**
     * Render honeypot field
     *
     * @return string HTML.
     */
    protected function render_honeypot() {
        $hp_name = 'qaf_hp_' . $this->get_id();
        
        // Visually hidden but accessible to bots
        $html = '<div class="qaf-hp-field" aria-hidden="true" style="position: absolute; left: -9999px; top: -9999px; opacity: 0; height: 0; width: 0; overflow: hidden;">';
        $html .= '<label for="' . esc_attr( $hp_name ) . '">' . esc_html__( 'Leave this empty', 'quill-accessible-forms' ) . '</label>';
        $html .= '<input type="text" id="' . esc_attr( $hp_name ) . '" name="' . esc_attr( $hp_name ) . '" value="" tabindex="-1" autocomplete="off">';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render Google reCAPTCHA
     *
     * @return string HTML.
     */
    protected function render_recaptcha() {
        $html_id = $this->get_html_id();
        $site_key = $this->config['recaptcha_site_key'] ?? '';
        $version = $this->config['recaptcha_version'] ?? 'v2';
        $theme = $this->config['recaptcha_theme'] ?? 'light';
        $size = $this->config['recaptcha_size'] ?? 'normal';

        if ( empty( $site_key ) ) {
            return '<p class="qaf-captcha-error">' . esc_html__( 'reCAPTCHA site key not configured.', 'quill-accessible-forms' ) . '</p>';
        }

        $wrapper_class = array( 'qaf-field-captcha', 'qaf-captcha-recaptcha', 'qaf-recaptcha-' . $version );

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        if ( $version === 'v2' ) {
            $html .= '<div id="' . esc_attr( $html_id ) . '" class="g-recaptcha"';
            $html .= ' data-sitekey="' . esc_attr( $site_key ) . '"';
            $html .= ' data-theme="' . esc_attr( $theme ) . '"';
            $html .= ' data-size="' . esc_attr( $size ) . '">';
            $html .= '</div>';
        } elseif ( $version === 'v2_invisible' ) {
            $html .= '<div id="' . esc_attr( $html_id ) . '" class="g-recaptcha"';
            $html .= ' data-sitekey="' . esc_attr( $site_key ) . '"';
            $html .= ' data-size="invisible">';
            $html .= '</div>';
        } else {
            // v3 is rendered via JS, store token in hidden field
            $html .= '<input type="hidden" id="' . esc_attr( $html_id ) . '" name="g-recaptcha-response" class="qaf-recaptcha-v3"';
            $html .= ' data-sitekey="' . esc_attr( $site_key ) . '">';
        }

        // Honeypot
        if ( ! empty( $this->config['honeypot_enabled'] ) ) {
            $html .= $this->render_honeypot();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render hCaptcha
     *
     * @return string HTML.
     */
    protected function render_hcaptcha() {
        $html_id = $this->get_html_id();
        $site_key = $this->config['hcaptcha_site_key'] ?? '';

        if ( empty( $site_key ) ) {
            return '<p class="qaf-captcha-error">' . esc_html__( 'hCaptcha site key not configured.', 'quill-accessible-forms' ) . '</p>';
        }

        $wrapper_class = array( 'qaf-field-captcha', 'qaf-captcha-hcaptcha' );

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        $html .= '<div id="' . esc_attr( $html_id ) . '" class="h-captcha"';
        $html .= ' data-sitekey="' . esc_attr( $site_key ) . '">';
        $html .= '</div>';

        // Honeypot
        if ( ! empty( $this->config['honeypot_enabled'] ) ) {
            $html .= $this->render_honeypot();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render Cloudflare Turnstile
     *
     * @return string HTML.
     */
    protected function render_turnstile() {
        $html_id = $this->get_html_id();
        $site_key = $this->config['turnstile_site_key'] ?? '';

        if ( empty( $site_key ) ) {
            return '<p class="qaf-captcha-error">' . esc_html__( 'Turnstile site key not configured.', 'quill-accessible-forms' ) . '</p>';
        }

        $wrapper_class = array( 'qaf-field-captcha', 'qaf-captcha-turnstile' );

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        $html .= '<div id="' . esc_attr( $html_id ) . '" class="cf-turnstile"';
        $html .= ' data-sitekey="' . esc_attr( $site_key ) . '">';
        $html .= '</div>';

        // Honeypot
        if ( ! empty( $this->config['honeypot_enabled'] ) ) {
            $html .= $this->render_honeypot();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Validate CAPTCHA
     *
     * @return bool True if valid.
     */
    public function validate() {
        $captcha_type = $this->config['captcha_type'] ?? 'math';
        $name = 'qaf_field_' . $this->get_id();

        // Check honeypot first
        if ( ! empty( $this->config['honeypot_enabled'] ) ) {
            $hp_name = 'qaf_hp_' . $this->get_id();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( ! empty( $_POST[ $hp_name ] ) ) {
                // Silently fail - it's a bot
                $this->add_error( __( 'Spam detected.', 'quill-accessible-forms' ) );
                return false;
            }
        }

        switch ( $captcha_type ) {
            case 'math':
                return $this->validate_math();
            case 'recaptcha':
                return $this->validate_recaptcha();
            case 'hcaptcha':
                return $this->validate_hcaptcha();
            case 'turnstile':
                return $this->validate_turnstile();
        }

        return true;
    }

    /**
     * Validate math CAPTCHA
     *
     * @return bool True if valid.
     */
    protected function validate_math() {
        $name = 'qaf_field_' . $this->get_id();
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $answer = isset( $_POST[ $name ] ) ? (int) $_POST[ $name ] : null;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $token = isset( $_POST[ $name . '_token' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name . '_token' ] ) ) : '';

        if ( empty( $token ) ) {
            $this->add_error( __( 'Invalid CAPTCHA token.', 'quill-accessible-forms' ) );
            return false;
        }

        $expected = get_transient( 'qaf_captcha_' . $token );
        delete_transient( 'qaf_captcha_' . $token );

        if ( $expected === false ) {
            $this->add_error( __( 'CAPTCHA expired. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( $answer !== (int) $expected ) {
            $this->add_error( __( 'Incorrect answer. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        return true;
    }

    /**
     * Validate reCAPTCHA
     *
     * @return bool True if valid.
     */
    protected function validate_recaptcha() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

        if ( empty( $response ) ) {
            $this->add_error( __( 'Please complete the reCAPTCHA.', 'quill-accessible-forms' ) );
            return false;
        }

        $secret = get_option( 'qaf_recaptcha_secret_key', '' );
        
        if ( empty( $secret ) ) {
            // If no secret configured, allow through (admin error)
            return true;
        }

        $verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $secret,
                'response' => $response,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $verify ) ) {
            $this->add_error( __( 'Could not verify reCAPTCHA. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        $result = json_decode( wp_remote_retrieve_body( $verify ), true );

        if ( empty( $result['success'] ) ) {
            $this->add_error( __( 'reCAPTCHA verification failed. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        return true;
    }

    /**
     * Validate hCaptcha
     *
     * @return bool True if valid.
     */
    protected function validate_hcaptcha() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $response = isset( $_POST['h-captcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ) ) : '';

        if ( empty( $response ) ) {
            $this->add_error( __( 'Please complete the hCaptcha.', 'quill-accessible-forms' ) );
            return false;
        }

        $secret = get_option( 'qaf_hcaptcha_secret_key', '' );
        
        if ( empty( $secret ) ) {
            return true;
        }

        $verify = wp_remote_post( 'https://hcaptcha.com/siteverify', array(
            'body' => array(
                'secret'   => $secret,
                'response' => $response,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $verify ) ) {
            $this->add_error( __( 'Could not verify hCaptcha. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        $result = json_decode( wp_remote_retrieve_body( $verify ), true );

        if ( empty( $result['success'] ) ) {
            $this->add_error( __( 'hCaptcha verification failed. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        return true;
    }

    /**
     * Validate Cloudflare Turnstile
     *
     * @return bool True if valid.
     */
    protected function validate_turnstile() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $response = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';

        if ( empty( $response ) ) {
            $this->add_error( __( 'Please complete the verification.', 'quill-accessible-forms' ) );
            return false;
        }

        $secret = get_option( 'qaf_turnstile_secret_key', '' );
        
        if ( empty( $secret ) ) {
            return true;
        }

        $verify = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret'   => $secret,
                'response' => $response,
                'remoteip' => $this->get_client_ip(),
            ),
        ) );

        if ( is_wp_error( $verify ) ) {
            $this->add_error( __( 'Could not verify. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        $result = json_decode( wp_remote_retrieve_body( $verify ), true );

        if ( empty( $result['success'] ) ) {
            $this->add_error( __( 'Verification failed. Please try again.', 'quill-accessible-forms' ) );
            return false;
        }

        return true;
    }

    /**
     * Get client IP address
     *
     * @return string IP address.
     */
    protected function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return $ip;
    }

    /**
     * CAPTCHA doesn't store values
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }
}
