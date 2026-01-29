<?php
/**
 * Magic Links Feature
 *
 * Provides tokenized URLs for entry access without requiring login.
 * Supports viewing, editing, and administrative actions via secure links.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Magic_Links
 *
 * Manages tokenized access to form entries.
 */
class Magic_Links {

    /**
     * Token length.
     */
    private const TOKEN_LENGTH = 64;

    /**
     * Link types.
     */
    public const TYPE_VIEW   = 'view';
    public const TYPE_EDIT   = 'edit';
    public const TYPE_DELETE = 'delete';
    public const TYPE_PRINT  = 'print';
    public const TYPE_CUSTOM = 'custom';

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
        // Handle magic link access
        add_action( 'init', [ $this, 'maybe_handle_magic_link' ], 20 );

        // Generate magic links for new entries
        add_action( 'baf_entry_created', [ $this, 'generate_entry_links' ], 10, 2 );

        // Register merge tags for magic links
        add_filter( 'baf_merge_tag_value', [ $this, 'process_merge_tags' ], 10, 4 );

        // Clean up expired tokens
        add_action( 'baf_daily_tasks', [ $this, 'cleanup_expired_links' ] );

        // AJAX handlers
        add_action( 'wp_ajax_baf_generate_magic_link', [ $this, 'ajax_generate_link' ] );
        add_action( 'wp_ajax_baf_revoke_magic_link', [ $this, 'ajax_revoke_link' ] );

        // REST API
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Handle magic link access.
     *
     * @return void
     */
    public function maybe_handle_magic_link(): void {
        // Check for magic link parameter
        $token = isset( $_GET['baf_token'] ) ? sanitize_text_field( $_GET['baf_token'] ) : '';
        
        if ( ! $token ) {
            return;
        }

        $link = $this->get_link_by_token( $token );

        if ( ! $link ) {
            $this->show_error( __( 'This link is invalid or has expired.', 'jexi-forms' ) );
            return;
        }

        // Check expiration
        if ( $link->expires_at && strtotime( $link->expires_at ) < current_time( 'timestamp' ) ) {
            $this->show_error( __( 'This link has expired.', 'jexi-forms' ) );
            return;
        }

        // Check max uses
        if ( $link->max_uses > 0 && $link->use_count >= $link->max_uses ) {
            $this->show_error( __( 'This link has reached its maximum number of uses.', 'jexi-forms' ) );
            return;
        }

        // Increment usage
        $this->increment_usage( $link->id );

        // Load entry
        $entry = Entry::get( $link->entry_id );
        
        if ( ! $entry ) {
            $this->show_error( __( 'The requested entry could not be found.', 'jexi-forms' ) );
            return;
        }

        // Store context for templates
        set_query_var( 'baf_magic_link', $link );
        set_query_var( 'baf_magic_entry', $entry );

        /**
         * Allow custom handlers before default actions.
         *
         * @param object $link  Link object.
         * @param Entry  $entry Entry object.
         */
        do_action( 'baf_magic_link_access', $link, $entry );

        // Handle action
        switch ( $link->link_type ) {
            case self::TYPE_VIEW:
                $this->handle_view( $entry, $link );
                break;

            case self::TYPE_EDIT:
                $this->handle_edit( $entry, $link );
                break;

            case self::TYPE_DELETE:
                $this->handle_delete( $entry, $link );
                break;

            case self::TYPE_PRINT:
                $this->handle_print( $entry, $link );
                break;

            case self::TYPE_CUSTOM:
                /**
                 * Handle custom magic link action.
                 *
                 * @param Entry  $entry  Entry object.
                 * @param object $link   Link object.
                 * @param string $action Custom action name.
                 */
                do_action( 'baf_magic_link_custom_' . $link->custom_action, $entry, $link );
                break;
        }
    }

    /**
     * Handle view action.
     *
     * @param Entry  $entry Entry object.
     * @param object $link  Link object.
     * @return void
     */
    private function handle_view( Entry $entry, object $link ): void {
        $form = Form::get( $entry->get_form_id() );

        // Load view template
        $template = apply_filters( 'baf_magic_link_view_template', null, $entry, $form );

        if ( $template && file_exists( $template ) ) {
            include $template;
            exit;
        }

        // Default: output entry data
        $this->render_entry_view( $entry, $form );
    }

    /**
     * Handle edit action.
     *
     * @param Entry  $entry Entry object.
     * @param object $link  Link object.
     * @return void
     */
    private function handle_edit( Entry $entry, object $link ): void {
        // Set up editing context
        add_filter( 'baf_form_render_mode', function() { return 'edit'; } );
        add_filter( 'baf_entry_being_edited', function() use ( $entry ) { return $entry; } );

        // Let normal form handling take over
        // The form will be prepopulated with entry values
    }

    /**
     * Handle delete action.
     *
     * @param Entry  $entry Entry object.
     * @param object $link  Link object.
     * @return void
     */
    private function handle_delete( Entry $entry, object $link ): void {
        // Require confirmation
        $confirmed = isset( $_GET['confirm'] ) && $_GET['confirm'] === 'yes';

        if ( ! $confirmed ) {
            $this->render_delete_confirmation( $entry );
            return;
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'baf_delete_entry_' . $entry->get_id() ) ) {
            $this->show_error( __( 'Security check failed. Please try again.', 'jexi-forms' ) );
            return;
        }

        // Delete entry
        $entry->delete();

        // Revoke all magic links for this entry
        $this->revoke_entry_links( $entry->get_id() );

        // Show confirmation
        $form     = Form::get( $entry->get_form_id() );
        $redirect = $form->get_setting( 'delete_redirect_url' );

        if ( $redirect ) {
            wp_redirect( $redirect );
            exit;
        }

        $this->render_delete_success();
    }

    /**
     * Handle print action.
     *
     * @param Entry  $entry Entry object.
     * @param object $link  Link object.
     * @return void
     */
    private function handle_print( Entry $entry, object $link ): void {
        $form = Form::get( $entry->get_form_id() );

        // Output print-friendly version
        $this->render_entry_print( $entry, $form );
    }

    /**
     * Generate magic links for a new entry.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function generate_entry_links( Entry $entry, Form $form ): void {
        $auto_generate = $form->get_setting( 'magic_links_auto_generate' );

        if ( empty( $auto_generate ) ) {
            return;
        }

        foreach ( (array) $auto_generate as $type ) {
            $this->create_link( $entry->get_id(), $type, [
                'expires_days' => $form->get_setting( "magic_link_{$type}_expires" ) ?: 0,
                'max_uses'     => $form->get_setting( "magic_link_{$type}_max_uses" ) ?: 0,
            ] );
        }
    }

    /**
     * Create a magic link.
     *
     * @param int    $entry_id Entry ID.
     * @param string $type     Link type.
     * @param array  $args     Additional arguments.
     * @return string|false Link URL or false on failure.
     */
    public function create_link( int $entry_id, string $type = self::TYPE_VIEW, array $args = [] ): string|false {
        global $wpdb;

        $entry = Entry::get( $entry_id );
        if ( ! $entry ) {
            return false;
        }

        $token = $this->generate_token();
        $table = $wpdb->prefix . 'baf_magic_links';

        // Calculate expiration
        $expires_at = null;
        if ( ! empty( $args['expires_days'] ) ) {
            $expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$args['expires_days']} days" ) );
        } elseif ( ! empty( $args['expires_at'] ) ) {
            $expires_at = $args['expires_at'];
        }

        $result = $wpdb->insert(
            $table,
            [
                'entry_id'      => $entry_id,
                'form_id'       => $entry->get_form_id(),
                'token'         => $token,
                'link_type'     => $type,
                'custom_action' => $args['custom_action'] ?? null,
                'expires_at'    => $expires_at,
                'max_uses'      => $args['max_uses'] ?? 0,
                'use_count'     => 0,
                'created_by'    => get_current_user_id() ?: null,
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ]
        );

        if ( ! $result ) {
            return false;
        }

        $url = $this->get_link_url( $token );

        /**
         * Fires after magic link is created.
         *
         * @param string $url      Generated URL.
         * @param int    $entry_id Entry ID.
         * @param string $type     Link type.
         * @param array  $args     Additional arguments.
         */
        do_action( 'baf_magic_link_created', $url, $entry_id, $type, $args );

        return $url;
    }

    /**
     * Get magic link URL.
     *
     * @param string $token Link token.
     * @return string URL.
     */
    public function get_link_url( string $token ): string {
        $base_url = apply_filters( 'baf_magic_link_base_url', home_url( '/' ) );
        
        return add_query_arg( 'baf_token', $token, $base_url );
    }

    /**
     * Get existing link for an entry.
     *
     * @param int    $entry_id Entry ID.
     * @param string $type     Link type.
     * @return object|null Link object or null.
     */
    public function get_entry_link( int $entry_id, string $type = self::TYPE_VIEW ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_magic_links';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                WHERE entry_id = %d AND link_type = %s AND revoked_at IS NULL
                ORDER BY created_at DESC LIMIT 1",
                $entry_id,
                $type
            )
        );
    }

    /**
     * Get or create link for an entry.
     *
     * @param int    $entry_id Entry ID.
     * @param string $type     Link type.
     * @param array  $args     Arguments for creation if needed.
     * @return string|false Link URL or false.
     */
    public function get_or_create_link( int $entry_id, string $type = self::TYPE_VIEW, array $args = [] ): string|false {
        $existing = $this->get_entry_link( $entry_id, $type );

        if ( $existing && ( ! $existing->expires_at || strtotime( $existing->expires_at ) > current_time( 'timestamp' ) ) ) {
            return $this->get_link_url( $existing->token );
        }

        return $this->create_link( $entry_id, $type, $args );
    }

    /**
     * Revoke a magic link.
     *
     * @param int $link_id Link ID.
     * @return bool Success.
     */
    public function revoke_link( int $link_id ): bool {
        global $wpdb;

        $table  = $wpdb->prefix . 'baf_magic_links';
        $result = $wpdb->update(
            $table,
            [
                'revoked_at' => current_time( 'mysql' ),
                'revoked_by' => get_current_user_id() ?: null,
            ],
            [ 'id' => $link_id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Revoke all links for an entry.
     *
     * @param int $entry_id Entry ID.
     * @return int Number of links revoked.
     */
    public function revoke_entry_links( int $entry_id ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_magic_links';
        
        return (int) $wpdb->update(
            $table,
            [
                'revoked_at' => current_time( 'mysql' ),
                'revoked_by' => get_current_user_id() ?: null,
            ],
            [ 'entry_id' => $entry_id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );
    }

    /**
     * Get link by token.
     *
     * @param string $token Token.
     * @return object|null Link object or null.
     */
    private function get_link_by_token( string $token ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_magic_links';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token = %s AND revoked_at IS NULL",
                $token
            )
        );
    }

    /**
     * Generate secure token.
     *
     * @return string Token.
     */
    private function generate_token(): string {
        return bin2hex( random_bytes( self::TOKEN_LENGTH / 2 ) );
    }

    /**
     * Increment link usage count.
     *
     * @param int $link_id Link ID.
     * @return void
     */
    private function increment_usage( int $link_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_magic_links';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET use_count = use_count + 1, last_used_at = %s WHERE id = %d",
                current_time( 'mysql' ),
                $link_id
            )
        );
    }

    /**
     * Process merge tags for magic links.
     *
     * @param mixed  $value    Current value.
     * @param string $tag      Tag name.
     * @param Entry  $entry    Entry object.
     * @param Form   $form     Form object.
     * @return mixed Processed value.
     */
    public function process_merge_tags( $value, string $tag, Entry $entry, Form $form ) {
        $magic_tags = [
            'view_entry_link'   => self::TYPE_VIEW,
            'edit_entry_link'   => self::TYPE_EDIT,
            'delete_entry_link' => self::TYPE_DELETE,
            'print_entry_link'  => self::TYPE_PRINT,
        ];

        if ( isset( $magic_tags[ $tag ] ) ) {
            return $this->get_or_create_link( $entry->get_id(), $magic_tags[ $tag ] );
        }

        return $value;
    }

    /**
     * Clean up expired links.
     *
     * @return void
     */
    public function cleanup_expired_links(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_magic_links';
        $days  = apply_filters( 'baf_magic_link_retention_days', 30 );

        // Delete expired links older than retention period
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} 
                WHERE (expires_at IS NOT NULL AND expires_at < %s)
                   OR (revoked_at IS NOT NULL AND revoked_at < %s)",
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ),
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
            )
        );
    }

    /**
     * AJAX: Generate magic link.
     *
     * @return void
     */
    public function ajax_generate_link(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $entry_id = (int) ( $_POST['entry_id'] ?? 0 );
        $type     = sanitize_text_field( $_POST['type'] ?? self::TYPE_VIEW );
        $args     = [];

        if ( ! empty( $_POST['expires_days'] ) ) {
            $args['expires_days'] = (int) $_POST['expires_days'];
        }

        if ( ! empty( $_POST['max_uses'] ) ) {
            $args['max_uses'] = (int) $_POST['max_uses'];
        }

        $url = $this->create_link( $entry_id, $type, $args );

        if ( ! $url ) {
            wp_send_json_error( [ 'message' => __( 'Failed to create magic link.', 'jexi-forms' ) ] );
        }

        wp_send_json_success( [
            'url'  => $url,
            'type' => $type,
        ] );
    }

    /**
     * AJAX: Revoke magic link.
     *
     * @return void
     */
    public function ajax_revoke_link(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $link_id = (int) ( $_POST['link_id'] ?? 0 );

        if ( $this->revoke_link( $link_id ) ) {
            wp_send_json_success();
        }

        wp_send_json_error( [ 'message' => __( 'Failed to revoke link.', 'jexi-forms' ) ] );
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route( 'baf/v1', '/magic-links/(?P<entry_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'rest_get_links' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'rest_create_link' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
    }

    /**
     * REST: Get links for entry.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response.
     */
    public function rest_get_links( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $entry_id = (int) $request->get_param( 'entry_id' );
        $table    = $wpdb->prefix . 'baf_magic_links';

        $links = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entry_id = %d ORDER BY created_at DESC",
                $entry_id
            )
        );

        foreach ( $links as &$link ) {
            $link->url = $this->get_link_url( $link->token );
            unset( $link->token ); // Don't expose token in API response
        }

        return new \WP_REST_Response( $links, 200 );
    }

    /**
     * REST: Create magic link.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response.
     */
    public function rest_create_link( \WP_REST_Request $request ): \WP_REST_Response {
        $entry_id = (int) $request->get_param( 'entry_id' );
        $type     = $request->get_param( 'type' ) ?? self::TYPE_VIEW;
        $args     = $request->get_params();

        $url = $this->create_link( $entry_id, $type, $args );

        if ( ! $url ) {
            return new \WP_REST_Response( [ 'error' => 'Failed to create link' ], 400 );
        }

        return new \WP_REST_Response( [ 'url' => $url ], 201 );
    }

    /**
     * Show error page.
     *
     * @param string $message Error message.
     * @return void
     */
    private function show_error( string $message ): void {
        wp_die(
            esc_html( $message ),
            __( 'Link Error', 'jexi-forms' ),
            [ 'response' => 403 ]
        );
    }

    /**
     * Render entry view.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    private function render_entry_view( Entry $entry, Form $form ): void {
        // Include view template
        include dirname( __FILE__ ) . '/../views/partials/magic-link-view.php';
        exit;
    }

    /**
     * Render print view.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    private function render_entry_print( Entry $entry, Form $form ): void {
        include dirname( __FILE__ ) . '/../views/partials/magic-link-print.php';
        exit;
    }

    /**
     * Render delete confirmation.
     *
     * @param Entry $entry Entry object.
     * @return void
     */
    private function render_delete_confirmation( Entry $entry ): void {
        $form        = Form::get( $entry->get_form_id() );
        $confirm_url = add_query_arg( [
            'confirm'  => 'yes',
            '_wpnonce' => wp_create_nonce( 'baf_delete_entry_' . $entry->get_id() ),
        ] );

        include dirname( __FILE__ ) . '/../views/partials/magic-link-delete.php';
        exit;
    }

    /**
     * Render delete success.
     *
     * @return void
     */
    private function render_delete_success(): void {
        include dirname( __FILE__ ) . '/../views/partials/magic-link-deleted.php';
        exit;
    }

    /**
     * Create tables on activation.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table           = $wpdb->prefix . 'baf_magic_links';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) UNSIGNED NOT NULL,
            form_id bigint(20) UNSIGNED NOT NULL,
            token varchar(64) NOT NULL,
            link_type varchar(20) NOT NULL DEFAULT 'view',
            custom_action varchar(50) DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            max_uses int(11) NOT NULL DEFAULT 0,
            use_count int(11) NOT NULL DEFAULT 0,
            last_used_at datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL,
            revoked_at datetime DEFAULT NULL,
            revoked_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY entry_id (entry_id),
            KEY form_id (form_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
