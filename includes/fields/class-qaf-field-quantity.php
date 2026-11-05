<?php
/**
 * Quantity Field - Numeric quantity selector for products
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Quantity class
 */
class QAF_Field_Quantity extends QAF_Field {

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
            'label'              => __( 'Quantity', 'quill-accessible-forms' ),
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
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'values' => array(
                'default_value' => array(
                    'type'  => 'number',
                    'label' => __( 'Default Quantity', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'min' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
                'step' => array(
                    'type'  => 'number',
                    'label' => __( 'Step', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'spinner'  => __( 'Number Spinner', 'quill-accessible-forms' ),
                        'stepper'  => __( 'Stepper (+/-)', 'quill-accessible-forms' ),
                        'dropdown' => __( 'Dropdown Select', 'quill-accessible-forms' ),
                        'buttons'  => __( 'Quantity Buttons', 'quill-accessible-forms' ),
                        'slider'   => __( 'Range Slider', 'quill-accessible-forms' ),
                    ),
                ),
                'show_controls' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Show Increment/Decrement Controls', 'quill-accessible-forms' ),
                    'condition' => array( 'display_style' => array( 'spinner', 'stepper' ) ),
                ),
                'control_style' => array(
                    'type'      => 'select',
                    'label'     => __( 'Control Style', 'quill-accessible-forms' ),
                    'options'   => array(
                        'buttons' => __( '+ / - Buttons', 'quill-accessible-forms' ),
                        'arrows'  => __( 'Up/Down Arrows', 'quill-accessible-forms' ),
                        'icons'   => __( 'Icon Buttons', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'show_controls' => true ),
                ),
            ),
            'decimal' => array(
                'allow_decimal' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Decimal Values', 'quill-accessible-forms' ),
                ),
                'decimal_places' => array(
                    'type'      => 'select',
                    'label'     => __( 'Decimal Places', 'quill-accessible-forms' ),
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
                    'label'       => __( 'Unit Label', 'quill-accessible-forms' ),
                    'placeholder' => __( 'e.g., kg, items, cases', 'quill-accessible-forms' ),
                ),
                'unit_position' => array(
                    'type'      => 'select',
                    'label'     => __( 'Unit Position', 'quill-accessible-forms' ),
                    'options'   => array(
                        'before' => __( 'Before value', 'quill-accessible-forms' ),
                        'after'  => __( 'After value', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'unit_label' => '!empty' ),
                ),
            ),
            'stock' => array(
                'show_stock' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Stock Count', 'quill-accessible-forms' ),
                ),
                'stock_count' => array(
                    'type'      => 'number',
                    'label'     => __( 'Available Stock', 'quill-accessible-forms' ),
                    'min'       => 0,
                    'condition' => array( 'show_stock' => true ),
                ),
                'low_stock_threshold' => array(
                    'type'        => 'number',
                    'label'       => __( 'Low Stock Warning', 'quill-accessible-forms' ),
                    'description' => __( 'Show warning when stock falls below this', 'quill-accessible-forms' ),
                    'min'         => 0,
                    'condition'   => array( 'show_stock' => true ),
                ),
            ),
            'advanced' => array(
                'linked_product' => array(
                    'type'        => 'text',
                    'label'       => __( 'Linked Product Field', 'quill-accessible-forms' ),
                    'description' => __( 'Field ID of product field to link quantity to', 'quill-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Quantity', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $display_style = $this->config['display_style'] ?? 'spinner';
        $value = $this->config['default_value'] ?? 1;
        $min = (int) ( $this->config['min'] ?? 1 );
        $max = (int) ( $this->config['max'] ?? 100 );
        $step = (int) ( $this->config['step'] ?? 1 );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-quantity', 'qaf-quantity-' . $display_style );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="qaf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        // Stock info
        if ( ! empty( $this->config['show_stock'] ) && $this->config['stock_count'] > 0 ) {
            $stock = (int) $this->config['stock_count'];
            $threshold = (int) ( $this->config['low_stock_threshold'] ?? 5 );
            $stock_class = $stock <= $threshold ? 'qaf-low-stock' : '';
            
            $html .= '<p class="qaf-stock-info ' . esc_attr( $stock_class ) . '">';
            if ( $stock <= $threshold ) {
                /* translators: %d: stock count */
                $html .= esc_html( sprintf( __( 'Only %d left!', 'quill-accessible-forms' ), $stock ) );
            } else {
                /* translators: %d: stock count */
                $html .= esc_html( sprintf( __( '%d in stock', 'quill-accessible-forms' ), $stock ) );
            }
            $html .= '</p>';
        }

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $input_class = 'qaf-quantity-input';
        $name = 'qaf_field_' . $this->get_id();

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
            $html .= '<span class="qaf-quantity-unit">' . esc_html( $this->config['unit_label'] ) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render spinner input
     */
    protected function render_spinner( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<div class="qaf-quantity-spinner-wrapper">';

        if ( ! empty( $this->config['show_controls'] ) ) {
            $html .= '<button type="button" class="qaf-qty-decrease" aria-label="' . esc_attr__( 'Decrease quantity', 'quill-accessible-forms' ) . '">−</button>';
        }

        $html .= '<input type="number"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' step="' . esc_attr( $step ) . '"';
        $html .= ' class="qaf-quantity-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        if ( ! empty( $this->config['show_controls'] ) ) {
            $html .= '<button type="button" class="qaf-qty-increase" aria-label="' . esc_attr__( 'Increase quantity', 'quill-accessible-forms' ) . '">+</button>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render stepper input
     */
    protected function render_stepper( $html_id, $name, $value, $min, $max, $step, $aria_attrs ) {
        $html = '<div class="qaf-quantity-stepper">';
        $html .= '<button type="button" class="qaf-qty-decrease qaf-stepper-btn" aria-label="' . esc_attr__( 'Decrease', 'quill-accessible-forms' ) . '">';
        $html .= '<span aria-hidden="true">−</span>';
        $html .= '</button>';

        $html .= '<input type="number"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' step="' . esc_attr( $step ) . '"';
        $html .= ' class="qaf-quantity-input qaf-stepper-input"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        $html .= '<button type="button" class="qaf-qty-increase qaf-stepper-btn" aria-label="' . esc_attr__( 'Increase', 'quill-accessible-forms' ) . '">';
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
        $html .= ' class="qaf-quantity-select"';
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
        $html = '<div class="qaf-quantity-buttons" role="radiogroup" aria-labelledby="' . esc_attr( $html_id ) . '-label">';
        
        for ( $i = $min; $i <= $max; $i += $step ) {
            $checked = ( $i == $value ) ? ' checked' : '';
            $btn_id = $html_id . '-' . $i;
            
            $html .= '<input type="radio" id="' . esc_attr( $btn_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $i ) . '"';
            $html .= ' class="qaf-quantity-radio sr-only"';
            $html .= $checked . '>';
            
            $html .= '<label for="' . esc_attr( $btn_id ) . '" class="qaf-quantity-btn">';
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
        $html = '<div class="qaf-quantity-slider-wrapper">';
        
        $html .= '<input type="range"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="' . esc_attr( $name ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' step="' . esc_attr( $step ) . '"';
        $html .= ' class="qaf-quantity-slider"';
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        $html .= '<output for="' . esc_attr( $html_id ) . '" class="qaf-quantity-output">' . esc_html( $value ) . '</output>';
        
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
            $this->add_error( __( 'Please enter a quantity.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( $value !== '' && $value !== null ) {
            $num = (float) $value;
            
            if ( $num < $min ) {
                /* translators: %d: minimum quantity */
                $this->add_error( sprintf( __( 'Quantity must be at least %d.', 'quill-accessible-forms' ), $min ) );
                return false;
            }
            
            if ( $num > $max ) {
                /* translators: %d: maximum quantity */
                $this->add_error( sprintf( __( 'Quantity cannot exceed %d.', 'quill-accessible-forms' ), $max ) );
                return false;
            }

            // Check stock
            if ( ! empty( $this->config['show_stock'] ) ) {
                $stock = (int) $this->config['stock_count'];
                if ( $num > $stock ) {
                    /* translators: %d: available stock */
                    $this->add_error( sprintf( __( 'Only %d available in stock.', 'quill-accessible-forms' ), $stock ) );
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
