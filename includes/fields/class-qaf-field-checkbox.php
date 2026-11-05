<?php
/**
 * Checkbox Field - uses fieldset/legend for accessibility
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Checkbox class
 * 
 * Multiple checkboxes with proper fieldset/legend for WCAG 2.2 AA compliance.
 */
class QAF_Field_Checkbox extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'checkbox';

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
            'choices'          => array(),
            'layout'           => 'vertical',
            'select_all'       => false,
            'select_all_label' => __( 'Select All', 'quill-accessible-forms' ),
            'min_selections'   => 0,
            'max_selections'   => 0, // 0 = unlimited
            'other_option'     => false,
            'other_label'      => __( 'Other', 'quill-accessible-forms' ),
            'randomize'        => false,
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
                'choices' => array(
                    'type'  => 'choices',
                    'label' => __( 'Choices', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                    'desc'  => __( 'At least one option must be selected.', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'quill-accessible-forms' ),
                    'options' => array(
                        'vertical'   => __( 'Vertical', 'quill-accessible-forms' ),
                        'horizontal' => __( 'Horizontal', 'quill-accessible-forms' ),
                        'inline'     => __( 'Inline', 'quill-accessible-forms' ),
                    ),
                ),
                'select_all' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show "Select All" option', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_selections' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Selections', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_selections' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Selections', 'quill-accessible-forms' ),
                    'desc'  => __( '0 for unlimited.', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
            ),
            'advanced' => array(
                'other_option' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable "Other" Option', 'quill-accessible-forms' ),
                ),
                'randomize' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Randomize Choices', 'quill-accessible-forms' ),
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
        $choices = $this->get_choices();
        
        if ( empty( $choices ) ) {
            return '';
        }

        $html_id  = $this->get_html_id();
        $name     = 'qaf_field_' . $this->get_id() . '[]';
        $values   = (array) $this->get_value();
        $layout   = $this->config['layout'];
        $required = $this->is_required();
        $min      = (int) $this->config['min_selections'];
        $max      = (int) $this->config['max_selections'];

        $wrapper_class = array(
            'qaf-field',
            'qaf-field-' . $this->type,
            'qaf-field-size-' . $this->config['size'],
            'qaf-checkbox-layout-' . $layout,
        );

        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        if ( $required ) {
            $wrapper_class[] = 'qaf-field-required';
        }

        if ( ! empty( $this->errors ) ) {
            $wrapper_class[] = 'qaf-field-error';
        }

        // Build fieldset for grouped controls (WCAG requirement)
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-min-selections="' . esc_attr( $min ) . '"';
        $html .= ' data-max-selections="' . esc_attr( $max ) . '"';
        
        // ARIA for group
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }
        if ( $min > 0 || $max > 0 ) {
            $described_by[] = $html_id . '-constraints';
        }
        if ( ! empty( $this->errors ) ) {
            $described_by[] = $html_id . '-error';
        }
        if ( ! empty( $described_by ) ) {
            $html .= ' aria-describedby="' . esc_attr( implode( ' ', $described_by ) ) . '"';
        }
        if ( ! empty( $this->errors ) ) {
            $html .= ' aria-invalid="true"';
        }
        
        $html .= '>';

        // Legend
        $html .= '<legend class="qaf-fieldset-legend">';
        $html .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Selection constraints hint
        if ( $min > 0 || $max > 0 ) {
            $html .= '<div id="' . esc_attr( $html_id ) . '-constraints" class="qaf-selection-hint">';
            if ( $min > 0 && $max > 0 ) {
                $html .= sprintf(
                    esc_html__( 'Select between %1$d and %2$d options', 'quill-accessible-forms' ),
                    $min,
                    $max
                );
            } elseif ( $min > 0 ) {
                $html .= sprintf(
                    esc_html__( 'Select at least %d option(s)', 'quill-accessible-forms' ),
                    $min
                );
            } elseif ( $max > 0 ) {
                $html .= sprintf(
                    esc_html__( 'Select up to %d option(s)', 'quill-accessible-forms' ),
                    $max
                );
            }
            $html .= '</div>';
        }

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Checkbox options container
        $html .= '<div class="qaf-checkbox-options" role="group">';

        // Select All option
        if ( ! empty( $this->config['select_all'] ) ) {
            $html .= $this->render_select_all( $html_id, count( $choices ), count( $values ) );
        }

        foreach ( $choices as $index => $choice ) {
            $html .= $this->render_checkbox_option( $choice, $index, $name, $values );
        }

        // Other option
        if ( ! empty( $this->config['other_option'] ) ) {
            $html .= $this->render_other_option( $name, $values );
        }

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
     * Render Select All option
     *
     * @param string $html_id     Field HTML ID.
     * @param int    $total       Total choices.
     * @param int    $selected    Selected count.
     * @return string Select All HTML.
     */
    protected function render_select_all( $html_id, $total, $selected ) {
        $checked = $selected === $total && $total > 0;
        $indeterminate = $selected > 0 && $selected < $total;
        
        $html = '<div class="qaf-checkbox-option qaf-checkbox-select-all">';
        
        $html .= '<input type="checkbox"';
        $html .= ' id="' . esc_attr( $html_id ) . '-select-all"';
        $html .= ' class="qaf-checkbox-input qaf-select-all-trigger"';
        $html .= ' aria-controls="' . esc_attr( $html_id ) . '-options"';
        
        if ( $checked ) {
            $html .= ' checked';
        }
        
        if ( $indeterminate ) {
            $html .= ' data-indeterminate="true"';
        }
        
        $html .= '>';
        
        $html .= '<label for="' . esc_attr( $html_id ) . '-select-all" class="qaf-checkbox-label">';
        $html .= '<span class="qaf-checkbox-indicator" aria-hidden="true"></span>';
        $html .= '<span class="qaf-checkbox-text">' . esc_html( $this->config['select_all_label'] ) . '</span>';
        $html .= '</label>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render individual checkbox option
     *
     * @param array  $choice Choice data.
     * @param int    $index  Choice index.
     * @param string $name   Field name.
     * @param array  $values Current values.
     * @return string Checkbox HTML.
     */
    protected function render_checkbox_option( $choice, $index, $name, $values ) {
        $choice_value = $choice['value'] ?? $choice['label'];
        $choice_label = $choice['label'];
        $option_id    = $this->get_html_id() . '-' . $index;
        $checked      = in_array( $choice_value, $values, true );
        $max          = (int) $this->config['max_selections'];

        $html = '<div class="qaf-checkbox-option">';
        
        $html .= '<input type="checkbox"';
        $html .= ' id="' . esc_attr( $option_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $choice_value ) . '"';
        $html .= ' class="qaf-checkbox-input"';
        
        if ( $checked ) {
            $html .= ' checked';
        }
        
        // Disable unchecked options if max reached
        if ( $max > 0 && count( $values ) >= $max && ! $checked ) {
            $html .= ' disabled aria-disabled="true"';
        }
        
        $html .= '>';
        
        $html .= '<label for="' . esc_attr( $option_id ) . '" class="qaf-checkbox-label">';
        $html .= '<span class="qaf-checkbox-indicator" aria-hidden="true"></span>';
        $html .= '<span class="qaf-checkbox-text">' . esc_html( $choice_label ) . '</span>';
        $html .= '</label>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Render "Other" option
     *
     * @param string $name   Field name.
     * @param array  $values Current values.
     * @return string Other option HTML.
     */
    protected function render_other_option( $name, $values ) {
        $other_id    = $this->get_html_id() . '-other';
        $other_label = $this->config['other_label'] ?? __( 'Other', 'quill-accessible-forms' );
        $other_value = $this->get_other_value( $values );
        $is_checked  = ! empty( $other_value );

        $html = '<div class="qaf-checkbox-option qaf-checkbox-other">';
        
        $html .= '<input type="checkbox"';
        $html .= ' id="' . esc_attr( $other_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="__other__"';
        $html .= ' class="qaf-checkbox-input qaf-checkbox-other-trigger"';
        $html .= ' aria-controls="' . esc_attr( $other_id ) . '-input"';
        
        if ( $is_checked ) {
            $html .= ' checked';
        }
        
        $html .= '>';
        
        $html .= '<label for="' . esc_attr( $other_id ) . '" class="qaf-checkbox-label">';
        $html .= '<span class="qaf-checkbox-indicator" aria-hidden="true"></span>';
        $html .= '<span class="qaf-checkbox-text">' . esc_html( $other_label ) . '</span>';
        $html .= '</label>';
        
        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $other_id ) . '-input"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '_other"';
        $html .= ' value="' . esc_attr( $other_value ) . '"';
        $html .= ' class="qaf-checkbox-other-input"';
        $html .= ' aria-label="' . esc_attr__( 'Specify other value', 'quill-accessible-forms' ) . '"';
        
        if ( ! $is_checked ) {
            $html .= ' disabled';
        }
        
        $html .= '>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Get choices array
     *
     * @return array Choices.
     */
    protected function get_choices() {
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

        if ( ! empty( $this->config['randomize'] ) ) {
            shuffle( $choices );
        }

        return $choices;
    }

    /**
     * Get "other" value from values array
     *
     * @param array $values All values.
     * @return string Other value.
     */
    protected function get_other_value( $values ) {
        $predefined = array_map( function( $choice ) {
            return $choice['value'] ?? $choice['label'];
        }, $this->get_choices() );

        foreach ( $values as $value ) {
            if ( ! in_array( $value, $predefined, true ) && $value !== '__other__' ) {
                return $value;
            }
        }

        return '';
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
        
        foreach ( $value as $v ) {
            if ( $v === '__other__' ) {
                // Get other value from separate field
                if ( isset( $_POST[ 'qaf_field_' . $this->get_id() . '_other' ] ) ) {
                    $other = sanitize_text_field( wp_unslash( $_POST[ 'qaf_field_' . $this->get_id() . '_other' ] ) );
                    if ( ! empty( $other ) ) {
                        $sanitized[] = $other;
                    }
                }
            } else {
                $sanitized[] = sanitize_text_field( $v );
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
        $values = (array) $value;
        $count = count( $values );
        $min = (int) $this->config['min_selections'];
        $max = (int) $this->config['max_selections'];

        if ( $min > 0 && $count < $min ) {
            $this->add_error( sprintf(
                __( 'Please select at least %d option(s).', 'quill-accessible-forms' ),
                $min
            ) );
        }

        if ( $max > 0 && $count > $max ) {
            $this->add_error( sprintf(
                __( 'Please select no more than %d option(s).', 'quill-accessible-forms' ),
                $max
            ) );
        }
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty.
     */
    protected function is_empty( $value ) {
        return empty( $value ) || ( is_array( $value ) && count( array_filter( $value ) ) === 0 );
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

        return esc_html( implode( ', ', $value ) );
    }
}
