<?php
/**
 * BFL reader: BFL text to the form model.
 *
 * BFL is a readable notation of the form model (docs/bfl-and-the-model.md).
 * This reader accepts every form the older parser (includes/bfl) accepts,
 * with all four block styles (indentation, { }, [ ] and begin/end), and the
 * newer spellings for everything else the model holds. BAF_BFL_Writer writes
 * the canonical spelling, which this reader turns back into the same model.
 *
 * No WordPress functions are used.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-baf-form-model.php';

/**
 * BAF_BFL_Reader class.
 */
class BAF_BFL_Reader {

    /**
     * The highest BFL version this reader knows.
     */
    const VERSION = 1;

    /**
     * Older type names.
     *
     * @var array
     */
    const TYPE_ALIASES = array(
        'dropdown'   => 'select',
        'paragraph'  => 'textarea',
        'url'        => 'website',
        'checkboxes' => 'checkbox',
        'file'       => 'fileupload',
        'savebutton' => 'save',
    );

    /**
     * Older layout words that become an html field.
     *
     * @var array
     */
    const ELEMENT_TYPES = array( 'divider', 'spacer' );

    /**
     * Modifiers that never take a value.
     *
     * @var array
     */
    const FLAGS = array( 'required', 'unique', 'readonly', 'disabled', 'multiple', 'autofocus', 'searchable', 'hidden', 'admin', 'admin-only', 'confirm', 'allow-other', 'allowother', 'partial', 'case_sensitive' );

    /**
     * Modifiers whose value is an expression running to the end of the line.
     *
     * @var array
     */
    const EXPRESSION_MODIFIERS = array( 'when', 'calculate' );

    /**
     * Older modifier names that become a prop with another name.
     *
     * @var array
     */
    const PROP_ALIASES = array(
        'readonly'   => 'read_only',
        'class'      => 'css_class',
        'css'        => 'css_class',
        'admin'      => 'admin_only',
        'admin-only' => 'admin_only',
        'allow-other' => 'allow_other',
        'allowother' => 'allow_other',
        'confirm'    => 'confirm_email',
    );

    /**
     * Older form modifiers and the settings they stand for.
     *
     * @var array
     */
    const SETTING_ALIASES = array(
        'ajax'     => 'ajax_submission',
        'honeypot' => 'enable_honeypot',
        'captcha'  => 'enable_captcha',
    );

    /**
     * Names in the older compiler's settings block.
     *
     * @var array
     */
    const COMPILER_SETTINGS = array(
        'honeypot'        => 'enable_honeypot',
        'captcha'         => 'enable_captcha',
        'ajax'            => 'ajax_submission',
        'saveAndContinue' => 'save_continue_enabled',
        'rateLimit'       => 'rate_limit_enabled',
    );

    /** @var string */
    private $src = '';
    /** @var array */
    private $toks = array();
    /** @var int */
    private $p = 0;
    /** @var array */
    private $errors = array();
    /** @var array */
    private $warnings = array();
    /** @var int */
    private $item_counter = 0;
    /** @var array */
    private $model = array();
    /** @var int|null Index of the page being filled. */
    private $page = null;
    /** @var bool Whether the current page was written with a page line. */
    private $page_declared = false;
    /** @var string form|page Which header a stray modifier belongs to. */
    private $owner = 'form';

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    /**
     * Read BFL text.
     *
     * @param string $source BFL.
     * @return array array( 'model' => ..., 'errors' => [...], 'warnings' => [...] ).
     *               Each problem is array( 'line' => n, 'message' => ... ).
     */
    public static function read( $source ) {
        $r = new self();
        return $r->run( (string) $source );
    }

    /**
     * Read BFL and return only the model.
     *
     * @param string $source BFL.
     * @return array Model.
     * @throws InvalidArgumentException When the text has errors.
     */
    public static function to_model( $source ) {
        $result = self::read( $source );
        if ( $result['errors'] ) {
            $e = $result['errors'][0];
            throw new InvalidArgumentException( 'Line ' . $e['line'] . ': ' . $e['message'] );
        }
        return $result['model'];
    }

    /**
     * Worker.
     *
     * @param string $source BFL.
     * @return array Result.
     */
    private function run( $source ) {
        $this->src   = str_replace( array( "\r\n", "\r" ), "\n", $source );
        if ( substr( $this->src, 0, 3 ) === "\xEF\xBB\xBF" ) {
            $this->src = substr( $this->src, 3 );
        }
        $this->toks  = $this->lex( $this->src );
        $this->p     = 0;
        $this->model = array( 'version' => BAF_Form_Model::VERSION, 'title' => '', 'pages' => array() );

        $this->parse_top();

        if ( ! $this->model['pages'] ) {
            $this->model['pages'][] = array( 'id' => 'page_1', 'items' => array() );
        }
        foreach ( $this->model['pages'] as &$page ) {
            $page['items'] = $this->finish_items( $page['items'] );
        }
        unset( $page );

        // Put the document's keys in the order the documentation lists them.
        $order = array( 'version', 'id', 'title', 'description', 'language', 'settings', 'choice_libraries', 'pages', 'rules', 'scoring', 'confirmations', 'notifications', 'translations', 'extensions' );
        $model = array();
        foreach ( $order as $k ) {
            if ( array_key_exists( $k, $this->model ) ) {
                $model[ $k ] = $this->model[ $k ];
            }
        }

        return array( 'model' => $model, 'errors' => $this->errors, 'warnings' => $this->warnings );
    }

    // ---------------------------------------------------------------------
    // Lexer
    // ---------------------------------------------------------------------

    /**
     * Split BFL into tokens. Never fails: problems become errors and the
     * lexer carries on.
     *
     * @param string $s Source (LF line ends).
     * @return array Tokens.
     */
    private function lex( $s ) {
        $toks       = array();
        $n          = strlen( $s );
        $i          = 0;
        $line       = 1;
        $indent     = 0;
        $line_first = true;
        $at_line    = true;

        while ( $i < $n ) {
            $c = $s[ $i ];
            if ( $at_line ) {
                $indent = 0;
                while ( $i < $n && ( $s[ $i ] === ' ' || $s[ $i ] === "\t" ) ) {
                    $indent += $s[ $i ] === "\t" ? 4 : 1;
                    $i++;
                }
                $at_line = false;
                continue;
            }
            if ( $c === "\n" ) {
                $line++;
                $i++;
                $at_line    = true;
                $line_first = true;
                continue;
            }
            if ( $c === ' ' || $c === "\t" ) {
                $i++;
                continue;
            }
            if ( $c === '/' && $i + 1 < $n && $s[ $i + 1 ] === '/' ) {
                while ( $i < $n && $s[ $i ] !== "\n" ) {
                    $i++;
                }
                continue;
            }
            if ( $c === '/' && $i + 1 < $n && $s[ $i + 1 ] === '*' ) {
                $end = strpos( $s, '*/', $i + 2 );
                $end = $end === false ? $n : $end + 2;
                $line += substr_count( $s, "\n", $i, $end - $i );
                $i = $end;
                continue;
            }

            $start = $i;
            $tok   = array( 'line' => $line, 'indent' => $indent, 'first' => $line_first, 'start' => $start );

            if ( $c === '"' || $c === "'" ) {
                try {
                    list( $value, $i ) = BAF_Expression::scan_string( $s, $i );
                } catch ( BAF_Expression_Error $e ) {
                    $this->error( $line, 'A quoted value is not closed.' );
                    $eol   = strpos( $s, "\n", $i );
                    $eol   = $eol === false ? $n : $eol;
                    $value = substr( $s, $i + 1, $eol - $i - 1 );
                    $i     = $eol;
                }
                $line += substr_count( $s, "\n", $start, $i - $start );
                $tok['t'] = 'STRING';
                $tok['v'] = $value;
            } elseif ( $c === '#' && $i + 1 < $n && ( ctype_alnum( $s[ $i + 1 ] ) || $s[ $i + 1 ] === '_' ) ) {
                $j = $i + 1;
                while ( $j < $n && ( ctype_alnum( $s[ $j ] ) || $s[ $j ] === '_' ) ) {
                    $j++;
                }
                $tok['t'] = 'ID';
                $tok['v'] = substr( $s, $i + 1, $j - $i - 1 );
                $i        = $j;
            } elseif ( $c === '@' ) {
                $j = $i + 1;
                while ( $j < $n && ( ctype_alnum( $s[ $j ] ) || $s[ $j ] === '_' || $s[ $j ] === '-' ) ) {
                    $j++;
                }
                $tok['t'] = 'MOD';
                $tok['v'] = substr( $s, $i + 1, $j - $i - 1 );
                $i        = $j;
            } elseif ( $c === '+' && ( $line_first || ( $toks && end( $toks )['line'] === $line && in_array( end( $toks )['t'], array( 'LBRACE', 'LBRACKET' ), true ) ) ) ) {
                $eol      = strpos( $s, "\n", $i );
                $eol      = $eol === false ? $n : $eol;
                $tok['t'] = 'CHOICE';
                $tok['v'] = trim( substr( $s, $i + 1, $eol - $i - 1 ) );
                $i        = $eol;
            } elseif ( ctype_digit( $c ) || ( $c === '-' && $i + 1 < $n && ctype_digit( $s[ $i + 1 ] ) ) ) {
                $j = $i + 1;
                while ( $j < $n && ctype_digit( $s[ $j ] ) ) {
                    $j++;
                }
                if ( $j + 1 < $n && $s[ $j ] === '.' && ctype_digit( $s[ $j + 1 ] ) ) {
                    $j++;
                    while ( $j < $n && ctype_digit( $s[ $j ] ) ) {
                        $j++;
                    }
                }
                $text     = substr( $s, $i, $j - $i );
                $tok['t'] = 'NUMBER';
                $tok['v'] = strpos( $text, '.' ) === false ? (int) $text : (float) $text;
                $i        = $j;
            } elseif ( ctype_alpha( $c ) || $c === '_' ) {
                $j = $i + 1;
                while ( $j < $n && ( ctype_alnum( $s[ $j ] ) || $s[ $j ] === '_' || ( $s[ $j ] === '-' && $j + 1 < $n && ctype_alnum( $s[ $j + 1 ] ) ) ) ) {
                    $j++;
                }
                $tok['t'] = 'WORD';
                $tok['v'] = substr( $s, $i, $j - $i );
                $i        = $j;
            } else {
                $map      = array( '{' => 'LBRACE', '}' => 'RBRACE', '[' => 'LBRACKET', ']' => 'RBRACKET' );
                $tok['t'] = $map[ $c ] ?? 'OTHER';
                $tok['v'] = $c;
                $i++;
            }
            $tok['end']  = $i;
            $toks[]      = $tok;
            $line_first  = false;
        }
        $toks[] = array( 't' => 'EOF', 'v' => '', 'line' => $line, 'indent' => 0, 'first' => true, 'start' => $n, 'end' => $n );
        return $toks;
    }

    // ---------------------------------------------------------------------
    // Token helpers
    // ---------------------------------------------------------------------

    /** @return array */
    private function peek( $ahead = 0 ) {
        return $this->toks[ min( $this->p + $ahead, count( $this->toks ) - 1 ) ];
    }

    /** @return array */
    private function next() {
        $t = $this->peek();
        if ( $t['t'] !== 'EOF' ) {
            $this->p++;
        }
        return $t;
    }

    /** @return bool */
    private function at_end() {
        return $this->peek()['t'] === 'EOF';
    }

    /** @return bool */
    private function is_word( $t, $word = null ) {
        return $t['t'] === 'WORD' && ( $word === null || strtolower( $t['v'] ) === $word );
    }

    /** @return bool */
    private function is_open( $t ) {
        return $t['t'] === 'LBRACE' || $t['t'] === 'LBRACKET' || $this->is_word( $t, 'begin' );
    }

    /** @return bool */
    private function is_close( $t ) {
        return $t['t'] === 'RBRACE' || $t['t'] === 'RBRACKET' || $this->is_word( $t, 'end' );
    }

    /** Record an error. */
    private function error( $line, $message ) {
        $this->errors[] = array( 'line' => $line, 'message' => $message );
    }

    /** Record a warning. */
    private function warn( $line, $message ) {
        $this->warnings[] = array( 'line' => $line, 'message' => $message );
    }

    /**
     * The model type a word names, or null.
     *
     * @param string $word Word.
     * @return string|null Type.
     */
    private static function field_type( $word ) {
        $w = strtolower( $word );
        if ( isset( self::TYPE_ALIASES[ $w ] ) ) {
            return self::TYPE_ALIASES[ $w ];
        }
        if ( in_array( $w, self::ELEMENT_TYPES, true ) ) {
            return 'html';
        }
        return BAF_Form_Model::is_field_type( $w ) ? $w : null;
    }

    /**
     * Whether a token starts an item (a field, section, columns or condition).
     *
     * @param array $t Token.
     * @return bool Item.
     */
    private function starts_item( $t ) {
        if ( $t['t'] !== 'WORD' ) {
            return false;
        }
        $w = strtolower( $t['v'] );
        return self::field_type( $w ) !== null || in_array( $w, array( 'section', 'columns', 'if', 'conditional' ), true );
    }

    /**
     * Whether a token starts a top-level statement.
     *
     * @param array $t Token.
     * @return bool Statement.
     */
    private function starts_statement( $t ) {
        return $t['t'] === 'WORD' && in_array( strtolower( $t['v'] ), array( 'form', 'page', 'library', 'rule', 'scoring', 'confirmation', 'notification', 'translation', 'settings', 'bfl' ), true );
    }

    /**
     * The raw text from the current position to the end of the line, for an
     * expression. Stops at a // comment and at a } or ] that closes a block.
     * Tokens inside the text are skipped.
     *
     * @param array $after Token the text follows.
     * @param bool  $header Whether this is an if header, which may end with a block opener.
     * @return string Text.
     */
    private function raw_to_eol( $after, $header = false ) {
        $s     = $this->src;
        $n     = strlen( $s );
        $i     = $after['end'];
        $depth = 0;
        $quote = null;
        $stop  = $n;
        for ( ; $i < $n; $i++ ) {
            $c = $s[ $i ];
            if ( $quote !== null ) {
                if ( $c === '\\' ) {
                    $i++;
                } elseif ( $c === $quote ) {
                    $quote = null;
                } elseif ( $c === "\n" ) {
                    $stop = $i;
                    break;
                }
                continue;
            }
            if ( $c === "\n" || ( $c === '/' && $i + 1 < $n && $s[ $i + 1 ] === '/' ) ) {
                $stop = $i;
                break;
            }
            if ( $c === '"' || $c === "'" ) {
                $quote = $c;
            } elseif ( $c === '{' || $c === '[' || $c === '(' ) {
                $depth++;
            } elseif ( $c === '}' || $c === ']' || $c === ')' ) {
                if ( $depth === 0 ) {
                    $stop = $i;
                    break;
                }
                $depth--;
            }
        }
        $text = substr( $s, $after['end'], $stop - $after['end'] );
        $end  = $stop;
        if ( $header ) {
            $trim = rtrim( $text );
            if ( $trim !== '' && ( substr( $trim, -1 ) === '{' || substr( $trim, -1 ) === '[' ) ) {
                $end  = $after['end'] + strlen( $trim ) - 1;
                $text = substr( $trim, 0, -1 );
            } elseif ( preg_match( '/\sbegin$/i', $trim ) ) {
                $end  = $after['end'] + strlen( $trim ) - 5;
                $text = substr( $trim, 0, -5 );
            }
        }
        while ( ! $this->at_end() && $this->peek()['start'] < $end ) {
            $this->p++;
        }
        return trim( $text );
    }

    /**
     * Parse and canonicalise an expression, recording a problem as an error.
     *
     * @param string $text Text.
     * @param int    $line Line.
     * @return string|null Canonical text.
     */
    private function expression( $text, $line ) {
        try {
            return BAF_Expression::canonical( $text );
        } catch ( BAF_Expression_Error $e ) {
            $this->error( $line, 'The expression "' . $text . '" is not valid: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Capture a balanced JSON value starting at the current token ({ or [).
     *
     * @return array array( bool ok, mixed value ).
     */
    private function try_json() {
        $t = $this->peek();
        $s = $this->src;
        $n = strlen( $s );
        $depth = 0;
        $quote = false;
        for ( $i = $t['start']; $i < $n; $i++ ) {
            $c = $s[ $i ];
            if ( $quote ) {
                if ( $c === '\\' ) {
                    $i++;
                } elseif ( $c === '"' ) {
                    $quote = false;
                }
                continue;
            }
            if ( $c === '"' ) {
                $quote = true;
            } elseif ( $c === '{' || $c === '[' ) {
                $depth++;
            } elseif ( $c === '}' || $c === ']' ) {
                $depth--;
                if ( $depth === 0 ) {
                    break;
                }
            }
        }
        if ( $i >= $n ) {
            return array( false, null );
        }
        $value = json_decode( substr( $s, $t['start'], $i + 1 - $t['start'] ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array( false, null );
        }
        $end = $i + 1;
        while ( ! $this->at_end() && $this->peek()['start'] < $end ) {
            $this->p++;
        }
        return array( true, $value );
    }

    /**
     * Read a modifier's value from the rest of its line, if there is one.
     *
     * @param array $mod The modifier token.
     * @return array array( bool has_value, mixed value ).
     */
    private function mod_value( $mod ) {
        $t = $this->peek();
        if ( $t['line'] !== $mod['line'] || $t['t'] === 'EOF' ) {
            return array( false, null );
        }
        switch ( $t['t'] ) {
            case 'STRING':
            case 'NUMBER':
            case 'ID':
                $this->next();
                return array( true, $t['v'] );
            case 'WORD':
                $w = strtolower( $t['v'] );
                if ( ! in_array( $w, array( 'true', 'false', 'null' ), true ) && ( $this->starts_item( $t ) || $this->starts_statement( $t ) || $this->is_open( $t ) || $this->is_close( $t ) || $w === 'column' || $w === 'else' ) ) {
                    return array( false, null );
                }
                $this->next();
                if ( $w === 'true' || $w === 'false' ) {
                    return array( true, $w === 'true' );
                }
                if ( $w === 'null' ) {
                    return array( true, null );
                }
                return array( true, $t['v'] );
            case 'LBRACE':
                $rest = ltrim( substr( $this->src, $t['start'] + 1, 64 ) );
                if ( $rest !== '' && ( $rest[0] === '"' || $rest[0] === '}' ) ) {
                    list( $ok, $value ) = $this->try_json();
                    if ( $ok ) {
                        return array( true, $value );
                    }
                }
                return array( false, null );
            case 'LBRACKET':
                $save = $this->p;
                list( $ok, $value ) = $this->try_json();
                if ( $ok ) {
                    return array( true, $value );
                }
                // An older list of bare words on one line: [US, CA].
                $this->p = $save;
                $items   = array();
                $i       = 1;
                while ( true ) {
                    $x = $this->peek( $i );
                    if ( $x['line'] !== $t['line'] || $x['t'] === 'EOF' ) {
                        return array( false, null );
                    }
                    if ( $x['t'] === 'RBRACKET' ) {
                        break;
                    }
                    if ( in_array( $x['t'], array( 'STRING', 'WORD', 'NUMBER' ), true ) ) {
                        $items[] = $x['v'];
                    } elseif ( ! ( $x['t'] === 'OTHER' && $x['v'] === ',' ) ) {
                        return array( false, null );
                    }
                    $i++;
                }
                $this->p += $i + 1;
                return array( true, $items );
        }
        return array( false, null );
    }

    // ---------------------------------------------------------------------
    // Blocks
    // ---------------------------------------------------------------------

    /**
     * Read the body of a header: inline modifiers, then an explicit block or
     * the lines indented under it. Calls $on for each modifier, choice and
     * child statement; $on returns false for a child it does not take, which
     * ends an indented block (the parent reads it) or is skipped in an
     * explicit one.
     *
     * @param array    $header Header token.
     * @param callable $on     function( string $kind, array $token ): bool.
     * @return string 'explicit', 'indent' or 'none' (nothing followed).
     */
    private function body( $header, $on ) {
        $explicit = false;
        $base     = $header['indent'];
        $line     = $header['line'];
        $took     = false;
        $children = false;

        while ( ! $this->at_end() ) {
            $t = $this->peek();
            if ( ! $explicit && ! $took && $this->is_open( $t ) && ( $t['line'] === $line || ( $t['first'] && ( $t['t'] === 'LBRACE' || $this->is_word( $t, 'begin' ) ) ) ) ) {
                $this->next();
                $explicit = true;
                continue;
            }
            if ( $explicit && $this->is_close( $t ) ) {
                $this->next();
                return 'explicit';
            }
            if ( ! $explicit && $this->is_close( $t ) ) {
                break;
            }
            if ( ! $explicit && $t['line'] !== $line && $t['first'] && $t['indent'] <= $base ) {
                // Older BFL let modifiers and choices follow a field without
                // indenting them; they still belong to it, up to its first child.
                if ( $children || ! in_array( $t['t'], array( 'MOD', 'CHOICE' ), true ) ) {
                    break;
                }
            }
            if ( $t['t'] === 'MOD' ) {
                $this->next();
                $on( 'mod', $t );
                $took = true;
                continue;
            }
            if ( $t['t'] === 'CHOICE' ) {
                $this->next();
                $on( 'choice', $t );
                $took = true;
                continue;
            }
            if ( $t['t'] === 'WORD' && $on( 'child', $t ) ) {
                $took     = true;
                $children = true;
                continue;
            }
            if ( $t['t'] === 'WORD' && ( $this->starts_item( $t ) || $this->starts_statement( $t ) || $this->is_word( $t, 'column' ) || $this->is_word( $t, 'else' ) ) ) {
                if ( ! $explicit ) {
                    break;
                }
                // An older form left a { block open before the next statement.
                $this->warn( $t['line'], 'A block opened on line ' . $line . ' was not closed.' );
                return 'explicit';
            }
            $this->warn( $t['line'], 'Skipped "' . $t['v'] . '", which BFL does not expect here.' );
            $this->next();
        }
        return $explicit ? 'explicit' : ( $took ? 'indent' : 'none' );
    }

    // ---------------------------------------------------------------------
    // Top level
    // ---------------------------------------------------------------------

    /**
     * The page items are being added to, created on first use.
     *
     * @return int Page index.
     */
    private function current_page() {
        if ( $this->page === null ) {
            $this->model['pages'][] = array( 'id' => 'page_' . ( count( $this->model['pages'] ) + 1 ), 'items' => array() );
            $this->page             = count( $this->model['pages'] ) - 1;
            $this->page_declared    = false;
        }
        return $this->page;
    }

    /**
     * Top-level statements.
     */
    private function parse_top() {
        $first = true;
        while ( ! $this->at_end() ) {
            $t = $this->peek();

            if ( $first && $this->is_word( $t, 'bfl' ) && $t['first'] ) {
                $this->next();
                $v = $this->peek();
                if ( $v['t'] === 'NUMBER' && $v['line'] === $t['line'] ) {
                    $this->next();
                    if ( $v['v'] > self::VERSION ) {
                        $this->error( $t['line'], 'This form is written in BFL ' . $v['v'] . '; this copy of BAF reads BFL ' . self::VERSION . ' and older. Update BAF to open it.' );
                        return;
                    }
                } else {
                    $this->error( $t['line'], 'The bfl line needs a version number, such as "bfl 1".' );
                }
                $first = false;
                continue;
            }
            $first = false;

            if ( $this->is_close( $t ) ) {
                // The end of an explicit form or page block.
                $this->next();
                continue;
            }
            if ( $t['t'] === 'MOD' ) {
                $this->next();
                if ( $this->owner === 'page' && $this->page !== null ) {
                    $this->page_mod( $this->model['pages'][ $this->page ], $t );
                } else {
                    $this->form_mod( $t );
                }
                continue;
            }
            if ( $t['t'] !== 'WORD' ) {
                $this->warn( $t['line'], 'Skipped "' . $t['v'] . '", which BFL does not expect here.' );
                $this->next();
                continue;
            }
            $w = strtolower( $t['v'] );
            switch ( $w ) {
                case 'form':
                    $this->parse_form();
                    continue 2;
                case 'page':
                    $this->parse_page();
                    continue 2;
                case 'library':
                    $this->parse_library();
                    continue 2;
                case 'rule':
                    $this->parse_rule();
                    continue 2;
                case 'scoring':
                    $this->parse_scoring();
                    continue 2;
                case 'confirmation':
                case 'notification':
                    $this->parse_message_object( $w );
                    continue 2;
                case 'translation':
                    $this->parse_translation();
                    continue 2;
                case 'settings':
                    $this->parse_settings_block();
                    continue 2;
            }
            if ( $this->starts_item( $t ) ) {
                $items = $this->parse_item_statement( null );
                $pi    = $this->current_page();
                foreach ( $items as $item ) {
                    $this->model['pages'][ $pi ]['items'][] = $item;
                }
                continue;
            }
            $this->warn( $t['line'], 'Skipped "' . $t['v'] . '", which BFL does not know.' );
            $this->next();
        }
    }

    /**
     * form [#id] ["Title"] modifiers.
     */
    private function parse_form() {
        $header = $this->next();
        if ( $this->peek()['t'] === 'ID' && $this->peek()['line'] === $header['line'] ) {
            $this->model['id'] = $this->next()['v'];
        }
        if ( $this->peek()['t'] === 'STRING' && $this->peek()['line'] === $header['line'] ) {
            $this->model['title'] = $this->next()['v'];
        }
        $this->owner = 'form';
        $this->header_with_open_block( $header, function ( $mod ) {
            $this->form_mod( $mod );
        } );
    }

    /**
     * Read inline modifiers and indented modifiers under a form or page
     * header. An explicit block opener is consumed and its contents are read
     * by the top level, which closes it when it meets the closer.
     *
     * @param array    $header Header.
     * @param callable $mod    Modifier handler.
     */
    private function header_with_open_block( $header, $mod ) {
        while ( ! $this->at_end() ) {
            $t = $this->peek();
            if ( $t['t'] === 'MOD' && ( $t['line'] === $header['line'] || ( $t['first'] && $t['indent'] > $header['indent'] ) || ! $t['first'] ) ) {
                $this->next();
                $mod( $t );
                continue;
            }
            if ( $this->is_open( $t ) && ( $t['line'] === $header['line'] || $t['first'] ) ) {
                $this->next();
                return;
            }
            return;
        }
    }

    /**
     * A form-level modifier.
     *
     * @param array $mod Token.
     */
    private function form_mod( $mod ) {
        $name = $mod['v'];
        list( $has, $value ) = $this->mod_value( $mod );
        switch ( $name ) {
            case 'description':
            case 'desc':
                $this->model['description'] = (string) $value;
                return;
            case 'language':
                $this->model['language'] = (string) $value;
                return;
            case 'extensions':
                if ( is_array( $value ) ) {
                    $this->model['extensions'] = $value;
                } else {
                    $this->error( $mod['line'], '@extensions needs a JSON object.' );
                }
                return;
        }
        if ( isset( self::SETTING_ALIASES[ $name ] ) ) {
            $this->model['settings'][ self::SETTING_ALIASES[ $name ] ] = true;
            if ( $name === 'captcha' && $has && is_string( $value ) ) {
                $this->model['settings']['captcha_type'] = $value;
            }
            return;
        }
        $this->model['settings'][ str_replace( '-', '_', $name ) ] = $has ? $value : true;
    }

    /**
     * page [#id] ["Title"] modifiers.
     */
    private function parse_page() {
        $header = $this->next();
        // The first page line takes over the page that items before it would
        // otherwise have started, when nothing has been put on it yet.
        $reuse = $this->page !== null && ! $this->page_declared && ! $this->model['pages'][ $this->page ]['items'] && count( $this->model['pages'] ) === 1;
        if ( ! $reuse ) {
            $this->model['pages'][] = array( 'id' => 'page_' . ( count( $this->model['pages'] ) + 1 ), 'items' => array() );
            $this->page             = count( $this->model['pages'] ) - 1;
        }
        $this->page_declared = true;
        $page                = &$this->model['pages'][ $this->page ];
        if ( $this->peek()['t'] === 'ID' && $this->peek()['line'] === $header['line'] ) {
            $page['id'] = $this->next()['v'];
        }
        if ( $this->peek()['t'] === 'STRING' && $this->peek()['line'] === $header['line'] ) {
            $page['title'] = $this->next()['v'];
        }
        $this->owner = 'page';
        $this->header_with_open_block( $header, function ( $mod ) use ( &$page ) {
            $this->page_mod( $page, $mod );
        } );
        // Keep "items" last, as the documentation lists it.
        $items = $page['items'];
        unset( $page['items'] );
        $page['items'] = $items;
        unset( $page );
    }

    /**
     * A page modifier.
     *
     * @param array $page Page (by reference).
     * @param array $mod  Token.
     */
    private function page_mod( &$page, $mod ) {
        $name = $mod['v'];
        if ( $name === 'when' ) {
            $expr = $this->expression( $this->raw_to_eol( $mod ), $mod['line'] );
            if ( $expr !== null ) {
                $page['when'] = $expr;
            }
            return;
        }
        list( $has, $value ) = $this->mod_value( $mod );
        if ( $name === 'description' || $name === 'desc' ) {
            $page['description'] = (string) $value;
        } elseif ( $name === 'title' ) {
            $page['title'] = (string) $value;
        } else {
            $page['props'][ str_replace( '-', '_', $name ) ] = $has ? $value : true;
        }
    }

    // ---------------------------------------------------------------------
    // Items
    // ---------------------------------------------------------------------

    /**
     * Read one item statement: a field, section or columns (one item), or an
     * if block (any number of items, each given the condition).
     *
     * @param string|null $condition Condition from enclosing if blocks.
     * @return array Items.
     */
    private function parse_item_statement( $condition ) {
        $t = $this->peek();
        $w = strtolower( $t['v'] );
        if ( $w === 'if' || $w === 'conditional' ) {
            return $this->parse_if( $condition );
        }
        $item = $w === 'section' ? $this->parse_section() : ( $w === 'columns' ? $this->parse_columns() : $this->parse_field() );
        if ( $condition !== null ) {
            $item['when'] = isset( $item['when'] ) ? self::both( $condition, $item['when'] ) : $condition;
            $item         = self::order_item( $item );
        }
        return array( $item );
    }

    /**
     * Join two conditions with and.
     *
     * @param string $a Condition.
     * @param string $b Condition.
     * @return string Canonical text.
     */
    private static function both( $a, $b ) {
        return BAF_Expression::canonical( BAF_Expression::to_text( array( 'type' => 'op', 'op' => 'and', 'args' => array( BAF_Expression::parse( $a ), BAF_Expression::parse( $b ) ) ) ) );
    }

    /**
     * Read the children of a container as items.
     *
     * @param array       $header    Container header token.
     * @param string|null $condition Condition to apply.
     * @param callable    $mod       Handler for the container's own modifiers.
     * @param array       $extra     Extra child words: word => callable( token ) returning true when taken.
     * @return array array( items, mode ).
     */
    private function container( $header, $condition, $mod, $extra = array() ) {
        $items = array();
        $mode  = $this->body( $header, function ( $kind, $t ) use ( &$items, $condition, $mod, $extra ) {
            if ( $kind === 'mod' ) {
                $mod( $t );
                return true;
            }
            if ( $kind === 'choice' ) {
                $this->warn( $t['line'], 'A choice ("+ ' . $t['v'] . '") is only allowed under a choice field.' );
                return true;
            }
            $w = strtolower( $t['v'] );
            if ( isset( $extra[ $w ] ) ) {
                return $extra[ $w ]( $t );
            }
            if ( $this->starts_item( $t ) ) {
                foreach ( $this->parse_item_statement( $condition ) as $item ) {
                    $items[] = $item;
                }
                return true;
            }
            return false;
        } );
        return array( $items, $mode );
    }

    /**
     * if <expression> block [else block].
     *
     * @param string|null $outer Condition from enclosing if blocks.
     * @return array Items.
     */
    private function parse_if( $outer ) {
        $header = $this->next();
        $text   = $this->raw_to_eol( $header, true );
        $cond   = $this->expression( $text, $header['line'] );
        $cond   = $cond === null ? 'false' : $cond;
        $full   = $outer === null ? $cond : self::both( $outer, $cond );
        list( $items ) = $this->container( $header, $full, function ( $t ) {
            $this->warn( $t['line'], 'An if block holds items, not modifiers; @' . $t['v'] . ' was skipped.' );
            $this->mod_value( $t );
        } );
        $t = $this->peek();
        if ( $this->is_word( $t, 'else' ) && ( ! $t['first'] || $t['indent'] === $header['indent'] ) ) {
            $this->next();
            $negated = BAF_Expression::canonical( BAF_Expression::to_text( array( 'type' => 'op', 'op' => 'not', 'args' => array( BAF_Expression::parse( $cond ) ) ) ) );
            $else    = $outer === null ? $negated : self::both( $outer, $negated );
            list( $more ) = $this->container( $t, $else, function ( $m ) {
                $this->mod_value( $m );
            } );
            $items = array_merge( $items, $more );
        }
        return $items;
    }

    /**
     * Next automatic item ID: field_1, field_2, ... in document order, as
     * the older parser numbered them.
     *
     * @return string ID.
     */
    private function auto_id() {
        $this->item_counter++;
        return 'field_' . $this->item_counter;
    }

    /**
     * Optional #id and "label" on a header line.
     *
     * @param array $header Header.
     * @param array $item   Item to fill (by reference).
     * @param string $label_key Key for the string.
     */
    private function header_id_label( $header, &$item, $label_key = 'label' ) {
        $auto = $this->auto_id();
        if ( $this->peek()['t'] === 'ID' && $this->peek()['line'] === $header['line'] ) {
            $item['id'] = $this->next()['v'];
        } else {
            $item['id'] = $auto;
        }
        if ( $this->peek()['t'] === 'STRING' && $this->peek()['line'] === $header['line'] ) {
            $item[ $label_key ] = $this->next()['v'];
        }
    }

    /**
     * section [#id] ["Label"] modifiers, then its items.
     *
     * @return array Section.
     */
    private function parse_section() {
        $header  = $this->next();
        $section = array( 'type' => 'section' );
        $this->header_id_label( $header, $section );
        list( $items, $mode ) = $this->container( $header, null, function ( $mod ) use ( &$section ) {
            $this->item_mod( $section, $mod );
        } );
        $section['items'] = $items;
        if ( $mode !== 'explicit' && ! $items ) {
            // Older BFL wrote sections flat: the fields after a section are in it.
            $section['__flat'] = true;
        }
        return self::order_item( $section );
    }

    /**
     * columns [#id] [count] modifiers, then column blocks.
     *
     * @return array Columns.
     */
    private function parse_columns() {
        $header = $this->next();
        $item   = array( 'type' => 'columns' );
        $this->header_id_label( $header, $item );
        unset( $item['label'] );
        $count = null;
        if ( $this->peek()['t'] === 'NUMBER' && $this->peek()['line'] === $header['line'] ) {
            $count = $this->next()['v'];
        }
        $columns = array();
        list( $stray ) = $this->container( $header, null, function ( $mod ) use ( &$item ) {
            $this->item_mod( $item, $mod );
        }, array(
            'column' => function ( $t ) use ( &$columns ) {
                $this->next();
                $col = array();
                list( $items ) = $this->container( $t, null, function ( $mod ) use ( &$col ) {
                    list( $has, $value ) = $this->mod_value( $mod );
                    if ( $mod['v'] === 'width' ) {
                        $col['width'] = (string) $value;
                    } else {
                        $this->warn( $mod['line'], 'A column has only a width; @' . $mod['v'] . ' was skipped.' );
                    }
                    unset( $has );
                } );
                $col['items'] = $items;
                $columns[]    = $col;
                return true;
            },
        ) );
        if ( $stray ) {
            $this->warn( $header['line'], 'Fields directly inside columns belong in a column; they were put in the last column.' );
            if ( ! $columns ) {
                $columns[] = array( 'items' => array() );
            }
            $columns[ count( $columns ) - 1 ]['items'] = array_merge( $columns[ count( $columns ) - 1 ]['items'], $stray );
        }
        if ( ! $columns ) {
            $columns[] = array( 'items' => array() );
        }
        if ( $count !== null && $count !== count( $columns ) ) {
            $this->warn( $header['line'], 'This columns layout says ' . $count . ' columns and has ' . count( $columns ) . '.' );
        }
        $item['columns'] = $columns;
        return self::order_item( $item );
    }

    /**
     * A field: type [#id] ["Label"] modifiers, choices and (repeaters) items.
     *
     * @return array Field.
     */
    private function parse_field() {
        $header = $this->next();
        $word   = strtolower( $header['v'] );
        $field  = array( 'type' => self::field_type( $word ) );
        $this->header_id_label( $header, $field );
        if ( in_array( $word, self::ELEMENT_TYPES, true ) ) {
            $field['props']['element'] = $word;
        }
        $choices = array();
        $items   = array();
        $this->body( $header, function ( $kind, $t ) use ( &$field, &$choices, &$items ) {
            if ( $kind === 'mod' ) {
                $this->item_mod( $field, $t );
                return true;
            }
            if ( $kind === 'choice' ) {
                $choice = $this->choice( $t );
                if ( $choice ) {
                    $choices[] = $choice;
                }
                return true;
            }
            if ( $field['type'] === 'repeater' && $this->starts_item( $t ) ) {
                foreach ( $this->parse_item_statement( null ) as $item ) {
                    $items[] = $item;
                }
                return true;
            }
            return false;
        } );
        if ( $choices ) {
            $field['choices'] = $choices;
        }
        if ( $field['type'] === 'repeater' ) {
            $field['items'] = $items;
        }
        if ( $field['type'] === 'checkbox' && ! isset( $field['choices'] ) && ! isset( $field['choice_library'] ) && ! isset( $field['cascade'] ) ) {
            // A checkbox written without choices is one box, checked or not.
            $field['choices'] = array( array( 'value' => 'yes', 'label' => 'Yes' ) );
        }
        return self::order_item( $field );
    }

    /**
     * Apply an item modifier (fields, sections and columns).
     *
     * @param array $item Item (by reference).
     * @param array $mod  Token.
     */
    private function item_mod( &$item, $mod ) {
        $name = $mod['v'];
        if ( in_array( $name, self::EXPRESSION_MODIFIERS, true ) || $name === 'smart_default' ) {
            $nt = $this->peek();
            if ( $name === 'smart_default' && $nt['t'] === 'STRING' && $nt['line'] === $mod['line'] && $this->peek( 1 )['line'] !== $mod['line'] ) {
                // Older BFL: @smart_default "today+7".
                $this->next();
                $item['smart_default'] = self::legacy_smart_default( $nt['v'] );
                return;
            }
            $expr = $this->expression( $this->raw_to_eol( $mod ), $mod['line'] );
            if ( $expr === null ) {
                return;
            }
            $key          = array( 'when' => 'when', 'calculate' => 'calculation', 'smart_default' => 'smart_default' )[ $name ];
            $item[ $key ] = $expr;
            return;
        }
        $has   = false;
        $value = null;
        if ( ! in_array( $name, self::FLAGS, true ) ) {
            list( $has, $value ) = $this->mod_value( $mod );
        } else {
            // A flag may say true or false outright: @required false.
            $nt = $this->peek();
            if ( $nt['t'] === 'WORD' && $nt['line'] === $mod['line'] && in_array( strtolower( $nt['v'] ), array( 'true', 'false' ), true ) ) {
                $this->next();
                $has   = true;
                $value = strtolower( $nt['v'] ) === 'true';
            }
        }
        $flag = $has ? (bool) $value : true;
        switch ( $name ) {
            case 'required':
                $item['required'] = $flag;
                return;
            case 'placeholder':
                $item['placeholder'] = (string) $value;
                return;
            case 'default':
                $item['default'] = $value;
                return;
            case 'description':
            case 'desc':
            case 'help':
                $item['help'] = (string) $value;
                return;
            case 'mask':
                $item['mask'] = (string) $value;
                return;
            case 'library':
                $item['choice_library'] = (string) $value;
                return;
            case 'cascade_from':
            case 'cascadefrom':
                $item['cascade']['from'] = (string) $value;
                return;
            case 'cascade_library':
            case 'cascadelibrary':
                $item['cascade']['library'] = (string) $value;
                return;
            case 'empty_message':
            case 'emptymessage':
                $item['cascade']['empty_message'] = (string) $value;
                return;
            case 'loading_message':
            case 'loadingmessage':
                $item['cascade']['loading_message'] = (string) $value;
                return;
            case 'min':
            case 'max':
                $item['validation'][ $name ] = $value;
                return;
            case 'minlength':
            case 'min_length':
                $item['validation']['min_length'] = (int) $value;
                return;
            case 'maxlength':
            case 'max_length':
                $item['validation']['max_length'] = (int) $value;
                return;
            case 'pattern':
                $item['validation']['pattern'] = (string) $value;
                return;
            case 'error_message':
                $item['validation']['message'] = (string) $value;
                return;
            case 'unique':
                $item['validation']['unique'] = $flag;
                return;
            case 'unique_scope':
                $item['validation']['unique_scope'] = (string) $value;
                return;
            case 'unique_message':
                $item['validation']['unique_message'] = (string) $value;
                return;
            case 'attr':
                $attr_value = null;
                if ( $has ) {
                    list( $has2, $attr_value ) = $this->mod_value( $mod );
                    if ( ! $has2 ) {
                        $attr_value = null;
                    }
                }
                if ( $has && $attr_value !== null && self::safe_attribute( (string) $value ) ) {
                    $item['attributes'][ (string) $value ] = $attr_value;
                } elseif ( $has ) {
                    $this->warn( $mod['line'], 'The attribute "' . $value . '" is not allowed and was skipped.' );
                }
                return;
            case 'correct':
                $item['scoring']['correct'] = array_map( 'strval', is_array( $value ) ? $value : array( $value ) );
                return;
            case 'points':
                $item['scoring']['points'] = $value;
                return;
            case 'partial':
            case 'case_sensitive':
                $item['scoring'][ $name ] = $flag;
                return;
            case 'feedback_correct':
            case 'feedback_incorrect':
            case 'feedback_partial':
            case 'explanation':
                $item['scoring'][ $name ] = (string) $value;
                return;
            case 'hidden':
                $item['props']['visibility'] = 'hidden';
                return;
            case 'spellcheck':
                $item['props']['spellcheck'] = $has ? ( is_string( $value ) ? in_array( strtolower( $value ), array( 'true', 'yes', '1', 'on' ), true ) : $value ) : true;
                return;
            case 'capture':
                $item['props']['capture'] = $has ? $value : 'environment';
                return;
        }
        $key = self::PROP_ALIASES[ $name ] ?? str_replace( '-', '_', $name );
        $item['props'][ $key ] = in_array( $name, self::FLAGS, true ) ? $flag : ( $has ? $value : true );
    }

    /**
     * Whether an HTML attribute name is one the model allows.
     *
     * @param string $name Name.
     * @return bool Allowed.
     */
    private static function safe_attribute( $name ) {
        return preg_match( '/^(data-[a-z0-9_.-]+|aria-[a-z]+|title|lang|dir|translate|contenteditable|draggable|spellcheck|tabindex|accesskey|role|slot|part)$/', $name ) === 1;
    }

    /**
     * Older smart default text to an expression.
     *
     * @param string $text "today", "today+7", "user:display_name", "query:ref", "geo".
     * @return string Canonical expression.
     */
    public static function legacy_smart_default( $text ) {
        $t = trim( (string) $text );
        if ( preg_match( '/^today\s*([+-])\s*(\d+)$/i', $t, $m ) ) {
            return 'today() ' . $m[1] . ' ' . (int) $m[2];
        }
        if ( strtolower( $t ) === 'today' ) {
            return 'today()';
        }
        if ( strtolower( $t ) === 'geo' ) {
            return 'geo()';
        }
        if ( preg_match( '/^(user|query|get):([A-Za-z0-9_.-]+)$/i', $t, $m ) ) {
            $fn = strtolower( $m[1] ) === 'user' ? 'user' : 'query';
            return $fn . '(' . BAF_Expression::quote( $m[2] ) . ')';
        }
        return BAF_Expression::quote( $t );
    }

    /**
     * A choice line.
     *
     * Quoted form: + "value" | "label" @price 10 @points 2 ...
     *              + "label"            (the value is the label's slug)
     * Older form:  + label | + value|label | + label:price
     *
     * @param array $t CHOICE token.
     * @return array|null Choice.
     */
    private function choice( $t ) {
        $raw = $t['v'];
        if ( $raw === '' ) {
            $this->warn( $t['line'], 'An empty choice was skipped.' );
            return null;
        }
        if ( $raw[0] !== '"' ) {
            if ( strpos( $raw, '|' ) !== false ) {
                list( $value, $label ) = explode( '|', $raw, 2 );
                return array( 'value' => trim( $value ), 'label' => trim( $label ) );
            }
            if ( strpos( $raw, ':' ) !== false ) {
                list( $label, $price ) = explode( ':', $raw, 2 );
                $label  = trim( $label );
                $price  = trim( $price );
                $choice = array( 'value' => BAF_Form_Model::slug( $label ), 'label' => $label );
                if ( is_numeric( $price ) ) {
                    $choice['price'] = strpos( $price, '.' ) === false ? (int) $price : (float) $price;
                } else {
                    // Not a price after all: the colon is part of the label.
                    $choice = array( 'value' => BAF_Form_Model::slug( $raw ), 'label' => $raw );
                }
                return $choice;
            }
            return array( 'value' => BAF_Form_Model::slug( $raw ), 'label' => $raw );
        }

        // Quoted form: lex the line on its own.
        $sub    = new self();
        $sub->src = $raw;
        $toks   = $sub->lex( $raw );
        $first  = $toks[0];
        $choice = array();
        $i      = 1;
        if ( ( $toks[1]['t'] ?? '' ) === 'OTHER' && $toks[1]['v'] === '|' && ( $toks[2]['t'] ?? '' ) === 'STRING' ) {
            $choice = array( 'value' => (string) $first['v'], 'label' => (string) $toks[2]['v'] );
            $i      = 3;
        } else {
            $choice = array( 'value' => BAF_Form_Model::slug( $first['v'] ), 'label' => (string) $first['v'] );
        }
        foreach ( $sub->errors as $e ) {
            $this->error( $t['line'], $e['message'] );
        }
        $sub->toks = $toks;
        $sub->p    = $i;
        while ( ! $sub->at_end() ) {
            $m = $sub->next();
            if ( $m['t'] !== 'MOD' ) {
                $this->warn( $t['line'], 'Skipped "' . $m['v'] . '" in a choice.' );
                continue;
            }
            list( $has, $value ) = $sub->mod_value( $m );
            switch ( $m['v'] ) {
                case 'price':
                case 'points':
                    $choice[ $m['v'] ] = $value;
                    break;
                case 'image':
                case 'description':
                    $choice[ $m['v'] ] = (string) $value;
                    break;
                case 'selected':
                    $choice['selected'] = $has ? (bool) $value : true;
                    break;
                default:
                    $this->warn( $t['line'], 'A choice has no "' . $m['v'] . '"; it was skipped.' );
            }
        }
        return $choice;
    }

    /**
     * Fixed key order for an item, so a model read from BFL is laid out
     * the same way the documentation lists the properties.
     *
     * @param array $item Item.
     * @return array Item.
     */
    private static function order_item( $item ) {
        $order = array( 'id', 'type', 'label', 'help', 'placeholder', 'required', 'default', 'smart_default', 'mask', 'when', 'calculation', 'choices', 'choice_library', 'cascade', 'validation', 'scoring', 'attributes', 'columns', 'items', 'props', '__flat' );
        $out   = array();
        foreach ( $order as $k ) {
            if ( array_key_exists( $k, $item ) ) {
                $out[ $k ] = $item[ $k ];
            }
        }
        return $out;
    }

    /**
     * Fold flat sections and clean up a list of items, recursively.
     *
     * @param array $items Items.
     * @return array Items.
     */
    private function finish_items( $items ) {
        $out  = array();
        $open = null;
        foreach ( $items as $item ) {
            if ( ( $item['type'] ?? '' ) === 'section' ) {
                $open = null;
                if ( ! empty( $item['__flat'] ) ) {
                    unset( $item['__flat'] );
                    $out[] = $item;
                    $open  = count( $out ) - 1;
                    continue;
                }
            }
            if ( $open !== null ) {
                $out[ $open ]['items'][] = $item;
            } else {
                $out[] = $item;
            }
        }
        foreach ( $out as &$item ) {
            if ( ( $item['type'] ?? '' ) === 'columns' ) {
                foreach ( $item['columns'] as &$col ) {
                    $col['items'] = $this->finish_items( $col['items'] );
                }
                unset( $col );
            } elseif ( isset( $item['items'] ) ) {
                $item['items'] = $this->finish_items( $item['items'] );
            }
            unset( $item['__flat'] );
        }
        unset( $item );
        return $out;
    }

    // ---------------------------------------------------------------------
    // Everything else
    // ---------------------------------------------------------------------

    /**
     * library #name, then choices.
     */
    private function parse_library() {
        $header = $this->next();
        $name   = null;
        if ( in_array( $this->peek()['t'], array( 'ID', 'WORD', 'STRING' ), true ) && $this->peek()['line'] === $header['line'] ) {
            $name = (string) $this->next()['v'];
        }
        $choices = array();
        $this->body( $header, function ( $kind, $t ) use ( &$choices ) {
            if ( $kind === 'choice' ) {
                $c = $this->choice( $t );
                if ( $c ) {
                    $choices[] = $c;
                }
                return true;
            }
            if ( $kind === 'mod' ) {
                $this->warn( $t['line'], 'A library holds only choices; @' . $t['v'] . ' was skipped.' );
                $this->mod_value( $t );
                return true;
            }
            return false;
        } );
        if ( $name === null ) {
            $this->error( $header['line'], 'A library needs a name: library #sizes.' );
            return;
        }
        $this->model['choice_libraries'][ $name ] = $choices;
    }

    /**
     * rule <action> #target ... with @value and @when.
     */
    private function parse_rule() {
        $header = $this->next();
        $rule   = array();
        if ( $this->peek()['t'] === 'WORD' && $this->peek()['line'] === $header['line'] ) {
            $rule['action'] = strtolower( str_replace( '-', '_', $this->next()['v'] ) );
        }
        $rule['targets'] = array();
        while ( $this->peek()['t'] === 'ID' && $this->peek()['line'] === $header['line'] ) {
            $rule['targets'][] = $this->next()['v'];
        }
        $this->body( $header, function ( $kind, $t ) use ( &$rule ) {
            if ( $kind !== 'mod' ) {
                return false;
            }
            if ( $t['v'] === 'when' || $t['v'] === 'value' ) {
                $expr = $this->expression( $this->raw_to_eol( $t ), $t['line'] );
                if ( $expr !== null ) {
                    $rule[ $t['v'] ] = $expr;
                }
            } else {
                $this->warn( $t['line'], 'A rule has only @when and @value; @' . $t['v'] . ' was skipped.' );
                $this->mod_value( $t );
            }
            return true;
        } );
        if ( ! isset( $rule['action'] ) ) {
            $this->error( $header['line'], 'A rule needs an action: show, hide, require, optional, enable, disable, set or skip_to.' );
            return;
        }
        $ordered = array();
        foreach ( array( 'action', 'targets', 'when', 'value' ) as $k ) {
            if ( isset( $rule[ $k ] ) ) {
                $ordered[ $k ] = $rule[ $k ];
            }
        }
        $this->model['rules'][] = $ordered;
    }

    /**
     * scoring, its modifiers and its outcomes.
     */
    private function parse_scoring() {
        $header  = $this->next();
        $scoring = $this->model['scoring'] ?? array();
        $this->body( $header, function ( $kind, $t ) use ( &$scoring ) {
            if ( $kind === 'mod' ) {
                list( $has, $value ) = $this->mod_value( $t );
                $scoring[ $t['v'] ] = $has ? $value : true;
                return true;
            }
            if ( $kind === 'child' && $this->is_word( $t, 'outcome' ) ) {
                $this->next();
                $n       = count( $scoring['outcomes'] ?? array() ) + 1;
                $outcome = array( 'id' => 'outcome_' . $n );
                if ( $this->peek()['t'] === 'ID' && $this->peek()['line'] === $t['line'] ) {
                    $outcome['id'] = $this->next()['v'];
                }
                $this->body( $t, function ( $k2, $m ) use ( &$outcome ) {
                    if ( $k2 !== 'mod' ) {
                        return false;
                    }
                    if ( $m['v'] === 'when' ) {
                        $expr = $this->expression( $this->raw_to_eol( $m ), $m['line'] );
                        if ( $expr !== null ) {
                            $outcome['when'] = $expr;
                        }
                    } else {
                        list( $has, $value ) = $this->mod_value( $m );
                        $outcome[ $m['v'] ] = $has ? $value : true;
                    }
                    return true;
                } );
                $scoring['outcomes'][] = $outcome;
                return true;
            }
            return false;
        } );
        $this->model['scoring'] = $scoring;
    }

    /**
     * confirmation [#id] or notification [#id], with modifiers.
     *
     * @param string $kind confirmation|notification.
     */
    private function parse_message_object( $kind ) {
        $header = $this->next();
        $list   = $kind . 's';
        $obj    = array( 'id' => $kind . '_' . ( count( $this->model[ $list ] ?? array() ) + 1 ) );
        if ( $this->peek()['t'] === 'ID' && $this->peek()['line'] === $header['line'] ) {
            $obj['id'] = $this->next()['v'];
        }
        if ( $kind === 'confirmation' ) {
            $obj['type'] = 'message';
        }
        $fixed = BAF_Form_Model::FIXED_NAMES[ $kind ];
        $this->body( $header, function ( $k, $t ) use ( &$obj, $fixed ) {
            if ( $k !== 'mod' ) {
                return false;
            }
            if ( $t['v'] === 'when' ) {
                $expr = $this->expression( $this->raw_to_eol( $t ), $t['line'] );
                if ( $expr !== null ) {
                    $obj['when'] = $expr;
                }
                return true;
            }
            list( $has, $value ) = $this->mod_value( $t );
            $value = $has ? $value : true;
            if ( in_array( $t['v'], $fixed, true ) ) {
                $obj[ $t['v'] ] = $value;
            } else {
                $obj['props'][ str_replace( '-', '_', $t['v'] ) ] = $value;
            }
            return true;
        } );
        $ordered = array();
        foreach ( $fixed as $k ) {
            if ( array_key_exists( $k, $obj ) ) {
                $ordered[ $k ] = $obj[ $k ];
            }
        }
        $this->model[ $list ][] = $ordered;
    }

    /**
     * translation "es", with its parts.
     */
    private function parse_translation() {
        $header = $this->next();
        $lang   = null;
        if ( in_array( $this->peek()['t'], array( 'STRING', 'WORD' ), true ) && $this->peek()['line'] === $header['line'] ) {
            $lang = (string) $this->next()['v'];
        }
        $tr = array();
        $this->body( $header, function ( $kind, $t ) use ( &$tr ) {
            if ( $kind === 'mod' ) {
                list( $has, $value ) = $this->mod_value( $t );
                if ( in_array( $t['v'], array( 'title', 'description' ), true ) ) {
                    $tr[ $t['v'] ] = (string) $value;
                } else {
                    $this->warn( $t['line'], 'A translation has @title and @description; @' . $t['v'] . ' was skipped.' );
                }
                unset( $has );
                return true;
            }
            if ( $kind !== 'child' ) {
                return false;
            }
            $w   = strtolower( $t['v'] );
            $map = array( 'page' => 'pages', 'field' => 'fields', 'confirmation' => 'confirmations', 'outcome' => 'outcomes', 'message' => 'messages' );
            if ( ! isset( $map[ $w ] ) ) {
                return false;
            }
            $this->next();
            $id = null;
            if ( in_array( $this->peek()['t'], array( 'ID', 'WORD' ), true ) && $this->peek()['line'] === $t['line'] ) {
                $id = (string) $this->next()['v'];
            }
            if ( $id === null ) {
                $this->error( $t['line'], 'Say which ' . $w . ' this translates: ' . $w . ' #id.' );
                return true;
            }
            if ( $w === 'message' ) {
                if ( $this->peek()['t'] === 'STRING' && $this->peek()['line'] === $t['line'] ) {
                    $tr['messages'][ $id ] = $this->next()['v'];
                }
                return true;
            }
            $part    = array();
            $choices = array();
            $this->body( $t, function ( $k2, $m ) use ( &$part, &$choices, $w ) {
                if ( $k2 === 'mod' ) {
                    list( $has, $value ) = $this->mod_value( $m );
                    $key          = $m['v'] === 'description' && $w === 'field' ? 'help' : $m['v'];
                    $part[ $key ] = (string) $value;
                    unset( $has );
                    return true;
                }
                if ( $k2 === 'choice' ) {
                    $c = $this->choice( $m );
                    if ( $c ) {
                        $choices[ $c['value'] ] = $c['label'];
                    }
                    return true;
                }
                return false;
            } );
            if ( $choices ) {
                $part['choices'] = $choices;
            }
            $allowed = array(
                'pages'         => array( 'title', 'description' ),
                'fields'        => array( 'label', 'help', 'placeholder', 'choices' ),
                'confirmations' => array( 'message' ),
                'outcomes'      => array( 'message' ),
            )[ $map[ $w ] ];
            $clean   = array();
            foreach ( $allowed as $k ) {
                if ( isset( $part[ $k ] ) ) {
                    $clean[ $k ] = $part[ $k ];
                }
            }
            foreach ( array_diff( array_keys( $part ), $allowed ) as $k ) {
                $this->warn( $t['line'], 'A translated ' . $w . ' has no @' . $k . '; it was skipped.' );
            }
            $tr[ $map[ $w ] ][ $id ] = $clean;
            return true;
        } );
        if ( $lang === null ) {
            $this->error( $header['line'], 'Say which language: translation "es".' );
            return;
        }
        $ordered = array();
        foreach ( array( 'title', 'description', 'pages', 'fields', 'confirmations', 'outcomes', 'messages' ) as $k ) {
            if ( isset( $tr[ $k ] ) ) {
                $ordered[ $k ] = $tr[ $k ];
            }
        }
        $this->model['translations'][ $lang ] = $ordered;
    }

    /**
     * The older compiler's settings block: settings / name value lines.
     */
    private function parse_settings_block() {
        $header = $this->next();
        $line   = $header['line'];
        while ( ! $this->at_end() ) {
            $t = $this->peek();
            if ( $t['first'] && $t['indent'] <= $header['indent'] && $t['line'] !== $line ) {
                break;
            }
            if ( $t['t'] !== 'WORD' ) {
                $this->next();
                continue;
            }
            $this->next();
            list( $has, $value ) = $this->mod_value( $t );
            $key = self::COMPILER_SETTINGS[ $t['v'] ] ?? $t['v'];
            $this->model['settings'][ $key ] = $has ? $value : true;
        }
    }
}
