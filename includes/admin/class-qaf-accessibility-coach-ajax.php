<?php
/**
 * Accessibility Coach AJAX Handler
 *
 * Handles AJAX requests for accessibility analysis.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Accessibility_Coach_Ajax class
 */
class QAF_Accessibility_Coach_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_qaf_analyze_accessibility', array( $this, 'analyze_accessibility' ) );
        add_action( 'wp_ajax_qaf_analyze_field_accessibility', array( $this, 'analyze_field' ) );
    }

    /**
     * Analyze full form accessibility
     */
    public function analyze_accessibility() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $form_config = isset( $_POST['form_config'] ) ? json_decode( stripslashes( $_POST['form_config'] ), true ) : array();

        if ( empty( $form_config ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid form configuration.', 'quill-accessible-forms' ) ) );
        }

        $coach = new QAF_Accessibility_Coach();
        $result = $coach->analyze( $form_config );

        wp_send_json_success( $result );
    }

    /**
     * Analyze single field accessibility
     */
    public function analyze_field() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $field = isset( $_POST['field'] ) ? json_decode( stripslashes( $_POST['field'] ), true ) : array();

        if ( empty( $field ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid field configuration.', 'quill-accessible-forms' ) ) );
        }

        $coach = new QAF_Accessibility_Coach();
        $issues = $coach->analyze_field( $field, 0 );

        wp_send_json_success( array(
            'issues' => $issues,
            'count'  => count( $issues ),
        ) );
    }
}

// Initialize
new QAF_Accessibility_Coach_Ajax();
