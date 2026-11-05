<?php
/**
 * Password Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Password class
 */
class QAF_Field_Password extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'password';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'standard';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'placeholder'        => '',
            'min_length'         => 8,
            'require_uppercase'  => true,
            'require_lowercase'  => true,
            'require_number'     => true,
            'require_special'    => true,
            'show_strength'      => true,
            'enable_confirm'     => true,
            'show_toggle'        => true,
        ) );
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
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
                'require_uppercase' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Uppercase Letter', 'quill-accessible-forms' ),
                ),
                'require_lowercase' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Lowercase Letter', 'quill-accessible-forms' ),
                ),
                'require_number' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Number', 'quill-accessible-forms' ),
                ),
                'require_special' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Special Character', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'show_strength' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Strength Meter', 'quill-accessible-forms' ),
                ),
                'enable_confirm' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable Confirm Password', 'quill-accessible-forms' ),
                ),
                'show_toggle' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Password Toggle', 'quill-accessible-forms' ),
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
        $name    = 'qaf_field_' . $this->get_id();
        $min_len = (int) $this->config['min_length'];

        // Build requirements description for screen readers
        $requirements = $this->get_requirements_text();
        $req_id = $html_id . '-requirements';

        $wrapper_class = $this->get_wrapper_classes();
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Password requirements hint
        $html .= '<div id="' . esc_attr( $req_id ) . '" class="qaf-password-requirements">';
        $html .= esc_html( $requirements );
        $html .= '</div>';

        // Password input wrapper
        $html .= '<div class="qaf-password-input-wrapper">';
        
        // Main password input
        $attrs = array(
            'type'            => 'password',
            'id'              => $html_id,
            'name'            => $name,
            'class'           => 'qaf-password-input',
            'autocomplete'    => 'new-password',
            'minlength'       => $min_len,
            'aria-describedby' => $req_id,
        );

        if ( ! empty( $this->config['placeholder'] ) ) {
            $attrs['placeholder'] = $this->config['placeholder'];
        }

        if ( $this->is_required() ) {
            $attrs['required'] = 'required';
            $attrs['aria-required'] = 'true';
        }

        if ( ! empty( $this->errors ) ) {
            $attrs['aria-invalid'] = 'true';
            $attrs['aria-describedby'] .= ' ' . $html_id . '-error';
        }

        $html .= '<input ' . $this->build_attributes( $attrs ) . '>';

        // Password visibility toggle
        if ( ! empty( $this->config['show_toggle'] ) ) {
            $html .= '<button type="button" class="qaf-password-toggle" ';
            $html .= 'aria-label="' . esc_attr__( 'Show password', 'quill-accessible-forms' ) . '" ';
            $html .= 'aria-pressed="false" ';
            $html .= 'data-target="' . esc_attr( $html_id ) . '">';
            $html .= '<span class="qaf-icon-eye" aria-hidden="true"></span>';
            $html .= '<span class="qaf-icon-eye-off" aria-hidden="true" style="display:none;"></span>';
            $html .= '</button>';
        }

        $html .= '</div>';

        // Strength meter
        if ( ! empty( $this->config['show_strength'] ) ) {
            $html .= '<div class="qaf-password-strength" role="status" aria-live="polite">';
            $html .= '<div class="qaf-strength-meter">';
            $html .= '<div class="qaf-strength-fill" style="width: 0%"></div>';
            $html .= '</div>';
            $html .= '<span class="qaf-strength-text"></span>';
            $html .= '</div>';
        }

        // Confirm password field
        if ( ! empty( $this->config['enable_confirm'] ) ) {
            $confirm_id = $html_id . '-confirm';
            
            $html .= '<div class="qaf-confirm-password-wrapper">';
            $html .= '<label for="' . esc_attr( $confirm_id ) . '" class="qaf-field-label">';
            $html .= esc_html__( 'Confirm Password', 'quill-accessible-forms' );
            if ( $this->is_required() ) {
                $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
            }
            $html .= '</label>';

            $html .= '<div class="qaf-password-input-wrapper">';
            $html .= '<input type="password"';
            $html .= ' id="' . esc_attr( $confirm_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '_confirm"';
            $html .= ' class="qaf-password-input qaf-password-confirm"';
            $html .= ' autocomplete="new-password"';
            $html .= ' aria-describedby="' . esc_attr( $confirm_id ) . '-status"';
            if ( $this->is_required() ) {
                $html .= ' required aria-required="true"';
            }
            $html .= '>';

            if ( ! empty( $this->config['show_toggle'] ) ) {
                $html .= '<button type="button" class="qaf-password-toggle" ';
                $html .= 'aria-label="' . esc_attr__( 'Show password', 'quill-accessible-forms' ) . '" ';
                $html .= 'aria-pressed="false" ';
                $html .= 'data-target="' . esc_attr( $confirm_id ) . '">';
                $html .= '<span class="qaf-icon-eye" aria-hidden="true"></span>';
                $html .= '<span class="qaf-icon-eye-off" aria-hidden="true" style="display:none;"></span>';
                $html .= '</button>';
            }

            $html .= '</div>';

            // Match status
            $html .= '<div id="' . esc_attr( $confirm_id ) . '-status" class="qaf-password-match-status" role="status" aria-live="polite"></div>';
            $html .= '</div>';
        }

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
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
     * Get password requirements text
     *
     * @return string Requirements description.
     */
    protected function get_requirements_text() {
        $requirements = array();
        $min = (int) $this->config['min_length'];

        $requirements[] = sprintf(
            __( 'At least %d characters', 'quill-accessible-forms' ),
            $min
        );

        if ( ! empty( $this->config['require_uppercase'] ) ) {
            $requirements[] = __( 'one uppercase letter', 'quill-accessible-forms' );
        }

        if ( ! empty( $this->config['require_lowercase'] ) ) {
            $requirements[] = __( 'one lowercase letter', 'quill-accessible-forms' );
        }

        if ( ! empty( $this->config['require_number'] ) ) {
            $requirements[] = __( 'one number', 'quill-accessible-forms' );
        }

        if ( ! empty( $this->config['require_special'] ) ) {
            $requirements[] = __( 'one special character (!@#$%^&*)', 'quill-accessible-forms' );
        }

        return __( 'Password must contain: ', 'quill-accessible-forms' ) . implode( ', ', $requirements );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value (passwords are not modified).
     */
    public function sanitize( $value ) {
        // Passwords should not be sanitized to preserve special characters
        return $value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $min = (int) $this->config['min_length'];

        if ( strlen( $value ) < $min ) {
            $this->add_error( sprintf(
                __( 'Password must be at least %d characters long.', 'quill-accessible-forms' ),
                $min
            ) );
            return;
        }

        if ( ! empty( $this->config['require_uppercase'] ) && ! preg_match( '/[A-Z]/', $value ) ) {
            $this->add_error( __( 'Password must contain at least one uppercase letter.', 'quill-accessible-forms' ) );
        }

        if ( ! empty( $this->config['require_lowercase'] ) && ! preg_match( '/[a-z]/', $value ) ) {
            $this->add_error( __( 'Password must contain at least one lowercase letter.', 'quill-accessible-forms' ) );
        }

        if ( ! empty( $this->config['require_number'] ) && ! preg_match( '/[0-9]/', $value ) ) {
            $this->add_error( __( 'Password must contain at least one number.', 'quill-accessible-forms' ) );
        }

        if ( ! empty( $this->config['require_special'] ) && ! preg_match( '/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $value ) ) {
            $this->add_error( __( 'Password must contain at least one special character.', 'quill-accessible-forms' ) );
        }

        // Check confirm password match
        if ( ! empty( $this->config['enable_confirm'] ) ) {
            $confirm_key = 'qaf_field_' . $this->get_id() . '_confirm';
            $confirm = isset( $_POST[ $confirm_key ] ) ? $_POST[ $confirm_key ] : '';
            
            if ( $value !== $confirm ) {
                $this->add_error( __( 'Passwords do not match.', 'quill-accessible-forms' ) );
            }
        }
    }

    /**
     * Format value for display (masked)
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        return '••••••••';
    }
}
