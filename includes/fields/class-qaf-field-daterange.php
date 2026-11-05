<?php
/**
 * Date Range Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_DateRange class
 * 
 * Date range input with fieldset/legend for proper accessibility.
 */
class QAF_Field_DateRange extends QAF_Field {

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
            'start_label'     => __( 'Start Date', 'quill-accessible-forms' ),
            'end_label'       => __( 'End Date', 'quill-accessible-forms' ),
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
                    'label' => __( 'Group Label', 'quill-accessible-forms' ),
                ),
                'start_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Start Date Label', 'quill-accessible-forms' ),
                ),
                'end_label' => array(
                    'type'  => 'text',
                    'label' => __( 'End Date Label', 'quill-accessible-forms' ),
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
                'min_date' => array(
                    'type'  => 'date',
                    'label' => __( 'Earliest Date', 'quill-accessible-forms' ),
                ),
                'max_date' => array(
                    'type'  => 'date',
                    'label' => __( 'Latest Date', 'quill-accessible-forms' ),
                ),
                'disable_past' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Disable Past Dates', 'quill-accessible-forms' ),
                ),
                'min_duration' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Days', 'quill-accessible-forms' ),
                    'desc'  => __( 'Minimum difference between start and end.', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_duration' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Days', 'quill-accessible-forms' ),
                    'desc'  => __( 'Maximum difference between start and end. 0 for unlimited.', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
            ),
            'appearance' => array(
                'date_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Date Format', 'quill-accessible-forms' ),
                    'options' => array(
                        'Y-m-d' => __( 'YYYY-MM-DD', 'quill-accessible-forms' ),
                        'm/d/Y' => __( 'MM/DD/YYYY', 'quill-accessible-forms' ),
                        'd/m/Y' => __( 'DD/MM/YYYY', 'quill-accessible-forms' ),
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
        $name    = 'qaf_field_' . $this->get_id();
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
        $html .= '<legend class="qaf-fieldset-legend">';
        $html .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
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
            $constraints[] = __( 'Dates cannot be in the past', 'quill-accessible-forms' );
        }
        if ( ! empty( $this->config['min_duration'] ) ) {
            $constraints[] = sprintf(
                __( 'Minimum duration: %d days', 'quill-accessible-forms' ),
                $this->config['min_duration']
            );
        }
        if ( ! empty( $this->config['max_duration'] ) ) {
            $constraints[] = sprintf(
                __( 'Maximum duration: %d days', 'quill-accessible-forms' ),
                $this->config['max_duration']
            );
        }

        if ( ! empty( $constraints ) ) {
            $described_by[] = $html_id . '-constraints';
            $html .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="qaf-daterange-hint">';
            $html .= esc_html( implode( '. ', $constraints ) );
            $html .= '</div>';
        }

        // Date inputs container
        $html .= '<div class="qaf-daterange-inputs">';

        // Start date
        $html .= '<div class="qaf-daterange-start">';
        $html .= '<label for="' . esc_attr( $html_id ) . '-start" class="qaf-field-label">';
        $html .= esc_html( $this->config['start_label'] );
        if ( $this->is_required() ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        $start_attrs = array(
            'type'  => 'date',
            'id'    => $html_id . '-start',
            'name'  => $name . '[start]',
            'value' => $start_value,
            'class' => 'qaf-date-input qaf-daterange-start-input',
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
        $html .= '<span class="qaf-daterange-separator" aria-hidden="true">' . esc_html__( 'to', 'quill-accessible-forms' ) . '</span>';

        // End date
        $html .= '<div class="qaf-daterange-end">';
        $html .= '<label for="' . esc_attr( $html_id ) . '-end" class="qaf-field-label">';
        $html .= esc_html( $this->config['end_label'] );
        if ( $this->is_required() ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        $end_attrs = array(
            'type'  => 'date',
            'id'    => $html_id . '-end',
            'name'  => $name . '[end]',
            'value' => $end_value,
            'class' => 'qaf-date-input qaf-daterange-end-input',
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
        $html .= '<div class="qaf-daterange-duration" role="status" aria-live="polite"></div>';

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
            $this->add_error( __( 'Please select both start and end dates.', 'quill-accessible-forms' ) );
            return;
        }

        $start_ts = strtotime( $start );
        $end_ts   = strtotime( $end );

        if ( $start_ts === false || $end_ts === false ) {
            $this->add_error( __( 'Please enter valid dates.', 'quill-accessible-forms' ) );
            return;
        }

        // End must be after or equal to start
        if ( $end_ts < $start_ts ) {
            $this->add_error( __( 'End date must be on or after the start date.', 'quill-accessible-forms' ) );
            return;
        }

        // Calculate duration in days
        $duration = ( $end_ts - $start_ts ) / DAY_IN_SECONDS;

        // Check minimum duration
        $min_dur = (int) $this->config['min_duration'];
        if ( $min_dur > 0 && $duration < $min_dur ) {
            $this->add_error( sprintf(
                __( 'The date range must be at least %d days.', 'quill-accessible-forms' ),
                $min_dur
            ) );
        }

        // Check maximum duration
        $max_dur = (int) $this->config['max_duration'];
        if ( $max_dur > 0 && $duration > $max_dur ) {
            $this->add_error( sprintf(
                __( 'The date range cannot exceed %d days.', 'quill-accessible-forms' ),
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
                    __( 'Start date cannot be before %s.', 'quill-accessible-forms' ),
                    date_i18n( get_option( 'date_format' ), $min_ts )
                ) );
            }
        }

        if ( $max ) {
            $max_ts = strtotime( $max );
            if ( $end_ts > $max_ts ) {
                $this->add_error( sprintf(
                    __( 'End date cannot be after %s.', 'quill-accessible-forms' ),
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
