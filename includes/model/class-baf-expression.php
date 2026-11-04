<?php
/**
 * The BAF expression language.
 *
 * One small language for conditions, calculations, quiz scoring and smart
 * defaults. The form model stores expressions as canonical text; this class
 * parses that text into a tree (the AST, which is plain JSON-shaped arrays),
 * writes a tree back to canonical text, evaluates it, and renders it as an
 * English sentence. The grammar and every rule of evaluation are written down
 * in docs/expression-language.md, and tests/conformance/expressions holds
 * the cases any other implementation (QUILL's Python reader) must match.
 *
 * No WordPress functions are used, so this file runs anywhere PHP does.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Raised for text the expression grammar does not accept.
 */
class BAF_Expression_Error extends Exception {

    /**
     * Character offset of the problem in the source text.
     *
     * @var int
     */
    public $offset = 0;

    /**
     * Constructor.
     *
     * @param string $message Message.
     * @param int    $offset  Offset.
     */
    public function __construct( $message, $offset = 0 ) {
        parent::__construct( $message );
        $this->offset = $offset;
    }
}

/**
 * BAF_Expression class.
 */
class BAF_Expression {

    /**
     * Functions the language knows, with their allowed argument counts
     * (minimum, maximum; -1 means any number).
     *
     * @var array
     */
    const FUNCTIONS = array(
        'today'        => array( 0, 0 ),
        'date'         => array( 1, 1 ),
        'days_between' => array( 2, 2 ),
        'age'          => array( 1, 1 ),
        'abs'          => array( 1, 1 ),
        'round'        => array( 1, 2 ),
        'floor'        => array( 1, 1 ),
        'ceil'         => array( 1, 1 ),
        'min'          => array( 1, -1 ),
        'max'          => array( 1, -1 ),
        'sum'          => array( 1, -1 ),
        'avg'          => array( 1, -1 ),
        'count'        => array( 1, 1 ),
        'length'       => array( 1, 1 ),
        'if'           => array( 3, 3 ),
        'score'        => array( 1, 1 ),
        'user'         => array( 1, 1 ),
        'query'        => array( 1, 1 ),
        'geo'          => array( 0, 0 ),
    );

    /**
     * Words that can never be read as a bare string value.
     *
     * @var array
     */
    const RESERVED = array(
        'and', 'or', 'not', 'is', 'contains', 'does', 'contain', 'starts', 'ends', 'with',
        'empty', 'filled', 'before', 'after', 'within', 'days', 'of', 'greater', 'less',
        'than', 'true', 'false', 'null', 'score', 'today', 'at', 'least', 'most',
    );

    /**
     * Binding strength of each operator, lowest first.
     *
     * @var array
     */
    const PRECEDENCE = array(
        'or' => 1, 'and' => 2, 'not' => 3,
        'is' => 4, 'is_not' => 4, 'lt' => 4, 'le' => 4, 'gt' => 4, 'ge' => 4,
        'one_of' => 4, 'not_one_of' => 4, 'empty' => 4, 'not_empty' => 4,
        'contains' => 4, 'not_contains' => 4, 'starts_with' => 4, 'ends_with' => 4,
        'before' => 4, 'after' => 4, 'within' => 4,
        'add' => 5, 'sub' => 5, 'mul' => 6, 'div' => 6, 'mod' => 6, 'neg' => 7,
    );

    /**
     * Tokens of the text being parsed.
     *
     * @var array
     */
    private $tokens = array();

    /**
     * Position in $tokens.
     *
     * @var int
     */
    private $pos = 0;

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    /**
     * Parse expression text into a tree.
     *
     * @param string $text Expression text.
     * @return array AST node.
     * @throws BAF_Expression_Error When the text is not a valid expression.
     */
    public static function parse( $text ) {
        $p         = new self();
        $p->tokens = self::tokenize( (string) $text );
        $p->pos    = 0;
        if ( $p->peek_type() === 'eof' ) {
            throw new BAF_Expression_Error( 'The expression is empty.', 0 );
        }
        $node = $p->parse_or();
        if ( $p->peek_type() !== 'eof' ) {
            $t = $p->peek();
            throw new BAF_Expression_Error( 'Unexpected "' . $t['text'] . '".', $t['offset'] );
        }
        return $node;
    }

    /**
     * Check expression text without throwing.
     *
     * @param string $text Expression text.
     * @return string|null Null when valid, otherwise the error message.
     */
    public static function check( $text ) {
        try {
            self::parse( $text );
            return null;
        } catch ( BAF_Expression_Error $e ) {
            return $e->getMessage();
        }
    }

    /**
     * Parse and rewrite text in canonical spelling.
     *
     * @param string $text Expression text.
     * @return string Canonical text.
     */
    public static function canonical( $text ) {
        return self::to_text( self::parse( $text ) );
    }

    /**
     * Field IDs an expression refers to, in order of first use.
     *
     * @param array $node AST node.
     * @return array Field IDs.
     */
    public static function refs( $node ) {
        $out = array();
        self::collect_refs( $node, $out );
        return array_values( array_unique( $out ) );
    }

    /**
     * Collect refs.
     *
     * @param array $node AST node.
     * @param array $out  Accumulator.
     */
    private static function collect_refs( $node, &$out ) {
        if ( ! is_array( $node ) ) {
            return;
        }
        if ( ( $node['type'] ?? '' ) === 'ref' ) {
            $out[] = $node['field'];
            return;
        }
        foreach ( array( 'args', 'items' ) as $k ) {
            if ( isset( $node[ $k ] ) ) {
                foreach ( $node[ $k ] as $child ) {
                    self::collect_refs( $child, $out );
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Tokenizer
    // ---------------------------------------------------------------------

    /**
     * Split text into tokens.
     *
     * @param string $s Text.
     * @return array Tokens.
     * @throws BAF_Expression_Error On a character the language does not use.
     */
    public static function tokenize( $s ) {
        $tokens = array();
        $n      = strlen( $s );
        $i      = 0;
        while ( $i < $n ) {
            $c = $s[ $i ];
            if ( $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" ) {
                $i++;
                continue;
            }
            $start = $i;
            if ( $c === '#' || $c === '{' ) {
                $close = $c === '{';
                $i++;
                $j = $i;
                while ( $j < $n && ( ctype_alnum( $s[ $j ] ) || $s[ $j ] === '_' ) ) {
                    $j++;
                }
                if ( $j === $i ) {
                    throw new BAF_Expression_Error( 'A field reference needs a field ID after "' . $c . '".', $start );
                }
                $id = substr( $s, $i, $j - $i );
                $i  = $j;
                if ( $close ) {
                    if ( $i >= $n || $s[ $i ] !== '}' ) {
                        throw new BAF_Expression_Error( 'A field reference that starts with "{" must end with "}".', $start );
                    }
                    $i++;
                }
                $tokens[] = array( 'type' => 'ref', 'text' => $id, 'offset' => $start );
                continue;
            }
            if ( ctype_digit( $c ) || ( $c === '.' && $i + 1 < $n && ctype_digit( $s[ $i + 1 ] ) ) ) {
                $j = $i;
                while ( $j < $n && ctype_digit( $s[ $j ] ) ) {
                    $j++;
                }
                if ( $j < $n && $s[ $j ] === '.' && $j + 1 < $n && ctype_digit( $s[ $j + 1 ] ) ) {
                    $j++;
                    while ( $j < $n && ctype_digit( $s[ $j ] ) ) {
                        $j++;
                    }
                }
                $tokens[] = array( 'type' => 'number', 'text' => substr( $s, $i, $j - $i ), 'offset' => $start );
                $i        = $j;
                continue;
            }
            if ( $c === '"' || $c === "'" ) {
                list( $value, $i ) = self::scan_string( $s, $i );
                $tokens[]          = array( 'type' => 'string', 'text' => $value, 'offset' => $start );
                continue;
            }
            if ( ctype_alpha( $c ) || $c === '_' ) {
                $j = $i;
                while ( $j < $n && ( ctype_alnum( $s[ $j ] ) || $s[ $j ] === '_' ) ) {
                    $j++;
                }
                $tokens[] = array( 'type' => 'word', 'text' => substr( $s, $i, $j - $i ), 'offset' => $start );
                $i        = $j;
                continue;
            }
            $two = substr( $s, $i, 2 );
            if ( in_array( $two, array( '==', '!=', '<>', '<=', '>=' ), true ) ) {
                $tokens[] = array( 'type' => 'punct', 'text' => $two, 'offset' => $start );
                $i       += 2;
                continue;
            }
            if ( strpos( '()[],+-*/%=<>', $c ) !== false ) {
                $tokens[] = array( 'type' => 'punct', 'text' => $c, 'offset' => $start );
                $i++;
                continue;
            }
            throw new BAF_Expression_Error( 'The character "' . $c . '" is not part of the expression language.', $start );
        }
        $tokens[] = array( 'type' => 'eof', 'text' => '', 'offset' => $n );
        return $tokens;
    }

    /**
     * Scan a quoted string. Escapes: \n \t \r \\ \" \' \/ \uXXXX.
     *
     * @param string $s Source.
     * @param int    $i Offset of the opening quote.
     * @return array [value, offset after closing quote]
     * @throws BAF_Expression_Error When the string is not closed.
     */
    public static function scan_string( $s, $i ) {
        $quote = $s[ $i ];
        $n     = strlen( $s );
        $start = $i;
        $i++;
        $out = '';
        while ( $i < $n && $s[ $i ] !== $quote ) {
            $c = $s[ $i ];
            if ( $c === '\\' && $i + 1 < $n ) {
                $e = $s[ $i + 1 ];
                $i += 2;
                switch ( $e ) {
                    case 'n':
                        $out .= "\n";
                        break;
                    case 't':
                        $out .= "\t";
                        break;
                    case 'r':
                        $out .= "\r";
                        break;
                    case 'u':
                        $hex = substr( $s, $i, 4 );
                        if ( strlen( $hex ) === 4 && ctype_xdigit( $hex ) ) {
                            $cp = hexdec( $hex );
                            $i += 4;
                            // Surrogate pair.
                            if ( $cp >= 0xD800 && $cp <= 0xDBFF && substr( $s, $i, 2 ) === '\\u' && ctype_xdigit( substr( $s, $i + 2, 4 ) ) ) {
                                $lo = hexdec( substr( $s, $i + 2, 4 ) );
                                if ( $lo >= 0xDC00 && $lo <= 0xDFFF ) {
                                    $cp = 0x10000 + ( ( $cp - 0xD800 ) << 10 ) + ( $lo - 0xDC00 );
                                    $i += 6;
                                }
                            }
                            $out .= self::utf8_chr( $cp );
                        } else {
                            $out .= 'u';
                        }
                        break;
                    default:
                        $out .= $e; // \\ \" \' \/ and anything else stand for themselves.
                }
                continue;
            }
            $out .= $c;
            $i++;
        }
        if ( $i >= $n ) {
            throw new BAF_Expression_Error( 'A quoted value is not closed.', $start );
        }
        return array( $out, $i + 1 );
    }

    /**
     * Encode a code point as UTF-8.
     *
     * @param int $cp Code point.
     * @return string UTF-8 bytes.
     */
    private static function utf8_chr( $cp ) {
        if ( $cp < 0x80 ) {
            return chr( $cp );
        }
        if ( $cp < 0x800 ) {
            return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) );
        }
        if ( $cp < 0x10000 ) {
            return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
        }
        return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
    }

    // ---------------------------------------------------------------------
    // Parser
    // ---------------------------------------------------------------------

    /** @return array */
    private function peek( $ahead = 0 ) {
        $i = min( $this->pos + $ahead, count( $this->tokens ) - 1 );
        return $this->tokens[ $i ];
    }

    /** @return string */
    private function peek_type( $ahead = 0 ) {
        return $this->peek( $ahead )['type'];
    }

    /** @return bool */
    private function is_word( $word, $ahead = 0 ) {
        $t = $this->peek( $ahead );
        return $t['type'] === 'word' && strtolower( $t['text'] ) === $word;
    }

    /** @return bool */
    private function is_punct( $p, $ahead = 0 ) {
        $t = $this->peek( $ahead );
        return $t['type'] === 'punct' && $t['text'] === $p;
    }

    /** @return array */
    private function next() {
        $t = $this->peek();
        if ( $t['type'] !== 'eof' ) {
            $this->pos++;
        }
        return $t;
    }

    /**
     * Consume a word or fail.
     *
     * @param string $word Word.
     * @throws BAF_Expression_Error When absent.
     */
    private function expect_word( $word ) {
        if ( ! $this->is_word( $word ) ) {
            $t = $this->peek();
            throw new BAF_Expression_Error( 'Expected "' . $word . '" but found ' . ( $t['type'] === 'eof' ? 'the end' : '"' . $t['text'] . '"' ) . '.', $t['offset'] );
        }
        $this->next();
    }

    /**
     * Consume punctuation or fail.
     *
     * @param string $p Punctuation.
     * @throws BAF_Expression_Error When absent.
     */
    private function expect_punct( $p ) {
        if ( ! $this->is_punct( $p ) ) {
            $t = $this->peek();
            throw new BAF_Expression_Error( 'Expected "' . $p . '" but found ' . ( $t['type'] === 'eof' ? 'the end' : '"' . $t['text'] . '"' ) . '.', $t['offset'] );
        }
        $this->next();
    }

    /** @return array */
    private static function op( $op, $args ) {
        return array( 'type' => 'op', 'op' => $op, 'args' => $args );
    }

    /** @return array */
    private function parse_or() {
        $args = array( $this->parse_and() );
        while ( $this->is_word( 'or' ) ) {
            $this->next();
            $args[] = $this->parse_and();
        }
        return count( $args ) === 1 ? $args[0] : self::flatten( 'or', $args );
    }

    /** @return array */
    private function parse_and() {
        $args = array( $this->parse_not() );
        while ( $this->is_word( 'and' ) ) {
            $this->next();
            $args[] = $this->parse_not();
        }
        return count( $args ) === 1 ? $args[0] : self::flatten( 'and', $args );
    }

    /**
     * Merge nested and/or of the same kind so the tree is canonical.
     *
     * @param string $op   and|or.
     * @param array  $args Operands.
     * @return array Node.
     */
    private static function flatten( $op, $args ) {
        $flat = array();
        foreach ( $args as $a ) {
            if ( ( $a['type'] ?? '' ) === 'op' && $a['op'] === $op ) {
                foreach ( $a['args'] as $b ) {
                    $flat[] = $b;
                }
            } else {
                $flat[] = $a;
            }
        }
        return self::op( $op, $flat );
    }

    /** @return array */
    private function parse_not() {
        if ( $this->is_word( 'not' ) ) {
            $this->next();
            return self::op( 'not', array( $this->parse_not() ) );
        }
        return $this->parse_comparison();
    }

    /** @return array */
    private function parse_comparison() {
        $left = $this->parse_additive();
        $t    = $this->peek();

        if ( $t['type'] === 'punct' ) {
            $map = array( '=' => 'is', '==' => 'is', '!=' => 'is_not', '<>' => 'is_not', '<' => 'lt', '<=' => 'le', '>' => 'gt', '>=' => 'ge' );
            if ( isset( $map[ $t['text'] ] ) ) {
                $this->next();
                return self::op( $map[ $t['text'] ], array( $left, $this->parse_additive() ) );
            }
            return $left;
        }
        if ( $t['type'] !== 'word' ) {
            return $left;
        }
        $w = strtolower( $t['text'] );

        if ( $w === 'is' ) {
            $this->next();
            $neg = false;
            if ( $this->is_word( 'not' ) ) {
                $this->next();
                $neg = true;
            }
            if ( $this->is_word( 'one' ) && $this->is_word( 'of', 1 ) ) {
                $this->next();
                $this->next();
                return self::op( $neg ? 'not_one_of' : 'one_of', array( $left, $this->parse_list() ) );
            }
            if ( $this->is_word( 'empty' ) ) {
                $this->next();
                return self::op( $neg ? 'not_empty' : 'empty', array( $left ) );
            }
            if ( $this->is_word( 'filled' ) ) {
                $this->next();
                if ( $this->is_word( 'in' ) ) {
                    $this->next();
                }
                return self::op( $neg ? 'empty' : 'not_empty', array( $left ) );
            }
            if ( ! $neg ) {
                foreach ( array( 'greater' => 'gt', 'less' => 'lt' ) as $word => $op ) {
                    if ( $this->is_word( $word ) ) {
                        $this->next();
                        if ( $this->is_word( 'than' ) ) {
                            $this->next();
                        }
                        return self::op( $op, array( $left, $this->parse_additive() ) );
                    }
                }
                if ( $this->is_word( 'at' ) && ( $this->is_word( 'least', 1 ) || $this->is_word( 'most', 1 ) ) ) {
                    $this->next();
                    $op = $this->is_word( 'least' ) ? 'ge' : 'le';
                    $this->next();
                    return self::op( $op, array( $left, $this->parse_additive() ) );
                }
                if ( $this->is_word( 'before' ) || $this->is_word( 'after' ) ) {
                    $op = strtolower( $this->next()['text'] );
                    return self::op( $op, array( $left, $this->parse_additive() ) );
                }
                if ( $this->is_word( 'within' ) ) {
                    $this->next();
                    return $this->parse_within( $left );
                }
            }
            return self::op( $neg ? 'is_not' : 'is', array( $left, $this->parse_additive() ) );
        }

        switch ( $w ) {
            case 'empty':
                $this->next();
                return self::op( 'empty', array( $left ) );
            case 'filled':
                $this->next();
                return self::op( 'not_empty', array( $left ) );
            case 'contains':
                $this->next();
                return self::op( 'contains', array( $left, $this->parse_additive() ) );
            case 'does':
                $this->next();
                $this->expect_word( 'not' );
                $this->expect_word( 'contain' );
                return self::op( 'not_contains', array( $left, $this->parse_additive() ) );
            case 'starts':
            case 'ends':
                $this->next();
                if ( $this->is_word( 'with' ) ) {
                    $this->next();
                }
                return self::op( $w === 'starts' ? 'starts_with' : 'ends_with', array( $left, $this->parse_additive() ) );
            case 'greater':
            case 'less':
                $this->next();
                if ( $this->is_word( 'than' ) ) {
                    $this->next();
                }
                return self::op( $w === 'greater' ? 'gt' : 'lt', array( $left, $this->parse_additive() ) );
            case 'before':
            case 'after':
                $this->next();
                return self::op( $w, array( $left, $this->parse_additive() ) );
            case 'within':
                $this->next();
                return $this->parse_within( $left );
        }
        return $left;
    }

    /**
     * The rest of "within N days [of X]".
     *
     * @param array $left Left operand.
     * @return array Node.
     */
    private function parse_within( $left ) {
        $n = $this->parse_additive();
        $this->expect_word( 'days' );
        $args = array( $left, $n );
        if ( $this->is_word( 'of' ) ) {
            $this->next();
            $args[] = $this->parse_additive();
        }
        return self::op( 'within', $args );
    }

    /** @return array */
    private function parse_list() {
        $start = $this->peek();
        $this->expect_punct( '[' );
        $items = array();
        if ( ! $this->is_punct( ']' ) ) {
            $items[] = $this->parse_additive();
            while ( $this->is_punct( ',' ) ) {
                $this->next();
                $items[] = $this->parse_additive();
            }
        }
        if ( ! $this->is_punct( ']' ) ) {
            throw new BAF_Expression_Error( 'A list must end with "]".', $start['offset'] );
        }
        $this->next();
        return array( 'type' => 'list', 'items' => $items );
    }

    /** @return array */
    private function parse_additive() {
        $left = $this->parse_multiplicative();
        while ( $this->is_punct( '+' ) || $this->is_punct( '-' ) ) {
            $op   = $this->next()['text'] === '+' ? 'add' : 'sub';
            $left = self::op( $op, array( $left, $this->parse_multiplicative() ) );
        }
        return $left;
    }

    /** @return array */
    private function parse_multiplicative() {
        $left = $this->parse_unary();
        while ( $this->is_punct( '*' ) || $this->is_punct( '/' ) || $this->is_punct( '%' ) ) {
            $p    = $this->next()['text'];
            $op   = $p === '*' ? 'mul' : ( $p === '/' ? 'div' : 'mod' );
            $left = self::op( $op, array( $left, $this->parse_unary() ) );
        }
        return $left;
    }

    /** @return array */
    private function parse_unary() {
        if ( $this->is_punct( '-' ) ) {
            $this->next();
            $operand = $this->parse_unary();
            if ( $operand['type'] === 'literal' && ( is_int( $operand['value'] ) || is_float( $operand['value'] ) ) ) {
                return array( 'type' => 'literal', 'value' => -$operand['value'] );
            }
            return self::op( 'neg', array( $operand ) );
        }
        return $this->parse_primary();
    }

    /**
     * Number text to a PHP number (int when it has no fraction).
     *
     * @param string $text Digits.
     * @return int|float Number.
     */
    private static function to_number( $text ) {
        if ( strpos( $text, '.' ) === false && strlen( ltrim( $text, '0' ) ) < 16 ) {
            return (int) $text;
        }
        return (float) $text;
    }

    /** @return array */
    private function parse_primary() {
        $t = $this->peek();
        switch ( $t['type'] ) {
            case 'number':
                $this->next();
                return array( 'type' => 'literal', 'value' => self::to_number( $t['text'] ) );
            case 'string':
                $this->next();
                return array( 'type' => 'literal', 'value' => $t['text'] );
            case 'ref':
                $this->next();
                return array( 'type' => 'ref', 'field' => $t['text'] );
            case 'punct':
                if ( $t['text'] === '(' ) {
                    $this->next();
                    $inner = $this->parse_or();
                    $this->expect_punct( ')' );
                    return $inner;
                }
                if ( $t['text'] === '[' ) {
                    return $this->parse_list();
                }
                throw new BAF_Expression_Error( 'Unexpected "' . $t['text'] . '".', $t['offset'] );
            case 'word':
                $w = strtolower( $t['text'] );
                if ( $this->is_punct( '(', 1 ) ) {
                    if ( ! isset( self::FUNCTIONS[ $w ] ) ) {
                        throw new BAF_Expression_Error( 'There is no function called "' . $t['text'] . '".', $t['offset'] );
                    }
                    $this->next();
                    $this->next();
                    $args = array();
                    if ( ! $this->is_punct( ')' ) ) {
                        $args[] = $this->parse_or();
                        while ( $this->is_punct( ',' ) ) {
                            $this->next();
                            $args[] = $this->parse_or();
                        }
                    }
                    $this->expect_punct( ')' );
                    list( $min, $max ) = self::FUNCTIONS[ $w ];
                    if ( count( $args ) < $min || ( $max >= 0 && count( $args ) > $max ) ) {
                        throw new BAF_Expression_Error( 'The function "' . $w . '" was given ' . count( $args ) . ' value(s).', $t['offset'] );
                    }
                    return array( 'type' => 'call', 'name' => $w, 'args' => $args );
                }
                if ( $w === 'true' || $w === 'false' ) {
                    $this->next();
                    return array( 'type' => 'literal', 'value' => $w === 'true' );
                }
                if ( $w === 'null' ) {
                    $this->next();
                    return array( 'type' => 'literal', 'value' => null );
                }
                if ( $w === 'today' ) {
                    $this->next();
                    return array( 'type' => 'call', 'name' => 'today', 'args' => array() );
                }
                if ( $w === 'score' ) {
                    $this->next();
                    return array( 'type' => 'var', 'name' => 'score' );
                }
                if ( in_array( $w, self::RESERVED, true ) ) {
                    throw new BAF_Expression_Error( 'Expected a value but found "' . $t['text'] . '".', $t['offset'] );
                }
                // A bare word is a text value (BFL written before 2026 allowed it).
                $this->next();
                return array( 'type' => 'literal', 'value' => $t['text'] );
            case 'eof':
                throw new BAF_Expression_Error( 'The expression ends too early.', $t['offset'] );
        }
        throw new BAF_Expression_Error( 'Unexpected "' . $t['text'] . '".', $t['offset'] );
    }

    // ---------------------------------------------------------------------
    // Canonical text
    // ---------------------------------------------------------------------

    /**
     * Encode a string the way the model and BFL both write it: JSON, with
     * non-ASCII characters and slashes kept as they are.
     *
     * @param string $s String.
     * @return string Quoted string.
     */
    public static function quote( $s ) {
        return json_encode( (string) $s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_INVALID_UTF8_SUBSTITUTE );
    }

    /**
     * Write a literal value.
     *
     * @param mixed $v Value.
     * @return string Text.
     */
    public static function literal_text( $v ) {
        if ( $v === null ) {
            return 'null';
        }
        if ( is_bool( $v ) ) {
            return $v ? 'true' : 'false';
        }
        if ( is_int( $v ) || is_float( $v ) ) {
            return json_encode( $v );
        }
        return self::quote( $v );
    }

    /**
     * Precedence of a node (primaries bind tightest).
     *
     * @param array $node Node.
     * @return int Precedence.
     */
    private static function prec( $node ) {
        if ( ( $node['type'] ?? '' ) === 'op' ) {
            return self::PRECEDENCE[ $node['op'] ];
        }
        if ( ( $node['type'] ?? '' ) === 'literal' && ( is_int( $node['value'] ) || is_float( $node['value'] ) ) && $node['value'] < 0 ) {
            return 7;
        }
        return 8;
    }

    /**
     * Write a child, in parentheses when it binds more loosely than $min.
     *
     * @param array $node Child.
     * @param int   $min  Minimum precedence that needs no parentheses.
     * @return string Text.
     */
    private static function child( $node, $min ) {
        $text = self::to_text( $node );
        return self::prec( $node ) < $min ? '(' . $text . ')' : $text;
    }

    /**
     * Write a tree as canonical text.
     *
     * @param array $node AST node.
     * @return string Text.
     */
    public static function to_text( $node ) {
        switch ( $node['type'] ) {
            case 'literal':
                return self::literal_text( $node['value'] );
            case 'ref':
                return '#' . $node['field'];
            case 'var':
                return $node['name'];
            case 'list':
                return '[' . implode( ', ', array_map( function ( $n ) {
                    return self::child( $n, 5 );
                }, $node['items'] ) ) . ']';
            case 'call':
                return $node['name'] . '(' . implode( ', ', array_map( array( __CLASS__, 'to_text' ), $node['args'] ) ) . ')';
        }
        $a  = $node['args'];
        $op = $node['op'];
        switch ( $op ) {
            case 'or':
            case 'and':
                $p = self::PRECEDENCE[ $op ];
                return implode( ' ' . $op . ' ', array_map( function ( $n ) use ( $p ) {
                    return self::child( $n, $p + 1 );
                }, $a ) );
            case 'not':
                return 'not ' . self::child( $a[0], 3 );
            case 'neg':
                return '-' . self::child( $a[0], 7 );
            case 'empty':
                return self::child( $a[0], 5 ) . ' is empty';
            case 'not_empty':
                return self::child( $a[0], 5 ) . ' is not empty';
            case 'one_of':
            case 'not_one_of':
                return self::child( $a[0], 5 ) . ( $op === 'one_of' ? ' is one of ' : ' is not one of ' ) . self::to_text( $a[1] );
            case 'within':
                $text = self::child( $a[0], 5 ) . ' is within ' . self::child( $a[1], 5 ) . ' days';
                if ( isset( $a[2] ) ) {
                    $text .= ' of ' . self::child( $a[2], 5 );
                }
                return $text;
        }
        $infix = array(
            'is' => 'is', 'is_not' => 'is not', 'lt' => '<', 'le' => '<=', 'gt' => '>', 'ge' => '>=',
            'contains' => 'contains', 'not_contains' => 'does not contain',
            'starts_with' => 'starts with', 'ends_with' => 'ends with',
            'before' => 'is before', 'after' => 'is after',
            'add' => '+', 'sub' => '-', 'mul' => '*', 'div' => '/', 'mod' => '%',
        );
        $p = self::PRECEDENCE[ $op ];
        if ( $p === 4 ) {
            return self::child( $a[0], 5 ) . ' ' . $infix[ $op ] . ' ' . self::child( $a[1], 5 );
        }
        // Arithmetic is left-associative: the right operand needs parentheses at equal precedence.
        return self::child( $a[0], $p ) . ' ' . $infix[ $op ] . ' ' . self::child( $a[1], $p + 1 );
    }

    // ---------------------------------------------------------------------
    // Sentences
    // ---------------------------------------------------------------------

    /**
     * Render a tree as an English sentence fragment, for BFL comments, the
     * builder and QUILL's windows ("Type is Business or Type is Charity").
     *
     * @param array $node    AST node.
     * @param array $labels  Field ID => label.
     * @param array $choices Field ID => array( value => label ).
     * @return string Sentence.
     */
    public static function to_sentence( $node, $labels = array(), $choices = array() ) {
        return self::sentence( $node, $labels, $choices, null );
    }

    /**
     * Sentence worker.
     *
     * @param array       $node    Node.
     * @param array       $labels  Labels.
     * @param array       $choices Choice labels.
     * @param string|null $context Field whose choice labels name literal values.
     * @return string Text.
     */
    private static function sentence( $node, $labels, $choices, $context ) {
        $s = function ( $n, $ctx = null ) use ( $labels, $choices ) {
            return self::sentence( $n, $labels, $choices, $ctx );
        };
        // A group inside another group is set off with parentheses.
        $g = function ( $n, $ctx = null ) use ( $s ) {
            $text = $s( $n, $ctx );
            return ( ( $n['type'] ?? '' ) === 'op' && in_array( $n['op'], array( 'and', 'or' ), true ) ) ? '(' . $text . ')' : $text;
        };
        switch ( $node['type'] ) {
            case 'literal':
                $v = $node['value'];
                if ( $v === null ) {
                    return 'nothing';
                }
                if ( is_bool( $v ) ) {
                    return $v ? 'yes' : 'no';
                }
                if ( $context !== null && isset( $choices[ $context ] ) && is_string( $v ) && isset( $choices[ $context ][ $v ] ) ) {
                    return (string) $choices[ $context ][ $v ];
                }
                return is_string( $v ) ? $v : json_encode( $v );
            case 'ref':
                return isset( $labels[ $node['field'] ] ) && $labels[ $node['field'] ] !== '' ? (string) $labels[ $node['field'] ] : $node['field'];
            case 'var':
                return 'the score';
            case 'list':
                $items = array_map( function ( $n ) use ( $s, $context ) {
                    return $s( $n, $context );
                }, $node['items'] );
                if ( count( $items ) <= 1 ) {
                    return implode( '', $items );
                }
                $last = array_pop( $items );
                return implode( ', ', $items ) . ' or ' . $last;
            case 'call':
                $args = $node['args'];
                switch ( $node['name'] ) {
                    case 'today':
                        return 'today';
                    case 'geo':
                        return 'the visitor\'s location';
                    case 'score':
                        return 'the score for ' . $s( $args[0] );
                    case 'count':
                        return 'the number of choices in ' . $s( $args[0] );
                    case 'length':
                        return 'the length of ' . $s( $args[0] );
                    case 'user':
                        return 'the signed-in user\'s ' . str_replace( '_', ' ', (string) ( $args[0]['value'] ?? '' ) );
                    case 'query':
                        return 'the link\'s ' . (string) ( $args[0]['value'] ?? '' ) . ' value';
                    case 'if':
                        return $s( $args[1] ) . ' if ' . $s( $args[0] ) . ', otherwise ' . $s( $args[2] );
                    case 'days_between':
                        return 'the days from ' . $s( $args[0] ) . ' to ' . $s( $args[1] );
                }
                $names = array( 'sum' => 'the total of', 'avg' => 'the average of', 'min' => 'the smallest of', 'max' => 'the largest of', 'round' => 'rounded', 'floor' => 'rounded down', 'ceil' => 'rounded up', 'abs' => 'the size of', 'age' => 'the age in years from', 'date' => 'the date' );
                $parts = array_map( $s, $args );
                if ( $node['name'] === 'round' && count( $parts ) === 2 ) {
                    return $parts[0] . ' rounded to ' . $parts[1] . ' places';
                }
                if ( in_array( $node['name'], array( 'round', 'floor', 'ceil' ), true ) ) {
                    return $parts[0] . ' ' . $names[ $node['name'] ];
                }
                $last = array_pop( $parts );
                return $names[ $node['name'] ] . ' ' . ( $parts ? implode( ', ', $parts ) . ' and ' : '' ) . $last;
        }
        $a   = $node['args'];
        $op  = $node['op'];
        $ctx = ( $a[0]['type'] ?? '' ) === 'ref' ? $a[0]['field'] : null;
        switch ( $op ) {
            case 'or':
            case 'and':
                $parts = array_map( $g, $a );
                $last  = array_pop( $parts );
                return implode( ', ', $parts ) . ( count( $parts ) > 1 ? ',' : '' ) . ' ' . $op . ' ' . $last;
            case 'not':
                $inner    = $a[0];
                $inverses = array( 'is' => 'is_not', 'is_not' => 'is', 'empty' => 'not_empty', 'not_empty' => 'empty', 'one_of' => 'not_one_of', 'not_one_of' => 'one_of', 'contains' => 'not_contains', 'not_contains' => 'contains' );
                if ( ( $inner['type'] ?? '' ) === 'op' && isset( $inverses[ $inner['op'] ] ) ) {
                    return $s( array( 'type' => 'op', 'op' => $inverses[ $inner['op'] ], 'args' => $inner['args'] ) );
                }
                if ( ( $inner['type'] ?? '' ) === 'op' ) {
                    return 'not (' . $s( $inner ) . ')';
                }
                return 'not ' . $s( $inner );
            case 'neg':
                return 'minus ' . $s( $a[0] );
            case 'empty':
                return $s( $a[0] ) . ' is empty';
            case 'not_empty':
                return $s( $a[0] ) . ' is filled in';
            case 'one_of':
                return $s( $a[0] ) . ' is one of ' . $s( $a[1], $ctx );
            case 'not_one_of':
                return $s( $a[0] ) . ' is not one of ' . $s( $a[1], $ctx );
            case 'within':
                return $s( $a[0] ) . ' is within ' . $s( $a[1] ) . ' days of ' . ( isset( $a[2] ) ? $s( $a[2] ) : 'today' );
        }
        $words = array(
            'is' => 'is', 'is_not' => 'is not', 'lt' => 'is less than', 'le' => 'is at most',
            'gt' => 'is greater than', 'ge' => 'is at least', 'contains' => 'contains',
            'not_contains' => 'does not contain', 'starts_with' => 'starts with', 'ends_with' => 'ends with',
            'before' => 'is before', 'after' => 'is after',
            'add' => 'plus', 'sub' => 'minus', 'mul' => 'times', 'div' => 'divided by', 'mod' => 'remainder after dividing by',
        );
        $left  = $s( $a[0] );
        $right = $s( $a[1], $ctx );
        if ( self::PRECEDENCE[ $op ] >= 5 ) {
            if ( ( $a[0]['type'] ?? '' ) === 'op' && self::PRECEDENCE[ $a[0]['op'] ] < self::PRECEDENCE[ $op ] ) {
                $left = '(' . $left . ')';
            }
            if ( ( $a[1]['type'] ?? '' ) === 'op' && self::PRECEDENCE[ $a[1]['op'] ] <= self::PRECEDENCE[ $op ] ) {
                $right = '(' . $right . ')';
            }
        }
        return $left . ' ' . $words[ $op ] . ' ' . $right;
    }

    // ---------------------------------------------------------------------
    // Evaluation
    // ---------------------------------------------------------------------

    /**
     * Evaluate a tree.
     *
     * Context keys, all optional:
     *  - values: field ID => value (string, number, bool, list or null)
     *  - today:  "YYYY-MM-DD" (defaults to the server's date)
     *  - user:   key => value for user()
     *  - query:  key => value for query()
     *  - geo:    value for geo()
     *  - scores: field ID => points, for score(#field)
     *  - score:  total points, for the word score
     *
     * @param array|string $node    AST node or expression text.
     * @param array        $context Context.
     * @return mixed Result.
     */
    public static function evaluate( $node, $context = array() ) {
        if ( is_string( $node ) ) {
            $node = self::parse( $node );
        }
        switch ( $node['type'] ) {
            case 'literal':
                return $node['value'];
            case 'ref':
                return $context['values'][ $node['field'] ] ?? null;
            case 'var':
                return $context['score'] ?? 0;
            case 'list':
                return array_map( function ( $n ) use ( $context ) {
                    return self::evaluate( $n, $context );
                }, $node['items'] );
            case 'call':
                return self::call( $node['name'], $node['args'], $context );
        }
        $a  = $node['args'];
        $op = $node['op'];
        $ev = function ( $i ) use ( $a, $context ) {
            return self::evaluate( $a[ $i ], $context );
        };
        switch ( $op ) {
            case 'or':
                foreach ( $a as $i => $unused ) {
                    if ( self::truthy( $ev( $i ) ) ) {
                        return true;
                    }
                }
                return false;
            case 'and':
                foreach ( $a as $i => $unused ) {
                    if ( ! self::truthy( $ev( $i ) ) ) {
                        return false;
                    }
                }
                return true;
            case 'not':
                return ! self::truthy( $ev( 0 ) );
            case 'neg':
                return self::normalize_number( -self::num( $ev( 0 ) ) );
            case 'empty':
                return self::is_empty( $ev( 0 ) );
            case 'not_empty':
                return ! self::is_empty( $ev( 0 ) );
            case 'is':
                return self::equals( $ev( 0 ), $ev( 1 ) );
            case 'is_not':
                return ! self::equals( $ev( 0 ), $ev( 1 ) );
            case 'lt':
            case 'le':
            case 'gt':
            case 'ge':
                $c = self::compare( $ev( 0 ), $ev( 1 ) );
                if ( $c === null ) {
                    return false;
                }
                return ( $op === 'lt' && $c < 0 ) || ( $op === 'le' && $c <= 0 ) || ( $op === 'gt' && $c > 0 ) || ( $op === 'ge' && $c >= 0 );
            case 'one_of':
            case 'not_one_of':
                $left = $ev( 0 );
                $list = $ev( 1 );
                $list = is_array( $list ) ? $list : array( $list );
                $hit  = false;
                foreach ( is_array( $left ) ? $left : array( $left ) as $l ) {
                    foreach ( $list as $r ) {
                        if ( self::scalar_equals( $l, $r ) ) {
                            $hit = true;
                            break 2;
                        }
                    }
                }
                return $op === 'one_of' ? $hit : ! $hit;
            case 'contains':
            case 'not_contains':
                $hit = self::contains( $ev( 0 ), $ev( 1 ) );
                return $op === 'contains' ? $hit : ! $hit;
            case 'starts_with':
            case 'ends_with':
                $l = strtolower( self::str( $ev( 0 ) ) );
                $r = strtolower( self::str( $ev( 1 ) ) );
                if ( $r === '' ) {
                    return true;
                }
                return $op === 'starts_with' ? strpos( $l, $r ) === 0 : substr( $l, -strlen( $r ) ) === $r;
            case 'before':
            case 'after':
                $l = self::day_number( $ev( 0 ) );
                $r = self::day_number( $ev( 1 ) );
                if ( $l === null || $r === null ) {
                    return false;
                }
                return $op === 'before' ? $l < $r : $l > $r;
            case 'within':
                $l = self::day_number( $ev( 0 ) );
                $n = self::num( $ev( 1 ) );
                $r = self::day_number( isset( $a[2] ) ? $ev( 2 ) : self::today( $context ) );
                if ( $l === null || $r === null ) {
                    return false;
                }
                return abs( $l - $r ) <= $n;
            case 'add':
            case 'sub':
                $l  = $ev( 0 );
                $r  = $ev( 1 );
                $ld = self::day_number( $l );
                $rd = self::day_number( $r );
                if ( $ld !== null && $rd === null ) {
                    $days = (int) round( self::num( $r ) );
                    return self::day_to_date( $op === 'add' ? $ld + $days : $ld - $days );
                }
                if ( $op === 'sub' && $ld !== null && $rd !== null ) {
                    return $ld - $rd;
                }
                return self::normalize_number( $op === 'add' ? self::num( $l ) + self::num( $r ) : self::num( $l ) - self::num( $r ) );
            case 'mul':
                return self::normalize_number( self::num( $ev( 0 ) ) * self::num( $ev( 1 ) ) );
            case 'div':
            case 'mod':
                $d = self::num( $ev( 1 ) );
                if ( $d == 0 ) {
                    return null;
                }
                $l = self::num( $ev( 0 ) );
                return self::normalize_number( $op === 'div' ? $l / $d : fmod( $l, $d ) );
        }
        return null;
    }

    /**
     * Evaluate a function call.
     *
     * @param string $name    Function.
     * @param array  $args    Argument nodes.
     * @param array  $context Context.
     * @return mixed Result.
     */
    private static function call( $name, $args, $context ) {
        $vals = function () use ( $args, $context ) {
            $out = array();
            foreach ( $args as $n ) {
                $v = self::evaluate( $n, $context );
                foreach ( is_array( $v ) ? $v : array( $v ) as $x ) {
                    $out[] = $x;
                }
            }
            return $out;
        };
        $arg = function ( $i ) use ( $args, $context ) {
            return self::evaluate( $args[ $i ], $context );
        };
        switch ( $name ) {
            case 'today':
                return self::today( $context );
            case 'date':
                $d = self::day_number( $arg( 0 ) );
                return $d === null ? null : self::day_to_date( $d );
            case 'days_between':
                $x = self::day_number( $arg( 0 ) );
                $y = self::day_number( $arg( 1 ) );
                return ( $x === null || $y === null ) ? null : $y - $x;
            case 'age':
                $d = self::parse_date( $arg( 0 ) );
                $t = self::parse_date( self::today( $context ) );
                if ( ! $d || ! $t ) {
                    return null;
                }
                $years = $t[0] - $d[0];
                if ( $t[1] < $d[1] || ( $t[1] === $d[1] && $t[2] < $d[2] ) ) {
                    $years--;
                }
                return $years;
            case 'abs':
                return self::normalize_number( abs( self::num( $arg( 0 ) ) ) );
            case 'round':
                $places = isset( $args[1] ) ? (int) self::num( $arg( 1 ) ) : 0;
                return self::normalize_number( round( self::num( $arg( 0 ) ), $places, PHP_ROUND_HALF_UP ) );
            case 'floor':
                return self::normalize_number( floor( self::num( $arg( 0 ) ) ) );
            case 'ceil':
                return self::normalize_number( ceil( self::num( $arg( 0 ) ) ) );
            case 'min':
            case 'max':
            case 'sum':
            case 'avg':
                $nums = array();
                foreach ( $vals() as $v ) {
                    if ( ! self::is_empty( $v ) ) {
                        $nums[] = self::num( $v );
                    }
                }
                if ( $name === 'sum' ) {
                    return self::normalize_number( array_sum( $nums ) );
                }
                if ( ! $nums ) {
                    return null;
                }
                if ( $name === 'avg' ) {
                    return self::normalize_number( array_sum( $nums ) / count( $nums ) );
                }
                return self::normalize_number( $name === 'min' ? min( $nums ) : max( $nums ) );
            case 'count':
                $v = $arg( 0 );
                if ( is_array( $v ) ) {
                    return count( array_filter( $v, function ( $x ) {
                        return ! self::is_empty( $x );
                    } ) );
                }
                return self::is_empty( $v ) ? 0 : 1;
            case 'length':
                return (int) preg_match_all( '/./us', self::str( $arg( 0 ) ) );
            case 'if':
                return self::truthy( $arg( 0 ) ) ? $arg( 1 ) : $arg( 2 );
            case 'score':
                $ref = $args[0];
                $id  = $ref['type'] === 'ref' ? $ref['field'] : self::str( $arg( 0 ) );
                return $context['scores'][ $id ] ?? 0;
            case 'user':
                return $context['user'][ self::str( $arg( 0 ) ) ] ?? null;
            case 'query':
                return $context['query'][ self::str( $arg( 0 ) ) ] ?? null;
            case 'geo':
                return $context['geo'] ?? null;
        }
        return null;
    }

    /**
     * Truthiness: false, null, the number 0, "" and the empty list are false.
     *
     * @param mixed $v Value.
     * @return bool Truth.
     */
    public static function truthy( $v ) {
        if ( $v === null || $v === false || $v === '' || $v === array() ) {
            return false;
        }
        if ( is_int( $v ) || is_float( $v ) ) {
            return $v != 0;
        }
        return true;
    }

    /**
     * Emptiness: null, "", whitespace and the empty list (or a list of empties).
     *
     * @param mixed $v Value.
     * @return bool Empty.
     */
    public static function is_empty( $v ) {
        if ( $v === null || $v === false ) {
            return $v === null;
        }
        if ( is_array( $v ) ) {
            foreach ( $v as $x ) {
                if ( ! self::is_empty( $x ) ) {
                    return false;
                }
            }
            return true;
        }
        if ( is_string( $v ) ) {
            return trim( $v ) === '';
        }
        return false;
    }

    /**
     * Text form of a value.
     *
     * @param mixed $v Value.
     * @return string Text.
     */
    private static function str( $v ) {
        if ( $v === null ) {
            return '';
        }
        if ( is_bool( $v ) ) {
            return $v ? 'true' : 'false';
        }
        if ( is_array( $v ) ) {
            return implode( ', ', array_map( array( __CLASS__, 'str' ), $v ) );
        }
        if ( is_float( $v ) ) {
            return json_encode( $v );
        }
        return (string) $v;
    }

    /**
     * Whether a value is a number or numeric text.
     *
     * @param mixed $v Value.
     * @return bool Numeric.
     */
    private static function is_numeric_value( $v ) {
        if ( is_int( $v ) || is_float( $v ) ) {
            return true;
        }
        return is_string( $v ) && preg_match( '/^\s*-?(\d+(\.\d*)?|\.\d+)\s*$/', $v ) === 1;
    }

    /**
     * Number form of a value: numbers and numeric text are themselves, true
     * is 1, a list is the total of its members, anything else is 0.
     *
     * @param mixed $v Value.
     * @return int|float Number.
     */
    private static function num( $v ) {
        if ( is_int( $v ) || is_float( $v ) ) {
            return $v;
        }
        if ( $v === true ) {
            return 1;
        }
        if ( is_array( $v ) ) {
            $t = 0;
            foreach ( $v as $x ) {
                $t += self::num( $x );
            }
            return $t;
        }
        if ( self::is_numeric_value( $v ) ) {
            $v = trim( $v );
            return ( strpos( $v, '.' ) === false ) ? (int) $v : (float) $v;
        }
        return 0;
    }

    /**
     * Integral floats become ints, so results are the same in every reader.
     *
     * @param int|float $n Number.
     * @return int|float Number.
     */
    private static function normalize_number( $n ) {
        if ( is_float( $n ) && is_finite( $n ) && floor( $n ) == $n && abs( $n ) < 1e15 ) {
            return (int) $n;
        }
        return $n;
    }

    /**
     * Equality of two scalars: numerically when both are numeric, otherwise
     * as exact text (true and false compare as "true" and "false").
     *
     * @param mixed $a Left.
     * @param mixed $b Right.
     * @return bool Equal.
     */
    private static function scalar_equals( $a, $b ) {
        if ( $a === null ) {
            $a = '';
        }
        if ( $b === null ) {
            $b = '';
        }
        if ( self::is_numeric_value( $a ) && self::is_numeric_value( $b ) ) {
            return self::num( $a ) == self::num( $b );
        }
        return self::str( $a ) === self::str( $b );
    }

    /**
     * "is": a list on the left (several choices) is the right value when
     * the list includes it; a list on both sides must match as sets.
     *
     * @param mixed $a Left.
     * @param mixed $b Right.
     * @return bool Equal.
     */
    private static function equals( $a, $b ) {
        if ( is_array( $a ) && is_array( $b ) ) {
            if ( count( $a ) !== count( $b ) ) {
                return false;
            }
            foreach ( $b as $x ) {
                if ( ! self::contains( $a, $x ) ) {
                    return false;
                }
            }
            return true;
        }
        if ( is_array( $a ) ) {
            return self::contains( $a, $b );
        }
        return self::scalar_equals( $a, $b );
    }

    /**
     * "contains": membership for a list, case-insensitive text search otherwise.
     *
     * @param mixed $a Haystack.
     * @param mixed $b Needle.
     * @return bool Found.
     */
    private static function contains( $a, $b ) {
        if ( is_array( $a ) ) {
            foreach ( $a as $x ) {
                if ( self::scalar_equals( $x, $b ) ) {
                    return true;
                }
            }
            return false;
        }
        $needle = strtolower( self::str( $b ) );
        return $needle === '' || strpos( strtolower( self::str( $a ) ), $needle ) !== false;
    }

    /**
     * Order two values: numbers numerically, dates by day, text by bytes.
     * Null when either side is empty.
     *
     * @param mixed $a Left.
     * @param mixed $b Right.
     * @return int|null Sign.
     */
    private static function compare( $a, $b ) {
        if ( self::is_empty( $a ) || self::is_empty( $b ) ) {
            return null;
        }
        if ( self::is_numeric_value( $a ) && self::is_numeric_value( $b ) ) {
            return self::num( $a ) <=> self::num( $b );
        }
        $da = self::day_number( $a );
        $db = self::day_number( $b );
        if ( $da !== null && $db !== null ) {
            return $da <=> $db;
        }
        if ( is_array( $a ) || is_array( $b ) ) {
            return self::num( $a ) <=> self::num( $b );
        }
        $c = strcmp( self::str( $a ), self::str( $b ) );
        return $c < 0 ? -1 : ( $c > 0 ? 1 : 0 );
    }

    /**
     * Today's date for evaluation.
     *
     * @param array $context Context.
     * @return string ISO date.
     */
    private static function today( $context ) {
        return isset( $context['today'] ) ? (string) $context['today'] : gmdate( 'Y-m-d' );
    }

    /**
     * Read "YYYY-MM-DD" (anything after the tenth character, such as a time,
     * is ignored) into [year, month, day], or null.
     *
     * @param mixed $v Value.
     * @return array|null Parts.
     */
    private static function parse_date( $v ) {
        if ( ! is_string( $v ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:$|[T ])/', $v, $m ) ) {
            return null;
        }
        $y  = (int) $m[1];
        $mo = (int) $m[2];
        $d  = (int) $m[3];
        if ( ! checkdate( $mo, $d, $y ) ) {
            return null;
        }
        return array( $y, $mo, $d );
    }

    /**
     * A date as a count of days (days from 1970-01-01), or null.
     *
     * @param mixed $v Value.
     * @return int|null Day number.
     */
    private static function day_number( $v ) {
        $p = self::parse_date( $v );
        if ( ! $p ) {
            return null;
        }
        // Days from civil (Howard Hinnant's algorithm), independent of time zones.
        list( $y, $m, $d ) = $p;
        $y  -= $m <= 2 ? 1 : 0;
        $era = intdiv( $y >= 0 ? $y : $y - 399, 400 );
        $yoe = $y - $era * 400;
        $doy = intdiv( 153 * ( $m + ( $m > 2 ? -3 : 9 ) ) + 2, 5 ) + $d - 1;
        $doe = $yoe * 365 + intdiv( $yoe, 4 ) - intdiv( $yoe, 100 ) + $doy;
        return $era * 146097 + $doe - 719468;
    }

    /**
     * A day number back to "YYYY-MM-DD".
     *
     * @param int $z Day number.
     * @return string ISO date.
     */
    private static function day_to_date( $z ) {
        $z  += 719468;
        $era = intdiv( $z >= 0 ? $z : $z - 146096, 146097 );
        $doe = $z - $era * 146097;
        $yoe = intdiv( $doe - intdiv( $doe, 1460 ) + intdiv( $doe, 36524 ) - intdiv( $doe, 146096 ), 365 );
        $y   = $yoe + $era * 400;
        $doy = $doe - ( 365 * $yoe + intdiv( $yoe, 4 ) - intdiv( $yoe, 100 ) );
        $mp  = intdiv( 5 * $doy + 2, 153 );
        $d   = $doy - intdiv( 153 * $mp + 2, 5 ) + 1;
        $m   = $mp + ( $mp < 10 ? 3 : -9 );
        $y  += $m <= 2 ? 1 : 0;
        return sprintf( '%04d-%02d-%02d', $y, $m, $d );
    }
}
