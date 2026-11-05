<?php
/**
 * Save Field - Save and continue button
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Save class
 */
class QAF_Field_Save extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'save';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'form';

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
            'label'                => __( 'Save Progress', 'quill-accessible-forms' ),
            'button_text'          => __( 'Save and Continue Later', 'quill-accessible-forms' ),
            'button_style'         => 'secondary',
            'button_size'          => 'medium',
            'icon'                 => 'save',
            'icon_position'        => 'before',
            'show_confirmation'    => true,
            'confirmation_message' => __( 'Your progress has been saved!', 'quill-accessible-forms' ),
            'send_link'            => true,
            'email_subject'        => __( 'Continue your form submission', 'quill-accessible-forms' ),
            'email_message'        => __( 'Click the link below to continue filling out your form:', 'quill-accessible-forms' ),
            'link_expiry_days'     => 30,
            'alignment'            => 'left',
            'conditional'          => array(),
            'css_class'            => '',
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
                'label' => array(
                    'type'        => 'text',
                    'label'       => __( 'Label (Screen Reader)', 'quill-accessible-forms' ),
                    'description' => __( 'Accessible label for the button', 'quill-accessible-forms' ),
                ),
                'button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Button Text', 'quill-accessible-forms' ),
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
                        'text'      => __( 'Text/Link', 'quill-accessible-forms' ),
                        'success'   => __( 'Success (Green)', 'quill-accessible-forms' ),
                        'info'      => __( 'Info (Blue)', 'quill-accessible-forms' ),
                    ),
                ),
                'button_size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Button Size', 'quill-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small', 'quill-accessible-forms' ),
                        'medium' => __( 'Medium', 'quill-accessible-forms' ),
                        'large'  => __( 'Large', 'quill-accessible-forms' ),
                    ),
                ),
                'icon' => array(
                    'type'    => 'select',
                    'label'   => __( 'Icon', 'quill-accessible-forms' ),
                    'options' => array(
                        'none'     => __( 'None', 'quill-accessible-forms' ),
                        'save'     => __( 'Save/Disk', 'quill-accessible-forms' ),
                        'bookmark' => __( 'Bookmark', 'quill-accessible-forms' ),
                        'cloud'    => __( 'Cloud', 'quill-accessible-forms' ),
                        'pause'    => __( 'Pause', 'quill-accessible-forms' ),
                    ),
                ),
                'icon_position' => array(
                    'type'      => 'select',
                    'label'     => __( 'Icon Position', 'quill-accessible-forms' ),
                    'options'   => array(
                        'before' => __( 'Before text', 'quill-accessible-forms' ),
                        'after'  => __( 'After text', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'icon' => '!none' ),
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
            'confirmation' => array(
                'show_confirmation' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Confirmation Message', 'quill-accessible-forms' ),
                ),
                'confirmation_message' => array(
                    'type'      => 'text',
                    'label'     => __( 'Confirmation Message', 'quill-accessible-forms' ),
                    'condition' => array( 'show_confirmation' => true ),
                ),
            ),
            'email' => array(
                'send_link' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Email Continuation Link', 'quill-accessible-forms' ),
                ),
                'email_subject' => array(
                    'type'      => 'text',
                    'label'     => __( 'Email Subject', 'quill-accessible-forms' ),
                    'condition' => array( 'send_link' => true ),
                ),
                'email_message' => array(
                    'type'      => 'textarea',
                    'label'     => __( 'Email Message', 'quill-accessible-forms' ),
                    'condition' => array( 'send_link' => true ),
                ),
                'link_expiry_days' => array(
                    'type'      => 'number',
                    'label'     => __( 'Link Expiry (days)', 'quill-accessible-forms' ),
                    'min'       => 1,
                    'max'       => 365,
                    'condition' => array( 'send_link' => true ),
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
        $html_id = $this->get_html_id();
        $label = $this->config['label'] ?? __( 'Save Progress', 'quill-accessible-forms' );
        $button_text = $this->config['button_text'] ?? __( 'Save and Continue Later', 'quill-accessible-forms' );
        $button_style = $this->config['button_style'] ?? 'secondary';
        $button_size = $this->config['button_size'] ?? 'medium';
        $icon = $this->config['icon'] ?? 'save';
        $icon_position = $this->config['icon_position'] ?? 'before';
        $alignment = $this->config['alignment'] ?? 'left';

        $wrapper_class = array( 'qaf-field', 'qaf-field-save', 'qaf-align-' . $alignment );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $button_class = array(
            'qaf-save-button',
            'qaf-button',
            'qaf-button-' . $button_style,
            'qaf-button-' . $button_size,
        );

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        $html .= '<button type="button"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' class="' . esc_attr( implode( ' ', $button_class ) ) . '"';
        $html .= ' data-action="save-progress"';
        $html .= ' aria-label="' . esc_attr( $label ) . '"';
        $html .= '>';

        // Icon before
        if ( $icon !== 'none' && $icon_position === 'before' ) {
            $html .= $this->get_icon_svg( $icon );
        }

        $html .= '<span class="qaf-button-text">' . esc_html( $button_text ) . '</span>';

        // Icon after
        if ( $icon !== 'none' && $icon_position === 'after' ) {
            $html .= $this->get_icon_svg( $icon );
        }

        // Loading spinner
        $html .= '<span class="qaf-button-spinner" style="display: none;" aria-hidden="true"></span>';

        $html .= '</button>';

        // Status message area
        $html .= '<div id="' . esc_attr( $html_id . '-status' ) . '" class="qaf-save-status" role="status" aria-live="polite" style="display: none;">';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get SVG icon markup
     *
     * @param string $icon Icon name.
     * @return string SVG markup.
     */
    protected function get_icon_svg( $icon ) {
        $icons = array(
            'save' => '<svg class="qaf-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>',
            'bookmark' => '<svg class="qaf-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>',
            'cloud' => '<svg class="qaf-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"></path></svg>',
            'pause' => '<svg class="qaf-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>',
        );

        return $icons[ $icon ] ?? '';
    }

    /**
     * Save fields don't need validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Sanitize - nothing to sanitize for button
     *
     * @param mixed $value Value.
     * @return string Empty string.
     */
    public function sanitize( $value ) {
        return '';
    }
}
