<?php
/**
 * Populate Anything Feature
 *
 * Dynamically populate fields from posts, users, taxonomies, entries, and REST APIs.
 * Inspired by GP Populate Anything.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Populate_Anything
 *
 * Provides dynamic field population from various data sources.
 */
class Populate_Anything {

    /**
     * Registered data sources.
     *
     * @var array
     */
    private array $sources = [];

    /**
     * Cache for populated data.
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->register_default_sources();
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        add_filter( 'baf_field_choices', [ $this, 'populate_field_choices' ], 10, 3 );
        add_filter( 'baf_field_default_value', [ $this, 'populate_field_value' ], 10, 3 );
        add_action( 'wp_ajax_baf_populate_field', [ $this, 'ajax_populate_field' ] );
        add_action( 'wp_ajax_nopriv_baf_populate_field', [ $this, 'ajax_populate_field' ] );
    }

    /**
     * Register default data sources.
     *
     * @return void
     */
    private function register_default_sources(): void {
        // Posts source
        $this->register_source( 'posts', [
            'label'    => __( 'Posts', 'jexi-forms' ),
            'callback' => [ $this, 'get_posts_data' ],
            'fields'   => [
                'ID'           => __( 'Post ID', 'jexi-forms' ),
                'post_title'   => __( 'Title', 'jexi-forms' ),
                'post_content' => __( 'Content', 'jexi-forms' ),
                'post_excerpt' => __( 'Excerpt', 'jexi-forms' ),
                'post_date'    => __( 'Date', 'jexi-forms' ),
                'post_author'  => __( 'Author ID', 'jexi-forms' ),
                'post_status'  => __( 'Status', 'jexi-forms' ),
                'permalink'    => __( 'Permalink', 'jexi-forms' ),
                'thumbnail'    => __( 'Featured Image URL', 'jexi-forms' ),
                'meta:*'       => __( 'Custom Field', 'jexi-forms' ),
            ],
        ] );

        // Users source
        $this->register_source( 'users', [
            'label'    => __( 'Users', 'jexi-forms' ),
            'callback' => [ $this, 'get_users_data' ],
            'fields'   => [
                'ID'           => __( 'User ID', 'jexi-forms' ),
                'user_login'   => __( 'Username', 'jexi-forms' ),
                'user_email'   => __( 'Email', 'jexi-forms' ),
                'display_name' => __( 'Display Name', 'jexi-forms' ),
                'first_name'   => __( 'First Name', 'jexi-forms' ),
                'last_name'    => __( 'Last Name', 'jexi-forms' ),
                'user_url'     => __( 'Website', 'jexi-forms' ),
                'roles'        => __( 'Roles', 'jexi-forms' ),
                'meta:*'       => __( 'User Meta', 'jexi-forms' ),
            ],
        ] );

        // Taxonomies source
        $this->register_source( 'taxonomies', [
            'label'    => __( 'Taxonomies', 'jexi-forms' ),
            'callback' => [ $this, 'get_taxonomies_data' ],
            'fields'   => [
                'term_id'     => __( 'Term ID', 'jexi-forms' ),
                'name'        => __( 'Name', 'jexi-forms' ),
                'slug'        => __( 'Slug', 'jexi-forms' ),
                'description' => __( 'Description', 'jexi-forms' ),
                'count'       => __( 'Post Count', 'jexi-forms' ),
                'parent'      => __( 'Parent ID', 'jexi-forms' ),
                'meta:*'      => __( 'Term Meta', 'jexi-forms' ),
            ],
        ] );

        // Form entries source
        $this->register_source( 'entries', [
            'label'    => __( 'Form Entries', 'jexi-forms' ),
            'callback' => [ $this, 'get_entries_data' ],
            'fields'   => [
                'id'           => __( 'Entry ID', 'jexi-forms' ),
                'form_id'      => __( 'Form ID', 'jexi-forms' ),
                'user_id'      => __( 'User ID', 'jexi-forms' ),
                'date_created' => __( 'Date Created', 'jexi-forms' ),
                'status'       => __( 'Status', 'jexi-forms' ),
                'field:*'      => __( 'Field Value', 'jexi-forms' ),
            ],
        ] );

        // REST API source
        $this->register_source( 'rest_api', [
            'label'    => __( 'REST API', 'jexi-forms' ),
            'callback' => [ $this, 'get_rest_api_data' ],
            'fields'   => [
                '*' => __( 'JSON Path', 'jexi-forms' ),
            ],
        ] );

        // Database query source
        $this->register_source( 'database', [
            'label'    => __( 'Database Query', 'jexi-forms' ),
            'callback' => [ $this, 'get_database_data' ],
            'fields'   => [
                '*' => __( 'Column Name', 'jexi-forms' ),
            ],
        ] );

        // Current user source
        $this->register_source( 'current_user', [
            'label'    => __( 'Current User', 'jexi-forms' ),
            'callback' => [ $this, 'get_current_user_data' ],
            'fields'   => [
                'ID'           => __( 'User ID', 'jexi-forms' ),
                'user_login'   => __( 'Username', 'jexi-forms' ),
                'user_email'   => __( 'Email', 'jexi-forms' ),
                'display_name' => __( 'Display Name', 'jexi-forms' ),
                'first_name'   => __( 'First Name', 'jexi-forms' ),
                'last_name'    => __( 'Last Name', 'jexi-forms' ),
                'meta:*'       => __( 'User Meta', 'jexi-forms' ),
            ],
        ] );

        // Current post source
        $this->register_source( 'current_post', [
            'label'    => __( 'Current Post', 'jexi-forms' ),
            'callback' => [ $this, 'get_current_post_data' ],
            'fields'   => [
                'ID'           => __( 'Post ID', 'jexi-forms' ),
                'post_title'   => __( 'Title', 'jexi-forms' ),
                'post_content' => __( 'Content', 'jexi-forms' ),
                'post_author'  => __( 'Author ID', 'jexi-forms' ),
                'meta:*'       => __( 'Custom Field', 'jexi-forms' ),
            ],
        ] );

        // URL parameters source
        $this->register_source( 'url_params', [
            'label'    => __( 'URL Parameters', 'jexi-forms' ),
            'callback' => [ $this, 'get_url_params_data' ],
            'fields'   => [
                '*' => __( 'Parameter Name', 'jexi-forms' ),
            ],
        ] );

        /**
         * Fires after default sources are registered.
         *
         * @param Populate_Anything $this Instance.
         */
        do_action( 'baf_populate_register_sources', $this );
    }

    /**
     * Register a data source.
     *
     * @param string $key    Source key.
     * @param array  $config Source configuration.
     * @return void
     */
    public function register_source( string $key, array $config ): void {
        $this->sources[ $key ] = wp_parse_args( $config, [
            'label'    => $key,
            'callback' => null,
            'fields'   => [],
        ] );
    }

    /**
     * Populate field choices (for select, checkboxes, radio).
     *
     * @param array  $choices  Original choices.
     * @param object $field    Field object.
     * @param Form   $form     Form object.
     * @return array Populated choices.
     */
    public function populate_field_choices( array $choices, $field, Form $form ): array {
        $populate = $field->get_property( 'populate' );
        
        if ( empty( $populate ) || ! is_array( $populate ) ) {
            return $choices;
        }

        $source      = $populate['source'] ?? '';
        $value_field = $populate['value'] ?? '';
        $label_field = $populate['label'] ?? $value_field;
        $filters     = $populate['filters'] ?? [];
        $order       = $populate['order'] ?? [];
        $limit       = $populate['limit'] ?? 0;

        if ( empty( $source ) || ! isset( $this->sources[ $source ] ) ) {
            return $choices;
        }

        // Get data from source
        $data = $this->get_source_data( $source, $filters, $order, $limit, $populate );

        if ( empty( $data ) ) {
            return $choices;
        }

        // Build choices
        $populated_choices = [];
        
        foreach ( $data as $item ) {
            $value = $this->extract_field_value( $item, $value_field );
            $label = $this->extract_field_value( $item, $label_field );

            if ( $value !== null ) {
                $populated_choices[] = [
                    'value' => $value,
                    'label' => $label ?: $value,
                ];
            }
        }

        // Allow filtering the populated choices
        $populated_choices = apply_filters( 'baf_populated_choices', $populated_choices, $field, $source, $populate );

        // Option to prepend or replace
        if ( ! empty( $populate['mode'] ) && $populate['mode'] === 'prepend' ) {
            return array_merge( $choices, $populated_choices );
        }

        return $populated_choices;
    }

    /**
     * Populate field default value.
     *
     * @param mixed  $value Default value.
     * @param object $field Field object.
     * @param Form   $form  Form object.
     * @return mixed Populated value.
     */
    public function populate_field_value( $value, $field, Form $form ) {
        $populate = $field->get_property( 'populate_value' );
        
        if ( empty( $populate ) || ! is_array( $populate ) ) {
            return $value;
        }

        $source      = $populate['source'] ?? '';
        $value_field = $populate['field'] ?? '';
        $filters     = $populate['filters'] ?? [];

        if ( empty( $source ) || ! isset( $this->sources[ $source ] ) ) {
            return $value;
        }

        // Get single value
        $data = $this->get_source_data( $source, $filters, [], 1, $populate );

        if ( empty( $data ) ) {
            return $value;
        }

        $item = reset( $data );
        return $this->extract_field_value( $item, $value_field ) ?? $value;
    }

    /**
     * Get data from a source.
     *
     * @param string $source   Source key.
     * @param array  $filters  Filter conditions.
     * @param array  $order    Order configuration.
     * @param int    $limit    Result limit.
     * @param array  $config   Full configuration.
     * @return array Data items.
     */
    public function get_source_data( string $source, array $filters = [], array $order = [], int $limit = 0, array $config = [] ): array {
        if ( ! isset( $this->sources[ $source ] ) ) {
            return [];
        }

        // Build cache key
        $cache_key = md5( $source . serialize( $filters ) . serialize( $order ) . $limit . serialize( $config ) );
        
        if ( isset( $this->cache[ $cache_key ] ) ) {
            return $this->cache[ $cache_key ];
        }

        // Call source callback
        $callback = $this->sources[ $source ]['callback'];
        
        if ( ! is_callable( $callback ) ) {
            return [];
        }

        $data = call_user_func( $callback, $filters, $order, $limit, $config );

        // Cache result
        $this->cache[ $cache_key ] = $data;

        return $data;
    }

    /**
     * Extract field value from data item.
     *
     * @param mixed  $item  Data item (array or object).
     * @param string $field Field name (supports dot notation).
     * @return mixed Field value.
     */
    private function extract_field_value( $item, string $field ) {
        if ( empty( $field ) ) {
            return null;
        }

        // Handle dot notation for nested data
        $parts = explode( '.', $field );
        $value = $item;

        foreach ( $parts as $part ) {
            if ( is_array( $value ) && isset( $value[ $part ] ) ) {
                $value = $value[ $part ];
            } elseif ( is_object( $value ) && isset( $value->$part ) ) {
                $value = $value->$part;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get posts data.
     *
     * @param array $filters Filter conditions.
     * @param array $order   Order config.
     * @param int   $limit   Limit.
     * @param array $config  Full config.
     * @return array Posts data.
     */
    public function get_posts_data( array $filters, array $order, int $limit, array $config ): array {
        $args = [
            'post_type'      => $config['post_type'] ?? 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit ?: -1,
            'orderby'        => $order['field'] ?? 'title',
            'order'          => $order['direction'] ?? 'ASC',
        ];

        // Apply filters
        foreach ( $filters as $filter ) {
            $field    = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? '=';
            $value    = $filter['value'] ?? '';

            // Handle live values (from other fields)
            $value = $this->process_filter_value( $value );

            switch ( $field ) {
                case 'post_author':
                    $args['author'] = $value;
                    break;
                case 'category':
                    $args['cat'] = $value;
                    break;
                case 'tag':
                    $args['tag_id'] = $value;
                    break;
                case 'taxonomy':
                    $taxonomy = $filter['taxonomy'] ?? 'category';
                    $args['tax_query'][] = [
                        'taxonomy' => $taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $value,
                        'operator' => $this->map_operator( $operator ),
                    ];
                    break;
                case 'meta':
                    $meta_key = $filter['meta_key'] ?? '';
                    $args['meta_query'][] = [
                        'key'     => $meta_key,
                        'value'   => $value,
                        'compare' => $this->map_operator( $operator ),
                    ];
                    break;
            }
        }

        $query = new \WP_Query( $args );
        $posts = [];

        foreach ( $query->posts as $post ) {
            $posts[] = [
                'ID'           => $post->ID,
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_date'    => $post->post_date,
                'post_author'  => $post->post_author,
                'post_status'  => $post->post_status,
                'permalink'    => get_permalink( $post->ID ),
                'thumbnail'    => get_the_post_thumbnail_url( $post->ID, 'full' ),
                'meta'         => get_post_meta( $post->ID ),
            ];
        }

        return $posts;
    }

    /**
     * Get users data.
     *
     * @param array $filters Filter conditions.
     * @param array $order   Order config.
     * @param int   $limit   Limit.
     * @param array $config  Full config.
     * @return array Users data.
     */
    public function get_users_data( array $filters, array $order, int $limit, array $config ): array {
        $args = [
            'number'  => $limit ?: -1,
            'orderby' => $order['field'] ?? 'display_name',
            'order'   => $order['direction'] ?? 'ASC',
        ];

        // Role filter
        if ( ! empty( $config['role'] ) ) {
            $args['role__in'] = (array) $config['role'];
        }

        // Apply filters
        foreach ( $filters as $filter ) {
            $field    = $filter['field'] ?? '';
            $value    = $this->process_filter_value( $filter['value'] ?? '' );

            switch ( $field ) {
                case 'role':
                    $args['role'] = $value;
                    break;
                case 'meta':
                    $meta_key = $filter['meta_key'] ?? '';
                    $args['meta_query'][] = [
                        'key'     => $meta_key,
                        'value'   => $value,
                        'compare' => $this->map_operator( $filter['operator'] ?? '=' ),
                    ];
                    break;
            }
        }

        $users = get_users( $args );
        $result = [];

        foreach ( $users as $user ) {
            $result[] = [
                'ID'           => $user->ID,
                'user_login'   => $user->user_login,
                'user_email'   => $user->user_email,
                'display_name' => $user->display_name,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'user_url'     => $user->user_url,
                'roles'        => implode( ', ', $user->roles ),
                'meta'         => get_user_meta( $user->ID ),
            ];
        }

        return $result;
    }

    /**
     * Get taxonomies/terms data.
     *
     * @param array $filters Filter conditions.
     * @param array $order   Order config.
     * @param int   $limit   Limit.
     * @param array $config  Full config.
     * @return array Terms data.
     */
    public function get_taxonomies_data( array $filters, array $order, int $limit, array $config ): array {
        $taxonomy = $config['taxonomy'] ?? 'category';
        
        $args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => $config['hide_empty'] ?? false,
            'number'     => $limit ?: 0,
            'orderby'    => $order['field'] ?? 'name',
            'order'      => $order['direction'] ?? 'ASC',
        ];

        // Parent filter
        if ( isset( $config['parent'] ) ) {
            $args['parent'] = $config['parent'];
        }

        // Apply filters
        foreach ( $filters as $filter ) {
            $field = $filter['field'] ?? '';
            $value = $this->process_filter_value( $filter['value'] ?? '' );

            switch ( $field ) {
                case 'parent':
                    $args['parent'] = $value;
                    break;
                case 'meta':
                    $meta_key = $filter['meta_key'] ?? '';
                    $args['meta_query'][] = [
                        'key'     => $meta_key,
                        'value'   => $value,
                        'compare' => $this->map_operator( $filter['operator'] ?? '=' ),
                    ];
                    break;
            }
        }

        $terms = get_terms( $args );
        
        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $terms as $term ) {
            $result[] = [
                'term_id'     => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'count'       => $term->count,
                'parent'      => $term->parent,
                'meta'        => get_term_meta( $term->term_id ),
            ];
        }

        return $result;
    }

    /**
     * Get form entries data.
     *
     * @param array $filters Filter conditions.
     * @param array $order   Order config.
     * @param int   $limit   Limit.
     * @param array $config  Full config.
     * @return array Entries data.
     */
    public function get_entries_data( array $filters, array $order, int $limit, array $config ): array {
        $form_id = $config['form_id'] ?? 0;
        
        if ( ! $form_id ) {
            return [];
        }

        $args = [
            'form_id' => $form_id,
            'status'  => $config['status'] ?? 'active',
            'limit'   => $limit ?: 100,
            'orderby' => $order['field'] ?? 'id',
            'order'   => $order['direction'] ?? 'DESC',
        ];

        // Apply filters
        foreach ( $filters as $filter ) {
            $field = $filter['field'] ?? '';
            $value = $this->process_filter_value( $filter['value'] ?? '' );

            if ( strpos( $field, 'field:' ) === 0 ) {
                $field_id = substr( $field, 6 );
                $args['field_filters'][] = [
                    'key'      => $field_id,
                    'value'    => $value,
                    'operator' => $filter['operator'] ?? '=',
                ];
            } else {
                $args[ $field ] = $value;
            }
        }

        // Get entries using Entry class
        $entries = Entry::query( $args );
        $result  = [];

        foreach ( $entries as $entry ) {
            $entry_data = [
                'id'           => $entry->get_id(),
                'form_id'      => $entry->get_form_id(),
                'user_id'      => $entry->get_user_id(),
                'date_created' => $entry->get_date_created(),
                'status'       => $entry->get_status(),
            ];

            // Add field values
            foreach ( $entry->get_data() as $field_id => $value ) {
                $entry_data[ 'field:' . $field_id ] = $value;
            }

            $result[] = $entry_data;
        }

        return $result;
    }

    /**
     * Get REST API data.
     *
     * @param array $filters Filter conditions (not typically used).
     * @param array $order   Order config (not typically used).
     * @param int   $limit   Limit.
     * @param array $config  Full config.
     * @return array API data.
     */
    public function get_rest_api_data( array $filters, array $order, int $limit, array $config ): array {
        $url     = $config['url'] ?? '';
        $method  = $config['method'] ?? 'GET';
        $headers = $config['headers'] ?? [];
        $body    = $config['body'] ?? null;

        if ( empty( $url ) ) {
            return [];
        }

        // Replace merge tags in URL
        $url = Merge_Tags::instance()->process( $url );

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 15,
        ];

        if ( $body ) {
            $args['body'] = is_array( $body ) ? wp_json_encode( $body ) : $body;
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'BAF Populate API Error: ' . $response->get_error_message() );
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return [];
        }

        // Handle nested data path
        if ( ! empty( $config['data_path'] ) ) {
            $data = $this->extract_field_value( $data, $config['data_path'] );
            
            if ( ! is_array( $data ) ) {
                return [];
            }
        }

        // Ensure it's a list
        if ( ! isset( $data[0] ) ) {
            $data = [ $data ];
        }

        // Apply limit
        if ( $limit > 0 ) {
            $data = array_slice( $data, 0, $limit );
        }

        return $data;
    }

    /**
     * Get database query data.
     *
     * @param array $filters Filter conditions.
     * @param array $order   Order config.
     * @param int   $limit   Limit.
     * @param array $config  Full config.
     * @return array Query results.
     */
    public function get_database_data( array $filters, array $order, int $limit, array $config ): array {
        global $wpdb;

        $table  = $config['table'] ?? '';
        $select = $config['select'] ?? '*';

        if ( empty( $table ) ) {
            return [];
        }

        // Sanitize table name
        $table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );
        
        // Add prefix if needed
        if ( ! empty( $config['use_prefix'] ) ) {
            $table = $wpdb->prefix . $table;
        }

        // Build query
        $sql = "SELECT {$select} FROM {$table}";

        // WHERE clause from filters
        $where = [];
        $values = [];

        foreach ( $filters as $filter ) {
            $field    = sanitize_key( $filter['field'] ?? '' );
            $operator = $this->map_operator( $filter['operator'] ?? '=' );
            $value    = $this->process_filter_value( $filter['value'] ?? '' );

            if ( $field ) {
                $where[] = "{$field} {$operator} %s";
                $values[] = $value;
            }
        }

        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        // ORDER BY
        if ( ! empty( $order['field'] ) ) {
            $order_field = sanitize_key( $order['field'] );
            $direction   = strtoupper( $order['direction'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY {$order_field} {$direction}";
        }

        // LIMIT
        if ( $limit > 0 ) {
            $sql .= " LIMIT {$limit}";
        }

        // Execute query
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return is_array( $results ) ? $results : [];
    }

    /**
     * Get current user data.
     *
     * @param array $filters Not used.
     * @param array $order   Not used.
     * @param int   $limit   Not used.
     * @param array $config  Full config.
     * @return array User data.
     */
    public function get_current_user_data( array $filters, array $order, int $limit, array $config ): array {
        if ( ! is_user_logged_in() ) {
            return [];
        }

        $user = wp_get_current_user();

        return [
            [
                'ID'           => $user->ID,
                'user_login'   => $user->user_login,
                'user_email'   => $user->user_email,
                'display_name' => $user->display_name,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'user_url'     => $user->user_url,
                'roles'        => implode( ', ', $user->roles ),
                'meta'         => get_user_meta( $user->ID ),
            ],
        ];
    }

    /**
     * Get current post data.
     *
     * @param array $filters Not used.
     * @param array $order   Not used.
     * @param int   $limit   Not used.
     * @param array $config  Full config.
     * @return array Post data.
     */
    public function get_current_post_data( array $filters, array $order, int $limit, array $config ): array {
        $post_id = get_the_ID();
        
        if ( ! $post_id ) {
            return [];
        }

        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return [];
        }

        return [
            [
                'ID'           => $post->ID,
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_author'  => $post->post_author,
                'post_date'    => $post->post_date,
                'permalink'    => get_permalink( $post->ID ),
                'thumbnail'    => get_the_post_thumbnail_url( $post->ID, 'full' ),
                'meta'         => get_post_meta( $post->ID ),
            ],
        ];
    }

    /**
     * Get URL parameters data.
     *
     * @param array $filters Not used.
     * @param array $order   Not used.
     * @param int   $limit   Not used.
     * @param array $config  Full config.
     * @return array Parameters.
     */
    public function get_url_params_data( array $filters, array $order, int $limit, array $config ): array {
        $params = [];
        
        foreach ( $_GET as $key => $value ) {
            $params[ sanitize_key( $key ) ] = sanitize_text_field( $value );
        }

        return [ $params ];
    }

    /**
     * Process filter value (handle live field references).
     *
     * @param mixed $value Filter value.
     * @return mixed Processed value.
     */
    private function process_filter_value( $value ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        // Check for field reference: {field_name}
        if ( preg_match( '/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $value, $matches ) ) {
            // This would be handled by JavaScript for live filtering
            // For server-side, check POST data
            if ( isset( $_POST[ $matches[1] ] ) ) {
                return sanitize_text_field( $_POST[ $matches[1] ] );
            }
        }

        // Process merge tags
        return Merge_Tags::instance()->process( $value );
    }

    /**
     * Map filter operator to SQL/WordPress operator.
     *
     * @param string $operator Filter operator.
     * @return string Mapped operator.
     */
    private function map_operator( string $operator ): string {
        $map = [
            '='         => '=',
            '!='        => '!=',
            '<>'        => '!=',
            '>'         => '>',
            '<'         => '<',
            '>='        => '>=',
            '<='        => '<=',
            'contains'  => 'LIKE',
            'starts'    => 'LIKE',
            'ends'      => 'LIKE',
            'in'        => 'IN',
            'not_in'    => 'NOT IN',
            'exists'    => 'EXISTS',
            'not_exists' => 'NOT EXISTS',
        ];

        return $map[ $operator ] ?? '=';
    }

    /**
     * Handle AJAX populate field request.
     *
     * @return void
     */
    public function ajax_populate_field(): void {
        check_ajax_referer( 'baf_populate', 'nonce' );

        $source      = sanitize_text_field( $_POST['source'] ?? '' );
        $filters     = $_POST['filters'] ?? [];
        $order       = $_POST['order'] ?? [];
        $limit       = absint( $_POST['limit'] ?? 0 );
        $config      = $_POST['config'] ?? [];
        $value_field = sanitize_text_field( $_POST['value_field'] ?? '' );
        $label_field = sanitize_text_field( $_POST['label_field'] ?? '' );

        // Sanitize arrays
        $filters = $this->sanitize_filters( $filters );
        $order   = array_map( 'sanitize_text_field', (array) $order );
        $config  = array_map( function( $v ) {
            return is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_text_field( $v );
        }, (array) $config );

        $data = $this->get_source_data( $source, $filters, $order, $limit, $config );

        // Format as choices
        $choices = [];
        
        foreach ( $data as $item ) {
            $value = $this->extract_field_value( $item, $value_field );
            $label = $this->extract_field_value( $item, $label_field ?: $value_field );

            if ( $value !== null ) {
                $choices[] = [
                    'value' => $value,
                    'label' => $label ?: $value,
                ];
            }
        }

        wp_send_json_success( [
            'choices' => $choices,
            'count'   => count( $choices ),
        ] );
    }

    /**
     * Sanitize filter array.
     *
     * @param array $filters Raw filters.
     * @return array Sanitized filters.
     */
    private function sanitize_filters( array $filters ): array {
        $sanitized = [];

        foreach ( $filters as $filter ) {
            if ( ! is_array( $filter ) ) {
                continue;
            }

            $sanitized[] = [
                'field'    => sanitize_key( $filter['field'] ?? '' ),
                'operator' => sanitize_text_field( $filter['operator'] ?? '=' ),
                'value'    => is_array( $filter['value'] ?? '' ) 
                    ? array_map( 'sanitize_text_field', $filter['value'] )
                    : sanitize_text_field( $filter['value'] ?? '' ),
                'meta_key' => sanitize_key( $filter['meta_key'] ?? '' ),
                'taxonomy' => sanitize_key( $filter['taxonomy'] ?? '' ),
            ];
        }

        return $sanitized;
    }

    /**
     * Get available sources for UI.
     *
     * @return array Sources configuration.
     */
    public function get_available_sources(): array {
        return $this->sources;
    }
}
