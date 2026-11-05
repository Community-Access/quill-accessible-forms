<?php
/**
 * Select/Dropdown Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Select class
 */
class QAF_Field_Select extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'select';

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
            'choices'            => array(),
            'placeholder'        => __( 'Select an option...', 'quill-accessible-forms' ),
            'multiple'           => false,
            'searchable'         => false,
            'allow_other'        => false,
            'other_label'        => __( 'Other', 'quill-accessible-forms' ),
            'optgroups'          => false,
            // Choice library support
            'choice_library'     => '',
            // Cascading dropdown support
            'cascade_from'       => '',
            'cascade_mapping'    => array(),
            'cascade_library'    => '', // Use library for cascading (e.g., 'states' cascades from 'countries')
            'empty_message'      => __( 'Please select an option above first', 'quill-accessible-forms' ),
            'loading_message'    => __( 'Loading options...', 'quill-accessible-forms' ),
            // Dynamic populate
            'populate'           => array(),
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
                'choices' => array(
                    'type'  => 'choices',
                    'label' => __( 'Choices', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder Text', 'quill-accessible-forms' ),
                ),
                'multiple' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Multiple Selections', 'quill-accessible-forms' ),
                ),
                'searchable' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Make Searchable', 'quill-accessible-forms' ),
                    'desc'  => __( 'Enables filtering options as user types.', 'quill-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'choice_library' => array(
                    'type'        => 'select',
                    'label'       => __( 'Use Choice Library', 'quill-accessible-forms' ),
                    'description' => __( 'Use a predefined list instead of custom choices.', 'quill-accessible-forms' ),
                    'options'     => $this->get_choice_libraries(),
                ),
            ),
            'cascade' => array(
                'cascade_from' => array(
                    'type'        => 'field_select',
                    'label'       => __( 'Cascade From Field', 'quill-accessible-forms' ),
                    'description' => __( 'Filter options based on selection in another field.', 'quill-accessible-forms' ),
                    'filter'      => array( 'select', 'radio', 'checkbox' ),
                ),
                'cascade_library' => array(
                    'type'        => 'select',
                    'label'       => __( 'Use Cascading Library', 'quill-accessible-forms' ),
                    'description' => __( 'Auto-populate based on parent selection (e.g., States from Country).', 'quill-accessible-forms' ),
                    'options'     => array(
                        ''          => __( 'None (use mapping below)', 'quill-accessible-forms' ),
                        'states'    => __( 'States/Provinces (from Country)', 'quill-accessible-forms' ),
                    ),
                    'condition'   => array( 'cascade_from' => '!empty' ),
                ),
                'cascade_mapping' => array(
                    'type'        => 'cascade_builder',
                    'label'       => __( 'Cascade Mapping', 'quill-accessible-forms' ),
                    'description' => __( 'Define which options appear for each parent value.', 'quill-accessible-forms' ),
                    'condition'   => array( 'cascade_from' => '!empty', 'cascade_library' => '' ),
                ),
                'empty_message' => array(
                    'type'      => 'text',
                    'label'     => __( 'Empty State Message', 'quill-accessible-forms' ),
                    'condition' => array( 'cascade_from' => '!empty' ),
                ),
                'loading_message' => array(
                    'type'      => 'text',
                    'label'     => __( 'Loading Message', 'quill-accessible-forms' ),
                    'condition' => array( 'cascade_from' => '!empty' ),
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
        $html_id       = $this->get_html_id();
        $name          = 'qaf_field_' . $this->get_id();
        $value         = $this->get_value();
        $choices       = $this->get_choices();
        $is_multiple   = ! empty( $this->config['multiple'] );
        $is_search     = ! empty( $this->config['searchable'] );
        $cascade_from  = $this->config['cascade_from'] ?? '';
        $is_cascading  = ! empty( $cascade_from );

        if ( $is_multiple ) {
            $name .= '[]';
            $value = (array) $value;
        }

        $class = 'qaf-select-input';
        if ( $is_search ) {
            $class .= ' qaf-select-searchable';
        }
        if ( $is_cascading ) {
            $class .= ' qaf-select-cascade';
        }

        $attrs = array(
            'id'    => $html_id,
            'name'  => $name,
            'class' => $class,
        );

        if ( $is_multiple ) {
            $attrs['multiple'] = true;
        }

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $attrs = array_merge( $attrs, $aria );

        // Cascade data attributes
        if ( $is_cascading ) {
            $attrs['data-cascade-from'] = $cascade_from;
            $attrs['data-empty-message'] = $this->config['empty_message'] ?? '';
            $attrs['data-loading-message'] = $this->config['loading_message'] ?? '';
            
            // Include cascade mapping for client-side filtering
            if ( ! empty( $this->config['cascade_mapping'] ) ) {
                $attrs['data-cascade-mapping'] = wp_json_encode( $this->config['cascade_mapping'] );
            }
            
            // Include populate config for server-side filtering  
            if ( ! empty( $this->config['populate'] ) ) {
                $attrs['data-populate'] = wp_json_encode( $this->config['populate'] );
            }
        }

        // Start select building
        $field = '<select ' . $this->build_attributes( $attrs ) . '>';

        // Placeholder option or cascade empty message
        if ( ! $is_multiple ) {
            if ( $is_cascading ) {
                $field .= '<option value="">' . esc_html( $this->config['empty_message'] ?? $this->config['placeholder'] ) . '</option>';
            } elseif ( ! empty( $this->config['placeholder'] ) ) {
                $field .= '<option value="">' . esc_html( $this->config['placeholder'] ) . '</option>';
            }
        }

        // Render options (if not cascading or has initial value)
        if ( ! $is_cascading || ! empty( $value ) ) {
            if ( ! empty( $this->config['optgroups'] ) ) {
                $field .= $this->render_optgroups( $choices, $value, $is_multiple );
            } else {
                $field .= $this->render_options( $choices, $value, $is_multiple );
            }
        }

        $field .= '</select>';

        // Loading indicator for cascading
        if ( $is_cascading ) {
            $field .= '<div class="qaf-cascade-loading" aria-live="polite" hidden>';
            $field .= '<span class="qaf-spinner" aria-hidden="true"></span>';
            $field .= '<span class="qaf-loading-text">' . esc_html( $this->config['loading_message'] ?? '' ) . '</span>';
            $field .= '</div>';
        }

        return $this->render_wrapper( $field );
    }

    /**
     * Render options
     *
     * @param array $choices    Choices array.
     * @param mixed $value      Current value.
     * @param bool  $is_multiple Whether multiple selection.
     * @return string Options HTML.
     */
    protected function render_options( $choices, $value, $is_multiple ) {
        $html = '';

        foreach ( $choices as $choice ) {
            $choice_value = $choice['value'] ?? $choice['label'];
            $choice_label = $choice['label'];
            
            $selected = $is_multiple 
                ? in_array( $choice_value, $value, true ) 
                : $value === $choice_value;

            $html .= '<option value="' . esc_attr( $choice_value ) . '"';
            if ( $selected ) {
                $html .= ' selected';
            }
            $html .= '>' . esc_html( $choice_label ) . '</option>';
        }

        return $html;
    }

    /**
     * Render optgroups
     *
     * @param array $groups     Groups array.
     * @param mixed $value      Current value.
     * @param bool  $is_multiple Whether multiple selection.
     * @return string Optgroups HTML.
     */
    protected function render_optgroups( $groups, $value, $is_multiple ) {
        $html = '';

        foreach ( $groups as $group ) {
            if ( isset( $group['options'] ) ) {
                $html .= '<optgroup label="' . esc_attr( $group['label'] ) . '">';
                $html .= $this->render_options( $group['options'], $value, $is_multiple );
                $html .= '</optgroup>';
            } else {
                // Ungrouped option
                $html .= $this->render_options( array( $group ), $value, $is_multiple );
            }
        }

        return $html;
    }

    /**
     * Get available choice libraries for admin UI
     *
     * @return array Library options.
     */
    protected function get_choice_libraries() {
        if ( class_exists( 'QAF_Choice_Libraries' ) ) {
            $libraries = QAF_Choice_Libraries::get_all();
            return array_merge( array( '' => __( 'None (custom choices)', 'quill-accessible-forms' ) ), $libraries );
        }
        return array( '' => __( 'None', 'quill-accessible-forms' ) );
    }

    /**
     * Get choices array
     *
     * @return array Choices.
     */
    protected function get_choices() {
        // Check for choice library first
        if ( ! empty( $this->config['choice_library'] ) && class_exists( 'QAF_Choice_Libraries' ) ) {
            return QAF_Choice_Libraries::get( $this->config['choice_library'] );
        }

        // Check for cascade library
        if ( ! empty( $this->config['cascade_library'] ) && class_exists( 'QAF_Choice_Libraries' ) ) {
            // Get parent field value for cascading
            $parent_value = $this->get_cascade_parent_value();
            return QAF_Choice_Libraries::get( $this->config['cascade_library'], $parent_value );
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
     * Get cascade parent field value
     *
     * @return string|null Parent value.
     */
    protected function get_cascade_parent_value() {
        $cascade_from = $this->config['cascade_from'] ?? '';
        if ( empty( $cascade_from ) ) {
            return null;
        }

        // Check POST data first (during form submission)
        $field_name = 'qaf_field_' . $cascade_from;
        if ( isset( $_POST[ $field_name ] ) ) {
            return sanitize_text_field( $_POST[ $field_name ] );
        }

        // Check GET data (for pre-populated forms)
        if ( isset( $_GET[ $field_name ] ) ) {
            return sanitize_text_field( $_GET[ $field_name ] );
        }

        return null;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'sanitize_text_field', $value );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $choices = $this->get_choices();
        $valid_values = array_map( function( $c ) {
            return $c['value'] ?? $c['label'];
        }, $choices );

        if ( is_array( $value ) ) {
            foreach ( $value as $v ) {
                if ( ! in_array( $v, $valid_values, true ) ) {
                    $this->add_error( __( 'Please select a valid option.', 'quill-accessible-forms' ) );
                    return;
                }
            }
        } elseif ( ! empty( $value ) && ! in_array( $value, $valid_values, true ) ) {
            $this->add_error( __( 'Please select a valid option.', 'quill-accessible-forms' ) );
        }
    }
}
