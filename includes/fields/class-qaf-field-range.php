<?php
/**
 * Range Slider Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Range class
 */
class QAF_Field_Range extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'range';

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
        return array_merge( parent::get_default_config(), array(
            'min'            => 0,
            'max'            => 100,
            'step'           => 1,
            'default_value'  => 50,
            'show_value'     => true,
            'show_labels'    => true,
            'min_label'      => '',
            'max_label'      => '',
            'prefix'         => '',
            'suffix'         => '',
            'dual_handle'    => false,
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
            'range' => array(
                'min' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Value', 'quill-accessible-forms' ),
                ),
                'max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Value', 'quill-accessible-forms' ),
                ),
                'step' => array(
                    'type'  => 'number',
                    'label' => __( 'Step', 'quill-accessible-forms' ),
                    'min'   => 0.01,
                ),
                'default_value' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                ),
                'dual_handle' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Dual Handle (Range Selection)', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'show_value' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Current Value', 'quill-accessible-forms' ),
                ),
                'show_labels' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Min/Max Labels', 'quill-accessible-forms' ),
                ),
                'min_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Min Label', 'quill-accessible-forms' ),
                    'desc'  => __( 'Leave empty to show number.', 'quill-accessible-forms' ),
                ),
                'max_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Max Label', 'quill-accessible-forms' ),
                ),
                'prefix' => array(
                    'type'  => 'text',
                    'label' => __( 'Value Prefix', 'quill-accessible-forms' ),
                ),
                'suffix' => array(
                    'type'  => 'text',
                    'label' => __( 'Value Suffix', 'quill-accessible-forms' ),
                    'desc'  => __( 'E.g., %, px, km', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the range slider field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'qaf_field_' . $this->get_id();
        $value   = $this->get_value();
        
        $min = (float) $this->config['min'];
        $max = (float) $this->config['max'];
        $step = (float) $this->config['step'];
        $default = (float) ( $this->config['default_value'] ?? ( $min + $max ) / 2 );
        $dual = ! empty( $this->config['dual_handle'] );

        // Handle dual slider values
        if ( $dual ) {
            $value_low = $value['low'] ?? $min;
            $value_high = $value['high'] ?? $max;
        } else {
            $value = $value !== '' ? (float) $value : $default;
        }

        $wrapper_class = $this->get_wrapper_classes();
        
        if ( $dual ) {
            $wrapper_class[] = 'qaf-range-dual';
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-min="' . esc_attr( $min ) . '"';
        $html .= ' data-max="' . esc_attr( $max ) . '"';
        $html .= ' data-step="' . esc_attr( $step ) . '"';
        $html .= ' data-dual="' . ( $dual ? 'true' : 'false' ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
        $html .= '<span class="qaf-label-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</label>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Current value display
        if ( ! empty( $this->config['show_value'] ) ) {
            $prefix = $this->config['prefix'] ?? '';
            $suffix = $this->config['suffix'] ?? '';

            $html .= '<output id="' . esc_attr( $html_id ) . '-output" class="qaf-range-output" ';
            $html .= 'for="' . esc_attr( $html_id ) . '" ';
            $html .= 'aria-live="polite" aria-atomic="true">';
            
            if ( $dual ) {
                $html .= esc_html( $prefix . $value_low . $suffix . ' – ' . $prefix . $value_high . $suffix );
            } else {
                $html .= esc_html( $prefix . $value . $suffix );
            }
            
            $html .= '</output>';
        }

        // Slider container
        $html .= '<div class="qaf-range-container">';

        // Min label
        if ( ! empty( $this->config['show_labels'] ) ) {
            $min_label = $this->config['min_label'] ?: $min;
            $html .= '<span class="qaf-range-label qaf-range-min" aria-hidden="true">' . esc_html( $min_label ) . '</span>';
        }

        // Track and thumb(s)
        $html .= '<div class="qaf-range-track-wrapper">';

        if ( $dual ) {
            // Dual range slider
            $html .= '<div class="qaf-range-track">';
            $html .= '<div class="qaf-range-selection"></div>';
            $html .= '</div>';

            // Low handle
            $html .= '<input type="range" id="' . esc_attr( $html_id ) . '-low"';
            $html .= ' name="' . esc_attr( $name ) . '[low]"';
            $html .= ' class="qaf-range-input qaf-range-low"';
            $html .= ' min="' . esc_attr( $min ) . '"';
            $html .= ' max="' . esc_attr( $max ) . '"';
            $html .= ' step="' . esc_attr( $step ) . '"';
            $html .= ' value="' . esc_attr( $value_low ) . '"';
            $html .= ' aria-label="' . esc_attr__( 'Minimum value', 'quill-accessible-forms' ) . '"';
            $html .= ' aria-valuemin="' . esc_attr( $min ) . '"';
            $html .= ' aria-valuemax="' . esc_attr( $max ) . '"';
            $html .= ' aria-valuenow="' . esc_attr( $value_low ) . '">';

            // High handle
            $html .= '<input type="range" id="' . esc_attr( $html_id ) . '-high"';
            $html .= ' name="' . esc_attr( $name ) . '[high]"';
            $html .= ' class="qaf-range-input qaf-range-high"';
            $html .= ' min="' . esc_attr( $min ) . '"';
            $html .= ' max="' . esc_attr( $max ) . '"';
            $html .= ' step="' . esc_attr( $step ) . '"';
            $html .= ' value="' . esc_attr( $value_high ) . '"';
            $html .= ' aria-label="' . esc_attr__( 'Maximum value', 'quill-accessible-forms' ) . '"';
            $html .= ' aria-valuemin="' . esc_attr( $min ) . '"';
            $html .= ' aria-valuemax="' . esc_attr( $max ) . '"';
            $html .= ' aria-valuenow="' . esc_attr( $value_high ) . '">';
        } else {
            // Single range slider
            $html .= '<input type="range" id="' . esc_attr( $html_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' class="qaf-range-input"';
            $html .= ' min="' . esc_attr( $min ) . '"';
            $html .= ' max="' . esc_attr( $max ) . '"';
            $html .= ' step="' . esc_attr( $step ) . '"';
            $html .= ' value="' . esc_attr( $value ) . '"';
            $html .= ' aria-valuemin="' . esc_attr( $min ) . '"';
            $html .= ' aria-valuemax="' . esc_attr( $max ) . '"';
            $html .= ' aria-valuenow="' . esc_attr( $value ) . '"';
            
            if ( $this->is_required() ) {
                $html .= ' aria-required="true"';
            }
            
            if ( $this->get_description() ) {
                $html .= ' aria-describedby="' . esc_attr( $html_id ) . '-desc"';
            }
            
            $html .= '>';
        }

        $html .= '</div>';

        // Max label
        if ( ! empty( $this->config['show_labels'] ) ) {
            $max_label = $this->config['max_label'] ?: $max;
            $html .= '<span class="qaf-range-label qaf-range-max" aria-hidden="true">' . esc_html( $max_label ) . '</span>';
        }

        $html .= '</div>';

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
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        $dual = ! empty( $this->config['dual_handle'] );

        if ( $dual ) {
            if ( ! is_array( $value ) ) {
                return array(
                    'low'  => (float) $this->config['min'],
                    'high' => (float) $this->config['max'],
                );
            }

            return array(
                'low'  => (float) ( $value['low'] ?? $this->config['min'] ),
                'high' => (float) ( $value['high'] ?? $this->config['max'] ),
            );
        }

        if ( $value === '' || $value === null ) {
            return '';
        }

        return (float) $value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $min = (float) $this->config['min'];
        $max = (float) $this->config['max'];
        $dual = ! empty( $this->config['dual_handle'] );

        if ( $dual ) {
            $low = $value['low'] ?? $min;
            $high = $value['high'] ?? $max;

            if ( $low < $min || $low > $max ) {
                $this->add_error( __( 'Minimum value is out of range.', 'quill-accessible-forms' ) );
            }

            if ( $high < $min || $high > $max ) {
                $this->add_error( __( 'Maximum value is out of range.', 'quill-accessible-forms' ) );
            }

            if ( $low > $high ) {
                $this->add_error( __( 'Minimum value cannot exceed maximum value.', 'quill-accessible-forms' ) );
            }
        } else {
            if ( $value < $min || $value > $max ) {
                $this->add_error( sprintf(
                    __( 'Value must be between %1$s and %2$s.', 'quill-accessible-forms' ),
                    $min,
                    $max
                ) );
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
        $prefix = $this->config['prefix'] ?? '';
        $suffix = $this->config['suffix'] ?? '';
        $dual = ! empty( $this->config['dual_handle'] );

        if ( $dual && is_array( $value ) ) {
            return sprintf(
                '%s%s%s – %s%s%s',
                $prefix,
                $value['low'] ?? '',
                $suffix,
                $prefix,
                $value['high'] ?? '',
                $suffix
            );
        }

        return $prefix . $value . $suffix;
    }
}
