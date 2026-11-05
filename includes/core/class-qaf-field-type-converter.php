<?php
/**
 * Field Type Converter
 *
 * Handles conversion between field types with property preservation and safety checks.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Type_Converter class
 * 
 * Enables safe field type conversion with property mapping,
 * data preservation, and conditional logic repair.
 */
class QAF_Field_Type_Converter {

    /**
     * Property groups for preservation
     *
     * @var array
     */
    private $property_groups = array(
        // Universal properties (preserved for all conversions)
        'universal' => array(
            'id',
            'label',
            'description',
            'help_text',
            'required',
            'readonly',
            'disabled',
            'admin_only',
            'css_class',
            'css_id',
            'default_value',
            'placeholder',
            'conditional_logic',
            'page',
            'section',
        ),

        // Validation properties
        'validation' => array(
            'min',
            'max',
            'minlength',
            'maxlength',
            'pattern',
            'step',
        ),

        // Choice properties
        'choices' => array(
            'choices',
            'default_value',
            'allow_other',
            'randomize',
        ),

        // Text properties
        'text' => array(
            'autocomplete',
            'autocapitalize',
            'spellcheck',
            'inputmode',
            'enterkeyhint',
        ),

        // Layout properties
        'layout' => array(
            'columns',
            'label_position',
            'size',
            'width',
        ),

        // Date/time properties
        'datetime' => array(
            'date_format',
            'time_format',
            'min_date',
            'max_date',
            'min_time',
            'max_time',
            'disable_dates',
        ),

        // File properties
        'file' => array(
            'accept',
            'multiple',
            'max_file_size',
            'max_files',
            'capture',
        ),
    );

    /**
     * Conversion compatibility matrix
     * Maps source types to compatible target types with conversion quality
     *
     * @var array
     */
    private $compatibility_matrix = array(
        // Text-based conversions
        'text' => array(
            'textarea'  => 'lossless',
            'email'     => 'lossless',
            'phone'     => 'lossless',
            'website'   => 'lossless',
            'password'  => 'lossless',
            'hidden'    => 'lossless',
            'number'    => 'lossy',      // Non-numeric values lost
            'select'    => 'lossy',      // Needs choices
            'radio'     => 'lossy',      // Needs choices
        ),

        'textarea' => array(
            'text'      => 'lossy',      // Truncation possible
            'html'      => 'lossless',
        ),

        'email' => array(
            'text'      => 'lossless',
            'textarea'  => 'lossless',
        ),

        'phone' => array(
            'text'      => 'lossless',
            'number'    => 'lossy',
        ),

        'number' => array(
            'text'      => 'lossless',
            'range'     => 'lossless',
            'price'     => 'lossless',
            'quantity'  => 'lossless',
        ),

        'range' => array(
            'number'    => 'lossless',
        ),

        // Choice conversions
        'select' => array(
            'radio'       => 'lossless',
            'multiselect' => 'lossless',
            'checkbox'    => 'lossless',
            'text'        => 'lossy',    // Choices lost
        ),

        'multiselect' => array(
            'checkbox'    => 'lossless',
            'select'      => 'lossy',    // Multi→single
        ),

        'radio' => array(
            'select'      => 'lossless',
            'checkbox'    => 'lossless',
        ),

        'checkbox' => array(
            'radio'       => 'lossy',    // Multi→single
            'select'      => 'lossy',
            'multiselect' => 'lossless',
            'consent'     => 'lossy',    // Single checkbox only
            'toggle'      => 'lossy',    // Single checkbox only
        ),

        'consent' => array(
            'checkbox'    => 'lossless',
            'toggle'      => 'lossless',
        ),

        'toggle' => array(
            'checkbox'    => 'lossless',
            'consent'     => 'lossless',
        ),

        // Date/time conversions
        'date' => array(
            'datetime'    => 'lossless',
            'text'        => 'lossy',
            'daterange'   => 'lossless',
        ),

        'time' => array(
            'datetime'    => 'lossless',
            'text'        => 'lossy',
        ),

        'datetime' => array(
            'date'        => 'lossy',    // Time lost
            'time'        => 'lossy',    // Date lost
            'text'        => 'lossy',
        ),

        'daterange' => array(
            'date'        => 'lossy',    // Range→single
        ),

        // Rating/scale conversions
        'rating' => array(
            'likert'      => 'lossy',
            'nps'         => 'lossy',
            'number'      => 'lossy',
            'radio'       => 'lossless',
        ),

        'likert' => array(
            'radio'       => 'lossless',
            'select'      => 'lossless',
        ),

        'nps' => array(
            'rating'      => 'lossy',
            'radio'       => 'lossless',
            'number'      => 'lossless',
        ),

        'ranking' => array(
            'multiselect' => 'lossy',    // Order lost
            'checkbox'    => 'lossy',    // Order lost
        ),
    );

    /**
     * Convert a field to a new type
     *
     * @param array  $field       Field configuration.
     * @param string $target_type Target field type.
     * @param array  $options     Conversion options.
     * @return array Conversion result with 'field', 'report', 'warnings'.
     */
    public function convert( $field, $target_type, $options = array() ) {
        $defaults = array(
            'preserve_id'            => true,
            'generate_choices'       => true,  // Auto-generate choices if needed
            'preserve_conditional'   => true,
            'dry_run'                => false, // Just return report, don't convert
        );
        $options = wp_parse_args( $options, $defaults );

        $source_type = $field['type'] ?? 'text';

        // Start building result
        $result = array(
            'success'   => true,
            'field'     => $field,
            'report'    => array(
                'source_type'  => $source_type,
                'target_type'  => $target_type,
                'preserved'    => array(),
                'transformed'  => array(),
                'dropped'      => array(),
                'warnings'     => array(),
            ),
        );

        // Same type - nothing to do
        if ( $source_type === $target_type ) {
            return $result;
        }

        // Check compatibility
        $compatibility = $this->get_compatibility( $source_type, $target_type );
        $result['report']['compatibility'] = $compatibility;

        if ( $compatibility === 'incompatible' ) {
            $result['report']['warnings'][] = sprintf(
                /* translators: 1: source type, 2: target type */
                __( 'Converting from %1$s to %2$s may lose significant data. Review carefully.', 'quill-accessible-forms' ),
                $source_type,
                $target_type
            );
        }

        if ( $options['dry_run'] ) {
            $result['report'] = $this->generate_preview_report( $field, $target_type );
            return $result;
        }

        // Perform conversion
        $converted = $this->perform_conversion( $field, $target_type, $result['report'], $options );
        $result['field'] = $converted;

        return $result;
    }

    /**
     * Perform the actual conversion
     *
     * @param array  $field       Field configuration.
     * @param string $target_type Target field type.
     * @param array  &$report     Report array to update.
     * @param array  $options     Conversion options.
     * @return array Converted field.
     */
    private function perform_conversion( $field, $target_type, &$report, $options ) {
        $source_type = $field['type'] ?? 'text';
        $converted = array();

        // Always change the type
        $converted['type'] = $target_type;

        // Preserve universal properties
        foreach ( $this->property_groups['universal'] as $prop ) {
            if ( isset( $field[ $prop ] ) ) {
                $converted[ $prop ] = $field[ $prop ];
                $report['preserved'][] = $prop;
            }
        }

        // Get property groups for source and target
        $source_groups = $this->get_field_property_groups( $source_type );
        $target_groups = $this->get_field_property_groups( $target_type );

        // Process each property group
        foreach ( $this->property_groups as $group_name => $properties ) {
            if ( $group_name === 'universal' ) {
                continue;
            }

            $source_has = in_array( $group_name, $source_groups, true );
            $target_has = in_array( $group_name, $target_groups, true );

            foreach ( $properties as $prop ) {
                if ( ! isset( $field[ $prop ] ) ) {
                    continue;
                }

                if ( $target_has ) {
                    // Target supports this property - preserve it
                    $converted[ $prop ] = $field[ $prop ];
                    $report['preserved'][] = $prop;
                } elseif ( $source_has ) {
                    // Source has it but target doesn't - dropped
                    $report['dropped'][] = $prop;
                }
            }
        }

        // Special handling for specific conversions
        $converted = $this->apply_special_conversions( $field, $converted, $source_type, $target_type, $report, $options );

        return $converted;
    }

    /**
     * Apply special conversion logic for specific type pairs
     *
     * @param array  $source      Source field.
     * @param array  $converted   Converted field.
     * @param string $source_type Source type.
     * @param string $target_type Target type.
     * @param array  &$report     Report array.
     * @param array  $options     Options.
     * @return array Updated converted field.
     */
    private function apply_special_conversions( $source, $converted, $source_type, $target_type, &$report, $options ) {
        // Text → Choice: Generate choices from default value or placeholder
        if ( $this->is_choice_type( $target_type ) && ! $this->is_choice_type( $source_type ) ) {
            if ( empty( $converted['choices'] ) && $options['generate_choices'] ) {
                $converted['choices'] = array(
                    array( 'value' => 'option_1', 'label' => __( 'Option 1', 'quill-accessible-forms' ) ),
                    array( 'value' => 'option_2', 'label' => __( 'Option 2', 'quill-accessible-forms' ) ),
                    array( 'value' => 'option_3', 'label' => __( 'Option 3', 'quill-accessible-forms' ) ),
                );
                $report['transformed'][] = array(
                    'property' => 'choices',
                    'action'   => 'generated',
                    'note'     => __( 'Default choices added. Please customize.', 'quill-accessible-forms' ),
                );
            }
        }

        // Choice → Text: Note about choice data
        if ( $this->is_choice_type( $source_type ) && ! $this->is_choice_type( $target_type ) ) {
            if ( ! empty( $source['choices'] ) ) {
                $report['warnings'][] = sprintf(
                    /* translators: %d: number of choices */
                    __( '%d choice options will be lost in this conversion.', 'quill-accessible-forms' ),
                    count( $source['choices'] )
                );
            }
        }

        // Multiselect → Single select: Warn about multiple values
        if ( in_array( $source_type, array( 'multiselect', 'checkbox' ), true ) &&
             in_array( $target_type, array( 'select', 'radio' ), true ) ) {
            $report['warnings'][] = __( 'Existing entries with multiple selections will keep only the first value.', 'quill-accessible-forms' );
        }

        // Rating → Radio: Generate scale choices
        if ( $source_type === 'rating' && $target_type === 'radio' ) {
            $max = $source['max'] ?? 5;
            $converted['choices'] = array();
            for ( $i = 1; $i <= $max; $i++ ) {
                $converted['choices'][] = array(
                    'value' => (string) $i,
                    'label' => (string) $i . ' ' . ( $i === 1 ? __( 'star', 'quill-accessible-forms' ) : __( 'stars', 'quill-accessible-forms' ) ),
                );
            }
            $report['transformed'][] = array(
                'property' => 'choices',
                'action'   => 'generated_from_scale',
            );
        }

        // NPS → Number/Radio: Generate 0-10 scale
        if ( $source_type === 'nps' && in_array( $target_type, array( 'radio', 'select' ), true ) ) {
            $converted['choices'] = array();
            for ( $i = 0; $i <= 10; $i++ ) {
                $converted['choices'][] = array(
                    'value' => (string) $i,
                    'label' => (string) $i,
                );
            }
            $report['transformed'][] = array(
                'property' => 'choices',
                'action'   => 'generated_nps_scale',
            );
        }

        // Likert → Select/Radio: Preserve scale as choices
        if ( $source_type === 'likert' && in_array( $target_type, array( 'radio', 'select' ), true ) ) {
            if ( ! empty( $source['scale'] ) ) {
                $converted['choices'] = array();
                foreach ( $source['scale'] as $value => $label ) {
                    $converted['choices'][] = array(
                        'value' => (string) $value,
                        'label' => $label,
                    );
                }
                $report['transformed'][] = array(
                    'property' => 'scale',
                    'action'   => 'converted_to_choices',
                );
            }
        }

        // Text → Number: Set inputmode
        if ( $target_type === 'number' ) {
            $converted['inputmode'] = 'numeric';
        }

        // Text → Email: Set autocomplete
        if ( $target_type === 'email' ) {
            $converted['autocomplete'] = $converted['autocomplete'] ?? 'email';
        }

        // Text → Phone: Set autocomplete and inputmode
        if ( $target_type === 'phone' ) {
            $converted['autocomplete'] = $converted['autocomplete'] ?? 'tel';
            $converted['inputmode'] = 'tel';
        }

        // Text → Website: Set autocomplete and inputmode
        if ( $target_type === 'website' ) {
            $converted['autocomplete'] = $converted['autocomplete'] ?? 'url';
            $converted['inputmode'] = 'url';
        }

        // Textarea rows
        if ( $target_type === 'textarea' && ! isset( $converted['rows'] ) ) {
            $converted['rows'] = 4;
        }

        return $converted;
    }

    /**
     * Generate preview report without actually converting
     *
     * @param array  $field       Field configuration.
     * @param string $target_type Target type.
     * @return array Preview report.
     */
    public function generate_preview_report( $field, $target_type ) {
        $source_type = $field['type'] ?? 'text';

        $report = array(
            'source_type'   => $source_type,
            'target_type'   => $target_type,
            'compatibility' => $this->get_compatibility( $source_type, $target_type ),
            'preserved'     => array(),
            'transformed'   => array(),
            'dropped'       => array(),
            'warnings'      => array(),
        );

        // Get property groups
        $target_groups = $this->get_field_property_groups( $target_type );

        // Check each property
        foreach ( $field as $prop => $value ) {
            if ( $prop === 'type' ) {
                continue;
            }

            // Check if universal
            if ( in_array( $prop, $this->property_groups['universal'], true ) ) {
                $report['preserved'][] = $prop;
                continue;
            }

            // Check if target supports it
            $supported = false;
            foreach ( $target_groups as $group_name ) {
                if ( isset( $this->property_groups[ $group_name ] ) &&
                     in_array( $prop, $this->property_groups[ $group_name ], true ) ) {
                    $supported = true;
                    break;
                }
            }

            if ( $supported ) {
                $report['preserved'][] = $prop;
            } else {
                $report['dropped'][] = $prop;
            }
        }

        // Add warnings based on conversion type
        if ( $report['compatibility'] === 'lossy' || $report['compatibility'] === 'incompatible' ) {
            $report['warnings'][] = __( 'Some data may be lost in this conversion.', 'quill-accessible-forms' );
        }

        // Specific warnings
        if ( $this->is_choice_type( $source_type ) && ! $this->is_choice_type( $target_type ) ) {
            $report['warnings'][] = __( 'Choice options will be lost.', 'quill-accessible-forms' );
        }

        if ( ! $this->is_choice_type( $source_type ) && $this->is_choice_type( $target_type ) ) {
            $report['warnings'][] = __( 'You will need to add choice options after conversion.', 'quill-accessible-forms' );
        }

        return $report;
    }

    /**
     * Get compatibility level between two field types
     *
     * @param string $source_type Source type.
     * @param string $target_type Target type.
     * @return string 'lossless', 'lossy', or 'incompatible'.
     */
    public function get_compatibility( $source_type, $target_type ) {
        if ( $source_type === $target_type ) {
            return 'lossless';
        }

        if ( isset( $this->compatibility_matrix[ $source_type ][ $target_type ] ) ) {
            return $this->compatibility_matrix[ $source_type ][ $target_type ];
        }

        // Check reverse (some conversions are symmetric)
        if ( isset( $this->compatibility_matrix[ $target_type ][ $source_type ] ) ) {
            $reverse = $this->compatibility_matrix[ $target_type ][ $source_type ];
            // Reverse of lossless might still be lossy
            return $reverse === 'lossless' ? 'lossy' : 'incompatible';
        }

        // Universal fallback: everything can become text (lossy)
        if ( $target_type === 'text' || $target_type === 'textarea' ) {
            return 'lossy';
        }

        // Everything can come from text (might need setup)
        if ( $source_type === 'text' || $source_type === 'textarea' ) {
            return 'lossy';
        }

        return 'incompatible';
    }

    /**
     * Get all compatible target types for a source type
     *
     * @param string $source_type Source type.
     * @return array Compatible types with compatibility level.
     */
    public function get_compatible_types( $source_type ) {
        $compatible = array();

        // Direct mappings
        if ( isset( $this->compatibility_matrix[ $source_type ] ) ) {
            foreach ( $this->compatibility_matrix[ $source_type ] as $target => $level ) {
                $compatible[ $target ] = $level;
            }
        }

        // Add text/textarea as universal targets
        if ( ! isset( $compatible['text'] ) && $source_type !== 'text' ) {
            $compatible['text'] = 'lossy';
        }
        if ( ! isset( $compatible['textarea'] ) && $source_type !== 'textarea' ) {
            $compatible['textarea'] = 'lossy';
        }

        return $compatible;
    }

    /**
     * Get all field types available for conversion
     *
     * @return array Field types grouped by category with compatibility hints.
     */
    public function get_conversion_targets() {
        $registry = QAF_Field_Registry::get_instance();
        $all_types = $registry->get_all_field_types();

        // Exclude non-convertible types
        $excluded = array(
            'page', 'section', 'columns', 'html', 'submit', 'save',
            'captcha', 'calculation', 'repeater',
        );

        $targets = array();
        foreach ( $all_types as $type => $info ) {
            if ( in_array( $type, $excluded, true ) ) {
                continue;
            }
            $targets[ $type ] = array(
                'label'    => $info['label'],
                'icon'     => $info['icon'],
                'category' => $info['category'],
            );
        }

        return $targets;
    }

    /**
     * Check if type is a choice-based field
     *
     * @param string $type Field type.
     * @return bool True if choice field.
     */
    private function is_choice_type( $type ) {
        return in_array( $type, array(
            'select', 'multiselect', 'radio', 'checkbox',
            'likert', 'ranking', 'rating', 'nps',
        ), true );
    }

    /**
     * Get property groups applicable to a field type
     *
     * @param string $type Field type.
     * @return array Applicable property group names.
     */
    private function get_field_property_groups( $type ) {
        $groups = array( 'universal' );

        // Text-based
        if ( in_array( $type, array( 'text', 'email', 'phone', 'website', 'password', 'textarea', 'hidden' ), true ) ) {
            $groups[] = 'text';
            $groups[] = 'validation';
        }

        // Number-based
        if ( in_array( $type, array( 'number', 'range', 'price', 'quantity', 'rating', 'nps' ), true ) ) {
            $groups[] = 'validation';
        }

        // Choice-based
        if ( $this->is_choice_type( $type ) ) {
            $groups[] = 'choices';
        }

        // Date/time
        if ( in_array( $type, array( 'date', 'time', 'datetime', 'daterange' ), true ) ) {
            $groups[] = 'datetime';
        }

        // File
        if ( in_array( $type, array( 'fileupload', 'post_image' ), true ) ) {
            $groups[] = 'file';
        }

        // Layout (most fields)
        $groups[] = 'layout';

        return $groups;
    }

    /**
     * Analyze conditional logic affected by a field type change
     *
     * @param array  $form_config   Full form configuration.
     * @param string $field_id      Field ID being changed.
     * @param string $source_type   Current field type.
     * @param string $target_type   New field type.
     * @return array Affected conditional rules with suggestions.
     */
    public function analyze_conditional_impact( $form_config, $field_id, $source_type, $target_type ) {
        $affected = array();
        $fields = $form_config['fields'] ?? array();

        foreach ( $fields as $field ) {
            if ( empty( $field['conditional_logic'] ) ) {
                continue;
            }

            $logic = $field['conditional_logic'];
            if ( ! is_array( $logic ) || empty( $logic['rules'] ) ) {
                continue;
            }

            foreach ( $logic['rules'] as $rule_index => $rule ) {
                if ( ( $rule['field'] ?? '' ) !== $field_id ) {
                    continue;
                }

                $operator = $rule['operator'] ?? '=';
                $issue = $this->check_operator_compatibility( $operator, $source_type, $target_type );

                if ( $issue ) {
                    $affected[] = array(
                        'field_id'     => $field['id'],
                        'field_label'  => $field['label'] ?? $field['id'],
                        'rule_index'   => $rule_index,
                        'operator'     => $operator,
                        'issue'        => $issue['message'],
                        'suggestion'   => $issue['suggestion'],
                        'auto_fixable' => $issue['auto_fixable'],
                    );
                }
            }
        }

        return $affected;
    }

    /**
     * Check if an operator is compatible with a field type
     *
     * @param string $operator    Conditional operator.
     * @param string $source_type Source type.
     * @param string $target_type Target type.
     * @return array|null Issue details or null if compatible.
     */
    private function check_operator_compatibility( $operator, $source_type, $target_type ) {
        $numeric_operators = array( '>', '<', '>=', '<=', 'between' );
        $text_operators = array( 'contains', 'starts_with', 'ends_with' );
        $choice_operators = array( '=', '!=', 'in', 'not_in' );

        $is_source_numeric = in_array( $source_type, array( 'number', 'range', 'rating', 'nps', 'price', 'quantity' ), true );
        $is_target_numeric = in_array( $target_type, array( 'number', 'range', 'rating', 'nps', 'price', 'quantity' ), true );

        $is_source_choice = $this->is_choice_type( $source_type );
        $is_target_choice = $this->is_choice_type( $target_type );

        // Numeric operator on non-numeric target
        if ( in_array( $operator, $numeric_operators, true ) && ! $is_target_numeric ) {
            return array(
                'message'      => sprintf(
                    /* translators: %s: operator */
                    __( 'Operator "%s" requires a numeric field.', 'quill-accessible-forms' ),
                    $operator
                ),
                'suggestion'   => __( 'Change to "equals" or "not equals".', 'quill-accessible-forms' ),
                'auto_fixable' => true,
                'fix'          => '=',
            );
        }

        // Text operator on choice field
        if ( in_array( $operator, $text_operators, true ) && $is_target_choice ) {
            return array(
                'message'      => sprintf(
                    /* translators: %s: operator */
                    __( 'Operator "%s" does not work well with dropdown/radio fields.', 'quill-accessible-forms' ),
                    $operator
                ),
                'suggestion'   => __( 'Use "equals" or "in" instead.', 'quill-accessible-forms' ),
                'auto_fixable' => true,
                'fix'          => '=',
            );
        }

        // Choice values may no longer match
        if ( $is_source_choice && ! $is_target_choice ) {
            return array(
                'message'      => __( 'Choice values will no longer be available.', 'quill-accessible-forms' ),
                'suggestion'   => __( 'Update the condition value to match expected text input.', 'quill-accessible-forms' ),
                'auto_fixable' => false,
            );
        }

        return null;
    }

    /**
     * Auto-repair conditional logic after conversion
     *
     * @param array  $form_config Form configuration.
     * @param string $field_id    Changed field ID.
     * @param string $target_type New field type.
     * @return array Updated form config with repair report.
     */
    public function repair_conditional_logic( $form_config, $field_id, $target_type ) {
        $repairs = array();
        $fields = $form_config['fields'] ?? array();

        foreach ( $fields as $field_index => &$field ) {
            if ( empty( $field['conditional_logic']['rules'] ) ) {
                continue;
            }

            foreach ( $field['conditional_logic']['rules'] as $rule_index => &$rule ) {
                if ( ( $rule['field'] ?? '' ) !== $field_id ) {
                    continue;
                }

                $operator = $rule['operator'] ?? '=';
                $issue = $this->check_operator_compatibility( $operator, 'text', $target_type );

                if ( $issue && $issue['auto_fixable'] && isset( $issue['fix'] ) ) {
                    $old_operator = $rule['operator'];
                    $rule['operator'] = $issue['fix'];

                    $repairs[] = array(
                        'field_id'     => $field['id'],
                        'rule_index'   => $rule_index,
                        'old_operator' => $old_operator,
                        'new_operator' => $issue['fix'],
                    );
                }
            }
        }

        $form_config['fields'] = $fields;

        return array(
            'config'  => $form_config,
            'repairs' => $repairs,
        );
    }

    /**
     * Get all field types organized by category for UI
     *
     * @return array Field types grouped by category.
     */
    public function get_all_field_types() {
        return array(
            __( 'Text Fields', 'quill-accessible-forms' ) => array(
                'text' => array(
                    'label' => __( 'Text', 'quill-accessible-forms' ),
                    'icon'  => '📝',
                ),
                'textarea' => array(
                    'label' => __( 'Textarea', 'quill-accessible-forms' ),
                    'icon'  => '📄',
                ),
                'email' => array(
                    'label' => __( 'Email', 'quill-accessible-forms' ),
                    'icon'  => '✉️',
                ),
                'phone' => array(
                    'label' => __( 'Phone', 'quill-accessible-forms' ),
                    'icon'  => '📞',
                ),
                'website' => array(
                    'label' => __( 'Website', 'quill-accessible-forms' ),
                    'icon'  => '🌐',
                ),
                'password' => array(
                    'label' => __( 'Password', 'quill-accessible-forms' ),
                    'icon'  => '🔒',
                ),
                'hidden' => array(
                    'label' => __( 'Hidden', 'quill-accessible-forms' ),
                    'icon'  => '👁️',
                ),
            ),
            __( 'Number Fields', 'quill-accessible-forms' ) => array(
                'number' => array(
                    'label' => __( 'Number', 'quill-accessible-forms' ),
                    'icon'  => '#️⃣',
                ),
                'range' => array(
                    'label' => __( 'Range Slider', 'quill-accessible-forms' ),
                    'icon'  => '🎚️',
                ),
                'price' => array(
                    'label' => __( 'Price', 'quill-accessible-forms' ),
                    'icon'  => '💰',
                ),
                'quantity' => array(
                    'label' => __( 'Quantity', 'quill-accessible-forms' ),
                    'icon'  => '🔢',
                ),
            ),
            __( 'Choice Fields', 'quill-accessible-forms' ) => array(
                'select' => array(
                    'label' => __( 'Dropdown', 'quill-accessible-forms' ),
                    'icon'  => '🔽',
                ),
                'multiselect' => array(
                    'label' => __( 'Multi-Select', 'quill-accessible-forms' ),
                    'icon'  => '☑️',
                ),
                'radio' => array(
                    'label' => __( 'Radio Buttons', 'quill-accessible-forms' ),
                    'icon'  => '🔘',
                ),
                'checkbox' => array(
                    'label' => __( 'Checkboxes', 'quill-accessible-forms' ),
                    'icon'  => '✅',
                ),
                'consent' => array(
                    'label' => __( 'Consent', 'quill-accessible-forms' ),
                    'icon'  => '📋',
                ),
                'toggle' => array(
                    'label' => __( 'Toggle', 'quill-accessible-forms' ),
                    'icon'  => '🔀',
                ),
            ),
            __( 'Date & Time', 'quill-accessible-forms' ) => array(
                'date' => array(
                    'label' => __( 'Date', 'quill-accessible-forms' ),
                    'icon'  => '📅',
                ),
                'time' => array(
                    'label' => __( 'Time', 'quill-accessible-forms' ),
                    'icon'  => '⏰',
                ),
                'datetime' => array(
                    'label' => __( 'Date & Time', 'quill-accessible-forms' ),
                    'icon'  => '📆',
                ),
                'daterange' => array(
                    'label' => __( 'Date Range', 'quill-accessible-forms' ),
                    'icon'  => '📅',
                ),
            ),
            __( 'Rating Fields', 'quill-accessible-forms' ) => array(
                'rating' => array(
                    'label' => __( 'Star Rating', 'quill-accessible-forms' ),
                    'icon'  => '⭐',
                ),
                'likert' => array(
                    'label' => __( 'Likert Scale', 'quill-accessible-forms' ),
                    'icon'  => '📊',
                ),
                'nps' => array(
                    'label' => __( 'NPS Score', 'quill-accessible-forms' ),
                    'icon'  => '📈',
                ),
                'ranking' => array(
                    'label' => __( 'Ranking', 'quill-accessible-forms' ),
                    'icon'  => '🏆',
                ),
            ),
            __( 'File Fields', 'quill-accessible-forms' ) => array(
                'fileupload' => array(
                    'label' => __( 'File Upload', 'quill-accessible-forms' ),
                    'icon'  => '📎',
                ),
            ),
        );
    }

    /**
     * Get the compatibility matrix for JavaScript
     *
     * @return array Compatibility matrix.
     */
    public function get_compatibility_matrix() {
        return $this->compatibility_matrix;
    }

    /**
     * Get property groups for JavaScript
     *
     * @return array Property groups.
     */
    public function get_property_groups() {
        return $this->property_groups;
    }
}
