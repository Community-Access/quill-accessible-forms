<?php
/**
 * BFL Parser
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_BFL_Parser class
 * 
 * Parses BFL tokens into form configuration.
 */
class BAF_BFL_Parser {

    /**
     * Skip trivia tokens (newline/indent/comments)
     */
    private function skip_trivia() {
        while ( ! $this->is_at_end() ) {
            $token = $this->peek();
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_NEWLINE ||
                 $token['type'] === BAF_BFL_Lexer::TOKEN_INDENT ||
                 $token['type'] === BAF_BFL_Lexer::TOKEN_COMMENT ) {
                $this->advance();
                continue;
            }
            break;
        }
    }

    /**
     * Try to consume a block open token ({, [, or begin)
     *
     * @return string|null Opening delimiter type.
     */
    private function try_consume_block_open() {
        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACE_OPEN ) ) {
            $this->advance();
            return 'brace';
        }
        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_OPEN ) ) {
            $this->advance();
            return 'bracket';
        }
        if ( $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) && $this->peek()['value'] === 'begin' ) {
            $this->advance();
            return 'begin';
        }
        return null;
    }

    /**
     * Check if current token closes a block (}, ], or end)
     *
     * @return bool
     */
    private function is_block_close() {
        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACE_CLOSE ) || $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_CLOSE ) ) {
            return true;
        }
        return $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) && $this->peek()['value'] === 'end';
    }

    /**
     * Consume a block close token (}, ], or end) if present.
     */
    private function consume_block_close_if_present() {
        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACE_CLOSE ) || $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_CLOSE ) ) {
            $this->advance();
            return;
        }
        if ( $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) && $this->peek()['value'] === 'end' ) {
            $this->advance();
        }
    }

    /**
     * Tokens to parse
     *
     * @var array
     */
    private $tokens = array();

    /**
     * Current position
     *
     * @var int
     */
    private $position = 0;

    /**
     * Current indent level
     *
     * @var int
     */
    private $indent_level = 0;

    /**
     * Parse errors
     *
     * @var array
     */
    private $errors = array();

    /**
     * Generated form config
     *
     * @var array
     */
    private $form = array();

    /**
     * Current page index
     *
     * @var int
     */
    private $current_page = 0;

    /**
     * Field counter for auto ID generation
     *
     * @var int
     */
    private $field_counter = 0;

    /**
     * Parse BFL source code
     *
     * @param string $source BFL source code.
     * @return array Form configuration.
     */
    public function parse( $source ) {
        // Tokenize
        $lexer        = new BAF_BFL_Lexer();
        $this->tokens = $lexer->tokenize( $source );

        // Reset state
        $this->position     = 0;
        $this->indent_level = 0;
        $this->errors       = array();
        $this->current_page = 0;
        $this->field_counter = 0;

        // Initialize form structure
        $this->form = array(
            'title'         => '',
            'description'   => '',
            'fields'        => array(),
            'pages'         => array(
                array(
                    'id'    => 'page_1',
                    'title' => '',
                ),
            ),
            'settings'      => array(),
            'conditional_logic' => array(),
        );

        // Parse form
        $this->parse_form();

        return array(
            'form'   => $this->form,
            'errors' => $this->errors,
        );
    }

    /**
     * Parse form structure
     */
    private function parse_form() {
        while ( ! $this->is_at_end() ) {
            $this->skip_whitespace();

            if ( $this->is_at_end() ) {
                break;
            }

            $token = $this->peek();

            // Skip comments
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_COMMENT ) {
                $this->advance();
                continue;
            }

            // Form declaration
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $token['value'] === 'form' ) {
                $this->parse_form_declaration();
                continue;
            }

            // Page break
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $token['value'] === 'page' ) {
                $this->parse_page();
                continue;
            }

            // Section
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $token['value'] === 'section' ) {
                $this->parse_section();
                continue;
            }

            // Columns
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $token['value'] === 'columns' ) {
                $this->parse_columns();
                continue;
            }

            // Conditional block
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && ( $token['value'] === 'if' || $token['value'] === 'conditional' ) ) {
                $this->parse_conditional();
                continue;
            }

            // Field
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $this->is_field_type( $token['value'] ) ) {
                $field = $this->parse_field();
                if ( $field ) {
                    $field['page'] = 'page_' . ( $this->current_page + 1 );
                    $this->form['fields'][] = $field;
                }
                continue;
            }

            // Unknown token - skip
            $this->advance();
        }
    }

    /**
     * Parse form declaration (form "Title")
     */
    private function parse_form_declaration() {
        $this->advance(); // Skip 'form' keyword

        // Get form title
        if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
            $this->form['title'] = $this->advance()['value'];
        }

        // Parse form-level modifiers
        $this->skip_whitespace();

        while ( $this->check( BAF_BFL_Lexer::TOKEN_MODIFIER ) ) {
            $modifier = $this->advance()['value'];
            $this->parse_form_modifier( $modifier );
        }
    }

    /**
     * Parse form-level modifier
     *
     * @param string $modifier Modifier name.
     */
    private function parse_form_modifier( $modifier ) {
        $this->skip_whitespace();

        switch ( $modifier ) {
            case 'description':
            case 'desc':
                if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                    $this->form['description'] = $this->advance()['value'];
                }
                break;

            case 'ajax':
                $this->form['settings']['ajax_submission'] = true;
                break;

            case 'honeypot':
                $this->form['settings']['enable_honeypot'] = true;
                break;

            case 'captcha':
                $this->form['settings']['enable_captcha'] = true;
                if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                    $this->form['settings']['captcha_type'] = $this->advance()['value'];
                }
                break;
        }
    }

    /**
     * Parse page break
     */
    private function parse_page() {
        $this->advance(); // Skip 'page' keyword

        // Create new page
        $this->current_page++;
        $page = array(
            'id'    => 'page_' . ( $this->current_page + 1 ),
            'title' => '',
        );

        // Get page title
        if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
            $page['title'] = $this->advance()['value'];
        }

        // Parse page modifiers
        $this->skip_whitespace();

        while ( $this->check( BAF_BFL_Lexer::TOKEN_MODIFIER ) ) {
            $modifier = $this->advance()['value'];
            if ( $modifier === 'description' || $modifier === 'desc' ) {
                $this->skip_whitespace();
                if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                    $page['description'] = $this->advance()['value'];
                }
            }
        }

        $this->form['pages'][] = $page;
    }

    /**
     * Parse section
     */
    private function parse_section() {
        $this->advance(); // Skip 'section' keyword

        $field = array(
            'id'   => $this->generate_field_id(),
            'type' => 'section',
        );

        // Get section title
        if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
            $field['label'] = $this->advance()['value'];
        }

        // Parse modifiers
        $field = $this->parse_field_modifiers( $field );

        $field['page'] = 'page_' . ( $this->current_page + 1 );
        $this->form['fields'][] = $field;
    }

    /**
     * Parse columns layout
     */
    private function parse_columns() {
        $this->advance(); // Skip 'columns' keyword

        $field = array(
            'id'      => $this->generate_field_id(),
            'type'    => 'columns',
            'columns' => array(),
        );

        // Get column count
        if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
            $field['column_count'] = (int) $this->advance()['value'];
        }

        $this->skip_trivia();

        // Optional explicit block: columns 2 { ... } / columns 2 begin ... end
        $open = $this->try_consume_block_open();
        if ( $open ) {
            while ( ! $this->is_at_end() ) {
                $this->skip_trivia();

                if ( $this->is_block_close() ) {
                    $this->consume_block_close_if_present();
                    break;
                }

                $token = $this->peek();

                if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $token['value'] === 'column' ) {
                    $this->advance();

                    // Support either: column { ... } / column begin ... end
                    // OR indentation under "column".
                    $col_open = $this->try_consume_block_open();
                    if ( $col_open ) {
                        $column_fields = $this->parse_field_list_in_block();
                        $this->consume_block_close_if_present();
                    } else {
                        // Get current indent for indentation-style parsing within explicit block
                        $col_indent = $this->get_current_indent();
                        $column_fields = $this->parse_column_content( $col_indent );
                    }

                    $field['columns'][] = $column_fields;
                    continue;
                }

                $this->advance();
            }

            $field['page'] = 'page_' . ( $this->current_page + 1 );
            $this->form['fields'][] = $field;
            return;
        }

        // Indentation-style block
        $this->skip_to_next_line();
        $base_indent = $this->get_current_indent();

        while ( ! $this->is_at_end() ) {
            $this->skip_whitespace();

            if ( $this->is_block_close() ) {
                break;
            }

            $indent = $this->get_current_indent();

            if ( $indent <= $base_indent ) {
                break;
            }

            $token = $this->peek();

            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $token['value'] === 'column' ) {
                $this->advance();
                $column_fields = $this->parse_column_content( $indent );
                $field['columns'][] = $column_fields;
            } else {
                break;
            }
        }

        $field['page'] = 'page_' . ( $this->current_page + 1 );
        $this->form['fields'][] = $field;
    }

    /**
     * Parse column content
     *
     * @param int $base_indent Base indentation level.
     * @return array Column fields.
     */
    private function parse_column_content( $base_indent ) {
        $fields = array();

        $this->skip_to_next_line();

        while ( ! $this->is_at_end() ) {
            $this->skip_whitespace();

            if ( $this->is_block_close() ) {
                break;
            }

            $indent = $this->get_current_indent();

            if ( $indent <= $base_indent ) {
                break;
            }

            $token = $this->peek();

            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $this->is_field_type( $token['value'] ) ) {
                $field = $this->parse_field();
                if ( $field ) {
                    $fields[] = $field;
                }
            } else {
                break;
            }
        }

        return $fields;
    }

    /**
     * Parse a list of fields until a block close token (}, ], or end).
     *
     * @return array
     */
    private function parse_field_list_in_block() {
        $fields = array();

        while ( ! $this->is_at_end() ) {
            $this->skip_trivia();

            if ( $this->is_block_close() ) {
                break;
            }

            $token = $this->peek();

            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $this->is_field_type( $token['value'] ) ) {
                $field = $this->parse_field();
                if ( $field ) {
                    $fields[] = $field;
                }
                continue;
            }

            // Unknown content in a block; skip.
            $this->advance();
        }

        return $fields;
    }

    /**
     * Parse conditional block
     */
    private function parse_conditional() {
        $this->advance(); // Skip 'if' or 'conditional'

        $condition = $this->parse_condition();

        $this->skip_trivia();

        // Optional explicit block: if ... { ... } / if ... begin ... end
        $open = $this->try_consume_block_open();
        if ( $open ) {
            while ( ! $this->is_at_end() ) {
                $this->skip_trivia();

                if ( $this->is_block_close() ) {
                    $this->consume_block_close_if_present();
                    break;
                }

                $token = $this->peek();
                if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $this->is_field_type( $token['value'] ) ) {
                    $field = $this->parse_field();
                    if ( $field ) {
                        $field['conditional_logic'] = array(
                            'enabled' => true,
                            'action'  => 'show',
                            'logic'   => 'all',
                            'rules'   => $condition,
                        );
                        $field['page'] = 'page_' . ( $this->current_page + 1 );
                        $this->form['fields'][] = $field;
                    }
                    continue;
                }

                $this->advance();
            }
            return;
        }

        // Indentation-style block
        $this->skip_to_next_line();
        $base_indent = $this->get_current_indent();

        while ( ! $this->is_at_end() ) {
            $this->skip_whitespace();

            if ( $this->is_block_close() ) {
                break;
            }

            $indent = $this->get_current_indent();

            if ( $indent <= $base_indent ) {
                break;
            }

            $token = $this->peek();

            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_KEYWORD && $this->is_field_type( $token['value'] ) ) {
                $field = $this->parse_field();
                if ( $field ) {
                    $field['conditional_logic'] = array(
                        'enabled' => true,
                        'action'  => 'show',
                        'logic'   => 'all',
                        'rules'   => $condition,
                    );
                    $field['page'] = 'page_' . ( $this->current_page + 1 );
                    $this->form['fields'][] = $field;
                }
            } else {
                $this->advance();
            }
        }
    }

    /**
     * Parse condition expression
     *
     * @return array Condition rules.
     */
    private function parse_condition() {
        $rules = array();

        // Simple condition: field_id is "value"
        // or: field_id contains "value"
        // or: field_id is empty

        if ( $this->check( BAF_BFL_Lexer::TOKEN_IDENTIFIER ) ) {
            $field_id = $this->advance()['value'];

            $this->skip_whitespace();

            // Get operator
            $operator = 'is';
            if ( $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                $op_token = $this->advance()['value'];
                switch ( $op_token ) {
                    case 'is':
                        $operator = 'equals';
                        break;
                    case 'contains':
                        $operator = 'contains';
                        break;
                    case 'starts':
                        $operator = 'starts_with';
                        break;
                    case 'ends':
                        $operator = 'ends_with';
                        break;
                    case 'greater':
                        $operator = 'greater_than';
                        break;
                    case 'less':
                        $operator = 'less_than';
                        break;
                    case 'empty':
                        $operator = 'is_empty';
                        break;
                    case 'filled':
                        $operator = 'is_not_empty';
                        break;
                }
            }

            // Get value
            $value = '';
            $this->skip_whitespace();

            if ( ! in_array( $operator, array( 'is_empty', 'is_not_empty' ), true ) ) {
                if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                    $value = $this->advance()['value'];
                } elseif ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                    $value = $this->advance()['value'];
                }
            }

            $rules[] = array(
                'field'    => $field_id,
                'operator' => $operator,
                'value'    => $value,
            );
        }

        return $rules;
    }

    /**
     * Parse field
     *
     * @return array|null Field configuration.
     */
    private function parse_field() {
        $type_token = $this->advance();
        $type       = $this->normalize_field_type( $type_token['value'] );

        $field = array(
            'id'   => $this->generate_field_id(),
            'type' => $type,
        );

        $this->skip_whitespace();

        // Parse field ID (#identifier)
        if ( $this->check( BAF_BFL_Lexer::TOKEN_IDENTIFIER ) ) {
            $field['id'] = $this->advance()['value'];
        }

        // Parse label ("string")
        $this->skip_whitespace();
        if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
            $field['label'] = $this->advance()['value'];
        }

        // Parse choices for choice fields
        if ( $this->is_choice_field( $type ) ) {
            $field['choices'] = $this->parse_choices();
        }

        // Parse modifiers
        $field = $this->parse_field_modifiers( $field );

        return $field;
    }

    /**
     * Parse field modifiers
     *
     * @param array $field Field configuration.
     * @return array Updated field configuration.
     */
    private function parse_field_modifiers( $field ) {
        $this->skip_trivia();

        // Optional explicit modifier block: { ... } / [ ... ] / begin ... end
        $in_explicit_block = false;
        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACE_OPEN ) ||
             $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_OPEN ) ||
             ( $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) && $this->peek()['value'] === 'begin' ) ) {
            $this->try_consume_block_open();
            $in_explicit_block = true;
        }

        while ( ! $this->is_at_end() ) {
            $this->skip_trivia();

            if ( $in_explicit_block && $this->is_block_close() ) {
                $this->consume_block_close_if_present();
                break;
            }

            // In indentation mode, stop once we're no longer looking at modifiers.
            if ( ! $in_explicit_block &&
                 ! $this->check( BAF_BFL_Lexer::TOKEN_MODIFIER ) &&
                 ! $this->check( BAF_BFL_Lexer::TOKEN_INDENT ) &&
                 ! $this->check( BAF_BFL_Lexer::TOKEN_NEWLINE ) ) {
                break;
            }

            // Skip indent/newline tokens
            if ( $this->check( BAF_BFL_Lexer::TOKEN_INDENT ) || $this->check( BAF_BFL_Lexer::TOKEN_NEWLINE ) ) {
                $this->advance();
                continue;
            }

            if ( ! $this->check( BAF_BFL_Lexer::TOKEN_MODIFIER ) ) {
                if ( $in_explicit_block ) {
                    $this->advance();
                    continue;
                }
                break;
            }

            $modifier = $this->advance()['value'];
            $this->skip_trivia();

            switch ( $modifier ) {
                case 'required':
                    $field['required'] = true;
                    break;

                case 'placeholder':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['placeholder'] = $this->advance()['value'];
                    }
                    break;

                case 'default':
                    $this->skip_whitespace();
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['default_value'] = $this->advance()['value'];
                    } elseif ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['default_value'] = $this->advance()['value'];
                    }
                    break;

                case 'description':
                case 'desc':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['description'] = $this->advance()['value'];
                    }
                    break;

                case 'min':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['min'] = $this->advance()['value'];
                    }
                    break;

                case 'max':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['max'] = $this->advance()['value'];
                    }
                    break;

                case 'minlength':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['min_length'] = $this->advance()['value'];
                    }
                    break;

                case 'maxlength':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['max_length'] = $this->advance()['value'];
                    }
                    break;

                case 'rows':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['rows'] = $this->advance()['value'];
                    }
                    break;

                case 'size':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['size'] = $this->advance()['value'];
                    }
                    break;

                case 'class':
                case 'css':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['css_class'] = $this->advance()['value'];
                    }
                    break;

                case 'readonly':
                    $field['read_only'] = true;
                    break;

                case 'disabled':
                    $field['disabled'] = true;
                    break;

                case 'admin':
                case 'admin-only':
                    $field['admin_only'] = true;
                    break;

                case 'hidden':
                    $field['visibility'] = 'hidden';
                    break;

                case 'confirm':
                    $field['confirm_email'] = true;
                    break;

                case 'mask':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['input_mask'] = $this->advance()['value'];
                    }
                    break;

                case 'autocomplete':
                    // Used for HTML autocomplete (text fields) OR address autocomplete toggle
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['autocomplete'] = $this->advance()['value'];
                    } else {
                        $field['autocomplete'] = true;
                    }
                    break;

                case 'autocomplete_provider':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                        $field['autocomplete_provider'] = $this->advance()['value'];
                    }
                    break;

                case 'autocomplete_countries':
                    $countries = array();
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_OPEN ) ) {
                        $this->advance();
                        while ( ! $this->is_at_end() && ! $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_CLOSE ) ) {
                            if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                                $countries[] = $this->advance()['value'];
                                continue;
                            }
                            $this->advance();
                        }
                        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_CLOSE ) ) {
                            $this->advance();
                        }
                    }
                    $field['autocomplete_countries'] = $countries;
                    break;

                case 'autocomplete_types':
                    $types = array();
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_OPEN ) ) {
                        $this->advance();
                        while ( ! $this->is_at_end() && ! $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_CLOSE ) ) {
                            if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                                $types[] = $this->advance()['value'];
                                continue;
                            }
                            $this->advance();
                        }
                        if ( $this->check( BAF_BFL_Lexer::TOKEN_BRACKET_CLOSE ) ) {
                            $this->advance();
                        }
                    }
                    $field['autocomplete_types'] = $types;
                    break;

                case 'smart_default':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['smart_default'] = $this->advance()['value'];
                    }
                    break;

                case 'unique_scope':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                        $field['unique_scope'] = $this->advance()['value'];
                    }
                    break;

                case 'unique_message':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['unique_message'] = $this->advance()['value'];
                    }
                    break;

                case 'library':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                        $field['choice_library'] = $this->advance()['value'];
                    }
                    break;

                case 'cascade_from':
                case 'cascadefrom':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_IDENTIFIER ) ) {
                        $field['cascade_from'] = $this->advance()['value'];
                    }
                    break;

                case 'cascade_library':
                case 'cascadelibrary':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) || $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
                        $field['cascade_library'] = $this->advance()['value'];
                    }
                    break;

                case 'empty_message':
                case 'emptymessage':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['empty_message'] = $this->advance()['value'];
                    }
                    break;

                case 'loading_message':
                case 'loadingmessage':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['loading_message'] = $this->advance()['value'];
                    }
                    break;

                case 'multiple':
                    $field['multiple'] = true;
                    break;

                case 'allow-other':
                case 'allowother':
                    $field['allow_other'] = true;
                    break;

                case 'format':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['format'] = $this->advance()['value'];
                    }
                    break;

                case 'step':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_NUMBER ) ) {
                        $field['step'] = $this->advance()['value'];
                    }
                    break;

                case 'prefix':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['prefix'] = $this->advance()['value'];
                    }
                    break;

                case 'suffix':
                    if ( $this->check( BAF_BFL_Lexer::TOKEN_STRING ) ) {
                        $field['suffix'] = $this->advance()['value'];
                    }
                    break;
            }

            $this->skip_trivia();
        }

        return $field;
    }

    /**
     * Parse choices for select/radio/checkbox fields
     *
     * @return array Choices array.
     */
    private function parse_choices() {
        $choices = array();

        $this->skip_whitespace();

        // Check for choice library reference
        if ( $this->check( BAF_BFL_Lexer::TOKEN_KEYWORD ) ) {
            $token = $this->peek();
            if ( $this->is_choice_library( $token['value'] ) ) {
                return array( 'library' => $this->advance()['value'] );
            }
        }

        // Optional explicit block: { ... } / [ ... ] / begin ... end
        $open = $this->try_consume_block_open();
        if ( $open ) {
            while ( ! $this->is_at_end() ) {
                $this->skip_trivia();

                if ( $this->is_block_close() ) {
                    $this->consume_block_close_if_present();
                    break;
                }

                if ( ! $this->check( BAF_BFL_Lexer::TOKEN_CHOICE ) ) {
                    $this->advance();
                    continue;
                }

                $choice_value = $this->advance()['value'];

                // Parse value|label format
                if ( strpos( $choice_value, '|' ) !== false ) {
                    list( $value, $label ) = explode( '|', $choice_value, 2 );
                    $choices[] = array(
                        'value' => trim( $value ),
                        'label' => trim( $label ),
                    );
                    continue;
                }

                // Parse value:price format
                if ( strpos( $choice_value, ':' ) !== false ) {
                    list( $label, $price ) = explode( ':', $choice_value, 2 );
                    $choices[] = array(
                        'value' => sanitize_title( trim( $label ) ),
                        'label' => trim( $label ),
                        'price' => trim( $price ),
                    );
                    continue;
                }

                $choices[] = array(
                    'value' => sanitize_title( $choice_value ),
                    'label' => $choice_value,
                );
            }

            return $choices;
        }

        // Indentation-style choices
        $this->skip_to_next_line();

        while ( $this->check( BAF_BFL_Lexer::TOKEN_CHOICE ) || $this->check( BAF_BFL_Lexer::TOKEN_INDENT ) || $this->check( BAF_BFL_Lexer::TOKEN_NEWLINE ) ) {
            if ( $this->check( BAF_BFL_Lexer::TOKEN_INDENT ) || $this->check( BAF_BFL_Lexer::TOKEN_NEWLINE ) ) {
                $this->advance();
                continue;
            }

            if ( $this->check( BAF_BFL_Lexer::TOKEN_CHOICE ) ) {
                $choice_value = $this->advance()['value'];

                // Parse value|label format
                if ( strpos( $choice_value, '|' ) !== false ) {
                    list( $value, $label ) = explode( '|', $choice_value, 2 );
                    $choices[] = array(
                        'value' => trim( $value ),
                        'label' => trim( $label ),
                    );
                } else {
                    // Parse value:price format
                    if ( strpos( $choice_value, ':' ) !== false ) {
                        list( $label, $price ) = explode( ':', $choice_value, 2 );
                        $choices[] = array(
                            'value' => sanitize_title( trim( $label ) ),
                            'label' => trim( $label ),
                            'price' => trim( $price ),
                        );
                    } else {
                        $choices[] = array(
                            'value' => sanitize_title( $choice_value ),
                            'label' => $choice_value,
                        );
                    }
                }
            }
        }

        return $choices;
    }

    /**
     * Check if token type matches
     *
     * @param string $type Token type.
     * @return bool True if matches.
     */
    private function check( $type ) {
        if ( $this->is_at_end() ) {
            return false;
        }
        return $this->peek()['type'] === $type;
    }

    /**
     * Advance and return current token
     *
     * @return array Current token.
     */
    private function advance() {
        if ( ! $this->is_at_end() ) {
            $this->position++;
        }
        return $this->tokens[ $this->position - 1 ];
    }

    /**
     * Get current token without advancing
     *
     * @return array Current token.
     */
    private function peek() {
        return $this->tokens[ $this->position ];
    }

    /**
     * Check if at end
     *
     * @return bool True if at end.
     */
    private function is_at_end() {
        return $this->position >= count( $this->tokens ) ||
               $this->tokens[ $this->position ]['type'] === BAF_BFL_Lexer::TOKEN_EOF;
    }

    /**
     * Skip whitespace tokens
     */
    private function skip_whitespace() {
        while ( ! $this->is_at_end() ) {
            $token = $this->peek();
            if ( $token['type'] === BAF_BFL_Lexer::TOKEN_NEWLINE ||
                 $token['type'] === BAF_BFL_Lexer::TOKEN_COMMENT ) {
                $this->advance();
            } else {
                break;
            }
        }
    }

    /**
     * Skip to next line
     */
    private function skip_to_next_line() {
        while ( ! $this->is_at_end() && $this->peek()['type'] !== BAF_BFL_Lexer::TOKEN_NEWLINE ) {
            $this->advance();
        }
        if ( $this->check( BAF_BFL_Lexer::TOKEN_NEWLINE ) ) {
            $this->advance();
        }
    }

    /**
     * Get current indentation level
     *
     * @return int Indent level.
     */
    private function get_current_indent() {
        if ( $this->check( BAF_BFL_Lexer::TOKEN_INDENT ) ) {
            return $this->peek()['level'] ?? 0;
        }
        return 0;
    }

    /**
     * Check if keyword is a field type
     *
     * @param string $keyword Keyword to check.
     * @return bool True if field type.
     */
    private function is_field_type( $keyword ) {
        $field_types = array(
            'text', 'textarea', 'paragraph', 'number', 'email', 'phone', 'website', 'url',
            'hidden', 'password', 'select', 'dropdown', 'multiselect', 'radio', 'checkbox',
            'checkboxes', 'consent', 'likert', 'ranking', 'rating', 'date', 'time',
            'datetime', 'daterange', 'name', 'address', 'file', 'fileupload', 'signature',
            'captcha', 'repeater', 'calculation', 'product', 'quantity', 'price', 'total',
            'coupon', 'html', 'divider', 'spacer', 'submit', 'save', 'savebutton',
            'post_title', 'post_content', 'post_excerpt', 'post_category', 'post_tags',
            'post_image', 'post_custom', 'user_login', 'user_email', 'user_password',
            'section',
        );

        return in_array( $keyword, $field_types, true );
    }

    /**
     * Check if keyword is a choice library
     *
     * @param string $keyword Keyword to check.
     * @return bool True if choice library.
     */
    private function is_choice_library( $keyword ) {
        $libraries = array(
            'countries', 'states', 'provinces', 'us_states', 'ca_provinces',
            'uk_counties', 'au_states', 'currencies', 'timezones',
            'languages', 'months', 'days', 'years', 'salutations',
            'gender', 'age_ranges', 'education', 'employment_status',
            'industries', 'company_sizes',
        );

        return in_array( $keyword, $libraries, true );
    }

    /**
     * Check if field type is choice field
     *
     * @param string $type Field type.
     * @return bool True if choice field.
     */
    private function is_choice_field( $type ) {
        return in_array( $type, array(
            'select', 'dropdown', 'multiselect', 'radio', 'checkbox', 'checkboxes',
            'likert', 'ranking',
        ), true );
    }

    /**
     * Normalize field type name
     *
     * @param string $type Field type from BFL.
     * @return string Normalized type.
     */
    private function normalize_field_type( $type ) {
        $aliases = array(
            'dropdown'    => 'select',
            'paragraph'   => 'textarea',
            'url'         => 'website',
            'checkboxes'  => 'checkbox',
            'file'        => 'fileupload',
            'savebutton'  => 'save',
        );

        return isset( $aliases[ $type ] ) ? $aliases[ $type ] : $type;
    }

    /**
     * Generate unique field ID
     *
     * @return string Field ID.
     */
    private function generate_field_id() {
        $this->field_counter++;
        return 'field_' . $this->field_counter;
    }

    /**
     * Add error
     *
     * @param string $message Error message.
     * @param int    $line    Line number.
     * @param int    $column  Column number.
     */
    private function add_error( $message, $line = 0, $column = 0 ) {
        $this->errors[] = array(
            'message' => $message,
            'line'    => $line ?: ( $this->peek()['line'] ?? 0 ),
            'column'  => $column ?: ( $this->peek()['column'] ?? 0 ),
        );
    }
}
