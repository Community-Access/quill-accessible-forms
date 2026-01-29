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

        // Settings
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
}
