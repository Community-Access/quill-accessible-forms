<?php
/**
 * Number Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Number class
 */
class BAF_Field_Number extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'number';

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
            'min'          => '',
            'max'          => '',
            'step'         => '',
            'prefix'       => '',  // e.g., "$"
            'suffix'       => '',  // e.g., "units"
            'format'       => 'decimal', // decimal, integer, currency
            'decimals'     => 2,
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
                'min' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Value', 'bits-accessible-forms' ),
                ),
                'max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Value', 'bits-accessible-forms' ),
                ),
                'step' => array(
                    'type'  => 'number',
                    'label' => __( 'Step Increment', 'bits-accessible-forms' ),
                    'desc'  => __( 'e.g., 0.01 for cents, 1 for whole numbers', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'bits-accessible-forms' ),
                ),
                'prefix' => array(
                    'type'  => 'text',
                    'label' => __( 'Prefix', 'bits-accessible-forms' ),
                    'desc'  => __( 'e.g., $', 'bits-accessible-forms' ),
                ),
                'suffix' => array(
                    'type'  => 'text',
                    'label' => __( 'Suffix', 'bits-accessible-forms' ),
                    'desc'  => __( 'e.g., kg, units', 'bits-accessible-forms' ),
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
            'type'  => 'number',
            'id'    => $html_id,
            'name'  => $name,
            'value' => $value,
            'class' => 'baf-number-input',
        );

        // Optional attributes
        if ( ! empty( $this->config['placeholder'] ) ) {
            $attrs['placeholder'] = $this->config['placeholder'];
        }
        if ( $this->config['min'] !== '' ) {
            $attrs['min'] = $this->config['min'];
        }
        if ( $this->config['max'] !== '' ) {
            $attrs['max'] = $this->config['max'];
        }
        if ( ! empty( $this->config['step'] ) ) {
            $attrs['step'] = $this->config['step'];
        }

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $attrs = array_merge( $attrs, $aria );

        // Add range description to aria-describedby
        if ( $this->config['min'] !== '' || $this->config['max'] !== '' ) {
            $range_id = $html_id . '-range';
            $existing = $attrs['aria-describedby'] ?? '';
            $attrs['aria-describedby'] = trim( $range_id . ' ' . $existing );
        }

        // Build input with optional prefix/suffix
        $field = '';
        
        if ( ! empty( $this->config['prefix'] ) || ! empty( $this->config['suffix'] ) ) {
            $field .= '<div class="baf-number-wrapper">';
            
            if ( ! empty( $this->config['prefix'] ) ) {
                $field .= '<span class="baf-number-prefix" aria-hidden="true">' . esc_html( $this->config['prefix'] ) . '</span>';
            }
        }
        
        $field .= '<input ' . $this->build_attributes( $attrs ) . '>';
        
        if ( ! empty( $this->config['prefix'] ) || ! empty( $this->config['suffix'] ) ) {
            if ( ! empty( $this->config['suffix'] ) ) {
                $field .= '<span class="baf-number-suffix" aria-hidden="true">' . esc_html( $this->config['suffix'] ) . '</span>';
            }
            
            $field .= '</div>';
        }

        // Add range hint
        if ( $this->config['min'] !== '' || $this->config['max'] !== '' ) {
            $field .= '<div id="' . esc_attr( $html_id ) . '-range" class="baf-range-hint">';
            if ( $this->config['min'] !== '' && $this->config['max'] !== '' ) {
                $field .= sprintf(
                    esc_html__( 'Enter a number between %1$s and %2$s', 'bits-accessible-forms' ),
                    esc_html( $this->config['min'] ),
                    esc_html( $this->config['max'] )
                );
            } elseif ( $this->config['min'] !== '' ) {
                $field .= sprintf(
                    esc_html__( 'Minimum value: %s', 'bits-accessible-forms' ),
                    esc_html( $this->config['min'] )
                );
            } else {
                $field .= sprintf(
                    esc_html__( 'Maximum value: %s', 'bits-accessible-forms' ),
                    esc_html( $this->config['max'] )
                );
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
        if ( $value === '' ) {
            return '';
        }

        // Remove non-numeric characters except . and -
        $value = preg_replace( '/[^0-9.\-]/', '', $value );

        if ( $this->config['format'] === 'integer' ) {
            return (string) intval( $value );
        }

        return (string) floatval( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( ! is_numeric( $value ) ) {
            $this->add_error( $this->get_error_message( 'number' ) );
            return;
        }

        $num = floatval( $value );

        if ( $this->config['min'] !== '' && $num < floatval( $this->config['min'] ) ) {
            $this->add_error( sprintf(
                __( 'Please enter a value greater than or equal to %s.', 'bits-accessible-forms' ),
                $this->config['min']
            ) );
        }

        if ( $this->config['max'] !== '' && $num > floatval( $this->config['max'] ) ) {
            $this->add_error( sprintf(
                __( 'Please enter a value less than or equal to %s.', 'bits-accessible-forms' ),
                $this->config['max']
            ) );
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( $value === '' ) {
            return '';
        }

        $formatted = number_format(
            floatval( $value ),
            $this->config['decimals'] ?? 2,
            '.',
            ','
        );

        if ( ! empty( $this->config['prefix'] ) ) {
            $formatted = $this->config['prefix'] . $formatted;
        }

        if ( ! empty( $this->config['suffix'] ) ) {
            $formatted = $formatted . ' ' . $this->config['suffix'];
        }

        return esc_html( $formatted );
    }
}
