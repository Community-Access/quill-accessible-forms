<?php
/**
 * Google Places Autocomplete Provider
 *
 * Provides address autocomplete using Google Places API.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Autocomplete_Google_Places class
 */
class BAF_Autocomplete_Google_Places implements BAF_Autocomplete_Provider {

    /**
     * Get provider ID
     *
     * @return string
     */
    public function get_id() {
        return 'google_places';
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return __( 'Google Places', 'bits-accessible-forms' );
    }

    /**
     * Check if available
     *
     * @return bool True if API key is configured.
     */
    public function is_available() {
        $api_key = $this->get_api_key();
        return ! empty( $api_key );
    }

    /**
     * Get unavailability reason
     *
     * @return string|null
     */
    public function get_unavailable_reason() {
        if ( ! $this->get_api_key() ) {
            return __( 'Google Maps API key is not configured. Go to BAF Settings to add your API key.', 'bits-accessible-forms' );
        }
        return null;
    }

    /**
     * Get API key
     *
     * @return string|null
     */
    private function get_api_key() {
        $settings = BAF_Settings::get_instance();
        return $settings->get( 'google_maps_api_key', '' );
    }

    /**
     * Get options schema
     *
     * @return array
     */
    public function get_options_schema() {
        return array(
            'types' => array(
                'type'    => 'multiselect',
                'label'   => __( 'Place Types', 'bits-accessible-forms' ),
                'options' => array(
                    'address'       => __( 'Addresses', 'bits-accessible-forms' ),
                    'geocode'       => __( 'Geocodes', 'bits-accessible-forms' ),
                    'establishment' => __( 'Businesses', 'bits-accessible-forms' ),
                    'regions'       => __( 'Regions (cities, states, countries)', 'bits-accessible-forms' ),
                    'cities'        => __( 'Cities only', 'bits-accessible-forms' ),
                ),
                'default' => array( 'address' ),
            ),
            'countries' => array(
                'type'        => 'text',
                'label'       => __( 'Restrict to Countries', 'bits-accessible-forms' ),
                'description' => __( 'Comma-separated ISO 3166-1 Alpha-2 country codes (e.g., us,ca,mx)', 'bits-accessible-forms' ),
                'placeholder' => 'us,ca',
            ),
            'fields' => array(
                'type'    => 'multiselect',
                'label'   => __( 'Address Fields to Return', 'bits-accessible-forms' ),
                'options' => array(
                    'street_number'               => __( 'Street Number', 'bits-accessible-forms' ),
                    'route'                       => __( 'Street Name', 'bits-accessible-forms' ),
                    'sublocality'                 => __( 'Neighborhood', 'bits-accessible-forms' ),
                    'locality'                    => __( 'City', 'bits-accessible-forms' ),
                    'administrative_area_level_1' => __( 'State/Province', 'bits-accessible-forms' ),
                    'administrative_area_level_2' => __( 'County', 'bits-accessible-forms' ),
                    'country'                     => __( 'Country', 'bits-accessible-forms' ),
                    'postal_code'                 => __( 'Postal Code', 'bits-accessible-forms' ),
                ),
                'default' => array( 'street_number', 'route', 'locality', 'administrative_area_level_1', 'postal_code', 'country' ),
            ),
            'fill_address_fields' => array(
                'type'        => 'checkbox',
                'label'       => __( 'Auto-fill Address Fields', 'bits-accessible-forms' ),
                'description' => __( 'Automatically populate related address fields when an address is selected.', 'bits-accessible-forms' ),
                'default'     => true,
            ),
            'field_mapping' => array(
                'type'        => 'object',
                'label'       => __( 'Field Mapping', 'bits-accessible-forms' ),
                'description' => __( 'Map Google Places components to form field IDs.', 'bits-accessible-forms' ),
                'condition'   => array( 'fill_address_fields' => true ),
                'properties'  => array(
                    'street_address' => array(
                        'type'  => 'text',
                        'label' => __( 'Street Address Field ID', 'bits-accessible-forms' ),
                    ),
                    'city' => array(
                        'type'  => 'text',
                        'label' => __( 'City Field ID', 'bits-accessible-forms' ),
                    ),
                    'state' => array(
                        'type'  => 'text',
                        'label' => __( 'State Field ID', 'bits-accessible-forms' ),
                    ),
                    'postal_code' => array(
                        'type'  => 'text',
                        'label' => __( 'Postal Code Field ID', 'bits-accessible-forms' ),
                    ),
                    'country' => array(
                        'type'  => 'text',
                        'label' => __( 'Country Field ID', 'bits-accessible-forms' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Get suggestions (server-side - typically not used for Google Places)
     *
     * @param string $query   Search query.
     * @param array  $options Options.
     * @return array Suggestions.
     */
    public function get_suggestions( $query, $options = array() ) {
        // Google Places autocomplete is typically client-side
        // Server-side requires Places API (different from Autocomplete)
        if ( strlen( $query ) < 3 ) {
            return array();
        }

        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return array();
        }

        // Build request URL
        $params = array(
            'input' => $query,
            'key'   => $api_key,
        );

        if ( ! empty( $options['types'] ) ) {
            $params['types'] = is_array( $options['types'] ) ? implode( '|', $options['types'] ) : $options['types'];
        }

        if ( ! empty( $options['countries'] ) ) {
            $countries = is_array( $options['countries'] ) ? $options['countries'] : explode( ',', $options['countries'] );
            $params['components'] = 'country:' . implode( '|country:', array_map( 'trim', $countries ) );
        }

        $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json?' . http_build_query( $params );

        $response = wp_remote_get( $url, array(
            'timeout' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['predictions'] ) ) {
            return array();
        }

        $suggestions = array();
        foreach ( $data['predictions'] as $prediction ) {
            $suggestions[] = array(
                'value'    => $prediction['description'],
                'label'    => $prediction['description'],
                'place_id' => $prediction['place_id'],
            );
        }

        return $suggestions;
    }

    /**
     * Get JS config
     *
     * @param array $options Options.
     * @return array JS config.
     */
    public function get_js_config( $options = array() ) {
        return array(
            'provider'          => 'google_places',
            'apiKey'            => $this->get_api_key(),
            'types'             => $options['types'] ?? array( 'address' ),
            'countries'         => $this->parse_countries( $options['countries'] ?? '' ),
            'fields'            => $options['fields'] ?? array(),
            'fillAddressFields' => $options['fill_address_fields'] ?? true,
            'fieldMapping'      => $options['field_mapping'] ?? array(),
        );
    }

    /**
     * Parse countries string to array
     *
     * @param string $countries Countries string.
     * @return array Countries array.
     */
    private function parse_countries( $countries ) {
        if ( empty( $countries ) ) {
            return array();
        }

        if ( is_array( $countries ) ) {
            return $countries;
        }

        return array_map( 'trim', explode( ',', $countries ) );
    }

    /**
     * Get place details by place_id
     *
     * @param string $place_id Place ID.
     * @param array  $options  Options.
     * @return array|WP_Error Place details or error.
     */
    public function get_place_details( $place_id, $options = array() ) {
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', __( 'Google Maps API key not configured.', 'bits-accessible-forms' ) );
        }

        $fields = $options['fields'] ?? array(
            'street_number',
            'route',
            'locality',
            'administrative_area_level_1',
            'postal_code',
            'country',
        );

        $params = array(
            'place_id' => $place_id,
            'key'      => $api_key,
            'fields'   => 'address_components,formatted_address,geometry',
        );

        $url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query( $params );

        $response = wp_remote_get( $url, array(
            'timeout' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['result'] ) ) {
            return new WP_Error( 'no_result', __( 'Place not found.', 'bits-accessible-forms' ) );
        }

        return $this->parse_place_result( $data['result'] );
    }

    /**
     * Parse place result to structured address
     *
     * @param array $result Place result.
     * @return array Structured address.
     */
    private function parse_place_result( $result ) {
        $address = array(
            'formatted_address' => $result['formatted_address'] ?? '',
            'street_number'     => '',
            'route'             => '',
            'street_address'    => '',
            'sublocality'       => '',
            'locality'          => '',
            'administrative_area_level_1' => '',
            'administrative_area_level_2' => '',
            'country'           => '',
            'country_code'      => '',
            'postal_code'       => '',
        );

        if ( ! empty( $result['geometry']['location'] ) ) {
            $address['lat'] = $result['geometry']['location']['lat'];
            $address['lng'] = $result['geometry']['location']['lng'];
        }

        if ( empty( $result['address_components'] ) ) {
            return $address;
        }

        foreach ( $result['address_components'] as $component ) {
            $type = $component['types'][0] ?? '';

            switch ( $type ) {
                case 'street_number':
                    $address['street_number'] = $component['long_name'];
                    break;
                case 'route':
                    $address['route'] = $component['long_name'];
                    break;
                case 'sublocality':
                case 'sublocality_level_1':
                    $address['sublocality'] = $component['long_name'];
                    break;
                case 'locality':
                    $address['locality'] = $component['long_name'];
                    break;
                case 'administrative_area_level_1':
                    $address['administrative_area_level_1'] = $component['long_name'];
                    $address['state_code'] = $component['short_name'];
                    break;
                case 'administrative_area_level_2':
                    $address['administrative_area_level_2'] = $component['long_name'];
                    break;
                case 'country':
                    $address['country'] = $component['long_name'];
                    $address['country_code'] = $component['short_name'];
                    break;
                case 'postal_code':
                    $address['postal_code'] = $component['long_name'];
                    break;
            }
        }

        // Build street address
        $address['street_address'] = trim( $address['street_number'] . ' ' . $address['route'] );

        return $address;
    }
}
