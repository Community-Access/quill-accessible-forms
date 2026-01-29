<?php
/**
 * DateTime Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_DateTime class
 */
class BAF_Field_DateTime extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'datetime';

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
            'min_datetime'      => '',
            'max_datetime'      => '',
            'disable_past'      => false,
            'disable_future'    => false,
            'step'              => 60,
            'date_format'       => 'Y-m-d',
            'time_format'       => '12',
            'default_value'     => '',
            'timezone'          => 'site',
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
                'min_datetime' => array(
                    'type'  => 'datetime-local',
                    'label' => __( 'Earliest Date/Time', 'bits-accessible-forms' ),
                ),
                'max_datetime' => array(
                    'type'  => 'datetime-local',
                    'label' => __( 'Latest Date/Time', 'bits-accessible-forms' ),
                ),
                'disable_past' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Past Dates/Times', 'bits-accessible-forms' ),
                ),
                'disable_future' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Future Dates/Times', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'date_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Date Format', 'bits-accessible-forms' ),
                    'options' => array(
                        'Y-m-d' => __( 'YYYY-MM-DD', 'bits-accessible-forms' ),
                        'm/d/Y' => __( 'MM/DD/YYYY', 'bits-accessible-forms' ),
                        'd/m/Y' => __( 'DD/MM/YYYY', 'bits-accessible-forms' ),
                    ),
                ),
                'time_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Time Format', 'bits-accessible-forms' ),
                    'options' => array(
                        '12' => __( '12-hour (AM/PM)', 'bits-accessible-forms' ),
                        '24' => __( '24-hour', 'bits-accessible-forms' ),
                    ),
                ),
                'step' => array(
                    'type'    => 'select',
                    'label'   => __( 'Time Intervals', 'bits-accessible-forms' ),
                    'options' => array(
                        '60'   => __( '1 minute', 'bits-accessible-forms' ),
                        '300'  => __( '5 minutes', 'bits-accessible-forms' ),
                        '900'  => __( '15 minutes', 'bits-accessible-forms' ),
                        '1800' => __( '30 minutes', 'bits-accessible-forms' ),
                        '3600' => __( '1 hour', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'advanced' => array(
                'timezone' => array(
                    'type'    => 'select',
                    'label'   => __( 'Timezone', 'bits-accessible-forms' ),
                    'options' => array(
                        'site' => __( 'Use Site Timezone', 'bits-accessible-forms' ),
                        'user' => __( 'Use User Timezone', 'bits-accessible-forms' ),
                        'utc'  => __( 'UTC', 'bits-accessible-forms' ),
                    ),
                ),
                'default_value' => array(
                    'type'    => 'select',
                    'label'   => __( 'Default Value', 'bits-accessible-forms' ),
                    'options' => array(
                        ''      => __( 'None', 'bits-accessible-forms' ),
                        'now'   => __( 'Current Date/Time', 'bits-accessible-forms' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML using fieldset/legend for grouped inputs
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        $step    = (int) $this->config['step'];

        // Get default value
        if ( empty( $value ) && $this->config['default_value'] === 'now' ) {
            $value = current_time( 'Y-m-d\TH:i' );
        }

        $wrapper_class = $this->get_wrapper_classes();
        
        // Build min/max
        $min = $this->get_min_datetime();
        $max = $this->get_max_datetime();

        // Use fieldset/legend for composite datetime
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Legend
        $html .= '<legend class="baf-fieldset-legend">';
        $html .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Constraints hint
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }

        if ( $min || $max || ! empty( $this->config['disable_past'] ) || ! empty( $this->config['disable_future'] ) ) {
            $described_by[] = $html_id . '-constraints';
            $html .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="baf-datetime-hint">';
            
            if ( ! empty( $this->config['disable_past'] ) ) {
                $html .= esc_html__( 'Past dates/times are not allowed. ', 'bits-accessible-forms' );
            }
            if ( ! empty( $this->config['disable_future'] ) ) {
                $html .= esc_html__( 'Future dates/times are not allowed. ', 'bits-accessible-forms' );
            }
            if ( $min && $max ) {
                $html .= sprintf(
                    esc_html__( 'Must be between %1$s and %2$s.', 'bits-accessible-forms' ),
                    $this->format_datetime( $min ),
                    $this->format_datetime( $max )
                );
            } elseif ( $min ) {
                $html .= sprintf(
                    esc_html__( 'Must be %s or later.', 'bits-accessible-forms' ),
                    $this->format_datetime( $min )
                );
            } elseif ( $max ) {
                $html .= sprintf(
                    esc_html__( 'Must be %s or earlier.', 'bits-accessible-forms' ),
                    $this->format_datetime( $max )
                );
            }
            
            $html .= '</div>';
        }

        // DateTime input
        $attrs = array(
            'type'  => 'datetime-local',
            'id'    => $html_id,
            'name'  => $name,
            'value' => $value,
            'class' => 'baf-datetime-input',
            'step'  => $step,
        );

        if ( $min ) {
            $attrs['min'] = $min;
        }

        if ( $max ) {
            $attrs['max'] = $max;
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
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Get minimum datetime
     *
     * @return string|null Min datetime.
     */
    protected function get_min_datetime() {
        if ( ! empty( $this->config['disable_past'] ) ) {
            return current_time( 'Y-m-d\TH:i' );
        }

        return $this->config['min_datetime'] ?: null;
    }

    /**
     * Get maximum datetime
     *
     * @return string|null Max datetime.
     */
    protected function get_max_datetime() {
        if ( ! empty( $this->config['disable_future'] ) ) {
            return current_time( 'Y-m-d\TH:i' );
        }

        return $this->config['max_datetime'] ?: null;
    }

    /**
     * Format datetime for display
     *
     * @param string $datetime DateTime string.
     * @return string Formatted datetime.
     */
    protected function format_datetime( $datetime ) {
        if ( empty( $datetime ) ) {
            return '';
        }

        $date_format = $this->config['date_format'];
        $time_format = $this->config['time_format'] === '12' ? 'g:i A' : 'H:i';
        
        $timestamp = strtotime( $datetime );
        
        return date( $date_format . ' ' . $time_format, $timestamp );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        $value = sanitize_text_field( $value );

        // Validate datetime-local format
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
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
        $timestamp = strtotime( $value );

        if ( $timestamp === false ) {
            $this->add_error( __( 'Please enter a valid date and time.', 'bits-accessible-forms' ) );
            return;
        }

        $min = $this->get_min_datetime();
        $max = $this->get_max_datetime();

        if ( $min ) {
            $min_ts = strtotime( $min );
            if ( $timestamp < $min_ts ) {
                $this->add_error( sprintf(
                    __( 'Date/time must be %s or later.', 'bits-accessible-forms' ),
                    $this->format_datetime( $min )
                ) );
            }
        }

        if ( $max ) {
            $max_ts = strtotime( $max );
            if ( $timestamp > $max_ts ) {
                $this->add_error( sprintf(
                    __( 'Date/time must be %s or earlier.', 'bits-accessible-forms' ),
                    $this->format_datetime( $max )
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
        return $this->format_datetime( $value );
    }
}
