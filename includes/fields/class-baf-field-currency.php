<?php
/**
 * Currency Input Field
 *
 * Formatted currency input with symbol, thousands separator, and decimal handling.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Currency class
 */
class BAF_Field_Currency extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'currency';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'number';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'currency_code'        => 'USD',
            'currency_symbol'      => '$',
            'symbol_position'      => 'before', // 'before' or 'after'
            'decimal_places'       => 2,
            'decimal_separator'    => '.',
            'thousands_separator'  => ',',
            'min'                  => null,
            'max'                  => null,
            'step'                 => '0.01',
            'placeholder'          => '',
            'allow_negative'       => false,
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
            'currency' => array(
                'currency_code' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency', 'bits-accessible-forms' ),
                    'options' => $this->get_currency_options(),
                ),
                'symbol_position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Symbol Position', 'bits-accessible-forms' ),
                    'options' => array(
                        'before' => __( 'Before ($100)', 'bits-accessible-forms' ),
                        'after'  => __( 'After (100$)', 'bits-accessible-forms' ),
                    ),
                ),
                'decimal_places' => array(
                    'type'    => 'number',
                    'label'   => __( 'Decimal Places', 'bits-accessible-forms' ),
                    'min'     => 0,
                    'max'     => 4,
                    'default' => 2,
                ),
            ),
            'validation' => array(
                'min' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Value', 'bits-accessible-forms' ),
                    'step'  => '0.01',
                ),
                'max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Value', 'bits-accessible-forms' ),
                    'step'  => '0.01',
                ),
                'allow_negative' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Negative Values', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'bits-accessible-forms' ),
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
        $html_id  = $this->get_html_id();
        $name     = 'baf_field_' . $this->get_id();
        $value    = $this->get_value();
        $symbol   = $this->get_currency_symbol();
        $position = $this->config['symbol_position'] ?? 'before';

        // Format the display value
        $display_value = '';
        if ( $value !== '' && $value !== null ) {
            $display_value = $this->format_currency( $value, false ); // Without symbol for input
        }

        $attrs = array(
            'type'        => 'text',
            'id'          => $html_id,
            'name'        => $name,
            'value'       => $display_value,
            'class'       => 'baf-currency-input',
            'inputmode'   => 'decimal',
            'placeholder' => $this->config['placeholder'] ?? '',
            'data-currency-code'     => $this->config['currency_code'] ?? 'USD',
            'data-decimal-places'    => $this->config['decimal_places'] ?? 2,
            'data-decimal-sep'       => $this->config['decimal_separator'] ?? '.',
            'data-thousands-sep'     => $this->config['thousands_separator'] ?? ',',
            'data-allow-negative'    => ( $this->config['allow_negative'] ?? false ) ? 'true' : 'false',
        );

        // Add min/max if set
        if ( isset( $this->config['min'] ) && $this->config['min'] !== '' ) {
            $attrs['data-min'] = $this->config['min'];
        }
        if ( isset( $this->config['max'] ) && $this->config['max'] !== '' ) {
            $attrs['data-max'] = $this->config['max'];
        }

        // ARIA attributes
        $aria = $this->get_aria_attributes();
        $attrs = array_merge( $attrs, $aria );

        // Build the input with currency symbol
        $field = '<div class="baf-currency-wrapper baf-currency-symbol-' . esc_attr( $position ) . '">';
        
        if ( $position === 'before' ) {
            $field .= '<span class="baf-currency-symbol baf-currency-symbol-before" aria-hidden="true">' . esc_html( $symbol ) . '</span>';
        }

        $field .= '<input ' . $this->build_attributes( $attrs ) . '>';

        if ( $position === 'after' ) {
            $field .= '<span class="baf-currency-symbol baf-currency-symbol-after" aria-hidden="true">' . esc_html( $symbol ) . '</span>';
        }

        $field .= '</div>';

        // Hidden field for actual numeric value
        $field .= '<input type="hidden" name="' . esc_attr( $name ) . '_raw" value="' . esc_attr( $value ) . '" class="baf-currency-raw">';

        return $this->render_wrapper( $field );
    }

    /**
     * Get currency symbol
     *
     * @return string Currency symbol.
     */
    protected function get_currency_symbol() {
        if ( ! empty( $this->config['currency_symbol'] ) ) {
            return $this->config['currency_symbol'];
        }

        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'INR' => '₹',
            'MXN' => '$',
            'BRL' => 'R$',
            'KRW' => '₩',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'RUB' => '₽',
            'ZAR' => 'R',
            'NZD' => 'NZ$',
            'SGD' => 'S$',
        );

        $code = $this->config['currency_code'] ?? 'USD';
        return $symbols[ $code ] ?? $code;
    }

    /**
     * Get currency options for admin UI
     *
     * @return array Currency options.
     */
    protected function get_currency_options() {
        return array(
            'USD' => __( 'US Dollar ($)', 'bits-accessible-forms' ),
            'EUR' => __( 'Euro (€)', 'bits-accessible-forms' ),
            'GBP' => __( 'British Pound (£)', 'bits-accessible-forms' ),
            'JPY' => __( 'Japanese Yen (¥)', 'bits-accessible-forms' ),
            'CAD' => __( 'Canadian Dollar (C$)', 'bits-accessible-forms' ),
            'AUD' => __( 'Australian Dollar (A$)', 'bits-accessible-forms' ),
            'CHF' => __( 'Swiss Franc (CHF)', 'bits-accessible-forms' ),
            'CNY' => __( 'Chinese Yuan (¥)', 'bits-accessible-forms' ),
            'INR' => __( 'Indian Rupee (₹)', 'bits-accessible-forms' ),
            'MXN' => __( 'Mexican Peso ($)', 'bits-accessible-forms' ),
            'BRL' => __( 'Brazilian Real (R$)', 'bits-accessible-forms' ),
            'KRW' => __( 'South Korean Won (₩)', 'bits-accessible-forms' ),
            'SEK' => __( 'Swedish Krona (kr)', 'bits-accessible-forms' ),
            'NOK' => __( 'Norwegian Krone (kr)', 'bits-accessible-forms' ),
            'DKK' => __( 'Danish Krone (kr)', 'bits-accessible-forms' ),
            'PLN' => __( 'Polish Zloty (zł)', 'bits-accessible-forms' ),
            'RUB' => __( 'Russian Ruble (₽)', 'bits-accessible-forms' ),
            'ZAR' => __( 'South African Rand (R)', 'bits-accessible-forms' ),
            'NZD' => __( 'New Zealand Dollar (NZ$)', 'bits-accessible-forms' ),
            'SGD' => __( 'Singapore Dollar (S$)', 'bits-accessible-forms' ),
        );
    }

    /**
     * Format currency value
     *
     * @param float|string $value        Value to format.
     * @param bool         $with_symbol  Include currency symbol.
     * @return string Formatted value.
     */
    protected function format_currency( $value, $with_symbol = true ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $decimal_places = (int) ( $this->config['decimal_places'] ?? 2 );
        $decimal_sep    = $this->config['decimal_separator'] ?? '.';
        $thousands_sep  = $this->config['thousands_separator'] ?? ',';
        $symbol         = $this->get_currency_symbol();
        $position       = $this->config['symbol_position'] ?? 'before';

        $formatted = number_format( (float) $value, $decimal_places, $decimal_sep, $thousands_sep );

        if ( ! $with_symbol ) {
            return $formatted;
        }

        return $position === 'before'
            ? $symbol . $formatted
            : $formatted . $symbol;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return float|string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        // Remove currency symbol and formatting
        $decimal_sep   = $this->config['decimal_separator'] ?? '.';
        $thousands_sep = $this->config['thousands_separator'] ?? ',';

        // Remove thousands separator and currency symbols
        $value = preg_replace( '/[^\d' . preg_quote( $decimal_sep, '/' ) . '-]/', '', $value );

        // Normalize decimal separator to period
        if ( $decimal_sep !== '.' ) {
            $value = str_replace( $decimal_sep, '.', $value );
        }

        // Validate it's a number
        if ( ! is_numeric( $value ) ) {
            return '';
        }

        return (float) $value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( $value === '' || $value === null ) {
            return;
        }

        $value = (float) $value;

        // Check negative values
        if ( ! ( $this->config['allow_negative'] ?? false ) && $value < 0 ) {
            $this->add_error( __( 'Negative values are not allowed.', 'bits-accessible-forms' ) );
            return;
        }

        // Check min
        if ( isset( $this->config['min'] ) && $this->config['min'] !== '' ) {
            if ( $value < (float) $this->config['min'] ) {
                $this->add_error( sprintf(
                    /* translators: %s: minimum value */
                    __( 'Value must be at least %s.', 'bits-accessible-forms' ),
                    $this->format_currency( $this->config['min'] )
                ) );
            }
        }

        // Check max
        if ( isset( $this->config['max'] ) && $this->config['max'] !== '' ) {
            if ( $value > (float) $this->config['max'] ) {
                $this->add_error( sprintf(
                    /* translators: %s: maximum value */
                    __( 'Value must be no more than %s.', 'bits-accessible-forms' ),
                    $this->format_currency( $this->config['max'] )
                ) );
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
        if ( $value === '' || $value === null ) {
            return '';
        }

        return $this->format_currency( $value, true );
    }
}
