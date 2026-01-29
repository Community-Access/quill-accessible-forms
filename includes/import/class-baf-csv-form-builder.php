<?php
/**
 * CSV Form Builder
 *
 * Analyzes CSV files and generates form structures with intelligent field type inference.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_CSV_Form_Builder class
 * 
 * Converts CSV files into form definitions with field type inference,
 * choice detection, and cascade relationship support.
 */
class BAF_CSV_Form_Builder {

    /**
     * Default options
     *
     * @var array
     */
    private $defaults = array(
        'delimiter'           => ',',
        'enclosure'           => '"',
        'escape'              => '\\',
        'has_header'          => true,
        'sample_rows'         => 100,     // Rows to analyze for type inference
        'unique_threshold'    => 20,      // Max unique values to suggest dropdown
        'choice_threshold'    => 0.8,     // Ratio threshold for dropdown (80% of values in limited set)
        'encoding'            => 'UTF-8',
        'infer_types'         => true,    // Auto-detect field types
        'detect_choices'      => true,    // Auto-detect choice fields
        'detect_cascades'     => true,    // Auto-detect cascade relationships
    );

    /**
     * Column analysis cache
     *
     * @var array
     */
    private $column_analysis = array();

    /**
     * Analyze CSV file and suggest form structure
     *
     * @param string $file    File path.
     * @param array  $options Analysis options.
     * @return array|WP_Error Analysis result or error.
     */
    public function analyze( $file, $options = array() ) {
        $options = wp_parse_args( $options, $this->defaults );

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'file_not_found', __( 'CSV file not found or not readable.', 'bits-accessible-forms' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_failed', __( 'Failed to open CSV file.', 'bits-accessible-forms' ) );
        }

        // Handle BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $headers = array();
        $sample_data = array();
        $row_count = 0;
        $total_rows = 0;

        // Read sample rows
        while ( ( $row = fgetcsv( $handle, 0, $options['delimiter'], $options['enclosure'], $options['escape'] ) ) !== false ) {
            $total_rows++;

            // Header row
            if ( $options['has_header'] && empty( $headers ) ) {
                $headers = array_map( 'trim', $row );
                continue;
            }

            // Sample data for analysis
            if ( $row_count < $options['sample_rows'] ) {
                // Convert encoding if needed
                if ( $options['encoding'] !== 'UTF-8' ) {
                    $row = array_map( function( $val ) use ( $options ) {
                        return mb_convert_encoding( $val, 'UTF-8', $options['encoding'] );
                    }, $row );
                }
                $sample_data[] = $row;
                $row_count++;
            }
        }

        fclose( $handle );

        // Generate column headers if none
        if ( empty( $headers ) && ! empty( $sample_data ) ) {
            $col_count = count( $sample_data[0] );
            for ( $i = 0; $i < $col_count; $i++ ) {
                $headers[] = 'Column ' . ( $i + 1 );
            }
        }

        // Analyze each column
        $columns = array();
        foreach ( $headers as $index => $header ) {
            $column_values = array_column( $sample_data, $index );
            $columns[] = $this->analyze_column( $header, $column_values, $index, $options );
        }

        // Detect cascade relationships
        $cascades = array();
        if ( $options['detect_cascades'] ) {
            $cascades = $this->detect_cascades( $columns, $sample_data, $headers );
        }

        $this->column_analysis = $columns;

        return array(
            'file'        => basename( $file ),
            'total_rows'  => $total_rows - ( $options['has_header'] ? 1 : 0 ),
            'sample_rows' => count( $sample_data ),
            'columns'     => $columns,
            'cascades'    => $cascades,
            'preview'     => array_slice( $sample_data, 0, 5 ),
            'headers'     => $headers,
        );
    }

    /**
     * Analyze a single column
     *
     * @param string $header  Column header.
     * @param array  $values  Column values.
     * @param int    $index   Column index.
     * @param array  $options Analysis options.
     * @return array Column analysis.
     */
    private function analyze_column( $header, $values, $index, $options ) {
        $non_empty = array_filter( $values, function( $v ) {
            return $v !== '' && $v !== null;
        });

        $unique_values = array_unique( $non_empty );
        $unique_count = count( $unique_values );
        $total_count = count( $non_empty );
        $empty_count = count( $values ) - $total_count;

        // Generate field ID from header
        $field_id = $this->generate_field_id( $header, $index );

        $analysis = array(
            'index'          => $index,
            'header'         => $header,
            'field_id'       => $field_id,
            'field_label'    => $this->generate_label( $header ),
            'total_values'   => count( $values ),
            'non_empty'      => $total_count,
            'empty_count'    => $empty_count,
            'unique_count'   => $unique_count,
            'suggested_type' => 'text',
            'is_required'    => $empty_count === 0,
            'choices'        => array(),
            'confidence'     => 0,
            'samples'        => array_slice( array_values( $unique_values ), 0, 10 ),
        );

        if ( $total_count === 0 ) {
            return $analysis;
        }

        // Infer field type
        if ( $options['infer_types'] ) {
            $type_inference = $this->infer_type( $unique_values, $header );
            $analysis['suggested_type'] = $type_inference['type'];
            $analysis['confidence'] = $type_inference['confidence'];
            $analysis['type_hints'] = $type_inference['hints'] ?? array();
        }

        // Detect if this should be a choice field
        if ( $options['detect_choices'] && $analysis['suggested_type'] === 'text' ) {
            $choice_analysis = $this->analyze_choices( $unique_values, $total_count, $options );
            if ( $choice_analysis['is_choice'] ) {
                $analysis['suggested_type'] = $choice_analysis['type'];
                $analysis['choices'] = $choice_analysis['choices'];
                $analysis['confidence'] = $choice_analysis['confidence'];
            }
        }

        return $analysis;
    }

    /**
     * Infer field type from values
     *
     * @param array  $values Unique values.
     * @param string $header Column header.
     * @return array Type inference result.
     */
    private function infer_type( $values, $header ) {
        $header_lower = strtolower( $header );
        $values = array_values( $values );

        // Check header for hints
        $header_hints = $this->get_header_type_hints( $header_lower );
        if ( $header_hints['type'] !== 'text' ) {
            return $header_hints;
        }

        // Analyze value patterns
        $type_scores = array(
            'email'    => 0,
            'phone'    => 0,
            'url'      => 0,
            'date'     => 0,
            'datetime' => 0,
            'time'     => 0,
            'number'   => 0,
            'boolean'  => 0,
            'text'     => 0,
        );

        $total = count( $values );
        if ( $total === 0 ) {
            return array( 'type' => 'text', 'confidence' => 0.5 );
        }

        foreach ( $values as $value ) {
            $value = trim( $value );
            if ( $value === '' ) {
                continue;
            }

            // Email
            if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                $type_scores['email']++;
            }

            // URL
            if ( filter_var( $value, FILTER_VALIDATE_URL ) || preg_match( '/^(https?:\/\/|www\.)/i', $value ) ) {
                $type_scores['url']++;
            }

            // Phone (various formats)
            if ( preg_match( '/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,4}[-\s\.]?[0-9]{1,9}$/', preg_replace( '/\s+/', '', $value ) ) ) {
                $type_scores['phone']++;
            }

            // Number (integer or decimal)
            if ( is_numeric( $value ) || preg_match( '/^-?[\d,]+\.?\d*$/', str_replace( array( '$', '€', '£', '%' ), '', $value ) ) ) {
                $type_scores['number']++;
            }

            // DateTime patterns
            if ( $this->is_datetime( $value ) ) {
                $type_scores['datetime']++;
            } elseif ( $this->is_date( $value ) ) {
                $type_scores['date']++;
            } elseif ( $this->is_time( $value ) ) {
                $type_scores['time']++;
            }

            // Boolean
            if ( in_array( strtolower( $value ), array( 'yes', 'no', 'true', 'false', '1', '0', 'y', 'n' ), true ) ) {
                $type_scores['boolean']++;
            }
        }

        // Find best match
        $best_type = 'text';
        $best_score = 0;

        foreach ( $type_scores as $type => $score ) {
            $ratio = $score / $total;
            if ( $ratio > 0.7 && $ratio > $best_score ) {
                $best_type = $type;
                $best_score = $ratio;
            }
        }

        // Map boolean to checkbox/radio
        if ( $best_type === 'boolean' ) {
            $best_type = count( $values ) <= 2 ? 'checkbox' : 'radio';
        }

        return array(
            'type'       => $best_type,
            'confidence' => $best_score > 0 ? $best_score : 0.5,
            'hints'      => $type_scores,
        );
    }

    /**
     * Get type hints from header name
     *
     * @param string $header Lowercase header.
     * @return array Type hint.
     */
    private function get_header_type_hints( $header ) {
        $patterns = array(
            'email'    => array( 'email', 'e-mail', 'mail' ),
            'phone'    => array( 'phone', 'tel', 'mobile', 'cell', 'fax' ),
            'url'      => array( 'url', 'website', 'web', 'link', 'homepage' ),
            'date'     => array( 'date', 'dob', 'birth', 'created', 'updated', 'expires' ),
            'datetime' => array( 'datetime', 'timestamp' ),
            'time'     => array( 'time', 'hour' ),
            'number'   => array( 'amount', 'price', 'cost', 'total', 'quantity', 'qty', 'age', 'count', 'number', 'num', '#' ),
            'textarea' => array( 'description', 'comment', 'comments', 'notes', 'message', 'bio', 'biography', 'about' ),
            'name'     => array( 'name', 'fullname', 'full_name' ),
            'address'  => array( 'address', 'street', 'addr' ),
        );

        foreach ( $patterns as $type => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( strpos( $header, $keyword ) !== false ) {
                    return array( 'type' => $type, 'confidence' => 0.8 );
                }
            }
        }

        return array( 'type' => 'text', 'confidence' => 0.5 );
    }

    /**
     * Check if value is a date
     *
     * @param string $value Value to check.
     * @return bool True if date.
     */
    private function is_date( $value ) {
        $patterns = array(
            '/^\d{4}-\d{2}-\d{2}$/',                      // 2024-01-15
            '/^\d{2}\/\d{2}\/\d{4}$/',                    // 01/15/2024 or 15/01/2024
            '/^\d{2}-\d{2}-\d{4}$/',                      // 01-15-2024
            '/^[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}$/',     // January 15, 2024
            '/^\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}$/',       // 15 January 2024
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $value ) ) {
                return true;
            }
        }

        // Try strtotime
        $timestamp = strtotime( $value );
        return $timestamp !== false && $timestamp > 0;
    }

    /**
     * Check if value is a datetime
     *
     * @param string $value Value to check.
     * @return bool True if datetime.
     */
    private function is_datetime( $value ) {
        // Must contain both date and time components
        return preg_match( '/\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}/', $value ) ||
               preg_match( '/\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}/', $value );
    }

    /**
     * Check if value is a time
     *
     * @param string $value Value to check.
     * @return bool True if time.
     */
    private function is_time( $value ) {
        return preg_match( '/^\d{1,2}:\d{2}(:\d{2})?\s*(am|pm)?$/i', $value );
    }

    /**
     * Analyze if column should be a choice field
     *
     * @param array $unique_values Unique values.
     * @param int   $total_count   Total non-empty values.
     * @param array $options       Analysis options.
     * @return array Choice analysis.
     */
    private function analyze_choices( $unique_values, $total_count, $options ) {
        $unique_count = count( $unique_values );

        // If too many unique values, not a choice field
        if ( $unique_count > $options['unique_threshold'] ) {
            return array( 'is_choice' => false );
        }

        // If only 2-3 values and low cardinality, likely radio/checkbox
        if ( $unique_count <= 3 && $unique_count < $total_count * 0.5 ) {
            return array(
                'is_choice'  => true,
                'type'       => $unique_count === 2 ? 'radio' : 'select',
                'choices'    => $this->format_choices( $unique_values ),
                'confidence' => 0.9,
            );
        }

        // If unique values are a small portion of total, likely a choice field
        $uniqueness_ratio = $unique_count / max( $total_count, 1 );
        if ( $uniqueness_ratio < ( 1 - $options['choice_threshold'] ) && $unique_count <= $options['unique_threshold'] ) {
            return array(
                'is_choice'  => true,
                'type'       => 'select',
                'choices'    => $this->format_choices( $unique_values ),
                'confidence' => 1 - $uniqueness_ratio,
            );
        }

        return array( 'is_choice' => false );
    }

    /**
     * Format choices array
     *
     * @param array $values Unique values.
     * @return array Formatted choices.
     */
    private function format_choices( $values ) {
        $choices = array();
        foreach ( $values as $value ) {
            $value = trim( $value );
            if ( $value !== '' ) {
                $choices[] = array(
                    'value' => sanitize_title( $value ),
                    'label' => $value,
                );
            }
        }
        return $choices;
    }

    /**
     * Detect cascade relationships between columns
     *
     * @param array $columns     Column analysis.
     * @param array $sample_data Sample data rows.
     * @param array $headers     Column headers.
     * @return array Detected cascades.
     */
    private function detect_cascades( $columns, $sample_data, $headers ) {
        $cascades = array();

        // Look for known patterns (country/state, category/subcategory, etc.)
        $cascade_patterns = array(
            array( 'country', 'state' ),
            array( 'country', 'province' ),
            array( 'country', 'region' ),
            array( 'state', 'city' ),
            array( 'province', 'city' ),
            array( 'category', 'subcategory' ),
            array( 'department', 'team' ),
            array( 'parent', 'child' ),
        );

        $header_lower = array_map( 'strtolower', $headers );

        foreach ( $cascade_patterns as $pattern ) {
            $parent_idx = null;
            $child_idx = null;

            foreach ( $header_lower as $idx => $header ) {
                if ( strpos( $header, $pattern[0] ) !== false ) {
                    $parent_idx = $idx;
                }
                if ( strpos( $header, $pattern[1] ) !== false ) {
                    $child_idx = $idx;
                }
            }

            if ( $parent_idx !== null && $child_idx !== null && $parent_idx !== $child_idx ) {
                // Verify cascade relationship in data
                $relationship = $this->verify_cascade( $sample_data, $parent_idx, $child_idx );
                if ( $relationship['is_cascade'] ) {
                    $cascades[] = array(
                        'parent_column' => $headers[ $parent_idx ],
                        'parent_index'  => $parent_idx,
                        'child_column'  => $headers[ $child_idx ],
                        'child_index'   => $child_idx,
                        'mapping'       => $relationship['mapping'],
                        'confidence'    => $relationship['confidence'],
                    );
                }
            }
        }

        return $cascades;
    }

    /**
     * Verify cascade relationship between two columns
     *
     * @param array $data        Sample data.
     * @param int   $parent_idx  Parent column index.
     * @param int   $child_idx   Child column index.
     * @return array Verification result.
     */
    private function verify_cascade( $data, $parent_idx, $child_idx ) {
        $mapping = array();

        foreach ( $data as $row ) {
            $parent_val = isset( $row[ $parent_idx ] ) ? trim( $row[ $parent_idx ] ) : '';
            $child_val = isset( $row[ $child_idx ] ) ? trim( $row[ $child_idx ] ) : '';

            if ( $parent_val === '' || $child_val === '' ) {
                continue;
            }

            if ( ! isset( $mapping[ $parent_val ] ) ) {
                $mapping[ $parent_val ] = array();
            }

            if ( ! in_array( $child_val, $mapping[ $parent_val ], true ) ) {
                $mapping[ $parent_val ][] = $child_val;
            }
        }

        // Check if it's a valid cascade (children mostly unique to parents)
        $total_children = 0;
        $unique_children = array();

        foreach ( $mapping as $parent => $children ) {
            $total_children += count( $children );
            foreach ( $children as $child ) {
                $unique_children[ $child ] = ( $unique_children[ $child ] ?? 0 ) + 1;
            }
        }

        // If most children appear under only one parent, it's a cascade
        $unique_count = count( array_filter( $unique_children, function( $count ) {
            return $count === 1;
        }));

        $confidence = $unique_count / max( count( $unique_children ), 1 );

        return array(
            'is_cascade' => $confidence > 0.6 && count( $mapping ) > 1,
            'mapping'    => $mapping,
            'confidence' => $confidence,
        );
    }

    /**
     * Generate form from analysis
     *
     * @param array  $analysis Analysis result from analyze().
     * @param array  $options  Generation options.
     * @return array Form configuration.
     */
    public function generate_form( $analysis, $options = array() ) {
        $defaults = array(
            'title'            => __( 'Imported Form', 'bits-accessible-forms' ),
            'description'      => '',
            'include_columns'  => array(), // Empty = all
            'exclude_columns'  => array(),
            'field_overrides'  => array(), // column_index => field_config overrides
        );

        $options = wp_parse_args( $options, $defaults );

        $form = array(
            'title'       => $options['title'],
            'description' => $options['description'],
            'fields'      => array(),
            'settings'    => array(),
            'pages'       => array(
                array(
                    'id'    => 'page_1',
                    'title' => '',
                ),
            ),
        );

        // Generate fields from columns
        foreach ( $analysis['columns'] as $column ) {
            // Check include/exclude
            if ( ! empty( $options['include_columns'] ) && ! in_array( $column['index'], $options['include_columns'], true ) ) {
                continue;
            }
            if ( in_array( $column['index'], $options['exclude_columns'], true ) ) {
                continue;
            }

            $field = $this->column_to_field( $column );

            // Apply overrides
            if ( isset( $options['field_overrides'][ $column['index'] ] ) ) {
                $field = array_merge( $field, $options['field_overrides'][ $column['index'] ] );
            }

            $form['fields'][] = $field;
        }

        // Apply cascade relationships
        foreach ( $analysis['cascades'] as $cascade ) {
            $form = $this->apply_cascade( $form, $cascade );
        }

        // Add submit button
        $form['fields'][] = array(
            'id'    => 'submit',
            'type'  => 'submit',
            'label' => __( 'Submit', 'bits-accessible-forms' ),
        );

        return $form;
    }

    /**
     * Convert column analysis to field config
     *
     * @param array $column Column analysis.
     * @return array Field configuration.
     */
    private function column_to_field( $column ) {
        $field = array(
            'id'          => $column['field_id'],
            'type'        => $column['suggested_type'],
            'label'       => $column['field_label'],
            'required'    => $column['is_required'],
            'page'        => 'page_1',
        );

        // Add choices if present
        if ( ! empty( $column['choices'] ) ) {
            $field['choices'] = $column['choices'];
        }

        // Add type-specific settings
        switch ( $column['suggested_type'] ) {
            case 'email':
                $field['autocomplete'] = 'email';
                break;

            case 'phone':
                $field['autocomplete'] = 'tel';
                $field['inputmode'] = 'tel';
                break;

            case 'url':
                $field['autocomplete'] = 'url';
                $field['inputmode'] = 'url';
                break;

            case 'number':
                $field['inputmode'] = 'numeric';
                break;

            case 'date':
                $field['autocomplete'] = 'bday';
                break;

            case 'textarea':
                $field['rows'] = 4;
                break;
        }

        return $field;
    }

    /**
     * Apply cascade relationship to form
     *
     * @param array $form    Form configuration.
     * @param array $cascade Cascade definition.
     * @return array Updated form.
     */
    private function apply_cascade( $form, $cascade ) {
        $parent_id = $this->generate_field_id( $cascade['parent_column'], $cascade['parent_index'] );
        $child_id = $this->generate_field_id( $cascade['child_column'], $cascade['child_index'] );

        foreach ( $form['fields'] as &$field ) {
            if ( $field['id'] === $child_id ) {
                $field['cascade_from'] = $parent_id;
                $field['cascade_mapping'] = $cascade['mapping'];
                $field['empty_message'] = sprintf(
                    /* translators: %s: parent field label */
                    __( 'Please select a %s first', 'bits-accessible-forms' ),
                    strtolower( $cascade['parent_column'] )
                );
            }
        }

        return $form;
    }

    /**
     * Generate field ID from header
     *
     * @param string $header Header text.
     * @param int    $index  Column index.
     * @return string Field ID.
     */
    private function generate_field_id( $header, $index ) {
        $id = sanitize_title( $header );
        $id = str_replace( '-', '_', $id );

        // Ensure it starts with a letter
        if ( preg_match( '/^[0-9]/', $id ) ) {
            $id = 'field_' . $id;
        }

        // Fallback
        if ( empty( $id ) ) {
            $id = 'field_' . ( $index + 1 );
        }

        return $id;
    }

    /**
     * Generate readable label from header
     *
     * @param string $header Header text.
     * @return string Label.
     */
    private function generate_label( $header ) {
        // Replace common separators with spaces
        $label = str_replace( array( '_', '-', '.' ), ' ', $header );

        // Title case
        $label = ucwords( strtolower( $label ) );

        return $label;
    }

    /**
     * Generate BFL from form config
     *
     * @param array $form Form configuration.
     * @return string BFL code.
     */
    public function generate_bfl( $form ) {
        $bfl = 'form "' . addslashes( $form['title'] ) . '"';

        if ( ! empty( $form['description'] ) ) {
            $bfl .= ' @description "' . addslashes( $form['description'] ) . '"';
        }

        $bfl .= "\n";

        foreach ( $form['fields'] as $field ) {
            if ( $field['type'] === 'submit' ) {
                $bfl .= "\nsubmit \"" . addslashes( $field['label'] ) . "\"\n";
                continue;
            }

            $bfl .= "\n" . $field['type'] . ' #' . $field['id'] . ' "' . addslashes( $field['label'] ) . '"';

            // Modifiers
            $modifiers = array();

            if ( ! empty( $field['required'] ) ) {
                $modifiers[] = '@required';
            }

            if ( ! empty( $field['placeholder'] ) ) {
                $modifiers[] = '@placeholder "' . addslashes( $field['placeholder'] ) . '"';
            }

            if ( ! empty( $field['autocomplete'] ) ) {
                $modifiers[] = '@autocomplete "' . $field['autocomplete'] . '"';
            }

            if ( ! empty( $field['inputmode'] ) ) {
                $modifiers[] = '@inputmode "' . $field['inputmode'] . '"';
            }

            if ( ! empty( $field['rows'] ) ) {
                $modifiers[] = '@rows ' . $field['rows'];
            }

            if ( ! empty( $field['cascade_from'] ) ) {
                $modifiers[] = '@cascade_from #' . $field['cascade_from'];
                if ( ! empty( $field['empty_message'] ) ) {
                    $modifiers[] = '@empty_message "' . addslashes( $field['empty_message'] ) . '"';
                }
            }

            if ( ! empty( $modifiers ) ) {
                $bfl .= "\n  " . implode( "\n  ", $modifiers );
            }

            // Choices
            if ( ! empty( $field['choices'] ) ) {
                $bfl .= "\n";
                foreach ( $field['choices'] as $choice ) {
                    if ( $choice['value'] !== sanitize_title( $choice['label'] ) ) {
                        $bfl .= "  + " . $choice['value'] . '|' . $choice['label'] . "\n";
                    } else {
                        $bfl .= "  + " . $choice['label'] . "\n";
                    }
                }
            }

            $bfl .= "\n";
        }

        return trim( $bfl );
    }

    /**
     * Parse inline delimited choices from column values
     *
     * Handles values like "Option1|Option2|Option3" or "Red;Green;Blue"
     *
     * @param array  $values    Column values.
     * @param string $delimiter Delimiter character.
     * @return array|null Parsed choices or null if not delimited.
     */
    public function parse_inline_choices( $values, $delimiter = '|' ) {
        $all_parts = array();

        foreach ( $values as $value ) {
            $value = trim( $value );
            if ( empty( $value ) ) {
                continue;
            }

            // Check if value contains delimiter
            if ( strpos( $value, $delimiter ) === false ) {
                return null; // Not a delimited value
            }

            $parts = array_map( 'trim', explode( $delimiter, $value ) );
            foreach ( $parts as $part ) {
                if ( ! empty( $part ) && ! in_array( $part, $all_parts, true ) ) {
                    $all_parts[] = $part;
                }
            }
        }

        if ( empty( $all_parts ) ) {
            return null;
        }

        return $this->format_choices( $all_parts );
    }

    /**
     * Load lookup table from external CSV file
     *
     * Creates choice options from a separate CSV file.
     *
     * @param string $file        Lookup CSV file path.
     * @param int    $value_col   Column index for choice value (0-based).
     * @param int    $label_col   Column index for choice label (0-based).
     * @param array  $options     Parse options.
     * @return array|WP_Error Array of choices or error.
     */
    public function load_lookup_table( $file, $value_col = 0, $label_col = 1, $options = array() ) {
        $options = wp_parse_args( $options, array(
            'delimiter'  => ',',
            'enclosure'  => '"',
            'has_header' => true,
            'encoding'   => 'UTF-8',
        ));

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'file_not_found', __( 'Lookup table file not found.', 'bits-accessible-forms' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_failed', __( 'Failed to open lookup table.', 'bits-accessible-forms' ) );
        }

        // Handle BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $choices = array();
        $is_first = true;

        while ( ( $row = fgetcsv( $handle, 0, $options['delimiter'], $options['enclosure'] ) ) !== false ) {
            // Skip header
            if ( $is_first && $options['has_header'] ) {
                $is_first = false;
                continue;
            }
            $is_first = false;

            $value = isset( $row[ $value_col ] ) ? trim( $row[ $value_col ] ) : '';
            $label = isset( $row[ $label_col ] ) ? trim( $row[ $label_col ] ) : $value;

            if ( empty( $value ) ) {
                continue;
            }

            // Convert encoding
            if ( $options['encoding'] !== 'UTF-8' ) {
                $value = mb_convert_encoding( $value, 'UTF-8', $options['encoding'] );
                $label = mb_convert_encoding( $label, 'UTF-8', $options['encoding'] );
            }

            $choices[] = array(
                'value' => sanitize_title( $value ),
                'label' => $label,
            );
        }

        fclose( $handle );

        if ( empty( $choices ) ) {
            return new WP_Error( 'empty_lookup', __( 'Lookup table is empty.', 'bits-accessible-forms' ) );
        }

        return $choices;
    }

    /**
     * Load cascade mapping from CSV
     *
     * Creates parent-child relationships from a CSV file.
     * Expected format: parent_value, child_value, [child_label]
     *
     * @param string $file       CSV file path.
     * @param int    $parent_col Parent column index.
     * @param int    $child_col  Child column index.
     * @param int    $label_col  Optional label column index.
     * @param array  $options    Parse options.
     * @return array|WP_Error Cascade mapping or error.
     */
    public function load_cascade_mapping( $file, $parent_col = 0, $child_col = 1, $label_col = null, $options = array() ) {
        $options = wp_parse_args( $options, array(
            'delimiter'  => ',',
            'enclosure'  => '"',
            'has_header' => true,
        ));

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'file_not_found', __( 'Cascade mapping file not found.', 'bits-accessible-forms' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_failed', __( 'Failed to open cascade mapping.', 'bits-accessible-forms' ) );
        }

        // Handle BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $mapping = array();
        $is_first = true;

        while ( ( $row = fgetcsv( $handle, 0, $options['delimiter'], $options['enclosure'] ) ) !== false ) {
            if ( $is_first && $options['has_header'] ) {
                $is_first = false;
                continue;
            }
            $is_first = false;

            $parent = isset( $row[ $parent_col ] ) ? trim( $row[ $parent_col ] ) : '';
            $child = isset( $row[ $child_col ] ) ? trim( $row[ $child_col ] ) : '';
            $label = $label_col !== null && isset( $row[ $label_col ] ) 
                ? trim( $row[ $label_col ] ) 
                : $child;

            if ( empty( $parent ) || empty( $child ) ) {
                continue;
            }

            if ( ! isset( $mapping[ $parent ] ) ) {
                $mapping[ $parent ] = array();
            }

            // Check if child already exists for this parent
            $exists = false;
            foreach ( $mapping[ $parent ] as $existing ) {
                if ( $existing['value'] === $child ) {
                    $exists = true;
                    break;
                }
            }

            if ( ! $exists ) {
                $mapping[ $parent ][] = array(
                    'value' => sanitize_title( $child ),
                    'label' => $label,
                );
            }
        }

        fclose( $handle );

        return $mapping;
    }

    /**
     * Apply lookup table to a column
     *
     * @param array  $column  Column analysis from analyze().
     * @param array  $choices Choices from load_lookup_table().
     * @return array Updated column.
     */
    public function apply_lookup_to_column( $column, $choices ) {
        $column['suggested_type'] = count( $choices ) <= 5 ? 'radio' : 'select';
        $column['choices'] = $choices;
        $column['confidence'] = 1.0;
        $column['lookup_applied'] = true;
        return $column;
    }

    /**
     * Import CSV and create form (convenience method)
     *
     * @param string $file    CSV file path.
     * @param array  $options Import options.
     * @return BAF_Form|WP_Error Created form or error.
     */
    public function import_and_create( $file, $options = array() ) {
        // Analyze CSV
        $analysis = $this->analyze( $file, $options );

        if ( is_wp_error( $analysis ) ) {
            return $analysis;
        }

        // Generate form config
        $form_options = array(
            'title'       => $options['title'] ?? sprintf( __( 'Form from %s', 'bits-accessible-forms' ), basename( $file ) ),
            'description' => $options['description'] ?? '',
        );

        $form_config = $this->generate_form( $analysis, $form_options );

        // Generate BFL
        $bfl = $this->generate_bfl( $form_config );

        // Create form via importer
        $importer = new BAF_Form_Importer();
        return $importer->import_bfl( $bfl );
    }

    /**
     * Get analysis report for UI display
     *
     * @param array $analysis Analysis result.
     * @return array Report data.
     */
    public function get_report( $analysis ) {
        $report = array(
            'summary' => array(
                'total_columns'    => count( $analysis['columns'] ),
                'total_rows'       => $analysis['total_rows'],
                'inferred_types'   => array(),
                'required_fields'  => 0,
                'choice_fields'    => 0,
                'cascade_pairs'    => count( $analysis['cascades'] ),
            ),
            'columns' => array(),
            'warnings' => array(),
            'suggestions' => array(),
        );

        foreach ( $analysis['columns'] as $col ) {
            $report['summary']['inferred_types'][ $col['suggested_type'] ] = 
                ( $report['summary']['inferred_types'][ $col['suggested_type'] ] ?? 0 ) + 1;

            if ( $col['is_required'] ) {
                $report['summary']['required_fields']++;
            }

            if ( ! empty( $col['choices'] ) ) {
                $report['summary']['choice_fields']++;
            }

            $report['columns'][] = array(
                'header'         => $col['header'],
                'suggested_type' => $col['suggested_type'],
                'confidence'     => round( $col['confidence'] * 100 ) . '%',
                'is_required'    => $col['is_required'],
                'unique_values'  => $col['unique_count'],
                'choice_count'   => count( $col['choices'] ),
                'samples'        => $col['samples'],
            );

            // Add warnings for low confidence
            if ( $col['confidence'] < 0.7 ) {
                $report['warnings'][] = sprintf(
                    /* translators: 1: column name, 2: confidence percentage */
                    __( 'Column "%1$s" type detection has low confidence (%2$s). Please verify.', 'bits-accessible-forms' ),
                    $col['header'],
                    round( $col['confidence'] * 100 ) . '%'
                );
            }
        }

        // Add suggestions
        if ( $report['summary']['choice_fields'] > 0 ) {
            $report['suggestions'][] = __( 'Some fields have been detected as dropdowns. Review the choices to ensure accuracy.', 'bits-accessible-forms' );
        }

        if ( count( $analysis['cascades'] ) > 0 ) {
            $report['suggestions'][] = __( 'Cascade relationships have been detected. These fields will be linked.', 'bits-accessible-forms' );
        }

        return $report;
    }
}
