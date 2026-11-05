<?php
/**
 * BFL Lexer
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_BFL_Lexer class
 * 
 * Tokenizes QAF form language source (the older reader).
 */
class QAF_BFL_Lexer {

    /**
     * Token types
     */
    const TOKEN_KEYWORD      = 'KEYWORD';      // form, page, section, field types
    const TOKEN_IDENTIFIER   = 'IDENTIFIER';   // field IDs (#name)
    const TOKEN_STRING       = 'STRING';       // "quoted strings"
    const TOKEN_NUMBER       = 'NUMBER';       // 123, 45.67
    const TOKEN_MODIFIER     = 'MODIFIER';     // @required, @placeholder
    const TOKEN_CHOICE       = 'CHOICE';       // + Option text
    const TOKEN_COMMENT      = 'COMMENT';      // // comment or /* block */
    const TOKEN_INDENT       = 'INDENT';       // Leading whitespace
    const TOKEN_NEWLINE      = 'NEWLINE';      // Line break
    const TOKEN_COLON        = 'COLON';        // :
    const TOKEN_PIPE         = 'PIPE';         // |
    const TOKEN_COMMA        = 'COMMA';        // ,
    const TOKEN_BRACKET_OPEN = 'BRACKET_OPEN'; // [
    const TOKEN_BRACKET_CLOSE = 'BRACKET_CLOSE'; // ]
    const TOKEN_BRACE_OPEN   = 'BRACE_OPEN';   // {
    const TOKEN_BRACE_CLOSE  = 'BRACE_CLOSE';  // }
    const TOKEN_PAREN_OPEN   = 'PAREN_OPEN';   // (
    const TOKEN_PAREN_CLOSE  = 'PAREN_CLOSE';  // )
    const TOKEN_EQUALS       = 'EQUALS';       // =
    const TOKEN_ARROW        = 'ARROW';        // =>
    const TOKEN_EOF          = 'EOF';          // End of file

    /**
     * BFL keywords (field types and structural elements)
     *
     * @var array
     */
    private $keywords = array(
        // Structural
        'form', 'page', 'section', 'columns', 'column', 'group', 'conditional',

        // Standard fields
        'text', 'textarea', 'paragraph', 'number', 'email', 'phone', 'website', 'url',
        'hidden', 'password',

        // Choice fields
        'select', 'dropdown', 'multiselect', 'radio', 'checkbox', 'checkboxes',
        'consent', 'likert', 'ranking', 'rating',

        // Date/time fields
        'date', 'time', 'datetime', 'daterange',

        // Advanced fields
        'name', 'address', 'file', 'fileupload', 'signature', 'captcha',
        'repeater', 'calculation',

        // Pricing fields
        'product', 'quantity', 'price', 'total', 'coupon',

        // Layout elements
        'html', 'divider', 'spacer',

        // Form helpers
        'submit', 'save', 'savebutton',

        // Post fields
        'post_title', 'post_content', 'post_excerpt', 'post_category',
        'post_tags', 'post_image', 'post_custom',

        // User fields
        'user_login', 'user_email', 'user_password',

        // Choice libraries
        'countries', 'states', 'provinces', 'us_states', 'ca_provinces',
        'uk_counties', 'au_states', 'currencies', 'timezones',
        'languages', 'months', 'days', 'years', 'salutations',
        'gender', 'age_ranges', 'education', 'employment_status',
        'industries', 'company_sizes',

        // Logic keywords
        'if', 'else', 'then', 'and', 'or', 'not', 'is', 'contains',
        'starts', 'ends', 'greater', 'less', 'between', 'empty', 'filled',

        // Alternative block delimiters
        'begin', 'end',
    );

    /**
     * Source code
     *
     * @var string
     */
    private $source = '';

    /**
     * Current position
     *
     * @var int
     */
    private $position = 0;

    /**
     * Current line
     *
     * @var int
     */
    private $line = 1;

    /**
     * Current column
     *
     * @var int
     */
    private $column = 1;

    /**
     * Source length
     *
     * @var int
     */
    private $length = 0;

    /**
     * Generated tokens
     *
     * @var array
     */
    private $tokens = array();

    /**
     * Tokenize BFL source code
     *
     * @param string $source BFL source code.
     * @return array Array of tokens.
     */
    public function tokenize( $source ) {
        $this->source   = $source;
        $this->position = 0;
        $this->line     = 1;
        $this->column   = 1;
        $this->length   = strlen( $source );
        $this->tokens   = array();

        while ( ! $this->is_at_end() ) {
            $this->scan_token();
        }

        $this->tokens[] = $this->create_token( self::TOKEN_EOF, '' );

        return $this->tokens;
    }

    /**
     * Scan and add the next token
     */
    private function scan_token() {
        $char = $this->peek();

        // Handle newlines (preserve for indentation tracking)
        if ( $char === "\n" ) {
            $this->tokens[] = $this->create_token( self::TOKEN_NEWLINE, "\n" );
            $this->advance();
            $this->line++;
            $this->column = 1;

            // Check for indentation on new line
            $this->scan_indentation();
            return;
        }

        // Skip carriage return
        if ( $char === "\r" ) {
            $this->advance();
            return;
        }

        // Handle indentation at start or after newline
        if ( $this->column === 1 && ( $char === ' ' || $char === "\t" ) ) {
            $this->scan_indentation();
            return;
        }

        // Skip other whitespace
        if ( $char === ' ' || $char === "\t" ) {
            $this->advance();
            return;
        }

        // Comments
        if ( $char === '/' ) {
            $next = $this->peek_next();
            if ( $next === '/' ) {
                $this->scan_line_comment();
                return;
            } elseif ( $next === '*' ) {
                $this->scan_block_comment();
                return;
            }
        }

        // String literals
        if ( $char === '"' || $char === "'" ) {
            $this->scan_string( $char );
            return;
        }

        // Numbers
        if ( $this->is_digit( $char ) || ( $char === '-' && $this->is_digit( $this->peek_next() ) ) ) {
            $this->scan_number();
            return;
        }

        // Identifiers (field IDs starting with #)
        if ( $char === '#' ) {
            $this->scan_identifier();
            return;
        }

        // Modifiers (starting with @)
        if ( $char === '@' ) {
            $this->scan_modifier();
            return;
        }

        // Choice options (starting with +)
        if ( $char === '+' ) {
            $this->scan_choice();
            return;
        }

        // Single-character tokens
        switch ( $char ) {
            case ':':
                $this->tokens[] = $this->create_token( self::TOKEN_COLON, ':' );
                $this->advance();
                return;

            case '|':
                $this->tokens[] = $this->create_token( self::TOKEN_PIPE, '|' );
                $this->advance();
                return;

            case ',':
                $this->tokens[] = $this->create_token( self::TOKEN_COMMA, ',' );
                $this->advance();
                return;

            case '[':
                $this->tokens[] = $this->create_token( self::TOKEN_BRACKET_OPEN, '[' );
                $this->advance();
                return;

            case ']':
                $this->tokens[] = $this->create_token( self::TOKEN_BRACKET_CLOSE, ']' );
                $this->advance();
                return;

            case '{':
                $this->tokens[] = $this->create_token( self::TOKEN_BRACE_OPEN, '{' );
                $this->advance();
                return;

            case '}':
                $this->tokens[] = $this->create_token( self::TOKEN_BRACE_CLOSE, '}' );
                $this->advance();
                return;

            case '(':
                $this->tokens[] = $this->create_token( self::TOKEN_PAREN_OPEN, '(' );
                $this->advance();
                return;

            case ')':
                $this->tokens[] = $this->create_token( self::TOKEN_PAREN_CLOSE, ')' );
                $this->advance();
                return;

            case '=':
                if ( $this->peek_next() === '>' ) {
                    $this->tokens[] = $this->create_token( self::TOKEN_ARROW, '=>' );
                    $this->advance();
                    $this->advance();
                } else {
                    $this->tokens[] = $this->create_token( self::TOKEN_EQUALS, '=' );
                    $this->advance();
                }
                return;
        }

        // Keywords and identifiers
        if ( $this->is_alpha( $char ) ) {
            $this->scan_keyword_or_identifier();
            return;
        }

        // Unknown character - skip
        $this->advance();
    }

    /**
     * Scan indentation
     */
    private function scan_indentation() {
        $indent = '';

        while ( ! $this->is_at_end() ) {
            $char = $this->peek();
            if ( $char === ' ' || $char === "\t" ) {
                $indent .= $char;
                $this->advance();
            } else {
                break;
            }
        }

        if ( ! empty( $indent ) ) {
            // Calculate indent level (tabs = 4 spaces, or count actual spaces)
            $level = 0;
            for ( $i = 0; $i < strlen( $indent ); $i++ ) {
                if ( $indent[ $i ] === "\t" ) {
                    $level += 4;
                } else {
                    $level++;
                }
            }

            $this->tokens[] = $this->create_token( self::TOKEN_INDENT, $indent, array( 'level' => $level ) );
        }
    }

    /**
     * Scan line comment (// ...)
     */
    private function scan_line_comment() {
        $start = $this->position;

        // Skip //
        $this->advance();
        $this->advance();

        // Read until newline
        while ( ! $this->is_at_end() && $this->peek() !== "\n" ) {
            $this->advance();
        }

        $comment = substr( $this->source, $start, $this->position - $start );
        $this->tokens[] = $this->create_token( self::TOKEN_COMMENT, $comment );
    }

    /**
     * Scan block comment
     */
    private function scan_block_comment() {
        $start = $this->position;

        // Skip /*
        $this->advance();
        $this->advance();

        // Read until */
        while ( ! $this->is_at_end() ) {
            if ( $this->peek() === '*' && $this->peek_next() === '/' ) {
                $this->advance();
                $this->advance();
                break;
            }

            if ( $this->peek() === "\n" ) {
                $this->line++;
                $this->column = 0;
            }

            $this->advance();
        }

        $comment = substr( $this->source, $start, $this->position - $start );
        $this->tokens[] = $this->create_token( self::TOKEN_COMMENT, $comment );
    }

    /**
     * Scan string literal
     *
     * @param string $quote Quote character used.
     */
    private function scan_string( $quote ) {
        $this->advance(); // Skip opening quote

        $value = '';

        while ( ! $this->is_at_end() && $this->peek() !== $quote ) {
            $char = $this->peek();

            // Handle escape sequences
            if ( $char === '\\' && ! $this->is_at_end() ) {
                $this->advance();
                $escaped = $this->peek();
                switch ( $escaped ) {
                    case 'n':
                        $value .= "\n";
                        break;
                    case 't':
                        $value .= "\t";
                        break;
                    case '\\':
                        $value .= '\\';
                        break;
                    case '"':
                        $value .= '"';
                        break;
                    case "'":
                        $value .= "'";
                        break;
                    default:
                        $value .= $escaped;
                }
                $this->advance();
                continue;
            }

            // Track newlines in multi-line strings
            if ( $char === "\n" ) {
                $this->line++;
                $this->column = 0;
            }

            $value .= $char;
            $this->advance();
        }

        // Skip closing quote
        if ( ! $this->is_at_end() ) {
            $this->advance();
        }

        $this->tokens[] = $this->create_token( self::TOKEN_STRING, $value );
    }

    /**
     * Scan number
     */
    private function scan_number() {
        $start = $this->position;

        // Handle negative
        if ( $this->peek() === '-' ) {
            $this->advance();
        }

        // Integer part
        while ( ! $this->is_at_end() && $this->is_digit( $this->peek() ) ) {
            $this->advance();
        }

        // Decimal part
        if ( ! $this->is_at_end() && $this->peek() === '.' && $this->is_digit( $this->peek_next() ) ) {
            $this->advance(); // Skip .
            while ( ! $this->is_at_end() && $this->is_digit( $this->peek() ) ) {
                $this->advance();
            }
        }

        $value = substr( $this->source, $start, $this->position - $start );
        $this->tokens[] = $this->create_token( self::TOKEN_NUMBER, $value );
    }

    /**
     * Scan identifier (#field_id)
     */
    private function scan_identifier() {
        $this->advance(); // Skip #

        $start = $this->position;

        while ( ! $this->is_at_end() && $this->is_alphanumeric( $this->peek() ) ) {
            $this->advance();
        }

        $value = substr( $this->source, $start, $this->position - $start );
        $this->tokens[] = $this->create_token( self::TOKEN_IDENTIFIER, $value );
    }

    /**
     * Scan modifier (@required, @placeholder, etc.)
     */
    private function scan_modifier() {
        $this->advance(); // Skip @

        $start = $this->position;

        // Read modifier name
        while ( ! $this->is_at_end() && ( $this->is_alphanumeric( $this->peek() ) || $this->peek() === '-' ) ) {
            $this->advance();
        }

        $name = substr( $this->source, $start, $this->position - $start );
        $this->tokens[] = $this->create_token( self::TOKEN_MODIFIER, $name );
    }

    /**
     * Scan choice option (+ Option Text)
     */
    private function scan_choice() {
        $this->advance(); // Skip +

        // Skip whitespace after +
        while ( ! $this->is_at_end() && ( $this->peek() === ' ' || $this->peek() === "\t" ) ) {
            $this->advance();
        }

        $start = $this->position;

        // Read until newline
        while ( ! $this->is_at_end() && $this->peek() !== "\n" ) {
            $this->advance();
        }

        $value = trim( substr( $this->source, $start, $this->position - $start ) );
        $this->tokens[] = $this->create_token( self::TOKEN_CHOICE, $value );
    }

    /**
     * Scan keyword or word
     */
    private function scan_keyword_or_identifier() {
        $start = $this->position;

        while ( ! $this->is_at_end() && ( $this->is_alphanumeric( $this->peek() ) || $this->peek() === '_' ) ) {
            $this->advance();
        }

        $value = substr( $this->source, $start, $this->position - $start );
        $lower = strtolower( $value );

        if ( in_array( $lower, $this->keywords, true ) ) {
            $this->tokens[] = $this->create_token( self::TOKEN_KEYWORD, $lower );
        } else {
            // Treat as string/value
            $this->tokens[] = $this->create_token( self::TOKEN_STRING, $value );
        }
    }

    /**
     * Create a token
     *
     * @param string $type  Token type.
     * @param string $value Token value.
     * @param array  $extra Extra data.
     * @return array Token.
     */
    private function create_token( $type, $value, $extra = array() ) {
        return array_merge( array(
            'type'   => $type,
            'value'  => $value,
            'line'   => $this->line,
            'column' => $this->column,
        ), $extra );
    }

    /**
     * Get current character
     *
     * @return string Current character.
     */
    private function peek() {
        if ( $this->is_at_end() ) {
            return "\0";
        }
        return $this->source[ $this->position ];
    }

    /**
     * Get next character
     *
     * @return string Next character.
     */
    private function peek_next() {
        if ( $this->position + 1 >= $this->length ) {
            return "\0";
        }
        return $this->source[ $this->position + 1 ];
    }

    /**
     * Advance position
     *
     * @return string Previous character.
     */
    private function advance() {
        $this->position++;
        $this->column++;
        return $this->source[ $this->position - 1 ];
    }

    /**
     * Check if at end
     *
     * @return bool True if at end.
     */
    private function is_at_end() {
        return $this->position >= $this->length;
    }

    /**
     * Check if character is digit
     *
     * @param string $char Character.
     * @return bool True if digit.
     */
    private function is_digit( $char ) {
        return $char >= '0' && $char <= '9';
    }

    /**
     * Check if character is alpha
     *
     * @param string $char Character.
     * @return bool True if alpha.
     */
    private function is_alpha( $char ) {
        return ( $char >= 'a' && $char <= 'z' ) ||
               ( $char >= 'A' && $char <= 'Z' ) ||
               $char === '_';
    }

    /**
     * Check if character is alphanumeric
     *
     * @param string $char Character.
     * @return bool True if alphanumeric.
     */
    private function is_alphanumeric( $char ) {
        return $this->is_alpha( $char ) || $this->is_digit( $char );
    }
}
