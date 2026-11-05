<?php
/**
 * Rich Text Editor Field
 *
 * WYSIWYG content input with accessible toolbar.
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Rich_Text class
 */
class QAF_Field_Rich_Text extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'rich_text';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'advanced';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'editor_height'   => 300,
            'toolbar'         => 'standard',    // 'minimal', 'standard', 'full'
            'media_buttons'   => false,
            'allow_html'      => true,
            'allowed_tags'    => array( 'p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4' ),
            'max_chars'       => 0,             // 0 = unlimited
            'show_char_count' => true,
            'placeholder'     => '',
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
                'placeholder' => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder Text', 'quill-accessible-forms' ),
                ),
                'required' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'editor' => array(
                'toolbar' => array(
                    'type'    => 'select',
                    'label'   => __( 'Toolbar', 'quill-accessible-forms' ),
                    'options' => array(
                        'minimal'  => __( 'Minimal (Bold, Italic, Link)', 'quill-accessible-forms' ),
                        'standard' => __( 'Standard (+ Lists, Headings)', 'quill-accessible-forms' ),
                        'full'     => __( 'Full (All formatting options)', 'quill-accessible-forms' ),
                    ),
                ),
                'editor_height' => array(
                    'type'  => 'number',
                    'label' => __( 'Editor Height (px)', 'quill-accessible-forms' ),
                    'min'   => 150,
                    'max'   => 800,
                ),
                'media_buttons' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Media Upload Button', 'quill-accessible-forms' ),
                    'desc'  => __( 'Allow users to insert images', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'max_chars' => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Characters', 'quill-accessible-forms' ),
                    'desc'  => __( '0 for unlimited', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'show_char_count' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Character Count', 'quill-accessible-forms' ),
                ),
            ),
            'security' => array(
                'allow_html' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Allow HTML Output', 'quill-accessible-forms' ),
                    'desc'  => __( 'If disabled, HTML will be stripped on save', 'quill-accessible-forms' ),
                ),
                'allowed_tags' => array(
                    'type'    => 'multiselect',
                    'label'   => __( 'Allowed HTML Tags', 'quill-accessible-forms' ),
                    'options' => array(
                        'p'          => 'Paragraph (p)',
                        'br'         => 'Line Break (br)',
                        'strong'     => 'Bold (strong)',
                        'em'         => 'Italic (em)',
                        'u'          => 'Underline (u)',
                        'a'          => 'Link (a)',
                        'ul'         => 'Unordered List (ul)',
                        'ol'         => 'Ordered List (ol)',
                        'li'         => 'List Item (li)',
                        'blockquote' => 'Blockquote',
                        'h2'         => 'Heading 2 (h2)',
                        'h3'         => 'Heading 3 (h3)',
                        'h4'         => 'Heading 4 (h4)',
                        'img'        => 'Image (img)',
                        'table'      => 'Table (table)',
                        'tr'         => 'Table Row (tr)',
                        'td'         => 'Table Cell (td)',
                        'th'         => 'Table Header (th)',
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
        $html_id      = $this->get_html_id();
        $name         = 'qaf_field_' . $this->get_id();
        $value        = $this->get_value();
        $height       = (int) ( $this->config['editor_height'] ?? 300 );
        $toolbar      = $this->config['toolbar'] ?? 'standard';
        $media        = ! empty( $this->config['media_buttons'] );
        $max_chars    = (int) ( $this->config['max_chars'] ?? 0 );
        $show_count   = $this->config['show_char_count'] ?? true;
        $placeholder  = $this->config['placeholder'] ?? '';

        // Get toolbar buttons based on level
        $toolbar_buttons = $this->get_toolbar_buttons( $toolbar );

        $wrapper_class = $this->get_wrapper_classes();
        $wrapper_class[] = 'qaf-rich-text-wrapper';

        $field = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $field .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $field .= ' data-max-chars="' . esc_attr( $max_chars ) . '">';

        // Label
        $field .= $this->render_label();

        // Description above
        if ( $this->get_description_placement() === 'above' ) {
            $field .= $this->render_description();
        }

        // Editor container
        $field .= '<div class="qaf-rich-text-container">';

        // Accessible toolbar
        $field .= '<div class="qaf-rich-text-toolbar" role="toolbar" aria-label="' . esc_attr__( 'Text formatting', 'quill-accessible-forms' ) . '">';
        $field .= $this->render_toolbar( $toolbar_buttons, $html_id );
        $field .= '</div>';

        // Editable area
        $field .= '<div id="' . esc_attr( $html_id ) . '-editor" ';
        $field .= 'class="qaf-rich-text-editor" ';
        $field .= 'contenteditable="true" ';
        $field .= 'role="textbox" ';
        $field .= 'aria-multiline="true" ';
        $field .= 'aria-labelledby="' . esc_attr( $html_id ) . '-label" ';
        if ( $placeholder ) {
            $field .= 'data-placeholder="' . esc_attr( $placeholder ) . '" ';
        }
        $field .= 'style="min-height: ' . esc_attr( $height ) . 'px;">';
        $field .= wp_kses_post( $value );
        $field .= '</div>';

        // Hidden textarea for form submission
        $field .= '<textarea id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $name ) . '" class="qaf-rich-text-value screen-reader-text" aria-hidden="true">';
        $field .= esc_textarea( $value );
        $field .= '</textarea>';

        // Character count
        if ( $show_count ) {
            $current_count = strlen( wp_strip_all_tags( $value ) );
            $field .= '<div class="qaf-rich-text-footer">';
            $field .= '<span class="qaf-char-count" aria-live="polite">';
            if ( $max_chars > 0 ) {
                $field .= sprintf(
                    /* translators: 1: current count, 2: max count */
                    __( '%1$d / %2$d characters', 'quill-accessible-forms' ),
                    $current_count,
                    $max_chars
                );
            } else {
                $field .= sprintf(
                    /* translators: %d: character count */
                    __( '%d characters', 'quill-accessible-forms' ),
                    $current_count
                );
            }
            $field .= '</span>';
            $field .= '</div>';
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

        $field .= '</div>';

        return $field;
    }

    /**
     * Get toolbar buttons for level
     *
     * @param string $level Toolbar level.
     * @return array Toolbar button groups.
     */
    protected function get_toolbar_buttons( $level ) {
        $minimal = array(
            'format' => array(
                array( 'id' => 'bold', 'label' => __( 'Bold', 'quill-accessible-forms' ), 'icon' => 'editor-bold', 'command' => 'bold' ),
                array( 'id' => 'italic', 'label' => __( 'Italic', 'quill-accessible-forms' ), 'icon' => 'editor-italic', 'command' => 'italic' ),
            ),
            'link' => array(
                array( 'id' => 'link', 'label' => __( 'Insert Link', 'quill-accessible-forms' ), 'icon' => 'admin-links', 'command' => 'createLink' ),
                array( 'id' => 'unlink', 'label' => __( 'Remove Link', 'quill-accessible-forms' ), 'icon' => 'editor-unlink', 'command' => 'unlink' ),
            ),
        );

        if ( $level === 'minimal' ) {
            return $minimal;
        }

        $standard = array_merge( $minimal, array(
            'list' => array(
                array( 'id' => 'ul', 'label' => __( 'Bullet List', 'quill-accessible-forms' ), 'icon' => 'editor-ul', 'command' => 'insertUnorderedList' ),
                array( 'id' => 'ol', 'label' => __( 'Numbered List', 'quill-accessible-forms' ), 'icon' => 'editor-ol', 'command' => 'insertOrderedList' ),
            ),
            'heading' => array(
                array( 'id' => 'h2', 'label' => __( 'Heading 2', 'quill-accessible-forms' ), 'text' => 'H2', 'command' => 'formatBlock', 'value' => 'h2' ),
                array( 'id' => 'h3', 'label' => __( 'Heading 3', 'quill-accessible-forms' ), 'text' => 'H3', 'command' => 'formatBlock', 'value' => 'h3' ),
            ),
        ) );

        if ( $level === 'standard' ) {
            return $standard;
        }

        // Full toolbar
        return array_merge( $standard, array(
            'format_extra' => array(
                array( 'id' => 'underline', 'label' => __( 'Underline', 'quill-accessible-forms' ), 'icon' => 'editor-underline', 'command' => 'underline' ),
                array( 'id' => 'strikethrough', 'label' => __( 'Strikethrough', 'quill-accessible-forms' ), 'icon' => 'editor-strikethrough', 'command' => 'strikeThrough' ),
            ),
            'block' => array(
                array( 'id' => 'blockquote', 'label' => __( 'Blockquote', 'quill-accessible-forms' ), 'icon' => 'editor-quote', 'command' => 'formatBlock', 'value' => 'blockquote' ),
                array( 'id' => 'paragraph', 'label' => __( 'Paragraph', 'quill-accessible-forms' ), 'text' => '¶', 'command' => 'formatBlock', 'value' => 'p' ),
            ),
            'align' => array(
                array( 'id' => 'alignleft', 'label' => __( 'Align Left', 'quill-accessible-forms' ), 'icon' => 'editor-alignleft', 'command' => 'justifyLeft' ),
                array( 'id' => 'aligncenter', 'label' => __( 'Align Center', 'quill-accessible-forms' ), 'icon' => 'editor-aligncenter', 'command' => 'justifyCenter' ),
                array( 'id' => 'alignright', 'label' => __( 'Align Right', 'quill-accessible-forms' ), 'icon' => 'editor-alignright', 'command' => 'justifyRight' ),
            ),
            'history' => array(
                array( 'id' => 'undo', 'label' => __( 'Undo', 'quill-accessible-forms' ), 'icon' => 'undo', 'command' => 'undo' ),
                array( 'id' => 'redo', 'label' => __( 'Redo', 'quill-accessible-forms' ), 'icon' => 'redo', 'command' => 'redo' ),
            ),
        ) );
    }

    /**
     * Render toolbar HTML
     *
     * @param array  $groups   Button groups.
     * @param string $field_id Field ID.
     * @return string Toolbar HTML.
     */
    protected function render_toolbar( $groups, $field_id ) {
        $html = '';

        foreach ( $groups as $group_id => $buttons ) {
            $html .= '<div class="qaf-toolbar-group" role="group" aria-label="' . esc_attr( ucfirst( $group_id ) ) . '">';

            foreach ( $buttons as $button ) {
                $data_attrs = 'data-command="' . esc_attr( $button['command'] ) . '"';
                if ( isset( $button['value'] ) ) {
                    $data_attrs .= ' data-value="' . esc_attr( $button['value'] ) . '"';
                }

                $html .= '<button type="button" class="qaf-toolbar-btn" ';
                $html .= 'id="' . esc_attr( $field_id . '-' . $button['id'] ) . '" ';
                $html .= 'aria-label="' . esc_attr( $button['label'] ) . '" ';
                $html .= 'title="' . esc_attr( $button['label'] ) . '" ';
                $html .= $data_attrs . '>';

                if ( isset( $button['icon'] ) ) {
                    $html .= '<span class="dashicons dashicons-' . esc_attr( $button['icon'] ) . '" aria-hidden="true"></span>';
                } elseif ( isset( $button['text'] ) ) {
                    $html .= '<span class="qaf-toolbar-text">' . esc_html( $button['text'] ) . '</span>';
                }

                $html .= '</button>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( empty( $this->config['allow_html'] ) ) {
            return sanitize_textarea_field( wp_strip_all_tags( $value ) );
        }

        $allowed_tags = $this->config['allowed_tags'] ?? array( 'p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li' );
        
        // Build allowed HTML array for wp_kses
        $allowed_html = array();
        foreach ( $allowed_tags as $tag ) {
            switch ( $tag ) {
                case 'a':
                    $allowed_html['a'] = array(
                        'href'   => true,
                        'title'  => true,
                        'target' => true,
                        'rel'    => true,
                    );
                    break;
                case 'img':
                    $allowed_html['img'] = array(
                        'src'    => true,
                        'alt'    => true,
                        'width'  => true,
                        'height' => true,
                        'class'  => true,
                    );
                    break;
                default:
                    $allowed_html[ $tag ] = array(
                        'class' => true,
                        'id'    => true,
                    );
            }
        }

        return wp_kses( $value, $allowed_html );
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        $max_chars = (int) ( $this->config['max_chars'] ?? 0 );
        
        if ( $max_chars > 0 ) {
            $char_count = strlen( wp_strip_all_tags( $value ) );
            if ( $char_count > $max_chars ) {
                $this->add_error( sprintf(
                    /* translators: 1: current count, 2: max count */
                    __( 'Content exceeds maximum length (%1$d / %2$d characters).', 'quill-accessible-forms' ),
                    $char_count,
                    $max_chars
                ) );
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
        return wp_kses_post( $value );
    }
}
