<?php
/**
 * Map View
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_View_Map class
 * 
 * Renders entries on an accessible map with list fallback.
 */
class BAF_View_Map extends BAF_View_Renderer {

    /**
     * Get view type
     *
     * @return string View type identifier.
     */
    protected function get_type() {
        return 'map';
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    protected function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'address_field'   => '',
            'lat_field'       => '',
            'lng_field'       => '',
            'title_field'     => '',
            'content_field'   => '',
            'map_provider'    => 'leaflet',
            'center_lat'      => 40.7128,
            'center_lng'      => -74.0060,
            'zoom'            => 10,
            'clustering'      => true,
            'list_view'       => true,
        ) );
    }

    /**
     * Render view content
     *
     * @return string HTML output.
     */
    protected function render_content() {
        if ( empty( $this->entries ) ) {
            return $this->render_empty_state();
        }

        $output = '<div class="baf-map-container">';

        // Skip to list link (accessibility)
        if ( $this->config['list_view'] ) {
            $output .= sprintf(
                '<a href="#baf-map-list" class="baf-skip-link">%s</a>',
                esc_html__( 'Skip to list view', 'bits-accessible-forms' )
            );
        }

        // Map
        $output .= $this->render_map();

        // List view (accessibility fallback)
        if ( $this->config['list_view'] ) {
            $output .= $this->render_list_view();
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render the map
     *
     * @return string HTML output.
     */
    private function render_map() {
        $markers = $this->get_markers();
        $markers_json = wp_json_encode( $markers );

        $output = sprintf(
            '<div class="baf-map" 
                 data-provider="%s"
                 data-center-lat="%s"
                 data-center-lng="%s"
                 data-zoom="%d"
                 data-clustering="%s"
                 data-markers="%s"
                 role="application"
                 aria-label="%s"
                 tabindex="0">
                <noscript>%s</noscript>
            </div>',
            esc_attr( $this->config['map_provider'] ),
            esc_attr( $this->config['center_lat'] ),
            esc_attr( $this->config['center_lng'] ),
            (int) $this->config['zoom'],
            $this->config['clustering'] ? 'true' : 'false',
            esc_attr( $markers_json ),
            esc_attr__( 'Interactive map showing entry locations', 'bits-accessible-forms' ),
            esc_html__( 'JavaScript is required to display the map. Please use the list view below.', 'bits-accessible-forms' )
        );

        return $output;
    }

    /**
     * Get markers data from entries
     *
     * @return array Markers data.
     */
    private function get_markers() {
        $markers = array();

        foreach ( $this->entries as $entry ) {
            $lat = null;
            $lng = null;

            // Try lat/lng fields first
            if ( $this->config['lat_field'] && $this->config['lng_field'] ) {
                $lat = $entry->get_field_value( $this->config['lat_field'] );
                $lng = $entry->get_field_value( $this->config['lng_field'] );
            }

            // Try address field (would need geocoding)
            if ( ( ! $lat || ! $lng ) && $this->config['address_field'] ) {
                $address = $entry->get_field_value( $this->config['address_field'] );
                // Note: Geocoding would be done separately, possibly cached
                // For now, skip entries without coordinates
                continue;
            }

            if ( ! $lat || ! $lng ) {
                continue;
            }

            // Get marker title
            $title = '';
            if ( $this->config['title_field'] ) {
                $title = $this->get_display_value( $entry, $this->config['title_field'] );
            }
            if ( empty( $title ) ) {
                $title = sprintf( __( 'Entry #%d', 'bits-accessible-forms' ), $entry->id );
            }

            // Get marker content
            $content = '';
            if ( $this->config['content_field'] ) {
                $content = $this->get_display_value( $entry, $this->config['content_field'] );
            }

            $markers[] = array(
                'id'      => $entry->id,
                'lat'     => (float) $lat,
                'lng'     => (float) $lng,
                'title'   => $title,
                'content' => $content,
            );
        }

        return $markers;
    }

    /**
     * Render list view (accessibility fallback)
     *
     * @return string HTML output.
     */
    private function render_list_view() {
        $output = '<div id="baf-map-list" class="baf-map-list">';
        $output .= sprintf(
            '<h3 class="baf-map-list-heading">%s</h3>',
            esc_html__( 'Location List', 'bits-accessible-forms' )
        );

        $output .= '<ul class="baf-map-locations" role="list">';

        foreach ( $this->entries as $entry ) {
            $output .= $this->render_location_item( $entry );
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render single location item
     *
     * @param BAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_location_item( $entry ) {
        // Get title
        $title = '';
        if ( $this->config['title_field'] ) {
            $title = $this->get_display_value( $entry, $this->config['title_field'] );
        }
        if ( empty( $title ) ) {
            $title = sprintf( __( 'Entry #%d', 'bits-accessible-forms' ), $entry->id );
        }

        // Get address
        $address = '';
        if ( $this->config['address_field'] ) {
            $address_value = $entry->get_field_value( $this->config['address_field'] );
            if ( is_array( $address_value ) ) {
                $address = implode( ', ', array_filter( $address_value ) );
            } else {
                $address = $address_value;
            }
        }

        $output = sprintf(
            '<li class="baf-location-item" data-entry-id="%d">',
            (int) $entry->id
        );

        $output .= sprintf(
            '<a href="#" class="baf-location-link" data-entry-id="%d">
                <span class="baf-location-title">%s</span>
                <span class="baf-location-address">%s</span>
            </a>',
            (int) $entry->id,
            esc_html( $title ),
            esc_html( $address )
        );

        $output .= '</li>';

        return $output;
    }

    /**
     * Render empty state
     *
     * @return string HTML output.
     */
    private function render_empty_state() {
        return sprintf(
            '<div class="baf-empty-state" role="status">
                <span class="dashicons dashicons-location" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No locations found.', 'bits-accessible-forms' )
        );
    }
}
