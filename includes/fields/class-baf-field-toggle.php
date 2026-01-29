<?php
/**
 * Toggle Field - Accessible on/off switch
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Toggle class
 */
class BAF_Field_Toggle extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'toggle';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'choice';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'on_label'    => __( 'On', 'bits-accessible-forms' ),
            'off_label'   => __( 'Off', 'bits-accessible-forms' ),
            'on_value'    => '1',
            'off_value'   => '0',
            'default'     => false,
            'style'       => 'switch',
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
                    'label' => __( 'Required (must be On)', 'bits-accessible-forms' ),
                ),
                'default' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Default to On', 'bits-accessible-forms' ),
                ),
            ),
            'labels' => array(
                'on_label' => array(
                    'type'  => 'text',
                    'label' => __( 'On State Label', 'bits-accessible-forms' ),
                ),
                'off_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Off State Label', 'bits-accessible-forms' ),
                ),
            ),
            'values' => array(
                'on_value' => array(
                    'type'  => 'text',
                    'label' => __( 'On Value (stored)', 'bits-accessible-forms' ),
                ),
                'off_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Off Value (stored)', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'switch'   => __( 'Toggle Switch', 'bits-accessible-forms' ),
                        'checkbox' => __( 'Checkbox Style', 'bits-accessible-forms' ),
                        'button'   => __( 'Button Style', 'bits-accessible-forms' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Render the toggle field with switch role
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        $style   = $this->config['style'] ?? 'switch';

        // Determine if checked
        $default = ! empty( $this->config['default'] );
        $on_value = $this->config['on_value'] ?? '1';
        $is_checked = ( $value === $on_value ) || ( $value === '' && $default );

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-toggle-style-' . $style;
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Toggle container
        $html .= '<div class="baf-toggle-container">';

        // The actual checkbox (hidden, semantic)
        $html .= '<input type="checkbox" id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $on_value ) . '"';
        $html .= ' class="baf-toggle-input"';
        $html .= ' role="switch"';
        $html .= ' aria-checked="' . ( $is_checked ? 'true' : 'false' ) . '"';
        
        if ( $is_checked ) {
            $html .= ' checked';
        }
        
        if ( $this->is_required() ) {
            $html .= ' aria-required="true"';
        }
        
        if ( $this->get_description() ) {
            $html .= ' aria-describedby="' . esc_attr( $html_id ) . '-desc"';
        }
        
        $html .= '>';

        // Visual toggle
        $html .= '<span class="baf-toggle-track" aria-hidden="true">';
        $html .= '<span class="baf-toggle-thumb"></span>';
        $html .= '</span>';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-toggle-label">';
        $html .= '<span class="baf-label-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</label>';

        $html .= '</div>';

        // State text (announced when changed)
        $html .= '<span class="baf-toggle-state screen-reader-text" aria-live="polite">';
        $html .= $is_checked ? esc_html( $this->config['on_label'] ) : esc_html( $this->config['off_label'] );
        $html .= '</span>';

        // Visual state labels
        $html .= '<span class="baf-toggle-labels" aria-hidden="true">';
        $html .= '<span class="baf-toggle-off-label' . ( $is_checked ? '' : ' active' ) . '">' . esc_html( $this->config['off_label'] ) . '</span>';
        $html .= '<span class="baf-toggle-on-label' . ( $is_checked ? ' active' : '' ) . '">' . esc_html( $this->config['on_label'] ) . '</span>';
        $html .= '</span>';

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
        $on_value = $this->config['on_value'] ?? '1';
        $off_value = $this->config['off_value'] ?? '0';

        // If checkbox was checked, value will be the on_value
        // If not checked, field won't be in POST
        return ( $value === $on_value ) ? $on_value : $off_value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $on_value = $this->config['on_value'] ?? '1';
        $off_value = $this->config['off_value'] ?? '0';

        // If required, must be on
        if ( $this->is_required() && $value !== $on_value ) {
            $this->add_error( sprintf(
                /* translators: Field label */
                __( '%s must be enabled.', 'bits-accessible-forms' ),
                $this->get_label()
            ) );
        }
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty (off state is NOT empty for non-required fields).
     */
    protected function is_empty( $value ) {
        // For required check, off value counts as empty
        $on_value = $this->config['on_value'] ?? '1';
        return $value !== $on_value;
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        $on_value = $this->config['on_value'] ?? '1';
        
        if ( $value === $on_value ) {
            return $this->config['on_label'] ?? __( 'On', 'bits-accessible-forms' );
        }
        
        return $this->config['off_label'] ?? __( 'Off', 'bits-accessible-forms' );
    }
}
