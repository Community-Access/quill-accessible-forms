<?php
/**
 * Public-facing functionality
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Public class
 * 
 * Handles public-facing form display and submission.
 */
class BAF_Public {

    /**
     * Plugin name
     *
     * @var string
     */
    private $plugin_name;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Form renderer
     *
     * @var BAF_Form_Renderer
     */
    private $renderer;

    /**
     * Constructor
     *
     * @param string $plugin_name Plugin name.
     * @param string $version     Plugin version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->renderer = new BAF_Form_Renderer();
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode( 'baf_form', array( $this, 'shortcode_form' ) );
        add_shortcode( 'baf_entries', array( $this, 'shortcode_entries' ) );
    }

    /**
     * Form shortcode handler
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function shortcode_form( $atts ) {
        $atts = shortcode_atts( array(
            'id'          => 0,
            'title'       => 'true',
            'description' => 'true',
            'ajax'        => 'true',
            'class'       => '',
        ), $atts, 'baf_form' );

        $form_id = intval( $atts['id'] );

        if ( ! $form_id ) {
            return '<p class="baf-error">' . esc_html__( 'Please specify a form ID.', 'bits-accessible-forms' ) . '</p>';
        }

        $form = new BAF_Form( $form_id );

        if ( ! $form->id || $form->status !== 'active' ) {
            return '<p class="baf-error">' . esc_html__( 'Form not found or inactive.', 'bits-accessible-forms' ) . '</p>';
        }

        return $this->renderer->render( $form, array(
            'show_title'       => filter_var( $atts['title'], FILTER_VALIDATE_BOOLEAN ),
            'show_description' => filter_var( $atts['description'], FILTER_VALIDATE_BOOLEAN ),
            'ajax'             => filter_var( $atts['ajax'], FILTER_VALIDATE_BOOLEAN ),
            'class'            => sanitize_html_class( $atts['class'] ),
        ) );
    }

    /**
     * Entries shortcode handler
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function shortcode_entries( $atts ) {
        $atts = shortcode_atts( array(
            'form_id' => 0,
            'view'    => 'table',
            'user'    => '',
            'limit'   => 10,
            'class'   => '',
        ), $atts, 'baf_entries' );

        $form_id = intval( $atts['form_id'] );

        if ( ! $form_id ) {
            return '<p class="baf-error">' . esc_html__( 'Please specify a form ID.', 'bits-accessible-forms' ) . '</p>';
        }

        // Check if user should only see their own entries
        $user_id = null;
        if ( $atts['user'] === 'current' && is_user_logged_in() ) {
            $user_id = get_current_user_id();
        }

        $entries = BAF_Entry::get_for_form( $form_id, array(
            'user_id' => $user_id,
            'status'  => 'active',
            'limit'   => intval( $atts['limit'] ),
        ) );

        // Get view renderer
        $view = BAF_View_Renderer::factory( $atts['view'] );
        $view->set_entries( $entries );

        return $view->render();
    }

    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            BAF_PLUGIN_URL . 'public/css/baf-public.css',
            array(),
            $this->version
        );

        // Enqueue high contrast stylesheet if needed
        if ( $this->is_high_contrast_mode() ) {
            wp_enqueue_style(
                $this->plugin_name . '-high-contrast',
                BAF_PLUGIN_URL . 'public/css/baf-high-contrast.css',
                array( $this->plugin_name ),
                $this->version
            );
        }
    }

    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        // The expression language and the model's runtime, the same rules the
        // server applies, for forms saved with a model.
        wp_register_script(
            $this->plugin_name . '-expression',
            BAF_PLUGIN_URL . 'public/js/baf-expression.js',
            array(),
            $this->version,
            true
        );
        wp_register_script(
            $this->plugin_name . '-form-model',
            BAF_PLUGIN_URL . 'public/js/baf-form-model.js',
            array( $this->plugin_name . '-expression' ),
            $this->version,
            true
        );
        wp_enqueue_script(
            $this->plugin_name,
            BAF_PLUGIN_URL . 'public/js/baf-public.js',
            array( 'jquery', $this->plugin_name . '-form-model' ),
            $this->version,
            true
        );

        // Localize script
        $settings = BAF_Settings::get_instance();
        wp_localize_script( $this->plugin_name, 'bafPublic', array(
            'ajaxUrl'  => rest_url( 'baf/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'google'   => array(
                'mapsApiKey' => $settings->get( 'google_maps_api_key', '' ),
            ),
            'i18n'     => array(
                'submitting'     => __( 'Submitting...', 'bits-accessible-forms' ),
                'success'        => __( 'Form submitted successfully!', 'bits-accessible-forms' ),
                'error'          => __( 'An error occurred. Please try again.', 'bits-accessible-forms' ),
                'required'       => __( 'This field is required.', 'bits-accessible-forms' ),
                'invalidEmail'   => __( 'Please enter a valid email address.', 'bits-accessible-forms' ),
                'invalidPhone'   => __( 'Please enter a valid phone number.', 'bits-accessible-forms' ),
                'invalidUrl'     => __( 'Please enter a valid URL.', 'bits-accessible-forms' ),
                'fileTooLarge'   => __( 'File is too large.', 'bits-accessible-forms' ),
                'invalidType'    => __( 'File type not allowed.', 'bits-accessible-forms' ),
                'minLength'      => __( 'Please enter at least %d characters.', 'bits-accessible-forms' ),
                'maxLength'      => __( 'Please enter no more than %d characters.', 'bits-accessible-forms' ),
                'pageProgress'   => __( 'Page %1$d of %2$d', 'bits-accessible-forms' ),
                'pageChanged'    => __( 'Now on page %1$d of %2$d.', 'bits-accessible-forms' ),
                'validationErrors' => __( 'Please correct the errors before submitting.', 'bits-accessible-forms' ),
                /* translators: %d: number of questions that appeared. */
                'questionsShown' => __( '%d more questions shown.', 'bits-accessible-forms' ),
                'questionShown'  => __( '1 more question shown.', 'bits-accessible-forms' ),
                /* translators: %d: number of questions that were hidden. */
                'questionsHidden' => __( '%d questions hidden.', 'bits-accessible-forms' ),
                'questionHidden' => __( '1 question hidden.', 'bits-accessible-forms' ),
                /* translators: %s: the quiz score. */
                'yourScore'      => __( 'Your score: %s.', 'bits-accessible-forms' ),
            ),
        ) );

        // Enqueue accessibility scripts
        wp_enqueue_script(
            $this->plugin_name . '-a11y',
            BAF_PLUGIN_URL . 'public/js/baf-accessibility.js',
            array( $this->plugin_name ),
            $this->version,
            true
        );
    }

    /**
     * Check if high contrast mode is active
     *
     * @return bool True if high contrast.
     */
    private function is_high_contrast_mode() {
        // Check user preference cookie
        if ( isset( $_COOKIE['baf_high_contrast'] ) && $_COOKIE['baf_high_contrast'] === '1' ) {
            return true;
        }

        // Check system preference via JS (handled client-side)
        return false;
    }

    /**
     * Handle AJAX form submission (legacy support)
     */
    public function ajax_submit_form() {
        check_ajax_referer( 'baf_submit_form', '_baf_nonce' );

        $form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
        $form = new BAF_Form( $form_id );

        if ( ! $form->id || $form->status !== 'active' ) {
            wp_send_json_error( array(
                'message' => __( 'Form not found or inactive.', 'bits-accessible-forms' ),
            ) );
        }

        // Process submission via REST API handler for consistency
        $request = new WP_REST_Request( 'POST', '/baf/v1/submit/' . $form_id );
        $request->set_param( 'fields', isset( $_POST['fields'] ) ? $_POST['fields'] : array() );

        $rest_api = new BAF_REST_API();
        $response = $rest_api->submit_form( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array(
                'message' => $response->get_error_message(),
            ) );
        }

        wp_send_json_success( $response->get_data() );
    }

    /**
     * Add form block
     */
    public function register_blocks() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'baf/form', array(
            'editor_script'   => 'baf-block-editor',
            'render_callback' => array( $this, 'render_form_block' ),
            'attributes'      => array(
                'formId' => array(
                    'type'    => 'number',
                    'default' => 0,
                ),
                'showTitle' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'showDescription' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
            ),
        ) );
    }

    /**
     * Render form block
     *
     * @param array $attributes Block attributes.
     * @return string HTML output.
     */
    public function render_form_block( $attributes ) {
        return $this->shortcode_form( array(
            'id'          => $attributes['formId'],
            'title'       => $attributes['showTitle'] ? 'true' : 'false',
            'description' => $attributes['showDescription'] ? 'true' : 'false',
        ) );
    }
}
