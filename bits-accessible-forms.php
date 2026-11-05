<?php
/**
 * BITS Accessible Forms
 *
 * @package           BAF
 * @author            Blind Information Technology Solutions
 * @copyright         2026 BITS
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name:       BITS Accessible Forms
 * Plugin URI:        https://bitsolutions.org/accessible-forms
 * Description:       The most accessible form builder for WordPress. WCAG 2.2 AA compliant by default, featuring keyboard-first design, Structure Mode, and the revolutionary BFL (BITS Form Language) for forms as code.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Blind Information Technology Solutions
 * Author URI:        https://bitsolutions.org
 * Text Domain:       bits-accessible-forms
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
define( 'BAF_VERSION', '1.0.0' );

/**
 * Plugin directory path
 */
define( 'BAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL
 */
define( 'BAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename
 */
define( 'BAF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required
 */
define( 'BAF_MIN_PHP_VERSION', '8.0' );

/**
 * Minimum WordPress version required
 */
define( 'BAF_MIN_WP_VERSION', '6.0' );

/**
 * Check PHP and WordPress versions before loading
 */
function baf_check_requirements() {
    $errors = array();

    if ( version_compare( PHP_VERSION, BAF_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Current PHP version, 2: Required PHP version */
            __( 'BITS Accessible Forms requires PHP %2$s or higher. You are running PHP %1$s.', 'bits-accessible-forms' ),
            PHP_VERSION,
            BAF_MIN_PHP_VERSION
        );
    }

    global $wp_version;
    if ( version_compare( $wp_version, BAF_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Current WordPress version, 2: Required WordPress version */
            __( 'BITS Accessible Forms requires WordPress %2$s or higher. You are running WordPress %1$s.', 'bits-accessible-forms' ),
            $wp_version,
            BAF_MIN_WP_VERSION
        );
    }

    return $errors;
}

/**
 * Display admin notice for requirements not met
 */
function baf_requirements_notice() {
    $errors = baf_check_requirements();
    if ( ! empty( $errors ) ) {
        foreach ( $errors as $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }
    }
}

/**
 * Main plugin class
 */
final class BITS_Accessible_Forms {

    /**
     * Single instance
     *
     * @var BITS_Accessible_Forms
     */
    private static $instance = null;

    /**
     * Form loader
     *
     * @var BAF_Loader
     */
    public $loader;

    /**
     * Admin instance
     *
     * @var BAF_Admin
     */
    public $admin;

    /**
     * Public instance
     *
     * @var BAF_Public
     */
    public $public;

    /**
     * Form builder instance
     *
     * @var BAF_Form_Builder
     */
    public $form_builder;

    /**
     * BFL Parser instance
     *
     * @var BAF_BFL_Parser
     */
    public $bfl_parser;

    /**
     * Accessibility manager
     *
     * @var BAF_Accessibility_Manager
     */
    public $accessibility;

    /**
     * Field registry
     *
     * @var BAF_Field_Registry
     */
    public $field_registry;

    /**
     * Get single instance
     *
     * @return BITS_Accessible_Forms
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
        require_once BAF_PLUGIN_DIR . 'includes/class-baf-loader.php';
        require_once BAF_PLUGIN_DIR . 'includes/class-baf-i18n.php';
        require_once BAF_PLUGIN_DIR . 'includes/class-baf-choice-libraries.php';
        require_once BAF_PLUGIN_DIR . 'includes/class-baf-cascade-manager.php';
        
        // Core models
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-form.php';
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-field.php';
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-entry.php';
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-view.php';
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-settings.php';
        
        // Field registry and all field types
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-registry.php';
        
        // Specialty field types (competitive features)
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-currency.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-otp.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-typed-signature.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-slider.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-image-choice.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-matrix.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-rich-text.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-progress.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-summary.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-quiz.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-appointment.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-map.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-social.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-table.php';
        require_once BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-payment.php';
        
        // BFL (BITS Form Language)
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-lexer.php';
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-parser.php';
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-compiler.php';
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-validator.php';
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-exporter.php';
        
        // Field Templates
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-field-templates.php';
        
        // Accessibility
        require_once BAF_PLUGIN_DIR . 'includes/accessibility/class-baf-accessibility-manager.php';
        require_once BAF_PLUGIN_DIR . 'includes/accessibility/class-baf-aria-generator.php';
        require_once BAF_PLUGIN_DIR . 'includes/accessibility/class-baf-focus-manager.php';
        require_once BAF_PLUGIN_DIR . 'includes/accessibility/class-baf-announcer.php';
        
        // Security
        require_once BAF_PLUGIN_DIR . 'includes/security/class-baf-security.php';
        require_once BAF_PLUGIN_DIR . 'includes/security/class-baf-honeypot.php';
        require_once BAF_PLUGIN_DIR . 'includes/security/class-baf-rate-limiter.php';
        require_once BAF_PLUGIN_DIR . 'includes/security/class-baf-captcha.php';
        require_once BAF_PLUGIN_DIR . 'includes/security/class-baf-sanitizer.php';
        
        // Import/Export
        require_once BAF_PLUGIN_DIR . 'includes/import/class-baf-csv-importer.php';
        require_once BAF_PLUGIN_DIR . 'includes/import/class-baf-form-importer.php';
        require_once BAF_PLUGIN_DIR . 'includes/import/class-baf-csv-form-builder.php';
        
        // Field type converter (needed for both admin and public)
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-field-type-converter.php';
        
        // Autocomplete providers
        require_once BAF_PLUGIN_DIR . 'includes/autocomplete/interface-baf-autocomplete-provider.php';
        require_once BAF_PLUGIN_DIR . 'includes/autocomplete/class-baf-autocomplete-static.php';
        require_once BAF_PLUGIN_DIR . 'includes/autocomplete/class-baf-autocomplete-browser.php';
        require_once BAF_PLUGIN_DIR . 'includes/autocomplete/class-baf-autocomplete-google-places.php';
        require_once BAF_PLUGIN_DIR . 'includes/autocomplete/class-baf-autocomplete-fallback.php';
        require_once BAF_PLUGIN_DIR . 'includes/autocomplete/class-baf-autocomplete-registry.php';
        
        // Admin AJAX handlers (load early for AJAX)
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            require_once BAF_PLUGIN_DIR . 'includes/import/class-baf-csv-import-ajax.php';
            require_once BAF_PLUGIN_DIR . 'includes/admin/class-baf-field-converter-ajax.php';
            require_once BAF_PLUGIN_DIR . 'includes/admin/class-baf-accessibility-coach-ajax.php';
        }
        
        // Accessibility Coach
        require_once BAF_PLUGIN_DIR . 'includes/accessibility/class-baf-accessibility-coach.php';
        
        // Views
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-renderer.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-table.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-cards.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-list.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-map.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-calendar.php';
        
        // Integrations
        require_once BAF_PLUGIN_DIR . 'includes/integrations/class-baf-integrations.php';
        
        // API
        require_once BAF_PLUGIN_DIR . 'includes/api/class-baf-rest-api.php';
        
        // Admin
        if ( is_admin() ) {
            require_once BAF_PLUGIN_DIR . 'admin/class-baf-admin.php';
            require_once BAF_PLUGIN_DIR . 'admin/class-baf-form-builder.php';
            require_once BAF_PLUGIN_DIR . 'admin/class-baf-structure-mode.php';
        }
        
        // Public
        require_once BAF_PLUGIN_DIR . 'public/class-baf-public.php';
        require_once BAF_PLUGIN_DIR . 'public/class-baf-form-renderer.php';
        
        // Initialize loader
        $this->loader = new BAF_Loader();
        
        // Initialize core components
        $this->field_registry = BAF_Field_Registry::get_instance();
        $this->bfl_parser = new BAF_BFL_Parser();
        $this->accessibility = new BAF_Accessibility_Manager();
        
        // Initialize choice libraries and cascade manager
        BAF_Choice_Libraries::init();
        baf_cascade_manager();
    }

    /**
     * Set plugin locale for internationalization
     */
    private function set_locale() {
        $i18n = new BAF_i18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    /**
     * Register admin hooks
     */
    private function define_admin_hooks() {
        if ( ! is_admin() ) {
            return;
        }

        $this->admin = new BAF_Admin();
        $this->form_builder = new BAF_Form_Builder();

        // Admin menu and pages
        $this->loader->add_action( 'admin_menu', $this->admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
        
        // Form builder
        $this->loader->add_action( 'wp_ajax_baf_save_form', $this->form_builder, 'ajax_save_form' );
        $this->loader->add_action( 'wp_ajax_baf_get_field_settings', $this->form_builder, 'ajax_get_field_settings' );
        $this->loader->add_action( 'wp_ajax_baf_parse_bfl', $this->form_builder, 'ajax_parse_bfl' );
        $this->loader->add_action( 'wp_ajax_baf_compile_bfl', $this->form_builder, 'ajax_compile_bfl' );
        $this->loader->add_action( 'wp_ajax_baf_import_csv', $this->form_builder, 'ajax_import_csv' );
        
        // Settings API registration
        $this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );

        // Plugin action links
        $this->loader->add_filter( 'plugin_action_links_' . BAF_PLUGIN_BASENAME, $this->admin, 'add_action_links' );
    }

    /**
     * Register public hooks
     */
    private function define_public_hooks() {
        $this->public = new BAF_Public( 'bits-accessible-forms', BAF_VERSION );

        // Enqueue public assets
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );
        
        // Register shortcodes
        $this->loader->add_action( 'init', $this->public, 'register_shortcodes' );
        
        // Register Gutenberg blocks
        $this->loader->add_action( 'init', $this->public, 'register_blocks' );
        
        // Form submission handler
        $this->loader->add_action( 'wp_ajax_baf_submit_form', $this->public, 'handle_form_submission' );
        $this->loader->add_action( 'wp_ajax_nopriv_baf_submit_form', $this->public, 'handle_form_submission' );
        
        // Save and continue
        $this->loader->add_action( 'wp_ajax_baf_save_draft', $this->public, 'handle_save_draft' );
        $this->loader->add_action( 'wp_ajax_nopriv_baf_save_draft', $this->public, 'handle_save_draft' );
        
        // File uploads
        $this->loader->add_action( 'wp_ajax_baf_upload_file', $this->public, 'handle_file_upload' );
        $this->loader->add_action( 'wp_ajax_nopriv_baf_upload_file', $this->public, 'handle_file_upload' );
    }

    /**
     * Register REST API hooks
     */
    private function define_api_hooks() {
        $api = new BAF_REST_API();
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
        return BAF_VERSION;
    }
}

/**
 * Returns main plugin instance
 *
 * @return BITS_Accessible_Forms
 */
function BAF() {
    return BITS_Accessible_Forms::instance();
}

/**
 * Activation hook
 */
function baf_activate() {
    require_once BAF_PLUGIN_DIR . 'includes/class-baf-activator.php';
    BAF_Activator::activate();
}
register_activation_hook( __FILE__, 'baf_activate' );

/**
 * Deactivation hook
 */
function baf_deactivate() {
    require_once BAF_PLUGIN_DIR . 'includes/class-baf-deactivator.php';
    BAF_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'baf_deactivate' );

/**
 * Initialize plugin
 */
function baf_init() {
    $errors = baf_check_requirements();
    
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'baf_requirements_notice' );
        return;
    }
    
    // Start the plugin
    BAF()->run();
}
add_action( 'plugins_loaded', 'baf_init' );
