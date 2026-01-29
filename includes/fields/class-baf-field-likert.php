<?php
/**
 * Likert Scale Field - Uses fieldset/legend and table for accessibility
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Likert class
 * 
 * Matrix-style Likert scale with proper WCAG-compliant table markup.
 */
class BAF_Field_Likert extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'likert';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'choice';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'rows'    => array(
                array( 'value' => 'q1', 'label' => __( 'Question 1', 'bits-accessible-forms' ) ),
                array( 'value' => 'q2', 'label' => __( 'Question 2', 'bits-accessible-forms' ) ),
            ),
            'columns' => array(
                array( 'value' => '1', 'label' => __( 'Strongly Disagree', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Disagree', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Agree', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Strongly Agree', 'bits-accessible-forms' ) ),
            ),
            'scale_type'     => 'agreement',
            'require_all'    => true,
            'randomize_rows' => false,
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
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'rows' => array(
                    'type'  => 'repeater',
                    'label' => __( 'Rows (Statements)', 'bits-accessible-forms' ),
                    'fields' => array(
                        'value' => array( 'type' => 'text', 'label' => __( 'Value', 'bits-accessible-forms' ) ),
                        'label' => array( 'type' => 'text', 'label' => __( 'Label', 'bits-accessible-forms' ) ),
                    ),
                ),
            ),
            'scale' => array(
                'scale_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Scale Type', 'bits-accessible-forms' ),
                    'options' => array(
                        'agreement'   => __( 'Agreement (Disagree to Agree)', 'bits-accessible-forms' ),
                        'frequency'   => __( 'Frequency (Never to Always)', 'bits-accessible-forms' ),
                        'satisfaction' => __( 'Satisfaction (Very Unsatisfied to Very Satisfied)', 'bits-accessible-forms' ),
                        'importance'  => __( 'Importance (Not Important to Very Important)', 'bits-accessible-forms' ),
                        'likelihood'  => __( 'Likelihood (Very Unlikely to Very Likely)', 'bits-accessible-forms' ),
                        'custom'      => __( 'Custom', 'bits-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'      => 'repeater',
                    'label'     => __( 'Columns (Scale Points)', 'bits-accessible-forms' ),
                    'condition' => array( 'scale_type' => 'custom' ),
                    'fields'    => array(
                        'value' => array( 'type' => 'text', 'label' => __( 'Value', 'bits-accessible-forms' ) ),
                        'label' => array( 'type' => 'text', 'label' => __( 'Label', 'bits-accessible-forms' ) ),
                    ),
                ),
            ),
            'validation' => array(
                'require_all' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require All Rows', 'bits-accessible-forms' ),
                    'desc'  => __( 'User must answer every row.', 'bits-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'randomize_rows' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Randomize Row Order', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML with table for matrix
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $values  = (array) $this->get_value();
        $rows    = $this->get_rows();
        $columns = $this->get_columns();

        $wrapper_class = $this->get_wrapper_classes();
        
        // Use fieldset for the entire likert scale
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Legend
        $html .= '<legend class="baf-fieldset-legend">';
        $html .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        if ( ! empty( $this->config['require_all'] ) ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Instructions for screen readers
        $html .= '<p id="' . esc_attr( $html_id ) . '-instructions" class="baf-likert-instructions">';
        $html .= esc_html__( 'For each statement, select your level of agreement using the radio buttons.', 'bits-accessible-forms' );
        $html .= '</p>';

        // Responsive table wrapper
        $html .= '<div class="baf-likert-wrapper" role="region" aria-label="' . esc_attr__( 'Likert scale matrix', 'bits-accessible-forms' ) . '" tabindex="0">';

        // Table
        $html .= '<table class="baf-likert-table" role="grid" aria-describedby="' . esc_attr( $html_id ) . '-instructions">';

        // Table header
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th scope="col" class="baf-likert-statement-header">' . esc_html__( 'Statement', 'bits-accessible-forms' ) . '</th>';
        
        foreach ( $columns as $col ) {
            $html .= '<th scope="col" class="baf-likert-option-header">';
            $html .= esc_html( $col['label'] );
            $html .= '</th>';
        }
        
        $html .= '</tr>';
        $html .= '</thead>';

        // Table body
        $html .= '<tbody>';
        
        foreach ( $rows as $row_index => $row ) {
            $row_value = $row['value'] ?? $row['label'];
            $row_name = $name . '[' . $row_value . ']';
            $row_id = $html_id . '-row-' . $row_index;
            $current_value = $values[ $row_value ] ?? '';

            $html .= '<tr>';
            
            // Row label (statement)
            $html .= '<th scope="row" class="baf-likert-statement" id="' . esc_attr( $row_id ) . '">';
            $html .= esc_html( $row['label'] );
            $html .= '</th>';

            // Options
            foreach ( $columns as $col_index => $col ) {
                $col_value = $col['value'] ?? $col['label'];
                $option_id = $row_id . '-col-' . $col_index;
                $is_checked = $current_value === $col_value;

                $html .= '<td class="baf-likert-option" role="gridcell">';
                $html .= '<input type="radio"';
                $html .= ' id="' . esc_attr( $option_id ) . '"';
                $html .= ' name="' . esc_attr( $row_name ) . '"';
                $html .= ' value="' . esc_attr( $col_value ) . '"';
                $html .= ' class="baf-likert-radio"';
                $html .= ' aria-labelledby="' . esc_attr( $row_id ) . '"';
                
                if ( $is_checked ) {
                    $html .= ' checked';
                }
                
                if ( ! empty( $this->config['require_all'] ) ) {
                    $html .= ' aria-required="true"';
                }
                
                $html .= '>';
                
                // Hidden label for screen readers (column header is announced separately)
                $html .= '<label for="' . esc_attr( $option_id ) . '" class="baf-likert-label">';
                $html .= '<span class="screen-reader-text">' . esc_html( $col['label'] ) . '</span>';
                $html .= '</label>';
                
                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

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
     * Get rows
     *
     * @return array Rows.
     */
    protected function get_rows() {
        $rows = $this->config['rows'] ?? array();

        if ( ! empty( $this->config['randomize_rows'] ) ) {
            shuffle( $rows );
        }

        return $rows;
    }

    /**
     * Get columns based on scale type
     *
     * @return array Columns.
     */
    protected function get_columns() {
        $scale_type = $this->config['scale_type'] ?? 'agreement';

        if ( $scale_type === 'custom' ) {
            return $this->config['columns'] ?? array();
        }

        $scales = array(
            'agreement' => array(
                array( 'value' => '1', 'label' => __( 'Strongly Disagree', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Disagree', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Agree', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Strongly Agree', 'bits-accessible-forms' ) ),
            ),
            'frequency' => array(
                array( 'value' => '1', 'label' => __( 'Never', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Rarely', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Sometimes', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Often', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Always', 'bits-accessible-forms' ) ),
            ),
            'satisfaction' => array(
                array( 'value' => '1', 'label' => __( 'Very Unsatisfied', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Unsatisfied', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Satisfied', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Very Satisfied', 'bits-accessible-forms' ) ),
            ),
            'importance' => array(
                array( 'value' => '1', 'label' => __( 'Not Important', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Slightly Important', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Moderately Important', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Important', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Very Important', 'bits-accessible-forms' ) ),
            ),
            'likelihood' => array(
                array( 'value' => '1', 'label' => __( 'Very Unlikely', 'bits-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Unlikely', 'bits-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'bits-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Likely', 'bits-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Very Likely', 'bits-accessible-forms' ) ),
            ),
        );

        return $scales[ $scale_type ] ?? $scales['agreement'];
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized values.
     */
    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $value as $key => $val ) {
            $sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $val );
        }

        return $sanitized;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( empty( $this->config['require_all'] ) ) {
            return;
        }

        $rows = $this->get_rows();
        $values = (array) $value;

        foreach ( $rows as $row ) {
            $row_value = $row['value'] ?? $row['label'];
            if ( empty( $values[ $row_value ] ) ) {
                $this->add_error( sprintf(
                    __( 'Please answer: %s', 'bits-accessible-forms' ),
                    $row['label']
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

        return empty( array_filter( $value ) );
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

        $rows = $this->get_rows();
        $columns = $this->get_columns();
        $output = array();

        foreach ( $rows as $row ) {
            $row_value = $row['value'] ?? $row['label'];
            $selected = $value[ $row_value ] ?? '';
            
            if ( $selected ) {
                $label = $selected;
                foreach ( $columns as $col ) {
                    if ( ( $col['value'] ?? $col['label'] ) === $selected ) {
                        $label = $col['label'];
                        break;
                    }
                }
                $output[] = $row['label'] . ': ' . $label;
            }
        }

        return implode( "\n", $output );
    }
}
