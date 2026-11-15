<?php
/**
 * Textarea Field Type
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Textarea class
 *
 * Multi-line text (Paragraph) field with WCAG 2.2 AA compliance.
 */
class QAF_Field_Textarea extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'textarea';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'standard';

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'rows'         => 5,
            'cols'         => 50,
            'min_length'   => '',
            'max_length'   => '',
            'show_counter' => false,
            'resize'       => 'vertical', // vertical, horizontal, both, none
            'rich_editor'  => false,
            'allowed_tags' => 'basic', // basic, post
            'autocomplete' => '',
        ) );
    }

    /**
     * Get field settings for admin UI
     *
     * QAF_Field declares this abstract; without it, loading this class is a
     * PHP fatal error on any page that uses a paragraph field.
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'label'         => array(
                    'type'  => 'text',
                    'label' => __( 'Label', 'quill-accessible-forms' ),
                ),
                'description'   => array(
                    'type'  => 'textarea',
                    'label' => __( 'Description', 'quill-accessible-forms' ),
                ),
                'placeholder'   => array(
                    'type'  => 'text',
                    'label' => __( 'Placeholder', 'quill-accessible-forms' ),
                ),
                'default_value' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Default Value', 'quill-accessible-forms' ),
                ),
                'required'      => array(
                    'type'  => 'toggle',
                    'label' => __( 'Required', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'rows'      => array(
                    'type'  => 'number',
                    'label' => __( 'Rows', 'quill-accessible-forms' ),
                    'min'   => 1,
                ),
                'resize'    => array(
                    'type'    => 'select',
                    'label'   => __( 'Resize', 'quill-accessible-forms' ),
                    'options' => array(
                        'vertical'   => __( 'Vertical', 'quill-accessible-forms' ),
                        'horizontal' => __( 'Horizontal', 'quill-accessible-forms' ),
                        'both'       => __( 'Both', 'quill-accessible-forms' ),
                        'none'       => __( 'None', 'quill-accessible-forms' ),
                    ),
                ),
                'css_class' => array(
                    'type'  => 'text',
                    'label' => __( 'CSS Class', 'quill-accessible-forms' ),
                ),
            ),
            'validation' => array(
                'min_length'   => array(
                    'type'  => 'number',
                    'label' => __( 'Minimum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'max_length'   => array(
                    'type'  => 'number',
                    'label' => __( 'Maximum Length', 'quill-accessible-forms' ),
                    'min'   => 0,
                ),
                'show_counter' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Character Counter', 'quill-accessible-forms' ),
                ),
            ),
            'advanced' => array(
                'rich_editor' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Rich Text Editor', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Is the WordPress rich editor in use for this field?
     *
     * @return bool True when rich_editor is on and wp_editor() is available.
     */
    protected function uses_rich_editor() {
        return ! empty( $this->config['rich_editor'] ) && function_exists( 'wp_editor' );
    }

    /**
     * The wp_editor() ID for this field (lowercase letters, digits and underscores only).
     *
     * @return string Editor ID.
     */
    protected function get_editor_id() {
        return strtolower( preg_replace( '/[^A-Za-z0-9_]/', '_', $this->get_html_id() ) );
    }

    /**
     * Render the field
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $value = (string) $this->get_value();

        if ( $this->uses_rich_editor() ) {
            return $this->render_wrapper( $this->render_rich_editor( $value ) );
        }

        $attrs = array(
            'id'          => $this->get_html_id(),
            'name'        => $this->get_input_name(),
            'rows'        => max( 1, absint( $this->config['rows'] ) ),
            'cols'        => max( 1, absint( $this->config['cols'] ) ),
            'class'       => 'qaf-input qaf-input-textarea',
            'placeholder' => $this->config['placeholder'],
        );

        $max_length = absint( $this->config['max_length'] );
        $min_length = absint( $this->config['min_length'] );

        if ( $max_length > 0 ) {
            $attrs['maxlength'] = $max_length;
            // qaf-public.js builds and updates a polite live-region counter for [data-counter].
            $attrs['data-counter'] = 'true';
        }

        if ( $min_length > 0 ) {
            $attrs['minlength'] = $min_length;
        }

        if ( ! empty( $this->config['autocomplete'] ) ) {
            $attrs['autocomplete'] = $this->config['autocomplete'];
        }

        if ( ! empty( $this->config['read_only'] ) ) {
            $attrs['readonly'] = true;
        }

        if ( ! empty( $this->config['disabled'] ) ) {
            $attrs['disabled'] = true;
        }

        $resize = $this->config['resize'];
        if ( in_array( $resize, array( 'vertical', 'horizontal', 'none' ), true ) ) {
            $attrs['style'] = 'resize: ' . $resize . ';';
        }

        // ARIA attributes (required, describedby, invalid, label, readonly, disabled).
        $attrs = array_merge( $attrs, $this->get_aria_attributes() );

        // HTML5 attributes (autofocus, spellcheck, inputmode, enterkeyhint). A textarea has no pattern attribute.
        $html5 = $this->get_html5_attributes();
        unset( $html5['pattern'] );
        $attrs = array_merge( $attrs, $html5 );

        $input = '<textarea ' . $this->build_attributes( $attrs ) . '>' . esc_textarea( $value ) . '</textarea>';

        // With no maximum the script has nothing to count down from, so show the count server-side.
        if ( $max_length === 0 && ! empty( $this->config['show_counter'] ) ) {
            $input .= $this->render_character_counter( $value );
        }

        return $this->render_wrapper( $input );
    }

    /**
     * Render the label, pointing it at the wp_editor() textarea in rich mode
     *
     * @return string Label HTML.
     */
    protected function render_label() {
        $html = parent::render_label();

        if ( $html !== '' && $this->uses_rich_editor() ) {
            $html = str_replace(
                'for="' . esc_attr( $this->get_html_id() ) . '"',
                'for="' . esc_attr( $this->get_editor_id() ) . '"',
                $html
            );
        }

        return $html;
    }

    /**
     * Render the WordPress rich text editor
     *
     * @param string $value Field value.
     * @return string Editor HTML.
     */
    protected function render_rich_editor( $value ) {
        $editor_id = $this->get_editor_id();
        $title     = $this->config['aria_label'] ?: $this->get_label();

        $settings = array(
            'textarea_name' => $this->get_input_name(),
            'textarea_rows' => max( 1, absint( $this->config['rows'] ) ),
            'media_buttons' => false,
            'teeny'         => ( $this->config['allowed_tags'] === 'basic' ),
            'quicktags'     => true,
            'tinymce'       => array(
                'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
            ),
        );

        // Give this editor's iframe an accessible name. Scoped to this editor ID and removed afterwards.
        $title_filter = function ( $init, $id = '' ) use ( $editor_id, $title ) {
            if ( $id === $editor_id && $title !== '' ) {
                $init['init_instance_callback'] = 'function(ed){var f=ed.iframeElement;if(f){f.setAttribute("title",' . wp_json_encode( $title ) . ');}}';
            }
            return $init;
        };

        add_filter( 'tiny_mce_before_init', $title_filter, 10, 2 );
        add_filter( 'teeny_mce_before_init', $title_filter, 10, 2 );

        ob_start();
        wp_editor( $value, $editor_id, $settings );
        $html = ob_get_clean();

        remove_filter( 'tiny_mce_before_init', $title_filter, 10 );
        remove_filter( 'teeny_mce_before_init', $title_filter, 10 );

        // Carry the field's ARIA state onto the underlying textarea (used by the Text tab and without JS).
        // The label's for= is pointed at this textarea by render_label(), so no aria-label is added here.
        $aria_string = $this->build_attributes( $this->get_aria_attributes() );

        if ( $aria_string !== '' ) {
            $html = preg_replace(
                '/(<textarea\b[^>]*\bid="' . preg_quote( $editor_id, '/' ) . '")/',
                '$1 ' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $aria_string ),
                $html,
                1
            );
        }

        return $html;
    }

    /**
     * Render a static character count (used when there is no maximum)
     *
     * @param string $value Current value.
     * @return string Counter HTML.
     */
    protected function render_character_counter( $value ) {
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );

        return sprintf(
            '<div class="qaf-char-counter" id="%1$s" aria-live="polite" aria-atomic="true">%2$s</div>',
            esc_attr( $this->get_html_id() . '-counter' ),
            esc_html( sprintf(
                /* translators: %d: character count */
                _n( '%d character entered', '%d characters entered', $length, 'quill-accessible-forms' ),
                $length
            ) )
        );
    }

    /**
     * Length of a value as the user sees it (tags stripped in rich mode)
     *
     * @param string $value Value.
     * @return int Character count.
     */
    protected function value_length( $value ) {
        $value = (string) $value;

        if ( ! empty( $this->config['rich_editor'] ) ) {
            $value = wp_strip_all_tags( $value );
        }

        return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
    }

    /**
     * Field-specific validation
     *
     * @param mixed $value Value to validate.
     */
    protected function validate_value( $value ) {
        if ( is_array( $value ) ) {
            $this->add_error( __( 'Please enter a valid value.', 'quill-accessible-forms' ) );
            return;
        }

        $length = $this->value_length( $value );

        $min = absint( $this->config['min_length'] );
        if ( $min > 0 && $length < $min ) {
            $this->add_error( sprintf(
                /* translators: %d: minimum character count */
                __( 'Please enter at least %d characters.', 'quill-accessible-forms' ),
                $min
            ) );
        }

        $max = absint( $this->config['max_length'] );
        if ( $max > 0 && $length > $max ) {
            $this->add_error( sprintf(
                /* translators: %d: maximum character count */
                __( 'Please enter no more than %d characters.', 'quill-accessible-forms' ),
                $max
            ) );
        }
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Raw value.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( is_array( $value ) ) {
            return '';
        }

        $value = (string) $value;

        if ( ! empty( $this->config['rich_editor'] ) ) {
            if ( $this->config['allowed_tags'] === 'basic' ) {
                return wp_kses( $value, array(
                    'a'      => array( 'href' => true, 'title' => true ),
                    'br'     => array(),
                    'em'     => array(),
                    'strong' => array(),
                    'b'      => array(),
                    'i'      => array(),
                    'u'      => array(),
                    'p'      => array(),
                    'ul'     => array(),
                    'ol'     => array(),
                    'li'     => array(),
                ) );
            }

            return wp_kses_post( $value );
        }

        return sanitize_textarea_field( $value );
    }

    /**
     * Format value for display, keeping line breaks
     *
     * @param mixed $value Raw value.
     * @return string Formatted value.
     */
    public function format_display( $value ) {
        if ( is_array( $value ) ) {
            return parent::format_display( $value );
        }

        if ( ! empty( $this->config['rich_editor'] ) ) {
            return wp_kses_post( (string) $value );
        }

        return nl2br( esc_html( (string) $value ) );
    }

    /**
     * Format value for export (no HTML line breaks or markup)
     *
     * @param mixed $value Raw value.
     * @return string Export value.
     */
    public function format_export( $value ) {
        if ( is_array( $value ) ) {
            return parent::format_export( $value );
        }

        $value = (string) $value;

        if ( ! empty( $this->config['rich_editor'] ) ) {
            $value = wp_strip_all_tags( $value );
        }

        return esc_html( $value );
    }

    /**
     * Get display value (alias kept for callers of the older API)
     *
     * @param mixed $value Raw value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        return $this->format_display( $value );
    }

    /**
     * Get field schema for JSON export
     *
     * QAF_Field defines no base schema, so this one is self-contained.
     *
     * @return array Field schema.
     */
    public function get_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'rows'         => array(
                    'type'    => 'integer',
                    'default' => 5,
                    'minimum' => 1,
                    'maximum' => 50,
                ),
                'cols'         => array(
                    'type'    => 'integer',
                    'default' => 50,
                    'minimum' => 10,
                    'maximum' => 200,
                ),
                'min_length'   => array(
                    'type'    => 'integer',
                    'default' => 0,
                    'minimum' => 0,
                ),
                'max_length'   => array(
                    'type'    => 'integer',
                    'default' => 0,
                    'minimum' => 0,
                ),
                'show_counter' => array(
                    'type'    => 'boolean',
                    'default' => false,
                ),
                'resize'       => array(
                    'type'    => 'string',
                    'enum'    => array( 'none', 'vertical', 'horizontal', 'both' ),
                    'default' => 'vertical',
                ),
                'rich_editor'  => array(
                    'type'    => 'boolean',
                    'default' => false,
                ),
            ),
        );
    }

    /**
     * Get BFL representation
     *
     * @return string BFL code for this field.
     */
    public function to_bfl() {
        $bfl = 'textarea';

        if ( $this->get_id() ) {
            $bfl .= ' #' . $this->get_id();
        }

        if ( $this->get_label() ) {
            $bfl .= ' "' . addslashes( $this->get_label() ) . '"';
        }

        $modifiers = array();

        if ( $this->is_required() ) {
            $modifiers[] = '@required';
        }

        if ( ! empty( $this->config['placeholder'] ) ) {
            $modifiers[] = '@placeholder "' . addslashes( $this->config['placeholder'] ) . '"';
        }

        $rows = absint( $this->config['rows'] );
        if ( $rows > 0 && $rows !== 5 ) {
            $modifiers[] = '@rows ' . $rows;
        }

        $cols = absint( $this->config['cols'] );
        if ( $cols > 0 && $cols !== 50 ) {
            $modifiers[] = '@cols ' . $cols;
        }

        if ( absint( $this->config['min_length'] ) > 0 ) {
            $modifiers[] = '@minlength ' . absint( $this->config['min_length'] );
        }

        if ( absint( $this->config['max_length'] ) > 0 ) {
            $modifiers[] = '@maxlength ' . absint( $this->config['max_length'] );
        }

        if ( ! empty( $this->config['default_value'] ) ) {
            $modifiers[] = '@default "' . addslashes( $this->config['default_value'] ) . '"';
        }

        if ( $this->get_description() ) {
            $modifiers[] = '@description "' . addslashes( $this->get_description() ) . '"';
        }

        if ( ! empty( $modifiers ) ) {
            $bfl .= "\n  " . implode( "\n  ", $modifiers );
        }

        return $bfl;
    }
}
