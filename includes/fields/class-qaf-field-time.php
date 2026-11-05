<?php
/**
 * Time Picker Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Time class
 */
class QAF_Field_Time extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'time';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'date_time';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'min_time'       => '',
            'max_time'       => '',
            'step'           => 60,
            'format'         => '12',
            'default_value'  => '',
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
                'min_time' => array(
                    'type'  => 'time',
                    'label' => __( 'Earliest Time', 'quill-accessible-forms' ),
                ),
                'max_time' => array(
                    'type'  => 'time',
                    'label' => __( 'Latest Time', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Format', 'quill-accessible-forms' ),
                    'options' => array(
                        '12' => __( '12-hour (AM/PM)', 'quill-accessible-forms' ),
                        '24' => __( '24-hour', 'quill-accessible-forms' ),
                    ),
                ),
                'step' => array(
                    'type'    => 'select',
                    'label'   => __( 'Time Intervals', 'quill-accessible-forms' ),
                    'options' => array(
                        '60'   => __( '1 minute', 'quill-accessible-forms' ),
                        '300'  => __( '5 minutes', 'quill-accessible-forms' ),
                        '600'  => __( '10 minutes', 'quill-accessible-forms' ),
                        '900'  => __( '15 minutes', 'quill-accessible-forms' ),
                        '1800' => __( '30 minutes', 'quill-accessible-forms' ),
                        '3600' => __( '1 hour', 'quill-accessible-forms' ),
                    ),
                ),
                'default_value' => array(
                    'type'  => 'time',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
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
        $value   = $this->get_value() ?: $this->config['default_value'];
        $step    = (int) $this->config['step'];
        $format  = $this->config['format'];

        $wrapper_class = $this->get_wrapper_classes();
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-format="' . esc_attr( $format ) . '">';

        // Label
        $html .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Time constraints hint
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }

        if ( ! empty( $this->config['min_time'] ) || ! empty( $this->config['max_time'] ) ) {
            $described_by[] = $html_id . '-constraints';
            $html .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="qaf-time-hint">';
            
            if ( ! empty( $this->config['min_time'] ) && ! empty( $this->config['max_time'] ) ) {
                $html .= sprintf(
                    esc_html__( 'Time must be between %1$s and %2$s', 'quill-accessible-forms' ),
                    $this->format_time( $this->config['min_time'] ),
                    $this->format_time( $this->config['max_time'] )
                );
            } elseif ( ! empty( $this->config['min_time'] ) ) {
                $html .= sprintf(
                    esc_html__( 'Time must be %s or later', 'quill-accessible-forms' ),
                    $this->format_time( $this->config['min_time'] )
                );
            } elseif ( ! empty( $this->config['max_time'] ) ) {
                $html .= sprintf(
                    esc_html__( 'Time must be %s or earlier', 'quill-accessible-forms' ),
                    $this->format_time( $this->config['max_time'] )
                );
            }
            
            $html .= '</div>';
        }

        // Time input
        $attrs = array(
            'type'  => 'time',
            'id'    => $html_id,
            'name'  => $name,
            'value' => $value,
            'class' => 'qaf-time-input',
            'step'  => $step,
        );

        if ( ! empty( $this->config['min_time'] ) ) {
            $attrs['min'] = $this->config['min_time'];
        }

        if ( ! empty( $this->config['max_time'] ) ) {
            $attrs['max'] = $this->config['max_time'];
        }

        if ( ! empty( $described_by ) ) {
            $attrs['aria-describedby'] = implode( ' ', $described_by );
        }

        if ( $this->is_required() ) {
            $attrs['required'] = 'required';
            $attrs['aria-required'] = 'true';
        }

        if ( ! empty( $this->errors ) ) {
            $attrs['aria-invalid'] = 'true';
            $attrs['aria-describedby'] = ( $attrs['aria-describedby'] ?? '' ) . ' ' . $html_id . '-error';
        }

        $html .= '<input ' . $this->build_attributes( $attrs ) . '>';

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
     * Format time for display
     *
     * @param string $time Time in 24h format (HH:MM).
     * @return string Formatted time.
     */
    protected function format_time( $time ) {
        if ( empty( $time ) ) {
            return '';
        }

        $format = $this->config['format'] === '12' ? 'g:i A' : 'H:i';
        $timestamp = strtotime( '2000-01-01 ' . $time );
        
        return date( $format, $timestamp );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        $value = sanitize_text_field( $value );

        // Validate time format
        if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
            return '';
        }

        return $value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Validate time format
        if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
            $this->add_error( __( 'Please enter a valid time.', 'quill-accessible-forms' ) );
            return;
        }

        $time = strtotime( '2000-01-01 ' . $value );

        // Check minimum time
        if ( ! empty( $this->config['min_time'] ) ) {
            $min = strtotime( '2000-01-01 ' . $this->config['min_time'] );
            if ( $time < $min ) {
                $this->add_error( sprintf(
                    __( 'Time must be %s or later.', 'quill-accessible-forms' ),
                    $this->format_time( $this->config['min_time'] )
                ) );
            }
        }

        // Check maximum time
        if ( ! empty( $this->config['max_time'] ) ) {
            $max = strtotime( '2000-01-01 ' . $this->config['max_time'] );
            if ( $time > $max ) {
                $this->add_error( sprintf(
                    __( 'Time must be %s or earlier.', 'quill-accessible-forms' ),
                    $this->format_time( $this->config['max_time'] )
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
        return $this->format_time( $value );
    }
}
