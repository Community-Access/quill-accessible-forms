<?php
/**
 * User Password Field - WordPress user password for registration
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_User_Password class
 */
class BAF_Field_User_Password extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'user_password';

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
            'label'                => __( 'Password', 'bits-accessible-forms' ),
            'description'          => '',
            'placeholder'          => __( 'Create a password', 'bits-accessible-forms' ),
            'confirm_password'     => true,
            'confirm_label'        => __( 'Confirm Password', 'bits-accessible-forms' ),
            'confirm_placeholder'  => __( 'Re-enter your password', 'bits-accessible-forms' ),
            'show_toggle'          => true,
            'show_strength'        => true,
            'show_generator'       => true,
            'min_length'           => 8,
            'max_length'           => 128,
            'require_uppercase'    => true,
            'require_lowercase'    => true,
            'require_number'       => true,
            'require_special'      => true,
            'min_strength'         => 'medium',
            'show_requirements'    => true,
            'auto_generate'        => false,
            'required'             => true,
            'conditional'          => array(),
            'css_class'            => '',
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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'confirmation' => array(
                'confirm_password' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Password Confirmation', 'bits-accessible-forms' ),
                ),
                'confirm_label' => array(
                    'type'      => 'text',
                    'label'     => __( 'Confirm Label', 'bits-accessible-forms' ),
                    'condition' => array( 'confirm_password' => true ),
                ),
                'confirm_placeholder' => array(
                    'type'      => 'text',
                    'label'     => __( 'Confirm Placeholder', 'bits-accessible-forms' ),
                    'condition' => array( 'confirm_password' => true ),
                ),
            ),
            'display' => array(
                'show_toggle' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Show/Hide Toggle', 'bits-accessible-forms' ),
                    'description' => __( 'Button to reveal/hide password', 'bits-accessible-forms' ),
                ),
                'show_strength' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Strength Meter', 'bits-accessible-forms' ),
                ),
                'show_requirements' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Requirements Checklist', 'bits-accessible-forms' ),
                ),
                'show_generator' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Show Password Generator', 'bits-accessible-forms' ),
                    'description' => __( 'Button to generate secure password', 'bits-accessible-forms' ),
                ),
            ),
            'strength' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'bits-accessible-forms' ),
                    'min'   => 4,
                    'max'   => 128,
                ),
                'require_uppercase' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Uppercase Letter', 'bits-accessible-forms' ),
                ),
                'require_lowercase' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Lowercase Letter', 'bits-accessible-forms' ),
                ),
                'require_number' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Number', 'bits-accessible-forms' ),
                ),
                'require_special' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Special Character', 'bits-accessible-forms' ),
                ),
                'min_strength' => array(
                    'type'    => 'select',
                    'label'   => __( 'Minimum Strength', 'bits-accessible-forms' ),
                    'options' => array(
                        'weak'   => __( 'Weak (any)', 'bits-accessible-forms' ),
                        'medium' => __( 'Medium', 'bits-accessible-forms' ),
                        'strong' => __( 'Strong', 'bits-accessible-forms' ),
                    ),
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
        $label = $this->config['label'] ?? __( 'Password', 'bits-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $required = ! empty( $this->config['required'] );
        $confirm = ! empty( $this->config['confirm_password'] );
        $min_length = (int) ( $this->config['min_length'] ?? 8 );

        $wrapper_class = array( 'baf-field', 'baf-field-user-password' );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-min-length="' . esc_attr( $min_length ) . '"';
        $html .= ' data-require-upper="' . ( ! empty( $this->config['require_uppercase'] ) ? 'true' : 'false' ) . '"';
        $html .= ' data-require-lower="' . ( ! empty( $this->config['require_lowercase'] ) ? 'true' : 'false' ) . '"';
        $html .= ' data-require-number="' . ( ! empty( $this->config['require_number'] ) ? 'true' : 'false' ) . '"';
        $html .= ' data-require-special="' . ( ! empty( $this->config['require_special'] ) ? 'true' : 'false' ) . '"';
        $html .= '>';

        // Primary password field
        $html .= '<div class="baf-password-primary">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="baf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        // Requirements checklist
        if ( ! empty( $this->config['show_requirements'] ) ) {
            $req_id = $html_id . '-requirements';
            $html .= '<ul id="' . esc_attr( $req_id ) . '" class="baf-password-requirements" aria-label="' . esc_attr__( 'Password requirements', 'bits-accessible-forms' ) . '">';
            $html .= '<li data-req="length"><span class="baf-req-icon" aria-hidden="true"></span>' . sprintf( esc_html__( 'At least %d characters', 'bits-accessible-forms' ), $min_length ) . '</li>';
            if ( ! empty( $this->config['require_uppercase'] ) ) {
                $html .= '<li data-req="upper"><span class="baf-req-icon" aria-hidden="true"></span>' . esc_html__( 'One uppercase letter', 'bits-accessible-forms' ) . '</li>';
            }
            if ( ! empty( $this->config['require_lowercase'] ) ) {
                $html .= '<li data-req="lower"><span class="baf-req-icon" aria-hidden="true"></span>' . esc_html__( 'One lowercase letter', 'bits-accessible-forms' ) . '</li>';
            }
            if ( ! empty( $this->config['require_number'] ) ) {
                $html .= '<li data-req="number"><span class="baf-req-icon" aria-hidden="true"></span>' . esc_html__( 'One number', 'bits-accessible-forms' ) . '</li>';
            }
            if ( ! empty( $this->config['require_special'] ) ) {
                $html .= '<li data-req="special"><span class="baf-req-icon" aria-hidden="true"></span>' . esc_html__( 'One special character (!@#$%^&*)', 'bits-accessible-forms' ) . '</li>';
            }
            $html .= '</ul>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        $describedby = array_filter( array( $desc_id ) );
        if ( ! empty( $this->config['show_requirements'] ) ) {
            $describedby[] = $html_id . '-requirements';
        }
        if ( ! empty( $describedby ) ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( implode( ' ', $describedby ) ) . '"';
        }

        // Input wrapper
        $html .= '<div class="baf-password-input-wrapper">';

        $html .= '<input type="password"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="baf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' minlength="' . esc_attr( $min_length ) . '"';
        $html .= ' autocomplete="new-password"';
        $html .= ' class="baf-input baf-password-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        // Show/hide toggle
        if ( ! empty( $this->config['show_toggle'] ) ) {
            $html .= '<button type="button" class="baf-password-toggle" aria-pressed="false" aria-label="' . esc_attr__( 'Show password', 'bits-accessible-forms' ) . '">';
            $html .= '<span class="baf-toggle-show" aria-hidden="true">👁</span>';
            $html .= '<span class="baf-toggle-hide" aria-hidden="true" hidden>🙈</span>';
            $html .= '</button>';
        }

        $html .= '</div>'; // .baf-password-input-wrapper

        // Password generator
        if ( ! empty( $this->config['show_generator'] ) ) {
            $html .= '<button type="button" class="baf-password-generator">';
            $html .= esc_html__( 'Generate secure password', 'bits-accessible-forms' );
            $html .= '</button>';
        }

        // Strength meter
        if ( ! empty( $this->config['show_strength'] ) ) {
            $html .= '<div class="baf-password-strength">';
            $html .= '<div class="baf-strength-meter">';
            $html .= '<div class="baf-strength-bar" data-strength=""></div>';
            $html .= '</div>';
            $html .= '<span class="baf-strength-text" aria-live="polite"></span>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .baf-password-primary

        // Confirmation field
        if ( $confirm ) {
            $confirm_id = $html_id . '-confirm';
            $confirm_label = $this->config['confirm_label'] ?? __( 'Confirm Password', 'bits-accessible-forms' );
            $confirm_placeholder = $this->config['confirm_placeholder'] ?? '';

            $html .= '<div class="baf-password-confirm">';

            $html .= '<label for="' . esc_attr( $confirm_id ) . '" class="baf-field-label">';
            $html .= esc_html( $confirm_label );
            if ( $required ) {
                $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
            }
            $html .= '</label>';

            $html .= '<div class="baf-password-input-wrapper">';

            $html .= '<input type="password"';
            $html .= ' id="' . esc_attr( $confirm_id ) . '"';
            $html .= ' name="baf_field_' . esc_attr( $this->get_id() ) . '_confirm"';
            $html .= ' placeholder="' . esc_attr( $confirm_placeholder ) . '"';
            $html .= ' autocomplete="new-password"';
            $html .= ' class="baf-input baf-password-confirm-input"';
            $html .= ' aria-describedby="' . esc_attr( $confirm_id . '-match' ) . '"';
            if ( $required ) {
                $html .= ' aria-required="true"';
            }
            $html .= '>';

            // Show/hide toggle for confirm
            if ( ! empty( $this->config['show_toggle'] ) ) {
                $html .= '<button type="button" class="baf-password-toggle" aria-pressed="false" aria-label="' . esc_attr__( 'Show password', 'bits-accessible-forms' ) . '">';
                $html .= '<span class="baf-toggle-show" aria-hidden="true">👁</span>';
                $html .= '<span class="baf-toggle-hide" aria-hidden="true" hidden>🙈</span>';
                $html .= '</button>';
            }

            $html .= '</div>'; // .baf-password-input-wrapper

            $html .= '<div id="' . esc_attr( $confirm_id . '-match' ) . '" class="baf-password-match" role="status" aria-live="polite"></div>';

            $html .= '</div>'; // .baf-password-confirm
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
        $value = $this->get_value();
        $min_length = (int) ( $this->config['min_length'] ?? 8 );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter a password.', 'bits-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $value ) ) {
            // Length check
            if ( mb_strlen( $value ) < $min_length ) {
                /* translators: %d: minimum characters */
                $this->add_error( sprintf( __( 'Password must be at least %d characters.', 'bits-accessible-forms' ), $min_length ) );
                return false;
            }

            // Uppercase check
            if ( ! empty( $this->config['require_uppercase'] ) && ! preg_match( '/[A-Z]/', $value ) ) {
                $this->add_error( __( 'Password must contain at least one uppercase letter.', 'bits-accessible-forms' ) );
                return false;
            }

            // Lowercase check
            if ( ! empty( $this->config['require_lowercase'] ) && ! preg_match( '/[a-z]/', $value ) ) {
                $this->add_error( __( 'Password must contain at least one lowercase letter.', 'bits-accessible-forms' ) );
                return false;
            }

            // Number check
            if ( ! empty( $this->config['require_number'] ) && ! preg_match( '/[0-9]/', $value ) ) {
                $this->add_error( __( 'Password must contain at least one number.', 'bits-accessible-forms' ) );
                return false;
            }

            // Special character check
            if ( ! empty( $this->config['require_special'] ) && ! preg_match( '/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $value ) ) {
                $this->add_error( __( 'Password must contain at least one special character.', 'bits-accessible-forms' ) );
                return false;
            }

            // Strength check
            $min_strength = $this->config['min_strength'] ?? 'weak';
            $strength = $this->calculate_strength( $value );
            $strength_order = array( 'weak' => 1, 'medium' => 2, 'strong' => 3 );
            if ( ( $strength_order[ $strength ] ?? 0 ) < ( $strength_order[ $min_strength ] ?? 0 ) ) {
                /* translators: %s: required strength level */
                $this->add_error( sprintf( __( 'Password must be at least %s strength.', 'bits-accessible-forms' ), $min_strength ) );
                return false;
            }

            // Confirmation check
            if ( ! empty( $this->config['confirm_password'] ) ) {
                $confirm = isset( $_POST[ 'baf_field_' . $this->get_id() . '_confirm' ] )
                    ? $_POST[ 'baf_field_' . $this->get_id() . '_confirm' ]
                    : '';

                if ( $value !== $confirm ) {
                    $this->add_error( __( 'Passwords do not match.', 'bits-accessible-forms' ) );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Calculate password strength
     *
     * @param string $password Password to check.
     * @return string Strength level: weak, medium, strong.
     */
    protected function calculate_strength( $password ) {
        $score = 0;
        $length = mb_strlen( $password );

        // Length scoring
        if ( $length >= 8 ) $score++;
        if ( $length >= 12 ) $score++;
        if ( $length >= 16 ) $score++;

        // Character type scoring
        if ( preg_match( '/[a-z]/', $password ) ) $score++;
        if ( preg_match( '/[A-Z]/', $password ) ) $score++;
        if ( preg_match( '/[0-9]/', $password ) ) $score++;
        if ( preg_match( '/[^a-zA-Z0-9]/', $password ) ) $score++;

        if ( $score >= 6 ) return 'strong';
        if ( $score >= 4 ) return 'medium';
        return 'weak';
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Password (not sanitized to preserve special chars).
     */
    public function sanitize( $value ) {
        // Don't alter password, but trim whitespace
        return trim( $value );
    }

    /**
     * Get user data key
     *
     * @return string User array key.
     */
    public function get_user_key() {
        return 'user_pass';
    }
}
