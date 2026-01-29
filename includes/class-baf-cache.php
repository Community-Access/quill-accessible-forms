<?php
/**
 * Performance Caching
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Cache class
 * 
 * Handles caching for forms, entries, and rendered output.
 */
class BAF_Cache {

    /**
     * Cache group
     *
     * @var string
     */
    const CACHE_GROUP = 'baf';

    /**
     * Default TTL (1 hour)
     *
     * @var int
     */
    const DEFAULT_TTL = 3600;

    /**
     * Form cache TTL (15 minutes)
     *
     * @var int
     */
    const FORM_TTL = 900;

    /**
     * Rendered form cache TTL (5 minutes)
     *
     * @var int
     */
    const RENDER_TTL = 300;

    /**
     * Use object cache
     *
     * @var bool
     */
    private $use_object_cache;

    /**
     * Local cache storage
     *
     * @var array
     */
    private $local_cache = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->use_object_cache = wp_using_ext_object_cache();
    }

    /**
     * Get cached value
     *
     * @param string $key   Cache key.
     * @param mixed  $group Cache group.
     * @return mixed|false Cached value or false.
     */
    public function get( $key, $group = self::CACHE_GROUP ) {
        // Check local cache first
        $local_key = $group . ':' . $key;
        if ( isset( $this->local_cache[ $local_key ] ) ) {
            return $this->local_cache[ $local_key ];
        }

        // Check object cache
        if ( $this->use_object_cache ) {
            $value = wp_cache_get( $key, $group );
            if ( false !== $value ) {
                $this->local_cache[ $local_key ] = $value;
                return $value;
            }
        }

        // Check transient fallback
        $value = get_transient( 'baf_' . md5( $local_key ) );
        if ( false !== $value ) {
            $this->local_cache[ $local_key ] = $value;
            return $value;
        }

        return false;
    }

    /**
     * Set cached value
     *
     * @param string $key    Cache key.
     * @param mixed  $value  Value to cache.
     * @param int    $ttl    Time to live in seconds.
     * @param string $group  Cache group.
     * @return bool True on success.
     */
    public function set( $key, $value, $ttl = self::DEFAULT_TTL, $group = self::CACHE_GROUP ) {
        $local_key = $group . ':' . $key;
        
        // Always set local cache
        $this->local_cache[ $local_key ] = $value;

        // Set object cache
        if ( $this->use_object_cache ) {
            return wp_cache_set( $key, $value, $group, $ttl );
        }

        // Fallback to transient
        return set_transient( 'baf_' . md5( $local_key ), $value, $ttl );
    }

    /**
     * Delete cached value
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return bool True on success.
     */
    public function delete( $key, $group = self::CACHE_GROUP ) {
        $local_key = $group . ':' . $key;
        
        unset( $this->local_cache[ $local_key ] );

        if ( $this->use_object_cache ) {
            wp_cache_delete( $key, $group );
        }

        delete_transient( 'baf_' . md5( $local_key ) );
        return true;
    }

    /**
     * Flush all BAF cache
     *
     * @return bool True on success.
     */
    public function flush() {
        $this->local_cache = array();

        if ( $this->use_object_cache ) {
            wp_cache_flush_group( self::CACHE_GROUP );
        }

        // Delete all BAF transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_baf_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_baf_%'" );

        return true;
    }

    /**
     * Get form from cache
     *
     * @param int $form_id Form ID.
     * @return BAF_Form|false Form object or false.
     */
    public function get_form( $form_id ) {
        return $this->get( 'form_' . $form_id );
    }

    /**
     * Cache form
     *
     * @param BAF_Form $form Form object.
     * @return bool True on success.
     */
    public function set_form( $form ) {
        return $this->set( 'form_' . $form->id, $form, self::FORM_TTL );
    }

    /**
     * Invalidate form cache
     *
     * @param int $form_id Form ID.
     */
    public function invalidate_form( $form_id ) {
        $this->delete( 'form_' . $form_id );
        $this->delete( 'form_config_' . $form_id );
        $this->delete( 'form_fields_' . $form_id );
        $this->delete( 'form_bfl_' . $form_id );
        
        // Also invalidate rendered form cache
        $this->delete_matching( 'rendered_form_' . $form_id . '_' );
    }

    /**
     * Get rendered form HTML from cache
     *
     * @param int    $form_id Form ID.
     * @param string $hash    Config hash.
     * @return string|false HTML or false.
     */
    public function get_rendered_form( $form_id, $hash ) {
        return $this->get( 'rendered_form_' . $form_id . '_' . $hash );
    }

    /**
     * Cache rendered form HTML
     *
     * @param int    $form_id Form ID.
     * @param string $hash    Config hash.
     * @param string $html    Rendered HTML.
     * @return bool True on success.
     */
    public function set_rendered_form( $form_id, $hash, $html ) {
        return $this->set( 'rendered_form_' . $form_id . '_' . $hash, $html, self::RENDER_TTL );
    }

    /**
     * Get entry count for form
     *
     * @param int    $form_id Form ID.
     * @param string $status  Status filter.
     * @return int|false Count or false.
     */
    public function get_entry_count( $form_id, $status = '' ) {
        $key = 'entry_count_' . $form_id;
        if ( $status ) {
            $key .= '_' . $status;
        }
        return $this->get( $key );
    }

    /**
     * Cache entry count
     *
     * @param int    $form_id Form ID.
     * @param int    $count   Entry count.
     * @param string $status  Status filter.
     * @return bool True on success.
     */
    public function set_entry_count( $form_id, $count, $status = '' ) {
        $key = 'entry_count_' . $form_id;
        if ( $status ) {
            $key .= '_' . $status;
        }
        return $this->set( $key, $count, 300 ); // 5 minutes
    }

    /**
     * Invalidate entry cache for form
     *
     * @param int $form_id Form ID.
     */
    public function invalidate_entries( $form_id ) {
        $this->delete( 'entry_count_' . $form_id );
        $this->delete( 'entry_count_' . $form_id . '_new' );
        $this->delete( 'entry_count_' . $form_id . '_read' );
        $this->delete( 'entry_count_' . $form_id . '_spam' );
        $this->delete( 'entry_count_' . $form_id . '_trash' );
        $this->delete( 'entries_recent_' . $form_id );
    }

    /**
     * Get view data from cache
     *
     * @param int    $view_id View ID.
     * @param string $hash    Query hash.
     * @return array|false View data or false.
     */
    public function get_view_data( $view_id, $hash ) {
        return $this->get( 'view_' . $view_id . '_' . $hash );
    }

    /**
     * Cache view data
     *
     * @param int    $view_id View ID.
     * @param string $hash    Query hash.
     * @param array  $data    View data.
     * @return bool True on success.
     */
    public function set_view_data( $view_id, $hash, $data ) {
        return $this->set( 'view_' . $view_id . '_' . $hash, $data, 300 );
    }

    /**
     * Delete cache keys matching pattern
     *
     * @param string $pattern Key pattern.
     */
    private function delete_matching( $pattern ) {
        // Delete from local cache
        foreach ( array_keys( $this->local_cache ) as $key ) {
            if ( strpos( $key, self::CACHE_GROUP . ':' . $pattern ) === 0 ) {
                unset( $this->local_cache[ $key ] );
            }
        }

        // Delete transients matching pattern
        global $wpdb;
        $transient_pattern = 'baf_' . md5( self::CACHE_GROUP . ':' . $pattern );
        $transient_pattern = substr( $transient_pattern, 0, -5 ) . '%';
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $transient_pattern
        ) );
    }

    /**
     * Get or compute value with caching
     *
     * @param string   $key      Cache key.
     * @param callable $callback Callback to compute value.
     * @param int      $ttl      Time to live.
     * @param string   $group    Cache group.
     * @return mixed Cached or computed value.
     */
    public function remember( $key, $callback, $ttl = self::DEFAULT_TTL, $group = self::CACHE_GROUP ) {
        $value = $this->get( $key, $group );

        if ( false !== $value ) {
            return $value;
        }

        $value = call_user_func( $callback );
        $this->set( $key, $value, $ttl, $group );

        return $value;
    }

    /**
     * Warm form cache
     *
     * @param int $form_id Form ID.
     */
    public function warm_form_cache( $form_id ) {
        $form = BAF_Form::find( $form_id );
        if ( ! $form ) {
            return;
        }

        // Cache form object
        $this->set_form( $form );

        // Cache form configuration
        $this->set( 'form_config_' . $form_id, $form->get_config(), self::FORM_TTL );

        // Cache fields
        $this->set( 'form_fields_' . $form_id, $form->get_fields(), self::FORM_TTL );

        // Cache BFL if exists
        if ( $form->bfl_source ) {
            $this->set( 'form_bfl_' . $form_id, $form->bfl_source, self::FORM_TTL );
        }

        // Cache entry count
        $count = BAF_Entry::count_by_form( $form_id );
        $this->set_entry_count( $form_id, $count );
    }

    /**
     * Warm all active forms cache
     */
    public function warm_all_forms() {
        global $wpdb;
        $table = $wpdb->prefix . 'baf_forms';

        $form_ids = $wpdb->get_col( "SELECT id FROM {$table} WHERE status = 'active'" );

        foreach ( $form_ids as $form_id ) {
            $this->warm_form_cache( $form_id );
        }
    }

    /**
     * Get cache stats
     *
     * @return array Cache statistics.
     */
    public function get_stats() {
        global $wpdb;

        $stats = array(
            'object_cache_available' => $this->use_object_cache,
            'local_cache_entries'    => count( $this->local_cache ),
            'transient_count'        => 0,
        );

        // Count transients
        $stats['transient_count'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_baf_%'"
        );

        if ( $this->use_object_cache && function_exists( 'wp_cache_get_stats' ) ) {
            $stats['object_cache_stats'] = wp_cache_get_stats();
        }

        return $stats;
    }

    /**
     * Preload frequently accessed forms
     */
    public function preload() {
        // Preload forms that have been accessed recently
        $recent_forms = get_option( 'baf_recent_forms', array() );
        
        if ( empty( $recent_forms ) ) {
            return;
        }

        // Limit to top 10
        $recent_forms = array_slice( $recent_forms, 0, 10 );

        foreach ( $recent_forms as $form_id ) {
            if ( ! $this->get_form( $form_id ) ) {
                $this->warm_form_cache( $form_id );
            }
        }
    }

    /**
     * Track form access for preloading
     *
     * @param int $form_id Form ID.
     */
    public function track_form_access( $form_id ) {
        $recent = get_option( 'baf_recent_forms', array() );
        
        // Move to front
        $recent = array_diff( $recent, array( $form_id ) );
        array_unshift( $recent, $form_id );
        
        // Keep only 50
        $recent = array_slice( $recent, 0, 50 );
        
        update_option( 'baf_recent_forms', $recent, false );
    }
}

/**
 * Get cache instance
 *
 * @return BAF_Cache
 */
function baf_cache() {
    static $instance = null;
    
    if ( null === $instance ) {
        $instance = new BAF_Cache();
    }
    
    return $instance;
}

// Invalidate caches on updates
add_action( 'baf_form_updated', function( $form_id ) {
    baf_cache()->invalidate_form( $form_id );
} );

add_action( 'baf_entry_created', function( $entry_id, $form_id ) {
    baf_cache()->invalidate_entries( $form_id );
}, 10, 2 );

add_action( 'baf_entry_updated', function( $entry_id, $form_id ) {
    baf_cache()->invalidate_entries( $form_id );
}, 10, 2 );

// Preload on admin pages
add_action( 'admin_init', function() {
    if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'baf' ) === 0 ) {
        baf_cache()->preload();
    }
} );
