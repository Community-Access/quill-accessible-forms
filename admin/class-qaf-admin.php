<?php
/**
 * Admin Class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Admin class
 * 
 * Handles all admin functionality.
 */
class QAF_Admin {

    /**
     * Register settings using WP Settings API
     */
    public function register_settings() {
        register_setting(
            'qaf_settings',
            QAF_Settings::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => QAF_Settings::get_instance()->get_defaults(),
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
        $defaults = QAF_Settings::get_instance()->get_defaults();
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
            __( 'QUILL Accessible Forms', 'quill-accessible-forms' ),
            __( 'QAF Forms', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-forms',
            array( $this, 'render_forms_page' ),
            'dashicons-feedback',
            30
        );

        // Forms list (same as main)
        add_submenu_page(
            'qaf-forms',
            __( 'All Forms', 'quill-accessible-forms' ),
            __( 'All Forms', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-forms',
            array( $this, 'render_forms_page' )
        );

        // Add New Form
        add_submenu_page(
            'qaf-forms',
            __( 'Add New Form', 'quill-accessible-forms' ),
            __( 'Add New', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-form-builder',
            array( $this, 'render_form_builder_page' )
        );

        // Entries
        add_submenu_page(
            'qaf-forms',
            __( 'Entries', 'quill-accessible-forms' ),
            __( 'Entries', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-entries',
            array( $this, 'render_entries_page' )
        );

        // Import from CSV
        add_submenu_page(
            'qaf-forms',
            __( 'Import from CSV', 'quill-accessible-forms' ),
            __( 'Import CSV', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-csv-import',
            array( $this, 'render_csv_import_page' )
        );

        // Settings
        add_submenu_page(
            'qaf-forms',
            __( 'Import and Export Forms', 'quill-accessible-forms' ),
            __( 'Import and Export', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-import-export',
            array( $this, 'render_import_export_page' )
        );

        add_submenu_page(
            'qaf-forms',
            __( 'Settings', 'quill-accessible-forms' ),
            __( 'Settings', 'quill-accessible-forms' ),
            'manage_options',
            'qaf-settings',
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
        if ( strpos( $hook, 'qaf' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'qaf-admin',
            QAF_PLUGIN_URL . 'admin/css/qaf-admin.css',
            array(),
            QAF_VERSION
        );

        // Form builder specific styles
        if ( strpos( $hook, 'qaf-form-builder' ) !== false ) {
            wp_enqueue_style(
                'qaf-form-builder',
                QAF_PLUGIN_URL . 'admin/css/qaf-form-builder.css',
                array( 'qaf-admin' ),
                QAF_VERSION
            );

            // Field type converter styles
            wp_enqueue_style(
                'qaf-field-converter',
                QAF_PLUGIN_URL . 'admin/css/qaf-field-converter.css',
                array( 'qaf-admin' ),
                QAF_VERSION
            );
        }

        // CSV import wizard styles
        if ( strpos( $hook, 'qaf-csv-import' ) !== false ) {
            wp_enqueue_style(
                'qaf-csv-import',
                QAF_PLUGIN_URL . 'admin/css/qaf-csv-import.css',
                array( 'qaf-admin' ),
                QAF_VERSION
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
        if ( strpos( $hook, 'qaf' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'qaf-admin',
            QAF_PLUGIN_URL . 'admin/js/qaf-admin.js',
            array( 'jquery', 'wp-util' ),
            QAF_VERSION,
            true
        );

        // Form builder specific scripts
        if ( strpos( $hook, 'qaf-form-builder' ) !== false ) {
            wp_enqueue_script(
                'qaf-form-builder',
                QAF_PLUGIN_URL . 'admin/js/qaf-form-builder.js',
                array( 'jquery', 'jquery-ui-sortable', 'wp-util', 'qaf-admin' ),
                QAF_VERSION,
                true
            );

            wp_enqueue_script(
                'qaf-structure-mode',
                QAF_PLUGIN_URL . 'admin/js/qaf-structure-mode.js',
                array( 'jquery', 'qaf-form-builder' ),
                QAF_VERSION,
                true
            );

            // The BFL panel: Format Code and Sync to Visual, on the form model.
            wp_enqueue_script(
                'qaf-bfl-mode',
                QAF_PLUGIN_URL . 'admin/js/qaf-bfl-mode.js',
                array( 'qaf-form-builder' ),
                QAF_VERSION,
                true
            );

            // Rules, Quiz outcomes, Translations and Choice libraries: the
            // screens check expressions with the published form's evaluator.
            wp_enqueue_script(
                'qaf-admin-expression',
                QAF_PLUGIN_URL . 'public/js/qaf-expression.js',
                array(),
                QAF_VERSION,
                true
            );
            wp_enqueue_script(
                'qaf-model-editor-core',
                QAF_PLUGIN_URL . 'admin/js/qaf-model-editor-core.js',
                array( 'qaf-admin-expression' ),
                QAF_VERSION,
                true
            );
            wp_enqueue_script(
                'qaf-model-editor',
                QAF_PLUGIN_URL . 'admin/js/qaf-model-editor.js',
                array( 'qaf-model-editor-core' ),
                QAF_VERSION,
                true
            );
            wp_enqueue_script(
                'qaf-model-screens',
                QAF_PLUGIN_URL . 'admin/js/qaf-model-screens.js',
                array( 'qaf-model-editor' ),
                QAF_VERSION,
                true
            );
            // The Scoring section of a question's settings in the Visual mode.
            wp_enqueue_script(
                'qaf-field-scoring',
                QAF_PLUGIN_URL . 'admin/js/qaf-field-scoring.js',
                array( 'qaf-model-editor' ),
                QAF_VERSION,
                true
            );
            wp_localize_script( 'qaf-model-editor', 'qafModelEditor', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'qaf_builder_nonce' ),
                'formId'    => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0,
                'libraries' => class_exists( 'QAF_Choice_Libraries' ) ? QAF_Choice_Libraries::get_all() : array(),
                'i18n'      => require QAF_PLUGIN_DIR . 'admin/model-editor-i18n.php',
            ) );

            // Get field registry for builder
            $field_registry = QAF_Field_Registry::get_instance();

            wp_localize_script( 'qaf-form-builder', 'qafBuilder', array(
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'qaf_builder_nonce' ),
                'formId'      => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0,
                'fieldTypes'  => $field_registry->get_for_builder(),
                'i18n'        => array(
                    /* translators: %s: question label. */
                    'confirmDelete'    => __( '%s will be deleted from the form.', 'quill-accessible-forms' ),
                    'saved'            => __( 'Form saved successfully.', 'quill-accessible-forms' ),
                    'saveError'        => __( 'Error saving form. Please try again.', 'quill-accessible-forms' ),
                    'addField'         => __( 'Add Field', 'quill-accessible-forms' ),
                    'editField'        => __( 'Edit Field', 'quill-accessible-forms' ),
                    'deleteField'      => __( 'Delete Field', 'quill-accessible-forms' ),
                    'moveUp'           => __( 'Move Up', 'quill-accessible-forms' ),
                    'moveDown'         => __( 'Move Down', 'quill-accessible-forms' ),
                    'duplicate'        => __( 'Duplicate', 'quill-accessible-forms' ),
                    'untitledForm'     => __( 'Untitled Form', 'quill-accessible-forms' ),
                    'noFieldsYet'      => __( 'No fields yet. Add a field from the palette to get started.', 'quill-accessible-forms' ),
                    'bflError'         => __( 'Error', 'quill-accessible-forms' ),
                    'bflWarning'       => __( 'Warning', 'quill-accessible-forms' ),
                    'bflProblems'      => __( 'Problems in the QAF form language', 'quill-accessible-forms' ),
                    /* translators: %d: line number. */
                    'bflLine'          => __( 'line %d', 'quill-accessible-forms' ),
                    /* translators: %d: number of errors. */
                    'bflHasErrors'     => __( 'The QAF form language text has %d errors. They are listed after the editor.', 'quill-accessible-forms' ),
                    'bflFormatted'     => __( 'Formatted.', 'quill-accessible-forms' ),
                    'bflSynced'        => __( 'Saved. Reloading the visual builder.', 'quill-accessible-forms' ),
                    // The field cards (qaf-form-builder.js). %s, %d and %1$s are filled in by the script.
                    // phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
                    'addFailed'        => __( 'The question was not added.', 'quill-accessible-forms' ),
                    'alreadyAtBottom'  => __( '%s is already last.', 'quill-accessible-forms' ),
                    'alreadyAtTop'     => __( '%s is already first.', 'quill-accessible-forms' ),
                    'deleteFailed'     => __( 'The question was not deleted.', 'quill-accessible-forms' ),
                    'deleteRefused'    => __( '%1$s was not deleted. %2$s', 'quill-accessible-forms' ),
                    'deleteTitle'      => __( 'Delete question %d?', 'quill-accessible-forms' ),
                    'duplicateFailed'  => __( 'The question was not copied.', 'quill-accessible-forms' ),
                    'fieldAdded'       => __( '%1$s added, at position %2$d.', 'quill-accessible-forms' ),
                    'fieldDeleted'     => __( '%1$s deleted. %2$d questions left.', 'quill-accessible-forms' ),
                    'fieldDuplicated'  => __( '%1$s copied, as %2$s, at position %3$d.', 'quill-accessible-forms' ),
                    'fieldMoved'       => __( '%1$s moved to position %2$d of %3$d.', 'quill-accessible-forms' ),
                    'moveDownAt'       => __( 'Move %1$s down (currently position %2$d of %3$d)', 'quill-accessible-forms' ),
                    'moveFailed'       => __( 'The new order was not saved.', 'quill-accessible-forms' ),
                    'moveStays'        => __( '%s stays with its page or section. Move it to another page in Structure mode.', 'quill-accessible-forms' ),
                    'moveUpAt'         => __( 'Move %1$s up (currently position %2$d of %3$d)', 'quill-accessible-forms' ),
                    'needLabel'        => __( 'A question needs a label, so people know what it asks.', 'quill-accessible-forms' ),
                    'settingsFailed'   => __( 'The settings could not be opened.', 'quill-accessible-forms' ),
                    'settingsHeading'  => __( 'Settings: %s', 'quill-accessible-forms' ),
                    'settingsNotSaved' => __( 'The settings were not saved.', 'quill-accessible-forms' ),
                    'settingsSaved'    => __( 'Settings saved for %s.', 'quill-accessible-forms' ),
                    'convertChoose'    => __( 'Choose the type to change it to.', 'quill-accessible-forms' ),
                    'convertCurrent'   => __( 'It is now: %s.', 'quill-accessible-forms' ),
                    'convertFailed'    => __( 'It could not be converted.', 'quill-accessible-forms' ),
                    'convertNothing'   => __( 'Nothing else changes.', 'quill-accessible-forms' ),
                    'convertProblem'   => __( 'It could not be saved: %s', 'quill-accessible-forms' ),
                    'convertTitle'     => __( 'Convert %s', 'quill-accessible-forms' ),
                    'converted'        => __( '%1$s is now %2$s.', 'quill-accessible-forms' ),
                    'draftSaved'       => __( 'Saved as a draft.', 'quill-accessible-forms' ),
                    'formCreated'      => __( 'The form is made, as a draft. Every change is saved as you make it.', 'quill-accessible-forms' ),
                    'nTypes'           => __( '%d types.', 'quill-accessible-forms' ),
                    'oneType'          => __( '1 type.', 'quill-accessible-forms' ),
                    'published'        => __( 'Published. The form is live.', 'quill-accessible-forms' ),
                    'statusFailed'     => __( 'The form was not saved.', 'quill-accessible-forms' ),
                    // phpcs:enable
                ),
            ) );

            // Field type converter script
            wp_enqueue_script(
                'qaf-field-converter',
                QAF_PLUGIN_URL . 'admin/js/qaf-field-converter.js',
                array( 'jquery', 'qaf-form-builder' ),
                QAF_VERSION,
                true
            );

            // Get converter data
            $converter = new QAF_Field_Type_Converter();
            wp_localize_script( 'qaf-field-converter', 'qafConverterData', array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'qaf_converter_nonce' ),
                'compatibility' => $converter->get_compatibility_matrix(),
                'fieldTypes'    => $converter->get_all_field_types(),
            ) );

            // Accessibility Coach script
            wp_enqueue_script(
                'qaf-accessibility-coach',
                QAF_PLUGIN_URL . 'admin/js/qaf-accessibility-coach.js',
                array( 'jquery', 'qaf-form-builder' ),
                QAF_VERSION,
                true
            );

            // Accessibility Coach styles
            wp_enqueue_style(
                'qaf-accessibility-coach',
                QAF_PLUGIN_URL . 'admin/css/qaf-accessibility-coach.css',
                array( 'qaf-admin' ),
                QAF_VERSION
            );

            // Add coach i18n to builder data
            wp_localize_script( 'qaf-form-builder', 'qafBuilderCoach', array(
                'accessibilityCoach' => __( 'Accessibility Coach', 'quill-accessible-forms' ),
                'refreshAnalysis'    => __( 'Refresh analysis', 'quill-accessible-forms' ),
                'closeCoach'         => __( 'Close accessibility coach', 'quill-accessible-forms' ),
                'accessibilityScore' => __( 'Accessibility Score', 'quill-accessible-forms' ),
                'errors'             => __( 'Errors', 'quill-accessible-forms' ),
                'warnings'           => __( 'Warnings', 'quill-accessible-forms' ),
                'suggestions'        => __( 'Suggestions', 'quill-accessible-forms' ),
                'analyzing'          => __( 'Analyzing...', 'quill-accessible-forms' ),
                'noIssuesFound'      => __( 'No accessibility issues found. Great job!', 'quill-accessible-forms' ),
                'autoAnalyze'        => __( 'Auto-analyze on changes', 'quill-accessible-forms' ),
                'suggestion'         => __( 'Suggestion', 'quill-accessible-forms' ),
                'coachOpened'        => __( 'Accessibility coach opened', 'quill-accessible-forms' ),
                'coachClosed'        => __( 'Accessibility coach closed', 'quill-accessible-forms' ),
                'analyzeError'       => __( 'Analysis failed. Please try again.', 'quill-accessible-forms' ),
            ) );
        }

        // Import and Export Forms
        if ( strpos( $hook, 'qaf-import-export' ) !== false ) {
            wp_enqueue_script(
                'qaf-import-export',
                QAF_PLUGIN_URL . 'admin/js/qaf-import-export.js',
                array(),
                QAF_VERSION,
                true
            );
            wp_localize_script( 'qaf-import-export', 'qafImportExport', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'qaf_builder_nonce' ),
                'i18n'    => array(
                    'notCarried'      => __( 'Not carried over', 'quill-accessible-forms' ),
                    'changed'         => __( 'Changed', 'quill-accessible-forms' ),
                    'problems'        => __( 'Problems', 'quill-accessible-forms' ),
                    'toCheck'         => __( 'To check', 'quill-accessible-forms' ),
                    'notCarriedCount' => __( 'not carried over', 'quill-accessible-forms' ),
                    'changedCount'    => __( 'changed', 'quill-accessible-forms' ),
                    'problemsCount'   => __( 'problems', 'quill-accessible-forms' ),
                    'toCheckCount'    => __( 'to check', 'quill-accessible-forms' ),
                    'everything'      => __( 'Everything came across.', 'quill-accessible-forms' ),
                    'fileRead'        => __( 'The file is in the text box. Check it, then choose Import.', 'quill-accessible-forms' ),
                    'fileUnreadable'  => __( 'That file could not be read.', 'quill-accessible-forms' ),
                    'needExport'      => __( 'Paste the exported form, or choose its file.', 'quill-accessible-forms' ),
                    'importing'       => __( 'Importing...', 'quill-accessible-forms' ),
                    'failed'          => __( 'The import did not work.', 'quill-accessible-forms' ),
                    'importReport'    => __( 'Import report', 'quill-accessible-forms' ),
                    /* translators: 1: number of items, 2: form builder. */
                    'imported'        => __( 'Imported %1$d items from %2$s as a draft form.', 'quill-accessible-forms' ),
                    'openForm'        => __( 'Open the new form in the builder', 'quill-accessible-forms' ),
                    'reportFollows'   => __( 'The report follows the Import button.', 'quill-accessible-forms' ),
                    'exporting'       => __( 'Exporting...', 'quill-accessible-forms' ),
                    'exportFailed'    => __( 'The export did not work.', 'quill-accessible-forms' ),
                    'exportReport'    => __( 'Export report', 'quill-accessible-forms' ),
                    /* translators: 1: number of items, 2: form builder. */
                    'exported'        => __( 'Exported %1$d items to %2$s.', 'quill-accessible-forms' ),
                    'exportFollows'   => __( 'The report and the export follow the Export button.', 'quill-accessible-forms' ),
                    /* translators: %s: file name. */
                    'download'        => __( 'Download %s', 'quill-accessible-forms' ),
                    /* translators: %s: file name. */
                    'downloaded'      => __( 'Downloading %s.', 'quill-accessible-forms' ),
                    'copied'          => __( 'The export is copied.', 'quill-accessible-forms' ),
                    'copyFailed'      => __( 'Copying was refused; the export is selected for you to copy.', 'quill-accessible-forms' ),
                ),
            ) );
        }

        // CSV import wizard scripts
        if ( strpos( $hook, 'qaf-csv-import' ) !== false ) {
            wp_enqueue_script(
                'qaf-csv-import',
                QAF_PLUGIN_URL . 'admin/js/qaf-csv-import.js',
                array( 'jquery', 'wp-util', 'qaf-admin' ),
                QAF_VERSION,
                true
            );

            wp_localize_script( 'qaf-csv-import', 'qafCsvImport', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'qaf_csv_import' ),
                'i18n'    => array(
                    'uploading'      => __( 'Uploading...', 'quill-accessible-forms' ),
                    'analyzing'      => __( 'Analyzing...', 'quill-accessible-forms' ),
                    'creating'       => __( 'Creating form...', 'quill-accessible-forms' ),
                    'uploadError'    => __( 'Upload failed. Please try again.', 'quill-accessible-forms' ),
                    'analyzeError'   => __( 'Analysis failed.', 'quill-accessible-forms' ),
                    'createError'    => __( 'Failed to create form.', 'quill-accessible-forms' ),
                    'dropFile'       => __( 'Drop file here', 'quill-accessible-forms' ),
                    'copied'         => __( 'Copied to clipboard!', 'quill-accessible-forms' ),
                    'highConfidence' => __( 'High', 'quill-accessible-forms' ),
                    'medConfidence'  => __( 'Medium', 'quill-accessible-forms' ),
                    'lowConfidence'  => __( 'Low', 'quill-accessible-forms' ),
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
            '<a href="' . admin_url( 'admin.php?page=qaf-settings' ) . '">' . 
                esc_html__( 'Settings', 'quill-accessible-forms' ) . '</a>',
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * Render forms list page
     */
    public function render_forms_page() {
        include QAF_PLUGIN_DIR . 'admin/partials/forms-list.php';
    }

    /**
     * Render form builder page
     */
    public function render_form_builder_page() {
        include QAF_PLUGIN_DIR . 'admin/partials/form-builder.php';
    }

    /**
     * Render entries page
     */
    public function render_entries_page() {
        include QAF_PLUGIN_DIR . 'admin/partials/entries-list.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include QAF_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    /**
     * Render CSV import wizard page
     */
    public function render_csv_import_page() {
        include QAF_PLUGIN_DIR . 'admin/partials/csv-import-wizard.php';
    }

    /**
     * Render the Import and Export Forms page
     */
    public function render_import_export_page() {
        include QAF_PLUGIN_DIR . 'admin/partials/import-export.php';
    }
}
