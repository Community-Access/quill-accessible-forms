<?php
/**
 * Form Importer
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Form_Importer class
 * 
 * Handles importing forms from various sources.
 */
class QAF_Form_Importer {

    /**
     * Import form from JSON
     *
     * @param string $json JSON string.
     * @return QAF_Form|WP_Error Form object or error.
     */
    public function import_json( $json ) {
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'invalid_json', __( 'Invalid JSON format.', 'quill-accessible-forms' ) );
        }

        return $this->import_array( $data );
    }

    /**
     * Import form from array
     *
     * @param array $data Form data.
     * @return QAF_Form|WP_Error Form object or error.
     */
    public function import_array( $data ) {
        // Validate structure
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing_title', __( 'Form title is required.', 'quill-accessible-forms' ) );
        }

        // Create form
        $form = new QAF_Form();
        $form->title = sanitize_text_field( $data['title'] );
        $form->description = isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '';
        $form->config = isset( $data['config'] ) ? $data['config'] : array();
        $form->bfl_source = isset( $data['bfl_source'] ) ? $data['bfl_source'] : '';
        $form->settings = isset( $data['settings'] ) ? $data['settings'] : array();
        $form->status = 'draft';

        // Generate unique IDs for fields
        // A form with a stored model keeps its IDs: its conditions name them.
        if ( ! empty( $form->config['fields'] ) && ! isset( $form->config['form_model'] ) ) {
            $form->config['fields'] = $this->regenerate_field_ids( $form->config['fields'] );
        }

        if ( ! empty( $form->config['pages'] ) ) {
            foreach ( $form->config['pages'] as &$page ) {
                if ( ! empty( $page['fields'] ) ) {
                    $page['fields'] = $this->regenerate_field_ids( $page['fields'] );
                }
            }
        }

        $result = $form->save();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $form;
    }

    /**
     * Import form from BFL
     *
     * @param string $bfl BFL source code.
     * @return QAF_Form|WP_Error Form object or error.
     */
    public function import_bfl( $bfl ) {
        $parser = new QAF_BFL_Parser();
        $result = $parser->parse( $bfl );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $form = new QAF_Form();
        $form->title = $result['title'] ?? __( 'Imported Form', 'quill-accessible-forms' );
        $form->description = $result['description'] ?? '';
        $form->config = $result['config'] ?? array();
        $form->bfl_source = $bfl;
        $form->settings = $result['settings'] ?? array();
        $form->status = 'draft';

        $save_result = $form->save();

        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }

        return $form;
    }

    /**
     * Import from file
     *
     * @param string $file File path.
     * @return QAF_Form|WP_Error Form object or error.
     */
    public function import_file( $file ) {
        if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found or not readable.', 'quill-accessible-forms' ) );
        }

        $content = file_get_contents( $file );
        $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

        switch ( $extension ) {
            case 'json':
                return $this->import_json( $content );

            case 'qaf': // Saved forms; the first line is "bfl 1", the language's version.
            case 'bfl': // Files saved before the rename read the same.
                return $this->import_bfl( $content );

            default:
                return new WP_Error( 'unsupported_format', __( 'Unsupported file format.', 'quill-accessible-forms' ) );
        }
    }

    /**
     * Export form to JSON
     *
     * @param int  $form_id        Form ID.
     * @param bool $include_entries Include entries.
     * @return string|WP_Error JSON string or error.
     */
    public function export_json( $form_id, $include_entries = false ) {
        $form = new QAF_Form( $form_id );

        if ( ! $form->id ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'quill-accessible-forms' ) );
        }

        $data = array(
            'title'       => $form->title,
            'description' => $form->description,
            'config'      => $form->config,
            'bfl_source'  => $form->bfl_source,
            'settings'    => $form->settings,
            'version'     => QAF_VERSION,
            'exported_at' => current_time( 'c' ),
        );

        if ( $include_entries ) {
            $entries = QAF_Entry::get_for_form( $form_id, array( 'limit' => 10000 ) );
            $data['entries'] = array();

            foreach ( $entries as $entry ) {
                $data['entries'][] = array(
                    'fields'     => $entry->get_field_values(),
                    'status'     => $entry->status,
                    'created_at' => $entry->created_at,
                );
            }
        }

        return wp_json_encode( $data, JSON_PRETTY_PRINT );
    }

    /**
     * Export form to BFL
     *
     * @param int $form_id Form ID.
     * @return string|WP_Error BFL string or error.
     */
    public function export_bfl( $form_id ) {
        $form = new QAF_Form( $form_id );

        if ( ! $form->id ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'quill-accessible-forms' ) );
        }

        // If form has BFL source, return it
        if ( ! empty( $form->bfl_source ) ) {
            return $form->bfl_source;
        }

        // Otherwise compile from config
        $compiler = new QAF_BFL_Compiler();
        return $compiler->compile( $form->config );
    }

    /**
     * Regenerate field IDs to avoid conflicts
     *
     * @param array $fields Fields array.
     * @return array Fields with new IDs.
     */
    private function regenerate_field_ids( $fields ) {
        $id_map = array();

        foreach ( $fields as &$field ) {
            if ( isset( $field['id'] ) ) {
                $old_id = $field['id'];
                $new_id = uniqid( $field['type'] . '_' );
                $id_map[ $old_id ] = $new_id;
                $field['id'] = $new_id;
            }

            // Handle nested fields
            if ( ! empty( $field['fields'] ) ) {
                $field['fields'] = $this->regenerate_field_ids( $field['fields'] );
            }
        }

        // Update references in conditional logic
        foreach ( $fields as &$field ) {
            if ( ! empty( $field['conditional'] ) ) {
                $field['conditional'] = $this->update_conditional_refs( $field['conditional'], $id_map );
            }
        }

        return $fields;
    }

    /**
     * Update conditional logic field references
     *
     * @param array $conditional Conditional logic.
     * @param array $id_map      ID mapping.
     * @return array Updated conditional logic.
     */
    private function update_conditional_refs( $conditional, $id_map ) {
        if ( ! empty( $conditional['rules'] ) ) {
            foreach ( $conditional['rules'] as &$rule ) {
                if ( isset( $rule['field_id'] ) && isset( $id_map[ $rule['field_id'] ] ) ) {
                    $rule['field_id'] = $id_map[ $rule['field_id'] ];
                }
            }
        }

        return $conditional;
    }

    /**
     * Duplicate a form
     *
     * @param int    $form_id  Form ID to duplicate.
     * @param string $new_title Optional new title.
     * @return QAF_Form|WP_Error New form or error.
     */
    public function duplicate( $form_id, $new_title = '' ) {
        $form = new QAF_Form( $form_id );

        if ( ! $form->id ) {
            return new WP_Error( 'form_not_found', __( 'Form not found.', 'quill-accessible-forms' ) );
        }

        $data = array(
            'title'       => $new_title ?: sprintf( __( '%s (Copy)', 'quill-accessible-forms' ), $form->title ),
            'description' => $form->description,
            'config'      => $form->config,
            'bfl_source'  => $form->bfl_source,
            'settings'    => $form->settings,
        );

        return $this->import_array( $data );
    }
}
