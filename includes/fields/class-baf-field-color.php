<?php
/**
 * Color Picker Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Color class
 */
class BAF_Field_Color extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'color';

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
            'default_color'  => '#000000',
            'allow_alpha'    => false,
            'preset_colors'  => array(),
            'show_input'     => true,
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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
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
            'options' => array(
                'default_color' => array(
                    'type'  => 'color',
                    'label' => __( 'Default Color', 'bits-accessible-forms' ),
                ),
                'allow_alpha' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Alpha/Transparency', 'bits-accessible-forms' ),
                ),
                'show_input' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Hex Input', 'bits-accessible-forms' ),
                ),
                'preset_colors' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Preset Colors', 'bits-accessible-forms' ),
                    'desc'  => __( 'One hex color per line.', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the color picker field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        $default = $this->config['default_color'] ?? '#000000';
        $alpha   = ! empty( $this->config['allow_alpha'] );

        if ( empty( $value ) ) {
            $value = $default;
        }

        $wrapper_class = $this->get_wrapper_classes();
        $desc_id = $html_id . '-desc';
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-alpha="' . ( $alpha ? 'true' : 'false' ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-field-label">';
        $html .= '<span class="baf-label-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</label>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Color picker container
        $html .= '<div class="baf-color-container">';

        // Native color input
        $html .= '<input type="color" id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $this->to_hex( $value ) ) . '"';
        $html .= ' class="baf-color-input"';
        
        if ( $this->is_required() ) {
            $html .= ' aria-required="true"';
        }
        
        if ( $this->get_description() ) {
            $html .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }
        
        $html .= '>';

        // Text input for hex value
        if ( ! empty( $this->config['show_input'] ) ) {
            $html .= '<input type="text" id="' . esc_attr( $html_id ) . '-text"';
            $html .= ' class="baf-color-text"';
            $html .= ' value="' . esc_attr( $value ) . '"';
            $html .= ' pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"';
            $html .= ' placeholder="#000000"';
            $html .= ' aria-label="' . esc_attr__( 'Color hex value', 'bits-accessible-forms' ) . '">';
        }

        // Color swatch preview
        $html .= '<div class="baf-color-swatch" role="img" aria-label="' . esc_attr__( 'Selected color preview', 'bits-accessible-forms' ) . '">';
        $html .= '<div class="baf-color-preview" style="background-color: ' . esc_attr( $value ) . ';"></div>';
        $html .= '</div>';

        $html .= '</div>';

        // Preset colors
        $presets = $this->get_preset_colors();
        if ( ! empty( $presets ) ) {
            $html .= '<div class="baf-color-presets" role="listbox" aria-label="' . esc_attr__( 'Preset colors', 'bits-accessible-forms' ) . '">';
            
            foreach ( $presets as $index => $color ) {
                $is_selected = ( strtolower( $color ) === strtolower( $value ) );
                $html .= '<button type="button" role="option" class="baf-color-preset"';
                $html .= ' data-color="' . esc_attr( $color ) . '"';
                $html .= ' style="background-color: ' . esc_attr( $color ) . ';"';
                $html .= ' aria-label="' . esc_attr( $color ) . '"';
                $html .= ' aria-selected="' . ( $is_selected ? 'true' : 'false' ) . '"';
                $html .= ' tabindex="' . ( $index === 0 ? '0' : '-1' ) . '">';
                
                if ( $is_selected ) {
                    $html .= '<span class="baf-icon-check" aria-hidden="true"></span>';
                }
                
                $html .= '</button>';
            }
            
            $html .= '</div>';
        }

        // Alpha slider (if enabled)
        if ( $alpha ) {
            $alpha_value = $this->extract_alpha( $value );
            
            $html .= '<div class="baf-color-alpha">';
            $html .= '<label for="' . esc_attr( $html_id ) . '-alpha" class="baf-alpha-label">';
            $html .= esc_html__( 'Opacity', 'bits-accessible-forms' );
            $html .= '</label>';
            
            $html .= '<input type="range" id="' . esc_attr( $html_id ) . '-alpha"';
            $html .= ' class="baf-alpha-slider"';
            $html .= ' min="0" max="100" step="1"';
            $html .= ' value="' . esc_attr( $alpha_value * 100 ) . '"';
            $html .= ' aria-valuenow="' . esc_attr( $alpha_value * 100 ) . '"';
            $html .= ' aria-valuemin="0" aria-valuemax="100">';
            
            $html .= '<output class="baf-alpha-value">' . esc_html( $alpha_value * 100 ) . '%</output>';
            $html .= '</div>';
        }

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get preset colors array
     *
     * @return array Colors.
     */
    protected function get_preset_colors() {
        $presets = $this->config['preset_colors'] ?? array();

        if ( is_string( $presets ) ) {
            $presets = array_filter( array_map( 'trim', explode( "\n", $presets ) ) );
        }

        return array_filter( $presets, function( $color ) {
            return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color );
        } );
    }

    /**
     * Convert color to hex (strip alpha if present)
     *
     * @param string $color Color value.
     * @return string Hex color.
     */
    protected function to_hex( $color ) {
        // If already hex
        if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
            return $color;
        }

        // If rgba, extract rgb
        if ( preg_match( '/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches ) ) {
            return sprintf( '#%02x%02x%02x', $matches[1], $matches[2], $matches[3] );
        }

        return '#000000';
    }

    /**
     * Extract alpha value from rgba color
     *
     * @param string $color Color value.
     * @return float Alpha (0-1).
     */
    protected function extract_alpha( $color ) {
        if ( preg_match( '/rgba\([^)]+,\s*([\d.]+)\)/', $color, $matches ) ) {
            return (float) $matches[1];
        }

        return 1;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Hex format
        if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
            return strtolower( $value );
        }

        // RGB/RGBA format
        if ( preg_match( '/^rgba?\([\d,.\s]+\)$/', $value ) ) {
            return $value;
        }

        // Try to fix common issues
        $value = ltrim( $value, '#' );
        if ( preg_match( '/^[A-Fa-f0-9]{6}$/', $value ) ) {
            return '#' . strtolower( $value );
        }
        if ( preg_match( '/^[A-Fa-f0-9]{3}$/', $value ) ) {
            return '#' . strtolower( $value );
        }

        return '';
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $is_valid_hex = preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value );
        $is_valid_rgb = preg_match( '/^rgba?\([\d,.\s]+\)$/', $value );

        if ( ! $is_valid_hex && ! $is_valid_rgb ) {
            $this->add_error( __( 'Please enter a valid color value.', 'bits-accessible-forms' ) );
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        return '<span class="baf-color-display">' .
               '<span class="baf-color-swatch-display" style="background-color: ' . esc_attr( $value ) . ';"></span>' .
               '<span class="baf-color-value-display">' . esc_html( $value ) . '</span>' .
               '</span>';
    }
}
