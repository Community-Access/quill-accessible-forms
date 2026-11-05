<?php
/**
 * User Email Field - WordPress user email for registration
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_User_Email class
 */
class QAF_Field_User_Email extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'user_email';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'user';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'label'              => __( 'Email Address', 'quill-accessible-forms' ),
            'description'        => '',
            'placeholder'        => __( 'you@example.com', 'quill-accessible-forms' ),
            'confirm_email'      => true,
            'confirm_label'      => __( 'Confirm Email', 'quill-accessible-forms' ),
            'confirm_placeholder' => __( 'Re-enter your email', 'quill-accessible-forms' ),
            'check_availability' => true,
            'blocked_domains'    => '',
            'allowed_domains'    => '',
            'send_verification'  => false,
            'required'           => true,
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
                'label' => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'confirmation' => array(
                'confirm_email' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Email Confirmation', 'quill-accessible-forms' ),
                ),
                'confirm_label' => array(
                    'type'      => 'text',
                    'label'     => __( 'Confirm Label', 'quill-accessible-forms' ),
                    'condition' => array( 'confirm_email' => true ),
                ),
                'confirm_placeholder' => array(
                    'type'      => 'text',
                    'label'     => __( 'Confirm Placeholder', 'quill-accessible-forms' ),
                    'condition' => array( 'confirm_email' => true ),
                ),
            ),
            'validation' => array(
                'check_availability' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Check If Already Registered', 'quill-accessible-forms' ),
                    'description' => __( 'Verify email is not already in use', 'quill-accessible-forms' ),
                ),
            ),
            'domains' => array(
                'blocked_domains' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Blocked Domains', 'quill-accessible-forms' ),
                    'description' => __( 'One domain per line (e.g., tempmail.com)', 'quill-accessible-forms' ),
                ),
                'allowed_domains' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Allowed Domains Only', 'quill-accessible-forms' ),
                    'description' => __( 'Only allow these domains (leave empty for all)', 'quill-accessible-forms' ),
                ),
            ),
            'verification' => array(
                'send_verification' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Send Verification Email', 'quill-accessible-forms' ),
                    'description' => __( 'Require email verification before account activation', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $label = $this->config['label'] ?? __( 'Email Address', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $value = $this->get_value();
        $required = ! empty( $this->config['required'] );
        $confirm = ! empty( $this->config['confirm_email'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-user-email' );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        if ( ! empty( $this->config['check_availability'] ) ) {
            $html .= ' data-check-availability="true"';
        }
        $html .= '>';

        // Primary email field
        $html .= '<div class="qaf-email-primary">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="qaf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $html .= '<div class="qaf-email-input-wrapper">';
        $html .= '<input type="email"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' autocomplete="email"';
        $html .= ' class="qaf-input qaf-email-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        // Availability indicator
        if ( ! empty( $this->config['check_availability'] ) ) {
            $html .= '<span class="qaf-availability-indicator" data-availability aria-hidden="true"></span>';
        }
        $html .= '</div>';

        // Status
        $html .= '<div class="qaf-email-status" role="status" aria-live="polite"></div>';

        $html .= '</div>'; // .qaf-email-primary

        // Confirmation field
        if ( $confirm ) {
            $confirm_id = $html_id . '-confirm';
            $confirm_label = $this->config['confirm_label'] ?? __( 'Confirm Email', 'quill-accessible-forms' );
            $confirm_placeholder = $this->config['confirm_placeholder'] ?? '';

            $html .= '<div class="qaf-email-confirm">';

            $html .= '<label for="' . esc_attr( $confirm_id ) . '" class="qaf-field-label">';
            $html .= esc_html( $confirm_label );
            if ( $required ) {
                $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
            }
            $html .= '</label>';

            $html .= '<input type="email"';
            $html .= ' id="' . esc_attr( $confirm_id ) . '"';
            $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '_confirm"';
            $html .= ' placeholder="' . esc_attr( $confirm_placeholder ) . '"';
            $html .= ' autocomplete="email"';
            $html .= ' class="qaf-input qaf-email-confirm-input"';
            $html .= ' aria-describedby="' . esc_attr( $confirm_id . '-match' ) . '"';
            if ( $required ) {
                $html .= ' aria-required="true"';
            }
            $html .= '>';

            $html .= '<div id="' . esc_attr( $confirm_id . '-match' ) . '" class="qaf-email-match" role="status" aria-live="polite"></div>';

            $html .= '</div>'; // .qaf-email-confirm
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = trim( $this->get_value() );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter an email address.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $value ) ) {
            // Valid email format
            if ( ! is_email( $value ) ) {
                $this->add_error( __( 'Please enter a valid email address.', 'quill-accessible-forms' ) );
                return false;
            }

            // Check domain restrictions
            $domain = strtolower( substr( strrchr( $value, '@' ), 1 ) );

            // Blocked domains
            if ( ! empty( $this->config['blocked_domains'] ) ) {
                $blocked = array_map( 'trim', array_filter( explode( "\n", strtolower( $this->config['blocked_domains'] ) ) ) );
                if ( in_array( $domain, $blocked ) ) {
                    $this->add_error( __( 'Email addresses from this domain are not allowed.', 'quill-accessible-forms' ) );
                    return false;
                }
            }

            // Allowed domains
            if ( ! empty( $this->config['allowed_domains'] ) ) {
                $allowed = array_map( 'trim', array_filter( explode( "\n", strtolower( $this->config['allowed_domains'] ) ) ) );
                if ( ! in_array( $domain, $allowed ) ) {
                    $this->add_error( __( 'Please use an email address from an allowed domain.', 'quill-accessible-forms' ) );
                    return false;
                }
            }

            // Check if email exists
            if ( ! empty( $this->config['check_availability'] ) && email_exists( $value ) ) {
                $this->add_error( __( 'This email address is already registered.', 'quill-accessible-forms' ) );
                return false;
            }

            // Check confirmation
            if ( ! empty( $this->config['confirm_email'] ) ) {
                $confirm = isset( $_POST[ 'qaf_field_' . $this->get_id() . '_confirm' ] )
                    ? sanitize_email( $_POST[ 'qaf_field_' . $this->get_id() . '_confirm' ] )
                    : '';

                if ( strtolower( $value ) !== strtolower( $confirm ) ) {
                    $this->add_error( __( 'Email addresses do not match.', 'quill-accessible-forms' ) );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized email.
     */
    public function sanitize( $value ) {
        return sanitize_email( trim( $value ) );
    }

    /**
     * Get user data key
     *
     * @return string User array key.
     */
    public function get_user_key() {
        return 'user_email';
    }
}
