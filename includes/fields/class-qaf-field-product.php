<?php
/**
 * Product Field - E-commerce product selection
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Product class
 */
class QAF_Field_Product extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'product';

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
        return array_merge( parent::get_default_config(), array(
            'products'           => array(),
            'display_style'      => 'cards',
            'show_images'        => true,
            'show_descriptions'  => true,
            'show_prices'        => true,
            'allow_quantity'     => false,
            'min_quantity'       => 1,
            'max_quantity'       => 100,
            'currency'           => 'USD',
            'currency_position'  => 'before',
            'price_format'       => 'symbol',
            'multiple_selection' => false,
            'columns'            => 3,
            'featured_badge'     => __( 'Popular', 'quill-accessible-forms' ),
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
                'products' => array(
                    'type'   => 'product_builder',
                    'label'  => __( 'Products', 'quill-accessible-forms' ),
                    'fields' => array(
                        'name'        => array( 'type' => 'text', 'label' => __( 'Product Name', 'quill-accessible-forms' ) ),
                        'price'       => array( 'type' => 'number', 'label' => __( 'Price', 'quill-accessible-forms' ), 'step' => '0.01' ),
                        'description' => array( 'type' => 'textarea', 'label' => __( 'Description', 'quill-accessible-forms' ) ),
                        'image'       => array( 'type' => 'image', 'label' => __( 'Image', 'quill-accessible-forms' ) ),
                        'featured'    => array( 'type' => 'toggle', 'label' => __( 'Featured', 'quill-accessible-forms' ) ),
                        'sku'         => array( 'type' => 'text', 'label' => __( 'SKU', 'quill-accessible-forms' ) ),
                        'disabled'    => array( 'type' => 'toggle', 'label' => __( 'Sold Out', 'quill-accessible-forms' ) ),
                    ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'display' => array(
                'display_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Display Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'cards'    => __( 'Product Cards', 'quill-accessible-forms' ),
                        'dropdown' => __( 'Dropdown', 'quill-accessible-forms' ),
                        'radio'    => __( 'Radio Buttons', 'quill-accessible-forms' ),
                        'list'     => __( 'List View', 'quill-accessible-forms' ),
                        'table'    => __( 'Comparison Table', 'quill-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'      => 'select',
                    'label'     => __( 'Columns', 'quill-accessible-forms' ),
                    'condition' => array( 'display_style' => array( 'cards' ) ),
                    'options'   => array(
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                    ),
                ),
                'show_images' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Product Images', 'quill-accessible-forms' ),
                ),
                'show_descriptions' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Descriptions', 'quill-accessible-forms' ),
                ),
                'show_prices' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Prices', 'quill-accessible-forms' ),
                ),
                'featured_badge' => array(
                    'type'  => 'text',
                    'label' => __( 'Featured Badge Text', 'quill-accessible-forms' ),
                ),
            ),
            'pricing' => array(
                'currency' => array(
                    'type'    => 'select',
                    'label'   => __( 'Currency', 'quill-accessible-forms' ),
                    'options' => $this->get_currency_options(),
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
                'price_format' => array(
                    'type'    => 'select',
                    'label'   => __( 'Price Format', 'quill-accessible-forms' ),
                    'options' => array(
                        'symbol' => __( 'Symbol ($)', 'quill-accessible-forms' ),
                        'code'   => __( 'Code (USD)', 'quill-accessible-forms' ),
                        'name'   => __( 'Name (US Dollar)', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'quantity' => array(
                'allow_quantity' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Quantity Selection', 'quill-accessible-forms' ),
                ),
                'min_quantity' => array(
                    'type'      => 'number',
                    'label'     => __( 'Minimum Quantity', 'quill-accessible-forms' ),
                    'min'       => 1,
                    'condition' => array( 'allow_quantity' => true ),
                ),
                'max_quantity' => array(
                    'type'      => 'number',
                    'label'     => __( 'Maximum Quantity', 'quill-accessible-forms' ),
                    'min'       => 1,
                    'condition' => array( 'allow_quantity' => true ),
                ),
            ),
            'selection' => array(
                'multiple_selection' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Multiple Products', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Get currency options
     *
     * @return array Currency options.
     */
    protected function get_currency_options() {
        return array(
            'USD' => __( 'US Dollar ($)', 'quill-accessible-forms' ),
            'EUR' => __( 'Euro (€)', 'quill-accessible-forms' ),
            'GBP' => __( 'British Pound (£)', 'quill-accessible-forms' ),
            'CAD' => __( 'Canadian Dollar (C$)', 'quill-accessible-forms' ),
            'AUD' => __( 'Australian Dollar (A$)', 'quill-accessible-forms' ),
            'JPY' => __( 'Japanese Yen (¥)', 'quill-accessible-forms' ),
            'CHF' => __( 'Swiss Franc (CHF)', 'quill-accessible-forms' ),
            'INR' => __( 'Indian Rupee (₹)', 'quill-accessible-forms' ),
            'BRL' => __( 'Brazilian Real (R$)', 'quill-accessible-forms' ),
            'MXN' => __( 'Mexican Peso (MX$)', 'quill-accessible-forms' ),
        );
    }

    /**
     * Render the field HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id  = $this->get_html_id();
        $name     = 'qaf_field_' . $this->get_id();
        $products = $this->config['products'] ?? array();
        $style    = $this->config['display_style'] ?? 'cards';
        $value    = $this->get_value();

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'qaf-product-style-' . $style;

        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        $html .= '<legend class="qaf-fieldset-legend">';
        $html .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        switch ( $style ) {
            case 'cards':
                $html .= $this->render_cards( $products, $name, $value );
                break;
            case 'dropdown':
                $html .= $this->render_dropdown( $products, $name, $value );
                break;
            case 'radio':
                $html .= $this->render_radio( $products, $name, $value );
                break;
            case 'list':
                $html .= $this->render_list( $products, $name, $value );
                break;
            case 'table':
                $html .= $this->render_table( $products, $name, $value );
                break;
        }

        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Render cards layout
     *
     * @param array  $products Products.
     * @param string $name     Field name.
     * @param mixed  $value    Current value.
     * @return string HTML.
     */
    protected function render_cards( $products, $name, $value ) {
        $columns = $this->config['columns'] ?? 3;
        $is_multiple = ! empty( $this->config['multiple_selection'] );
        $input_type = $is_multiple ? 'checkbox' : 'radio';
        
        if ( $is_multiple ) {
            $name .= '[]';
            $value = (array) $value;
        }

        $html = '<div class="qaf-product-grid qaf-product-cols-' . esc_attr( $columns ) . '">';

        foreach ( $products as $index => $product ) {
            $product_id = $product['sku'] ?? $product['name'] ?? $index;
            $option_id = $this->get_html_id() . '-' . $index;
            $is_checked = $is_multiple 
                ? in_array( $product_id, $value, true ) 
                : $value === $product_id;
            $is_disabled = ! empty( $product['disabled'] );
            $is_featured = ! empty( $product['featured'] );

            $card_class = 'qaf-product-card';
            if ( $is_featured ) {
                $card_class .= ' qaf-product-featured';
            }
            if ( $is_disabled ) {
                $card_class .= ' qaf-product-disabled';
            }
            if ( $is_checked ) {
                $card_class .= ' qaf-product-selected';
            }

            $html .= '<div class="' . esc_attr( $card_class ) . '">';

            if ( $is_featured && ! empty( $this->config['featured_badge'] ) ) {
                $html .= '<span class="qaf-product-badge">' . esc_html( $this->config['featured_badge'] ) . '</span>';
            }

            if ( ! empty( $this->config['show_images'] ) && ! empty( $product['image'] ) ) {
                $html .= '<div class="qaf-product-image">';
                $html .= '<img src="' . esc_url( $product['image'] ) . '" alt="' . esc_attr( $product['name'] ) . '">';
                $html .= '</div>';
            }

            $html .= '<div class="qaf-product-content">';
            $html .= '<h4 class="qaf-product-name">' . esc_html( $product['name'] ) . '</h4>';

            if ( ! empty( $this->config['show_descriptions'] ) && ! empty( $product['description'] ) ) {
                $html .= '<p class="qaf-product-description">' . esc_html( $product['description'] ) . '</p>';
            }

            if ( ! empty( $this->config['show_prices'] ) && isset( $product['price'] ) ) {
                $html .= '<div class="qaf-product-price">' . $this->format_price( $product['price'] ) . '</div>';
            }

            $html .= '</div>';

            $html .= '<div class="qaf-product-selection">';
            $html .= '<input type="' . $input_type . '"';
            $html .= ' id="' . esc_attr( $option_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $product_id ) . '"';
            $html .= ' data-price="' . esc_attr( $product['price'] ?? 0 ) . '"';
            
            if ( $is_checked ) {
                $html .= ' checked';
            }
            if ( $is_disabled ) {
                $html .= ' disabled aria-disabled="true"';
            }
            
            $html .= '>';
            $html .= '<label for="' . esc_attr( $option_id ) . '">';
            $html .= $is_disabled ? esc_html__( 'Sold Out', 'quill-accessible-forms' ) : esc_html__( 'Select', 'quill-accessible-forms' );
            $html .= '</label>';

            if ( ! empty( $this->config['allow_quantity'] ) && ! $is_disabled ) {
                $html .= $this->render_quantity_input( $index );
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render dropdown
     *
     * @param array  $products Products.
     * @param string $name     Field name.
     * @param mixed  $value    Current value.
     * @return string HTML.
     */
    protected function render_dropdown( $products, $name, $value ) {
        $html = '<select id="' . esc_attr( $this->get_html_id() ) . '" name="' . esc_attr( $name ) . '" class="qaf-product-select">';
        $html .= '<option value="">' . esc_html__( 'Select a product...', 'quill-accessible-forms' ) . '</option>';

        foreach ( $products as $index => $product ) {
            $product_id = $product['sku'] ?? $product['name'] ?? $index;
            $is_selected = $value === $product_id;
            $is_disabled = ! empty( $product['disabled'] );
            $price_text = isset( $product['price'] ) ? ' - ' . $this->format_price( $product['price'] ) : '';

            $html .= '<option value="' . esc_attr( $product_id ) . '"';
            $html .= ' data-price="' . esc_attr( $product['price'] ?? 0 ) . '"';
            if ( $is_selected ) {
                $html .= ' selected';
            }
            if ( $is_disabled ) {
                $html .= ' disabled';
            }
            $html .= '>';
            $html .= esc_html( $product['name'] . $price_text );
            if ( $is_disabled ) {
                $html .= ' (' . esc_html__( 'Sold Out', 'quill-accessible-forms' ) . ')';
            }
            $html .= '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Render radio buttons
     *
     * @param array  $products Products.
     * @param string $name     Field name.
     * @param mixed  $value    Current value.
     * @return string HTML.
     */
    protected function render_radio( $products, $name, $value ) {
        $html = '<div class="qaf-product-radio-group">';

        foreach ( $products as $index => $product ) {
            $product_id = $product['sku'] ?? $product['name'] ?? $index;
            $option_id = $this->get_html_id() . '-' . $index;
            $is_selected = $value === $product_id;
            $is_disabled = ! empty( $product['disabled'] );

            $html .= '<div class="qaf-product-radio-option">';
            $html .= '<input type="radio"';
            $html .= ' id="' . esc_attr( $option_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $product_id ) . '"';
            $html .= ' data-price="' . esc_attr( $product['price'] ?? 0 ) . '"';
            
            if ( $is_selected ) {
                $html .= ' checked';
            }
            if ( $is_disabled ) {
                $html .= ' disabled';
            }
            
            $html .= '>';
            $html .= '<label for="' . esc_attr( $option_id ) . '">';
            $html .= '<span class="qaf-product-radio-name">' . esc_html( $product['name'] ) . '</span>';
            
            if ( ! empty( $this->config['show_prices'] ) && isset( $product['price'] ) ) {
                $html .= '<span class="qaf-product-radio-price">' . $this->format_price( $product['price'] ) . '</span>';
            }
            
            if ( $is_disabled ) {
                $html .= '<span class="qaf-product-sold-out">' . esc_html__( 'Sold Out', 'quill-accessible-forms' ) . '</span>';
            }
            
            $html .= '</label>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render list view
     *
     * @param array  $products Products.
     * @param string $name     Field name.
     * @param mixed  $value    Current value.
     * @return string HTML.
     */
    protected function render_list( $products, $name, $value ) {
        return $this->render_radio( $products, $name, $value );
    }

    /**
     * Render comparison table
     *
     * @param array  $products Products.
     * @param string $name     Field name.
     * @param mixed  $value    Current value.
     * @return string HTML.
     */
    protected function render_table( $products, $name, $value ) {
        $html = '<div class="qaf-product-table-wrapper">';
        $html .= '<table class="qaf-product-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . esc_html__( 'Select', 'quill-accessible-forms' ) . '</th>';
        $html .= '<th>' . esc_html__( 'Product', 'quill-accessible-forms' ) . '</th>';
        
        if ( ! empty( $this->config['show_descriptions'] ) ) {
            $html .= '<th>' . esc_html__( 'Description', 'quill-accessible-forms' ) . '</th>';
        }
        if ( ! empty( $this->config['show_prices'] ) ) {
            $html .= '<th>' . esc_html__( 'Price', 'quill-accessible-forms' ) . '</th>';
        }
        
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ( $products as $index => $product ) {
            $product_id = $product['sku'] ?? $product['name'] ?? $index;
            $option_id = $this->get_html_id() . '-' . $index;
            $is_selected = $value === $product_id;
            $is_disabled = ! empty( $product['disabled'] );

            $html .= '<tr class="' . ( $is_disabled ? 'qaf-row-disabled' : '' ) . '">';
            $html .= '<td>';
            $html .= '<input type="radio"';
            $html .= ' id="' . esc_attr( $option_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $product_id ) . '"';
            
            if ( $is_selected ) {
                $html .= ' checked';
            }
            if ( $is_disabled ) {
                $html .= ' disabled';
            }
            
            $html .= '>';
            $html .= '</td>';
            $html .= '<td><label for="' . esc_attr( $option_id ) . '">' . esc_html( $product['name'] ) . '</label></td>';
            
            if ( ! empty( $this->config['show_descriptions'] ) ) {
                $html .= '<td>' . esc_html( $product['description'] ?? '' ) . '</td>';
            }
            if ( ! empty( $this->config['show_prices'] ) ) {
                $html .= '<td>' . ( isset( $product['price'] ) ? $this->format_price( $product['price'] ) : '' ) . '</td>';
            }
            
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render quantity input
     *
     * @param int $index Product index.
     * @return string HTML.
     */
    protected function render_quantity_input( $index ) {
        $min = $this->config['min_quantity'] ?? 1;
        $max = $this->config['max_quantity'] ?? 100;
        $qty_id = $this->get_html_id() . '-qty-' . $index;
        $qty_name = 'qaf_field_' . $this->get_id() . '_qty[' . $index . ']';

        $html = '<div class="qaf-product-quantity">';
        $html .= '<label for="' . esc_attr( $qty_id ) . '" class="screen-reader-text">';
        $html .= esc_html__( 'Quantity', 'quill-accessible-forms' );
        $html .= '</label>';
        $html .= '<input type="number"';
        $html .= ' id="' . esc_attr( $qty_id ) . '"';
        $html .= ' name="' . esc_attr( $qty_name ) . '"';
        $html .= ' value="' . esc_attr( $min ) . '"';
        $html .= ' min="' . esc_attr( $min ) . '"';
        $html .= ' max="' . esc_attr( $max ) . '"';
        $html .= ' class="qaf-quantity-input">';
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
        $format = $this->config['price_format'] ?? 'symbol';

        $symbols = array(
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'C$',
            'AUD' => 'A$', 'JPY' => '¥', 'CHF' => 'CHF', 'INR' => '₹',
            'BRL' => 'R$', 'MXN' => 'MX$',
        );

        $symbol = $symbols[ $currency ] ?? $currency;
        
        if ( $format === 'code' ) {
            $symbol = $currency;
        }

        $formatted = number_format( (float) $price, 2 );

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
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        if ( is_array( $value ) ) {
            return array_map( 'sanitize_text_field', $value );
        }
        return sanitize_text_field( $value );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $products = $this->config['products'] ?? array();
        $valid_ids = array();

        foreach ( $products as $index => $product ) {
            if ( empty( $product['disabled'] ) ) {
                $valid_ids[] = $product['sku'] ?? $product['name'] ?? $index;
            }
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $v ) {
                if ( ! in_array( $v, $valid_ids, true ) ) {
                    $this->add_error( __( 'Please select a valid product.', 'quill-accessible-forms' ) );
                    return;
                }
            }
        } elseif ( ! in_array( $value, $valid_ids, true ) ) {
            $this->add_error( __( 'Please select a valid product.', 'quill-accessible-forms' ) );
        }
    }

    /**
     * Get product price by value
     *
     * @param mixed $value Product value.
     * @return float Price.
     */
    public function get_product_price( $value ) {
        $products = $this->config['products'] ?? array();

        foreach ( $products as $index => $product ) {
            $product_id = $product['sku'] ?? $product['name'] ?? $index;
            if ( $product_id === $value ) {
                return (float) ( $product['price'] ?? 0 );
            }
        }

        return 0;
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        $products = $this->config['products'] ?? array();
        $output = array();

        $values = is_array( $value ) ? $value : array( $value );

        foreach ( $products as $index => $product ) {
            $product_id = $product['sku'] ?? $product['name'] ?? $index;
            if ( in_array( $product_id, $values, true ) ) {
                $text = $product['name'];
                if ( isset( $product['price'] ) ) {
                    $text .= ' - ' . $this->format_price( $product['price'] );
                }
                $output[] = $text;
            }
        }

        return implode( ', ', $output );
    }
}
