<?php
/**
 * Star Rating Field
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Rating class
 */
class QAF_Field_Rating extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'rating';

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
            'max_rating'     => 5,
            'icon_type'      => 'star',
            'show_labels'    => true,
            'low_label'      => __( 'Poor', 'quill-accessible-forms' ),
            'high_label'     => __( 'Excellent', 'quill-accessible-forms' ),
            'allow_half'     => false,
            'allow_clear'    => true,
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
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'scale' => array(
                'max_rating' => array(
                    'type'    => 'select',
                    'label'   => __( 'Maximum Rating', 'quill-accessible-forms' ),
                    'options' => array(
                        '3'  => '3',
                        '5'  => '5',
                        '7'  => '7',
                        '10' => '10',
                    ),
                ),
                'allow_half' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Half Values', 'quill-accessible-forms' ),
                ),
                'allow_clear' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow Clear Selection', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'icon_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Icon Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'star'   => __( 'Stars', 'quill-accessible-forms' ),
                        'heart'  => __( 'Hearts', 'quill-accessible-forms' ),
                        'circle' => __( 'Circles', 'quill-accessible-forms' ),
                        'thumb'  => __( 'Thumbs Up', 'quill-accessible-forms' ),
                    ),
                ),
                'show_labels' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Low/High Labels', 'quill-accessible-forms' ),
                ),
                'low_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Low Label', 'quill-accessible-forms' ),
                ),
                'high_label' => array(
                    'type'  => 'text',
                    'label' => __( 'High Label', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the field HTML with accessible radio group
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id   = $this->get_html_id();
        $name      = 'qaf_field_' . $this->get_id();
        $value     = $this->get_value();
        $max       = (int) $this->config['max_rating'];
        $icon_type = $this->config['icon_type'];
        $allow_half = ! empty( $this->config['allow_half'] );

        $wrapper_class = $this->get_wrapper_classes();
        
        // Use fieldset/legend for radio group
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-max-rating="' . esc_attr( $max ) . '"';
        $html .= ' data-icon-type="' . esc_attr( $icon_type ) . '"';
        $html .= ' data-allow-half="' . ( $allow_half ? 'true' : 'false' ) . '">';

        // Legend
        $html .= '<legend class="qaf-fieldset-legend">';
        $html .= '<span class="qaf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Rating container
        $html .= '<div class="qaf-rating-container">';

        // Low label
        if ( ! empty( $this->config['show_labels'] ) && ! empty( $this->config['low_label'] ) ) {
            $html .= '<span class="qaf-rating-label qaf-rating-low">' . esc_html( $this->config['low_label'] ) . '</span>';
        }

        // Rating group with radio buttons
        $html .= '<div class="qaf-rating-group" role="radiogroup" aria-label="' . esc_attr( $this->get_label() ) . '">';

        // Generate rating options
        $step = $allow_half ? 0.5 : 1;
        for ( $i = $step; $i <= $max; $i += $step ) {
            $option_id = $html_id . '-' . str_replace( '.', '-', $i );
            $is_checked = ( (float) $value === (float) $i );
            $is_filled = ( (float) $value >= (float) $i );
            $is_half = ( $allow_half && ( $i - floor( $i ) === 0.5 ) );

            $html .= '<label for="' . esc_attr( $option_id ) . '" class="qaf-rating-item' . ( $is_filled ? ' qaf-filled' : '' ) . ( $is_half ? ' qaf-half' : '' ) . '">';
            
            $html .= '<input type="radio"';
            $html .= ' id="' . esc_attr( $option_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $i ) . '"';
            $html .= ' class="qaf-rating-input"';
            
            if ( $is_checked ) {
                $html .= ' checked';
            }
            
            if ( $this->is_required() ) {
                $html .= ' aria-required="true"';
            }
            
            $html .= '>';

            // Visual icon
            $html .= '<span class="qaf-rating-icon qaf-icon-' . esc_attr( $icon_type ) . '" aria-hidden="true"></span>';
            
            // Screen reader text
            $html .= '<span class="screen-reader-text">';
            if ( $allow_half && $is_half ) {
                $html .= sprintf(
                    __( '%s out of %s', 'quill-accessible-forms' ),
                    $i,
                    $max
                );
            } else {
                $html .= sprintf(
                    __( '%d out of %d', 'quill-accessible-forms' ),
                    (int) $i,
                    $max
                );
            }
            $html .= '</span>';
            
            $html .= '</label>';
        }

        $html .= '</div>';

        // High label
        if ( ! empty( $this->config['show_labels'] ) && ! empty( $this->config['high_label'] ) ) {
            $html .= '<span class="qaf-rating-label qaf-rating-high">' . esc_html( $this->config['high_label'] ) . '</span>';
        }

        $html .= '</div>';

        // Clear button
        if ( ! empty( $this->config['allow_clear'] ) ) {
            $html .= '<button type="button" class="qaf-rating-clear" aria-label="' . esc_attr__( 'Clear rating', 'quill-accessible-forms' ) . '"';
            if ( empty( $value ) ) {
                $html .= ' style="display: none;"';
            }
            $html .= '>';
            $html .= esc_html__( 'Clear', 'quill-accessible-forms' );
            $html .= '</button>';
        }

        // Current value announcement
        $html .= '<div class="qaf-rating-status" role="status" aria-live="polite" aria-atomic="true">';
        if ( $value ) {
            $html .= sprintf(
                esc_html__( 'Current rating: %s out of %s', 'quill-accessible-forms' ),
                $value,
                $max
            );
        }
        $html .= '</div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="qaf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
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

        $value = (float) $value;
        $max = (int) $this->config['max_rating'];
        $allow_half = ! empty( $this->config['allow_half'] );

        // Clamp to valid range
        if ( $value < 0 ) {
            return '';
        }
        if ( $value > $max ) {
            $value = $max;
        }

        // Round to valid step
        if ( $allow_half ) {
            $value = round( $value * 2 ) / 2;
        } else {
            $value = round( $value );
        }

        return $value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $max = (int) $this->config['max_rating'];

        if ( $value < 0 || $value > $max ) {
            $this->add_error( sprintf(
                __( 'Please select a rating between 1 and %d.', 'quill-accessible-forms' ),
                $max
            ) );
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $max = (int) $this->config['max_rating'];
        
        return sprintf(
            __( '%s / %s', 'quill-accessible-forms' ),
            $value,
            $max
        );
    }
}
