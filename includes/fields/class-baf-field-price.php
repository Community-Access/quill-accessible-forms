<?php
/**
 * Price Field - Display calculated price
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Price class
 */
class BAF_Field_Price extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'price';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'pricing';

    /**
     * Whether field is read-only display
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
            'label'              => __( 'Price', 'bits-accessible-forms' ),
            'base_price'         => 0,
            'currency'           => 'USD',
            'currency_position'  => 'before',
            'decimal_places'     => 2,
            'thousand_separator' => ',',
            'decimal_separator'  => '.',
            'display_style'      => 'inline',
            'show_original'      => false,
            'original_price'     => 0,
            'sale_badge'         => __( 'Sale!', 'bits-accessible-forms' ),
            'per_unit_label'     => '',
            'linked_fields'      => array(),
            'calculation'        => '',
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
                    'label' => __( 'Label', 'bits-accessible-forms' ),
                ),
                'base_price' => array(
                    'type'  => 'number',
                    'label' => __( 'Base Price', 'bits-accessible-forms' ),
                    'min'   => 0,
                    'step'  => '0.01',
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
                        'CHF' => __( 'Swiss Franc (CHF)', 'bits-accessible-forms' ),
                        'CNY' => __( 'Chinese Yuan (¥)', 'bits-accessible-forms' ),
                        'INR' => __( 'Indian Rupee (₹)', 'bits-accessible-forms' ),
                        'MXN' => __( 'Mexican Peso (MX$)', 'bits-accessible-forms' ),
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
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'inline' => __( 'Inline', 'bits-accessible-forms' ),
                        'large'  => __( 'Large/Prominent', 'bits-accessible-forms' ),
                        'badge'  => __( 'Badge Style', 'bits-accessible-forms' ),
                        'subtle' => __( 'Subtle/Muted', 'bits-accessible-forms' ),
                    ),
                ),
                'per_unit_label' => array(
                    'type'        => 'text',
                    'label'       => __( 'Per Unit Label', 'bits-accessible-forms' ),
                    'placeholder' => __( 'e.g., /month, /item, each', 'bits-accessible-forms' ),
                ),
            ),
            'sale' => array(
                'show_original' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Original Price (Strikethrough)', 'bits-accessible-forms' ),
                ),
                'original_price' => array(
                    'type'      => 'number',
                    'label'     => __( 'Original Price', 'bits-accessible-forms' ),
                    'min'       => 0,
                    'step'      => '0.01',
                    'condition' => array( 'show_original' => true ),
                ),
                'sale_badge' => array(
                    'type'      => 'text',
                    'label'     => __( 'Sale Badge Text', 'bits-accessible-forms' ),
                    'condition' => array( 'show_original' => true ),
                ),
            ),
            'calculation' => array(
                'linked_fields' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Linked Fields', 'bits-accessible-forms' ),
                    'description' => __( 'Field IDs that affect this price (comma-separated)', 'bits-accessible-forms' ),
                ),
                'calculation' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Price Calculation', 'bits-accessible-forms' ),
                    'description' => __( 'Formula to calculate price dynamically', 'bits-accessible-forms' ),
                    'placeholder' => 'base_price * quantity',
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
        $label = $this->config['label'] ?? '';
        $display_style = $this->config['display_style'] ?? 'inline';
        $base_price = (float) ( $this->config['base_price'] ?? 0 );

        $wrapper_class = array( 'baf-field', 'baf-field-price', 'baf-price-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-base-price="' . esc_attr( $base_price ) . '"';
        if ( ! empty( $this->config['calculation'] ) ) {
            $html .= ' data-calculation="' . esc_attr( $this->config['calculation'] ) . '"';
        }
        $html .= '>';

        // Label
        if ( ! empty( $label ) ) {
            $html .= '<span class="baf-price-label">' . esc_html( $label ) . '</span>';
        }

        // Sale badge
        if ( ! empty( $this->config['show_original'] ) && ! empty( $this->config['sale_badge'] ) ) {
            $html .= '<span class="baf-sale-badge" aria-label="' . esc_attr__( 'On sale', 'bits-accessible-forms' ) . '">';
            $html .= esc_html( $this->config['sale_badge'] );
            $html .= '</span>';
        }

        $html .= '<span class="baf-price-display">';

        // Original price (strikethrough)
        if ( ! empty( $this->config['show_original'] ) ) {
            $original = (float) ( $this->config['original_price'] ?? 0 );
            if ( $original > 0 && $original > $base_price ) {
                $html .= '<span class="baf-price-original"><del>';
                $html .= esc_html( $this->format_price( $original ) );
                $html .= '</del></span> ';
            }
        }

        // Current price
        $html .= '<span class="baf-price-current" data-price-value aria-live="polite">';
        $html .= esc_html( $this->format_price( $base_price ) );
        $html .= '</span>';

        // Per unit label
        if ( ! empty( $this->config['per_unit_label'] ) ) {
            $html .= '<span class="baf-price-unit">' . esc_html( $this->config['per_unit_label'] ) . '</span>';
        }

        $html .= '</span>';

        // Hidden input for form data
        $html .= '<input type="hidden" name="baf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' value="' . esc_attr( $base_price ) . '" data-price-hidden>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Format price with currency
     *
     * @param float $price Price to format.
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
            'AUD' => 'A$', 'JPY' => '¥', 'CHF' => 'CHF', 'CNY' => '¥',
            'INR' => '₹', 'MXN' => 'MX$',
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
     * Validate - display field, always valid
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Sanitize value
     *
     * @param mixed $value Value to sanitize.
     * @return float Sanitized price.
     */
    public function sanitize( $value ) {
        return (float) $value;
    }

    /**
     * Format for display
     *
     * @param mixed $value Value.
     * @return string Formatted price.
     */
    public function format_display( $value ) {
        return $this->format_price( (float) $value );
    }
}
