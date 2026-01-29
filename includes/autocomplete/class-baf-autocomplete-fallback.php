<?php
/**
 * Autocomplete Fallback Provider
 *
 * Provides graceful fallback when Google Places is unavailable.
 * Uses static country/state data for address fields.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Autocomplete_Fallback class
 */
class BAF_Autocomplete_Fallback implements BAF_Autocomplete_Provider {

    /**
     * Static provider instance
     *
     * @var BAF_Autocomplete_Static
     */
    private $static_provider;

    /**
     * Browser provider instance
     *
     * @var BAF_Autocomplete_Browser
     */
    private $browser_provider;

    /**
     * Constructor
     */
    public function __construct() {
        $this->static_provider  = new BAF_Autocomplete_Static();
        $this->browser_provider = new BAF_Autocomplete_Browser();
    }

    /**
     * Get provider ID
     *
     * @return string
     */
    public function get_id() {
        return 'fallback';
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Address Fallback', 'bits-accessible-forms' );
    }

    /**
     * Check if available
     *
     * @return bool Always true.
     */
    public function is_available() {
        return true;
    }

    /**
     * Get unavailability reason
     *
     * @return string|null
     */
    public function get_unavailable_reason() {
        return null;
    }

    /**
     * Get options schema
     *
     * @return array
     */
    public function get_options_schema() {
        return array(
            'mode' => array(
                'type'        => 'select',
                'label'       => __( 'Fallback Mode', 'bits-accessible-forms' ),
                'description' => __( 'Choose how to handle address autocomplete when Google Places is unavailable.', 'bits-accessible-forms' ),
                'options'     => array(
                    'country_state' => __( 'Country/State dropdowns', 'bits-accessible-forms' ),
                    'static_only'   => __( 'Static lists only', 'bits-accessible-forms' ),
                    'browser'       => __( 'Browser autocomplete', 'bits-accessible-forms' ),
                    'none'          => __( 'Manual entry only', 'bits-accessible-forms' ),
                ),
                'default'     => 'country_state',
            ),
            'default_country' => array(
                'type'    => 'select',
                'label'   => __( 'Default Country', 'bits-accessible-forms' ),
                'options' => $this->get_country_options(),
                'default' => 'US',
            ),
            'supported_countries' => array(
                'type'        => 'multiselect',
                'label'       => __( 'Supported Countries', 'bits-accessible-forms' ),
                'description' => __( 'Leave empty for all countries.', 'bits-accessible-forms' ),
                'options'     => $this->get_country_options(),
                'default'     => array(),
            ),
        );
    }

    /**
     * Get suggestions
     *
     * @param string $query   Search query.
     * @param array  $options Options.
     * @return array Suggestions.
     */
    public function get_suggestions( $query, $options = array() ) {
        $mode = $options['mode'] ?? 'country_state';

        switch ( $mode ) {
            case 'country_state':
            case 'static_only':
                return $this->get_static_suggestions( $query, $options );

            case 'browser':
                return array(); // Browser handles it

            default:
                return array();
        }
    }

    /**
     * Get static suggestions
     *
     * @param string $query   Search query.
     * @param array  $options Options.
     * @return array Suggestions.
     */
    private function get_static_suggestions( $query, $options ) {
        $field_type = $options['field_type'] ?? '';

        // Determine which dataset to use based on context
        switch ( $field_type ) {
            case 'country':
                return $this->static_provider->get_suggestions( $query, array(
                    'dataset' => 'countries',
                ) );

            case 'state':
            case 'province':
                $country = $options['country'] ?? 'US';
                $dataset = 'US' === $country ? 'us_states' : 'ca_provinces';
                return $this->static_provider->get_suggestions( $query, array(
                    'dataset' => $dataset,
                ) );

            case 'city':
                // No built-in city list - return empty
                return array();

            default:
                return array();
        }
    }

    /**
     * Get JS config
     *
     * @param array $options Options.
     * @return array JS config.
     */
    public function get_js_config( $options = array() ) {
        $mode = $options['mode'] ?? 'country_state';

        $config = array(
            'provider'          => 'fallback',
            'mode'              => $mode,
            'defaultCountry'    => $options['default_country'] ?? 'US',
            'supportedCountries' => $options['supported_countries'] ?? array(),
        );

        if ( 'country_state' === $mode ) {
            $config['countries'] = $this->static_provider->get_dataset( 'countries' );
            $config['states'] = array(
                'US' => $this->static_provider->get_dataset( 'us_states' ),
                'CA' => $this->static_provider->get_dataset( 'ca_provinces' ),
            );
        }

        if ( 'browser' === $mode ) {
            $config['browserTokens'] = array(
                'street'     => 'street-address',
                'city'       => 'address-level2',
                'state'      => 'address-level1',
                'postal'     => 'postal-code',
                'country'    => 'country',
            );
        }

        return $config;
    }

    /**
     * Get country options for select
     *
     * @return array Country options.
     */
    private function get_country_options() {
        $countries = $this->static_provider->get_dataset( 'countries' );
        $options = array( '' => __( '-- Select --', 'bits-accessible-forms' ) );

        foreach ( $countries as $country ) {
            $options[ $country['value'] ] = $country['label'];
        }

        return $options;
    }

    /**
     * Build address form config with cascading
     *
     * @param array $options Options.
     * @return array Form field configs.
     */
    public function build_address_fields( $options = array() ) {
        $mode    = $options['mode'] ?? 'country_state';
        $prefix  = $options['prefix'] ?? 'address';
        $country = $options['default_country'] ?? 'US';

        $fields = array();

        // Street address - always text with browser autocomplete
        $fields[ $prefix . '_street' ] = array(
            'type'         => 'text',
            'label'        => __( 'Street Address', 'bits-accessible-forms' ),
            'autocomplete' => 'street-address',
        );

        $fields[ $prefix . '_street2' ] = array(
            'type'         => 'text',
            'label'        => __( 'Address Line 2', 'bits-accessible-forms' ),
            'autocomplete' => 'address-line2',
            'required'     => false,
        );

        // City - text with browser autocomplete
        $fields[ $prefix . '_city' ] = array(
            'type'         => 'text',
            'label'        => __( 'City', 'bits-accessible-forms' ),
            'autocomplete' => 'address-level2',
        );

        if ( 'country_state' === $mode ) {
            // Country dropdown
            $fields[ $prefix . '_country' ] = array(
                'type'    => 'dropdown',
                'label'   => __( 'Country', 'bits-accessible-forms' ),
                'choices' => $this->static_provider->get_dataset( 'countries' ),
                'default' => $country,
            );

            // State dropdown - cascades from country
            $fields[ $prefix . '_state' ] = array(
                'type'      => 'dropdown',
                'label'     => __( 'State/Province', 'bits-accessible-forms' ),
                'choices'   => array(),
                'cascade'   => array(
                    'parent'    => $prefix . '_country',
                    'datasets'  => array(
                        'US' => $this->static_provider->get_dataset( 'us_states' ),
                        'CA' => $this->static_provider->get_dataset( 'ca_provinces' ),
                    ),
                ),
            );
        } else {
            // Text inputs for state
            $fields[ $prefix . '_state' ] = array(
                'type'         => 'text',
                'label'        => __( 'State/Province', 'bits-accessible-forms' ),
                'autocomplete' => 'address-level1',
            );

            // Text input for country
            $fields[ $prefix . '_country' ] = array(
                'type'         => 'text',
                'label'        => __( 'Country', 'bits-accessible-forms' ),
                'autocomplete' => 'country',
                'default'      => 'US' === $country ? 'United States' : $country,
            );
        }

        // Postal code - always text
        $fields[ $prefix . '_postal' ] = array(
            'type'         => 'text',
            'label'        => __( 'Postal Code', 'bits-accessible-forms' ),
            'autocomplete' => 'postal-code',
        );

        return $fields;
    }
}
