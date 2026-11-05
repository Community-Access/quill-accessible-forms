<?php
/**
 * BFL writer: the form model to canonical BFL.
 *
 * Every model feature has one BFL spelling, and this writer always uses it,
 * so QAF and QUILL write the same bytes for the same form. Reading the
 * output with QAF_BFL_Reader gives the model back exactly.
 *
 * No WordPress functions are used.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-qaf-form-model.php';

/**
 * QAF_BFL_Writer class.
 */
class QAF_BFL_Writer {

    /** @var array Output lines. */
    private $lines = array();

    /** @var int Automatic item number, as the reader counts. */
    private $counter = 0;

    /**
     * Write a model as BFL.
     *
     * @param array $model Model.
     * @return string BFL text ending in a newline.
     */
    public static function write( $model ) {
        $w = new self();
        return $w->run( $model );
    }

    /**
     * Worker.
     *
     * @param array $model Model.
     * @return string BFL.
     */
    private function run( $model ) {
        $this->lines   = array( 'bfl ' . QAF_Form_Model::VERSION );
        $this->counter = 0;

        $head = 'form';
        if ( isset( $model['id'] ) ) {
            $head .= ' #' . $model['id'];
        }
        $this->line( 0, $head . ' ' . QAF_Expression::quote( $model['title'] ?? '' ) );
        if ( isset( $model['description'] ) ) {
            $this->mod( 1, 'description', $model['description'] );
        }
        if ( isset( $model['language'] ) ) {
            $this->mod( 1, 'language', $model['language'] );
        }
        foreach ( $model['settings'] ?? array() as $k => $v ) {
            $this->mod( 1, $k, $v );
        }
        if ( isset( $model['extensions'] ) ) {
            $this->line( 1, '@extensions ' . QAF_Form_Model::encode( $model['extensions'] === array() ? new stdClass() : $model['extensions'] ) );
        }

        foreach ( $model['choice_libraries'] ?? array() as $name => $choices ) {
            $this->blank();
            $this->line( 0, 'library #' . $name );
            foreach ( $choices as $c ) {
                $this->line( 1, self::choice_line( $c ) );
            }
        }

        $pages = $model['pages'] ?? array();
        $bare  = count( $pages ) === 1 && self::is_bare_page( $pages[0], 0 );
        foreach ( $pages as $i => $page ) {
            $this->blank();
            if ( $bare ) {
                $this->items( $page['items'], 0 );
                continue;
            }
            $head = 'page';
            if ( $page['id'] !== 'page_' . ( $i + 1 ) ) {
                $head .= ' #' . $page['id'];
            }
            if ( isset( $page['title'] ) ) {
                $head .= ' ' . QAF_Expression::quote( $page['title'] );
            }
            $this->line( 0, $head );
            if ( isset( $page['description'] ) ) {
                $this->mod( 1, 'description', $page['description'] );
            }
            if ( isset( $page['when'] ) ) {
                $this->line( 1, '@when ' . $page['when'] );
            }
            foreach ( $page['props'] ?? array() as $k => $v ) {
                $this->mod( 1, $k, $v );
            }
            $this->items( $page['items'], 1 );
        }

        foreach ( $model['rules'] ?? array() as $rule ) {
            $this->blank();
            $head = 'rule ' . $rule['action'];
            foreach ( $rule['targets'] as $t ) {
                $head .= ' #' . $t;
            }
            $this->line( 0, $head );
            if ( isset( $rule['value'] ) ) {
                $this->line( 1, '@value ' . $rule['value'] );
            }
            $this->line( 1, '@when ' . $rule['when'] );
        }

        if ( isset( $model['scoring'] ) ) {
            $this->blank();
            $this->line( 0, 'scoring' );
            foreach ( $model['scoring'] as $k => $v ) {
                if ( $k !== 'outcomes' ) {
                    $this->mod( 1, $k, $v );
                }
            }
            foreach ( $model['scoring']['outcomes'] ?? array() as $i => $o ) {
                $this->line( 1, 'outcome' . ( $o['id'] !== 'outcome_' . ( $i + 1 ) ? ' #' . $o['id'] : '' ) );
                $this->line( 2, '@when ' . $o['when'] );
                $this->mod( 2, 'message', $o['message'] );
            }
        }

        foreach ( array( 'confirmation', 'notification' ) as $kind ) {
            foreach ( $model[ $kind . 's' ] ?? array() as $i => $obj ) {
                $this->blank();
                $this->line( 0, $kind . ( $obj['id'] !== $kind . '_' . ( $i + 1 ) ? ' #' . $obj['id'] : '' ) );
                foreach ( QAF_Form_Model::FIXED_NAMES[ $kind ] as $k ) {
                    if ( in_array( $k, array( 'id', 'when', 'props' ), true ) || ! array_key_exists( $k, $obj ) ) {
                        continue;
                    }
                    if ( $kind === 'confirmation' && $k === 'type' && $obj['type'] === 'message' ) {
                        continue;
                    }
                    $this->mod( 1, $k, $obj[ $k ] );
                }
                if ( isset( $obj['when'] ) ) {
                    $this->line( 1, '@when ' . $obj['when'] );
                }
                foreach ( $obj['props'] ?? array() as $k => $v ) {
                    $this->mod( 1, $k, $v );
                }
            }
        }

        foreach ( $model['translations'] ?? array() as $lang => $t ) {
            $this->blank();
            $this->line( 0, 'translation ' . QAF_Expression::quote( (string) $lang ) );
            foreach ( array( 'title', 'description' ) as $k ) {
                if ( isset( $t[ $k ] ) ) {
                    $this->mod( 1, $k, $t[ $k ] );
                }
            }
            foreach ( $t['pages'] ?? array() as $id => $part ) {
                $this->line( 1, 'page #' . $id );
                foreach ( array( 'title', 'description' ) as $k ) {
                    if ( isset( $part[ $k ] ) ) {
                        $this->mod( 2, $k, $part[ $k ] );
                    }
                }
            }
            foreach ( $t['fields'] ?? array() as $id => $part ) {
                $this->line( 1, 'field #' . $id );
                foreach ( array( 'label' => 'label', 'help' => 'description', 'placeholder' => 'placeholder' ) as $k => $spelling ) {
                    if ( isset( $part[ $k ] ) ) {
                        $this->mod( 2, $spelling, $part[ $k ] );
                    }
                }
                foreach ( $part['choices'] ?? array() as $value => $label ) {
                    $this->line( 2, self::choice_line( array( 'value' => (string) $value, 'label' => $label ) ) );
                }
            }
            foreach ( array( 'confirmations' => 'confirmation', 'outcomes' => 'outcome' ) as $key => $word ) {
                foreach ( $t[ $key ] ?? array() as $id => $part ) {
                    $this->line( 1, $word . ' #' . $id );
                    if ( isset( $part['message'] ) ) {
                        $this->mod( 2, 'message', $part['message'] );
                    }
                }
            }
            foreach ( $t['messages'] ?? array() as $id => $text ) {
                $this->line( 1, 'message #' . $id . ' ' . QAF_Expression::quote( $text ) );
            }
        }

        return implode( "\n", $this->lines ) . "\n";
    }

    /**
     * Whether a page needs no page line (the only page, with nothing but items).
     *
     * @param array $page  Page.
     * @param int   $index Index.
     * @return bool Bare.
     */
    private static function is_bare_page( $page, $index ) {
        return $page['id'] === 'page_' . ( $index + 1 ) && count( $page ) === 2 && array_key_exists( 'items', $page );
    }

    /** Add a line at an indent level (two spaces each). */
    private function line( $level, $text ) {
        $this->lines[] = str_repeat( '  ', $level ) . $text;
    }

    /** Add one blank line, never two. */
    private function blank() {
        if ( end( $this->lines ) !== '' ) {
            $this->lines[] = '';
        }
    }

    /**
     * A value as BFL writes it.
     *
     * @param mixed $v Value.
     * @return string Text.
     */
    public static function value( $v ) {
        if ( is_array( $v ) ) {
            return QAF_Form_Model::encode( $v );
        }
        return QAF_Expression::literal_text( $v );
    }

    /**
     * A modifier line: @name value, or @name alone for true.
     *
     * @param int    $level Indent.
     * @param string $name  Name.
     * @param mixed  $v     Value.
     */
    private function mod( $level, $name, $v ) {
        $this->line( $level, '@' . $name . ( $v === true ? '' : ' ' . self::value( $v ) ) );
    }

    /**
     * Whether text can be written bare in a choice line.
     *
     * @param string $s Text.
     * @return bool Plain.
     */
    private static function plain( $s ) {
        return $s !== '' && trim( $s ) === $s && strpos( $s, "\n" ) === false && $s[0] !== '"';
    }

    /**
     * A choice line.
     *
     * @param array $c Choice.
     * @return string Line.
     */
    public static function choice_line( $c ) {
        $value  = (string) $c['value'];
        $label  = (string) $c['label'];
        $extras = array_diff_key( $c, array( 'value' => 1, 'label' => 1 ) );
        $bare   = self::plain( $label ) && strpos( $label, '|' ) === false && strpos( $label, ':' ) === false && $value === QAF_Form_Model::slug( $label ) && $value !== '';
        // A price alone, on a choice whose value is its label's slug: the short
        // spelling "+ Small:10", which reads back to the same choice.
        if ( $bare && array_keys( $extras ) === array( 'price' ) && ( is_int( $c['price'] ) || ( is_float( $c['price'] ) && is_finite( $c['price'] ) ) ) ) {
            return '+ ' . $label . ':' . QAF_Expression::literal_text( $c['price'] );
        }
        if ( ! $extras ) {
            if ( $bare ) {
                return '+ ' . $label;
            }
            if ( self::plain( $value ) && strpos( $value, '|' ) === false && self::plain( $label ) ) {
                return '+ ' . $value . '|' . $label;
            }
        }
        $line = '+ ' . QAF_Expression::quote( $value ) . ' | ' . QAF_Expression::quote( $label );
        foreach ( array( 'price', 'image', 'points', 'description', 'selected' ) as $k ) {
            if ( array_key_exists( $k, $c ) ) {
                $line .= ' @' . $k . ( $c[ $k ] === true ? '' : ' ' . self::value( $c[ $k ] ) );
            }
        }
        return $line;
    }

    /**
     * Write a list of items.
     *
     * @param array $items Items.
     * @param int   $level Indent.
     */
    private function items( $items, $level ) {
        foreach ( $items as $item ) {
            $this->item( $item, $level );
        }
    }

    /**
     * The #id part of a header, left out when the reader would make it up.
     *
     * @param array $item Item.
     * @return string Text.
     */
    private function id_part( $item ) {
        $this->counter++;
        return $item['id'] === 'field_' . $this->counter ? '' : ' #' . $item['id'];
    }

    /**
     * Write one item.
     *
     * @param array $item  Item.
     * @param int   $level Indent.
     */
    private function item( $item, $level ) {
        $type = $item['type'];
        $head = $type . $this->id_part( $item );

        if ( $type === 'columns' ) {
            $this->line( $level, $head . ' ' . count( $item['columns'] ) );
            if ( isset( $item['when'] ) ) {
                $this->line( $level + 1, '@when ' . $item['when'] );
            }
            foreach ( $item['props'] ?? array() as $k => $v ) {
                $this->mod( $level + 1, $k, $v );
            }
            foreach ( $item['columns'] as $col ) {
                $this->line( $level + 1, 'column' );
                if ( isset( $col['width'] ) ) {
                    $this->mod( $level + 2, 'width', $col['width'] );
                }
                $this->items( $col['items'], $level + 2 );
            }
            return;
        }

        if ( isset( $item['label'] ) ) {
            $head .= ' ' . QAF_Expression::quote( $item['label'] );
        }

        $mods = $this->item_mods( $item );
        if ( $type === 'section' && ! $item['items'] ) {
            // An empty section is closed explicitly, so it does not take the items after it.
            if ( ! $mods ) {
                $this->line( $level, $head . ' {}' );
                return;
            }
            $this->line( $level, $head . ' {' );
            foreach ( $mods as $m ) {
                $this->line( $level + 1, $m );
            }
            $this->line( $level, '}' );
            return;
        }

        $this->line( $level, $head );
        foreach ( $mods as $m ) {
            $this->line( $level + 1, $m );
        }
        foreach ( $item['choices'] ?? array() as $c ) {
            $this->line( $level + 1, self::choice_line( $c ) );
        }
        if ( isset( $item['items'] ) ) {
            $this->items( $item['items'], $level + 1 );
        }
    }

    /**
     * The modifier lines of a field or section, in canonical order.
     *
     * @param array $item Item.
     * @return array Lines.
     */
    private function item_mods( $item ) {
        $out = array();
        $add = function ( $name, $v ) use ( &$out ) {
            $out[] = '@' . $name . ( $v === true ? '' : ' ' . self::value( $v ) );
        };
        if ( array_key_exists( 'required', $item ) ) {
            $add( 'required', $item['required'] );
        }
        foreach ( array( 'placeholder' => 'placeholder', 'help' => 'description' ) as $k => $spelling ) {
            if ( isset( $item[ $k ] ) ) {
                $add( $spelling, $item[ $k ] );
            }
        }
        if ( array_key_exists( 'default', $item ) ) {
            $out[] = '@default ' . self::value( $item['default'] );
        }
        foreach ( array( 'smart_default' => 'smart_default' ) as $k => $spelling ) {
            if ( isset( $item[ $k ] ) ) {
                $out[] = '@' . $spelling . ' ' . $item[ $k ];
            }
        }
        if ( isset( $item['mask'] ) ) {
            $add( 'mask', $item['mask'] );
        }
        if ( isset( $item['when'] ) ) {
            $out[] = '@when ' . $item['when'];
        }
        if ( isset( $item['calculation'] ) ) {
            $out[] = '@calculate ' . $item['calculation'];
        }
        if ( isset( $item['choice_library'] ) ) {
            $add( 'library', $item['choice_library'] );
        }
        if ( isset( $item['cascade'] ) ) {
            $c = $item['cascade'];
            if ( isset( $c['from'] ) ) {
                $out[] = '@cascade_from #' . $c['from'];
            }
            foreach ( array( 'library' => 'cascade_library', 'empty_message' => 'empty_message', 'loading_message' => 'loading_message' ) as $k => $spelling ) {
                if ( isset( $c[ $k ] ) ) {
                    $add( $spelling, $c[ $k ] );
                }
            }
        }
        $v = $item['validation'] ?? array();
        foreach ( array( 'min' => 'min', 'max' => 'max', 'min_length' => 'minlength', 'max_length' => 'maxlength', 'pattern' => 'pattern', 'message' => 'error_message', 'unique' => 'unique', 'unique_scope' => 'unique_scope', 'unique_message' => 'unique_message' ) as $k => $spelling ) {
            if ( array_key_exists( $k, $v ) ) {
                $add( $spelling, $v[ $k ] );
            }
        }
        $s = $item['scoring'] ?? array();
        if ( array_key_exists( 'correct', $s ) ) {
            $out[] = '@correct ' . QAF_Form_Model::encode( array_values( $s['correct'] ) );
        }
        foreach ( array( 'points', 'partial', 'case_sensitive', 'feedback_correct', 'feedback_incorrect', 'feedback_partial', 'explanation' ) as $k ) {
            if ( array_key_exists( $k, $s ) ) {
                $add( $k, $s[ $k ] );
            }
        }
        foreach ( $item['attributes'] ?? array() as $name => $value ) {
            $out[] = '@attr ' . QAF_Expression::quote( $name ) . ' ' . self::value( $value );
        }
        foreach ( $item['props'] ?? array() as $k => $value ) {
            $add( $k, $value );
        }
        return $out;
    }
}
