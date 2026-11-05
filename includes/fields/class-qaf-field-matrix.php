<?php
/**
 * Matrix/Grid Field
 *
 * Flexible rows × columns input grid (more flexible than Likert).
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Matrix class
 */
class QAF_Field_Matrix extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'matrix';

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
            'rows'            => array(),
            'columns'         => array(),
            'input_type'      => 'radio',       // 'radio', 'checkbox', 'text', 'number', 'select'
            'select_options'  => array(),       // For select input type
            'require_all'     => false,
            'alternate_rows'  => true,
            'sticky_header'   => false,
            'mobile_layout'   => 'stacked',     // 'stacked', 'scroll'
            'row_label_width' => 200,
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
                'rows' => array(
                    'type'   => 'repeater_simple',
                    'label'  => __( 'Rows', 'quill-accessible-forms' ),
                    'fields' => array(
                        'value' => __( 'Value', 'quill-accessible-forms' ),
                        'label' => __( 'Label', 'quill-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'   => 'repeater_simple',
                    'label'  => __( 'Columns', 'quill-accessible-forms' ),
                    'fields' => array(
                        'value' => __( 'Value', 'quill-accessible-forms' ),
                        'label' => __( 'Label', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'input' => array(
                'input_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Input Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'radio'    => __( 'Radio (Single Choice per Row)', 'quill-accessible-forms' ),
                        'checkbox' => __( 'Checkbox (Multiple per Row)', 'quill-accessible-forms' ),
                        'text'     => __( 'Text Input', 'quill-accessible-forms' ),
                        'number'   => __( 'Number Input', 'quill-accessible-forms' ),
                        'select'   => __( 'Dropdown', 'quill-accessible-forms' ),
                    ),
                ),
                'select_options' => array(
                    'type'   => 'repeater_simple',
                    'label'  => __( 'Dropdown Options (for Select type)', 'quill-accessible-forms' ),
                    'fields' => array(
                        'value' => __( 'Value', 'quill-accessible-forms' ),
                        'label' => __( 'Label', 'quill-accessible-forms' ),
                    ),
                ),
                'require_all' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require All Rows', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'alternate_rows' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Alternate Row Colors', 'quill-accessible-forms' ),
                ),
                'sticky_header' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Sticky Column Headers', 'quill-accessible-forms' ),
                ),
                'mobile_layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Mobile Layout', 'quill-accessible-forms' ),
                    'options' => array(
                        'stacked' => __( 'Stacked Cards', 'quill-accessible-forms' ),
                        'scroll'  => __( 'Horizontal Scroll', 'quill-accessible-forms' ),
                    ),
                ),
                'row_label_width' => array(
                    'type'  => 'number',
                    'label' => __( 'Row Label Width (px)', 'quill-accessible-forms' ),
                    'min'   => 100,
                    'max'   => 400,
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
        $html_id      = $this->get_html_id();
        $name         = 'qaf_field_' . $this->get_id();
        $value        = $this->get_value();
        $rows         = $this->config['rows'] ?? array();
        $columns      = $this->config['columns'] ?? array();
        $input_type   = $this->config['input_type'] ?? 'radio';
        $alternate    = $this->config['alternate_rows'] ?? true;
        $sticky       = $this->config['sticky_header'] ?? false;
        $mobile       = $this->config['mobile_layout'] ?? 'stacked';
        $row_width    = (int) ( $this->config['row_label_width'] ?? 200 );

        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $wrapper_classes = array(
            'qaf-matrix-wrapper',
            'qaf-mobile-' . $mobile,
        );
        if ( $alternate ) {
            $wrapper_classes[] = 'qaf-alternate-rows';
        }
        if ( $sticky ) {
            $wrapper_classes[] = 'qaf-sticky-header';
        }

        // Use fieldset/legend
        $field = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';
        $field .= ' style="--qaf-row-label-width: ' . esc_attr( $row_width ) . 'px;">';

        $field .= '<legend class="qaf-fieldset-legend">';
        $field .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $field .= $this->render_required_indicator();
        $field .= '</legend>';

        // Description
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Table
        $field .= '<div class="qaf-matrix-scroll">';
        $field .= '<table class="qaf-matrix-table" role="grid">';

        // Header row
        $field .= '<thead>';
        $field .= '<tr>';
        $field .= '<th scope="col" class="qaf-matrix-corner"></th>';
        foreach ( $columns as $col_index => $column ) {
            $col_label = $column['label'] ?? $column['value'] ?? '';
            $field .= '<th scope="col" class="qaf-matrix-col-header" id="' . esc_attr( $html_id . '-col-' . $col_index ) . '">';
            $field .= esc_html( $col_label );
            $field .= '</th>';
        }
        $field .= '</tr>';
        $field .= '</thead>';

        // Body rows
        $field .= '<tbody>';
        foreach ( $rows as $row_index => $row ) {
            $row_value = $row['value'] ?? $row['label'] ?? 'row_' . $row_index;
            $row_label = $row['label'] ?? $row_value;
            $row_id = $html_id . '-row-' . $row_index;

            $field .= '<tr>';
            $field .= '<th scope="row" class="qaf-matrix-row-header" id="' . esc_attr( $row_id ) . '">';
            $field .= esc_html( $row_label );
            $field .= '</th>';

            foreach ( $columns as $col_index => $column ) {
                $col_value = $column['value'] ?? $column['label'] ?? 'col_' . $col_index;
                $col_id = $html_id . '-col-' . $col_index;
                $cell_id = $html_id . '-' . $row_index . '-' . $col_index;
                $cell_name = $name . '[' . $row_value . ']';
                $cell_value = $value[ $row_value ] ?? '';

                $field .= '<td class="qaf-matrix-cell" role="gridcell">';
                $field .= $this->render_cell_input(
                    $input_type,
                    $cell_id,
                    $cell_name,
                    $col_value,
                    $cell_value,
                    $row_label,
                    $column['label'] ?? $col_value
                );
                $field .= '</td>';
            }

            $field .= '</tr>';
        }
        $field .= '</tbody>';

        $field .= '</table>';
        $field .= '</div>';

        // Mobile stacked view (hidden on desktop)
        if ( $mobile === 'stacked' ) {
            $field .= $this->render_mobile_stacked( $html_id, $name, $value, $rows, $columns, $input_type );
        }

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $field .= $this->render_description();
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</fieldset>';

        return $field;
    }

    /**
     * Render a cell input based on type
     *
     * @param string $type       Input type.
     * @param string $id         Input ID.
     * @param string $name       Input name.
     * @param string $col_value  Column value.
     * @param mixed  $cell_value Current cell value.
     * @param string $row_label  Row label for aria.
     * @param string $col_label  Column label for aria.
     * @return string Input HTML.
     */
    protected function render_cell_input( $type, $id, $name, $col_value, $cell_value, $row_label, $col_label ) {
        $aria_label = sprintf(
            /* translators: 1: row label, 2: column label */
            __( '%1$s, %2$s', 'quill-accessible-forms' ),
            $row_label,
            $col_label
        );

        switch ( $type ) {
            case 'radio':
                $checked = $cell_value === $col_value ? ' checked' : '';
                return '<input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $col_value ) . '"' . $checked . ' aria-label="' . esc_attr( $aria_label ) . '">';

            case 'checkbox':
                $name_arr = $name . '[]';
                $checked = is_array( $cell_value ) && in_array( $col_value, $cell_value, true ) ? ' checked' : '';
                return '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_arr ) . '" value="' . esc_attr( $col_value ) . '"' . $checked . ' aria-label="' . esc_attr( $aria_label ) . '">';

            case 'text':
                $name_col = $name . '[' . $col_value . ']';
                $val = is_array( $cell_value ) ? ( $cell_value[ $col_value ] ?? '' ) : '';
                return '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_col ) . '" value="' . esc_attr( $val ) . '" class="qaf-matrix-text" aria-label="' . esc_attr( $aria_label ) . '">';

            case 'number':
                $name_col = $name . '[' . $col_value . ']';
                $val = is_array( $cell_value ) ? ( $cell_value[ $col_value ] ?? '' ) : '';
                return '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_col ) . '" value="' . esc_attr( $val ) . '" class="qaf-matrix-number" aria-label="' . esc_attr( $aria_label ) . '">';

            case 'select':
                $name_col = $name . '[' . $col_value . ']';
                $val = is_array( $cell_value ) ? ( $cell_value[ $col_value ] ?? '' ) : '';
                $options = $this->config['select_options'] ?? array();
                
                $html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name_col ) . '" class="qaf-matrix-select" aria-label="' . esc_attr( $aria_label ) . '">';
                $html .= '<option value="">' . esc_html__( 'Select...', 'quill-accessible-forms' ) . '</option>';
                foreach ( $options as $opt ) {
                    $opt_value = $opt['value'] ?? $opt['label'] ?? '';
                    $opt_label = $opt['label'] ?? $opt_value;
                    $selected = $val === $opt_value ? ' selected' : '';
                    $html .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
                }
                $html .= '</select>';
                return $html;

            default:
                return '';
        }
    }

    /**
     * Render mobile stacked view
     *
     * @param string $html_id    Field ID.
     * @param string $name       Field name.
     * @param array  $value      Current values.
     * @param array  $rows       Row definitions.
     * @param array  $columns    Column definitions.
     * @param string $input_type Input type.
     * @return string Mobile HTML.
     */
    protected function render_mobile_stacked( $html_id, $name, $value, $rows, $columns, $input_type ) {
        $html = '<div class="qaf-matrix-mobile" aria-hidden="true">';

        foreach ( $rows as $row_index => $row ) {
            $row_value = $row['value'] ?? $row['label'] ?? 'row_' . $row_index;
            $row_label = $row['label'] ?? $row_value;
            $cell_value = $value[ $row_value ] ?? '';

            $html .= '<div class="qaf-matrix-card">';
            $html .= '<div class="qaf-matrix-card-header">' . esc_html( $row_label ) . '</div>';
            $html .= '<div class="qaf-matrix-card-body">';

            foreach ( $columns as $col_index => $column ) {
                $col_value = $column['value'] ?? $column['label'] ?? 'col_' . $col_index;
                $col_label = $column['label'] ?? $col_value;
                $cell_id = $html_id . '-mobile-' . $row_index . '-' . $col_index;
                $cell_name = $name . '[' . $row_value . ']';

                $html .= '<div class="qaf-matrix-card-row">';
                $html .= '<span class="qaf-matrix-card-label">' . esc_html( $col_label ) . '</span>';
                $html .= $this->render_cell_input( $input_type, $cell_id, $cell_name, $col_value, $cell_value, $row_label, $col_label );
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized value.
     */
    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        $input_type = $this->config['input_type'] ?? 'radio';

        foreach ( $value as $row => $row_value ) {
            $row = sanitize_key( $row );
            
            if ( is_array( $row_value ) ) {
                $sanitized[ $row ] = array_map( 'sanitize_text_field', $row_value );
            } else {
                $sanitized[ $row ] = sanitize_text_field( $row_value );
            }
        }

        return $sanitized;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( ! empty( $this->config['require_all'] ) ) {
            $rows = $this->config['rows'] ?? array();
            
            foreach ( $rows as $row ) {
                $row_value = $row['value'] ?? $row['label'] ?? '';
                if ( empty( $value[ $row_value ] ) ) {
                    $this->add_error( sprintf(
                        /* translators: %s: row label */
                        __( 'Please complete the row: %s', 'quill-accessible-forms' ),
                        $row['label'] ?? $row_value
                    ) );
                    return;
                }
            }
        }
    }
}
