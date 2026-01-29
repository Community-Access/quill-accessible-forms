<?php
/**
 * Page Break Field - Multi-page form navigation
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Page class
 */
class BAF_Field_Page extends BAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'page';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'layout';

    /**
     * Whether field stores data
     *
     * @var bool
     */
    protected $has_value = false;

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'page_title'          => '',
            'prev_button_text'    => __( 'Previous', 'bits-accessible-forms' ),
            'next_button_text'    => __( 'Next', 'bits-accessible-forms' ),
            'show_progress'       => true,
            'progress_style'      => 'bar',
            'validate_on_next'    => true,
            'conditional'         => array(),
            'css_class'           => '',
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
                'page_title' => array(
                    'type'  => 'text',
                    'label' => __( 'Page Title', 'bits-accessible-forms' ),
                    'desc'  => __( 'Optional title shown in progress indicator.', 'bits-accessible-forms' ),
                ),
            ),
            'buttons' => array(
                'prev_button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Previous Button Text', 'bits-accessible-forms' ),
                ),
                'next_button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Next Button Text', 'bits-accessible-forms' ),
                ),
            ),
            'progress' => array(
                'show_progress' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Progress Indicator', 'bits-accessible-forms' ),
                ),
                'progress_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Progress Style', 'bits-accessible-forms' ),
                    'options' => array(
                        'bar'    => __( 'Progress Bar', 'bits-accessible-forms' ),
                        'steps'  => __( 'Step Indicator', 'bits-accessible-forms' ),
                        'text'   => __( 'Text Only', 'bits-accessible-forms' ),
                    ),
                ),
            ),
            'validation' => array(
                'validate_on_next' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Validate Before Moving Next', 'bits-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the page break HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id = $this->get_html_id();
        $page_title = $this->config['page_title'] ?? '';
        $prev_text = $this->config['prev_button_text'] ?? __( 'Previous', 'bits-accessible-forms' );
        $next_text = $this->config['next_button_text'] ?? __( 'Next', 'bits-accessible-forms' );
        $show_progress = ! empty( $this->config['show_progress'] );
        $progress_style = $this->config['progress_style'] ?? 'bar';
        $validate = ! empty( $this->config['validate_on_next'] );

        $wrapper_class = array( 'baf-page-break' );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-page-title="' . esc_attr( $page_title ) . '"';
        $html .= ' data-validate="' . ( $validate ? 'true' : 'false' ) . '">';

        // Page navigation buttons
        $html .= '<div class="baf-page-navigation" role="navigation" aria-label="' . esc_attr__( 'Form page navigation', 'bits-accessible-forms' ) . '">';
        
        // Previous button
        $html .= '<button type="button" class="baf-page-prev baf-button baf-button-secondary"';
        $html .= ' aria-label="' . esc_attr( sprintf( __( '%s page', 'bits-accessible-forms' ), $prev_text ) ) . '">';
        $html .= '<span class="baf-button-icon baf-icon-arrow-left" aria-hidden="true"></span>';
        $html .= '<span class="baf-button-text">' . esc_html( $prev_text ) . '</span>';
        $html .= '</button>';

        // Next button
        $html .= '<button type="button" class="baf-page-next baf-button baf-button-primary"';
        $html .= ' aria-label="' . esc_attr( sprintf( __( '%s page', 'bits-accessible-forms' ), $next_text ) ) . '">';
        $html .= '<span class="baf-button-text">' . esc_html( $next_text ) . '</span>';
        $html .= '<span class="baf-button-icon baf-icon-arrow-right" aria-hidden="true"></span>';
        $html .= '</button>';

        $html .= '</div>';

        // Progress indicator (rendered by JS based on total pages)
        if ( $show_progress ) {
            $html .= '<div class="baf-page-progress baf-progress-' . esc_attr( $progress_style ) . '"';
            $html .= ' role="progressbar"';
            $html .= ' aria-valuenow="0"';
            $html .= ' aria-valuemin="0"';
            $html .= ' aria-valuemax="100"';
            $html .= ' aria-label="' . esc_attr__( 'Form progress', 'bits-accessible-forms' ) . '">';

            if ( $progress_style === 'bar' ) {
                $html .= '<div class="baf-progress-bar"><div class="baf-progress-fill"></div></div>';
                $html .= '<span class="baf-progress-text" aria-live="polite"></span>';
            } elseif ( $progress_style === 'steps' ) {
                $html .= '<ol class="baf-progress-steps" aria-label="' . esc_attr__( 'Form steps', 'bits-accessible-forms' ) . '"></ol>';
            } else {
                $html .= '<span class="baf-progress-text" aria-live="polite"></span>';
            }

            $html .= '</div>';
        }

        // Status announcer
        $html .= '<div class="baf-page-status" role="status" aria-live="polite" aria-atomic="true"></div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Page breaks don't need validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Page breaks don't store values
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }
}
