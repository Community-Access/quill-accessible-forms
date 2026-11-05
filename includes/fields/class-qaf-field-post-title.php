<?php
/**
 * Post Title Field - WordPress post title
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Title class
 */
class QAF_Field_Post_Title extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_title';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'post';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'label'           => __( 'Title', 'quill-accessible-forms' ),
            'description'     => '',
            'placeholder'     => __( 'Enter title', 'quill-accessible-forms' ),
            'default_value'   => '',
            'min_length'      => 0,
            'max_length'      => 200,
            'show_counter'    => true,
            'generate_slug'   => true,
            'auto_capitalize' => 'words',
            'required'        => true,
            'conditional'     => array(),
            'css_class'       => '',
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
                'label' => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'text',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Length', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
            ),
            'appearance' => array(
                'show_counter' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Character Counter', 'quill-accessible-forms' ),
                ),
                'auto_capitalize' => array(
                    'type'    => 'select',
                    'label'   => __( 'Auto Capitalize', 'quill-accessible-forms' ),
                    'options' => array(
                        'none'      => __( 'None', 'quill-accessible-forms' ),
                        'words'     => __( 'Words (Title Case)', 'quill-accessible-forms' ),
                        'sentences' => __( 'Sentences', 'quill-accessible-forms' ),
                        'all'       => __( 'All Characters', 'quill-accessible-forms' ),
                    ),
                ),
            ),
            'post' => array(
                'generate_slug' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Auto-generate Slug', 'quill-accessible-forms' ),
                    'description' => __( 'Generate post slug from title', 'quill-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Title', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $value = $this->get_value() ?: ( $this->config['default_value'] ?? '' );
        $max_length = (int) ( $this->config['max_length'] ?? 200 );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-title' );
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '">';

        // Label
        $html .= '<label for="' . esc_attr( $html_id ) . '" class="qaf-field-label">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <span class="qaf-required" aria-hidden="true">*</span>';
        }
        $html .= '</label>';

        // Description
        $desc_id = '';
        if ( ! empty( $description ) ) {
            $desc_id = $html_id . '-desc';
            $html .= '<p id="' . esc_attr( $desc_id ) . '" class="qaf-field-description">';
            $html .= esc_html( $description );
            $html .= '</p>';
        }

        // Input
        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        $html .= '<input type="text"';
        $html .= ' id="' . esc_attr( $html_id ) . '"';
        $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
        $html .= ' value="' . esc_attr( $value ) . '"';
        $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
        $html .= ' maxlength="' . esc_attr( $max_length ) . '"';
        $html .= ' class="qaf-input qaf-post-title-input"';
        $html .= ' autocomplete="off"';
        if ( ! empty( $this->config['auto_capitalize'] ) && $this->config['auto_capitalize'] !== 'none' ) {
            $html .= ' autocapitalize="' . esc_attr( $this->config['auto_capitalize'] ) . '"';
        }
        $html .= ' ' . $aria_attrs;
        $html .= '>';

        // Character counter
        if ( ! empty( $this->config['show_counter'] ) ) {
            $html .= '<div class="qaf-char-counter" aria-live="polite">';
            $html .= '<span class="qaf-char-current">' . strlen( $value ) . '</span>';
            $html .= ' / <span class="qaf-char-max">' . esc_html( $max_length ) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Validate the field value
     *
     * @return bool True if valid.
     */
    public function validate() {
        $value = trim( $this->get_value() );
        $min_length = (int) ( $this->config['min_length'] ?? 0 );
        $max_length = (int) ( $this->config['max_length'] ?? 200 );

        if ( ! empty( $this->config['required'] ) && empty( $value ) ) {
            $this->add_error( __( 'Please enter a title.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $value ) ) {
            $length = mb_strlen( $value );

            if ( $min_length > 0 && $length < $min_length ) {
                /* translators: %d: minimum characters */
                $this->add_error( sprintf( __( 'Title must be at least %d characters.', 'quill-accessible-forms' ), $min_length ) );
                return false;
            }

            if ( $length > $max_length ) {
                /* translators: %d: maximum characters */
                $this->add_error( sprintf( __( 'Title cannot exceed %d characters.', 'quill-accessible-forms' ), $max_length ) );
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized title.
     */
    public function sanitize( $value ) {
        return sanitize_text_field( trim( $value ) );
    }

    /**
     * Get post data key
     *
     * @return string Post array key.
     */
    public function get_post_key() {
        return 'post_title';
    }
}
