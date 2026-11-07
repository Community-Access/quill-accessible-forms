<?php
/**
 * REST API Controller
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_REST_API class
 * 
 * Registers and handles REST API endpoints.
 */
class QAF_REST_API {

    /**
     * API namespace
     *
     * @var string
     */
    const NAMESPACE = 'qaf/v1';

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Forms endpoints
        register_rest_route( self::NAMESPACE, '/forms', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_forms' ),
                'permission_callback' => array( $this, 'get_forms_permissions_check' ),
                'args'                => $this->get_collection_params(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_form' ),
                'permission_callback' => array( $this, 'create_form_permissions_check' ),
                'args'                => $this->get_form_schema(),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/forms/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_form' ),
                'permission_callback' => array( $this, 'get_form_permissions_check' ),
                'args'                => array(
                    'id' => array(
                        'required' => true,
                        'type'     => 'integer',
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_form' ),
                'permission_callback' => array( $this, 'update_form_permissions_check' ),
                'args'                => $this->get_form_schema(),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_form' ),
                'permission_callback' => array( $this, 'delete_form_permissions_check' ),
            ),
        ) );

        // Entries endpoints
        register_rest_route( self::NAMESPACE, '/entries', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_entries' ),
                'permission_callback' => array( $this, 'get_entries_permissions_check' ),
                'args'                => $this->get_entries_collection_params(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_entry' ),
                'permission_callback' => array( $this, 'create_entry_permissions_check' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/entries/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_entry' ),
                'permission_callback' => array( $this, 'get_entry_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_entry' ),
                'permission_callback' => array( $this, 'update_entry_permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_entry' ),
                'permission_callback' => array( $this, 'delete_entry_permissions_check' ),
            ),
        ) );

        // Form submission endpoint (public)
        register_rest_route( self::NAMESPACE, '/submit/(?P<form_id>[\d]+)', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'submit_form' ),
            'permission_callback' => '__return_true',
        ) );

        // Views endpoints
        register_rest_route( self::NAMESPACE, '/views', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_views' ),
                'permission_callback' => array( $this, 'get_views_permissions_check' ),
            ),
        ) );

        // BFL endpoints
        register_rest_route( self::NAMESPACE, '/bfl/parse', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'parse_bfl' ),
            'permission_callback' => array( $this, 'get_forms_permissions_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/bfl/compile', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'compile_bfl' ),
            'permission_callback' => array( $this, 'get_forms_permissions_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/bfl/validate', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'validate_bfl' ),
            'permission_callback' => array( $this, 'get_forms_permissions_check' ),
        ) );

        register_rest_route( self::NAMESPACE, '/bfl/format', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'format_bfl' ),
            'permission_callback' => array( $this, 'get_forms_permissions_check' ),
        ) );
    }

    /**
     * Get forms
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_forms( $request ) {
        $args = array(
            'status'  => $request->get_param( 'status' ),
            'orderby' => $request->get_param( 'orderby' ) ?: 'created_at',
            'order'   => $request->get_param( 'order' ) ?: 'DESC',
            'limit'   => $request->get_param( 'per_page' ) ?: 10,
            'offset'  => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * ( $request->get_param( 'per_page' ) ?: 10 ),
            'search'  => $request->get_param( 'search' ),
        );

        $forms = QAF_Form::get_all( $args );
        $total = QAF_Form::count( $args['status'] );

        $data = array();
        foreach ( $forms as $form ) {
            $data[] = $this->prepare_form_response( $form );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', ceil( $total / $args['limit'] ) );

        return $response;
    }

    /**
     * Get single form
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_form( $request ) {
        $form = new QAF_Form( $request->get_param( 'id' ) );

        if ( ! $form->id ) {
            return new WP_Error( 'rest_form_not_found', __( 'Form not found.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $this->prepare_form_response( $form ) );
    }

    /**
     * Create form
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function create_form( $request ) {
        $form = new QAF_Form();

        $form->title       = $request->get_param( 'title' );
        $form->description = $request->get_param( 'description' );
        $form->config      = $request->get_param( 'config' ) ?: array();
        $form->bfl_source  = $request->get_param( 'bfl_source' );
        $form->status      = $request->get_param( 'status' ) ?: 'draft';
        $form->settings    = $request->get_param( 'settings' ) ?: array();

        $model_result = $this->apply_model( $form, $request );
        if ( is_wp_error( $model_result ) ) {
            return $model_result;
        }

        $result = $form->save();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $this->prepare_form_response( $form ) );
    }

    /**
     * Store a form model sent with a create or update (QUILL, BFL tools).
     *
     * A form saved with only `config` keeps its model but shows no fields,
     * because the renderer reads `config.fields`. With a `model`, set_model()
     * writes the fields the renderer reads. Other `config` keys the site
     * already had (or that came with the request) are kept.
     *
     * @param QAF_Form        $form    The form being saved.
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error True, or the model's validation error.
     */
    private function apply_model( $form, $request ) {
        if ( ! $request->has_param( 'model' ) ) {
            return true;
        }
        $kept   = is_array( $form->config ) ? $form->config : array();
        $result = $form->set_model( $request->get_param( 'model' ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $form->config = array_merge( $kept, $form->config );
        return true;
    }

    /**
     * Update form
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function update_form( $request ) {
        $form = new QAF_Form( $request->get_param( 'id' ) );

        if ( ! $form->id ) {
            return new WP_Error( 'rest_form_not_found', __( 'Form not found.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        if ( $request->has_param( 'title' ) ) {
            $form->title = $request->get_param( 'title' );
        }
        if ( $request->has_param( 'description' ) ) {
            $form->description = $request->get_param( 'description' );
        }
        if ( $request->has_param( 'config' ) ) {
            $form->config = $request->get_param( 'config' );
        }
        if ( $request->has_param( 'bfl_source' ) ) {
            $form->bfl_source = $request->get_param( 'bfl_source' );
        }
        if ( $request->has_param( 'status' ) ) {
            $form->status = $request->get_param( 'status' );
        }
        if ( $request->has_param( 'settings' ) ) {
            $form->settings = $request->get_param( 'settings' );
        }

        $model_result = $this->apply_model( $form, $request );
        if ( is_wp_error( $model_result ) ) {
            return $model_result;
        }

        $result = $form->save();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $this->prepare_form_response( $form ) );
    }

    /**
     * Delete form
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function delete_form( $request ) {
        $form = new QAF_Form( $request->get_param( 'id' ) );

        if ( ! $form->id ) {
            return new WP_Error( 'rest_form_not_found', __( 'Form not found.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        $result = $form->delete();

        if ( ! $result ) {
            return new WP_Error( 'rest_form_delete_failed', __( 'Failed to delete form.', 'quill-accessible-forms' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /**
     * Get entries
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_entries( $request ) {
        $form_id = $request->get_param( 'form_id' );

        if ( ! $form_id ) {
            return new WP_Error( 'rest_missing_form_id', __( 'Form ID is required.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
        }

        $args = array(
            'status'     => $request->get_param( 'status' ) ?: 'active',
            'orderby'    => $request->get_param( 'orderby' ) ?: 'created_at',
            'order'      => $request->get_param( 'order' ) ?: 'DESC',
            'limit'      => $request->get_param( 'per_page' ) ?: 20,
            'offset'     => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * ( $request->get_param( 'per_page' ) ?: 20 ),
            'is_starred' => $request->get_param( 'starred' ),
        );

        $entries = QAF_Entry::get_for_form( $form_id, $args );
        $total = QAF_Entry::count_for_form( $form_id, $args['status'] );

        $data = array();
        foreach ( $entries as $entry ) {
            $data[] = $this->prepare_entry_response( $entry );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', ceil( $total / $args['limit'] ) );

        return $response;
    }

    /**
     * Get single entry
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_entry( $request ) {
        $entry = new QAF_Entry( $request->get_param( 'id' ) );

        if ( ! $entry->id ) {
            return new WP_Error( 'rest_entry_not_found', __( 'Entry not found.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $this->prepare_entry_response( $entry ) );
    }

    /**
     * Create entry
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function create_entry( $request ) {
        $entry = new QAF_Entry();

        $entry->form_id    = $request->get_param( 'form_id' );
        $entry->user_id    = get_current_user_id();
        $entry->status     = 'active';
        $entry->ip_address = $this->get_client_ip();
        $entry->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $entry->source_url = wp_get_referer();

        $field_values = $request->get_param( 'fields' ) ?: array();
        $entry->set_field_values( $field_values );

        $result = $entry->save();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $this->prepare_entry_response( $entry ) );
    }

    /**
     * Update entry
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function update_entry( $request ) {
        $entry = new QAF_Entry( $request->get_param( 'id' ) );

        if ( ! $entry->id ) {
            return new WP_Error( 'rest_entry_not_found', __( 'Entry not found.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        if ( $request->has_param( 'status' ) ) {
            $entry->status = $request->get_param( 'status' );
        }
        if ( $request->has_param( 'is_starred' ) ) {
            $entry->is_starred = (bool) $request->get_param( 'is_starred' );
        }
        if ( $request->has_param( 'is_read' ) ) {
            $entry->is_read = (bool) $request->get_param( 'is_read' );
        }
        if ( $request->has_param( 'fields' ) ) {
            $entry->set_field_values( $request->get_param( 'fields' ) );
        }

        $result = $entry->save();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $this->prepare_entry_response( $entry ) );
    }

    /**
     * Delete entry
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function delete_entry( $request ) {
        $entry = new QAF_Entry( $request->get_param( 'id' ) );

        if ( ! $entry->id ) {
            return new WP_Error( 'rest_entry_not_found', __( 'Entry not found.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        $force = (bool) $request->get_param( 'force' );
        $result = $entry->delete( $force );

        if ( ! $result ) {
            return new WP_Error( 'rest_entry_delete_failed', __( 'Failed to delete entry.', 'quill-accessible-forms' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /**
     * Submit form (public endpoint)
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function submit_form( $request ) {
        $form_id = $request->get_param( 'form_id' );
        $form = new QAF_Form( $form_id );

        if ( ! $form->id || $form->status !== 'active' ) {
            return new WP_Error( 'rest_form_not_found', __( 'Form not found or inactive.', 'quill-accessible-forms' ), array( 'status' => 404 ) );
        }

        // Security checks
        $security = new QAF_Security();
        $security_check = $security->validate_submission( $form, $request );

        if ( is_wp_error( $security_check ) ) {
            return $security_check;
        }

        // Create entry
        $entry = new QAF_Entry();
        $entry->form_id    = $form_id;
        $entry->user_id    = get_current_user_id();
        $entry->status     = 'active';
        $entry->ip_address = $this->get_client_ip();
        $entry->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
        $entry->source_url = wp_get_referer();

        // Process and validate field values
        $field_values = $request->get_param( 'fields' ) ?: array();
        $sanitizer = new QAF_Sanitizer();
        $sanitized_values = array();

        foreach ( $form->get_fields() as $field ) {
            $field_id = $field['id'];
            $value = isset( $field_values[ $field_id ] ) ? $field_values[ $field_id ] : '';
            $sanitized_values[ $field_id ] = $sanitizer->sanitize( $value, $field['type'] );
        }

        // A paid form (payment contract version 1): check it again, work out
        // the amount here from the model, and refuse before anything is saved
        // if the site cannot take this payment. Nothing the page sends about
        // money is used: only the visitor's own answers.
        $model        = ( isset( $form->config['form_model'] ) && is_array( $form->config['form_model'] ) ) ? $form->config['form_model'] : null;
        $payment_plan = null;
        $submission   = '';
        if ( $model && class_exists( 'QAF_Payment_Config' ) && QAF_Payment_Config::applies( $model ) ) {
            $payments   = QAF_Payment_Service::instance();
            $submission = (string) $request->get_param( 'qaf_submission' );
            $existing   = $payments->existing( $form_id, $submission );
            if ( $existing ) {
                // The same page sent the same form again: the first entry and
                // its payment stand, and nothing is charged twice.
                return rest_ensure_response( array(
                    'success'  => true,
                    'message'  => __( 'Your answers were already saved.', 'quill-accessible-forms' ),
                    'entry_id' => (int) $existing['entry_id'],
                    'payment'  => $payments->for_page( $existing, $payments->reissue( $existing ), QAF_Payment_Config::get( $model ) ),
                ) );
            }
            $paid_item = QAF_Payment_Config::payment_field( $model );
            if ( $paid_item && isset( $field_values[ $paid_item['id'] ] ) && is_scalar( $field_values[ $paid_item['id'] ] ) ) {
                $sanitized_values[ $paid_item['id'] ] = sanitize_text_field( (string) $field_values[ $paid_item['id'] ] );
            }
            $plan = $payments->prepare( $model, $sanitized_values, array( 'today' => wp_date( 'Y-m-d' ) ) );
            if ( is_wp_error( $plan ) ) {
                return $plan;
            }
            // Store the site's own totals, never the page's.
            $sanitized_values = $plan['values'];
            if ( $plan['due'] ) {
                $payment_plan = $plan;
            }
        }

        $entry->set_field_values( $sanitized_values );
        $result = $entry->save();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        /**
         * Fires after a form is submitted
         *
         * @param QAF_Entry $entry The entry object.
         * @param QAF_Form  $form  The form object.
         */
        do_action( 'qaf_form_submitted', $entry, $form );

        $response = array(
            'success' => true,
            'message' => __( 'Form submitted successfully.', 'quill-accessible-forms' ),
            'entry_id' => $entry->id,
        );

        if ( $payment_plan ) {
            $opened              = QAF_Payment_Service::instance()->open( $form_id, $entry->id, $payment_plan, $submission );
            $response['payment'] = QAF_Payment_Service::instance()->for_page( $opened['attempt'], $opened['token'], $payment_plan['config'] );
            /* translators: %s: amount with currency, such as 25.00 USD. */
            $response['message'] = sprintf( __( 'Your answers are saved. To finish, pay %s below.', 'quill-accessible-forms' ), $response['payment']['spoken'] );
        }

        // A quiz: the outcome is chosen here, so its answers never reach the page.
        if ( $model ) {
            $lang    = QAF_Form_Runtime::language( $model, sanitize_key( (string) $request->get_param( 'qaf_lang' ) ) );
            $outcome = QAF_Form_Runtime::outcome( $model, $sanitized_values, $lang, array( 'today' => wp_date( 'Y-m-d' ) ) );
            if ( $outcome ) {
                $response['outcome'] = $outcome;
                if ( $outcome['message'] !== '' ) {
                    $response['message'] = $outcome['message'];
                }
            }
        }

        return rest_ensure_response( $response );
    }

    /**
     * Get views
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function get_views( $request ) {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_views';
        $views = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );

        return rest_ensure_response( $views );
    }

    /**
     * Parse BFL with the BFL 1 reader (which reads every older spelling too).
     *
     * The reply is the form model, the builder's shape written from it
     * (config, title, settings), and line-numbered errors and warnings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function parse_bfl( $request ) {
        $bfl = $request->get_param( 'bfl' );

        if ( empty( $bfl ) ) {
            return new WP_Error( 'rest_invalid_bfl', __( 'The QAF form language text is required.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
        }

        return rest_ensure_response( QAF_BFL_Service::parse( $bfl ) );
    }

    /**
     * Check BFL: the reader's problems, then the model's schema and rules.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function validate_bfl( $request ) {
        $bfl = $request->get_param( 'bfl' );

        if ( empty( $bfl ) ) {
            return new WP_Error( 'rest_invalid_bfl', __( 'The QAF form language text is required.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
        }

        return rest_ensure_response( QAF_BFL_Service::validate( $bfl ) );
    }

    /**
     * Rewrite BFL in its canonical form.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function format_bfl( $request ) {
        $bfl = $request->get_param( 'bfl' );

        if ( empty( $bfl ) ) {
            return new WP_Error( 'rest_invalid_bfl', __( 'The QAF form language text is required.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
        }

        return rest_ensure_response( QAF_BFL_Service::format( $bfl ) );
    }

    /**
     * Write BFL from a form model ("model") or the builder's shape ("config").
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function compile_bfl( $request ) {
        $input = $request->get_param( 'model' );
        if ( empty( $input ) ) {
            $input = $request->get_param( 'config' );
        }

        if ( empty( $input ) || ! is_array( $input ) ) {
            return new WP_Error( 'rest_invalid_config', __( 'A form model or form config is required.', 'quill-accessible-forms' ), array( 'status' => 400 ) );
        }

        return rest_ensure_response( QAF_BFL_Service::compile( $input ) );
    }

    /**
     * Prepare form response
     *
     * @param QAF_Form $form Form object.
     * @return array Response data.
     */
    private function prepare_form_response( $form ) {
        return array(
            'id'            => $form->id,
            'title'         => $form->title,
            'slug'          => $form->slug,
            'description'   => $form->description,
            'config'        => $form->config,
            'model'         => $form->get_model(),
            'bfl_source'    => $form->bfl_source,
            'status'        => $form->status,
            'settings'      => $form->settings,
            'entry_count'   => $form->get_entry_count(),
            'created_at'    => $form->created_at,
            'updated_at'    => $form->updated_at,
        );
    }

    /**
     * Prepare entry response
     *
     * @param QAF_Entry $entry Entry object.
     * @return array Response data.
     */
    private function prepare_entry_response( $entry ) {
        $data = $this->entry_fields( $entry );
        // Payment contract version 1: the entry's payment, when it has one.
        // No payment object means unknown, never paid.
        if ( class_exists( 'QAF_Payment_Service' ) ) {
            $payment = QAF_Payment_Service::instance()->for_entry( (int) $entry->id );
            if ( $payment ) {
                $data['payment'] = $payment;
            }
        }
        return $data;
    }

    /**
     * An entry's own fields, for a reply.
     *
     * @param QAF_Entry $entry Entry object.
     * @return array Fields.
     */
    private function entry_fields( $entry ) {
        return array(
            'id'          => $entry->id,
            'form_id'     => $entry->form_id,
            'user_id'     => $entry->user_id,
            'status'      => $entry->status,
            'is_starred'  => $entry->is_starred,
            'is_read'     => $entry->is_read,
            'fields'      => $entry->get_field_values(),
            'created_at'  => $entry->created_at,
            'updated_at'  => $entry->updated_at,
        );
    }

    /**
     * Get client IP address
     *
     * Note: Only trust proxy headers if explicitly configured. By default,
     * we use REMOTE_ADDR which cannot be spoofed.
     *
     * @return string IP address.
     */
    private function get_client_ip() {
        $trust_proxy_headers = apply_filters( 'qaf_trust_proxy_headers', false );
        
        if ( $trust_proxy_headers ) {
            // Only use proxy headers when behind a known/trusted proxy
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                // Take the first (original client) IP from the chain
                $ips = explode( ',', sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
                $ip = trim( $ips[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        // Default to REMOTE_ADDR (cannot be spoofed)
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        return '';
    }

    // Permission callbacks

    public function get_forms_permissions_check( $request ) {
        return current_user_can( 'qaf_manage_forms' ) || current_user_can( 'manage_options' );
    }

    public function get_form_permissions_check( $request ) {
        return current_user_can( 'qaf_manage_forms' ) || current_user_can( 'manage_options' );
    }

    public function create_form_permissions_check( $request ) {
        return current_user_can( 'qaf_create_forms' ) || current_user_can( 'manage_options' );
    }

    public function update_form_permissions_check( $request ) {
        return current_user_can( 'qaf_edit_forms' ) || current_user_can( 'manage_options' );
    }

    public function delete_form_permissions_check( $request ) {
        return current_user_can( 'qaf_delete_forms' ) || current_user_can( 'manage_options' );
    }

    public function get_entries_permissions_check( $request ) {
        return current_user_can( 'qaf_view_entries' ) || current_user_can( 'manage_options' );
    }

    public function get_entry_permissions_check( $request ) {
        return current_user_can( 'qaf_view_entries' ) || current_user_can( 'manage_options' );
    }

    public function create_entry_permissions_check( $request ) {
        return true; // Public can create entries
    }

    public function update_entry_permissions_check( $request ) {
        return current_user_can( 'qaf_edit_entries' ) || current_user_can( 'manage_options' );
    }

    public function delete_entry_permissions_check( $request ) {
        return current_user_can( 'qaf_delete_entries' ) || current_user_can( 'manage_options' );
    }

    public function get_views_permissions_check( $request ) {
        return current_user_can( 'qaf_manage_views' ) || current_user_can( 'manage_options' );
    }

    /**
     * Get collection parameters
     *
     * @return array Parameters.
     */
    private function get_collection_params() {
        return array(
            'page'     => array(
                'default'           => 1,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'default'           => 10,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'search'   => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'status'   => array(
                'type'              => 'string',
                'enum'              => array( 'draft', 'active', 'inactive' ),
            ),
            'orderby'  => array(
                'type'              => 'string',
                'default'           => 'created_at',
            ),
            'order'    => array(
                'type'              => 'string',
                'default'           => 'DESC',
                'enum'              => array( 'ASC', 'DESC' ),
            ),
        );
    }

    /**
     * Get entries collection parameters
     *
     * @return array Parameters.
     */
    private function get_entries_collection_params() {
        return array_merge( $this->get_collection_params(), array(
            'form_id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'starred' => array(
                'type'    => 'boolean',
            ),
        ) );
    }

    /**
     * Get form schema
     *
     * @return array Schema.
     */
    private function get_form_schema() {
        return array(
            'title' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'description' => array(
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ),
            'config' => array(
                'type' => 'object',
            ),
            'bfl_source' => array(
                'type' => 'string',
            ),
            'status' => array(
                'type' => 'string',
                'enum' => array( 'draft', 'active', 'inactive' ),
            ),
            'settings' => array(
                'type' => 'object',
            ),
        );
    }
}
