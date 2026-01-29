<?php
/**
 * Cascade Manager
 *
 * Manages cascading field relationships for dynamic dropdown filtering.
 * Provides both client-side (static mapping) and server-side (AJAX) cascade support.
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Cascade_Manager class
 */
class BAF_Cascade_Manager {

    /**
     * Singleton instance
     *
     * @var BAF_Cascade_Manager
     */
    private static $instance = null;

    /**
     * Registered cascade configurations
     *
     * @var array
     */
    private $cascades = array();

    /**
     * Get singleton instance
     *
     * @return BAF_Cascade_Manager
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_baf_get_cascade_options', array( $this, 'ajax_get_options' ) );
        add_action( 'wp_ajax_nopriv_baf_get_cascade_options', array( $this, 'ajax_get_options' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register a cascade relationship
     *
     * @param string $cascade_id   Unique identifier for the cascade.
     * @param array  $config       Configuration array.
     * @return bool Success.
     */
    public function register( $cascade_id, $config ) {
        $defaults = array(
            'parent_field'    => '',
            'child_field'     => '',
            'source'          => 'static',  // static, callback, taxonomy, posts, rest
            'mapping'         => array(),
            'callback'        => null,
            'taxonomy'        => '',
            'post_type'       => '',
            'rest_endpoint'   => '',
            'value_field'     => 'value',
            'label_field'     => 'label',
            'filters'         => array(),
            'cache_enabled'   => true,
            'cache_ttl'       => HOUR_IN_SECONDS,
            'empty_message'   => __( 'Please select an option above first', 'bits-accessible-forms' ),
            'loading_message' => __( 'Loading options...', 'bits-accessible-forms' ),
            'no_results'      => __( 'No options available', 'bits-accessible-forms' ),
        );

        $this->cascades[ $cascade_id ] = wp_parse_args( $config, $defaults );
        return true;
    }

    /**
     * Get cascade configuration
     *
     * @param string $cascade_id Cascade identifier.
     * @return array|null Configuration or null.
     */
    public function get_config( $cascade_id ) {
        return $this->cascades[ $cascade_id ] ?? null;
    }

    /**
     * Get all registered cascades
     *
     * @return array All configurations.
     */
    public function get_all() {
        return $this->cascades;
    }

    /**
     * Get options for a cascade based on parent value
     *
     * @param string $cascade_id   Cascade identifier.
     * @param string $parent_value Parent field value.
     * @return array Options array.
     */
    public function get_options( $cascade_id, $parent_value ) {
        $config = $this->get_config( $cascade_id );
        
        if ( ! $config ) {
            return array();
        }

        // Check cache
        if ( $config['cache_enabled'] ) {
            $cache_key = 'baf_cascade_' . $cascade_id . '_' . md5( $parent_value );
            $cached = wp_cache_get( $cache_key, 'baf_cascade' );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $options = array();

        switch ( $config['source'] ) {
            case 'static':
                $options = $this->get_static_options( $config, $parent_value );
                break;

            case 'callback':
                $options = $this->get_callback_options( $config, $parent_value );
                break;

            case 'taxonomy':
                $options = $this->get_taxonomy_options( $config, $parent_value );
                break;

            case 'posts':
                $options = $this->get_posts_options( $config, $parent_value );
                break;

            case 'library':
                $options = $this->get_library_options( $config, $parent_value );
                break;

            case 'rest':
                $options = $this->get_rest_options( $config, $parent_value );
                break;
        }

        // Apply filters
        $options = apply_filters( 'baf_cascade_options', $options, $cascade_id, $parent_value, $config );

        // Cache results
        if ( $config['cache_enabled'] && ! empty( $options ) ) {
            wp_cache_set( $cache_key, $options, 'baf_cascade', $config['cache_ttl'] );
        }

        return $options;
    }

    /**
     * Get options from static mapping
     *
     * @param array  $config       Cascade configuration.
     * @param string $parent_value Parent value.
     * @return array Options.
     */
    private function get_static_options( $config, $parent_value ) {
        $mapping = $config['mapping'];
        
        if ( isset( $mapping[ $parent_value ] ) ) {
            return $this->normalize_options( $mapping[ $parent_value ] );
        }

        return array();
    }

    /**
     * Get options from callback
     *
     * @param array  $config       Cascade configuration.
     * @param string $parent_value Parent value.
     * @return array Options.
     */
    private function get_callback_options( $config, $parent_value ) {
        if ( ! is_callable( $config['callback'] ) ) {
            return array();
        }

        $options = call_user_func( $config['callback'], $parent_value, $config );
        return $this->normalize_options( $options );
    }

    /**
     * Get options from taxonomy
     *
     * @param array  $config       Cascade configuration.
     * @param string $parent_value Parent value (term ID or slug).
     * @return array Options.
     */
    private function get_taxonomy_options( $config, $parent_value ) {
        $taxonomy = $config['taxonomy'];
        
        if ( ! taxonomy_exists( $taxonomy ) ) {
            return array();
        }

        $args = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        );

        // Filter by parent term
        if ( is_numeric( $parent_value ) ) {
            $args['parent'] = (int) $parent_value;
        } else {
            // Get parent term by slug
            $parent_term = get_term_by( 'slug', $parent_value, $taxonomy );
            if ( $parent_term ) {
                $args['parent'] = $parent_term->term_id;
            }
        }

        // Apply additional filters
        foreach ( $config['filters'] as $filter ) {
            if ( isset( $filter['meta_key'] ) ) {
                $args['meta_query'][] = array(
                    'key'   => $filter['meta_key'],
                    'value' => $filter['meta_value'] ?? $parent_value,
                );
            }
        }

        $terms = get_terms( $args );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        $options = array();
        foreach ( $terms as $term ) {
            $options[] = array(
                'value' => $term->term_id,
                'label' => $term->name,
            );
        }

        return $options;
    }

    /**
     * Get options from posts
     *
     * @param array  $config       Cascade configuration.
     * @param string $parent_value Parent value.
     * @return array Options.
     */
    private function get_posts_options( $config, $parent_value ) {
        $args = array(
            'post_type'      => $config['post_type'] ?: 'post',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        // Apply filters
        $meta_query = array();
        foreach ( $config['filters'] as $filter ) {
            if ( isset( $filter['field'] ) && strpos( $filter['field'], 'meta:' ) === 0 ) {
                $meta_key = substr( $filter['field'], 5 );
                $meta_query[] = array(
                    'key'     => $meta_key,
                    'value'   => $parent_value,
                    'compare' => $filter['compare'] ?? '=',
                );
            } elseif ( isset( $filter['taxonomy'] ) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => $filter['taxonomy'],
                    'field'    => 'term_id',
                    'terms'    => $parent_value,
                );
            }
        }

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }

        $posts = get_posts( $args );

        $options = array();
        $value_field = $config['value_field'];
        $label_field = $config['label_field'];

        foreach ( $posts as $post ) {
            $value = $value_field === 'ID' ? $post->ID : get_post_meta( $post->ID, $value_field, true );
            $label = $label_field === 'post_title' ? $post->post_title : get_post_meta( $post->ID, $label_field, true );
            
            $options[] = array(
                'value' => $value ?: $post->ID,
                'label' => $label ?: $post->post_title,
            );
        }

        return $options;
    }

    /**
     * Get options from choice library
     *
     * @param array  $config       Cascade configuration.
     * @param string $parent_value Parent value.
     * @return array Options.
     */
    private function get_library_options( $config, $parent_value ) {
        if ( ! class_exists( 'BAF_Choice_Libraries' ) ) {
            return array();
        }

        $library_id = $config['library'] ?? '';
        return BAF_Choice_Libraries::get( $library_id, $parent_value );
    }

    /**
     * Get options from REST endpoint
     *
     * @param array  $config       Cascade configuration.
     * @param string $parent_value Parent value.
     * @return array Options.
     */
    private function get_rest_options( $config, $parent_value ) {
        $endpoint = $config['rest_endpoint'];
        
        if ( empty( $endpoint ) ) {
            return array();
        }

        // Replace placeholder in endpoint
        $endpoint = str_replace( '{parent}', urlencode( $parent_value ), $endpoint );

        $response = wp_remote_get( $endpoint, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return array();
        }

        return $this->normalize_options( $data, $config['value_field'], $config['label_field'] );
    }

    /**
     * Normalize options to standard format
     *
     * @param array  $options     Raw options.
     * @param string $value_field Value field name.
     * @param string $label_field Label field name.
     * @return array Normalized options.
     */
    private function normalize_options( $options, $value_field = 'value', $label_field = 'label' ) {
        $normalized = array();

        foreach ( $options as $key => $option ) {
            if ( is_array( $option ) ) {
                $normalized[] = array(
                    'value' => $option[ $value_field ] ?? $option['value'] ?? $key,
                    'label' => $option[ $label_field ] ?? $option['label'] ?? $option['name'] ?? $key,
                );
            } else {
                // Simple key => value format
                $normalized[] = array(
                    'value' => $key,
                    'label' => $option,
                );
            }
        }

        return $normalized;
    }

    /**
     * AJAX handler for getting cascade options
     */
    public function ajax_get_options() {
        check_ajax_referer( 'baf_cascade_nonce', 'nonce' );

        $cascade_id = isset( $_POST['cascade_id'] ) ? sanitize_text_field( $_POST['cascade_id'] ) : '';
        $parent_value = isset( $_POST['parent_value'] ) ? sanitize_text_field( $_POST['parent_value'] ) : '';
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

        if ( empty( $cascade_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing cascade ID' ) );
        }

        $options = $this->get_options( $cascade_id, $parent_value );
        
        $config = $this->get_config( $cascade_id );
        
        wp_send_json_success( array(
            'options'        => $options,
            'empty_message'  => $config['empty_message'] ?? '',
            'no_results'     => $config['no_results'] ?? '',
        ) );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route( 'baf/v1', '/cascade/(?P<cascade_id>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_options' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'cascade_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'parent_value' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * REST API handler for getting cascade options
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response.
     */
    public function rest_get_options( $request ) {
        $cascade_id = $request->get_param( 'cascade_id' );
        $parent_value = $request->get_param( 'parent_value' );

        $options = $this->get_options( $cascade_id, $parent_value );
        $config = $this->get_config( $cascade_id );

        return rest_ensure_response( array(
            'options'        => $options,
            'empty_message'  => $config['empty_message'] ?? '',
            'no_results'     => $config['no_results'] ?? '',
        ) );
    }

    /**
     * Build cascade data attributes for field rendering
     *
     * @param array $field_config Field configuration.
     * @return array Data attributes.
     */
    public function get_field_data_attributes( $field_config ) {
        $attrs = array();

        if ( empty( $field_config['cascade_from'] ) ) {
            return $attrs;
        }

        $attrs['data-cascade-from'] = $field_config['cascade_from'];
        
        if ( ! empty( $field_config['cascade_mapping'] ) ) {
            $attrs['data-cascade-mapping'] = wp_json_encode( $field_config['cascade_mapping'] );
        }

        if ( ! empty( $field_config['cascade_library'] ) ) {
            $attrs['data-cascade-library'] = $field_config['cascade_library'];
        }

        if ( ! empty( $field_config['empty_message'] ) ) {
            $attrs['data-empty-message'] = $field_config['empty_message'];
        }

        if ( ! empty( $field_config['loading_message'] ) ) {
            $attrs['data-loading-message'] = $field_config['loading_message'];
        }

        return $attrs;
    }

    /**
     * Clear cascade cache
     *
     * @param string $cascade_id Optional specific cascade ID.
     */
    public function clear_cache( $cascade_id = null ) {
        if ( $cascade_id ) {
            wp_cache_delete( 'baf_cascade_' . $cascade_id, 'baf_cascade' );
        } else {
            wp_cache_flush();
        }
    }
}

/**
 * Get cascade manager instance
 *
 * @return BAF_Cascade_Manager
 */
function baf_cascade_manager() {
    return BAF_Cascade_Manager::instance();
}
