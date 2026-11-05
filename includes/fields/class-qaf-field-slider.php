<?php
/**
 * Slider Field
 *
 * Accessible range slider with numeric input pairing.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Slider class
 */
class QAF_Field_Slider extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'slider';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'numeric';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'min'           => 0,
            'max'           => 100,
            'step'          => 1,
            'default_value' => null,
            'show_value'    => true,
            'show_labels'   => true,
            'show_input'    => true,    // Show paired number input for keyboard entry
            'prefix'        => '',       // e.g., "$"
            'suffix'        => '',       // e.g., "%", "lbs"
            'tick_marks'    => array(),  // Array of values to show tick marks
            'track_color'   => '',
            'value_color'   => '',
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
                    'step'  => 'any',
                ),
                'max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Value', 'quill-accessible-forms' ),
                    'step'  => 'any',
                ),
                'step' => array(
                    'type'  => 'number',
                    'label' => __( 'Step', 'quill-accessible-forms' ),
                    'step'  => 'any',
                    'min'   => 0.001,
                ),
                'default_value' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                    'step'  => 'any',
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
                'show_input' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Number Input', 'quill-accessible-forms' ),
                    'desc'  => __( 'Provides keyboard-accessible alternative to slider', 'quill-accessible-forms' ),
                ),
                'prefix' => array(
                    'type'  => 'text',
                    'label' => __( 'Prefix', 'quill-accessible-forms' ),
                    'desc'  => __( 'Text before value (e.g., $)', 'quill-accessible-forms' ),
                ),
                'suffix' => array(
                    'type'  => 'text',
                    'label' => __( 'Suffix', 'quill-accessible-forms' ),
                    'desc'  => __( 'Text after value (e.g., %, lbs)', 'quill-accessible-forms' ),
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
        $html_id     = $this->get_html_id();
        $name        = 'qaf_field_' . $this->get_id();
        $value       = $this->get_value();
        $min         = (float) ( $this->config['min'] ?? 0 );
        $max         = (float) ( $this->config['max'] ?? 100 );
        $step        = (float) ( $this->config['step'] ?? 1 );
        $show_value  = $this->config['show_value'] ?? true;
        $show_labels = $this->config['show_labels'] ?? true;
        $show_input  = $this->config['show_input'] ?? true;
        $prefix      = $this->config['prefix'] ?? '';
        $suffix      = $this->config['suffix'] ?? '';

        // Set default value
        if ( $value === '' || $value === null ) {
            $value = $this->config['default_value'] ?? $min;
        }
        $value = (float) $value;

        $field = '<div class="qaf-slider-wrapper">';

        // Value display (live output)
        if ( $show_value ) {
            $field .= '<div class="qaf-slider-value-display">';
            $field .= '<output id="' . esc_attr( $html_id ) . '-output" for="' . esc_attr( $html_id ) . '">';
            $field .= esc_html( $prefix . $this->format_value( $value ) . $suffix );
            $field .= '</output>';
            $field .= '</div>';
        }

        // Slider container with labels
        $field .= '<div class="qaf-slider-container">';

        // Min label
        if ( $show_labels ) {
            $field .= '<span class="qaf-slider-label-min" aria-hidden="true">';
            $field .= esc_html( $prefix . $this->format_value( $min ) . $suffix );
            $field .= '</span>';
        }

        // Slider track wrapper
        $field .= '<div class="qaf-slider-track-wrapper">';
        
        // Range input
        $slider_attrs = array(
            'type'         => 'range',
            'id'           => $html_id,
            'name'         => $name,
            'value'        => $value,
            'min'          => $min,
            'max'          => $max,
            'step'         => $step,
            'class'        => 'qaf-slider-input',
        );

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        if ( $show_value ) {
            $aria['aria-describedby'] = ( $aria['aria-describedby'] ?? '' ) . ' ' . $html_id . '-output';
            $aria['aria-describedby'] = trim( $aria['aria-describedby'] );
        }
        $slider_attrs = array_merge( $slider_attrs, $aria );

        // Value text for screen readers
        $slider_attrs['aria-valuetext'] = $prefix . $this->format_value( $value ) . $suffix;
        $slider_attrs['aria-valuenow'] = $value;
        $slider_attrs['aria-valuemin'] = $min;
        $slider_attrs['aria-valuemax'] = $max;

        // Custom data attributes for JS
        $slider_attrs['data-prefix'] = $prefix;
        $slider_attrs['data-suffix'] = $suffix;

        $field .= '<input ' . $this->build_attributes( $slider_attrs ) . '>';

        // Tick marks (if provided)
        $tick_marks = $this->config['tick_marks'] ?? array();
        if ( ! empty( $tick_marks ) ) {
            $field .= '<datalist id="' . esc_attr( $html_id ) . '-ticks">';
            foreach ( $tick_marks as $tick ) {
                $field .= '<option value="' . esc_attr( $tick ) . '">';
            }
            $field .= '</datalist>';
            // Note: range input needs list attribute to connect
        }

        $field .= '</div>';

        // Max label
        if ( $show_labels ) {
            $field .= '<span class="qaf-slider-label-max" aria-hidden="true">';
            $field .= esc_html( $prefix . $this->format_value( $max ) . $suffix );
            $field .= '</span>';
        }

        $field .= '</div>';

        // Paired numeric input (accessible alternative)
        if ( $show_input ) {
            $input_attrs = array(
                'type'         => 'number',
                'id'           => $html_id . '-number',
                'value'        => $value,
                'min'          => $min,
                'max'          => $max,
                'step'         => $step,
                'class'        => 'qaf-slider-number-input',
                'aria-label'   => sprintf(
                    /* translators: %s: field label */
                    __( '%s (numeric input)', 'quill-accessible-forms' ),
                    $this->get_label()
                ),
                'data-linked-slider' => $html_id,
            );

            $field .= '<div class="qaf-slider-number-wrapper">';
            
            if ( ! empty( $prefix ) ) {
                $field .= '<span class="qaf-slider-number-prefix">' . esc_html( $prefix ) . '</span>';
            }
            
            $field .= '<input ' . $this->build_attributes( $input_attrs ) . '>';
            
            if ( ! empty( $suffix ) ) {
                $field .= '<span class="qaf-slider-number-suffix">' . esc_html( $suffix ) . '</span>';
            }
            
            $field .= '</div>';
        }

        $field .= '</div>';

        return $this->render_wrapper( $field );
    }

    /**
     * Format a numeric value
     *
     * @param float $value Value to format.
     * @return string Formatted value.
     */
    protected function format_value( $value ) {
        $step = (float) ( $this->config['step'] ?? 1 );
        
        // Determine decimal places from step
        if ( $step < 1 ) {
            $decimals = strlen( substr( strrchr( (string) $step, '.' ), 1 ) );
        } else {
            $decimals = 0;
        }

        return number_format( $value, $decimals );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return float Sanitized value.
     */
    public function sanitize( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $value = (float) $value;
        $min   = (float) ( $this->config['min'] ?? 0 );
        $max   = (float) ( $this->config['max'] ?? 100 );

        // Clamp to range
        return max( $min, min( $max, $value ) );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( $value === '' || $value === null ) {
            return; // Empty handled by required check
        }

        $min = (float) ( $this->config['min'] ?? 0 );
        $max = (float) ( $this->config['max'] ?? 100 );

        if ( $value < $min || $value > $max ) {
            $this->add_error( sprintf(
                __( 'Please select a value between %1$s and %2$s.', 'quill-accessible-forms' ),
                $this->format_value( $min ),
                $this->format_value( $max )
            ) );
        }
    }
}
