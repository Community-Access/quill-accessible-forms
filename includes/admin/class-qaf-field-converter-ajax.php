<?php
/**
 * Field Converter AJAX Handler
 *
 * Handles AJAX requests for field type conversion.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Converter_Ajax class
 */
class QAF_Field_Converter_Ajax {

    /**
     * Constructor - register AJAX hooks
     */
    public function __construct() {
        add_action( 'wp_ajax_qaf_preview_conversion', array( $this, 'preview_conversion' ) );
        add_action( 'wp_ajax_qaf_convert_field', array( $this, 'convert_field' ) );
        add_action( 'wp_ajax_qaf_get_conversion_compatibility', array( $this, 'get_compatibility' ) );
    }

    /**
     * Preview a field conversion
     */
    public function preview_conversion() {
        // Verify nonce
        if ( ! check_ajax_referer( 'qaf_converter_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'quill-accessible-forms' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        // Get parameters
        $field_json  = isset( $_POST['field'] ) ? wp_unslash( $_POST['field'] ) : '';
        $target_type = isset( $_POST['target_type'] ) ? sanitize_key( $_POST['target_type'] ) : '';
        $form_id     = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( empty( $field_json ) || empty( $target_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'quill-accessible-forms' ) ) );
        }

        $field = json_decode( $field_json, true );
        if ( ! is_array( $field ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid field data.', 'quill-accessible-forms' ) ) );
        }

        // Perform dry-run conversion
        $converter = new QAF_Field_Type_Converter();
        $result = $converter->convert( $field, $target_type, array(
            'dry_run' => true,
        ) );

        // Analyze conditional logic impact if we have a form
        $conditional_impact = array();
        if ( $form_id > 0 ) {
            $form_config = $this->get_form_config( $form_id );
            if ( $form_config ) {
                $conditional_impact = $converter->analyze_conditional_impact(
                    $form_config,
                    $field['id'] ?? '',
                    $field['type'] ?? 'text',
                    $target_type
                );
            }
        }

        wp_send_json_success( array(
            'report'             => $result['report'],
            'conditional_impact' => $conditional_impact,
        ) );
    }

    /**
     * Perform field conversion
     */
    public function convert_field() {
        // Verify nonce
        if ( ! check_ajax_referer( 'qaf_converter_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'quill-accessible-forms' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        // Get parameters
        $field_id    = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';
        $target_type = isset( $_POST['target_type'] ) ? sanitize_key( $_POST['target_type'] ) : '';
        $auto_repair = isset( $_POST['auto_repair'] ) && $_POST['auto_repair'];
        $form_id     = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( empty( $field_id ) || empty( $target_type ) || empty( $form_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'quill-accessible-forms' ) ) );
        }

        // The conversion is made on the form model, as every other change
        // to a question is, so validate() checks it and nothing else moves.
        // IDs do not change, so the conditions and rules that name the
        // question keep naming it; auto_repair has nothing left to do.
        $form = new QAF_Form( $form_id );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'quill-accessible-forms' ) ) );
        }
        $result = QAF_Builder_Field_Ops::convert( $form->get_model(), $field_id, $target_type );
        if ( $result === null ) {
            wp_send_json_error( array( 'message' => __( 'Field not found.', 'quill-accessible-forms' ) ) );
        }
        $why = QAF_Builder_Field_Ops::refused( $result );
        if ( $why !== null ) {
            wp_send_json_error( array( 'message' => $why ) );
        }
        list( $model, $changes ) = $result;
        $set = $form->set_model( $model );
        if ( is_wp_error( $set ) ) {
            wp_send_json_error( array( 'message' => $set->get_error_message(), 'errors' => $set->get_error_data() ) );
        }
        $saved = $form->save();
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => __( 'Failed to save form.', 'quill-accessible-forms' ) ) );
        }

        wp_send_json_success( array(
            'field'   => QAF_Field_Scoring_Panel::find( $model, $field_id ),
            'report'  => array( 'changes' => $changes ),
            'repairs' => array(),
        ) );
    }

    /**
     * Get compatibility information for a source type
     */
    public function get_compatibility() {
        // Verify nonce
        if ( ! check_ajax_referer( 'qaf_converter_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'quill-accessible-forms' ) ) );
        }

        $source_type = isset( $_POST['source_type'] ) ? sanitize_key( $_POST['source_type'] ) : '';

        if ( empty( $source_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing source type.', 'quill-accessible-forms' ) ) );
        }

        $converter = new QAF_Field_Type_Converter();
        $compatible = $converter->get_compatible_types( $source_type );

        wp_send_json_success( array(
            'source_type' => $source_type,
            'compatible'  => $compatible,
        ) );
    }

    /**
     * Get form configuration
     *
     * @param int $form_id Form ID.
     * @return array|null Form config or null.
     */
    private function get_form_config( $form_id ) {
        $form = get_post( $form_id );
        if ( ! $form || 'qaf_form' !== $form->post_type ) {
            return null;
        }

        $config = get_post_meta( $form_id, '_qaf_form_config', true );
        return is_array( $config ) ? $config : array();
    }

    /**
     * Save form configuration
     *
     * @param int   $form_id Form ID.
     * @param array $config  Form config.
     * @return bool Success.
     */
    private function save_form_config( $form_id, $config ) {
        return update_post_meta( $form_id, '_qaf_form_config', $config );
    }

    /**
     * Find a field in form config by ID
     *
     * @param array  $config   Form config.
     * @param string $field_id Field ID to find.
     * @return array|null Field or null.
     */
    private function find_field_in_config( $config, $field_id ) {
        $fields = $config['fields'] ?? array();
        return $this->find_field_recursive( $fields, $field_id );
    }

    /**
     * Recursively find a field
     *
     * @param array  $fields   Fields array.
     * @param string $field_id Field ID.
     * @return array|null Field or null.
     */
    private function find_field_recursive( $fields, $field_id ) {
        foreach ( $fields as $field ) {
            if ( ( $field['id'] ?? '' ) === $field_id ) {
                return $field;
            }
            if ( ! empty( $field['fields'] ) ) {
                $found = $this->find_field_recursive( $field['fields'], $field_id );
                if ( $found ) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Replace a field in form config
     *
     * @param array $config    Form config.
     * @param array $new_field New field data.
     * @return array Updated config.
     */
    private function replace_field_in_config( $config, $new_field ) {
        if ( ! isset( $config['fields'] ) ) {
            $config['fields'] = array();
        }

        $config['fields'] = $this->replace_field_recursive( $config['fields'], $new_field );
        return $config;
    }

    /**
     * Recursively replace a field
     *
     * @param array $fields    Fields array.
     * @param array $new_field New field.
     * @return array Updated fields.
     */
    private function replace_field_recursive( $fields, $new_field ) {
        foreach ( $fields as $index => $field ) {
            if ( ( $field['id'] ?? '' ) === ( $new_field['id'] ?? '' ) ) {
                $fields[ $index ] = $new_field;
                return $fields;
            }
            if ( ! empty( $field['fields'] ) ) {
                $fields[ $index ]['fields'] = $this->replace_field_recursive( $field['fields'], $new_field );
            }
        }
        return $fields;
    }
}

// Initialize
new QAF_Field_Converter_Ajax();
