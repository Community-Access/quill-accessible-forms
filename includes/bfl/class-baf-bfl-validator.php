<?php
/**
 * BFL Validator
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_BFL_Validator class
 * 
 * Validates BFL source code and form configurations.
 */
class BAF_BFL_Validator {

    /**
     * Validation errors
     *
     * @var array
     */
    private $errors = array();

    /**
     * Validation warnings
     *
     * @var array
     */
    private $warnings = array();

    /**
     * Valid field types
     *
     * @var array
     */
    private $valid_field_types = array(
        'text', 'textarea', 'paragraph', 'number', 'email', 'phone', 'website', 'url',
        'hidden', 'password', 'select', 'dropdown', 'multiselect', 'radio', 'checkbox',
        'checkboxes', 'consent', 'likert', 'ranking', 'rating', 'nps', 'date', 'time',
        'datetime', 'daterange', 'name', 'address', 'file', 'fileupload', 'signature',
        'captcha', 'repeater', 'calculation', 'product', 'quantity', 'price', 'total',
        'coupon', 'html', 'divider', 'spacer', 'submit', 'save', 'savebutton', 'section',
        'columns', 'toggle', 'range', 'color', 'page',
    );

    /**
     * Valid modifiers
     *
     * All modifiers in this list are implemented in BAF_BFL_Parser::parse_field_modifiers()
     *
     * @var array
     */
    private $valid_modifiers = array(
        // Core field properties
        'required', 'placeholder', 'default', 'description', 'desc',
        // Validation constraints
        'min', 'max', 'minlength', 'maxlength', 'pattern', 'unique',
        // Appearance
        'rows', 'cols', 'size', 'class', 'css', 'prefix', 'suffix',
        // State modifiers
        'readonly', 'disabled', 'admin', 'admin-only', 'hidden',
        // Email field
        'confirm',
        // Input masking
        'mask', 'format',
        // Choice fields
        'library', 'multiple', 'allow-other', 'allowother', 'searchable',
        // Numeric fields
        'step',
        // HTML5 attributes
        'autocomplete', 'autofocus', 'spellcheck', 'inputmode', 'enterkeyhint',
        // File upload attributes
        'accept', 'capture',
        // Autocomplete/address providers
        'autocomplete_provider', 'autocomplete_countries', 'autocomplete_types',
        // Smart defaults
        'smart_default', 'unique_scope', 'unique_message',
        // Cascading dropdowns
        'cascade_from', 'cascadefrom', 'cascade_library', 'cascadelibrary',
        'empty_message', 'emptymessage', 'loading_message', 'loadingmessage',
        // Arbitrary custom attributes
        'attr',
        // Repeater settings
        'min_rows', 'minrows', 'max_rows', 'maxrows',
    );

    /**
     * Validate BFL source code
     *
     * @param string $bfl BFL source code.
     * @return array Validation result with errors and warnings.
     */
    public function validate( $bfl ) {
        $this->errors = array();
        $this->warnings = array();

        // Parse the BFL
        $parser = new BAF_BFL_Parser();
        $result = $parser->parse( $bfl );

        // Check for parser errors
        if ( ! empty( $result['errors'] ) ) {
            foreach ( $result['errors'] as $error ) {
                $this->add_error( $error['message'], $error['line'], $error['column'] );
            }
        }

        // Validate the parsed form
        if ( ! empty( $result['form'] ) ) {
            $this->validate_form( $result['form'] );
        }

        return array(
            'valid'    => empty( $this->errors ),
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        );
    }

    /**
     * Validate form configuration
     *
     * @param array $form Form configuration.
     * @return array Validation result.
     */
    public function validate_form( $form ) {
        $this->errors = array();
        $this->warnings = array();

        // Validate form structure
        $this->validate_form_structure( $form );

        // Validate fields
        if ( ! empty( $form['fields'] ) ) {
            $this->validate_fields( $form['fields'] );
        }

        // Validate pages
        if ( ! empty( $form['pages'] ) ) {
            $this->validate_pages( $form['pages'] );
        }

        // Validate conditional logic
        if ( ! empty( $form['conditional_logic'] ) ) {
            $this->validate_conditional_logic( $form['conditional_logic'], $form );
        }

        // Check accessibility requirements
        $this->validate_accessibility( $form );

        return array(
            'valid'    => empty( $this->errors ),
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        );
    }

    /**
     * Validate form structure
     *
     * @param array $form Form configuration.
     */
    private function validate_form_structure( $form ) {
        // Check for title
        if ( empty( $form['title'] ) ) {
            $this->add_warning( __( 'Form has no title.', 'bits-accessible-forms' ) );
        }

        // Check for at least one field
        if ( empty( $form['fields'] ) ) {
            $this->add_error( __( 'Form must have at least one field.', 'bits-accessible-forms' ) );
        }
    }

    /**
     * Validate fields array
     *
     * @param array $fields Array of field configurations.
     */
    private function validate_fields( $fields ) {
        $field_ids = array();
        $has_submit = false;

        foreach ( $fields as $index => $field ) {
            $this->validate_field( $field, $index );

            // Check for duplicate IDs
            if ( ! empty( $field['id'] ) ) {
                if ( in_array( $field['id'], $field_ids, true ) ) {
                    $this->add_error(
                        sprintf(
                            __( 'Duplicate field ID "%s" at field index %d.', 'bits-accessible-forms' ),
                            $field['id'],
                            $index
                        )
                    );
                }
                $field_ids[] = $field['id'];
            }

            // Check for submit button
            if ( isset( $field['type'] ) && $field['type'] === 'submit' ) {
                $has_submit = true;
            }
        }

        // Warning if no submit button
        if ( ! $has_submit ) {
            $this->add_warning( __( 'Form has no submit button field.', 'bits-accessible-forms' ) );
        }
    }

    /**
     * Validate single field
     *
     * @param array $field Field configuration.
     * @param int   $index Field index.
     */
    private function validate_field( $field, $index ) {
        // Check required properties
        if ( empty( $field['id'] ) ) {
            $this->add_error(
                sprintf( __( 'Field at index %d is missing an ID.', 'bits-accessible-forms' ), $index )
            );
        }

        if ( empty( $field['type'] ) ) {
            $this->add_error(
                sprintf( __( 'Field at index %d is missing a type.', 'bits-accessible-forms' ), $index )
            );
            return;
        }

        // Validate field type
        if ( ! in_array( $field['type'], $this->valid_field_types, true ) ) {
            $this->add_error(
                sprintf(
                    __( 'Invalid field type "%s" at index %d.', 'bits-accessible-forms' ),
                    $field['type'],
                    $index
                )
            );
        }

        // Check for label (accessibility requirement)
        if ( empty( $field['label'] ) && ! in_array( $field['type'], array( 'hidden', 'html', 'section', 'submit' ), true ) ) {
            $this->add_warning(
                sprintf(
                    __( 'Field "%s" at index %d is missing a label (accessibility issue).', 'bits-accessible-forms' ),
                    $field['id'] ?? $index,
                    $index
                )
            );
        }

        // Type-specific validation
        $this->validate_field_by_type( $field, $index );
    }

    /**
     * Validate field based on its type
     *
     * @param array $field Field configuration.
     * @param int   $index Field index.
     */
    private function validate_field_by_type( $field, $index ) {
        $type = $field['type'];

        switch ( $type ) {
            case 'select':
            case 'radio':
            case 'checkbox':
            case 'multiselect':
                // Check for choices
                if ( empty( $field['choices'] ) ) {
                    $this->add_warning(
                        sprintf(
                            __( 'Choice field "%s" has no options defined.', 'bits-accessible-forms' ),
                            $field['id'] ?? $index
                        )
                    );
                }
                break;

            case 'number':
            case 'range':
                // Validate min/max
                if ( isset( $field['min'] ) && isset( $field['max'] ) ) {
                    if ( (float) $field['min'] > (float) $field['max'] ) {
                        $this->add_error(
                            sprintf(
                                __( 'Field "%s" has min value greater than max value.', 'bits-accessible-forms' ),
                                $field['id'] ?? $index
                            )
                        );
                    }
                }
                break;

            case 'email':
                // Email validation is built-in
                break;

            case 'fileupload':
                // Check for file type restrictions
                if ( empty( $field['accept'] ) && empty( $field['allowed_types'] ) ) {
                    $this->add_warning(
                        sprintf(
                            __( 'File upload field "%s" has no file type restrictions.', 'bits-accessible-forms' ),
                            $field['id'] ?? $index
                        )
                    );
                }
                break;

            case 'calculation':
                // Check for formula
                if ( empty( $field['formula'] ) ) {
                    $this->add_error(
                        sprintf(
                            __( 'Calculation field "%s" is missing a formula.', 'bits-accessible-forms' ),
                            $field['id'] ?? $index
                        )
                    );
                }
                break;

            case 'repeater':
                // Check for nested fields
                if ( empty( $field['fields'] ) && empty( $field['children'] ) ) {
                    $this->add_error(
                        sprintf(
                            __( 'Repeater field "%s" has no nested fields.', 'bits-accessible-forms' ),
                            $field['id'] ?? $index
                        )
                    );
                }
                break;

            case 'likert':
                // Check for rows and columns
                if ( empty( $field['rows'] ) || empty( $field['columns'] ) ) {
                    $this->add_warning(
                        sprintf(
                            __( 'Likert field "%s" may be missing rows or columns.', 'bits-accessible-forms' ),
                            $field['id'] ?? $index
                        )
                    );
                }
                break;
        }
    }

    /**
     * Validate pages
     *
     * @param array $pages Array of page configurations.
     */
    private function validate_pages( $pages ) {
        $page_ids = array();

        foreach ( $pages as $index => $page ) {
            // Check for duplicate page IDs
            if ( ! empty( $page['id'] ) ) {
                if ( in_array( $page['id'], $page_ids, true ) ) {
                    $this->add_error(
                        sprintf(
                            __( 'Duplicate page ID "%s" at page index %d.', 'bits-accessible-forms' ),
                            $page['id'],
                            $index
                        )
                    );
                }
                $page_ids[] = $page['id'];
            }

            // Check for title (accessibility)
            if ( empty( $page['title'] ) && count( $pages ) > 1 ) {
                $this->add_warning(
                    sprintf(
                        __( 'Page at index %d is missing a title (accessibility issue for multi-page forms).', 'bits-accessible-forms' ),
                        $index
                    )
                );
            }
        }
    }

    /**
     * Validate conditional logic
     *
     * @param array $logic Conditional logic configuration.
     * @param array $form  Form configuration for field reference.
     */
    private function validate_conditional_logic( $logic, $form ) {
        $field_ids = array();
        if ( ! empty( $form['fields'] ) ) {
            foreach ( $form['fields'] as $field ) {
                if ( ! empty( $field['id'] ) ) {
                    $field_ids[] = $field['id'];
                }
            }
        }

        foreach ( $logic as $rule ) {
            // Check that referenced fields exist
            if ( ! empty( $rule['field'] ) && ! in_array( $rule['field'], $field_ids, true ) ) {
                $this->add_warning(
                    sprintf(
                        __( 'Conditional logic references non-existent field "%s".', 'bits-accessible-forms' ),
                        $rule['field']
                    )
                );
            }

            // Validate operator
            $valid_operators = array(
                'equals', 'not_equals', 'contains', 'not_contains',
                'starts_with', 'ends_with', 'greater_than', 'less_than',
                'greater_equal', 'less_equal', 'is_empty', 'is_not_empty',
            );

            if ( ! empty( $rule['operator'] ) && ! in_array( $rule['operator'], $valid_operators, true ) ) {
                $this->add_warning(
                    sprintf(
                        __( 'Invalid conditional logic operator "%s".', 'bits-accessible-forms' ),
                        $rule['operator']
                    )
                );
            }
        }
    }

    /**
     * Validate accessibility requirements
     *
     * @param array $form Form configuration.
     */
    private function validate_accessibility( $form ) {
        if ( empty( $form['fields'] ) ) {
            return;
        }

        foreach ( $form['fields'] as $field ) {
            // Skip non-input fields
            if ( in_array( $field['type'] ?? '', array( 'html', 'section', 'submit', 'hidden' ), true ) ) {
                continue;
            }

            // Check for label
            if ( empty( $field['label'] ) && empty( $field['aria_label'] ) ) {
                $this->add_warning(
                    sprintf(
                        __( 'Field "%s" has no accessible label.', 'bits-accessible-forms' ),
                        $field['id'] ?? 'unknown'
                    ),
                    0,
                    0,
                    'accessibility'
                );
            }

            // Check radio/checkbox groups have fieldset
            if ( in_array( $field['type'] ?? '', array( 'radio', 'checkbox' ), true ) ) {
                if ( ! empty( $field['choices'] ) && count( $field['choices'] ) > 1 ) {
                    // This will be rendered with fieldset/legend automatically
                    // Just note it for verification
                }
            }
        }

        // Check overall form accessibility
        if ( empty( $form['title'] ) ) {
            $this->add_warning(
                __( 'Form lacks a title for screen reader users.', 'bits-accessible-forms' ),
                0,
                0,
                'accessibility'
            );
        }
    }

    /**
     * Validate field ID format
     *
     * @param string $id Field ID.
     * @return bool True if valid.
     */
    public function is_valid_field_id( $id ) {
        // Allow alphanumeric, underscores, hyphens
        return (bool) preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id );
    }

    /**
     * Validate modifier name
     *
     * @param string $modifier Modifier name.
     * @return bool True if valid.
     */
    public function is_valid_modifier( $modifier ) {
        return in_array( strtolower( $modifier ), $this->valid_modifiers, true );
    }

    /**
     * Add error
     *
     * @param string $message  Error message.
     * @param int    $line     Line number (optional).
     * @param int    $column   Column number (optional).
     * @param string $category Error category (optional).
     */
    private function add_error( $message, $line = 0, $column = 0, $category = 'general' ) {
        $this->errors[] = array(
            'message'  => $message,
            'line'     => $line,
            'column'   => $column,
            'category' => $category,
        );
    }

    /**
     * Add warning
     *
     * @param string $message  Warning message.
     * @param int    $line     Line number (optional).
     * @param int    $column   Column number (optional).
     * @param string $category Warning category (optional).
     */
    private function add_warning( $message, $line = 0, $column = 0, $category = 'general' ) {
        $this->warnings[] = array(
            'message'  => $message,
            'line'     => $line,
            'column'   => $column,
            'category' => $category,
        );
    }

    /**
     * Get all valid field types
     *
     * @return array Valid field types.
     */
    public function get_valid_field_types() {
        return $this->valid_field_types;
    }

    /**
     * Get all valid modifiers
     *
     * @return array Valid modifiers.
     */
    public function get_valid_modifiers() {
        return $this->valid_modifiers;
    }
}
