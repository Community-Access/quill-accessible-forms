<?php
/**
 * Address Composite Field - uses fieldset/legend for grouped inputs
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Address class
 * 
 * Composite address field with fieldset/legend for WCAG 2.2 AA compliance.
 */
class BAF_Field_Address extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'address';

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
            // Autocomplete (Google Places)
            'autocomplete'            => false,
            'autocomplete_provider'   => 'google_places',
            'autocomplete_types'      => array( 'address' ),
            'autocomplete_countries'  => array(),

            // Subfields visibility
            'show_line2'        => true,
            'show_city'         => true,
            'show_state'        => true,
            'show_zip'          => true,
            'show_country'      => true,
            
            // Subfield labels
            'label_line1'       => __( 'Street Address', 'bits-accessible-forms' ),
            'label_line2'       => __( 'Address Line 2', 'bits-accessible-forms' ),
            'label_city'        => __( 'City', 'bits-accessible-forms' ),
            'label_state'       => __( 'State / Province', 'bits-accessible-forms' ),
            'label_zip'         => __( 'ZIP / Postal Code', 'bits-accessible-forms' ),
            'label_country'     => __( 'Country', 'bits-accessible-forms' ),
            
            // Subfield placeholders
            'placeholder_line1' => '',
            'placeholder_line2' => __( 'Apartment, suite, unit, etc. (optional)', 'bits-accessible-forms' ),
            
            // Required subfields
            'line1_required'    => true,
            'city_required'     => true,
            'state_required'    => true,
            'zip_required'      => true,
            'country_required'  => false,
            
            // Country settings
            'default_country'   => 'US',
            'country_list'      => 'all', // all, us_only, custom
            
            // Layout
            'layout'            => 'stacked', // stacked, inline
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
                    'label' => __( 'Field Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'subfields' => array(
                'show_line2' => array(
                    'type'    => 'toggle',
                    'label'   => __( 'Show Address Line 2', 'bits-accessible-forms' ),
                    'default' => true,
                ),
                'show_country' => array(
                    'type'    => 'toggle',
                    'label'   => __( 'Show Country', 'bits-accessible-forms' ),
                    'default' => true,
                ),
            ),
            'appearance' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'bits-accessible-forms' ),
                    'options' => array(
                        'stacked' => __( 'Stacked (vertical)', 'bits-accessible-forms' ),
                        'inline'  => __( 'Inline', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'advanced' => array(
                'autocomplete' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Enable Address Autocomplete', 'bits-accessible-forms' ),
                    'description' => __( 'Uses Google Places when a Google Maps API key is configured.', 'bits-accessible-forms' ),
                ),
                'autocomplete_countries' => array(
                    'type'        => 'text',
                    'label'       => __( 'Autocomplete Countries', 'bits-accessible-forms' ),
                    'description' => __( 'Comma-separated country codes (e.g. US,CA). Leave blank for all.', 'bits-accessible-forms' ),
                    'conditional' => array( 'autocomplete' => true ),
                ),
            ),
        );
    }

    /**
     * Get subfields configuration
     *
     * @return array Subfields.
     */
    protected function get_subfields() {
        $subfields = array();

        // Street Address Line 1
        $subfields['line1'] = array(
            'key'          => 'line1',
            'type'         => 'text',
            'label'        => $this->config['label_line1'],
            'placeholder'  => $this->config['placeholder_line1'],
            'required'     => ! empty( $this->config['line1_required'] ),
            'autocomplete' => 'address-line1',
            'width'        => 'full',
        );

        // Street Address Line 2
        if ( ! empty( $this->config['show_line2'] ) ) {
            $subfields['line2'] = array(
                'key'          => 'line2',
                'type'         => 'text',
                'label'        => $this->config['label_line2'],
                'placeholder'  => $this->config['placeholder_line2'],
                'required'     => false,
                'autocomplete' => 'address-line2',
                'width'        => 'full',
            );
        }

        // City
        if ( ! empty( $this->config['show_city'] ) ) {
            $subfields['city'] = array(
                'key'          => 'city',
                'type'         => 'text',
                'label'        => $this->config['label_city'],
                'required'     => ! empty( $this->config['city_required'] ),
                'autocomplete' => 'address-level2',
                'width'        => 'half',
            );
        }

        // State/Province
        if ( ! empty( $this->config['show_state'] ) ) {
            $subfields['state'] = array(
                'key'          => 'state',
                'type'         => 'text',
                'label'        => $this->config['label_state'],
                'required'     => ! empty( $this->config['state_required'] ),
                'autocomplete' => 'address-level1',
                'width'        => 'quarter',
            );
        }

        // ZIP/Postal Code
        if ( ! empty( $this->config['show_zip'] ) ) {
            $subfields['zip'] = array(
                'key'          => 'zip',
                'type'         => 'text',
                'label'        => $this->config['label_zip'],
                'required'     => ! empty( $this->config['zip_required'] ),
                'autocomplete' => 'postal-code',
                'width'        => 'quarter',
            );
        }

        // Country
        if ( ! empty( $this->config['show_country'] ) ) {
            $subfields['country'] = array(
                'key'          => 'country',
                'type'         => 'select',
                'label'        => $this->config['label_country'],
                'required'     => ! empty( $this->config['country_required'] ),
                'autocomplete' => 'country-name',
                'options'      => $this->get_country_list(),
                'default'      => $this->config['default_country'],
                'width'        => 'full',
            );
        }

        return $subfields;
    }

    /**
     * Render the field HTML with fieldset/legend
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $subfields = $this->get_subfields();
        $html_id   = $this->get_html_id();
        $values    = $this->get_value();
        $layout    = $this->config['layout'];
        $required  = $this->is_required();

        $wrapper_class = array(
            'baf-field',
            'baf-field-' . $this->type,
            'baf-field-size-' . $this->config['size'],
            'baf-address-layout-' . $layout,
        );

        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        if ( $required ) {
            $wrapper_class[] = 'baf-field-required';
        }

        if ( ! empty( $this->errors ) ) {
            $wrapper_class[] = 'baf-field-error';
        }

        // Fieldset for composite field
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        
        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }
        if ( ! empty( $this->errors ) ) {
            $described_by[] = $html_id . '-error';
        }
        if ( ! empty( $described_by ) ) {
            $html .= ' aria-describedby="' . esc_attr( implode( ' ', $described_by ) ) . '"';
        }
        
        $html .= '>';

        // Legend
        $html .= '<legend class="baf-fieldset-legend">';
        $html .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        if ( $required ) {
            $html .= $this->render_required_indicator();
        }
        $html .= '</legend>';

        // Description
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Subfields container
        $html .= '<div class="baf-address-fields">';

        foreach ( $subfields as $key => $subfield ) {
            $html .= $this->render_subfield( $subfield, $values );
        }

        $html .= '</div>';

        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Render individual subfield
     *
     * @param array $subfield Subfield config.
     * @param array $values   Current values.
     * @return string Subfield HTML.
     */
    protected function render_subfield( $subfield, $values ) {
        $key        = $subfield['key'];
        $input_id   = $this->get_html_id() . '-' . $key;
        $input_name = 'baf_field_' . $this->get_id() . '[' . $key . ']';
        $value      = isset( $values[ $key ] ) ? $values[ $key ] : ( $subfield['default'] ?? '' );
        $required   = ! empty( $subfield['required'] );
        $has_error  = isset( $this->errors[ $key ] );
        $width      = $subfield['width'] ?? 'full';

        $html = '<div class="baf-address-subfield baf-address-' . esc_attr( $key ) . ' baf-width-' . esc_attr( $width ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $input_id ) . '" class="baf-subfield-label">';
        $html .= esc_html( $subfield['label'] );
        if ( $required ) {
            $html .= ' <span class="baf-required-indicator" aria-hidden="true">*</span>';
            $html .= '<span class="baf-sr-only"> ' . esc_html__( 'required', 'bits-accessible-forms' ) . '</span>';
        }
        $html .= '</label>';

        // Input
        if ( $subfield['type'] === 'select' ) {
            $html .= '<select';
            $html .= ' id="' . esc_attr( $input_id ) . '"';
            $html .= ' name="' . esc_attr( $input_name ) . '"';
            $html .= ' class="baf-address-input baf-address-select"';
            
            if ( ! empty( $subfield['autocomplete'] ) ) {
                $html .= ' autocomplete="' . esc_attr( $subfield['autocomplete'] ) . '"';
            }
            if ( $required ) {
                $html .= ' aria-required="true"';
            }
            if ( $has_error ) {
                $html .= ' aria-invalid="true"';
            }
            
            $html .= '>';
            
            $html .= '<option value="">' . esc_html__( 'Select...', 'bits-accessible-forms' ) . '</option>';
            
            foreach ( $subfield['options'] as $code => $name ) {
                $selected = $value === $code ? ' selected' : '';
                $html .= '<option value="' . esc_attr( $code ) . '"' . $selected . '>' . esc_html( $name ) . '</option>';
            }
            
            $html .= '</select>';
        } else {
            $html .= '<input type="text"';
            $html .= ' id="' . esc_attr( $input_id ) . '"';
            $html .= ' name="' . esc_attr( $input_name ) . '"';
            $html .= ' value="' . esc_attr( $value ) . '"';
            $html .= ' class="baf-address-input baf-address-text"';

            // Google Places autocomplete hooks (line1 only)
            if ( $key === 'line1' && ! empty( $this->config['autocomplete'] ) && ( $this->config['autocomplete_provider'] ?? '' ) === 'google_places' ) {
                $html .= ' data-google-places="true"';
                $html .= ' data-google-places-types="' . esc_attr( wp_json_encode( $this->config['autocomplete_types'] ?? array( 'address' ) ) ) . '"';
                $html .= ' data-google-places-countries="' . esc_attr( wp_json_encode( $this->config['autocomplete_countries'] ?? array() ) ) . '"';
                $html .= ' data-address-field-id="' . esc_attr( $this->get_id() ) . '"';
            }
            
            if ( ! empty( $subfield['placeholder'] ) ) {
                $html .= ' placeholder="' . esc_attr( $subfield['placeholder'] ) . '"';
            }
            if ( ! empty( $subfield['autocomplete'] ) ) {
                $html .= ' autocomplete="' . esc_attr( $subfield['autocomplete'] ) . '"';
            }
            if ( $required ) {
                $html .= ' aria-required="true"';
            }
            if ( $has_error ) {
                $html .= ' aria-invalid="true"';
            }
            
            $html .= '>';
        }

        // Error
        if ( $has_error ) {
            $html .= '<div class="baf-subfield-error" role="alert">' . esc_html( $this->errors[ $key ] ) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get country list
     *
     * @return array Countries.
     */
    protected function get_country_list() {
        return array(
            'US' => __( 'United States', 'bits-accessible-forms' ),
            'CA' => __( 'Canada', 'bits-accessible-forms' ),
            'GB' => __( 'United Kingdom', 'bits-accessible-forms' ),
            'AU' => __( 'Australia', 'bits-accessible-forms' ),
            'DE' => __( 'Germany', 'bits-accessible-forms' ),
            'FR' => __( 'France', 'bits-accessible-forms' ),
            'ES' => __( 'Spain', 'bits-accessible-forms' ),
            'IT' => __( 'Italy', 'bits-accessible-forms' ),
            'NL' => __( 'Netherlands', 'bits-accessible-forms' ),
            'BE' => __( 'Belgium', 'bits-accessible-forms' ),
            'CH' => __( 'Switzerland', 'bits-accessible-forms' ),
            'AT' => __( 'Austria', 'bits-accessible-forms' ),
            'SE' => __( 'Sweden', 'bits-accessible-forms' ),
            'NO' => __( 'Norway', 'bits-accessible-forms' ),
            'DK' => __( 'Denmark', 'bits-accessible-forms' ),
            'FI' => __( 'Finland', 'bits-accessible-forms' ),
            'IE' => __( 'Ireland', 'bits-accessible-forms' ),
            'NZ' => __( 'New Zealand', 'bits-accessible-forms' ),
            'JP' => __( 'Japan', 'bits-accessible-forms' ),
            'IN' => __( 'India', 'bits-accessible-forms' ),
            'BR' => __( 'Brazil', 'bits-accessible-forms' ),
            'MX' => __( 'Mexico', 'bits-accessible-forms' ),
        );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized values.
     */
    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        
        foreach ( $value as $key => $v ) {
            $sanitized[ $key ] = sanitize_text_field( $v );
        }

        return $sanitized;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     * @return bool True if valid.
     */
    public function validate( $value ) {
        $this->errors = array();
        $subfields = $this->get_subfields();
        $values = is_array( $value ) ? $value : array();

        foreach ( $subfields as $key => $subfield ) {
            $subvalue = isset( $values[ $key ] ) ? trim( $values[ $key ] ) : '';
            
            if ( ! empty( $subfield['required'] ) && empty( $subvalue ) ) {
                $this->errors[ $key ] = sprintf(
                    __( '%s is required.', 'bits-accessible-forms' ),
                    $subfield['label']
                );
            }
        }

        return empty( $this->errors );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted address.
     */
    public function format_display( $value ) {
        if ( ! is_array( $value ) ) {
            return '';
        }

        $parts = array();

        if ( ! empty( $value['line1'] ) ) {
            $parts[] = $value['line1'];
        }
        if ( ! empty( $value['line2'] ) ) {
            $parts[] = $value['line2'];
        }

        $city_state_zip = array();
        if ( ! empty( $value['city'] ) ) {
            $city_state_zip[] = $value['city'];
        }
        if ( ! empty( $value['state'] ) ) {
            $city_state_zip[] = $value['state'];
        }
        if ( ! empty( $value['zip'] ) ) {
            $city_state_zip[] = $value['zip'];
        }
        if ( ! empty( $city_state_zip ) ) {
            $parts[] = implode( ', ', $city_state_zip );
        }

        if ( ! empty( $value['country'] ) ) {
            $countries = $this->get_country_list();
            $parts[] = $countries[ $value['country'] ] ?? $value['country'];
        }

        return esc_html( implode( "\n", $parts ) );
    }
}
