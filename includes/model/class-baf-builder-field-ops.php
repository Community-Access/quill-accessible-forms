<?php
/**
 * What the Visual mode's field cards do, done on the form model: add a
 * question, duplicate one, delete one, put them in a new order, and save a
 * question's settings (label, help, placeholder, required). Each changes
 * only its own question (or the order) in a copy of the model; the caller
 * saves it through BAF_Form::set_model(), so BAF_Form_Model::validate()
 * checks it as it checks any other save.
 *
 * Also draws a question's settings for the field settings panel. Uses only
 * __(), esc_html() and esc_attr() from WordPress, so the tests run it with
 * the mocks.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Builder_Field_Ops class.
 */
class BAF_Builder_Field_Ops {

    /** Types that need choices (the schema's rule), given two to start. */
    const CHOICE_TYPES = array( 'select', 'multiselect', 'radio', 'checkbox', 'ranking', 'image_choice', 'quiz' );

    /** Types whose box can show a placeholder. */
    const PLACEHOLDER_TYPES = array( 'text', 'textarea', 'email', 'url', 'phone', 'number', 'password', 'search' );

    /** The settings the panel edits. */
    const SETTINGS = array( 'label', 'help', 'placeholder', 'required' );

    /**
     * The list an item sits in, by reference, and its index there.
     *
     * @param array  $model Model (by reference).
     * @param string $id    Item ID.
     * @return array|null array( &list, index ) or null.
     */
    private static function &locate( &$model, $id ) {
        $none = null;
        foreach ( BAF_Form_Model::items( $model ) as $entry ) {
            if ( ( $entry['item']['id'] ?? null ) !== $id ) {
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
     * An ID not yet used in the model: base, then base_2, base_3...
     *
     * @param array  $model    Model.
     * @param string $base     Wanted ID.
     * @param array  $reserved IDs taken already though not in the model (ID => true).
     * @return string ID.
     */
    public static function unique_id( $model, $base, $reserved = array() ) {
        $base = preg_replace( '/[^A-Za-z0-9_]+/', '_', (string) $base );
        $base = trim( $base, '_' );
        if ( $base === '' || ctype_digit( $base[0] ) ) {
            $base = 'q_' . $base;
        }
        $used = $reserved;
        foreach ( BAF_Form_Model::items( $model ) as $entry ) {
            if ( isset( $entry['item']['id'] ) ) {
                $used[ $entry['item']['id'] ] = true;
            }
        }
        foreach ( $model['pages'] ?? array() as $p ) {
            $used[ $p['id'] ] = true;
        }
        if ( ! isset( $used[ $base ] ) ) {
            return $base;
        }
        for ( $n = 2; ; $n++ ) {
            if ( ! isset( $used[ $base . '_' . $n ] ) ) {
                return $base . '_' . $n;
            }
        }
    }

    /**
     * A new question of a type, with what the schema needs for that type.
     *
     * @param string $id    ID.
     * @param string $type  Field type.
     * @param string $label Label.
     * @return array Field.
     */
    public static function new_field( $id, $type, $label ) {
        $field = array( 'id' => $id, 'type' => $type, 'label' => $label );
        if ( in_array( $type, self::CHOICE_TYPES, true ) ) {
            $field['choices'] = array(
                array( 'value' => 'option-1', 'label' => __( 'Option 1', 'bits-accessible-forms' ) ),
                array( 'value' => 'option-2', 'label' => __( 'Option 2', 'bits-accessible-forms' ) ),
            );
        }
        if ( $type === 'quiz' ) {
            $field['scoring'] = array( 'points' => 1 );
        }
        if ( $type === 'calculation' ) {
            $field['calculation'] = '0';
        }
        if ( $type === 'matrix' ) {
            $field['props'] = array( 'rows' => array( __( 'Row 1', 'bits-accessible-forms' ) ), 'columns' => array( __( 'Column 1', 'bits-accessible-forms' ) ) );
        }
        if ( $type === 'likert' ) {
            $field['props'] = array( 'rows' => array( __( 'Statement 1', 'bits-accessible-forms' ) ) );
        }
        return $field;
    }

    /**
     * Add a question at the end of the last page.
     *
     * @param array  $model Model.
     * @param string $type  Field type.
     * @param string $label Label.
     * @return array array( model, id ).
     */
    public static function add( $model, $type, $label ) {
        $id = self::unique_id( $model, $type );
        if ( empty( $model['pages'] ) ) {
            $model['pages'] = array( array( 'id' => self::unique_id( $model, 'page_1' ), 'items' => array() ) );
        }
        $last                               = count( $model['pages'] ) - 1;
        $model['pages'][ $last ]['items'][] = self::new_field( $id, $type, $label );
        return array( $model, $id );
    }

    /**
     * Copy a question, just after it, with a new ID and " (copy)" on its label.
     *
     * @param array  $model  Model.
     * @param string $id     Question ID.
     * @param string $suffix What to add to the label.
     * @return array|null array( model, new id ), or null when there is no such question.
     */
    public static function duplicate( $model, $id, $suffix ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $copy       = $at[0][ $at[1] ];
        $copy['id'] = self::unique_id( $model, $id );
        if ( isset( $copy['label'] ) && $copy['label'] !== '' ) {
            $copy['label'] .= $suffix;
        }
        // A repeater's own questions need IDs of their own as well.
        if ( isset( $copy['items'] ) ) {
            $reserved      = array( $copy['id'] => true );
            $copy['items'] = self::renumber( $model, $copy['items'], $reserved );
        }
        array_splice( $at[0], $at[1] + 1, 0, array( $copy ) );
        return array( $model, $copy['id'] );
    }

    /**
     * New IDs for a list of copied items.
     *
     * @param array $model    Model (for the IDs in use).
     * @param array $items    Items.
     * @param array $reserved IDs given out already (ID => true), added to.
     * @return array Items.
     */
    private static function renumber( $model, $items, &$reserved ) {
        foreach ( $items as $i => $item ) {
            if ( isset( $item['id'] ) ) {
                $items[ $i ]['id']                = self::unique_id( $model, $item['id'], $reserved );
                $reserved[ $items[ $i ]['id'] ] = true;
            }
            if ( isset( $item['items'] ) ) {
                $items[ $i ]['items'] = self::renumber( $model, $item['items'], $reserved );
            }
        }
        return $items;
    }

    /**
     * Delete a question.
     *
     * @param array  $model Model.
     * @param string $id    Question ID.
     * @return array|null Model, or null when there is no such question.
     */
    public static function remove( $model, $id ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        array_splice( $at[0], $at[1], 1 );
        return $model;
    }

    /**
     * Put questions in the order given. Each question moves only among its
     * own neighbours (the same page, section or column): moving between
     * pages is done in Structure mode. The caller compares order() with
     * what it asked for to learn whether everything moved.
     *
     * @param array $model Model.
     * @param array $ids   Question IDs, in the wanted order.
     * @return array Model.
     */
    public static function reorder( $model, $ids ) {
        $rank = array_flip( array_values( array_map( 'strval', $ids ) ) );
        $sort = function ( &$list ) use ( &$sort, $rank ) {
            $slots = array();
            foreach ( $list as $i => $item ) {
                if ( isset( $item['id'], $rank[ $item['id'] ] ) ) {
                    $slots[] = $i;
                }
            }
            $moving = array_map( function ( $i ) use ( $list ) { return $list[ $i ]; }, $slots );
            usort( $moving, function ( $a, $b ) use ( $rank ) { return $rank[ $a['id'] ] <=> $rank[ $b['id'] ]; } );
            foreach ( $slots as $k => $i ) {
                $list[ $i ] = $moving[ $k ];
            }
            foreach ( $list as $i => $item ) {
                if ( ( $item['type'] ?? '' ) === 'columns' ) {
                    foreach ( $list[ $i ]['columns'] as $c => $col ) {
                        $sort( $list[ $i ]['columns'][ $c ]['items'] );
                    }
                } elseif ( isset( $item['items'] ) && is_array( $item['items'] ) ) {
                    $sort( $list[ $i ]['items'] );
                }
            }
        };
        foreach ( $model['pages'] as $p => $page ) {
            $sort( $model['pages'][ $p ]['items'] );
        }
        return $model;
    }

    /**
     * The model's order of the given IDs.
     *
     * @param array $model Model.
     * @param array $ids   IDs.
     * @return array IDs, in the model's order.
     */
    public static function order( $model, $ids ) {
        $want = array_flip( array_map( 'strval', $ids ) );
        $out  = array();
        foreach ( BAF_Form_Model::items( $model ) as $entry ) {
            if ( isset( $entry['item']['id'], $want[ $entry['item']['id'] ] ) ) {
                $out[] = $entry['item']['id'];
            }
        }
        return $out;
    }

    /**
     * Save a question's settings: only label, help, placeholder and required.
     * An empty text, or required off, is removed rather than stored empty.
     *
     * @param array  $model    Model.
     * @param string $id       Question ID.
     * @param array  $settings Settings, as sent; a key left out is kept as it was.
     * @return array|null Model, or null when there is no such question.
     */
    public static function apply_settings( $model, $id, $settings ) {
        $at = &self::locate( $model, $id );
        if ( $at === null ) {
            return null;
        }
        $field = &$at[0][ $at[1] ];
        foreach ( array( 'label', 'help', 'placeholder' ) as $k ) {
            if ( ! array_key_exists( $k, $settings ) ) {
                continue;
            }
            $v = trim( (string) $settings[ $k ] );
            if ( $v === '' ) {
                unset( $field[ $k ] );
            } else {
                $field[ $k ] = $v;
            }
        }
        if ( array_key_exists( 'required', $settings ) ) {
            if ( $settings['required'] && $settings['required'] !== 'false' && $settings['required'] !== '0' ) {
                $field['required'] = true;
            } else {
                unset( $field['required'] );
            }
        }
        return $model;
    }

    /**
     * Problems with settings before they are saved.
     *
     * @param array $settings Settings.
     * @return array Problems: array( field, message ).
     */
    public static function check_settings( $settings ) {
        if ( array_key_exists( 'label', $settings ) && trim( (string) $settings['label'] ) === '' ) {
            return array( array( 'field' => 'label', 'message' => __( 'A question needs a label, so people know what it asks.', 'bits-accessible-forms' ) ) );
        }
        return array();
    }

    /**
     * The settings panel's content for a question.
     *
     * @param array  $field      The question, from the model.
     * @param string $type_label The type's name, such as "Dropdown".
     * @return string HTML.
     */
    public static function render_settings( $field, $type_label = '' ) {
        $label = ( $field['label'] ?? '' ) !== '' ? $field['label'] : '#' . ( $field['id'] ?? '' );
        $place = in_array( $field['type'] ?? '', self::PLACEHOLDER_TYPES, true ) || isset( $field['placeholder'] );
        ob_start();
        ?>
<section class="baf-field-settings-section" aria-labelledby="baf-fs-heading" data-field-id="<?php echo esc_attr( $field['id'] ?? '' ); ?>">
    <h3 id="baf-fs-heading" tabindex="-1"><?php echo esc_html( sprintf( __( 'Settings: %s', 'bits-accessible-forms' ), $label ) ); ?></h3>
    <p><?php echo esc_html( sprintf( __( 'Type: %1$s. ID: %2$s.', 'bits-accessible-forms' ), $type_label !== '' ? $type_label : ( $field['type'] ?? '' ), $field['id'] ?? '' ) ); ?></p>
    <div id="baf-fs-summary" class="baf-error-summary" tabindex="-1" hidden></div>
    <div id="baf-fs-status" class="baf-status" role="status" aria-live="polite" aria-atomic="true"></div>
    <form id="baf-fs-form" novalidate>
        <p>
            <label for="baf-fs-label"><?php esc_html_e( 'Label', 'bits-accessible-forms' ); ?></label><br>
            <input type="text" id="baf-fs-label" name="label" class="regular-text" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" aria-describedby="baf-fs-label-help baf-fs-label-error" aria-required="true">
            <span id="baf-fs-label-help" class="description"><?php esc_html_e( 'What the question asks. Screen readers say it when the question is reached.', 'bits-accessible-forms' ); ?></span>
            <span id="baf-fs-label-error" class="baf-field-error" hidden></span>
        </p>
        <p>
            <label for="baf-fs-help"><?php esc_html_e( 'Help', 'bits-accessible-forms' ); ?></label><br>
            <textarea id="baf-fs-help" name="help" rows="2" class="large-text" aria-describedby="baf-fs-help-help"><?php echo esc_html( (string) ( $field['help'] ?? '' ) ); ?></textarea>
            <span id="baf-fs-help-help" class="description"><?php esc_html_e( 'Shown under the question and read after its label. Leave it empty for none.', 'bits-accessible-forms' ); ?></span>
        </p>
        <?php if ( $place ) : ?>
            <p>
                <label for="baf-fs-placeholder"><?php esc_html_e( 'Placeholder', 'bits-accessible-forms' ); ?></label><br>
                <input type="text" id="baf-fs-placeholder" name="placeholder" class="regular-text" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" aria-describedby="baf-fs-placeholder-help">
                <span id="baf-fs-placeholder-help" class="description"><?php esc_html_e( 'An example inside the empty box. It is not a label; put anything people must know in the label or help.', 'bits-accessible-forms' ); ?></span>
            </p>
        <?php endif; ?>
        <p>
            <input type="checkbox" id="baf-fs-required" name="required" value="1"<?php echo ! empty( $field['required'] ) ? ' checked' : ''; ?>>
            <label for="baf-fs-required"><?php esc_html_e( 'Required', 'bits-accessible-forms' ); ?></label>
        </p>
        <p>
            <button type="submit" class="button button-primary baf-settings-save"><?php esc_html_e( 'Save settings', 'bits-accessible-forms' ); ?></button>
            <button type="button" class="button baf-settings-close"><?php esc_html_e( 'Close', 'bits-accessible-forms' ); ?></button>
        </p>
    </form>
</section>
        <?php
        return ob_get_clean();
    }
}
