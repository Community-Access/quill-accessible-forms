<?php
/**
 * Text field type
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Text class
 * 
 * Single line text input field with WCAG 2.2 AA compliance.
 */
class QAF_Field_Text extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'text';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'standard';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'input_mask'        => '',
            'input_mask_custom' => '',
            'min_length'        => '',
            'max_length'        => '',
            'autocomplete'      => '', // off, name, email, tel, etc.
            'input_type'        => 'text', // text, search
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
                'label'       => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Field Size', 'quill-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small', 'quill-accessible-forms' ),
                        'medium' => __( 'Medium', 'quill-accessible-forms' ),
                        'large'  => __( 'Large', 'quill-accessible-forms' ),
                        'full'   => __( 'Full Width', 'quill-accessible-forms' ),
                    ),
                ),
                'label_placement' => array(
                    'type'    => 'select',
                    'label'   => __( 'Label Placement', 'quill-accessible-forms' ),
                    'options' => array(
                        'inherit' => __( 'Inherit from Form', 'quill-accessible-forms' ),
                        'above'   => __( 'Above', 'quill-accessible-forms' ),
                        'below'   => __( 'Below', 'quill-accessible-forms' ),
                        'left'    => __( 'Left', 'quill-accessible-forms' ),
                        'hidden'  => __( 'Hidden (Screen Reader Only)', 'quill-accessible-forms' ),
                    ),
                ),
                'css_class' => array(
                    'type'  => 'text',
                    'label' => __( 'CSS Class', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'custom_error_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Custom Error Message', 'quill-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'input_mask' => array(
                    'type'    => 'select',
                    'label'   => __( 'Input Mask', 'quill-accessible-forms' ),
                    'options' => array(
                        ''           => __( 'None', 'quill-accessible-forms' ),
                        'phone_us'   => __( 'US Phone: (999) 999-9999', 'quill-accessible-forms' ),
                        'phone_intl' => __( 'International Phone', 'quill-accessible-forms' ),
                        'date_us'    => __( 'Date: MM/DD/YYYY', 'quill-accessible-forms' ),
                        'date_eu'    => __( 'Date: DD/MM/YYYY', 'quill-accessible-forms' ),
                        'ssn'        => __( 'SSN: 999-99-9999', 'quill-accessible-forms' ),
                        'zip_us'     => __( 'US Zip: 99999', 'quill-accessible-forms' ),
                        'zip_full'   => __( 'US Zip+4: 99999-9999', 'quill-accessible-forms' ),
                        'currency'   => __( 'Currency', 'quill-accessible-forms' ),
                        'custom'     => __( 'Custom', 'quill-accessible-forms' ),
                    ),
                ),
                'input_mask_custom' => array(
                    'type'        => 'text',
                    'label'       => __( 'Custom Mask Pattern', 'quill-accessible-forms' ),
                    'description' => __( '9 = digit, A = letter, * = alphanumeric', 'quill-accessible-forms' ),
                    'conditional' => array( 'input_mask' => 'custom' ),
                ),
                'autocomplete' => array(
                    'type'    => 'select',
                    'label'   => __( 'Autocomplete', 'quill-accessible-forms' ),
                    'options' => array(
                        ''                 => __( 'Default', 'quill-accessible-forms' ),
                        'off'              => __( 'Off', 'quill-accessible-forms' ),
                        'name'             => __( 'Name', 'quill-accessible-forms' ),
                        'given-name'       => __( 'First Name', 'quill-accessible-forms' ),
                        'family-name'      => __( 'Last Name', 'quill-accessible-forms' ),
                        'email'            => __( 'Email', 'quill-accessible-forms' ),
                        'tel'              => __( 'Phone', 'quill-accessible-forms' ),
                        'organization'     => __( 'Organization', 'quill-accessible-forms' ),
                        'street-address'   => __( 'Street Address', 'quill-accessible-forms' ),
                        'postal-code'      => __( 'Postal Code', 'quill-accessible-forms' ),
                    ),
                ),
                'allow_prefill' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Dynamic Prefill', 'quill-accessible-forms' ),
                ),
                'prefill_parameter' => array(
                    'type'        => 'text',
                    'label'       => __( 'URL Parameter Name', 'quill-accessible-forms' ),
                    'conditional' => array( 'allow_prefill' => true ),
                ),
                'admin_only' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Admin Only', 'quill-accessible-forms' ),
                ),
            ),
            'accessibility' => array(
                'aria_label' => array(
                    'type'        => 'text',
                    'label'       => __( 'ARIA Label', 'quill-accessible-forms' ),
                    'description' => __( 'Override the accessible name (optional)', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $value = $this->get_value();

        // Build input attributes
        $attrs = array(
            'type'        => $this->config['input_type'] ?: 'text',
            'id'          => $this->get_html_id(),
            'name'        => 'qaf_fields[' . $this->get_id() . ']',
            'value'       => esc_attr( $value ),
            'class'       => 'qaf-input qaf-input-text',
            'placeholder' => $this->config['placeholder'],
        );

        // Max length
        if ( ! empty( $this->config['max_length'] ) ) {
            $attrs['maxlength'] = $this->config['max_length'];
        }

        // Min length
        if ( ! empty( $this->config['min_length'] ) ) {
            $attrs['minlength'] = $this->config['min_length'];
        }

        // Autocomplete
        if ( ! empty( $this->config['autocomplete'] ) ) {
            $attrs['autocomplete'] = $this->config['autocomplete'];
        }

        // Read-only
        if ( ! empty( $this->config['read_only'] ) ) {
            $attrs['readonly'] = true;
        }

        // Disabled
        if ( ! empty( $this->config['disabled'] ) ) {
            $attrs['disabled'] = true;
        }

        // Add ARIA attributes
        $attrs = array_merge( $attrs, $this->get_aria_attributes() );

        // Add HTML5 attributes (pattern, autofocus, spellcheck, inputmode, enterkeyhint)
        $attrs = array_merge( $attrs, $this->get_html5_attributes() );

        // Input mask data attribute
        if ( ! empty( $this->config['input_mask'] ) ) {
            $mask = $this->get_input_mask_pattern();
            if ( $mask ) {
                $attrs['data-inputmask'] = $mask;
                $attrs['class'] .= ' qaf-masked';
            }
        }

        // Build the input element
        $input = '<input ' . $this->build_attributes( $attrs ) . '>';

        // Character counter (if max_length set)
        if ( ! empty( $this->config['max_length'] ) ) {
            $current = strlen( $value );
            $max     = (int) $this->config['max_length'];
            $input  .= sprintf(
                '<div class="qaf-char-counter" aria-live="polite" aria-atomic="true"><span class="qaf-char-count">%d</span> / %d %s</div>',
                $current,
                $max,
                esc_html__( 'characters', 'quill-accessible-forms' )
            );
        }

        return $this->render_wrapper( $input );
    }

    /**
     * Get input mask pattern
     *
     * @return string Mask pattern.
     */
    private function get_input_mask_pattern() {
        $masks = array(
            'phone_us'   => '(999) 999-9999',
            'phone_intl' => '+9{1,15}',
            'date_us'    => '99/99/9999',
            'date_eu'    => '99/99/9999',
            'ssn'        => '999-99-9999',
            'zip_us'     => '99999',
            'zip_full'   => '99999-9999',
            'currency'   => 'currency',
        );

        $mask_type = $this->config['input_mask'];

        if ( $mask_type === 'custom' && ! empty( $this->config['input_mask_custom'] ) ) {
            return $this->config['input_mask_custom'];
        }

        return isset( $masks[ $mask_type ] ) ? $masks[ $mask_type ] : '';
    }

    /**
     * Field-specific validation
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $value = (string) $value;

        // Min length
        if ( ! empty( $this->config['min_length'] ) ) {
            $min = (int) $this->config['min_length'];
            if ( strlen( $value ) < $min ) {
                $this->add_error( sprintf(
                    __( 'Please enter at least %d characters.', 'quill-accessible-forms' ),
                    $min
                ) );
            }
        }

        // Max length
        if ( ! empty( $this->config['max_length'] ) ) {
            $max = (int) $this->config['max_length'];
            if ( strlen( $value ) > $max ) {
                $this->add_error( sprintf(
                    __( 'Please enter no more than %d characters.', 'quill-accessible-forms' ),
                    $max
                ) );
            }
        }
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        return sanitize_text_field( $value );
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code for this field.
     */
    public function to_bfl() {
        $bfl = 'text';

        if ( $this->get_id() ) {
            $bfl .= ' #' . $this->get_id();
        }

        if ( $this->get_label() ) {
            $bfl .= ' "' . addslashes( $this->get_label() ) . '"';
        }

        $modifiers = array();

        if ( $this->is_required() ) {
            $modifiers[] = '@required';
        }

        if ( ! empty( $this->config['placeholder'] ) ) {
            $modifiers[] = '@placeholder "' . addslashes( $this->config['placeholder'] ) . '"';
        }

        if ( ! empty( $this->config['max_length'] ) ) {
            $modifiers[] = '@maxlength ' . $this->config['max_length'];
        }

        if ( ! empty( $this->config['min_length'] ) ) {
            $modifiers[] = '@minlength ' . $this->config['min_length'];
        }

        if ( ! empty( $this->config['default_value'] ) ) {
            $modifiers[] = '@default "' . addslashes( $this->config['default_value'] ) . '"';
        }

        if ( ! empty( $modifiers ) ) {
            $bfl .= "\n  " . implode( "\n  ", $modifiers );
        }

        return $bfl;
    }
}
