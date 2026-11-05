<?php
/**
 * Converter between QAF's stored form (the builder format) and the model.
 *
 * QAF has stored a form as the qaf_forms columns config (a flat list of
 * fields, each naming its page, plus a list of pages), settings,
 * confirmations and notifications. The visual builder and the renderer still
 * read that shape. This converter:
 *
 * - reads any stored form, old or new, into the model (from_legacy), with an
 *   import report of anything it could not carry over;
 * - writes a model back into that shape (to_legacy) so the builder and the
 *   renderer keep working, with what they have no place for kept beside it,
 *   so from_legacy( to_legacy( $model ) ) is the same model.
 *
 * The migration it performs is documented in docs/form-model.md.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-qaf-form-model.php';
require_once __DIR__ . '/class-qaf-import-report.php';
require_once __DIR__ . '/class-qaf-bfl-reader.php';

/**
 * QAF_Form_Model_Converter class.
 */
class QAF_Form_Model_Converter {

    /**
     * Stored condition operators and the expression operators they are.
     *
     * @var array
     */
    const OPERATORS = array(
        'equals'           => 'is',
        'not_equals'       => 'is_not',
        'contains'         => 'contains',
        'not_contains'     => 'not_contains',
        'starts_with'      => 'starts_with',
        'ends_with'        => 'ends_with',
        'greater_than'     => 'gt',
        'less_than'        => 'lt',
        'greater_or_equal' => 'ge',
        'less_or_equal'    => 'le',
        'is_empty'         => 'empty',
        'is_not_empty'     => 'not_empty',
        'before'           => 'before',
        'after'            => 'after',
    );

    /**
     * Stored keys this converter reads into fixed model properties.
     *
     * @var array
     */
    const MAPPED = array(
        'id', 'type', 'label', 'description', 'required', 'placeholder', 'default_value', 'input_mask',
        'smart_default', 'smart_default_expression', 'conditional_logic', 'formula', 'calculation',
        'choices', 'options', 'choice_library', 'cascade_from', 'cascade_library', 'empty_message', 'loading_message',
        'min', 'max', 'min_length', 'max_length', 'pattern', 'custom_error_message', 'unique', 'unique_scope',
        'unique_message', 'correct_answers', 'points', 'partial_credit', 'case_sensitive', 'feedback_correct',
        'feedback_incorrect', 'feedback_partial', 'explanation', 'custom_attributes', 'page', 'fields',
        'model_items', 'when',
    );

    /**
     * Settings every QAF field class starts with (QAF_Field::get_default_config).
     * A form saved before the model stores them on every field; they say
     * nothing, so they are not carried into props.
     *
     * @var array
     */
    const BASE_DEFAULTS = array(
        'css_class' => '', 'size' => 'medium', 'label_placement' => 'inherit', 'description_placement' => 'inherit',
        'admin_only' => false, 'visibility' => 'visible', 'aria_label' => '', 'aria_describedby' => '',
        'validation_rules' => array(), 'read_only' => false, 'disabled' => false, 'allow_prefill' => true,
        'prefill_parameter' => '', 'autofocus' => false, 'spellcheck' => null, 'inputmode' => '', 'enterkeyhint' => '',
    );

    /** @var QAF_Import_Report */
    private $report;

    /** @var array IDs in use. */
    private $ids = array();

    /** @var array Data kept under extensions.qaf_legacy. */
    private $legacy = array();

    /** @var bool Whether the stored form was written by to_legacy (so "" and null mean what they say). */
    private $ours = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->report = new QAF_Import_Report( 'QAF stored form' );
    }

    /**
     * Whether a stored key holds a value worth carrying. Forms QAF wrote
     * before the model store empty text for "not set"; forms written from a
     * model mean exactly what they hold.
     *
     * @param array  $f Stored array.
     * @param string $k Key.
     * @return bool Present.
     */
    private function has( $f, $k ) {
        if ( $this->ours ) {
            return array_key_exists( $k, $f );
        }
        return isset( $f[ $k ] ) && $f[ $k ] !== '' && $f[ $k ] !== array();
    }

    // ---------------------------------------------------------------------
    // Stored form to model
    // ---------------------------------------------------------------------

    /**
     * Read a stored form into the model.
     *
     * @param array $stored Keys: title, description, config, settings, confirmations, notifications
     *                      (each as stored: arrays or JSON text).
     * @return array array( 'model' => ..., 'report' => QAF_Import_Report ).
     */
    public static function from_legacy( $stored ) {
        $c = new self();
        return $c->read( $stored );
    }

    /**
     * Decode a column that may still be JSON text.
     *
     * @param mixed $v Value.
     * @return array Array.
     */
    private static function column( $v ) {
        if ( is_string( $v ) ) {
            $v = json_decode( $v, true );
        }
        return is_array( $v ) ? $v : array();
    }

    /**
     * Worker.
     *
     * @param array $stored Stored form.
     * @return array Result.
     */
    private function read( $stored ) {
        $config     = self::column( $stored['config'] ?? array() );
        $this->ours = isset( $config['model_version'] );

        if ( isset( $config['form_model'] ) && is_array( $config['form_model'] ) ) {
            // Saved by this version or later: the model itself is stored.
            $model = $config['form_model'];
            $this->report->carried = count( QAF_Form_Model::items( $model ) );
            return array( 'model' => $model, 'report' => $this->report );
        }

        $model = array( 'version' => QAF_Form_Model::VERSION, 'title' => (string) ( $stored['title'] ?? '' ) );
        $extra = isset( $config['model_extra'] ) && is_array( $config['model_extra'] ) ? $config['model_extra'] : array();
        if ( isset( $stored['description'] ) && $stored['description'] !== '' ) {
            $model['description'] = (string) $stored['description'];
        }
        foreach ( array( 'id', 'description', 'language' ) as $k ) {
            if ( isset( $extra[ $k ] ) ) {
                $model[ $k ] = $extra[ $k ];
            }
        }
        $settings = self::column( $stored['settings'] ?? array() );
        if ( $settings ) {
            $model['settings'] = $this->settings( $settings );
        }
        if ( isset( $extra['choice_libraries'] ) ) {
            $model['choice_libraries'] = $extra['choice_libraries'];
        }

        // Pages.
        $pages = array();
        foreach ( $config['pages'] ?? array() as $i => $p ) {
            $id = $this->clean_id( $p['id'] ?? '', 'page_' . ( $i + 1 ), 'page ' . ( $i + 1 ) );
            $page = array( 'id' => $id );
            if ( $this->has( $p, 'title' ) ) {
                $page['title'] = (string) $p['title'];
            }
            if ( $this->has( $p, 'description' ) ) {
                $page['description'] = (string) $p['description'];
            }
            if ( isset( $p['when'] ) ) {
                $page['when'] = $p['when'];
            }
            $page['items'] = array();
            if ( isset( $p['props'] ) && is_array( $p['props'] ) && $p['props'] ) {
                $page['props'] = $p['props'];
            }
            $pages[ (string) ( $p['id'] ?? $id ) ] = $page;
        }
        if ( ! $pages ) {
            $pages['page_1'] = array( 'id' => 'page_1', 'items' => array() );
        }
        $first = array_key_first( $pages );

        // Fields, grouped by page in the order they appear.
        $by_page = array();
        foreach ( $config['fields'] ?? array() as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $pid = (string) ( $field['page'] ?? $first );
            if ( ! isset( $pages[ $pid ] ) ) {
                $this->report->changed( 'field ' . ( $field['id'] ?? '?' ), 'Its page "' . $pid . '" does not exist, so it was put on the first page.' );
                $pid = $first;
            }
            $by_page[ $pid ][] = $field;
        }
        foreach ( $by_page as $pid => $fields ) {
            $i                       = 0;
            $pages[ $pid ]['items'] = $this->items( $fields, $i, null );
        }
        $model['pages'] = array_values( $pages );

        foreach ( array( 'rules', 'scoring' ) as $k ) {
            if ( isset( $extra[ $k ] ) ) {
                $model[ $k ] = $extra[ $k ];
            }
        }
        foreach ( array( 'confirmations' => 'confirmation', 'notifications' => 'notification' ) as $col => $kind ) {
            $list = self::column( $stored[ $col ] ?? array() );
            if ( $list ) {
                $model[ $col ] = $this->messages( array_values( $list ), $kind );
            }
        }
        foreach ( array( 'translations', 'extensions' ) as $k ) {
            if ( isset( $extra[ $k ] ) ) {
                $model[ $k ] = $extra[ $k ];
            }
        }
        if ( $this->legacy ) {
            $model['extensions']['qaf_legacy'] = array_merge( $model['extensions']['qaf_legacy'] ?? array(), $this->legacy );
        }

        foreach ( QAF_Form_Model::validate( $model ) as $e ) {
            $this->report->error( $e['path'], $e['message'] );
        }
        return array( 'model' => $model, 'report' => $this->report );
    }

    /**
     * Settings, with names the model cannot use set aside.
     *
     * @param array $settings Stored settings.
     * @return array Model settings.
     */
    private function settings( $settings ) {
        $out = array();
        foreach ( $settings as $k => $v ) {
            $k = (string) $k;
            if ( ! $this->ours && ( $v === array() || $v === null ) ) {
                continue;
            }
            if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $k ) || in_array( $k, QAF_Form_Model::FIXED_NAMES['settings'], true ) ) {
                $this->legacy['settings'][ $k ] = $v;
                $this->report->dropped( 'setting ' . $k, 'The model cannot hold a setting with this name; it is kept under extensions.qaf_legacy.' );
                continue;
            }
            $out[ $k ] = $v;
        }
        return $out;
    }

    /**
     * Confirmations or notifications.
     *
     * @param array  $list Stored list.
     * @param string $kind confirmation|notification.
     * @return array Model list.
     */
    private function messages( $list, $kind ) {
        $out   = array();
        $fixed = QAF_Form_Model::FIXED_NAMES[ $kind ];
        foreach ( $list as $i => $obj ) {
            if ( ! is_array( $obj ) ) {
                continue;
            }
            $m = array( 'id' => $this->clean_id( $obj['id'] ?? '', $kind . '_' . ( $i + 1 ), $kind . ' ' . ( $i + 1 ), false ) );
            if ( $kind === 'confirmation' ) {
                $type      = $obj['type'] ?? 'message';
                $m['type'] = in_array( $type, array( 'message', 'redirect', 'page' ), true ) ? $type : 'message';
                if ( $m['type'] !== $type ) {
                    $this->report->changed( $kind . ' ' . $m['id'], 'Its type "' . $type . '" is not one the model knows; it is now a message.' );
                }
            }
            foreach ( $obj as $k => $v ) {
                if ( in_array( $k, array( 'id', 'type', 'props' ), true ) ) {
                    continue;
                }
                if ( $k === 'conditional_logic' ) {
                    $when = $this->condition( $v, $kind . ' ' . $m['id'] );
                    if ( $when !== null ) {
                        $m['when'] = $when;
                    }
                } elseif ( in_array( $k, $fixed, true ) ) {
                    $m[ $k ] = $v;
                } elseif ( $v !== null && $v !== '' && $v !== array() ) {
                    $m['props'][ $k ] = $v;
                }
            }
            if ( isset( $obj['props'] ) && is_array( $obj['props'] ) ) {
                foreach ( $obj['props'] as $k => $v ) {
                    $m['props'][ $k ] = $v;
                }
            }
            $out[] = $m;
        }
        return $out;
    }

    /**
     * A usable, unique ID.
     *
     * @param mixed  $id       Stored ID.
     * @param string $fallback ID to use when there is none.
     * @param string $where    Description for the report.
     * @param bool   $global   Whether the ID shares the item and page namespace.
     * @return string ID.
     */
    private function clean_id( $id, $fallback, $where, $global = true ) {
        $id    = (string) $id;
        $clean = $id === '' ? $fallback : preg_replace( '/[^A-Za-z0-9_]/', '_', $id );
        if ( preg_match( '/^[0-9]/', $clean ) ) {
            $clean = 'f_' . $clean;
        }
        if ( $global ) {
            $base = $clean;
            $n    = 2;
            while ( isset( $this->ids[ $clean ] ) ) {
                $clean = $base . '_' . $n++;
            }
            $this->ids[ $clean ] = true;
        }
        if ( $id !== '' && $clean !== $id ) {
            $this->report->changed( $where, 'Its ID "' . $id . '" became "' . $clean . '".' );
        }
        return $clean;
    }

    /**
     * Read stored fields into items, folding sections and their contents.
     *
     * @param array    $fields Flat stored fields.
     * @param int      $i      Position (by reference).
     * @param int|null $limit  How many items to read (a section's stored count), or null for all.
     * @param bool     $in_section Whether these are a flat section's contents.
     * @return array Items.
     */
    private function items( $fields, &$i, $limit, $in_section = false ) {
        $items = array();
        while ( $i < count( $fields ) && ( $limit === null || count( $items ) < $limit ) ) {
            $f = $fields[ $i ];
            if ( $in_section && $limit === null && ( $f['type'] ?? '' ) === 'section' ) {
                break; // A flat section ends at the next section.
            }
            $i++;
            $item = $this->item( $f, $fields, $i );
            if ( $item !== null ) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * One stored field to an item.
     *
     * @param array $f      Stored field.
     * @param array $fields All fields at this level (sections read on from them).
     * @param int   $i      Position after $f (by reference).
     * @return array|null Item.
     */
    private function item( $f, $fields, &$i ) {
        $type  = strtolower( (string) ( $f['type'] ?? '' ) );
        $where = 'field ' . ( $f['id'] ?? $type );
        $alias = QAF_BFL_Reader::TYPE_ALIASES[ $type ] ?? null;
        $props = array();
        if ( $alias ) {
            $this->report->changed( $where, 'The type "' . $type . '" is now "' . $alias . '".' );
            $type = $alias;
        } elseif ( in_array( $type, QAF_BFL_Reader::ELEMENT_TYPES, true ) ) {
            $this->report->changed( $where, 'The type "' . $type . '" is now an html field with element "' . $type . '".' );
            $props['element'] = $type;
            $type             = 'html';
        }
        if ( $type !== 'section' && $type !== 'columns' && ! QAF_Form_Model::is_field_type( $type ) ) {
            $this->legacy['unknown_fields'][] = $f;
            $this->report->dropped( $where, 'The model has no field type "' . $type . '"; the field is kept under extensions.qaf_legacy.' );
            return null;
        }
        $this->report->carried++;
        $item = array( 'id' => $this->clean_id( $f['id'] ?? '', 'field_' . ( count( $this->ids ) + 1 ), $where ), 'type' => $type );

        if ( $this->has( $f, 'label' ) ) {
            $item['label'] = (string) $f['label'];
        }
        if ( $this->has( $f, 'description' ) ) {
            $item['help'] = (string) $f['description'];
        }
        if ( $this->has( $f, 'placeholder' ) ) {
            $item['placeholder'] = (string) $f['placeholder'];
        }
        if ( array_key_exists( 'required', $f ) && $type !== 'section' && $type !== 'columns' ) {
            if ( is_bool( $f['required'] ) ) {
                $item['required'] = $f['required'];
            } elseif ( ! empty( $f['required'] ) ) {
                $item['required'] = true;
            }
        }
        if ( $this->has( $f, 'default_value' ) && ( $this->ours || $f['default_value'] !== null ) ) {
            $item['default'] = $f['default_value'];
        }
        if ( ! empty( $f['smart_default_expression'] ) ) {
            $item['smart_default'] = $this->expression( $f['smart_default_expression'], $where, 'smart default' );
        } elseif ( isset( $f['smart_default'] ) && $f['smart_default'] !== '' ) {
            $item['smart_default'] = QAF_BFL_Reader::legacy_smart_default( $f['smart_default'] );
        }
        if ( $this->has( $f, 'input_mask' ) ) {
            $item['mask'] = (string) $f['input_mask'];
        }
        if ( isset( $f['when'] ) && is_string( $f['when'] ) ) {
            $item['when'] = $this->expression( $f['when'], $where, 'condition' );
        } elseif ( isset( $f['conditional_logic'] ) ) {
            $when = $this->condition( $f['conditional_logic'], $where );
            if ( $when !== null ) {
                $item['when'] = $when;
            }
        }
        if ( isset( $f['calculation'] ) && $f['calculation'] !== '' ) {
            $item['calculation'] = $this->expression( $f['calculation'], $where, 'calculation' );
        } elseif ( isset( $f['formula'] ) && $f['formula'] !== '' ) {
            $expr = $this->expression( self::translate_formula( $f['formula'] ), $where, 'formula', false );
            if ( $expr !== null ) {
                $item['calculation'] = $expr;
            } else {
                $this->legacy['fields'][ $item['id'] ]['formula'] = $f['formula'];
                $this->report->dropped( $where, 'Its formula "' . $f['formula'] . '" uses spreadsheet functions the expression language does not have; it is kept under extensions.qaf_legacy.' );
            }
        }
        foreach ( array( 'calculation', 'smart_default', 'when' ) as $k ) {
            if ( array_key_exists( $k, $item ) && $item[ $k ] === null ) {
                unset( $item[ $k ] );
            }
        }

        // Choices.
        $choices = $f['choices'] ?? null;
        if ( is_array( $choices ) && isset( $choices['library'] ) ) {
            $item['choice_library'] = (string) $choices['library'];
            $choices                = null;
        }
        if ( ( ! is_array( $choices ) || ! $choices ) && in_array( $type, QAF_Form_Model::CHOICE_TYPES, true ) && ! empty( $f['options'] ) && is_array( $f['options'] ) ) {
            $choices = array();
            foreach ( $f['options'] as $value => $label ) {
                $choices[] = is_array( $label ) ? $label : array( 'value' => (string) $value, 'label' => (string) $label );
            }
            $this->report->changed( $where, 'Its options became choices.' );
        } elseif ( ! empty( $f['options'] ) ) {
            $this->legacy['fields'][ $item['id'] ]['options'] = $f['options'];
            $this->report->dropped( $where, 'Its "options" setting has no place in the model; it is kept under extensions.qaf_legacy.' );
        }
        if ( is_array( $choices ) && $choices ) {
            $item['choices'] = $this->choices( $choices, $where );
        }
        if ( isset( $f['choice_library'] ) && $f['choice_library'] !== '' ) {
            $item['choice_library'] = (string) $f['choice_library'];
        }
        if ( ! empty( $f['cascade_from'] ) ) {
            $item['cascade'] = array( 'from' => (string) $f['cascade_from'] );
            foreach ( array( 'cascade_library' => 'library', 'empty_message' => 'empty_message', 'loading_message' => 'loading_message' ) as $k => $to ) {
                if ( isset( $f[ $k ] ) && $f[ $k ] !== '' ) {
                    $item['cascade'][ $to ] = (string) $f[ $k ];
                }
            }
        } else {
            foreach ( array( 'cascade_library', 'empty_message', 'loading_message' ) as $k ) {
                if ( isset( $f[ $k ] ) && $f[ $k ] !== '' ) {
                    $this->legacy['fields'][ $item['id'] ][ $k ] = $f[ $k ];
                    $this->report->dropped( $where, 'It has "' . $k . '" but no cascade_from; kept under extensions.qaf_legacy.' );
                }
            }
        }
        if ( $type === 'checkbox' && ! isset( $item['choices'] ) && ! isset( $item['choice_library'] ) && ! isset( $item['cascade'] ) ) {
            $item['choices'] = array( array( 'value' => 'yes', 'label' => 'Yes' ) );
            $this->report->changed( $where, 'A checkbox with no choices is one box; it got the choice yes|Yes.' );
        }

        // Validation.
        $v = array();
        foreach ( array( 'min', 'max' ) as $k ) {
            if ( isset( $f[ $k ] ) && $f[ $k ] !== '' ) {
                $v[ $k ] = self::number_or_text( $f[ $k ] );
            }
        }
        foreach ( array( 'min_length', 'max_length' ) as $k ) {
            if ( isset( $f[ $k ] ) && $f[ $k ] !== '' && is_numeric( $f[ $k ] ) ) {
                $v[ $k ] = (int) $f[ $k ];
            }
        }
        if ( isset( $f['pattern'] ) && $f['pattern'] !== '' ) {
            $v['pattern'] = (string) $f['pattern'];
        }
        if ( isset( $f['custom_error_message'] ) && $f['custom_error_message'] !== '' ) {
            $v['message'] = (string) $f['custom_error_message'];
        }
        if ( array_key_exists( 'unique', $f ) && ( $this->ours ? is_bool( $f['unique'] ) || ! empty( $f['unique'] ) : ! empty( $f['unique'] ) ) ) {
            $v['unique'] = (bool) $f['unique'];
        }
        if ( isset( $f['unique_scope'] ) && $f['unique_scope'] !== '' && ( $this->ours || ! empty( $f['unique'] ) ) ) {
            if ( in_array( $f['unique_scope'], array( 'form', 'global' ), true ) ) {
                $v['unique_scope'] = $f['unique_scope'];
            } else {
                $this->report->dropped( $where, 'Its unique scope "' . $f['unique_scope'] . '" is not form or global.' );
            }
        }
        if ( isset( $f['unique_message'] ) && $f['unique_message'] !== '' && ( $this->ours || ! empty( $f['unique'] ) ) ) {
            $v['unique_message'] = (string) $f['unique_message'];
        }
        if ( $v ) {
            $item['validation'] = $v;
        }

        // Scoring.
        $s = array();
        if ( isset( $f['correct_answers'] ) && $f['correct_answers'] !== '' && $f['correct_answers'] !== array() ) {
            $s['correct'] = array_values( array_map( 'strval', (array) $f['correct_answers'] ) );
        }
        foreach ( array( 'points' => 'points', 'partial_credit' => 'partial', 'case_sensitive' => 'case_sensitive' ) as $k => $to ) {
            if ( array_key_exists( $k, $f ) && $f[ $k ] !== '' && $f[ $k ] !== null ) {
                $s[ $to ] = $to === 'points' ? self::number_or_text( $f[ $k ] ) : (bool) $f[ $k ];
            }
        }
        foreach ( array( 'feedback_correct', 'feedback_incorrect', 'feedback_partial', 'explanation' ) as $k ) {
            if ( isset( $f[ $k ] ) && $f[ $k ] !== '' ) {
                $s[ $k ] = (string) $f[ $k ];
            }
        }
        if ( $s && ( isset( $s['correct'] ) || $type === 'quiz' ) ) {
            $item['scoring'] = $s;
        } elseif ( $s ) {
            $this->legacy['fields'][ $item['id'] ]['scoring'] = $s;
            $this->report->dropped( $where, 'It has quiz settings but no correct answers; kept under extensions.qaf_legacy.' );
        }

        // Attributes.
        if ( ! empty( $f['custom_attributes'] ) && is_array( $f['custom_attributes'] ) ) {
            foreach ( $f['custom_attributes'] as $name => $value ) {
                if ( preg_match( '/^(data-[a-z0-9_.-]+|aria-[a-z]+|title|lang|dir|translate|contenteditable|draggable|spellcheck|tabindex|accesskey|role|slot|part)$/', (string) $name ) && is_scalar( $value ) ) {
                    $item['attributes'][ (string) $name ] = $value;
                } else {
                    $this->report->dropped( $where, 'The attribute "' . $name . '" is not allowed.' );
                }
            }
        }

        // Containers.
        if ( $type === 'section' ) {
            $limit         = isset( $f['model_items'] ) && is_numeric( $f['model_items'] ) ? (int) $f['model_items'] : null;
            $item['items'] = $this->items( $fields, $i, $limit, true );
        } elseif ( $type === 'columns' ) {
            $cols = array();
            foreach ( (array) ( $f['columns'] ?? array() ) as $col ) {
                $list = isset( $col['fields'] ) ? $col['fields'] : ( QAF_JSON_Schema::is_list( $col ) ? $col : array() );
                $j    = 0;
                $c    = array();
                if ( isset( $col['width'] ) && $col['width'] !== '' ) {
                    $c['width'] = (string) $col['width'];
                }
                $c['items'] = $this->items( $list, $j, null );
                $cols[]     = $c;
            }
            $item['columns'] = $cols ? $cols : array( array( 'items' => array() ) );
        } elseif ( $type === 'repeater' ) {
            $j             = 0;
            $item['items'] = $this->items( is_array( $f['fields'] ?? null ) ? $f['fields'] : array(), $j, null );
        }

        // Everything else is a prop, if the model can hold it.
        $skip = array_merge( self::MAPPED, $type === 'columns' ? array( 'columns', 'column_count' ) : array() );
        foreach ( $f as $k => $value ) {
            $k = (string) $k;
            if ( in_array( $k, $skip, true ) || ( ! $this->ours && ( $value === null || $value === '' || $value === array() ) ) ) {
                continue;
            }
            if ( ! $this->ours && array_key_exists( $k, self::BASE_DEFAULTS ) && $value === self::BASE_DEFAULTS[ $k ] ) {
                continue;
            }
            if ( $k === 'props' && is_array( $value ) ) {
                foreach ( $value as $pk => $pv ) {
                    $props[ $pk ] = $pv;
                }
                continue;
            }
            if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $k ) || ! self::prop_allowed( $k ) ) {
                $this->legacy['fields'][ $item['id'] ][ $k ] = $value;
                $this->report->dropped( $where, 'Its setting "' . $k . '" has no place in the model; it is kept under extensions.qaf_legacy.' );
                continue;
            }
            $props[ $k ] = $value;
        }
        if ( $props ) {
            $item['props'] = $props;
        }

        $order = array( 'id', 'type', 'label', 'help', 'placeholder', 'required', 'default', 'smart_default', 'mask', 'when', 'calculation', 'choices', 'choice_library', 'cascade', 'validation', 'scoring', 'attributes', 'columns', 'items', 'props' );
        $out   = array();
        foreach ( $order as $k ) {
            if ( array_key_exists( $k, $item ) ) {
                $out[ $k ] = $item[ $k ];
            }
        }
        // Sections and columns hold fewer properties than fields.
        $allowed = array(
            'section' => array( 'id', 'type', 'label', 'help', 'when', 'items', 'props' ),
            'columns' => array( 'id', 'type', 'when', 'columns', 'props' ),
        );
        if ( isset( $allowed[ $type ] ) ) {
            foreach ( array_diff( array_keys( $out ), $allowed[ $type ] ) as $k ) {
                $this->legacy['fields'][ $out['id'] ][ $k ] = $out[ $k ];
                $this->report->dropped( $where, 'A ' . $type . ' has no "' . $k . '"; it is kept under extensions.qaf_legacy.' );
                unset( $out[ $k ] );
            }
        }
        return $out;
    }

    /**
     * A formula in QAF's calculation syntax ({field}, upper-case functions,
     * semicolons between arguments) in expression-language spelling.
     *
     * @param string $formula Formula.
     * @return string Expression text (which may still not parse).
     */
    public static function translate_formula( $formula ) {
        $names = array( 'average' => 'avg', 'ceiling' => 'ceil' );
        $out   = preg_replace_callback( '/\b([A-Za-z_][A-Za-z0-9_]*)\s*\(/', function ( $m ) use ( $names ) {
            $n = strtolower( $m[1] );
            return ( $names[ $n ] ?? $n ) . '(';
        }, (string) $formula );
        return str_replace( ';', ',', $out );
    }

    /**
     * Whether a name may be a prop.
     *
     * @param string $name Name.
     * @return bool Allowed.
     */
    private static function prop_allowed( $name ) {
        static $reserved = null;
        if ( $reserved === null ) {
            $schema   = json_decode( file_get_contents( QAF_Form_Model::schema_path() ), true );
            $reserved = $schema['$defs']['props']['propertyNames']['not']['enum'];
        }
        return ! in_array( $name, $reserved, true );
    }

    /**
     * Numbers stay numbers; numeric text becomes a number; other text stays.
     *
     * @param mixed $v Value.
     * @return mixed Value.
     */
    private static function number_or_text( $v ) {
        if ( is_int( $v ) || is_float( $v ) ) {
            return $v;
        }
        if ( is_string( $v ) && preg_match( '/^-?\d+$/', trim( $v ) ) ) {
            return (int) $v;
        }
        if ( is_string( $v ) && is_numeric( trim( $v ) ) ) {
            return (float) $v;
        }
        return (string) $v;
    }

    /**
     * Stored choices to model choices.
     *
     * @param array  $choices Stored choices.
     * @param string $where   Report location.
     * @return array Choices.
     */
    private function choices( $choices, $where ) {
        $out = array();
        foreach ( $choices as $c ) {
            if ( ! is_array( $c ) ) {
                $c = array( 'value' => QAF_Form_Model::slug( (string) $c ), 'label' => (string) $c );
            }
            $label  = (string) ( $c['label'] ?? $c['text'] ?? $c['value'] ?? '' );
            $choice = array( 'value' => (string) ( $c['value'] ?? QAF_Form_Model::slug( $label ) ), 'label' => $label );
            foreach ( $c as $k => $v ) {
                if ( in_array( $k, array( 'value', 'label', 'text' ), true ) || $v === null || $v === '' ) {
                    continue;
                }
                if ( in_array( $k, array( 'price', 'points' ), true ) && is_numeric( $v ) ) {
                    $choice[ $k ] = self::number_or_text( $v );
                } elseif ( in_array( $k, array( 'image', 'description' ), true ) ) {
                    $choice[ $k ] = (string) $v;
                } elseif ( $k === 'selected' || $k === 'isSelected' ) {
                    $choice['selected'] = (bool) $v;
                } else {
                    $this->report->dropped( $where, 'Choice "' . $label . '" has "' . $k . '", which a choice cannot hold.' );
                }
            }
            $out[] = $choice;
        }
        return $out;
    }

    /**
     * Parse and canonicalise expression text.
     *
     * @param string $text    Text.
     * @param string $where   Report location.
     * @param string $what    What it is.
     * @param bool   $report  Report a failure.
     * @return string|null Canonical text.
     */
    private function expression( $text, $where, $what, $report = true ) {
        try {
            return QAF_Expression::canonical( (string) $text );
        } catch ( QAF_Expression_Error $e ) {
            if ( $report ) {
                $this->report->dropped( $where, 'Its ' . $what . ' "' . $text . '" is not a valid expression: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * A stored conditional_logic block to a condition.
     *
     * @param mixed  $logic Stored logic.
     * @param string $where Report location.
     * @return string|null Canonical condition, or null for none.
     */
    private function condition( $logic, $where ) {
        if ( ! is_array( $logic ) || empty( $logic['enabled'] ) ) {
            return null;
        }
        if ( ! empty( $logic['expression'] ) ) {
            return $this->expression( $logic['expression'], $where, 'condition' );
        }
        $tests = array();
        foreach ( $logic['rules'] ?? array() as $rule ) {
            $op = self::OPERATORS[ $rule['operator'] ?? 'equals' ] ?? null;
            if ( $op === null || empty( $rule['field'] ) ) {
                $this->report->dropped( $where, 'A condition rule with operator "' . ( $rule['operator'] ?? '' ) . '" could not be read.' );
                continue;
            }
            $ref = array( 'type' => 'ref', 'field' => preg_replace( '/[^A-Za-z0-9_]/', '_', (string) $rule['field'] ) );
            if ( $op === 'empty' || $op === 'not_empty' ) {
                $tests[] = array( 'type' => 'op', 'op' => $op, 'args' => array( $ref ) );
            } else {
                $value = $rule['value'] ?? '';
                $value = is_scalar( $value ) ? $value : '';
                if ( ! $this->ours && in_array( $op, array( 'gt', 'lt', 'ge', 'le' ), true ) && is_string( $value ) && is_numeric( trim( $value ) ) ) {
                    $value = self::number_or_text( $value );
                }
                $tests[] = array( 'type' => 'op', 'op' => $op, 'args' => array( $ref, array( 'type' => 'literal', 'value' => $value ) ) );
            }
        }
        if ( ! $tests ) {
            return null;
        }
        $node = count( $tests ) === 1 ? $tests[0] : array( 'type' => 'op', 'op' => ( $logic['logic'] ?? 'all' ) === 'any' ? 'or' : 'and', 'args' => $tests );
        if ( ( $logic['action'] ?? 'show' ) === 'hide' ) {
            $inverse = array( 'is' => 'is_not', 'is_not' => 'is', 'empty' => 'not_empty', 'not_empty' => 'empty', 'contains' => 'not_contains', 'not_contains' => 'contains' );
            if ( ! $this->ours && count( $tests ) === 1 && isset( $inverse[ $node['op'] ] ) ) {
                $node['op'] = $inverse[ $node['op'] ];
            } else {
                $node = array( 'type' => 'op', 'op' => 'not', 'args' => array( $node ) );
            }
        }
        return QAF_Expression::canonical( QAF_Expression::to_text( $node ) );
    }

    // ---------------------------------------------------------------------
    // Model to stored form
    // ---------------------------------------------------------------------

    /**
     * Write a model in QAF's stored shape.
     *
     * @param array $model Model.
     * @return array Keys: title, description, config, settings, confirmations, notifications.
     */
    public static function to_legacy( $model ) {
        $config = array( 'model_version' => $model['version'], 'fields' => array(), 'pages' => array() );
        foreach ( $model['pages'] as $page ) {
            $p = array( 'id' => $page['id'] );
            if ( isset( $page['title'] ) ) {
                $p['title'] = $page['title'];
            }
            if ( isset( $page['description'] ) ) {
                $p['description'] = $page['description'];
            }
            if ( isset( $page['when'] ) ) {
                $p['when'] = $page['when'];
            }
            if ( isset( $page['props'] ) ) {
                $p['props'] = $page['props'];
            }
            $config['pages'][] = $p;
            foreach ( self::flatten( $page['items'] ) as $f ) {
                $f['page']          = $page['id'];
                $config['fields'][] = $f;
            }
        }
        $extra = array();
        foreach ( array( 'id', 'language', 'choice_libraries', 'rules', 'scoring', 'translations', 'extensions' ) as $k ) {
            if ( isset( $model[ $k ] ) ) {
                $extra[ $k ] = $model[ $k ];
            }
        }
        if ( ( $model['description'] ?? null ) === '' ) {
            $extra['description'] = '';
        }
        if ( $extra ) {
            $config['model_extra'] = $extra;
        }
        return array(
            'title'         => $model['title'],
            'description'   => $model['description'] ?? '',
            'config'        => $config,
            'settings'      => $model['settings'] ?? array(),
            'confirmations' => array_map( array( __CLASS__, 'legacy_message' ), $model['confirmations'] ?? array() ),
            'notifications' => array_map( array( __CLASS__, 'legacy_message' ), $model['notifications'] ?? array() ),
        );
    }

    /**
     * A confirmation or notification in stored shape (conditions as QAF stores them).
     *
     * @param array $m Model object.
     * @return array Stored object.
     */
    private static function legacy_message( $m ) {
        if ( isset( $m['when'] ) ) {
            $m['conditional_logic'] = self::legacy_condition( $m['when'] );
            unset( $m['when'] );
        }
        return $m;
    }

    /**
     * Items to QAF's flat stored fields (a section is followed by its items,
     * and says how many it has, so they fold back the same way).
     *
     * @param array $items Items.
     * @return array Stored fields.
     */
    private static function flatten( $items ) {
        $out = array();
        foreach ( $items as $item ) {
            $f = self::legacy_field( $item );
            if ( $item['type'] === 'section' ) {
                $f['model_items'] = count( $item['items'] );
                $out[]            = $f;
                foreach ( self::flatten( $item['items'] ) as $child ) {
                    $out[] = $child;
                }
                continue;
            }
            $out[] = $f;
        }
        return $out;
    }

    /**
     * One item in stored shape.
     *
     * @param array $item Item.
     * @return array Stored field.
     */
    private static function legacy_field( $item ) {
        $f = array( 'id' => $item['id'], 'type' => $item['type'] );
        $map = array( 'label' => 'label', 'help' => 'description', 'placeholder' => 'placeholder', 'required' => 'required', 'default' => 'default_value', 'mask' => 'input_mask', 'choices' => 'choices', 'choice_library' => 'choice_library' );
        foreach ( $map as $from => $to ) {
            if ( array_key_exists( $from, $item ) ) {
                $f[ $to ] = $item[ $from ];
            }
        }
        if ( isset( $item['smart_default'] ) ) {
            $legacy = self::legacy_smart_default( $item['smart_default'] );
            if ( $legacy !== null ) {
                $f['smart_default'] = $legacy;
            } else {
                $f['smart_default_expression'] = $item['smart_default'];
            }
        }
        if ( isset( $item['when'] ) ) {
            $f['conditional_logic'] = self::legacy_condition( $item['when'] );
        }
        if ( isset( $item['calculation'] ) ) {
            $f['calculation'] = $item['calculation'];
            // The calculation field's own formula, where the older syntax can say it.
            if ( preg_match( '/^[#A-Za-z0-9_ .+\-*\/()]+$/', $item['calculation'] ) ) {
                $f['formula'] = preg_replace( '/#([A-Za-z_][A-Za-z0-9_]*)/', '{$1}', $item['calculation'] );
            }
        }
        if ( isset( $item['cascade'] ) ) {
            $f['cascade_from'] = $item['cascade']['from'];
            foreach ( array( 'library' => 'cascade_library', 'empty_message' => 'empty_message', 'loading_message' => 'loading_message' ) as $from => $to ) {
                if ( isset( $item['cascade'][ $from ] ) ) {
                    $f[ $to ] = $item['cascade'][ $from ];
                }
            }
        }
        foreach ( array( 'min' => 'min', 'max' => 'max', 'min_length' => 'min_length', 'max_length' => 'max_length', 'pattern' => 'pattern', 'message' => 'custom_error_message', 'unique' => 'unique', 'unique_scope' => 'unique_scope', 'unique_message' => 'unique_message' ) as $from => $to ) {
            if ( array_key_exists( $from, $item['validation'] ?? array() ) ) {
                $f[ $to ] = $item['validation'][ $from ];
            }
        }
        foreach ( array( 'correct' => 'correct_answers', 'points' => 'points', 'partial' => 'partial_credit', 'case_sensitive' => 'case_sensitive', 'feedback_correct' => 'feedback_correct', 'feedback_incorrect' => 'feedback_incorrect', 'feedback_partial' => 'feedback_partial', 'explanation' => 'explanation' ) as $from => $to ) {
            if ( array_key_exists( $from, $item['scoring'] ?? array() ) ) {
                $f[ $to ] = $item['scoring'][ $from ];
            }
        }
        if ( isset( $item['attributes'] ) ) {
            $f['custom_attributes'] = $item['attributes'];
        }
        if ( $item['type'] === 'columns' ) {
            $f['columns'] = array();
            foreach ( $item['columns'] as $col ) {
                $c = array();
                if ( isset( $col['width'] ) ) {
                    $c['width'] = $col['width'];
                }
                $c['fields']     = self::flatten( $col['items'] );
                $f['columns'][] = $c;
            }
        } elseif ( $item['type'] === 'repeater' ) {
            $f['fields'] = self::flatten( $item['items'] ?? array() );
        }
        if ( isset( $item['props'] ) ) {
            if ( $item['type'] === 'columns' ) {
                $f['props'] = $item['props']; // "columns" is taken by the column list.
            } else {
                foreach ( $item['props'] as $k => $v ) {
                    $f[ $k ] = $v;
                }
            }
        }
        return $f;
    }

    /**
     * A smart default in the older text form, or null if it has none.
     *
     * @param string $expr Canonical expression.
     * @return string|null Text.
     */
    private static function legacy_smart_default( $expr ) {
        foreach ( array( 'today', 'geo' ) as $fn ) {
            if ( $expr === $fn . '()' ) {
                return $fn;
            }
        }
        if ( preg_match( '/^today\(\) ([+-]) (\d+)$/', $expr, $m ) ) {
            return 'today' . $m[1] . $m[2];
        }
        if ( preg_match( '/^(user|query)\("([A-Za-z0-9_.-]+)"\)$/', $expr, $m ) ) {
            return $m[1] . ':' . $m[2];
        }
        $node = QAF_Expression::parse( $expr );
        if ( $node['type'] === 'literal' && is_string( $node['value'] ) ) {
            // Only when reading it back gives the same expression.
            return QAF_BFL_Reader::legacy_smart_default( $node['value'] ) === $expr ? $node['value'] : null;
        }
        return null;
    }

    /**
     * A condition in QAF's stored rule shape. When the rule list cannot say
     * it, the expression rides along and the rules stay empty.
     *
     * @param string $when Canonical condition.
     * @return array Stored conditional_logic.
     */
    public static function legacy_condition( $when ) {
        $node   = QAF_Expression::parse( $when );
        $action = 'show';
        if ( $node['type'] === 'op' && $node['op'] === 'not' ) {
            $action = 'hide';
            $node   = $node['args'][0];
        }
        $logic = 'all';
        $tests = array( $node );
        if ( $node['type'] === 'op' && ( $node['op'] === 'and' || $node['op'] === 'or' ) ) {
            $logic = $node['op'] === 'or' ? 'any' : 'all';
            $tests = $node['args'];
        }
        $names = array_flip( self::OPERATORS );
        $rules = array();
        foreach ( $tests as $t ) {
            $ok = $t['type'] === 'op' && isset( $names[ $t['op'] ] ) && $t['args'][0]['type'] === 'ref'
                && ( count( $t['args'] ) === 1 || ( $t['args'][1]['type'] === 'literal' && is_scalar( $t['args'][1]['value'] ) && ! is_bool( $t['args'][1]['value'] ) ) );
            if ( ! $ok ) {
                return array( 'enabled' => true, 'action' => 'show', 'logic' => 'all', 'rules' => array(), 'expression' => $when );
            }
            $rule = array( 'field' => $t['args'][0]['field'], 'operator' => $names[ $t['op'] ], 'value' => count( $t['args'] ) > 1 ? $t['args'][1]['value'] : '' );
            $rules[] = $rule;
        }
        $out = array( 'enabled' => true, 'action' => $action, 'logic' => $logic, 'rules' => $rules );
        // Reading the rules back must give the same text; otherwise keep the text.
        $reader       = new self();
        $reader->ours = true;
        $back         = $reader->condition( $out, '' );
        if ( $back !== $when ) {
            $out = array( 'enabled' => true, 'action' => 'show', 'logic' => 'all', 'rules' => array(), 'expression' => $when );
        }
        return $out;
    }
}
