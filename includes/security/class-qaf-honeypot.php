<?php
/**
 * Honeypot Anti-Spam
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Honeypot class
 * 
 * Implements honeypot anti-spam protection.
 */
class QAF_Honeypot {

    /**
     * Field names used as honeypots
     *
     * @var array
     */
    private $field_names = array(
        'qaf_website',
        'qaf_email_confirm',
        'qaf_phone_alt',
        'qaf_address_2',
    );

    /**
     * Validate honeypot fields
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True if valid, WP_Error if honeypot was triggered.
     */
    public function validate( $request ) {
        foreach ( $this->field_names as $field_name ) {
            $value = $request->get_param( $field_name );
            
            if ( ! empty( $value ) ) {
                return new WP_Error(
                    'security_honeypot',
                    __( 'Form submission failed validation.', 'quill-accessible-forms' ),
                    array( 'status' => 400 )
                );
            }
        }

        return true;
    }

    /**
     * Render honeypot fields
     *
     * @param string $form_id Form ID for unique naming.
     * @return string HTML for honeypot fields.
     */
    public function render( $form_id ) {
        $output = '';
        
        foreach ( $this->field_names as $field_name ) {
            // Use CSS to hide visually but keep accessible for screen readers with aria-hidden
            $output .= sprintf(
                '<div class="qaf-hp-field" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;">
                    <label for="%1$s_%2$s">%3$s</label>
                    <input type="text" name="%1$s" id="%1$s_%2$s" tabindex="-1" autocomplete="off" />
                </div>',
                esc_attr( $field_name ),
                esc_attr( $form_id ),
                esc_html__( 'Leave this field empty', 'quill-accessible-forms' )
            );
        }

        return $output;
    }

    /**
     * Get honeypot field names
     *
     * @return array Field names.
     */
    public function get_field_names() {
        return $this->field_names;
    }
}
