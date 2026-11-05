<?php
/**
 * Table Input Field
 *
 * Editable spreadsheet-style grid input.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Table class
 */
class QAF_Field_Table extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'table';

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
            'columns'         => array(),           // Column definitions
            'min_rows'        => 1,
            'max_rows'        => 50,
            'default_rows'    => 3,
            'allow_add_rows'  => true,
            'allow_delete_rows' => true,
            'allow_reorder'   => true,
            'show_row_numbers' => true,
            'header_style'    => 'sticky',          // 'sticky', 'static', 'none'
            'stripe_rows'     => true,
            'cell_padding'    => 'normal',          // 'compact', 'normal', 'spacious'
            'mobile_layout'   => 'cards',           // 'cards', 'scroll'
            'export_csv'      => false,
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
            'columns' => array(
                'columns' => array(
                    'type'   => 'repeater',
                    'label'  => __( 'Columns', 'quill-accessible-forms' ),
                    'fields' => array(
                        'id'          => array( 'type' => 'text', 'label' => __( 'Column ID', 'quill-accessible-forms' ) ),
                        'label'       => array( 'type' => 'text', 'label' => __( 'Header Label', 'quill-accessible-forms' ) ),
                        'type'        => array(
                            'type'    => 'select',
                            'label'   => __( 'Input Type', 'quill-accessible-forms' ),
                            'options' => array(
                                'text'     => __( 'Text', 'quill-accessible-forms' ),
                                'number'   => __( 'Number', 'quill-accessible-forms' ),
                                'email'    => __( 'Email', 'quill-accessible-forms' ),
                                'date'     => __( 'Date', 'quill-accessible-forms' ),
                                'select'   => __( 'Dropdown', 'quill-accessible-forms' ),
                                'checkbox' => __( 'Checkbox', 'quill-accessible-forms' ),
                            ),
                        ),
                        'width'       => array( 'type' => 'number', 'label' => __( 'Width (px)', 'quill-accessible-forms' ) ),
                        'required'    => array( 'type' => 'toggle', 'label' => __( 'Required', 'quill-accessible-forms' ) ),
                        'options'     => array( 'type' => 'textarea', 'label' => __( 'Options (for dropdown)', 'quill-accessible-forms' ) ),
                        'placeholder' => array( 'type' => 'text', 'label' => __( 'Placeholder', 'quill-accessible-forms' ) ),
                    ),
                ),
            ),
            'rows' => array(
                'min_rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Rows', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Rows', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 100,
                ),
                'default_rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Rows', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'allow_add_rows' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Adding Rows', 'quill-accessible-forms' ),
                ),
                'allow_delete_rows' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Deleting Rows', 'quill-accessible-forms' ),
                ),
                'allow_reorder' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Reordering Rows', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'show_row_numbers' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Row Numbers', 'quill-accessible-forms' ),
                ),
                'header_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Header Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'sticky' => __( 'Sticky', 'quill-accessible-forms' ),
                        'static' => __( 'Static', 'quill-accessible-forms' ),
                        'none'   => __( 'Hidden', 'quill-accessible-forms' ),
                    ),
                ),
                'stripe_rows' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Striped Rows', 'quill-accessible-forms' ),
                ),
                'cell_padding' => array(
                    'type'    => 'select',
                    'label'   => __( 'Cell Padding', 'quill-accessible-forms' ),
                    'options' => array(
                        'compact'  => __( 'Compact', 'quill-accessible-forms' ),
                        'normal'   => __( 'Normal', 'quill-accessible-forms' ),
                        'spacious' => __( 'Spacious', 'quill-accessible-forms' ),
                    ),
                ),
                'mobile_layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Mobile Layout', 'quill-accessible-forms' ),
                    'options' => array(
                        'cards'  => __( 'Stacked Cards', 'quill-accessible-forms' ),
                        'scroll' => __( 'Horizontal Scroll', 'quill-accessible-forms' ),
                    ),
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
        $html_id        = $this->get_html_id();
        $name           = 'qaf_field_' . $this->get_id();
        $value          = $this->get_value();
        $columns        = $this->config['columns'] ?? array();
        $min_rows       = (int) ( $this->config['min_rows'] ?? 1 );
        $max_rows       = (int) ( $this->config['max_rows'] ?? 50 );
        $default_rows   = (int) ( $this->config['default_rows'] ?? 3 );
        $allow_add      = $this->config['allow_add_rows'] ?? true;
        $allow_delete   = $this->config['allow_delete_rows'] ?? true;
        $allow_reorder  = $this->config['allow_reorder'] ?? true;
        $show_numbers   = $this->config['show_row_numbers'] ?? true;
        $header_style   = $this->config['header_style'] ?? 'sticky';
        $stripe         = $this->config['stripe_rows'] ?? true;
        $padding        = $this->config['cell_padding'] ?? 'normal';
        $mobile         = $this->config['mobile_layout'] ?? 'cards';

        // Ensure we have at least default rows
        if ( ! is_array( $value ) ) {
            $value = array();
        }
        while ( count( $value ) < $default_rows && count( $value ) < $max_rows ) {
            $value[] = array();
        }

        $wrapper_classes = array(
            'qaf-table-wrapper',
            'qaf-table-header-' . $header_style,
            'qaf-table-padding-' . $padding,
            'qaf-table-mobile-' . $mobile,
        );
        if ( $stripe ) {
            $wrapper_classes[] = 'qaf-table-striped';
        }

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';
        $field .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $field .= ' data-min-rows="' . esc_attr( $min_rows ) . '"';
        $field .= ' data-max-rows="' . esc_attr( $max_rows ) . '"';
        $field .= ' data-allow-add="' . esc_attr( $allow_add ? 'true' : 'false' ) . '"';
        $field .= ' data-allow-delete="' . esc_attr( $allow_delete ? 'true' : 'false' ) . '"';
        $field .= ' data-allow-reorder="' . esc_attr( $allow_reorder ? 'true' : 'false' ) . '">';

        // Label
        $field .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Table
        $field .= '<div class="qaf-table-scroll">';
        $field .= '<table class="qaf-table" id="' . esc_attr( $html_id ) . '-table" role="grid">';

        // Header
        if ( $header_style !== 'none' ) {
            $field .= '<thead>';
            $field .= '<tr>';
            
            if ( $show_numbers ) {
                $field .= '<th scope="col" class="qaf-table-num-col">#</th>';
            }
            if ( $allow_reorder ) {
                $field .= '<th scope="col" class="qaf-table-drag-col"><span class="screen-reader-text">' . esc_html__( 'Reorder', 'quill-accessible-forms' ) . '</span></th>';
            }
            
            foreach ( $columns as $col ) {
                $width_style = ! empty( $col['width'] ) ? ' style="width: ' . intval( $col['width'] ) . 'px;"' : '';
                $required = ! empty( $col['required'] ) ? ' <span class="qaf-required">*</span>' : '';
                $field .= '<th scope="col"' . $width_style . '>';
                $field .= esc_html( $col['label'] ?? '' ) . $required;
                $field .= '</th>';
            }

            if ( $allow_delete ) {
                $field .= '<th scope="col" class="qaf-table-action-col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'quill-accessible-forms' ) . '</span></th>';
            }

            $field .= '</tr>';
            $field .= '</thead>';
        }

        // Body
        $field .= '<tbody id="' . esc_attr( $html_id ) . '-body">';
        foreach ( $value as $row_index => $row_data ) {
            $field .= $this->render_row( $html_id, $name, $columns, $row_index, $row_data, $show_numbers, $allow_reorder, $allow_delete, $min_rows, count( $value ) );
        }
        $field .= '</tbody>';

        $field .= '</table>';
        $field .= '</div>';

        // Mobile card view
        if ( $mobile === 'cards' ) {
            $field .= '<div class="qaf-table-mobile-cards" aria-hidden="true">';
            foreach ( $value as $row_index => $row_data ) {
                $field .= $this->render_mobile_card( $html_id, $name, $columns, $row_index, $row_data, $allow_delete, $min_rows, count( $value ) );
            }
            $field .= '</div>';
        }

        // Controls
        $field .= '<div class="qaf-table-controls">';
        
        if ( $allow_add ) {
            $add_disabled = count( $value ) >= $max_rows ? ' disabled' : '';
            $field .= '<button type="button" class="qaf-table-add-btn"' . $add_disabled . '>';
            $field .= '<span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>';
            $field .= esc_html__( 'Add Row', 'quill-accessible-forms' );
            $field .= '</button>';
        }

        $field .= '<span class="qaf-table-count" aria-live="polite">';
        $field .= sprintf(
            /* translators: 1: current count, 2: max count */
            __( '%1$d of %2$d rows', 'quill-accessible-forms' ),
            count( $value ),
            $max_rows
        );
        $field .= '</span>';

        $field .= '</div>';

        // Row template (hidden)
        $field .= '<template class="qaf-table-row-template">';
        $field .= $this->render_row( $html_id, $name, $columns, '{{index}}', array(), $show_numbers, $allow_reorder, $allow_delete, $min_rows, 0 );
        $field .= '</template>';

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

        $field .= '</div>';

        return $field;
    }

    /**
     * Render a table row
     *
     * @param string $html_id       Field ID.
     * @param string $name          Field name.
     * @param array  $columns       Column definitions.
     * @param mixed  $row_index     Row index.
     * @param array  $row_data      Row data.
     * @param bool   $show_numbers  Show row numbers.
     * @param bool   $allow_reorder Allow reordering.
     * @param bool   $allow_delete  Allow deletion.
     * @param int    $min_rows      Minimum rows.
     * @param int    $total_rows    Current total rows.
     * @return string HTML.
     */
    protected function render_row( $html_id, $name, $columns, $row_index, $row_data, $show_numbers, $allow_reorder, $allow_delete, $min_rows, $total_rows ) {
        $html = '<tr class="qaf-table-row" data-row="' . esc_attr( $row_index ) . '">';

        // Row number
        if ( $show_numbers ) {
            $display_num = is_numeric( $row_index ) ? ( $row_index + 1 ) : $row_index;
            $html .= '<td class="qaf-table-num-cell">' . esc_html( $display_num ) . '</td>';
        }

        // Drag handle
        if ( $allow_reorder ) {
            $html .= '<td class="qaf-table-drag-cell">';
            $html .= '<button type="button" class="qaf-table-drag-handle" aria-label="' . esc_attr__( 'Drag to reorder row', 'quill-accessible-forms' ) . '">';
            $html .= '<span class="dashicons dashicons-menu" aria-hidden="true"></span>';
            $html .= '</button>';
            $html .= '</td>';
        }

        // Data cells
        foreach ( $columns as $col_index => $col ) {
            $col_id = $col['id'] ?? 'col_' . $col_index;
            $cell_value = $row_data[ $col_id ] ?? '';
            $cell_id = $html_id . '-' . $row_index . '-' . $col_id;
            $cell_name = $name . '[' . $row_index . '][' . $col_id . ']';

            $html .= '<td class="qaf-table-cell" data-col="' . esc_attr( $col_id ) . '">';
            $html .= $this->render_cell_input( $col, $cell_id, $cell_name, $cell_value );
            $html .= '</td>';
        }

        // Delete button
        if ( $allow_delete ) {
            $delete_disabled = $total_rows <= $min_rows ? ' disabled' : '';
            $html .= '<td class="qaf-table-action-cell">';
            $html .= '<button type="button" class="qaf-table-delete-btn"' . $delete_disabled . ' ';
            $html .= 'aria-label="' . esc_attr__( 'Delete row', 'quill-accessible-forms' ) . '">';
            $html .= '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
            $html .= '</button>';
            $html .= '</td>';
        }

        $html .= '</tr>';

        return $html;
    }

    /**
     * Render a mobile card
     *
     * @param string $html_id      Field ID.
     * @param string $name         Field name.
     * @param array  $columns      Column definitions.
     * @param int    $row_index    Row index.
     * @param array  $row_data     Row data.
     * @param bool   $allow_delete Allow deletion.
     * @param int    $min_rows     Minimum rows.
     * @param int    $total_rows   Current total rows.
     * @return string HTML.
     */
    protected function render_mobile_card( $html_id, $name, $columns, $row_index, $row_data, $allow_delete, $min_rows, $total_rows ) {
        $html = '<div class="qaf-table-card" data-row="' . esc_attr( $row_index ) . '">';

        $html .= '<div class="qaf-card-header">';
        $html .= '<span class="qaf-card-number">' . sprintf( __( 'Row %d', 'quill-accessible-forms' ), $row_index + 1 ) . '</span>';
        if ( $allow_delete ) {
            $delete_disabled = $total_rows <= $min_rows ? ' disabled' : '';
            $html .= '<button type="button" class="qaf-table-delete-btn"' . $delete_disabled . '>';
            $html .= '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
            $html .= '</button>';
        }
        $html .= '</div>';

        $html .= '<div class="qaf-card-body">';
        foreach ( $columns as $col_index => $col ) {
            $col_id = $col['id'] ?? 'col_' . $col_index;
            $cell_value = $row_data[ $col_id ] ?? '';
            $cell_id = $html_id . '-mobile-' . $row_index . '-' . $col_id;
            $cell_name = $name . '[' . $row_index . '][' . $col_id . ']';

            $html .= '<div class="qaf-card-field">';
            $html .= '<label for="' . esc_attr( $cell_id ) . '">' . esc_html( $col['label'] ?? '' ) . '</label>';
            $html .= $this->render_cell_input( $col, $cell_id, $cell_name, $cell_value );
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render cell input based on column type
     *
     * @param array  $col   Column definition.
     * @param string $id    Input ID.
     * @param string $name  Input name.
     * @param mixed  $value Current value.
     * @return string HTML.
     */
    protected function render_cell_input( $col, $id, $name, $value ) {
        $type = $col['type'] ?? 'text';
        $placeholder = $col['placeholder'] ?? '';
        $required = ! empty( $col['required'] ) ? ' required' : '';
        $aria_required = ! empty( $col['required'] ) ? ' aria-required="true"' : '';

        switch ( $type ) {
            case 'number':
                return '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="qaf-table-input qaf-table-input-number" placeholder="' . esc_attr( $placeholder ) . '"' . $required . $aria_required . '>';

            case 'email':
                return '<input type="email" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="qaf-table-input qaf-table-input-email" placeholder="' . esc_attr( $placeholder ) . '"' . $required . $aria_required . '>';

            case 'date':
                return '<input type="date" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="qaf-table-input qaf-table-input-date"' . $required . $aria_required . '>';

            case 'select':
                $options = $this->parse_select_options( $col['options'] ?? '' );
                $html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="qaf-table-input qaf-table-input-select"' . $required . $aria_required . '>';
                $html .= '<option value="">' . esc_html( $placeholder ?: __( 'Select...', 'quill-accessible-forms' ) ) . '</option>';
                foreach ( $options as $opt_value => $opt_label ) {
                    $selected = $value === $opt_value ? ' selected' : '';
                    $html .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
                }
                $html .= '</select>';
                return $html;

            case 'checkbox':
                $checked = ! empty( $value ) ? ' checked' : '';
                return '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" class="qaf-table-input qaf-table-input-checkbox"' . $checked . '>';

            default: // text
                return '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="qaf-table-input qaf-table-input-text" placeholder="' . esc_attr( $placeholder ) . '"' . $required . $aria_required . '>';
        }
    }

    /**
     * Parse select options from string
     *
     * @param string $options Options string (one per line, or value|label format).
     * @return array Parsed options.
     */
    protected function parse_select_options( $options ) {
        if ( empty( $options ) ) {
            return array();
        }

        if ( is_array( $options ) ) {
            return $options;
        }

        $parsed = array();
        $lines = explode( "\n", trim( $options ) );
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            
            if ( strpos( $line, '|' ) !== false ) {
                list( $value, $label ) = explode( '|', $line, 2 );
                $parsed[ trim( $value ) ] = trim( $label );
            } else {
                $parsed[ $line ] = $line;
            }
        }

        return $parsed;
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

        $columns = $this->config['columns'] ?? array();
        $sanitized = array();

        foreach ( $value as $row_index => $row_data ) {
            if ( ! is_array( $row_data ) ) {
                continue;
            }

            $sanitized_row = array();
            foreach ( $columns as $col_index => $col ) {
                $col_id = $col['id'] ?? 'col_' . $col_index;
                $col_type = $col['type'] ?? 'text';
                $cell_value = $row_data[ $col_id ] ?? '';

                switch ( $col_type ) {
                    case 'number':
                        $sanitized_row[ $col_id ] = is_numeric( $cell_value ) ? floatval( $cell_value ) : '';
                        break;
                    case 'email':
                        $sanitized_row[ $col_id ] = sanitize_email( $cell_value );
                        break;
                    case 'date':
                        $sanitized_row[ $col_id ] = sanitize_text_field( $cell_value );
                        break;
                    case 'checkbox':
                        $sanitized_row[ $col_id ] = ! empty( $cell_value ) ? '1' : '';
                        break;
                    default:
                        $sanitized_row[ $col_id ] = sanitize_text_field( $cell_value );
                }
            }

            // Only keep non-empty rows
            $has_data = array_filter( $sanitized_row, function( $v ) {
                return $v !== '' && $v !== null;
            } );

            if ( ! empty( $has_data ) ) {
                $sanitized[] = $sanitized_row;
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
        if ( ! is_array( $value ) ) {
            return;
        }

        $columns = $this->config['columns'] ?? array();
        $min_rows = (int) ( $this->config['min_rows'] ?? 1 );
        $max_rows = (int) ( $this->config['max_rows'] ?? 50 );

        // Check row count
        $row_count = count( $value );
        if ( $row_count < $min_rows ) {
            $this->add_error( sprintf(
                /* translators: %d: minimum rows */
                __( 'Please enter at least %d rows.', 'quill-accessible-forms' ),
                $min_rows
            ) );
            return;
        }

        if ( $row_count > $max_rows ) {
            $this->add_error( sprintf(
                /* translators: %d: maximum rows */
                __( 'Maximum %d rows allowed.', 'quill-accessible-forms' ),
                $max_rows
            ) );
            return;
        }

        // Validate required columns
        foreach ( $value as $row_index => $row_data ) {
            foreach ( $columns as $col_index => $col ) {
                if ( empty( $col['required'] ) ) {
                    continue;
                }

                $col_id = $col['id'] ?? 'col_' . $col_index;
                $cell_value = $row_data[ $col_id ] ?? '';

                if ( $cell_value === '' || $cell_value === null ) {
                    $this->add_error( sprintf(
                        /* translators: 1: column name, 2: row number */
                        __( '%1$s is required in row %2$d.', 'quill-accessible-forms' ),
                        $col['label'] ?? $col_id,
                        $row_index + 1
                    ) );
                }
            }
        }
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return '';
        }

        $columns = $this->config['columns'] ?? array();
        $col_labels = array();
        foreach ( $columns as $col_index => $col ) {
            $col_id = $col['id'] ?? 'col_' . $col_index;
            $col_labels[ $col_id ] = $col['label'] ?? $col_id;
        }

        $output = array();
        foreach ( $value as $row_index => $row_data ) {
            $row_parts = array();
            foreach ( $row_data as $col_id => $cell_value ) {
                if ( $cell_value !== '' && $cell_value !== null ) {
                    $label = $col_labels[ $col_id ] ?? $col_id;
                    $row_parts[] = $label . ': ' . $cell_value;
                }
            }
            if ( ! empty( $row_parts ) ) {
                $output[] = 'Row ' . ( $row_index + 1 ) . ': ' . implode( ', ', $row_parts );
            }
        }

        return implode( "\n", $output );
    }
}
