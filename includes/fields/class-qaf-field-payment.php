<?php
/**
 * Payment Field
 *
 * Stripe/PayPal inline payment elements.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Payment class
 */
class QAF_Field_Payment extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'payment';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'ecommerce';

    /**
     * Supported payment providers
     *
     * @var array
     */
    protected $providers = array(
        'stripe' => array(
            'label'       => 'Stripe',
            'description' => 'Accept cards via Stripe',
            'methods'     => array( 'card', 'apple_pay', 'google_pay', 'sepa', 'ideal' ),
        ),
        'paypal' => array(
            'label'       => 'PayPal',
            'description' => 'Accept PayPal payments',
            'methods'     => array( 'paypal', 'venmo', 'paylater' ),
        ),
        'square' => array(
            'label'       => 'Square',
            'description' => 'Accept cards via Square',
            'methods'     => array( 'card', 'apple_pay', 'google_pay' ),
        ),
    );

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'provider'          => 'stripe',
            'payment_methods'   => array( 'card' ),
            'amount_type'       => 'fixed',          // 'fixed', 'field', 'user_input'
            'amount'            => 0,
            'amount_field'      => '',               // Field ID for dynamic amount
            'currency'          => 'USD',
            'allow_tip'         => false,
            'tip_options'       => array( 10, 15, 20, 25 ),
            'custom_tip'        => true,
            'description'       => '',
            'statement_descriptor' => '',
            'capture_method'    => 'automatic',      // 'automatic', 'manual'
            'show_total'        => true,
            'theme'             => 'default',        // 'default', 'night', 'flat', 'minimal'
            'locale'            => 'auto',
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
                    'type'    => 'toggle',
                    'label'   => __( 'Required', 'quill-accessible-forms' ),
                    'default' => true,
                ),
            ),
            'provider' => array(
                'provider' => array(
                    'type'    => 'select',
                    'label'   => __( 'Payment Provider', 'quill-accessible-forms' ),
                    'options' => $this->get_provider_options(),
                ),
                'payment_methods' => array(
                    'type'    => 'multiselect',
                    'label'   => __( 'Payment Methods', 'quill-accessible-forms' ),
                    'options' => array(
                        'card'       => __( 'Credit/Debit Card', 'quill-accessible-forms' ),
                        'apple_pay'  => __( 'Apple Pay', 'quill-accessible-forms' ),
                        'google_pay' => __( 'Google Pay', 'quill-accessible-forms' ),
                        'paypal'     => __( 'PayPal', 'quill-accessible-forms' ),
                        'venmo'      => __( 'Venmo', 'quill-accessible-forms' ),
                        'sepa'       => __( 'SEPA Direct Debit', 'quill-accessible-forms' ),
                        'ideal'      => __( 'iDEAL', 'quill-accessible-forms' ),
                        'paylater'   => __( 'Pay Later', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'amount' => array(
                'amount_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Amount Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'fixed'      => __( 'Fixed Amount', 'quill-accessible-forms' ),
                        'field'      => __( 'From Another Field', 'quill-accessible-forms' ),
                        'user_input' => __( 'User Enters Amount', 'quill-accessible-forms' ),
                    ),
                ),
                'amount' => array(
                    'type'  => 'number',
                    'label' => __( 'Amount', 'quill-accessible-forms' ),
                    'desc'  => __( 'For fixed amount type', 'quill-accessible-forms' ),
                    'min'   => 0,
                    'step'  => 0.01,
                ),
                'amount_field' => array(
                    'type'  => 'field_select',
                    'label' => __( 'Amount Field', 'quill-accessible-forms' ),
                    'desc'  => __( 'For field-based amount', 'quill-accessible-forms' ),
                ),
                'currency' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency', 'quill-accessible-forms' ),
                    'options' => $this->get_currency_options(),
                ),
            ),
            'tips' => array(
                'allow_tip' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Tips', 'quill-accessible-forms' ),
                ),
                'tip_options' => array(
                    'type'  => 'text',
                    'label' => __( 'Tip Percentages', 'quill-accessible-forms' ),
                    'desc'  => __( 'Comma-separated (e.g., 10, 15, 20, 25)', 'quill-accessible-forms' ),
                ),
                'custom_tip' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Custom Tip', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'show_total' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Order Total', 'quill-accessible-forms' ),
                ),
                'theme' => array(
                    'type'    => 'select',
                    'label'   => __( 'Theme', 'quill-accessible-forms' ),
                    'options' => array(
                        'default' => __( 'Default', 'quill-accessible-forms' ),
                        'night'   => __( 'Night/Dark', 'quill-accessible-forms' ),
                        'flat'    => __( 'Flat', 'quill-accessible-forms' ),
                        'minimal' => __( 'Minimal', 'quill-accessible-forms' ),
                    ),
                ),
                'locale' => array(
                    'type'    => 'select',
                    'label'   => __( 'Locale', 'quill-accessible-forms' ),
                    'options' => array(
                        'auto' => __( 'Auto-detect', 'quill-accessible-forms' ),
                        'en'   => 'English',
                        'es'   => 'Español',
                        'fr'   => 'Français',
                        'de'   => 'Deutsch',
                        'it'   => 'Italiano',
                        'pt'   => 'Português',
                        'ja'   => '日本語',
                        'zh'   => '中文',
                    ),
                ),
            ),
            'processing' => array(
                'capture_method' => array(
                    'type'    => 'select',
                    'label'   => __( 'Capture Method', 'quill-accessible-forms' ),
                    'options' => array(
                        'automatic' => __( 'Automatic', 'quill-accessible-forms' ),
                        'manual'    => __( 'Manual (Authorize Only)', 'quill-accessible-forms' ),
                    ),
                ),
                'statement_descriptor' => array(
                    'type'  => 'text',
                    'label' => __( 'Statement Descriptor', 'quill-accessible-forms' ),
                    'desc'  => __( 'Text shown on customer\'s statement', 'quill-accessible-forms' ),
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
        $html_id        = $this->get_html_id();
        $name           = 'qaf_field_' . $this->get_id();
        $value          = $this->get_value();
        $provider       = $this->config['provider'] ?? 'stripe';
        $methods        = $this->config['payment_methods'] ?? array( 'card' );
        $amount_type    = $this->config['amount_type'] ?? 'fixed';
        $amount         = floatval( $this->config['amount'] ?? 0 );
        $currency       = $this->config['currency'] ?? 'USD';
        $allow_tip      = $this->config['allow_tip'] ?? false;
        $tip_options    = $this->config['tip_options'] ?? array( 10, 15, 20, 25 );
        $custom_tip     = $this->config['custom_tip'] ?? true;
        $show_total     = $this->config['show_total'] ?? true;
        $theme          = $this->config['theme'] ?? 'default';

        // Parse current value
        $current_data = array();
        if ( ! empty( $value ) ) {
            $current_data = is_string( $value ) ? json_decode( $value, true ) : $value;
        }

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'qaf-payment-wrapper';
        $wrapper_class[] = 'qaf-payment-provider-' . $provider;
        $wrapper_class[] = 'qaf-payment-theme-' . $theme;

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $field .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $field .= ' data-provider="' . esc_attr( $provider ) . '"';
        $field .= ' data-currency="' . esc_attr( $currency ) . '"';
        $field .= ' data-amount-type="' . esc_attr( $amount_type ) . '"';
        if ( $amount_type === 'fixed' ) {
            $field .= ' data-amount="' . esc_attr( $amount ) . '"';
        } elseif ( $amount_type === 'field' ) {
            $field .= ' data-amount-field="' . esc_attr( $this->config['amount_field'] ?? '' ) . '"';
        }
        $field .= ' data-methods="' . esc_attr( wp_json_encode( $methods ) ) . '">';

        // Label
        $field .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Amount display / input
        $field .= '<div class="qaf-payment-amount-section">';
        
        if ( $amount_type === 'user_input' ) {
            $field .= '<div class="qaf-payment-amount-input">';
            $field .= '<label for="' . esc_attr( $html_id ) . '-amount">' . esc_html__( 'Amount', 'quill-accessible-forms' ) . '</label>';
            $field .= '<div class="qaf-amount-input-wrapper">';
            $field .= '<span class="qaf-currency-symbol">' . esc_html( $this->get_currency_symbol( $currency ) ) . '</span>';
            $field .= '<input type="number" id="' . esc_attr( $html_id ) . '-amount" ';
            $field .= 'class="qaf-payment-amount" ';
            $field .= 'min="0.01" step="0.01" ';
            $field .= 'value="' . esc_attr( $current_data['amount'] ?? '' ) . '" ';
            $field .= 'aria-describedby="' . esc_attr( $html_id ) . '-amount-desc">';
            $field .= '</div>';
            $field .= '<span id="' . esc_attr( $html_id ) . '-amount-desc" class="qaf-field-hint">';
            $field .= esc_html__( 'Enter payment amount', 'quill-accessible-forms' );
            $field .= '</span>';
            $field .= '</div>';
        } elseif ( $show_total && $amount > 0 ) {
            $field .= '<div class="qaf-payment-subtotal">';
            $field .= '<span class="qaf-subtotal-label">' . esc_html__( 'Subtotal:', 'quill-accessible-forms' ) . '</span>';
            $field .= '<span class="qaf-subtotal-amount">' . esc_html( $this->format_amount( $amount, $currency ) ) . '</span>';
            $field .= '</div>';
        }

        $field .= '</div>';

        // Tip section
        if ( $allow_tip && $amount_type !== 'user_input' ) {
            $field .= $this->render_tip_section( $html_id, $tip_options, $custom_tip, $current_data, $currency );
        }

        // Total display
        if ( $show_total ) {
            $total = $amount;
            if ( isset( $current_data['tip'] ) ) {
                $total += floatval( $current_data['tip'] );
            }
            
            $field .= '<div class="qaf-payment-total" aria-live="polite">';
            $field .= '<span class="qaf-total-label">' . esc_html__( 'Total:', 'quill-accessible-forms' ) . '</span>';
            $field .= '<span class="qaf-total-amount" id="' . esc_attr( $html_id ) . '-total">';
            $field .= esc_html( $this->format_amount( $total, $currency ) );
            $field .= '</span>';
            $field .= '</div>';
        }

        // Payment method selection
        if ( count( $methods ) > 1 ) {
            $field .= $this->render_method_selection( $html_id, $methods, $current_data );
        }

        // Payment element container
        $field .= '<div class="qaf-payment-element" id="' . esc_attr( $html_id ) . '-element" ';
        $field .= 'role="group" aria-label="' . esc_attr__( 'Payment details', 'quill-accessible-forms' ) . '">';
        
        // Placeholder content before JS initializes
        $field .= '<div class="qaf-payment-loading">';
        $field .= '<span class="qaf-spinner" aria-hidden="true"></span>';
        $field .= '<span>' . esc_html__( 'Loading payment form...', 'quill-accessible-forms' ) . '</span>';
        $field .= '</div>';
        
        $field .= '</div>';

        // Error display for payment element
        $field .= '<div id="' . esc_attr( $html_id ) . '-payment-errors" class="qaf-payment-errors" role="alert" aria-live="polite"></div>';

        // Hidden inputs for form submission
        $field .= '<input type="hidden" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $name ) . '" ';
        $field .= 'value="' . esc_attr( is_array( $value ) ? wp_json_encode( $value ) : $value ) . '" class="qaf-payment-value">';

        // Payment intent ID (populated by JS after payment)
        $field .= '<input type="hidden" id="' . esc_attr( $html_id ) . '-intent" name="' . esc_attr( $name ) . '_intent" value="" class="qaf-payment-intent">';

        // Security badges
        $field .= '<div class="qaf-payment-security">';
        $field .= '<span class="dashicons dashicons-lock" aria-hidden="true"></span>';
        $field .= '<span class="qaf-security-text">';
        $field .= esc_html__( 'Secured by', 'quill-accessible-forms' ) . ' ';
        $field .= esc_html( $this->providers[ $provider ]['label'] ?? ucfirst( $provider ) );
        $field .= '</span>';
        $field .= '</div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $field .= $this->render_description();
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</div>';

        return $field;
    }

    /**
     * Render tip selection section
     *
     * @param string $html_id      Field ID.
     * @param array  $tip_options  Tip percentages.
     * @param bool   $custom_tip   Allow custom tip.
     * @param array  $current_data Current value data.
     * @param string $currency     Currency code.
     * @return string HTML.
     */
    protected function render_tip_section( $html_id, $tip_options, $custom_tip, $current_data, $currency ) {
        $current_tip = $current_data['tip_percent'] ?? null;

        $html = '<fieldset class="qaf-payment-tip-section">';
        $html .= '<legend>' . esc_html__( 'Add a tip', 'quill-accessible-forms' ) . '</legend>';

        $html .= '<div class="qaf-tip-options" role="radiogroup">';

        // No tip option
        $no_tip_checked = empty( $current_tip ) ? ' checked' : '';
        $html .= '<div class="qaf-tip-option">';
        $html .= '<input type="radio" id="' . esc_attr( $html_id ) . '-tip-none" name="' . esc_attr( $html_id ) . '_tip" value="0"' . $no_tip_checked . '>';
        $html .= '<label for="' . esc_attr( $html_id ) . '-tip-none">' . esc_html__( 'No tip', 'quill-accessible-forms' ) . '</label>';
        $html .= '</div>';

        // Percentage options
        foreach ( $tip_options as $percent ) {
            $percent = intval( $percent );
            $checked = $current_tip === $percent ? ' checked' : '';
            $html .= '<div class="qaf-tip-option">';
            $html .= '<input type="radio" id="' . esc_attr( $html_id ) . '-tip-' . $percent . '" ';
            $html .= 'name="' . esc_attr( $html_id ) . '_tip" value="' . esc_attr( $percent ) . '"' . $checked . '>';
            $html .= '<label for="' . esc_attr( $html_id ) . '-tip-' . $percent . '">' . esc_html( $percent ) . '%</label>';
            $html .= '</div>';
        }

        // Custom tip option
        if ( $custom_tip ) {
            $custom_checked = $current_tip === 'custom' ? ' checked' : '';
            $custom_value = $current_data['tip_custom'] ?? '';
            
            $html .= '<div class="qaf-tip-option qaf-tip-custom">';
            $html .= '<input type="radio" id="' . esc_attr( $html_id ) . '-tip-custom" ';
            $html .= 'name="' . esc_attr( $html_id ) . '_tip" value="custom"' . $custom_checked . '>';
            $html .= '<label for="' . esc_attr( $html_id ) . '-tip-custom">' . esc_html__( 'Custom', 'quill-accessible-forms' ) . '</label>';
            $html .= '<div class="qaf-custom-tip-input">';
            $html .= '<span class="qaf-currency-symbol">' . esc_html( $this->get_currency_symbol( $currency ) ) . '</span>';
            $html .= '<input type="number" id="' . esc_attr( $html_id ) . '-tip-amount" ';
            $html .= 'class="qaf-tip-custom-amount" min="0" step="0.01" ';
            $html .= 'value="' . esc_attr( $custom_value ) . '" ';
            $html .= 'aria-label="' . esc_attr__( 'Custom tip amount', 'quill-accessible-forms' ) . '">';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Render payment method selection
     *
     * @param string $html_id      Field ID.
     * @param array  $methods      Available methods.
     * @param array  $current_data Current value data.
     * @return string HTML.
     */
    protected function render_method_selection( $html_id, $methods, $current_data ) {
        $current_method = $current_data['method'] ?? $methods[0];

        $html = '<div class="qaf-payment-methods">';
        $html .= '<span class="qaf-methods-label">' . esc_html__( 'Pay with:', 'quill-accessible-forms' ) . '</span>';
        $html .= '<div class="qaf-method-buttons" role="radiogroup">';

        $method_labels = array(
            'card'       => __( 'Card', 'quill-accessible-forms' ),
            'apple_pay'  => __( 'Apple Pay', 'quill-accessible-forms' ),
            'google_pay' => __( 'Google Pay', 'quill-accessible-forms' ),
            'paypal'     => __( 'PayPal', 'quill-accessible-forms' ),
            'venmo'      => __( 'Venmo', 'quill-accessible-forms' ),
            'sepa'       => __( 'Bank Transfer', 'quill-accessible-forms' ),
            'ideal'      => __( 'iDEAL', 'quill-accessible-forms' ),
            'paylater'   => __( 'Pay Later', 'quill-accessible-forms' ),
        );

        $method_icons = array(
            'card'       => 'credit-card',
            'apple_pay'  => 'smartphone',
            'google_pay' => 'smartphone',
            'paypal'     => 'cart',
            'venmo'      => 'money-alt',
            'sepa'       => 'bank',
            'ideal'      => 'bank',
            'paylater'   => 'calendar-alt',
        );

        foreach ( $methods as $method ) {
            $checked = $method === $current_method ? ' checked' : '';
            $label = $method_labels[ $method ] ?? ucfirst( $method );
            $icon = $method_icons[ $method ] ?? 'money';

            $html .= '<div class="qaf-method-option">';
            $html .= '<input type="radio" id="' . esc_attr( $html_id ) . '-method-' . $method . '" ';
            $html .= 'name="' . esc_attr( $html_id ) . '_method" value="' . esc_attr( $method ) . '"' . $checked . '>';
            $html .= '<label for="' . esc_attr( $html_id ) . '-method-' . $method . '">';
            $html .= '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
            $html .= '<span class="qaf-method-label">' . esc_html( $label ) . '</span>';
            $html .= '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get provider options
     *
     * @return array Provider options.
     */
    protected function get_provider_options() {
        $options = array();
        foreach ( $this->providers as $key => $config ) {
            $options[ $key ] = $config['label'];
        }
        return $options;
    }

    /**
     * Get currency options
     *
     * @return array Currency options.
     */
    protected function get_currency_options() {
        return array(
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'CAD' => 'CAD - Canadian Dollar',
            'AUD' => 'AUD - Australian Dollar',
            'JPY' => 'JPY - Japanese Yen',
            'CHF' => 'CHF - Swiss Franc',
            'CNY' => 'CNY - Chinese Yuan',
            'INR' => 'INR - Indian Rupee',
            'MXN' => 'MXN - Mexican Peso',
            'BRL' => 'BRL - Brazilian Real',
            'NZD' => 'NZD - New Zealand Dollar',
            'SEK' => 'SEK - Swedish Krona',
            'NOK' => 'NOK - Norwegian Krone',
            'DKK' => 'DKK - Danish Krone',
            'PLN' => 'PLN - Polish Złoty',
            'SGD' => 'SGD - Singapore Dollar',
            'HKD' => 'HKD - Hong Kong Dollar',
        );
    }

    /**
     * Get currency symbol
     *
     * @param string $currency Currency code.
     * @return string Currency symbol.
     */
    protected function get_currency_symbol( $currency ) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
            'MXN' => '$',
            'BRL' => 'R$',
            'NZD' => 'NZ$',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'SGD' => 'S$',
            'HKD' => 'HK$',
        );

        return $symbols[ $currency ] ?? $currency;
    }

    /**
     * Format amount with currency
     *
     * @param float  $amount   Amount.
     * @param string $currency Currency code.
     * @return string Formatted amount.
     */
    protected function format_amount( $amount, $currency ) {
        $symbol = $this->get_currency_symbol( $currency );
        $decimals = in_array( $currency, array( 'JPY', 'KRW' ), true ) ? 0 : 2;
        
        return $symbol . number_format( $amount, $decimals );
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized JSON.
     */
    public function sanitize( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            return '';
        }

        $sanitized = array();

        // Sanitize each component
        if ( isset( $data['payment_intent'] ) ) {
            $sanitized['payment_intent'] = sanitize_text_field( $data['payment_intent'] );
        }
        if ( isset( $data['payment_method'] ) ) {
            $sanitized['payment_method'] = sanitize_text_field( $data['payment_method'] );
        }
        if ( isset( $data['method'] ) ) {
            $sanitized['method'] = sanitize_key( $data['method'] );
        }
        if ( isset( $data['amount'] ) ) {
            $sanitized['amount'] = floatval( $data['amount'] );
        }
        if ( isset( $data['currency'] ) ) {
            $sanitized['currency'] = strtoupper( sanitize_text_field( $data['currency'] ) );
        }
        if ( isset( $data['tip'] ) ) {
            $sanitized['tip'] = floatval( $data['tip'] );
        }
        if ( isset( $data['status'] ) ) {
            $sanitized['status'] = sanitize_key( $data['status'] );
        }
        if ( isset( $data['last4'] ) ) {
            $sanitized['last4'] = sanitize_text_field( $data['last4'] );
        }

        return wp_json_encode( $sanitized );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( empty( $value ) ) {
            return;
        }

        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            $this->add_error( __( 'Invalid payment data.', 'quill-accessible-forms' ) );
            return;
        }

        // Check for payment intent
        if ( empty( $data['payment_intent'] ) && empty( $data['payment_method'] ) ) {
            $this->add_error( __( 'Payment has not been completed.', 'quill-accessible-forms' ) );
            return;
        }

        // Check payment status
        if ( isset( $data['status'] ) && ! in_array( $data['status'], array( 'succeeded', 'requires_capture' ), true ) ) {
            $this->add_error( __( 'Payment was not successful. Please try again.', 'quill-accessible-forms' ) );
        }

        // Validate amount
        $amount_type = $this->config['amount_type'] ?? 'fixed';
        if ( $amount_type === 'user_input' && empty( $data['amount'] ) ) {
            $this->add_error( __( 'Please enter a payment amount.', 'quill-accessible-forms' ) );
        }
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $data = is_string( $value ) ? json_decode( $value, true ) : $value;

        if ( ! is_array( $data ) ) {
            return '';
        }

        $parts = array();

        // Amount
        if ( isset( $data['amount'] ) && isset( $data['currency'] ) ) {
            $parts[] = $this->format_amount( $data['amount'], $data['currency'] );
        }

        // Method
        if ( isset( $data['method'] ) ) {
            $parts[] = ucfirst( str_replace( '_', ' ', $data['method'] ) );
        }

        // Last 4 digits
        if ( isset( $data['last4'] ) ) {
            $parts[] = '****' . $data['last4'];
        }

        // Status
        if ( isset( $data['status'] ) ) {
            $status_labels = array(
                'succeeded'        => __( 'Paid', 'quill-accessible-forms' ),
                'requires_capture' => __( 'Authorized', 'quill-accessible-forms' ),
                'pending'          => __( 'Pending', 'quill-accessible-forms' ),
                'failed'           => __( 'Failed', 'quill-accessible-forms' ),
            );
            $parts[] = $status_labels[ $data['status'] ] ?? $data['status'];
        }

        return implode( ' - ', $parts );
    }
}
