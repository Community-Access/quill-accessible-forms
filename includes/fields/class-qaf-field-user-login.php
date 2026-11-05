<?php
/**
 * User Login Field - WordPress username
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_User_Login class
 */
class QAF_Field_User_Login extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'user_login';

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
            'label'              => __( 'Username', 'quill-accessible-forms' ),
            'description'        => '',
            'placeholder'        => __( 'Choose a username', 'quill-accessible-forms' ),
            'min_length'         => 3,
            'max_length'         => 60,
            'check_availability' => true,
            'show_requirements'  => true,
            'auto_generate'      => false,
            'generate_from'      => 'email',
            'prefix'             => '',
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
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 60,
                ),
                'max_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Length', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 60,
                ),
                'check_availability' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Check Availability (AJAX)', 'quill-accessible-forms' ),
                    'description' => __( 'Check if username is taken as user types', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'show_requirements' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Username Requirements', 'quill-accessible-forms' ),
                ),
            ),
            'auto' => array(
                'auto_generate' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Auto-generate Username', 'quill-accessible-forms' ),
                    'description' => __( 'Generate username from another field', 'quill-accessible-forms' ),
                ),
                'generate_from' => array(
                    'type'      => 'select',
                    'label'     => __( 'Generate From', 'quill-accessible-forms' ),
                    'options'   => array(
                        'email'      => __( 'Email Address', 'quill-accessible-forms' ),
                        'name'       => __( 'Full Name', 'quill-accessible-forms' ),
                        'first_name' => __( 'First Name', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'auto_generate' => true ),
                ),
                'prefix' => array(
                    'type'      => 'text',
                    'label'     => __( 'Username Prefix', 'quill-accessible-forms' ),
                    'condition' => array( 'auto_generate' => true ),
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
        $label = $this->config['label'] ?? __( 'Username', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $value = $this->get_value();
        $min_length = (int) ( $this->config['min_length'] ?? 3 );
        $max_length = (int) ( $this->config['max_length'] ?? 60 );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-user-login' );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        if ( ! empty( $this->config['check_availability'] ) ) {
            $html .= ' data-check-availability="true"';
        }
        $html .= '>';

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

        // Requirements
        if ( ! empty( $this->config['show_requirements'] ) ) {
            $req_id = $html_id . '-requirements';
            $html .= '<ul id="' . esc_attr( $req_id ) . '" class="qaf-username-requirements" aria-label="' . esc_attr__( 'Username requirements', 'quill-accessible-forms' ) . '">';
            $html .= '<li data-req="length">' . sprintf( esc_html__( '%d-%d characters', 'quill-accessible-forms' ), $min_length, $max_length ) . '</li>';
            $html .= '<li data-req="chars">' . esc_html__( 'Letters, numbers, and underscores only', 'quill-accessible-forms' ) . '</li>';
            $html .= '<li data-req="start">' . esc_html__( 'Must start with a letter', 'quill-accessible-forms' ) . '</li>';
            $html .= '</ul>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        $describedby = array_filter( array( $desc_id, $html_id . '-status' ) );
        if ( ! empty( $this->config['show_requirements'] ) ) {
            $describedby[] = $html_id . '-requirements';
        }
        if ( ! empty( $describedby ) ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( implode( ' ', $describedby ) ) . '"';
        }

        // Input wrapper for availability indicator
        $html .= '<div class="qaf-username-input-wrapper">';

        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' minlength="' . esc_attr( $min_length ) . '"';
        $html .= ' maxlength="' . esc_attr( $max_length ) . '"';
        $html .= ' autocomplete="username"';
        $html .= ' autocapitalize="none"';
        $html .= ' spellcheck="false"';
        $html .= ' class="qaf-input qaf-username-input"';
        $html .= ' pattern="[a-zA-Z][a-zA-Z0-9_]*"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        // Availability indicator
        if ( ! empty( $this->config['check_availability'] ) ) {
            $html .= '<span class="qaf-availability-indicator" data-availability aria-hidden="true"></span>';
        }

        $html .= '</div>';

        // Status message
        $html .= '<div id="' . esc_attr( $html_id . '-status' ) . '" class="qaf-username-status" role="status" aria-live="polite"></div>';

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
        $min_length = (int) ( $this->config['min_length'] ?? 3 );
        $max_length = (int) ( $this->config['max_length'] ?? 60 );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter a username.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $value ) ) {
            $length = mb_strlen( $value );

            if ( $length < $min_length ) {
                /* translators: %d: minimum characters */
                $this->add_error( sprintf( __( 'Username must be at least %d characters.', 'quill-accessible-forms' ), $min_length ) );
                return false;
            }

            if ( $length > $max_length ) {
                /* translators: %d: maximum characters */
                $this->add_error( sprintf( __( 'Username cannot exceed %d characters.', 'quill-accessible-forms' ), $max_length ) );
                return false;
            }

            // Validate format
            if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_]*$/', $value ) ) {
                $this->add_error( __( 'Username can only contain letters, numbers, and underscores, and must start with a letter.', 'quill-accessible-forms' ) );
                return false;
            }

            // Check if username exists
            if ( username_exists( $value ) ) {
                $this->add_error( __( 'This username is already taken.', 'quill-accessible-forms' ) );
                return false;
            }

            // Check for reserved usernames
            $reserved = array( 'admin', 'administrator', 'root', 'webmaster', 'sysadmin', 'support', 'moderator' );
            if ( in_array( strtolower( $value ), $reserved ) ) {
                $this->add_error( __( 'This username is reserved.', 'quill-accessible-forms' ) );
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized username.
     */
    public function sanitize( $value ) {
        return sanitize_user( trim( $value ), true );
    }

    /**
     * Get user data key
     *
     * @return string User array key.
     */
    public function get_user_key() {
        return 'user_login';
    }
}
