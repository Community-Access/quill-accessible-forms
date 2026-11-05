<?php
/**
 * Autocomplete Provider Registry
 *
 * Manages registration and access to autocomplete providers.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Autocomplete_Registry class
 */
class QAF_Autocomplete_Registry {

    /**
     * Singleton instance
     *
     * @var QAF_Autocomplete_Registry
     */
    private static $instance = null;

    /**
     * Registered providers
     *
     * @var array
     */
    private $providers = array();

    /**
     * Get singleton instance
     *
     * @return QAF_Autocomplete_Registry
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
        $this->register_core_providers();

        /**
         * Allow plugins to register custom providers
         *
         * @param QAF_Autocomplete_Registry $registry Registry instance.
         */
        do_action( 'qaf_register_autocomplete_providers', $this );
    }

    /**
     * Register core providers
     */
    private function register_core_providers() {
        // Static dataset provider (always available)
        $this->register( new QAF_Autocomplete_Static() );

        // Browser HTML5 autocomplete (always available)
        $this->register( new QAF_Autocomplete_Browser() );

        // Google Places (optional - requires API key)
        $this->register( new QAF_Autocomplete_Google_Places() );

        // Address fallback (always available)
        $this->register( new QAF_Autocomplete_Fallback() );
    }

    /**
     * Register a provider
     *
     * @param QAF_Autocomplete_Provider $provider Provider instance.
     */
    public function register( QAF_Autocomplete_Provider $provider ) {
        $this->providers[ $provider->get_id() ] = $provider;
    }

    /**
     * Unregister a provider
     *
     * @param string $id Provider ID.
     */
    public function unregister( $id ) {
        unset( $this->providers[ $id ] );
    }

    /**
     * Get a provider by ID
     *
     * @param string $id Provider ID.
     * @return QAF_Autocomplete_Provider|null Provider or null.
     */
    public function get( $id ) {
        return isset( $this->providers[ $id ] ) ? $this->providers[ $id ] : null;
    }

    /**
     * Get all providers
     *
     * @return array All registered providers.
     */
    public function get_all() {
        return $this->providers;
    }

    /**
     * Get available providers
     *
     * @return array Providers that are currently available.
     */
    public function get_available() {
        return array_filter( $this->providers, function( $provider ) {
            return $provider->is_available();
        } );
    }

    /**
     * Get the best available provider for a use case
     *
     * @param string $preferred_id Preferred provider ID.
     * @param string $fallback_id  Fallback provider ID.
     * @return QAF_Autocomplete_Provider|null Best available provider.
     */
    public function get_best_available( $preferred_id, $fallback_id = 'static' ) {
        // Try preferred
        if ( isset( $this->providers[ $preferred_id ] ) && $this->providers[ $preferred_id ]->is_available() ) {
            return $this->providers[ $preferred_id ];
        }

        // Try fallback
        if ( isset( $this->providers[ $fallback_id ] ) && $this->providers[ $fallback_id ]->is_available() ) {
            return $this->providers[ $fallback_id ];
        }

        // Return first available
        foreach ( $this->providers as $provider ) {
            if ( $provider->is_available() ) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Get providers for builder UI
     *
     * @return array Provider options for UI.
     */
    public function get_for_builder() {
        $options = array();

        foreach ( $this->providers as $id => $provider ) {
            $options[ $id ] = array(
                'id'          => $id,
                'name'        => $provider->get_name(),
                'available'   => $provider->is_available(),
                'reason'      => $provider->get_unavailable_reason(),
                'schema'      => $provider->get_options_schema(),
            );
        }

        return $options;
    }
}
