<?php
/**
 * Total Field - Auto-calculated order total
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Total class
 */
class QAF_Field_Total extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'total';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'pricing';

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
            'label'              => __( 'Total', 'quill-accessible-forms' ),
            'currency'           => 'USD',
            'currency_position'  => 'before',
            'decimal_places'     => 2,
            'thousand_separator' => ',',
            'decimal_separator'  => '.',
            'show_breakdown'     => true,
            'show_subtotal'      => true,
            'show_tax'           => false,
            'tax_rate'           => 0,
            'tax_label'          => __( 'Tax', 'quill-accessible-forms' ),
            'tax_included'       => false,
            'show_shipping'      => false,
            'shipping_amount'    => 0,
            'shipping_label'     => __( 'Shipping', 'quill-accessible-forms' ),
            'free_shipping_min'  => 0,
            'show_discount'      => true,
            'discount_label'     => __( 'Discount', 'quill-accessible-forms' ),
            'conditional'        => array(),
            'css_class'          => '',
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
                    'type'  => 'text',
                    'label' => __( 'Total Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
            ),
            'formatting' => array(
                'currency' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency', 'quill-accessible-forms' ),
                    'options' => array(
                        'USD' => __( 'US Dollar ($)', 'quill-accessible-forms' ),
                        'EUR' => __( 'Euro (€)', 'quill-accessible-forms' ),
                        'GBP' => __( 'British Pound (£)', 'quill-accessible-forms' ),
                        'CAD' => __( 'Canadian Dollar (C$)', 'quill-accessible-forms' ),
                        'AUD' => __( 'Australian Dollar (A$)', 'quill-accessible-forms' ),
                        'JPY' => __( 'Japanese Yen (¥)', 'quill-accessible-forms' ),
                    ),
                ),
                'currency_position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency Position', 'quill-accessible-forms' ),
                    'options' => array(
                        'before'       => __( 'Before ($99)', 'quill-accessible-forms' ),
                        'after'        => __( 'After (99$)', 'quill-accessible-forms' ),
                        'before_space' => __( 'Before with space ($ 99)', 'quill-accessible-forms' ),
                        'after_space'  => __( 'After with space (99 $)', 'quill-accessible-forms' ),
                    ),
                ),
                'decimal_places' => array(
                    'type'    => 'select',
                    'label'   => __( 'Decimal Places', 'quill-accessible-forms' ),
                    'options' => array(
                        '0' => '0',
                        '2' => '2',
                        '3' => '3',
                    ),
                ),
                'thousand_separator' => array(
                    'type'    => 'select',
                    'label'   => __( 'Thousand Separator', 'quill-accessible-forms' ),
                    'options' => array(
                        ','  => __( 'Comma (1,000)', 'quill-accessible-forms' ),
                        '.'  => __( 'Period (1.000)', 'quill-accessible-forms' ),
                        ' '  => __( 'Space (1 000)', 'quill-accessible-forms' ),
                        ''   => __( 'None (1000)', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'breakdown' => array(
                'show_breakdown' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Breakdown', 'quill-accessible-forms' ),
                ),
                'show_subtotal' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Subtotal', 'quill-accessible-forms' ),
                ),
                'show_discount' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Discount Line', 'quill-accessible-forms' ),
                ),
            ),
            'tax' => array(
                'show_tax' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Include Tax', 'quill-accessible-forms' ),
                ),
                'tax_rate' => array(
                    'type'        => 'number',
                    'label'       => __( 'Tax Rate (%)', 'quill-accessible-forms' ),
                    'min'         => 0,
                    'max'         => 100,
                    'step'        => '0.01',
                    'condition'   => array( 'show_tax' => true ),
                ),
                'tax_label' => array(
                    'type'      => 'text',
                    'label'     => __( 'Tax Label', 'quill-accessible-forms' ),
                    'condition' => array( 'show_tax' => true ),
                ),
                'tax_included' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Tax Included in Prices', 'quill-accessible-forms' ),
                    'condition' => array( 'show_tax' => true ),
                ),
            ),
            'shipping' => array(
                'show_shipping' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Include Shipping', 'quill-accessible-forms' ),
                ),
                'shipping_amount' => array(
                    'type'      => 'number',
                    'label'     => __( 'Shipping Amount', 'quill-accessible-forms' ),
                    'min'       => 0,
                    'step'      => '0.01',
                    'condition' => array( 'show_shipping' => true ),
                ),
                'shipping_label' => array(
                    'type'      => 'text',
                    'label'     => __( 'Shipping Label', 'quill-accessible-forms' ),
                    'condition' => array( 'show_shipping' => true ),
                ),
                'free_shipping_min' => array(
                    'type'        => 'number',
                    'label'       => __( 'Free Shipping Minimum', 'quill-accessible-forms' ),
                    'description' => __( 'Set to 0 to disable free shipping threshold', 'quill-accessible-forms' ),
                    'min'         => 0,
                    'step'        => '0.01',
                    'condition'   => array( 'show_shipping' => true ),
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
        $label = $this->config['label'] ?? __( 'Total', 'quill-accessible-forms' );

        $wrapper_class = array( 'qaf-field', 'qaf-field-total' );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-currency="' . esc_attr( $this->config['currency'] ?? 'USD' ) . '"';
        $html .= ' data-tax-rate="' . esc_attr( $this->config['tax_rate'] ?? 0 ) . '"';
        $html .= ' data-shipping="' . esc_attr( $this->config['shipping_amount'] ?? 0 ) . '"';
        $html .= ' data-free-shipping-min="' . esc_attr( $this->config['free_shipping_min'] ?? 0 ) . '"';
        $html .= '>';

        if ( ! empty( $this->config['show_breakdown'] ) ) {
            $html .= '<div class="qaf-total-breakdown" role="list" aria-label="' . esc_attr__( 'Order summary', 'quill-accessible-forms' ) . '">';

            // Subtotal
            if ( ! empty( $this->config['show_subtotal'] ) ) {
                $html .= '<div class="qaf-total-line qaf-subtotal" role="listitem">';
                $html .= '<span class="qaf-total-label">' . esc_html__( 'Subtotal', 'quill-accessible-forms' ) . '</span>';
                $html .= '<span class="qaf-total-value" data-total-subtotal>-</span>';
                $html .= '</div>';
            }

            // Discount
            if ( ! empty( $this->config['show_discount'] ) ) {
                $html .= '<div class="qaf-total-line qaf-discount" role="listitem" style="display: none;">';
                $html .= '<span class="qaf-total-label">' . esc_html( $this->config['discount_label'] ?? __( 'Discount', 'quill-accessible-forms' ) ) . '</span>';
                $html .= '<span class="qaf-total-value" data-total-discount>-</span>';
                $html .= '</div>';
            }

            // Shipping
            if ( ! empty( $this->config['show_shipping'] ) ) {
                $html .= '<div class="qaf-total-line qaf-shipping" role="listitem">';
                $html .= '<span class="qaf-total-label">' . esc_html( $this->config['shipping_label'] ?? __( 'Shipping', 'quill-accessible-forms' ) ) . '</span>';
                $html .= '<span class="qaf-total-value" data-total-shipping>-</span>';
                $html .= '</div>';
            }

            // Tax
            if ( ! empty( $this->config['show_tax'] ) ) {
                $tax_label = $this->config['tax_label'] ?? __( 'Tax', 'quill-accessible-forms' );
                $tax_rate = $this->config['tax_rate'] ?? 0;
                if ( $tax_rate > 0 ) {
                    $tax_label .= ' (' . $tax_rate . '%)';
                }

                $html .= '<div class="qaf-total-line qaf-tax" role="listitem">';
                $html .= '<span class="qaf-total-label">' . esc_html( $tax_label ) . '</span>';
                $html .= '<span class="qaf-total-value" data-total-tax>-</span>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Final total
        $html .= '<div class="qaf-total-final">';
        $html .= '<span class="qaf-total-final-label">' . esc_html( $label ) . '</span>';
        $html .= '<span class="qaf-total-final-value" data-total-grand aria-live="polite">';
        $html .= $this->format_price( 0 );
        $html .= '</span>';
        $html .= '</div>';

        // Hidden input for form value
        $html .= '<input type="hidden" name="qaf_field_' . esc_attr( $this->get_id() ) . '" value="0" data-total-hidden>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Format price
     *
     * @param float $price Price value.
     * @return string Formatted price.
     */
    protected function format_price( $price ) {
        $currency = $this->config['currency'] ?? 'USD';
        $position = $this->config['currency_position'] ?? 'before';
        $decimals = (int) ( $this->config['decimal_places'] ?? 2 );
        $thousand = $this->config['thousand_separator'] ?? ',';
        $decimal = $this->config['decimal_separator'] ?? '.';

        $symbols = array(
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'C$',
            'AUD' => 'A$', 'JPY' => '¥',
        );

        $symbol = $symbols[ $currency ] ?? $currency;
        $formatted = number_format( (float) $price, $decimals, $decimal, $thousand );

        switch ( $position ) {
            case 'after':
                return $formatted . $symbol;
            case 'before_space':
                return $symbol . ' ' . $formatted;
            case 'after_space':
                return $formatted . ' ' . $symbol;
            default:
                return $symbol . $formatted;
        }
    }

    /**
     * Total fields don't need standard validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Sanitize - just return the calculated total
     *
     * @param mixed $value Value.
     * @return float Sanitized total.
     */
    public function sanitize( $value ) {
        return (float) $value;
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted price.
     */
    public function format_display( $value ) {
        return $this->format_price( (float) $value );
    }
}
