<?php
/**
 * Email field type
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Email class
 * 
 * Email input field with validation and accessibility.
 */
class QAF_Field_Email extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'email';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'standard';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'confirm_email'          => false,
            'confirm_label'          => __( 'Confirm Email', 'quill-accessible-forms' ),
            'block_disposable'       => true,
            'block_free_email'       => false,
            'allowed_domains'        => '',
            'blocked_domains'        => '',
            'show_email_validation'  => true, // Show real-time validation icon
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
                'label'       => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'    => 'text',
                    'label'   => __( 'Placeholder', 'quill-accessible-forms' ),
                    'default' => 'email@example.com',
                ),
                'default_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'confirm_email' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable Email Confirmation', 'quill-accessible-forms' ),
                ),
                'confirm_label' => array(
                    'type'        => 'text',
                    'label'       => __( 'Confirmation Field Label', 'quill-accessible-forms' ),
                    'conditional' => array( 'confirm_email' => true ),
                ),
                'block_disposable' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Block Disposable Email Addresses', 'quill-accessible-forms' ),
                    'description' => __( 'Reject temporary/throwaway email addresses', 'quill-accessible-forms' ),
                ),
                'block_free_email' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Block Free Email Providers', 'quill-accessible-forms' ),
                    'description' => __( 'Reject gmail.com, yahoo.com, etc.', 'quill-accessible-forms' ),
                ),
                'allowed_domains' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Allowed Domains', 'quill-accessible-forms' ),
                    'description' => __( 'One domain per line. Leave empty to allow all.', 'quill-accessible-forms' ),
                ),
                'blocked_domains' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Blocked Domains', 'quill-accessible-forms' ),
                    'description' => __( 'One domain per line.', 'quill-accessible-forms' ),
                ),
                'custom_error_message' => array(
                    'type'  => 'text',
                    'label' => __( 'Custom Error Message', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Field Size', 'quill-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small', 'quill-accessible-forms' ),
                        'medium' => __( 'Medium', 'quill-accessible-forms' ),
                        'large'  => __( 'Large', 'quill-accessible-forms' ),
                        'full'   => __( 'Full Width', 'quill-accessible-forms' ),
                    ),
                ),
                'show_email_validation' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Validation Status Icon', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $value = $this->get_value();

        // Build input attributes
        $attrs = array(
            'type'         => 'email',
            'id'           => $this->get_html_id(),
            'name'         => 'qaf_fields[' . $this->get_id() . ']',
            'value'        => esc_attr( $value ),
            'class'        => 'qaf-input qaf-input-email',
            'placeholder'  => $this->config['placeholder'] ?: 'email@example.com',
            'autocomplete' => 'email',
        );

        // Read-only
        if ( ! empty( $this->config['read_only'] ) ) {
            $attrs['readonly'] = true;
        }

        // Disabled
        if ( ! empty( $this->config['disabled'] ) ) {
            $attrs['disabled'] = true;
        }

        // Add ARIA attributes
        $attrs = array_merge( $attrs, $this->get_aria_attributes() );

        // Build the input element
        $input = '<div class="qaf-email-wrapper">';
        $input .= '<input ' . $this->build_attributes( $attrs ) . '>';

        // Validation status icon (for real-time feedback)
        if ( ! empty( $this->config['show_email_validation'] ) ) {
            $input .= '<span class="qaf-email-status" aria-hidden="true"></span>';
        }

        $input .= '</div>';

        // Add confirmation field if enabled
        if ( ! empty( $this->config['confirm_email'] ) ) {
            $confirm_id = $this->get_html_id() . '-confirm';
            $confirm_label = $this->config['confirm_label'] ?: __( 'Confirm Email', 'quill-accessible-forms' );

            $input .= '<div class="qaf-email-confirm-wrapper">';
            $input .= '<label for="' . esc_attr( $confirm_id ) . '" class="qaf-field-label">';
            $input .= esc_html( $confirm_label );
            if ( $this->is_required() ) {
                $input .= $this->render_required_indicator();
            }
            $input .= '</label>';
            
            $confirm_attrs = array(
                'type'         => 'email',
                'id'           => $confirm_id,
                'name'         => 'qaf_fields[' . $this->get_id() . '_confirm]',
                'class'        => 'qaf-input qaf-input-email-confirm',
                'placeholder'  => $this->config['placeholder'] ?: 'email@example.com',
                'autocomplete' => 'email',
            );

            if ( $this->is_required() ) {
                $confirm_attrs['aria-required'] = 'true';
            }

            $input .= '<input ' . $this->build_attributes( $confirm_attrs ) . '>';
            $input .= '</div>';
        }

        return $this->render_wrapper( $input );
    }

    /**
     * Field-specific validation
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // Basic email format
        if ( ! is_email( $value ) ) {
            $this->add_error( $this->get_error_message( 'email' ) );
            return;
        }

        // Extract domain
        $domain = strtolower( substr( strrchr( $value, '@' ), 1 ) );

        // Check allowed domains
        if ( ! empty( $this->config['allowed_domains'] ) ) {
            $allowed = array_filter( array_map( 'trim', explode( "\n", $this->config['allowed_domains'] ) ) );
            if ( ! empty( $allowed ) && ! in_array( $domain, $allowed, true ) ) {
                $this->add_error( __( 'Please use an email address from an allowed domain.', 'quill-accessible-forms' ) );
                return;
            }
        }

        // Check blocked domains
        if ( ! empty( $this->config['blocked_domains'] ) ) {
            $blocked = array_filter( array_map( 'trim', explode( "\n", $this->config['blocked_domains'] ) ) );
            if ( in_array( $domain, $blocked, true ) ) {
                $this->add_error( __( 'This email domain is not allowed.', 'quill-accessible-forms' ) );
                return;
            }
        }

        // Block disposable emails
        if ( ! empty( $this->config['block_disposable'] ) && $this->is_disposable_email( $domain ) ) {
            $this->add_error( __( 'Please use a permanent email address.', 'quill-accessible-forms' ) );
            return;
        }

        // Block free email providers
        if ( ! empty( $this->config['block_free_email'] ) && $this->is_free_email_provider( $domain ) ) {
            $this->add_error( __( 'Please use a business email address.', 'quill-accessible-forms' ) );
            return;
        }

        // Check confirmation
        if ( ! empty( $this->config['confirm_email'] ) ) {
            $confirm_value = isset( $_POST['qaf_fields'][ $this->get_id() . '_confirm' ] ) 
                ? sanitize_email( wp_unslash( $_POST['qaf_fields'][ $this->get_id() . '_confirm' ] ) )
                : '';

            if ( strtolower( $value ) !== strtolower( $confirm_value ) ) {
                $this->add_error( __( 'Email addresses do not match.', 'quill-accessible-forms' ) );
            }
        }
    }

    /**
     * Check if email domain is disposable
     *
     * @param string $domain Email domain.
     * @return bool True if disposable.
     */
    private function is_disposable_email( $domain ) {
        // Common disposable email domains
        $disposable = array(
            'tempmail.com', 'throwaway.email', 'mailinator.com', 'guerrillamail.com',
            'tempmail.net', '10minutemail.com', 'fakeinbox.com', 'temp-mail.org',
            'dispostable.com', 'mailnesia.com', 'sharklasers.com', 'yopmail.com',
            'getnada.com', 'trashmail.com', 'mailcatch.com', 'maildrop.cc',
        );

        /**
         * Filter disposable email domains
         *
         * @param array $disposable Disposable domains list.
         */
        $disposable = apply_filters( 'qaf_disposable_email_domains', $disposable );

        return in_array( $domain, $disposable, true );
    }

    /**
     * Check if email domain is free provider
     *
     * @param string $domain Email domain.
     * @return bool True if free provider.
     */
    private function is_free_email_provider( $domain ) {
        $free_providers = array(
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com',
            'icloud.com', 'mail.com', 'protonmail.com', 'zoho.com', 'yandex.com',
            'gmx.com', 'live.com', 'msn.com', 'yahoo.co.uk', 'btinternet.com',
        );

        /**
         * Filter free email provider domains
         *
         * @param array $free_providers Free provider domains list.
         */
        $free_providers = apply_filters( 'qaf_free_email_providers', $free_providers );

        return in_array( $domain, $free_providers, true );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized email.
     */
    public function sanitize( $value ) {
        return sanitize_email( $value );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        return '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code for this field.
     */
    public function to_bfl() {
        $bfl = 'email';

        if ( $this->get_id() ) {
            $bfl .= ' #' . $this->get_id();
        }

        if ( $this->get_label() ) {
            $bfl .= ' "' . addslashes( $this->get_label() ) . '"';
        }

        $modifiers = array();

        if ( $this->is_required() ) {
            $modifiers[] = '@required';
        }

        if ( ! empty( $this->config['confirm_email'] ) ) {
            $modifiers[] = '@confirm';
        }

        if ( ! empty( $this->config['block_disposable'] ) ) {
            $modifiers[] = '@no-disposable';
        }

        if ( ! empty( $modifiers ) ) {
            $bfl .= "\n  " . implode( "\n  ", $modifiers );
        }

        return $bfl;
    }
}
