<?php
/**
 * CSV Import AJAX Handler
 *
 * Handles AJAX requests for the CSV Form Import wizard.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_CSV_Import_Ajax class
 * 
 * Provides AJAX endpoints for the CSV import wizard UI.
 */
class QAF_CSV_Import_Ajax {

    /**
     * Builder instance
     *
     * @var QAF_CSV_Form_Builder
     */
    private $builder;

    /**
     * Constructor - registers AJAX handlers
     */
    public function __construct() {
        add_action( 'wp_ajax_qaf_csv_upload', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_qaf_csv_analyze', array( $this, 'handle_analyze' ) );
        add_action( 'wp_ajax_qaf_csv_preview', array( $this, 'handle_preview' ) );
        add_action( 'wp_ajax_qaf_csv_create_form', array( $this, 'handle_create_form' ) );
        add_action( 'wp_ajax_qaf_csv_load_lookup', array( $this, 'handle_load_lookup' ) );
    }

    /**
     * Get builder instance
     *
     * @return QAF_CSV_Form_Builder
     */
    private function get_builder() {
        if ( ! $this->builder ) {
            if ( ! class_exists( 'QAF_CSV_Form_Builder' ) ) {
                require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-csv-form-builder.php';
            }
            $this->builder = new QAF_CSV_Form_Builder();
        }
        return $this->builder;
    }

    /**
     * Handle CSV file upload
     */
    public function handle_upload() {
        check_ajax_referer( 'qaf_csv_import', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied.', 'quill-accessible-forms' ),
            ) );
        }

        if ( empty( $_FILES['csv_file'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'No file uploaded.', 'quill-accessible-forms' ),
            ) );
        }

        $file = $_FILES['csv_file'];

        // Validate file type
        $allowed_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
        $finfo = wp_check_filetype( $file['name'] );

        if ( ! in_array( $file['type'], $allowed_types, true ) && $finfo['ext'] !== 'csv' ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid file type. Please upload a CSV file.', 'quill-accessible-forms' ),
            ) );
        }

        // Move to temp location
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/qaf-temp';

        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }

        $temp_file = $temp_dir . '/' . wp_unique_filename( $temp_dir, sanitize_file_name( $file['name'] ) );

        if ( ! move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to save uploaded file.', 'quill-accessible-forms' ),
            ) );
        }

        // Generate a secure token for this import session
        $token = wp_generate_password( 32, false );
        set_transient( 'qaf_csv_import_' . $token, $temp_file, HOUR_IN_SECONDS );

        wp_send_json_success( array(
            'token'    => $token,
            'filename' => basename( $file['name'] ),
            'size'     => size_format( $file['size'] ),
        ) );
    }

    /**
     * Handle CSV analysis
     */
    public function handle_analyze() {
        check_ajax_referer( 'qaf_csv_import', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied.', 'quill-accessible-forms' ),
            ) );
        }

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $file = get_transient( 'qaf_csv_import_' . $token );

        if ( ! $file || ! file_exists( $file ) ) {
            wp_send_json_error( array(
                'message' => __( 'Upload session expired. Please upload the file again.', 'quill-accessible-forms' ),
            ) );
        }

        // Get options
        $options = array(
            'delimiter'     => isset( $_POST['delimiter'] ) ? sanitize_text_field( $_POST['delimiter'] ) : ',',
            'has_header'    => isset( $_POST['has_header'] ) ? (bool) $_POST['has_header'] : true,
            'infer_types'   => isset( $_POST['infer_types'] ) ? (bool) $_POST['infer_types'] : true,
            'detect_choices' => isset( $_POST['detect_choices'] ) ? (bool) $_POST['detect_choices'] : true,
            'detect_cascades' => isset( $_POST['detect_cascades'] ) ? (bool) $_POST['detect_cascades'] : true,
        );

        $builder = $this->get_builder();
        $analysis = $builder->analyze( $file, $options );

        if ( is_wp_error( $analysis ) ) {
            wp_send_json_error( array(
                'message' => $analysis->get_error_message(),
            ) );
        }

        // Store analysis for later use
        set_transient( 'qaf_csv_analysis_' . $token, $analysis, HOUR_IN_SECONDS );

        // Get human-readable report
        $report = $builder->get_report( $analysis );

        wp_send_json_success( array(
            'analysis' => $analysis,
            'report'   => $report,
        ) );
    }

    /**
     * Handle form preview
     */
    public function handle_preview() {
        check_ajax_referer( 'qaf_csv_import', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied.', 'quill-accessible-forms' ),
            ) );
        }

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $analysis = get_transient( 'qaf_csv_analysis_' . $token );

        if ( ! $analysis ) {
            wp_send_json_error( array(
                'message' => __( 'Analysis session expired. Please start over.', 'quill-accessible-forms' ),
            ) );
        }

        // Apply any mapping customizations from the UI
        if ( ! empty( $_POST['column_config'] ) ) {
            $column_config = json_decode( stripslashes( $_POST['column_config'] ), true );
            if ( is_array( $column_config ) ) {
                $analysis = $this->apply_column_config( $analysis, $column_config );
            }
        }

        // Generate form options
        $form_options = array(
            'title'           => isset( $_POST['form_title'] ) 
                ? sanitize_text_field( $_POST['form_title'] ) 
                : __( 'Imported Form', 'quill-accessible-forms' ),
            'description'     => isset( $_POST['form_description'] ) 
                ? sanitize_textarea_field( $_POST['form_description'] ) 
                : '',
            'exclude_columns' => isset( $_POST['exclude_columns'] ) 
                ? array_map( 'absint', (array) $_POST['exclude_columns'] ) 
                : array(),
        );

        $builder = $this->get_builder();
        $form = $builder->generate_form( $analysis, $form_options );
        $bfl = $builder->generate_bfl( $form );

        wp_send_json_success( array(
            'form' => $form,
            'bfl'  => $bfl,
        ) );
    }

    /**
     * Handle form creation
     */
    public function handle_create_form() {
        check_ajax_referer( 'qaf_csv_import', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied.', 'quill-accessible-forms' ),
            ) );
        }

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $analysis = get_transient( 'qaf_csv_analysis_' . $token );
        $file = get_transient( 'qaf_csv_import_' . $token );

        if ( ! $analysis ) {
            wp_send_json_error( array(
                'message' => __( 'Session expired. Please start over.', 'quill-accessible-forms' ),
            ) );
        }

        // Apply column config
        if ( ! empty( $_POST['column_config'] ) ) {
            $column_config = json_decode( stripslashes( $_POST['column_config'] ), true );
            if ( is_array( $column_config ) ) {
                $analysis = $this->apply_column_config( $analysis, $column_config );
            }
        }

        // Generate form
        $form_options = array(
            'title'           => isset( $_POST['form_title'] ) 
                ? sanitize_text_field( $_POST['form_title'] ) 
                : __( 'Imported Form', 'quill-accessible-forms' ),
            'description'     => isset( $_POST['form_description'] ) 
                ? sanitize_textarea_field( $_POST['form_description'] ) 
                : '',
            'exclude_columns' => isset( $_POST['exclude_columns'] ) 
                ? array_map( 'absint', (array) $_POST['exclude_columns'] ) 
                : array(),
        );

        $builder = $this->get_builder();
        $form_config = $builder->generate_form( $analysis, $form_options );
        $bfl = $builder->generate_bfl( $form_config );

        // Create form using form importer
        if ( ! class_exists( 'QAF_Form_Importer' ) ) {
            require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-form-importer.php';
        }

        $importer = new QAF_Form_Importer();
        $result = $importer->import_bfl( $bfl );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        // Clean up temp files and transients
        if ( $file && file_exists( $file ) ) {
            unlink( $file );
        }
        delete_transient( 'qaf_csv_import_' . $token );
        delete_transient( 'qaf_csv_analysis_' . $token );

        wp_send_json_success( array(
            'form_id'  => $result->get_id(),
            'edit_url' => admin_url( 'admin.php?page=qaf-form-builder&form_id=' . $result->get_id() ),
            'message'  => __( 'Form created successfully!', 'quill-accessible-forms' ),
        ) );
    }

    /**
     * Handle lookup table loading
     */
    public function handle_load_lookup() {
        check_ajax_referer( 'qaf_csv_import', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Permission denied.', 'quill-accessible-forms' ),
            ) );
        }

        if ( empty( $_FILES['lookup_file'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'No lookup file uploaded.', 'quill-accessible-forms' ),
            ) );
        }

        $file = $_FILES['lookup_file'];
        $temp_file = $file['tmp_name'];

        $value_col = isset( $_POST['value_col'] ) ? absint( $_POST['value_col'] ) : 0;
        $label_col = isset( $_POST['label_col'] ) ? absint( $_POST['label_col'] ) : 1;
        $has_header = isset( $_POST['has_header'] ) ? (bool) $_POST['has_header'] : true;

        $builder = $this->get_builder();
        $choices = $builder->load_lookup_table( $temp_file, $value_col, $label_col, array(
            'has_header' => $has_header,
        ) );

        if ( is_wp_error( $choices ) ) {
            wp_send_json_error( array(
                'message' => $choices->get_error_message(),
            ) );
        }

        wp_send_json_success( array(
            'choices' => $choices,
            'count'   => count( $choices ),
        ) );
    }

    /**
     * Apply column configuration from UI
     *
     * @param array $analysis      Analysis data.
     * @param array $column_config Column configuration.
     * @return array Modified analysis.
     */
    private function apply_column_config( $analysis, $column_config ) {
        foreach ( $analysis['columns'] as $index => &$column ) {
            if ( isset( $column_config[ $index ] ) ) {
                $config = $column_config[ $index ];

                // Override field type
                if ( ! empty( $config['type'] ) ) {
                    $column['suggested_type'] = sanitize_key( $config['type'] );
                }

                // Override field ID
                if ( ! empty( $config['field_id'] ) ) {
                    $column['field_id'] = sanitize_key( $config['field_id'] );
                }

                // Override label
                if ( ! empty( $config['label'] ) ) {
                    $column['field_label'] = sanitize_text_field( $config['label'] );
                }

                // Override required
                if ( isset( $config['required'] ) ) {
                    $column['is_required'] = (bool) $config['required'];
                }

                // Apply custom choices
                if ( ! empty( $config['choices'] ) && is_array( $config['choices'] ) ) {
                    $column['choices'] = array_map( function( $choice ) {
                        return array(
                            'value' => sanitize_key( $choice['value'] ?? '' ),
                            'label' => sanitize_text_field( $choice['label'] ?? '' ),
                        );
                    }, $config['choices'] );
                }
            }
        }

        return $analysis;
    }
}

// Initialize AJAX handlers
new QAF_CSV_Import_Ajax();
