<?php
/**
 * BFL Exporter
 *
 * Exports form configuration to BFL (BITS Form Language) format.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_BFL_Exporter class
 */
class BAF_BFL_Exporter {

    /**
     * Default indentation
     *
     * @var string
     */
    private $indent = '    ';

    /**
     * Current indentation level
     *
     * @var int
     */
    private $level = 0;

    /**
     * Export options
     *
     * @var array
     */
    private $options = array();

    /**
     * Constructor
     *
     * @param array $options Export options.
     */
    public function __construct( $options = array() ) {
        $this->options = wp_parse_args( $options, array(
            'indent'            => '    ',
            'include_comments'  => true,
            'include_ids'       => false,
            'block_style'       => 'indent', // 'indent', 'curly', 'begin'
            'choice_style'      => 'dash',   // 'dash', 'bracket', 'inline'
            'multiline_options' => true,
        ) );

        $this->indent = $this->options['indent'];
    }

    /**
     * Export form configuration to BFL
     *
     * @param array $form_config Form configuration array.
     * @return string BFL code.
     */
    public function export( $form_config ) {
        $bfl = '';

        // Form title as comment
        if ( ! empty( $form_config['title'] ) && $this->options['include_comments'] ) {
            $bfl .= '# ' . $form_config['title'] . "\n";
        }

        // Form description as comment
        if ( ! empty( $form_config['description'] ) && $this->options['include_comments'] ) {
            $bfl .= '# ' . $form_config['description'] . "\n";
        }

        if ( $bfl ) {
            $bfl .= "\n";
        }

        // Export fields
        $fields = $form_config['fields'] ?? array();
        $bfl .= $this->export_fields( $fields );

        return trim( $bfl );
    }

    /**
     * Export array of fields
     *
     * @param array $fields Fields array.
     * @return string BFL code.
     */
    private function export_fields( $fields ) {
        $bfl = '';

        foreach ( $fields as $field ) {
            $bfl .= $this->export_field( $field );
        }

        return $bfl;
    }

    /**
     * Export single field
     *
     * @param array $field Field configuration.
     * @return string BFL code.
     */
    private function export_field( $field ) {
        $type = $field['type'] ?? 'text';

        // Handle structural elements
        switch ( $type ) {
            case 'page':
                return $this->export_page( $field );
            case 'section':
                return $this->export_section( $field );
            case 'columns':
                return $this->export_columns( $field );
            case 'repeater':
                return $this->export_repeater( $field );
            default:
                return $this->export_input_field( $field );
        }
    }

    /**
     * Export page
     *
     * @param array $field Page configuration.
     * @return string BFL code.
     */
    private function export_page( $field ) {
        $bfl = $this->get_indent() . '=== ' . ( $field['label'] ?? __( 'Page', 'bits-accessible-forms' ) );

        if ( ! empty( $field['description'] ) ) {
            $bfl .= ' @help(' . $this->escape_value( $field['description'] ) . ')';
        }

        $bfl .= " ===\n";

        // Export page content
        if ( ! empty( $field['fields'] ) ) {
            $this->level++;
            $bfl .= $this->export_fields( $field['fields'] );
            $this->level--;
        }

        $bfl .= "\n";

        return $bfl;
    }

    /**
     * Export section
     *
     * @param array $field Section configuration.
     * @return string BFL code.
     */
    private function export_section( $field ) {
        $bfl = $this->get_indent() . '## ' . ( $field['label'] ?? __( 'Section', 'bits-accessible-forms' ) );

        if ( ! empty( $field['description'] ) ) {
            $bfl .= ' @help(' . $this->escape_value( $field['description'] ) . ')';
        }

        $bfl .= "\n";

        // Export section content
        if ( ! empty( $field['fields'] ) ) {
            $this->level++;
            $bfl .= $this->export_fields( $field['fields'] );
            $this->level--;
        }

        $bfl .= "\n";

        return $bfl;
    }

    /**
     * Export columns
     *
     * @param array $field Columns configuration.
     * @return string BFL code.
     */
    private function export_columns( $field ) {
        $columns = $field['columns'] ?? array();
        $widths = array();

        foreach ( $columns as $col ) {
            $widths[] = $col['width'] ?? '1/2';
        }

        $bfl = $this->get_indent() . '[columns ' . implode( ' | ', $widths ) . "]\n";

        foreach ( $columns as $index => $col ) {
            if ( $index > 0 ) {
                $bfl .= $this->get_indent() . "---\n";
            }

            if ( ! empty( $col['fields'] ) ) {
                $this->level++;
                $bfl .= $this->export_fields( $col['fields'] );
                $this->level--;
            }
        }

        $bfl .= $this->get_indent() . "[/columns]\n\n";

        return $bfl;
    }

    /**
     * Export repeater
     *
     * @param array $field Repeater configuration.
     * @return string BFL code.
     */
    private function export_repeater( $field ) {
        $id = $this->get_field_id( $field );
        $label = $field['label'] ?? '';

        $bfl = $this->get_indent() . $id . ': repeater';

        // Modifiers
        if ( ! empty( $field['min_rows'] ) && $field['min_rows'] > 0 ) {
            $bfl .= ' @min_rows(' . (int) $field['min_rows'] . ')';
        }
        if ( ! empty( $field['max_rows'] ) ) {
            $bfl .= ' @max_rows(' . (int) $field['max_rows'] . ')';
        }

        if ( $label ) {
            $bfl .= ' "' . $this->escape_value( $label ) . '"';
        }

        $bfl .= "\n";

        // Repeater fields
        if ( ! empty( $field['fields'] ) ) {
            $this->level++;
            $bfl .= $this->export_fields( $field['fields'] );
            $this->level--;
        }

        $bfl .= "\n";

        return $bfl;
    }

    /**
     * Export input field
     *
     * @param array $field Field configuration.
     * @return string BFL code.
     */
    private function export_input_field( $field ) {
        $id = $this->get_field_id( $field );
        $type = $this->normalize_type( $field['type'] ?? 'text' );
        $label = $field['label'] ?? '';

        $bfl = $this->get_indent() . $id . ': ' . $type;

        // Add modifiers
        $bfl .= $this->export_modifiers( $field );

        // Add label
        if ( $label ) {
            $bfl .= ' "' . $this->escape_value( $label ) . '"';
        }

        $bfl .= "\n";

        // Add options for choice fields
        if ( $this->is_choice_field( $type ) && ! empty( $field['options'] ) ) {
            $bfl .= $this->export_options( $field['options'] );
        }

        // Add conditional logic
        if ( ! empty( $field['conditional'] ) ) {
            $bfl .= $this->export_conditional( $field['conditional'] );
        }

        return $bfl;
    }

    /**
     * Export field modifiers
     *
     * @param array $field Field configuration.
     * @return string Modifier string.
     */
    private function export_modifiers( $field ) {
        $modifiers = '';

        // Required
        if ( ! empty( $field['required'] ) ) {
            $modifiers .= ' @required';
        }

        // Placeholder
        if ( ! empty( $field['placeholder'] ) ) {
            $modifiers .= ' @placeholder(' . $this->escape_value( $field['placeholder'] ) . ')';
        }

        // Help text
        if ( ! empty( $field['help_text'] ) ) {
            $modifiers .= ' @help(' . $this->escape_value( $field['help_text'] ) . ')';
        }

        // Default value
        if ( isset( $field['default'] ) && $field['default'] !== '' ) {
            $modifiers .= ' @default(' . $this->escape_value( $field['default'] ) . ')';
        }

        // Validation
        if ( ! empty( $field['min'] ) ) {
            $modifiers .= ' @min(' . $field['min'] . ')';
        }
        if ( ! empty( $field['max'] ) ) {
            $modifiers .= ' @max(' . $field['max'] . ')';
        }
        if ( ! empty( $field['minlength'] ) ) {
            $modifiers .= ' @minlength(' . $field['minlength'] . ')';
        }
        if ( ! empty( $field['maxlength'] ) ) {
            $modifiers .= ' @maxlength(' . $field['maxlength'] . ')';
        }
        if ( ! empty( $field['pattern'] ) ) {
            $modifiers .= ' @pattern(' . $this->escape_value( $field['pattern'] ) . ')';
        }

        // Autocomplete
        if ( ! empty( $field['autocomplete'] ) ) {
            $modifiers .= ' @autocomplete(' . $field['autocomplete'] . ')';
        }

        // Input mode
        if ( ! empty( $field['inputmode'] ) ) {
            $modifiers .= ' @inputmode(' . $field['inputmode'] . ')';
        }

        // Rows (textarea)
        if ( ! empty( $field['rows'] ) ) {
            $modifiers .= ' @rows(' . (int) $field['rows'] . ')';
        }

        // Columns (textarea)
        if ( ! empty( $field['cols'] ) ) {
            $modifiers .= ' @cols(' . (int) $field['cols'] . ')';
        }

        // Step (number, range)
        if ( isset( $field['step'] ) && $field['step'] !== '' ) {
            $modifiers .= ' @step(' . $field['step'] . ')';
        }

        // Accept (file)
        if ( ! empty( $field['accept'] ) ) {
            $modifiers .= ' @accept(' . $field['accept'] . ')';
        }

        // Capture (file)
        if ( ! empty( $field['capture'] ) ) {
            $modifiers .= ' @capture(' . $field['capture'] . ')';
        }

        // Multiple
        if ( ! empty( $field['multiple'] ) ) {
            $modifiers .= ' @multiple';
        }

        // Disabled
        if ( ! empty( $field['disabled'] ) ) {
            $modifiers .= ' @disabled';
        }

        // Readonly
        if ( ! empty( $field['readonly'] ) ) {
            $modifiers .= ' @readonly';
        }

        // Autofocus
        if ( ! empty( $field['autofocus'] ) ) {
            $modifiers .= ' @autofocus';
        }

        // Spellcheck
        if ( isset( $field['spellcheck'] ) ) {
            $modifiers .= ' @spellcheck(' . ( $field['spellcheck'] ? 'true' : 'false' ) . ')';
        }

        // Searchable (select)
        if ( ! empty( $field['searchable'] ) ) {
            $modifiers .= ' @searchable';
        }

        // Width
        if ( ! empty( $field['width'] ) && $field['width'] !== 'full' ) {
            $modifiers .= ' @width(' . $field['width'] . ')';
        }

        // Custom attributes
        if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ) {
            foreach ( $field['custom_attributes'] as $attr => $value ) {
                $modifiers .= ' @attr(' . $attr . '=' . $this->escape_value( $value ) . ')';
            }
        }

        return $modifiers;
    }

    /**
     * Export field options
     *
     * @param array $options Options array.
     * @return string BFL options.
     */
    private function export_options( $options ) {
        $bfl = '';
        $style = $this->options['choice_style'];

        if ( $style === 'inline' && count( $options ) <= 3 ) {
            // Inline style for few options
            $opts = array();
            foreach ( $options as $value => $label ) {
                if ( $value === $label || is_numeric( $value ) ) {
                    $opts[] = $label;
                } else {
                    $opts[] = $value . '=' . $label;
                }
            }
            $bfl .= $this->get_indent() . '  [' . implode( ', ', $opts ) . "]\n";
        } else {
            // Multiline style
            foreach ( $options as $value => $label ) {
                $bfl .= $this->get_indent() . '  - ';

                if ( is_array( $label ) && isset( $label['label'] ) ) {
                    // Optgroup or complex option
                    if ( isset( $label['options'] ) ) {
                        // Optgroup
                        $bfl .= '[' . $label['label'] . "]\n";
                        foreach ( $label['options'] as $opt_val => $opt_label ) {
                            $bfl .= $this->get_indent() . '    - ' . $this->format_option( $opt_val, $opt_label ) . "\n";
                        }
                        continue;
                    }
                    $label = $label['label'];
                }

                $bfl .= $this->format_option( $value, $label ) . "\n";
            }
        }

        return $bfl;
    }

    /**
     * Format a single option
     *
     * @param string|int $value Option value.
     * @param string     $label Option label.
     * @return string Formatted option.
     */
    private function format_option( $value, $label ) {
        // If value equals label or is numeric index, just use label
        if ( $value === $label || ( is_numeric( $value ) && (int) $value === array_search( $label, array_values( $this->current_options ?? array() ) ) ) ) {
            return $label;
        }

        return $value . '=' . $label;
    }

    /**
     * Export conditional logic
     *
     * @param array $conditional Conditional configuration.
     * @return string BFL conditional.
     */
    private function export_conditional( $conditional ) {
        $bfl = '';

        if ( empty( $conditional['rules'] ) ) {
            return $bfl;
        }

        $rules = $conditional['rules'];
        $logic = $conditional['logic'] ?? 'and';

        $bfl .= $this->get_indent() . '  @if ';

        $conditions = array();
        foreach ( $rules as $rule ) {
            $conditions[] = $this->format_condition( $rule );
        }

        if ( $logic === 'or' ) {
            $bfl .= implode( ' OR ', $conditions );
        } else {
            $bfl .= implode( ' AND ', $conditions );
        }

        $bfl .= "\n";

        return $bfl;
    }

    /**
     * Format a single condition
     *
     * @param array $rule Condition rule.
     * @return string Formatted condition.
     */
    private function format_condition( $rule ) {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? '';

        $op_map = array(
            'equals'          => '=',
            'not_equals'      => '!=',
            'contains'        => '~=',
            'greater_than'    => '>',
            'less_than'       => '<',
            'greater_or_equal' => '>=',
            'less_or_equal'   => '<=',
            'is_empty'        => 'empty',
            'is_not_empty'    => 'not_empty',
        );

        $op = $op_map[ $operator ] ?? '=';

        if ( in_array( $operator, array( 'is_empty', 'is_not_empty' ), true ) ) {
            return $field . ' ' . $op;
        }

        return $field . ' ' . $op . ' "' . $this->escape_value( $value ) . '"';
    }

    /**
     * Get field ID
     *
     * @param array $field Field configuration.
     * @return string Field ID.
     */
    private function get_field_id( $field ) {
        if ( $this->options['include_ids'] && ! empty( $field['id'] ) ) {
            return $field['id'];
        }

        // Generate from label
        if ( ! empty( $field['label'] ) ) {
            return $this->slugify( $field['label'] );
        }

        return 'field_' . wp_generate_uuid4();
    }

    /**
     * Normalize field type to BFL type
     *
     * @param string $type Field type.
     * @return string BFL type.
     */
    private function normalize_type( $type ) {
        $map = array(
            'text'           => 'text',
            'email'          => 'email',
            'tel'            => 'tel',
            'url'            => 'url',
            'number'         => 'number',
            'password'       => 'password',
            'textarea'       => 'textarea',
            'select'         => 'select',
            'radio'          => 'radio',
            'checkbox'       => 'checkbox',
            'date'           => 'date',
            'time'           => 'time',
            'datetime-local' => 'datetime',
            'file'           => 'file',
            'hidden'         => 'hidden',
            'range'          => 'slider',
            'slider'         => 'slider',
            'rating'         => 'rating',
            'currency'       => 'currency',
            'otp'            => 'otp',
            'typed_signature' => 'signature',
        );

        return $map[ $type ] ?? $type;
    }

    /**
     * Check if field is a choice field
     *
     * @param string $type Field type.
     * @return bool Is choice field.
     */
    private function is_choice_field( $type ) {
        return in_array( $type, array( 'select', 'radio', 'checkbox' ), true );
    }

    /**
     * Get current indentation
     *
     * @return string Indentation string.
     */
    private function get_indent() {
        return str_repeat( $this->indent, $this->level );
    }

    /**
     * Escape a value for BFL
     *
     * @param string $value Value to escape.
     * @return string Escaped value.
     */
    private function escape_value( $value ) {
        // Escape parentheses and quotes
        $value = str_replace( array( '(', ')', '"' ), array( '\\(', '\\)', '\\"' ), $value );
        return $value;
    }

    /**
     * Convert string to slug
     *
     * @param string $text Text to slugify.
     * @return string Slugified text.
     */
    private function slugify( $text ) {
        $text = strtolower( $text );
        $text = preg_replace( '/[^a-z0-9]+/', '_', $text );
        $text = trim( $text, '_' );
        return $text;
    }
}
