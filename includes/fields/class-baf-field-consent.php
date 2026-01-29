<?php
/**
 * Consent/Terms Field - Uses fieldset/legend for accessibility
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Consent class
 */
class BAF_Field_Consent extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'consent';

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
            'consent_text'     => __( 'I agree to the terms and conditions.', 'bits-accessible-forms' ),
            'terms_content'    => '',
            'terms_page'       => 0,
            'show_terms_box'   => false,
            'terms_box_height' => 200,
            'checkbox_label'   => '',
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
                    'label' => __( 'Field Label', 'bits-accessible-forms' ),
                    'desc'  => __( 'Optional heading for the consent section.', 'bits-accessible-forms' ),
                ),
                'consent_text' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Consent Text', 'bits-accessible-forms' ),
                    'desc'  => __( 'The checkbox label. Can include HTML links.', 'bits-accessible-forms' ),
                ),
                'required' => array(
                    'type'    => 'toggle',
                    'label'   => __( 'Required', 'bits-accessible-forms' ),
                    'default' => true,
                ),
            ),
            'terms' => array(
                'show_terms_box' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Terms Box', 'bits-accessible-forms' ),
                    'desc'  => __( 'Display scrollable terms content above checkbox.', 'bits-accessible-forms' ),
                ),
                'terms_content' => array(
                    'type'  => 'richtext',
                    'label' => __( 'Terms Content', 'bits-accessible-forms' ),
                ),
                'terms_page' => array(
                    'type'  => 'page_select',
                    'label' => __( 'Or Link to Page', 'bits-accessible-forms' ),
                    'desc'  => __( 'Link to an existing page for terms.', 'bits-accessible-forms' ),
                ),
                'terms_box_height' => array(
                    'type'  => 'number',
                    'label' => __( 'Terms Box Height (px)', 'bits-accessible-forms' ),
                    'min'   => 100,
                    'max'   => 600,
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
        $name    = 'baf_field_' . $this->get_id();
        $value   = $this->get_value();
        
        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'baf-field-consent';

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Optional section heading
        if ( $this->get_label() ) {
            $html .= '<h3 class="baf-consent-heading">' . esc_html( $this->get_label() ) . '</h3>';
        }

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $html .= $this->render_description();
        }

        // Terms box (scrollable)
        if ( ! empty( $this->config['show_terms_box'] ) ) {
            $html .= $this->render_terms_box();
        }

        // Consent checkbox
        $html .= '<div class="baf-consent-checkbox">';
        
        $checkbox_attrs = array(
            'type'  => 'checkbox',
            'id'    => $html_id,
            'name'  => $name,
            'value' => '1',
            'class' => 'baf-checkbox-input baf-consent-input',
        );

        if ( $value ) {
            $checkbox_attrs['checked'] = 'checked';
        }

        if ( $this->is_required() ) {
            $checkbox_attrs['required'] = 'required';
            $checkbox_attrs['aria-required'] = 'true';
        }

        $described_by = array();
        if ( $this->get_description() ) {
            $described_by[] = $html_id . '-desc';
        }
        if ( ! empty( $this->errors ) ) {
            $checkbox_attrs['aria-invalid'] = 'true';
            $described_by[] = $html_id . '-error';
        }
        if ( ! empty( $described_by ) ) {
            $checkbox_attrs['aria-describedby'] = implode( ' ', $described_by );
        }

        $html .= '<input ' . $this->build_attributes( $checkbox_attrs ) . '>';

        // Label with consent text (may contain HTML)
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="baf-consent-label">';
        $html .= '<span class="baf-checkbox-indicator" aria-hidden="true"></span>';
        $html .= '<span class="baf-consent-text">';
        $html .= wp_kses_post( $this->get_consent_text() );
        if ( $this->is_required() ) {
            $html .= ' <span class="baf-required" aria-hidden="true">*</span>';
        }
        $html .= '</span>';
        $html .= '</label>';

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

        $html .= '</div>';

        return $html;
    }

    /**
     * Render scrollable terms box
     *
     * @return string Terms box HTML.
     */
    protected function render_terms_box() {
        $html_id = $this->get_html_id();
        $height  = (int) $this->config['terms_box_height'];
        $content = $this->get_terms_content();

        if ( empty( $content ) ) {
            return '';
        }

        $html = '<div class="baf-terms-box-wrapper">';
        $html .= '<div id="' . esc_attr( $html_id ) . '-terms" ';
        $html .= 'class="baf-terms-box" ';
        $html .= 'role="region" ';
        $html .= 'aria-label="' . esc_attr__( 'Terms and Conditions', 'bits-accessible-forms' ) . '" ';
        $html .= 'tabindex="0" ';
        $html .= 'style="max-height: ' . esc_attr( $height ) . 'px;">';
        $html .= wp_kses_post( $content );
        $html .= '</div>';
        
        // Scroll hint for keyboard users
        $html .= '<p class="baf-terms-scroll-hint">';
        $html .= esc_html__( 'Use arrow keys to scroll within the terms above.', 'bits-accessible-forms' );
        $html .= '</p>';
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Get consent text with link substitution
     *
     * @return string Consent text.
     */
    protected function get_consent_text() {
        $text = $this->config['consent_text'];

        // If there's a terms page, create a link
        if ( ! empty( $this->config['terms_page'] ) ) {
            $page_id = (int) $this->config['terms_page'];
            $page_url = get_permalink( $page_id );
            $page_title = get_the_title( $page_id );

            if ( $page_url ) {
                // Replace {terms_link} placeholder or append
                if ( strpos( $text, '{terms_link}' ) !== false ) {
                    $link = '<a href="' . esc_url( $page_url ) . '" target="_blank" rel="noopener">' . 
                            esc_html( $page_title ) . 
                            ' <span class="screen-reader-text">' . esc_html__( '(opens in new tab)', 'bits-accessible-forms' ) . '</span></a>';
                    $text = str_replace( '{terms_link}', $link, $text );
                }
            }
        }

        return $text;
    }

    /**
     * Get terms content
     *
     * @return string Terms content.
     */
    protected function get_terms_content() {
        // Check for inline content first
        if ( ! empty( $this->config['terms_content'] ) ) {
            return $this->config['terms_content'];
        }

        // Check for linked page
        if ( ! empty( $this->config['terms_page'] ) ) {
            $page = get_post( (int) $this->config['terms_page'] );
            if ( $page ) {
                return apply_filters( 'the_content', $page->post_content );
            }
        }

        return '';
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return int Sanitized value (1 or 0).
     */
    public function sanitize( $value ) {
        return $value ? 1 : 0;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        // If required, must be checked (value = 1)
        // This is handled by is_empty() check in parent
    }

    /**
     * Check if value is empty
     *
     * @param mixed $value Value to check.
     * @return bool True if empty.
     */
    protected function is_empty( $value ) {
        return empty( $value );
    }

    /**
     * Format value for display
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        return $value ? __( 'Agreed', 'bits-accessible-forms' ) : __( 'Not Agreed', 'bits-accessible-forms' );
    }
}
