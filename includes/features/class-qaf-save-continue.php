<?php
/**
 * Save & Continue Feature
 *
 * Allows users to save form progress and resume later via unique link.
 *
 * @package QAF
 * @since 1.0.0
 */

namespace QAF\Features;

use QAF\Core\Form;
use QAF\Core\Entry;

/**
 * Class Save_Continue
 *
 * Implements save and continue functionality similar to Gravity Forms.
 */
class Save_Continue {

    /**
     * Database table name for saved drafts.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Expiration period in days.
     *
     * @var int
     */
    private int $expiration_days = 30;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'qaf_saved_forms';
        $this->init();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init(): void {
        add_action( 'wp_ajax_qaf_save_form_progress', [ $this, 'ajax_save_progress' ] );
        add_action( 'wp_ajax_nopriv_qaf_save_form_progress', [ $this, 'ajax_save_progress' ] );
        add_action( 'wp_ajax_qaf_load_saved_form', [ $this, 'ajax_load_saved' ] );
        add_action( 'wp_ajax_nopriv_qaf_load_saved_form', [ $this, 'ajax_load_saved' ] );
        add_action( 'wp_ajax_qaf_delete_saved_form', [ $this, 'ajax_delete_saved' ] );
        add_action( 'wp_ajax_nopriv_qaf_delete_saved_form', [ $this, 'ajax_delete_saved' ] );
        add_action( 'qaf_daily_cleanup', [ $this, 'cleanup_expired' ] );
        add_filter( 'qaf_form_render', [ $this, 'add_save_button' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'handle_resume_link' ] );
    }

    /**
     * Create database table on plugin activation.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;
        
        $table_name      = $wpdb->prefix . 'qaf_saved_forms';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token varchar(64) NOT NULL,
            form_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            form_data longtext NOT NULL,
            current_page int(11) DEFAULT 1,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            resume_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY form_id (form_id),
            KEY user_id (user_id),
            KEY email (email),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Save form progress via AJAX.
     *
     * @return void
     */
    public function ajax_save_progress(): void {
        check_ajax_referer( 'qaf_save_progress', 'nonce' );

        $form_id      = absint( $_POST['form_id'] ?? 0 );
        $form_data    = $_POST['form_data'] ?? [];
        $current_page = absint( $_POST['current_page'] ?? 1 );
        $email        = sanitize_email( $_POST['email'] ?? '' );
        $token        = sanitize_text_field( $_POST['token'] ?? '' );

        if ( ! $form_id || empty( $form_data ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid form data.', 'jexi-forms' ),
            ] );
        }

        // Get form to check if save & continue is enabled
        $form = Form::get( $form_id );
        if ( ! $form || ! $form->get_setting( 'save_and_continue' ) ) {
            wp_send_json_error( [
                'message' => __( 'Save & Continue is not enabled for this form.', 'jexi-forms' ),
            ] );
        }

        // Sanitize form data
        $sanitized_data = $this->sanitize_form_data( $form_data, $form );

        // Generate or use existing token
        if ( empty( $token ) ) {
            $token = $this->generate_token();
        }

        // Save to database
        $result = $this->save_draft( [
            'token'        => $token,
            'form_id'      => $form_id,
            'user_id'      => get_current_user_id() ?: null,
            'email'        => $email,
            'form_data'    => $sanitized_data,
            'current_page' => $current_page,
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [
                'message' => $result->get_error_message(),
            ] );
        }

        // Generate resume link
        $resume_link = $this->get_resume_link( $token, $form );

        // Send email if provided
        if ( ! empty( $email ) && $form->get_setting( 'save_email_link' ) ) {
            $this->send_resume_email( $email, $resume_link, $form );
        }

        wp_send_json_success( [
            'message'     => __( 'Your progress has been saved.', 'jexi-forms' ),
            'token'       => $token,
            'resume_link' => $resume_link,
            'expires'     => $this->get_expiration_date(),
        ] );
    }

    /**
     * Load saved form data via AJAX.
     *
     * @return void
     */
    public function ajax_load_saved(): void {
        check_ajax_referer( 'qaf_load_saved', 'nonce' );

        $token = sanitize_text_field( $_POST['token'] ?? '' );

        if ( empty( $token ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid resume token.', 'jexi-forms' ),
            ] );
        }

        $draft = $this->get_draft( $token );

        if ( ! $draft ) {
            wp_send_json_error( [
                'message' => __( 'Saved form data not found or has expired.', 'jexi-forms' ),
            ] );
        }

        // Check ownership if user is logged in
        if ( is_user_logged_in() && $draft->user_id && $draft->user_id !== get_current_user_id() ) {
            // Allow admins to view any draft
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [
                    'message' => __( 'You do not have permission to access this saved form.', 'jexi-forms' ),
                ] );
            }
        }

        // Increment resume count
        $this->increment_resume_count( $token );

        wp_send_json_success( [
            'form_id'      => $draft->form_id,
            'form_data'    => json_decode( $draft->form_data, true ),
            'current_page' => $draft->current_page,
            'updated_at'   => $draft->updated_at,
        ] );
    }

    /**
     * Delete saved form data via AJAX.
     *
     * @return void
     */
    public function ajax_delete_saved(): void {
        check_ajax_referer( 'qaf_delete_saved', 'nonce' );

        $token = sanitize_text_field( $_POST['token'] ?? '' );

        if ( empty( $token ) ) {
            wp_send_json_error( [
                'message' => __( 'Invalid token.', 'jexi-forms' ),
            ] );
        }

        $draft = $this->get_draft( $token );

        if ( ! $draft ) {
            wp_send_json_error( [
                'message' => __( 'Saved form not found.', 'jexi-forms' ),
            ] );
        }

        // Check ownership
        if ( $draft->user_id && ! is_user_logged_in() ) {
            wp_send_json_error( [
                'message' => __( 'You must be logged in to delete this saved form.', 'jexi-forms' ),
            ] );
        }

        if ( $draft->user_id && $draft->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => __( 'You do not have permission to delete this saved form.', 'jexi-forms' ),
            ] );
        }

        $this->delete_draft( $token );

        wp_send_json_success( [
            'message' => __( 'Saved form data has been deleted.', 'jexi-forms' ),
        ] );
    }

    /**
     * Save draft to database.
     *
     * @param array $data Draft data.
     * @return int|WP_Error Draft ID or error.
     */
    private function save_draft( array $data ) {
        global $wpdb;

        $existing = $this->get_draft( $data['token'] );
        $now      = current_time( 'mysql' );
        $expires  = $this->get_expiration_date();

        $insert_data = [
            'token'        => $data['token'],
            'form_id'      => $data['form_id'],
            'user_id'      => $data['user_id'],
            'email'        => $data['email'],
            'form_data'    => wp_json_encode( $data['form_data'] ),
            'current_page' => $data['current_page'],
            'ip_address'   => $this->get_client_ip(),
            'user_agent'   => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            'updated_at'   => $now,
            'expires_at'   => $expires,
        ];

        if ( $existing ) {
            // Update existing draft
            $result = $wpdb->update(
                $this->table_name,
                $insert_data,
                [ 'token' => $data['token'] ],
                [ '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ],
                [ '%s' ]
            );
        } else {
            // Insert new draft
            $insert_data['created_at'] = $now;
            $result = $wpdb->insert(
                $this->table_name,
                $insert_data,
                [ '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
            );
        }

        if ( false === $result ) {
            return new \WP_Error( 'db_error', __( 'Failed to save form progress.', 'jexi-forms' ) );
        }

        /**
         * Fires after form progress is saved.
         *
         * @param string $token   The unique token.
         * @param array  $data    The saved data.
         * @param bool   $is_new  Whether this is a new draft.
         */
        do_action( 'qaf_form_progress_saved', $data['token'], $data, ! $existing );

        return $existing ? $existing->id : $wpdb->insert_id;
    }

    /**
     * Get draft by token.
     *
     * @param string $token Resume token.
     * @return object|null Draft object or null.
     */
    private function get_draft( string $token ): ?object {
        global $wpdb;

        $draft = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE token = %s AND expires_at > %s",
                $token,
                current_time( 'mysql' )
            )
        );

        return $draft ?: null;
    }

    /**
     * Delete draft by token.
     *
     * @param string $token Resume token.
     * @return bool Success.
     */
    private function delete_draft( string $token ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            [ 'token' => $token ],
            [ '%s' ]
        );

        return false !== $result;
    }

    /**
     * Increment resume count for a draft.
     *
     * @param string $token Resume token.
     * @return void
     */
    private function increment_resume_count( string $token ): void {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} 
                SET resume_count = resume_count + 1 
                WHERE token = %s",
                $token
            )
        );
    }

    /**
     * Delete draft after successful submission.
     *
     * @param string $token Resume token.
     * @return void
     */
    public function delete_on_submission( string $token ): void {
        if ( ! empty( $token ) ) {
            $this->delete_draft( $token );
        }
    }

    /**
     * Generate unique token.
     *
     * @return string 64-character hex string.
     */
    private function generate_token(): string {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * Get resume link URL.
     *
     * @param string $token Resume token.
     * @param Form   $form  Form object.
     * @return string Resume URL.
     */
    private function get_resume_link( string $token, Form $form ): string {
        $base_url = $form->get_setting( 'resume_page' );
        
        if ( empty( $base_url ) ) {
            // Use referer or home URL
            $base_url = wp_get_referer() ?: home_url();
        }

        return add_query_arg( [
            'qaf_resume' => $token,
        ], $base_url );
    }

    /**
     * Get expiration date.
     *
     * @return string MySQL datetime string.
     */
    private function get_expiration_date(): string {
        $days = apply_filters( 'qaf_save_continue_expiration_days', $this->expiration_days );
        return gmdate( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
    }

    /**
     * Sanitize form data before saving.
     *
     * @param array $data Form data.
     * @param Form  $form Form object.
     * @return array Sanitized data.
     */
    private function sanitize_form_data( array $data, Form $form ): array {
        $sanitized = [];
        $fields    = $form->get_fields();

        foreach ( $data as $field_id => $value ) {
            // Skip sensitive fields (passwords, credit cards)
            $field = $fields[ $field_id ] ?? null;
            if ( $field ) {
                $type = $field->get_type();
                if ( in_array( $type, [ 'password', 'creditcard', 'payment' ], true ) ) {
                    continue;
                }
            }

            // Sanitize based on type
            if ( is_array( $value ) ) {
                $sanitized[ $field_id ] = array_map( 'sanitize_text_field', $value );
            } else {
                $sanitized[ $field_id ] = sanitize_text_field( $value );
            }
        }

        return $sanitized;
    }

    /**
     * Send resume email to user.
     *
     * @param string $email       Recipient email.
     * @param string $resume_link Resume URL.
     * @param Form   $form        Form object.
     * @return bool Success.
     */
    private function send_resume_email( string $email, string $resume_link, Form $form ): bool {
        $site_name   = get_bloginfo( 'name' );
        $form_title  = $form->get_title();
        $expiry_days = apply_filters( 'qaf_save_continue_expiration_days', $this->expiration_days );

        $subject = apply_filters(
            'qaf_save_continue_email_subject',
            sprintf(
                /* translators: 1: Form title, 2: Site name */
                __( 'Continue Your %1$s - %2$s', 'jexi-forms' ),
                $form_title,
                $site_name
            ),
            $form
        );

        $message = apply_filters(
            'qaf_save_continue_email_message',
            sprintf(
                /* translators: 1: Form title, 2: Resume link, 3: Expiry days, 4: Site name */
                __(
                    "You saved your progress on the form \"%1\$s\".\n\n" .
                    "Use this link to continue where you left off:\n%2\$s\n\n" .
                    "This link will expire in %3\$d days.\n\n" .
                    "If you did not request this, you can ignore this email.\n\n" .
                    "%4\$s",
                    'jexi-forms'
                ),
                $form_title,
                $resume_link,
                $expiry_days,
                $site_name
            ),
            $form,
            $resume_link
        );

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        ];

        $sent = wp_mail( $email, $subject, $message, $headers );

        /**
         * Fires after resume email is sent.
         *
         * @param string $email       Recipient email.
         * @param bool   $sent        Whether email was sent.
         * @param string $resume_link Resume URL.
         * @param Form   $form        Form object.
         */
        do_action( 'qaf_save_continue_email_sent', $email, $sent, $resume_link, $form );

        return $sent;
    }

    /**
     * Handle resume link redirect.
     *
     * @return void
     */
    public function handle_resume_link(): void {
        if ( empty( $_GET['qaf_resume'] ) ) {
            return;
        }

        $token = sanitize_text_field( $_GET['qaf_resume'] );
        $draft = $this->get_draft( $token );

        if ( ! $draft ) {
            // Add admin notice or flash message about expired/invalid link
            add_filter( 'qaf_form_messages', function( $messages ) {
                $messages['resume_expired'] = [
                    'type'    => 'warning',
                    'message' => __( 'Your saved form data has expired or could not be found.', 'jexi-forms' ),
                ];
                return $messages;
            } );
            return;
        }

        // Store token in session for form to pick up
        if ( ! session_id() ) {
            session_start();
        }
        $_SESSION['qaf_resume_token'] = $token;

        /**
         * Fires when a user resumes a saved form.
         *
         * @param object $draft The draft data.
         * @param string $token The resume token.
         */
        do_action( 'qaf_form_resumed', $draft, $token );
    }

    /**
     * Add save button to form.
     *
     * @param string $html Form HTML.
     * @param Form   $form Form object.
     * @return string Modified HTML.
     */
    public function add_save_button( string $html, Form $form ): string {
        if ( ! $form->get_setting( 'save_and_continue' ) ) {
            return $html;
        }

        $button_text = $form->get_setting( 'save_button_text' ) ?: __( 'Save & Continue Later', 'jexi-forms' );
        $show_email  = $form->get_setting( 'save_email_link' );

        $save_html = sprintf(
            '<div class="qaf-save-continue" role="group" aria-label="%s">',
            esc_attr__( 'Save and continue options', 'jexi-forms' )
        );

        if ( $show_email ) {
            $save_html .= sprintf(
                '<div class="qaf-save-email">
                    <label for="qaf-save-email-%d">%s</label>
                    <input type="email" 
                           id="qaf-save-email-%d" 
                           name="qaf_save_email" 
                           placeholder="%s"
                           aria-describedby="qaf-save-email-help-%d">
                    <small id="qaf-save-email-help-%d">%s</small>
                </div>',
                $form->get_id(),
                esc_html__( 'Email me a link to continue', 'jexi-forms' ),
                $form->get_id(),
                esc_attr__( 'your@email.com', 'jexi-forms' ),
                $form->get_id(),
                $form->get_id(),
                esc_html__( 'Optional: We\'ll email you a link to continue where you left off.', 'jexi-forms' )
            );
        }

        $save_html .= sprintf(
            '<button type="button" 
                     class="qaf-save-button qaf-button-secondary" 
                     data-form-id="%d"
                     aria-describedby="qaf-save-status-%d">
                <span class="qaf-save-icon" aria-hidden="true">💾</span>
                %s
            </button>
            <div id="qaf-save-status-%d" 
                 class="qaf-save-status" 
                 role="status" 
                 aria-live="polite"></div>
            </div>',
            $form->get_id(),
            $form->get_id(),
            esc_html( $button_text ),
            $form->get_id()
        );

        // Insert before submit button
        $html = preg_replace(
            '/(<button[^>]*type=["\']submit["\'][^>]*>)/i',
            $save_html . '$1',
            $html,
            1
        );

        return $html;
    }

    /**
     * Cleanup expired drafts.
     *
     * @return int Number of deleted drafts.
     */
    public function cleanup_expired(): int {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE expires_at < %s",
                current_time( 'mysql' )
            )
        );

        /**
         * Fires after expired drafts are cleaned up.
         *
         * @param int $deleted Number of deleted drafts.
         */
        do_action( 'qaf_save_continue_cleanup', $deleted );

        return (int) $deleted;
    }

    /**
     * Get saved drafts for a user.
     *
     * @param int $user_id User ID.
     * @return array Array of draft objects.
     */
    public function get_user_drafts( int $user_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sf.*, f.post_title as form_title 
                FROM {$this->table_name} sf
                LEFT JOIN {$wpdb->posts} f ON sf.form_id = f.ID
                WHERE sf.user_id = %d AND sf.expires_at > %s
                ORDER BY sf.updated_at DESC",
                $user_id,
                current_time( 'mysql' )
            )
        );
    }

    /**
     * Get client IP address.
     *
     * @return string IP address.
     */
    private function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = $_SERVER[ $key ];
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
