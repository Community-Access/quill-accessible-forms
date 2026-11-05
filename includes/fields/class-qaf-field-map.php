<?php
/**
 * Map/Geolocation Picker Field
 *
 * Click-to-select location with address autocomplete.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Map class
 */
class QAF_Field_Map extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'map';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'map_provider'      => 'leaflet',       // 'leaflet' (free), 'google'
            'default_lat'       => 40.7128,         // Default center latitude
            'default_lng'       => -74.0060,        // Default center longitude
            'default_zoom'      => 13,
            'min_zoom'          => 1,
            'max_zoom'          => 19,
            'map_height'        => 400,
            'show_search'       => true,
            'show_current_location' => true,
            'show_coordinates'  => false,
            'draggable_marker'  => true,
            'reverse_geocode'   => true,            // Get address from coordinates
            'address_components' => array( 'formatted' ), // Which components to capture
            'restrict_country'  => '',              // Country code to restrict search
            'map_style'         => 'streets',       // 'streets', 'satellite', 'terrain'
        ) );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'label' => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'map' => array(
                'map_provider' => array(
                    'type'    => 'select',
                    'label'   => __( 'Map Provider', 'quill-accessible-forms' ),
                    'options' => array(
                        'leaflet' => __( 'Leaflet/OpenStreetMap (Free)', 'quill-accessible-forms' ),
                        'google'  => __( 'Google Maps (Requires API Key)', 'quill-accessible-forms' ),
                    ),
                ),
                'map_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Map Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'streets'   => __( 'Streets', 'quill-accessible-forms' ),
                        'satellite' => __( 'Satellite', 'quill-accessible-forms' ),
                        'terrain'   => __( 'Terrain', 'quill-accessible-forms' ),
                    ),
                ),
                'map_height' => array(
                    'type'  => 'number',
                    'label' => __( 'Map Height (px)', 'quill-accessible-forms' ),
                    'min'   => 200,
                    'max'   => 800,
                ),
            ),
            'location' => array(
                'default_lat' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Latitude', 'quill-accessible-forms' ),
                ),
                'default_lng' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Longitude', 'quill-accessible-forms' ),
                ),
                'default_zoom' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Zoom', 'quill-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 19,
                ),
                'restrict_country' => array(
                    'type'  => 'text',
                    'label' => __( 'Restrict to Country', 'quill-accessible-forms' ),
                    'desc'  => __( 'Two-letter country code (e.g., US, GB)', 'quill-accessible-forms' ),
                ),
            ),
            'features' => array(
                'show_search' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Search Box', 'quill-accessible-forms' ),
                ),
                'show_current_location' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show "Use My Location" Button', 'quill-accessible-forms' ),
                ),
                'show_coordinates' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Coordinates', 'quill-accessible-forms' ),
                ),
                'draggable_marker' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Draggable Marker', 'quill-accessible-forms' ),
                ),
                'reverse_geocode' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Get Address from Coordinates', 'quill-accessible-forms' ),
                ),
            ),
            'data' => array(
                'address_components' => array(
                    'type'    => 'multiselect',
                    'label'   => __( 'Address Components to Capture', 'quill-accessible-forms' ),
                    'options' => array(
                        'formatted'      => __( 'Full Formatted Address', 'quill-accessible-forms' ),
                        'street_number'  => __( 'Street Number', 'quill-accessible-forms' ),
                        'street'         => __( 'Street Name', 'quill-accessible-forms' ),
                        'city'           => __( 'City', 'quill-accessible-forms' ),
                        'state'          => __( 'State/Province', 'quill-accessible-forms' ),
                        'postal_code'    => __( 'Postal Code', 'quill-accessible-forms' ),
                        'country'        => __( 'Country', 'quill-accessible-forms' ),
                        'lat'            => __( 'Latitude', 'quill-accessible-forms' ),
                        'lng'            => __( 'Longitude', 'quill-accessible-forms' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id      = $this->get_html_id();
        $name         = 'qaf_field_' . $this->get_id();
        $value        = $this->get_value();
        $provider     = $this->config['map_provider'] ?? 'leaflet';
        $height       = (int) ( $this->config['map_height'] ?? 400 );
        $default_lat  = floatval( $this->config['default_lat'] ?? 40.7128 );
        $default_lng  = floatval( $this->config['default_lng'] ?? -74.0060 );
        $default_zoom = (int) ( $this->config['default_zoom'] ?? 13 );
        $show_search  = $this->config['show_search'] ?? true;
        $show_locate  = $this->config['show_current_location'] ?? true;
        $show_coords  = $this->config['show_coordinates'] ?? false;
        $draggable    = $this->config['draggable_marker'] ?? true;
        $reverse_geo  = $this->config['reverse_geocode'] ?? true;
        $restrict     = $this->config['restrict_country'] ?? '';
        $map_style    = $this->config['map_style'] ?? 'streets';

        // Parse current value
        $current_data = array();
        if ( ! empty( $value ) ) {
            $current_data = is_string( $value ) ? json_decode( $value, true ) : $value;
        }

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'qaf-map-wrapper';

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $field .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $field .= ' data-provider="' . esc_attr( $provider ) . '"';
        $field .= ' data-lat="' . esc_attr( $current_data['lat'] ?? $default_lat ) . '"';
        $field .= ' data-lng="' . esc_attr( $current_data['lng'] ?? $default_lng ) . '"';
        $field .= ' data-zoom="' . esc_attr( $default_zoom ) . '"';
        $field .= ' data-draggable="' . esc_attr( $draggable ? 'true' : 'false' ) . '"';
        $field .= ' data-reverse-geocode="' . esc_attr( $reverse_geo ? 'true' : 'false' ) . '"';
        $field .= ' data-style="' . esc_attr( $map_style ) . '"';
        if ( $restrict ) {
            $field .= ' data-country="' . esc_attr( $restrict ) . '"';
        }
        $field .= '>';

        // Label
        $field .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Search box
        if ( $show_search ) {
            $field .= '<div class="qaf-map-search">';
            $field .= '<label for="' . esc_attr( $html_id ) . '-search" class="screen-reader-text">';
            $field .= esc_html__( 'Search for address', 'quill-accessible-forms' );
            $field .= '</label>';
            $field .= '<input type="text" id="' . esc_attr( $html_id ) . '-search" ';
            $field .= 'class="qaf-map-search-input" ';
            $field .= 'placeholder="' . esc_attr__( 'Search for an address...', 'quill-accessible-forms' ) . '" ';
            $field .= 'autocomplete="off">';
            $field .= '<button type="button" class="qaf-map-search-btn" aria-label="' . esc_attr__( 'Search', 'quill-accessible-forms' ) . '">';
            $field .= '<span class="dashicons dashicons-search" aria-hidden="true"></span>';
            $field .= '</button>';
            $field .= '<div class="qaf-map-search-results" role="listbox" hidden></div>';
            $field .= '</div>';
        }

        // Map container
        $field .= '<div class="qaf-map-container" style="height: ' . esc_attr( $height ) . 'px;" ';
        $field .= 'id="' . esc_attr( $html_id ) . '-map" ';
        $field .= 'role="application" ';
        $field .= 'aria-label="' . esc_attr__( 'Interactive map for selecting location', 'quill-accessible-forms' ) . '">';
        $field .= '<noscript>' . esc_html__( 'JavaScript is required to use the map.', 'quill-accessible-forms' ) . '</noscript>';
        $field .= '</div>';

        // Map controls
        $field .= '<div class="qaf-map-controls">';
        
        if ( $show_locate ) {
            $field .= '<button type="button" class="qaf-map-locate-btn">';
            $field .= '<span class="dashicons dashicons-location" aria-hidden="true"></span>';
            $field .= esc_html__( 'Use My Location', 'quill-accessible-forms' );
            $field .= '</button>';
        }

        $field .= '<button type="button" class="qaf-map-clear-btn">';
        $field .= '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
        $field .= esc_html__( 'Clear Selection', 'quill-accessible-forms' );
        $field .= '</button>';

        $field .= '</div>';

        // Selected location display
        $field .= '<div class="qaf-map-selected" id="' . esc_attr( $html_id ) . '-selected" aria-live="polite">';
        if ( ! empty( $current_data['formatted'] ) ) {
            $field .= '<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>';
            $field .= '<span class="qaf-map-address">' . esc_html( $current_data['formatted'] ) . '</span>';
        }
        $field .= '</div>';

        // Coordinates display (optional)
        if ( $show_coords ) {
            $lat_val = $current_data['lat'] ?? '';
            $lng_val = $current_data['lng'] ?? '';
            
            $field .= '<div class="qaf-map-coordinates">';
            $field .= '<div class="qaf-coord-group">';
            $field .= '<label for="' . esc_attr( $html_id ) . '-lat">' . esc_html__( 'Latitude:', 'quill-accessible-forms' ) . '</label>';
            $field .= '<input type="text" id="' . esc_attr( $html_id ) . '-lat" class="qaf-coord-input" value="' . esc_attr( $lat_val ) . '" readonly>';
            $field .= '</div>';
            $field .= '<div class="qaf-coord-group">';
            $field .= '<label for="' . esc_attr( $html_id ) . '-lng">' . esc_html__( 'Longitude:', 'quill-accessible-forms' ) . '</label>';
            $field .= '<input type="text" id="' . esc_attr( $html_id ) . '-lng" class="qaf-coord-input" value="' . esc_attr( $lng_val ) . '" readonly>';
            $field .= '</div>';
            $field .= '</div>';
        }

        // Hidden input for form submission (JSON encoded)
        $field .= '<input type="hidden" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $name ) . '" ';
        $field .= 'value="' . esc_attr( is_array( $value ) ? wp_json_encode( $value ) : $value ) . '" class="qaf-map-value">';

        // Screen reader instructions
        $field .= '<div class="screen-reader-text">';
        $field .= esc_html__( 'Use the search box to find an address, click on the map to set a location, or use the "Use My Location" button to use your current position.', 'quill-accessible-forms' );
        $field .= '</div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $field .= $this->render_description();
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</div>';

        return $field;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized JSON string.
     */
    public function sanitize( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Parse if string
        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            return '';
        }

        // Sanitize each component
        $sanitized = array();

        if ( isset( $data['lat'] ) ) {
            $sanitized['lat'] = floatval( $data['lat'] );
            // Validate latitude range
            if ( $sanitized['lat'] < -90 || $sanitized['lat'] > 90 ) {
                $sanitized['lat'] = 0;
            }
        }

        if ( isset( $data['lng'] ) ) {
            $sanitized['lng'] = floatval( $data['lng'] );
            // Validate longitude range
            if ( $sanitized['lng'] < -180 || $sanitized['lng'] > 180 ) {
                $sanitized['lng'] = 0;
            }
        }

        // Text fields
        $text_fields = array( 'formatted', 'street_number', 'street', 'city', 'state', 'postal_code', 'country' );
        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( empty( $value ) ) {
            return;
        }

        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            $this->add_error( __( 'Invalid location data.', 'quill-accessible-forms' ) );
            return;
        }

        // Require at least lat/lng
        if ( ! isset( $data['lat'] ) || ! isset( $data['lng'] ) ) {
            $this->add_error( __( 'Please select a location on the map.', 'quill-accessible-forms' ) );
            return;
        }

        // Validate coordinate ranges
        if ( $data['lat'] < -90 || $data['lat'] > 90 ) {
            $this->add_error( __( 'Invalid latitude value.', 'quill-accessible-forms' ) );
            return;
        }

        if ( $data['lng'] < -180 || $data['lng'] > 180 ) {
            $this->add_error( __( 'Invalid longitude value.', 'quill-accessible-forms' ) );
            return;
        }

        // Check country restriction if set
        $restrict = $this->config['restrict_country'] ?? '';
        if ( $restrict && isset( $data['country'] ) ) {
            $country_code = strtoupper( substr( $data['country'], 0, 2 ) );
            if ( strtoupper( $restrict ) !== $country_code ) {
                $this->add_error( sprintf(
                    /* translators: %s: country code */
                    __( 'Location must be within %s.', 'quill-accessible-forms' ),
                    $restrict
                ) );
            }
        }
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            return '';
        }

        // Return formatted address if available
        if ( ! empty( $data['formatted'] ) ) {
            return $data['formatted'];
        }

        // Otherwise build from components
        $parts = array();
        if ( ! empty( $data['street_number'] ) && ! empty( $data['street'] ) ) {
            $parts[] = $data['street_number'] . ' ' . $data['street'];
        } elseif ( ! empty( $data['street'] ) ) {
            $parts[] = $data['street'];
        }
        if ( ! empty( $data['city'] ) ) {
            $parts[] = $data['city'];
        }
        if ( ! empty( $data['state'] ) ) {
            $parts[] = $data['state'];
        }
        if ( ! empty( $data['postal_code'] ) ) {
            $parts[] = $data['postal_code'];
        }
        if ( ! empty( $data['country'] ) ) {
            $parts[] = $data['country'];
        }

        if ( ! empty( $parts ) ) {
            return implode( ', ', $parts );
        }

        // Fallback to coordinates
        if ( isset( $data['lat'] ) && isset( $data['lng'] ) ) {
            return sprintf( '%f, %f', $data['lat'], $data['lng'] );
        }

        return '';
    }

    /**
     * Get individual address component
     *
     * @param mixed  $value     Field value.
     * @param string $component Component name.
     * @return string Component value.
     */
    public function get_component( $value, $component ) {
        if ( empty( $value ) ) {
            return '';
        }

        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            return '';
        }

        return $data[ $component ] ?? '';
    }
}
