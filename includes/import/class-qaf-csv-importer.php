<?php
/**
 * CSV Importer
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_CSV_Importer class
 * 
 * Handles importing entries from CSV files.
 */
class QAF_CSV_Importer {

    /**
     * Default options
     *
     * @var array
     */
    private $defaults = array(
        'delimiter'      => ',',
        'enclosure'      => '"',
        'escape'         => '\\',
        'has_header'     => true,
        'skip_rows'      => 0,
        'date_format'    => 'Y-m-d H:i:s',
        'encoding'       => 'UTF-8',
    );

    /**
     * Import CSV file
     *
     * @param int    $form_id Form ID to import entries to.
     * @param string $file    File path.
     * @param array  $mapping Field mapping (csv_column => form_field_id).
     * @param array  $options Import options.
     * @return array|WP_Error Import result or error.
     */
    public function import( $form_id, $file, $mapping, $options = array() ) {
        $options = wp_parse_args( $options, $this->defaults );

        // Validate file
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'file_not_found', __( 'CSV file not found or not readable.', 'quill-accessible-forms' ) );
        }

        // Validate form
        $form = new QAF_Form( $form_id );
        if ( ! $form->id ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'quill-accessible-forms' ) );
        }

        // Open file
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_failed', __( 'Failed to open CSV file.', 'quill-accessible-forms' ) );
        }

        // Handle BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        // Results
        $results = array(
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => array(),
        );

        $row_num = 0;
        $headers = array();

        while ( ( $row = fgetcsv( $handle, 0, $options['delimiter'], $options['enclosure'], $options['escape'] ) ) !== false ) {
            $row_num++;

            // Skip rows if needed
            if ( $row_num <= $options['skip_rows'] ) {
                continue;
            }

            // Handle header row
            if ( $options['has_header'] && empty( $headers ) ) {
                $headers = $row;
                continue;
            }

            // Map data
            $entry_data = array();
            foreach ( $mapping as $csv_index => $field_id ) {
                if ( empty( $field_id ) ) {
                    continue;
                }

                // Handle column by index or header name
                $value = '';
                if ( is_numeric( $csv_index ) ) {
                    $value = isset( $row[ $csv_index ] ) ? $row[ $csv_index ] : '';
                } elseif ( ! empty( $headers ) ) {
                    $col_index = array_search( $csv_index, $headers, true );
                    if ( $col_index !== false ) {
                        $value = isset( $row[ $col_index ] ) ? $row[ $col_index ] : '';
                    }
                }

                // Convert encoding if needed
                if ( $options['encoding'] !== 'UTF-8' ) {
                    $value = mb_convert_encoding( $value, 'UTF-8', $options['encoding'] );
                }

                $entry_data[ $field_id ] = $value;
            }

            // Create entry
            $entry = new QAF_Entry();
            $entry->form_id = $form_id;
            $entry->status = 'active';
            $entry->set_field_values( $entry_data );

            $result = $entry->save();

            if ( is_wp_error( $result ) ) {
                $results['errors'][] = array(
                    'row'     => $row_num,
                    'message' => $result->get_error_message(),
                );
                $results['skipped']++;
            } else {
                $results['imported']++;
            }
        }

        fclose( $handle );

        return $results;
    }

    /**
     * Preview CSV file
     *
     * @param string $file    File path.
     * @param int    $rows    Number of rows to preview.
     * @param array  $options Import options.
     * @return array|WP_Error Preview data or error.
     */
    public function preview( $file, $rows = 5, $options = array() ) {
        $options = wp_parse_args( $options, $this->defaults );

        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'file_not_found', __( 'CSV file not found or not readable.', 'quill-accessible-forms' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_open_failed', __( 'Failed to open CSV file.', 'quill-accessible-forms' ) );
        }

        // Handle BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $preview = array(
            'headers' => array(),
            'rows'    => array(),
            'total'   => 0,
        );

        $row_num = 0;

        while ( ( $row = fgetcsv( $handle, 0, $options['delimiter'], $options['enclosure'], $options['escape'] ) ) !== false ) {
            $row_num++;

            // Skip rows if needed
            if ( $row_num <= $options['skip_rows'] ) {
                continue;
            }

            // Handle header row
            if ( $options['has_header'] && empty( $preview['headers'] ) ) {
                $preview['headers'] = $row;
                continue;
            }

            // Add row to preview
            if ( count( $preview['rows'] ) < $rows ) {
                $preview['rows'][] = $row;
            }

            $preview['total']++;
        }

        fclose( $handle );

        return $preview;
    }

    /**
     * Export entries to CSV
     *
     * @param int   $form_id  Form ID.
     * @param array $options  Export options.
     * @return string|WP_Error CSV content or error.
     */
    public function export( $form_id, $options = array() ) {
        $defaults = array(
            'fields'     => array(), // Empty = all fields
            'status'     => 'active',
            'date_from'  => '',
            'date_to'    => '',
            'limit'      => 0,
        );

        $options = wp_parse_args( $options, $defaults );

        $form = new QAF_Form( $form_id );
        if ( ! $form->id ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'quill-accessible-forms' ) );
        }

        // Get entries
        $args = array(
            'status' => $options['status'],
            'limit'  => $options['limit'] ?: 10000,
        );

        $entries = QAF_Entry::get_for_form( $form_id, $args );

        // Get fields
        $form_fields = $form->get_fields();
        $export_fields = array();

        if ( empty( $options['fields'] ) ) {
            $export_fields = $form_fields;
        } else {
            foreach ( $form_fields as $field ) {
                if ( in_array( $field['id'], $options['fields'], true ) ) {
                    $export_fields[] = $field;
                }
            }
        }

        // Build CSV
        $output = fopen( 'php://memory', 'r+' );

        // Header row
        $headers = array( 'ID', 'Date' );
        foreach ( $export_fields as $field ) {
            $headers[] = $field['label'] ?? $field['id'];
        }
        fputcsv( $output, $headers );

        // Data rows
        foreach ( $entries as $entry ) {
            $field_values = $entry->get_field_values();
            $row = array( $entry->id, $entry->created_at );

            foreach ( $export_fields as $field ) {
                $value = isset( $field_values[ $field['id'] ] ) ? $field_values[ $field['id'] ] : '';

                // Flatten arrays
                if ( is_array( $value ) ) {
                    $value = implode( ', ', $value );
                }

                $row[] = $value;
            }

            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }
}
