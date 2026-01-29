<?php
/**
 * BFL Compiler
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_BFL_Compiler class
 * 
 * Compiles form configuration to BFL source code.
 */
class BAF_BFL_Compiler {

    /**
     * Indentation string (2 spaces)
     *
     * @var string
     */
    private $indent = '  ';

    /**
     * Current indentation level
     *
     * @var int
     */
    private $indent_level = 0;

    /**
     * Output lines
     *
     * @var array
     */
    private $output = array();

    /**
     * Compile form configuration to BFL
     *
     * @param array|BAF_Form $form Form configuration or BAF_Form object.
     * @return string BFL source code.
     */
    public function compile( $form ) {
        $this->output = array();
        $this->indent_level = 0;

        // Handle BAF_Form object
        if ( $form instanceof BAF_Form ) {
            $config = array(
                'title'       => $form->title,
                'description' => $form->description,
                'fields'      => $form->get_fields(),
                'pages'       => $form->get_pages(),
                'settings'    => $form->settings,
            );
        } else {
            $config = $form;
        }

        // Compile form declaration
        $this->compile_form_declaration( $config );

        // Compile settings
        if ( ! empty( $config['settings'] ) ) {
            $this->compile_settings( $config['settings'] );
        }

        // Compile pages and fields
        $this->compile_pages( $config );

        return implode( "\n", $this->output );
    }

    /**
     * Compile form declaration
     *
     * @param array $config Form configuration.
     */
    private function compile_form_declaration( $config ) {
        $title = isset( $config['title'] ) ? $config['title'] : 'Untitled Form';
        $this->write_line( 'form "' . $this->escape_string( $title ) . '"' );

        if ( ! empty( $config['description'] ) ) {
            $this->indent_level++;
            $this->write_line( '@description "' . $this->escape_string( $config['description'] ) . '"' );
            $this->indent_level--;
        }

        $this->write_line( '' );
    }

    /**
     * Compile settings block
     *
     * @param array $settings Form settings.
     */
    private function compile_settings( $settings ) {
        $relevant_settings = $this->filter_settings( $settings );

        if ( empty( $relevant_settings ) ) {
            return;
        }

        $this->indent_level++;
        $this->write_line( 'settings' );
        $this->indent_level++;

        foreach ( $relevant_settings as $key => $value ) {
            $this->compile_setting( $key, $value );
        }

        $this->indent_level--;
        $this->indent_level--;
        $this->write_line( '' );
    }

    /**
     * Filter settings to only include non-default values
     *
     * @param array $settings All settings.
     * @return array Filtered settings.
     */
    private function filter_settings( $settings ) {
        $relevant = array();

        $setting_map = array(
            'enable_honeypot'     => 'honeypot',
            'enable_captcha'      => 'captcha',
            'ajax_submission'     => 'ajax',
            'save_continue_enabled' => 'saveAndContinue',
            'rate_limit_enabled'  => 'rateLimit',
        );

        foreach ( $setting_map as $internal => $bfl ) {
            if ( isset( $settings[ $internal ] ) && $settings[ $internal ] ) {
                $relevant[ $bfl ] = $settings[ $internal ];
            }
        }

        return $relevant;
    }

    /**
     * Compile individual setting
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     */
    private function compile_setting( $key, $value ) {
        if ( is_bool( $value ) ) {
            $this->write_line( $key . ' ' . ( $value ? 'true' : 'false' ) );
        } elseif ( is_numeric( $value ) ) {
            $this->write_line( $key . ' ' . $value );
        } elseif ( is_string( $value ) ) {
            $this->write_line( $key . ' "' . $this->escape_string( $value ) . '"' );
        } elseif ( is_array( $value ) ) {
            $this->write_line( $key );
            $this->indent_level++;
            foreach ( $value as $k => $v ) {
                $this->compile_setting( $k, $v );
            }
            $this->indent_level--;
        }
    }

    /**
     * Compile pages and their fields
     *
     * @param array $config Form configuration.
     */
    private function compile_pages( $config ) {
        $fields = isset( $config['fields'] ) ? $config['fields'] : array();
        $pages = isset( $config['pages'] ) ? $config['pages'] : array();

        // Group fields by page
        $fields_by_page = array();
        foreach ( $fields as $field ) {
            $page = isset( $field['page'] ) ? $field['page'] : 'page_1';
            if ( ! isset( $fields_by_page[ $page ] ) ) {
                $fields_by_page[ $page ] = array();
            }
            $fields_by_page[ $page ][] = $field;
        }

        // If multi-page, compile with page declarations
        if ( count( $pages ) > 1 || count( $fields_by_page ) > 1 ) {
            foreach ( $pages as $page ) {
                $this->compile_page( $page, $fields_by_page );
            }
        } else {
            // Single page - compile fields directly
            $this->indent_level++;
            foreach ( $fields as $field ) {
                $this->compile_field( $field );
            }
            $this->indent_level--;
        }
    }

    /**
     * Compile single page
     *
     * @param array $page           Page configuration.
     * @param array $fields_by_page Fields grouped by page.
     */
    private function compile_page( $page, $fields_by_page ) {
        $page_id = isset( $page['id'] ) ? $page['id'] : '';
        $title = isset( $page['title'] ) ? $page['title'] : 'Page';

        $this->indent_level++;
        $this->write_line( 'page "' . $this->escape_string( $title ) . '"' );

        // Compile fields for this page
        if ( isset( $fields_by_page[ $page_id ] ) ) {
            $this->indent_level++;

            // Group fields by section
            $current_section = null;
            foreach ( $fields_by_page[ $page_id ] as $field ) {
                if ( $field['type'] === 'section' ) {
                    // New section
                    if ( $current_section !== null ) {
                        $this->indent_level--;
                    }
                    $this->compile_section( $field );
                    $current_section = $field;
                    $this->indent_level++;
                } else {
                    $this->compile_field( $field );
                }
            }

            if ( $current_section !== null ) {
                $this->indent_level--;
            }

            $this->indent_level--;
        }

        $this->indent_level--;
        $this->write_line( '' );
    }

    /**
     * Compile section
     *
     * @param array $section Section configuration.
     */
    private function compile_section( $section ) {
        $label = isset( $section['label'] ) ? $section['label'] : 'Section';
        $this->write_line( 'section "' . $this->escape_string( $label ) . '"' );

        if ( ! empty( $section['description'] ) ) {
            $this->indent_level++;
            $this->write_line( '@description "' . $this->escape_string( $section['description'] ) . '"' );
            $this->indent_level--;
        }
    }

    /**
     * Compile field
     *
     * @param array $field Field configuration.
     */
    private function compile_field( $field ) {
        if ( $field['type'] === 'section' ) {
            return; // Sections handled separately
        }

        $type = $this->get_bfl_field_type( $field['type'] );
        $id = isset( $field['id'] ) ? $field['id'] : '';
        $label = isset( $field['label'] ) ? $field['label'] : '';

        // Build field line
        $line = $type;

        // Add ID if custom
        if ( $id && ! preg_match( '/^field_\d+$/', $id ) ) {
            $line .= ' #' . $id;
        }

        // Add label
        if ( $label ) {
            $line .= ' "' . $this->escape_string( $label ) . '"';
        }

        $this->write_line( $line );

        // Compile field modifiers
        $this->indent_level++;
        $this->compile_field_modifiers( $field );
        $this->compile_field_choices( $field );
        $this->indent_level--;
    }

    /**
     * Compile field modifiers
     *
     * @param array $field Field configuration.
     */
    private function compile_field_modifiers( $field ) {
        // Required
        if ( ! empty( $field['required'] ) ) {
            $this->write_line( '@required' );
        }

        // Placeholder
        if ( ! empty( $field['placeholder'] ) ) {
            $this->write_line( '@placeholder "' . $this->escape_string( $field['placeholder'] ) . '"' );
        }

        // Default value
        if ( isset( $field['default_value'] ) && $field['default_value'] !== '' ) {
            $this->write_line( '@default "' . $this->escape_string( $field['default_value'] ) . '"' );
        }

        // Description
        if ( ! empty( $field['description'] ) ) {
            $this->write_line( '@description "' . $this->escape_string( $field['description'] ) . '"' );
        }

        // Min/Max for numbers
        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $this->write_line( '@min ' . $field['min'] );
        }
        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $this->write_line( '@max ' . $field['max'] );
        }

        // Min/Max length for text
        if ( isset( $field['min_length'] ) && $field['min_length'] !== '' ) {
            $this->write_line( '@minlength ' . $field['min_length'] );
        }
        if ( isset( $field['max_length'] ) && $field['max_length'] !== '' ) {
            $this->write_line( '@maxlength ' . $field['max_length'] );
        }

        // Rows for textarea
        if ( isset( $field['rows'] ) && $field['rows'] !== '' ) {
            $this->write_line( '@rows ' . $field['rows'] );
        }

        // Read only
        if ( ! empty( $field['read_only'] ) ) {
            $this->write_line( '@readonly' );
        }

        // Disabled
        if ( ! empty( $field['disabled'] ) ) {
            $this->write_line( '@disabled' );
        }

        // CSS class
        if ( ! empty( $field['css_class'] ) ) {
            $this->write_line( '@class "' . $this->escape_string( $field['css_class'] ) . '"' );
        }

        // Input mask
        if ( ! empty( $field['input_mask'] ) ) {
            $this->write_line( '@mask "' . $this->escape_string( $field['input_mask'] ) . '"' );
        }

        // Autocomplete (HTML autocomplete or address autocomplete)
        if ( array_key_exists( 'autocomplete', $field ) ) {
            if ( $field['autocomplete'] === true ) {
                $this->write_line( '@autocomplete' );
            } elseif ( $field['autocomplete'] !== '' ) {
                $this->write_line( '@autocomplete "' . $this->escape_string( $field['autocomplete'] ) . '"' );
            }
        }

        if ( ! empty( $field['autocomplete_provider'] ) ) {
            $this->write_line( '@autocomplete_provider "' . $this->escape_string( $field['autocomplete_provider'] ) . '"' );
        }

        if ( ! empty( $field['autocomplete_countries'] ) && is_array( $field['autocomplete_countries'] ) ) {
            $countries = array_map( array( $this, 'escape_string' ), $field['autocomplete_countries'] );
            $this->write_line( '@autocomplete_countries ["' . implode( '","', $countries ) . '"]' );
        }

        if ( ! empty( $field['autocomplete_types'] ) && is_array( $field['autocomplete_types'] ) ) {
            $types = array_map( array( $this, 'escape_string' ), $field['autocomplete_types'] );
            $this->write_line( '@autocomplete_types ["' . implode( '","', $types ) . '"]' );
        }

        // Smart default
        if ( ! empty( $field['smart_default'] ) ) {
            $this->write_line( '@smart_default "' . $this->escape_string( $field['smart_default'] ) . '"' );
        }

        // Unique validation enhancements
        if ( ! empty( $field['unique_scope'] ) ) {
            $this->write_line( '@unique_scope "' . $this->escape_string( $field['unique_scope'] ) . '"' );
        }
        if ( ! empty( $field['unique_message'] ) ) {
            $this->write_line( '@unique_message "' . $this->escape_string( $field['unique_message'] ) . '"' );
        }
    }

    /**
     * Compile field choices for select/radio/checkbox
     *
     * @param array $field Field configuration.
     */
    private function compile_field_choices( $field ) {
        if ( empty( $field['choices'] ) ) {
            return;
        }

        $choices = $field['choices'];

        // Check for choice library
        if ( isset( $choices['library'] ) ) {
            $this->write_line( '@library ' . $choices['library'] );
            return;
        }

        // Compile inline choices
        foreach ( $choices as $choice ) {
            if ( is_array( $choice ) ) {
                $label = isset( $choice['label'] ) ? $choice['label'] : '';
                $value = isset( $choice['value'] ) ? $choice['value'] : '';
                $price = isset( $choice['price'] ) ? $choice['price'] : '';

                if ( $price ) {
                    $this->write_line( '+ ' . $label . ':' . $price );
                } elseif ( $value && $value !== sanitize_title( $label ) ) {
                    $this->write_line( '+ ' . $value . '|' . $label );
                } else {
                    $this->write_line( '+ ' . $label );
                }
            } else {
                $this->write_line( '+ ' . $choice );
            }
        }
    }

    /**
     * Get BFL field type from internal type
     *
     * @param string $type Internal field type.
     * @return string BFL field type.
     */
    private function get_bfl_field_type( $type ) {
        $type_map = array(
            'text'       => 'text',
            'textarea'   => 'textarea',
            'email'      => 'email',
            'number'     => 'number',
            'phone'      => 'phone',
            'website'    => 'website',
            'password'   => 'password',
            'hidden'     => 'hidden',
            'select'     => 'select',
            'multiselect' => 'multiselect',
            'radio'      => 'radio',
            'checkbox'   => 'checkbox',
            'consent'    => 'consent',
            'date'       => 'date',
            'time'       => 'time',
            'datetime'   => 'datetime',
            'daterange'  => 'daterange',
            'name'       => 'name',
            'address'    => 'address',
            'fileupload' => 'file',
            'signature'  => 'signature',
            'captcha'    => 'captcha',
            'repeater'   => 'repeater',
            'calculation' => 'calculation',
            'rating'     => 'rating',
            'likert'     => 'likert',
            'ranking'    => 'ranking',
            'nps'        => 'nps',
            'html'       => 'html',
            'submit'     => 'submit',
            'columns'    => 'columns',
            'toggle'     => 'toggle',
            'range'      => 'range',
            'color'      => 'color',
            'page'       => 'page',
        );

        return isset( $type_map[ $type ] ) ? $type_map[ $type ] : $type;
    }

    /**
     * Write indented line
     *
     * @param string $line Line content.
     */
    private function write_line( $line ) {
        if ( $line === '' ) {
            $this->output[] = '';
        } else {
            $this->output[] = str_repeat( $this->indent, $this->indent_level ) . $line;
        }
    }

    /**
     * Escape string for BFL output
     *
     * @param string $str String to escape.
     * @return string Escaped string.
     */
    private function escape_string( $str ) {
        return str_replace(
            array( '\\', '"', "\n", "\t" ),
            array( '\\\\', '\\"', '\\n', '\\t' ),
            $str
        );
    }

    /**
     * Format/pretty-print BFL code
     *
     * @param string $bfl BFL source code.
     * @return string Formatted BFL code.
     */
    public static function format( $bfl ) {
        $lines = explode( "\n", $bfl );
        $formatted = array();
        $indent_level = 0;
        $indent = '  ';

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            if ( empty( $trimmed ) ) {
                $formatted[] = '';
                continue;
            }

            // Check if line should decrease indent (doesn't start a new block)
            $keywords_that_start_block = array( 'form', 'settings', 'page', 'section', 'columns', 'column', 'conditional' );
            $starts_block = false;
            foreach ( $keywords_that_start_block as $keyword ) {
                if ( strpos( $trimmed, $keyword ) === 0 ) {
                    $starts_block = true;
                    break;
                }
            }

            // Determine indentation
            if ( $starts_block && $indent_level > 0 && strpos( $trimmed, 'form' ) !== 0 ) {
                // Keep current indent for blocks
            }

            $formatted[] = str_repeat( $indent, $indent_level ) . $trimmed;

            // Increase indent after block starters
            if ( $starts_block ) {
                $indent_level++;
            }
        }

        return implode( "\n", $formatted );
    }
}
