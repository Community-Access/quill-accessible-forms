<?php
/**
 * Post Content Field - WordPress post content/body
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Post_Content class
 */
class QAF_Field_Post_Content extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'post_content';

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
            'label'         => __( 'Content', 'quill-accessible-forms' ),
            'description'   => '',
            'placeholder'   => __( 'Enter content...', 'quill-accessible-forms' ),
            'default_value' => '',
            'editor_type'   => 'textarea',
            'rows'          => 10,
            'min_length'    => 0,
            'max_length'    => 0,
            'allow_html'    => false,
            'allowed_tags'  => 'p,br,strong,em,ul,ol,li,a,h2,h3,h4,blockquote',
            'media_buttons' => false,
            'required'      => false,
            'conditional'   => array(),
            'css_class'     => '',
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
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'editor' => array(
                'editor_type' => array(
                    'type'    => 'select',
                    'label'   => __( 'Editor Type', 'quill-accessible-forms' ),
                    'options' => array(
                        'textarea' => __( 'Plain Textarea', 'quill-accessible-forms' ),
                        'rich'     => __( 'Rich Text (TinyMCE)', 'quill-accessible-forms' ),
                        'markdown' => __( 'Markdown', 'quill-accessible-forms' ),
                        'basic'    => __( 'Basic Formatting', 'quill-accessible-forms' ),
                    ),
                ),
                'rows' => array(
                    'type'  => 'number',
                    'label' => __( 'Rows', 'quill-accessible-forms' ),
                    'min'   => 3,
                    'max'   => 30,
                ),
                'media_buttons' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Show Media Buttons', 'quill-accessible-forms' ),
                    'condition' => array( 'editor_type' => 'rich' ),
                ),
            ),
            'html' => array(
                'allow_html' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow HTML', 'quill-accessible-forms' ),
                ),
                'allowed_tags' => array(
                    'type'        => 'text',
                    'label'       => __( 'Allowed HTML Tags', 'quill-accessible-forms' ),
                    'description' => __( 'Comma-separated list of allowed tags', 'quill-accessible-forms' ),
                    'condition'   => array( 'allow_html' => true ),
                ),
            ),
            'validation' => array(
                'min_length' => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_length' => array(
                    'type'        => 'number',
                    'label'       => __( 'Maximum Length', 'quill-accessible-forms' ),
                    'min'         => 0,
                    'description' => __( '0 for unlimited', 'quill-accessible-forms' ),
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
        $label = $this->config['label'] ?? __( 'Content', 'quill-accessible-forms' );
        $description = $this->config['description'] ?? '';
        $placeholder = $this->config['placeholder'] ?? '';
        $value = $this->get_value() ?: ( $this->config['default_value'] ?? '' );
        $editor_type = $this->config['editor_type'] ?? 'textarea';
        $rows = (int) ( $this->config['rows'] ?? 10 );
        $required = ! empty( $this->config['required'] );

        $wrapper_class = array( 'qaf-field', 'qaf-field-post-content', 'qaf-editor-' . $editor_type );
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

        $aria_attrs = $required ? 'aria-required="true"' : '';
        if ( $desc_id ) {
            $aria_attrs .= ' aria-describedby="' . esc_attr( $desc_id ) . '"';
        }

        // Basic formatting toolbar
        if ( $editor_type === 'basic' ) {
            $html .= '<div class="qaf-basic-toolbar" role="toolbar" aria-label="' . esc_attr__( 'Formatting options', 'quill-accessible-forms' ) . '">';
            $html .= '<button type="button" data-format="bold" title="' . esc_attr__( 'Bold', 'quill-accessible-forms' ) . '"><strong>B</strong></button>';
            $html .= '<button type="button" data-format="italic" title="' . esc_attr__( 'Italic', 'quill-accessible-forms' ) . '"><em>I</em></button>';
            $html .= '<button type="button" data-format="link" title="' . esc_attr__( 'Link', 'quill-accessible-forms' ) . '">🔗</button>';
            $html .= '<button type="button" data-format="list" title="' . esc_attr__( 'List', 'quill-accessible-forms' ) . '">• List</button>';
            $html .= '</div>';
        }

        // Markdown hint
        if ( $editor_type === 'markdown' ) {
            $html .= '<p class="qaf-markdown-hint">';
            $html .= esc_html__( 'Markdown supported: **bold**, *italic*, [link](url), - list', 'quill-accessible-forms' );
            $html .= '</p>';
        }

        if ( $editor_type === 'rich' && function_exists( 'wp_editor' ) ) {
            // Use WordPress editor
            ob_start();
            wp_editor( $value, $html_id, array(
                'textarea_name' => 'qaf_field_' . $this->get_id(),
                'textarea_rows' => $rows,
                'media_buttons' => ! empty( $this->config['media_buttons'] ),
                'teeny'         => true,
                'quicktags'     => true,
            ) );
            $html .= ob_get_clean();
        } else {
            // Plain textarea
            $html .= '<textarea';
            $html .= ' id="' . esc_attr( $html_id ) . '"';
            $html .= ' name="qaf_field_' . esc_attr( $this->get_id() ) . '"';
            $html .= ' rows="' . esc_attr( $rows ) . '"';
            $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
            $html .= ' class="qaf-textarea qaf-post-content-input"';
            $html .= ' ' . $aria_attrs;
            $html .= '>' . esc_textarea( $value ) . '</textarea>';
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
        $value = $this->get_value();
        $text = wp_strip_all_tags( $value );
        $min_length = (int) ( $this->config['min_length'] ?? 0 );
        $max_length = (int) ( $this->config['max_length'] ?? 0 );

        if ( ! empty( $this->config['required'] ) && empty( trim( $text ) ) ) {
            $this->add_error( __( 'Please enter content.', 'quill-accessible-forms' ) );
            return false;
        }

        if ( ! empty( $text ) ) {
            $length = mb_strlen( $text );

            if ( $min_length > 0 && $length < $min_length ) {
                /* translators: %d: minimum characters */
                $this->add_error( sprintf( __( 'Content must be at least %d characters.', 'quill-accessible-forms' ), $min_length ) );
                return false;
            }

            if ( $max_length > 0 && $length > $max_length ) {
                /* translators: %d: maximum characters */
                $this->add_error( sprintf( __( 'Content cannot exceed %d characters.', 'quill-accessible-forms' ), $max_length ) );
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize the field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized content.
     */
    public function sanitize( $value ) {
        if ( ! empty( $this->config['allow_html'] ) ) {
            $allowed = $this->config['allowed_tags'] ?? '';
            $tags = array_map( 'trim', explode( ',', $allowed ) );
            $allowed_html = array();
            foreach ( $tags as $tag ) {
                $allowed_html[ $tag ] = array(
                    'href'   => array(),
                    'title'  => array(),
                    'target' => array(),
                    'class'  => array(),
                );
            }
            return wp_kses( $value, $allowed_html );
        }
        return sanitize_textarea_field( $value );
    }

    /**
     * Get post data key
     *
     * @return string Post array key.
     */
    public function get_post_key() {
        return 'post_content';
    }
}
