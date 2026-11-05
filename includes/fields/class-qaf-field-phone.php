<?php
/**
 * Phone Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Phone class
 */
class QAF_Field_Phone extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'phone';

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
            'format'           => 'any', // any, us, international
            'placeholder'      => '',
            'default_country'  => 'US',
            'show_country_code' => false,
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
                'format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Phone Format', 'quill-accessible-forms' ),
                    'options' => array(
                        'any'           => __( 'Any format', 'quill-accessible-forms' ),
                        'us'            => __( 'US format (xxx) xxx-xxxx', 'quill-accessible-forms' ),
                        'international' => __( 'International (+xx xxxxxxxxxx)', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
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
        $value   = $this->get_value();
        $format  = $this->config['format'];

        $attrs = array(
            'type'         => 'tel',
            'id'           => $html_id,
            'name'         => $name,
            'value'        => $value,
            'class'        => 'qaf-phone-input',
            'autocomplete' => 'tel',
        );

        // Set placeholder based on format
        $placeholder = $this->config['placeholder'];
        if ( empty( $placeholder ) ) {
            switch ( $format ) {
                case 'us':
                    $placeholder = '(555) 555-5555';
                    break;
                case 'international':
                    $placeholder = '+1 555 555 5555';
                    break;
            }
        }
        if ( $placeholder ) {
            $attrs['placeholder'] = $placeholder;
        }

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $attrs = array_merge( $attrs, $aria );

        // Add format hint
        $format_id = $html_id . '-format';
        if ( $format !== 'any' ) {
            $existing = $attrs['aria-describedby'] ?? '';
            $attrs['aria-describedby'] = trim( $format_id . ' ' . $existing );
        }

        $field = '<input ' . $this->build_attributes( $attrs ) . '>';

        // Format hint
        if ( $format !== 'any' ) {
            $field .= '<div id="' . esc_attr( $format_id ) . '" class="qaf-format-hint">';
            switch ( $format ) {
                case 'us':
                    $field .= esc_html__( 'Format: (xxx) xxx-xxxx', 'quill-accessible-forms' );
                    break;
                case 'international':
                    $field .= esc_html__( 'Include country code, e.g., +1 for US', 'quill-accessible-forms' );
                    break;
            }
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
        // Allow only numbers, spaces, dashes, parentheses, and plus sign
        return preg_replace( '/[^0-9\s\-\(\)\+]/', '', $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $format = $this->config['format'];
        $digits = preg_replace( '/[^0-9]/', '', $value );

        switch ( $format ) {
            case 'us':
                if ( strlen( $digits ) !== 10 ) {
                    $this->add_error( __( 'Please enter a valid 10-digit US phone number.', 'quill-accessible-forms' ) );
                }
                break;

            case 'international':
                if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
                    $this->add_error( __( 'Please enter a valid international phone number.', 'quill-accessible-forms' ) );
                }
                break;

            default:
                // Basic validation - at least 7 digits
                if ( strlen( $digits ) < 7 ) {
                    $this->add_error( $this->get_error_message( 'phone' ) );
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
        $digits = preg_replace( '/[^0-9]/', '', $value );

        // Format US numbers
        if ( strlen( $digits ) === 10 ) {
            return sprintf(
                '(%s) %s-%s',
                substr( $digits, 0, 3 ),
                substr( $digits, 3, 3 ),
                substr( $digits, 6, 4 )
            );
        }

        return esc_html( $value );
    }

    /**
     * Format value as tel: link
     *
     * @param mixed $value Raw value.
     * @return string Tel link.
     */
    public function format_link( $value ) {
        $digits = preg_replace( '/[^0-9\+]/', '', $value );
        return '<a href="tel:' . esc_attr( $digits ) . '">' . esc_html( $this->format_display( $value ) ) . '</a>';
    }
}
