<?php
/**
 * Form Renderer
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Form_Renderer class
 * 
 * Renders forms for public display with full accessibility support.
 */
class BAF_Form_Renderer {

    /**
     * ARIA generator
     *
     * @var BAF_ARIA_Generator
     */
    private $aria;

    /**
     * Security handler
     *
     * @var BAF_Security
     */
    private $security;

    /**
     * Honeypot handler
     *
     * @var BAF_Honeypot
     */
    private $honeypot;

    /**
     * Captcha handler
     *
     * @var BAF_Captcha
     */
    private $captcha;

    /**
     * Constructor
     */
    public function __construct() {
        $this->aria = new BAF_ARIA_Generator();
        $this->security = new BAF_Security();
        $this->honeypot = new BAF_Honeypot();
        $this->captcha = new BAF_Captcha();
    }

    /**
     * Render a form
     *
     * @param BAF_Form $form    Form object.
     * @param array    $options Render options.
     * @return string HTML output.
     */
    public function render( $form, $options = array() ) {
        $defaults = array(
            'show_title'       => true,
            'show_description' => true,
            'ajax'             => true,
            'class'            => '',
            'prefix'           => 'baf',
        );

        $options = wp_parse_args( $options, $defaults );
        $form_id = $form->id;
        $config = $form->config;
        $settings = $form->settings;

        // Generate unique instance ID for ARIA
        $instance_id = 'baf-form-' . $form_id . '-' . wp_rand( 1000, 9999 );

        // Start output buffer
        ob_start();

        // Live region for announcements
        echo '<div id="' . esc_attr( $instance_id ) . '-announcer" class="baf-announcer" aria-live="polite" aria-atomic="true"></div>';

        // Form wrapper
        $wrapper_classes = array(
            'baf-form-wrapper',
            'baf-form-' . $form_id,
        );

        if ( ! empty( $options['class'] ) ) {
            $wrapper_classes[] = $options['class'];
        }

        echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

        // Title
        if ( $options['show_title'] && ! empty( $form->title ) ) {
            echo '<h2 class="baf-form-title" id="' . esc_attr( $instance_id ) . '-title">' . esc_html( $form->title ) . '</h2>';
        }

        // Description
        if ( $options['show_description'] && ! empty( $form->description ) ) {
            echo '<div class="baf-form-description" id="' . esc_attr( $instance_id ) . '-desc">' . wp_kses_post( $form->description ) . '</div>';
        }

        // Form tag
        $form_attrs = array(
            'id'              => $instance_id,
            'class'           => 'baf-form' . ( $options['ajax'] ? ' baf-ajax-form' : '' ),
            'method'          => 'post',
            'action'          => rest_url( 'baf/v1/submit/' . $form_id ),
            'enctype'         => 'multipart/form-data',
            'novalidate'      => 'novalidate', // We handle validation accessibly
            'aria-labelledby' => $options['show_title'] ? $instance_id . '-title' : '',
            'aria-describedby' => $options['show_description'] ? $instance_id . '-desc' : '',
            'data-form-id'    => $form_id,
        );

        echo '<form ' . $this->build_attrs( $form_attrs ) . '>';

        // Hidden fields
        wp_nonce_field( 'baf_submit_form_' . $form_id, '_baf_token' );
        echo '<input type="hidden" name="form_id" value="' . esc_attr( $form_id ) . '" />';
        echo '<input type="hidden" name="_baf_form_loaded" value="' . esc_attr( time() ) . '" />';

        // Honeypot fields
        echo $this->honeypot->render( $form_id );

        // Get pages and fields
        $pages = isset( $config['pages'] ) ? $config['pages'] : array( array( 'fields' => $form->get_fields() ) );
        $total_pages = count( $pages );
        $is_multi_page = $total_pages > 1;

        // Progress indicator for multi-page forms
        if ( $is_multi_page ) {
            echo $this->render_progress_indicator( $pages, $instance_id );
        }

        // Render pages
        foreach ( $pages as $page_index => $page ) {
            $page_number = $page_index + 1;
            $page_id = $instance_id . '-page-' . $page_number;

            $page_classes = array( 'baf-page' );
            if ( $page_index > 0 ) {
                $page_classes[] = 'baf-page-hidden';
            }

            echo '<div class="' . esc_attr( implode( ' ', $page_classes ) ) . '" ';
            echo 'id="' . esc_attr( $page_id ) . '" ';
            echo 'data-page="' . esc_attr( $page_number ) . '" ';
            echo 'role="group" ';
            echo 'aria-label="' . sprintf( esc_attr__( 'Page %1$d of %2$d', 'bits-accessible-forms' ), $page_number, $total_pages ) . '"';

            if ( $page_index > 0 ) {
                echo ' aria-hidden="true"';
            }

            echo '>';

            // Page title
            if ( ! empty( $page['title'] ) ) {
                echo '<h3 class="baf-page-title">' . esc_html( $page['title'] ) . '</h3>';
            }

            // Page description
            if ( ! empty( $page['description'] ) ) {
                echo '<div class="baf-page-description">' . wp_kses_post( $page['description'] ) . '</div>';
            }

            // Render fields
            $fields = isset( $page['fields'] ) ? $page['fields'] : array();
            echo $this->render_fields( $fields, $form, $instance_id );

            // Page navigation
            if ( $is_multi_page ) {
                echo $this->render_page_navigation( $page_number, $total_pages, $instance_id );
            }

            echo '</div>'; // .baf-page
        }

        // CAPTCHA (if enabled and on last page or single page)
        if ( ! empty( $settings['captcha_enabled'] ) ) {
            echo '<div class="baf-captcha-wrapper">';
            echo $this->captcha->render( $form_id );
            echo '</div>';
        }

        // Submit button (shown on last page)
        $submit_classes = array( 'baf-submit-wrapper' );
        if ( $is_multi_page ) {
            $submit_classes[] = 'baf-submit-hidden';
        }

        echo '<div class="' . esc_attr( implode( ' ', $submit_classes ) ) . '" data-show-on-page="' . esc_attr( $total_pages ) . '">';

        $submit_text = ! empty( $settings['submit_text'] ) ? $settings['submit_text'] : __( 'Submit', 'bits-accessible-forms' );

        echo '<button type="submit" class="baf-submit-button">';
        echo '<span class="baf-submit-text">' . esc_html( $submit_text ) . '</span>';
        echo '<span class="baf-submit-loading" aria-hidden="true" style="display:none;">';
        echo '<span class="baf-spinner"></span>';
        echo '<span class="screen-reader-text">' . esc_html__( 'Submitting...', 'bits-accessible-forms' ) . '</span>';
        echo '</span>';
        echo '</button>';

        echo '</div>';

        echo '</form>';

        // Success/error message containers
        echo '<div class="baf-messages" role="alert" aria-live="assertive"></div>';

        echo '</div>'; // .baf-form-wrapper

        return ob_get_clean();
    }

    /**
     * Render fields
     *
     * @param array    $fields      Fields to render.
     * @param BAF_Form $form        Form object.
     * @param string   $instance_id Form instance ID.
     * @return string HTML output.
     */
    private function render_fields( $fields, $form, $instance_id ) {
        $output = '<div class="baf-fields">';

        foreach ( $fields as $field ) {
            $output .= $this->render_field( $field, $form, $instance_id );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a single field
     *
     * @param array    $field       Field config.
     * @param BAF_Form $form        Form object.
     * @param string   $instance_id Form instance ID.
     * @return string HTML output.
     */
    private function render_field( $field, $form, $instance_id ) {
        $type     = isset( $field['type'] ) ? $field['type'] : 'text';
        $field_id = isset( $field['id'] ) ? $field['id'] : $type . '_' . wp_rand( 1000, 9999 );

        // Ensure ID exists for JS/submit mapping
        if ( empty( $field['id'] ) ) {
            $field['id'] = $field_id;
        }

        // Create field instance using registry (fields render their own wrappers/labels/errors)
        $field_registry = BAF_Field_Registry::get_instance();
        $field_obj      = $field_registry->create_field( $field, $form->id );

        if ( ! $field_obj ) {
            return '';
        }

        // Skip non-renderable fields (these field classes already render)
        if ( in_array( $type, array( 'hidden', 'page' ), true ) ) {
            return '';
        }

        $output = $field_obj->render();

        // Back-compat: JS conditional logic expects [data-conditional] on a wrapper.
        if ( ! empty( $field['conditional_logic'] ) && ! empty( $field['conditional_logic']['enabled'] ) ) {
            $logic = $field['conditional_logic'];

            // Translate rules schema -> JS schema.
            $conditional = array(
                'action'     => $logic['action'] ?? 'show',
                'logic'      => ( $logic['logic'] ?? 'all' ) === 'any' ? 'any' : 'all',
                'conditions' => $logic['rules'] ?? array(),
            );

            $output = preg_replace(
                '/\sdata-field-id="' . preg_quote( (string) $field_id, '/' ) . '"/',
                ' data-conditional="' . esc_attr( wp_json_encode( $conditional ) ) . '" data-field-id="' . esc_attr( $field_id ) . '"',
                $output,
                1
            );
        }

        return $output;
    }

    /**
     * Render section
     *
     * @param array    $field       Field config.
     * @param BAF_Form $form        Form object.
     * @param string   $instance_id Form instance ID.
     * @return string HTML output.
     */
    private function render_section( $field, $form, $instance_id ) {
        $output = '<fieldset class="baf-section">';

        if ( ! empty( $field['label'] ) ) {
            $output .= '<legend class="baf-section-title">' . esc_html( $field['label'] ) . '</legend>';
        }

        if ( ! empty( $field['description'] ) ) {
            $output .= '<div class="baf-section-description">' . wp_kses_post( $field['description'] ) . '</div>';
        }

        // Render nested fields
        if ( ! empty( $field['fields'] ) ) {
            $output .= $this->render_fields( $field['fields'], $form, $instance_id );
        }

        $output .= '</fieldset>';

        return $output;
    }

    /**
     * Render progress indicator
     *
     * @param array  $pages       Pages array.
     * @param string $instance_id Form instance ID.
     * @return string HTML output.
     */
    private function render_progress_indicator( $pages, $instance_id ) {
        $total_pages = count( $pages );

        $output = '<div class="baf-progress" role="progressbar" aria-valuemin="1" aria-valuemax="' . esc_attr( $total_pages ) . '" aria-valuenow="1" aria-label="' . esc_attr__( 'Form progress', 'bits-accessible-forms' ) . '">';

        // Visual progress bar
        $output .= '<div class="baf-progress-bar">';
        $output .= '<div class="baf-progress-fill" style="width: ' . ( 100 / $total_pages ) . '%"></div>';
        $output .= '</div>';

        // Step indicators
        $output .= '<ol class="baf-progress-steps">';

        foreach ( $pages as $index => $page ) {
            $step_num = $index + 1;
            $step_classes = array( 'baf-progress-step' );

            if ( $index === 0 ) {
                $step_classes[] = 'baf-step-current';
            }

            $output .= '<li class="' . esc_attr( implode( ' ', $step_classes ) ) . '" data-step="' . esc_attr( $step_num ) . '">';
            $output .= '<span class="baf-step-number" aria-hidden="true">' . esc_html( $step_num ) . '</span>';
            $output .= '<span class="baf-step-title">';

            if ( ! empty( $page['title'] ) ) {
                $output .= esc_html( $page['title'] );
            } else {
                $output .= sprintf( esc_html__( 'Step %d', 'bits-accessible-forms' ), $step_num );
            }

            $output .= '</span>';
            $output .= '</li>';
        }

        $output .= '</ol>';

        // Screen reader status
        $output .= '<div class="screen-reader-text baf-progress-status">';
        $output .= sprintf( esc_html__( 'Step 1 of %d', 'bits-accessible-forms' ), $total_pages );
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Render page navigation
     *
     * @param int    $current_page Current page number.
     * @param int    $total_pages  Total pages.
     * @param string $instance_id  Form instance ID.
     * @return string HTML output.
     */
    private function render_page_navigation( $current_page, $total_pages, $instance_id ) {
        $output = '<div class="baf-page-nav">';

        // Previous button
        if ( $current_page > 1 ) {
            $output .= '<button type="button" class="baf-nav-prev baf-button" data-action="prev-page">';
            $output .= '<span aria-hidden="true">&larr;</span> ';
            $output .= esc_html__( 'Previous', 'bits-accessible-forms' );
            $output .= '</button>';
        }

        // Next button (not on last page)
        if ( $current_page < $total_pages ) {
            $output .= '<button type="button" class="baf-nav-next baf-button baf-button-primary" data-action="next-page">';
            $output .= esc_html__( 'Next', 'bits-accessible-forms' );
            $output .= ' <span aria-hidden="true">&rarr;</span>';
            $output .= '</button>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Build HTML attributes string
     *
     * @param array $attrs Attributes array.
     * @return string Attributes string.
     */
    private function build_attrs( $attrs ) {
        $output = array();

        foreach ( $attrs as $key => $value ) {
            if ( $value === '' || $value === null ) {
                continue;
            }

            if ( is_bool( $value ) ) {
                if ( $value ) {
                    $output[] = esc_attr( $key );
                }
            } else {
                $output[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
            }
        }

        return implode( ' ', $output );
    }
}
