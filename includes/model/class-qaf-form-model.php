<?php
/**
 * The QAF form model.
 *
 * A form is one JSON document, described by schema/form.schema.json and
 * docs/form-model.md. QAF stores it; the visual builder, BFL and QUILL are
 * views of it. This class holds the facts about the model that code needs
 * (the field types, which ones take choices), checks a model (the schema plus
 * the rules a schema cannot say, such as "every #id an expression names must
 * exist"), and works out quiz scores and outcomes.
 *
 * No WordPress functions are used.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-qaf-expression.php';
require_once __DIR__ . '/class-qaf-json-schema.php';

/**
 * QAF_Form_Model class.
 */
class QAF_Form_Model {

    /**
     * The model version this code writes.
     */
    const VERSION = 1;

    /**
     * Every field type, in the order the builder's palette lists them.
     *
     * @var array
     */
    const FIELD_TYPES = array(
        'text', 'textarea', 'number', 'email', 'phone', 'website', 'hidden', 'password',
        'color', 'range', 'slider', 'currency', 'otp', 'toggle',
        'select', 'multiselect', 'radio', 'checkbox', 'consent', 'image_choice',
        'likert', 'matrix', 'ranking', 'rating', 'nps', 'quiz',
        'date', 'time', 'datetime', 'daterange', 'appointment',
        'name', 'address', 'fileupload', 'signature', 'typed_signature', 'rich_text',
        'map', 'social', 'table', 'captcha', 'repeater', 'calculation',
        'payment', 'product', 'quantity', 'price', 'total', 'coupon',
        'html', 'progress', 'summary', 'submit', 'save',
        'post_title', 'post_content', 'post_excerpt', 'post_category', 'post_tags', 'post_image', 'post_custom',
        'user_login', 'user_email', 'user_password',
    );

    /**
     * Item types that hold other items rather than collect a value.
     *
     * @var array
     */
    const LAYOUT_TYPES = array( 'section', 'columns' );

    /**
     * Field types whose answers come from a list of choices.
     *
     * @var array
     */
    const CHOICE_TYPES = array( 'select', 'multiselect', 'radio', 'checkbox', 'ranking', 'image_choice', 'quiz' );

    /**
     * Field types that may carry choices without needing them.
     *
     * @var array
     */
    const OPTIONAL_CHOICE_TYPES = array( 'product', 'likert', 'consent', 'toggle' );

    /**
     * Fixed property names of the objects that have props, by kind. A prop
     * must not use one of these, so its BFL spelling stays unambiguous.
     *
     * @var array
     */
    const FIXED_NAMES = array(
        'page'         => array( 'id', 'title', 'description', 'when', 'items', 'props' ),
        'confirmation' => array( 'id', 'type', 'message', 'url', 'page_id', 'when', 'props' ),
        'notification' => array( 'id', 'enabled', 'to', 'cc', 'bcc', 'from_name', 'from_email', 'reply_to', 'subject', 'message', 'when', 'props' ),
        // BFL writes settings as form modifiers, beside these.
        'settings'     => array( 'description', 'desc', 'language', 'extensions', 'ajax', 'honeypot', 'captcha' ),
    );

    /**
     * Cached schema validator.
     *
     * @var QAF_JSON_Schema|null
     */
    private static $schema = null;

    /**
     * Path of the published JSON Schema.
     *
     * @return string Path.
     */
    public static function schema_path() {
        return dirname( __DIR__, 2 ) . '/schema/form.schema.json';
    }

    /**
     * A new, empty form.
     *
     * @param string $title Title.
     * @return array Model.
     */
    public static function create( $title = '' ) {
        return array(
            'version' => self::VERSION,
            'title'   => (string) $title,
            'pages'   => array( array( 'id' => 'page_1', 'items' => array() ) ),
        );
    }

    /**
     * Whether a type is a field type (rather than section or columns).
     *
     * @param string $type Type.
     * @return bool Field type.
     */
    public static function is_field_type( $type ) {
        return in_array( $type, self::FIELD_TYPES, true );
    }

    /**
     * The slug QAF uses for a choice's value when only a label is written:
     * lower case, every run of characters other than a-z and 0-9 turned into
     * one hyphen, and hyphens trimmed from both ends. For plain English labels
     * this is what WordPress's sanitize_title() gives.
     *
     * @param string $text Label.
     * @return string Slug.
     */
    public static function slug( $text ) {
        $s = strtolower( (string) $text );
        $s = preg_replace( '/[^a-z0-9]+/', '-', $s );
        return trim( $s, '-' );
    }

    // ---------------------------------------------------------------------
    // Walking the tree
    // ---------------------------------------------------------------------

    /**
     * Every item in the form (fields, sections, columns, and the fields
     * inside repeaters), depth first, with its JSON pointer path.
     *
     * @param array $model Model.
     * @return array List of array( 'item' => ..., 'path' => '/pages/0/items/1', 'page' => page id ).
     */
    public static function items( $model ) {
        $out = array();
        foreach ( $model['pages'] ?? array() as $pi => $page ) {
            self::walk( $page['items'] ?? array(), '/pages/' . $pi . '/items', $page['id'] ?? '', $out );
        }
        return $out;
    }

    /**
     * Walk worker.
     *
     * @param array  $items Items.
     * @param string $path  Path.
     * @param string $page  Page id.
     * @param array  $out   Accumulator.
     */
    private static function walk( $items, $path, $page, &$out ) {
        foreach ( $items as $i => $item ) {
            $p     = $path . '/' . $i;
            $out[] = array( 'item' => $item, 'path' => $p, 'page' => $page );
            if ( ( $item['type'] ?? '' ) === 'columns' ) {
                foreach ( $item['columns'] ?? array() as $ci => $col ) {
                    self::walk( $col['items'] ?? array(), $p . '/columns/' . $ci . '/items', $page, $out );
                }
            } elseif ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
                self::walk( $item['items'], $p . '/items', $page, $out );
            }
        }
    }

    /**
     * Fields only, keyed by ID.
     *
     * @param array $model Model.
     * @return array ID => field.
     */
    public static function fields( $model ) {
        $out = array();
        foreach ( self::items( $model ) as $entry ) {
            $item = $entry['item'];
            if ( self::is_field_type( $item['type'] ?? '' ) ) {
                $out[ $item['id'] ] = $item;
            }
        }
        return $out;
    }

    /**
     * Labels for sentences: item ID => label.
     *
     * @param array $model Model.
     * @return array Labels.
     */
    public static function labels( $model ) {
        $out = array();
        foreach ( self::items( $model ) as $entry ) {
            if ( isset( $entry['item']['id'] ) ) {
                $out[ $entry['item']['id'] ] = $entry['item']['label'] ?? '';
            }
        }
        return $out;
    }

    /**
     * Choice labels for sentences: field ID => ( value => label ).
     *
     * @param array $model Model.
     * @return array Choice labels.
     */
    public static function choice_labels( $model ) {
        $out = array();
        foreach ( self::fields( $model ) as $id => $field ) {
            $choices = $field['choices'] ?? ( isset( $field['choice_library'] ) ? ( $model['choice_libraries'][ $field['choice_library'] ] ?? array() ) : array() );
            foreach ( $choices as $c ) {
                $out[ $id ][ $c['value'] ] = $c['label'];
            }
        }
        return $out;
    }

    /**
     * An expression as an English sentence, using this form's labels.
     *
     * @param array  $model Model.
     * @param string $text  Expression text.
     * @return string Sentence.
     */
    public static function sentence( $model, $text ) {
        return QAF_Expression::to_sentence( QAF_Expression::parse( $text ), self::labels( $model ), self::choice_labels( $model ) );
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /**
     * Check a model against the schema and the rules the schema cannot express.
     *
     * @param array $model Model.
     * @return array Errors: array( 'path' => ..., 'message' => ... ). Empty when valid.
     */
    public static function validate( $model ) {
        if ( self::$schema === null ) {
            self::$schema = QAF_JSON_Schema::from_file( self::schema_path() );
        }
        $errors = self::$schema->validate( $model );
        if ( $errors ) {
            return $errors;
        }
        return self::semantic_errors( $model );
    }

    /**
     * Whether a model is valid.
     *
     * @param array $model Model.
     * @return bool Valid.
     */
    public static function is_valid( $model ) {
        return empty( self::validate( $model ) );
    }

    /**
     * Rules beyond the schema.
     *
     * @param array $model A model that already passed the schema.
     * @return array Errors.
     */
    public static function semantic_errors( $model ) {
        $errors   = array();
        $ids      = array();
        $page_ids = array();
        $fields   = array();
        $err      = function ( $path, $message ) use ( &$errors ) {
            $errors[] = array( 'path' => $path, 'message' => $message );
        };

        foreach ( array_keys( $model['settings'] ?? array() ) as $k ) {
            if ( in_array( $k, self::FIXED_NAMES['settings'], true ) ) {
                $err( '/settings/' . $k, 'A setting cannot be called "' . $k . '".' );
            }
        }
        foreach ( $model['pages'] as $pi => $page ) {
            if ( isset( $page_ids[ $page['id'] ] ) ) {
                $err( '/pages/' . $pi . '/id', 'The page ID "' . $page['id'] . '" is used twice.' );
            }
            $page_ids[ $page['id'] ] = true;
            foreach ( array_keys( $page['props'] ?? array() ) as $k ) {
                if ( in_array( $k, self::FIXED_NAMES['page'], true ) ) {
                    $err( '/pages/' . $pi . '/props/' . $k, 'A page prop cannot be called "' . $k . '".' );
                }
            }
        }
        foreach ( self::items( $model ) as $entry ) {
            $item = $entry['item'];
            if ( isset( $ids[ $item['id'] ] ) || isset( $page_ids[ $item['id'] ] ) ) {
                $err( $entry['path'] . '/id', 'The ID "' . $item['id'] . '" is used twice.' );
            }
            $ids[ $item['id'] ] = $item['type'];
            if ( self::is_field_type( $item['type'] ) ) {
                $fields[ $item['id'] ] = $item;
            }
        }

        $check_expr = function ( $text, $path ) use ( $ids, $err ) {
            foreach ( QAF_Expression::refs( QAF_Expression::parse( $text ) ) as $ref ) {
                if ( ! isset( $ids[ $ref ] ) ) {
                    $err( $path, 'The expression refers to #' . $ref . ', which is not in this form.' );
                }
            }
        };

        foreach ( $model['pages'] as $pi => $page ) {
            if ( isset( $page['when'] ) ) {
                $check_expr( $page['when'], '/pages/' . $pi . '/when' );
            }
        }
        foreach ( self::items( $model ) as $entry ) {
            $item = $entry['item'];
            foreach ( array( 'when', 'calculation', 'smart_default' ) as $k ) {
                if ( isset( $item[ $k ] ) ) {
                    $check_expr( $item[ $k ], $entry['path'] . '/' . $k );
                }
            }
            if ( isset( $item['cascade']['from'] ) && ! isset( $fields[ $item['cascade']['from'] ] ) ) {
                $err( $entry['path'] . '/cascade/from', 'The cascade comes from #' . $item['cascade']['from'] . ', which is not in this form.' );
            }
            if ( isset( $item['choices'] ) ) {
                $seen = array();
                foreach ( $item['choices'] as $ci => $c ) {
                    if ( isset( $seen[ $c['value'] ] ) ) {
                        $err( $entry['path'] . '/choices/' . $ci . '/value', 'The choice value "' . $c['value'] . '" is used twice.' );
                    }
                    $seen[ $c['value'] ] = true;
                }
            }
            if ( isset( $item['scoring']['correct'], $item['choices'] ) ) {
                $values = array_column( $item['choices'], 'value' );
                foreach ( $item['scoring']['correct'] as $v ) {
                    if ( ! in_array( $v, $values, true ) ) {
                        $err( $entry['path'] . '/scoring/correct', 'The correct answer "' . $v . '" is not one of the choices.' );
                    }
                }
            }
        }
        foreach ( $model['rules'] ?? array() as $ri => $rule ) {
            $check_expr( $rule['when'], '/rules/' . $ri . '/when' );
            if ( isset( $rule['value'] ) ) {
                $check_expr( $rule['value'], '/rules/' . $ri . '/value' );
            }
            foreach ( $rule['targets'] as $ti => $target ) {
                $known = $rule['action'] === 'skip_to' ? isset( $page_ids[ $target ] ) : isset( $ids[ $target ] ) || isset( $page_ids[ $target ] );
                if ( ! $known ) {
                    $err( '/rules/' . $ri . '/targets/' . $ti, 'The rule names #' . $target . ', which is not in this form.' );
                }
            }
        }
        foreach ( $model['scoring']['outcomes'] ?? array() as $oi => $outcome ) {
            $check_expr( $outcome['when'], '/scoring/outcomes/' . $oi . '/when' );
        }
        foreach ( array( 'confirmations' => 'confirmation', 'notifications' => 'notification' ) as $list => $kind ) {
            $seen = array();
            foreach ( $model[ $list ] ?? array() as $i => $obj ) {
                if ( isset( $seen[ $obj['id'] ] ) ) {
                    $err( '/' . $list . '/' . $i . '/id', 'The ID "' . $obj['id'] . '" is used twice.' );
                }
                $seen[ $obj['id'] ] = true;
                if ( isset( $obj['when'] ) ) {
                    $check_expr( $obj['when'], '/' . $list . '/' . $i . '/when' );
                }
                foreach ( array_keys( $obj['props'] ?? array() ) as $k ) {
                    if ( in_array( $k, self::FIXED_NAMES[ $kind ], true ) ) {
                        $err( '/' . $list . '/' . $i . '/props/' . $k, 'A ' . $kind . ' prop cannot be called "' . $k . '".' );
                    }
                }
            }
        }
        foreach ( $model['translations'] ?? array() as $lang => $t ) {
            foreach ( array_keys( $t['fields'] ?? array() ) as $id ) {
                if ( ! isset( $ids[ $id ] ) ) {
                    $err( '/translations/' . $lang . '/fields/' . $id, 'The translation names #' . $id . ', which is not in this form.' );
                }
            }
            foreach ( array_keys( $t['pages'] ?? array() ) as $id ) {
                if ( ! isset( $page_ids[ $id ] ) ) {
                    $err( '/translations/' . $lang . '/pages/' . $id, 'The translation names page #' . $id . ', which is not in this form.' );
                }
            }
        }
        return $errors;
    }

    // ---------------------------------------------------------------------
    // Scoring
    // ---------------------------------------------------------------------

    /**
     * Points each scored field earns for the given answers, and the total.
     *
     * A field earns its scoring points when its answer matches the correct
     * answers (for several correct answers, all of them and nothing else;
     * with partial, a share for each correct one chosen, nothing if a wrong
     * one is chosen), plus the points of every chosen choice.
     *
     * @param array $model  Model.
     * @param array $values Field ID => answer.
     * @return array array( 'fields' => ID => points, 'total' => points ).
     */
    public static function score( $model, $values ) {
        $scores = array();
        foreach ( self::fields( $model ) as $id => $field ) {
            $answer   = $values[ $id ] ?? null;
            $chosen   = is_array( $answer ) ? array_map( 'strval', $answer ) : ( QAF_Expression::is_empty( $answer ) ? array() : array( (string) $answer ) );
            $points   = 0;
            $has      = false;
            $scoring  = $field['scoring'] ?? null;
            if ( $scoring && isset( $scoring['correct'] ) ) {
                $has     = true;
                $worth   = $scoring['points'] ?? 1;
                $correct = array_map( 'strval', $scoring['correct'] );
                $fold    = empty( $scoring['case_sensitive'] );
                $norm    = function ( $s ) use ( $fold ) {
                    $s = trim( $s );
                    return $fold ? strtolower( $s ) : $s;
                };
                $c = array_map( $norm, $correct );
                $a = array_map( $norm, $chosen );
                sort( $c );
                sort( $a );
                if ( $a === $c && $a !== array() ) {
                    $points += $worth;
                } elseif ( ! empty( $scoring['partial'] ) && $a && ! array_diff( $a, $c ) ) {
                    $points += $worth * count( $a ) / count( $c );
                }
            }
            foreach ( $field['choices'] ?? array() as $choice ) {
                if ( isset( $choice['points'] ) ) {
                    $has = true;
                    if ( in_array( (string) $choice['value'], $chosen, true ) ) {
                        $points += $choice['points'];
                    }
                }
            }
            if ( $has ) {
                $scores[ $id ] = ( is_float( $points ) && floor( $points ) == $points ) ? (int) $points : $points;
            }
        }
        $total = array_sum( $scores );
        return array( 'fields' => $scores, 'total' => ( is_float( $total ) && floor( $total ) == $total ) ? (int) $total : $total );
    }

    /**
     * The evaluation context for a set of answers.
     *
     * @param array $model  Model.
     * @param array $values Answers.
     * @param array $extra  Other context (today, user, query, geo).
     * @return array Context.
     */
    public static function context( $model, $values, $extra = array() ) {
        $score = self::score( $model, $values );
        return array_merge( $extra, array(
            'values' => $values,
            'scores' => $score['fields'],
            'score'  => $score['total'],
        ) );
    }

    /**
     * The first outcome whose condition holds, or null.
     *
     * @param array $model  Model.
     * @param array $values Answers.
     * @param array $extra  Other context.
     * @return array|null Outcome.
     */
    public static function outcome( $model, $values, $extra = array() ) {
        $ctx = self::context( $model, $values, $extra );
        foreach ( $model['scoring']['outcomes'] ?? array() as $outcome ) {
            if ( QAF_Expression::truthy( QAF_Expression::evaluate( $outcome['when'], $ctx ) ) ) {
                return $outcome;
            }
        }
        return null;
    }

    /**
     * Whether an item is shown for these answers: its own condition, every
     * enclosing section's and the page's must hold, and show/hide rules apply
     * (a hide rule that holds wins over a show rule).
     *
     * @param array  $model  Model.
     * @param string $id     Item ID.
     * @param array  $values Answers.
     * @param array  $extra  Other context.
     * @return bool Shown.
     */
    public static function is_shown( $model, $id, $values, $extra = array() ) {
        $ctx = self::context( $model, $values, $extra );
        foreach ( $model['pages'] as $page ) {
            $chain = self::chain( $page['items'], $id );
            if ( $chain === null ) {
                continue;
            }
            if ( isset( $page['when'] ) && ! QAF_Expression::truthy( QAF_Expression::evaluate( $page['when'], $ctx ) ) ) {
                return false;
            }
            foreach ( $chain as $item ) {
                if ( isset( $item['when'] ) && ! QAF_Expression::truthy( QAF_Expression::evaluate( $item['when'], $ctx ) ) ) {
                    return false;
                }
            }
            $shown = true;
            foreach ( $model['rules'] ?? array() as $rule ) {
                $names = array_column( $chain, 'id' );
                $names[] = $page['id'];
                if ( ! array_intersect( $rule['targets'], $names ) ) {
                    continue;
                }
                $holds = QAF_Expression::truthy( QAF_Expression::evaluate( $rule['when'], $ctx ) );
                if ( $rule['action'] === 'hide' && $holds ) {
                    return false;
                }
                if ( $rule['action'] === 'show' && ! $holds ) {
                    $shown = false;
                }
            }
            return $shown;
        }
        return false;
    }

    /**
     * The items from the top of a page down to the one with this ID.
     *
     * @param array  $items Items.
     * @param string $id    ID.
     * @return array|null Chain.
     */
    private static function chain( $items, $id ) {
        foreach ( $items as $item ) {
            if ( ( $item['id'] ?? null ) === $id ) {
                return array( $item );
            }
            $children = array();
            if ( ( $item['type'] ?? '' ) === 'columns' ) {
                foreach ( $item['columns'] ?? array() as $col ) {
                    $children = array_merge( $children, $col['items'] ?? array() );
                }
            } else {
                $children = $item['items'] ?? array();
            }
            $sub = self::chain( $children, $id );
            if ( $sub !== null ) {
                array_unshift( $sub, $item );
                return $sub;
            }
        }
        return null;
    }

    // ---------------------------------------------------------------------
    // Comparing and writing
    // ---------------------------------------------------------------------

    /**
     * A copy with every object's keys sorted, for comparison and stable output.
     *
     * @param mixed $v Value.
     * @return mixed Canonical value.
     */
    public static function canonicalize( $v ) {
        if ( ! is_array( $v ) ) {
            return $v;
        }
        if ( QAF_JSON_Schema::is_list( $v ) ) {
            return array_map( array( __CLASS__, 'canonicalize' ), $v );
        }
        ksort( $v, SORT_STRING );
        foreach ( $v as $k => $x ) {
            $v[ $k ] = self::canonicalize( $x );
        }
        return $v;
    }

    /**
     * Whether two models are the same document (key order ignored, types not).
     *
     * @param mixed $a Left.
     * @param mixed $b Right.
     * @return bool Same.
     */
    public static function same( $a, $b ) {
        return self::canonicalize( $a ) === self::canonicalize( $b );
    }

    /**
     * The model as pretty JSON.
     *
     * @param array $model Model.
     * @return string JSON.
     */
    public static function to_json( $model ) {
        return self::encode( $model, true ) . "\n";
    }

    /**
     * JSON-encode, keeping objects that are empty as {}.
     *
     * @param mixed $v      Value.
     * @param bool  $pretty Pretty-print.
     * @return string JSON.
     */
    public static function encode( $v, $pretty = false ) {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRESERVE_ZERO_FRACTION;
        if ( $pretty ) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode( self::objectify( $v ), $flags );
    }

    /**
     * Turn associative arrays into objects so empty objects stay objects.
     *
     * @param mixed  $v   Value.
     * @param string $key The key this value sits under.
     * @return mixed Value.
     */
    private static function objectify( $v, $key = '' ) {
        if ( ! is_array( $v ) ) {
            return $v;
        }
        // An empty PHP array is ambiguous; these keys always hold objects.
        $object_keys = array( 'settings', 'props', 'attributes', 'extensions', 'translations', 'choice_libraries', 'messages' );
        if ( $v === array() ) {
            return in_array( $key, $object_keys, true ) ? new stdClass() : array();
        }
        // A translation's choices map values to labels; values such as "0", "1"
        // arrive as a PHP list but are still an object.
        $is_label_map = $key === 'choices' && ! array_filter( $v, 'is_array' );
        if ( QAF_JSON_Schema::is_list( $v ) && ! $is_label_map ) {
            return array_map( array( __CLASS__, 'objectify' ), $v );
        }
        $o = new stdClass();
        foreach ( $v as $k => $x ) {
            $o->{$k} = self::objectify( $x, (string) $k );
        }
        return $o;
    }
}
