<?php
/**
 * Submit Button Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Submit class
 */
class BAF_Field_Submit extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'submit';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'layout';

    /**
     * Whether field stores data
     *
     * @var bool
     */
    protected $has_value = false;

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'button_text'      => __( 'Submit', 'bits-accessible-forms' ),
            'processing_text'  => __( 'Submitting...', 'bits-accessible-forms' ),
            'button_style'     => 'primary',
            'button_size'      => 'normal',
            'full_width'       => false,
            'icon'             => '',
            'icon_position'    => 'before',
            'alignment'        => 'left',
            'conditional'      => array(),
            'css_class'        => '',
        );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Button Text', 'bits-accessible-forms' ),
                ),
                'processing_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Processing Text', 'bits-accessible-forms' ),
                    'desc'  => __( 'Text shown while form is being submitted.', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'button_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Button Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'primary'   => __( 'Primary', 'bits-accessible-forms' ),
                        'secondary' => __( 'Secondary', 'bits-accessible-forms' ),
                        'outline'   => __( 'Outline', 'bits-accessible-forms' ),
                        'link'      => __( 'Link Style', 'bits-accessible-forms' ),
                    ),
                ),
                'button_size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Button Size', 'bits-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small', 'bits-accessible-forms' ),
                        'normal' => __( 'Normal', 'bits-accessible-forms' ),
                        'large'  => __( 'Large', 'bits-accessible-forms' ),
                    ),
                ),
                'full_width' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Full Width', 'bits-accessible-forms' ),
                ),
                'alignment' => array(
                    'type'    => 'select',
                    'label'   => __( 'Alignment', 'bits-accessible-forms' ),
                    'options' => array(
                        'left'   => __( 'Left', 'bits-accessible-forms' ),
                        'center' => __( 'Center', 'bits-accessible-forms' ),
                        'right'  => __( 'Right', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'icon' => array(
                'icon' => array(
                    'type'    => 'select',
                    'label'   => __( 'Icon', 'bits-accessible-forms' ),
                    'options' => array(
                        ''             => __( 'None', 'bits-accessible-forms' ),
                        'arrow-right'  => __( 'Arrow Right', 'bits-accessible-forms' ),
                        'check'        => __( 'Checkmark', 'bits-accessible-forms' ),
                        'send'         => __( 'Send', 'bits-accessible-forms' ),
                        'paper-plane'  => __( 'Paper Plane', 'bits-accessible-forms' ),
                    ),
                ),
                'icon_position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Icon Position', 'bits-accessible-forms' ),
                    'options' => array(
                        'before' => __( 'Before Text', 'bits-accessible-forms' ),
                        'after'  => __( 'After Text', 'bits-accessible-forms' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Render the submit button
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $text = $this->config['button_text'] ?? __( 'Submit', 'bits-accessible-forms' );
        $processing = $this->config['processing_text'] ?? __( 'Submitting...', 'bits-accessible-forms' );
        $style = $this->config['button_style'] ?? 'primary';
        $size = $this->config['button_size'] ?? 'normal';
        $icon = $this->config['icon'] ?? '';
        $icon_position = $this->config['icon_position'] ?? 'before';
        $alignment = $this->config['alignment'] ?? 'left';
        $full_width = ! empty( $this->config['full_width'] );

        $wrapper_class = array( 
            'baf-submit-wrapper',
            'baf-align-' . $alignment,
        );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $button_class = array(
            'baf-submit-button',
            'baf-button',
            'baf-button-' . $style,
            'baf-button-' . $size,
        );
        
        if ( $full_width ) {
            $button_class[] = 'baf-button-full';
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        $html .= '<button type="submit" id="' . esc_attr( $html_id ) . '"';
        $html .= ' class="' . esc_attr( implode( ' ', $button_class ) ) . '"';
        $html .= ' data-processing-text="' . esc_attr( $processing ) . '"';
        $html .= ' data-original-text="' . esc_attr( $text ) . '">';

        // Icon before
        if ( $icon && $icon_position === 'before' ) {
            $html .= '<span class="baf-button-icon baf-icon-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ';
        }

        // Button text
        $html .= '<span class="baf-button-text">' . esc_html( $text ) . '</span>';

        // Icon after
        if ( $icon && $icon_position === 'after' ) {
            $html .= ' <span class="baf-button-icon baf-icon-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
        }

        // Loading spinner (hidden by default)
        $html .= '<span class="baf-button-spinner" aria-hidden="true" style="display: none;">';
        $html .= '<span class="baf-spinner"></span>';
        $html .= '</span>';

        $html .= '</button>';

        // Status announcer for screen readers
        $html .= '<span class="baf-submit-status" role="status" aria-live="polite" aria-atomic="true"></span>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Submit button doesn't need validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Submit button doesn't store values
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }
}
