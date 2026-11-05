<?php
/**
 * Integrations Manager
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Integrations class
 * 
 * Manages third-party integrations.
 */
class QAF_Integrations {

    /**
     * Registered integrations
     *
     * @var array
     */
    private $integrations = array();

    /**
     * Settings instance
     *
     * @var QAF_Settings
     */
    private $settings;

    /**
     * Singleton instance
     *
     * @var QAF_Integrations
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return QAF_Integrations Instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->settings = QAF_Settings::get_instance();
        $this->register_default_integrations();
    }

    /**
     * Register default integrations
     */
    private function register_default_integrations() {
        // Payment integrations
        $this->register( 'stripe', array(
            'name'        => __( 'Stripe', 'quill-accessible-forms' ),
            'description' => __( 'Accept credit card payments with Stripe.', 'quill-accessible-forms' ),
            'class'       => 'QAF_Integration_Stripe',
            'category'    => 'payment',
            'icon'        => 'dashicons-money-alt',
        ) );

        $this->register( 'paypal', array(
            'name'        => __( 'PayPal', 'quill-accessible-forms' ),
            'description' => __( 'Accept payments with PayPal.', 'quill-accessible-forms' ),
            'class'       => 'QAF_Integration_PayPal',
            'category'    => 'payment',
            'icon'        => 'dashicons-money',
        ) );

        // Email integrations
        $this->register( 'mailchimp', array(
            'name'        => __( 'Mailchimp', 'quill-accessible-forms' ),
            'description' => __( 'Subscribe users to Mailchimp lists.', 'quill-accessible-forms' ),
            'class'       => 'QAF_Integration_Mailchimp',
            'category'    => 'email',
            'icon'        => 'dashicons-email',
        ) );

        // CRM integrations
        $this->register( 'salesforce', array(
            'name'        => __( 'Salesforce', 'quill-accessible-forms' ),
            'description' => __( 'Send form data to Salesforce.', 'quill-accessible-forms' ),
            'class'       => 'QAF_Integration_Salesforce',
            'category'    => 'crm',
            'icon'        => 'dashicons-cloud',
        ) );

        // Notification integrations
        $this->register( 'slack', array(
            'name'        => __( 'Slack', 'quill-accessible-forms' ),
            'description' => __( 'Send notifications to Slack channels.', 'quill-accessible-forms' ),
            'class'       => 'QAF_Integration_Slack',
            'category'    => 'notification',
            'icon'        => 'dashicons-format-chat',
        ) );

        // Webhook integration
        $this->register( 'webhook', array(
            'name'        => __( 'Webhook', 'quill-accessible-forms' ),
            'description' => __( 'Send form data to custom webhooks.', 'quill-accessible-forms' ),
            'class'       => 'QAF_Integration_Webhook',
            'category'    => 'developer',
            'icon'        => 'dashicons-rest-api',
        ) );

        /**
         * Fires after default integrations are registered
         *
         * @param QAF_Integrations $integrations Integration manager instance.
         */
        do_action( 'qaf_register_integrations', $this );
    }

    /**
     * Register an integration
     *
     * @param string $id   Integration ID.
     * @param array  $args Integration arguments.
     */
    public function register( $id, $args ) {
        $defaults = array(
            'name'        => '',
            'description' => '',
            'class'       => '',
            'category'    => 'general',
            'icon'        => 'dashicons-admin-plugins',
            'settings'    => array(),
        );

        $this->integrations[ $id ] = wp_parse_args( $args, $defaults );
    }

    /**
     * Get all integrations
     *
     * @param string|null $category Optional category filter.
     * @return array Integrations.
     */
    public function get_all( $category = null ) {
        if ( $category ) {
            return array_filter( $this->integrations, function( $integration ) use ( $category ) {
                return $integration['category'] === $category;
            } );
        }

        return $this->integrations;
    }

    /**
     * Get an integration
     *
     * @param string $id Integration ID.
     * @return array|null Integration or null.
     */
    public function get( $id ) {
        return isset( $this->integrations[ $id ] ) ? $this->integrations[ $id ] : null;
    }

    /**
     * Check if integration is enabled
     *
     * @param string $id Integration ID.
     * @return bool True if enabled.
     */
    public function is_enabled( $id ) {
        return (bool) $this->settings->get( 'integration_' . $id . '_enabled', false );
    }

    /**
     * Get integration instance
     *
     * @param string $id Integration ID.
     * @return object|null Integration instance or null.
     */
    public function get_instance_by_id( $id ) {
        $integration = $this->get( $id );

        if ( ! $integration || empty( $integration['class'] ) ) {
            return null;
        }

        if ( ! class_exists( $integration['class'] ) ) {
            return null;
        }

        return new $integration['class']();
    }

    /**
     * Process form submission through integrations
     *
     * @param QAF_Entry $entry Entry object.
     * @param QAF_Form  $form  Form object.
     */
    public function process_submission( $entry, $form ) {
        $form_integrations = $form->get_setting( 'integrations', array() );

        foreach ( $form_integrations as $integration_id => $integration_config ) {
            if ( empty( $integration_config['enabled'] ) ) {
                continue;
            }

            $instance = $this->get_instance_by_id( $integration_id );

            if ( ! $instance ) {
                continue;
            }

            try {
                $instance->process( $entry, $form, $integration_config );
            } catch ( Exception $e ) {
                /**
                 * Fires when an integration fails
                 *
                 * @param string    $integration_id Integration ID.
                 * @param Exception $e              Exception object.
                 * @param QAF_Entry $entry          Entry object.
                 * @param QAF_Form  $form           Form object.
                 */
                do_action( 'qaf_integration_error', $integration_id, $e, $entry, $form );
            }
        }
    }

    /**
     * Get integration categories
     *
     * @return array Categories.
     */
    public function get_categories() {
        return array(
            'payment'      => __( 'Payment', 'quill-accessible-forms' ),
            'email'        => __( 'Email Marketing', 'quill-accessible-forms' ),
            'crm'          => __( 'CRM', 'quill-accessible-forms' ),
            'notification' => __( 'Notifications', 'quill-accessible-forms' ),
            'developer'    => __( 'Developer', 'quill-accessible-forms' ),
            'general'      => __( 'General', 'quill-accessible-forms' ),
        );
    }
}

/**
 * Abstract integration class
 */
abstract class QAF_Integration_Abstract {

    /**
     * Integration ID
     *
     * @var string
     */
    protected $id;

    /**
     * Settings
     *
     * @var QAF_Settings
     */
    protected $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = QAF_Settings::get_instance();
    }

    /**
     * Process a form submission
     *
     * @param QAF_Entry $entry  Entry object.
     * @param QAF_Form  $form   Form object.
     * @param array     $config Integration config for this form.
     */
    abstract public function process( $entry, $form, $config );

    /**
     * Get settings fields
     *
     * @return array Settings fields.
     */
    abstract public function get_settings_fields();

    /**
     * Validate settings
     *
     * @param array $settings Settings to validate.
     * @return true|WP_Error True or error.
     */
    public function validate_settings( $settings ) {
        return true;
    }

    /**
     * Test connection
     *
     * @return true|WP_Error True or error.
     */
    public function test_connection() {
        return new WP_Error( 'not_implemented', __( 'Connection test not implemented.', 'quill-accessible-forms' ) );
    }

    /**
     * Get integration setting
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed Setting value.
     */
    protected function get_setting( $key, $default = null ) {
        return $this->settings->get( 'integration_' . $this->id . '_' . $key, $default );
    }

    /**
     * Map form fields to integration fields
     *
     * @param QAF_Entry $entry   Entry object.
     * @param array     $mapping Field mapping.
     * @return array Mapped data.
     */
    protected function map_fields( $entry, $mapping ) {
        $field_values = $entry->get_field_values();
        $mapped = array();

        foreach ( $mapping as $integration_field => $form_field ) {
            if ( isset( $field_values[ $form_field ] ) ) {
                $mapped[ $integration_field ] = $field_values[ $form_field ];
            }
        }

        return $mapped;
    }
}
