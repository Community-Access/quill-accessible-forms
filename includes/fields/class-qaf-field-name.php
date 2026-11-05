<?php
/**
 * Name Composite Field - uses fieldset/legend for grouped inputs
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Name class
 * 
 * Composite name field with fieldset/legend for WCAG 2.2 AA compliance.
 */
class QAF_Field_Name extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'name';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Sub-fields configuration
     *
     * @var array
     */
    protected $subfields = array();

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'format'               => 'first_last', // first_last, first_middle_last, extended
            'prefix_enabled'       => false,
            'prefix_options'       => array( 'Mr.', 'Mrs.', 'Ms.', 'Dr.' ),
            'middle_enabled'       => false,
            'suffix_enabled'       => false,
            'suffix_options'       => array( 'Jr.', 'Sr.', 'II', 'III', 'IV' ),
            
            // Sub-field labels
            'label_prefix'         => __( 'Prefix', 'quill-accessible-forms' ),
            'label_first'          => __( 'First Name', 'quill-accessible-forms' ),
            'label_middle'         => __( 'Middle Name', 'quill-accessible-forms' ),
            'label_last'           => __( 'Last Name', 'quill-accessible-forms' ),
            'label_suffix'         => __( 'Suffix', 'quill-accessible-forms' ),
            
            // Sub-field required
            'first_required'       => true,
            'last_required'        => true,
            'middle_required'      => false,
            
            // Layout
            'layout'               => 'inline', // inline, stacked
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
                    'label' => __( 'Field Label', 'quill-accessible-forms' ),
                    'desc'  => __( 'Label for the entire name group (legend).', 'quill-accessible-forms' ),
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
            'subfields' => array(
                'format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Name Format', 'quill-accessible-forms' ),
                    'options' => array(
                        'first_last'        => __( 'First + Last', 'quill-accessible-forms' ),
                        'first_middle_last' => __( 'First + Middle + Last', 'quill-accessible-forms' ),
                        'extended'          => __( 'Extended (Prefix, First, Middle, Last, Suffix)', 'quill-accessible-forms' ),
                    ),
                ),
                'prefix_enabled' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Prefix (Title)', 'quill-accessible-forms' ),
                ),
                'suffix_enabled' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Suffix', 'quill-accessible-forms' ),
                ),
            ),
            'labels' => array(
                'label_first' => array(
                    'type'    => 'text',
                    'label'   => __( 'First Name Label', 'quill-accessible-forms' ),
                    'default' => __( 'First Name', 'quill-accessible-forms' ),
                ),
                'label_middle' => array(
                    'type'    => 'text',
                    'label'   => __( 'Middle Name Label', 'quill-accessible-forms' ),
                    'default' => __( 'Middle Name', 'quill-accessible-forms' ),
                ),
                'label_last' => array(
                    'type'    => 'text',
                    'label'   => __( 'Last Name Label', 'quill-accessible-forms' ),
                    'default' => __( 'Last Name', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'quill-accessible-forms' ),
                    'options' => array(
                        'inline'  => __( 'Inline (side by side)', 'quill-accessible-forms' ),
                        'stacked' => __( 'Stacked (vertical)', 'quill-accessible-forms' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Initialize subfields based on configuration
     */
    protected function init_subfields() {
        $this->subfields = array();
        $format = $this->config['format'];

        // Prefix (title)
        if ( $format === 'extended' || ! empty( $this->config['prefix_enabled'] ) ) {
            $this->subfields['prefix'] = array(
                'key'         => 'prefix',
                'type'        => 'select',
                'label'       => $this->config['label_prefix'],
                'required'    => false,
                'options'     => $this->config['prefix_options'],
                'placeholder' => __( 'Select...', 'quill-accessible-forms' ),
            );
        }

        // First Name
        $this->subfields['first'] = array(
            'key'         => 'first',
            'type'        => 'text',
            'label'       => $this->config['label_first'],
            'required'    => ! empty( $this->config['first_required'] ),
            'placeholder' => '',
            'autocomplete' => 'given-name',
        );

        // Middle Name
        if ( $format === 'first_middle_last' || $format === 'extended' || ! empty( $this->config['middle_enabled'] ) ) {
            $this->subfields['middle'] = array(
                'key'         => 'middle',
                'type'        => 'text',
                'label'       => $this->config['label_middle'],
                'required'    => ! empty( $this->config['middle_required'] ),
                'placeholder' => '',
                'autocomplete' => 'additional-name',
            );
        }

        // Last Name
        $this->subfields['last'] = array(
            'key'         => 'last',
            'type'        => 'text',
            'label'       => $this->config['label_last'],
            'required'    => ! empty( $this->config['last_required'] ),
            'placeholder' => '',
            'autocomplete' => 'family-name',
        );

        // Suffix
        if ( $format === 'extended' || ! empty( $this->config['suffix_enabled'] ) ) {
            $this->subfields['suffix'] = array(
                'key'         => 'suffix',
                'type'        => 'select',
                'label'       => $this->config['label_suffix'],
                'required'    => false,
                'options'     => $this->config['suffix_options'],
                'placeholder' => __( 'Select...', 'quill-accessible-forms' ),
            );
        }
    }

    /**
     * Render the field HTML with fieldset/legend
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $this->init_subfields();

        $html_id  = $this->get_html_id();
        $values   = $this->get_value();
        $layout   = $this->config['layout'];
        $required = $this->is_required();

        $wrapper_class = array(
            'qaf-field',
            'qaf-field-' . $this->type,
            'qaf-field-size-' . $this->config['size'],
            'qaf-name-layout-' . $layout,
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

        // Fieldset for composite field (WCAG requirement for related inputs)
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        
        // ARIA
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
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
        if ( $required ) {
            $html .= $this->render_required_indicator();
        }
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Subfields container
        $html .= '<div class="qaf-name-fields">';

        foreach ( $this->subfields as $key => $subfield ) {
            $html .= $this->render_subfield( $subfield, $values );
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
     * Render individual subfield
     *
     * @param array $subfield Subfield configuration.
     * @param array $values   Current values.
     * @return string Subfield HTML.
     */
    protected function render_subfield( $subfield, $values ) {
        $key          = $subfield['key'];
        $input_id     = $this->get_html_id() . '-' . $key;
        $input_name   = 'qaf_field_' . $this->get_id() . '[' . $key . ']';
        $value        = isset( $values[ $key ] ) ? $values[ $key ] : '';
        $is_required  = ! empty( $subfield['required'] );
        $has_error    = isset( $this->errors[ $key ] );

        $html = '<div class="qaf-name-subfield qaf-name-' . esc_attr( $key ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $input_id ) . '" class="qaf-subfield-label">';
        $html .= esc_html( $subfield['label'] );
        if ( $is_required ) {
            $html .= ' <span class="qaf-required-indicator" aria-hidden="true">*</span>';
            $html .= '<span class="qaf-sr-only"> ' . esc_html__( 'required', 'quill-accessible-forms' ) . '</span>';
        }
        $html .= '</label>';

        // Input
        if ( $subfield['type'] === 'select' ) {
            $html .= '<select';
            $html .= ' id="' . esc_attr( $input_id ) . '"';
            $html .= ' name="' . esc_attr( $input_name ) . '"';
            $html .= ' class="qaf-name-input qaf-name-select"';
            
            if ( $is_required ) {
                $html .= ' aria-required="true"';
            }
            if ( $has_error ) {
                $html .= ' aria-invalid="true"';
            }
            
            $html .= '>';
            
            // Placeholder option
            if ( ! empty( $subfield['placeholder'] ) ) {
                $html .= '<option value="">' . esc_html( $subfield['placeholder'] ) . '</option>';
            }
            
            foreach ( $subfield['options'] as $option ) {
                $selected = $value === $option ? ' selected' : '';
                $html .= '<option value="' . esc_attr( $option ) . '"' . $selected . '>' . esc_html( $option ) . '</option>';
            }
            
            $html .= '</select>';
        } else {
            $html .= '<input type="text"';
            $html .= ' id="' . esc_attr( $input_id ) . '"';
            $html .= ' name="' . esc_attr( $input_name ) . '"';
            $html .= ' value="' . esc_attr( $value ) . '"';
            $html .= ' class="qaf-name-input qaf-name-text"';
            
            if ( ! empty( $subfield['placeholder'] ) ) {
                $html .= ' placeholder="' . esc_attr( $subfield['placeholder'] ) . '"';
            }
            if ( ! empty( $subfield['autocomplete'] ) ) {
                $html .= ' autocomplete="' . esc_attr( $subfield['autocomplete'] ) . '"';
            }
            if ( $is_required ) {
                $html .= ' aria-required="true"';
            }
            if ( $has_error ) {
                $html .= ' aria-invalid="true"';
                $html .= ' aria-describedby="' . esc_attr( $input_id ) . '-error"';
            }
            
            $html .= '>';
        }

        // Subfield error
        if ( $has_error ) {
            $html .= '<div id="' . esc_attr( $input_id ) . '-error" class="qaf-subfield-error" role="alert">';
            $html .= esc_html( $this->errors[ $key ] );
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
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
        
        foreach ( $value as $key => $v ) {
            $sanitized[ $key ] = sanitize_text_field( $v );
        }

        return $sanitized;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     * @return bool True if valid.
     */
    public function validate( $value ) {
        $this->errors = array();
        $this->init_subfields();

        $values = is_array( $value ) ? $value : array();

        foreach ( $this->subfields as $key => $subfield ) {
            $subvalue = isset( $values[ $key ] ) ? trim( $values[ $key ] ) : '';
            
            if ( ! empty( $subfield['required'] ) && empty( $subvalue ) ) {
                $this->errors[ $key ] = sprintf(
                    __( '%s is required.', 'quill-accessible-forms' ),
                    $subfield['label']
                );
            }
        }

        return empty( $this->errors );
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

        // Consider empty if all required subfields are empty
        $this->init_subfields();

        foreach ( $this->subfields as $key => $subfield ) {
            if ( ! empty( $subfield['required'] ) ) {
                $subvalue = isset( $value[ $key ] ) ? trim( $value[ $key ] ) : '';
                if ( ! empty( $subvalue ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted name.
     */
    public function format_display( $value ) {
        if ( ! is_array( $value ) ) {
            return '';
        }

        $parts = array();

        if ( ! empty( $value['prefix'] ) ) {
            $parts[] = $value['prefix'];
        }
        if ( ! empty( $value['first'] ) ) {
            $parts[] = $value['first'];
        }
        if ( ! empty( $value['middle'] ) ) {
            $parts[] = $value['middle'];
        }
        if ( ! empty( $value['last'] ) ) {
            $parts[] = $value['last'];
        }
        if ( ! empty( $value['suffix'] ) ) {
            $parts[] = $value['suffix'];
        }

        return esc_html( implode( ' ', $parts ) );
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code.
     */
    public function to_bfl() {
        $bfl = 'name #' . $this->get_id() . ' "' . addslashes( $this->get_label() ) . '"';
        
        if ( $this->config['format'] !== 'first_last' ) {
            $bfl .= "\n  @format \"" . $this->config['format'] . '"';
        }

        if ( $this->is_required() ) {
            $bfl .= "\n  @required";
        }

        return $bfl;
    }
}
