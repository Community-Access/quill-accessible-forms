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

    /**
     * AJAX handler for getting field settings
     */
    public function ajax_get_field_settings() {
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
        <div class="baf-field-card" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-position="<?php echo esc_attr( $position ); ?>" role="listitem" aria-label="<?php echo esc_attr( $field_label ); ?>">
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
                        class="baf-action-btn baf-move-up" 
                        data-action="move-up"
                        aria-label="<?php echo esc_attr( $move_attrs['move_up']['aria-label'] ); ?>"
                        <?php echo $move_attrs['move_up']['disabled'] ? 'disabled aria-disabled="true"' : ''; ?>>
                    <span aria-hidden="true">↑</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Move Up', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Move Down Button -->
                <button type="button" 
                        class="baf-action-btn baf-move-down" 
                        data-action="move-down"
                        aria-label="<?php echo esc_attr( $move_attrs['move_down']['aria-label'] ); ?>"
                        <?php echo $move_attrs['move_down']['disabled'] ? 'disabled aria-disabled="true"' : ''; ?>>
                    <span aria-hidden="true">↓</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Move Down', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Edit Button -->
                <button type="button" 
                        class="baf-action-btn baf-edit" 
                        data-action="edit"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'bits-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">✏️</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Edit', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Duplicate Button -->
                <button type="button" 
                        class="baf-action-btn baf-duplicate" 
                        data-action="duplicate"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Duplicate %s', 'bits-accessible-forms' ), $field_label ) ); ?>">
                    <span aria-hidden="true">📋</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Duplicate', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Convert Type Button -->
                <button type="button" 
                        class="baf-action-btn baf-field-action-convert" 
                        data-action="convert"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Convert %s to different type', 'bits-accessible-forms' ), $field_label ) ); ?>"
                        aria-haspopup="dialog">
                    <span aria-hidden="true">🔄</span>
                    <span class="baf-sr-only"><?php esc_html_e( 'Convert Type', 'bits-accessible-forms' ); ?></span>
                </button>
                
                <!-- Delete Button -->
                <button type="button" 
                        class="baf-action-btn baf-delete" 
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
