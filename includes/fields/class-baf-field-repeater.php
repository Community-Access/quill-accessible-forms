<?php
/**
 * Repeater Field - Dynamic field group repeating
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Repeater class
 */
class BAF_Field_Repeater extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'repeater';

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
            'fields'            => array(),
            'min_rows'          => 1,
            'max_rows'          => 10,
            'default_rows'      => 1,
            'row_label'         => __( 'Row', 'bits-accessible-forms' ),
            'add_button_text'   => __( 'Add Row', 'bits-accessible-forms' ),
            'remove_button_text' => __( 'Remove', 'bits-accessible-forms' ),
            'layout'            => 'block',
            'collapsible'       => false,
            'sortable'          => true,
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
                'fields' => array(
                    'type'  => 'repeater_builder',
                    'label' => __( 'Fields in Repeater', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'limits' => array(
                'min_rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Rows', 'bits-accessible-forms' ),
                    'min'   => 0,
                    'max'   => 100,
                ),
                'max_rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Rows', 'bits-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 100,
                ),
                'default_rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Rows', 'bits-accessible-forms' ),
                    'min'   => 0,
                    'max'   => 100,
                ),
            ),
            'appearance' => array(
                'row_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Row Label', 'bits-accessible-forms' ),
                ),
                'add_button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Add Button Text', 'bits-accessible-forms' ),
                ),
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'bits-accessible-forms' ),
                    'options' => array(
                        'block' => __( 'Block (stacked)', 'bits-accessible-forms' ),
                        'table' => __( 'Table', 'bits-accessible-forms' ),
                        'inline' => __( 'Inline', 'bits-accessible-forms' ),
                    ),
                ),
                'collapsible' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Collapsible Rows', 'bits-accessible-forms' ),
                ),
                'sortable' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Sortable Rows', 'bits-accessible-forms' ),
                    'desc'  => __( 'Allows keyboard and drag-drop reordering.', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the repeater field with accessible controls
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $values  = (array) $this->get_value();
        $fields  = $this->config['fields'] ?? array();
        $min     = (int) $this->config['min_rows'];
        $max     = (int) $this->config['max_rows'];
        $default = (int) $this->config['default_rows'];
        $layout  = $this->config['layout'] ?? 'block';

        // Ensure minimum rows
        $num_rows = max( count( $values ), $default, $min );
        if ( empty( $values ) ) {
            $values = array_fill( 0, $num_rows, array() );
        }

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-layout-' . $layout;
        
        if ( ! empty( $this->config['sortable'] ) ) {
            $wrapper_class[] = 'baf-sortable';
        }

        // Use fieldset/legend for the repeater group
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-min-rows="' . esc_attr( $min ) . '"';
        $html .= ' data-max-rows="' . esc_attr( $max ) . '">';

        // Legend
        $html .= '<legend class="baf-fieldset-legend">';
        $html .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Instructions for screen readers
        $html .= '<p id="' . esc_attr( $html_id ) . '-instructions" class="screen-reader-text">';
        $html .= sprintf(
            __( 'This repeater allows %1$d to %2$d rows. Use the buttons to add or remove rows.', 'bits-accessible-forms' ),
            $min,
            $max
        );
        if ( ! empty( $this->config['sortable'] ) ) {
            $html .= ' ' . __( 'Rows can be reordered using the move buttons.', 'bits-accessible-forms' );
        }
        $html .= '</p>';

        // Rows container
        $html .= '<div class="baf-repeater-rows" role="list" aria-describedby="' . esc_attr( $html_id ) . '-instructions">';

        // Row template (hidden, for JS cloning)
        $html .= '<template class="baf-repeater-template">';
        $html .= $this->render_row( $fields, $name, array(), '{{INDEX}}', $max );
        $html .= '</template>';

        // Render existing rows
        foreach ( $values as $index => $row_values ) {
            $html .= $this->render_row( $fields, $name, $row_values, $index, $max );
        }

        $html .= '</div>';

        // Add row button
        $can_add = count( $values ) < $max;
        $html .= '<button type="button" class="baf-repeater-add baf-button baf-button-secondary"';
        $html .= ' aria-label="' . esc_attr( $this->config['add_button_text'] ) . '"';
        if ( ! $can_add ) {
            $html .= ' disabled aria-disabled="true"';
        }
        $html .= '>';
        $html .= '<span class="baf-icon-plus" aria-hidden="true"></span> ';
        $html .= esc_html( $this->config['add_button_text'] );
        $html .= '</button>';

        // Row count status
        $html .= '<div class="baf-repeater-status" role="status" aria-live="polite" aria-atomic="true">';
        $html .= sprintf(
            esc_html__( '%1$d of %2$d rows maximum', 'bits-accessible-forms' ),
            count( $values ),
            $max
        );
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
     * Render a single row
     *
     * @param array      $fields      Field configurations.
     * @param string     $name        Base field name.
     * @param array      $values      Row values.
     * @param int|string $index       Row index.
     * @param int        $max         Maximum rows.
     * @return string Row HTML.
     */
    protected function render_row( $fields, $name, $values, $index, $max ) {
        $row_id = $this->get_html_id() . '-row-' . $index;
        $row_label = $this->config['row_label'] ?? __( 'Row', 'bits-accessible-forms' );
        $collapsible = ! empty( $this->config['collapsible'] );
        $sortable = ! empty( $this->config['sortable'] );
        $min = (int) $this->config['min_rows'];

        $html = '<div class="baf-repeater-row" role="listitem" data-index="' . esc_attr( $index ) . '">';

        // Row header
        $html .= '<div class="baf-repeater-row-header">';

        // Drag handle / sort controls
        if ( $sortable ) {
            $html .= '<span class="baf-repeater-handle" aria-hidden="true">';
            $html .= '<span class="baf-icon-grip"></span>';
            $html .= '</span>';

            $html .= '<span class="baf-repeater-sort-buttons">';
            $html .= '<button type="button" class="baf-repeater-move-up" ';
            $html .= 'aria-label="' . esc_attr( sprintf( __( 'Move %s up', 'bits-accessible-forms' ), $row_label ) ) . '">';
            $html .= '<span class="baf-icon-arrow-up" aria-hidden="true"></span>';
            $html .= '</button>';
            $html .= '<button type="button" class="baf-repeater-move-down" ';
            $html .= 'aria-label="' . esc_attr( sprintf( __( 'Move %s down', 'bits-accessible-forms' ), $row_label ) ) . '">';
            $html .= '<span class="baf-icon-arrow-down" aria-hidden="true"></span>';
            $html .= '</button>';
            $html .= '</span>';
        }

        // Row label/title
        $row_num = is_numeric( $index ) ? (int) $index + 1 : $index;
        
        if ( $collapsible ) {
            $html .= '<button type="button" class="baf-repeater-toggle" ';
            $html .= 'aria-expanded="true" ';
            $html .= 'aria-controls="' . esc_attr( $row_id ) . '-content">';
            $html .= '<span class="baf-icon-chevron" aria-hidden="true"></span> ';
            $html .= '<span class="baf-repeater-row-title">' . esc_html( $row_label ) . ' ' . $row_num . '</span>';
            $html .= '</button>';
        } else {
            $html .= '<span class="baf-repeater-row-title">' . esc_html( $row_label ) . ' ' . $row_num . '</span>';
        }

        // Remove button
        $html .= '<button type="button" class="baf-repeater-remove" ';
        $html .= 'aria-label="' . esc_attr( sprintf( __( 'Remove %s %s', 'bits-accessible-forms' ), $row_label, $row_num ) ) . '">';
        $html .= '<span class="baf-icon-close" aria-hidden="true"></span>';
        $html .= '<span class="baf-remove-text">' . esc_html( $this->config['remove_button_text'] ?? __( 'Remove', 'bits-accessible-forms' ) ) . '</span>';
        $html .= '</button>';

        $html .= '</div>';

        // Row content (fields)
        $html .= '<div id="' . esc_attr( $row_id ) . '-content" class="baf-repeater-row-content">';

        foreach ( $fields as $field_index => $field_config ) {
            $field_name = $name . '[' . $index . '][' . $field_index . ']';
            $field_value = $values[ $field_index ] ?? '';
            
            $html .= $this->render_sub_field( $field_config, $field_name, $field_value, $index, $field_index );
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a sub-field within a row
     *
     * @param array      $config      Field configuration.
     * @param string     $name        Field name.
     * @param mixed      $value       Field value.
     * @param int|string $row_index   Row index.
     * @param int        $field_index Field index.
     * @return string Field HTML.
     */
    protected function render_sub_field( $config, $name, $value, $row_index, $field_index ) {
        $field_type = $config['type'] ?? 'text';
        $field_class = $this->get_field_class( $field_type );

        if ( ! $field_class || ! class_exists( $field_class ) ) {
            return '';
        }

        // Create temporary field instance
        $config['id'] = $this->get_id() . '_' . $row_index . '_' . $field_index;
        $field = new $field_class( $config );
        $field->set_value( $value );

        // Override the field name
        $html = str_replace(
            'name="baf_field_' . $config['id'] . '"',
            'name="' . esc_attr( $name ) . '"',
            $field->render()
        );

        return $html;
    }

    /**
     * Get field class name for a type
     *
     * @param string $type Field type.
     * @return string|false Class name or false.
     */
    protected function get_field_class( $type ) {
        $registry = BAF_Field_Registry::get_instance();
        $fields = $registry->get_all_fields();
        
        return $fields[ $type ]['class'] ?? false;
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
        $fields = $this->config['fields'] ?? array();

        foreach ( $value as $row_index => $row_values ) {
            if ( ! is_array( $row_values ) ) {
                continue;
            }

            $sanitized_row = array();
            
            foreach ( $row_values as $field_index => $field_value ) {
                if ( ! isset( $fields[ $field_index ] ) ) {
                    continue;
                }

                $field_config = $fields[ $field_index ];
                $field_type = $field_config['type'] ?? 'text';
                $field_class = $this->get_field_class( $field_type );

                if ( $field_class && class_exists( $field_class ) ) {
                    $field = new $field_class( $field_config );
                    $sanitized_row[ $field_index ] = $field->sanitize( $field_value );
                } else {
                    $sanitized_row[ $field_index ] = sanitize_text_field( $field_value );
                }
            }

            $sanitized[ $row_index ] = $sanitized_row;
        }

        return $sanitized;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $rows = is_array( $value ) ? $value : array();
        $count = count( $rows );
        $min = (int) $this->config['min_rows'];
        $max = (int) $this->config['max_rows'];
        $fields = $this->config['fields'] ?? array();

        if ( $count < $min ) {
            $this->add_error( sprintf(
                __( 'Please add at least %d rows.', 'bits-accessible-forms' ),
                $min
            ) );
            return;
        }

        if ( $count > $max ) {
            $this->add_error( sprintf(
                __( 'Maximum %d rows allowed.', 'bits-accessible-forms' ),
                $max
            ) );
            return;
        }

        // Validate each row's fields
        foreach ( $rows as $row_index => $row_values ) {
            foreach ( $fields as $field_index => $field_config ) {
                $field_type = $field_config['type'] ?? 'text';
                $field_class = $this->get_field_class( $field_type );

                if ( ! $field_class || ! class_exists( $field_class ) ) {
                    continue;
                }

                $field = new $field_class( $field_config );
                $field->set_value( $row_values[ $field_index ] ?? '' );
                
                if ( ! $field->validate() ) {
                    foreach ( $field->get_errors() as $error ) {
                        $this->add_error( sprintf(
                            /* translators: 1: row number, 2: field label, 3: error message */
                            __( 'Row %1$d, %2$s: %3$s', 'bits-accessible-forms' ),
                            $row_index + 1,
                            $field_config['label'] ?? __( 'Field', 'bits-accessible-forms' ),
                            $error
                        ) );
                    }
                }
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
        if ( ! is_array( $value ) || empty( $value ) ) {
            return true;
        }

        // Check if all rows are empty
        foreach ( $value as $row ) {
            if ( is_array( $row ) ) {
                foreach ( $row as $field_value ) {
                    if ( ! empty( $field_value ) ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return '';
        }

        $fields = $this->config['fields'] ?? array();
        $output = array();

        foreach ( $value as $row_index => $row_values ) {
            $row_output = array();
            
            foreach ( $row_values as $field_index => $field_value ) {
                if ( ! isset( $fields[ $field_index ] ) ) {
                    continue;
                }

                $field_config = $fields[ $field_index ];
                $field_label = $field_config['label'] ?? '';
                
                $row_output[] = $field_label . ': ' . $field_value;
            }

            $output[] = sprintf(
                __( 'Row %d: %s', 'bits-accessible-forms' ),
                $row_index + 1,
                implode( ', ', $row_output )
            );
        }

        return implode( "\n", $output );
    }
}
