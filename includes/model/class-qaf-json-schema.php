<?php
/**
 * A small JSON Schema validator.
 *
 * Enough of JSON Schema 2020-12 to check a form model against the published
 * schema/form.schema.json, so PHP validates against the same file QUILL's
 * Python reader uses instead of a second, hand-written copy of the rules.
 *
 * Keywords supported: type, enum, const, required, properties,
 * additionalProperties, propertyNames, items, minItems, maxItems, minLength,
 * minimum, maximum, pattern, format ("qaf-expression" is checked; others are
 * ignored), $ref (to "#/..." within the same document), allOf, anyOf, oneOf,
 * not, if/then/else, uniqueItems. Unknown keywords are ignored, as the
 * specification asks.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_JSON_Schema class.
 */
class QAF_JSON_Schema {

    /**
     * The root schema document.
     *
     * @var array
     */
    private $root;

    /**
     * Errors found.
     *
     * @var array
     */
    private $errors = array();

    /**
     * Constructor.
     *
     * @param array $schema Decoded schema document.
     */
    public function __construct( $schema ) {
        $this->root = $schema;
    }

    /**
     * Load a schema file.
     *
     * @param string $path Path.
     * @return self Validator.
     */
    public static function from_file( $path ) {
        return new self( json_decode( file_get_contents( $path ), true ) );
    }

    /**
     * Validate a decoded JSON value.
     *
     * @param mixed $data Data (decoded with json_decode( $s, true )).
     * @return array Errors: array of array( 'path' => '/a/0', 'message' => '...' ).
     */
    public function validate( $data ) {
        $this->errors = array();
        $this->check( $data, $this->root, '' );
        return $this->errors;
    }

    /**
     * Whether an array is a JSON object (an empty array counts as both).
     *
     * @param mixed $v Value.
     * @return bool Object.
     */
    public static function is_object( $v ) {
        return is_array( $v ) && ( $v === array() || array_keys( $v ) !== range( 0, count( $v ) - 1 ) );
    }

    /**
     * Whether an array is a JSON array.
     *
     * @param mixed $v Value.
     * @return bool List.
     */
    public static function is_list( $v ) {
        return is_array( $v ) && ( $v === array() || array_keys( $v ) === range( 0, count( $v ) - 1 ) );
    }

    /**
     * Whether a value has a JSON type.
     *
     * @param mixed  $v    Value.
     * @param string $type JSON type.
     * @return bool Match.
     */
    private static function has_type( $v, $type ) {
        switch ( $type ) {
            case 'object':
                return self::is_object( $v );
            case 'array':
                return self::is_list( $v );
            case 'string':
                return is_string( $v );
            case 'integer':
                return is_int( $v ) || ( is_float( $v ) && floor( $v ) == $v );
            case 'number':
                return is_int( $v ) || is_float( $v );
            case 'boolean':
                return is_bool( $v );
            case 'null':
                return $v === null;
        }
        return false;
    }

    /**
     * Resolve a local reference.
     *
     * @param string $ref Reference such as "#/$defs/field".
     * @return array Schema.
     */
    private function resolve( $ref ) {
        $node = $this->root;
        foreach ( explode( '/', substr( $ref, 2 ) ) as $part ) {
            $part = str_replace( array( '~1', '~0' ), array( '/', '~' ), $part );
            $node = $node[ $part ];
        }
        return $node;
    }

    /**
     * Run a schema and report whether it passed, without keeping its errors.
     *
     * @param mixed  $v      Value.
     * @param array  $schema Schema.
     * @param string $path   Path.
     * @return bool Passed.
     */
    private function passes( $v, $schema, $path ) {
        $saved        = $this->errors;
        $this->errors = array();
        $this->check( $v, $schema, $path );
        $ok           = empty( $this->errors );
        $this->errors = $saved;
        return $ok;
    }

    /**
     * Record an error.
     *
     * @param string $path    Path.
     * @param string $message Message.
     */
    private function error( $path, $message ) {
        $this->errors[] = array( 'path' => $path === '' ? '/' : $path, 'message' => $message );
    }

    /**
     * Validate one value against one schema.
     *
     * @param mixed      $v      Value.
     * @param array|bool $schema Schema.
     * @param string     $path   Path.
     */
    private function check( $v, $schema, $path ) {
        if ( $schema === true ) {
            return;
        }
        if ( $schema === false ) {
            $this->error( $path, 'Nothing is allowed here.' );
            return;
        }
        if ( isset( $schema['$ref'] ) ) {
            $this->check( $v, $this->resolve( $schema['$ref'] ), $path );
        }
        if ( isset( $schema['type'] ) ) {
            $types = (array) $schema['type'];
            $ok    = false;
            foreach ( $types as $t ) {
                if ( self::has_type( $v, $t ) ) {
                    $ok = true;
                    break;
                }
            }
            if ( ! $ok ) {
                $this->error( $path, 'Expected ' . implode( ' or ', $types ) . '.' );
                return;
            }
        }
        if ( array_key_exists( 'const', $schema ) && $v !== $schema['const'] ) {
            $this->error( $path, 'Must be ' . json_encode( $schema['const'] ) . '.' );
        }
        if ( isset( $schema['enum'] ) && ! in_array( $v, $schema['enum'], true ) ) {
            $this->error( $path, 'Must be one of: ' . implode( ', ', array_map( 'json_encode', $schema['enum'] ) ) . '.' );
        }
        if ( is_string( $v ) ) {
            $len = (int) preg_match_all( '/./us', $v );
            if ( isset( $schema['minLength'] ) && $len < $schema['minLength'] ) {
                $this->error( $path, 'Must be at least ' . $schema['minLength'] . ' character(s).' );
            }
            if ( isset( $schema['pattern'] ) && ! preg_match( '/' . str_replace( '/', '\/', $schema['pattern'] ) . '/u', $v ) ) {
                $this->error( $path, 'Does not match the pattern ' . $schema['pattern'] . '.' );
            }
            if ( ( $schema['format'] ?? '' ) === 'qaf-expression' && class_exists( 'QAF_Expression' ) ) {
                $problem = QAF_Expression::check( $v );
                if ( $problem !== null ) {
                    $this->error( $path, 'Not a valid expression: ' . $problem );
                }
            }
        }
        if ( is_int( $v ) || is_float( $v ) ) {
            if ( isset( $schema['minimum'] ) && $v < $schema['minimum'] ) {
                $this->error( $path, 'Must be at least ' . $schema['minimum'] . '.' );
            }
            if ( isset( $schema['maximum'] ) && $v > $schema['maximum'] ) {
                $this->error( $path, 'Must be at most ' . $schema['maximum'] . '.' );
            }
        }
        if ( self::is_list( $v ) && ( isset( $schema['items'] ) || isset( $schema['minItems'] ) || isset( $schema['maxItems'] ) || isset( $schema['uniqueItems'] ) ) && ( ! self::is_object( $v ) || $v === array() ) ) {
            if ( isset( $schema['minItems'] ) && count( $v ) < $schema['minItems'] ) {
                $this->error( $path, 'Needs at least ' . $schema['minItems'] . ' item(s).' );
            }
            if ( isset( $schema['maxItems'] ) && count( $v ) > $schema['maxItems'] ) {
                $this->error( $path, 'Allows at most ' . $schema['maxItems'] . ' item(s).' );
            }
            if ( ! empty( $schema['uniqueItems'] ) && count( array_unique( array_map( 'json_encode', $v ) ) ) !== count( $v ) ) {
                $this->error( $path, 'Items must not repeat.' );
            }
            if ( isset( $schema['items'] ) ) {
                foreach ( $v as $i => $item ) {
                    $this->check( $item, $schema['items'], $path . '/' . $i );
                }
            }
        }
        if ( self::is_object( $v ) ) {
            foreach ( $schema['required'] ?? array() as $key ) {
                if ( ! array_key_exists( $key, $v ) ) {
                    $this->error( $path, 'Missing "' . $key . '".' );
                }
            }
            $props = $schema['properties'] ?? array();
            foreach ( $v as $key => $value ) {
                $key = (string) $key;
                $sub = $path . '/' . str_replace( array( '~', '/' ), array( '~0', '~1' ), $key );
                if ( isset( $schema['propertyNames'] ) && ! $this->passes( $key, $schema['propertyNames'], $sub ) ) {
                    $this->error( $sub, 'The name "' . $key . '" is not allowed here.' );
                }
                if ( array_key_exists( $key, $props ) ) {
                    $this->check( $value, $props[ $key ], $sub );
                } elseif ( array_key_exists( 'additionalProperties', $schema ) ) {
                    if ( $schema['additionalProperties'] === false ) {
                        $this->error( $sub, 'Unknown property "' . $key . '".' );
                    } else {
                        $this->check( $value, $schema['additionalProperties'], $sub );
                    }
                }
            }
        }
        foreach ( $schema['allOf'] ?? array() as $sub ) {
            $this->check( $v, $sub, $path );
        }
        if ( isset( $schema['anyOf'] ) ) {
            $ok = false;
            foreach ( $schema['anyOf'] as $sub ) {
                if ( $this->passes( $v, $sub, $path ) ) {
                    $ok = true;
                    break;
                }
            }
            if ( ! $ok ) {
                $this->error( $path, $schema['x-message'] ?? 'Does not match any allowed shape.' );
            }
        }
        if ( isset( $schema['oneOf'] ) ) {
            $n = 0;
            foreach ( $schema['oneOf'] as $sub ) {
                if ( $this->passes( $v, $sub, $path ) ) {
                    $n++;
                }
            }
            if ( $n !== 1 ) {
                $this->error( $path, $schema['x-message'] ?? 'Must match exactly one allowed shape.' );
            }
        }
        if ( isset( $schema['not'] ) && $this->passes( $v, $schema['not'], $path ) ) {
            $this->error( $path, $schema['x-message'] ?? 'This shape is not allowed here.' );
        }
        if ( isset( $schema['if'] ) ) {
            if ( $this->passes( $v, $schema['if'], $path ) ) {
                if ( isset( $schema['then'] ) ) {
                    $this->check( $v, $schema['then'], $path );
                }
            } elseif ( isset( $schema['else'] ) ) {
                $this->check( $v, $schema['else'], $path );
            }
        }
    }
}
