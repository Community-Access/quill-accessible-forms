<?php
/**
 * Multi-Select Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Multiselect class
 */
class BAF_Field_Multiselect extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'multiselect';

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
            'choices'         => array(),
            'min_selections'  => 0,
            'max_selections'  => 0,
            'size'            => 5,
            'enhanced'        => true,
            'searchable'      => true,
            'allow_create'    => false,
            'choice_library'  => '',
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
                'choices' => array(
                    'type'  => 'choices',
                    'label' => __( 'Choices', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_selections' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Selections', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_selections' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Selections', 'bits-accessible-forms' ),
                    'desc'  => __( '0 for unlimited.', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
            ),
            'appearance' => array(
                'size' => array(
                    'type'  => 'number',
                    'label' => __( 'Visible Options', 'bits-accessible-forms' ),
                    'min'   => 2,
                    'max'   => 20,
                ),
                'enhanced' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enhanced UI', 'bits-accessible-forms' ),
                    'desc'  => __( 'Use accessible combobox instead of native select.', 'bits-accessible-forms' ),
                ),
                'searchable' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Searchable', 'bits-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'choice_library' => array(
                    'type'    => 'select',
                    'label'   => __( 'Use Choice Library', 'bits-accessible-forms' ),
                    'options' => $this->get_choice_libraries(),
                ),
                'allow_create' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow New Values', 'bits-accessible-forms' ),
                    'desc'  => __( 'Users can create new options.', 'bits-accessible-forms' ),
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
        if ( ! empty( $this->config['enhanced'] ) ) {
            return $this->render_enhanced();
        }
        
        return $this->render_native();
    }

    /**
     * Render native multi-select
     *
     * @return string Rendered HTML.
     */
    protected function render_native() {
        $choices = $this->get_choices();
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id() . '[]';
        $values  = (array) $this->get_value();
        $size    = (int) $this->config['size'];
        $min     = (int) $this->config['min_selections'];
        $max     = (int) $this->config['max_selections'];

        $wrapper_class = $this->get_wrapper_classes();
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= $this->render_label();

        // Selection constraints hint
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }

        if ( $min > 0 || $max > 0 ) {
            $described_by[] = $html_id . '-constraints';
            $html .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="baf-selection-hint">';
            if ( $min > 0 && $max > 0 ) {
                $html .= sprintf(
                    esc_html__( 'Select between %1$d and %2$d options. Hold Ctrl/Cmd to select multiple.', 'bits-accessible-forms' ),
                    $min,
                    $max
                );
            } elseif ( $min > 0 ) {
                $html .= sprintf(
                    esc_html__( 'Select at least %d option(s). Hold Ctrl/Cmd to select multiple.', 'bits-accessible-forms' ),
                    $min
                );
            } elseif ( $max > 0 ) {
                $html .= sprintf(
                    esc_html__( 'Select up to %d option(s). Hold Ctrl/Cmd to select multiple.', 'bits-accessible-forms' ),
                    $max
                );
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="baf-selection-hint">';
            $html .= esc_html__( 'Hold Ctrl/Cmd and click to select multiple options.', 'bits-accessible-forms' );
            $html .= '</div>';
        }

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Select element
        $attrs = array(
            'id'       => $html_id,
            'name'     => $name,
            'class'    => 'baf-multiselect-input',
            'multiple' => 'multiple',
            'size'     => $size,
        );

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

        $html .= '<select ' . $this->build_attributes( $attrs ) . '>';

        foreach ( $choices as $choice ) {
            if ( isset( $choice['optgroup'] ) ) {
                $html .= '<optgroup label="' . esc_attr( $choice['optgroup'] ) . '">';
                foreach ( $choice['options'] as $opt ) {
                    $html .= $this->render_option( $opt, $values );
                }
                $html .= '</optgroup>';
            } else {
                $html .= $this->render_option( $choice, $values );
            }
        }

        $html .= '</select>';

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

        $html .= '</div>';

        return $html;
    }

    /**
     * Render enhanced accessible multi-select (combobox pattern)
     *
     * @return string Rendered HTML.
     */
    protected function render_enhanced() {
        $choices = $this->get_choices();
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id() . '[]';
        $values  = (array) $this->get_value();
        $min     = (int) $this->config['min_selections'];
        $max     = (int) $this->config['max_selections'];

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-multiselect-enhanced';

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-min-selections="' . esc_attr( $min ) . '"';
        $html .= ' data-max-selections="' . esc_attr( $max ) . '"';
        $html .= ' data-searchable="' . ( ! empty( $this->config['searchable'] ) ? 'true' : 'false' ) . '"';
        $html .= ' data-allow-create="' . ( ! empty( $this->config['allow_create'] ) ? 'true' : 'false' ) . '">';

        // Label
        $html .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Hidden inputs for selected values
        $html .= '<div class="baf-multiselect-values">';
        foreach ( $values as $val ) {
            $html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
        }
        $html .= '</div>';

        // Selected tags display
        $html .= '<div class="baf-multiselect-tags" role="list" aria-label="' . esc_attr__( 'Selected options', 'bits-accessible-forms' ) . '">';
        foreach ( $values as $val ) {
            $label = $this->get_choice_label( $val, $choices );
            $html .= '<span class="baf-multiselect-tag" role="listitem" data-value="' . esc_attr( $val ) . '">';
            $html .= '<span class="baf-tag-text">' . esc_html( $label ) . '</span>';
            $html .= '<button type="button" class="baf-tag-remove" aria-label="' . esc_attr( sprintf( __( 'Remove %s', 'bits-accessible-forms' ), $label ) ) . '">&times;</button>';
            $html .= '</span>';
        }
        $html .= '</div>';

        // Combobox
        $html .= '<div class="baf-multiselect-combobox">';
        
        // Search input
        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' class="baf-multiselect-search"';
        $html .= ' role="combobox"';
        $html .= ' aria-expanded="false"';
        $html .= ' aria-haspopup="listbox"';
        $html .= ' aria-controls="' . esc_attr( $html_id ) . '-listbox"';
        $html .= ' aria-autocomplete="list"';
        
        if ( ! empty( $this->config['searchable'] ) ) {
            $html .= ' placeholder="' . esc_attr__( 'Type to search...', 'bits-accessible-forms' ) . '"';
        } else {
            $html .= ' placeholder="' . esc_attr__( 'Click to select...', 'bits-accessible-forms' ) . '"';
            $html .= ' readonly';
        }
        
        if ( $this->is_required() && empty( $values ) ) {
            $html .= ' aria-required="true"';
        }
        
        $html .= '>';

        // Dropdown button
        $html .= '<button type="button" class="baf-multiselect-toggle" tabindex="-1" aria-label="' . esc_attr__( 'Toggle dropdown', 'bits-accessible-forms' ) . '">';
        $html .= '<span class="baf-icon-chevron" aria-hidden="true"></span>';
        $html .= '</button>';

        // Listbox
        $html .= '<ul id="' . esc_attr( $html_id ) . '-listbox" class="baf-multiselect-listbox" role="listbox" aria-multiselectable="true" hidden>';
        
        foreach ( $choices as $index => $choice ) {
            $choice_value = $choice['value'] ?? $choice['label'];
            $choice_label = $choice['label'];
            $is_selected = in_array( $choice_value, $values, true );
            $option_id = $html_id . '-option-' . $index;

            $html .= '<li id="' . esc_attr( $option_id ) . '"';
            $html .= ' class="baf-multiselect-option' . ( $is_selected ? ' baf-selected' : '' ) . '"';
            $html .= ' role="option"';
            $html .= ' aria-selected="' . ( $is_selected ? 'true' : 'false' ) . '"';
            $html .= ' data-value="' . esc_attr( $choice_value ) . '">';
            $html .= '<span class="baf-option-checkbox" aria-hidden="true"></span>';
            $html .= '<span class="baf-option-text">' . esc_html( $choice_label ) . '</span>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        // Status announcer
        $html .= '<div class="baf-multiselect-status" role="status" aria-live="polite" aria-atomic="true"></div>';

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

        $html .= '</div>';

        return $html;
    }

    /**
     * Render single option
     *
     * @param array $choice Choice data.
     * @param array $values Selected values.
     * @return string Option HTML.
     */
    protected function render_option( $choice, $values ) {
        $value = $choice['value'] ?? $choice['label'];
        $label = $choice['label'];
        $selected = in_array( $value, $values, true );

        return '<option value="' . esc_attr( $value ) . '"' . ( $selected ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
    }

    /**
     * Get choice label by value
     *
     * @param string $value  Value to find.
     * @param array  $choices Choices array.
     * @return string Choice label.
     */
    protected function get_choice_label( $value, $choices ) {
        foreach ( $choices as $choice ) {
            if ( isset( $choice['optgroup'] ) ) {
                foreach ( $choice['options'] as $opt ) {
                    if ( ( $opt['value'] ?? $opt['label'] ) === $value ) {
                        return $opt['label'];
                    }
                }
            } else {
                if ( ( $choice['value'] ?? $choice['label'] ) === $value ) {
                    return $choice['label'];
                }
            }
        }
        return $value;
    }

    /**
     * Get available choice libraries
     *
     * @return array Library options.
     */
    protected function get_choice_libraries() {
        return array(
            ''             => __( 'None', 'bits-accessible-forms' ),
            'countries'    => __( 'Countries', 'bits-accessible-forms' ),
            'us_states'    => __( 'US States', 'bits-accessible-forms' ),
            'ca_provinces' => __( 'Canadian Provinces', 'bits-accessible-forms' ),
            'timezones'    => __( 'Timezones', 'bits-accessible-forms' ),
            'currencies'   => __( 'Currencies', 'bits-accessible-forms' ),
            'languages'    => __( 'Languages', 'bits-accessible-forms' ),
        );
    }

    /**
     * Get choices array
     *
     * @return array Choices.
     */
    protected function get_choices() {
        // Check for choice library first
        if ( ! empty( $this->config['choice_library'] ) ) {
            return BAF_Choice_Libraries::get( $this->config['choice_library'] );
        }

        $choices = $this->config['choices'] ?? array();

        if ( is_string( $choices ) ) {
            $lines = explode( "\n", $choices );
            $choices = array();
            
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( empty( $line ) ) {
                    continue;
                }
                
                if ( strpos( $line, '|' ) !== false ) {
                    list( $value, $label ) = explode( '|', $line, 2 );
                    $choices[] = array(
                        'value' => trim( $value ),
                        'label' => trim( $label ),
                    );
                } else {
                    $choices[] = array(
                        'value' => $line,
                        'label' => $line,
                    );
                }
            }
        }

        return $choices;
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

        return array_map( 'sanitize_text_field', $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $values = (array) $value;
        $count = count( $values );
        $min = (int) $this->config['min_selections'];
        $max = (int) $this->config['max_selections'];

        if ( $min > 0 && $count < $min ) {
            $this->add_error( sprintf(
                __( 'Please select at least %d option(s).', 'bits-accessible-forms' ),
                $min
            ) );
        }

        if ( $max > 0 && $count > $max ) {
            $this->add_error( sprintf(
                __( 'Please select no more than %d option(s).', 'bits-accessible-forms' ),
                $max
            ) );
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( ! is_array( $value ) ) {
            return esc_html( $value );
        }

        $choices = $this->get_choices();
        $labels = array();

        foreach ( $value as $val ) {
            $labels[] = $this->get_choice_label( $val, $choices );
        }

        return esc_html( implode( ', ', $labels ) );
    }
}
