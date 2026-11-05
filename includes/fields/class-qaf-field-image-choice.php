<?php
/**
 * Image Choice Field
 *
 * Visual radio/checkbox selection using images or icons.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Image_Choice class
 */
class QAF_Field_Image_Choice extends QAF_Field {

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
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'choices' => array(
                    'type'   => 'image_choices',
                    'label'  => __( 'Choices', 'quill-accessible-forms' ),
                    'fields' => array(
                        'value' => __( 'Value', 'quill-accessible-forms' ),
                        'label' => __( 'Label', 'quill-accessible-forms' ),
                        'image' => __( 'Image URL', 'quill-accessible-forms' ),
                        'icon'  => __( 'Icon Class', 'quill-accessible-forms' ),
                    ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
                'multiple' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Multiple Selections', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'layout' => array(
                    'type'    => 'select',
                    'label'   => __( 'Layout', 'quill-accessible-forms' ),
                    'options' => array(
                        'grid'   => __( 'Grid', 'quill-accessible-forms' ),
                        'list'   => __( 'List', 'quill-accessible-forms' ),
                        'inline' => __( 'Inline', 'quill-accessible-forms' ),
                    ),
                ),
                'columns' => array(
                    'type'  => 'number',
                    'label' => __( 'Columns (Grid Layout)', 'quill-accessible-forms' ),
                    'min'   => 2,
                    'max'   => 6,
                ),
                'image_size' => array(
                    'type'    => 'select',
                    'label'   => __( 'Image Size', 'quill-accessible-forms' ),
                    'options' => array(
                        'small'  => __( 'Small (80px)', 'quill-accessible-forms' ),
                        'medium' => __( 'Medium (120px)', 'quill-accessible-forms' ),
                        'large'  => __( 'Large (180px)', 'quill-accessible-forms' ),
                        'custom' => __( 'Custom', 'quill-accessible-forms' ),
                    ),
                ),
                'custom_width' => array(
                    'type'  => 'number',
                    'label' => __( 'Custom Width (px)', 'quill-accessible-forms' ),
                    'min'   => 50,
                    'max'   => 500,
                ),
                'custom_height' => array(
                    'type'  => 'number',
                    'label' => __( 'Custom Height (px)', 'quill-accessible-forms' ),
                    'min'   => 50,
                    'max'   => 500,
                ),
                'show_labels' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Labels', 'quill-accessible-forms' ),
                ),
                'show_radio_check' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Selection Indicator', 'quill-accessible-forms' ),
                ),
                'hover_effect' => array(
                    'type'    => 'select',
                    'label'   => __( 'Hover Effect', 'quill-accessible-forms' ),
                    'options' => array(
                        'none'   => __( 'None', 'quill-accessible-forms' ),
                        'scale'  => __( 'Scale Up', 'quill-accessible-forms' ),
                        'shadow' => __( 'Shadow', 'quill-accessible-forms' ),
                        'border' => __( 'Border Highlight', 'quill-accessible-forms' ),
                    ),
                ),
                'selected_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Selected Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'border'    => __( 'Border', 'quill-accessible-forms' ),
                        'overlay'   => __( 'Color Overlay', 'quill-accessible-forms' ),
                        'checkmark' => __( 'Checkmark', 'quill-accessible-forms' ),
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
        $name      = 'qaf_field_' . $this->get_id();
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
            'qaf-image-choice-wrapper',
            'qaf-layout-' . $layout,
            'qaf-hover-' . $hover,
            'qaf-selected-' . $selected_style,
            'qaf-size-' . $image_size,
        );

        // Use fieldset/legend for grouped choices
        $field = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"';
        $field .= ' data-columns="' . esc_attr( $columns ) . '"';
        $field .= ' style="--qaf-img-width: ' . esc_attr( $dimensions['width'] ) . 'px; --qaf-img-height: ' . esc_attr( $dimensions['height'] ) . 'px; --qaf-columns: ' . esc_attr( $columns ) . ';"';
        $field .= '>';

        $field .= '<legend class="qaf-fieldset-legend">';
        $field .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $field .= $this->render_required_indicator();
        $field .= '</legend>';

        // Description
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Choices container
        $field .= '<div class="qaf-image-choice-grid" role="group">';

        foreach ( $choices as $index => $choice ) {
            $choice_value = $choice['value'] ?? $choice['label'] ?? '';
            $choice_label = $choice['label'] ?? $choice_value;
            $choice_image = $choice['image'] ?? '';
            $choice_icon  = $choice['icon'] ?? '';
            $choice_id    = $html_id . '-' . $index;

            $is_selected = $multiple 
                ? in_array( $choice_value, $value, true )
                : $value === $choice_value;

            $item_classes = array( 'qaf-image-choice-item' );
            if ( $is_selected ) {
                $item_classes[] = 'is-selected';
            }

            $field .= '<label class="' . esc_attr( implode( ' ', $item_classes ) ) . '" for="' . esc_attr( $choice_id ) . '">';

            // Hidden input
            $field .= '<input type="' . esc_attr( $input_type ) . '"';
            $field .= ' id="' . esc_attr( $choice_id ) . '"';
            $field .= ' name="' . esc_attr( $input_name ) . '"';
            $field .= ' value="' . esc_attr( $choice_value ) . '"';
            $field .= ' class="qaf-image-choice-input screen-reader-text"';
            if ( $is_selected ) {
                $field .= ' checked';
            }
            $field .= '>';

            // Visual card
            $field .= '<span class="qaf-image-choice-card">';

            // Selection indicator
            if ( $show_indicator ) {
                $field .= '<span class="qaf-choice-indicator" aria-hidden="true">';
                $field .= '<span class="qaf-indicator-icon"></span>';
                $field .= '</span>';
            }

            // Image or icon
            $field .= '<span class="qaf-choice-visual">';
            if ( ! empty( $choice_image ) ) {
                $field .= '<img src="' . esc_url( $choice_image ) . '" alt="" class="qaf-choice-image" loading="lazy">';
            } elseif ( ! empty( $choice_icon ) ) {
                $field .= '<span class="qaf-choice-icon ' . esc_attr( $choice_icon ) . '" aria-hidden="true"></span>';
            } else {
                // Placeholder
                $field .= '<span class="qaf-choice-placeholder" aria-hidden="true"></span>';
            }
            $field .= '</span>';

            // Label text
            if ( $show_labels ) {
                $field .= '<span class="qaf-choice-label">' . esc_html( $choice_label ) . '</span>';
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
        $field .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
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
                    $this->add_error( __( 'Invalid selection.', 'quill-accessible-forms' ) );
                    return;
                }
            }
        } else {
            if ( ! empty( $value ) && ! in_array( $value, $valid_values, true ) ) {
                $this->add_error( __( 'Invalid selection.', 'quill-accessible-forms' ) );
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
