<?php
/**
 * Autocomplete Provider Interface
 *
 * Defines the contract for all autocomplete providers.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Autocomplete_Provider interface
 */
interface BAF_Autocomplete_Provider {

    /**
     * Get provider ID
     *
     * @return string Provider identifier.
     */
    public function get_id();

    /**
     * Get provider name
     *
     * @return string Human-readable name.
     */
    public function get_name();

    /**
     * Check if provider is available
     *
     * @return bool True if available and configured.
     */
    public function is_available();

    /**
     * Get unavailability reason
     *
     * @return string|null Reason or null if available.
     */
    public function get_unavailable_reason();

    /**
     * Get provider options schema
     *
     * @return array Options schema for configuration.
     */
    public function get_options_schema();

    /**
     * Get suggestions (server-side)
     *
     * @param string $query   Search query.
     * @param array  $options Provider options.
     * @return array Array of suggestions.
     */
    public function get_suggestions( $query, $options = array() );

    /**
     * Get JavaScript adapter config
     *
     * @param array $options Field options.
     * @return array JS config for client-side.
     */
    public function get_js_config( $options = array() );
}
