<?php
/**
 * Likert Scale Field - Uses fieldset/legend and table for accessibility
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Likert class
 * 
 * Matrix-style Likert scale with proper WCAG-compliant table markup.
 */
class QAF_Field_Likert extends QAF_Field {

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
                array( 'value' => 'q1', 'label' => __( 'Question 1', 'quill-accessible-forms' ) ),
                array( 'value' => 'q2', 'label' => __( 'Question 2', 'quill-accessible-forms' ) ),
            ),
            'columns' => array(
                array( 'value' => '1', 'label' => __( 'Strongly Disagree', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Disagree', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Agree', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Strongly Agree', 'quill-accessible-forms' ) ),
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
                    'label' => __( 'Group Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'rows' => array(
                    'type'  => 'repeater',
                    'label' => __( 'Rows (Statements)', 'quill-accessible-forms' ),
                    'fields' => array(
                        'value' => array( 'type' => 'text', 'label' => __( 'Value', 'quill-accessible-forms' ) ),
                        'label' => array( 'type' => 'text', 'label' => __( 'Label', 'quill-accessible-forms' ) ),
                    ),
                ),
            ),
            'scale' => array(
                'scale_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Scale Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'agreement'   => __( 'Agreement (Disagree to Agree)', 'quill-accessible-forms' ),
                        'frequency'   => __( 'Frequency (Never to Always)', 'quill-accessible-forms' ),
                        'satisfaction' => __( 'Satisfaction (Very Unsatisfied to Very Satisfied)', 'quill-accessible-forms' ),
                        'importance'  => __( 'Importance (Not Important to Very Important)', 'quill-accessible-forms' ),
                        'likelihood'  => __( 'Likelihood (Very Unlikely to Very Likely)', 'quill-accessible-forms' ),
                        'custom'      => __( 'Custom', 'quill-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'      => 'repeater',
                    'label'     => __( 'Columns (Scale Points)', 'quill-accessible-forms' ),
                    'condition' => array( 'scale_type' => 'custom' ),
                    'fields'    => array(
                        'value' => array( 'type' => 'text', 'label' => __( 'Value', 'quill-accessible-forms' ) ),
                        'label' => array( 'type' => 'text', 'label' => __( 'Label', 'quill-accessible-forms' ) ),
                    ),
                ),
            ),
            'validation' => array(
                'require_all' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require All Rows', 'quill-accessible-forms' ),
                    'desc'  => __( 'User must answer every row.', 'quill-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'randomize_rows' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Randomize Row Order', 'quill-accessible-forms' ),
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
        $name    = 'qaf_field_' . $this->get_id();
        $values  = (array) $this->get_value();
        $rows    = $this->get_rows();
        $columns = $this->get_columns();

        $wrapper_class = $this->get_wrapper_classes();
        
        // Use fieldset for the entire likert scale
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Legend
        $html .= '<legend class="qaf-fieldset-legend">';
        $html .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        if ( ! empty( $this->config['require_all'] ) ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Instructions for screen readers
        $html .= '<p id="' . esc_attr( $html_id ) . '-instructions" class="qaf-likert-instructions">';
        $html .= esc_html__( 'For each statement, select your level of agreement using the radio buttons.', 'quill-accessible-forms' );
        $html .= '</p>';

        // Responsive table wrapper
        $html .= '<div class="qaf-likert-wrapper" role="region" aria-label="' . esc_attr__( 'Likert scale matrix', 'quill-accessible-forms' ) . '" tabindex="0">';

        // Table
        $html .= '<table class="qaf-likert-table" role="grid" aria-describedby="' . esc_attr( $html_id ) . '-instructions">';

        // Table header
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th scope="col" class="qaf-likert-statement-header">' . esc_html__( 'Statement', 'quill-accessible-forms' ) . '</th>';
        
        foreach ( $columns as $col ) {
            $html .= '<th scope="col" class="qaf-likert-option-header">';
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
            $html .= '<th scope="row" class="qaf-likert-statement" id="' . esc_attr( $row_id ) . '">';
            $html .= esc_html( $row['label'] );
            $html .= '</th>';

            // Options
            foreach ( $columns as $col_index => $col ) {
                $col_value = $col['value'] ?? $col['label'];
                $option_id = $row_id . '-col-' . $col_index;
                $is_checked = $current_value === $col_value;

                $html .= '<td class="qaf-likert-option" role="gridcell">';
                $html .= '<input type="radio"';
                $html .= ' id="' . esc_attr( $option_id ) . '"';
                $html .= ' name="' . esc_attr( $row_name ) . '"';
                $html .= ' value="' . esc_attr( $col_value ) . '"';
                $html .= ' class="qaf-likert-radio"';
                $html .= ' aria-labelledby="' . esc_attr( $row_id ) . '"';
                
                if ( $is_checked ) {
                    $html .= ' checked';
                }
                
                if ( ! empty( $this->config['require_all'] ) ) {
                    $html .= ' aria-required="true"';
                }
                
                $html .= '>';
                
                // Hidden label for screen readers (column header is announced separately)
                $html .= '<label for="' . esc_attr( $option_id ) . '" class="qaf-likert-label">';
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
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
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
                array( 'value' => '1', 'label' => __( 'Strongly Disagree', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Disagree', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Agree', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Strongly Agree', 'quill-accessible-forms' ) ),
            ),
            'frequency' => array(
                array( 'value' => '1', 'label' => __( 'Never', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Rarely', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Sometimes', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Often', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Always', 'quill-accessible-forms' ) ),
            ),
            'satisfaction' => array(
                array( 'value' => '1', 'label' => __( 'Very Unsatisfied', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Unsatisfied', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Satisfied', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Very Satisfied', 'quill-accessible-forms' ) ),
            ),
            'importance' => array(
                array( 'value' => '1', 'label' => __( 'Not Important', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Slightly Important', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Moderately Important', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Important', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Very Important', 'quill-accessible-forms' ) ),
            ),
            'likelihood' => array(
                array( 'value' => '1', 'label' => __( 'Very Unlikely', 'quill-accessible-forms' ) ),
                array( 'value' => '2', 'label' => __( 'Unlikely', 'quill-accessible-forms' ) ),
                array( 'value' => '3', 'label' => __( 'Neutral', 'quill-accessible-forms' ) ),
                array( 'value' => '4', 'label' => __( 'Likely', 'quill-accessible-forms' ) ),
                array( 'value' => '5', 'label' => __( 'Very Likely', 'quill-accessible-forms' ) ),
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
                    __( 'Please answer: %s', 'quill-accessible-forms' ),
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
