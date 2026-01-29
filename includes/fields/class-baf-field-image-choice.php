<?php
/**
 * Image Choice Field
 *
 * Visual radio/checkbox selection using images or icons.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Image_Choice class
 */
class BAF_Field_Image_Choice extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'image_choice';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'choice';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'choices'         => array(),
            'multiple'        => false,
            'layout'          => 'grid',        // 'grid', 'list', 'inline'
            'columns'         => 3,             // For grid layout
            'image_size'      => 'medium',      // 'small', 'medium', 'large', 'custom'
            'custom_width'    => 150,
            'custom_height'   => 150,
            'show_labels'     => true,
            'show_radio_check' => true,         // Show radio/checkbox indicator
            'hover_effect'    => 'scale',       // 'none', 'scale', 'shadow', 'border'
            'selected_style'  => 'border',      // 'border', 'overlay', 'checkmark'
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
                'choices' => array(
                    'type'   => 'image_choices',
                    'label'  => __( 'Choices', 'bits-accessible-forms' ),
                    'fields' => array(
                        'value' => __( 'Value', 'bits-accessible-forms' ),
                        'label' => __( 'Label', 'bits-accessible-forms' ),
                        'image' => __( 'Image URL', 'bits-accessible-forms' ),
                        'icon'  => __( 'Icon Class', 'bits-accessible-forms' ),
                    ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'bits-accessible-forms' ),
                ),
                'multiple' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Multiple Selections', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'bits-accessible-forms' ),
                    'options' => array(
                        'grid'   => __( 'Grid', 'bits-accessible-forms' ),
                        'list'   => __( 'List', 'bits-accessible-forms' ),
                        'inline' => __( 'Inline', 'bits-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'  => 'number',
                    'label' => __( 'Columns (Grid Layout)', 'bits-accessible-forms' ),
                    'min'   => 2,
                    'max'   => 6,
                ),
                'image_size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Image Size', 'bits-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small (80px)', 'bits-accessible-forms' ),
                        'medium' => __( 'Medium (120px)', 'bits-accessible-forms' ),
                        'large'  => __( 'Large (180px)', 'bits-accessible-forms' ),
                        'custom' => __( 'Custom', 'bits-accessible-forms' ),
                    ),
                ),
                'custom_width' => array(
                    'type'  => 'number',
                    'label' => __( 'Custom Width (px)', 'bits-accessible-forms' ),
                    'min'   => 50,
                    'max'   => 500,
                ),
                'custom_height' => array(
                    'type'  => 'number',
                    'label' => __( 'Custom Height (px)', 'bits-accessible-forms' ),
                    'min'   => 50,
                    'max'   => 500,
                ),
                'show_labels' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Labels', 'bits-accessible-forms' ),
                ),
                'show_radio_check' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Selection Indicator', 'bits-accessible-forms' ),
                ),
                'hover_effect' => array(
                    'type'    => 'select',
                    'label'   => __( 'Hover Effect', 'bits-accessible-forms' ),
                    'options' => array(
                        'none'   => __( 'None', 'bits-accessible-forms' ),
                        'scale'  => __( 'Scale Up', 'bits-accessible-forms' ),
                        'shadow' => __( 'Shadow', 'bits-accessible-forms' ),
                        'border' => __( 'Border Highlight', 'bits-accessible-forms' ),
                    ),
                ),
                'selected_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Selected Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'border'    => __( 'Border', 'bits-accessible-forms' ),
                        'overlay'   => __( 'Color Overlay', 'bits-accessible-forms' ),
                        'checkmark' => __( 'Checkmark', 'bits-accessible-forms' ),
                    ),
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
        $html_id   = $this->get_html_id();
        $name      = 'baf_field_' . $this->get_id();
        $value     = $this->get_value();
        $choices   = $this->config['choices'] ?? array();
        $multiple  = ! empty( $this->config['multiple'] );
        $layout    = $this->config['layout'] ?? 'grid';
        $columns   = (int) ( $this->config['columns'] ?? 3 );
        $show_labels = $this->config['show_labels'] ?? true;
        $show_indicator = $this->config['show_radio_check'] ?? true;
        $image_size = $this->config['image_size'] ?? 'medium';
        $hover = $this->config['hover_effect'] ?? 'scale';
        $selected_style = $this->config['selected_style'] ?? 'border';

        // Ensure value is array for multiple
        if ( $multiple && ! is_array( $value ) ) {
            $value = $value ? array( $value ) : array();
        }

        $input_type = $multiple ? 'checkbox' : 'radio';
        $input_name = $multiple ? $name . '[]' : $name;

        // Get image dimensions
        $dimensions = $this->get_image_dimensions( $image_size );

        // Build wrapper classes
        $wrapper_classes = array(
            'baf-image-choice-wrapper',
            'baf-layout-' . $layout,
            'baf-hover-' . $hover,
            'baf-selected-' . $selected_style,
            'baf-size-' . $image_size,
        );

        // Use fieldset/legend for grouped choices
        $field = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';
        $field .= ' data-columns="' . esc_attr( $columns ) . '"';
        $field .= ' style="--baf-img-width: ' . esc_attr( $dimensions['width'] ) . 'px; --baf-img-height: ' . esc_attr( $dimensions['height'] ) . 'px; --baf-columns: ' . esc_attr( $columns ) . ';"';
        $field .= '>';

        $field .= '<legend class="baf-fieldset-legend">';
        $field .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $field .= $this->render_required_indicator();
        $field .= '</legend>';

        // Description
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Choices container
        $field .= '<div class="baf-image-choice-grid" role="group">';

        foreach ( $choices as $index => $choice ) {
            $choice_value = $choice['value'] ?? $choice['label'] ?? '';
            $choice_label = $choice['label'] ?? $choice_value;
            $choice_image = $choice['image'] ?? '';
            $choice_icon  = $choice['icon'] ?? '';
            $choice_id    = $html_id . '-' . $index;

            $is_selected = $multiple 
                ? in_array( $choice_value, $value, true )
                : $value === $choice_value;

            $item_classes = array( 'baf-image-choice-item' );
            if ( $is_selected ) {
                $item_classes[] = 'is-selected';
            }

            $field .= '<label class="' . esc_attr( implode( ' ', $item_classes ) ) . '" for="' . esc_attr( $choice_id ) . '">';

            // Hidden input
            $field .= '<input type="' . esc_attr( $input_type ) . '"';
            $field .= ' id="' . esc_attr( $choice_id ) . '"';
            $field .= ' name="' . esc_attr( $input_name ) . '"';
            $field .= ' value="' . esc_attr( $choice_value ) . '"';
            $field .= ' class="baf-image-choice-input screen-reader-text"';
            if ( $is_selected ) {
                $field .= ' checked';
            }
            $field .= '>';

            // Visual card
            $field .= '<span class="baf-image-choice-card">';

            // Selection indicator
            if ( $show_indicator ) {
                $field .= '<span class="baf-choice-indicator" aria-hidden="true">';
                $field .= '<span class="baf-indicator-icon"></span>';
                $field .= '</span>';
            }

            // Image or icon
            $field .= '<span class="baf-choice-visual">';
            if ( ! empty( $choice_image ) ) {
                $field .= '<img src="' . esc_url( $choice_image ) . '" alt="" class="baf-choice-image" loading="lazy">';
            } elseif ( ! empty( $choice_icon ) ) {
                $field .= '<span class="baf-choice-icon ' . esc_attr( $choice_icon ) . '" aria-hidden="true"></span>';
            } else {
                // Placeholder
                $field .= '<span class="baf-choice-placeholder" aria-hidden="true"></span>';
            }
            $field .= '</span>';

            // Label text
            if ( $show_labels ) {
                $field .= '<span class="baf-choice-label">' . esc_html( $choice_label ) . '</span>';
            }

            $field .= '</span>';
            $field .= '</label>';
        }

        $field .= '</div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $field .= $this->render_description();
        }

        // Error container
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $field .= $this->render_errors();
        }
        $field .= '</div>';

        $field .= '</fieldset>';

        return $field;
    }

    /**
     * Get image dimensions based on size setting
     *
     * @param string $size Size name.
     * @return array Width and height.
     */
    protected function get_image_dimensions( $size ) {
        switch ( $size ) {
            case 'small':
                return array( 'width' => 80, 'height' => 80 );
            case 'medium':
                return array( 'width' => 120, 'height' => 120 );
            case 'large':
                return array( 'width' => 180, 'height' => 180 );
            case 'custom':
                return array(
                    'width'  => (int) ( $this->config['custom_width'] ?? 150 ),
                    'height' => (int) ( $this->config['custom_height'] ?? 150 ),
                );
            default:
                return array( 'width' => 120, 'height' => 120 );
        }
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return mixed Sanitized value.
     */
    public function sanitize( $value ) {
        $multiple = ! empty( $this->config['multiple'] );

        if ( $multiple ) {
            if ( ! is_array( $value ) ) {
                return array();
            }
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
        $choices = $this->config['choices'] ?? array();
        $valid_values = array_map( function( $c ) {
            return $c['value'] ?? $c['label'] ?? '';
        }, $choices );

        $multiple = ! empty( $this->config['multiple'] );

        if ( $multiple ) {
            if ( ! is_array( $value ) ) {
                $value = array();
            }
            foreach ( $value as $v ) {
                if ( ! in_array( $v, $valid_values, true ) ) {
                    $this->add_error( __( 'Invalid selection.', 'bits-accessible-forms' ) );
                    return;
                }
            }
        } else {
            if ( ! empty( $value ) && ! in_array( $value, $valid_values, true ) ) {
                $this->add_error( __( 'Invalid selection.', 'bits-accessible-forms' ) );
            }
        }
    }

    /**
     * Get display value
     *
     * @param mixed $value Field value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        $choices = $this->config['choices'] ?? array();
        $labels = array();

        $values = is_array( $value ) ? $value : array( $value );

        foreach ( $choices as $choice ) {
            $choice_value = $choice['value'] ?? $choice['label'] ?? '';
            if ( in_array( $choice_value, $values, true ) ) {
                $labels[] = $choice['label'] ?? $choice_value;
            }
        }

        return implode( ', ', $labels );
    }
}
