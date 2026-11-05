<?php
/**
 * Form Renderer
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Form_Renderer class
 * 
 * Renders forms for public display with full accessibility support.
 */
class QAF_Form_Renderer {

    /**
     * ARIA generator
     *
     * @var QAF_ARIA_Generator
     */
    private $aria;

    /**
     * Security handler
     *
     * @var QAF_Security
     */
    private $security;

    /**
     * Honeypot handler
     *
     * @var QAF_Honeypot
     */
    private $honeypot;

    /**
     * Captcha handler
     *
     * @var QAF_Captcha
     */
    private $captcha;

    /**
     * Whether the form being drawn has a model, so the browser runs the
     * model's rules instead of the builder's per-field rule lists.
     *
     * @var bool
     */
    private $has_model = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->aria = new QAF_ARIA_Generator();
        $this->security = new QAF_Security();
        $this->honeypot = new QAF_Honeypot();
        $this->captcha = new QAF_Captcha();
    }

    /**
     * Render a form
     *
     * @param QAF_Form $form    Form object.
     * @param array    $options Render options.
     * @return string HTML output.
     */
    public function render( $form, $options = array() ) {
        $defaults = array(
            'show_title'       => true,
            'show_description' => true,
            'ajax'             => true,
            'class'            => '',
            'prefix'           => 'qaf',
        );

        $options = wp_parse_args( $options, $defaults );
        $form_id = $form->id;
        $config = $form->config;
        $settings = $form->settings;

        // A form saved with a model: its language, and what the browser runs.
        $model = ( isset( $config['form_model'] ) && is_array( $config['form_model'] ) ) ? $config['form_model'] : null;
        $this->has_model = (bool) $model;
        $lang  = '';
        $title = $form->title;
        if ( $model ) {
            $requested = isset( $_GET['qaf_lang'] ) ? sanitize_key( wp_unslash( $_GET['qaf_lang'] ) ) : '';
            $lang      = QAF_Form_Runtime::language( $model, $requested, function_exists( 'determine_locale' ) ? determine_locale() : get_locale() );
            $config    = QAF_Form_Runtime::translate_config( $config, $model, $lang );
            $title     = QAF_Form_Runtime::text( $model, $lang, 'title', $title );
        }

        // Generate unique instance ID for ARIA
        $instance_id = 'qaf-form-' . $form_id . '-' . wp_rand( 1000, 9999 );

        // Start output buffer
        ob_start();

        // Live region for announcements
        echo '<div id="' . esc_attr( $instance_id ) . '-announcer" class="qaf-announcer" aria-live="polite" aria-atomic="true"></div>';

        // Form wrapper
        $wrapper_classes = array(
            'qaf-form-wrapper',
            'qaf-form-' . $form_id,
        );

        if ( ! empty( $options['class'] ) ) {
            $wrapper_classes[] = $options['class'];
        }

        echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

        // Title
        if ( $options['show_title'] && ! empty( $title ) ) {
            echo '<h2 class="qaf-form-title" id="' . esc_attr( $instance_id ) . '-title">' . esc_html( $title ) . '</h2>';
        }

        // Description
        if ( $options['show_description'] && ! empty( $form->description ) ) {
            echo '<div class="qaf-form-description" id="' . esc_attr( $instance_id ) . '-desc">' . wp_kses_post( $form->description ) . '</div>';
        }

        // Form tag
        $form_attrs = array(
            'id'              => $instance_id,
            'class'           => 'qaf-form' . ( $options['ajax'] ? ' qaf-ajax-form' : '' ),
            'method'          => 'post',
            'action'          => rest_url( 'qaf/v1/submit/' . $form_id ),
            'enctype'         => 'multipart/form-data',
            'novalidate'      => 'novalidate', // We handle validation accessibly
            'aria-labelledby' => $options['show_title'] ? $instance_id . '-title' : '',
            'aria-describedby' => $options['show_description'] ? $instance_id . '-desc' : '',
            'data-form-id'    => $form_id,
        );
        if ( $model ) {
            $form_attrs['data-qaf-model'] = '1';
            if ( $lang !== '' ) {
                $form_attrs['lang'] = $lang;
            }
        }

        echo '<form ' . $this->build_attrs( $form_attrs ) . '>';

        // Hidden fields
        wp_nonce_field( 'qaf_submit_form_' . $form_id, '_qaf_token' );
        echo '<input type="hidden" name="form_id" value="' . esc_attr( $form_id ) . '" />';
        echo '<input type="hidden" name="_qaf_form_loaded" value="' . esc_attr( time() ) . '" />';

        // Honeypot fields
        echo $this->honeypot->render( $form_id );

        if ( $model ) {
            echo '<input type="hidden" name="qaf_lang" value="' . esc_attr( $lang ) . '" />';
            $user = array();
            if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
                $u    = wp_get_current_user();
                $user = array(
                    'id'           => $u->ID,
                    'display_name' => $u->display_name,
                    'first_name'   => $u->first_name,
                    'last_name'    => $u->last_name,
                    'email'        => $u->user_email,
                    'login'        => $u->user_login,
                );
            }
            $payload = QAF_Form_Runtime::payload( $model, function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) : gmdate( 'Y-m-d' ), $user );
            echo '<script type="application/json" class="qaf-form-model">' . wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>';
        }

        // Get pages and fields
        $pages = isset( $config['pages'] ) ? $config['pages'] : array( array( 'fields' => $form->get_fields() ) );
        $pages = self::fill_pages( $pages, $config['fields'] ?? array() );
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

            $page_classes = array( 'qaf-page' );
            if ( $page_index > 0 ) {
                $page_classes[] = 'qaf-page-hidden';
            }

            echo '<div class="' . esc_attr( implode( ' ', $page_classes ) ) . '" ';
            echo 'id="' . esc_attr( $page_id ) . '" ';
            echo 'data-page="' . esc_attr( $page_number ) . '" ';
            if ( isset( $page['id'] ) ) {
                echo 'data-page-id="' . esc_attr( $page['id'] ) . '" ';
            }
            echo 'role="group" ';
            echo 'aria-label="' . sprintf( esc_attr__( 'Page %1$d of %2$d', 'quill-accessible-forms' ), $page_number, $total_pages ) . '"';

            if ( $page_index > 0 ) {
                echo ' aria-hidden="true"';
            }

            echo '>';

            // Page title
            if ( ! empty( $page['title'] ) ) {
                echo '<h3 class="qaf-page-title">' . esc_html( $page['title'] ) . '</h3>';
            }

            // Page description
            if ( ! empty( $page['description'] ) ) {
                echo '<div class="qaf-page-description">' . wp_kses_post( $page['description'] ) . '</div>';
            }

            // Render fields
            $fields = isset( $page['fields'] ) ? $page['fields'] : array();
            echo $this->render_fields( $fields, $form, $instance_id );

            // Page navigation
            if ( $is_multi_page ) {
                echo $this->render_page_navigation( $page_number, $total_pages, $instance_id );
            }

            echo '</div>'; // .qaf-page
        }

        // CAPTCHA (if enabled and on last page or single page)
        if ( ! empty( $settings['captcha_enabled'] ) ) {
            echo '<div class="qaf-captcha-wrapper">';
            echo $this->captcha->render( $form_id );
            echo '</div>';
        }

        // Submit button (shown on last page)
        $submit_classes = array( 'qaf-submit-wrapper' );
        if ( $is_multi_page ) {
            $submit_classes[] = 'qaf-submit-hidden';
        }

        echo '<div class="' . esc_attr( implode( ' ', $submit_classes ) ) . '" data-show-on-page="' . esc_attr( $total_pages ) . '">';

        $submit_text = ! empty( $settings['submit_text'] ) ? $settings['submit_text'] : __( 'Submit', 'quill-accessible-forms' );
        if ( $model ) {
            $submit_text = QAF_Form_Runtime::text( $model, $lang, 'submit', $submit_text );
        }

        echo '<button type="submit" class="qaf-submit-button">';
        echo '<span class="qaf-submit-text">' . esc_html( $submit_text ) . '</span>';
        echo '<span class="qaf-submit-loading" aria-hidden="true" style="display:none;">';
        echo '<span class="qaf-spinner"></span>';
        echo '<span class="screen-reader-text">' . esc_html__( 'Submitting...', 'quill-accessible-forms' ) . '</span>';
        echo '</span>';
        echo '</button>';

        echo '</div>';

        echo '</form>';

        // Success/error message containers
        echo '<div class="qaf-messages" role="alert" aria-live="assertive"></div>';

        echo '</div>'; // .qaf-form-wrapper

        return ob_get_clean();
    }

    /**
     * Give each page its fields. The builder keeps fields in one list, each
     * naming its page, and pages only describe themselves; a page that
     * already lists its fields is left as it is.
     *
     * @param array $pages  Pages.
     * @param array $fields Fields.
     * @return array Pages with fields.
     */
    public static function fill_pages( $pages, $fields ) {
        $ids = array();
        foreach ( $pages as $page ) {
            if ( isset( $page['id'] ) ) {
                $ids[] = (string) $page['id'];
            }
        }
        foreach ( $pages as $i => $page ) {
            if ( isset( $page['fields'] ) ) {
                continue;
            }
            $pages[ $i ]['fields'] = array();
            foreach ( $fields as $field ) {
                $on = isset( $field['page'] ) ? (string) $field['page'] : '';
                // A field naming no page, or a page that does not exist, goes on the first.
                $here = ( $on !== '' && in_array( $on, $ids, true ) ) ? $on === (string) ( $page['id'] ?? '' ) : 0 === $i;
                if ( $here ) {
                    $pages[ $i ]['fields'][] = $field;
                }
            }
        }
        return $pages;
    }

    /**
     * Render fields
     *
     * @param array    $fields      Fields to render.
     * @param QAF_Form $form        Form object.
     * @param string   $instance_id Form instance ID.
     * @return string HTML output.
     */
    private function render_fields( $fields, $form, $instance_id ) {
        $output = '<div class="qaf-fields">';

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
     * @param QAF_Form $form        Form object.
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
        $field_registry = QAF_Field_Registry::get_instance();
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
        // A form with a model runs the model's conditions instead.
        if ( ! $this->has_model && ! empty( $field['conditional_logic'] ) && ! empty( $field['conditional_logic']['enabled'] ) ) {
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
     * @param QAF_Form $form        Form object.
     * @param string   $instance_id Form instance ID.
     * @return string HTML output.
     */
    private function render_section( $field, $form, $instance_id ) {
        $output = '<fieldset class="qaf-section">';

        if ( ! empty( $field['label'] ) ) {
            $output .= '<legend class="qaf-section-title">' . esc_html( $field['label'] ) . '</legend>';
        }

        if ( ! empty( $field['description'] ) ) {
            $output .= '<div class="qaf-section-description">' . wp_kses_post( $field['description'] ) . '</div>';
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

        $output = '<div class="qaf-progress" role="progressbar" aria-valuemin="1" aria-valuemax="' . esc_attr( $total_pages ) . '" aria-valuenow="1" aria-label="' . esc_attr__( 'Form progress', 'quill-accessible-forms' ) . '">';

        // Visual progress bar
        $output .= '<div class="qaf-progress-bar">';
        $output .= '<div class="qaf-progress-fill" style="width: ' . ( 100 / $total_pages ) . '%"></div>';
        $output .= '</div>';

        // Step indicators
        $output .= '<ol class="qaf-progress-steps">';

        foreach ( $pages as $index => $page ) {
            $step_num = $index + 1;
            $step_classes = array( 'qaf-progress-step' );

            if ( $index === 0 ) {
                $step_classes[] = 'qaf-step-current';
            }

            $output .= '<li class="' . esc_attr( implode( ' ', $step_classes ) ) . '" data-step="' . esc_attr( $step_num ) . '">';
            $output .= '<span class="qaf-step-number" aria-hidden="true">' . esc_html( $step_num ) . '</span>';
            $output .= '<span class="qaf-step-title">';

            if ( ! empty( $page['title'] ) ) {
                $output .= esc_html( $page['title'] );
            } else {
                $output .= sprintf( esc_html__( 'Step %d', 'quill-accessible-forms' ), $step_num );
            }

            $output .= '</span>';
            $output .= '</li>';
        }

        $output .= '</ol>';

        // Screen reader status
        $output .= '<div class="screen-reader-text qaf-progress-status">';
        $output .= sprintf( esc_html__( 'Step 1 of %d', 'quill-accessible-forms' ), $total_pages );
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
        $output = '<div class="qaf-page-nav">';

        // Previous button
        if ( $current_page > 1 ) {
            $output .= '<button type="button" class="qaf-nav-prev qaf-button" data-action="prev-page">';
            $output .= '<span aria-hidden="true">&larr;</span> ';
            $output .= esc_html__( 'Previous', 'quill-accessible-forms' );
            $output .= '</button>';
        }

        // Next button (not on last page)
        if ( $current_page < $total_pages ) {
            $output .= '<button type="button" class="qaf-nav-next qaf-button qaf-button-primary" data-action="next-page">';
            $output .= esc_html__( 'Next', 'quill-accessible-forms' );
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
