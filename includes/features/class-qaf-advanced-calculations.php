<?php
/**
 * Advanced Calculations Engine
 *
 * Sophisticated calculation system with conditional logic, math functions,
 * and complex formula support. Inspired by GP Advanced Calculations.
 *
 * @package QAF
 * @since 1.0.0
 */

namespace QAF\Features;

use QAF\Core\Form;

/**
 * Class Advanced_Calculations
 *
 * Provides a powerful calculation engine for form fields.
 */
class Advanced_Calculations {

    /**
     * Available math functions.
     *
     * @var array
     */
    private array $math_functions = [
        'abs', 'ceil', 'floor', 'round', 'sqrt',
        'min', 'max', 'pow', 'log', 'log10',
        'sin', 'cos', 'tan', 'exp',
        'sum', 'avg', 'count', 'median', 'stdev',
        'fv', 'pv', 'pmt', 'rate', 'nper', // Financial functions
    ];

    /**
     * Field values for current calculation.
     *
     * @var array
     */
    private array $values = [];

    /**
     * Form instance.
     *
     * @var Form|null
     */
    private ?Form $form = null;

    /**
     * Calculation cache.
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Recursion protection.
     *
     * @var array
     */
    private array $calculation_stack = [];

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_ajax_qaf_calculate', [ $this, 'ajax_calculate' ] );
        add_action( 'wp_ajax_nopriv_qaf_calculate', [ $this, 'ajax_calculate' ] );
        add_filter( 'qaf_field_value', [ $this, 'process_calculation_field' ], 10, 3 );
    }

    /**
     * Evaluate a calculation formula.
     *
     * @param string    $formula Formula string.
     * @param array     $values  Field values.
     * @param Form|null $form    Form instance.
     * @return float|string Result.
     */
    public function evaluate( string $formula, array $values = [], ?Form $form = null ) {
        $this->values = $values;
        $this->form   = $form;

        // Check cache
        $cache_key = md5( $formula . serialize( $values ) );
        if ( isset( $this->cache[ $cache_key ] ) ) {
            return $this->cache[ $cache_key ];
        }

        try {
            // Replace field references with values
            $formula = $this->replace_field_references( $formula );

            // Process conditional logic (if/else)
            $formula = $this->process_conditionals( $formula );

            // Process math functions
            $formula = $this->process_functions( $formula );

            // Evaluate the mathematical expression
            $result = $this->evaluate_expression( $formula );

            // Cache result
            $this->cache[ $cache_key ] = $result;

            return $result;

        } catch ( \Exception $e ) {
            // Log error and return 0
            error_log( 'QAF Calculation Error: ' . $e->getMessage() . ' in formula: ' . $formula );
            return apply_filters( 'qaf_calculation_error_value', 0, $formula, $e );
        }
    }

    /**
     * Replace field references with their values.
     * Supports: {field_name}, field_name, and :field_name
     *
     * @param string $formula Formula with field references.
     * @return string Formula with values.
     */
    private function replace_field_references( string $formula ): string {
        // Pattern 1: {field_name} (merge tag style)
        $formula = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function( $matches ) {
                return $this->get_field_value( $matches[1] );
            },
            $formula
        );

        // Pattern 2: :field_name (colon prefix)
        $formula = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            function( $matches ) {
                return $this->get_field_value( $matches[1] );
            },
            $formula
        );

        return $formula;
    }

    /**
     * Get field value for calculation.
     *
     * @param string $field_id Field identifier.
     * @return string Numeric value as string.
     */
    private function get_field_value( string $field_id ): string {
        // Check for circular reference
        if ( in_array( $field_id, $this->calculation_stack, true ) ) {
            throw new \Exception( "Circular reference detected for field: {$field_id}" );
        }

        // Get raw value
        $value = $this->values[ $field_id ] ?? 0;

        // Handle arrays (sum them)
        if ( is_array( $value ) ) {
            $value = array_sum( array_map( 'floatval', $value ) );
        }

        // Extract numeric value from strings with currency
        if ( is_string( $value ) ) {
            $value = $this->extract_number( $value );
        }

        // Check if this field is itself a calculation
        if ( $this->form ) {
            $field = $this->form->get_field( $field_id );
            if ( $field && $field->get_type() === 'calculation' ) {
                $nested_formula = $field->get_property( 'formula' );
                if ( $nested_formula ) {
                    $this->calculation_stack[] = $field_id;
                    $value = $this->evaluate( $nested_formula, $this->values, $this->form );
                    array_pop( $this->calculation_stack );
                }
            }
        }

        return (string) floatval( $value );
    }

    /**
     * Extract numeric value from string.
     *
     * @param string $value String value.
     * @return float Numeric value.
     */
    private function extract_number( string $value ): float {
        // Remove currency symbols and formatting
        $value = preg_replace( '/[^\d.\-]/', '', $value );
        return (float) $value;
    }

    /**
     * Process conditional statements in formula.
     * Supports: if(condition, true_value, false_value)
     *          condition ? true_value : false_value
     *
     * @param string $formula Formula with conditionals.
     * @return string Processed formula.
     */
    private function process_conditionals( string $formula ): string {
        // Pattern for if() function
        $pattern = '/if\s*\(\s*([^,]+)\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/i';
        
        while ( preg_match( $pattern, $formula, $matches ) ) {
            $condition   = trim( $matches[1] );
            $true_value  = trim( $matches[2] );
            $false_value = trim( $matches[3] );

            $result = $this->evaluate_condition( $condition ) ? $true_value : $false_value;
            $formula = str_replace( $matches[0], $result, $formula );
        }

        // Pattern for ternary operator: condition ? true : false
        $ternary_pattern = '/\(([^?]+)\)\s*\?\s*([^:]+)\s*:\s*([^;)]+)/';
        
        while ( preg_match( $ternary_pattern, $formula, $matches ) ) {
            $condition   = trim( $matches[1] );
            $true_value  = trim( $matches[2] );
            $false_value = trim( $matches[3] );

            $result = $this->evaluate_condition( $condition ) ? $true_value : $false_value;
            $formula = str_replace( $matches[0], $result, $formula );
        }

        return $formula;
    }

    /**
     * Evaluate a condition.
     *
     * @param string $condition Condition string.
     * @return bool Result.
     */
    private function evaluate_condition( string $condition ): bool {
        // Supported operators
        $operators = [
            '===' => fn( $a, $b ) => $a === $b,
            '!==' => fn( $a, $b ) => $a !== $b,
            '=='  => fn( $a, $b ) => $a == $b,
            '!='  => fn( $a, $b ) => $a != $b,
            '<='  => fn( $a, $b ) => (float) $a <= (float) $b,
            '>='  => fn( $a, $b ) => (float) $a >= (float) $b,
            '<'   => fn( $a, $b ) => (float) $a < (float) $b,
            '>'   => fn( $a, $b ) => (float) $a > (float) $b,
        ];

        foreach ( $operators as $op => $callback ) {
            if ( strpos( $condition, $op ) !== false ) {
                list( $left, $right ) = array_map( 'trim', explode( $op, $condition, 2 ) );
                
                // Evaluate both sides
                $left_val  = $this->evaluate_expression( $left );
                $right_val = $this->evaluate_expression( $right );

                return $callback( $left_val, $right_val );
            }
        }

        // Check for logical operators
        if ( stripos( $condition, ' and ' ) !== false || stripos( $condition, ' && ' ) !== false ) {
            $parts = preg_split( '/\s+(?:and|&&)\s+/i', $condition );
            foreach ( $parts as $part ) {
                if ( ! $this->evaluate_condition( trim( $part ) ) ) {
                    return false;
                }
            }
            return true;
        }

        if ( stripos( $condition, ' or ' ) !== false || stripos( $condition, ' || ' ) !== false ) {
            $parts = preg_split( '/\s+(?:or|\|\|)\s+/i', $condition );
            foreach ( $parts as $part ) {
                if ( $this->evaluate_condition( trim( $part ) ) ) {
                    return true;
                }
            }
            return false;
        }

        // Simple truthy check
        $value = $this->evaluate_expression( $condition );
        return ! empty( $value );
    }

    /**
     * Process math functions in formula.
     *
     * @param string $formula Formula with functions.
     * @return string Processed formula.
     */
    private function process_functions( string $formula ): string {
        foreach ( $this->math_functions as $func ) {
            $pattern = '/' . $func . '\s*\(([^)]+)\)/i';
            
            while ( preg_match( $pattern, $formula, $matches ) ) {
                $args   = $this->parse_function_args( $matches[1] );
                $result = $this->execute_function( $func, $args );
                $formula = str_replace( $matches[0], (string) $result, $formula );
            }
        }

        return $formula;
    }

    /**
     * Parse function arguments.
     *
     * @param string $args_string Comma-separated arguments.
     * @return array Parsed arguments.
     */
    private function parse_function_args( string $args_string ): array {
        $args = [];
        $current = '';
        $depth = 0;

        for ( $i = 0; $i < strlen( $args_string ); $i++ ) {
            $char = $args_string[ $i ];
            
            if ( $char === '(' ) {
                $depth++;
                $current .= $char;
            } elseif ( $char === ')' ) {
                $depth--;
                $current .= $char;
            } elseif ( $char === ',' && $depth === 0 ) {
                $args[] = $this->evaluate_expression( trim( $current ) );
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ( ! empty( $current ) ) {
            $args[] = $this->evaluate_expression( trim( $current ) );
        }

        return $args;
    }

    /**
     * Execute a math function.
     *
     * @param string $func Function name.
     * @param array  $args Function arguments.
     * @return float Result.
     */
    private function execute_function( string $func, array $args ): float {
        switch ( strtolower( $func ) ) {
            // Basic math
            case 'abs':
                return abs( (float) ( $args[0] ?? 0 ) );
                
            case 'ceil':
                return ceil( (float) ( $args[0] ?? 0 ) );
                
            case 'floor':
                return floor( (float) ( $args[0] ?? 0 ) );
                
            case 'round':
                $precision = (int) ( $args[1] ?? 0 );
                return round( (float) ( $args[0] ?? 0 ), $precision );
                
            case 'sqrt':
                return sqrt( abs( (float) ( $args[0] ?? 0 ) ) );
                
            case 'pow':
                return pow( (float) ( $args[0] ?? 0 ), (float) ( $args[1] ?? 2 ) );
                
            case 'log':
                return log( max( 0.0001, (float) ( $args[0] ?? 1 ) ) );
                
            case 'log10':
                return log10( max( 0.0001, (float) ( $args[0] ?? 1 ) ) );

            case 'exp':
                return exp( (float) ( $args[0] ?? 0 ) );

            // Trigonometry
            case 'sin':
                return sin( (float) ( $args[0] ?? 0 ) );
            case 'cos':
                return cos( (float) ( $args[0] ?? 0 ) );
            case 'tan':
                return tan( (float) ( $args[0] ?? 0 ) );

            // Aggregates
            case 'min':
                return count( $args ) > 0 ? min( array_map( 'floatval', $args ) ) : 0;
                
            case 'max':
                return count( $args ) > 0 ? max( array_map( 'floatval', $args ) ) : 0;
                
            case 'sum':
                return array_sum( array_map( 'floatval', $args ) );
                
            case 'avg':
                if ( empty( $args ) ) {
                    return 0;
                }
                return array_sum( array_map( 'floatval', $args ) ) / count( $args );
                
            case 'count':
                return count( $args );

            case 'median':
                return $this->calculate_median( $args );

            case 'stdev':
                return $this->calculate_stdev( $args );

            // Financial functions
            case 'fv': // Future Value: fv(rate, periods, payment, present_value)
                return $this->calculate_fv( $args );
                
            case 'pv': // Present Value: pv(rate, periods, payment, future_value)
                return $this->calculate_pv( $args );
                
            case 'pmt': // Payment: pmt(rate, periods, present_value, future_value)
                return $this->calculate_pmt( $args );

            case 'rate': // Interest Rate
                return $this->calculate_rate( $args );

            case 'nper': // Number of Periods
                return $this->calculate_nper( $args );

            default:
                // Allow custom functions via filter
                return apply_filters( "qaf_calculation_function_{$func}", 0, $args );
        }
    }

    /**
     * Calculate median.
     *
     * @param array $values Values.
     * @return float Median.
     */
    private function calculate_median( array $values ): float {
        if ( empty( $values ) ) {
            return 0;
        }

        $values = array_map( 'floatval', $values );
        sort( $values );
        $count = count( $values );
        $middle = (int) floor( ( $count - 1 ) / 2 );

        if ( $count % 2 === 0 ) {
            return ( $values[ $middle ] + $values[ $middle + 1 ] ) / 2;
        }

        return $values[ $middle ];
    }

    /**
     * Calculate standard deviation.
     *
     * @param array $values Values.
     * @return float Standard deviation.
     */
    private function calculate_stdev( array $values ): float {
        if ( count( $values ) < 2 ) {
            return 0;
        }

        $values = array_map( 'floatval', $values );
        $mean = array_sum( $values ) / count( $values );
        $sum_squared_diff = 0;

        foreach ( $values as $value ) {
            $sum_squared_diff += pow( $value - $mean, 2 );
        }

        return sqrt( $sum_squared_diff / ( count( $values ) - 1 ) );
    }

    /**
     * Calculate Future Value.
     * FV = PV * (1 + r)^n + PMT * (((1 + r)^n - 1) / r)
     *
     * @param array $args [rate, periods, payment, present_value].
     * @return float Future value.
     */
    private function calculate_fv( array $args ): float {
        $rate    = (float) ( $args[0] ?? 0 ) / 100;
        $periods = (int) ( $args[1] ?? 0 );
        $payment = (float) ( $args[2] ?? 0 );
        $pv      = (float) ( $args[3] ?? 0 );

        if ( $rate === 0.0 ) {
            return -( $pv + $payment * $periods );
        }

        $factor = pow( 1 + $rate, $periods );
        return -( $pv * $factor + $payment * ( ( $factor - 1 ) / $rate ) );
    }

    /**
     * Calculate Present Value.
     *
     * @param array $args [rate, periods, payment, future_value].
     * @return float Present value.
     */
    private function calculate_pv( array $args ): float {
        $rate    = (float) ( $args[0] ?? 0 ) / 100;
        $periods = (int) ( $args[1] ?? 0 );
        $payment = (float) ( $args[2] ?? 0 );
        $fv      = (float) ( $args[3] ?? 0 );

        if ( $rate === 0.0 ) {
            return -( $fv + $payment * $periods );
        }

        $factor = pow( 1 + $rate, $periods );
        return -( $fv / $factor + $payment * ( ( 1 - ( 1 / $factor ) ) / $rate ) );
    }

    /**
     * Calculate Payment (PMT).
     *
     * @param array $args [rate, periods, present_value, future_value].
     * @return float Payment amount.
     */
    private function calculate_pmt( array $args ): float {
        $rate    = (float) ( $args[0] ?? 0 ) / 100;
        $periods = (int) ( $args[1] ?? 0 );
        $pv      = (float) ( $args[2] ?? 0 );
        $fv      = (float) ( $args[3] ?? 0 );

        if ( $rate === 0.0 ) {
            return -( $pv + $fv ) / $periods;
        }

        $factor = pow( 1 + $rate, $periods );
        return -( $rate * ( $pv * $factor + $fv ) ) / ( $factor - 1 );
    }

    /**
     * Calculate Interest Rate (iterative approximation).
     *
     * @param array $args [periods, payment, present_value, future_value].
     * @return float Interest rate as percentage.
     */
    private function calculate_rate( array $args ): float {
        $periods = (int) ( $args[0] ?? 0 );
        $payment = (float) ( $args[1] ?? 0 );
        $pv      = (float) ( $args[2] ?? 0 );
        $fv      = (float) ( $args[3] ?? 0 );

        if ( $periods <= 0 ) {
            return 0;
        }

        // Newton-Raphson iteration
        $rate = 0.1;
        $tolerance = 0.0000001;
        $max_iterations = 100;

        for ( $i = 0; $i < $max_iterations; $i++ ) {
            $f = $pv * pow( 1 + $rate, $periods ) + $payment * ( ( pow( 1 + $rate, $periods ) - 1 ) / $rate ) + $fv;
            
            $df = $pv * $periods * pow( 1 + $rate, $periods - 1 )
                + $payment * ( ( $periods * pow( 1 + $rate, $periods - 1 ) * $rate - ( pow( 1 + $rate, $periods ) - 1 ) ) / ( $rate * $rate ) );
            
            if ( abs( $df ) < $tolerance ) {
                break;
            }
            
            $new_rate = $rate - $f / $df;
            
            if ( abs( $new_rate - $rate ) < $tolerance ) {
                return $new_rate * 100;
            }
            
            $rate = $new_rate;
        }

        return $rate * 100;
    }

    /**
     * Calculate Number of Periods.
     *
     * @param array $args [rate, payment, present_value, future_value].
     * @return float Number of periods.
     */
    private function calculate_nper( array $args ): float {
        $rate    = (float) ( $args[0] ?? 0 ) / 100;
        $payment = (float) ( $args[1] ?? 0 );
        $pv      = (float) ( $args[2] ?? 0 );
        $fv      = (float) ( $args[3] ?? 0 );

        if ( $rate === 0.0 ) {
            return -( $pv + $fv ) / $payment;
        }

        $num = log( ( $payment - $rate * $fv ) / ( $payment + $rate * $pv ) );
        $den = log( 1 + $rate );

        return $num / $den;
    }

    /**
     * Evaluate a mathematical expression safely.
     *
     * @param string $expression Math expression.
     * @return float Result.
     * @throws \Exception If expression is invalid.
     */
    private function evaluate_expression( string $expression ) {
        // Remove whitespace
        $expression = preg_replace( '/\s+/', '', $expression );

        // Handle empty or simple values
        if ( $expression === '' ) {
            return 0;
        }

        if ( is_numeric( $expression ) ) {
            return (float) $expression;
        }

        // Validate expression (only allow safe characters)
        if ( ! preg_match( '/^[\d\.\+\-\*\/\%\(\)]+$/', $expression ) ) {
            // Check if it's a string/text value
            if ( preg_match( '/^["\'].*["\']$/', $expression ) ) {
                return trim( $expression, '"\'' );
            }
            throw new \Exception( "Invalid expression: {$expression}" );
        }

        // Use a simple recursive descent parser
        return $this->parse_expression( $expression, 0 )[0];
    }

    /**
     * Parse and evaluate expression recursively.
     *
     * @param string $expr Expression.
     * @param int    $pos  Current position.
     * @return array [result, new_position].
     */
    private function parse_expression( string $expr, int $pos ): array {
        list( $result, $pos ) = $this->parse_term( $expr, $pos );

        while ( $pos < strlen( $expr ) ) {
            $op = $expr[ $pos ] ?? '';
            
            if ( $op === '+' ) {
                $pos++;
                list( $right, $pos ) = $this->parse_term( $expr, $pos );
                $result += $right;
            } elseif ( $op === '-' ) {
                $pos++;
                list( $right, $pos ) = $this->parse_term( $expr, $pos );
                $result -= $right;
            } else {
                break;
            }
        }

        return [ $result, $pos ];
    }

    /**
     * Parse a term (handles * and /).
     *
     * @param string $expr Expression.
     * @param int    $pos  Current position.
     * @return array [result, new_position].
     */
    private function parse_term( string $expr, int $pos ): array {
        list( $result, $pos ) = $this->parse_factor( $expr, $pos );

        while ( $pos < strlen( $expr ) ) {
            $op = $expr[ $pos ] ?? '';
            
            if ( $op === '*' ) {
                $pos++;
                list( $right, $pos ) = $this->parse_factor( $expr, $pos );
                $result *= $right;
            } elseif ( $op === '/' ) {
                $pos++;
                list( $right, $pos ) = $this->parse_factor( $expr, $pos );
                if ( $right == 0 ) {
                    throw new \Exception( 'Division by zero' );
                }
                $result /= $right;
            } elseif ( $op === '%' ) {
                $pos++;
                list( $right, $pos ) = $this->parse_factor( $expr, $pos );
                if ( $right == 0 ) {
                    throw new \Exception( 'Modulo by zero' );
                }
                $result = fmod( $result, $right );
            } else {
                break;
            }
        }

        return [ $result, $pos ];
    }

    /**
     * Parse a factor (handles numbers and parentheses).
     *
     * @param string $expr Expression.
     * @param int    $pos  Current position.
     * @return array [result, new_position].
     */
    private function parse_factor( string $expr, int $pos ): array {
        // Handle negative numbers
        $negative = false;
        if ( isset( $expr[ $pos ] ) && $expr[ $pos ] === '-' ) {
            $negative = true;
            $pos++;
        }

        // Handle parentheses
        if ( isset( $expr[ $pos ] ) && $expr[ $pos ] === '(' ) {
            $pos++;
            list( $result, $pos ) = $this->parse_expression( $expr, $pos );
            
            if ( ! isset( $expr[ $pos ] ) || $expr[ $pos ] !== ')' ) {
                throw new \Exception( 'Mismatched parentheses' );
            }
            $pos++;
        } else {
            // Parse number
            $start = $pos;
            while ( $pos < strlen( $expr ) && ( ctype_digit( $expr[ $pos ] ) || $expr[ $pos ] === '.' ) ) {
                $pos++;
            }
            
            if ( $pos === $start ) {
                throw new \Exception( "Expected number at position {$pos}" );
            }
            
            $result = (float) substr( $expr, $start, $pos - $start );
        }

        return [ $negative ? -$result : $result, $pos ];
    }

    /**
     * Handle AJAX calculation request.
     *
     * @return void
     */
    public function ajax_calculate(): void {
        check_ajax_referer( 'qaf_calculate', 'nonce' );

        $formula = sanitize_text_field( $_POST['formula'] ?? '' );
        $values  = $_POST['values'] ?? [];
        $form_id = absint( $_POST['form_id'] ?? 0 );

        if ( empty( $formula ) ) {
            wp_send_json_error( [ 'message' => 'No formula provided' ] );
        }

        // Sanitize values
        $sanitized_values = [];
        foreach ( $values as $key => $value ) {
            $sanitized_values[ sanitize_key( $key ) ] = is_array( $value ) 
                ? array_map( 'sanitize_text_field', $value )
                : sanitize_text_field( $value );
        }

        $form = $form_id ? Form::get( $form_id ) : null;

        try {
            $result = $this->evaluate( $formula, $sanitized_values, $form );
            wp_send_json_success( [ 'result' => $result ] );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * Process calculation field value on submission.
     *
     * @param mixed  $value    Field value.
     * @param object $field    Field object.
     * @param array  $all_data All form data.
     * @return mixed Calculated value.
     */
    public function process_calculation_field( $value, $field, array $all_data ) {
        if ( $field->get_type() !== 'calculation' ) {
            return $value;
        }

        $formula = $field->get_property( 'formula' );
        if ( empty( $formula ) ) {
            return $value;
        }

        return $this->evaluate( $formula, $all_data, $field->get_form() );
    }

    /**
     * Get list of available functions for documentation.
     *
     * @return array Function documentation.
     */
    public static function get_function_documentation(): array {
        return [
            'Math Functions' => [
                'abs(x)'           => 'Absolute value',
                'ceil(x)'          => 'Round up to nearest integer',
                'floor(x)'         => 'Round down to nearest integer',
                'round(x, n)'      => 'Round to n decimal places',
                'sqrt(x)'          => 'Square root',
                'pow(x, n)'        => 'x raised to power n',
                'log(x)'           => 'Natural logarithm',
                'log10(x)'         => 'Base-10 logarithm',
                'exp(x)'           => 'e raised to power x',
            ],
            'Trigonometry' => [
                'sin(x)' => 'Sine (radians)',
                'cos(x)' => 'Cosine (radians)',
                'tan(x)' => 'Tangent (radians)',
            ],
            'Aggregates' => [
                'min(a, b, ...)'   => 'Minimum value',
                'max(a, b, ...)'   => 'Maximum value',
                'sum(a, b, ...)'   => 'Sum of values',
                'avg(a, b, ...)'   => 'Average of values',
                'count(a, b, ...)' => 'Count of values',
                'median(a, b, ...)' => 'Median value',
                'stdev(a, b, ...)' => 'Standard deviation',
            ],
            'Financial' => [
                'fv(rate, periods, pmt, pv)'   => 'Future Value',
                'pv(rate, periods, pmt, fv)'   => 'Present Value',
                'pmt(rate, periods, pv, fv)'   => 'Payment amount',
                'rate(periods, pmt, pv, fv)'   => 'Interest rate',
                'nper(rate, pmt, pv, fv)'      => 'Number of periods',
            ],
            'Conditional' => [
                'if(condition, true_val, false_val)' => 'Conditional logic',
            ],
        ];
    }
}
