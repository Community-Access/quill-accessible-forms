<?php
/**
 * Convert, and the conditions that read the question.
 *
 * When a question changes type, its answers can change kind: a choice's
 * stored value ("post") becomes whatever is typed ("Post"), or a typed
 * answer becomes one of the new choices' values. Conditions, calculations
 * and rules that compared the question with its old values would then
 * quietly never match. This rewrites what it can (a choice value to its
 * label, a typed answer to the choice whose label it is) and flags what it
 * cannot, so Convert can say both before and after it changes anything.
 *
 * No WordPress functions are needed beyond __(), so it runs in tests.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Condition_Rewriter class.
 */
class QAF_Condition_Rewriter {

    /** Types whose answer is a number. */
    const NUMBER_TYPES = array( 'number', 'range', 'slider', 'rating', 'nps', 'currency', 'quantity', 'calculation', 'price', 'total', 'scale' );

    /** Types whose answer is a date or a time. */
    const DATE_TYPES = array( 'date', 'datetime', 'time', 'daterange', 'appointment' );

    /** Types that hold no answer at all. */
    const NO_ANSWER_TYPES = array( 'html', 'captcha', 'submit', 'save', 'progress', 'summary', 'page' );

    /** Operators that compare a question with a value. */
    const VALUE_OPS = array( 'is', 'is_not', 'contains', 'not_contains', 'one_of', 'not_one_of', 'starts_with', 'ends_with' );

    /** Operators that order: numbers or dates. */
    const ORDER_OPS = array( 'lt', 'le', 'gt', 'ge' );

    /** Operators that only dates answer. */
    const DATE_OPS = array( 'before', 'after', 'within' );

    /** Arithmetic. */
    const MATH_OPS = array( 'add', 'sub', 'mul', 'div', 'mod', 'neg' );

    /**
     * Rewrite or flag every expression that reads a question whose type
     * changed.
     *
     * @param array  $model  The model after the change.
     * @param string $id     The question's ID.
     * @param array  $before The question as it was.
     * @param array  $after  The question as it is now.
     * @param array  $choice_types Types that answer with a choice.
     * @return array array( model, rewritten: list of array( before, after ), flagged: list of array( text, reason ) ).
     */
    public static function rewrite( $model, $id, $before, $after, $choice_types ) {
        $from = (string) ( $before['type'] ?? '' );
        $to   = (string) ( $after['type'] ?? '' );
        $ctx  = array(
            'id'          => (string) $id,
            'from_choice' => in_array( $from, $choice_types, true ),
            'to_choice'   => in_array( $to, $choice_types, true ),
            'old'         => self::choice_map( $before, $model ),
            'new'         => self::choice_map( $after, $model ),
            'to_number'   => in_array( $to, self::NUMBER_TYPES, true ),
            'to_date'     => in_array( $to, self::DATE_TYPES, true ),
            'no_answer'   => in_array( $to, self::NO_ANSWER_TYPES, true ),
            'label'       => (string) ( $after['label'] ?? $id ),
            'to'          => $to,
        );
        $rewritten = array();
        $flagged   = array();
        if ( $from === $to ) {
            return array( $model, $rewritten, $flagged );
        }

        $visit = function ( &$text ) use ( $ctx, &$rewritten, &$flagged ) {
            if ( ! is_string( $text ) || $text === '' ) {
                return;
            }
            try {
                $ast = QAF_Expression::parse( $text );
            } catch ( Exception $e ) {
                return; // An expression that does not read is validate()'s to report.
            }
            if ( ! in_array( $ctx['id'], QAF_Expression::refs( $ast ), true ) ) {
                return;
            }
            $reasons = array();
            $changed = false;
            $ast     = self::transform( $ast, $ctx, $reasons, $changed );
            if ( $changed ) {
                $new         = QAF_Expression::to_text( $ast );
                $rewritten[] = array( 'before' => $text, 'after' => $new );
                $text        = $new;
            }
            foreach ( array_unique( $reasons ) as $why ) {
                $flagged[] = array( 'text' => $text, 'reason' => $why );
            }
        };

        foreach ( $model['pages'] ?? array() as $p => $page ) {
            if ( isset( $page['when'] ) ) {
                $visit( $model['pages'][ $p ]['when'] );
            }
            if ( isset( $page['items'] ) && is_array( $page['items'] ) ) {
                self::walk( $model['pages'][ $p ]['items'], $visit );
            }
        }
        foreach ( $model['rules'] ?? array() as $r => $rule ) {
            if ( isset( $rule['when'] ) ) {
                $visit( $model['rules'][ $r ]['when'] );
            }
            if ( isset( $rule['value'] ) && is_string( $rule['value'] ) ) {
                $visit( $model['rules'][ $r ]['value'] );
            }
        }
        foreach ( $model['scoring']['outcomes'] ?? array() as $o => $outcome ) {
            if ( isset( $outcome['when'] ) ) {
                $visit( $model['scoring']['outcomes'][ $o ]['when'] );
            }
        }
        return array( $model, $rewritten, $flagged );
    }

    /**
     * Visit each expression on items, in sections, columns and repeaters.
     *
     * @param array    $items Items (by reference).
     * @param callable $visit Visitor, given each text by reference.
     */
    private static function walk( &$items, $visit ) {
        foreach ( $items as $i => $item ) {
            foreach ( array( 'when', 'calculation', 'smart_default' ) as $k ) {
                if ( isset( $item[ $k ] ) ) {
                    $visit( $items[ $i ][ $k ] );
                }
            }
            if ( ( $item['type'] ?? '' ) === 'columns' ) {
                foreach ( $item['columns'] ?? array() as $c => $col ) {
                    if ( isset( $col['items'] ) && is_array( $col['items'] ) ) {
                        self::walk( $items[ $i ]['columns'][ $c ]['items'], $visit );
                    }
                }
            } elseif ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
                self::walk( $items[ $i ]['items'], $visit );
            }
        }
    }

    /**
     * A question's choices, value => label (inline, or from the model's own
     * libraries). Null when they come from somewhere this cannot see (a
     * built-in library or a cascade).
     *
     * @param array $field Field.
     * @param array $model Model.
     * @return array|null Choices.
     */
    private static function choice_map( $field, $model ) {
        $list = null;
        if ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
            $list = $field['choices'];
        } elseif ( isset( $field['choice_library'], $model['choice_libraries'][ $field['choice_library'] ] ) ) {
            $list = $model['choice_libraries'][ $field['choice_library'] ];
        }
        if ( $list === null ) {
            return null;
        }
        $out = array();
        foreach ( $list as $c ) {
            if ( is_array( $c ) && isset( $c['value'] ) ) {
                $out[ (string) $c['value'] ] = (string) ( $c['label'] ?? $c['value'] );
            } elseif ( is_scalar( $c ) ) {
                $out[ (string) $c ] = (string) $c;
            }
        }
        return $out;
    }

    /**
     * Whether a node is a reference to the question.
     *
     * @param array $node Node.
     * @param array $ctx  Context.
     * @return bool
     */
    private static function is_ref( $node, $ctx ) {
        return is_array( $node ) && ( $node['type'] ?? '' ) === 'ref' && (string) ( $node['field'] ?? '' ) === $ctx['id'];
    }

    /**
     * Rewrite a tree, collecting the reasons a part could not be.
     *
     * @param array $node    Node.
     * @param array $ctx     Context.
     * @param array $reasons Reasons (added to).
     * @param bool  $changed Set when anything was rewritten.
     * @return array Node.
     */
    private static function transform( $node, $ctx, &$reasons, &$changed ) {
        if ( ! is_array( $node ) ) {
            return $node;
        }
        $type = $node['type'] ?? '';
        if ( $type === 'ref' && self::is_ref( $node, $ctx ) && $ctx['no_answer'] ) {
            /* translators: 1: question label, 2: its new type. */
            $reasons[] = sprintf( __( '%1$s is now a %2$s, which has no answer to read.', 'quill-accessible-forms' ), $ctx['label'], $ctx['to'] );
            return $node;
        }
        if ( $type === 'list' ) {
            foreach ( $node['items'] as $k => $child ) {
                $node['items'][ $k ] = self::transform( $child, $ctx, $reasons, $changed );
            }
            return $node;
        }
        if ( $type === 'call' ) {
            foreach ( $node['args'] as $k => $child ) {
                $node['args'][ $k ] = self::transform( $child, $ctx, $reasons, $changed );
            }
            return $node;
        }
        if ( $type !== 'op' ) {
            return $node;
        }
        $op   = $node['op'];
        $args = $node['args'];

        if ( in_array( $op, self::VALUE_OPS, true ) && count( $args ) === 2 && ! $ctx['no_answer'] ) {
            $side = self::is_ref( $args[0], $ctx ) ? 1 : ( self::is_ref( $args[1], $ctx ) && in_array( $op, array( 'is', 'is_not' ), true ) ? 0 : -1 );
            if ( $side >= 0 ) {
                $node['args'][ $side ] = self::map_values( $args[ $side ], $ctx, $reasons, $changed );
                return $node;
            }
        }
        $reads = false;
        foreach ( $args as $a ) {
            $reads = $reads || self::is_ref( $a, $ctx );
        }
        if ( $reads && ! $ctx['no_answer'] ) {
            if ( in_array( $op, self::ORDER_OPS, true ) && ! $ctx['to_number'] && ! $ctx['to_date'] ) {
                /* translators: %s: question label. */
                $reasons[] = sprintf( __( 'It compares %s as a number or a date, and its answer is no longer one.', 'quill-accessible-forms' ), $ctx['label'] );
            } elseif ( in_array( $op, self::DATE_OPS, true ) && ! $ctx['to_date'] ) {
                /* translators: %s: question label. */
                $reasons[] = sprintf( __( 'It reads %s as a date, and its answer is no longer one.', 'quill-accessible-forms' ), $ctx['label'] );
            } elseif ( in_array( $op, self::MATH_OPS, true ) && ! $ctx['to_number'] && ! $ctx['to_date'] ) {
                /* translators: %s: question label. */
                $reasons[] = sprintf( __( 'It does arithmetic with %s, and its answer is no longer a number.', 'quill-accessible-forms' ), $ctx['label'] );
            }
        }
        foreach ( $args as $k => $child ) {
            $node['args'][ $k ] = self::transform( $child, $ctx, $reasons, $changed );
        }
        return $node;
    }

    /**
     * The values a question is compared with, in its new kind of answer.
     *
     * @param array $node    A literal, or a list of them.
     * @param array $ctx     Context.
     * @param array $reasons Reasons (added to).
     * @param bool  $changed Set when anything was rewritten.
     * @return array Node.
     */
    private static function map_values( $node, $ctx, &$reasons, &$changed ) {
        if ( ( $node['type'] ?? '' ) === 'list' ) {
            foreach ( $node['items'] as $k => $item ) {
                $node['items'][ $k ] = self::map_values( $item, $ctx, $reasons, $changed );
            }
            return $node;
        }
        if ( ( $node['type'] ?? '' ) !== 'literal' || ! is_scalar( $node['value'] ) ) {
            return $node;
        }
        $value = (string) $node['value'];

        // A choice's value to what is typed: its label.
        if ( $ctx['from_choice'] && ! $ctx['to_choice'] ) {
            if ( $ctx['old'] === null || ! array_key_exists( $value, $ctx['old'] ) ) {
                /* translators: 1: question label, 2: a value. */
                $reasons[] = sprintf( __( 'It compares %1$s with "%2$s", which was not one of its choices, so what is typed now may never match it.', 'quill-accessible-forms' ), $ctx['label'], $value );
                return $node;
            }
            if ( $ctx['to_number'] || $ctx['to_date'] ) {
                /* translators: 1: question label, 2: a choice label. */
                $reasons[] = sprintf( __( 'It compares %1$s with the choice "%2$s", and its answer is now typed as a number or date.', 'quill-accessible-forms' ), $ctx['label'], $ctx['old'][ $value ] );
                return $node;
            }
            $label = $ctx['old'][ $value ];
            if ( $label !== $value ) {
                $node['value'] = $label;
                $changed       = true;
            }
            return $node;
        }

        // What was typed to a choice: the value of the choice it names.
        if ( ! $ctx['from_choice'] && $ctx['to_choice'] ) {
            if ( $ctx['new'] === null ) {
                return $node;
            }
            if ( array_key_exists( $value, $ctx['new'] ) ) {
                return $node;
            }
            foreach ( $ctx['new'] as $v => $label ) {
                if ( strtolower( trim( $label ) ) === strtolower( trim( $value ) ) ) {
                    $node['value'] = (string) $v;
                    $changed       = true;
                    return $node;
                }
            }
            /* translators: 1: question label, 2: a value. */
            $reasons[] = sprintf( __( 'It compares %1$s with "%2$s", which is not one of its choices now.', 'quill-accessible-forms' ), $ctx['label'], $value );
        }
        return $node;
    }

    /**
     * What Convert says about the conditions it rewrote and flagged.
     *
     * @param array $rewritten Rewritten (before, after).
     * @param array $flagged   Flagged (text, reason).
     * @return array Sentences.
     */
    public static function sentences( $rewritten, $flagged ) {
        $out = array();
        foreach ( $rewritten as $r ) {
            /* translators: 1: the condition before, 2: after. */
            $out[] = sprintf( __( 'A condition was rewritten for the new answers: %1$s became %2$s.', 'quill-accessible-forms' ), $r['before'], $r['after'] );
        }
        foreach ( $flagged as $f ) {
            /* translators: 1: the condition, 2: why it needs checking. */
            $out[] = sprintf( __( 'Check this condition: %1$s. %2$s', 'quill-accessible-forms' ), $f['text'], $f['reason'] );
        }
        return $out;
    }
}
