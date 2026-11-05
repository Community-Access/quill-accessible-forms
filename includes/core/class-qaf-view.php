<?php
/**
 * View model class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_View class
 * 
 * Represents an entry view configuration.
 */
class QAF_View {

    /**
     * View ID
     *
     * @var int
     */
    public $id = 0;

    /**
     * View title
     *
     * @var string
     */
    public $title = '';

    /**
     * View slug
     *
     * @var string
     */
    public $slug = '';

    /**
     * Form ID
     *
     * @var int
     */
    public $form_id = 0;

    /**
     * View type (table, cards, list, map, calendar)
     *
     * @var string
     */
    public $view_type = 'table';

    /**
     * View configuration
     *
     * @var array
     */
    public $config = array();

    /**
     * View status
     *
     * @var string
     */
    public $status = 'draft';

    /**
     * View permissions
     *
     * @var array
     */
    public $permissions = array();

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
     * @param int|object $view View ID or database row object.
     */
    public function __construct( $view = 0 ) {
        if ( $view instanceof self ) {
            $this->init( (object) get_object_vars( $view ) );
        } elseif ( is_object( $view ) ) {
            $this->init( $view );
        } elseif ( is_numeric( $view ) && $view > 0 ) {
            $this->read( $view );
        }
    }

    /**
     * Initialize view from object
     *
     * @param object $data Database row object.
     */
    protected function init( $data ) {
        foreach ( get_object_vars( $data ) as $key => $value ) {
            if ( property_exists( $this, $key ) ) {
                if ( in_array( $key, array( 'config', 'permissions' ), true ) ) {
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
        $this->config = wp_parse_args( $this->config, $this->get_default_config() );
    }

    /**
     * Read view from database
     *
     * @param int $id View ID.
     * @return bool True if found, false otherwise.
     */
    public function read( $id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_views';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( $row ) {
            $this->init( $row );
            return true;
        }

        return false;
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        $defaults = array(
            // Fields to display
            'fields'           => array(),
            'sort_field'       => 'created_at',
            'sort_order'       => 'DESC',
            
            // Pagination
            'items_per_page'   => 20,
            'show_pagination'  => true,
            
            // Filtering
            'enable_search'    => true,
            'enable_filters'   => true,
            'filter_fields'    => array(),
            
            // Accessibility
            'aria_label'       => '',
            'skip_link'        => true,
            
            // Layout
            'css_class'        => '',
        );

        // Type-specific defaults
        switch ( $this->view_type ) {
            case 'table':
                $defaults = array_merge( $defaults, array(
                    'responsive_mode' => 'stackable', // stackable, scroll, collapse
                    'sticky_header'   => false,
                    'sortable_columns' => true,
                ) );
                break;

            case 'cards':
                $defaults = array_merge( $defaults, array(
                    'columns'         => 3,
                    'card_image_field' => '',
                    'card_title_field' => '',
                    'masonry_layout'  => false,
                ) );
                break;

            case 'list':
                $defaults = array_merge( $defaults, array(
                    'list_title_field' => '',
                    'list_subtitle_field' => '',
                    'show_bullet'     => false,
                ) );
                break;

            case 'map':
                $defaults = array_merge( $defaults, array(
                    'address_field'   => '',
                    'lat_field'       => '',
                    'lng_field'       => '',
                    'map_provider'    => 'openstreetmap', // openstreetmap, google
                    'map_zoom'        => 12,
                    'map_center_lat'  => 0,
                    'map_center_lng'  => 0,
                    'marker_clustering' => true,
                ) );
                break;

            case 'calendar':
                $defaults = array_merge( $defaults, array(
                    'date_field'       => '',
                    'end_date_field'   => '',
                    'title_field'      => '',
                    'default_view'     => 'month', // month, week, day, list
                    'week_starts_on'   => 0,
                ) );
                break;
        }

        return $defaults;
    }

    /**
     * Save view to database
     *
     * @return int|WP_Error View ID on success, WP_Error on failure.
     */
    public function save() {
        global $wpdb;

        // Validate
        if ( empty( $this->title ) ) {
            return new WP_Error( 'empty_title', __( 'View title is required.', 'quill-accessible-forms' ) );
        }

        if ( empty( $this->form_id ) ) {
            return new WP_Error( 'no_form', __( 'A form must be selected.', 'quill-accessible-forms' ) );
        }

        if ( ! in_array( $this->view_type, array( 'table', 'cards', 'list', 'map', 'calendar' ), true ) ) {
            return new WP_Error( 'invalid_type', __( 'Invalid view type.', 'quill-accessible-forms' ) );
        }

        $table = $wpdb->prefix . 'qaf_views';
        $now   = current_time( 'mysql' );

        $data = array(
            'title'       => sanitize_text_field( $this->title ),
            'slug'        => sanitize_title( $this->slug ?: $this->title ),
            'form_id'     => $this->form_id,
            'view_type'   => $this->view_type,
            'config'      => wp_json_encode( $this->config ),
            'status'      => $this->status,
            'permissions' => wp_json_encode( $this->permissions ),
            'updated_at'  => $now,
        );

        $format = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

        if ( $this->id > 0 ) {
            // Update
            $result = $wpdb->update( $table, $data, array( 'id' => $this->id ), $format, array( '%d' ) );

            if ( false === $result ) {
                return new WP_Error( 'db_error', __( 'Failed to update view.', 'quill-accessible-forms' ) );
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
                return new WP_Error( 'db_error', __( 'Failed to create view.', 'quill-accessible-forms' ) );
            }

            $this->id         = $wpdb->insert_id;
            $this->created_at = $data['created_at'];
            $this->created_by = $data['created_by'];
        }

        $this->slug       = $data['slug'];
        $this->updated_at = $now;

        // Clear cache
        delete_transient( 'qaf_view_cache' );

        return $this->id;
    }

    /**
     * Delete view
     *
     * @return bool True on success, false on failure.
     */
    public function delete() {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        $table  = $wpdb->prefix . 'qaf_views';
        $result = $wpdb->delete( $table, array( 'id' => $this->id ), array( '%d' ) );

        if ( $result ) {
            delete_transient( 'qaf_view_cache' );
            return true;
        }

        return false;
    }

    /**
     * Render the view
     *
     * @param array $args Override arguments.
     * @return string Rendered HTML.
     */
    public function render( $args = array() ) {
        // Merge args with config
        $config = wp_parse_args( $args, $this->config );

        // Check permissions
        if ( ! $this->check_permissions() ) {
            return '<p class="qaf-view-access-denied">' . 
                   esc_html__( 'You do not have permission to view this content.', 'quill-accessible-forms' ) . 
                   '</p>';
        }

        // Get entries
        $entries = $this->get_entries( $config );

        // Load the appropriate view renderer
        $renderer_class = 'QAF_View_Renderer_' . ucfirst( $this->view_type );

        if ( class_exists( $renderer_class ) ) {
            $renderer = new $renderer_class( $this, $entries, $config );
            return $renderer->render();
        }

        return '<!-- View type not supported -->';
    }

    /**
     * Get entries for the view
     *
     * @param array $config View configuration.
     * @return array Array of QAF_Entry objects.
     */
    protected function get_entries( $config ) {
        $args = array(
            'status'  => 'active',
            'orderby' => $config['sort_field'] ?? 'created_at',
            'order'   => $config['sort_order'] ?? 'DESC',
            'limit'   => $config['items_per_page'] ?? 20,
        );

        // Handle pagination
        $paged = isset( $_GET['qaf_page'] ) ? max( 1, intval( $_GET['qaf_page'] ) ) : 1;
        $args['offset'] = ( $paged - 1 ) * $args['limit'];

        // Handle search
        if ( ! empty( $config['enable_search'] ) && ! empty( $_GET['qaf_search'] ) ) {
            $args['search'] = sanitize_text_field( wp_unslash( $_GET['qaf_search'] ) );
        }

        // Handle filters
        if ( ! empty( $config['enable_filters'] ) && ! empty( $config['filter_fields'] ) ) {
            foreach ( $config['filter_fields'] as $field_id ) {
                $param = 'qaf_filter_' . $field_id;
                if ( ! empty( $_GET[ $param ] ) ) {
                    $args['filters'][ $field_id ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
                }
            }
        }

        return QAF_Entry::get_for_form( $this->form_id, $args );
    }

    /**
     * Check if current user has permission to view
     *
     * @return bool True if has permission.
     */
    protected function check_permissions() {
        if ( empty( $this->permissions ) || empty( $this->permissions['enabled'] ) ) {
            return true;
        }

        // Check if logged in is required
        if ( ! empty( $this->permissions['require_login'] ) && ! is_user_logged_in() ) {
            return false;
        }

        // Check role requirements
        if ( ! empty( $this->permissions['roles'] ) ) {
            $user = wp_get_current_user();
            $has_role = array_intersect( $this->permissions['roles'], $user->roles );
            if ( empty( $has_role ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate unique slug
     *
     * @param string $slug Base slug.
     * @return string Unique slug.
     */
    private function generate_unique_slug( $slug ) {
        global $wpdb;

        $table         = $wpdb->prefix . 'qaf_views';
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
     * Get view by slug
     *
     * @param string $slug View slug.
     * @return QAF_View|null View object or null if not found.
     */
    public static function get_by_slug( $slug ) {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_views';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ) );

        if ( $row ) {
            return new self( $row );
        }

        return null;
    }

    /**
     * Get views for a form
     *
     * @param int   $form_id Form ID.
     * @param array $args    Query arguments.
     * @return array Array of QAF_View objects.
     */
    public static function get_for_form( $form_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'  => '',
            'orderby' => 'title',
            'order'   => 'ASC',
        );

        $args  = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'qaf_views';

        $where  = array( 'form_id = %d' );
        $params = array( $form_id );

        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'id', 'title', 'created_at', 'view_type' );
        if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
            $args['orderby'] = 'title';
        }

        $order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE $where_clause ORDER BY {$args['orderby']} $order",
            $params
        ) );

        $views = array();
        foreach ( $rows as $row ) {
            $views[] = new self( $row );
        }

        return $views;
    }

    /**
     * Get available view types
     *
     * @return array View types with labels.
     */
    public static function get_view_types() {
        return array(
            'table'    => __( 'Table View', 'quill-accessible-forms' ),
            'cards'    => __( 'Cards View', 'quill-accessible-forms' ),
            'list'     => __( 'List View', 'quill-accessible-forms' ),
            'map'      => __( 'Map View', 'quill-accessible-forms' ),
            'calendar' => __( 'Calendar View', 'quill-accessible-forms' ),
        );
    }
}
