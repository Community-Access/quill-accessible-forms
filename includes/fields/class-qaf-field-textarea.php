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
 */
class QAF_Field_Textarea extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'textarea';

    /**
     * Get default settings
     *
     * @return array Default settings.
     */
    protected function get_default_settings() {
        return array(
            'rows'            => 5,
            'cols'            => 50,
            'min_length'      => 0,
            'max_length'      => 0,
            'show_counter'    => false,
            'resize'          => 'vertical',
            'rich_editor'     => false,
            'allowed_tags'    => 'basic',
        );
    }

    /**
     * Render field HTML
     *
     * @param mixed $value Field value.
     * @return string Field HTML.
     */
    public function render( $value = null ) {
        $value = $value ?? $this->get_setting( 'default_value', '' );
        $id    = $this->get_id();
        $name  = $this->get_name();

        $aria = $this->get_aria_attributes();

        $attrs = array(
            'id'          => esc_attr( $id ),
            'name'        => esc_attr( $name ),
            'rows'        => absint( $this->get_setting( 'rows', 5 ) ),
            'cols'        => absint( $this->get_setting( 'cols', 50 ) ),
            'class'       => 'qaf-field-textarea',
            'aria-label'  => esc_attr( $aria['aria-label'] ),
        );

        if ( $aria['aria-describedby'] ) {
            $attrs['aria-describedby'] = esc_attr( $aria['aria-describedby'] );
        }

        if ( $aria['aria-required'] ) {
            $attrs['aria-required'] = 'true';
            $attrs['required']      = 'required';
        }

        if ( $aria['aria-invalid'] ) {
            $attrs['aria-invalid'] = 'true';
        }

        if ( $this->get_setting( 'placeholder' ) ) {
            $attrs['placeholder'] = esc_attr( $this->get_setting( 'placeholder' ) );
        }

        if ( $this->get_setting( 'max_length' ) > 0 ) {
            $attrs['maxlength'] = absint( $this->get_setting( 'max_length' ) );
        }

        if ( $this->get_setting( 'min_length' ) > 0 ) {
            $attrs['minlength'] = absint( $this->get_setting( 'min_length' ) );
        }

        if ( $this->get_setting( 'read_only' ) ) {
            $attrs['readonly'] = 'readonly';
        }

        if ( $this->get_setting( 'disabled' ) ) {
            $attrs['disabled'] = 'disabled';
        }

        // Resize style
        $resize = $this->get_setting( 'resize', 'vertical' );
        if ( $resize !== 'both' ) {
            $attrs['style'] = 'resize: ' . esc_attr( $resize ) . ';';
        }

        // Build attributes string
        $attr_string = '';
        foreach ( $attrs as $key => $val ) {
            $attr_string .= sprintf( ' %s="%s"', $key, $val );
        }

        $html = $this->render_wrapper_start();

        // Rich text editor mode
        if ( $this->get_setting( 'rich_editor' ) ) {
            $html .= $this->render_rich_editor( $value, $attrs );
        } else {
            $html .= sprintf(
                '<textarea%s>%s</textarea>',
                $attr_string,
                esc_textarea( $value )
            );
        }

        // Character counter
        if ( $this->get_setting( 'show_counter' ) || $this->get_setting( 'max_length' ) > 0 ) {
            $html .= $this->render_character_counter( $value );
        }

        $html .= $this->render_wrapper_end();

        return $html;
    }

    /**
     * Render rich text editor
     *
     * @param string $value  Field value.
     * @param array  $attrs  Attributes array.
     * @return string Editor HTML.
     */
    protected function render_rich_editor( $value, $attrs ) {
        ob_start();

        $editor_id = str_replace( '-', '_', $attrs['id'] );

        $settings = array(
            'textarea_name' => $attrs['name'],
            'textarea_rows' => $attrs['rows'],
            'media_buttons' => false,
            'teeny'         => ( $this->get_setting( 'allowed_tags' ) === 'basic' ),
            'quicktags'     => true,
            'tinymce'       => array(
                'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                'toolbar2' => '',
            ),
        );

        // Add accessibility attributes to TinyMCE
        add_filter( 'tiny_mce_before_init', function( $init ) use ( $attrs ) {
            $init['init_instance_callback'] = 'function(ed) {
                ed.on("init", function() {
                    var iframe = ed.iframeElement;
                    if (iframe) {
                        iframe.setAttribute("title", "' . esc_js( $attrs['aria-label'] ) . '");
                    }
                });
            }';
            return $init;
        });

        wp_editor( $value, $editor_id, $settings );

        return ob_get_clean();
    }

    /**
     * Render character counter
     *
     * @param string $value Current value.
     * @return string Counter HTML.
     */
    protected function render_character_counter( $value ) {
        $max    = $this->get_setting( 'max_length', 0 );
        $length = mb_strlen( $value );

        $html = sprintf(
            '<div class="qaf-character-counter" aria-live="polite" aria-atomic="true">
                <span class="qaf-counter-current">%d</span>',
            $length
        );

        if ( $max > 0 ) {
            $html .= sprintf(
                '<span class="qaf-counter-separator">/</span>
                <span class="qaf-counter-max">%d</span>
                <span class="qaf-sr-only">%s</span>',
                $max,
                /* translators: %1$d: current count, %2$d: maximum count */
                sprintf( __( '%1$d of %2$d characters used', 'quill-accessible-forms' ), $length, $max )
            );
        } else {
            $html .= sprintf(
                '<span class="qaf-sr-only">%s</span>',
                /* translators: %d: character count */
                sprintf( __( '%d characters entered', 'quill-accessible-forms' ), $length )
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Validate field value
     *
     * @param mixed $value Value to validate.
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public function validate( $value ) {
        // Parent validation (required check)
        $result = parent::validate( $value );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( empty( $value ) ) {
            return true;
        }

        $value  = (string) $value;
        $length = mb_strlen( $value );

        // Min length
        $min_length = $this->get_setting( 'min_length', 0 );
        if ( $min_length > 0 && $length < $min_length ) {
            return new \WP_Error(
                'min_length',
                sprintf(
                    /* translators: %d: minimum character count */
                    __( 'Please enter at least %d characters.', 'quill-accessible-forms' ),
                    $min_length
                )
            );
        }

        // Max length
        $max_length = $this->get_setting( 'max_length', 0 );
        if ( $max_length > 0 && $length > $max_length ) {
            return new \WP_Error(
                'max_length',
                sprintf(
                    /* translators: %d: maximum character count */
                    __( 'Please enter no more than %d characters.', 'quill-accessible-forms' ),
                    $max_length
                )
            );
        }

        return true;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Raw value.
     * @return string Sanitized value.
     */
    public function sanitize( $value ) {
        if ( $this->get_setting( 'rich_editor' ) ) {
            $allowed_tags = $this->get_setting( 'allowed_tags', 'basic' );

            if ( $allowed_tags === 'basic' ) {
                $allowed = array(
                    'a'      => array( 'href' => true, 'title' => true ),
                    'br'     => array(),
                    'em'     => array(),
                    'strong' => array(),
                    'b'      => array(),
                    'i'      => array(),
                    'p'      => array(),
                    'ul'     => array(),
                    'ol'     => array(),
                    'li'     => array(),
                );
                return wp_kses( $value, $allowed );
            }

            return wp_kses_post( $value );
        }

        return sanitize_textarea_field( $value );
    }

    /**
     * Get display value
     *
     * @param mixed $value Raw value.
     * @return string Display value.
     */
    public function get_display_value( $value ) {
        if ( $this->get_setting( 'rich_editor' ) ) {
            return wp_kses_post( $value );
        }
        return nl2br( esc_html( $value ) );
    }

    /**
     * Get field schema for JSON export
     *
     * @return array Field schema.
     */
    public function get_schema() {
        $schema = parent::get_schema();

        $schema['properties']['rows'] = array(
            'type'    => 'integer',
            'default' => 5,
            'minimum' => 1,
            'maximum' => 50,
        );

        $schema['properties']['cols'] = array(
            'type'    => 'integer',
            'default' => 50,
            'minimum' => 10,
            'maximum' => 200,
        );

        $schema['properties']['min_length'] = array(
            'type'    => 'integer',
            'default' => 0,
            'minimum' => 0,
        );

        $schema['properties']['max_length'] = array(
            'type'    => 'integer',
            'default' => 0,
            'minimum' => 0,
        );

        $schema['properties']['show_counter'] = array(
            'type'    => 'boolean',
            'default' => false,
        );

        $schema['properties']['resize'] = array(
            'type'    => 'string',
            'enum'    => array( 'none', 'vertical', 'horizontal', 'both' ),
            'default' => 'vertical',
        );

        $schema['properties']['rich_editor'] = array(
            'type'    => 'boolean',
            'default' => false,
        );

        return $schema;
    }

    /**
     * Convert to BFL
     *
     * @return string BFL representation.
     */
    public function to_bfl() {
        $bfl = 'textarea';

        if ( $this->id !== 'field_' . $this->get_setting( 'field_number', '' ) ) {
            $bfl .= ' #' . $this->id;
        }

        if ( $this->get_setting( 'label' ) ) {
            $bfl .= ' "' . $this->get_setting( 'label' ) . '"';
        }

        // Modifiers
        $modifiers = array();

        if ( $this->get_setting( 'required' ) ) {
            $modifiers[] = '@required';
        }

        if ( $this->get_setting( 'placeholder' ) ) {
            $modifiers[] = '@placeholder "' . $this->get_setting( 'placeholder' ) . '"';
        }

        if ( $this->get_setting( 'rows', 5 ) !== 5 ) {
            $modifiers[] = '@rows ' . $this->get_setting( 'rows' );
        }

        if ( $this->get_setting( 'min_length', 0 ) > 0 ) {
            $modifiers[] = '@minlength ' . $this->get_setting( 'min_length' );
        }

        if ( $this->get_setting( 'max_length', 0 ) > 0 ) {
            $modifiers[] = '@maxlength ' . $this->get_setting( 'max_length' );
        }

        if ( $this->get_setting( 'description' ) ) {
            $modifiers[] = '@description "' . $this->get_setting( 'description' ) . '"';
        }

        if ( ! empty( $modifiers ) ) {
            $bfl .= "\n    " . implode( "\n    ", $modifiers );
        }

        return $bfl;
    }
}
