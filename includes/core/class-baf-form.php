<?php
/**
 * Form model class
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Form class
 * 
 * Represents a form and provides CRUD operations.
 */
class BAF_Form {

    /**
     * Form ID
     *
     * @var int
     */
    public $id = 0;

    /**
     * Form title
     *
     * @var string
     */
    public $title = '';

    /**
     * Form slug
     *
     * @var string
     */
    public $slug = '';

    /**
     * Form description
     *
     * @var string
     */
    public $description = '';

    /**
     * Form configuration (fields, pages, etc.)
     *
     * @var array
     */
    public $config = array();

    /**
     * BFL source code
     *
     * @var string
     */
    public $bfl_source = '';

    /**
     * Form status (draft, active, inactive)
     *
     * @var string
     */
    public $status = 'draft';

    /**
     * Form settings
     *
     * @var array
     */
    public $settings = array();

    /**
     * Form confirmations
     *
     * @var array
     */
    public $confirmations = array();

    /**
     * Form notifications
     *
     * @var array
     */
    public $notifications = array();

    /**
     * Created timestamp
     *
     * @var string
     */
    public $created_at = '';

    /**
     * Updated timestamp
     *
     * @var string
     */
    public $updated_at = '';

    /**
     * Creator user ID
     *
     * @var int
     */
    public $created_by = 0;

    /**
     * Constructor
     *
     * @param int|object $form Form ID or database row object.
     */
    public function __construct( $form = 0 ) {
        if ( $form instanceof self ) {
            $this->init( (object) get_object_vars( $form ) );
        } elseif ( is_object( $form ) ) {
            $this->init( $form );
        } elseif ( is_numeric( $form ) && $form > 0 ) {
            $this->read( $form );
        }
    }

    /**
     * Initialize form from object
     *
     * @param object $data Database row object.
     */
    protected function init( $data ) {
        foreach ( get_object_vars( $data ) as $key => $value ) {
            if ( property_exists( $this, $key ) ) {
                if ( in_array( $key, array( 'config', 'settings', 'confirmations', 'notifications' ), true ) ) {
                    $this->$key = is_string( $value ) ? json_decode( $value, true ) : $value;
                    if ( ! is_array( $this->$key ) ) {
                        $this->$key = array();
                    }
                } else {
                    $this->$key = $value;
                }
            }
        }

        // Ensure defaults
        $this->settings = wp_parse_args( $this->settings, $this->get_default_settings() );
    }

    /**
     * Read form from database
     *
     * @param int $id Form ID.
     * @return bool True if found, false otherwise.
     */
    public function read( $id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_forms';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( $row ) {
            $this->init( $row );
            return true;
        }

        return false;
    }

    /**
     * Save form to database
     *
     * @return int|WP_Error Form ID on success, WP_Error on failure.
     */
    public function save() {
        global $wpdb;

        // Validate
        $validation = $this->validate();
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $table = $wpdb->prefix . 'baf_forms';
        $now   = current_time( 'mysql' );

        // Forms saved before the form model get it now, when it converts cleanly.
        // The builder's own fields stay as they are, so nothing that reads them changes.
        $this->upgrade_to_model();

        $data = array(
            'title'         => sanitize_text_field( $this->title ),
            'slug'          => sanitize_title( $this->slug ?: $this->title ),
            'description'   => wp_kses_post( $this->description ),
            'config'        => wp_json_encode( $this->config, JSON_PRESERVE_ZERO_FRACTION ),
            'bfl_source'    => $this->bfl_source,
            'status'        => $this->status,
            'settings'      => wp_json_encode( $this->settings ),
            'confirmations' => wp_json_encode( $this->confirmations ),
            'notifications' => wp_json_encode( $this->notifications ),
            'updated_at'    => $now,
        );

        $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

        if ( $this->id > 0 ) {
            // Update
            $result = $wpdb->update( $table, $data, array( 'id' => $this->id ), $format, array( '%d' ) );

            if ( false === $result ) {
                return new WP_Error( 'db_error', __( 'Failed to update form.', 'bits-accessible-forms' ) );
            }
        } else {
            // Insert
            $data['created_at'] = $now;
            $data['created_by'] = get_current_user_id();
            $format[]           = '%s';
            $format[]           = '%d';

            // Ensure unique slug
            $data['slug'] = $this->generate_unique_slug( $data['slug'] );

            $result = $wpdb->insert( $table, $data, $format );

            if ( false === $result ) {
                return new WP_Error( 'db_error', __( 'Failed to create form.', 'bits-accessible-forms' ) );
            }

            $this->id         = $wpdb->insert_id;
            $this->created_at = $data['created_at'];
            $this->created_by = $data['created_by'];
        }

        $this->slug       = $data['slug'];
        $this->updated_at = $now;

        // Clear cache
        wp_cache_delete( 'baf_form_' . $this->id, 'baf' );
        delete_transient( 'baf_form_cache' );

        /**
         * Fires after a form is saved
         *
         * @param BAF_Form $form The form object.
         */
        do_action( 'baf_form_saved', $this );

        return $this->id;
    }

    /**
     * Delete form
     *
     * @return bool True on success, false on failure.
     */
    public function delete() {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        // Delete related entries
        $entries_table    = $wpdb->prefix . 'baf_entries';
        $entry_meta_table = $wpdb->prefix . 'baf_entry_meta';
        $notes_table      = $wpdb->prefix . 'baf_entry_notes';
        $uploads_table    = $wpdb->prefix . 'baf_uploads';

        // Get entry IDs for this form
        $entry_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $entries_table WHERE form_id = %d", $this->id ) );

        if ( ! empty( $entry_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );

            // Delete entry meta
            $wpdb->query( $wpdb->prepare( "DELETE FROM $entry_meta_table WHERE entry_id IN ($placeholders)", $entry_ids ) );

            // Delete entry notes
            $wpdb->query( $wpdb->prepare( "DELETE FROM $notes_table WHERE entry_id IN ($placeholders)", $entry_ids ) );

            // Delete uploads
            $wpdb->query( $wpdb->prepare( "DELETE FROM $uploads_table WHERE entry_id IN ($placeholders)", $entry_ids ) );

            // Delete entries
            $wpdb->delete( $entries_table, array( 'form_id' => $this->id ), array( '%d' ) );
        }

        // Delete related views
        $views_table = $wpdb->prefix . 'baf_views';
        $wpdb->delete( $views_table, array( 'form_id' => $this->id ), array( '%d' ) );

        // Delete related drafts
        $drafts_table = $wpdb->prefix . 'baf_drafts';
        $wpdb->delete( $drafts_table, array( 'form_id' => $this->id ), array( '%d' ) );

        // Delete form
        $table  = $wpdb->prefix . 'baf_forms';
        $result = $wpdb->delete( $table, array( 'id' => $this->id ), array( '%d' ) );

        if ( $result ) {
            // Clear cache
            wp_cache_delete( 'baf_form_' . $this->id, 'baf' );
            delete_transient( 'baf_form_cache' );

            /**
             * Fires after a form is deleted
             *
             * @param int $form_id The deleted form ID.
             */
            do_action( 'baf_form_deleted', $this->id );

            return true;
        }

        return false;
    }

    /**
     * Validate form data
     *
     * @return true|WP_Error True if valid, WP_Error otherwise.
     */
    public function validate() {
        $errors = new WP_Error();

        if ( empty( $this->title ) ) {
            $errors->add( 'empty_title', __( 'Form title is required.', 'bits-accessible-forms' ) );
        }

        if ( ! in_array( $this->status, array( 'draft', 'active', 'inactive' ), true ) ) {
            $errors->add( 'invalid_status', __( 'Invalid form status.', 'bits-accessible-forms' ) );
        }

        // Validate config structure
        if ( ! is_array( $this->config ) ) {
            $errors->add( 'invalid_config', __( 'Form configuration must be an array.', 'bits-accessible-forms' ) );
        }

        // Check for field errors
        if ( ! empty( $this->config['fields'] ) && is_array( $this->config['fields'] ) ) {
            foreach ( $this->config['fields'] as $index => $field ) {
                if ( empty( $field['id'] ) ) {
                    $errors->add( 'field_no_id', sprintf( __( 'Field at index %d is missing an ID.', 'bits-accessible-forms' ), $index ) );
                }
                if ( empty( $field['type'] ) ) {
                    $errors->add( 'field_no_type', sprintf( __( 'Field at index %d is missing a type.', 'bits-accessible-forms' ), $index ) );
                }
            }
        }

        if ( $errors->has_errors() ) {
            return $errors;
        }

        return true;
    }

    /**
     * Duplicate form
     *
     * @return int|WP_Error New form ID on success, WP_Error on failure.
     */
    public function duplicate() {
        $new_form = new self();

        $new_form->title         = sprintf( __( '%s (Copy)', 'bits-accessible-forms' ), $this->title );
        $new_form->description   = $this->description;
        $new_form->config        = $this->config;
        $new_form->bfl_source    = $this->bfl_source;
        $new_form->status        = 'draft';
        $new_form->settings      = $this->settings;
        $new_form->confirmations = $this->confirmations;
        $new_form->notifications = $this->notifications;

        // Generate new field IDs. A form with a stored model keeps its IDs:
        // they belong to the form, and its conditions and calculations name them.
        if ( ! empty( $new_form->config['fields'] ) && ! isset( $new_form->config['form_model'] ) ) {
            foreach ( $new_form->config['fields'] as &$field ) {
                $field['id'] = $this->generate_field_id();
            }
        }

        return $new_form->save();
    }

    // ---------------------------------------------------------------------
    // The form model (docs/form-model.md)
    // ---------------------------------------------------------------------

    /**
     * The form as BAF stores it, for the model converter.
     *
     * @return array Stored form.
     */
    public function to_stored() {
        return array(
            'title'         => $this->title,
            'description'   => $this->description,
            'config'        => $this->config,
            'settings'      => $this->settings,
            'confirmations' => $this->confirmations,
            'notifications' => $this->notifications,
        );
    }

    /**
     * The form model: the stored model, or one read from the builder's
     * fields for a form saved before the model existed.
     *
     * @return array Model.
     */
    public function get_model() {
        return $this->get_model_and_report()['model'];
    }

    /**
     * The form model, with the report of anything that did not convert.
     *
     * @return array array( 'model' => ..., 'report' => BAF_Import_Report ).
     */
    public function get_model_and_report() {
        return BAF_Form_Model_Converter::from_legacy( $this->to_stored() );
    }

    /**
     * Replace the form with a model. The model is stored as it is, and the
     * builder's fields, settings, confirmations, notifications and BFL source
     * are rewritten from it, so every view stays in step. Call save() after.
     *
     * @param array $model Model.
     * @return true|WP_Error True, or the model's problems.
     */
    public function set_model( $model ) {
        $errors = BAF_Form_Model::validate( $model );
        if ( $errors ) {
            return new WP_Error( 'baf_invalid_model', __( 'The form model is not valid.', 'bits-accessible-forms' ), $errors );
        }
        $stored                       = BAF_Form_Model_Converter::to_legacy( $model );
        $this->title                  = $stored['title'];
        $this->description            = $stored['description'];
        $this->config                 = $stored['config'];
        $this->config['form_model']   = $model;
        $this->settings               = $stored['settings'];
        $this->confirmations          = $stored['confirmations'];
        $this->notifications          = $stored['notifications'];
        $this->bfl_source             = BAF_BFL_Writer::write( $model );
        return true;
    }

    /**
     * Take the visual builder's fields and settings, keeping what the builder
     * has no place for (rules, quiz outcomes, translations, choice libraries,
     * page conditions) from the model as it was. Call save() after.
     *
     * @param array      $config   The builder's config.
     * @param array|null $settings The builder's settings, if it sent them.
     * @return BAF_Import_Report What the builder's fields lost on the way in.
     */
    public function apply_builder_config( $config, $settings = null ) {
        $previous = $this->get_model();
        unset( $config['form_model'] );
        $this->config = is_array( $config ) ? $config : array();
        if ( is_array( $settings ) ) {
            $this->settings = $settings;
        }
        $result = BAF_Form_Model_Converter::from_legacy( $this->to_stored() );
        $model  = $result['model'];
        foreach ( array( 'id', 'language', 'choice_libraries', 'rules', 'scoring', 'translations', 'extensions' ) as $key ) {
            if ( ! isset( $model[ $key ] ) && isset( $previous[ $key ] ) ) {
                $model[ $key ] = $previous[ $key ];
            }
        }
        $old_pages = array();
        foreach ( $previous['pages'] ?? array() as $page ) {
            $old_pages[ $page['id'] ] = $page;
        }
        foreach ( $model['pages'] as &$page ) {
            foreach ( array( 'when', 'props' ) as $key ) {
                if ( ! isset( $page[ $key ] ) && isset( $old_pages[ $page['id'] ][ $key ] ) ) {
                    $page[ $key ] = $old_pages[ $page['id'] ][ $key ];
                }
            }
        }
        unset( $page );
        $kept = $this->set_model( $model );
        if ( is_wp_error( $kept ) ) {
            // Keep the builder's fields as they are; the model is read from them later.
            $this->config = is_array( $config ) ? $config : array();
            foreach ( $kept->get_error_data() as $e ) {
                $result['report']->error( $e['path'], $e['message'] );
            }
        }
        return $result['report'];
    }

    /**
     * Store the model beside the builder's fields of a form saved before the
     * model existed, when they convert to a valid model.
     *
     * @return bool Whether the form now has a stored model.
     */
    public function upgrade_to_model() {
        if ( isset( $this->config['form_model'] ) ) {
            return true;
        }
        if ( ! class_exists( 'BAF_Form_Model_Converter' ) ) {
            return false;
        }
        $model = BAF_Form_Model_Converter::from_legacy( $this->to_stored() )['model'];
        if ( BAF_Form_Model::validate( $model ) ) {
            return false;
        }
        $this->config['form_model'] = $model;
        return true;
    }

    /**
     * Get form fields
     *
     * @return array Array of field configurations.
     */
    public function get_fields() {
        return isset( $this->config['fields'] ) ? $this->config['fields'] : array();
    }

    /**
     * Get field by ID
     *
     * @param string $field_id Field ID.
     * @return array|null Field configuration or null if not found.
     */
    public function get_field( $field_id ) {
        foreach ( $this->get_fields() as $field ) {
            if ( isset( $field['id'] ) && $field['id'] === $field_id ) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Add field to form
     *
     * @param array  $field    Field configuration.
     * @param int    $position Position to insert at (default: end).
     * @param string $page     Page ID (for multi-page forms).
     * @return bool True on success.
     */
    public function add_field( $field, $position = -1, $page = '' ) {
        if ( ! isset( $this->config['fields'] ) ) {
            $this->config['fields'] = array();
        }

        // Generate field ID if not set
        if ( empty( $field['id'] ) ) {
            $field['id'] = $this->generate_field_id();
        }

        // Set page if specified
        if ( ! empty( $page ) ) {
            $field['page'] = $page;
        }

        if ( $position < 0 || $position >= count( $this->config['fields'] ) ) {
            $this->config['fields'][] = $field;
        } else {
            array_splice( $this->config['fields'], $position, 0, array( $field ) );
        }

        return true;
    }

    /**
     * Update field
     *
     * @param string $field_id Field ID.
     * @param array  $updates  Updated field configuration.
     * @return bool True on success, false if field not found.
     */
    public function update_field( $field_id, $updates ) {
        if ( ! isset( $this->config['fields'] ) ) {
            return false;
        }

        foreach ( $this->config['fields'] as &$field ) {
            if ( isset( $field['id'] ) && $field['id'] === $field_id ) {
                $field = array_merge( $field, $updates );
                return true;
            }
        }

        return false;
    }

    /**
     * Remove field
     *
     * @param string $field_id Field ID.
     * @return bool True on success, false if field not found.
     */
    public function remove_field( $field_id ) {
        if ( ! isset( $this->config['fields'] ) ) {
            return false;
        }

        foreach ( $this->config['fields'] as $index => $field ) {
            if ( isset( $field['id'] ) && $field['id'] === $field_id ) {
                array_splice( $this->config['fields'], $index, 1 );
                return true;
            }
        }

        return false;
    }

    /**
     * Get form pages
     *
     * @return array Array of page configurations.
     */
    public function get_pages() {
        return isset( $this->config['pages'] ) ? $this->config['pages'] : array();
    }

    /**
     * Check if form is multi-page
     *
     * @return bool True if multi-page.
     */
    public function is_multipage() {
        $pages = $this->get_pages();
        return count( $pages ) > 1;
    }

    /**
     * Get entry count
     *
     * @param string $status Optional status filter.
     * @return int Number of entries.
     */
    public function get_entry_count( $status = '' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_entries';

        if ( $status ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE form_id = %d AND status = %s",
                $this->id,
                $status
            ) );
        } else {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE form_id = %d",
                $this->id
            ) );
        }

        return (int) $count;
    }

    /**
     * Get default form settings
     *
     * @return array Default settings.
     */
    public function get_default_settings() {
        return array(
            // Form behavior
            'form_button_text'         => __( 'Submit', 'bits-accessible-forms' ),
            'form_button_type'         => 'text',
            'form_button_image'        => '',
            'ajax_submission'          => true,
            'scroll_to_error'          => true,
            'focus_first_error'        => true,
            
            // Accessibility
            'aria_live_announcements'  => true,
            'error_announcement_delay' => 150, // milliseconds
            'progress_announcements'   => true,
            'submit_announcements'     => true,
            'label_placement'          => 'above',
            'description_placement'    => 'below',
            'sub_label_placement'      => 'below',
            
            // Validation
            'validation_mode'          => 'on_blur', // on_blur, on_submit, real_time
            'show_inline_errors'       => true,
            'error_summary_position'   => 'top', // top, bottom, none
            'required_indicator'       => 'asterisk', // asterisk, text, both
            'required_text'            => __( '(Required)', 'bits-accessible-forms' ),
            
            // Security
            'enable_honeypot'          => true,
            'enable_captcha'           => false,
            'captcha_type'             => 'recaptcha_v3',
            'rate_limit_enabled'       => true,
            'rate_limit_count'         => 10,
            'rate_limit_period'        => 'minute',
            
            // Save & Continue
            'save_continue_enabled'    => false,
            'auto_save_enabled'        => false,
            'auto_save_interval'       => 30,
            
            // Layout
            'form_css_class'           => '',
            'field_spacing'            => 'normal', // compact, normal, relaxed
            'submit_button_location'   => 'bottom', // bottom, both
            
            // Multi-page
            'progress_indicator'       => 'steps', // steps, bar, percentage, none
            'navigation_style'         => 'buttons', // buttons, tabs
            'enable_page_validation'   => true,
            
            // Scheduling
            'schedule_enabled'         => false,
            'schedule_start'           => '',
            'schedule_end'             => '',
            'schedule_message'         => __( 'This form is not currently accepting submissions.', 'bits-accessible-forms' ),
            
            // Entry limits
            'entry_limit_enabled'      => false,
            'entry_limit_count'        => 100,
            'entry_limit_message'      => __( 'Sorry, this form has reached its submission limit.', 'bits-accessible-forms' ),
            
            // Conditional logic
            'conditional_logic'        => array(),
        );
    }

    /**
     * Generate unique slug
     *
     * @param string $slug Base slug.
     * @return string Unique slug.
     */
    private function generate_unique_slug( $slug ) {
        global $wpdb;

        $table         = $wpdb->prefix . 'baf_forms';
        $original_slug = $slug;
        $counter       = 1;

        while ( true ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s AND id != %d",
                $slug,
                $this->id
            ) );

            if ( ! $existing ) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate unique field ID
     *
     * @return string Unique field ID.
     */
    private function generate_field_id() {
        return 'field_' . wp_generate_password( 8, false );
    }

    /**
     * Export form to JSON
     *
     * @return string JSON string.
     */
    public function to_json() {
        return wp_json_encode( array(
            'title'         => $this->title,
            'description'   => $this->description,
            'config'        => $this->config,
            'bfl_source'    => $this->bfl_source,
            'settings'      => $this->settings,
            'confirmations' => $this->confirmations,
            'notifications' => $this->notifications,
            'version'       => BAF_VERSION,
            'exported_at'   => current_time( 'mysql' ),
        ), JSON_PRETTY_PRINT );
    }

    /**
     * Import form from JSON
     *
     * @param string $json JSON string.
     * @return int|WP_Error Form ID on success, WP_Error on failure.
     */
    public static function from_json( $json ) {
        $data = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', __( 'Invalid JSON format.', 'bits-accessible-forms' ) );
        }

        $form = new self();

        $form->title         = isset( $data['title'] ) ? $data['title'] : '';
        $form->description   = isset( $data['description'] ) ? $data['description'] : '';
        $form->config        = isset( $data['config'] ) ? $data['config'] : array();
        $form->bfl_source    = isset( $data['bfl_source'] ) ? $data['bfl_source'] : '';
        $form->settings      = isset( $data['settings'] ) ? $data['settings'] : array();
        $form->confirmations = isset( $data['confirmations'] ) ? $data['confirmations'] : array();
        $form->notifications = isset( $data['notifications'] ) ? $data['notifications'] : array();
        $form->status        = 'draft';

        // Regenerate field IDs to avoid conflicts (not for a stored model; see duplicate()).
        if ( ! empty( $form->config['fields'] ) && ! isset( $form->config['form_model'] ) ) {
            foreach ( $form->config['fields'] as &$field ) {
                $field['id'] = $form->generate_field_id();
            }
        }

        return $form->save();
    }

    /**
     * Get form by slug
     *
     * @param string $slug Form slug.
     * @return BAF_Form|null Form object or null if not found.
     */
    public static function get_by_slug( $slug ) {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_forms';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ) );

        if ( $row ) {
            return new self( $row );
        }

        return null;
    }

    /**
     * Get all forms
     *
     * @param array $args Query arguments.
     * @return array Array of BAF_Form objects.
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'limit'    => -1,
            'offset'   => 0,
            'search'   => '',
        );

        $args  = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'baf_forms';

        $where = array( '1=1' );
        $params = array();

        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if ( $args['search'] ) {
            $where[]  = '(title LIKE %s OR description LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'id', 'title', 'created_at', 'updated_at', 'status' );
        if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
            $args['orderby'] = 'created_at';
        }

        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY {$args['orderby']} $order";

        if ( $args['limit'] > 0 ) {
            $sql     .= ' LIMIT %d OFFSET %d';
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        if ( ! empty( $params ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        } else {
            $rows = $wpdb->get_results( $sql );
        }

        $forms = array();
        foreach ( $rows as $row ) {
            $forms[] = new self( $row );
        }

        return $forms;
    }

    /**
     * Count forms
     *
     * @param string $status Optional status filter.
     * @return int Number of forms.
     */
    public static function count( $status = '' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_forms';

        if ( $status ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s",
                $status
            ) );
        } else {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        }

        return (int) $count;
    }
}
