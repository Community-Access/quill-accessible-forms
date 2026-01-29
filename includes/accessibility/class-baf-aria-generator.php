<?php
/**
 * ARIA Attributes Generator
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_ARIA_Generator class
 * 
 * Generates proper ARIA attributes for all field types.
 */
class BAF_ARIA_Generator {

    /**
     * Get ARIA role for field type
     *
     * @param string $field_type Field type identifier.
     * @return string ARIA role.
     */
    public function get_role( $field_type ) {
        $roles = array(
            'text'        => 'textbox',
            'textarea'    => 'textbox',
            'email'       => 'textbox',
            'url'         => 'textbox',
            'password'    => 'textbox',
            'phone'       => 'textbox',
            'number'      => 'spinbutton',
            'range'       => 'slider',
            'select'      => 'listbox',
            'multiselect' => 'listbox',
            'combobox'    => 'combobox',
            'radio'       => 'radiogroup',
            'checkbox'    => 'group',
            'toggle'      => 'switch',
            'rating'      => 'slider',
            'date'        => 'textbox',
            'time'        => 'textbox',
            'file'        => 'button',
            'submit'      => 'button',
            'section'     => 'group',
            'fieldset'    => 'group',
        );

        /**
         * Filter ARIA roles for field types
         *
         * @param array $roles Field type to role mapping.
         */
        $roles = apply_filters( 'baf_aria_roles', $roles );

        return isset( $roles[ $field_type ] ) ? $roles[ $field_type ] : '';
    }

    /**
     * Generate complete ARIA attributes for a field
     *
     * @param BAF_Field $field Field instance.
     * @return array ARIA attributes.
     */
    public function generate_for_field( $field ) {
        $attrs = array();
        $config = $field->config ?? array();

        // aria-required
        if ( $field->is_required() ) {
            $attrs['aria-required'] = 'true';
        }

        // aria-label (custom override)
        if ( ! empty( $config['aria_label'] ) ) {
            $attrs['aria-label'] = $config['aria_label'];
        }

        // aria-describedby (description + error references)
        $described_by = array();
        
        if ( ! empty( $config['description'] ) ) {
            $described_by[] = $field->get_html_id() . '-desc';
        }
        
        if ( ! empty( $field->get_errors() ) ) {
            $described_by[] = $field->get_html_id() . '-error';
        }

        if ( ! empty( $config['aria_describedby'] ) ) {
            $described_by[] = $config['aria_describedby'];
        }

        if ( ! empty( $described_by ) ) {
            $attrs['aria-describedby'] = implode( ' ', $described_by );
        }

        // aria-invalid
        if ( ! empty( $field->get_errors() ) ) {
            $attrs['aria-invalid'] = 'true';
            $attrs['aria-errormessage'] = $field->get_html_id() . '-error';
        }

        // aria-readonly
        if ( ! empty( $config['read_only'] ) ) {
            $attrs['aria-readonly'] = 'true';
        }

        // aria-disabled
        if ( ! empty( $config['disabled'] ) ) {
            $attrs['aria-disabled'] = 'true';
        }

        // Field type specific attributes
        $attrs = $this->add_type_specific_attrs( $attrs, $field );

        /**
         * Filter ARIA attributes for a field
         *
         * @param array     $attrs ARIA attributes.
         * @param BAF_Field $field Field instance.
         */
        return apply_filters( 'baf_field_aria_attributes', $attrs, $field );
    }

    /**
     * Add type-specific ARIA attributes
     *
     * @param array     $attrs Current attributes.
     * @param BAF_Field $field Field instance.
     * @return array Modified attributes.
     */
    private function add_type_specific_attrs( $attrs, $field ) {
        $type = $field->get_type();
        $config = $field->config ?? array();

        switch ( $type ) {
            case 'number':
            case 'range':
            case 'rating':
                if ( isset( $config['min'] ) ) {
                    $attrs['aria-valuemin'] = $config['min'];
                }
                if ( isset( $config['max'] ) ) {
                    $attrs['aria-valuemax'] = $config['max'];
                }
                if ( isset( $config['step'] ) ) {
                    $attrs['aria-valuestep'] = $config['step'];
                }
                break;

            case 'toggle':
                $attrs['role'] = 'switch';
                $value = $field->get_value();
                $attrs['aria-checked'] = $value ? 'true' : 'false';
                break;

            case 'multiselect':
                $attrs['aria-multiselectable'] = 'true';
                break;

            case 'combobox':
                $attrs['aria-autocomplete'] = 'list';
                $attrs['aria-haspopup'] = 'listbox';
                $attrs['aria-expanded'] = 'false'; // Updated by JS
                break;

            case 'textarea':
                $attrs['aria-multiline'] = 'true';
                break;

            case 'password':
                // Note: don't expose password visibility state for security
                break;

            case 'file':
                if ( ! empty( $config['multiple'] ) ) {
                    $attrs['aria-label'] = sprintf(
                        __( '%s (multiple files allowed)', 'bits-accessible-forms' ),
                        $field->get_label()
                    );
                }
                break;
        }

        return $attrs;
    }

    /**
     * Generate ARIA attributes for fieldset/legend groups
     *
     * @param string $legend_text Legend text.
     * @param string $description Optional description.
     * @param array  $options     Additional options.
     * @return array Attributes for fieldset.
     */
    public function generate_for_fieldset( $legend_text, $description = '', $options = array() ) {
        $fieldset_id = $options['id'] ?? 'baf-fieldset-' . wp_generate_password( 8, false );

        $attrs = array(
            'fieldset' => array(
                'role' => 'group',
            ),
            'legend' => array(
                'id' => $fieldset_id . '-legend',
            ),
        );

        if ( $description ) {
            $attrs['fieldset']['aria-describedby'] = $fieldset_id . '-desc';
            $attrs['description_id'] = $fieldset_id . '-desc';
        }

        if ( ! empty( $options['required'] ) ) {
            $attrs['fieldset']['aria-required'] = 'true';
        }

        return $attrs;
    }

    /**
     * Generate ARIA for radio button group
     *
     * @param array $options Radio options.
     * @param mixed $selected_value Currently selected value.
     * @param bool  $required Whether selection is required.
     * @return array ARIA attributes for group and options.
     */
    public function generate_for_radio_group( $options, $selected_value = null, $required = false ) {
        $attrs = array(
            'group' => array(
                'role' => 'radiogroup',
            ),
            'options' => array(),
        );

        if ( $required ) {
            $attrs['group']['aria-required'] = 'true';
        }

        foreach ( $options as $index => $option ) {
            $value = is_array( $option ) ? ( $option['value'] ?? $index ) : $option;
            $attrs['options'][ $index ] = array(
                'role' => 'radio',
                'aria-checked' => ( $value == $selected_value ) ? 'true' : 'false',
            );
        }

        return $attrs;
    }

    /**
     * Generate ARIA for checkbox group
     *
     * @param array $options Checkbox options.
     * @param array $selected_values Currently selected values.
     * @return array ARIA attributes for group and options.
     */
    public function generate_for_checkbox_group( $options, $selected_values = array() ) {
        $attrs = array(
            'group' => array(
                'role' => 'group',
            ),
            'options' => array(),
        );

        $selected_values = (array) $selected_values;

        foreach ( $options as $index => $option ) {
            $value = is_array( $option ) ? ( $option['value'] ?? $index ) : $option;
            $attrs['options'][ $index ] = array(
                'aria-checked' => in_array( $value, $selected_values, true ) ? 'true' : 'false',
            );
        }

        return $attrs;
    }

    /**
     * Generate live region attributes
     *
     * @param string $politeness 'polite', 'assertive', or 'off'.
     * @param bool   $atomic     Whether to announce entire region.
     * @return array Live region attributes.
     */
    public function generate_live_region( $politeness = 'polite', $atomic = true ) {
        return array(
            'role'        => 'status',
            'aria-live'   => $politeness,
            'aria-atomic' => $atomic ? 'true' : 'false',
        );
    }

    /**
     * Generate ARIA for error message container
     *
     * @param string $field_id Field ID this error relates to.
     * @return array Error container attributes.
     */
    public function generate_for_error( $field_id ) {
        return array(
            'id'          => $field_id . '-error',
            'role'        => 'alert',
            'aria-live'   => 'polite',
            'aria-atomic' => 'true',
            'class'       => 'baf-field-error-message',
        );
    }

    /**
     * Generate ARIA for move buttons (drag-drop alternative)
     *
     * @param string $item_label Label of the item being moved.
     * @param int    $position   Current position (1-indexed).
     * @param int    $total      Total number of items.
     * @return array Move button attributes.
     */
    public function generate_for_move_buttons( $item_label, $position, $total ) {
        return array(
            'move_up' => array(
                'aria-label' => sprintf(
                    /* translators: %1$s: item label, %2$d: current position */
                    __( 'Move %1$s up from position %2$d', 'bits-accessible-forms' ),
                    $item_label,
                    $position
                ),
                'disabled' => $position <= 1,
            ),
            'move_down' => array(
                'aria-label' => sprintf(
                    /* translators: %1$s: item label, %2$d: current position */
                    __( 'Move %1$s down from position %2$d', 'bits-accessible-forms' ),
                    $item_label,
                    $position
                ),
                'disabled' => $position >= $total,
            ),
        );
    }

    /**
     * Generate ARIA for progress indicator
     *
     * @param int    $current Current step/page.
     * @param int    $total   Total steps/pages.
     * @param string $mode    'steps' or 'percentage'.
     * @return array Progress attributes.
     */
    public function generate_for_progress( $current, $total, $mode = 'steps' ) {
        $percentage = $total > 0 ? round( ( $current / $total ) * 100 ) : 0;

        $attrs = array(
            'role'          => 'progressbar',
            'aria-valuemin' => '0',
            'aria-valuemax' => '100',
            'aria-valuenow' => (string) $percentage,
        );

        if ( $mode === 'steps' ) {
            $attrs['aria-valuetext'] = sprintf(
                /* translators: %1$d: current step, %2$d: total steps */
                __( 'Step %1$d of %2$d', 'bits-accessible-forms' ),
                $current,
                $total
            );
        } else {
            $attrs['aria-valuetext'] = sprintf(
                /* translators: %d: percentage complete */
                __( '%d%% complete', 'bits-accessible-forms' ),
                $percentage
            );
        }

        return $attrs;
    }

    /**
     * Build ARIA attributes string from array
     *
     * @param array $attrs Attributes array.
     * @return string Attributes string.
     */
    public function build_attrs_string( $attrs ) {
        $parts = array();

        foreach ( $attrs as $key => $value ) {
            if ( is_bool( $value ) ) {
                if ( $value ) {
                    $parts[] = esc_attr( $key );
                }
            } elseif ( $value !== '' && $value !== null ) {
                $parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
            }
        }

        return implode( ' ', $parts );
    }
}
