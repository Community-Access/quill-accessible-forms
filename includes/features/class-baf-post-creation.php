<?php
/**
 * Post Creation Feature
 *
 * Create WordPress posts, pages, and custom post types from form entries.
 * Supports field mapping, taxonomy assignment, featured images, and ACF fields.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Post_Creation
 *
 * Create posts from form submissions.
 */
class Post_Creation {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Create post on submission
        add_action( 'baf_entry_created', [ $this, 'maybe_create_post' ], 30, 2 );

        // Update post on entry update
        add_action( 'baf_entry_updated', [ $this, 'maybe_update_post' ], 30, 2 );

        // Delete post when entry deleted
        add_action( 'baf_entry_deleted', [ $this, 'maybe_delete_post' ], 30, 1 );

        // Handle deferred post creation (for pending/approved workflows)
        add_action( 'baf_entry_status_changed', [ $this, 'handle_status_change' ], 30, 3 );

        // Add merge tags for created post
        add_filter( 'baf_merge_tag_value', [ $this, 'process_merge_tags' ], 10, 4 );
    }

    /**
     * Maybe create post from entry.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function maybe_create_post( Entry $entry, Form $form ): void {
        $feeds = $this->get_post_feeds( $form );

        if ( empty( $feeds ) ) {
            return;
        }

        foreach ( $feeds as $feed ) {
            if ( ! $this->should_process_feed( $feed, $entry ) ) {
                continue;
            }

            // Check if should wait for approval
            if ( ! empty( $feed['wait_for_approval'] ) ) {
                $entry->update_meta( '_pending_post_feed_' . $feed['id'], $feed );
                continue;
            }

            $this->create_post_from_feed( $feed, $entry, $form );
        }
    }

    /**
     * Maybe update post when entry is updated.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function maybe_update_post( Entry $entry, Form $form ): void {
        $post_id = $entry->get_meta( '_created_post_id' );

        if ( ! $post_id ) {
            return;
        }

        $feeds = $this->get_post_feeds( $form );

        foreach ( $feeds as $feed ) {
            if ( empty( $feed['update_on_edit'] ) ) {
                continue;
            }

            if ( ! $this->should_process_feed( $feed, $entry ) ) {
                continue;
            }

            $this->update_post_from_feed( $feed, $entry, $form, $post_id );
        }
    }

    /**
     * Maybe delete post when entry deleted.
     *
     * @param Entry $entry Entry object.
     * @return void
     */
    public function maybe_delete_post( Entry $entry ): void {
        $post_id       = $entry->get_meta( '_created_post_id' );
        $delete_action = $entry->get_meta( '_post_delete_action' ) ?: 'trash';

        if ( ! $post_id ) {
            return;
        }

        switch ( $delete_action ) {
            case 'trash':
                wp_trash_post( $post_id );
                break;
            case 'delete':
                wp_delete_post( $post_id, true );
                break;
            case 'draft':
                wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => 'draft',
                ] );
                break;
            // 'nothing' - do nothing
        }
    }

    /**
     * Handle entry status change for deferred creation.
     *
     * @param Entry  $entry      Entry object.
     * @param string $new_status New status.
     * @param string $old_status Old status.
     * @return void
     */
    public function handle_status_change( Entry $entry, string $new_status, string $old_status ): void {
        // Create post when approved
        if ( $new_status === 'approved' ) {
            $pending_feeds = $entry->get_all_meta();
            
            foreach ( $pending_feeds as $key => $feed ) {
                if ( str_starts_with( $key, '_pending_post_feed_' ) && is_array( $feed ) ) {
                    $form = Form::get( $entry->get_form_id() );
                    $this->create_post_from_feed( $feed, $entry, $form );
                    $entry->delete_meta( $key );
                }
            }
        }

        // Update post status when entry status changes
        $post_id = $entry->get_meta( '_created_post_id' );
        
        if ( $post_id && $this->should_sync_status() ) {
            $post_status = $this->map_entry_status_to_post( $new_status );
            
            if ( $post_status ) {
                wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => $post_status,
                ] );
            }
        }
    }

    /**
     * Create post from feed configuration.
     *
     * @param array $feed  Feed configuration.
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return int|false Post ID or false on failure.
     */
    public function create_post_from_feed( array $feed, Entry $entry, Form $form ): int|false {
        $post_data = $this->build_post_data( $feed, $entry, $form );

        if ( ! $post_data ) {
            return false;
        }

        // Create the post
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            $this->log_error( 'Post creation failed', $post_id->get_error_message(), $entry, $feed );
            return false;
        }

        // Handle taxonomies
        $this->process_taxonomies( $feed, $entry, $post_id );

        // Handle custom fields/meta
        $this->process_post_meta( $feed, $entry, $post_id );

        // Handle ACF fields
        if ( function_exists( 'update_field' ) ) {
            $this->process_acf_fields( $feed, $entry, $post_id );
        }

        // Handle featured image
        $this->process_featured_image( $feed, $entry, $post_id );

        // Store reference
        $entry->update_meta( '_created_post_id', $post_id );
        $entry->update_meta( '_post_delete_action', $feed['delete_action'] ?? 'trash' );

        // Store reverse reference
        update_post_meta( $post_id, '_baf_entry_id', $entry->get_id() );
        update_post_meta( $post_id, '_baf_form_id', $form->get_id() );

        /**
         * Fires after post is created from entry.
         *
         * @param int   $post_id Post ID.
         * @param Entry $entry   Entry object.
         * @param Form  $form    Form object.
         * @param array $feed    Feed configuration.
         */
        do_action( 'baf_post_created', $post_id, $entry, $form, $feed );

        return $post_id;
    }

    /**
     * Update post from feed configuration.
     *
     * @param array $feed    Feed configuration.
     * @param Entry $entry   Entry object.
     * @param Form  $form    Form object.
     * @param int   $post_id Existing post ID.
     * @return bool Success.
     */
    public function update_post_from_feed( array $feed, Entry $entry, Form $form, int $post_id ): bool {
        $post_data = $this->build_post_data( $feed, $entry, $form );

        if ( ! $post_data ) {
            return false;
        }

        $post_data['ID'] = $post_id;

        $result = wp_update_post( $post_data, true );

        if ( is_wp_error( $result ) ) {
            $this->log_error( 'Post update failed', $result->get_error_message(), $entry, $feed );
            return false;
        }

        // Update taxonomies
        $this->process_taxonomies( $feed, $entry, $post_id );

        // Update meta
        $this->process_post_meta( $feed, $entry, $post_id );

        // Update ACF
        if ( function_exists( 'update_field' ) ) {
            $this->process_acf_fields( $feed, $entry, $post_id );
        }

        // Update featured image
        $this->process_featured_image( $feed, $entry, $post_id );

        /**
         * Fires after post is updated from entry.
         *
         * @param int   $post_id Post ID.
         * @param Entry $entry   Entry object.
         * @param Form  $form    Form object.
         * @param array $feed    Feed configuration.
         */
        do_action( 'baf_post_updated', $post_id, $entry, $form, $feed );

        return true;
    }

    /**
     * Build post data array.
     *
     * @param array $feed  Feed configuration.
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return array|false Post data or false.
     */
    private function build_post_data( array $feed, Entry $entry, Form $form ): array|false {
        $title_field   = $feed['title_field'] ?? '';
        $content_field = $feed['content_field'] ?? '';
        $excerpt_field = $feed['excerpt_field'] ?? '';

        // Require at least a title
        $title = $this->get_mapped_value( $title_field, $entry, $form );
        
        if ( empty( $title ) && empty( $feed['default_title'] ) ) {
            return false;
        }

        $post_data = [
            'post_type'    => $feed['post_type'] ?? 'post',
            'post_status'  => $feed['post_status'] ?? 'draft',
            'post_title'   => $title ?: ( $feed['default_title'] ?? __( 'Untitled', 'jexi-forms' ) ),
            'post_content' => $this->get_mapped_value( $content_field, $entry, $form ),
            'post_excerpt' => $this->get_mapped_value( $excerpt_field, $entry, $form ),
        ];

        // Post author
        $author_field = $feed['author_field'] ?? '';
        
        if ( $author_field ) {
            $author_value = $entry->get_field_value( $author_field );
            
            if ( is_numeric( $author_value ) ) {
                $post_data['post_author'] = (int) $author_value;
            } elseif ( is_email( $author_value ) ) {
                $user = get_user_by( 'email', $author_value );
                if ( $user ) {
                    $post_data['post_author'] = $user->ID;
                }
            }
        } elseif ( ! empty( $feed['default_author'] ) ) {
            $post_data['post_author'] = (int) $feed['default_author'];
        } elseif ( is_user_logged_in() ) {
            $post_data['post_author'] = get_current_user_id();
        }

        // Post date
        $date_field = $feed['date_field'] ?? '';
        
        if ( $date_field ) {
            $date_value = $entry->get_field_value( $date_field );
            
            if ( $date_value ) {
                $post_data['post_date'] = date( 'Y-m-d H:i:s', strtotime( $date_value ) );
            }
        }

        // Slug
        $slug_field = $feed['slug_field'] ?? '';
        
        if ( $slug_field ) {
            $slug = $entry->get_field_value( $slug_field );
            
            if ( $slug ) {
                $post_data['post_name'] = sanitize_title( $slug );
            }
        }

        // Parent
        $parent_field = $feed['parent_field'] ?? '';
        
        if ( $parent_field ) {
            $parent = (int) $entry->get_field_value( $parent_field );
            
            if ( $parent ) {
                $post_data['post_parent'] = $parent;
            }
        }

        // Menu order
        $order_field = $feed['order_field'] ?? '';
        
        if ( $order_field ) {
            $post_data['menu_order'] = (int) $entry->get_field_value( $order_field );
        }

        // Comment status
        if ( isset( $feed['comment_status'] ) ) {
            $post_data['comment_status'] = $feed['comment_status'];
        }

        return $post_data;
    }

    /**
     * Get mapped value from entry.
     *
     * @param string $mapping Mapping configuration.
     * @param Entry  $entry   Entry object.
     * @param Form   $form    Form object.
     * @return string Value.
     */
    private function get_mapped_value( string $mapping, Entry $entry, Form $form ): string {
        if ( ! $mapping ) {
            return '';
        }

        // Check if it's a merge tag template
        if ( strpos( $mapping, '{' ) !== false ) {
            return $this->process_template( $mapping, $entry, $form );
        }

        // Simple field mapping
        return (string) $entry->get_field_value( $mapping );
    }

    /**
     * Process merge tag template.
     *
     * @param string $template Template string.
     * @param Entry  $entry    Entry object.
     * @param Form   $form     Form object.
     * @return string Processed string.
     */
    private function process_template( string $template, Entry $entry, Form $form ): string {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            function( $matches ) use ( $entry ) {
                return $entry->get_field_value( $matches[1] ) ?? '';
            },
            $template
        );
    }

    /**
     * Process taxonomy assignments.
     *
     * @param array $feed    Feed configuration.
     * @param Entry $entry   Entry object.
     * @param int   $post_id Post ID.
     * @return void
     */
    private function process_taxonomies( array $feed, Entry $entry, int $post_id ): void {
        $taxonomies = $feed['taxonomies'] ?? [];

        foreach ( $taxonomies as $taxonomy => $config ) {
            $terms = [];

            // Fixed terms
            if ( ! empty( $config['fixed'] ) ) {
                $terms = array_merge( $terms, (array) $config['fixed'] );
            }

            // Dynamic from field
            if ( ! empty( $config['field'] ) ) {
                $field_value = $entry->get_field_value( $config['field'] );
                
                if ( $field_value ) {
                    $dynamic = is_array( $field_value ) ? $field_value : explode( ',', $field_value );
                    $terms   = array_merge( $terms, array_map( 'trim', $dynamic ) );
                }
            }

            // Create terms if allowed
            $create_new = $config['create_new'] ?? false;

            $term_ids = [];
            
            foreach ( $terms as $term ) {
                if ( is_numeric( $term ) ) {
                    $term_ids[] = (int) $term;
                } else {
                    $existing = term_exists( $term, $taxonomy );
                    
                    if ( $existing ) {
                        $term_ids[] = (int) $existing['term_id'];
                    } elseif ( $create_new ) {
                        $new_term = wp_insert_term( $term, $taxonomy );
                        
                        if ( ! is_wp_error( $new_term ) ) {
                            $term_ids[] = $new_term['term_id'];
                        }
                    }
                }
            }

            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $post_id, $term_ids, $taxonomy, $config['append'] ?? false );
            }
        }
    }

    /**
     * Process post meta.
     *
     * @param array $feed    Feed configuration.
     * @param Entry $entry   Entry object.
     * @param int   $post_id Post ID.
     * @return void
     */
    private function process_post_meta( array $feed, Entry $entry, int $post_id ): void {
        $meta_mapping = $feed['meta'] ?? [];

        foreach ( $meta_mapping as $meta_key => $field_id ) {
            $value = is_string( $field_id ) ? $entry->get_field_value( $field_id ) : $field_id;
            
            if ( $value !== '' && $value !== null ) {
                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Custom meta from mapping
        $custom_meta = $feed['custom_meta'] ?? [];
        
        foreach ( $custom_meta as $mapping ) {
            $key   = $mapping['key'] ?? '';
            $value = $this->get_mapped_value( $mapping['value'] ?? '', $entry, Form::get( $entry->get_form_id() ) );

            if ( $key && $value !== '' ) {
                update_post_meta( $post_id, $key, $value );
            }
        }
    }

    /**
     * Process ACF fields.
     *
     * @param array $feed    Feed configuration.
     * @param Entry $entry   Entry object.
     * @param int   $post_id Post ID.
     * @return void
     */
    private function process_acf_fields( array $feed, Entry $entry, int $post_id ): void {
        $acf_mapping = $feed['acf_fields'] ?? [];

        foreach ( $acf_mapping as $acf_field => $field_id ) {
            $value = $entry->get_field_value( $field_id );
            
            if ( $value !== '' && $value !== null ) {
                update_field( $acf_field, $value, $post_id );
            }
        }
    }

    /**
     * Process featured image.
     *
     * @param array $feed    Feed configuration.
     * @param Entry $entry   Entry object.
     * @param int   $post_id Post ID.
     * @return void
     */
    private function process_featured_image( array $feed, Entry $entry, int $post_id ): void {
        $image_field = $feed['featured_image_field'] ?? '';

        if ( ! $image_field ) {
            return;
        }

        $image_value = $entry->get_field_value( $image_field );

        if ( ! $image_value ) {
            return;
        }

        // If it's already an attachment ID
        if ( is_numeric( $image_value ) ) {
            set_post_thumbnail( $post_id, (int) $image_value );
            return;
        }

        // If it's a URL, try to sideload
        if ( filter_var( $image_value, FILTER_VALIDATE_URL ) ) {
            $attachment_id = $this->sideload_image( $image_value, $post_id );
            
            if ( $attachment_id ) {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
    }

    /**
     * Sideload image from URL.
     *
     * @param string $url     Image URL.
     * @param int    $post_id Post ID.
     * @return int|false Attachment ID or false.
     */
    private function sideload_image( string $url, int $post_id ): int|false {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url );

        if ( is_wp_error( $tmp ) ) {
            return false;
        }

        $file_array = [
            'name'     => basename( $url ),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return false;
        }

        return $attachment_id;
    }

    /**
     * Check if feed should process.
     *
     * @param array $feed  Feed configuration.
     * @param Entry $entry Entry object.
     * @return bool
     */
    private function should_process_feed( array $feed, Entry $entry ): bool {
        // Check if enabled
        if ( empty( $feed['enabled'] ) ) {
            return false;
        }

        // Check conditions
        $conditions = $feed['conditions'] ?? [];

        if ( empty( $conditions ) ) {
            return true;
        }

        $logic  = $feed['condition_logic'] ?? 'all';
        $passed = 0;

        foreach ( $conditions as $condition ) {
            $field    = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value    = $condition['value'] ?? '';

            $field_value = $entry->get_field_value( $field );

            if ( $this->evaluate_condition( $field_value, $operator, $value ) ) {
                $passed++;
                
                if ( $logic === 'any' ) {
                    return true;
                }
            } elseif ( $logic === 'all' ) {
                return false;
            }
        }

        return $logic === 'all' ? $passed === count( $conditions ) : $passed > 0;
    }

    /**
     * Evaluate condition.
     *
     * @param mixed  $field_value Field value.
     * @param string $operator    Operator.
     * @param mixed  $compare     Comparison value.
     * @return bool
     */
    private function evaluate_condition( $field_value, string $operator, $compare ): bool {
        if ( is_array( $field_value ) ) {
            $field_value = implode( ',', $field_value );
        }

        switch ( $operator ) {
            case 'equals':
                return $field_value == $compare;
            case 'not_equals':
                return $field_value != $compare;
            case 'contains':
                return stripos( $field_value, $compare ) !== false;
            case 'is_empty':
                return empty( $field_value );
            case 'is_not_empty':
                return ! empty( $field_value );
            default:
                return false;
        }
    }

    /**
     * Get post feeds for form.
     *
     * @param Form $form Form object.
     * @return array Feeds.
     */
    private function get_post_feeds( Form $form ): array {
        return $form->get_setting( 'post_creation_feeds' ) ?: [];
    }

    /**
     * Check if should sync post status with entry status.
     *
     * @return bool
     */
    private function should_sync_status(): bool {
        return apply_filters( 'baf_sync_post_status', false );
    }

    /**
     * Map entry status to post status.
     *
     * @param string $entry_status Entry status.
     * @return string|null Post status.
     */
    private function map_entry_status_to_post( string $entry_status ): ?string {
        $map = apply_filters( 'baf_entry_to_post_status_map', [
            'approved'  => 'publish',
            'pending'   => 'pending',
            'rejected'  => 'draft',
            'spam'      => 'draft',
        ] );

        return $map[ $entry_status ] ?? null;
    }

    /**
     * Process merge tags for created post.
     *
     * @param mixed  $value Current value.
     * @param string $tag   Tag name.
     * @param Entry  $entry Entry object.
     * @param Form   $form  Form object.
     * @return mixed Processed value.
     */
    public function process_merge_tags( $value, string $tag, Entry $entry, Form $form ) {
        if ( ! str_starts_with( $tag, 'post:' ) ) {
            return $value;
        }

        $post_id = $entry->get_meta( '_created_post_id' );

        if ( ! $post_id ) {
            return '';
        }

        $property = substr( $tag, 5 );

        switch ( $property ) {
            case 'id':
                return $post_id;
            case 'url':
            case 'link':
                return get_permalink( $post_id );
            case 'edit_url':
                return get_edit_post_link( $post_id );
            case 'title':
                return get_the_title( $post_id );
            case 'status':
                return get_post_status( $post_id );
            case 'type':
                return get_post_type( $post_id );
        }

        return $value;
    }

    /**
     * Log error.
     *
     * @param string $message Message.
     * @param string $details Details.
     * @param Entry  $entry   Entry object.
     * @param array  $feed    Feed configuration.
     * @return void
     */
    private function log_error( string $message, string $details, Entry $entry, array $feed ): void {
        error_log( sprintf(
            '[BAF Post Creation] %s - Entry #%d - %s - Feed: %s',
            $message,
            $entry->get_id(),
            $details,
            $feed['name'] ?? 'Unknown'
        ) );
    }

    /**
     * Get post type options for UI.
     *
     * @return array Post types.
     */
    public static function get_post_type_options(): array {
        $types   = get_post_types( [ 'public' => true ], 'objects' );
        $options = [];

        foreach ( $types as $type ) {
            $options[ $type->name ] = $type->label;
        }

        return $options;
    }

    /**
     * Get post status options for UI.
     *
     * @return array Statuses.
     */
    public static function get_post_status_options(): array {
        return [
            'publish' => __( 'Published', 'jexi-forms' ),
            'draft'   => __( 'Draft', 'jexi-forms' ),
            'pending' => __( 'Pending Review', 'jexi-forms' ),
            'private' => __( 'Private', 'jexi-forms' ),
            'future'  => __( 'Scheduled', 'jexi-forms' ),
        ];
    }
}
