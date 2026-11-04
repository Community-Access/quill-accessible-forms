<?php
/**
 * What a published form needs from its model.
 *
 * The renderer draws a form from the builder's flat fields; this class adds
 * what only the model knows. It chooses the language and applies the
 * translation, writes the part of the model the browser needs to decide what
 * to show, require and calculate (public/js/baf-form-model.js runs it), and
 * picks the quiz outcome when the form is sent.
 *
 * What reaches the browser is only what it needs: items, their conditions,
 * calculations and smart defaults, choice values, and the rules. Correct
 * answers, outcomes, notifications, confirmations and settings stay on the
 * server, so a quiz's answers are not in the page and no address is.
 *
 * No WordPress functions are used, so the tests run without WordPress.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Form_Runtime class.
 */
class BAF_Form_Runtime {

    /**
     * Item keys the browser needs.
     *
     * @var array
     */
    const ITEM_KEYS = array( 'id', 'type', 'required', 'when', 'calculation', 'smart_default', 'default' );

    /**
     * The language to show the form in: the one asked for when the form has
     * it, else the visitor's locale when the form has that, else the form's own.
     *
     * @param array  $model     Model.
     * @param string $requested Language asked for (baf_lang), or ''.
     * @param string $locale    Site or visitor locale, such as "es_ES", or ''.
     * @return string Language code, or '' for the form's own text.
     */
    public static function language( $model, $requested = '', $locale = '' ) {
        $have = array_keys( $model['translations'] ?? array() );
        if ( ! $have ) {
            return '';
        }
        $own = strtolower( (string) ( $model['language'] ?? '' ) );
        foreach ( array( $requested, $locale ) as $want ) {
            $want = strtolower( str_replace( '_', '-', (string) $want ) );
            if ( $want === '' ) {
                continue;
            }
            if ( $want === $own || ( $own !== '' && strtok( $want, '-' ) === strtok( $own, '-' ) ) ) {
                return '';
            }
            foreach ( $have as $lang ) {
                if ( strtolower( $lang ) === $want ) {
                    return $lang;
                }
            }
            foreach ( $have as $lang ) {
                if ( strtok( strtolower( $lang ), '-' ) === strtok( $want, '-' ) ) {
                    return $lang;
                }
            }
        }
        return '';
    }

    /**
     * Apply a translation to the builder's stored form, for rendering.
     * Missing text falls back to the form's own.
     *
     * @param array  $config Builder config (fields, pages).
     * @param array  $model  Model.
     * @param string $lang   Language from language().
     * @return array Config.
     */
    public static function translate_config( $config, $model, $lang ) {
        $t = $lang !== '' ? ( $model['translations'][ $lang ] ?? null ) : null;
        if ( ! $t ) {
            return $config;
        }
        foreach ( $config['pages'] ?? array() as $i => $page ) {
            $pt = $t['pages'][ $page['id'] ?? '' ] ?? array();
            foreach ( array( 'title', 'description' ) as $k ) {
                if ( isset( $pt[ $k ] ) ) {
                    $config['pages'][ $i ][ $k ] = $pt[ $k ];
                }
            }
        }
        $map = array( 'label' => 'label', 'help' => 'description', 'placeholder' => 'placeholder' );
        foreach ( $config['fields'] ?? array() as $i => $field ) {
            $ft = $t['fields'][ $field['id'] ?? '' ] ?? null;
            if ( ! $ft ) {
                continue;
            }
            foreach ( $map as $from => $to ) {
                if ( isset( $ft[ $from ] ) ) {
                    $config['fields'][ $i ][ $to ] = $ft[ $from ];
                }
            }
            if ( isset( $ft['choices'] ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
                foreach ( $field['choices'] as $ci => $choice ) {
                    $value = is_array( $choice ) ? ( $choice['value'] ?? null ) : null;
                    if ( $value !== null && isset( $ft['choices'][ (string) $value ] ) ) {
                        $config['fields'][ $i ]['choices'][ $ci ]['label'] = $ft['choices'][ (string) $value ];
                    }
                }
            }
        }
        return $config;
    }

    /**
     * A translated piece of form-level text: title, or a built-in message.
     *
     * @param array  $model    Model.
     * @param string $lang     Language.
     * @param string $key      "title" or a message key such as "submit".
     * @param string $fallback Text when there is no translation.
     * @return string Text.
     */
    public static function text( $model, $lang, $key, $fallback ) {
        $t = $lang !== '' ? ( $model['translations'][ $lang ] ?? array() ) : array();
        if ( $key === 'title' ) {
            return isset( $t['title'] ) ? (string) $t['title'] : $fallback;
        }
        return isset( $t['messages'][ $key ] ) ? (string) $t['messages'][ $key ] : $fallback;
    }

    /**
     * The part of the model the browser runs.
     *
     * @param array $model Model.
     * @return array Public model.
     */
    public static function public_model( $model ) {
        $out = array( 'version' => $model['version'] ?? 1, 'pages' => array() );
        foreach ( $model['pages'] ?? array() as $page ) {
            $p = array( 'id' => $page['id'], 'items' => self::public_items( $page['items'] ?? array() ) );
            if ( isset( $page['when'] ) ) {
                $p['when'] = $page['when'];
            }
            $out['pages'][] = $p;
        }
        if ( ! empty( $model['rules'] ) ) {
            $out['rules'] = array_values( $model['rules'] );
        }
        return $out;
    }

    /**
     * Items for the browser.
     *
     * @param array $items Items.
     * @return array Items.
     */
    private static function public_items( $items ) {
        $out = array();
        foreach ( $items as $item ) {
            $o = array();
            foreach ( self::ITEM_KEYS as $k ) {
                if ( array_key_exists( $k, $item ) ) {
                    $o[ $k ] = $item[ $k ];
                }
            }
            if ( isset( $item['choices'] ) ) {
                $o['choices'] = array_map( function ( $c ) {
                    return array( 'value' => $c['value'] );
                }, $item['choices'] );
            }
            if ( ( $item['type'] ?? '' ) === 'columns' ) {
                $o['columns'] = array_map( function ( $col ) {
                    return array( 'items' => self::public_items( $col['items'] ?? array() ) );
                }, $item['columns'] ?? array() );
            } elseif ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
                $o['items'] = self::public_items( $item['items'] );
            }
            $out[] = $o;
        }
        return $out;
    }

    /**
     * Every expression in the model, for finding what it asks of the server.
     *
     * @param array $model Model.
     * @return array Expression texts.
     */
    public static function expressions( $model ) {
        $out = array();
        foreach ( $model['pages'] ?? array() as $page ) {
            if ( isset( $page['when'] ) ) {
                $out[] = $page['when'];
            }
        }
        foreach ( BAF_Form_Model::items( $model ) as $entry ) {
            foreach ( array( 'when', 'calculation', 'smart_default' ) as $k ) {
                if ( isset( $entry['item'][ $k ] ) ) {
                    $out[] = $entry['item'][ $k ];
                }
            }
        }
        foreach ( $model['rules'] ?? array() as $rule ) {
            $out[] = $rule['when'];
            if ( isset( $rule['value'] ) ) {
                $out[] = $rule['value'];
            }
        }
        return $out;
    }

    /**
     * The user() keys the published form asks for, so only those reach the page.
     *
     * @param array $model Model.
     * @return array Keys.
     */
    public static function user_keys( $model ) {
        $keys = array();
        foreach ( self::expressions( $model ) as $text ) {
            try {
                self::collect_user_keys( BAF_Expression::parse( $text ), $keys );
            } catch ( BAF_Expression_Error $e ) {
                continue;
            }
        }
        return array_values( array_unique( $keys ) );
    }

    /**
     * Worker for user_keys().
     *
     * @param array $node AST.
     * @param array $keys Accumulator.
     */
    private static function collect_user_keys( $node, &$keys ) {
        if ( ( $node['type'] ?? '' ) === 'call' && $node['name'] === 'user' && ( $node['args'][0]['type'] ?? '' ) === 'literal' && is_string( $node['args'][0]['value'] ) ) {
            $keys[] = $node['args'][0]['value'];
        }
        foreach ( array( 'args', 'items' ) as $k ) {
            foreach ( $node[ $k ] ?? array() as $child ) {
                self::collect_user_keys( $child, $keys );
            }
        }
    }

    /**
     * The user() values for the signed-in visitor, limited to the keys asked for.
     *
     * @param array $model Model.
     * @param array $user  Everything known about the visitor: key => value.
     * @return array Key => value.
     */
    public static function user_context( $model, $user ) {
        $out = array();
        foreach ( self::user_keys( $model ) as $k ) {
            if ( isset( $user[ $k ] ) && is_scalar( $user[ $k ] ) ) {
                $out[ $k ] = $user[ $k ];
            }
        }
        return $out;
    }

    /**
     * What the browser receives: the public model and the context it runs in.
     *
     * @param array  $model Model.
     * @param string $today Site date, "YYYY-MM-DD".
     * @param array  $user  Visitor data (see user_context()).
     * @return array array( 'model' => ..., 'context' => ... ).
     */
    public static function payload( $model, $today, $user = array() ) {
        return array(
            'model'   => self::public_model( $model ),
            'context' => array(
                'today' => $today,
                'user'  => (object) self::user_context( $model, $user ),
            ),
        );
    }

    /**
     * The quiz outcome for a sent form, translated, or null.
     *
     * @param array  $model  Model.
     * @param array  $values Answers: field ID => value.
     * @param string $lang   Language.
     * @param array  $extra  Context (today, user, query, geo).
     * @return array|null array( 'id', 'message', 'score' (when the form shows it) ).
     */
    public static function outcome( $model, $values, $lang = '', $extra = array() ) {
        if ( empty( $model['scoring']['outcomes'] ) ) {
            return null;
        }
        $outcome = BAF_Form_Model::outcome( $model, $values, $extra );
        if ( ! $outcome ) {
            return null;
        }
        $message = (string) ( $outcome['message'] ?? '' );
        $t       = $lang !== '' ? ( $model['translations'][ $lang ]['outcomes'][ $outcome['id'] ]['message'] ?? null ) : null;
        $out     = array( 'id' => $outcome['id'], 'message' => $t !== null ? (string) $t : $message );
        if ( ! empty( $model['scoring']['show_score'] ) ) {
            $out['score'] = BAF_Form_Model::score( $model, $values )['total'];
        }
        return $out;
    }

    /**
     * Whether a field was shown when the form was sent, so the server does
     * not ask for an answer the visitor never saw.
     *
     * @param array  $model  Model.
     * @param string $id     Field ID.
     * @param array  $values Answers.
     * @param array  $extra  Context.
     * @return bool Shown.
     */
    public static function was_shown( $model, $id, $values, $extra = array() ) {
        try {
            return BAF_Form_Model::is_shown( $model, $id, $values, $extra );
        } catch ( BAF_Expression_Error $e ) {
            return true;
        }
    }
}
