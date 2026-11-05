<?php
/**
 * Exports from the form model to other form builders' formats.
 *
 * The other half of QAF_Importers: Gravity Forms and WPForms JSON, a
 * Contact Form 7 template, Ninja Forms JSON, and a Google Forms resource in
 * the shape of the Forms API's forms.get (what forms.create and
 * batchUpdate take is built from it). Each export comes with a report, in
 * the same shape as an import report, of what the other builder has no
 * place for, so nothing is left out without saying so.
 *
 * Every export reads back through QAF_Importers to the same questions,
 * labels, choices and (where the builder has them) conditions; the tests
 * check that.
 *
 * No WordPress functions are used, so the tests run without WordPress.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-qaf-form-model.php';
require_once __DIR__ . '/class-qaf-import-report.php';

/**
 * QAF_Exporters class.
 */
class QAF_Exporters {

    /**
     * Builders a form can be exported to: key => name.
     *
     * @var array
     */
    const TARGETS = array(
        'gravity-forms'  => 'Gravity Forms',
        'wpforms'        => 'WPForms',
        'contact-form-7' => 'Contact Form 7',
        'ninja-forms'    => 'Ninja Forms',
        'google-forms'   => 'Google Forms',
    );

    /**
     * Comparison operators a simple condition may use.
     *
     * @var array
     */
    const SIMPLE_OPS = array( 'is', 'is_not', 'gt', 'lt', 'contains', 'not_contains', 'starts_with', 'ends_with', 'empty', 'not_empty' );

    /**
     * Gives the choices of one of QAF's built-in libraries (countries, months
     * and so on): function ( string $name ) returning a list of
     * array( 'value' => ..., 'label' => ... ), or an empty list. The plugin
     * sets it from QAF_Choice_Libraries; without it, such a field is
     * reported and written without choices.
     *
     * @var callable|null
     */
    public static $library_resolver = null;

    /**
     * Export a model.
     *
     * @param string $target One of TARGETS.
     * @param array  $model  Model.
     * @return array Keys: data (array, or the template text for Contact Form 7),
     *               text (what to save), filename, report (QAF_Import_Report).
     * @throws InvalidArgumentException For an unknown target.
     */
    public static function export( $target, $model ) {
        switch ( $target ) {
            case 'gravity-forms':
                $out = self::gravity_forms( $model );
                break;
            case 'wpforms':
                $out = self::wpforms( $model );
                break;
            case 'contact-form-7':
                $out = self::contact_form_7( $model );
                break;
            case 'ninja-forms':
                $out = self::ninja_forms( $model );
                break;
            case 'google-forms':
                $out = self::google_forms( $model );
                break;
            default:
                throw new InvalidArgumentException( 'QAF cannot export to "' . $target . '".' );
        }
        $out['report']->direction = 'export';
        $slug = QAF_Form_Model::slug( (string) ( $model['title'] ?? '' ) );
        $slug = $slug !== '' ? $slug : 'form';
        if ( is_string( $out['data'] ) ) {
            $out['text']     = $out['data'];
            $out['filename'] = $slug . '-' . $target . '.txt';
        } else {
            $out['text']     = json_encode( $out['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
            $out['filename'] = $slug . '-' . $target . '.json';
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Shared
    // ---------------------------------------------------------------------

    /**
     * What every builder lacks: rules, outcomes, translations, extensions.
     *
     * @param array             $model  Model.
     * @param QAF_Import_Report $report Report.
     * @param array             $has    Which of 'rules', 'scoring', 'translations' this builder carries.
     */
    private static function report_form_level( $model, $report, $has = array() ) {
        if ( ! empty( $model['rules'] ) && ! in_array( 'rules', $has, true ) ) {
            foreach ( $model['rules'] as $i => $rule ) {
                $report->dropped( 'rule ' . ( $i + 1 ) . ' (' . $rule['action'] . ')', 'This builder has no rule like it.' );
            }
        }
        if ( ! empty( $model['scoring']['outcomes'] ) && ! in_array( 'scoring', $has, true ) ) {
            $report->dropped( 'scoring', 'This builder has no quiz outcomes; ' . count( $model['scoring']['outcomes'] ) . ' were left out.' );
        }
        if ( ! empty( $model['translations'] ) ) {
            $report->dropped( 'translations', 'This builder keeps one language; the translations (' . implode( ', ', array_keys( $model['translations'] ) ) . ') were left out.' );
        }
        if ( ! empty( $model['choice_libraries'] ) ) {
            $report->changed( 'choice libraries', 'The form\'s own libraries were written out as each field\'s choices.' );
        }
        foreach ( $model['notifications'] ?? array() as $n ) {
            $report->dropped( 'notification ' . $n['id'], 'Set up email notifications again in the other builder.' );
        }
    }

    /**
     * The fields of a model in order, with sections and columns opened up.
     *
     * @param array $items Items.
     * @param array $out   Accumulator: array( 'item' => ..., 'section' => bool ).
     */
    private static function flatten( $items, &$out ) {
        foreach ( $items as $item ) {
            if ( $item['type'] === 'columns' ) {
                foreach ( $item['columns'] as $col ) {
                    self::flatten( $col['items'], $out );
                }
                continue;
            }
            $out[] = $item;
            if ( $item['type'] === 'section' ) {
                self::flatten( $item['items'], $out );
            }
        }
    }

    /**
     * Pages, each with its items flattened.
     *
     * @param array $model Model.
     * @return array List of array( 'page' => ..., 'items' => [...] ).
     */
    private static function pages( $model ) {
        $out = array();
        foreach ( $model['pages'] as $page ) {
            $items = array();
            self::flatten( $page['items'], $items );
            $out[] = array( 'page' => $page, 'items' => $items );
        }
        return $out;
    }

    /**
     * A field's choices: its own, or its library's from the model.
     *
     * @param array $model Model.
     * @param array $item  Item.
     * @param QAF_Import_Report $report Report.
     * @param string $where Where.
     * @return array Choices.
     */
    private static function choices( $model, $item, $report, $where ) {
        if ( isset( $item['choices'] ) ) {
            return $item['choices'];
        }
        if ( isset( $item['choice_library'] ) ) {
            if ( isset( $model['choice_libraries'][ $item['choice_library'] ] ) ) {
                return $model['choice_libraries'][ $item['choice_library'] ];
            }
            if ( is_callable( self::$library_resolver ) ) {
                $found = call_user_func( self::$library_resolver, (string) $item['choice_library'] );
                if ( $found ) {
                    $report->changed( $where, 'Its choices come from QAF\'s "' . $item['choice_library'] . '" library; they were written out.' );
                    return $found;
                }
            }
            $report->dropped( $where, 'Its choices come from QAF\'s "' . $item['choice_library'] . '" library; add them in the other builder.' );
        }
        return array();
    }

    /**
     * A condition as groups of simple tests: an "or" of "and"s.
     *
     * @param string $when Expression.
     * @return array|null array( 'hide' => bool, 'groups' => [[array( field, op, value )]] ), or null when it is not that simple.
     */
    public static function simple_condition( $when ) {
        try {
            $node = QAF_Expression::parse( $when );
        } catch ( QAF_Expression_Error $e ) {
            return null;
        }
        $hide = false;
        if ( $node['type'] === 'op' && $node['op'] === 'not' ) {
            $hide = true;
            $node = $node['args'][0];
        }
        $test = function ( $n ) {
            if ( ( $n['type'] ?? '' ) !== 'op' || ! in_array( $n['op'], self::SIMPLE_OPS, true ) || ( $n['args'][0]['type'] ?? '' ) !== 'ref' ) {
                return null;
            }
            if ( in_array( $n['op'], array( 'empty', 'not_empty' ), true ) ) {
                return array( $n['args'][0]['field'], $n['op'], '' );
            }
            $r = $n['args'][1];
            if ( $r['type'] !== 'literal' || ! is_scalar( $r['value'] ) || is_bool( $r['value'] ) ) {
                return null;
            }
            return array( $n['args'][0]['field'], $n['op'], is_string( $r['value'] ) ? $r['value'] : QAF_Expression::literal_text( $r['value'] ) );
        };
        $group = function ( $n ) use ( $test ) {
            $parts = ( $n['type'] === 'op' && $n['op'] === 'and' ) ? $n['args'] : array( $n );
            $out   = array();
            foreach ( $parts as $p ) {
                $t = $test( $p );
                if ( $t === null ) {
                    return null;
                }
                $out[] = $t;
            }
            return $out;
        };
        $groups = array();
        foreach ( ( $node['type'] === 'op' && $node['op'] === 'or' ) ? $node['args'] : array( $node ) as $n ) {
            $g = $group( $n );
            if ( $g === null ) {
                return null;
            }
            $groups[] = $g;
        }
        return array( 'hide' => $hide, 'groups' => $groups );
    }

    /**
     * A condition with one level of all/any, as Gravity Forms holds it.
     *
     * @param array|null $c From simple_condition().
     * @return array|null array( 'hide', 'join' => all|any, 'tests' ), or null.
     */
    private static function one_level( $c ) {
        if ( $c === null ) {
            return null;
        }
        if ( count( $c['groups'] ) === 1 ) {
            return array( 'hide' => $c['hide'], 'join' => 'all', 'tests' => $c['groups'][0] );
        }
        $tests = array();
        foreach ( $c['groups'] as $g ) {
            if ( count( $g ) !== 1 ) {
                return null;
            }
            $tests[] = $g[0];
        }
        return array( 'hide' => $c['hide'], 'join' => 'any', 'tests' => $tests );
    }

    /**
     * Report what a field has that no builder here carries.
     *
     * @param array             $item   Item.
     * @param QAF_Import_Report $report Report.
     * @param string            $where  Where.
     * @param array             $has    Keys this builder carries.
     */
    private static function report_item( $item, $report, $where, $has ) {
        $names = array(
            'when'          => 'Its condition',
            'calculation'   => 'Its calculation',
            'smart_default' => 'Its smart default',
            'mask'          => 'Its input mask',
            'validation'    => 'Its validation',
            'scoring'       => 'Its quiz scoring',
            'cascade'       => 'Its cascade',
            'attributes'    => 'Its HTML attributes',
        );
        foreach ( $names as $k => $what ) {
            if ( isset( $item[ $k ] ) && ! in_array( $k, $has, true ) ) {
                $report->dropped( $where, $what . ' has no place in this builder.' );
            }
        }
    }

    /**
     * Where a field is, for the report.
     *
     * @param array $item Item.
     * @return string Where.
     */
    private static function where( $item ) {
        return 'field ' . $item['id'] . ( isset( $item['label'] ) && $item['label'] !== '' ? ' (' . $item['label'] . ')' : '' );
    }

    // ---------------------------------------------------------------------
    // Gravity Forms
    // ---------------------------------------------------------------------

    /**
     * Gravity Forms' export: { "0": form, "version": ... }.
     *
     * @param array $model Model.
     * @return array data, report.
     */
    public static function gravity_forms( $model ) {
        $report = new QAF_Import_Report( 'Gravity Forms' );
        $types  = array(
            'text' => 'text', 'textarea' => 'textarea', 'select' => 'select', 'multiselect' => 'multiselect',
            'number' => 'number', 'checkbox' => 'checkbox', 'radio' => 'radio', 'hidden' => 'hidden', 'html' => 'html',
            'section' => 'section', 'name' => 'name', 'date' => 'date', 'time' => 'time', 'phone' => 'phone',
            'address' => 'address', 'website' => 'website', 'email' => 'email', 'fileupload' => 'fileupload',
            'captcha' => 'captcha', 'table' => 'list', 'consent' => 'consent', 'password' => 'password',
            'post_title' => 'post_title', 'post_content' => 'post_content', 'post_excerpt' => 'post_excerpt',
            'post_tags' => 'post_tags', 'post_category' => 'post_category', 'post_image' => 'post_image',
            'post_custom' => 'post_custom_field', 'product' => 'product', 'quantity' => 'quantity', 'total' => 'total',
            'payment' => 'creditcard', 'signature' => 'signature', 'user_login' => 'username', 'quiz' => 'quiz',
            'calculation' => 'number',
        );
        $ops    = array( 'is' => 'is', 'is_not' => 'isnot', 'gt' => '>', 'lt' => '<', 'contains' => 'contains', 'starts_with' => 'starts_with', 'ends_with' => 'ends_with', 'empty' => 'is', 'not_empty' => 'isnot' );

        $pages  = self::pages( $model );
        list( $gid, $breaks ) = self::numbers( $pages, $types );
        $logic = function ( $when, $where ) use ( $gid, $ops, $report ) {
            $c = self::one_level( self::simple_condition( $when ) );
            if ( $c !== null ) {
                foreach ( $c['tests'] as $t ) {
                    if ( ! isset( $gid[ $t[0] ] ) || ! isset( $ops[ $t[1] ] ) ) {
                        $c = null;
                        break;
                    }
                }
            }
            if ( $c === null ) {
                $report->dropped( $where, 'Its condition "' . $when . '" is more than Gravity Forms\' one list of tests can say.' );
                return null;
            }
            $rules = array();
            foreach ( $c['tests'] as $t ) {
                $rules[] = array( 'fieldId' => (string) $gid[ $t[0] ], 'operator' => $ops[ $t[1] ], 'value' => $t[2] );
            }
            return array( 'actionType' => $c['hide'] ? 'hide' : 'show', 'logicType' => $c['join'], 'rules' => $rules );
        };
        $labels  = QAF_Form_Model::labels( $model );
        $formula = function ( $text, $where ) use ( $gid, $labels, $report ) {
            try {
                $node = QAF_Expression::parse( $text );
            } catch ( QAF_Expression_Error $e ) {
                $node = null;
            }
            $ok   = true;
            $walk = function ( $n ) use ( &$walk, &$ok, $gid, $labels ) {
                if ( $n['type'] === 'ref' ) {
                    if ( ! isset( $gid[ $n['field'] ] ) ) {
                        $ok = false;
                        return '';
                    }
                    return '{' . str_replace( array( '{', '}', ':' ), '', (string) ( $labels[ $n['field'] ] ?? '' ) ) . ':' . $gid[ $n['field'] ] . '}';
                }
                if ( $n['type'] === 'literal' && ( is_int( $n['value'] ) || is_float( $n['value'] ) ) ) {
                    return QAF_Expression::literal_text( $n['value'] );
                }
                $sym = array( 'add' => '+', 'sub' => '-', 'mul' => '*', 'div' => '/' );
                if ( $n['type'] === 'op' && isset( $sym[ $n['op'] ] ) ) {
                    return '( ' . $walk( $n['args'][0] ) . ' ' . $sym[ $n['op'] ] . ' ' . $walk( $n['args'][1] ) . ' )';
                }
                $ok = false;
                return '';
            };
            $out = $node ? $walk( $node ) : '';
            if ( ! $ok || $node === null ) {
                $report->dropped( $where, 'Its calculation "' . $text . '" uses more than Gravity Forms\' + - * / and fields.' );
                return null;
            }
            return $out;
        };

        $fields = array();
        $titles = array();
        foreach ( $pages as $pi => $p ) {
            $page     = $p['page'];
            $titles[] = (string) ( $page['title'] ?? '' );
            if ( $pi > 0 ) {
                $break = array( 'type' => 'page' );
                $prev  = $pages[ $pi - 1 ]['page'];
                if ( isset( $prev['props']['next_button_text'] ) ) {
                    $break['nextButton'] = array( 'type' => 'text', 'text' => (string) $prev['props']['next_button_text'] );
                }
                if ( isset( $page['when'] ) ) {
                    $l = $logic( $page['when'], 'page ' . $page['id'] );
                    if ( $l ) {
                        $break['conditionalLogic'] = $l;
                    }
                }
                $break['id'] = $breaks[ $pi ];
                $fields[]    = $break;
            } elseif ( isset( $page['when'] ) ) {
                $report->dropped( 'page ' . $page['id'], 'Gravity Forms cannot skip its first page.' );
            }
            foreach ( $p['items'] as $item ) {
                $where = self::where( $item );
                if ( ! isset( $types[ $item['type'] ] ) ) {
                    $report->dropped( $where, 'Gravity Forms has no field like QAF\'s "' . $item['type'] . '"; it was left out.' );
                    continue;
                }
                $f = array( 'id' => $gid[ $item['id'] ], 'type' => $types[ $item['type'] ] );
                if ( isset( $item['label'] ) ) {
                    $f['label'] = (string) $item['label'];
                }
                if ( $item['type'] === 'html' && isset( $item['props']['content'] ) ) {
                    $f['content'] = (string) $item['props']['content'];
                }
                if ( isset( $item['help'] ) ) {
                    $f['description'] = (string) $item['help'];
                }
                if ( isset( $item['placeholder'] ) ) {
                    $f['placeholder'] = (string) $item['placeholder'];
                }
                if ( ! empty( $item['required'] ) ) {
                    $f['isRequired'] = true;
                }
                if ( isset( $item['default'] ) && is_scalar( $item['default'] ) ) {
                    $f['defaultValue'] = (string) $item['default'];
                }
                if ( isset( $item['mask'] ) ) {
                    $f['inputMask']      = true;
                    $f['inputMaskValue'] = (string) $item['mask'];
                }
                $choices = self::choices( $model, $item, $report, $where );
                if ( $choices ) {
                    $f['choices'] = array();
                    foreach ( $choices as $c ) {
                        $gc = array( 'text' => (string) $c['label'], 'value' => (string) $c['value'] );
                        if ( ! empty( $c['selected'] ) ) {
                            $gc['isSelected'] = true;
                        }
                        if ( isset( $c['price'] ) ) {
                            $gc['price'] = QAF_Expression::literal_text( $c['price'] );
                        }
                        $f['choices'][] = $gc;
                    }
                }
                if ( isset( $item['when'] ) ) {
                    $l = $logic( $item['when'], $where );
                    if ( $l ) {
                        $f['conditionalLogic'] = $l;
                    }
                }
                if ( isset( $item['calculation'] ) ) {
                    $calc = $formula( $item['calculation'], $where );
                    if ( $calc !== null ) {
                        $f['enableCalculation']  = true;
                        $f['calculationFormula'] = $calc;
                    }
                }
                self::report_item( $item, $report, $where, array( 'when', 'calculation', 'mask' ) );
                $report->carried++;
                $fields[] = $f;
            }
        }
        self::report_form_level( $model, $report );
        $form = array( 'title' => (string) $model['title'], 'fields' => $fields );
        if ( isset( $model['description'] ) ) {
            $form['description'] = (string) $model['description'];
        }
        if ( isset( $model['settings']['submit_label'] ) ) {
            $form['button'] = array( 'type' => 'text', 'text' => (string) $model['settings']['submit_label'] );
        }
        if ( count( $pages ) > 1 ) {
            $form['pagination'] = array( 'type' => 'steps', 'pages' => $titles );
        }
        return array( 'data' => array( '0' => $form, 'version' => '2.8' ), 'report' => $report );
    }

    /**
     * Numeric IDs, in form order, for the fields a builder takes and for the
     * page break before each page after the first.
     *
     * @param array $pages Pages from pages().
     * @param array $types QAF type => builder type.
     * @return array array( model ID => number, page index => break number ).
     */
    private static function numbers( $pages, $types ) {
        $ids    = array();
        $breaks = array();
        $n      = 0;
        foreach ( $pages as $pi => $p ) {
            if ( $pi > 0 ) {
                $breaks[ $pi ] = ++$n;
            }
            foreach ( $p['items'] as $item ) {
                if ( isset( $types[ $item['type'] ] ) ) {
                    $ids[ $item['id'] ] = ++$n;
                }
            }
        }
        return array( $ids, $breaks );
    }

    // ---------------------------------------------------------------------
    // WPForms
    // ---------------------------------------------------------------------

    /**
     * WPForms' export: one form, fields keyed by ID.
     *
     * @param array $model Model.
     * @return array data, report.
     */
    public static function wpforms( $model ) {
        $report = new QAF_Import_Report( 'WPForms' );
        $types  = array(
            'text' => 'text', 'textarea' => 'textarea', 'select' => 'select', 'radio' => 'radio', 'checkbox' => 'checkbox',
            'number' => 'number', 'name' => 'name', 'email' => 'email', 'slider' => 'number-slider', 'phone' => 'phone',
            'address' => 'address', 'datetime' => 'date-time', 'date' => 'date-time', 'website' => 'url', 'fileupload' => 'file-upload',
            'password' => 'password', 'section' => 'divider', 'html' => 'html', 'rating' => 'rating',
            'hidden' => 'hidden', 'captcha' => 'captcha', 'likert' => 'likert_scale', 'nps' => 'net_promoter_score',
            'signature' => 'signature', 'price' => 'payment-single', 'product' => 'payment-multiple', 'total' => 'payment-total',
            'rich_text' => 'richtext', 'summary' => 'entry-preview', 'consent' => 'gdpr-checkbox', 'payment' => 'stripe-credit-card',
        );
        $ops    = array( 'is' => '==', 'is_not' => '!=', 'empty' => 'e', 'not_empty' => '!e', 'contains' => 'c', 'not_contains' => '!c', 'starts_with' => '^', 'ends_with' => '~', 'gt' => '>', 'lt' => '<' );
        $pages  = self::pages( $model );

        list( $wid, $breaks ) = self::numbers( $pages, $types );
        $n    = $wid || $breaks ? max( array_merge( array_values( $wid ), array_values( $breaks ) ) ) : 0;
        $keys = array();
        foreach ( $pages as $p ) {
            foreach ( $p['items'] as $item ) {
                if ( isset( $wid[ $item['id'] ] ) ) {
                    foreach ( self::choices( $model, $item, new QAF_Import_Report( '' ), '' ) as $k => $c ) {
                        $keys[ $item['id'] ][ (string) $c['value'] ] = (string) ( $k + 1 );
                    }
                }
            }
        }
        $logic = function ( $when, $where ) use ( $wid, $ops, $keys, $report ) {
            $c = self::simple_condition( $when );
            if ( $c !== null ) {
                foreach ( $c['groups'] as $g ) {
                    foreach ( $g as $t ) {
                        if ( ! isset( $wid[ $t[0] ] ) ) {
                            $c = null;
                            break 2;
                        }
                    }
                }
            }
            if ( $c === null ) {
                $report->dropped( $where, 'Its condition "' . $when . '" is more than WPForms\' groups of tests can say.' );
                return null;
            }
            $groups = array();
            foreach ( $c['groups'] as $g ) {
                $rules = array();
                foreach ( $g as $t ) {
                    $value   = $keys[ $t[0] ][ $t[2] ] ?? $t[2];
                    $rules[] = array( 'field' => (string) $wid[ $t[0] ], 'operator' => $ops[ $t[1] ], 'value' => $value );
                }
                $groups[] = $rules;
            }
            return array( 'conditional_logic' => '1', 'conditional_type' => $c['hide'] ? 'hide' : 'show', 'conditionals' => $groups );
        };

        $fields = array();
        foreach ( $pages as $pi => $p ) {
            $page = $p['page'];
            if ( $pi > 0 ) {
                $id    = $breaks[ $pi ];
                $break = array( 'id' => (string) $id, 'type' => 'pagebreak', 'title' => (string) ( $page['title'] ?? '' ) );
                $prev  = $pages[ $pi - 1 ]['page'];
                if ( isset( $prev['props']['next_button_text'] ) ) {
                    $break['next'] = (string) $prev['props']['next_button_text'];
                }
                $fields[ (string) $id ] = $break;
            }
            if ( isset( $page['when'] ) ) {
                $report->dropped( 'page ' . $page['id'], 'WPForms cannot skip a page.' );
            }
            foreach ( $p['items'] as $item ) {
                $where = self::where( $item );
                if ( ! isset( $types[ $item['type'] ] ) ) {
                    $report->dropped( $where, 'WPForms has no field like QAF\'s "' . $item['type'] . '"; it was left out.' );
                    continue;
                }
                $id = (string) $wid[ $item['id'] ];
                $f  = array( 'id' => $id, 'type' => $types[ $item['type'] ] );
                if ( $item['type'] === 'date' ) {
                    $f['format'] = 'date';
                }
                if ( isset( $item['label'] ) ) {
                    $f['label'] = (string) $item['label'];
                }
                if ( $item['type'] === 'html' && isset( $item['props']['content'] ) ) {
                    $f['code'] = (string) $item['props']['content'];
                }
                if ( isset( $item['help'] ) ) {
                    $f['description'] = (string) $item['help'];
                }
                if ( isset( $item['placeholder'] ) ) {
                    $f['placeholder'] = (string) $item['placeholder'];
                }
                if ( ! empty( $item['required'] ) ) {
                    $f['required'] = '1';
                }
                if ( isset( $item['default'] ) && is_scalar( $item['default'] ) ) {
                    $f['default_value'] = (string) $item['default'];
                }
                $choices = self::choices( $model, $item, $report, $where );
                if ( $choices ) {
                    $f['choices'] = array();
                    foreach ( $choices as $k => $c ) {
                        $wc = array( 'label' => (string) $c['label'], 'value' => (string) $c['value'] );
                        if ( ! empty( $c['selected'] ) ) {
                            $wc['default'] = '1';
                        }
                        if ( isset( $c['price'] ) ) {
                            $wc['value'] = QAF_Expression::literal_text( $c['price'] );
                            $report->changed( $where, 'WPForms keeps a priced choice\'s price where its value would be.' );
                        }
                        $f['choices'][ (string) ( $k + 1 ) ] = $wc;
                    }
                }
                if ( isset( $item['when'] ) ) {
                    $l = $logic( $item['when'], $where );
                    if ( $l ) {
                        $f = array_merge( $f, $l );
                    }
                }
                self::report_item( $item, $report, $where, array( 'when' ) );
                $report->carried++;
                $fields[ $id ] = $f;
            }
        }
        self::report_form_level( $model, $report );
        $settings = array( 'form_title' => (string) $model['title'] );
        if ( isset( $model['description'] ) ) {
            $settings['form_desc'] = (string) $model['description'];
        }
        if ( isset( $model['settings']['submit_label'] ) ) {
            $settings['submit_text'] = (string) $model['settings']['submit_label'];
        }
        return array( 'data' => array( 'field_id' => $n + 1, 'fields' => $fields, 'settings' => $settings ), 'report' => $report );
    }

    // ---------------------------------------------------------------------
    // Contact Form 7
    // ---------------------------------------------------------------------

    /**
     * A Contact Form 7 form template.
     *
     * @param array $model Model.
     * @return array data (template text), report.
     */
    public static function contact_form_7( $model ) {
        $report = new QAF_Import_Report( 'Contact Form 7' );
        $types  = array(
            'text' => 'text', 'email' => 'email', 'website' => 'url', 'phone' => 'tel', 'number' => 'number',
            'range' => 'range', 'date' => 'date', 'textarea' => 'textarea', 'select' => 'select', 'multiselect' => 'select',
            'checkbox' => 'checkbox', 'radio' => 'radio', 'consent' => 'acceptance', 'fileupload' => 'file', 'hidden' => 'hidden',
        );
        $q      = function ( $s ) {
            return '"' . str_replace( array( '"', ']' ), array( '\'', ')' ), (string) $s ) . '"';
        };
        $esc    = function ( $s ) {
            return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
        };
        $blocks = array();
        $pages  = self::pages( $model );
        if ( count( $pages ) > 1 ) {
            $report->changed( 'pages', 'Contact Form 7 has one page; the ' . count( $pages ) . ' pages follow one another, each title a heading.' );
        }
        foreach ( $pages as $pi => $p ) {
            if ( count( $pages ) > 1 && ( $p['page']['title'] ?? '' ) !== '' ) {
                $blocks[] = '<h2>' . $esc( $p['page']['title'] ) . '</h2>';
            }
            if ( isset( $p['page']['when'] ) ) {
                $report->dropped( 'page ' . $p['page']['id'], 'Contact Form 7 has no conditions.' );
            }
            foreach ( $p['items'] as $item ) {
                $where = self::where( $item );
                if ( $item['type'] === 'section' ) {
                    $blocks[] = '<h3>' . $esc( $item['label'] ?? '' ) . '</h3>';
                    $report->changed( $where, 'The section is a heading.' );
                    self::report_item( $item, $report, $where, array() );
                    continue;
                }
                if ( $item['type'] === 'html' ) {
                    $blocks[] = (string) ( $item['props']['content'] ?? '' );
                    $report->carried++;
                    continue;
                }
                if ( ! isset( $types[ $item['type'] ] ) ) {
                    $report->dropped( $where, 'Contact Form 7 has no field like QAF\'s "' . $item['type'] . '"; it was left out.' );
                    continue;
                }
                $type = $types[ $item['type'] ];
                $name = preg_replace( '/[^A-Za-z0-9_\-]/', '-', $item['id'] );
                $tag  = $type . ( ! empty( $item['required'] ) && ! in_array( $type, array( 'acceptance', 'hidden' ), true ) ? '*' : '' ) . ' ' . $name;
                if ( $item['type'] === 'multiselect' ) {
                    $tag .= ' multiple';
                }
                if ( $type === 'acceptance' && empty( $item['required'] ) ) {
                    $tag .= ' optional';
                }
                if ( isset( $item['placeholder'] ) ) {
                    $tag .= ' placeholder ' . $q( $item['placeholder'] );
                } elseif ( isset( $item['default'] ) && is_scalar( $item['default'] ) && $type !== 'acceptance' ) {
                    $tag .= ' ' . $q( $item['default'] );
                }
                $choices = in_array( $type, array( 'select', 'checkbox', 'radio' ), true ) ? self::choices( $model, $item, $report, $where ) : array();
                $selected = array();
                foreach ( $choices as $k => $c ) {
                    $tag .= ' ' . $q( (string) $c['label'] === (string) $c['value'] ? $c['label'] : $c['label'] . '|' . $c['value'] );
                    if ( ! empty( $c['selected'] ) ) {
                        $selected[] = $k + 1;
                    }
                    if ( isset( $c['price'] ) ) {
                        $report->dropped( $where, 'Contact Form 7 has no prices; the price of "' . $c['label'] . '" was left out.' );
                    }
                }
                if ( $selected ) {
                    $tag .= ' default:' . implode( '_', $selected );
                }
                if ( $type === 'hidden' ) {
                    $line = '[' . $tag . ']';
                } else {
                    $line = '<label> ' . $esc( $item['label'] ?? '' ) . "\n    [" . $tag . '] </label>';
                }
                if ( isset( $item['help'] ) ) {
                    $report->changed( $where, 'Its help text is a line after it.' );
                    $line .= "\n" . '<small>' . $esc( $item['help'] ) . '</small>';
                }
                self::report_item( $item, $report, $where, array() );
                $report->carried++;
                $blocks[] = $line;
            }
        }
        $blocks[] = '[submit ' . $q( $model['settings']['submit_label'] ?? 'Submit' ) . ']';
        self::report_form_level( $model, $report );
        return array( 'data' => implode( "\n\n", $blocks ) . "\n", 'report' => $report );
    }

    // ---------------------------------------------------------------------
    // Ninja Forms
    // ---------------------------------------------------------------------

    /**
     * Ninja Forms' export: settings, fields in order, actions.
     *
     * @param array $model Model.
     * @return array data, report.
     */
    public static function ninja_forms( $model ) {
        $report = new QAF_Import_Report( 'Ninja Forms' );
        $types  = array(
            'text' => 'textbox', 'textarea' => 'textarea', 'email' => 'email', 'phone' => 'phone', 'number' => 'number',
            'date' => 'date', 'select' => 'listselect', 'radio' => 'listradio', 'checkbox' => 'listcheckbox',
            'multiselect' => 'listmultiselect', 'hidden' => 'hidden', 'html' => 'html', 'rating' => 'starrating',
            'captcha' => 'recaptcha', 'password' => 'password', 'product' => 'product', 'quantity' => 'quantity',
            'total' => 'total', 'save' => 'save', 'signature' => 'signature', 'fileupload' => 'file_upload',
        );
        $pages  = self::pages( $model );
        if ( count( $pages ) > 1 ) {
            $report->changed( 'pages', 'Ninja Forms needs its Multi-Part add-on for pages; the ' . count( $pages ) . ' pages follow one another.' );
        }
        $fields = array();
        $order  = 0;
        foreach ( $pages as $p ) {
            if ( isset( $p['page']['when'] ) ) {
                $report->dropped( 'page ' . $p['page']['id'], 'Ninja Forms conditions need its Conditional Logic add-on, whose format QAF does not write.' );
            }
            foreach ( $p['items'] as $item ) {
                $where = self::where( $item );
                if ( $item['type'] === 'section' ) {
                    $fields[] = array( 'type' => 'html', 'key' => $item['id'], 'label' => (string) ( $item['label'] ?? '' ), 'default' => '<h3>' . htmlspecialchars( (string) ( $item['label'] ?? '' ), ENT_QUOTES ) . '</h3>', 'order' => ++$order );
                    $report->changed( $where, 'The section is a heading.' );
                    continue;
                }
                $type = $types[ $item['type'] ] ?? null;
                if ( $type === null ) {
                    $report->dropped( $where, 'Ninja Forms has no field like QAF\'s "' . $item['type'] . '"; it was left out.' );
                    continue;
                }
                $choices = self::choices( $model, $item, $report, $where );
                if ( $item['type'] === 'checkbox' && count( $choices ) === 1 ) {
                    $type    = 'checkbox';
                    $choices = array();
                }
                $f = array( 'type' => $type, 'key' => $item['id'], 'label' => (string) ( $item['label'] ?? '' ), 'order' => ++$order, 'required' => ! empty( $item['required'] ) ? 1 : 0 );
                if ( $item['type'] === 'html' ) {
                    $f['default'] = (string) ( $item['props']['content'] ?? '' );
                    unset( $f['required'] );
                }
                if ( isset( $item['help'] ) ) {
                    $f['desc_text'] = (string) $item['help'];
                }
                if ( isset( $item['placeholder'] ) ) {
                    $f['placeholder'] = (string) $item['placeholder'];
                }
                if ( isset( $item['default'] ) && is_scalar( $item['default'] ) && $item['type'] !== 'html' ) {
                    $f['default'] = (string) $item['default'];
                }
                if ( isset( $item['mask'] ) ) {
                    $f['mask']        = 'custom';
                    $f['custom_mask'] = (string) $item['mask'];
                }
                if ( $choices ) {
                    $f['options'] = array();
                    foreach ( $choices as $k => $c ) {
                        $o = array( 'label' => (string) $c['label'], 'value' => (string) $c['value'], 'order' => $k );
                        if ( ! empty( $c['selected'] ) ) {
                            $o['selected'] = 1;
                        }
                        if ( isset( $c['price'] ) ) {
                            $o['calc'] = $c['price'];
                        }
                        $f['options'][] = $o;
                    }
                }
                if ( isset( $item['when'] ) ) {
                    $report->dropped( $where, 'Ninja Forms conditions need its Conditional Logic add-on, whose format QAF does not write.' );
                }
                self::report_item( $item, $report, $where, array( 'when', 'mask' ) );
                $report->carried++;
                $fields[] = $f;
            }
        }
        $fields[] = array( 'type' => 'submit', 'key' => 'submit', 'label' => (string) ( $model['settings']['submit_label'] ?? 'Submit' ), 'order' => ++$order );
        self::report_form_level( $model, $report );
        return array( 'data' => array( 'settings' => array( 'title' => (string) $model['title'] ), 'fields' => $fields, 'actions' => array() ), 'report' => $report );
    }

    // ---------------------------------------------------------------------
    // Google Forms
    // ---------------------------------------------------------------------

    /**
     * A Google Forms resource, shaped as forms.get returns it.
     *
     * @param array $model Model.
     * @return array data, report.
     */
    public static function google_forms( $model ) {
        $report  = new QAF_Import_Report( 'Google Forms' );
        $items   = array();
        $is_quiz = false;
        $pages   = self::pages( $model );
        foreach ( $pages as $pi => $p ) {
            $page = $p['page'];
            if ( $pi > 0 ) {
                $break = array( 'itemId' => 'page_' . $page['id'], 'title' => (string) ( $page['title'] ?? '' ), 'pageBreakItem' => new stdClass() );
                if ( isset( $page['description'] ) ) {
                    $break['description'] = (string) $page['description'];
                }
                $items[] = $break;
            }
            if ( isset( $page['when'] ) ) {
                $report->dropped( 'page ' . $page['id'], 'Google Forms goes to a section from a choice, not on a condition; set up the branching in Google Forms.' );
            }
            foreach ( $p['items'] as $item ) {
                $where = self::where( $item );
                $g     = array( 'itemId' => $item['id'], 'title' => (string) ( $item['label'] ?? '' ) );
                if ( isset( $item['help'] ) ) {
                    $g['description'] = (string) $item['help'];
                }
                $question = array();
                $type     = $item['type'];
                if ( $type === 'section' || $type === 'html' ) {
                    if ( $type === 'html' ) {
                        $g['description'] = trim( html_entity_decode( strip_tags( (string) ( $item['props']['content'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
                    }
                    $g['textItem'] = new stdClass();
                    if ( $type === 'section' ) {
                        $report->changed( $where, 'The section is a title and description; its questions follow it.' );
                    }
                    if ( isset( $item['when'] ) ) {
                        $report->dropped( $where, 'Google Forms has no conditions on items.' );
                    }
                    $report->carried++;
                    $items[] = $g;
                    continue;
                }
                if ( in_array( $type, array( 'text', 'email', 'phone', 'website', 'number', 'name', 'address', 'currency' ), true ) ) {
                    $question['textQuestion'] = array( 'paragraph' => false );
                    if ( $type !== 'text' ) {
                        $report->changed( $where, 'Google Forms has no "' . $type . '" question; it is a short answer.' );
                    }
                } elseif ( in_array( $type, array( 'textarea', 'rich_text' ), true ) ) {
                    $question['textQuestion'] = array( 'paragraph' => true );
                } elseif ( in_array( $type, array( 'radio', 'checkbox', 'select', 'quiz', 'multiselect' ), true ) ) {
                    $kind    = array( 'radio' => 'RADIO', 'checkbox' => 'CHECKBOX', 'select' => 'DROP_DOWN', 'quiz' => 'RADIO', 'multiselect' => 'CHECKBOX' )[ $type ];
                    if ( $type === 'quiz' && ( $item['props']['question_type'] ?? '' ) === 'multiple_answer' ) {
                        $kind = 'CHECKBOX';
                    }
                    $options = array();
                    foreach ( self::choices( $model, $item, $report, $where ) as $c ) {
                        $options[] = array( 'value' => (string) $c['label'] );
                        if ( (string) $c['value'] !== QAF_Form_Model::slug( (string) $c['label'] ) && (string) $c['value'] !== (string) $c['label'] ) {
                            $report->changed( $where, 'Google Forms stores the label "' . $c['label'] . '" as the answer, not "' . $c['value'] . '".' );
                        }
                    }
                    $question['choiceQuestion'] = array( 'type' => $kind, 'options' => $options );
                } elseif ( in_array( $type, array( 'date', 'datetime' ), true ) ) {
                    $question['dateQuestion'] = array( 'includeTime' => $type === 'datetime', 'includeYear' => true );
                } elseif ( $type === 'time' ) {
                    $question['timeQuestion'] = array( 'duration' => false );
                } elseif ( in_array( $type, array( 'rating', 'range', 'slider', 'nps' ), true ) ) {
                    $low  = $type === 'nps' ? 0 : (int) ( $item['validation']['min'] ?? 1 );
                    $high = $type === 'nps' ? 10 : (int) ( $item['validation']['max'] ?? ( $item['props']['max'] ?? 5 ) );
                    $question['scaleQuestion'] = array( 'low' => $low, 'high' => max( $low + 1, min( 10, $high ) ) );
                    if ( isset( $item['props']['low_label'] ) ) {
                        $question['scaleQuestion']['lowLabel'] = (string) $item['props']['low_label'];
                    }
                    if ( isset( $item['props']['high_label'] ) ) {
                        $question['scaleQuestion']['highLabel'] = (string) $item['props']['high_label'];
                    }
                } elseif ( $type === 'fileupload' ) {
                    $report->dropped( $where, 'A Google Forms file upload is made in Google Forms, which stores the files in Drive; add it there.' );
                    continue;
                } else {
                    $report->dropped( $where, 'Google Forms has no question like QAF\'s "' . $type . '"; it was left out.' );
                    continue;
                }
                if ( ! empty( $item['required'] ) ) {
                    $question['required'] = true;
                }
                if ( isset( $item['scoring']['correct'] ) ) {
                    $is_quiz = true;
                    $labels  = array();
                    foreach ( self::choices( $model, $item, new QAF_Import_Report( '' ), '' ) as $c ) {
                        $labels[ (string) $c['value'] ] = (string) $c['label'];
                    }
                    $answers = array();
                    foreach ( $item['scoring']['correct'] as $v ) {
                        $answers[] = array( 'value' => $labels[ (string) $v ] ?? (string) $v );
                    }
                    $question['grading'] = array( 'pointValue' => (int) ( $item['scoring']['points'] ?? 1 ), 'correctAnswers' => array( 'answers' => $answers ) );
                    foreach ( array( 'feedback_correct' => 'whenRight', 'feedback_incorrect' => 'whenWrong' ) as $k => $gk ) {
                        if ( isset( $item['scoring'][ $k ] ) ) {
                            $question['grading'][ $gk ] = array( 'text' => (string) $item['scoring'][ $k ] );
                        }
                    }
                    if ( ! empty( $item['scoring']['partial'] ) ) {
                        $report->changed( $where, 'Google Forms gives no partial credit.' );
                    }
                }
                if ( isset( $item['when'] ) ) {
                    $report->dropped( $where, 'Google Forms has no conditions on questions; set up branching in Google Forms.' );
                }
                self::report_item( $item, $report, $where, array( 'when', 'scoring' ) );
                $g['questionItem'] = array( 'question' => $question );
                $report->carried++;
                $items[] = $g;
            }
        }
        self::report_form_level( $model, $report );
        $info = array( 'title' => (string) $model['title'], 'documentTitle' => (string) $model['title'] );
        if ( isset( $model['description'] ) ) {
            $info['description'] = (string) $model['description'];
        }
        $data = array( 'info' => $info, 'items' => $items );
        if ( $is_quiz ) {
            $data['settings'] = array( 'quizSettings' => array( 'isQuiz' => true ) );
        }
        return array( 'data' => $data, 'report' => $report );
    }
}
