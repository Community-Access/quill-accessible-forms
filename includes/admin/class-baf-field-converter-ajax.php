<?php
/**
 * Field Converter AJAX Handler
 *
 * Handles AJAX requests for field type conversion.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Converter_Ajax class
 */
class BAF_Field_Converter_Ajax {

    /**
     * Constructor - register AJAX hooks
     */
    public function __construct() {
        add_action( 'wp_ajax_baf_preview_conversion', array( $this, 'preview_conversion' ) );
        add_action( 'wp_ajax_baf_convert_field', array( $this, 'convert_field' ) );
        add_action( 'wp_ajax_baf_get_conversion_compatibility', array( $this, 'get_compatibility' ) );
    }

    /**
     * Preview a field conversion
     */
    public function preview_conversion() {
        // Verify nonce
        if ( ! check_ajax_referer( 'baf_converter_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'bits-accessible-forms' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        // Get parameters
        $field_json  = isset( $_POST['field'] ) ? wp_unslash( $_POST['field'] ) : '';
        $target_type = isset( $_POST['target_type'] ) ? sanitize_key( $_POST['target_type'] ) : '';
        $form_id     = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( empty( $field_json ) || empty( $target_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'bits-accessible-forms' ) ) );
        }

        $field = json_decode( $field_json, true );
        if ( ! is_array( $field ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid field data.', 'bits-accessible-forms' ) ) );
        }

        // Perform dry-run conversion
        $converter = new BAF_Field_Type_Converter();
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
        if ( ! check_ajax_referer( 'baf_converter_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'bits-accessible-forms' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        // Get parameters
        $field_id    = isset( $_POST['field_id'] ) ? sanitize_key( $_POST['field_id'] ) : '';
        $target_type = isset( $_POST['target_type'] ) ? sanitize_key( $_POST['target_type'] ) : '';
        $auto_repair = isset( $_POST['auto_repair'] ) && $_POST['auto_repair'];
        $form_id     = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( empty( $field_id ) || empty( $target_type ) || empty( $form_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'bits-accessible-forms' ) ) );
        }

        // Get form config
        $form_config = $this->get_form_config( $form_id );
        if ( ! $form_config ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'bits-accessible-forms' ) ) );
        }

        // Find the field
        $field = $this->find_field_in_config( $form_config, $field_id );
        if ( ! $field ) {
            wp_send_json_error( array( 'message' => __( 'Field not found.', 'bits-accessible-forms' ) ) );
        }

        // Perform conversion
        $converter = new BAF_Field_Type_Converter();
        $result = $converter->convert( $field, $target_type, array(
            'dry_run' => false,
        ) );

        if ( ! $result['success'] ) {
            wp_send_json_error( array( 
                'message' => __( 'Conversion failed.', 'bits-accessible-forms' ),
                'report'  => $result['report'],
            ) );
        }

        // Update the field in form config
        $form_config = $this->replace_field_in_config( $form_config, $result['field'] );

        // Auto-repair conditional logic if requested
        $repairs = array();
        if ( $auto_repair ) {
            $repair_result = $converter->repair_conditional_logic( $form_config, $field_id, $target_type );
            $form_config = $repair_result['config'];
            $repairs = $repair_result['repairs'];
        }

        // Save the updated form
        $saved = $this->save_form_config( $form_id, $form_config );
        if ( ! $saved ) {
            wp_send_json_error( array( 'message' => __( 'Failed to save form.', 'bits-accessible-forms' ) ) );
        }

        wp_send_json_success( array(
            'field'   => $result['field'],
            'report'  => $result['report'],
            'repairs' => $repairs,
        ) );
    }

    /**
     * Get compatibility information for a source type
     */
    public function get_compatibility() {
        // Verify nonce
        if ( ! check_ajax_referer( 'baf_converter_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'bits-accessible-forms' ) ) );
        }

        $source_type = isset( $_POST['source_type'] ) ? sanitize_key( $_POST['source_type'] ) : '';

        if ( empty( $source_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing source type.', 'bits-accessible-forms' ) ) );
        }

        $converter = new BAF_Field_Type_Converter();
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
        if ( ! $form || 'baf_form' !== $form->post_type ) {
            return null;
        }

        $config = get_post_meta( $form_id, '_baf_form_config', true );
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
        return update_post_meta( $form_id, '_baf_form_config', $config );
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
new BAF_Field_Converter_Ajax();
