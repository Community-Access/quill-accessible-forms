<?php
/**
 * Form Builder Class
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Form_Builder class
 * 
 * Handles form builder functionality with full accessibility support.
 */
class BAF_Form_Builder {

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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $form_data = isset( $_POST['form_data'] ) ? json_decode( stripslashes( $_POST['form_data'] ), true ) : array();

        if ( empty( $form_data ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid form data.', 'bits-accessible-forms' ) ) );
        }

        $form = new BAF_Form( $form_id );
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
            'message' => __( 'Form saved successfully.', 'bits-accessible-forms' ),
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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-importers.php';
        $source = sanitize_key( $_POST['source'] ?? '' );
        if ( ! isset( BAF_Importers::SOURCES[ $source ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose what the form comes from.', 'bits-accessible-forms' ) ) );
        }
        try {
            if ( $source === 'google-forms' && ! empty( $_POST['google_form_id'] ) ) {
                $result = BAF_Importers::fetch_google_form( sanitize_text_field( wp_unslash( $_POST['google_form_id'] ) ), sanitize_text_field( wp_unslash( $_POST['access_token'] ?? '' ) ) );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
            } else {
                $result = BAF_Importers::import( $source, wp_unslash( $_POST['export'] ?? '' ) );
            }
        } catch ( InvalidArgumentException $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }

        $form = new BAF_Form();
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
            'edit_url'    => admin_url( 'admin.php?page=baf-form-builder&form_id=' . absint( $id ) ),
            'report'      => $result['report']->to_array(),
            'report_text' => $result['report']->to_text(),
        ) );
    }

    /**
     * AJAX: export a form to another builder's format, with its report.
     */
    public function ajax_export_form() {
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-exporters.php';
        $target = sanitize_key( $_POST['target'] ?? '' );
        if ( ! isset( BAF_Exporters::TARGETS[ $target ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose what to export the form to.', 'bits-accessible-forms' ) ) );
        }
        $form = new BAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'bits-accessible-forms' ) ) );
        }

        // BAF's built-in libraries (countries, months...) are written out as choices.
        BAF_Exporters::$library_resolver = array( __CLASS__, 'builtin_library_choices' );
        $result = BAF_Exporters::export( $target, $form->get_model() );
        wp_send_json_success( array(
            'text'        => $result['text'],
            'filename'    => $result['filename'],
            'report'      => $result['report']->to_array(),
            'report_text' => $result['report']->to_text(),
        ) );
    }

    /**
     * The choices of one of BAF's built-in libraries (countries, months...)
     * as the model writes choices, or an empty list when there is none.
     *
     * @param string $name Library name.
     * @return array Choices: array( value, label ).
     */
    public static function builtin_library_choices( $name ) {
        if ( ! class_exists( 'BAF_Choice_Libraries' ) || ! BAF_Choice_Libraries::exists( $name ) ) {
            return array();
        }
        $out = array();
        foreach ( (array) BAF_Choice_Libraries::get( $name ) as $key => $choice ) {
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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }
        wp_send_json_success( BAF_BFL_Service::read_choice_lines( wp_unslash( $_POST['lines'] ?? '' ) ) );
    }

    /**
     * AJAX: a question's Scoring section (POST form_id, field_id), drawn
     * from the question in the stored model, for the Visual mode's field
     * settings panel. admin/js/baf-field-scoring.js saves it.
     */
    public function ajax_get_field_scoring() {
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }
        $form = new BAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Save the form first; then its questions can be scored.', 'bits-accessible-forms' ) ) );
        }
        $model = $form->get_model();
        $field = BAF_Field_Scoring_Panel::find( $model, sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) ) );
        if ( ! $field ) {
            wp_send_json_error( array( 'message' => __( 'This question is not in the saved form yet. Save the form, then try again.', 'bits-accessible-forms' ) ) );
        }
        if ( ! BAF_Field_Scoring_Panel::is_scored_type( $field['type'] ?? '' ) ) {
            wp_send_json_error( array( 'message' => __( 'This type of question is not scored.', 'bits-accessible-forms' ) ) );
        }
        wp_send_json_success( array(
            'field_id' => $field['id'],
            'label'    => $field['label'] ?? '',
            'html'     => BAF_Field_Scoring_Panel::render( $field, $model, array( __CLASS__, 'builtin_library_choices' ) ),
        ) );
    }

    /**
     * AJAX: the choices of a built-in library (POST name), so the Choice
     * libraries screen can copy one into the form.
     */
    public function ajax_get_choice_library() {
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }
        $name    = sanitize_key( $_POST['name'] ?? '' );
        $choices = self::builtin_library_choices( $name );
        if ( ! $choices ) {
            wp_send_json_error( array( 'message' => __( 'There is no built-in library by that name.', 'bits-accessible-forms' ) ) );
        }
        wp_send_json_success( array( 'name' => $name, 'choices' => $choices ) );
    }

    /**
     * AJAX: the form model, its canonical BFL, and the conversion report.
     */
    public function ajax_get_form_model() {
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        $form = new BAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( ! $form->id ) {
            wp_send_json_error( array( 'message' => __( 'Form not found.', 'bits-accessible-forms' ) ) );
        }
        $result = $form->get_model_and_report();
        wp_send_json_success( array(
            'model'  => $result['model'],
            'bfl'    => BAF_BFL_Writer::write( $result['model'] ),
            'report' => $result['report']->to_array(),
        ) );
    }

    /**
     * AJAX: save a form from a model (JSON in "model") or from BFL ("bfl").
     */
    public function ajax_save_form_model() {
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        if ( isset( $_POST['bfl'] ) ) {
            $read = BAF_BFL_Reader::read( wp_unslash( $_POST['bfl'] ) );
            if ( $read['errors'] ) {
                wp_send_json_error( array( 'message' => __( 'The BFL has errors.', 'bits-accessible-forms' ), 'errors' => $read['errors'] ) );
            }
            $model = $read['model'];
        } else {
            $model = json_decode( wp_unslash( $_POST['model'] ?? '' ), true );
        }
        if ( ! is_array( $model ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid form data.', 'bits-accessible-forms' ) ) );
        }

        $form = new BAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        $set  = $form->set_model( $model );
        if ( is_wp_error( $set ) ) {
            wp_send_json_error( array( 'message' => $set->get_error_message(), 'errors' => $set->get_error_data() ) );
        }
        $result = $form->save();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array(
            'message' => __( 'Form saved successfully.', 'bits-accessible-forms' ),
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
     * @return array array( BAF_Form, model, created ).
     */
    private function card_form( $create = false ) {
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }
        $form = new BAF_Form( isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 );
        if ( $form->id ) {
            return array( $form, $form->get_model(), false );
        }
        if ( ! $create ) {
            wp_send_json_error( array( 'message' => __( 'This form has not been made yet. Add a question, or choose Save Draft, to make it.', 'bits-accessible-forms' ) ) );
        }
        $form->status = 'draft';
        return array( $form, BAF_Builder_Field_Ops::new_model( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) ), true );
    }

    /**
     * Save a changed model, or send why not (the message, and validate()'s
     * problems with their JSON pointers).
     *
     * @param BAF_Form   $form  Form.
     * @param array|null $model Model, or null when the question was not found.
     */
    private function card_save( $form, $model ) {
        if ( $model === null ) {
            wp_send_json_error( array( 'message' => __( 'That question is no longer in the form. Reload the builder.', 'bits-accessible-forms' ) ) );
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
     * @param BAF_Form $form    Form.
     * @param array    $model   Model.
     * @param bool     $created Whether this action made the form.
     * @param array    $extra   More to send.
     */
    private function card_reply( $form, $model, $created, $extra = array() ) {
        wp_send_json_success( array_merge( $extra, array(
            'form_id'  => (int) $form->id,
            'created'  => (bool) $created,
            'edit_url' => admin_url( 'admin.php?page=baf-form-builder&form_id=' . absint( $form->id ) ),
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
        $tree  = BAF_Builder_Field_Ops::canvas_tree( $model );
        $count = 0;
        foreach ( BAF_Form_Model::items( $model ) as $e ) {
            $count += ( isset( $e['item']['type'] ) && ! in_array( $e['item']['type'], array( 'section', 'columns' ), true ) ) ? 1 : 0;
        }
        if ( ! $count ) {
            return '<div class="baf-canvas-empty"><p>' . esc_html__( 'No questions yet. Add one from the palette, or with Add Field.', 'bits-accessible-forms' ) . '</p>'
                . '<p class="baf-canvas-hint">' . esc_html__( 'Tip: press F while in the canvas to add a question.', 'bits-accessible-forms' ) . '</p></div>';
        }
        $html  = '';
        $many  = count( $tree ) > 1;
        $group = 0;
        $list  = function ( $nodes, $label_id ) use ( &$list, &$group, $form_id ) {
            $out   = '<div class="baf-card-list" role="list" aria-labelledby="' . esc_attr( $label_id ) . '">';
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
                $gid  = 'baf-canvas-group-' . ( ++$group );
                $name = $n['kind'] === 'section'
                    ? sprintf( __( 'Section: %s', 'bits-accessible-forms' ), ( $item['label'] ?? '' ) !== '' ? $item['label'] : '#' . $item['id'] )
                    : sprintf( _n( 'Columns: %d column', 'Columns: %d columns', count( $n['columns'] ), 'bits-accessible-forms' ), count( $n['columns'] ) );
                $out .= '<div class="baf-group-card" role="listitem" data-group-id="' . esc_attr( $item['id'] ) . '">';
                $out .= '<p class="baf-group-label" id="' . esc_attr( $gid ) . '">' . esc_html( $name ) . '</p>';
                if ( $n['kind'] === 'section' ) {
                    $out .= $n['items'] ? $list( $n['items'], $gid ) : '<p class="baf-group-empty">' . esc_html__( 'No questions in this section yet. Move one in with Structure mode.', 'bits-accessible-forms' ) . '</p>';
                } else {
                    foreach ( $n['columns'] as $c => $col ) {
                        $cid  = $gid . '-' . ( $c + 1 );
                        $out .= '<p class="baf-group-label baf-column-label" id="' . esc_attr( $cid ) . '">' . esc_html( sprintf( __( 'Column %1$d of %2$d', 'bits-accessible-forms' ), $c + 1, count( $n['columns'] ) ) ) . '</p>';
                        $out .= $col ? $list( $col, $cid ) : '<p class="baf-group-empty">' . esc_html__( 'No questions in this column yet.', 'bits-accessible-forms' ) . '</p>';
                    }
                }
                $out .= '</div>';
            }
            return $out . '</div>';
        };
        foreach ( $tree as $p => $page ) {
            $label_id = 'baf-canvas-title';
            if ( $many ) {
                $label_id = 'baf-canvas-page-' . ( $p + 1 );
                $title    = $page['title'] !== '' ? sprintf( __( 'Page %1$d: %2$s', 'bits-accessible-forms' ), $p + 1, $page['title'] ) : sprintf( __( 'Page %d', 'bits-accessible-forms' ), $p + 1 );
                $html    .= '<h3 class="baf-canvas-page" id="' . esc_attr( $label_id ) . '">' . esc_html( $title ) . '</h3>';
            }
            $html .= $page['items'] ? $list( $page['items'], $label_id ) : '<p class="baf-group-empty">' . esc_html__( 'No questions on this page yet.', 'bits-accessible-forms' ) . '</p>';
        }
        return $html;
    }

    /**
     * AJAX: add a question (POST form_id, field_type, title) at the end of
     * the last page. A form not yet saved is made now, as a draft.
     */
    public function ajax_add_field() {
        $type = sanitize_key( $_POST['field_type'] ?? '' );
        $info = class_exists( 'BAF_Field_Registry' ) ? BAF_Field_Registry::get_instance()->get_field_type( $type ) : null;
        if ( ! $info || ! BAF_Form_Model::is_field_type( $type ) ) {
            check_ajax_referer( 'baf_builder_nonce', 'nonce' );
            wp_send_json_error( array( 'message' => __( 'That type of question cannot be added here.', 'bits-accessible-forms' ) ) );
        }
        list( $form, $model, $created ) = $this->card_form( true );
        list( $model, $id )             = BAF_Builder_Field_Ops::add( $model, $type, (string) $info['label'] );
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, $created, array( 'field_id' => $id, 'label' => $info['label'] ) );
    }

    /** AJAX: copy a question (POST form_id, field_id) just after it. */
    public function ajax_duplicate_field() {
        list( $form, $model ) = $this->card_form();
        $done = BAF_Builder_Field_Ops::duplicate( $model, sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) ), __( ' (copy)', 'bits-accessible-forms' ) );
        $this->card_save( $form, $done ? $done[0] : null );
        $copy = BAF_Field_Scoring_Panel::find( $done[0], $done[1] );
        $this->card_reply( $form, $done[0], false, array( 'field_id' => $done[1], 'label' => $copy['label'] ?? '' ) );
    }

    /** AJAX: delete a question (POST form_id, field_id). */
    public function ajax_delete_field() {
        list( $form, $model ) = $this->card_form();
        $id    = sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) );
        $model = BAF_Builder_Field_Ops::remove( $model, $id );
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
        $model = BAF_Builder_Field_Ops::reorder( $model, $ids );
        $this->card_save( $form, $model );
        $this->card_reply( $form, $model, false, array( 'order' => BAF_Builder_Field_Ops::order( $model, $ids ) ) );
    }

    /** AJAX: save a question's settings (POST form_id, field_id, label, help, placeholder, required). */
    public function ajax_save_field_settings() {
        list( $form, $model ) = $this->card_form();
        $id       = sanitize_text_field( wp_unslash( $_POST['field_id'] ?? '' ) );
        $settings = array();
        foreach ( BAF_Builder_Field_Ops::SETTINGS as $k ) {
            if ( isset( $_POST[ $k ] ) ) {
                $settings[ $k ] = $k === 'help' ? sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
            }
        }
        // An unchecked box sends nothing; the form says it sent the box.
        if ( ! empty( $_POST['has_required'] ) && ! isset( $settings['required'] ) ) {
            $settings['required'] = '';
        }
        $problems = BAF_Builder_Field_Ops::check_settings( $settings );
        if ( $problems ) {
            wp_send_json_error( array( 'message' => $problems[0]['message'], 'problems' => $problems ) );
        }
        $model = BAF_Builder_Field_Ops::apply_settings( $model, $id, $settings );
        $this->card_save( $form, $model );
        $field = BAF_Field_Scoring_Panel::find( $model, $id );
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
        $info   = class_exists( 'BAF_Field_Registry' ) ? BAF_Field_Registry::get_instance()->get_field_type( $type ) : null;
        if ( ! $info || ! BAF_Form_Model::is_field_type( $type ) ) {
            wp_send_json_error( array( 'message' => __( 'Choose the type to change it to.', 'bits-accessible-forms' ) ) );
        }
        $result = BAF_Builder_Field_Ops::convert( $model, $id, $type );
        if ( $result === null ) {
            $this->card_save( $form, null );
        }
        $why = BAF_Builder_Field_Ops::refused( $result );
        if ( $why !== null ) {
            wp_send_json_error( array( 'message' => $why ) );
        }
        list( $model, $changes ) = $result;
        if ( ! empty( $_POST['dry_run'] ) ) {
            $problems = BAF_Form_Model::validate( $model );
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
            wp_send_json_error( array( 'message' => __( 'Unknown status.', 'bits-accessible-forms' ) ) );
        }
        $model        = BAF_Builder_Field_Ops::set_title( $model, sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
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
            $field = BAF_Field_Scoring_Panel::find( $model, sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) );
            if ( ! $field ) {
                wp_send_json_error( array( 'message' => __( 'That question is no longer in the form. Reload the builder.', 'bits-accessible-forms' ) ) );
            }
            $info = class_exists( 'BAF_Field_Registry' ) ? BAF_Field_Registry::get_instance()->get_field_type( $field['type'] ?? '' ) : null;
            wp_send_json_success( array(
                'field_id' => $field['id'],
                'label'    => $field['label'] ?? '',
                'html'     => BAF_Builder_Field_Ops::render_settings( $field, $info ? (string) $info['label'] : '' ),
            ) );
        }

        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        $field_type = sanitize_key( $_POST['field_type'] ?? '' );

        $registry = BAF_Field_Registry::get_instance();
        $field_info = $registry->get_field_type( $field_type );

        if ( ! $field_info ) {
            wp_send_json_error( array( 'message' => __( 'Unknown field type.', 'bits-accessible-forms' ) ) );
        }

        // Create field instance to get settings
        $field = $registry->create_field( array( 'type' => $field_type ), 0 );

        if ( ! $field ) {
            wp_send_json_error( array( 'message' => __( 'Could not create field instance.', 'bits-accessible-forms' ) ) );
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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        $bfl_source = wp_unslash( $_POST['bfl_source'] ?? '' );

        if ( empty( $bfl_source ) ) {
            wp_send_json_error( array( 'message' => __( 'No BFL source provided.', 'bits-accessible-forms' ) ) );
        }

        // The BFL 1 reader reads every older spelling as well; the builder's
        // fields are written from the model it gives.
        $result = BAF_BFL_Service::parse( $bfl_source );
        if ( ! $result['valid'] ) {
            wp_send_json_error( array(
                'message'  => __( 'The BFL has errors.', 'bits-accessible-forms' ),
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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        $result = BAF_BFL_Service::format( wp_unslash( $_POST['bfl_source'] ?? '' ) );
        if ( $result['bfl'] === null ) {
            wp_send_json_error( array(
                'message'  => __( 'The BFL has errors.', 'bits-accessible-forms' ),
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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        $form_config = isset( $_POST['form_config'] ) ? json_decode( stripslashes( $_POST['form_config'] ), true ) : array();

        if ( empty( $form_config ) ) {
            wp_send_json_error( array( 'message' => __( 'No form configuration provided.', 'bits-accessible-forms' ) ) );
        }

        $result = BAF_BFL_Service::compile( $form_config );
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
        check_ajax_referer( 'baf_builder_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bits-accessible-forms' ) ) );
        }

        if ( ! isset( $_FILES['csv_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'bits-accessible-forms' ) ) );
        }

        $file = $_FILES['csv_file'];

        // Validate file type
        $allowed_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a CSV file.', 'bits-accessible-forms' ) ) );
        }

        try {
            $importer = new BAF_CSV_Importer();
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
        $registry = BAF_Field_Registry::get_instance();
        $field_info = $registry->get_field_type( $field_config['type'] ?? '' );

        $field_id = $field_config['id'] ?? '';
        $field_label = $field_config['label'] ?? __( 'Untitled Field', 'bits-accessible-forms' );
        $field_type_label = $field_info ? $field_info['label'] : $field_config['type'];
        $is_required = ! empty( $field_config['required'] );

        // ARIA generator for move buttons
        $aria = new BAF_ARIA_Generator();
        $move_attrs = $aria->generate_for_move_buttons( $field_label, $position, $total );

        ob_start();
        ?>
        <div class="baf-field-card" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-field-type="<?php echo esc_attr( $field_config['type'] ?? '' ); ?>" data-position="<?php echo esc_attr( $position ); ?>" role="listitem" aria-label="<?php echo esc_attr( $field_label ); ?>" draggable="true">
            <div class="baf-field-card-header">
                <span class="baf-field-drag-handle" aria-hidden="true" tabindex="-1">⋮⋮</span>
                <span class="baf-field-icon" aria-hidden="true"><?php echo esc_html( $field_info['icon'] ?? '📝' ); ?></span>
                <span class="baf-field-label">
                    <?php echo esc_html( $field_label ); ?>
                    <?php if ( $is_required ) : ?>
                        <span class="baf-required-badge" aria-label="<?php esc_attr_e( 'Required', 'bits-accessible-forms' ); ?>">*</span>
                    <?php endif; ?>
                </span>
                <span class="baf-field-type-badge"><?php echo esc_html( $field_type_label ); ?></span>
            </div>
            
            <div class="baf-field-card-actions" role="toolbar" aria-label="<?php esc_attr_e( 'Field actions', 'bits-accessible-forms' ); ?>">
                <!-- Move Up Button -->
                <button type="button" 
                        class="baf-action-btn baf-move-up baf-field-move-up" 
                        data-action="move-up"
                        aria-label="<?php echo esc_attr( $move_attrs['move_up']['aria-label'] ); ?>"
                        <?php echo $move_attrs['move_up']['disabled'] ? 'aria-disabled="true"' : ''; ?>>
                    <span aria-hidden="true">↑</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Move Up', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Move Down Button -->
                <button type="button" 
                        class="baf-action-btn baf-move-down baf-field-move-down" 
                        data-action="move-down"
                        aria-label="<?php echo esc_attr( $move_attrs['move_down']['aria-label'] ); ?>"
                        <?php echo $move_attrs['move_down']['disabled'] ? 'aria-disabled="true"' : ''; ?>>
                    <span aria-hidden="true">↓</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Move Down', 'bits-accessible-forms' ); ?></span>
                </button>

                <?php if ( class_exists( 'BAF_Field_Scoring_Panel' ) && BAF_Field_Scoring_Panel::is_scored_type( $field_config['type'] ?? '' ) ) : ?>
                <!-- Scoring Button: opens the question's Scoring section in the settings panel -->
                <button type="button"
                        class="baf-action-btn baf-scoring-btn"
                        data-action="scoring"
                        data-field-id="<?php echo esc_attr( $field_id ); ?>"
                        aria-controls="baf-field-settings"
                        aria-expanded="false"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Scoring for %s', 'bits-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">#</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Scoring', 'bits-accessible-forms' ); ?></span>
                </button>
                <?php endif; ?>

                <!-- Edit Button -->
                <button type="button" 
                        class="baf-action-btn baf-edit baf-field-edit" 
                        data-action="edit"
                        aria-controls="baf-field-settings"
                        aria-expanded="false"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'bits-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">✏️</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Edit', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Duplicate Button -->
                <button type="button" 
                        class="baf-action-btn baf-duplicate baf-field-duplicate" 
                        data-action="duplicate"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Duplicate %s', 'bits-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">📋</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Duplicate', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Convert Type Button -->
                <button type="button" 
                        class="baf-action-btn baf-field-action-convert-model baf-field-convert" 
                        data-action="convert-type"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Convert %s to different type', 'bits-accessible-forms' ), $field_label ) ); ?>"
                        aria-haspopup="dialog">
                    <span aria-hidden="true">🔄</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Convert Type', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Delete Button -->
                <button type="button" 
                        class="baf-action-btn baf-delete baf-field-delete" 
                        data-action="delete"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'bits-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">🗑️</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Delete', 'bits-accessible-forms' ); ?></span>
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
        $registry = BAF_Field_Registry::get_instance();
        $categories = $registry->get_for_builder();

        ob_start();
        ?>
        <div class="baf-field-palette" role="region" aria-label="<?php esc_attr_e( 'Available field types', 'bits-accessible-forms' ); ?>">
            <h3 class="baf-palette-title" id="baf-palette-heading"><?php esc_html_e( 'Add Fields', 'bits-accessible-forms' ); ?></h3>
            <p class="baf-palette-instructions baf-sr-only"><?php esc_html_e( 'Press Enter or Space to add field to form, or drag to desired position.', 'bits-accessible-forms' ); ?></p>
            
            <div class="baf-palette-search">
                <label for="baf-field-search" class="baf-sr-only"><?php esc_html_e( 'Search fields', 'bits-accessible-forms' ); ?></label>
                <input type="search" 
                       id="baf-field-search" 
                       class="baf-field-search-input" 
                       placeholder="<?php esc_attr_e( 'Search fields...', 'bits-accessible-forms' ); ?>"
                       aria-controls="baf-field-categories">
            </div>
            
            <div id="baf-field-categories" class="baf-field-categories" role="tablist" aria-label="<?php esc_attr_e( 'Field categories', 'bits-accessible-forms' ); ?>">
                <?php foreach ( $categories as $cat_id => $category ) : ?>
                <div class="baf-field-category">
                    <button type="button" 
                            class="baf-category-toggle" 
                            aria-expanded="true" 
                            aria-controls="baf-category-<?php echo esc_attr( $cat_id ); ?>-fields"
                            id="baf-category-<?php echo esc_attr( $cat_id ); ?>-btn">
                        <span class="baf-category-icon" aria-hidden="true"><?php echo esc_html( $category['icon'] ); ?></span>
                        <span class="baf-category-label"><?php echo esc_html( $category['label'] ); ?></span>
                        <span class="baf-category-arrow" aria-hidden="true">▼</span>
                    </button>
                    
                    <ul class="baf-category-fields" 
                        id="baf-category-<?php echo esc_attr( $cat_id ); ?>-fields" 
                        role="listbox"
                        aria-labelledby="baf-category-<?php echo esc_attr( $cat_id ); ?>-btn">
                        <?php foreach ( $category['fields'] as $field ) : ?>
                        <li class="baf-palette-field" 
                            role="option"
                            tabindex="0"
                            data-field-type="<?php echo esc_attr( $field['type'] ); ?>"
                            draggable="true"
                            aria-label="<?php echo esc_attr( $field['label'] ); ?>">
                            <span class="baf-palette-field-icon" aria-hidden="true"><?php echo esc_html( $field['icon'] ); ?></span>
                            <span class="baf-palette-field-label"><?php echo esc_html( $field['label'] ); ?></span>
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
