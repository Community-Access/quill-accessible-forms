<?php
/**
 * Form Builder Class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Form_Builder class
 * 
 * Handles form builder functionality with full accessibility support.
 */
class QAF_Form_Builder {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers are registered via loader
    }

    /**
     * AJAX handler for saving form
     */
    public function ajax_save_form() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $form_data = isset( $_POST['form_data'] ) ? json_decode( stripslashes( $_POST['form_data'] ), true ) : array();

        if ( empty( $form_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid form data.', 'quill-accessible-forms' ) ) );
        }

        $form = new QAF_Form( $form_id );
        $form->title = sanitize_text_field( $form_data['title'] ?? '' );
        $form->description = wp_kses_post( $form_data['description'] ?? '' );
        $form->status = sanitize_key( $form_data['status'] ?? 'draft' );

        // The builder edits the model's fields; what it has no place for
        // (rules, outcomes, translations) is kept from the stored model.
        $report = $form->apply_builder_config( $form_data['config'] ?? array(), $form_data['settings'] ?? array() );

        $result = $form->save();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Form saved successfully.', 'quill-accessible-forms' ),
            'form_id' => $result,
            'report'  => $report->to_array(),
        ) );
    }

    /**
     * AJAX: import a form from another builder's export (POST source and
     * export, the file's text) or from Google Forms (POST source=google-forms,
     * google_form_id and access_token). Saves it as a draft and returns the
     * import report.
     */
    public function ajax_import_form() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-importers.php';
        $source = sanitize_key( $_POST['source'] ?? '' );
        if ( ! isset( QAF_Importers::SOURCES[ $source ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose what the form comes from.', 'quill-accessible-forms' ) ) );
        }
        try {
            if ( $source === 'google-forms' && ! empty( $_POST['google_form_id'] ) ) {
                $result = QAF_Importers::fetch_google_form( sanitize_text_field( wp_unslash( $_POST['google_form_id'] ) ), sanitize_text_field( wp_unslash( $_POST['access_token'] ?? '' ) ) );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
            } else {
                $result = QAF_Importers::import( $source, wp_unslash( $_POST['export'] ?? '' ) );
            }
        } catch ( InvalidArgumentException $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }

        $form = new QAF_Form();
        $set  = $form->set_model( $result['model'] );
        if ( is_wp_error( $set ) ) {
            wp_send_json_error( array( 'message' => $set->get_error_message(), 'report' => $result['report']->to_array() ) );
        }
        $form->status = 'draft';
        $id           = $form->save();
        if ( is_wp_error( $id ) ) {
            wp_send_json_error( array( 'message' => $id->get_error_message() ) );
        }
        wp_send_json_success( array(
            'form_id'     => $id,
            'title'       => $form->title,
            'edit_url'    => admin_url( 'admin.php?page=qaf-form-builder&form_id=' . absint( $id ) ),
            'report'      => $result['report']->to_array(),
            'report_text' => $result['report']->to_text(),
        ) );
    }

    /**
     * AJAX: export a form to another builder's format, with its report.
     */
    public function ajax_export_form() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-exporters.php';
        $target = sanitize_key( $_POST['target'] ?? '' );
        if ( ! isset( QAF_Exporters::TARGETS[ $target ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose what to export the form to.', 'quill-accessible-forms' ) ) );
        }
        $form = new QAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'quill-accessible-forms' ) ) );
        }

        // QAF's built-in libraries (countries, months...) are written out as choices.
        QAF_Exporters::$library_resolver = array( __CLASS__, 'builtin_library_choices' );
        $result = QAF_Exporters::export( $target, $form->get_model() );
        wp_send_json_success( array(
            'text'        => $result['text'],
            'filename'    => $result['filename'],
            'report'      => $result['report']->to_array(),
            'report_text' => $result['report']->to_text(),
        ) );
    }

    /**
     * The choices of one of QAF's built-in libraries (countries, months...)
     * as the model writes choices, or an empty list when there is none.
     *
     * @param string $name Library name.
     * @return array Choices: array( value, label ).
     */
    public static function builtin_library_choices( $name ) {
        if ( ! class_exists( 'QAF_Choice_Libraries' ) || ! QAF_Choice_Libraries::exists( $name ) ) {
            return array();
        }
        $out = array();
        foreach ( (array) QAF_Choice_Libraries::get( $name ) as $key => $choice ) {
            if ( is_array( $choice ) ) {
                $value = (string) ( $choice['value'] ?? $key );
                $out[] = array( 'value' => $value, 'label' => (string) ( $choice['label'] ?? $value ) );
            } else {
                $out[] = array( 'value' => (string) $key, 'label' => (string) $choice );
            }
        }
        return $out;
    }

    /**
     * AJAX: pasted choice lines (POST lines), read by the BFL reader, for
     * the Choice libraries screen.
     */
    public function ajax_read_choice_lines() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }
        wp_send_json_success( QAF_BFL_Service::read_choice_lines( wp_unslash( $_POST['lines'] ?? '' ) ) );
    }

    /**
     * AJAX: a question's Scoring section (POST form_id, field_id), drawn
     * from the question in the stored model, for the Visual mode's field
     * settings panel. admin/js/qaf-field-scoring.js saves it.
     */
    public function ajax_get_field_scoring() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }
        $form = new QAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Save the form first; then its questions can be scored.', 'quill-accessible-forms' ) ) );
        }
        $model = $form->get_model();
        $field = QAF_Field_Scoring_Panel::find( $model, sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) ) );
        if ( ! $field ) {
            wp_send_json_error( array( 'message' => __( 'This question is not in the saved form yet. Save the form, then try again.', 'quill-accessible-forms' ) ) );
        }
        if ( ! QAF_Field_Scoring_Panel::is_scored_type( $field['type'] ?? '' ) ) {
            wp_send_json_error( array( 'message' => __( 'This type of question is not scored.', 'quill-accessible-forms' ) ) );
        }
        wp_send_json_success( array(
            'field_id' => $field['id'],
            'label'    => $field['label'] ?? '',
            'html'     => QAF_Field_Scoring_Panel::render( $field, $model, array( __CLASS__, 'builtin_library_choices' ) ),
        ) );
    }

    /**
     * AJAX: the choices of a built-in library (POST name), so the Choice
     * libraries screen can copy one into the form.
     */
    public function ajax_get_choice_library() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }
        $name    = sanitize_key( $_POST['name'] ?? '' );
        $choices = self::builtin_library_choices( $name );
        if ( ! $choices ) {
            wp_send_json_error( array( 'message' => __( 'There is no built-in library by that name.', 'quill-accessible-forms' ) ) );
        }
        wp_send_json_success( array( 'name' => $name, 'choices' => $choices ) );
    }

    /**
     * AJAX: the form model, its canonical BFL, and the conversion report.
     */
    public function ajax_get_form_model() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $form = new QAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'quill-accessible-forms' ) ) );
        }
        $result = $form->get_model_and_report();
        wp_send_json_success( array(
            'model'  => $result['model'],
            'bfl'    => QAF_BFL_Writer::write( $result['model'] ),
            'report' => $result['report']->to_array(),
        ) );
    }

    /**
     * AJAX: save a form from a model (JSON in "model") or from BFL ("bfl").
     */
    public function ajax_save_form_model() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        if ( isset( $_POST['bfl'] ) ) {
            $read = QAF_BFL_Reader::read( wp_unslash( $_POST['bfl'] ) );
            if ( $read['errors'] ) {
                wp_send_json_error( array( 'message' => __( 'The QAF form language text has errors.', 'quill-accessible-forms' ), 'errors' => $read['errors'] ) );
            }
            $model = $read['model'];
        } else {
            $model = json_decode( wp_unslash( $_POST['model'] ?? '' ), true );
        }
        if ( ! is_array( $model ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid form data.', 'quill-accessible-forms' ) ) );
        }

        $form = new QAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        $set  = $form->set_model( $model );
        if ( is_wp_error( $set ) ) {
            wp_send_json_error( array( 'message' => $set->get_error_message(), 'errors' => $set->get_error_data() ) );
        }
        $result = $form->save();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array(
            'message' => __( 'Form saved successfully.', 'quill-accessible-forms' ),
            'form_id' => $result,
        ) );
    }

    // ---------------------------------------------------------------------
    // The Visual mode's field cards, on the form model
    // ---------------------------------------------------------------------

    /**
     * The form named by POST form_id and its model, or a JSON error. With
     * $create, a form not yet saved (form_id 0) is made now, as a draft
     * with the title in POST title, so a new form can take its first
     * question without being saved and reloaded first.
     *
     * @param bool $create Make the form when there is none yet.
     * @return array array( QAF_Form, model, created ).
     */
    private function card_form( $create = false ) {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }
        $form = new QAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( $form->id ) {
            return array( $form, $form->get_model(), false );
        }
        if ( ! $create ) {
            wp_send_json_error( array( 'message' => __( 'This form has not been made yet. Add a question, or choose Save Draft, to make it.', 'quill-accessible-forms' ) ) );
        }
        $form->status = 'draft';
        return array( $form, QAF_Builder_Field_Ops::new_model( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) ), true );
    }

    /**
     * Save a changed model, or send why not (the message, and validate()'s
     * problems with their JSON pointers).
     *
     * @param QAF_Form   $form  Form.
     * @param array|null $model Model, or null when the question was not found.
     */
    private function card_save( $form, $model ) {
        if ( $model === null ) {
            wp_send_json_error( array( 'message' => __( 'That question is no longer in the form. Reload the builder.', 'quill-accessible-forms' ) ) );
        }
        $set = $form->set_model( $model );
        if ( is_wp_error( $set ) ) {
            $errors = (array) $set->get_error_data();
            $first  = $errors ? $errors[0]['message'] : '';
            wp_send_json_error( array( 'message' => trim( $set->get_error_message() . ' ' . $first ), 'errors' => $errors ) );
        }
        $saved = $form->save();
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
        }
        if ( ! $form->id && is_numeric( $saved ) ) {
            $form->id = (int) $saved;
        }
    }

    /**
     * What every card action sends back: the canvas drawn again from the
     * saved model, and the form's ID (new when this action made the form).
     *
     * @param QAF_Form $form    Form.
     * @param array    $model   Model.
     * @param bool     $created Whether this action made the form.
     * @param array    $extra   More to send.
     */
    private function card_reply( $form, $model, $created, $extra = array() ) {
        wp_send_json_success( array_merge( $extra, array(
            'form_id'  => (int) $form->id,
            'created'  => (bool) $created,
            'edit_url' => admin_url( 'admin.php?page=qaf-form-builder&form_id=' . absint( $form->id ) ),
            'canvas'   => $this->render_canvas( $model, (int) $form->id ),
        ) ) );
    }

    /**
     * The canvas: a list of cards per page, and inside it a group for each
     * section and for each column, so every question, however deep, has a
     * card with the same actions. A move stays among its neighbours.
     *
     * @param array $model   Model.
     * @param int   $form_id Form ID.
     * @return string HTML.
     */
    public function render_canvas( $model, $form_id = 0 ) {
        $tree  = QAF_Builder_Field_Ops::canvas_tree( $model );
        $count = 0;
        $items = QAF_Form_Model::items( $model );
        foreach ( $items as $e ) {
            $count += ( isset( $e['item']['type'] ) && ! in_array( $e['item']['type'], array( 'section', 'columns' ), true ) ) ? 1 : 0;
        }
        // An empty section or set of columns still has a card, with its actions.
        if ( ! $count && ! $items ) {
            return '<div class="qaf-canvas-empty"><p>' . esc_html__( 'No questions yet. Add one from the palette, or with Add Field.', 'quill-accessible-forms' ) . '</p>'
                . '<p class="qaf-canvas-hint">' . esc_html__( 'Tip: press F while in the canvas to add a question.', 'quill-accessible-forms' ) . '</p></div>';
        }
        $html  = '';
        $many  = count( $tree ) > 1;
        $group = 0;
        $list  = function ( $nodes, $label_id ) use ( &$list, &$group, $form_id ) {
            $out   = '<div class="qaf-card-list" role="list" aria-labelledby="' . esc_attr( $label_id ) . '">';
            $total = count( $nodes );
            foreach ( $nodes as $i => $n ) {
                $item = $n['item'];
                if ( $n['kind'] === 'field' ) {
                    $out .= $this->render_field_card(
                        array( 'id' => $item['id'], 'type' => $item['type'], 'label' => $item['label'] ?? '', 'required' => ! empty( $item['required'] ) ),
                        $i + 1,
                        $total,
                        $form_id
                    );
                    continue;
                }
                $gid  = 'qaf-canvas-group-' . ( ++$group );
                $name = $n['kind'] === 'section'
                    ? sprintf( __( 'Section: %s', 'quill-accessible-forms' ), ( $item['label'] ?? '' ) !== '' ? $item['label'] : '#' . $item['id'] )
                    : sprintf( _n( 'Columns: %d column', 'Columns: %d columns', count( $n['columns'] ), 'quill-accessible-forms' ), count( $n['columns'] ) );
                $out .= '<div class="qaf-group-card" role="listitem" data-group-id="' . esc_attr( $item['id'] ) . '" data-group-kind="' . esc_attr( $n['kind'] ) . '">';
                $out .= '<p class="qaf-group-label" id="' . esc_attr( $gid ) . '" tabindex="-1">' . esc_html( $name ) . '</p>';
                $out .= $this->render_group_actions( $item['id'], $n['kind'], $name, $i + 1, $total, $n['kind'] === 'columns' ? count( $n['columns'] ) : 0 );
                if ( $n['kind'] === 'section' ) {
                    $out .= $n['items'] ? $list( $n['items'], $gid ) : '<p class="qaf-group-empty">' . esc_html__( 'No questions in this section yet. Move one in with Structure mode.', 'quill-accessible-forms' ) . '</p>';
                } else {
                    foreach ( $n['columns'] as $c => $col ) {
                        $cid  = $gid . '-' . ( $c + 1 );
                        $out .= '<p class="qaf-group-label qaf-column-label" id="' . esc_attr( $cid ) . '" tabindex="-1">' . esc_html( sprintf( __( 'Column %1$d of %2$d', 'quill-accessible-forms' ), $c + 1, count( $n['columns'] ) ) ) . '</p>';
                        $out .= $this->render_column_actions( $item['id'], $c, count( $n['columns'] ) );
                        $out .= $col ? $list( $col, $cid ) : '<p class="qaf-group-empty">' . esc_html__( 'No questions in this column yet.', 'quill-accessible-forms' ) . '</p>';
                    }
                }
                $out .= '</div>';
            }
            return $out . '</div>';
        };
        foreach ( $tree as $p => $page ) {
            $label_id = 'qaf-canvas-title';
            if ( $many ) {
                $label_id = 'qaf-canvas-page-' . ( $p + 1 );
                $title    = $page['title'] !== '' ? sprintf( __( 'Page %1$d: %2$s', 'quill-accessible-forms' ), $p + 1, $page['title'] ) : sprintf( __( 'Page %d', 'quill-accessible-forms' ), $p + 1 );
                $html    .= '<h3 class="qaf-canvas-page" id="' . esc_attr( $label_id ) . '">' . esc_html( $title ) . '</h3>';
            }
            $html .= $page['items'] ? $list( $page['items'], $label_id ) : '<p class="qaf-group-empty">' . esc_html__( 'No questions on this page yet.', 'quill-accessible-forms' ) . '</p>';
        }
        return $html;
    }

    /**
     * A section's or a set of columns' own actions: move up and down among
     * its neighbours, copy, remove (keeping its questions), and for columns,
     * add a column. Each button says what it acts on; a move that cannot go
     * further is aria-disabled, so it stays in the tab order and says why.
     *
     * @param string $id       Group ID.
     * @param string $kind     section|columns.
     * @param string $name     What the group is called on the canvas.
     * @param int    $position Its position among its neighbours (1-based).
     * @param int    $total    How many neighbours.
     * @param int    $columns  How many columns (for a set of columns).
     * @return string HTML.
     */
    public function render_group_actions( $id, $kind, $name, $position, $total, $columns = 0 ) {
        $button = function ( $op, $text, $label, $extra = array() ) use ( $id ) {
            $attrs = array_merge( array( 'type' => 'button', 'class' => 'qaf-action-btn qaf-group-action', 'data-op' => $op, 'data-group-id' => $id, 'aria-label' => $label ), $extra );
            $html  = '<button';
            foreach ( $attrs as $k => $v ) {
                $html .= ' ' . $k . '="' . esc_attr( $v ) . '"';
            }
            return $html . '>' . esc_html( $text ) . '</button>';
        };
        $out  = '<div class="qaf-group-actions" role="group" aria-label="' . esc_attr( sprintf( __( 'Actions for %s', 'quill-accessible-forms' ), $name ) ) . '">';
        /* translators: 1: the group's name, 2: position, 3: how many. */
        $out .= $button( 'move', __( 'Move up', 'quill-accessible-forms' ), sprintf( __( 'Move up: %1$s, position %2$d of %3$d', 'quill-accessible-forms' ), $name, $position, $total ), array_merge( array( 'data-direction' => 'up' ), $position <= 1 ? array( 'aria-disabled' => 'true' ) : array() ) );
        /* translators: 1: the group's name, 2: position, 3: how many. */
        $out .= $button( 'move', __( 'Move down', 'quill-accessible-forms' ), sprintf( __( 'Move down: %1$s, position %2$d of %3$d', 'quill-accessible-forms' ), $name, $position, $total ), array_merge( array( 'data-direction' => 'down' ), $position >= $total ? array( 'aria-disabled' => 'true' ) : array() ) );
        /* translators: %s: the group's name. */
        $out .= $button( 'duplicate', __( 'Copy', 'quill-accessible-forms' ), sprintf( __( 'Copy %s, with copies of its questions', 'quill-accessible-forms' ), $name ) );
        if ( $kind === 'columns' ) {
            $full = $columns >= QAF_Builder_Layout_Ops::MAX_COLUMNS;
            /* translators: %s: the group's name. */
            $out .= $button( 'add_column', __( 'Add column', 'quill-accessible-forms' ), sprintf( __( 'Add column to %s', 'quill-accessible-forms' ), $name ), $full ? array( 'aria-disabled' => 'true' ) : array() );
        }
        $remove = $kind === 'section'
            /* translators: %s: the group's name. */
            ? sprintf( __( 'Remove %s; its questions stay, where it was', 'quill-accessible-forms' ), $name )
            /* translators: %s: the group's name. */
            : sprintf( __( 'Remove %s; its questions stay, in order, where they were', 'quill-accessible-forms' ), $name );
        $out .= $button( 'remove', __( 'Remove', 'quill-accessible-forms' ), $remove );
        return $out . '</div>';
    }

    /**
     * One column's actions: move left and right, remove (its questions join
     * the column beside it).
     *
     * @param string $id    Columns ID.
     * @param int    $index Column index (0-based).
     * @param int    $count How many columns.
     * @return string HTML.
     */
    public function render_column_actions( $id, $index, $count ) {
        $n    = $index + 1;
        $base = array( 'type' => 'button', 'class' => 'qaf-action-btn qaf-group-action', 'data-group-id' => $id, 'data-column' => (string) $index );
        $make = function ( $attrs, $text ) use ( $base ) {
            $html = '<button';
            foreach ( array_merge( $base, $attrs ) as $k => $v ) {
                $html .= ' ' . $k . '="' . esc_attr( $v ) . '"';
            }
            return $html . '>' . esc_html( $text ) . '</button>';
        };
        /* translators: 1: column number, 2: how many. */
        $name = sprintf( __( 'Column %1$d of %2$d', 'quill-accessible-forms' ), $n, $count );
        $out  = '<div class="qaf-group-actions qaf-column-actions" role="group" aria-label="' . esc_attr( sprintf( __( 'Actions for %s', 'quill-accessible-forms' ), $name ) ) . '">';
        /* translators: %s: Column 1 of 2. */
        $out .= $make( array_merge( array( 'data-op' => 'move_column', 'data-direction' => 'left', 'aria-label' => sprintf( __( 'Move left: %s', 'quill-accessible-forms' ), $name ) ), $index === 0 ? array( 'aria-disabled' => 'true' ) : array() ), __( 'Move left', 'quill-accessible-forms' ) );
        /* translators: %s: Column 1 of 2. */
        $out .= $make( array_merge( array( 'data-op' => 'move_column', 'data-direction' => 'right', 'aria-label' => sprintf( __( 'Move right: %s', 'quill-accessible-forms' ), $name ) ), $n >= $count ? array( 'aria-disabled' => 'true' ) : array() ), __( 'Move right', 'quill-accessible-forms' ) );
        /* translators: %s: Column 1 of 2. */
        $out .= $make( array_merge( array( 'data-op' => 'remove_column', 'aria-label' => sprintf( __( 'Remove %s; its questions join the column beside it', 'quill-accessible-forms' ), $name ) ), $count < 2 ? array( 'aria-disabled' => 'true' ) : array() ), __( 'Remove column', 'quill-accessible-forms' ) );
        return $out . '</div>';
    }

    /**
     * AJAX: an action on a section or a set of columns (POST form_id, op,
     * and group_id, direction, column, label, count as the op needs). Ops:
     * add_section, add_columns (after group_id, or at the end), move,
     * duplicate, remove, add_column, remove_column, move_column. Answers
     * with the canvas, the group to focus, and a sentence that says what
     * happened, for the page to speak.
     */
    public function ajax_layout_action() {
        $op = sanitize_key( $_POST['op'] ?? '' );
        list( $form, $model, $created ) = $this->card_form( in_array( $op, array( 'add_section', 'add_columns' ), true ) );
        $id     = sanitize_text_field( wp_unslash( $_POST['group_id'] ?? '' ) );
        $dir    = sanitize_key( $_POST['direction'] ?? '' );
        $column = isset( $_POST['column'] ) ? absint( $_POST['column'] ) : 0;
        $result = $this->layout_op( $model, $op, $id, $dir, $column, array(
            'label' => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
            'count' => isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 2,
            'after' => sanitize_text_field( wp_unslash( $_POST['after'] ?? '' ) ),
        ) );
        if ( isset( $result['error'] ) ) {
            if ( $result['error'] === null ) {
                $this->card_save( $form, null );
            }
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
        $this->card_save( $form, $result['model'] );
        $this->card_reply( $form, $result['model'], $created, array(
            'group_id' => $result['focus'],
            'column'   => $result['column'],
            'message'  => $result['message'],
        ) );
    }

    /**
     * Do one layout op on a model, and say what was done.
     *
     * @param array  $model  Model.
     * @param string $op     Op.
     * @param string $id     Group ID.
     * @param string $dir    up|down|left|right.
     * @param int    $column Column index.
     * @param array  $args   label, count, after.
     * @return array model, focus (group ID), column (index or null), message; or error (null when not found).
     */
    public function layout_op( $model, $op, $id, $dir, $column, $args = array() ) {
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-layout-ops.php';
        $labels = QAF_Form_Model::labels( $model );
        $name   = ( $labels[ $id ] ?? '' ) !== '' ? $labels[ $id ] : $id;
        foreach ( QAF_Form_Model::items( $model ) as $e ) {
            if ( ( $e['item']['id'] ?? '' ) === $id && ( $e['item']['type'] ?? '' ) === 'columns' ) {
                $name = __( 'The set of columns', 'quill-accessible-forms' );
            }
        }
        $out    = array( 'model' => null, 'focus' => $id, 'column' => null, 'message' => '' );
        switch ( $op ) {
            case 'add_section':
                list( $out['model'], $out['focus'] ) = QAF_Builder_Layout_Ops::add_section( $model, $args['label'] ?? '', $args['after'] ?? '' );
                /* translators: %s: the section's heading. */
                $out['message'] = sprintf( __( 'Section %s added. Move questions into it in Structure mode.', 'quill-accessible-forms' ), QAF_Form_Model::labels( $out['model'] )[ $out['focus'] ] );
                return $out;
            case 'add_columns':
                $count = max( 2, min( QAF_Builder_Layout_Ops::MAX_COLUMNS, (int) ( $args['count'] ?? 2 ) ) );
                list( $out['model'], $out['focus'] ) = QAF_Builder_Layout_Ops::add_columns( $model, $count, $args['after'] ?? '' );
                /* translators: %d: how many columns. */
                $out['message'] = sprintf( __( 'A set of %d columns added.', 'quill-accessible-forms' ), $count );
                return $out;
            case 'move':
                $r = QAF_Builder_Layout_Ops::move( $model, $id, $dir === 'up' ? 'up' : 'down' );
                if ( $r === null ) {
                    return array( 'error' => null );
                }
                $out['model'] = $r[0];
                $moved        = $r[0] !== $model;
                $out['message'] = $moved
                    /* translators: 1: group, 2: position, 3: how many. */
                    ? sprintf( __( '%1$s moved to position %2$d of %3$d.', 'quill-accessible-forms' ), $name, $r[1], $r[2] )
                    /* translators: %s: group. */
                    : ( $dir === 'up' ? sprintf( __( '%s is already first.', 'quill-accessible-forms' ), $name ) : sprintf( __( '%s is already last.', 'quill-accessible-forms' ), $name ) );
                return $out;
            case 'duplicate':
                $r = QAF_Builder_Layout_Ops::duplicate( $model, $id, __( ' (copy)', 'quill-accessible-forms' ) );
                break;
            case 'remove':
                $r = QAF_Builder_Layout_Ops::remove( $model, $id );
                break;
            case 'add_column':
                $r = QAF_Builder_Layout_Ops::add_column( $model, $id );
                break;
            case 'remove_column':
                $r = QAF_Builder_Layout_Ops::remove_column( $model, $id, $column );
                break;
            case 'move_column':
                $r = QAF_Builder_Layout_Ops::move_column( $model, $id, $column, $dir === 'left' ? 'left' : 'right' );
                break;
            default:
                return array( 'error' => __( 'That is not something the canvas can do.', 'quill-accessible-forms' ) );
        }
        if ( $r === null ) {
            return array( 'error' => null );
        }
        $why = QAF_Builder_Field_Ops::refused( $r );
        if ( $why !== null ) {
            return array( 'error' => $why );
        }
        $out['model'] = $r[0];
        switch ( $op ) {
            case 'duplicate':
                $out['focus'] = $r[1];
                /* translators: %s: group. */
                $out['message'] = sprintf( __( '%s copied, with copies of its questions, just after it.', 'quill-accessible-forms' ), $name );
                break;
            case 'remove':
                // Focus goes to the first question it held, now where it was.
                $out['focus'] = $r[3];
                /* translators: 1: group, 2: how many questions. */
                $out['message'] = trim( sprintf( _n( '%1$s removed. Its %2$d question stays where it was.', '%1$s removed. Its %2$d questions stay where they were.', $r[1], 'quill-accessible-forms' ), $name, $r[1] ) . ' ' . implode( ' ', $r[2] ) );
                break;
            case 'add_column':
                $out['column'] = $r[1] - 1;
                /* translators: %d: how many columns now. */
                $out['message'] = sprintf( __( 'Column added. The set has %d columns.', 'quill-accessible-forms' ), $r[1] );
                break;
            case 'remove_column':
                $out['column'] = $r[2] - 1;
                /* translators: 1: how many questions, 2: the column they joined. */
                $out['message'] = sprintf( _n( 'Column removed. Its %1$d question joined column %2$d.', 'Column removed. Its %1$d questions joined column %2$d.', $r[1], 'quill-accessible-forms' ), $r[1], $r[2] );
                break;
            case 'move_column':
                $out['column'] = $r[1] - 1;
                $out['message'] = $r[0] === $model
                    ? __( 'That column cannot move further that way.', 'quill-accessible-forms' )
                    /* translators: 1: position, 2: how many. */
                    : sprintf( __( 'Column moved to position %1$d of %2$d.', 'quill-accessible-forms' ), $r[1], $r[2] );
                break;
        }
        return $out;
    }

    /**
     * AJAX: the Accessibility Coach for the form as saved (POST form_id).
     * Answers with the results drawn as HTML, and a sentence to speak.
     */
    public function ajax_coach_check() {
        list( $form, $model ) = $this->card_form();
        $coach  = new QAF_Accessibility_Coach();
        $result = $coach->analyze_model( $model );
        wp_send_json_success( array(
            'html'    => self::render_coach_results( $result, $model ),
            'summary' => self::coach_summary( $result ),
            'count'   => count( $result['issues'] ),
        ) );
    }

    /**
     * What the coach found, in one sentence.
     *
     * @param array $result analyze_model()'s result.
     * @return string Sentence.
     */
    public static function coach_summary( $result ) {
        $s = $result['summary'];
        if ( ! $s['total'] ) {
            return __( 'The Accessibility Coach found nothing to fix.', 'quill-accessible-forms' );
        }
        /* translators: 1: total, 2: errors, 3: warnings, 4: suggestions. */
        return sprintf( _n( 'The Accessibility Coach found %1$d thing to look at: %2$d errors, %3$d warnings, %4$d suggestions. Each is listed with the question it concerns.', 'The Accessibility Coach found %1$d things to look at: %2$d errors, %3$d warnings, %4$d suggestions. Each is listed with the question it concerns.', $s['total'], 'quill-accessible-forms' ), $s['total'], $s['errors'], $s['warnings'], $s['info'] );
    }

    /**
     * Item ID => the ID of the card that shows it on the canvas: itself, or
     * for a question inside a repeating group, the group's card.
     *
     * @param array $model Model.
     * @return array Map.
     */
    public static function card_ids( $model ) {
        $map       = array();
        $repeaters = array();
        foreach ( QAF_Form_Model::items( $model ) as $e ) {
            $id    = (string) ( $e['item']['id'] ?? '' );
            $owner = $id;
            foreach ( $repeaters as $prefix => $rid ) {
                if ( strpos( $e['path'], $prefix ) === 0 ) {
                    $owner = $rid;
                    break;
                }
            }
            $map[ $id ] = $owner;
            if ( ( $e['item']['type'] ?? '' ) === 'repeater' && $owner === $id ) {
                $repeaters[ $e['path'] . '/items/' ] = $id;
            }
        }
        return $map;
    }

    /**
     * The coach's results as a list: a heading per problem, what it is, how
     * to fix it, and a button that goes to the question's card.
     *
     * @param array $result analyze_model()'s result.
     * @param array $model  Model.
     * @return string HTML.
     */
    public static function render_coach_results( $result, $model ) {
        if ( empty( $result['issues'] ) ) {
            return '<p class="qaf-coach-clear">' . esc_html__( 'Nothing to fix: every question has what a screen reader needs.', 'quill-accessible-forms' ) . '</p>';
        }
        $cards    = self::card_ids( $model );
        $severity = array(
            'error'   => __( 'Error', 'quill-accessible-forms' ),
            'warning' => __( 'Warning', 'quill-accessible-forms' ),
            'info'    => __( 'Suggestion', 'quill-accessible-forms' ),
        );
        $out = '<ol class="qaf-coach-list" aria-labelledby="qaf-coach-heading">';
        foreach ( $result['issues'] as $n => $issue ) {
            $hid   = 'qaf-coach-issue-' . ( $n + 1 );
            $fid   = (string) $issue['field_id'];
            $about = $fid === 'form'
                ? __( 'the form', 'quill-accessible-forms' )
                : ( ( $issue['field_label'] ?? '' ) !== '' ? $issue['field_label'] : '#' . $fid );
            $out  .= '<li class="qaf-coach-issue qaf-coach-' . esc_attr( $issue['severity'] ) . '">';
            /* translators: 1: Error, Warning or Suggestion, 2: the problem, 3: the question it concerns. */
            $out  .= '<h4 id="' . esc_attr( $hid ) . '">' . esc_html( sprintf( __( '%1$s: %2$s, in %3$s', 'quill-accessible-forms' ), $severity[ $issue['severity'] ] ?? $issue['severity'], $issue['title'], $about ) ) . '</h4>';
            $out  .= '<p>' . esc_html( $issue['message'] ) . '</p>';
            if ( ! empty( $issue['suggestion'] ) ) {
                $out .= '<p class="qaf-coach-fix">' . esc_html( $issue['suggestion'] ) . '</p>';
            }
            if ( $fid !== 'form' && isset( $cards[ $fid ] ) ) {
                /* translators: %s: the question. */
                $out .= '<button type="button" class="qaf-btn qaf-btn-secondary qaf-coach-goto" data-card-id="' . esc_attr( $cards[ $fid ] ) . '" aria-describedby="' . esc_attr( $hid ) . '">' . esc_html( sprintf( __( 'Go to %s', 'quill-accessible-forms' ), $about ) ) . '</button>';
            }
            $out .= '</li>';
        }
        return $out . '</ol>';
    }

    /**
     * A rendered form, made safe to show in the admin as a preview: it
     * posts nowhere (the address is "#" and the form's token is gone, so
     * not even a form sent without the page script can be saved), and it
     * carries data-qaf-preview, which the page script reads to check the
     * answers and say that nothing was sent.
     *
     * @param string $html  The form as the renderer drew it.
     * @return string HTML.
     */
    public static function preview_markup( $html ) {
        $html = preg_replace_callback( '/<form\b[^>]*>/i', function ( $m ) {
            $tag = preg_replace( '/\saction="[^"]*"/i', '', $m[0] );
            return preg_replace( '/^<form\b/i', '<form action="#" data-qaf-preview="1"', $tag );
        }, (string) $html );
        // The form's token and the signed start time: without them nothing
        // this page could send would be accepted, should it be sent anyway.
        return preg_replace( '/<input[^>]*\sname="(_qaf_token|_qaf_form_sig|_wp_http_referer)"[^>]*>/i', '', $html );
    }

    /**
     * The Preview screen: the form as a visitor sees it, drawn by the same
     * renderer and running the same page script, inside the admin. Its
     * status does not matter (a draft previews too), and nothing it sends is
     * saved.
     *
     * @param int $form_id Form ID.
     */
    public function render_preview( $form_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'quill-accessible-forms' ) );
        }
        $form = new QAF_Form( absint( $form_id ) );
        echo '<div class="wrap qaf-wrap qaf-preview-wrap">';
        if ( ! $form->id ) {
            echo '<h1>' . esc_html__( 'Preview', 'quill-accessible-forms' ) . '</h1>';
            echo '<p>' . esc_html__( 'Save the form first; then Preview shows it as a visitor sees it.', 'quill-accessible-forms' ) . '</p></div>';
            return;
        }
        /* translators: %s: form title. */
        echo '<h1>' . esc_html( sprintf( __( 'Preview: %s', 'quill-accessible-forms' ), $form->title ) ) . '</h1>';
        echo '<p class="qaf-preview-notice" id="qaf-preview-notice">' . esc_html__( 'This is the form as a visitor sees it. Fill it in to try it: Submit checks your answers, and nothing is sent or saved.', 'quill-accessible-forms' ) . '</p>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=qaf-form-builder&form_id=' . absint( $form->id ) ) ) . '">' . esc_html__( 'Back to the builder', 'quill-accessible-forms' ) . '</a></p>';
        if ( class_exists( 'QAF_Public' ) ) {
            $public = new QAF_Public( 'quill-accessible-forms', QAF_VERSION );
            $public->enqueue_styles();
            $public->enqueue_scripts();
        }
        $renderer = new QAF_Form_Renderer();
        echo self::preview_markup( $renderer->render( $form, array( 'ajax' => true ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- the renderer escapes its own output.
        echo '</div>';
    }

    /**
     * AJAX: add a question (POST form_id, field_type, title) at the end of
     * the last page. A form not yet saved is made now, as a draft.
     */
    public function ajax_add_field() {
        $type = sanitize_key( $_POST['field_type'] ?? '' );
        $info = class_exists( 'QAF_Field_Registry' ) ? QAF_Field_Registry::get_instance()->get_field_type( $type ) : null;
        if ( ! $info || ! QAF_Form_Model::is_field_type( $type ) ) {
            check_ajax_referer( 'qaf_builder_nonce', 'nonce' );
            wp_send_json_error( array( 'message' => __( 'That type of question cannot be added here.', 'quill-accessible-forms' ) ) );
        }
        list( $form, $model, $created ) = $this->card_form( true );
        list( $model, $id )             = QAF_Builder_Field_Ops::add( $model, $type, (string) $info['label'] );
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, $created, array( 'field_id' => $id, 'label' => $info['label'] ) );
    }

    /** AJAX: copy a question (POST form_id, field_id) just after it. */
    public function ajax_duplicate_field() {
        list( $form, $model ) = $this->card_form();
        $done = QAF_Builder_Field_Ops::duplicate( $model, sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) ), __( ' (copy)', 'quill-accessible-forms' ) );
        $this->card_save( $form, $done ? $done[0] : null );
        $copy = QAF_Field_Scoring_Panel::find( $done[0], $done[1] );
        $this->card_reply( $form, $done[0], false, array( 'field_id' => $done[1], 'label' => $copy['label'] ?? '' ) );
    }

    /** AJAX: delete a question (POST form_id, field_id). */
    public function ajax_delete_field() {
        list( $form, $model ) = $this->card_form();
        $id    = sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) );
        $model = QAF_Builder_Field_Ops::remove( $model, $id );
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, false, array( 'field_id' => $id ) );
    }

    /**
     * AJAX: put the questions in a new order (POST form_id, field_order[]).
     * Returns the order the model now has, which differs when a question
     * was asked to move onto another page, section or column; the canvas
     * sent back is then the model's, to put the cards back.
     */
    public function ajax_update_field_order() {
        list( $form, $model ) = $this->card_form();
        $ids   = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['field_order'] ?? array() ) );
        $model = QAF_Builder_Field_Ops::reorder( $model, $ids );
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, false, array( 'order' => QAF_Builder_Field_Ops::order( $model, $ids ) ) );
    }

    /** AJAX: save a question's settings (POST form_id, field_id, label, help, placeholder, required). */
    public function ajax_save_field_settings() {
        list( $form, $model ) = $this->card_form();
        $id       = sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) );
        $settings = array();
        foreach ( QAF_Builder_Field_Ops::SETTINGS as $k ) {
            if ( isset( $_POST[ $k ] ) ) {
                $settings[ $k ] = $k === 'help' ? sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
            }
        }
        // An unchecked box sends nothing; the form says it sent the box.
        if ( ! empty( $_POST['has_required'] ) && ! isset( $settings['required'] ) ) {
            $settings['required'] = '';
        }
        $problems = QAF_Builder_Field_Ops::check_settings( $settings );
        if ( $problems ) {
            wp_send_json_error( array( 'message' => $problems[0]['message'], 'problems' => $problems ) );
        }
        $model = QAF_Builder_Field_Ops::apply_settings( $model, $id, $settings );
        $this->card_save( $form, $model );
        $field = QAF_Field_Scoring_Panel::find( $model, $id );
        $this->card_reply( $form, $model, false, array( 'field_id' => $id, 'label' => $field['label'] ?? '' ) );
    }

    /**
     * AJAX: change a question's type on the model (POST form_id, field_id,
     * target_type; dry_run=1 only says what would change).
     */
    public function ajax_convert_question() {
        list( $form, $model ) = $this->card_form();
        $id     = sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) );
        $type   = sanitize_key( $_POST['target_type'] ?? '' );
        $info   = class_exists( 'QAF_Field_Registry' ) ? QAF_Field_Registry::get_instance()->get_field_type( $type ) : null;
        if ( ! $info || ! QAF_Form_Model::is_field_type( $type ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose the type to change it to.', 'quill-accessible-forms' ) ) );
        }
        $result = QAF_Builder_Field_Ops::convert( $model, $id, $type );
        if ( $result === null ) {
            $this->card_save( $form, null );
        }
        $why = QAF_Builder_Field_Ops::refused( $result );
        if ( $why !== null ) {
            wp_send_json_error( array( 'message' => $why ) );
        }
        list( $model, $changes ) = $result;
        if ( ! empty( $_POST['dry_run'] ) ) {
            $problems = QAF_Form_Model::validate( $model );
            wp_send_json_success( array( 'changes' => $changes, 'problems' => $problems, 'type_label' => $info['label'] ) );
        }
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, false, array( 'field_id' => $id, 'changes' => $changes, 'type_label' => $info['label'] ) );
    }

    /**
     * AJAX: Save Draft and Publish (POST form_id, title, status draft|active).
     * The title goes into the model; the questions are already saved, each
     * as it was changed. A form not yet saved is made now.
     */
    public function ajax_save_form_status() {
        list( $form, $model, $created ) = $this->card_form( true );
        $status = sanitize_key( $_POST['status'] ?? 'draft' );
        if ( ! in_array( $status, array( 'draft', 'active' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Unknown status.', 'quill-accessible-forms' ) ) );
        }
        $model        = QAF_Builder_Field_Ops::set_title( $model, sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
        $form->status = $status;
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, $created, array( 'status' => $status, 'title' => $model['title'] ) );
    }

    /**
     * AJAX handler for getting field settings. With field_id (and form_id),
     * the settings panel's content for that question in the stored model;
     * with only field_type, the type's settings description, as before.
     */
    public function ajax_get_field_settings() {
        if ( isset( $_POST['field_id'] ) ) {
            list( $form, $model ) = $this->card_form();
            $field = QAF_Field_Scoring_Panel::find( $model, sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) );
            if ( ! $field ) {
                wp_send_json_error( array( 'message' => __( 'That question is no longer in the form. Reload the builder.', 'quill-accessible-forms' ) ) );
            }
            $info = class_exists( 'QAF_Field_Registry' ) ? QAF_Field_Registry::get_instance()->get_field_type( $field['type'] ?? '' ) : null;
            wp_send_json_success( array(
                'field_id' => $field['id'],
                'label'    => $field['label'] ?? '',
                'html'     => QAF_Builder_Field_Ops::render_settings( $field, $info ? (string) $info['label'] : '' ),
            ) );
        }

        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $field_type = sanitize_key( $_POST['field_type'] ?? '' );

        $registry = QAF_Field_Registry::get_instance();
        $field_info = $registry->get_field_type( $field_type );

        if ( ! $field_info ) {
            wp_send_json_error( array( 'message' => __( 'Unknown field type.', 'quill-accessible-forms' ) ) );
        }

        // Create field instance to get settings
        $field = $registry->create_field( array( 'type' => $field_type ), 0 );

        if ( ! $field ) {
            wp_send_json_error( array( 'message' => __( 'Could not create field instance.', 'quill-accessible-forms' ) ) );
        }

        wp_send_json_success( array(
            'type'     => $field_type,
            'label'    => $field_info['label'],
            'category' => $field_info['category'],
            'settings' => $field->get_field_settings(),
            'defaults' => $field->get_default_config(),
        ) );
    }

    /**
     * AJAX handler for parsing BFL
     */
    public function ajax_parse_bfl() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $bfl_source = wp_unslash( $_POST['bfl_source'] ?? '' );

        if ( empty( $bfl_source ) ) {
            wp_send_json_error( array( 'message' => __( 'No QAF form language text was given.', 'quill-accessible-forms' ) ) );
        }

        // The BFL 1 reader reads every older spelling as well; the builder's
        // fields are written from the model it gives.
        $result = QAF_BFL_Service::parse( $bfl_source );
        if ( ! $result['valid'] ) {
            wp_send_json_error( array(
                'message'  => __( 'The QAF form language text has errors.', 'quill-accessible-forms' ),
                'errors'   => $result['errors'],
                'warnings' => $result['warnings'],
            ) );
        }
        wp_send_json_success( $result );
    }

    /**
     * AJAX: rewrite BFL in its canonical form (the BFL panel's Format Code).
     */
    public function ajax_format_bfl() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $result = QAF_BFL_Service::format( wp_unslash( $_POST['bfl_source'] ?? '' ) );
        if ( $result['bfl'] === null ) {
            wp_send_json_error( array(
                'message'  => __( 'The QAF form language text has errors.', 'quill-accessible-forms' ),
                'errors'   => $result['errors'],
                'warnings' => $result['warnings'],
            ) );
        }
        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for compiling BFL
     */
    public function ajax_compile_bfl() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        $form_config = isset( $_POST['form_config'] ) ? json_decode( stripslashes( $_POST['form_config'] ), true ) : array();

        if ( empty( $form_config ) ) {
            wp_send_json_error( array( 'message' => __( 'No form configuration provided.', 'quill-accessible-forms' ) ) );
        }

        $result = QAF_BFL_Service::compile( $form_config );
        wp_send_json_success( array(
            'bfl_source' => $result['bfl'],
            'model'      => $result['model'],
            'report'     => $result['report'],
        ) );
    }

    /**
     * AJAX handler for importing CSV
     */
    public function ajax_import_csv() {
        check_ajax_referer( 'qaf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'quill-accessible-forms' ) ) );
        }

        if ( ! isset( $_FILES['csv_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'quill-accessible-forms' ) ) );
        }

        $file = $_FILES['csv_file'];

        // Validate file type
        $allowed_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a CSV file.', 'quill-accessible-forms' ) ) );
        }

        try {
            $importer = new QAF_CSV_Importer();
            $form_config = $importer->import( $file['tmp_name'] );
            wp_send_json_success( array( 'config' => $form_config ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    /**
     * Render field card for the builder canvas
     *
     * @param array  $field_config Field configuration.
     * @param int    $position     Position in form (1-indexed).
     * @param int    $total        Total number of fields.
     * @param int    $form_id      Form ID.
     * @return string Field card HTML.
     */
    public function render_field_card( $field_config, $position, $total, $form_id = 0 ) {
        $registry = QAF_Field_Registry::get_instance();
        $field_info = $registry->get_field_type( $field_config['type'] ?? '' );

        $field_id = $field_config['id'] ?? '';
        $field_label = $field_config['label'] ?? __( 'Untitled Field', 'quill-accessible-forms' );
        $field_type_label = $field_info ? $field_info['label'] : $field_config['type'];
        $is_required = ! empty( $field_config['required'] );

        // ARIA generator for move buttons
        $aria = new QAF_ARIA_Generator();
        $move_attrs = $aria->generate_for_move_buttons( $field_label, $position, $total );

        ob_start();
        ?>
        <div class="qaf-field-card" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-field-type="<?php echo esc_attr( $field_config['type'] ?? '' ); ?>" data-position="<?php echo esc_attr( $position ); ?>" role="listitem" aria-label="<?php echo esc_attr( $field_label ); ?>" draggable="true">
            <div class="qaf-field-card-header">
                <span class="qaf-field-drag-handle" aria-hidden="true" tabindex="-1">⋮⋮</span>
                <span class="qaf-field-icon" aria-hidden="true"><?php echo esc_html( $field_info['icon'] ?? '📝' ); ?></span>
                <span class="qaf-field-label">
                    <?php echo esc_html( $field_label ); ?>
                    <?php if ( $is_required ) : ?>
                        <span class="qaf-required-badge" aria-label="<?php esc_attr_e( 'Required', 'quill-accessible-forms' ); ?>">*</span>
                    <?php endif; ?>
                </span>
                <span class="qaf-field-type-badge"><?php echo esc_html( $field_type_label ); ?></span>
            </div>
            
            <div class="qaf-field-card-actions" role="toolbar" aria-label="<?php esc_attr_e( 'Field actions', 'quill-accessible-forms' ); ?>">
                <!-- Move Up Button -->
                <button type="button" 
                        class="qaf-action-btn qaf-move-up qaf-field-move-up" 
                        data-action="move-up"
                        aria-label="<?php echo esc_attr( $move_attrs['move_up']['aria-label'] ); ?>"
                        <?php echo $move_attrs['move_up']['disabled'] ? 'aria-disabled="true"' : ''; ?>>
                    <span aria-hidden="true">↑</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Move Up', 'quill-accessible-forms' ); ?></span>
                </button>
                
                <!-- Move Down Button -->
                <button type="button" 
                        class="qaf-action-btn qaf-move-down qaf-field-move-down" 
                        data-action="move-down"
                        aria-label="<?php echo esc_attr( $move_attrs['move_down']['aria-label'] ); ?>"
                        <?php echo $move_attrs['move_down']['disabled'] ? 'aria-disabled="true"' : ''; ?>>
                    <span aria-hidden="true">↓</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Move Down', 'quill-accessible-forms' ); ?></span>
                </button>

                <?php if ( class_exists( 'QAF_Field_Scoring_Panel' ) && QAF_Field_Scoring_Panel::is_scored_type( $field_config['type'] ?? '' ) ) : ?>
                <!-- Scoring Button: opens the question's Scoring section in the settings panel -->
                <button type="button"
                        class="qaf-action-btn qaf-scoring-btn"
                        data-action="scoring"
                        data-field-id="<?php echo esc_attr( $field_id ); ?>"
                        aria-controls="qaf-field-settings"
                        aria-expanded="false"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Scoring for %s', 'quill-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">#</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Scoring', 'quill-accessible-forms' ); ?></span>
                </button>
                <?php endif; ?>

                <!-- Edit Button -->
                <button type="button" 
                        class="qaf-action-btn qaf-edit qaf-field-edit" 
                        data-action="edit"
                        aria-controls="qaf-field-settings"
                        aria-expanded="false"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'quill-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">✏️</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Edit', 'quill-accessible-forms' ); ?></span>
                </button>
                
                <!-- Duplicate Button -->
                <button type="button" 
                        class="qaf-action-btn qaf-duplicate qaf-field-duplicate" 
                        data-action="duplicate"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Duplicate %s', 'quill-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">📋</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Duplicate', 'quill-accessible-forms' ); ?></span>
                </button>
                
                <!-- Convert Type Button -->
                <button type="button" 
                        class="qaf-action-btn qaf-field-action-convert-model qaf-field-convert" 
                        data-action="convert-type"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Convert %s to different type', 'quill-accessible-forms' ), $field_label ) ); ?>"
                        aria-haspopup="dialog">
                    <span aria-hidden="true">🔄</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Convert Type', 'quill-accessible-forms' ); ?></span>
                </button>
                
                <!-- Delete Button -->
                <button type="button" 
                        class="qaf-action-btn qaf-delete qaf-field-delete" 
                        data-action="delete"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'quill-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">🗑️</span>
                    <span class="qaf-sr-only"><?php esc_html_e( 'Delete', 'quill-accessible-forms' ); ?></span>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the field palette for the builder
     *
     * @return string Field palette HTML.
     */
    public function render_field_palette() {
        $registry = QAF_Field_Registry::get_instance();
        $categories = $registry->get_for_builder();

        ob_start();
        ?>
        <div class="qaf-field-palette" role="region" aria-label="<?php esc_attr_e( 'Available field types', 'quill-accessible-forms' ); ?>">
            <h3 class="qaf-palette-title" id="qaf-palette-heading"><?php esc_html_e( 'Add Fields', 'quill-accessible-forms' ); ?></h3>
            <p class="qaf-palette-instructions qaf-sr-only"><?php esc_html_e( 'Press Enter or Space to add field to form, or drag to desired position.', 'quill-accessible-forms' ); ?></p>
            
            <div class="qaf-palette-search">
                <label for="qaf-field-search" class="qaf-sr-only"><?php esc_html_e( 'Search fields', 'quill-accessible-forms' ); ?></label>
                <input type="search" 
                       id="qaf-field-search" 
                       class="qaf-field-search-input" 
                       placeholder="<?php esc_attr_e( 'Search fields...', 'quill-accessible-forms' ); ?>"
                       aria-controls="qaf-field-categories">
            </div>
            
            <div id="qaf-field-categories" class="qaf-field-categories" role="tablist" aria-label="<?php esc_attr_e( 'Field categories', 'quill-accessible-forms' ); ?>">
                <?php foreach ( $categories as $cat_id => $category ) : ?>
                <div class="qaf-field-category">
                    <button type="button" 
                            class="qaf-category-toggle" 
                            aria-expanded="true" 
                            aria-controls="qaf-category-<?php echo esc_attr( $cat_id ); ?>-fields"
                            id="qaf-category-<?php echo esc_attr( $cat_id ); ?>-btn">
                        <span class="qaf-category-icon" aria-hidden="true"><?php echo esc_html( $category['icon'] ); ?></span>
                        <span class="qaf-category-label"><?php echo esc_html( $category['label'] ); ?></span>
                        <span class="qaf-category-arrow" aria-hidden="true">▼</span>
                    </button>
                    
                    <ul class="qaf-category-fields" 
                        id="qaf-category-<?php echo esc_attr( $cat_id ); ?>-fields" 
                        role="listbox"
                        aria-labelledby="qaf-category-<?php echo esc_attr( $cat_id ); ?>-btn">
                        <?php foreach ( $category['fields'] as $field ) : ?>
                        <li class="qaf-palette-field" 
                            role="option"
                            tabindex="0"
                            data-field-type="<?php echo esc_attr( $field['type'] ); ?>"
                            draggable="true"
                            aria-label="<?php echo esc_attr( $field['label'] ); ?>">
                            <span class="qaf-palette-field-icon" aria-hidden="true"><?php echo esc_html( $field['icon'] ); ?></span>
                            <span class="qaf-palette-field-label"><?php echo esc_html( $field['label'] ); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
