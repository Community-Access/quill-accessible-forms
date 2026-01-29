<?php
/**
 * Total Field - Auto-calculated order total
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Total class
 */
class BAF_Field_Total extends BAF_Field {

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
            'label'              => __( 'Total', 'bits-accessible-forms' ),
            'currency'           => 'USD',
            'currency_position'  => 'before',
            'decimal_places'     => 2,
            'thousand_separator' => ',',
            'decimal_separator'  => '.',
            'show_breakdown'     => true,
            'show_subtotal'      => true,
            'show_tax'           => false,
            'tax_rate'           => 0,
            'tax_label'          => __( 'Tax', 'bits-accessible-forms' ),
            'tax_included'       => false,
            'show_shipping'      => false,
            'shipping_amount'    => 0,
            'shipping_label'     => __( 'Shipping', 'bits-accessible-forms' ),
            'free_shipping_min'  => 0,
            'show_discount'      => true,
            'discount_label'     => __( 'Discount', 'bits-accessible-forms' ),
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
                    'label' => __( 'Total Label', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
            ),
            'formatting' => array(
                'currency' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency', 'bits-accessible-forms' ),
                    'options' => array(
                        'USD' => __( 'US Dollar ($)', 'bits-accessible-forms' ),
                        'EUR' => __( 'Euro (€)', 'bits-accessible-forms' ),
                        'GBP' => __( 'British Pound (£)', 'bits-accessible-forms' ),
                        'CAD' => __( 'Canadian Dollar (C$)', 'bits-accessible-forms' ),
                        'AUD' => __( 'Australian Dollar (A$)', 'bits-accessible-forms' ),
                        'JPY' => __( 'Japanese Yen (¥)', 'bits-accessible-forms' ),
                    ),
                ),
                'currency_position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency Position', 'bits-accessible-forms' ),
                    'options' => array(
                        'before'       => __( 'Before ($99)', 'bits-accessible-forms' ),
                        'after'        => __( 'After (99$)', 'bits-accessible-forms' ),
                        'before_space' => __( 'Before with space ($ 99)', 'bits-accessible-forms' ),
                        'after_space'  => __( 'After with space (99 $)', 'bits-accessible-forms' ),
                    ),
                ),
                'decimal_places' => array(
                    'type'    => 'select',
                    'label'   => __( 'Decimal Places', 'bits-accessible-forms' ),
                    'options' => array(
                        '0' => '0',
                        '2' => '2',
                        '3' => '3',
                    ),
                ),
                'thousand_separator' => array(
                    'type'    => 'select',
                    'label'   => __( 'Thousand Separator', 'bits-accessible-forms' ),
                    'options' => array(
                        ','  => __( 'Comma (1,000)', 'bits-accessible-forms' ),
                        '.'  => __( 'Period (1.000)', 'bits-accessible-forms' ),
                        ' '  => __( 'Space (1 000)', 'bits-accessible-forms' ),
                        ''   => __( 'None (1000)', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'breakdown' => array(
                'show_breakdown' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Breakdown', 'bits-accessible-forms' ),
                ),
                'show_subtotal' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Subtotal', 'bits-accessible-forms' ),
                ),
                'show_discount' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Discount Line', 'bits-accessible-forms' ),
                ),
            ),
            'tax' => array(
                'show_tax' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Include Tax', 'bits-accessible-forms' ),
                ),
                'tax_rate' => array(
                    'type'        => 'number',
                    'label'       => __( 'Tax Rate (%)', 'bits-accessible-forms' ),
                    'min'         => 0,
                    'max'         => 100,
                    'step'        => '0.01',
                    'condition'   => array( 'show_tax' => true ),
                ),
                'tax_label' => array(
                    'type'      => 'text',
                    'label'     => __( 'Tax Label', 'bits-accessible-forms' ),
                    'condition' => array( 'show_tax' => true ),
                ),
                'tax_included' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Tax Included in Prices', 'bits-accessible-forms' ),
                    'condition' => array( 'show_tax' => true ),
                ),
            ),
            'shipping' => array(
                'show_shipping' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Include Shipping', 'bits-accessible-forms' ),
                ),
                'shipping_amount' => array(
                    'type'      => 'number',
                    'label'     => __( 'Shipping Amount', 'bits-accessible-forms' ),
                    'min'       => 0,
                    'step'      => '0.01',
                    'condition' => array( 'show_shipping' => true ),
                ),
                'shipping_label' => array(
                    'type'      => 'text',
                    'label'     => __( 'Shipping Label', 'bits-accessible-forms' ),
                    'condition' => array( 'show_shipping' => true ),
                ),
                'free_shipping_min' => array(
                    'type'        => 'number',
                    'label'       => __( 'Free Shipping Minimum', 'bits-accessible-forms' ),
                    'description' => __( 'Set to 0 to disable free shipping threshold', 'bits-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Total', 'bits-accessible-forms' );

        $wrapper_class = array( 'baf-field', 'baf-field-total' );
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
            $html .= '<div class="baf-total-breakdown" role="list" aria-label="' . esc_attr__( 'Order summary', 'bits-accessible-forms' ) . '">';

            // Subtotal
            if ( ! empty( $this->config['show_subtotal'] ) ) {
                $html .= '<div class="baf-total-line baf-subtotal" role="listitem">';
                $html .= '<span class="baf-total-label">' . esc_html__( 'Subtotal', 'bits-accessible-forms' ) . '</span>';
                $html .= '<span class="baf-total-value" data-total-subtotal>-</span>';
                $html .= '</div>';
            }

            // Discount
            if ( ! empty( $this->config['show_discount'] ) ) {
                $html .= '<div class="baf-total-line baf-discount" role="listitem" style="display: none;">';
                $html .= '<span class="baf-total-label">' . esc_html( $this->config['discount_label'] ?? __( 'Discount', 'bits-accessible-forms' ) ) . '</span>';
                $html .= '<span class="baf-total-value" data-total-discount>-</span>';
                $html .= '</div>';
            }

            // Shipping
            if ( ! empty( $this->config['show_shipping'] ) ) {
                $html .= '<div class="baf-total-line baf-shipping" role="listitem">';
                $html .= '<span class="baf-total-label">' . esc_html( $this->config['shipping_label'] ?? __( 'Shipping', 'bits-accessible-forms' ) ) . '</span>';
                $html .= '<span class="baf-total-value" data-total-shipping>-</span>';
                $html .= '</div>';
            }

            // Tax
            if ( ! empty( $this->config['show_tax'] ) ) {
                $tax_label = $this->config['tax_label'] ?? __( 'Tax', 'bits-accessible-forms' );
                $tax_rate = $this->config['tax_rate'] ?? 0;
                if ( $tax_rate > 0 ) {
                    $tax_label .= ' (' . $tax_rate . '%)';
                }

                $html .= '<div class="baf-total-line baf-tax" role="listitem">';
                $html .= '<span class="baf-total-label">' . esc_html( $tax_label ) . '</span>';
                $html .= '<span class="baf-total-value" data-total-tax>-</span>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Final total
        $html .= '<div class="baf-total-final">';
        $html .= '<span class="baf-total-final-label">' . esc_html( $label ) . '</span>';
        $html .= '<span class="baf-total-final-value" data-total-grand aria-live="polite">';
        $html .= $this->format_price( 0 );
        $html .= '</span>';
        $html .= '</div>';

        // Hidden input for form value
        $html .= '<input type="hidden" name="baf_field_' . esc_attr( $this->get_id() ) . '" value="0" data-total-hidden>';

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
