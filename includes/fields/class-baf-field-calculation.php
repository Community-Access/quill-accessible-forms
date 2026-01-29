<?php
/**
 * Calculation Field - Formula-based computed values
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Calculation class
 */
class BAF_Field_Calculation extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'calculation';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Whether field is read-only by default
     *
     * @var bool
     */
    protected $readonly = true;

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'formula'           => '',
            'format'            => 'number',
            'decimal_places'    => 2,
            'thousand_separator' => true,
            'currency_symbol'   => '$',
            'currency_position' => 'before',
            'prefix'            => '',
            'suffix'            => '',
            'hide_empty'        => false,
            'round_method'      => 'round',
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
                'formula' => array(
                    'type'  => 'formula_builder',
                    'label' => __( 'Formula', 'bits-accessible-forms' ),
                    'desc'  => __( 'Use {field_id} to reference other fields. Supports +, -, *, /, (), and functions like sum(), avg(), min(), max().', 'bits-accessible-forms' ),
                ),
            ),
            'formatting' => array(
                'format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Format', 'bits-accessible-forms' ),
                    'options' => array(
                        'number'   => __( 'Number', 'bits-accessible-forms' ),
                        'currency' => __( 'Currency', 'bits-accessible-forms' ),
                        'percent'  => __( 'Percentage', 'bits-accessible-forms' ),
                    ),
                ),
                'decimal_places' => array(
                    'type'  => 'number',
                    'label' => __( 'Decimal Places', 'bits-accessible-forms' ),
                    'min'   => 0,
                    'max'   => 10,
                ),
                'thousand_separator' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Thousand Separator', 'bits-accessible-forms' ),
                ),
                'round_method' => array(
                    'type'    => 'select',
                    'label'   => __( 'Rounding Method', 'bits-accessible-forms' ),
                    'options' => array(
                        'round' => __( 'Round', 'bits-accessible-forms' ),
                        'ceil'  => __( 'Round Up', 'bits-accessible-forms' ),
                        'floor' => __( 'Round Down', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'currency' => array(
                'currency_symbol' => array(
                    'type'  => 'text',
                    'label' => __( 'Currency Symbol', 'bits-accessible-forms' ),
                ),
                'currency_position' => array(
                    'type'    => 'select',
                    'label'   => __( 'Symbol Position', 'bits-accessible-forms' ),
                    'options' => array(
                        'before' => __( 'Before', 'bits-accessible-forms' ),
                        'after'  => __( 'After', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'display' => array(
                'prefix' => array(
                    'type'  => 'text',
                    'label' => __( 'Prefix', 'bits-accessible-forms' ),
                ),
                'suffix' => array(
                    'type'  => 'text',
                    'label' => __( 'Suffix', 'bits-accessible-forms' ),
                ),
                'hide_empty' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Hide When Empty/Zero', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the calculation field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        $formula = $this->config['formula'] ?? '';

        $wrapper_class = $this->get_wrapper_classes();
        $desc_id = $html_id . '-desc';
        
        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-formula="' . esc_attr( $formula ) . '"';
        $html .= ' data-format="' . esc_attr( $this->config['format'] ) . '"';
        $html .= ' data-decimals="' . esc_attr( $this->config['decimal_places'] ) . '"';
        $html .= ' data-round="' . esc_attr( $this->config['round_method'] ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-field-label">';
        $html .= '<span class="baf-label-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= '</label>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Calculated display value
        $html .= '<div class="baf-calculation-display">';
        
        // Prefix
        if ( ! empty( $this->config['prefix'] ) ) {
            $html .= '<span class="baf-calculation-prefix">' . esc_html( $this->config['prefix'] ) . '</span>';
        }

        // The visible output
        $html .= '<output id="' . esc_attr( $html_id ) . '-output" class="baf-calculation-value" ';
        $html .= 'for="' . esc_attr( $this->get_formula_field_ids() ) . '" ';
        $html .= 'aria-live="polite" aria-atomic="true">';
        $html .= esc_html( $this->format_value( $value ) );
        $html .= '</output>';

        // Suffix
        if ( ! empty( $this->config['suffix'] ) ) {
            $html .= '<span class="baf-calculation-suffix">' . esc_html( $this->config['suffix'] ) . '</span>';
        }

        $html .= '</div>';

        // Hidden input for form submission
        $html .= '<input type="hidden" id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' class="baf-calculation-input"';
        $html .= ' readonly>';

        // Loading indicator
        $html .= '<span class="baf-calculation-loading" aria-hidden="true" style="display: none;">';
        $html .= '<span class="baf-spinner"></span>';
        $html .= '</span>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get space-separated field IDs from formula
     *
     * @return string Field IDs.
     */
    protected function get_formula_field_ids() {
        $formula = $this->config['formula'] ?? '';
        preg_match_all( '/\{([a-zA-Z0-9_]+)\}/', $formula, $matches );
        
        if ( empty( $matches[1] ) ) {
            return '';
        }

        return implode( ' ', array_map( function( $id ) {
            return 'baf-field-' . $id;
        }, array_unique( $matches[1] ) ) );
    }

    /**
     * Format a value according to settings
     *
     * @param float|string $value Raw value.
     * @return string Formatted value.
     */
    protected function format_value( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $value = (float) $value;
        $format = $this->config['format'] ?? 'number';
        $decimals = (int) $this->config['decimal_places'];
        $use_thousands = ! empty( $this->config['thousand_separator'] );

        // Apply rounding
        switch ( $this->config['round_method'] ?? 'round' ) {
            case 'ceil':
                $value = ceil( $value * pow( 10, $decimals ) ) / pow( 10, $decimals );
                break;
            case 'floor':
                $value = floor( $value * pow( 10, $decimals ) ) / pow( 10, $decimals );
                break;
            default:
                $value = round( $value, $decimals );
        }

        // Format number
        $formatted = number_format(
            $value,
            $decimals,
            '.',
            $use_thousands ? ',' : ''
        );

        // Apply format-specific formatting
        switch ( $format ) {
            case 'currency':
                $symbol = $this->config['currency_symbol'] ?? '$';
                $position = $this->config['currency_position'] ?? 'before';
                
                if ( $position === 'before' ) {
                    $formatted = $symbol . $formatted;
                } else {
                    $formatted = $formatted . $symbol;
                }
                break;

            case 'percent':
                $formatted = $formatted . '%';
                break;
        }

        return $formatted;
    }

    /**
     * Calculate value from formula and field values
     *
     * @param array $field_values Field ID => value pairs.
     * @return float Calculated value.
     */
    public function calculate( $field_values ) {
        $formula = $this->config['formula'] ?? '';
        
        if ( empty( $formula ) ) {
            return 0;
        }

        // Replace field references with values
        $expression = preg_replace_callback( '/\{([a-zA-Z0-9_]+)\}/', function( $matches ) use ( $field_values ) {
            $field_id = $matches[1];
            return isset( $field_values[ $field_id ] ) ? (float) $field_values[ $field_id ] : 0;
        }, $formula );

        // Process function calls
        $expression = $this->process_functions( $expression );

        // Safely evaluate the math expression
        return $this->evaluate_expression( $expression );
    }

    /**
     * Process formula functions
     *
     * @param string $expression Expression with functions.
     * @return string Expression with functions evaluated.
     */
    protected function process_functions( $expression ) {
        // sum(1,2,3)
        $expression = preg_replace_callback( '/sum\(([^)]+)\)/i', function( $matches ) {
            $values = array_map( 'floatval', explode( ',', $matches[1] ) );
            return array_sum( $values );
        }, $expression );

        // avg(1,2,3)
        $expression = preg_replace_callback( '/avg\(([^)]+)\)/i', function( $matches ) {
            $values = array_map( 'floatval', explode( ',', $matches[1] ) );
            return count( $values ) > 0 ? array_sum( $values ) / count( $values ) : 0;
        }, $expression );

        // min(1,2,3)
        $expression = preg_replace_callback( '/min\(([^)]+)\)/i', function( $matches ) {
            $values = array_map( 'floatval', explode( ',', $matches[1] ) );
            return count( $values ) > 0 ? min( $values ) : 0;
        }, $expression );

        // max(1,2,3)
        $expression = preg_replace_callback( '/max\(([^)]+)\)/i', function( $matches ) {
            $values = array_map( 'floatval', explode( ',', $matches[1] ) );
            return count( $values ) > 0 ? max( $values ) : 0;
        }, $expression );

        // abs(x)
        $expression = preg_replace_callback( '/abs\(([^)]+)\)/i', function( $matches ) {
            return abs( (float) $matches[1] );
        }, $expression );

        // round(x, decimals)
        $expression = preg_replace_callback( '/round\(([^,]+),?\s*(\d*)\)/i', function( $matches ) {
            $decimals = isset( $matches[2] ) && $matches[2] !== '' ? (int) $matches[2] : 0;
            return round( (float) $matches[1], $decimals );
        }, $expression );

        return $expression;
    }

    /**
     * Safely evaluate a mathematical expression
     *
     * @param string $expression Mathematical expression.
     * @return float Result.
     */
    protected function evaluate_expression( $expression ) {
        // Remove whitespace
        $expression = preg_replace( '/\s+/', '', $expression );

        // Only allow safe characters
        if ( ! preg_match( '/^[\d+\-*\/().]+$/', $expression ) ) {
            return 0;
        }

        // Prevent division by zero
        $expression = preg_replace( '/\/0(?![.\d])/', '/1', $expression );

        // Use eval safely (only numeric and operators)
        try {
            // Additional safety: limit expression length
            if ( strlen( $expression ) > 500 ) {
                return 0;
            }

            $result = 0;
            // phpcs:ignore Squiz.PHP.Eval.Discouraged
            eval( '$result = ' . $expression . ';' );
            
            return is_numeric( $result ) ? (float) $result : 0;
        } catch ( \Throwable $e ) {
            return 0;
        }
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

        return (float) $value;
    }

    /**
     * Calculation fields don't need typical validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        $formatted = $this->format_value( $value );
        
        $prefix = $this->config['prefix'] ?? '';
        $suffix = $this->config['suffix'] ?? '';
        
        return $prefix . $formatted . $suffix;
    }
}
