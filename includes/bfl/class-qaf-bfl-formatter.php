<?php
/**
 * BFL Formatter
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_BFL_Formatter class
 * 
 * Pretty-prints and formats BFL source code with proper indentation,
 * consistent styling, and syntax highlighting data.
 */
class QAF_BFL_Formatter {

    /**
     * Indentation string
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
     * Use tabs instead of spaces
     *
     * @var bool
     */
    private $use_tabs = false;

    /**
     * Add blank lines between sections
     *
     * @var bool
     */
    private $add_spacing = true;

    /**
     * Align property values
     *
     * @var bool
     */
    private $align_values = true;

    /**
     * Maximum line length for wrapping
     *
     * @var int
     */
    private $max_line_length = 80;

    /**
     * Format BFL source code
     *
     * @param string $bfl     BFL source code.
     * @param array  $options Formatting options.
     * @return string Formatted BFL code.
     */
    public function format( $bfl, $options = array() ) {
        // Apply options
        if ( isset( $options['use_tabs'] ) ) {
            $this->use_tabs = $options['use_tabs'];
            $this->indent = $this->use_tabs ? "\t" : '  ';
        }
        if ( isset( $options['indent_size'] ) && ! $this->use_tabs ) {
            $this->indent = str_repeat( ' ', intval( $options['indent_size'] ) );
        }
        if ( isset( $options['add_spacing'] ) ) {
            $this->add_spacing = $options['add_spacing'];
        }
        if ( isset( $options['align_values'] ) ) {
            $this->align_values = $options['align_values'];
        }
        if ( isset( $options['max_line_length'] ) ) {
            $this->max_line_length = $options['max_line_length'];
        }

        // Parse and re-compile for consistent formatting
        $parser = new QAF_BFL_Parser();
        $result = $parser->parse( $bfl );

        if ( ! empty( $result['errors'] ) ) {
            // Return original with basic cleanup if parsing fails
            return $this->basic_format( $bfl );
        }

        // Compile back to BFL with formatting
        return $this->compile_formatted( $result['form'] );
    }

    /**
     * Basic formatting without parsing
     *
     * @param string $bfl BFL source code.
     * @return string Formatted code.
     */
    private function basic_format( $bfl ) {
        $lines = explode( "\n", $bfl );
        $formatted = array();
        $indent_level = 0;
        
        $block_starters = array( 'form', 'settings', 'page', 'section', 'columns', 'column', 'conditional', 'if' );
        $field_types = array(
            'text', 'textarea', 'email', 'number', 'phone', 'select', 'radio', 
            'checkbox', 'date', 'time', 'name', 'address', 'file', 'hidden',
            'password', 'url', 'website', 'rating', 'signature', 'captcha',
        );

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Skip empty lines
            if ( empty( $trimmed ) ) {
                if ( ! empty( $formatted ) && end( $formatted ) !== '' ) {
                    $formatted[] = '';
                }
                continue;
            }

            // Skip comments
            if ( strpos( $trimmed, '//' ) === 0 || strpos( $trimmed, '#' ) === 0 ) {
                $formatted[] = str_repeat( $this->indent, $indent_level ) . $trimmed;
                continue;
            }

            // Check for dedent keywords
            $first_word = strtok( $trimmed, ' "' );
            $is_block_starter = in_array( $first_word, $block_starters, true );
            $is_field = in_array( $first_word, $field_types, true );

            // Check if this is a modifier (@something)
            $is_modifier = strpos( $trimmed, '@' ) === 0;

            // Check if this is a choice (+ Option)
            $is_choice = strpos( $trimmed, '+' ) === 0;

            // Determine indentation
            if ( $is_block_starter && $first_word !== 'form' ) {
                // Block starters maintain current level, then increase
                $formatted[] = str_repeat( $this->indent, $indent_level ) . $trimmed;
                $indent_level++;
            } elseif ( $first_word === 'form' ) {
                // Form resets indentation
                $indent_level = 0;
                $formatted[] = $trimmed;
                $indent_level++;
            } elseif ( $is_field ) {
                $formatted[] = str_repeat( $this->indent, $indent_level ) . $trimmed;
            } elseif ( $is_modifier || $is_choice ) {
                // Modifiers and choices are indented one more level
                $formatted[] = str_repeat( $this->indent, $indent_level + 1 ) . $trimmed;
            } else {
                $formatted[] = str_repeat( $this->indent, $indent_level ) . $trimmed;
            }
        }

        return implode( "\n", $formatted );
    }

    /**
     * Compile form to formatted BFL
     *
     * @param array $form Form configuration.
     * @return string Formatted BFL code.
     */
    private function compile_formatted( $form ) {
        $this->indent_level = 0;
        $output = array();

        // Form declaration
        $title = $form['title'] ?? 'Untitled Form';
        $output[] = 'form "' . $this->escape_string( $title ) . '"';

        // Description
        if ( ! empty( $form['description'] ) ) {
            $this->indent_level++;
            $output[] = $this->line( '@description "' . $this->escape_string( $form['description'] ) . '"' );
            $this->indent_level--;
        }

        // Settings block
        if ( ! empty( $form['settings'] ) ) {
            $settings_output = $this->format_settings( $form['settings'] );
            if ( $settings_output ) {
                if ( $this->add_spacing ) {
                    $output[] = '';
                }
                $output[] = $settings_output;
            }
        }

        // Pages and fields
        $pages = $form['pages'] ?? array( array( 'id' => 'page_1', 'title' => '' ) );
        $fields = $form['fields'] ?? array();
        $fields_by_page = $this->group_fields_by_page( $fields );

        foreach ( $pages as $page ) {
            if ( $this->add_spacing ) {
                $output[] = '';
            }
            $output[] = $this->format_page( $page, $fields_by_page );
        }

        return implode( "\n", array_filter( $output, function( $line ) {
            return $line !== null;
        } ) );
    }

    /**
     * Format settings block
     *
     * @param array $settings Settings configuration.
     * @return string Formatted settings.
     */
    private function format_settings( $settings ) {
        $relevant = array();

        // Map internal settings to BFL
        $setting_map = array(
            'enable_honeypot'      => 'honeypot',
            'enable_captcha'       => 'captcha',
            'ajax_submission'      => 'ajax',
            'save_continue_enabled' => 'saveAndContinue',
            'rate_limit_enabled'   => 'rateLimit',
            'captcha_type'         => 'captchaType',
        );

        foreach ( $setting_map as $internal => $bfl_name ) {
            if ( isset( $settings[ $internal ] ) && $settings[ $internal ] ) {
                $relevant[ $bfl_name ] = $settings[ $internal ];
            }
        }

        if ( empty( $relevant ) ) {
            return '';
        }

        $this->indent_level = 1;
        $lines = array( $this->line( 'settings' ) );
        $this->indent_level = 2;

        // Align values if option is enabled
        if ( $this->align_values ) {
            $max_key_length = max( array_map( 'strlen', array_keys( $relevant ) ) );
            foreach ( $relevant as $key => $value ) {
                $padding = str_repeat( ' ', $max_key_length - strlen( $key ) );
                $lines[] = $this->line( $key . $padding . ' ' . $this->format_value( $value ) );
            }
        } else {
            foreach ( $relevant as $key => $value ) {
                $lines[] = $this->line( $key . ' ' . $this->format_value( $value ) );
            }
        }

        $this->indent_level = 0;
        return implode( "\n", $lines );
    }

    /**
     * Format a page and its fields
     *
     * @param array $page           Page configuration.
     * @param array $fields_by_page Fields grouped by page.
     * @return string Formatted page.
     */
    private function format_page( $page, $fields_by_page ) {
        $page_id = $page['id'] ?? 'page_1';
        $title = $page['title'] ?? '';
        $lines = array();

        // Only output page declaration for multi-page or titled pages
        if ( ! empty( $title ) ) {
            $this->indent_level = 1;
            $lines[] = $this->line( 'page "' . $this->escape_string( $title ) . '"' );
            $this->indent_level = 2;
        } else {
            $this->indent_level = 1;
        }

        // Format fields
        $page_fields = $fields_by_page[ $page_id ] ?? array();
        $current_section = null;

        foreach ( $page_fields as $field ) {
            if ( ( $field['type'] ?? '' ) === 'section' ) {
                if ( $current_section !== null && $this->add_spacing ) {
                    $lines[] = '';
                }
                $lines[] = $this->format_section( $field );
                $current_section = $field;
                $this->indent_level++;
            } else {
                $lines[] = $this->format_field( $field );
            }
        }

        return implode( "\n", array_filter( $lines ) );
    }

    /**
     * Format a section
     *
     * @param array $section Section configuration.
     * @return string Formatted section.
     */
    private function format_section( $section ) {
        $label = $section['label'] ?? 'Section';
        $line = $this->line( 'section "' . $this->escape_string( $label ) . '"' );

        if ( ! empty( $section['description'] ) ) {
            $this->indent_level++;
            $line .= "\n" . $this->line( '@description "' . $this->escape_string( $section['description'] ) . '"' );
            $this->indent_level--;
        }

        return $line;
    }

    /**
     * Format a field
     *
     * @param array $field Field configuration.
     * @return string Formatted field.
     */
    private function format_field( $field ) {
        $type = $field['type'] ?? 'text';
        
        // Skip section and submit in field formatting
        if ( in_array( $type, array( 'section', 'page' ), true ) ) {
            return '';
        }

        $lines = array();
        $line = $type;

        // Add custom ID
        $id = $field['id'] ?? '';
        if ( $id && ! preg_match( '/^field_\d+$/', $id ) ) {
            $line .= ' #' . $id;
        }

        // Add label
        if ( ! empty( $field['label'] ) ) {
            $line .= ' "' . $this->escape_string( $field['label'] ) . '"';
        }

        $lines[] = $this->line( $line );

        // Add modifiers
        $modifiers = $this->get_field_modifiers( $field );
        $this->indent_level++;
        foreach ( $modifiers as $modifier ) {
            $lines[] = $this->line( $modifier );
        }

        // Add choices for choice fields
        if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
            foreach ( $this->format_choices( $field['choices'] ) as $choice_line ) {
                $lines[] = $this->line( $choice_line );
            }
        }

        $this->indent_level--;

        return implode( "\n", array_filter( $lines ) );
    }

    /**
     * Get field modifiers as array
     *
     * @param array $field Field configuration.
     * @return array Modifier strings.
     */
    private function get_field_modifiers( $field ) {
        $modifiers = array();

        if ( ! empty( $field['required'] ) ) {
            $modifiers[] = '@required';
        }
        if ( ! empty( $field['placeholder'] ) ) {
            $modifiers[] = '@placeholder "' . $this->escape_string( $field['placeholder'] ) . '"';
        }
        if ( isset( $field['default_value'] ) && $field['default_value'] !== '' ) {
            $modifiers[] = '@default "' . $this->escape_string( $field['default_value'] ) . '"';
        }
        if ( ! empty( $field['description'] ) ) {
            $modifiers[] = '@description "' . $this->escape_string( $field['description'] ) . '"';
        }
        if ( isset( $field['min'] ) && $field['min'] !== '' ) {
            $modifiers[] = '@min ' . $field['min'];
        }
        if ( isset( $field['max'] ) && $field['max'] !== '' ) {
            $modifiers[] = '@max ' . $field['max'];
        }
        if ( isset( $field['min_length'] ) && $field['min_length'] !== '' ) {
            $modifiers[] = '@minlength ' . $field['min_length'];
        }
        if ( isset( $field['max_length'] ) && $field['max_length'] !== '' ) {
            $modifiers[] = '@maxlength ' . $field['max_length'];
        }
        if ( ! empty( $field['rows'] ) ) {
            $modifiers[] = '@rows ' . $field['rows'];
        }
        if ( ! empty( $field['read_only'] ) ) {
            $modifiers[] = '@readonly';
        }
        if ( ! empty( $field['disabled'] ) ) {
            $modifiers[] = '@disabled';
        }
        if ( ! empty( $field['css_class'] ) ) {
            $modifiers[] = '@class "' . $this->escape_string( $field['css_class'] ) . '"';
        }
        if ( ! empty( $field['input_mask'] ) ) {
            $modifiers[] = '@mask "' . $this->escape_string( $field['input_mask'] ) . '"';
        }
        if ( ! empty( $field['autocomplete'] ) ) {
            $modifiers[] = '@autocomplete "' . $this->escape_string( $field['autocomplete'] ) . '"';
        }

        return $modifiers;
    }

    /**
     * Format choices array
     *
     * @param array $choices Choices configuration.
     * @return array Choice lines.
     */
    private function format_choices( $choices ) {
        // Check for library reference
        if ( isset( $choices['library'] ) ) {
            return array( '@library ' . $choices['library'] );
        }

        $lines = array();
        foreach ( $choices as $choice ) {
            if ( is_array( $choice ) ) {
                $label = $choice['label'] ?? '';
                $value = $choice['value'] ?? '';
                $price = $choice['price'] ?? '';

                if ( $price ) {
                    $lines[] = '+ ' . $label . ':' . $price;
                } elseif ( $value && $value !== sanitize_title( $label ) ) {
                    $lines[] = '+ ' . $value . '|' . $label;
                } else {
                    $lines[] = '+ ' . $label;
                }
            } else {
                $lines[] = '+ ' . $choice;
            }
        }

        return $lines;
    }

    /**
     * Group fields by page
     *
     * @param array $fields Fields array.
     * @return array Fields grouped by page ID.
     */
    private function group_fields_by_page( $fields ) {
        $grouped = array();
        foreach ( $fields as $field ) {
            $page = $field['page'] ?? 'page_1';
            if ( ! isset( $grouped[ $page ] ) ) {
                $grouped[ $page ] = array();
            }
            $grouped[ $page ][] = $field;
        }
        return $grouped;
    }

    /**
     * Create indented line
     *
     * @param string $content Line content.
     * @return string Indented line.
     */
    private function line( $content ) {
        return str_repeat( $this->indent, $this->indent_level ) . $content;
    }

    /**
     * Format a value for output
     *
     * @param mixed $value Value to format.
     * @return string Formatted value.
     */
    private function format_value( $value ) {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( is_numeric( $value ) ) {
            return (string) $value;
        }
        if ( is_string( $value ) ) {
            return '"' . $this->escape_string( $value ) . '"';
        }
        return 'null';
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
     * Minify BFL code
     *
     * @param string $bfl BFL source code.
     * @return string Minified code.
     */
    public function minify( $bfl ) {
        $lines = explode( "\n", $bfl );
        $minified = array();

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            
            // Skip empty lines and comments
            if ( empty( $trimmed ) || strpos( $trimmed, '//' ) === 0 || strpos( $trimmed, '#' ) === 0 ) {
                continue;
            }

            $minified[] = $trimmed;
        }

        return implode( "\n", $minified );
    }

    /**
     * Get syntax tokens for highlighting
     *
     * @param string $bfl BFL source code.
     * @return array Tokens with position and type info.
     */
    public function get_syntax_tokens( $bfl ) {
        $lexer = new QAF_BFL_Lexer();
        $tokens = $lexer->tokenize( $bfl );

        $highlight_tokens = array();
        foreach ( $tokens as $token ) {
            $highlight_tokens[] = array(
                'type'   => $this->map_token_type( $token['type'] ),
                'value'  => $token['value'],
                'line'   => $token['line'],
                'column' => $token['column'],
            );
        }

        return $highlight_tokens;
    }

    /**
     * Map lexer token type to highlight class
     *
     * @param string $type Token type.
     * @return string Highlight class.
     */
    private function map_token_type( $type ) {
        $type_map = array(
            QAF_BFL_Lexer::TOKEN_KEYWORD    => 'keyword',
            QAF_BFL_Lexer::TOKEN_IDENTIFIER => 'identifier',
            QAF_BFL_Lexer::TOKEN_STRING     => 'string',
            QAF_BFL_Lexer::TOKEN_NUMBER     => 'number',
            QAF_BFL_Lexer::TOKEN_MODIFIER   => 'modifier',
            QAF_BFL_Lexer::TOKEN_CHOICE     => 'choice',
            QAF_BFL_Lexer::TOKEN_COMMENT    => 'comment',
        );

        return $type_map[ $type ] ?? 'text';
    }

    /**
     * Validate and format with error recovery
     *
     * @param string $bfl BFL source code.
     * @return array Result with 'formatted' and 'errors' keys.
     */
    public function validate_and_format( $bfl ) {
        $validator = new QAF_BFL_Validator();
        $validation = $validator->validate( $bfl );

        return array(
            'formatted' => $this->format( $bfl ),
            'valid'     => $validation['valid'],
            'errors'    => $validation['errors'],
            'warnings'  => $validation['warnings'],
        );
    }
}
