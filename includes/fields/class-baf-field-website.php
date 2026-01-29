<?php
/**
 * Website/URL Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Website class
 */
class BAF_Field_Website extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'website';

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
            'placeholder'     => 'https://example.com',
            'require_https'   => false,
            'allow_relative'  => false,
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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'require_https' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require HTTPS', 'bits-accessible-forms' ),
                    'desc'  => __( 'Only accept secure URLs.', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'bits-accessible-forms' ),
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
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();

        $attrs = array(
            'type'         => 'url',
            'id'           => $html_id,
            'name'         => $name,
            'value'        => $value,
            'class'        => 'baf-url-input',
            'autocomplete' => 'url',
        );

        if ( ! empty( $this->config['placeholder'] ) ) {
            $attrs['placeholder'] = $this->config['placeholder'];
        }

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $attrs = array_merge( $attrs, $aria );

        $field = '<input ' . $this->build_attributes( $attrs ) . '>';

        // HTTPS hint
        if ( ! empty( $this->config['require_https'] ) ) {
            $field .= '<div class="baf-format-hint">';
            $field .= esc_html__( 'URL must start with https://', 'bits-accessible-forms' );
            $field .= '</div>';
        }

        return $this->render_wrapper( $field );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        $value = trim( $value );

        // Add https:// if no protocol specified
        if ( ! empty( $value ) && ! preg_match( '/^https?:\/\//', $value ) ) {
            $value = 'https://' . $value;
        }

        return esc_url_raw( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Check valid URL format
        if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
            $this->add_error( $this->get_error_message( 'url' ) );
            return;
        }

        // Check HTTPS requirement
        if ( ! empty( $this->config['require_https'] ) ) {
            if ( strpos( $value, 'https://' ) !== 0 ) {
                $this->add_error( __( 'Please enter a secure URL starting with https://', 'bits-accessible-forms' ) );
            }
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Remove protocol for display
        $display = preg_replace( '/^https?:\/\//', '', $value );
        $display = rtrim( $display, '/' );

        return esc_html( $display );
    }

    /**
     * Format value as clickable link
     *
     * @param mixed $value Raw value.
     * @return string Link HTML.
     */
    public function format_link( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        return '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . 
               esc_html( $this->format_display( $value ) ) . 
               ' <span class="screen-reader-text">' . esc_html__( '(opens in new tab)', 'bits-accessible-forms' ) . '</span></a>';
    }
}
