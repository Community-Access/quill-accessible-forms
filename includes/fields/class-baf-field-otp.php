<?php
/**
 * OTP (One-Time Password) / Verification Code Field
 *
 * Accessible verification code input with segmented boxes.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_OTP class
 */
class BAF_Field_OTP extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'otp';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'text';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'length'           => 6,
            'input_type'       => 'numeric', // 'numeric', 'alphanumeric', 'alphabetic'
            'separator_after'  => 0,         // Show separator after N digits (0 = none)
            'masked'           => false,     // Hide characters like password
            'auto_submit'      => false,     // Auto-submit when complete
            'placeholder_char' => '○',
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
            'otp_settings' => array(
                'length' => array(
                    'type'    => 'number',
                    'label'   => __( 'Code Length', 'bits-accessible-forms' ),
                    'min'     => 4,
                    'max'     => 10,
                    'default' => 6,
                ),
                'input_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Character Type', 'bits-accessible-forms' ),
                    'options' => array(
                        'numeric'      => __( 'Numbers only (0-9)', 'bits-accessible-forms' ),
                        'alphanumeric' => __( 'Letters and numbers', 'bits-accessible-forms' ),
                        'alphabetic'   => __( 'Letters only (A-Z)', 'bits-accessible-forms' ),
                    ),
                ),
                'separator_after' => array(
                    'type'    => 'number',
                    'label'   => __( 'Show Separator After', 'bits-accessible-forms' ),
                    'desc'    => __( 'Show a separator after N digits (0 = no separator)', 'bits-accessible-forms' ),
                    'min'     => 0,
                    'max'     => 5,
                    'default' => 0,
                ),
            ),
            'behavior' => array(
                'masked' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Mask Input', 'bits-accessible-forms' ),
                    'desc'  => __( 'Hide characters like a password field', 'bits-accessible-forms' ),
                ),
                'auto_submit' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Auto-Submit When Complete', 'bits-accessible-forms' ),
                    'desc'  => __( 'Automatically submit the form when all digits are entered', 'bits-accessible-forms' ),
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
        $name         = 'baf_field_' . $this->get_id();
        $value        = $this->get_value();
        $length       = (int) ( $this->config['length'] ?? 6 );
        $input_type   = $this->config['input_type'] ?? 'numeric';
        $separator    = (int) ( $this->config['separator_after'] ?? 0 );
        $masked       = ! empty( $this->config['masked'] );
        $auto_submit  = ! empty( $this->config['auto_submit'] );
        $placeholder  = $this->config['placeholder_char'] ?? '○';

        // Convert existing value to array
        $value_chars = str_split( (string) $value );
        while ( count( $value_chars ) < $length ) {
            $value_chars[] = '';
        }

        // Determine input mode and pattern
        $inputmode = 'numeric';
        $pattern   = '[0-9]*';
        $allowed   = '0-9';
        
        if ( $input_type === 'alphanumeric' ) {
            $inputmode = 'text';
            $pattern   = '[A-Za-z0-9]*';
            $allowed   = 'A-Za-z0-9';
        } elseif ( $input_type === 'alphabetic' ) {
            $inputmode = 'text';
            $pattern   = '[A-Za-z]*';
            $allowed   = 'A-Za-z';
        }

        // Build the OTP input group
        $field = '<div class="baf-otp-wrapper" role="group" aria-labelledby="' . esc_attr( $html_id ) . '-label">';
        $field .= '<span id="' . esc_attr( $html_id ) . '-label" class="baf-sr-only">' . 
                  sprintf(
                      /* translators: %d: number of digits */
                      __( 'Enter %d digit verification code', 'bits-accessible-forms' ),
                      $length
                  ) . '</span>';

        for ( $i = 0; $i < $length; $i++ ) {
            // Add separator if needed
            if ( $separator > 0 && $i > 0 && $i % $separator === 0 ) {
                $field .= '<span class="baf-otp-separator" aria-hidden="true">-</span>';
            }

            $input_id    = $html_id . '-' . $i;
            $input_value = $value_chars[ $i ] ?? '';
            $is_first    = $i === 0;

            $field .= '<input';
            $field .= ' type="' . ( $masked ? 'password' : 'text' ) . '"';
            $field .= ' id="' . esc_attr( $input_id ) . '"';
            $field .= ' class="baf-otp-input"';
            $field .= ' value="' . esc_attr( $input_value ) . '"';
            $field .= ' maxlength="1"';
            $field .= ' inputmode="' . esc_attr( $inputmode ) . '"';
            $field .= ' pattern="' . esc_attr( $pattern ) . '"';
            $field .= ' autocomplete="one-time-code"';
            $field .= ' data-index="' . $i . '"';
            $field .= ' data-allowed="' . esc_attr( $allowed ) . '"';
            $field .= ' placeholder="' . esc_attr( $placeholder ) . '"';
            $field .= ' aria-label="' . sprintf(
                /* translators: 1: current digit number, 2: total digits */
                __( 'Digit %1$d of %2$d', 'bits-accessible-forms' ),
                $i + 1,
                $length
            ) . '"';
            
            if ( $is_first ) {
                // First input gets ARIA attributes
                $aria = $this->get_aria_attributes();
                foreach ( $aria as $attr => $attr_value ) {
                    $field .= ' ' . esc_attr( $attr ) . '="' . esc_attr( $attr_value ) . '"';
                }
            }

            $field .= '>';
        }

        $field .= '</div>';

        // Hidden input for form submission
        $field .= '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $html_id ) . '" value="' . esc_attr( $value ) . '" class="baf-otp-hidden"';
        $field .= ' data-length="' . $length . '"';
        $field .= ' data-auto-submit="' . ( $auto_submit ? 'true' : 'false' ) . '"';
        $field .= '>';

        // Live region for announcements
        $field .= '<div class="baf-sr-only baf-otp-status" aria-live="polite"></div>';

        return $this->render_wrapper( $field );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        $value = sanitize_text_field( $value );
        
        // Filter based on input type
        $input_type = $this->config['input_type'] ?? 'numeric';
        
        if ( $input_type === 'numeric' ) {
            $value = preg_replace( '/[^0-9]/', '', $value );
        } elseif ( $input_type === 'alphabetic' ) {
            $value = preg_replace( '/[^A-Za-z]/', '', $value );
        } else {
            $value = preg_replace( '/[^A-Za-z0-9]/', '', $value );
        }

        // Limit to configured length
        $length = (int) ( $this->config['length'] ?? 6 );
        return substr( $value, 0, $length );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $length = (int) ( $this->config['length'] ?? 6 );

        if ( strlen( $value ) !== $length ) {
            $this->add_error( sprintf(
                /* translators: %d: required code length */
                __( 'Please enter a complete %d-digit code.', 'bits-accessible-forms' ),
                $length
            ) );
        }
    }
}
