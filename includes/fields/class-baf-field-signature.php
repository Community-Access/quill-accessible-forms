<?php
/**
 * Signature Field - Accessible signature capture
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Signature class
 */
class BAF_Field_Signature extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'signature';

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
            'width'            => 400,
            'height'           => 150,
            'stroke_width'     => 2,
            'stroke_color'     => '#000000',
            'background_color' => '#ffffff',
            'allow_type'       => true,
            'font_family'      => 'cursive',
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
            'dimensions' => array(
                'width' => array(
                    'type'  => 'number',
                    'label' => __( 'Canvas Width (px)', 'bits-accessible-forms' ),
                    'min'   => 200,
                    'max'   => 800,
                ),
                'height' => array(
                    'type'  => 'number',
                    'label' => __( 'Canvas Height (px)', 'bits-accessible-forms' ),
                    'min'   => 75,
                    'max'   => 300,
                ),
            ),
            'appearance' => array(
                'stroke_width' => array(
                    'type'  => 'number',
                    'label' => __( 'Stroke Width (px)', 'bits-accessible-forms' ),
                    'min'   => 1,
                    'max'   => 5,
                ),
                'stroke_color' => array(
                    'type'  => 'color',
                    'label' => __( 'Stroke Color', 'bits-accessible-forms' ),
                ),
                'background_color' => array(
                    'type'  => 'color',
                    'label' => __( 'Background Color', 'bits-accessible-forms' ),
                ),
            ),
            'accessibility' => array(
                'allow_type' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Typed Signature', 'bits-accessible-forms' ),
                    'desc'  => __( 'Essential for accessibility - allows users to type their name instead of drawing.', 'bits-accessible-forms' ),
                ),
                'font_family' => array(
                    'type'    => 'select',
                    'label'   => __( 'Typed Signature Font', 'bits-accessible-forms' ),
                    'options' => array(
                        'cursive'              => 'Cursive',
                        '"Brush Script MT"'    => 'Brush Script',
                        '"Lucida Handwriting"' => 'Lucida Handwriting',
                        'Georgia'              => 'Georgia',
                    ),
                ),
            ),
        );
    }

    /**
     * Render the signature field with accessible alternatives
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        $width   = (int) $this->config['width'];
        $height  = (int) $this->config['height'];

        $wrapper_class = $this->get_wrapper_classes();
        $desc_id = $html_id . '-desc';
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= '<label id="' . esc_attr( $html_id ) . '-label" class="baf-field-label">';
        $html .= '<span class="baf-label-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</label>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Mode selector tabs (for accessibility - allows choosing draw or type)
        if ( ! empty( $this->config['allow_type'] ) ) {
            $html .= '<div class="baf-signature-modes" role="tablist" aria-label="' . esc_attr__( 'Signature input method', 'bits-accessible-forms' ) . '">';
            
            $html .= '<button type="button" role="tab" class="baf-signature-mode-tab" ';
            $html .= 'id="' . esc_attr( $html_id ) . '-tab-draw" ';
            $html .= 'aria-controls="' . esc_attr( $html_id ) . '-panel-draw" ';
            $html .= 'aria-selected="true" tabindex="0">';
            $html .= '<span class="baf-icon-pen" aria-hidden="true"></span> ';
            $html .= esc_html__( 'Draw', 'bits-accessible-forms' );
            $html .= '</button>';

            $html .= '<button type="button" role="tab" class="baf-signature-mode-tab" ';
            $html .= 'id="' . esc_attr( $html_id ) . '-tab-type" ';
            $html .= 'aria-controls="' . esc_attr( $html_id ) . '-panel-type" ';
            $html .= 'aria-selected="false" tabindex="-1">';
            $html .= '<span class="baf-icon-keyboard" aria-hidden="true"></span> ';
            $html .= esc_html__( 'Type', 'bits-accessible-forms' );
            $html .= '</button>';

            $html .= '</div>';
        }

        // Draw panel
        $html .= '<div id="' . esc_attr( $html_id ) . '-panel-draw" role="tabpanel" ';
        $html .= 'aria-labelledby="' . esc_attr( $html_id ) . '-tab-draw" ';
        $html .= 'class="baf-signature-panel baf-signature-panel-draw">';

        // Canvas instructions
        $html .= '<p id="' . esc_attr( $html_id ) . '-canvas-instructions" class="baf-signature-instructions">';
        $html .= esc_html__( 'Draw your signature in the box below using mouse, touch, or stylus.', 'bits-accessible-forms' );
        $html .= '</p>';

        // Signature canvas
        $html .= '<canvas id="' . esc_attr( $html_id ) . '-canvas" ';
        $html .= 'class="baf-signature-canvas" ';
        $html .= 'width="' . esc_attr( $width ) . '" ';
        $html .= 'height="' . esc_attr( $height ) . '" ';
        $html .= 'aria-describedby="' . esc_attr( $html_id ) . '-canvas-instructions" ';
        $html .= 'aria-label="' . esc_attr__( 'Signature pad. Use mouse or touch to draw.', 'bits-accessible-forms' ) . '" ';
        $html .= 'role="img" ';
        $html .= 'tabindex="0" ';
        $html .= 'data-stroke-width="' . esc_attr( $this->config['stroke_width'] ) . '" ';
        $html .= 'data-stroke-color="' . esc_attr( $this->config['stroke_color'] ) . '" ';
        $html .= 'data-background="' . esc_attr( $this->config['background_color'] ) . '">';
        $html .= '</canvas>';

        // Canvas controls
        $html .= '<div class="baf-signature-controls">';
        $html .= '<button type="button" class="baf-signature-clear" aria-label="' . esc_attr__( 'Clear signature', 'bits-accessible-forms' ) . '">';
        $html .= '<span class="baf-icon-clear" aria-hidden="true"></span> ';
        $html .= esc_html__( 'Clear', 'bits-accessible-forms' );
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</div>';

        // Type panel (accessibility alternative)
        if ( ! empty( $this->config['allow_type'] ) ) {
            $html .= '<div id="' . esc_attr( $html_id ) . '-panel-type" role="tabpanel" ';
            $html .= 'aria-labelledby="' . esc_attr( $html_id ) . '-tab-type" ';
            $html .= 'class="baf-signature-panel baf-signature-panel-type" hidden>';

            $html .= '<label for="' . esc_attr( $html_id ) . '-typed" class="baf-signature-typed-label">';
            $html .= esc_html__( 'Type your full legal name:', 'bits-accessible-forms' );
            $html .= '</label>';

            $html .= '<input type="text" id="' . esc_attr( $html_id ) . '-typed" ';
            $html .= 'class="baf-signature-typed-input" ';
            $html .= 'name="' . esc_attr( $name ) . '_typed" ';
            $html .= 'autocomplete="name" ';
            $html .= 'style="font-family: ' . esc_attr( $this->config['font_family'] ) . ';">';

            // Preview of typed signature
            $html .= '<div class="baf-signature-typed-preview" ';
            $html .= 'style="font-family: ' . esc_attr( $this->config['font_family'] ) . '; ';
            $html .= 'width: ' . esc_attr( $width ) . 'px; height: ' . esc_attr( $height ) . 'px;" ';
            $html .= 'aria-live="polite"></div>';

            $html .= '</div>';
        }

        // Hidden input for signature data
        $html .= '<input type="hidden" id="' . esc_attr( $html_id ) . '" ';
        $html .= 'name="' . esc_attr( $name ) . '" ';
        $html .= 'value="' . esc_attr( $value ) . '" ';
        $html .= 'data-mode="draw">';

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
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // Value can be base64 encoded image data or typed name
        if ( strpos( $value, 'data:image/' ) === 0 ) {
            // Validate it's proper base64 image
            if ( preg_match( '/^data:image\/(png|jpeg|jpg);base64,[a-zA-Z0-9+\/=]+$/', $value ) ) {
                return $value;
            }
            return '';
        }

        // Typed signature - sanitize as text
        return sanitize_text_field( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Check if it's base64 image data
        if ( strpos( $value, 'data:image/' ) === 0 ) {
            // Validate base64 format
            if ( ! preg_match( '/^data:image\/(png|jpeg|jpg);base64,[a-zA-Z0-9+\/=]+$/', $value ) ) {
                $this->add_error( __( 'Invalid signature data.', 'bits-accessible-forms' ) );
                return;
            }

            // Check file size (limit to ~500KB decoded)
            $base64_data = substr( $value, strpos( $value, ',' ) + 1 );
            $decoded_size = strlen( base64_decode( $base64_data ) );
            
            if ( $decoded_size > 500 * 1024 ) {
                $this->add_error( __( 'Signature file is too large.', 'bits-accessible-forms' ) );
            }
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

        // If base64 image, show as image
        if ( strpos( $value, 'data:image/' ) === 0 ) {
            return '<img src="' . esc_attr( $value ) . '" alt="' . esc_attr__( 'Signature', 'bits-accessible-forms' ) . '" class="baf-signature-display">';
        }

        // Typed signature
        return sprintf(
            '<span class="baf-typed-signature" style="font-family: cursive;">%s</span>',
            esc_html( $value )
        );
    }

    /**
     * Get value for storage
     *
     * @return array Value array with image and typed.
     */
    public function get_storage_value() {
        $name = 'baf_field_' . $this->get_id();
        
        return array(
            'image' => $this->get_value(),
            'typed' => isset( $_POST[ $name . '_typed' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name . '_typed' ] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
        );
    }
}
