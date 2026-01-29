<?php
/**
 * Accessibility Coach
 *
 * Provides real-time accessibility guidance in the form builder.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Accessibility_Coach class
 */
class BAF_Accessibility_Coach {

    /**
     * Issue severity levels
     */
    const SEVERITY_ERROR   = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO    = 'info';

    /**
     * Issue categories
     */
    const CATEGORY_LABELS      = 'labels';
    const CATEGORY_HELP_TEXT   = 'help_text';
    const CATEGORY_STRUCTURE   = 'structure';
    const CATEGORY_KEYBOARD    = 'keyboard';
    const CATEGORY_VALIDATION  = 'validation';
    const CATEGORY_CONTRAST    = 'contrast';

    /**
     * Analyze a form configuration for accessibility issues
     *
     * @param array $form_config Form configuration.
     * @return array Analysis results.
     */
    public function analyze( $form_config ) {
        $issues = array();
        $fields = $form_config['fields'] ?? array();
        $pages  = $form_config['pages'] ?? array();

        // Analyze each field
        foreach ( $fields as $index => $field ) {
            $field_issues = $this->analyze_field( $field, $index, $fields );
            $issues = array_merge( $issues, $field_issues );
        }

        // Analyze form structure
        $issues = array_merge( $issues, $this->analyze_form_structure( $form_config ) );

        // Analyze page structure
        if ( ! empty( $pages ) ) {
            $issues = array_merge( $issues, $this->analyze_pages( $pages ) );
        }

        // Sort by severity
        usort( $issues, function( $a, $b ) {
            $order = array( self::SEVERITY_ERROR => 0, self::SEVERITY_WARNING => 1, self::SEVERITY_INFO => 2 );
            return ( $order[ $a['severity'] ] ?? 99 ) <=> ( $order[ $b['severity'] ] ?? 99 );
        } );

        return array(
            'issues'     => $issues,
            'summary'    => $this->generate_summary( $issues ),
            'score'      => $this->calculate_score( $issues ),
            'categories' => $this->group_by_category( $issues ),
        );
    }

    /**
     * Analyze a single field
     *
     * @param array $field       Field configuration.
     * @param int   $index       Field index.
     * @param array $all_fields  All fields.
     * @return array Field issues.
     */
    public function analyze_field( $field, $index, $all_fields = array() ) {
        $issues = array();
        $field_id = $field['id'] ?? 'field_' . $index;
        $field_type = $field['type'] ?? 'unknown';
        $field_label = $field['label'] ?? '';

        // Check for missing label
        if ( empty( $field_label ) ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_ERROR,
                self::CATEGORY_LABELS,
                $field_id,
                __( 'Missing field label', 'bits-accessible-forms' ),
                __( 'Every form field must have a visible label. Screen reader users cannot identify unlabeled fields.', 'bits-accessible-forms' ),
                __( 'Add a descriptive label that explains what information this field collects.', 'bits-accessible-forms' )
            );
        }

        // Check for generic/unclear labels
        if ( ! empty( $field_label ) && $this->is_generic_label( $field_label ) ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_WARNING,
                self::CATEGORY_LABELS,
                $field_id,
                __( 'Generic field label', 'bits-accessible-forms' ),
                sprintf(
                    /* translators: %s: label text */
                    __( 'The label "%s" is generic and may not clearly describe the expected input.', 'bits-accessible-forms' ),
                    $field_label
                ),
                __( 'Use a specific, descriptive label like "First Name" instead of "Name" or "Enter information" instead of "Input".', 'bits-accessible-forms' )
            );
        }

        // Check label length
        if ( ! empty( $field_label ) && strlen( $field_label ) < 3 ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_WARNING,
                self::CATEGORY_LABELS,
                $field_id,
                __( 'Label too short', 'bits-accessible-forms' ),
                __( 'Very short labels may not provide enough context for users to understand what information is needed.', 'bits-accessible-forms' ),
                __( 'Consider using a more descriptive label.', 'bits-accessible-forms' )
            );
        }

        // Check for help text on complex fields
        if ( $this->is_complex_field( $field_type ) && empty( $field['description'] ) && empty( $field['help_text'] ) ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_INFO,
                self::CATEGORY_HELP_TEXT,
                $field_id,
                __( 'Consider adding help text', 'bits-accessible-forms' ),
                __( 'Complex fields benefit from additional instructions or examples.', 'bits-accessible-forms' ),
                __( 'Add a description or help text to guide users on how to complete this field.', 'bits-accessible-forms' )
            );
        }

        // Check for required field without indication
        if ( ! empty( $field['required'] ) && empty( $field['required_indicator'] ) ) {
            // Note: This is usually handled by global settings, so just an info
            $issues[] = $this->create_issue(
                self::SEVERITY_INFO,
                self::CATEGORY_VALIDATION,
                $field_id,
                __( 'Required field indicator', 'bits-accessible-forms' ),
                __( 'Required fields should be clearly indicated both visually and for screen readers.', 'bits-accessible-forms' ),
                __( 'BAF automatically adds required indicators. Ensure your form settings have "Show required indicator" enabled.', 'bits-accessible-forms' )
            );
        }

        // Check placeholder-only labels (anti-pattern)
        if ( empty( $field_label ) && ! empty( $field['placeholder'] ) ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_ERROR,
                self::CATEGORY_LABELS,
                $field_id,
                __( 'Placeholder used as label', 'bits-accessible-forms' ),
                __( 'Using only a placeholder instead of a label is an accessibility violation. Placeholders disappear when typing and are not announced as labels by screen readers.', 'bits-accessible-forms' ),
                __( 'Add a proper visible label. The placeholder can provide additional hints but should not replace the label.', 'bits-accessible-forms' )
            );
        }

        // Check for validation message
        if ( ! empty( $field['pattern'] ) && empty( $field['validation_message'] ) ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_WARNING,
                self::CATEGORY_VALIDATION,
                $field_id,
                __( 'Missing validation message', 'bits-accessible-forms' ),
                __( 'Fields with pattern validation should have a custom error message explaining the expected format.', 'bits-accessible-forms' ),
                __( 'Add a validation message that explains what format is expected.', 'bits-accessible-forms' )
            );
        }

        // Check choice fields for sufficient options
        if ( $this->is_choice_field( $field_type ) ) {
            $choices = $field['choices'] ?? array();
            if ( count( $choices ) < 2 ) {
                $issues[] = $this->create_issue(
                    self::SEVERITY_WARNING,
                    self::CATEGORY_STRUCTURE,
                    $field_id,
                    __( 'Insufficient choice options', 'bits-accessible-forms' ),
                    __( 'Choice fields should have at least 2 options for users to choose from.', 'bits-accessible-forms' ),
                    __( 'Add more options or consider using a different field type.', 'bits-accessible-forms' )
                );
            }
        }

        // Check for radio/checkbox groups without fieldset
        if ( in_array( $field_type, array( 'radio', 'checkbox' ), true ) && count( $field['choices'] ?? array() ) > 1 ) {
            // BAF wraps these in fieldset/legend by default, but check if configured to hide
            if ( ! empty( $field['hide_legend'] ) ) {
                $issues[] = $this->create_issue(
                    self::SEVERITY_ERROR,
                    self::CATEGORY_STRUCTURE,
                    $field_id,
                    __( 'Grouped controls need legend', 'bits-accessible-forms' ),
                    __( 'Radio and checkbox groups must be wrapped in a fieldset with a legend for screen reader users to understand the relationship.', 'bits-accessible-forms' ),
                    __( 'Remove the "hide legend" setting or ensure the group has proper ARIA labeling.', 'bits-accessible-forms' )
                );
            }
        }

        // Nested fields (columns, sections)
        if ( ! empty( $field['fields'] ) ) {
            foreach ( $field['fields'] as $nested_index => $nested_field ) {
                $nested_issues = $this->analyze_field( $nested_field, $index . '_' . $nested_index, $all_fields );
                $issues = array_merge( $issues, $nested_issues );
            }
        }

        return $issues;
    }

    /**
     * Analyze form structure
     *
     * @param array $form_config Form configuration.
     * @return array Structure issues.
     */
    private function analyze_form_structure( $form_config ) {
        $issues = array();
        $fields = $form_config['fields'] ?? array();

        // Check for form title
        if ( empty( $form_config['title'] ) ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_WARNING,
                self::CATEGORY_STRUCTURE,
                'form',
                __( 'Missing form title', 'bits-accessible-forms' ),
                __( 'Forms should have a clear title to help users understand the purpose.', 'bits-accessible-forms' ),
                __( 'Add a descriptive form title.', 'bits-accessible-forms' )
            );
        }

        // Check for too many fields without sections
        $flat_field_count = $this->count_input_fields( $fields );
        $has_sections = $this->has_sections( $fields );

        if ( $flat_field_count > 10 && ! $has_sections ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_INFO,
                self::CATEGORY_STRUCTURE,
                'form',
                __( 'Consider organizing into sections', 'bits-accessible-forms' ),
                sprintf(
                    /* translators: %d: number of fields */
                    __( 'This form has %d fields. Long forms are easier to complete when organized into logical sections.', 'bits-accessible-forms' ),
                    $flat_field_count
                ),
                __( 'Group related fields into sections with clear headings.', 'bits-accessible-forms' )
            );
        }

        // Check for submit button
        $has_submit = $this->has_submit_button( $fields );
        if ( ! $has_submit ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_INFO,
                self::CATEGORY_STRUCTURE,
                'form',
                __( 'No submit button found', 'bits-accessible-forms' ),
                __( 'Forms typically need a submit button for users to send their responses.', 'bits-accessible-forms' ),
                __( 'Add a submit button or verify one is configured in form settings.', 'bits-accessible-forms' )
            );
        }

        // Check for duplicate field IDs
        $ids = $this->collect_field_ids( $fields );
        $duplicates = array_filter( array_count_values( $ids ), function( $count ) {
            return $count > 1;
        } );

        foreach ( array_keys( $duplicates ) as $dup_id ) {
            $issues[] = $this->create_issue(
                self::SEVERITY_ERROR,
                self::CATEGORY_STRUCTURE,
                $dup_id,
                __( 'Duplicate field ID', 'bits-accessible-forms' ),
                sprintf(
                    /* translators: %s: field ID */
                    __( 'The field ID "%s" is used multiple times. Each field must have a unique ID.', 'bits-accessible-forms' ),
                    $dup_id
                ),
                __( 'Rename one of the fields to use a unique ID.', 'bits-accessible-forms' )
            );
        }

        return $issues;
    }

    /**
     * Analyze page structure for multi-page forms
     *
     * @param array $pages Pages configuration.
     * @return array Page issues.
     */
    private function analyze_pages( $pages ) {
        $issues = array();

        foreach ( $pages as $index => $page ) {
            $page_id = $page['id'] ?? 'page_' . ( $index + 1 );

            // Check for page title
            if ( empty( $page['title'] ) ) {
                $issues[] = $this->create_issue(
                    self::SEVERITY_WARNING,
                    self::CATEGORY_STRUCTURE,
                    $page_id,
                    __( 'Missing page title', 'bits-accessible-forms' ),
                    sprintf(
                        /* translators: %d: page number */
                        __( 'Page %d has no title. Page titles help users understand progress in multi-page forms.', 'bits-accessible-forms' ),
                        $index + 1
                    ),
                    __( 'Add a descriptive title for this page.', 'bits-accessible-forms' )
                );
            }
        }

        return $issues;
    }

    /**
     * Create an issue array
     *
     * @param string $severity   Severity level.
     * @param string $category   Issue category.
     * @param string $field_id   Related field ID.
     * @param string $title      Issue title.
     * @param string $message    Detailed message.
     * @param string $suggestion Fix suggestion.
     * @return array Issue data.
     */
    private function create_issue( $severity, $category, $field_id, $title, $message, $suggestion = '' ) {
        return array(
            'severity'   => $severity,
            'category'   => $category,
            'field_id'   => $field_id,
            'title'      => $title,
            'message'    => $message,
            'suggestion' => $suggestion,
        );
    }

    /**
     * Check if a label is generic
     *
     * @param string $label Label text.
     * @return bool True if generic.
     */
    private function is_generic_label( $label ) {
        $generic = array(
            'input', 'field', 'enter', 'text', 'data', 'info', 'information',
            'value', 'item', 'here', 'name', 'your name', 'your info',
        );

        $label_lower = strtolower( trim( $label ) );
        return in_array( $label_lower, $generic, true );
    }

    /**
     * Check if a field type is complex
     *
     * @param string $type Field type.
     * @return bool True if complex.
     */
    private function is_complex_field( $type ) {
        return in_array( $type, array(
            'fileupload', 'date', 'datetime', 'time', 'daterange',
            'repeater', 'address', 'phone', 'password', 'likert',
            'ranking', 'signature', 'calculation',
        ), true );
    }

    /**
     * Check if a field type is a choice field
     *
     * @param string $type Field type.
     * @return bool True if choice field.
     */
    private function is_choice_field( $type ) {
        return in_array( $type, array(
            'select', 'multiselect', 'radio', 'checkbox',
            'likert', 'ranking', 'rating',
        ), true );
    }

    /**
     * Count input fields (excluding structural)
     *
     * @param array $fields Fields array.
     * @return int Count.
     */
    private function count_input_fields( $fields ) {
        $count = 0;
        $structural = array( 'page', 'section', 'columns', 'html', 'submit' );

        foreach ( $fields as $field ) {
            $type = $field['type'] ?? '';
            if ( ! in_array( $type, $structural, true ) ) {
                $count++;
            }
            if ( ! empty( $field['fields'] ) ) {
                $count += $this->count_input_fields( $field['fields'] );
            }
        }

        return $count;
    }

    /**
     * Check if form has sections
     *
     * @param array $fields Fields array.
     * @return bool True if has sections.
     */
    private function has_sections( $fields ) {
        foreach ( $fields as $field ) {
            if ( ( $field['type'] ?? '' ) === 'section' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if form has submit button
     *
     * @param array $fields Fields array.
     * @return bool True if has submit.
     */
    private function has_submit_button( $fields ) {
        foreach ( $fields as $field ) {
            if ( ( $field['type'] ?? '' ) === 'submit' ) {
                return true;
            }
            if ( ! empty( $field['fields'] ) && $this->has_submit_button( $field['fields'] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collect all field IDs
     *
     * @param array $fields Fields array.
     * @return array Field IDs.
     */
    private function collect_field_ids( $fields ) {
        $ids = array();

        foreach ( $fields as $field ) {
            if ( ! empty( $field['id'] ) ) {
                $ids[] = $field['id'];
            }
            if ( ! empty( $field['fields'] ) ) {
                $ids = array_merge( $ids, $this->collect_field_ids( $field['fields'] ) );
            }
        }

        return $ids;
    }

    /**
     * Generate summary of issues
     *
     * @param array $issues Issues array.
     * @return array Summary.
     */
    private function generate_summary( $issues ) {
        $summary = array(
            'total'    => count( $issues ),
            'errors'   => 0,
            'warnings' => 0,
            'info'     => 0,
        );

        foreach ( $issues as $issue ) {
            switch ( $issue['severity'] ) {
                case self::SEVERITY_ERROR:
                    $summary['errors']++;
                    break;
                case self::SEVERITY_WARNING:
                    $summary['warnings']++;
                    break;
                case self::SEVERITY_INFO:
                    $summary['info']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * Calculate accessibility score
     *
     * @param array $issues Issues array.
     * @return int Score 0-100.
     */
    private function calculate_score( $issues ) {
        if ( empty( $issues ) ) {
            return 100;
        }

        $penalty = 0;
        foreach ( $issues as $issue ) {
            switch ( $issue['severity'] ) {
                case self::SEVERITY_ERROR:
                    $penalty += 15;
                    break;
                case self::SEVERITY_WARNING:
                    $penalty += 5;
                    break;
                case self::SEVERITY_INFO:
                    $penalty += 1;
                    break;
            }
        }

        return max( 0, 100 - $penalty );
    }

    /**
     * Group issues by category
     *
     * @param array $issues Issues array.
     * @return array Grouped issues.
     */
    private function group_by_category( $issues ) {
        $grouped = array();

        foreach ( $issues as $issue ) {
            $category = $issue['category'];
            if ( ! isset( $grouped[ $category ] ) ) {
                $grouped[ $category ] = array(
                    'label'  => $this->get_category_label( $category ),
                    'issues' => array(),
                );
            }
            $grouped[ $category ]['issues'][] = $issue;
        }

        return $grouped;
    }

    /**
     * Get category label
     *
     * @param string $category Category ID.
     * @return string Label.
     */
    private function get_category_label( $category ) {
        $labels = array(
            self::CATEGORY_LABELS     => __( 'Labels & Names', 'bits-accessible-forms' ),
            self::CATEGORY_HELP_TEXT  => __( 'Help Text & Instructions', 'bits-accessible-forms' ),
            self::CATEGORY_STRUCTURE  => __( 'Form Structure', 'bits-accessible-forms' ),
            self::CATEGORY_KEYBOARD   => __( 'Keyboard Navigation', 'bits-accessible-forms' ),
            self::CATEGORY_VALIDATION => __( 'Validation & Errors', 'bits-accessible-forms' ),
            self::CATEGORY_CONTRAST   => __( 'Color & Contrast', 'bits-accessible-forms' ),
        );

        return $labels[ $category ] ?? $category;
    }

    /**
     * Get all available checks
     *
     * @return array Available checks.
     */
    public function get_available_checks() {
        return array(
            'missing_labels'       => __( 'Missing field labels', 'bits-accessible-forms' ),
            'generic_labels'       => __( 'Generic/unclear labels', 'bits-accessible-forms' ),
            'placeholder_as_label' => __( 'Placeholder used as label', 'bits-accessible-forms' ),
            'missing_help_text'    => __( 'Missing help text on complex fields', 'bits-accessible-forms' ),
            'missing_validation'   => __( 'Missing validation messages', 'bits-accessible-forms' ),
            'form_structure'       => __( 'Form organization', 'bits-accessible-forms' ),
            'duplicate_ids'        => __( 'Duplicate field IDs', 'bits-accessible-forms' ),
            'fieldset_legend'      => __( 'Fieldset/legend for grouped controls', 'bits-accessible-forms' ),
            'page_titles'          => __( 'Multi-page form titles', 'bits-accessible-forms' ),
        );
    }
}
