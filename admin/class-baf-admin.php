<?php
/**
 * Admin Class
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Admin class
 * 
 * Handles all admin functionality.
 */
class BAF_Admin {

    /**
     * Register settings using WP Settings API
     */
    public function register_settings() {
        register_setting(
            'baf_settings',
            BAF_Settings::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => BAF_Settings::get_instance()->get_defaults(),
            )
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Settings input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $defaults = BAF_Settings::get_instance()->get_defaults();
        $input    = is_array( $input ) ? $input : array();

        $sanitized = wp_parse_args( $input, $defaults );

        // Only minimal sanitization needed for new integration settings.
        if ( isset( $sanitized['google_maps_api_key'] ) ) {
            $sanitized['google_maps_api_key'] = sanitize_text_field( $sanitized['google_maps_api_key'] );
        }

        return $sanitized;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Nothing here - hooks added via loader
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __( 'BITS Accessible Forms', 'bits-accessible-forms' ),
            __( 'BAF Forms', 'bits-accessible-forms' ),
            'manage_options',
            'baf-forms',
            array( $this, 'render_forms_page' ),
            'dashicons-feedback',
            30
        );

        // Forms list (same as main)
        add_submenu_page(
            'baf-forms',
            __( 'All Forms', 'bits-accessible-forms' ),
            __( 'All Forms', 'bits-accessible-forms' ),
            'manage_options',
            'baf-forms',
            array( $this, 'render_forms_page' )
        );

        // Add New Form
        add_submenu_page(
            'baf-forms',
            __( 'Add New Form', 'bits-accessible-forms' ),
            __( 'Add New', 'bits-accessible-forms' ),
            'manage_options',
            'baf-form-builder',
            array( $this, 'render_form_builder_page' )
        );

        // Entries
        add_submenu_page(
            'baf-forms',
            __( 'Entries', 'bits-accessible-forms' ),
            __( 'Entries', 'bits-accessible-forms' ),
            'manage_options',
            'baf-entries',
            array( $this, 'render_entries_page' )
        );

        // Import from CSV
        add_submenu_page(
            'baf-forms',
            __( 'Import from CSV', 'bits-accessible-forms' ),
            __( 'Import CSV', 'bits-accessible-forms' ),
            'manage_options',
            'baf-csv-import',
            array( $this, 'render_csv_import_page' )
        );

        // Settings
        add_submenu_page(
            'baf-forms',
            __( 'Import and Export Forms', 'bits-accessible-forms' ),
            __( 'Import and Export', 'bits-accessible-forms' ),
            'manage_options',
            'baf-import-export',
            array( $this, 'render_import_export_page' )
        );

        add_submenu_page(
            'baf-forms',
            __( 'Settings', 'bits-accessible-forms' ),
            __( 'Settings', 'bits-accessible-forms' ),
            'manage_options',
            'baf-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin styles
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_styles( $hook ) {
        // Only load on our pages
        if ( strpos( $hook, 'baf' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'baf-admin',
            BAF_PLUGIN_URL . 'admin/css/baf-admin.css',
            array(),
            BAF_VERSION
        );

        // Form builder specific styles
        if ( strpos( $hook, 'baf-form-builder' ) !== false ) {
            wp_enqueue_style(
                'baf-form-builder',
                BAF_PLUGIN_URL . 'admin/css/baf-form-builder.css',
                array( 'baf-admin' ),
                BAF_VERSION
            );

            // Field type converter styles
            wp_enqueue_style(
                'baf-field-converter',
                BAF_PLUGIN_URL . 'admin/css/baf-field-converter.css',
                array( 'baf-admin' ),
                BAF_VERSION
            );
        }

        // CSV import wizard styles
        if ( strpos( $hook, 'baf-csv-import' ) !== false ) {
            wp_enqueue_style(
                'baf-csv-import',
                BAF_PLUGIN_URL . 'admin/css/baf-csv-import.css',
                array( 'baf-admin' ),
                BAF_VERSION
            );
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        // Only load on our pages
        if ( strpos( $hook, 'baf' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'baf-admin',
            BAF_PLUGIN_URL . 'admin/js/baf-admin.js',
            array( 'jquery', 'wp-util' ),
            BAF_VERSION,
            true
        );

        // Form builder specific scripts
        if ( strpos( $hook, 'baf-form-builder' ) !== false ) {
            wp_enqueue_script(
                'baf-form-builder',
                BAF_PLUGIN_URL . 'admin/js/baf-form-builder.js',
                array( 'jquery', 'jquery-ui-sortable', 'wp-util', 'baf-admin' ),
                BAF_VERSION,
                true
            );

            wp_enqueue_script(
                'baf-structure-mode',
                BAF_PLUGIN_URL . 'admin/js/baf-structure-mode.js',
                array( 'jquery', 'baf-form-builder' ),
                BAF_VERSION,
                true
            );

            // The BFL panel: Format Code and Sync to Visual, on the form model.
            wp_enqueue_script(
                'baf-bfl-mode',
                BAF_PLUGIN_URL . 'admin/js/baf-bfl-mode.js',
                array( 'baf-form-builder' ),
                BAF_VERSION,
                true
            );

            // Get field registry for builder
            $field_registry = BAF_Field_Registry::get_instance();

            wp_localize_script( 'baf-form-builder', 'bafBuilder', array(
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'baf_builder_nonce' ),
                'formId'      => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0,
                'fieldTypes'  => $field_registry->get_for_builder(),
                'i18n'        => array(
                    'confirmDelete'    => __( 'Are you sure you want to delete this field?', 'bits-accessible-forms' ),
                    'saved'            => __( 'Form saved successfully.', 'bits-accessible-forms' ),
                    'saveError'        => __( 'Error saving form. Please try again.', 'bits-accessible-forms' ),
                    'addField'         => __( 'Add Field', 'bits-accessible-forms' ),
                    'editField'        => __( 'Edit Field', 'bits-accessible-forms' ),
                    'deleteField'      => __( 'Delete Field', 'bits-accessible-forms' ),
                    'moveUp'           => __( 'Move Up', 'bits-accessible-forms' ),
                    'moveDown'         => __( 'Move Down', 'bits-accessible-forms' ),
                    'duplicate'        => __( 'Duplicate', 'bits-accessible-forms' ),
                    'untitledForm'     => __( 'Untitled Form', 'bits-accessible-forms' ),
                    'noFieldsYet'      => __( 'No fields yet. Add a field from the palette to get started.', 'bits-accessible-forms' ),
                    'bflError'         => __( 'Error', 'bits-accessible-forms' ),
                    'bflWarning'       => __( 'Warning', 'bits-accessible-forms' ),
                    'bflProblems'      => __( 'Problems in the BFL', 'bits-accessible-forms' ),
                    /* translators: %d: line number. */
                    'bflLine'          => __( 'line %d', 'bits-accessible-forms' ),
                    /* translators: %d: number of errors. */
                    'bflHasErrors'     => __( 'The BFL has %d errors. They are listed after the editor.', 'bits-accessible-forms' ),
                    'bflFormatted'     => __( 'Formatted.', 'bits-accessible-forms' ),
                    'bflSynced'        => __( 'Saved. Reloading the visual builder.', 'bits-accessible-forms' ),
                ),
            ) );

            // Field type converter script
            wp_enqueue_script(
                'baf-field-converter',
                BAF_PLUGIN_URL . 'admin/js/baf-field-converter.js',
                array( 'jquery', 'baf-form-builder' ),
                BAF_VERSION,
                true
            );

            // Get converter data
            $converter = new BAF_Field_Type_Converter();
            wp_localize_script( 'baf-field-converter', 'bafConverterData', array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'baf_converter_nonce' ),
                'compatibility' => $converter->get_compatibility_matrix(),
                'fieldTypes'    => $converter->get_all_field_types(),
            ) );

            // Accessibility Coach script
            wp_enqueue_script(
                'baf-accessibility-coach',
                BAF_PLUGIN_URL . 'admin/js/baf-accessibility-coach.js',
                array( 'jquery', 'baf-form-builder' ),
                BAF_VERSION,
                true
            );

            // Accessibility Coach styles
            wp_enqueue_style(
                'baf-accessibility-coach',
                BAF_PLUGIN_URL . 'admin/css/baf-accessibility-coach.css',
                array( 'baf-admin' ),
                BAF_VERSION
            );

            // Add coach i18n to builder data
            wp_localize_script( 'baf-form-builder', 'bafBuilderCoach', array(
                'accessibilityCoach' => __( 'Accessibility Coach', 'bits-accessible-forms' ),
                'refreshAnalysis'    => __( 'Refresh analysis', 'bits-accessible-forms' ),
                'closeCoach'         => __( 'Close accessibility coach', 'bits-accessible-forms' ),
                'accessibilityScore' => __( 'Accessibility Score', 'bits-accessible-forms' ),
                'errors'             => __( 'Errors', 'bits-accessible-forms' ),
                'warnings'           => __( 'Warnings', 'bits-accessible-forms' ),
                'suggestions'        => __( 'Suggestions', 'bits-accessible-forms' ),
                'analyzing'          => __( 'Analyzing...', 'bits-accessible-forms' ),
                'noIssuesFound'      => __( 'No accessibility issues found. Great job!', 'bits-accessible-forms' ),
                'autoAnalyze'        => __( 'Auto-analyze on changes', 'bits-accessible-forms' ),
                'suggestion'         => __( 'Suggestion', 'bits-accessible-forms' ),
                'coachOpened'        => __( 'Accessibility coach opened', 'bits-accessible-forms' ),
                'coachClosed'        => __( 'Accessibility coach closed', 'bits-accessible-forms' ),
                'analyzeError'       => __( 'Analysis failed. Please try again.', 'bits-accessible-forms' ),
            ) );
        }

        // Import and Export Forms
        if ( strpos( $hook, 'baf-import-export' ) !== false ) {
            wp_enqueue_script(
                'baf-import-export',
                BAF_PLUGIN_URL . 'admin/js/baf-import-export.js',
                array(),
                BAF_VERSION,
                true
            );
            wp_localize_script( 'baf-import-export', 'bafImportExport', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'baf_builder_nonce' ),
                'i18n'    => array(
                    'notCarried'      => __( 'Not carried over', 'bits-accessible-forms' ),
                    'changed'         => __( 'Changed', 'bits-accessible-forms' ),
                    'problems'        => __( 'Problems', 'bits-accessible-forms' ),
                    'toCheck'         => __( 'To check', 'bits-accessible-forms' ),
                    'notCarriedCount' => __( 'not carried over', 'bits-accessible-forms' ),
                    'changedCount'    => __( 'changed', 'bits-accessible-forms' ),
                    'problemsCount'   => __( 'problems', 'bits-accessible-forms' ),
                    'toCheckCount'    => __( 'to check', 'bits-accessible-forms' ),
                    'everything'      => __( 'Everything came across.', 'bits-accessible-forms' ),
                    'fileRead'        => __( 'The file is in the text box. Check it, then choose Import.', 'bits-accessible-forms' ),
                    'fileUnreadable'  => __( 'That file could not be read.', 'bits-accessible-forms' ),
                    'needExport'      => __( 'Paste the exported form, or choose its file.', 'bits-accessible-forms' ),
                    'importing'       => __( 'Importing...', 'bits-accessible-forms' ),
                    'failed'          => __( 'The import did not work.', 'bits-accessible-forms' ),
                    'importReport'    => __( 'Import report', 'bits-accessible-forms' ),
                    /* translators: 1: number of items, 2: form builder. */
                    'imported'        => __( 'Imported %1$d items from %2$s as a draft form.', 'bits-accessible-forms' ),
                    'openForm'        => __( 'Open the new form in the builder', 'bits-accessible-forms' ),
                    'reportFollows'   => __( 'The report follows the Import button.', 'bits-accessible-forms' ),
                    'exporting'       => __( 'Exporting...', 'bits-accessible-forms' ),
                    'exportFailed'    => __( 'The export did not work.', 'bits-accessible-forms' ),
                    'exportReport'    => __( 'Export report', 'bits-accessible-forms' ),
                    /* translators: 1: number of items, 2: form builder. */
                    'exported'        => __( 'Exported %1$d items to %2$s.', 'bits-accessible-forms' ),
                    'exportFollows'   => __( 'The report and the export follow the Export button.', 'bits-accessible-forms' ),
                    /* translators: %s: file name. */
                    'download'        => __( 'Download %s', 'bits-accessible-forms' ),
                    /* translators: %s: file name. */
                    'downloaded'      => __( 'Downloading %s.', 'bits-accessible-forms' ),
                    'copied'          => __( 'The export is copied.', 'bits-accessible-forms' ),
                    'copyFailed'      => __( 'Copying was refused; the export is selected for you to copy.', 'bits-accessible-forms' ),
                ),
            ) );
        }

        // CSV import wizard scripts
        if ( strpos( $hook, 'baf-csv-import' ) !== false ) {
            wp_enqueue_script(
                'baf-csv-import',
                BAF_PLUGIN_URL . 'admin/js/baf-csv-import.js',
                array( 'jquery', 'wp-util', 'baf-admin' ),
                BAF_VERSION,
                true
            );

            wp_localize_script( 'baf-csv-import', 'bafCsvImport', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'baf_csv_import' ),
                'i18n'    => array(
                    'uploading'      => __( 'Uploading...', 'bits-accessible-forms' ),
                    'analyzing'      => __( 'Analyzing...', 'bits-accessible-forms' ),
                    'creating'       => __( 'Creating form...', 'bits-accessible-forms' ),
                    'uploadError'    => __( 'Upload failed. Please try again.', 'bits-accessible-forms' ),
                    'analyzeError'   => __( 'Analysis failed.', 'bits-accessible-forms' ),
                    'createError'    => __( 'Failed to create form.', 'bits-accessible-forms' ),
                    'dropFile'       => __( 'Drop file here', 'bits-accessible-forms' ),
                    'copied'         => __( 'Copied to clipboard!', 'bits-accessible-forms' ),
                    'highConfidence' => __( 'High', 'bits-accessible-forms' ),
                    'medConfidence'  => __( 'Medium', 'bits-accessible-forms' ),
                    'lowConfidence'  => __( 'Low', 'bits-accessible-forms' ),
                ),
            ) );
        }
    }

    /**
     * Add plugin action links
     *
     * @param array $links Plugin action links.
     * @return array Modified links.
     */
    public function add_action_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=baf-settings' ) . '">' . 
                esc_html__( 'Settings', 'bits-accessible-forms' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Render forms list page
     */
    public function render_forms_page() {
        include BAF_PLUGIN_DIR . 'admin/partials/forms-list.php';
    }

    /**
     * Render form builder page
     */
    public function render_form_builder_page() {
        include BAF_PLUGIN_DIR . 'admin/partials/form-builder.php';
    }

    /**
     * Render entries page
     */
    public function render_entries_page() {
        include BAF_PLUGIN_DIR . 'admin/partials/entries-list.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include BAF_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    /**
     * Render CSV import wizard page
     */
    public function render_csv_import_page() {
        include BAF_PLUGIN_DIR . 'admin/partials/csv-import-wizard.php';
    }

    /**
     * Render the Import and Export Forms page
     */
    public function render_import_export_page() {
        include BAF_PLUGIN_DIR . 'admin/partials/import-export.php';
    }
}
