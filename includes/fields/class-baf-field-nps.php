<?php
/**
 * NPS (Net Promoter Score) Field
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Nps class
 */
class BAF_Field_Nps extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'nps';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'survey';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'low_label'        => __( 'Not at all likely', 'bits-accessible-forms' ),
            'high_label'       => __( 'Extremely likely', 'bits-accessible-forms' ),
            'show_scale_labels' => true,
            'followup_enabled' => false,
            'followup_detractor' => __( 'What could we do better?', 'bits-accessible-forms' ),
            'followup_passive'   => __( 'How can we improve?', 'bits-accessible-forms' ),
            'followup_promoter'  => __( 'What did you like most?', 'bits-accessible-forms' ),
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
                    'type'    => 'text',
                    'label'   => __( 'Question', 'bits-accessible-forms' ),
                    'default' => __( 'How likely are you to recommend us to a friend or colleague?', 'bits-accessible-forms' ),
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
            'scale' => array(
                'low_label' => array(
                    'type'  => 'text',
                    'label' => __( 'Low End Label (0)', 'bits-accessible-forms' ),
                ),
                'high_label' => array(
                    'type'  => 'text',
                    'label' => __( 'High End Label (10)', 'bits-accessible-forms' ),
                ),
                'show_scale_labels' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Scale Labels', 'bits-accessible-forms' ),
                ),
            ),
            'followup' => array(
                'followup_enabled' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Enable Follow-up Questions', 'bits-accessible-forms' ),
                ),
                'followup_detractor' => array(
                    'type'  => 'text',
                    'label' => __( 'Detractor Question (0-6)', 'bits-accessible-forms' ),
                ),
                'followup_passive' => array(
                    'type'  => 'text',
                    'label' => __( 'Passive Question (7-8)', 'bits-accessible-forms' ),
                ),
                'followup_promoter' => array(
                    'type'  => 'text',
                    'label' => __( 'Promoter Question (9-10)', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the NPS field with accessible controls
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();

        $wrapper_class = $this->get_wrapper_classes();
        
        // Use fieldset/legend for radio group
        $html = '<fieldset class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Legend
        $html .= '<legend class="baf-fieldset-legend">';
        $html .= '<span class="baf-legend-text">' . esc_html( $this->get_label() ) . '</span>';
        $html .= $this->render_required_indicator();
        $html .= '</legend>';

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // NPS scale container
        $html .= '<div class="baf-nps-container">';

        // Scale labels
        if ( ! empty( $this->config['show_scale_labels'] ) ) {
            $html .= '<div class="baf-nps-labels" aria-hidden="true">';
            $html .= '<span class="baf-nps-low">' . esc_html( $this->config['low_label'] ) . '</span>';
            $html .= '<span class="baf-nps-high">' . esc_html( $this->config['high_label'] ) . '</span>';
            $html .= '</div>';
        }

        // NPS scale buttons (0-10)
        $html .= '<div class="baf-nps-scale" role="radiogroup" aria-label="' . esc_attr( $this->get_label() ) . '">';
        
        for ( $i = 0; $i <= 10; $i++ ) {
            $option_id = $html_id . '-' . $i;
            $is_checked = ( $value !== '' && (int) $value === $i );
            
            // Determine category for styling
            $category = $this->get_category_for_score( $i );
            
            $html .= '<label for="' . esc_attr( $option_id ) . '" class="baf-nps-option baf-nps-' . esc_attr( $category ) . '">';
            
            $html .= '<input type="radio"';
            $html .= ' id="' . esc_attr( $option_id ) . '"';
            $html .= ' name="' . esc_attr( $name ) . '"';
            $html .= ' value="' . esc_attr( $i ) . '"';
            $html .= ' class="baf-nps-input"';
            
            if ( $is_checked ) {
                $html .= ' checked';
            }
            
            if ( $this->is_required() ) {
                $html .= ' aria-required="true"';
            }
            
            $html .= '>';

            // Visible number
            $html .= '<span class="baf-nps-number" aria-hidden="true">' . $i . '</span>';
            
            // Screen reader text
            $html .= '<span class="screen-reader-text">';
            $html .= sprintf(
                /* translators: 1: score number, 2: category name */
                __( '%1$d - %2$s', 'bits-accessible-forms' ),
                $i,
                $this->get_category_label( $i )
            );
            $html .= '</span>';
            
            $html .= '</label>';
        }

        $html .= '</div>';

        // Category legend
        $html .= '<div class="baf-nps-legend" aria-hidden="true">';
        $html .= '<span class="baf-nps-legend-item baf-nps-detractor">' . esc_html__( 'Detractor', 'bits-accessible-forms' ) . ' (0-6)</span>';
        $html .= '<span class="baf-nps-legend-item baf-nps-passive">' . esc_html__( 'Passive', 'bits-accessible-forms' ) . ' (7-8)</span>';
        $html .= '<span class="baf-nps-legend-item baf-nps-promoter">' . esc_html__( 'Promoter', 'bits-accessible-forms' ) . ' (9-10)</span>';
        $html .= '</div>';

        $html .= '</div>';

        // Follow-up question (conditionally shown based on score)
        if ( ! empty( $this->config['followup_enabled'] ) ) {
            $followup_value = $this->get_followup_value();
            
            $html .= '<div class="baf-nps-followup" ';
            $html .= 'data-detractor="' . esc_attr( $this->config['followup_detractor'] ) . '" ';
            $html .= 'data-passive="' . esc_attr( $this->config['followup_passive'] ) . '" ';
            $html .= 'data-promoter="' . esc_attr( $this->config['followup_promoter'] ) . '"';
            if ( $value === '' ) {
                $html .= ' style="display: none;"';
            }
            $html .= '>';

            $html .= '<label for="' . esc_attr( $html_id ) . '-followup" class="baf-nps-followup-label">';
            if ( $value !== '' ) {
                $html .= esc_html( $this->get_followup_question( (int) $value ) );
            }
            $html .= '</label>';

            $html .= '<textarea id="' . esc_attr( $html_id ) . '-followup"';
            $html .= ' name="' . esc_attr( $name ) . '_followup"';
            $html .= ' class="baf-nps-followup-input"';
            $html .= ' rows="3">';
            $html .= esc_textarea( $followup_value );
            $html .= '</textarea>';

            $html .= '</div>';
        }

        // Status announcer
        $html .= '<div class="baf-nps-status" role="status" aria-live="polite" aria-atomic="true">';
        if ( $value !== '' ) {
            $html .= sprintf(
                esc_html__( 'You selected %d. Category: %s.', 'bits-accessible-forms' ),
                (int) $value,
                $this->get_category_label( (int) $value )
            );
        }
        $html .= '</div>';

        // Description below
        if ( $this->get_description_placement() === 'below' ) {
            $html .= $this->render_description();
        }

        // Error container
        $html .= '<div id="' . esc_attr( $html_id ) . '-error" class="baf-field-error-container" role="alert" aria-live="polite">';
        if ( ! empty( $this->errors ) ) {
            $html .= $this->render_errors();
        }
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Get category for a score
     *
     * @param int $score NPS score.
     * @return string Category name.
     */
    protected function get_category_for_score( $score ) {
        if ( $score <= 6 ) {
            return 'detractor';
        } elseif ( $score <= 8 ) {
            return 'passive';
        }
        return 'promoter';
    }

    /**
     * Get category label for a score
     *
     * @param int $score NPS score.
     * @return string Category label.
     */
    protected function get_category_label( $score ) {
        switch ( $this->get_category_for_score( $score ) ) {
            case 'detractor':
                return __( 'Detractor', 'bits-accessible-forms' );
            case 'passive':
                return __( 'Passive', 'bits-accessible-forms' );
            case 'promoter':
                return __( 'Promoter', 'bits-accessible-forms' );
        }
        return '';
    }

    /**
     * Get follow-up question based on score
     *
     * @param int $score NPS score.
     * @return string Follow-up question.
     */
    protected function get_followup_question( $score ) {
        switch ( $this->get_category_for_score( $score ) ) {
            case 'detractor':
                return $this->config['followup_detractor'];
            case 'passive':
                return $this->config['followup_passive'];
            case 'promoter':
                return $this->config['followup_promoter'];
        }
        return '';
    }

    /**
     * Get follow-up value
     *
     * @return string Follow-up value.
     */
    protected function get_followup_value() {
        $key = 'baf_field_' . $this->get_id() . '_followup';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return int|string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $value = (int) $value;
        
        if ( $value < 0 ) {
            return 0;
        }
        if ( $value > 10 ) {
            return 10;
        }

        return $value;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( $value < 0 || $value > 10 ) {
            $this->add_error( __( 'Please select a score between 0 and 10.', 'bits-accessible-forms' ) );
        }
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }

        $score = (int) $value;
        $category = $this->get_category_label( $score );

        return sprintf(
            '%d/10 (%s)',
            $score,
            $category
        );
    }

    /**
     * Get entry value including follow-up
     *
     * @return array Value array with score and followup.
     */
    public function get_entry_value() {
        return array(
            'score'    => $this->get_value(),
            'category' => $this->get_value() !== '' ? $this->get_category_for_score( (int) $this->get_value() ) : '',
            'followup' => $this->get_followup_value(),
        );
    }
}
