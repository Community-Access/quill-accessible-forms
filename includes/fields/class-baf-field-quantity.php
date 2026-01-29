<?php
/**
 * Quantity Field - Numeric quantity selector for products
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Quantity class
 */
class BAF_Field_Quantity extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'quantity';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'pricing';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'label'              => __( 'Quantity', 'bits-accessible-forms' ),
            'description'        => '',
            'default_value'      => 1,
            'min'                => 1,
            'max'                => 100,
            'step'               => 1,
            'display_style'      => 'spinner',
            'show_controls'      => true,
            'control_style'      => 'buttons',
            'allow_decimal'      => false,
            'decimal_places'     => 0,
            'linked_product'     => '',
            'unit_label'         => '',
            'unit_position'      => 'after',
            'show_stock'         => false,
            'stock_count'        => 0,
            'low_stock_threshold' => 5,
            'required'           => false,
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
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
            ),
            'values' => array(
                'default_value' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Quantity', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'min' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum', 'bits-accessible-forms' ),
                    'min'   => 0,
                ),
                'max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum', 'bits-accessible-forms' ),
                    'min'   => 1,
                ),
                'step' => array(
                    'type'  => 'number',
                    'label' => __( 'Step', 'bits-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'spinner'  => __( 'Number Spinner', 'bits-accessible-forms' ),
                        'stepper'  => __( 'Stepper (+/-)', 'bits-accessible-forms' ),
                        'dropdown' => __( 'Dropdown Select', 'bits-accessible-forms' ),
                        'buttons'  => __( 'Quantity Buttons', 'bits-accessible-forms' ),
                        'slider'   => __( 'Range Slider', 'bits-accessible-forms' ),
                    ),
                ),
                'show_controls' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Show Increment/Decrement Controls', 'bits-accessible-forms' ),
                    'condition' => array( 'display_style' => array( 'spinner', 'stepper' ) ),
                ),
                'control_style' => array(
                    'type'      => 'select',
                    'label'     => __( 'Control Style', 'bits-accessible-forms' ),
                    'options'   => array(
                        'buttons' => __( '+ / - Buttons', 'bits-accessible-forms' ),
                        'arrows'  => __( 'Up/Down Arrows', 'bits-accessible-forms' ),
                        'icons'   => __( 'Icon Buttons', 'bits-accessible-forms' ),
                    ),
                    'condition' => array( 'show_controls' => true ),
                ),
            ),
            'decimal' => array(
                'allow_decimal' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Decimal Values', 'bits-accessible-forms' ),
                ),
                'decimal_places' => array(
                    'type'      => 'select',
                    'label'     => __( 'Decimal Places', 'bits-accessible-forms' ),
                    'options'   => array(
                        '1' => '1 (0.0)',
                        '2' => '2 (0.00)',
                        '3' => '3 (0.000)',
                    ),
                    'condition' => array( 'allow_decimal' => true ),
                ),
            ),
            'unit' => array(
                'unit_label' => array(
                    'type'        => 'text',
                    'label'       => __( 'Unit Label', 'bits-accessible-forms' ),
                    'placeholder' => __( 'e.g., kg, items, cases', 'bits-accessible-forms' ),
                ),
                'unit_position' => array(
                    'type'      => 'select',
                    'label'     => __( 'Unit Position', 'bits-accessible-forms' ),
                    'options'   => array(
                        'before' => __( 'Before value', 'bits-accessible-forms' ),
                        'after'  => __( 'After value', 'bits-accessible-forms' ),
                    ),
                    'condition' => array( 'unit_label' => '!empty' ),
                ),
            ),
            'stock' => array(
                'show_stock' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Stock Count', 'bits-accessible-forms' ),
                ),
                'stock_count' => array(
                    'type'      => 'number',
                    'label'     => __( 'Available Stock', 'bits-accessible-forms' ),
                    'min'       => 0,
                    'condition' => array( 'show_stock' => true ),
                ),
                'low_stock_threshold' => array(
                    'type'        => 'number',
                    'label'       => __( 'Low Stock Warning', 'bits-accessible-forms' ),
                    'description' => __( 'Show warning when stock falls below this', 'bits-accessible-forms' ),
                    'min'         => 0,
                    'condition'   => array( 'show_stock' => true ),
                ),
            ),
            'advanced' => array(
                'linked_product' => array(
                    'type'        => 'text',
                    'label'       => __( 'Linked Product Field', 'bits-accessible-forms' ),
                    'description' => __( 'Field ID of product field to link quantity to', 'bits-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Quantity', 'bits-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $display_style = $this->config['display_style'] ?? 'spinner';
        $value = $this->config['default_value'] ?? 1;
        $min = (int) ( $this->config['min'] ?? 1 );
        $max = (int) ( $this->config['max'] ?? 100 );
        $step = (int) ( $this->config['step'] ?? 1 );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'baf-field', 'baf-field-quantity', 'baf-quantity-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="baf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        // Stock info
        if ( ! empty( $this->config['show_stock'] ) && $this->config['stock_count'] > 0 ) {
            $stock = (int) $this->config['stock_count'];
            $threshold = (int) ( $this->config['low_stock_threshold'] ?? 5 );
            $stock_class = $stock <= $threshold ? 'baf-low-stock' : '';
            
            $html .= '<p class="baf-stock-info ' . esc_attr( $stock_class ) . '">';
            if ( $stock <= $threshold ) {
                /* translators: %d: stock count */
                $html .= esc_html( sprintf( __( 'Only %d left!', 'bits-accessible-forms' ), $stock ) );
            } else {
                /* translators: %d: stock count */
                $html .= esc_html( sprintf( __( '%d in stock', 'bits-accessible-forms' ), $stock ) );
            }
            $html .= '</p>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $input_class = 'baf-quantity-input';
        $name = 'baf_field_' . $this->get_id();

        switch ( $display_style ) {
            case 'dropdown':
                $html .= $this->render_dropdown( $html_id, $name, $value, $min, $max, $step, $aria_attrs );
                break;

            case 'buttons':
                $html .= $this->render_buttons( $html_id, $name, $value, $min, $max, $step, $aria_attrs );
                break;

            case 'slider':
                $html .= $this->render_slider( $html_id, $name, $value, $min, $max, $step, $aria_attrs );
                break;

            case 'stepper':
                $html .= $this->render_stepper( $html_id, $name, $value, $min, $max, $step, $aria_attrs );
                break;

            default: // spinner
                $html .= $this->render_spinner( $html_id, $name, $value, $min, $max, $step, $aria_attrs );
                break;
        }

        // Unit label
        if ( ! empty( $this->config['unit_label'] ) ) {
            $html .= '<span class="baf-quantity-unit">' . esc_html( $this->config['unit_label'] ) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render spinner input
     */
    protected function render_spinner( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<div class="baf-quantity-spinner-wrapper">';

        if ( ! empty( $this->config['show_controls'] ) ) {
            $html .= '<button type="button" class="baf-qty-decrease" aria-label="' . esc_attr__( 'Decrease quantity', 'bits-accessible-forms' ) . '">−</button>';
        }

        $html .= '<input type="number"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' step="' . esc_attr( $step ) . '"';
        $html .= ' class="baf-quantity-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        if ( ! empty( $this->config['show_controls'] ) ) {
            $html .= '<button type="button" class="baf-qty-increase" aria-label="' . esc_attr__( 'Increase quantity', 'bits-accessible-forms' ) . '">+</button>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render stepper input
     */
    protected function render_stepper( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<div class="baf-quantity-stepper">';
        $html .= '<button type="button" class="baf-qty-decrease baf-stepper-btn" aria-label="' . esc_attr__( 'Decrease', 'bits-accessible-forms' ) . '">';
        $html .= '<span aria-hidden="true">−</span>';
        $html .= '</button>';

        $html .= '<input type="number"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' step="' . esc_attr( $step ) . '"';
        $html .= ' class="baf-quantity-input baf-stepper-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        $html .= '<button type="button" class="baf-qty-increase baf-stepper-btn" aria-label="' . esc_attr__( 'Increase', 'bits-accessible-forms' ) . '">';
        $html .= '<span aria-hidden="true">+</span>';
        $html .= '</button>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render dropdown select
     */
    protected function render_dropdown( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<select';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' class="baf-quantity-select"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        for ( $i = $min; $i <= $max; $i += $step ) {
            $selected = ( $i == $value ) ? ' selected' : '';
            $html .= '<option value="' . esc_attr( $i ) . '"' . $selected . '>' . esc_html( $i ) . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Render button options
     */
    protected function render_buttons( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<div class="baf-quantity-buttons" role="radiogroup" aria-labelledby="' . esc_attr( $html_id ) . '-label">';
        
        for ( $i = $min; $i <= $max; $i += $step ) {
            $checked = ( $i == $value ) ? ' checked' : '';
            $btn_id = $html_id . '-' . $i;
            
            $html .= '<input type="radio" id="' . esc_attr( $btn_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $i ) . '"';
            $html .= ' class="baf-quantity-radio sr-only"';
            $html .= $checked . '>';
            
            $html .= '<label for="' . esc_attr( $btn_id ) . '" class="baf-quantity-btn">';
            $html .= esc_html( $i );
            $html .= '</label>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render slider
     */
    protected function render_slider( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<div class="baf-quantity-slider-wrapper">';
        
        $html .= '<input type="range"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' step="' . esc_attr( $step ) . '"';
        $html .= ' class="baf-quantity-slider"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        $html .= '<output for="' . esc_attr( $html_id ) . '" class="baf-quantity-output">' . esc_html( $value ) . '</output>';
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = $this->get_value();
        $min = (int) ( $this->config['min'] ?? 0 );
        $max = (int) ( $this->config['max'] ?? 100 );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter a quantity.', 'bits-accessible-forms' ) );
            return false;
        }

        if ( $value !== '' && $value !== null ) {
            $num = (float) $value;
            
            if ( $num < $min ) {
                /* translators: %d: minimum quantity */
                $this->add_error( sprintf( __( 'Quantity must be at least %d.', 'bits-accessible-forms' ), $min ) );
                return false;
            }
            
            if ( $num > $max ) {
                /* translators: %d: maximum quantity */
                $this->add_error( sprintf( __( 'Quantity cannot exceed %d.', 'bits-accessible-forms' ), $max ) );
                return false;
            }

            // Check stock
            if ( ! empty( $this->config['show_stock'] ) ) {
                $stock = (int) $this->config['stock_count'];
                if ( $num > $stock ) {
                    /* translators: %d: available stock */
                    $this->add_error( sprintf( __( 'Only %d available in stock.', 'bits-accessible-forms' ), $stock ) );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return int|float Sanitized value.
     */
    public function sanitize( $value ) {
        if ( ! empty( $this->config['allow_decimal'] ) ) {
            $decimals = (int) ( $this->config['decimal_places'] ?? 2 );
            return round( (float) $value, $decimals );
        }
        return (int) $value;
    }
}
