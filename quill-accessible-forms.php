<?php
/**
 * QUILL Accessible Forms
 *
 * @package           QAF
 * @author            Jeff Bishop
 * @copyright         Copyright (c) 2024-2026 Jeff Bishop
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name:       QUILL Accessible Forms
 * Plugin URI:        https://bitsolutions.org/accessible-forms
 * Description:       The most accessible form builder for WordPress. WCAG 2.2 AA compliant by default, featuring keyboard-first design, Structure Mode, and the QAF form language for forms as code (.qaf files).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jeff Bishop
 * Author URI:        https://bitsolutions.org
 * Text Domain:       quill-accessible-forms
 * Domain Path:       /languages
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version
 */
define( 'QAF_VERSION', '1.0.0' );

/**
 * Plugin directory path
 */
define( 'QAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL
 */
define( 'QAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename
 */
define( 'QAF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required
 */
define( 'QAF_MIN_PHP_VERSION', '8.0' );

/**
 * Minimum WordPress version required
 */
define( 'QAF_MIN_WP_VERSION', '6.0' );

/**
 * Check PHP and WordPress versions before loading
 */
function qaf_check_requirements() {
    $errors = array();

    if ( version_compare( PHP_VERSION, QAF_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Current PHP version, 2: Required PHP version */
            __( 'QUILL Accessible Forms requires PHP %2$s or higher. You are running PHP %1$s.', 'quill-accessible-forms' ),
            PHP_VERSION,
            QAF_MIN_PHP_VERSION
        );
    }

    global $wp_version;
    if ( version_compare( $wp_version, QAF_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Current WordPress version, 2: Required WordPress version */
            __( 'QUILL Accessible Forms requires WordPress %2$s or higher. You are running WordPress %1$s.', 'quill-accessible-forms' ),
            $wp_version,
            QAF_MIN_WP_VERSION
        );
    }

    return $errors;
}

/**
 * Display admin notice for requirements not met
 */
function qaf_requirements_notice() {
    $errors = qaf_check_requirements();
    if ( ! empty( $errors ) ) {
        foreach ( $errors as $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }
    }
}

/**
 * Main plugin class
 */
final class QUILL_Accessible_Forms {

    /**
     * Single instance
     *
     * @var QUILL_Accessible_Forms
     */
    private static $instance = null;

    /**
     * Form loader
     *
     * @var QAF_Loader
     */
    public $loader;

    /**
     * Admin instance
     *
     * @var QAF_Admin
     */
    public $admin;

    /**
     * Public instance
     *
     * @var QAF_Public
     */
    public $public;

    /**
     * Form builder instance
     *
     * @var QAF_Form_Builder
     */
    public $form_builder;

    /**
     * BFL Parser instance
     *
     * @var QAF_BFL_Parser
     */
    public $bfl_parser;

    /**
     * Accessibility manager
     *
     * @var QAF_Accessibility_Manager
     */
    public $accessibility;

    /**
     * Field registry
     *
     * @var QAF_Field_Registry
     */
    public $field_registry;

    /**
     * Get single instance
     *
     * @return QUILL_Accessible_Forms
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to enforce singleton
     */
    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once QAF_PLUGIN_DIR . 'includes/class-qaf-loader.php';
        require_once QAF_PLUGIN_DIR . 'includes/class-qaf-i18n.php';
        require_once QAF_PLUGIN_DIR . 'includes/class-qaf-choice-libraries.php';
        require_once QAF_PLUGIN_DIR . 'includes/class-qaf-cascade-manager.php';
        
        // Core models
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-form.php';
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-entry.php';
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-view.php';
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-settings.php';
        
        // Field registry and all field types
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-registry.php';
        
        // Specialty field types (competitive features)
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-currency.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-otp.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-typed-signature.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-slider.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-image-choice.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-matrix.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-rich-text.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-progress.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-summary.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-quiz.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-appointment.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-map.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-social.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-table.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-payment.php';
        
        // The QAF form language (the older parser, for the older file importer)
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-lexer.php';
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-parser.php';
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-compiler.php';
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-validator.php';
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-exporter.php';

        // The form model, its expression language, BFL as its notation, and the
        // converters to and from QAF's stored form and other form builders.
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-reader.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-writer.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model-converter.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-runtime.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-service.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-field-scoring-panel.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-field-ops.php';

        // Field Templates
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field-templates.php';
        
        // Accessibility
        require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-accessibility-manager.php';
        require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-aria-generator.php';
        require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-focus-manager.php';
        require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-announcer.php';
        
        // Security
        require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-security.php';
        require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-honeypot.php';
        require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-rate-limiter.php';
        require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-captcha.php';
        require_once QAF_PLUGIN_DIR . 'includes/security/class-qaf-sanitizer.php';
        
        // Import/Export
        require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-csv-importer.php';
        require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-form-importer.php';
        require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-csv-form-builder.php';
        
        // Field type converter (needed for both admin and public)
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field-type-converter.php';
        
        // Autocomplete providers
        require_once QAF_PLUGIN_DIR . 'includes/autocomplete/interface-qaf-autocomplete-provider.php';
        require_once QAF_PLUGIN_DIR . 'includes/autocomplete/class-qaf-autocomplete-static.php';
        require_once QAF_PLUGIN_DIR . 'includes/autocomplete/class-qaf-autocomplete-browser.php';
        require_once QAF_PLUGIN_DIR . 'includes/autocomplete/class-qaf-autocomplete-google-places.php';
        require_once QAF_PLUGIN_DIR . 'includes/autocomplete/class-qaf-autocomplete-fallback.php';
        require_once QAF_PLUGIN_DIR . 'includes/autocomplete/class-qaf-autocomplete-registry.php';
        
        // Admin AJAX handlers (load early for AJAX)
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-csv-import-ajax.php';
            require_once QAF_PLUGIN_DIR . 'includes/admin/class-qaf-field-converter-ajax.php';
            require_once QAF_PLUGIN_DIR . 'includes/admin/class-qaf-accessibility-coach-ajax.php';
        }
        
        // Accessibility Coach
        require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-accessibility-coach.php';
        
        // Views
        require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-renderer.php';
        require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-table.php';
        require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-cards.php';
        require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-list.php';
        require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-map.php';
        require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-calendar.php';
        
        // Integrations
        require_once QAF_PLUGIN_DIR . 'includes/integrations/class-qaf-integrations.php';
        
        // API
        require_once QAF_PLUGIN_DIR . 'includes/api/class-qaf-rest-api.php';
        // baf/v1, the old namespace, answers GET for one release.
        require_once QAF_PLUGIN_DIR . 'includes/api/class-qaf-legacy-rest.php';
        require_once QAF_PLUGIN_DIR . 'includes/class-qaf-migrator.php';
        
        // Admin
        if ( is_admin() ) {
            require_once QAF_PLUGIN_DIR . 'admin/class-qaf-admin.php';
            require_once QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php';
            require_once QAF_PLUGIN_DIR . 'admin/class-qaf-structure-mode.php';
        }
        
        // Public
        require_once QAF_PLUGIN_DIR . 'public/class-qaf-public.php';
        require_once QAF_PLUGIN_DIR . 'public/class-qaf-form-renderer.php';
        
        // Initialize loader
        $this->loader = new QAF_Loader();
        
        // Initialize core components
        $this->field_registry = QAF_Field_Registry::get_instance();
        $this->bfl_parser = new QAF_BFL_Parser();
        $this->accessibility = new QAF_Accessibility_Manager();
        
        // Initialize choice libraries and cascade manager
        QAF_Choice_Libraries::init();
        qaf_cascade_manager();
    }

    /**
     * Set plugin locale for internationalization
     */
    private function set_locale() {
        $i18n = new QAF_i18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        if ( ! is_admin() ) {
            return;
        }

        $this->admin = new QAF_Admin();
        $this->form_builder = new QAF_Form_Builder();

        // Admin menu and pages
        $this->loader->add_action( 'admin_menu', $this->admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
        
        // Form builder
        $this->loader->add_action( 'wp_ajax_qaf_save_form', $this->form_builder, 'ajax_save_form' );
        $this->loader->add_action( 'wp_ajax_qaf_get_field_settings', $this->form_builder, 'ajax_get_field_settings' );
        $this->loader->add_action( 'wp_ajax_qaf_parse_bfl', $this->form_builder, 'ajax_parse_bfl' );
        $this->loader->add_action( 'wp_ajax_qaf_compile_bfl', $this->form_builder, 'ajax_compile_bfl' );
        $this->loader->add_action( 'wp_ajax_qaf_format_bfl', $this->form_builder, 'ajax_format_bfl' );
        $this->loader->add_action( 'wp_ajax_qaf_get_form_model', $this->form_builder, 'ajax_get_form_model' );
        $this->loader->add_action( 'wp_ajax_qaf_save_form_model', $this->form_builder, 'ajax_save_form_model' );
        $this->loader->add_action( 'wp_ajax_qaf_import_form', $this->form_builder, 'ajax_import_form' );
        $this->loader->add_action( 'wp_ajax_qaf_export_form', $this->form_builder, 'ajax_export_form' );
        $this->loader->add_action( 'wp_ajax_qaf_read_choice_lines', $this->form_builder, 'ajax_read_choice_lines' );
        $this->loader->add_action( 'wp_ajax_qaf_get_choice_library', $this->form_builder, 'ajax_get_choice_library' );
        $this->loader->add_action( 'wp_ajax_qaf_get_field_scoring', $this->form_builder, 'ajax_get_field_scoring' );
        // The Visual mode's field cards: each changes the model and saves it.
        $this->loader->add_action( 'wp_ajax_qaf_add_field', $this->form_builder, 'ajax_add_field' );
        $this->loader->add_action( 'wp_ajax_qaf_duplicate_field', $this->form_builder, 'ajax_duplicate_field' );
        $this->loader->add_action( 'wp_ajax_qaf_delete_field', $this->form_builder, 'ajax_delete_field' );
        $this->loader->add_action( 'wp_ajax_qaf_update_field_order', $this->form_builder, 'ajax_update_field_order' );
        $this->loader->add_action( 'wp_ajax_qaf_save_field_settings', $this->form_builder, 'ajax_save_field_settings' );
        $this->loader->add_action( 'wp_ajax_qaf_convert_question', $this->form_builder, 'ajax_convert_question' );
        $this->loader->add_action( 'wp_ajax_qaf_save_form_status', $this->form_builder, 'ajax_save_form_status' );
        $this->loader->add_action( 'wp_ajax_qaf_import_csv', $this->form_builder, 'ajax_import_csv' );
        
        // Settings API registration
        $this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );

        // Plugin action links
        $this->loader->add_filter( 'plugin_action_links_' . QAF_PLUGIN_BASENAME, $this->admin, 'add_action_links' );
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks() {
        $this->public = new QAF_Public( 'quill-accessible-forms', QAF_VERSION );

        // Enqueue public assets
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );
        
        // Register shortcodes
        $this->loader->add_action( 'init', $this->public, 'register_shortcodes' );
        
        // Register Gutenberg blocks
        $this->loader->add_action( 'init', $this->public, 'register_blocks' );
        
        // Form submission handler
        $this->loader->add_action( 'wp_ajax_qaf_submit_form', $this->public, 'handle_form_submission' );
        $this->loader->add_action( 'wp_ajax_nopriv_qaf_submit_form', $this->public, 'handle_form_submission' );
        
        // Save and continue
        $this->loader->add_action( 'wp_ajax_qaf_save_draft', $this->public, 'handle_save_draft' );
        $this->loader->add_action( 'wp_ajax_nopriv_qaf_save_draft', $this->public, 'handle_save_draft' );
        
        // File uploads
        $this->loader->add_action( 'wp_ajax_qaf_upload_file', $this->public, 'handle_file_upload' );
        $this->loader->add_action( 'wp_ajax_nopriv_qaf_upload_file', $this->public, 'handle_file_upload' );
    }

    /**
     * Register REST API hooks
     */
    private function define_api_hooks() {
        $api = new QAF_REST_API();
        $this->loader->add_action( 'rest_api_init', $api, 'register_routes' );
    }

    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return QAF_VERSION;
    }
}

/**
 * Returns main plugin instance
 *
 * @return QUILL_Accessible_Forms
 */
function QAF() {
    return QUILL_Accessible_Forms::instance();
}

/**
 * Activation hook
 */
function qaf_activate() {
    // Bring a BITS Accessible Forms site's data across first, so the tables
    // the activator creates do not stand in for the old ones (see the class).
    require_once QAF_PLUGIN_DIR . 'includes/class-qaf-migrator.php';
    QAF_Migrator::on_activate();
    require_once QAF_PLUGIN_DIR . 'includes/class-qaf-activator.php';
    QAF_Activator::activate();
}
register_activation_hook( __FILE__, 'qaf_activate' );

/**
 * Deactivation hook
 */
function qaf_deactivate() {
    require_once QAF_PLUGIN_DIR . 'includes/class-qaf-deactivator.php';
    QAF_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'qaf_deactivate' );

/**
 * Initialize plugin
 */
function qaf_init() {
    $errors = qaf_check_requirements();
    
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'qaf_requirements_notice' );
        return;
    }
    
    // Start the plugin
    QAF()->run();
}
add_action( 'plugins_loaded', 'qaf_init' );

/**
 * The old name, for one release: baf/v1 answers GET, and a site whose files
 * were replaced without activating again is migrated once.
 */
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'QAF_Legacy_REST' ) ) {
        QAF_Legacy_REST::init();
    }
} );
add_action( 'admin_init', function () {
    if ( class_exists( 'QAF_Migrator' ) && current_user_can( 'activate_plugins' ) ) {
        QAF_Migrator::maybe_run();
    }
} );
