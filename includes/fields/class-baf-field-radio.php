<?php
/**
 * Radio Group Field - uses fieldset/legend for accessibility
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Radio class
 * 
 * Radio button group with proper fieldset/legend for WCAG 2.2 AA compliance.
 */
class BAF_Field_Radio extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'radio';

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
            'choices'          => array(),
            'layout'           => 'vertical', // vertical, horizontal, inline
            'other_option'     => false,
            'other_label'      => __( 'Other', 'bits-accessible-forms' ),
            'randomize'        => false,
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
                    'label' => __( 'Group Label', 'bits-accessible-forms' ),
                    'desc'  => __( 'Appears as the legend for this radio group.', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'choices' => array(
                    'type'  => 'choices',
                    'label' => __( 'Choices', 'bits-accessible-forms' ),
                    'desc'  => __( 'Enter each choice on a new line.', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'bits-accessible-forms' ),
                    'options' => array(
                        'vertical'   => __( 'Vertical (stacked)', 'bits-accessible-forms' ),
                        'horizontal' => __( 'Horizontal (side by side)', 'bits-accessible-forms' ),
                        'inline'     => __( 'Inline', 'bits-accessible-forms' ),
                    ),
                ),
                'other_option' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable "Other" Option', 'bits-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'randomize' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Randomize Choices', 'bits-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'select',
                    'label' => __( 'Default Selection', 'bits-accessible-forms' ),
                    'options' => 'choices', // Dynamically populated from choices
                ),
            ),
        );
    }

    /**
     * Render the field HTML with fieldset/legend
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $choices = $this->get_choices();
        
        if ( empty( $choices ) ) {
            return '';
        }

        $html_id   = $this->get_html_id();
        $name      = 'baf_field_' . $this->get_id();
        $value     = $this->get_value();
        $layout    = $this->config['layout'];
        $required  = $this->is_required();

        $wrapper_class = array(
            'baf-field',
            'baf-field-' . $this->type,
            'baf-field-size-' . $this->config['size'],
            'baf-radio-layout-' . $layout,
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

        // Start output with fieldset for grouped controls (WCAG requirement)
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '" data-field-id="' . esc_attr( $this->get_id() ) . '"';
        
        // Add ARIA attributes to fieldset
        if ( ! empty( $this->errors ) ) {
            $html .= ' aria-invalid="true"';
        }
        
        // Add describedby for group
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

        // Legend (acts as the group label)
        $html .= '<legend class="baf-fieldset-legend">';
        $html .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Description above options (if configured)
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Radio options container
        $html .= '<div class="baf-radio-options" role="radiogroup">';

        foreach ( $choices as $index => $choice ) {
            $html .= $this->render_radio_option( $choice, $index, $name, $value, $required && $index === 0 );
        }

        // "Other" option if enabled
        if ( ! empty( $this->config['other_option'] ) ) {
            $html .= $this->render_other_option( $name, $value );
        }

        $html .= '</div>';

        // Description below options (default)
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
     * Render individual radio option
     *
     * @param array  $choice Choice data.
     * @param int    $index  Choice index.
     * @param string $name   Field name.
     * @param mixed  $value  Current value.
     * @param bool   $first  Whether this is the first option.
     * @return string Radio option HTML.
     */
    protected function render_radio_option( $choice, $index, $name, $value, $first = false ) {
        $choice_value = $choice['value'] ?? $choice['label'];
        $choice_label = $choice['label'];
        $option_id    = $this->get_html_id() . '-' . $index;
        $checked      = $value === $choice_value;

        $html = '<div class="baf-radio-option">';
        
        $html .= '<input type="radio"';
        $html .= ' id="' . esc_attr( $option_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $choice_value ) . '"';
        
        if ( $checked ) {
            $html .= ' checked';
        }
        
        // Only first option is tabbable in a radio group (roving tabindex pattern)
        // This is handled by browser natively for radio buttons
        
        $html .= ' class="baf-radio-input">';
        
        $html .= '<label for="' . esc_attr( $option_id ) . '" class="baf-radio-label">';
        $html .= '<span class="baf-radio-indicator" aria-hidden="true"></span>';
        $html .= '<span class="baf-radio-text">' . esc_html( $choice_label ) . '</span>';
        $html .= '</label>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Render "Other" option with text input
     *
     * @param string $name  Field name.
     * @param mixed  $value Current value.
     * @return string Other option HTML.
     */
    protected function render_other_option( $name, $value ) {
        $other_id     = $this->get_html_id() . '-other';
        $other_label  = $this->config['other_label'] ?? __( 'Other', 'bits-accessible-forms' );
        $is_other     = ! empty( $value ) && ! $this->is_predefined_choice( $value );
        $other_value  = $is_other ? $value : '';

        $html = '<div class="baf-radio-option baf-radio-other">';
        
        $html .= '<input type="radio"';
        $html .= ' id="' . esc_attr( $other_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="__other__"';
        $html .= ' class="baf-radio-input baf-radio-other-trigger"';
        $html .= ' aria-controls="' . esc_attr( $other_id ) . '-input"';
        
        if ( $is_other ) {
            $html .= ' checked';
        }
        
        $html .= '>';
        
        $html .= '<label for="' . esc_attr( $other_id ) . '" class="baf-radio-label">';
        $html .= '<span class="baf-radio-indicator" aria-hidden="true"></span>';
        $html .= '<span class="baf-radio-text">' . esc_html( $other_label ) . '</span>';
        $html .= '</label>';
        
        // Text input for "other" value
        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $other_id ) . '-input"';
        $html .= ' name="' . esc_attr( $name ) . '_other"';
        $html .= ' value="' . esc_attr( $other_value ) . '"';
        $html .= ' class="baf-radio-other-input"';
        $html .= ' aria-label="' . esc_attr__( 'Specify other value', 'bits-accessible-forms' ) . '"';
        
        if ( ! $is_other ) {
            $html .= ' disabled';
        }
        
        $html .= '>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Get choices array
     *
     * @return array Choices.
     */
    protected function get_choices() {
        $choices = $this->config['choices'] ?? array();

        // Convert string format to array
        if ( is_string( $choices ) ) {
            $lines = explode( "\n", $choices );
            $choices = array();
            
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( empty( $line ) ) {
                    continue;
                }
                
                // Support value|label format
                if ( strpos( $line, '|' ) !== false ) {
                    list( $value, $label ) = explode( '|', $line, 2 );
                    $choices[] = array(
                        'value' => trim( $value ),
                        'label' => trim( $label ),
                    );
                } else {
                    $choices[] = array(
                        'value' => $line,
                        'label' => $line,
                    );
                }
            }
        }

        // Randomize if enabled
        if ( ! empty( $this->config['randomize'] ) ) {
            shuffle( $choices );
        }

        return $choices;
    }

    /**
     * Check if value is a predefined choice
     *
     * @param string $value Value to check.
     * @return bool True if predefined.
     */
    protected function is_predefined_choice( $value ) {
        foreach ( $this->get_choices() as $choice ) {
            if ( ( $choice['value'] ?? $choice['label'] ) === $value ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        // Handle "other" option
        if ( $value === '__other__' && isset( $_POST[ 'baf_field_' . $this->get_id() . '_other' ] ) ) {
            return sanitize_text_field( wp_unslash( $_POST[ 'baf_field_' . $this->get_id() . '_other' ] ) );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // If not allowing other and value is not a predefined choice
        if ( empty( $this->config['other_option'] ) && ! $this->is_predefined_choice( $value ) ) {
            $this->add_error( __( 'Please select a valid option.', 'bits-accessible-forms' ) );
        }
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code.
     */
    public function to_bfl() {
        $bfl = 'radio #' . $this->get_id() . ' "' . addslashes( $this->get_label() ) . '"';
        
        $choices = $this->get_choices();
        if ( ! empty( $choices ) ) {
            $bfl .= "\n  @choices";
            foreach ( $choices as $choice ) {
                $bfl .= "\n    \"" . addslashes( $choice['label'] ) . '"';
            }
        }

        if ( $this->is_required() ) {
            $bfl .= "\n  @required";
        }

        return $bfl;
    }
}
