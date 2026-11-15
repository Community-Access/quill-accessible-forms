<?php
/**
 * The canvas's actions for sections and columns, done on the form model:
 * add a section or a set of columns, move one among its neighbours, copy
 * one (with new IDs for everything inside), and remove one while keeping
 * its questions; and inside a set of columns, add, move and remove a
 * column. Like QAF_Builder_Field_Ops, each changes a copy of the model and
 * the caller saves it through QAF_Form::set_model(), where validate()
 * checks it. Nothing here deletes a question: removing a section or a
 * column puts its questions where it was.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-qaf-builder-field-ops.php';

/**
 * QAF_Builder_Layout_Ops class.
 */
class QAF_Builder_Layout_Ops {

    /** The two kinds of group the canvas draws. */
    const GROUP_TYPES = array( 'section', 'columns' );

    /** Most columns a set may have; more do not fit a page that people read. */
    const MAX_COLUMNS = 4;

    /**
     * The list an item sits in, by reference, and its index there.
     *
     * @param array  $model Model (by reference).
     * @param string $id    Item ID.
     * @return array|null array( &list, index ) or null.
     */
    private static function &locate( &$model, $id ) {
        $none = null;
        foreach ( QAF_Form_Model::items( $model ) as $entry ) {
            if ( (string) ( $entry['item']['id'] ?? '' ) !== (string) $id ) {
                continue;
            }
            $segments = explode( '/', ltrim( $entry['path'], '/' ) );
            $index    = (int) array_pop( $segments );
            $list     = &$model;
            foreach ( $segments as $s ) {
                $list = &$list[ ctype_digit( $s ) ? (int) $s : $s ];
            }
            $found = array( &$list, $index );
            return $found;
        }
        return $none;
    }

    /**
     * An error to send back (a WP_Error when WordPress is loaded); read it
     * with QAF_Builder_Field_Ops::refused().
     *
     * @param string $message Message.
     * @return WP_Error|array
     */
    private static function refusal( $message ) {
        return class_exists( 'WP_Error' ) ? new WP_Error( 'qaf_layout_refused', $message ) : array( 'error' => $message );
    }

    /**
     * Put an item after another, in that one's list; or at the end of the
     * last page when there is no such item.
     *
     * @param array  $model Model.
     * @param array  $item  Item.
     * @param string $after Item ID to follow, or ''.
     * @return array Model.
     */
    private static function insert( $model, $item, $after ) {
        if ( $after !== '' ) {
            $at = &self::locate( $model, $after );
            if ( $at !== null ) {
                array_splice( $at[0], $at[1] + 1, 0, array( $item ) );
                unset( $at );
                return $model;
            }
            unset( $at );
        }
        if ( empty( $model['pages'] ) ) {
            $model['pages'] = array( array( 'id' => QAF_Builder_Field_Ops::unique_id( $model, 'page_1' ), 'items' => array() ) );
        }
        $model['pages'][ count( $model['pages'] ) - 1 ]['items'][] = $item;
        return $model;
    }

    /**
     * Add a section.
     *
     * @param array  $model Model.
     * @param string $label Its heading.
     * @param string $after Item ID to put it after, or '' for the end of the last page.
     * @return array array( model, id ).
     */
    public static function add_section( $model, $label, $after = '' ) {
        $id    = QAF_Builder_Field_Ops::unique_id( $model, 'section' );
        $item  = array( 'type' => 'section', 'id' => $id, 'label' => trim( (string) $label ) !== '' ? trim( (string) $label ) : __( 'New section', 'quill-accessible-forms' ), 'items' => array() );
        return array( self::insert( $model, $item, (string) $after ), $id );
    }

    /**
     * Add a set of columns, each empty.
     *
     * @param array  $model Model.
     * @param int    $count How many columns (2 to MAX_COLUMNS).
     * @param string $after Item ID to put it after, or ''.
     * @return array array( model, id ).
     */
    public static function add_columns( $model, $count = 2, $after = '' ) {
        $count = max( 2, min( self::MAX_COLUMNS, (int) $count ) );
        $id    = QAF_Builder_Field_Ops::unique_id( $model, 'columns' );
        $cols  = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $cols[] = array( 'items' => array() );
        }
        $item = array( 'type' => 'columns', 'id' => $id, 'columns' => $cols );
        return array( self::insert( $model, $item, (string) $after ), $id );
    }

    /**
     * Move an item one place up or down among its neighbours (the same
     * page, section or column).
     *
     * @param array  $model     Model.
     * @param string $id        Item ID.
     * @param string $direction up|down.
     * @return array|null array( model, position (1-based), count ), or null when there is no such item.
     */
    public static function move( $model, $id, $direction ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $list  = &$at[0];
        $i     = $at[1];
        $j     = $direction === 'up' ? $i - 1 : $i + 1;
        $count = count( $list );
        if ( $j >= 0 && $j < $count ) {
            $tmp        = $list[ $i ];
            $list[ $i ] = $list[ $j ];
            $list[ $j ] = $tmp;
            $i          = $j;
        }
        unset( $list, $at );
        return array( $model, $i + 1, $count );
    }

    /**
     * Copy a section or a set of columns, just after it, with new IDs for it
     * and everything in it.
     *
     * @param array  $model  Model.
     * @param string $id     Group ID.
     * @param string $suffix Added to a section's heading.
     * @return array|WP_Error|null array( model, new id ), a refusal, or null.
     */
    public static function duplicate( $model, $id, $suffix ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $copy = $at[0][ $at[1] ];
        if ( ! in_array( $copy['type'] ?? '', self::GROUP_TYPES, true ) ) {
            unset( $at );
            return self::refusal( __( 'Only a section or a set of columns is copied here; copy a question with its own Duplicate button.', 'quill-accessible-forms' ) );
        }
        $reserved   = array();
        $copy['id'] = QAF_Builder_Field_Ops::unique_id( $model, $copy['id'] );
        $reserved[ $copy['id'] ] = true;
        if ( isset( $copy['label'] ) && $copy['label'] !== '' ) {
            $copy['label'] .= $suffix;
        }
        $copy = self::renumber_item( $model, $copy, $reserved, true );
        array_splice( $at[0], $at[1] + 1, 0, array( $copy ) );
        unset( $at );
        return array( $model, $copy['id'] );
    }

    /**
     * New IDs for everything inside a copied item.
     *
     * @param array $model    Model (for the IDs in use).
     * @param array $item     Item.
     * @param array $reserved IDs given out already (ID => true), added to.
     * @param bool  $top      The item itself already has its new ID.
     * @return array Item.
     */
    private static function renumber_item( $model, $item, &$reserved, $top = false ) {
        if ( ! $top && isset( $item['id'] ) ) {
            $item['id']              = QAF_Builder_Field_Ops::unique_id( $model, $item['id'], $reserved );
            $reserved[ $item['id'] ] = true;
        }
        if ( ( $item['type'] ?? '' ) === 'columns' ) {
            foreach ( $item['columns'] ?? array() as $c => $col ) {
                foreach ( $col['items'] ?? array() as $k => $child ) {
                    $item['columns'][ $c ]['items'][ $k ] = self::renumber_item( $model, $child, $reserved );
                }
            }
        } elseif ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
            foreach ( $item['items'] as $k => $child ) {
                $item['items'][ $k ] = self::renumber_item( $model, $child, $reserved );
            }
        }
        return $item;
    }

    /**
     * Remove a section or a set of columns, keeping its questions: they take
     * its place, in order (a set of columns gives the first column's, then
     * the second's).
     *
     * @param array  $model Model.
     * @param string $id    Group ID.
     * @return array|WP_Error|null array( model, how many items were kept, notes, the first kept item's ID or null ), a refusal, or null.
     */
    public static function remove( $model, $id ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $group = $at[0][ $at[1] ];
        $type  = $group['type'] ?? '';
        if ( ! in_array( $type, self::GROUP_TYPES, true ) ) {
            unset( $at );
            return self::refusal( __( 'Only a section or a set of columns is removed here; delete a question with its own Delete button.', 'quill-accessible-forms' ) );
        }
        $kept = array();
        if ( $type === 'columns' ) {
            foreach ( $group['columns'] ?? array() as $col ) {
                $kept = array_merge( $kept, $col['items'] ?? array() );
            }
        } else {
            $kept = $group['items'] ?? array();
        }
        $notes = array();
        if ( isset( $group['when'] ) && $kept ) {
            // Its questions showed only when its condition held; they now
            // show always, unless they carry the condition themselves.
            foreach ( $kept as $k => $item ) {
                $kept[ $k ]['when'] = isset( $item['when'] ) ? '(' . $group['when'] . ') and (' . $item['when'] . ')' : $group['when'];
                try {
                    $kept[ $k ]['when'] = QAF_Expression::canonical( $kept[ $k ]['when'] );
                } catch ( Exception $e ) {
                    // Left as written; validate() reports it on save.
                }
            }
            $notes[] = __( 'Its questions keep the condition it had, so they still show only when it holds.', 'quill-accessible-forms' );
        }
        array_splice( $at[0], $at[1], 1, $kept );
        unset( $at );
        return array( $model, count( $kept ), $notes, isset( $kept[0]['id'] ) ? (string) $kept[0]['id'] : null );
    }

    /**
     * Add an empty column at the end of a set.
     *
     * @param array  $model Model.
     * @param string $id    Columns ID.
     * @return array|WP_Error|null array( model, column count ), a refusal, or null.
     */
    public static function add_column( $model, $id ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $group = &$at[0][ $at[1] ];
        if ( ( $group['type'] ?? '' ) !== 'columns' ) {
            unset( $group, $at );
            return self::refusal( __( 'That is not a set of columns.', 'quill-accessible-forms' ) );
        }
        if ( count( $group['columns'] ) >= self::MAX_COLUMNS ) {
            unset( $group, $at );
            /* translators: %d: the most columns a set may have. */
            return self::refusal( sprintf( __( 'A set has at most %d columns.', 'quill-accessible-forms' ), self::MAX_COLUMNS ) );
        }
        $group['columns'][] = array( 'items' => array() );
        $n                  = count( $group['columns'] );
        unset( $group, $at );
        return array( $model, $n );
    }

    /**
     * Remove one column; its questions join the column before it (or, for
     * the first, the one after). The last column cannot go: remove the set.
     *
     * @param array  $model Model.
     * @param string $id    Columns ID.
     * @param int    $index Column index (0-based).
     * @return array|WP_Error|null array( model, how many questions moved, the column they joined, 1-based ), a refusal, or null.
     */
    public static function remove_column( $model, $id, $index ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $group = &$at[0][ $at[1] ];
        $index = (int) $index;
        if ( ( $group['type'] ?? '' ) !== 'columns' || ! isset( $group['columns'][ $index ] ) ) {
            unset( $group, $at );
            return self::refusal( __( 'That column is no longer in the form. Reload the builder.', 'quill-accessible-forms' ) );
        }
        if ( count( $group['columns'] ) < 2 ) {
            unset( $group, $at );
            return self::refusal( __( 'This is the only column. To take the columns away, remove the set; its questions stay.', 'quill-accessible-forms' ) );
        }
        $moving = $group['columns'][ $index ]['items'] ?? array();
        $into   = $index > 0 ? $index - 1 : 1;
        if ( $into < $index ) {
            $group['columns'][ $into ]['items'] = array_merge( $group['columns'][ $into ]['items'] ?? array(), $moving );
        } else {
            $group['columns'][ $into ]['items'] = array_merge( $moving, $group['columns'][ $into ]['items'] ?? array() );
        }
        array_splice( $group['columns'], $index, 1 );
        unset( $group, $at );
        return array( $model, count( $moving ), $into < $index ? $into + 1 : $into );
    }

    /**
     * Move a column one place left or right within its set.
     *
     * @param array  $model     Model.
     * @param string $id        Columns ID.
     * @param int    $index     Column index (0-based).
     * @param string $direction left|right.
     * @return array|WP_Error|null array( model, new position (1-based), count ), a refusal, or null.
     */
    public static function move_column( $model, $id, $index, $direction ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $group = &$at[0][ $at[1] ];
        $index = (int) $index;
        if ( ( $group['type'] ?? '' ) !== 'columns' || ! isset( $group['columns'][ $index ] ) ) {
            unset( $group, $at );
            return self::refusal( __( 'That column is no longer in the form. Reload the builder.', 'quill-accessible-forms' ) );
        }
        $count = count( $group['columns'] );
        $to    = $direction === 'left' ? $index - 1 : $index + 1;
        if ( $to >= 0 && $to < $count ) {
            $tmp                        = $group['columns'][ $index ];
            $group['columns'][ $index ] = $group['columns'][ $to ];
            $group['columns'][ $to ]    = $tmp;
            $index                      = $to;
        }
        unset( $group, $at );
        return array( $model, $index + 1, $count );
    }
}
