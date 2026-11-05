<?php
/**
 * Submit Button Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Submit class
 */
class QAF_Field_Submit extends QAF_Field {

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
            'button_text'      => __( 'Submit', 'quill-accessible-forms' ),
            'processing_text'  => __( 'Submitting...', 'quill-accessible-forms' ),
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
                    'label' => __( 'Button Text', 'quill-accessible-forms' ),
                ),
                'processing_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Processing Text', 'quill-accessible-forms' ),
                    'desc'  => __( 'Text shown while form is being submitted.', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'button_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Button Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'primary'   => __( 'Primary', 'quill-accessible-forms' ),
                        'secondary' => __( 'Secondary', 'quill-accessible-forms' ),
                        'outline'   => __( 'Outline', 'quill-accessible-forms' ),
                        'link'      => __( 'Link Style', 'quill-accessible-forms' ),
                    ),
                ),
                'button_size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Button Size', 'quill-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small', 'quill-accessible-forms' ),
                        'normal' => __( 'Normal', 'quill-accessible-forms' ),
                        'large'  => __( 'Large', 'quill-accessible-forms' ),
                    ),
                ),
                'full_width' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Full Width', 'quill-accessible-forms' ),
                ),
                'alignment' => array(
                    'type'    => 'select',
                    'label'   => __( 'Alignment', 'quill-accessible-forms' ),
                    'options' => array(
                        'left'   => __( 'Left', 'quill-accessible-forms' ),
                        'center' => __( 'Center', 'quill-accessible-forms' ),
                        'right'  => __( 'Right', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'icon' => array(
                'icon' => array(
                    'type'    => 'select',
                    'label'   => __( 'Icon', 'quill-accessible-forms' ),
                    'options' => array(
                        ''             => __( 'None', 'quill-accessible-forms' ),
                        'arrow-right'  => __( 'Arrow Right', 'quill-accessible-forms' ),
                        'check'        => __( 'Checkmark', 'quill-accessible-forms' ),
                        'send'         => __( 'Send', 'quill-accessible-forms' ),
                        'paper-plane'  => __( 'Paper Plane', 'quill-accessible-forms' ),
                    ),
                ),
                'icon_position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Icon Position', 'quill-accessible-forms' ),
                    'options' => array(
                        'before' => __( 'Before Text', 'quill-accessible-forms' ),
                        'after'  => __( 'After Text', 'quill-accessible-forms' ),
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
        $text = $this->config['button_text'] ?? __( 'Submit', 'quill-accessible-forms' );
        $processing = $this->config['processing_text'] ?? __( 'Submitting...', 'quill-accessible-forms' );
        $style = $this->config['button_style'] ?? 'primary';
        $size = $this->config['button_size'] ?? 'normal';
        $icon = $this->config['icon'] ?? '';
        $icon_position = $this->config['icon_position'] ?? 'before';
        $alignment = $this->config['alignment'] ?? 'left';
        $full_width = ! empty( $this->config['full_width'] );

        $wrapper_class = array( 
            'qaf-submit-wrapper',
            'qaf-align-' . $alignment,
        );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $button_class = array(
            'qaf-submit-button',
            'qaf-button',
            'qaf-button-' . $style,
            'qaf-button-' . $size,
        );
        
        if ( $full_width ) {
            $button_class[] = 'qaf-button-full';
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        $html .= '<button type="submit" id="' . esc_attr( $html_id ) . '"';
        $html .= ' class="' . esc_attr( implode( ' ', $button_class ) ) . '"';
        $html .= ' data-processing-text="' . esc_attr( $processing ) . '"';
        $html .= ' data-original-text="' . esc_attr( $text ) . '">';

        // Icon before
        if ( $icon && $icon_position === 'before' ) {
            $html .= '<span class="qaf-button-icon qaf-icon-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ';
        }

        // Button text
        $html .= '<span class="qaf-button-text">' . esc_html( $text ) . '</span>';

        // Icon after
        if ( $icon && $icon_position === 'after' ) {
            $html .= ' <span class="qaf-button-icon qaf-icon-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
        }

        // Loading spinner (hidden by default)
        $html .= '<span class="qaf-button-spinner" aria-hidden="true" style="display: none;">';
        $html .= '<span class="qaf-spinner"></span>';
        $html .= '</span>';

        $html .= '</button>';

        // Status announcer for screen readers
        $html .= '<span class="qaf-submit-status" role="status" aria-live="polite" aria-atomic="true"></span>';

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
