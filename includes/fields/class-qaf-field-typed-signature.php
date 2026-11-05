<?php
/**
 * Typed Signature Field
 *
 * Accessible alternative to handwritten signature - type your name to sign.
 * Renders typed text in a signature-style font.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Typed_Signature class
 */
class QAF_Field_Typed_Signature extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'typed_signature';

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
            'font_style'          => 'cursive',     // 'cursive', 'handwritten', 'formal'
            'agreement_text'      => '',
            'show_date'           => true,
            'show_preview'        => true,
            'placeholder'         => __( 'Type your full legal name', 'quill-accessible-forms' ),
            'confirmation_text'   => __( 'By typing my name above, I agree that this electronic signature is legally binding.', 'quill-accessible-forms' ),
            'require_confirmation' => true,
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
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'signature' => array(
                'font_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Signature Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'cursive'     => __( 'Cursive', 'quill-accessible-forms' ),
                        'handwritten' => __( 'Handwritten', 'quill-accessible-forms' ),
                        'formal'      => __( 'Formal', 'quill-accessible-forms' ),
                    ),
                ),
                'show_preview' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Signature Preview', 'quill-accessible-forms' ),
                    'desc'  => __( 'Display a live preview of the signature as the user types', 'quill-accessible-forms' ),
                ),
                'show_date' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Date', 'quill-accessible-forms' ),
                    'desc'  => __( 'Display the current date below the signature', 'quill-accessible-forms' ),
                ),
            ),
            'agreement' => array(
                'agreement_text' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Agreement Text', 'quill-accessible-forms' ),
                    'desc'  => __( 'Text displayed above the signature (e.g., terms being agreed to)', 'quill-accessible-forms' ),
                ),
                'confirmation_text' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Confirmation Text', 'quill-accessible-forms' ),
                    'desc'  => __( 'Legal confirmation text displayed below the signature', 'quill-accessible-forms' ),
                ),
                'require_confirmation' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Require Confirmation Checkbox', 'quill-accessible-forms' ),
                    'desc'  => __( 'User must check a box to confirm their signature', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder Text', 'quill-accessible-forms' ),
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
        $name         = 'qaf_field_' . $this->get_id();
        $value        = $this->get_value();
        $font_style   = $this->config['font_style'] ?? 'cursive';
        $show_preview = $this->config['show_preview'] ?? true;
        $show_date    = $this->config['show_date'] ?? true;
        $require_conf = $this->config['require_confirmation'] ?? true;

        // Get signature value and confirmation status
        $signature_value = '';
        $is_confirmed    = false;
        
        if ( is_array( $value ) ) {
            $signature_value = $value['signature'] ?? '';
            $is_confirmed    = ! empty( $value['confirmed'] );
        } else {
            $signature_value = $value;
        }

        $field = '<div class="qaf-typed-signature qaf-signature-style-' . esc_attr( $font_style ) . '">';

        // Agreement text
        if ( ! empty( $this->config['agreement_text'] ) ) {
            $field .= '<div class="qaf-signature-agreement">';
            $field .= wp_kses_post( wpautop( $this->config['agreement_text'] ) );
            $field .= '</div>';
        }

        // Signature input
        $input_attrs = array(
            'type'        => 'text',
            'id'          => $html_id,
            'name'        => $name . '[signature]',
            'value'       => $signature_value,
            'class'       => 'qaf-signature-input',
            'placeholder' => $this->config['placeholder'] ?? '',
            'autocomplete' => 'name',
        );

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $input_attrs = array_merge( $input_attrs, $aria );

        $field .= '<div class="qaf-signature-input-wrapper">';
        $field .= '<input ' . $this->build_attributes( $input_attrs ) . '>';
        $field .= '</div>';

        // Signature preview
        if ( $show_preview ) {
            $field .= '<div class="qaf-signature-preview" aria-hidden="true">';
            $field .= '<div class="qaf-signature-line"></div>';
            $field .= '<div class="qaf-signature-text" data-empty="' . esc_attr__( 'Your signature will appear here', 'quill-accessible-forms' ) . '">';
            if ( ! empty( $signature_value ) ) {
                $field .= esc_html( $signature_value );
            }
            $field .= '</div>';
            
            if ( $show_date ) {
                $field .= '<div class="qaf-signature-date">';
                $field .= esc_html( wp_date( get_option( 'date_format' ) ) );
                $field .= '</div>';
            }
            
            $field .= '</div>';
        }

        // Confirmation checkbox
        if ( $require_conf && ! empty( $this->config['confirmation_text'] ) ) {
            $conf_id = $html_id . '-confirm';
            $field .= '<div class="qaf-signature-confirmation">';
            $field .= '<label class="qaf-checkbox-label">';
            $field .= '<input type="checkbox" id="' . esc_attr( $conf_id ) . '" name="' . esc_attr( $name ) . '[confirmed]" value="1"';
            if ( $is_confirmed ) {
                $field .= ' checked';
            }
            $field .= ' class="qaf-signature-confirm-checkbox">';
            $field .= '<span class="qaf-checkbox-text">' . esc_html( $this->config['confirmation_text'] ) . '</span>';
            $field .= '</label>';
            $field .= '</div>';
        } elseif ( ! empty( $this->config['confirmation_text'] ) ) {
            // Just show text without checkbox
            $field .= '<p class="qaf-signature-legal-text">';
            $field .= esc_html( $this->config['confirmation_text'] );
            $field .= '</p>';
        }

        // Hidden timestamp field
        $field .= '<input type="hidden" name="' . esc_attr( $name ) . '[timestamp]" value="' . esc_attr( time() ) . '">';
        $field .= '<input type="hidden" name="' . esc_attr( $name ) . '[ip]" value="' . esc_attr( $this->get_client_ip() ) . '">';

        $field .= '</div>';

        return $this->render_wrapper( $field );
    }

    /**
     * Get client IP address
     *
     * @return string IP address.
     */
    protected function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip = explode( ',', $ip )[0]; // Get first IP if multiple
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ?: '';
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return array Sanitized value.
     */
    public function sanitize( $value ) {
        if ( ! is_array( $value ) ) {
            return array(
                'signature' => sanitize_text_field( $value ),
                'confirmed' => false,
                'timestamp' => time(),
                'ip'        => '',
            );
        }

        return array(
            'signature' => sanitize_text_field( $value['signature'] ?? '' ),
            'confirmed' => ! empty( $value['confirmed'] ),
            'timestamp' => (int) ( $value['timestamp'] ?? time() ),
            'ip'        => sanitize_text_field( $value['ip'] ?? '' ),
        );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $signature = is_array( $value ) ? ( $value['signature'] ?? '' ) : $value;
        
        if ( empty( trim( $signature ) ) ) {
            $this->add_error( __( 'Please type your name to sign.', 'quill-accessible-forms' ) );
            return;
        }

        // Require at least two words (first and last name)
        $words = preg_split( '/\s+/', trim( $signature ) );
        if ( count( $words ) < 2 ) {
            $this->add_error( __( 'Please enter your full legal name (first and last name).', 'quill-accessible-forms' ) );
            return;
        }

        // Check confirmation if required
        if ( $this->config['require_confirmation'] ?? true ) {
            $confirmed = is_array( $value ) ? ! empty( $value['confirmed'] ) : false;
            if ( ! $confirmed ) {
                $this->add_error( __( 'Please confirm your signature by checking the confirmation box.', 'quill-accessible-forms' ) );
            }
        }
    }

    /**
     * Get display value for entry views
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( ! is_array( $value ) ) {
            return esc_html( $value );
        }

        $output  = '<div class="qaf-signature-display">';
        $output .= '<div class="qaf-signature-name">' . esc_html( $value['signature'] ?? '' ) . '</div>';
        
        if ( ! empty( $value['timestamp'] ) ) {
            $output .= '<div class="qaf-signature-meta">';
            $output .= sprintf(
                /* translators: %s: date/time */
                __( 'Signed: %s', 'quill-accessible-forms' ),
                wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $value['timestamp'] )
            );
            
            if ( ! empty( $value['ip'] ) ) {
                $output .= ' | IP: ' . esc_html( $value['ip'] );
            }
            
            $output .= '</div>';
        }
        
        $output .= '</div>';

        return $output;
    }
}
