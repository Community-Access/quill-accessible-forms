<?php
/**
 * Date Range Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_DateRange class
 * 
 * Date range input with fieldset/legend for proper accessibility.
 */
class BAF_Field_DateRange extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'daterange';

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
            'start_label'     => __( 'Start Date', 'bits-accessible-forms' ),
            'end_label'       => __( 'End Date', 'bits-accessible-forms' ),
            'min_date'        => '',
            'max_date'        => '',
            'min_duration'    => 0,
            'max_duration'    => 0,
            'disable_past'    => false,
            'date_format'     => 'Y-m-d',
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
                    'label' => __( 'Group Label', 'bits-accessible-forms' ),
                ),
                'start_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Start Date Label', 'bits-accessible-forms' ),
                ),
                'end_label' => array(
                    'type'  => 'text',
                    'label' => __( 'End Date Label', 'bits-accessible-forms' ),
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
                'min_date' => array(
                    'type'  => 'date',
                    'label' => __( 'Earliest Date', 'bits-accessible-forms' ),
                ),
                'max_date' => array(
                    'type'  => 'date',
                    'label' => __( 'Latest Date', 'bits-accessible-forms' ),
                ),
                'disable_past' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Past Dates', 'bits-accessible-forms' ),
                ),
                'min_duration' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Days', 'bits-accessible-forms' ),
                    'desc'  => __( 'Minimum difference between start and end.', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_duration' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Days', 'bits-accessible-forms' ),
                    'desc'  => __( 'Maximum difference between start and end. 0 for unlimited.', 'bits-accessible-forms' ),
                    'min'   => 0,
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
            ),
        );
    }

    /**
     * Render the field HTML with fieldset/legend
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        
        $start_value = is_array( $value ) ? ( $value['start'] ?? '' ) : '';
        $end_value   = is_array( $value ) ? ( $value['end'] ?? '' ) : '';
        
        $min = $this->get_min_date();
        $max = $this->get_max_date();

        $wrapper_class = $this->get_wrapper_classes();
        
        // Use fieldset/legend for grouped date inputs
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-min-duration="' . esc_attr( $this->config['min_duration'] ) . '"';
        $html .= ' data-max-duration="' . esc_attr( $this->config['max_duration'] ) . '">';

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

        $constraints = array();
        if ( ! empty( $this->config['disable_past'] ) ) {
            $constraints[] = __( 'Dates cannot be in the past', 'bits-accessible-forms' );
        }
        if ( ! empty( $this->config['min_duration'] ) ) {
            $constraints[] = sprintf(
                __( 'Minimum duration: %d days', 'bits-accessible-forms' ),
                $this->config['min_duration']
            );
        }
        if ( ! empty( $this->config['max_duration'] ) ) {
            $constraints[] = sprintf(
                __( 'Maximum duration: %d days', 'bits-accessible-forms' ),
                $this->config['max_duration']
            );
        }

        if ( ! empty( $constraints ) ) {
            $described_by[] = $html_id . '-constraints';
            $html .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="baf-daterange-hint">';
            $html .= esc_html( implode( '. ', $constraints ) );
            $html .= '</div>';
        }

        // Date inputs container
        $html .= '<div class="baf-daterange-inputs">';

        // Start date
        $html .= '<div class="baf-daterange-start">';
        $html .= '<label for="' . esc_attr( $html_id ) . '-start" class="baf-field-label">';
        $html .= esc_html( $this->config['start_label'] );
        if ( $this->is_required() ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        $start_attrs = array(
            'type'  => 'date',
            'id'    => $html_id . '-start',
            'name'  => $name . '[start]',
            'value' => $start_value,
            'class' => 'baf-date-input baf-daterange-start-input',
        );

        if ( $min ) {
            $start_attrs['min'] = $min;
        }
        if ( $max ) {
            $start_attrs['max'] = $max;
        }
        if ( ! empty( $described_by ) ) {
            $start_attrs['aria-describedby'] = implode( ' ', $described_by );
        }
        if ( $this->is_required() ) {
            $start_attrs['required'] = 'required';
            $start_attrs['aria-required'] = 'true';
        }
        if ( ! empty( $this->errors ) ) {
            $start_attrs['aria-invalid'] = 'true';
        }

        $html .= '<input ' . $this->build_attributes( $start_attrs ) . '>';
        $html .= '</div>';

        // To separator (visually hidden but available)
        $html .= '<span class="baf-daterange-separator" aria-hidden="true">' . esc_html__( 'to', 'bits-accessible-forms' ) . '</span>';

        // End date
        $html .= '<div class="baf-daterange-end">';
        $html .= '<label for="' . esc_attr( $html_id ) . '-end" class="baf-field-label">';
        $html .= esc_html( $this->config['end_label'] );
        if ( $this->is_required() ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        $end_attrs = array(
            'type'  => 'date',
            'id'    => $html_id . '-end',
            'name'  => $name . '[end]',
            'value' => $end_value,
            'class' => 'baf-date-input baf-daterange-end-input',
        );

        if ( $min ) {
            $end_attrs['min'] = $min;
        }
        if ( $max ) {
            $end_attrs['max'] = $max;
        }
        if ( $this->is_required() ) {
            $end_attrs['required'] = 'required';
            $end_attrs['aria-required'] = 'true';
        }
        if ( ! empty( $this->errors ) ) {
            $end_attrs['aria-invalid'] = 'true';
        }

        $html .= '<input ' . $this->build_attributes( $end_attrs ) . '>';
        $html .= '</div>';

        $html .= '</div>';

        // Duration display
        $html .= '<div class="baf-daterange-duration" role="status" aria-live="polite"></div>';

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
     * Get minimum date
     *
     * @return string|null Min date.
     */
    protected function get_min_date() {
        if ( ! empty( $this->config['disable_past'] ) ) {
            return current_time( 'Y-m-d' );
        }

        return $this->config['min_date'] ?: null;
    }

    /**
     * Get maximum date
     *
     * @return string|null Max date.
     */
    protected function get_max_date() {
        return $this->config['max_date'] ?: null;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized values.
     */
    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'start' => '', 'end' => '' );
        }

        return array(
            'start' => isset( $value['start'] ) ? sanitize_text_field( $value['start'] ) : '',
            'end'   => isset( $value['end'] ) ? sanitize_text_field( $value['end'] ) : '',
        );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $start = $value['start'] ?? '';
        $end   = $value['end'] ?? '';

        if ( empty( $start ) || empty( $end ) ) {
            $this->add_error( __( 'Please select both start and end dates.', 'bits-accessible-forms' ) );
            return;
        }

        $start_ts = strtotime( $start );
        $end_ts   = strtotime( $end );

        if ( $start_ts === false || $end_ts === false ) {
            $this->add_error( __( 'Please enter valid dates.', 'bits-accessible-forms' ) );
            return;
        }

        // End must be after or equal to start
        if ( $end_ts < $start_ts ) {
            $this->add_error( __( 'End date must be on or after the start date.', 'bits-accessible-forms' ) );
            return;
        }

        // Calculate duration in days
        $duration = ( $end_ts - $start_ts ) / DAY_IN_SECONDS;

        // Check minimum duration
        $min_dur = (int) $this->config['min_duration'];
        if ( $min_dur > 0 && $duration < $min_dur ) {
            $this->add_error( sprintf(
                __( 'The date range must be at least %d days.', 'bits-accessible-forms' ),
                $min_dur
            ) );
        }

        // Check maximum duration
        $max_dur = (int) $this->config['max_duration'];
        if ( $max_dur > 0 && $duration > $max_dur ) {
            $this->add_error( sprintf(
                __( 'The date range cannot exceed %d days.', 'bits-accessible-forms' ),
                $max_dur
            ) );
        }

        // Check against min/max dates
        $min = $this->get_min_date();
        $max = $this->get_max_date();

        if ( $min ) {
            $min_ts = strtotime( $min );
            if ( $start_ts < $min_ts ) {
                $this->add_error( sprintf(
                    __( 'Start date cannot be before %s.', 'bits-accessible-forms' ),
                    date_i18n( get_option( 'date_format' ), $min_ts )
                ) );
            }
        }

        if ( $max ) {
            $max_ts = strtotime( $max );
            if ( $end_ts > $max_ts ) {
                $this->add_error( sprintf(
                    __( 'End date cannot be after %s.', 'bits-accessible-forms' ),
                    date_i18n( get_option( 'date_format' ), $max_ts )
                ) );
            }
        }
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty.
     */
    protected function is_empty( $value ) {
        if ( ! is_array( $value ) ) {
            return true;
        }

        return empty( $value['start'] ) && empty( $value['end'] );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( ! is_array( $value ) ) {
            return '';
        }

        $format = get_option( 'date_format' );
        $start  = ! empty( $value['start'] ) ? date_i18n( $format, strtotime( $value['start'] ) ) : '';
        $end    = ! empty( $value['end'] ) ? date_i18n( $format, strtotime( $value['end'] ) ) : '';

        if ( $start && $end ) {
            return sprintf( '%s – %s', $start, $end );
        }

        return $start ?: $end;
    }
}
