<?php
/**
 * Imports from other form builders into the form model.
 *
 * Each import is a mapping from the source's export to the model, with an
 * import report (BAF_Import_Report) of anything the model cannot hold.
 * Nothing is dropped without being listed. Sources:
 *
 * - Gravity Forms: its JSON export (Forms > Import/Export).
 * - WPForms: its JSON export (Tools > Export).
 * - Contact Form 7: the form template, alone or as JSON with "form", "mail"
 *   and "messages".
 * - Ninja Forms: its .nff export (JSON).
 * - Google Forms: the Forms API's form resource (forms.get), which
 *   BAF_Importers::fetch_google_form() fetches with an OAuth access token.
 *
 * Mappings are documented in docs/imports.md.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-baf-form-model.php';
require_once __DIR__ . '/class-baf-import-report.php';

/**
 * Collects items into pages for an importer, with unique IDs.
 */
class BAF_Import_Builder {

    /** @var array Model being built. */
    public $model;

    /** @var BAF_Import_Report */
    public $report;

    /** @var array IDs in use. */
    private $ids = array();

    /** @var int Index of the current page. */
    private $page = 0;

    /** @var bool Whether the current page has been given a title by the source. */
    private $page_declared = false;

    /** @var int|null Index of the open flat section on the current page. */
    private $section = null;

    /**
     * Constructor.
     *
     * @param string $source Source name for the report.
     * @param string $title  Form title.
     */
    public function __construct( $source, $title ) {
        $this->report = new BAF_Import_Report( $source );
        $this->model  = array( 'version' => BAF_Form_Model::VERSION, 'title' => (string) $title, 'pages' => array( array( 'id' => 'page_1', 'items' => array() ) ) );
        $this->ids['page_1'] = true;
    }

    /**
     * A unique, valid ID made from the source's own.
     *
     * @param mixed  $raw    Source ID or name.
     * @param string $prefix Prefix when the ID starts with a digit.
     * @return string ID.
     */
    public function id( $raw, $prefix = 'field_' ) {
        $id = preg_replace( '/[^A-Za-z0-9_]+/', '_', (string) $raw );
        $id = trim( $id, '_' );
        if ( $id === '' || ctype_digit( $id[0] ) ) {
            $id = $prefix . $id;
        }
        $base = $id;
        $n    = 2;
        while ( isset( $this->ids[ $id ] ) ) {
            $id = $base . '_' . $n++;
        }
        $this->ids[ $id ] = true;
        return $id;
    }

    /**
     * Start a new page (the first call names the first page).
     *
     * @param string $title       Title.
     * @param string $description Description.
     * @return int Page index.
     */
    public function page( $title = '', $description = '' ) {
        $current = &$this->model['pages'][ $this->page ];
        if ( ! $this->page_declared && ! $current['items'] ) {
            $this->page_declared = true;
        } else {
            $id                      = $this->id( 'page_' . ( count( $this->model['pages'] ) + 1 ), 'page_' );
            $this->model['pages'][]  = array( 'id' => $id, 'items' => array() );
            $this->page              = count( $this->model['pages'] ) - 1;
            $this->page_declared     = true;
        }
        unset( $current );
        $page = &$this->model['pages'][ $this->page ];
        $items = $page['items'];
        unset( $page['items'] );
        if ( $title !== '' ) {
            $page['title'] = (string) $title;
        }
        if ( $description !== '' ) {
            $page['description'] = (string) $description;
        }
        $page['items'] = $items;
        unset( $page );
        $this->section = null;
        return $this->page;
    }

    /**
     * The current page's ID.
     *
     * @return string ID.
     */
    public function page_id() {
        return $this->model['pages'][ $this->page ]['id'];
    }

    /**
     * Set a property of the current page.
     *
     * @param string $key   Key.
     * @param mixed  $value Value.
     */
    public function page_set( $key, $value ) {
        $page  = &$this->model['pages'][ $this->page ];
        $items = $page['items'];
        unset( $page['items'] );
        $page[ $key ]  = $value;
        $page['items'] = $items;
        unset( $page );
    }

    /**
     * Add an item to the current page, inside the open section if any.
     *
     * @param array $item Item.
     * @param bool  $flat_section When the item is a section: take the items after it.
     */
    public function add( $item, $flat_section = false ) {
        $this->report->carried++;
        $items = &$this->model['pages'][ $this->page ]['items'];
        if ( $item['type'] === 'section' ) {
            $item['items']  = $item['items'] ?? array();
            $items[]        = $item;
            $this->section  = $flat_section ? count( $items ) - 1 : null;
            return;
        }
        if ( $this->section !== null ) {
            $items[ $this->section ]['items'][] = $item;
        } else {
            $items[] = $item;
        }
        unset( $items );
    }

    /**
     * Join tests into one condition.
     *
     * @param array  $tests Expression trees.
     * @param string $join  and|or.
     * @param bool   $hide  Whether the source says "hide when".
     * @return string|null Canonical condition.
     */
    public static function condition( $tests, $join = 'and', $hide = false ) {
        if ( ! $tests ) {
            return null;
        }
        $node    = count( $tests ) === 1 ? $tests[0] : array( 'type' => 'op', 'op' => $join, 'args' => $tests );
        $inverse = array( 'is' => 'is_not', 'is_not' => 'is', 'empty' => 'not_empty', 'not_empty' => 'empty', 'contains' => 'not_contains', 'not_contains' => 'contains' );
        if ( $hide && isset( $inverse[ $node['op'] ] ) ) {
            $node['op'] = $inverse[ $node['op'] ];
        } elseif ( $hide ) {
            $node = array( 'type' => 'op', 'op' => 'not', 'args' => array( $node ) );
        }
        return BAF_Expression::canonical( BAF_Expression::to_text( $node ) );
    }

    /**
     * One test: #field <op> value.
     *
     * @param string $field Field ID.
     * @param string $op    Expression operator.
     * @param mixed  $value Value (unused for empty tests).
     * @return array Tree.
     */
    public static function test( $field, $op, $value = null ) {
        $ref = array( 'type' => 'ref', 'field' => $field );
        if ( $op === 'empty' || $op === 'not_empty' ) {
            return array( 'type' => 'op', 'op' => $op, 'args' => array( $ref ) );
        }
        if ( is_string( $value ) && in_array( $op, array( 'gt', 'lt', 'ge', 'le' ), true ) && is_numeric( $value ) ) {
            $value = strpos( $value, '.' ) === false ? (int) $value : (float) $value;
        }
        return array( 'type' => 'op', 'op' => $op, 'args' => array( $ref, array( 'type' => 'literal', 'value' => is_scalar( $value ) ? $value : '' ) ) );
    }

    /**
     * A number from text such as "$1,234.50", or null.
     *
     * @param mixed $v Value.
     * @return int|float|null Number.
     */
    public static function number( $v ) {
        if ( is_int( $v ) ) {
            return $v;
        }
        if ( is_float( $v ) ) {
            return floor( $v ) == $v && abs( $v ) < 1e15 ? (int) $v : $v;
        }
        $s = preg_replace( '/[^0-9.\-]/', '', (string) $v );
        if ( $s === '' || ! is_numeric( $s ) ) {
            return null;
        }
        $n = (float) $s;
        return floor( $n ) == $n && abs( $n ) < 1e15 ? (int) $n : $n;
    }

    /**
     * Finish: put keys in order, drop conditions that name missing fields, and
     * report anything the model's own checks still find.
     *
     * @return array array( 'model' => ..., 'report' => ... ).
     */
    public function finish() {
        $order = array( 'version', 'id', 'title', 'description', 'language', 'settings', 'choice_libraries', 'pages', 'rules', 'scoring', 'confirmations', 'notifications', 'translations', 'extensions' );
        $model = array();
        foreach ( $order as $k ) {
            if ( isset( $this->model[ $k ] ) && $this->model[ $k ] !== array() ) {
                $model[ $k ] = $this->model[ $k ];
            } elseif ( $k === 'pages' || $k === 'title' || $k === 'version' ) {
                $model[ $k ] = $this->model[ $k ];
            }
        }
        foreach ( BAF_Form_Model::fields( $model ) as $id => $field ) {
            if ( ( $field['label'] ?? '' ) === '' && ! in_array( $field['type'], array( 'html', 'hidden', 'progress', 'submit', 'save' ), true ) ) {
                $this->report->note( 'field ' . $id, 'It has no label, so a screen reader can only announce its type. Give it one before you publish the form.' );
            }
        }
        foreach ( BAF_Form_Model::validate( $model ) as $e ) {
            $this->report->error( $e['path'], $e['message'] );
        }
        return array( 'model' => $model, 'report' => $this->report );
    }

    /**
     * Rewrite another builder's merge tags in BAF's {name} form.
     *
     * @param string   $text  Text.
     * @param string   $regex Pattern for the source's tags; group 1 is the tag's name.
     * @param callable $map   function( string $name ): ?string, giving BAF's tag name or null.
     * @param string   $where Report location.
     * @return string Text.
     */
    public function merge_tags( $text, $regex, $map, $where ) {
        $unknown = array();
        $out     = preg_replace_callback( $regex, function ( $m ) use ( $map, &$unknown ) {
            $name = $map( $m[1] );
            if ( $name === null ) {
                $unknown[] = $m[0];
                return $m[0];
            }
            return '{' . $name . '}';
        }, (string) $text );
        foreach ( array_unique( $unknown ) as $tag ) {
            $this->report->dropped( $where, 'The merge tag ' . $tag . ' has no BAF equivalent; it was left as it is.' );
        }
        return $out;
    }
}

/**
 * BAF_Importers class.
 */
class BAF_Importers {

    /**
     * Sources this class reads.
     *
     * @var array
     */
    const SOURCES = array(
        'gravity-forms'  => 'Gravity Forms',
        'wpforms'        => 'WPForms',
        'contact-form-7' => 'Contact Form 7',
        'ninja-forms'    => 'Ninja Forms',
        'google-forms'   => 'Google Forms',
    );

    /**
     * Import one form.
     *
     * @param string $source One of the SOURCES keys.
     * @param string $text   The export, as text.
     * @return array array( 'model' => ..., 'report' => BAF_Import_Report ).
     * @throws InvalidArgumentException For an unknown source or unreadable export.
     */
    public static function import( $source, $text ) {
        switch ( $source ) {
            case 'gravity-forms':
                return self::gravity_forms( self::decode( $text, $source ) );
            case 'wpforms':
                return self::wpforms( self::decode( $text, $source ) );
            case 'contact-form-7':
                $data = json_decode( $text, true );
                return self::contact_form_7( is_array( $data ) && isset( $data['form'] ) ? $data : array( 'form' => (string) $text ) );
            case 'ninja-forms':
                return self::ninja_forms( self::decode( $text, $source ) );
            case 'google-forms':
                return self::google_forms( self::decode( $text, $source ) );
        }
        throw new InvalidArgumentException( 'Unknown import source: ' . $source );
    }

    /**
     * Decode a JSON export.
     *
     * @param string $text   Text.
     * @param string $source Source.
     * @return array Data.
     * @throws InvalidArgumentException When it is not JSON.
     */
    private static function decode( $text, $source ) {
        $data = json_decode( (string) $text, true );
        if ( ! is_array( $data ) ) {
            throw new InvalidArgumentException( 'This is not a ' . self::SOURCES[ $source ] . ' export: it is not JSON.' );
        }
        return $data;
    }

    /**
     * Fetch a form from the Google Forms API (forms.get). Needs an OAuth
     * access token with the forms.body.readonly scope; BAF does not store it.
     *
     * @param string $form_id      The form's ID (from its edit address).
     * @param string $access_token OAuth access token.
     * @return array|WP_Error The import result, or the error.
     */
    public static function fetch_google_form( $form_id, $access_token ) {
        if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) $form_id ) ) {
            return new WP_Error( 'baf_google_form_id', 'That is not a Google Forms form ID.' );
        }
        $response = wp_remote_get( 'https://forms.googleapis.com/v1/forms/' . rawurlencode( $form_id ), array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
        ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'baf_google_forms_http', 'Google Forms answered ' . $code . '.' );
        }
        return self::import( 'google-forms', wp_remote_retrieve_body( $response ) );
    }

    // ---------------------------------------------------------------------
    // Gravity Forms
    // ---------------------------------------------------------------------

    /**
     * Gravity Forms export to the model.
     *
     * @param array $data Export ({"0": form, "version": ...} or a list of forms, or one form).
     * @return array Result.
     */
    public static function gravity_forms( $data ) {
        $forms = isset( $data['fields'] ) ? array( $data ) : array_values( array_filter( $data, function ( $f ) {
            return is_array( $f ) && isset( $f['fields'] );
        } ) );
        $form  = $forms[0] ?? array( 'title' => '', 'fields' => array() );
        $b     = new BAF_Import_Builder( 'Gravity Forms', $form['title'] ?? '' );
        if ( count( $forms ) > 1 ) {
            $b->report->dropped( 'export', 'The export holds ' . count( $forms ) . ' forms; only the first was imported.' );
        }
        if ( ! empty( $form['description'] ) ) {
            $b->model['description'] = (string) $form['description'];
        }

        $types = array(
            'text' => 'text', 'textarea' => 'textarea', 'select' => 'select', 'multiselect' => 'multiselect',
            'number' => 'number', 'checkbox' => 'checkbox', 'radio' => 'radio', 'hidden' => 'hidden', 'html' => 'html',
            'section' => 'section', 'name' => 'name', 'date' => 'date', 'time' => 'time', 'phone' => 'phone',
            'address' => 'address', 'website' => 'website', 'email' => 'email', 'fileupload' => 'fileupload',
            'captcha' => 'captcha', 'list' => 'table', 'consent' => 'consent', 'password' => 'password',
            'post_title' => 'post_title', 'post_content' => 'post_content', 'post_excerpt' => 'post_excerpt',
            'post_tags' => 'post_tags', 'post_category' => 'post_category', 'post_image' => 'post_image',
            'post_custom_field' => 'post_custom', 'product' => 'product', 'quantity' => 'quantity', 'option' => 'checkbox',
            'shipping' => 'radio', 'total' => 'total', 'creditcard' => 'payment', 'stripe_creditcard' => 'payment',
            'signature' => 'signature', 'username' => 'user_login', 'quiz' => 'quiz', 'calculation' => 'calculation',
        );
        $operators = array( 'is' => 'is', 'isnot' => 'is_not', '>' => 'gt', '<' => 'lt', 'contains' => 'contains', 'starts_with' => 'starts_with', 'ends_with' => 'ends_with' );

        // First pass: the model ID of every source field, for conditions and formulas.
        $ids = array();
        foreach ( $form['fields'] ?? array() as $f ) {
            if ( ( $f['type'] ?? '' ) !== 'page' && isset( $f['id'] ) ) {
                $ids[ (string) $f['id'] ] = $b->id( 'field_' . $f['id'] );
            }
        }
        $condition = function ( $logic, $where ) use ( $ids, $operators, $b ) {
            if ( ! is_array( $logic ) || empty( $logic['rules'] ) ) {
                return null;
            }
            $tests = array();
            foreach ( $logic['rules'] as $r ) {
                $fid = (string) ( $r['fieldId'] ?? '' );
                $op  = $operators[ $r['operator'] ?? 'is' ] ?? null;
                if ( ! isset( $ids[ $fid ] ) || $op === null ) {
                    $b->report->dropped( $where, 'A condition on field ' . $fid . ' with "' . ( $r['operator'] ?? '' ) . '" could not be read.' );
                    continue;
                }
                $value   = (string) ( $r['value'] ?? '' );
                $tests[] = ( $op === 'is' && $value === '' ) ? BAF_Import_Builder::test( $ids[ $fid ], 'empty' ) : ( ( $op === 'is_not' && $value === '' ) ? BAF_Import_Builder::test( $ids[ $fid ], 'not_empty' ) : BAF_Import_Builder::test( $ids[ $fid ], $op, $value ) );
            }
            return BAF_Import_Builder::condition( $tests, ( $logic['logicType'] ?? 'all' ) === 'any' ? 'or' : 'and', ( $logic['actionType'] ?? 'show' ) === 'hide' );
        };
        $formula = function ( $text, $where ) use ( $ids, $b ) {
            $ok  = true;
            $out = preg_replace_callback( '/\{[^{}:]*:(\d+)(\.\d+)?[^{}]*\}/', function ( $m ) use ( $ids, &$ok ) {
                if ( ! empty( $m[2] ) || ! isset( $ids[ $m[1] ] ) ) {
                    $ok = false;
                    return $m[0];
                }
                return '#' . $ids[ $m[1] ];
            }, (string) $text );
            if ( $ok && BAF_Expression::check( $out ) === null ) {
                return BAF_Expression::canonical( $out );
            }
            $b->report->dropped( $where, 'Its formula "' . $text . '" could not be read.' );
            return null;
        };

        $page_titles = $form['pagination']['pages'] ?? array();
        if ( ! empty( $page_titles[0] ) ) {
            $b->page( (string) $page_titles[0] );
        }
        $page_n = 0;
        foreach ( $form['fields'] ?? array() as $f ) {
            $gtype = (string) ( $f['type'] ?? '' );
            $where = 'field ' . ( $f['id'] ?? '?' ) . ' (' . ( $f['label'] ?? $gtype ) . ')';
            if ( $gtype === 'page' ) {
                // The page break's Next button is on the page before it.
                if ( ! empty( $f['nextButton']['text'] ) ) {
                    $b->page_set( 'props', array( 'next_button_text' => (string) $f['nextButton']['text'] ) );
                }
                $page_n++;
                $b->page( (string) ( $page_titles[ $page_n ] ?? '' ) );
                $when = $condition( $f['conditionalLogic'] ?? null, $where );
                if ( $when !== null ) {
                    $b->page_set( 'when', $when );
                }
                continue;
            }
            $input_type = (string) ( $f['inputType'] ?? '' );
            $type       = $types[ $gtype ] ?? null;
            if ( $gtype === 'survey' ) {
                $type = array( 'likert' => 'likert', 'rank' => 'ranking', 'rating' => 'rating', 'radio' => 'radio', 'checkbox' => 'checkbox', 'select' => 'select', 'text' => 'text', 'textarea' => 'textarea' )[ $input_type ] ?? null;
            }
            if ( $gtype === 'product' && $input_type !== '' ) {
                $type = array( 'calculation' => 'calculation', 'price' => 'price' )[ $input_type ] ?? 'product';
            } elseif ( in_array( $gtype, array( 'option', 'shipping', 'quiz', 'post_custom_field' ), true ) && $input_type !== '' ) {
                $sub  = array( 'singleshipping' => 'price', 'select' => 'select', 'radio' => 'radio', 'checkbox' => 'checkbox' );
                $type = $gtype === 'quiz' ? 'quiz' : ( $sub[ $input_type ] ?? $type );
            }
            if ( $type === null ) {
                $b->report->dropped( $where, 'BAF has no field like Gravity Forms\' "' . $gtype . '".' );
                continue;
            }
            $item = array( 'id' => $ids[ (string) $f['id'] ], 'type' => $type );
            if ( ( $f['label'] ?? '' ) !== '' && $type !== 'html' ) {
                $item['label'] = (string) $f['label'];
            }
            if ( ! empty( $f['description'] ) ) {
                $item['help'] = (string) $f['description'];
            }
            if ( ! empty( $f['placeholder'] ) ) {
                $item['placeholder'] = (string) $f['placeholder'];
            }
            if ( ! empty( $f['isRequired'] ) && $type !== 'section' ) {
                $item['required'] = true;
            }
            if ( isset( $f['defaultValue'] ) && $f['defaultValue'] !== '' && $type !== 'section' ) {
                $smart = array( '{date_mdy}' => 'today()', '{date_dmy}' => 'today()', '{user:display_name}' => 'user("display_name")', '{user:user_email}' => 'user("user_email")' );
                if ( isset( $smart[ $f['defaultValue'] ] ) ) {
                    $item['smart_default'] = $smart[ $f['defaultValue'] ];
                } elseif ( strpos( (string) $f['defaultValue'], '{' ) !== false ) {
                    $b->report->dropped( $where, 'Its default "' . $f['defaultValue'] . '" is a Gravity Forms merge tag BAF does not have.' );
                } else {
                    $item['default'] = (string) $f['defaultValue'];
                }
            }
            if ( ! empty( $f['inputMask'] ) && ! empty( $f['inputMaskValue'] ) ) {
                $item['mask'] = (string) $f['inputMaskValue'];
            }
            $when = $condition( $f['conditionalLogic'] ?? null, $where );
            if ( $when !== null ) {
                $item['when'] = $when;
            }
            if ( ! empty( $f['enableCalculation'] ) || $type === 'calculation' ) {
                $calc = $formula( $f['calculationFormula'] ?? '', $where );
                if ( $calc !== null ) {
                    $item['calculation'] = $calc;
                    if ( $type === 'number' ) {
                        $item['type'] = 'calculation';
                        $b->report->changed( $where, 'A number with a calculation is a calculation field.' );
                    }
                } elseif ( $type === 'calculation' ) {
                    $item['type'] = 'number';
                }
            }
            if ( ! empty( $f['choices'] ) && is_array( $f['choices'] ) && $type !== 'section' ) {
                $correct = array();
                $choices = array();
                foreach ( $f['choices'] as $c ) {
                    $label  = (string) ( $c['text'] ?? '' );
                    $choice = array( 'value' => (string) ( ( $c['value'] ?? '' ) !== '' ? $c['value'] : $label ), 'label' => $label );
                    $price  = isset( $c['price'] ) && $c['price'] !== '' ? BAF_Import_Builder::number( $c['price'] ) : null;
                    if ( $price !== null ) {
                        $choice['price'] = $price;
                    }
                    if ( ! empty( $c['isSelected'] ) ) {
                        $choice['selected'] = true;
                    }
                    if ( isset( $c['gquizWeight'] ) && is_numeric( $c['gquizWeight'] ) ) {
                        $choice['points'] = BAF_Import_Builder::number( $c['gquizWeight'] );
                    }
                    if ( ! empty( $c['gquizIsCorrect'] ) ) {
                        $correct[] = $choice['value'];
                    }
                    $choices[] = $choice;
                }
                if ( $type === 'likert' || $type === 'table' ) {
                    $item['props']['columns'] = $choices;
                } else {
                    $item['choices'] = $choices;
                }
                if ( $correct ) {
                    $item['scoring'] = array( 'correct' => $correct );
                }
            }
            if ( $type === 'likert' && ! empty( $f['inputs'] ) ) {
                $rows = array();
                foreach ( $f['inputs'] as $in ) {
                    $rows[] = array( 'value' => (string) ( $in['name'] ?? $in['id'] ), 'label' => (string) ( $in['label'] ?? '' ) );
                }
                $item['props']['rows'] = $rows;
            } elseif ( $type === 'likert' ) {
                $item['props']['rows'] = array( array( 'value' => 'row_1', 'label' => $item['label'] ?? '' ) );
            }
            if ( $type === 'quiz' && ! isset( $item['scoring'] ) ) {
                $item['scoring'] = array( 'correct' => array() );
                $b->report->changed( $where, 'The quiz question has no correct answer marked.' );
            }
            if ( in_array( $item['type'], BAF_Form_Model::CHOICE_TYPES, true ) && empty( $item['choices'] ) ) {
                $item['choices'] = array( array( 'value' => 'yes', 'label' => 'Yes' ) );
                $b->report->changed( $where, 'It had no choices; it got the choice yes|Yes.' );
            }
            if ( isset( $item['scoring'] ) && isset( $f['gquizAnswerExplanation'] ) && $f['gquizAnswerExplanation'] !== '' ) {
                $item['scoring']['explanation'] = (string) $f['gquizAnswerExplanation'];
            }
            $v = array();
            if ( isset( $f['maxLength'] ) && is_numeric( $f['maxLength'] ) && (int) $f['maxLength'] > 0 ) {
                $v['max_length'] = (int) $f['maxLength'];
            }
            foreach ( array( 'rangeMin' => 'min', 'rangeMax' => 'max' ) as $k => $to ) {
                if ( isset( $f[ $k ] ) && $f[ $k ] !== '' && is_numeric( $f[ $k ] ) ) {
                    $v[ $to ] = BAF_Import_Builder::number( $f[ $k ] );
                }
            }
            if ( ! empty( $f['noDuplicates'] ) ) {
                $v['unique'] = true;
            }
            if ( $v ) {
                $item['validation'] = $v;
            }
            if ( $type === 'html' && isset( $f['content'] ) ) {
                $item['props']['content'] = (string) $f['content'];
            }
            if ( $type === 'consent' && ! empty( $f['checkboxLabel'] ) ) {
                $item['props']['consent_text'] = (string) $f['checkboxLabel'];
            }
            if ( ! empty( $f['cssClass'] ) ) {
                $item['props']['css_class'] = (string) $f['cssClass'];
            }
            if ( $type === 'fileupload' ) {
                if ( ! empty( $f['allowedExtensions'] ) ) {
                    $item['props']['accept'] = '.' . implode( ',.', array_map( 'trim', explode( ',', $f['allowedExtensions'] ) ) );
                }
                if ( ! empty( $f['maxFileSize'] ) ) {
                    $item['props']['max_size'] = BAF_Import_Builder::number( $f['maxFileSize'] );
                }
                if ( ! empty( $f['multipleFiles'] ) ) {
                    $item['props']['max_files'] = (int) ( $f['maxFiles'] ?? 0 ) ?: 10;
                }
            }
            if ( ( $type === 'name' || $type === 'address' ) && ! empty( $f['inputs'] ) ) {
                $b->report->changed( $where, 'Its parts (first name, city and so on) are BAF\'s own ' . $type . ' parts.' );
            }
            if ( $type === 'section' ) {
                unset( $item['placeholder'] );
            }
            $b->add( self::order( $item ), $type === 'section' );
        }

        // Form settings.
        $settings = array();
        if ( ! empty( $form['button']['text'] ) ) {
            $settings['submit_label'] = (string) $form['button']['text'];
        }
        $map = array( 'enableHoneypot' => 'enable_honeypot', 'limitEntries' => 'entry_limit_enabled', 'limitEntriesCount' => 'entry_limit_count', 'limitEntriesMessage' => 'entry_limit_message', 'scheduleForm' => 'schedule_enabled', 'scheduleStart' => 'schedule_start', 'scheduleEnd' => 'schedule_end', 'scheduleMessage' => 'schedule_message', 'requireLogin' => 'require_login', 'requireLoginMessage' => 'require_login_message' );
        foreach ( $map as $k => $to ) {
            if ( isset( $form[ $k ] ) && $form[ $k ] !== '' && $form[ $k ] !== false && $form[ $k ] !== null ) {
                $settings[ $to ] = is_numeric( $form[ $k ] ) && ! is_bool( $form[ $k ] ) && $k !== 'scheduleStart' && $k !== 'scheduleEnd' ? BAF_Import_Builder::number( $form[ $k ] ) : $form[ $k ];
            }
        }
        if ( ! empty( $form['save']['enabled'] ) ) {
            $settings['save_continue_enabled'] = true;
        }
        if ( $settings ) {
            $b->model['settings'] = $settings;
        }

        $i = 0;
        foreach ( (array) ( $form['confirmations'] ?? array() ) as $c ) {
            $i++;
            $type = in_array( $c['type'] ?? 'message', array( 'message', 'page', 'redirect' ), true ) ? $c['type'] : 'message';
            $conf = array( 'id' => 'confirmation_' . $i, 'type' => $type );
            if ( $type === 'message' ) {
                $conf['message'] = (string) ( $c['message'] ?? '' );
            } elseif ( $type === 'redirect' ) {
                $conf['url'] = (string) ( $c['url'] ?? '' );
            } else {
                $conf['page_id'] = (int) ( $c['pageId'] ?? 0 );
            }
            $when = $condition( $c['conditionalLogic'] ?? null, 'confirmation ' . ( $c['name'] ?? $i ) );
            if ( $when !== null ) {
                $conf['when'] = $when;
            }
            $b->model['confirmations'][] = $conf;
        }
        $i = 0;
        foreach ( (array) ( $form['notifications'] ?? array() ) as $n ) {
            $i++;
            $note = array( 'id' => 'notification_' . $i, 'enabled' => ! isset( $n['isActive'] ) || (bool) $n['isActive'] );
            if ( ( $n['toType'] ?? 'email' ) === 'routing' ) {
                $b->report->dropped( 'notification ' . ( $n['name'] ?? $i ), 'Its routing rules (send to different people by answer) have no place in the model.' );
            }
            foreach ( array( 'to' => 'to', 'cc' => 'cc', 'bcc' => 'bcc', 'fromName' => 'from_name', 'from' => 'from_email', 'replyTo' => 'reply_to', 'subject' => 'subject', 'message' => 'message' ) as $k => $to ) {
                if ( isset( $n[ $k ] ) && $n[ $k ] !== '' ) {
                    $note[ $to ] = (string) $n[ $k ];
                }
            }
            $when = $condition( $n['conditionalLogic'] ?? null, 'notification ' . ( $n['name'] ?? $i ) );
            if ( $when !== null ) {
                $note['when'] = $when;
            }
            $b->model['notifications'][] = $note;
        }
        $gf_tag = function ( $name ) use ( $ids ) {
            if ( preg_match( '/:(\d+)$/', $name, $m ) ) {
                return $ids[ $m[1] ] ?? null;
            }
            $same = array( 'all_fields', 'admin_email', 'form_title', 'entry_id', 'entry_url', 'date_mdy' => 'date', 'ip', 'user_agent', 'post_id', 'post_title' );
            foreach ( $same as $k => $v ) {
                if ( $name === ( is_int( $k ) ? $v : $k ) ) {
                    return $v;
                }
            }
            return null;
        };
        self::retag( $b, '/\{([^{}]+)\}/', $gf_tag );
        return $b->finish();
    }

    /**
     * Rewrite merge tags in every confirmation and notification.
     *
     * @param BAF_Import_Builder $b     Builder.
     * @param string             $regex Tag pattern.
     * @param callable           $map   Tag name map.
     */
    private static function retag( $b, $regex, $map ) {
        foreach ( array( 'confirmations' => array( 'message', 'url' ), 'notifications' => array( 'to', 'cc', 'bcc', 'from_name', 'from_email', 'reply_to', 'subject', 'message' ) ) as $list => $keys ) {
            foreach ( $b->model[ $list ] ?? array() as $i => $obj ) {
                foreach ( $keys as $k ) {
                    if ( isset( $obj[ $k ] ) ) {
                        $b->model[ $list ][ $i ][ $k ] = $b->merge_tags( $obj[ $k ], $regex, $map, rtrim( $list, 's' ) . ' ' . $obj['id'] );
                    }
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // WPForms
    // ---------------------------------------------------------------------

    /**
     * WPForms export to the model.
     *
     * @param array $data Export (a list of forms, or one form).
     * @return array Result.
     */
    public static function wpforms( $data ) {
        $forms = isset( $data['fields'] ) ? array( $data ) : array_values( array_filter( $data, function ( $f ) {
            return is_array( $f ) && isset( $f['fields'] );
        } ) );
        $form     = $forms[0] ?? array( 'fields' => array(), 'settings' => array() );
        $settings = $form['settings'] ?? array();
        $b        = new BAF_Import_Builder( 'WPForms', $settings['form_title'] ?? '' );
        if ( count( $forms ) > 1 ) {
            $b->report->dropped( 'export', 'The export holds ' . count( $forms ) . ' forms; only the first was imported.' );
        }
        if ( ! empty( $settings['form_desc'] ) ) {
            $b->model['description'] = (string) $settings['form_desc'];
        }

        $types = array(
            'text' => 'text', 'textarea' => 'textarea', 'select' => 'select', 'radio' => 'radio', 'checkbox' => 'checkbox',
            'number' => 'number', 'name' => 'name', 'email' => 'email', 'number-slider' => 'slider', 'phone' => 'phone',
            'address' => 'address', 'date-time' => 'datetime', 'url' => 'website', 'file-upload' => 'fileupload',
            'password' => 'password', 'divider' => 'section', 'html' => 'html', 'content' => 'html', 'rating' => 'rating',
            'hidden' => 'hidden', 'captcha' => 'captcha', 'likert_scale' => 'likert', 'net_promoter_score' => 'nps',
            'signature' => 'signature', 'payment-single' => 'price', 'payment-multiple' => 'product',
            'payment-checkbox' => 'product', 'payment-select' => 'product', 'payment-total' => 'total',
            'richtext' => 'rich_text', 'entry-preview' => 'summary', 'gdpr-checkbox' => 'consent',
            'stripe-credit-card' => 'payment', 'authorize_net' => 'payment', 'square' => 'payment', 'paypal-commerce' => 'payment',
            'internal-information' => 'html', 'camera' => 'fileupload',
        );
        $operators = array( '==' => 'is', '!=' => 'is_not', 'e' => 'empty', '!e' => 'not_empty', 'c' => 'contains', '!c' => 'not_contains', '^' => 'starts_with', '~' => 'ends_with', '>' => 'gt', '<' => 'lt' );

        $fields = array_values( (array) ( $form['fields'] ?? array() ) );
        $ids     = array();
        $choices = array();
        foreach ( $fields as $f ) {
            if ( isset( $f['id'] ) && ( $f['type'] ?? '' ) !== 'pagebreak' ) {
                $ids[ (string) $f['id'] ] = $b->id( 'field_' . $f['id'] );
            }
            foreach ( (array) ( $f['choices'] ?? array() ) as $key => $c ) {
                $choices[ (string) ( $f['id'] ?? '' ) ][ (string) $key ] = (string) ( ( $c['value'] ?? '' ) !== '' ? $c['value'] : ( $c['label'] ?? '' ) );
            }
        }
        $condition = function ( $f, $where ) use ( $ids, $operators, $choices, $b ) {
            if ( empty( $f['conditional_logic'] ) || empty( $f['conditionals'] ) ) {
                return null;
            }
            $groups = array();
            foreach ( (array) $f['conditionals'] as $group ) {
                $tests = array();
                foreach ( (array) $group as $r ) {
                    $fid = (string) ( $r['field'] ?? '' );
                    $op  = $operators[ $r['operator'] ?? '==' ] ?? null;
                    if ( ! isset( $ids[ $fid ] ) || $op === null ) {
                        $b->report->dropped( $where, 'A condition on field ' . $fid . ' with "' . ( $r['operator'] ?? '' ) . '" could not be read.' );
                        continue;
                    }
                    $value   = (string) ( $r['value'] ?? '' );
                    $value   = $choices[ $fid ][ $value ] ?? $value; // Choice conditions name the choice's key.
                    $tests[] = BAF_Import_Builder::test( $ids[ $fid ], $op, $value );
                }
                if ( $tests ) {
                    $groups[] = count( $tests ) === 1 ? $tests[0] : array( 'type' => 'op', 'op' => 'and', 'args' => $tests );
                }
            }
            return BAF_Import_Builder::condition( $groups, 'or', ( $f['conditional_type'] ?? 'show' ) === 'hide' );
        };

        foreach ( $fields as $f ) {
            $wtype = (string) ( $f['type'] ?? '' );
            $where = 'field ' . ( $f['id'] ?? '?' ) . ' (' . ( $f['label'] ?? $wtype ) . ')';
            if ( $wtype === 'pagebreak' ) {
                $position = $f['position'] ?? '';
                if ( $position === 'top' ) {
                    if ( ! empty( $f['title'] ) ) {
                        $b->page( (string) $f['title'] );
                    }
                } elseif ( $position !== 'bottom' ) {
                    // The break's Next button is on the page before it.
                    if ( ! empty( $f['next'] ) ) {
                        $b->page_set( 'props', array( 'next_button_text' => (string) $f['next'] ) );
                    }
                    $b->page( (string) ( $f['title'] ?? '' ) );
                }
                continue;
            }
            $type = $types[ $wtype ] ?? null;
            if ( $type === null ) {
                $b->report->dropped( $where, 'BAF has no field like WPForms\' "' . $wtype . '".' );
                continue;
            }
            $item = array( 'id' => $ids[ (string) $f['id'] ], 'type' => $type );
            if ( ( $f['label'] ?? '' ) !== '' && $type !== 'html' ) {
                $item['label'] = (string) $f['label'];
            }
            if ( ! empty( $f['description'] ) ) {
                $item['help'] = (string) $f['description'];
            }
            if ( ! empty( $f['placeholder'] ) ) {
                $item['placeholder'] = (string) $f['placeholder'];
            }
            if ( ! empty( $f['required'] ) && $type !== 'section' && $type !== 'html' ) {
                $item['required'] = true;
            }
            if ( isset( $f['default_value'] ) && $f['default_value'] !== '' && $type !== 'section' ) {
                $item['default'] = (string) $f['default_value'];
            }
            if ( isset( $f['input_mask'] ) && $f['input_mask'] !== '' ) {
                $item['mask'] = (string) $f['input_mask'];
            }
            $when = $condition( $f, $where );
            if ( $when !== null ) {
                $item['when'] = $when;
            }
            if ( ! empty( $f['choices'] ) && $type !== 'likert' ) {
                $list = array();
                foreach ( (array) $f['choices'] as $c ) {
                    $label  = (string) ( $c['label'] ?? '' );
                    $choice = array( 'value' => (string) ( ( $c['value'] ?? '' ) !== '' && strpos( $wtype, 'payment' ) !== 0 ? $c['value'] : $label ), 'label' => $label );
                    if ( strpos( $wtype, 'payment' ) === 0 && isset( $c['value'] ) && BAF_Import_Builder::number( $c['value'] ) !== null ) {
                        $choice['price'] = BAF_Import_Builder::number( $c['value'] );
                    }
                    if ( ! empty( $c['image'] ) ) {
                        $choice['image'] = (string) $c['image'];
                    }
                    if ( ! empty( $c['default'] ) ) {
                        $choice['selected'] = true;
                    }
                    $list[] = $choice;
                }
                $item['choices'] = $list;
                if ( ! empty( $f['choices_images'] ) && in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
                    $item['props']['multiple'] = $type === 'checkbox';
                    $item['type']              = 'image_choice';
                    $b->report->changed( $where, 'Choices with pictures are an image choice field.' );
                }
            }
            if ( $type === 'likert' ) {
                $rows = array();
                foreach ( (array) ( $f['rows'] ?? array() ) as $k => $label ) {
                    $rows[] = array( 'value' => (string) $k, 'label' => (string) $label );
                }
                $cols = array();
                foreach ( (array) ( $f['columns'] ?? array() ) as $k => $label ) {
                    $cols[] = array( 'value' => (string) $k, 'label' => (string) $label );
                }
                $item['props'] = array( 'rows' => $rows ?: array( array( 'value' => 'row_1', 'label' => $item['label'] ?? '' ) ), 'columns' => $cols );
            }
            if ( in_array( $item['type'], BAF_Form_Model::CHOICE_TYPES, true ) && empty( $item['choices'] ) ) {
                $item['choices'] = array( array( 'value' => 'yes', 'label' => 'Yes' ) );
                $b->report->changed( $where, 'It had no choices; it got the choice yes|Yes.' );
            }
            $v = array();
            if ( ! empty( $f['limit_enabled'] ) && ( $f['limit_mode'] ?? 'characters' ) === 'characters' && ! empty( $f['limit_count'] ) ) {
                $v['max_length'] = (int) $f['limit_count'];
            }
            foreach ( array( 'min' => 'min', 'max' => 'max' ) as $k => $to ) {
                if ( isset( $f[ $k ] ) && $f[ $k ] !== '' && is_numeric( $f[ $k ] ) ) {
                    $v[ $to ] = BAF_Import_Builder::number( $f[ $k ] );
                }
            }
            if ( ! empty( $f['unique_answer'] ) ) {
                $v['unique'] = true;
            }
            if ( $v ) {
                $item['validation'] = $v;
            }
            if ( $type === 'html' ) {
                $item['props']['content'] = (string) ( $f['code'] ?? $f['content'] ?? $f['description'] ?? '' );
                unset( $item['help'] );
            }
            if ( $type === 'name' && ! empty( $f['format'] ) ) {
                $item['props']['format'] = array( 'simple' => 'full', 'first-last' => 'first_last', 'first-middle-last' => 'first_middle_last' )[ $f['format'] ] ?? (string) $f['format'];
            }
            if ( $type === 'consent' && ! empty( $f['choices'] ) ) {
                $item['props']['consent_text'] = (string) ( array_values( (array) $f['choices'] )[0]['label'] ?? '' );
                unset( $item['choices'] );
            }
            if ( $type === 'fileupload' ) {
                if ( ! empty( $f['extensions'] ) ) {
                    $item['props']['accept'] = '.' . implode( ',.', array_map( 'trim', explode( ',', $f['extensions'] ) ) );
                }
                if ( ! empty( $f['max_size'] ) ) {
                    $item['props']['max_size'] = BAF_Import_Builder::number( $f['max_size'] );
                }
                if ( ! empty( $f['max_file_number'] ) ) {
                    $item['props']['max_files'] = (int) $f['max_file_number'];
                }
            }
            if ( $type === 'price' && isset( $f['price'] ) ) {
                $item['props']['base_price'] = BAF_Import_Builder::number( $f['price'] );
            }
            if ( $type === 'rating' && ! empty( $f['scale'] ) ) {
                $item['props']['max_rating'] = (int) $f['scale'];
            }
            if ( ! empty( $f['css'] ) ) {
                $item['props']['css_class'] = (string) $f['css'];
            }
            $b->add( self::order( $item ), $type === 'section' );
        }

        $s = array();
        if ( ! empty( $settings['submit_text'] ) ) {
            $s['submit_label'] = (string) $settings['submit_text'];
        }
        if ( isset( $settings['antispam'] ) ) {
            $s['enable_honeypot'] = (bool) $settings['antispam'];
        }
        if ( isset( $settings['ajax_submit'] ) ) {
            $s['ajax_submission'] = (bool) $settings['ajax_submit'];
        }
        if ( $s ) {
            $b->model['settings'] = $s;
        }
        $i = 0;
        foreach ( (array) ( $settings['confirmations'] ?? array() ) as $c ) {
            $i++;
            $type = array( 'message' => 'message', 'page' => 'page', 'redirect' => 'redirect' )[ $c['type'] ?? 'message' ] ?? 'message';
            $conf = array( 'id' => 'confirmation_' . $i, 'type' => $type );
            if ( $type === 'message' ) {
                $conf['message'] = (string) ( $c['message'] ?? '' );
            } elseif ( $type === 'page' ) {
                $conf['page_id'] = (int) ( $c['page'] ?? 0 );
            } else {
                $conf['url'] = (string) ( $c['redirect'] ?? '' );
            }
            $when = $condition( $c, 'confirmation ' . $i );
            if ( $when !== null ) {
                $conf['when'] = $when;
            }
            $b->model['confirmations'][] = $conf;
        }
        $i = 0;
        foreach ( (array) ( $settings['notifications'] ?? array() ) as $n ) {
            $i++;
            $note = array( 'id' => 'notification_' . $i );
            foreach ( array( 'email' => 'to', 'carboncopy' => 'cc', 'sender_name' => 'from_name', 'sender_address' => 'from_email', 'replyto' => 'reply_to', 'subject' => 'subject', 'message' => 'message' ) as $k => $to ) {
                if ( isset( $n[ $k ] ) && $n[ $k ] !== '' ) {
                    $note[ $to ] = (string) $n[ $k ];
                }
            }
            $when = $condition( $n, 'notification ' . $i );
            if ( $when !== null ) {
                $note['when'] = $when;
            }
            $b->model['notifications'][] = $note;
        }
        self::retag( $b, '/\{([^{}]+)\}/', function ( $name ) use ( $ids ) {
            if ( preg_match( '/^field_id="(\d+)"$/', $name, $m ) ) {
                return $ids[ $m[1] ] ?? null;
            }
            return in_array( $name, array( 'all_fields', 'admin_email', 'form_title', 'entry_id', 'date', 'user_email', 'user_id', 'site_name', 'site_url', 'post_id', 'post_title' ), true ) ? $name : null;
        } );
        return $b->finish();
    }

    // ---------------------------------------------------------------------
    // Contact Form 7
    // ---------------------------------------------------------------------

    /**
     * Contact Form 7 form template (and mail and messages, if given) to the model.
     *
     * @param array $data Keys: form (the template), title, mail, messages.
     * @return array Result.
     */
    public static function contact_form_7( $data ) {
        $b        = new BAF_Import_Builder( 'Contact Form 7', $data['title'] ?? '' );
        $template = str_replace( array( "\r\n", "\r" ), "\n", (string) $data['form'] );
        $types    = array(
            'text' => 'text', 'email' => 'email', 'url' => 'website', 'tel' => 'phone', 'number' => 'number',
            'range' => 'range', 'date' => 'date', 'textarea' => 'textarea', 'select' => 'select', 'checkbox' => 'checkbox',
            'radio' => 'radio', 'acceptance' => 'consent', 'quiz' => 'text', 'file' => 'fileupload', 'submit' => 'submit',
            'hidden' => 'hidden',
        );

        // Split into tags and the text between them; [acceptance] ... [/acceptance] holds its text.
        preg_match_all( '/\[(\w+)(\*?)((?:[^\]"]|"[^"]*")*)\](?:([^\[]*)\[\/\1\])?/', $template, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
        $pos     = 0;
        $pending = '';
        foreach ( $m as $tag ) {
            $before   = substr( $template, $pos, $tag[0][1] - $pos );
            $pos      = $tag[0][1] + strlen( $tag[0][0] );
            $pending .= $before;
            $cf7type  = $tag[1][0];
            $where    = '[' . $cf7type . ']';

            // The label is the text since the last block break, or inside an open <label>.
            $open  = strripos( $pending, '<label' );
            $close = strripos( $pending, '</label>' );
            if ( $open !== false && ( $close === false || $close < $open ) ) {
                $label_html = substr( $pending, $open );
                $rest       = substr( $pending, 0, $open );
            } else {
                // Otherwise the last piece of text since the previous field, between
                // paragraph and line breaks: "<p>Your name<br />[text your-name]".
                $parts = preg_split( '/(<\/?p[^>]*>|<br\s*\/?>|\n\s*\n|<\/label>)/i', $pending, -1, PREG_SPLIT_OFFSET_CAPTURE );
                for ( $k = count( $parts ) - 1; $k >= 0; $k-- ) {
                    if ( trim( strip_tags( $parts[ $k ][0] ) ) !== '' ) {
                        break;
                    }
                }
                $label_html = $k >= 0 ? substr( $pending, $parts[ $k ][1] ) : '';
                $rest       = $k >= 0 ? substr( $pending, 0, $parts[ $k ][1] ) : $pending;
            }
            $label = trim( html_entity_decode( strip_tags( $label_html ), ENT_QUOTES, 'UTF-8' ) );
            $label = trim( preg_replace( '/\s*\((required|optional)\)\s*$/i', '', $label ) );
            self::cf7_text( $b, $rest );
            $pending = '';

            $type = $types[ $cf7type ] ?? null;
            if ( $type === null ) {
                $b->report->dropped( $where, 'BAF has no field like Contact Form 7\'s [' . $cf7type . '].' );
                continue;
            }
            // Options and quoted values.
            preg_match_all( '/"([^"]*)"|(\S+)/', trim( $tag[3][0] ), $om, PREG_SET_ORDER );
            $name    = null;
            $options = array();
            $values  = array();
            foreach ( $om as $o ) {
                if ( isset( $o[2] ) && $o[2] !== '' ) {
                    if ( $name === null && $cf7type !== 'submit' ) {
                        $name = $o[2];
                    } else {
                        $options[] = $o[2];
                    }
                } else {
                    $values[] = $o[1];
                }
            }
            $item = array( 'id' => $b->id( $name ?? $cf7type ), 'type' => $type );
            if ( $name !== null && $item['id'] !== $name ) {
                $b->report->changed( $where, 'Its name "' . $name . '" became the ID "' . $item['id'] . '".' );
            }
            if ( $label !== '' && $type !== 'submit' ) {
                $item['label'] = $label;
            }
            if ( $tag[2][0] === '*' || ( $cf7type === 'acceptance' && ! in_array( 'optional', $options, true ) ) ) {
                $item['required'] = true;
            }
            $placeholder = in_array( 'placeholder', $options, true ) || in_array( 'watermark', $options, true );
            $default_idx = null;
            foreach ( $options as $opt ) {
                if ( strpos( $opt, ':' ) === false ) {
                    if ( preg_match( '/^(\d+)x(\d+)$/', $opt, $mm ) ) {
                        $item['props']['cols'] = (int) $mm[1];
                        $item['props']['rows'] = (int) $mm[2];
                    } elseif ( $opt === 'multiple' && $type === 'select' ) {
                        $item['type'] = 'multiselect';
                    } elseif ( in_array( $opt, array( 'include_blank', 'use_label_element', 'label_first', 'exclusive', 'free_text', 'optional', 'placeholder', 'watermark', 'invert' ), true ) ) {
                        if ( $opt === 'free_text' ) {
                            $item['props']['allow_other'] = true;
                        } elseif ( $opt === 'include_blank' || $opt === 'exclusive' ) {
                            $item['props'][ $opt ] = true;
                        }
                    } else {
                        $b->report->dropped( $where, 'The option "' . $opt . '" has no place in the model.' );
                    }
                    continue;
                }
                list( $k, $val ) = explode( ':', $opt, 2 );
                switch ( $k ) {
                    case 'min':
                    case 'max':
                        $item['validation'][ $k ] = BAF_Import_Builder::number( $val ) ?? $val;
                        break;
                    case 'minlength':
                    case 'maxlength':
                        $item['validation'][ $k === 'minlength' ? 'min_length' : 'max_length' ] = (int) $val;
                        break;
                    case 'step':
                        $item['props']['step'] = BAF_Import_Builder::number( $val );
                        break;
                    case 'class':
                        $item['props']['css_class'] = isset( $item['props']['css_class'] ) ? $item['props']['css_class'] . ' ' . $val : $val;
                        break;
                    case 'autocomplete':
                        $item['props']['autocomplete'] = $val;
                        break;
                    case 'filetypes':
                        $item['props']['accept'] = '.' . str_replace( '|', ',.', $val );
                        break;
                    case 'limit':
                        $item['props']['max_size'] = $val;
                        break;
                    case 'default':
                        if ( $val === 'user_display_name' || $val === 'user_email' || $val === 'user_login' ) {
                            $item['smart_default'] = 'user(' . BAF_Expression::quote( $val === 'user_display_name' ? 'display_name' : $val ) . ')';
                        } elseif ( $val === 'get' || $val === 'post' ) {
                            $item['smart_default'] = 'query(' . BAF_Expression::quote( $name ) . ')';
                        } elseif ( ctype_digit( str_replace( '_', '', $val ) ) ) {
                            $default_idx = array_map( 'intval', explode( '_', $val ) );
                        } else {
                            $b->report->dropped( $where, 'Its default "' . $val . '" has no place in the model.' );
                        }
                        break;
                    default:
                        $b->report->dropped( $where, 'The option "' . $opt . '" has no place in the model.' );
                }
            }
            // Values: choices, a starting value, a placeholder, the submit label.
            if ( in_array( $type, array( 'select', 'checkbox', 'radio' ), true ) ) {
                $choices = array();
                foreach ( $values as $n => $v ) {
                    $parts  = explode( '|', $v, 2 );
                    $choice = array( 'value' => count( $parts ) === 2 ? $parts[1] : $v, 'label' => $parts[0] );
                    if ( $default_idx && in_array( $n + 1, $default_idx, true ) ) {
                        $choice['selected'] = true;
                    }
                    $choices[] = $choice;
                }
                $item['choices'] = $choices ?: array( array( 'value' => 'yes', 'label' => 'Yes' ) );
            } elseif ( $type === 'submit' ) {
                $item['label'] = $values[0] ?? 'Send';
            } elseif ( $cf7type === 'quiz' ) {
                $pairs = array_map( function ( $v ) {
                    return explode( '|', $v, 2 );
                }, $values );
                if ( $pairs ) {
                    $item['label']   = $pairs[0][0];
                    $item['scoring'] = array( 'correct' => array( $pairs[0][1] ?? '' ) );
                    if ( count( $pairs ) > 1 ) {
                        $b->report->dropped( $where, 'Contact Form 7 asks one of ' . count( $pairs ) . ' quiz questions at random; only the first was kept.' );
                    }
                }
                $item['required'] = true;
            } elseif ( $cf7type === 'acceptance' ) {
                $item['props']['consent_text'] = trim( strip_tags( $tag[4][0] ?? '' ) );
            } elseif ( $values ) {
                if ( $placeholder ) {
                    $item['placeholder'] = $values[0];
                } else {
                    $item['default'] = $values[0];
                }
            }
            $b->add( self::order( $item ) );
        }
        self::cf7_text( $b, $pending . substr( $template, $pos ) );

        // Mail and messages, when the export has them.
        if ( ! empty( $data['mail'] ) && is_array( $data['mail'] ) ) {
            $b->model['notifications'][] = self::cf7_mail( $data['mail'], 'notification_1' );
            if ( ! empty( $data['mail_2']['active'] ) ) {
                $b->model['notifications'][] = self::cf7_mail( $data['mail_2'], 'notification_2' );
            }
        }
        if ( ! empty( $data['messages']['mail_sent_ok'] ) ) {
            $b->model['confirmations'][] = array( 'id' => 'confirmation_1', 'type' => 'message', 'message' => (string) $data['messages']['mail_sent_ok'] );
        }
        foreach ( (array) ( $data['messages'] ?? array() ) as $k => $text ) {
            if ( $k !== 'mail_sent_ok' && $text !== '' ) {
                $b->report->dropped( 'message ' . $k, 'BAF writes its own messages; "' . $text . '" was not kept.' );
            }
        }
        if ( ! empty( $data['additional_settings'] ) ) {
            $b->report->dropped( 'additional settings', 'Contact Form 7\'s additional settings have no place in the model.' );
        }
        $names = array();
        foreach ( BAF_Form_Model::fields( $b->model ) as $id => $unused ) {
            $names[ $id ] = true;
        }
        self::retag( $b, '/\[([A-Za-z_][A-Za-z0-9_-]*)\]/', function ( $name ) use ( $names ) {
            $special = array( '_site_title' => 'site_name', '_site_url' => 'site_url', '_site_admin_email' => 'admin_email', '_date' => 'date', '_time' => 'time', '_remote_ip' => 'ip', '_user_agent' => 'user_agent', '_post_id' => 'post_id', '_post_title' => 'post_title', '_user_email' => 'user_email', '_user_display_name' => 'user_display_name' );
            if ( isset( $special[ $name ] ) ) {
                return $special[ $name ];
            }
            $id = str_replace( '-', '_', $name );
            return isset( $names[ $id ] ) ? $id : null;
        } );
        return $b->finish();
    }

    /**
     * Text between Contact Form 7 tags: kept as an html field when it says something.
     *
     * @param BAF_Import_Builder $b    Builder.
     * @param string             $html Text.
     */
    private static function cf7_text( $b, $html ) {
        $clean = trim( preg_replace( '/<\/?(label|p|br)[^>]*>/i', ' ', $html ) );
        if ( trim( strip_tags( $clean ) ) === '' ) {
            return;
        }
        $b->add( array( 'id' => $b->id( 'text' ), 'type' => 'html', 'props' => array( 'content' => trim( $html ) ) ) );
    }

    /**
     * A Contact Form 7 mail setting to a notification.
     *
     * @param array  $mail Mail.
     * @param string $id   ID.
     * @return array Notification.
     */
    private static function cf7_mail( $mail, $id ) {
        $n = array( 'id' => $id, 'enabled' => true );
        if ( ! empty( $mail['recipient'] ) ) {
            $n['to'] = (string) $mail['recipient'];
        }
        if ( ! empty( $mail['sender'] ) ) {
            if ( preg_match( '/^\s*(.*?)\s*<([^>]+)>\s*$/', $mail['sender'], $m ) ) {
                if ( $m[1] !== '' ) {
                    $n['from_name'] = $m[1];
                }
                $n['from_email'] = $m[2];
            } else {
                $n['from_email'] = (string) $mail['sender'];
            }
        }
        if ( ! empty( $mail['additional_headers'] ) && preg_match( '/^Reply-To:\s*(.+)$/mi', $mail['additional_headers'], $m ) ) {
            $n['reply_to'] = trim( $m[1] );
        }
        if ( ! empty( $mail['subject'] ) ) {
            $n['subject'] = (string) $mail['subject'];
        }
        if ( ! empty( $mail['body'] ) ) {
            $n['message'] = (string) $mail['body'];
        }
        return $n;
    }

    // ---------------------------------------------------------------------
    // Ninja Forms
    // ---------------------------------------------------------------------

    /**
     * Ninja Forms .nff export to the model.
     *
     * @param array $data Export.
     * @return array Result.
     */
    public static function ninja_forms( $data ) {
        $settings = $data['settings'] ?? array();
        $b        = new BAF_Import_Builder( 'Ninja Forms', $settings['title'] ?? '' );
        $types    = array(
            'textbox' => 'text', 'textarea' => 'textarea', 'email' => 'email', 'phone' => 'phone', 'number' => 'number',
            'date' => 'date', 'firstname' => 'text', 'lastname' => 'text', 'address' => 'text', 'address2' => 'text',
            'city' => 'text', 'zip' => 'text', 'liststate' => 'select', 'listcountry' => 'select', 'listselect' => 'select',
            'listradio' => 'radio', 'listcheckbox' => 'checkbox', 'listmultiselect' => 'multiselect', 'checkbox' => 'checkbox',
            'hidden' => 'hidden', 'html' => 'html', 'hr' => 'html', 'submit' => 'submit', 'starrating' => 'rating',
            'recaptcha' => 'captcha', 'spam' => 'captcha', 'password' => 'password', 'product' => 'product',
            'quantity' => 'quantity', 'total' => 'total', 'shipping' => 'price', 'save' => 'save', 'repeater' => 'repeater',
            'creditcard' => 'payment', 'confirm' => 'text', 'note' => 'html', 'signature' => 'signature', 'file_upload' => 'fileupload',
        );
        $fields = (array) ( $data['fields'] ?? array() );
        usort( $fields, function ( $x, $y ) {
            return (int) ( $x['order'] ?? 0 ) <=> (int) ( $y['order'] ?? 0 );
        } );
        $ids = array();
        foreach ( $fields as $n => $f ) {
            $ids[ (string) ( $f['key'] ?? $n ) ] = $b->id( $f['key'] ?? ( 'field_' . ( $n + 1 ) ) );
        }
        foreach ( $fields as $n => $f ) {
            $ntype = (string) ( $f['type'] ?? '' );
            $where = 'field ' . ( $f['key'] ?? $n ) . ' (' . ( $f['label'] ?? $ntype ) . ')';
            $type  = $types[ $ntype ] ?? null;
            if ( $type === null ) {
                $b->report->dropped( $where, 'BAF has no field like Ninja Forms\' "' . $ntype . '".' );
                continue;
            }
            $item = array( 'id' => $ids[ (string) ( $f['key'] ?? $n ) ], 'type' => $type );
            if ( ( $f['label'] ?? '' ) !== '' && ! in_array( $type, array( 'html' ), true ) ) {
                $item['label'] = (string) $f['label'];
            }
            if ( ! empty( $f['desc_text'] ) ) {
                $item['help'] = trim( strip_tags( (string) $f['desc_text'] ) );
            } elseif ( ! empty( $f['help_text'] ) ) {
                $item['help'] = trim( strip_tags( (string) $f['help_text'] ) );
            }
            if ( ! empty( $f['placeholder'] ) ) {
                $item['placeholder'] = (string) $f['placeholder'];
            }
            if ( ! empty( $f['required'] ) && ! in_array( $type, array( 'html', 'submit' ), true ) ) {
                $item['required'] = true;
            }
            if ( isset( $f['default'] ) && $f['default'] !== '' && ! in_array( $type, array( 'html', 'submit' ), true ) ) {
                $smart = array( 'user_display_name' => 'user("display_name")', 'user_email' => 'user("user_email")', 'user_firstname' => 'user("first_name")', 'user_lastname' => 'user("last_name")', 'date' => 'today()' );
                if ( isset( $smart[ $f['default'] ] ) ) {
                    $item['smart_default'] = $smart[ $f['default'] ];
                } elseif ( strpos( (string) $f['default'], '{' ) !== false ) {
                    $b->report->dropped( $where, 'Its default "' . $f['default'] . '" is a Ninja Forms merge tag BAF does not have.' );
                } else {
                    $item['default'] = $f['default'];
                }
            }
            if ( ! empty( $f['mask'] ) ) {
                $mask = $f['mask'] === 'custom' ? ( $f['custom_mask'] ?? '' ) : array( 'us-phone' => '(999) 999-9999', 'date' => '99/99/9999', 'currency' => '' )[ $f['mask'] ] ?? '';
                if ( $mask !== '' ) {
                    $item['mask'] = $mask;
                }
            }
            if ( ! empty( $f['options'] ) ) {
                $choices = array();
                foreach ( (array) $f['options'] as $o ) {
                    $label  = (string) ( $o['label'] ?? '' );
                    $choice = array( 'value' => (string) ( ( $o['value'] ?? '' ) !== '' ? $o['value'] : $label ), 'label' => $label );
                    if ( isset( $o['calc'] ) && $o['calc'] !== '' && is_numeric( $o['calc'] ) ) {
                        $choice['points'] = BAF_Import_Builder::number( $o['calc'] );
                    }
                    if ( ! empty( $o['selected'] ) ) {
                        $choice['selected'] = true;
                    }
                    $choices[] = $choice;
                }
                $item['choices'] = $choices;
            } elseif ( $ntype === 'listcountry' ) {
                $item['choice_library'] = 'countries';
            } elseif ( $ntype === 'liststate' ) {
                $item['choice_library'] = 'us_states';
            }
            if ( in_array( $item['type'], BAF_Form_Model::CHOICE_TYPES, true ) && empty( $item['choices'] ) && empty( $item['choice_library'] ) ) {
                $item['choices'] = array( array( 'value' => 'yes', 'label' => 'Yes' ) );
                if ( $ntype !== 'checkbox' ) {
                    $b->report->changed( $where, 'It had no choices; it got the choice yes|Yes.' );
                }
            }
            $v = array();
            if ( isset( $f['num_min'] ) && $f['num_min'] !== '' ) {
                $v['min'] = BAF_Import_Builder::number( $f['num_min'] );
            }
            if ( isset( $f['num_max'] ) && $f['num_max'] !== '' ) {
                $v['max'] = BAF_Import_Builder::number( $f['num_max'] );
            }
            if ( ! empty( $f['input_limit'] ) && ( $f['input_limit_type'] ?? 'characters' ) === 'characters' ) {
                $v['max_length'] = (int) $f['input_limit'];
            }
            if ( $v ) {
                $item['validation'] = $v;
            }
            if ( $type === 'html' ) {
                $item['props']['content'] = $ntype === 'hr' ? '<hr>' : (string) ( $f['default'] ?? $f['value'] ?? '' );
                unset( $item['default'] );
            }
            if ( isset( $f['num_step'] ) && $f['num_step'] !== '' ) {
                $item['props']['step'] = BAF_Import_Builder::number( $f['num_step'] );
            }
            if ( $ntype === 'starrating' && ! empty( $f['number_of_stars'] ) ) {
                $item['props']['max_rating'] = (int) $f['number_of_stars'];
            }
            if ( $type === 'product' && isset( $f['product_price'] ) ) {
                $item['props']['base_price'] = BAF_Import_Builder::number( $f['product_price'] );
            }
            if ( $type === 'submit' && ! empty( $f['processing_label'] ) ) {
                $item['props']['processing_text'] = (string) $f['processing_label'];
            }
            if ( ! empty( $f['element_class'] ) ) {
                $item['props']['css_class'] = (string) $f['element_class'];
            }
            if ( in_array( $ntype, array( 'firstname', 'lastname', 'address', 'city', 'zip' ), true ) ) {
                $item['props']['autocomplete'] = array( 'firstname' => 'given-name', 'lastname' => 'family-name', 'address' => 'street-address', 'city' => 'address-level2', 'zip' => 'postal-code' )[ $ntype ];
            }
            if ( $type === 'repeater' ) {
                $item['items'] = array();
                $b->report->dropped( $where, 'The fields inside a Ninja Forms repeater are not in its export; add them in BAF.' );
            }
            $b->add( self::order( $item ) );
        }

        // Calculations become calculation fields.
        foreach ( (array) ( $settings['calculations'] ?? array() ) as $calc ) {
            $text = preg_replace_callback( '/\{field:([^}]+)\}/', function ( $m ) use ( $ids ) {
                return isset( $ids[ $m[1] ] ) ? '#' . $ids[ $m[1] ] : $m[0];
            }, (string) ( $calc['eq'] ?? '' ) );
            $text = preg_replace( '/\{calc:([A-Za-z0-9_]+)\}/', '#$1', $text );
            if ( BAF_Expression::check( $text ) !== null ) {
                $b->report->dropped( 'calculation ' . ( $calc['name'] ?? '' ), 'Its equation "' . ( $calc['eq'] ?? '' ) . '" could not be read.' );
                continue;
            }
            $b->add( array( 'id' => $b->id( $calc['name'] ?? 'calculation' ), 'type' => 'calculation', 'label' => (string) ( $calc['name'] ?? '' ), 'calculation' => BAF_Expression::canonical( $text ) ) );
            $b->report->changed( 'calculation ' . ( $calc['name'] ?? '' ), 'Ninja Forms calculations are calculation fields in BAF.' );
        }
        if ( ! empty( $settings['conditions'] ) ) {
            $b->report->dropped( 'conditional logic', 'Ninja Forms\' Conditional Logic add-on stores its conditions in its own format, which this import does not read; add the conditions in BAF.' );
        }

        // Actions.
        $c = 0;
        $n = 0;
        foreach ( (array) ( $data['actions'] ?? array() ) as $a ) {
            $type = $a['type'] ?? '';
            if ( $type === 'successmessage' ) {
                $c++;
                $b->model['confirmations'][] = array( 'id' => 'confirmation_' . $c, 'type' => 'message', 'message' => (string) ( $a['success_msg'] ?? '' ) );
            } elseif ( $type === 'redirect' ) {
                $c++;
                $b->model['confirmations'][] = array( 'id' => 'confirmation_' . $c, 'type' => 'redirect', 'url' => (string) ( $a['redirect_url'] ?? '' ) );
            } elseif ( $type === 'email' ) {
                $n++;
                $note = array( 'id' => 'notification_' . $n, 'enabled' => ! isset( $a['active'] ) || (bool) $a['active'] );
                foreach ( array( 'to' => 'to', 'cc' => 'cc', 'bcc' => 'bcc', 'from_name' => 'from_name', 'from_address' => 'from_email', 'reply_to' => 'reply_to', 'email_subject' => 'subject', 'email_message' => 'message' ) as $k => $to ) {
                    if ( isset( $a[ $k ] ) && $a[ $k ] !== '' ) {
                        $note[ $to ] = (string) $a[ $k ];
                    }
                }
                $b->model['notifications'][] = $note;
            } elseif ( $type !== 'save' ) {
                $b->report->dropped( 'action ' . ( $a['label'] ?? $type ), 'BAF has no action like Ninja Forms\' "' . $type . '".' );
            }
        }
        self::retag( $b, '/\{([^{}]+)\}/', function ( $name ) use ( $ids ) {
            if ( preg_match( '/^field:(.+)$/', $name, $m ) ) {
                return $ids[ $m[1] ] ?? null;
            }
            $map = array( 'all_fields_table' => 'all_fields', 'fields_table' => 'all_fields', 'wp:admin_email' => 'admin_email', 'wp:site_title' => 'site_name', 'wp:site_url' => 'site_url', 'other:date' => 'date', 'other:time' => 'time', 'other:user_ip' => 'ip', 'form:title' => 'form_title' );
            return $map[ $name ] ?? null;
        } );
        return $b->finish();
    }

    // ---------------------------------------------------------------------
    // Google Forms
    // ---------------------------------------------------------------------

    /**
     * Google Forms API form resource to the model.
     *
     * @param array $data forms.get response.
     * @return array Result.
     */
    public static function google_forms( $data ) {
        $info = $data['info'] ?? array();
        $b    = new BAF_Import_Builder( 'Google Forms', $info['title'] ?? ( $info['documentTitle'] ?? '' ) );
        if ( ! empty( $info['description'] ) ) {
            $b->model['description'] = (string) $info['description'];
        }
        $is_quiz = ! empty( $data['settings']['quizSettings']['isQuiz'] );

        // Page breaks first, so branching can name the page it goes to.
        $page_ids = array();
        foreach ( (array) ( $data['items'] ?? array() ) as $it ) {
            if ( isset( $it['pageBreakItem'] ) ) {
                $page_ids[ (string) $it['itemId'] ] = null;
            }
        }
        $pending_rules = array();

        foreach ( (array) ( $data['items'] ?? array() ) as $it ) {
            $title = (string) ( $it['title'] ?? '' );
            $where = 'item ' . ( $it['itemId'] ?? '?' ) . ( $title !== '' ? ' (' . $title . ')' : '' );
            if ( isset( $it['pageBreakItem'] ) ) {
                $b->page( $title, (string) ( $it['description'] ?? '' ) );
                $page_ids[ (string) $it['itemId'] ] = $b->page_id();
                continue;
            }
            if ( isset( $it['textItem'] ) || isset( $it['imageItem'] ) || isset( $it['videoItem'] ) ) {
                $html = $title !== '' ? '<h3>' . htmlspecialchars( $title, ENT_QUOTES ) . '</h3>' : '';
                if ( ! empty( $it['description'] ) ) {
                    $html .= '<p>' . htmlspecialchars( $it['description'], ENT_QUOTES ) . '</p>';
                }
                if ( isset( $it['imageItem'] ) ) {
                    $img   = $it['imageItem']['image'] ?? array();
                    $html .= '<img src="' . htmlspecialchars( $img['contentUri'] ?? '', ENT_QUOTES ) . '" alt="' . htmlspecialchars( $img['altText'] ?? '', ENT_QUOTES ) . '">';
                    if ( empty( $img['altText'] ) ) {
                        $b->report->changed( $where, 'The picture has no text alternative in Google Forms; add one in BAF.' );
                    }
                }
                if ( isset( $it['videoItem'] ) ) {
                    $uri   = $it['videoItem']['video']['youtubeUri'] ?? '';
                    $html .= '<p><a href="' . htmlspecialchars( $uri, ENT_QUOTES ) . '">' . htmlspecialchars( $it['videoItem']['caption'] ?? 'Video', ENT_QUOTES ) . '</a></p>';
                    $b->report->changed( $where, 'The video is a link to it.' );
                }
                $b->add( array( 'id' => $b->id( 'item_' . ( $it['itemId'] ?? '' ), 'item_' ), 'type' => 'html', 'props' => array( 'content' => $html ) ) );
                continue;
            }
            if ( isset( $it['questionGroupItem'] ) ) {
                $g    = $it['questionGroupItem'];
                $rows = array();
                foreach ( (array) ( $g['questions'] ?? array() ) as $q ) {
                    $rows[] = (string) ( $q['rowQuestion']['title'] ?? '' );
                }
                $cols = array();
                foreach ( (array) ( $g['grid']['columns']['options'] ?? array() ) as $o ) {
                    $cols[] = (string) ( $o['value'] ?? '' );
                }
                $item = array( 'id' => $b->id( 'q_' . ( $it['itemId'] ?? '' ), 'q_' ), 'type' => 'matrix', 'label' => $title );
                if ( ! empty( $it['description'] ) ) {
                    $item['help'] = (string) $it['description'];
                }
                if ( ! empty( $g['questions'][0]['required'] ) ) {
                    $item['required'] = true;
                }
                $item['props'] = array( 'rows' => $rows, 'columns' => $cols, 'input_type' => ( $g['grid']['columns']['type'] ?? 'RADIO' ) === 'CHECKBOX' ? 'checkbox' : 'radio' );
                if ( ! empty( $g['grid']['shuffleQuestions'] ) ) {
                    $item['props']['randomize_rows'] = true;
                }
                $b->add( $item );
                continue;
            }
            if ( ! isset( $it['questionItem']['question'] ) ) {
                $b->report->dropped( $where, 'This kind of Google Forms item is not one BAF knows.' );
                continue;
            }
            $q    = $it['questionItem']['question'];
            $item = array( 'id' => $b->id( 'q_' . ( $q['questionId'] ?? $it['itemId'] ?? '' ), 'q_' ), 'type' => 'text', 'label' => $title );
            if ( ! empty( $it['description'] ) ) {
                $item['help'] = (string) $it['description'];
            }
            if ( ! empty( $q['required'] ) ) {
                $item['required'] = true;
            }
            if ( isset( $q['textQuestion'] ) ) {
                $item['type'] = ! empty( $q['textQuestion']['paragraph'] ) ? 'textarea' : 'text';
            } elseif ( isset( $q['choiceQuestion'] ) ) {
                $cq           = $q['choiceQuestion'];
                $item['type'] = array( 'RADIO' => 'radio', 'CHECKBOX' => 'checkbox', 'DROP_DOWN' => 'select' )[ $cq['type'] ?? 'RADIO' ] ?? 'radio';
                $choices      = array();
                foreach ( (array) ( $cq['options'] ?? array() ) as $o ) {
                    if ( ! empty( $o['isOther'] ) ) {
                        $item['props']['allow_other'] = true;
                        continue;
                    }
                    $choice = array( 'value' => (string) ( $o['value'] ?? '' ), 'label' => (string) ( $o['value'] ?? '' ) );
                    if ( ! empty( $o['image']['contentUri'] ) ) {
                        $choice['image'] = (string) $o['image']['contentUri'];
                    }
                    $choices[] = $choice;
                    if ( ! empty( $o['goToSectionId'] ) ) {
                        $pending_rules[] = array( 'field' => $item['id'], 'value' => $choice['value'], 'section' => (string) $o['goToSectionId'] );
                    } elseif ( ! empty( $o['goToAction'] ) && $o['goToAction'] !== 'NEXT_SECTION' ) {
                        $b->report->dropped( $where, 'Choosing "' . $choice['value'] . '" goes to ' . strtolower( str_replace( '_', ' ', $o['goToAction'] ) ) . ', which has no place in the model.' );
                    }
                }
                $item['choices'] = $choices ?: array( array( 'value' => 'yes', 'label' => 'Yes' ) );
                if ( ! empty( $cq['shuffle'] ) ) {
                    $item['props']['randomize'] = true;
                }
                if ( array_filter( $choices, function ( $c ) {
                    return isset( $c['image'] );
                } ) && $item['type'] !== 'select' ) {
                    $item['props']['multiple'] = $item['type'] === 'checkbox';
                    $item['type']              = 'image_choice';
                    $b->report->changed( $where, 'Choices with pictures are an image choice field.' );
                }
            } elseif ( isset( $q['scaleQuestion'] ) ) {
                $s    = $q['scaleQuestion'];
                $low  = (int) ( $s['low'] ?? 1 );
                $high = (int) ( $s['high'] ?? 5 );
                if ( $low === 0 && $high === 10 ) {
                    $item['type'] = 'nps';
                } else {
                    $item['type']               = 'rating';
                    $item['props']['max_rating'] = $high;
                    $item['props']['icon_type']  = 'circle';
                    if ( $low !== 1 ) {
                        $item['props']['min_rating'] = $low;
                    }
                }
                foreach ( array( 'lowLabel' => 'low_label', 'highLabel' => 'high_label' ) as $k => $to ) {
                    if ( ! empty( $s[ $k ] ) ) {
                        $item['props'][ $to ] = (string) $s[ $k ];
                    }
                }
            } elseif ( isset( $q['ratingQuestion'] ) ) {
                $item['type']                = 'rating';
                $item['props']['max_rating'] = (int) ( $q['ratingQuestion']['ratingScaleLevel'] ?? 5 );
                $item['props']['icon_type']  = array( 'STAR' => 'star', 'HEART' => 'heart', 'THUMB_UP' => 'thumb' )[ $q['ratingQuestion']['iconType'] ?? 'STAR' ] ?? 'star';
            } elseif ( isset( $q['dateQuestion'] ) ) {
                $item['type'] = ! empty( $q['dateQuestion']['includeTime'] ) ? 'datetime' : 'date';
                if ( isset( $q['dateQuestion']['includeYear'] ) && ! $q['dateQuestion']['includeYear'] ) {
                    $b->report->changed( $where, 'Google Forms asked for a date without a year; BAF asks for the whole date.' );
                }
            } elseif ( isset( $q['timeQuestion'] ) ) {
                $item['type'] = 'time';
                if ( ! empty( $q['timeQuestion']['duration'] ) ) {
                    $b->report->changed( $where, 'Google Forms asked for a length of time; BAF asks for a time of day.' );
                }
            } elseif ( isset( $q['fileUploadQuestion'] ) ) {
                $f            = $q['fileUploadQuestion'];
                $item['type'] = 'fileupload';
                if ( ! empty( $f['maxFiles'] ) ) {
                    $item['props']['max_files'] = (int) $f['maxFiles'];
                }
                if ( ! empty( $f['maxFileSize'] ) ) {
                    $item['props']['max_size'] = BAF_Import_Builder::number( round( ( (int) $f['maxFileSize'] ) / 1048576, 2 ) );
                }
                $b->report->changed( $where, 'Files go to BAF\'s uploads, not a Google Drive folder.' );
            } else {
                $b->report->dropped( $where, 'This kind of Google Forms question is not one BAF knows.' );
                continue;
            }
            if ( isset( $q['grading'] ) ) {
                $g       = $q['grading'];
                $scoring = array( 'correct' => array() );
                foreach ( (array) ( $g['correctAnswers']['answers'] ?? array() ) as $a ) {
                    $scoring['correct'][] = (string) ( $a['value'] ?? '' );
                }
                if ( isset( $g['pointValue'] ) ) {
                    $scoring['points'] = (int) $g['pointValue'];
                }
                if ( ! empty( $g['whenRight']['text'] ) ) {
                    $scoring['feedback_correct'] = (string) $g['whenRight']['text'];
                }
                if ( ! empty( $g['whenWrong']['text'] ) ) {
                    $scoring['feedback_incorrect'] = (string) $g['whenWrong']['text'];
                }
                if ( ! empty( $g['generalFeedback']['text'] ) ) {
                    $scoring['explanation'] = (string) $g['generalFeedback']['text'];
                }
                $item['scoring'] = $scoring;
            }
            $b->add( self::order( $item ) );
        }

        foreach ( $pending_rules as $r ) {
            $target = $page_ids[ $r['section'] ] ?? null;
            if ( $target === null ) {
                $b->report->dropped( 'item ' . $r['field'], 'It goes to a section that is not in the form.' );
                continue;
            }
            $b->model['rules'][] = array( 'action' => 'skip_to', 'targets' => array( $target ), 'when' => BAF_Import_Builder::condition( array( BAF_Import_Builder::test( $r['field'], 'is', $r['value'] ) ) ) );
        }
        if ( $is_quiz ) {
            $b->model['scoring'] = array( 'show_score' => true );
        }
        if ( ! empty( $data['settings']['emailCollectionType'] ) && $data['settings']['emailCollectionType'] !== 'DO_NOT_COLLECT' ) {
            $b->report->dropped( 'settings', 'Google Forms collected email addresses itself; add an email field in BAF if you need one.' );
        }
        return $b->finish();
    }

    /**
     * Item keys in the documented order.
     *
     * @param array $item Item.
     * @return array Item.
     */
    private static function order( $item ) {
        $order = array( 'id', 'type', 'label', 'help', 'placeholder', 'required', 'default', 'smart_default', 'mask', 'when', 'calculation', 'choices', 'choice_library', 'cascade', 'validation', 'scoring', 'attributes', 'columns', 'items', 'props' );
        $out   = array();
        foreach ( $order as $k ) {
            if ( array_key_exists( $k, $item ) ) {
                $out[ $k ] = $item[ $k ];
            }
        }
        return $out;
    }
}
