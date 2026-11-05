<?php
/**
 * Price Field - Display calculated price
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Price class
 */
class QAF_Field_Price extends QAF_Field {

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
            'label'              => __( 'Price', 'quill-accessible-forms' ),
            'base_price'         => 0,
            'currency'           => 'USD',
            'currency_position'  => 'before',
            'decimal_places'     => 2,
            'thousand_separator' => ',',
            'decimal_separator'  => '.',
            'display_style'      => 'inline',
            'show_original'      => false,
            'original_price'     => 0,
            'sale_badge'         => __( 'Sale!', 'quill-accessible-forms' ),
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
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'base_price' => array(
                    'type'  => 'number',
                    'label' => __( 'Base Price', 'quill-accessible-forms' ),
                    'min'   => 0,
                    'step'  => '0.01',
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
                        'CHF' => __( 'Swiss Franc (CHF)', 'quill-accessible-forms' ),
                        'CNY' => __( 'Chinese Yuan (¥)', 'quill-accessible-forms' ),
                        'INR' => __( 'Indian Rupee (₹)', 'quill-accessible-forms' ),
                        'MXN' => __( 'Mexican Peso (MX$)', 'quill-accessible-forms' ),
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
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'inline' => __( 'Inline', 'quill-accessible-forms' ),
                        'large'  => __( 'Large/Prominent', 'quill-accessible-forms' ),
                        'badge'  => __( 'Badge Style', 'quill-accessible-forms' ),
                        'subtle' => __( 'Subtle/Muted', 'quill-accessible-forms' ),
                    ),
                ),
                'per_unit_label' => array(
                    'type'        => 'text',
                    'label'       => __( 'Per Unit Label', 'quill-accessible-forms' ),
                    'placeholder' => __( 'e.g., /month, /item, each', 'quill-accessible-forms' ),
                ),
            ),
            'sale' => array(
                'show_original' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Original Price (Strikethrough)', 'quill-accessible-forms' ),
                ),
                'original_price' => array(
                    'type'      => 'number',
                    'label'     => __( 'Original Price', 'quill-accessible-forms' ),
                    'min'       => 0,
                    'step'      => '0.01',
                    'condition' => array( 'show_original' => true ),
                ),
                'sale_badge' => array(
                    'type'      => 'text',
                    'label'     => __( 'Sale Badge Text', 'quill-accessible-forms' ),
                    'condition' => array( 'show_original' => true ),
                ),
            ),
            'calculation' => array(
                'linked_fields' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Linked Fields', 'quill-accessible-forms' ),
                    'description' => __( 'Field IDs that affect this price (comma-separated)', 'quill-accessible-forms' ),
                ),
                'calculation' => array(
                    'type'        => 'textarea',
                    'label'       => __( 'Price Calculation', 'quill-accessible-forms' ),
                    'description' => __( 'Formula to calculate price dynamically', 'quill-accessible-forms' ),
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

        $wrapper_class = array( 'qaf-field', 'qaf-field-price', 'qaf-price-' . $display_style );
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
            $html .= '<span class="qaf-price-label">' . esc_html( $label ) . '</span>';
        }

        // Sale badge
        if ( ! empty( $this->config['show_original'] ) && ! empty( $this->config['sale_badge'] ) ) {
            $html .= '<span class="qaf-sale-badge" aria-label="' . esc_attr__( 'On sale', 'quill-accessible-forms' ) . '">';
            $html .= esc_html( $this->config['sale_badge'] );
            $html .= '</span>';
        }

        $html .= '<span class="qaf-price-display">';

        // Original price (strikethrough)
        if ( ! empty( $this->config['show_original'] ) ) {
            $original = (float) ( $this->config['original_price'] ?? 0 );
            if ( $original > 0 && $original > $base_price ) {
                $html .= '<span class="qaf-price-original"><del>';
                $html .= esc_html( $this->format_price( $original ) );
                $html .= '</del></span> ';
            }
        }

        // Current price
        $html .= '<span class="qaf-price-current" data-price-value aria-live="polite">';
        $html .= esc_html( $this->format_price( $base_price ) );
        $html .= '</span>';

        // Per unit label
        if ( ! empty( $this->config['per_unit_label'] ) ) {
            $html .= '<span class="qaf-price-unit">' . esc_html( $this->config['per_unit_label'] ) . '</span>';
        }

        $html .= '</span>';

        // Hidden input for form data
        $html .= '<input type="hidden" name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
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
