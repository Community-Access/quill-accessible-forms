<?php
/**
 * Page Break Field - Multi-page form navigation
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Page class
 */
class QAF_Field_Page extends QAF_Field {

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
            'prev_button_text'    => __( 'Previous', 'quill-accessible-forms' ),
            'next_button_text'    => __( 'Next', 'quill-accessible-forms' ),
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
                    'label' => __( 'Page Title', 'quill-accessible-forms' ),
                    'desc'  => __( 'Optional title shown in progress indicator.', 'quill-accessible-forms' ),
                ),
            ),
            'buttons' => array(
                'prev_button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Previous Button Text', 'quill-accessible-forms' ),
                ),
                'next_button_text' => array(
                    'type'  => 'text',
                    'label' => __( 'Next Button Text', 'quill-accessible-forms' ),
                ),
            ),
            'progress' => array(
                'show_progress' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Progress Indicator', 'quill-accessible-forms' ),
                ),
                'progress_style' => array(
                    'type'    => 'select',
                    'label'   => __( 'Progress Style', 'quill-accessible-forms' ),
                    'options' => array(
                        'bar'    => __( 'Progress Bar', 'quill-accessible-forms' ),
                        'steps'  => __( 'Step Indicator', 'quill-accessible-forms' ),
                        'text'   => __( 'Text Only', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'validation' => array(
                'validate_on_next' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Validate Before Moving Next', 'quill-accessible-forms' ),
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
        $prev_text = $this->config['prev_button_text'] ?? __( 'Previous', 'quill-accessible-forms' );
        $next_text = $this->config['next_button_text'] ?? __( 'Next', 'quill-accessible-forms' );
        $show_progress = ! empty( $this->config['show_progress'] );
        $progress_style = $this->config['progress_style'] ?? 'bar';
        $validate = ! empty( $this->config['validate_on_next'] );

        $wrapper_class = array( 'qaf-page-break' );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' data-page-title="' . esc_attr( $page_title ) . '"';
        $html .= ' data-validate="' . ( $validate ? 'true' : 'false' ) . '">';

        // Page navigation buttons
        $html .= '<div class="qaf-page-navigation" role="navigation" aria-label="' . esc_attr__( 'Form page navigation', 'quill-accessible-forms' ) . '">';
        
        // Previous button
        $html .= '<button type="button" class="qaf-page-prev qaf-button qaf-button-secondary"';
        $html .= ' aria-label="' . esc_attr( sprintf( __( '%s page', 'quill-accessible-forms' ), $prev_text ) ) . '">';
        $html .= '<span class="qaf-button-icon qaf-icon-arrow-left" aria-hidden="true"></span>';
        $html .= '<span class="qaf-button-text">' . esc_html( $prev_text ) . '</span>';
        $html .= '</button>';

        // Next button
        $html .= '<button type="button" class="qaf-page-next qaf-button qaf-button-primary"';
        $html .= ' aria-label="' . esc_attr( sprintf( __( '%s page', 'quill-accessible-forms' ), $next_text ) ) . '">';
        $html .= '<span class="qaf-button-text">' . esc_html( $next_text ) . '</span>';
        $html .= '<span class="qaf-button-icon qaf-icon-arrow-right" aria-hidden="true"></span>';
        $html .= '</button>';

        $html .= '</div>';

        // Progress indicator (rendered by JS based on total pages)
        if ( $show_progress ) {
            $html .= '<div class="qaf-page-progress qaf-progress-' . esc_attr( $progress_style ) . '"';
            $html .= ' role="progressbar"';
            $html .= ' aria-valuenow="0"';
            $html .= ' aria-valuemin="0"';
            $html .= ' aria-valuemax="100"';
            $html .= ' aria-label="' . esc_attr__( 'Form progress', 'quill-accessible-forms' ) . '">';

            if ( $progress_style === 'bar' ) {
                $html .= '<div class="qaf-progress-bar"><div class="qaf-progress-fill"></div></div>';
                $html .= '<span class="qaf-progress-text" aria-live="polite"></span>';
            } elseif ( $progress_style === 'steps' ) {
                $html .= '<ol class="qaf-progress-steps" aria-label="' . esc_attr__( 'Form steps', 'quill-accessible-forms' ) . '"></ol>';
            } else {
                $html .= '<span class="qaf-progress-text" aria-live="polite"></span>';
            }

            $html .= '</div>';
        }

        // Status announcer
        $html .= '<div class="qaf-page-status" role="status" aria-live="polite" aria-atomic="true"></div>';

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
