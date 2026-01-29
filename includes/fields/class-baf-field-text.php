<?php
/**
 * Text field type
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Text class
 * 
 * Single line text input field with WCAG 2.2 AA compliance.
 */
class BAF_Field_Text extends BAF_Field {

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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'bits-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Value', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Field Size', 'bits-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small', 'bits-accessible-forms' ),
                        'medium' => __( 'Medium', 'bits-accessible-forms' ),
                        'large'  => __( 'Large', 'bits-accessible-forms' ),
                        'full'   => __( 'Full Width', 'bits-accessible-forms' ),
                    ),
                ),
                'label_placement' => array(
                    'type'    => 'select',
                    'label'   => __( 'Label Placement', 'bits-accessible-forms' ),
                    'options' => array(
                        'inherit' => __( 'Inherit from Form', 'bits-accessible-forms' ),
                        'above'   => __( 'Above', 'bits-accessible-forms' ),
                        'below'   => __( 'Below', 'bits-accessible-forms' ),
                        'left'    => __( 'Left', 'bits-accessible-forms' ),
                        'hidden'  => __( 'Hidden (Screen Reader Only)', 'bits-accessible-forms' ),
                    ),
                ),
                'css_class' => array(
                    'type'  => 'text',
                    'label' => __( 'CSS Class', 'bits-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Length', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'custom_error_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Custom Error Message', 'bits-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'input_mask' => array(
                    'type'    => 'select',
                    'label'   => __( 'Input Mask', 'bits-accessible-forms' ),
                    'options' => array(
                        ''           => __( 'None', 'bits-accessible-forms' ),
                        'phone_us'   => __( 'US Phone: (999) 999-9999', 'bits-accessible-forms' ),
                        'phone_intl' => __( 'International Phone', 'bits-accessible-forms' ),
                        'date_us'    => __( 'Date: MM/DD/YYYY', 'bits-accessible-forms' ),
                        'date_eu'    => __( 'Date: DD/MM/YYYY', 'bits-accessible-forms' ),
                        'ssn'        => __( 'SSN: 999-99-9999', 'bits-accessible-forms' ),
                        'zip_us'     => __( 'US Zip: 99999', 'bits-accessible-forms' ),
                        'zip_full'   => __( 'US Zip+4: 99999-9999', 'bits-accessible-forms' ),
                        'currency'   => __( 'Currency', 'bits-accessible-forms' ),
                        'custom'     => __( 'Custom', 'bits-accessible-forms' ),
                    ),
                ),
                'input_mask_custom' => array(
                    'type'        => 'text',
                    'label'       => __( 'Custom Mask Pattern', 'bits-accessible-forms' ),
                    'description' => __( '9 = digit, A = letter, * = alphanumeric', 'bits-accessible-forms' ),
                    'conditional' => array( 'input_mask' => 'custom' ),
                ),
                'autocomplete' => array(
                    'type'    => 'select',
                    'label'   => __( 'Autocomplete', 'bits-accessible-forms' ),
                    'options' => array(
                        ''                 => __( 'Default', 'bits-accessible-forms' ),
                        'off'              => __( 'Off', 'bits-accessible-forms' ),
                        'name'             => __( 'Name', 'bits-accessible-forms' ),
                        'given-name'       => __( 'First Name', 'bits-accessible-forms' ),
                        'family-name'      => __( 'Last Name', 'bits-accessible-forms' ),
                        'email'            => __( 'Email', 'bits-accessible-forms' ),
                        'tel'              => __( 'Phone', 'bits-accessible-forms' ),
                        'organization'     => __( 'Organization', 'bits-accessible-forms' ),
                        'street-address'   => __( 'Street Address', 'bits-accessible-forms' ),
                        'postal-code'      => __( 'Postal Code', 'bits-accessible-forms' ),
                    ),
                ),
                'allow_prefill' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Dynamic Prefill', 'bits-accessible-forms' ),
                ),
                'prefill_parameter' => array(
                    'type'        => 'text',
                    'label'       => __( 'URL Parameter Name', 'bits-accessible-forms' ),
                    'conditional' => array( 'allow_prefill' => true ),
                ),
                'admin_only' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Admin Only', 'bits-accessible-forms' ),
                ),
            ),
            'accessibility' => array(
                'aria_label' => array(
                    'type'        => 'text',
                    'label'       => __( 'ARIA Label', 'bits-accessible-forms' ),
                    'description' => __( 'Override the accessible name (optional)', 'bits-accessible-forms' ),
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
            'name'        => 'baf_fields[' . $this->get_id() . ']',
            'value'       => esc_attr( $value ),
            'class'       => 'baf-input baf-input-text',
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

        // Input mask data attribute
        if ( ! empty( $this->config['input_mask'] ) ) {
            $mask = $this->get_input_mask_pattern();
            if ( $mask ) {
                $attrs['data-inputmask'] = $mask;
                $attrs['class'] .= ' baf-masked';
            }
        }

        // Build the input element
        $input = '<input ' . $this->build_attributes( $attrs ) . '>';

        // Character counter (if max_length set)
        if ( ! empty( $this->config['max_length'] ) ) {
            $current = strlen( $value );
            $max     = (int) $this->config['max_length'];
            $input  .= sprintf(
                '<div class="baf-char-counter" aria-live="polite" aria-atomic="true"><span class="baf-char-count">%d</span> / %d %s</div>',
                $current,
                $max,
                esc_html__( 'characters', 'bits-accessible-forms' )
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
                    __( 'Please enter at least %d characters.', 'bits-accessible-forms' ),
                    $min
                ) );
            }
        }

        // Max length
        if ( ! empty( $this->config['max_length'] ) ) {
            $max = (int) $this->config['max_length'];
            if ( strlen( $value ) > $max ) {
                $this->add_error( sprintf(
                    __( 'Please enter no more than %d characters.', 'bits-accessible-forms' ),
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
