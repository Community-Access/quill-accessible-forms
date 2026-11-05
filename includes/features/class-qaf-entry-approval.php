<?php
/**
 * Entry Approval Workflow
 *
 * Pending/approved/rejected statuses with approval actions, notifications,
 * and role-based permissions. Similar to GravityView Entry Approval.
 *
 * @package QAF
 * @since 1.0.0
 */

namespace QAF\Features;

use QAF\Core\Entry;
use QAF\Core\Form;

/**
 * Class Entry_Approval
 *
 * Manages entry approval workflow.
 */
class Entry_Approval {

    /**
     * Approval statuses.
     */
    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_UNAPPROVED = 'unapproved';

    /**
     * Meta key for approval status.
     *
     * @var string
     */
    private const META_KEY = '_qaf_approval_status';

    /**
     * Meta key for approval history.
     *
     * @var string
     */
    private const HISTORY_META_KEY = '_qaf_approval_history';

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Set initial status on entry creation
        add_action( 'qaf_entry_created', [ $this, 'set_initial_status' ], 10, 2 );

        // Admin actions
        add_action( 'wp_ajax_qaf_approve_entry', [ $this, 'ajax_approve' ] );
        add_action( 'wp_ajax_qaf_reject_entry', [ $this, 'ajax_reject' ] );
        add_action( 'wp_ajax_qaf_unapprove_entry', [ $this, 'ajax_unapprove' ] );
        add_action( 'wp_ajax_qaf_bulk_approval', [ $this, 'ajax_bulk_approval' ] );

        // Filter entries by approval status in admin
        add_filter( 'qaf_entries_query_args', [ $this, 'filter_by_approval_status' ] );

        // Frontend: Only show approved entries in views
        add_filter( 'qaf_view_entries_query', [ $this, 'filter_view_entries' ], 10, 2 );

        // REST API
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Admin notices
        add_action( 'admin_notices', [ $this, 'show_pending_notices' ] );

        // Scheduled tasks
        add_action( 'qaf_daily_tasks', [ $this, 'send_pending_reminders' ] );
    }

    /**
     * Get available approval statuses.
     *
     * @return array Statuses with labels.
     */
    public static function get_statuses(): array {
        return apply_filters( 'qaf_approval_statuses', [
            self::STATUS_PENDING    => __( 'Pending Review', 'jexi-forms' ),
            self::STATUS_APPROVED   => __( 'Approved', 'jexi-forms' ),
            self::STATUS_REJECTED   => __( 'Rejected', 'jexi-forms' ),
            self::STATUS_UNAPPROVED => __( 'Unapproved', 'jexi-forms' ),
        ] );
    }

    /**
     * Set initial approval status on entry creation.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function set_initial_status( Entry $entry, Form $form ): void {
        // Check if form has approval enabled
        if ( ! $form->get_setting( 'enable_approval' ) ) {
            return;
        }

        $initial_status = $form->get_setting( 'default_approval_status' ) ?: self::STATUS_PENDING;

        // Auto-approve based on conditions
        if ( $this->should_auto_approve( $entry, $form ) ) {
            $initial_status = self::STATUS_APPROVED;
        }

        $this->set_status( $entry, $initial_status, [
            'note'    => __( 'Initial status on submission', 'jexi-forms' ),
            'user_id' => 0,
        ] );

        // Send notification if pending
        if ( $initial_status === self::STATUS_PENDING ) {
            $this->send_pending_notification( $entry, $form );
        }
    }

    /**
     * Check if entry should be auto-approved.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return bool True if should auto-approve.
     */
    private function should_auto_approve( Entry $entry, Form $form ): bool {
        // Auto-approve for logged-in users with specific roles
        $auto_approve_roles = $form->get_setting( 'auto_approve_roles' );
        if ( ! empty( $auto_approve_roles ) && is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( array_intersect( (array) $auto_approve_roles, $user->roles ) ) {
                return true;
            }
        }

        // Auto-approve based on field conditions
        $conditions = $form->get_setting( 'auto_approve_conditions' );
        if ( ! empty( $conditions ) && $this->evaluate_conditions( $conditions, $entry ) ) {
            return true;
        }

        return apply_filters( 'qaf_should_auto_approve', false, $entry, $form );
    }

    /**
     * Evaluate conditional rules.
     *
     * @param array $conditions Conditions array.
     * @param Entry $entry      Entry object.
     * @return bool True if conditions pass.
     */
    private function evaluate_conditions( array $conditions, Entry $entry ): bool {
        $data = $entry->get_data();

        foreach ( $conditions as $condition ) {
            $field_id  = $condition['field'] ?? '';
            $operator  = $condition['operator'] ?? '=';
            $value     = $condition['value'] ?? '';
            $field_val = $data[ $field_id ] ?? '';

            $passes = false;

            switch ( $operator ) {
                case '=':
                case '==':
                    $passes = $field_val == $value;
                    break;
                case '!=':
                    $passes = $field_val != $value;
                    break;
                case '>':
                    $passes = (float) $field_val > (float) $value;
                    break;
                case '<':
                    $passes = (float) $field_val < (float) $value;
                    break;
                case 'contains':
                    $passes = stripos( $field_val, $value ) !== false;
                    break;
                case 'not_empty':
                    $passes = ! empty( $field_val );
                    break;
                case 'empty':
                    $passes = empty( $field_val );
                    break;
            }

            // Check if all conditions must pass (AND) or any (OR)
            $logic = $conditions['logic'] ?? 'and';
            
            if ( $logic === 'or' && $passes ) {
                return true;
            }
            
            if ( $logic === 'and' && ! $passes ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get approval status for an entry.
     *
     * @param Entry|int $entry Entry object or ID.
     * @return string Status.
     */
    public function get_status( $entry ): string {
        $entry_id = $entry instanceof Entry ? $entry->get_id() : (int) $entry;
        $status   = get_metadata( 'qaf_entry', $entry_id, self::META_KEY, true );
        
        return $status ?: self::STATUS_UNAPPROVED;
    }

    /**
     * Set approval status for an entry.
     *
     * @param Entry|int $entry   Entry object or ID.
     * @param string    $status  New status.
     * @param array     $context Context data (user_id, note, etc.).
     * @return bool Success.
     */
    public function set_status( $entry, string $status, array $context = [] ): bool {
        $entry_id    = $entry instanceof Entry ? $entry->get_id() : (int) $entry;
        $old_status  = $this->get_status( $entry_id );
        $valid_statuses = array_keys( self::get_statuses() );

        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }

        // Update status
        update_metadata( 'qaf_entry', $entry_id, self::META_KEY, $status );

        // Add to history
        $this->add_history_entry( $entry_id, [
            'from'      => $old_status,
            'to'        => $status,
            'user_id'   => $context['user_id'] ?? get_current_user_id(),
            'note'      => $context['note'] ?? '',
            'timestamp' => current_time( 'mysql' ),
        ] );

        /**
         * Fires when approval status changes.
         *
         * @param int    $entry_id   Entry ID.
         * @param string $status     New status.
         * @param string $old_status Previous status.
         * @param array  $context    Context data.
         */
        do_action( 'qaf_approval_status_changed', $entry_id, $status, $old_status, $context );

        // Status-specific actions
        $this->handle_status_change( $entry_id, $status, $old_status, $context );

        return true;
    }

    /**
     * Handle status change actions.
     *
     * @param int    $entry_id   Entry ID.
     * @param string $status     New status.
     * @param string $old_status Previous status.
     * @param array  $context    Context data.
     * @return void
     */
    private function handle_status_change( int $entry_id, string $status, string $old_status, array $context ): void {
        $entry = Entry::get( $entry_id );
        
        if ( ! $entry ) {
            return;
        }

        $form = $entry->get_form();

        switch ( $status ) {
            case self::STATUS_APPROVED:
                do_action( 'qaf_entry_approved', $entry, $context );
                
                // Send approval notification
                if ( $form->get_setting( 'notify_on_approval' ) ) {
                    $this->send_status_notification( $entry, $form, 'approved', $context );
                }
                break;

            case self::STATUS_REJECTED:
                do_action( 'qaf_entry_rejected', $entry, $context );
                
                // Send rejection notification
                if ( $form->get_setting( 'notify_on_rejection' ) ) {
                    $this->send_status_notification( $entry, $form, 'rejected', $context );
                }
                break;
        }
    }

    /**
     * Add entry to approval history.
     *
     * @param int   $entry_id Entry ID.
     * @param array $data     History data.
     * @return void
     */
    private function add_history_entry( int $entry_id, array $data ): void {
        $history = get_metadata( 'qaf_entry', $entry_id, self::HISTORY_META_KEY, true ) ?: [];
        $history[] = $data;
        
        update_metadata( 'qaf_entry', $entry_id, self::HISTORY_META_KEY, $history );
    }

    /**
     * Get approval history for an entry.
     *
     * @param Entry|int $entry Entry object or ID.
     * @return array History entries.
     */
    public function get_history( $entry ): array {
        $entry_id = $entry instanceof Entry ? $entry->get_id() : (int) $entry;
        return get_metadata( 'qaf_entry', $entry_id, self::HISTORY_META_KEY, true ) ?: [];
    }

    /**
     * Approve an entry.
     *
     * @param Entry|int $entry Entry object or ID.
     * @param string    $note  Optional note.
     * @return bool Success.
     */
    public function approve( $entry, string $note = '' ): bool {
        if ( ! $this->can_moderate() ) {
            return false;
        }

        return $this->set_status( $entry, self::STATUS_APPROVED, [
            'note' => $note ?: __( 'Entry approved', 'jexi-forms' ),
        ] );
    }

    /**
     * Reject an entry.
     *
     * @param Entry|int $entry Entry object or ID.
     * @param string    $note  Rejection reason.
     * @return bool Success.
     */
    public function reject( $entry, string $note = '' ): bool {
        if ( ! $this->can_moderate() ) {
            return false;
        }

        return $this->set_status( $entry, self::STATUS_REJECTED, [
            'note' => $note ?: __( 'Entry rejected', 'jexi-forms' ),
        ] );
    }

    /**
     * Unapprove (reset to pending) an entry.
     *
     * @param Entry|int $entry Entry object or ID.
     * @param string    $note  Optional note.
     * @return bool Success.
     */
    public function unapprove( $entry, string $note = '' ): bool {
        if ( ! $this->can_moderate() ) {
            return false;
        }

        return $this->set_status( $entry, self::STATUS_PENDING, [
            'note' => $note ?: __( 'Entry status reset to pending', 'jexi-forms' ),
        ] );
    }

    /**
     * Check if current user can moderate entries.
     *
     * @return bool True if can moderate.
     */
    public function can_moderate(): bool {
        $capability = apply_filters( 'qaf_approval_capability', 'edit_others_posts' );
        return current_user_can( $capability );
    }

    /**
     * AJAX handler for approving entry.
     *
     * @return void
     */
    public function ajax_approve(): void {
        check_ajax_referer( 'qaf_approval', 'nonce' );

        if ( ! $this->can_moderate() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $note     = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry.', 'jexi-forms' ) ] );
        }

        if ( $this->approve( $entry_id, $note ) ) {
            wp_send_json_success( [
                'message' => __( 'Entry approved.', 'jexi-forms' ),
                'status'  => self::STATUS_APPROVED,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to approve entry.', 'jexi-forms' ) ] );
        }
    }

    /**
     * AJAX handler for rejecting entry.
     *
     * @return void
     */
    public function ajax_reject(): void {
        check_ajax_referer( 'qaf_approval', 'nonce' );

        if ( ! $this->can_moderate() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $note     = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry.', 'jexi-forms' ) ] );
        }

        if ( $this->reject( $entry_id, $note ) ) {
            wp_send_json_success( [
                'message' => __( 'Entry rejected.', 'jexi-forms' ),
                'status'  => self::STATUS_REJECTED,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to reject entry.', 'jexi-forms' ) ] );
        }
    }

    /**
     * AJAX handler for unapproving entry.
     *
     * @return void
     */
    public function ajax_unapprove(): void {
        check_ajax_referer( 'qaf_approval', 'nonce' );

        if ( ! $this->can_moderate() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $entry_id = absint( $_POST['entry_id'] ?? 0 );
        $note     = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $entry_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid entry.', 'jexi-forms' ) ] );
        }

        if ( $this->unapprove( $entry_id, $note ) ) {
            wp_send_json_success( [
                'message' => __( 'Entry status reset.', 'jexi-forms' ),
                'status'  => self::STATUS_PENDING,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to reset entry status.', 'jexi-forms' ) ] );
        }
    }

    /**
     * AJAX handler for bulk approval actions.
     *
     * @return void
     */
    public function ajax_bulk_approval(): void {
        check_ajax_referer( 'qaf_approval', 'nonce' );

        if ( ! $this->can_moderate() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $entry_ids = array_map( 'absint', (array) ( $_POST['entry_ids'] ?? [] ) );
        $action    = sanitize_key( $_POST['bulk_action'] ?? '' );
        $note      = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( empty( $entry_ids ) || empty( $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'jexi-forms' ) ] );
        }

        $success = 0;

        foreach ( $entry_ids as $entry_id ) {
            $result = false;

            switch ( $action ) {
                case 'approve':
                    $result = $this->approve( $entry_id, $note );
                    break;
                case 'reject':
                    $result = $this->reject( $entry_id, $note );
                    break;
                case 'unapprove':
                    $result = $this->unapprove( $entry_id, $note );
                    break;
            }

            if ( $result ) {
                $success++;
            }
        }

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of entries */
                __( '%d entries updated.', 'jexi-forms' ),
                $success
            ),
            'count' => $success,
        ] );
    }

    /**
     * Filter entries query by approval status.
     *
     * @param array $args Query arguments.
     * @return array Modified arguments.
     */
    public function filter_by_approval_status( array $args ): array {
        if ( isset( $_GET['approval_status'] ) && ! empty( $_GET['approval_status'] ) ) {
            $status = sanitize_key( $_GET['approval_status'] );
            
            if ( array_key_exists( $status, self::get_statuses() ) ) {
                $args['meta_query'][] = [
                    'key'   => self::META_KEY,
                    'value' => $status,
                ];
            }
        }

        return $args;
    }

    /**
     * Filter view entries to show only approved.
     *
     * @param array  $args Query arguments.
     * @param object $view View configuration.
     * @return array Modified arguments.
     */
    public function filter_view_entries( array $args, $view ): array {
        // Check view settings
        $show_status = $view->get_setting( 'show_approval_status' ) ?? [ self::STATUS_APPROVED ];
        
        if ( ! is_array( $show_status ) ) {
            $show_status = [ $show_status ];
        }

        // Admins can see all if configured
        if ( in_array( 'all', $show_status, true ) && current_user_can( 'manage_options' ) ) {
            return $args;
        }

        $args['meta_query'][] = [
            'key'     => self::META_KEY,
            'value'   => $show_status,
            'compare' => 'IN',
        ];

        return $args;
    }

    /**
     * Send pending notification to moderators.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    private function send_pending_notification( Entry $entry, Form $form ): void {
        $recipients = $form->get_setting( 'approval_notify_emails' );
        
        if ( empty( $recipients ) ) {
            // Default to admin email
            $recipients = get_option( 'admin_email' );
        }

        $subject = sprintf(
            /* translators: 1: Form title, 2: Site name */
            __( 'New entry pending approval: %1$s - %2$s', 'jexi-forms' ),
            $form->get_title(),
            get_bloginfo( 'name' )
        );

        $edit_url = admin_url( 'admin.php?page=qaf-entries&action=edit&entry_id=' . $entry->get_id() );

        $message = sprintf(
            /* translators: 1: Entry ID, 2: Form title, 3: Edit URL */
            __(
                "A new entry (ID: %1\$d) has been submitted to \"%2\$s\" and is pending your review.\n\n" .
                "Review this entry:\n%3\$s\n\n" .
                "Thank you.",
                'jexi-forms'
            ),
            $entry->get_id(),
            $form->get_title(),
            $edit_url
        );

        // Process message with merge tags
        $message = Merge_Tags::instance()->process( $message, $form, $entry );

        wp_mail(
            $recipients,
            $subject,
            $message,
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    /**
     * Send status change notification to entry submitter.
     *
     * @param Entry  $entry   Entry object.
     * @param Form   $form    Form object.
     * @param string $status  Status (approved/rejected).
     * @param array  $context Context data.
     * @return void
     */
    private function send_status_notification( Entry $entry, Form $form, string $status, array $context ): void {
        // Get submitter email
        $email_field = $form->get_setting( 'submitter_email_field' );
        $recipient   = $email_field ? $entry->get_field_value( $email_field ) : '';

        // Or get from user if logged in
        if ( empty( $recipient ) && $entry->get_user_id() ) {
            $user = get_user_by( 'id', $entry->get_user_id() );
            $recipient = $user ? $user->user_email : '';
        }

        if ( empty( $recipient ) ) {
            return;
        }

        // Get templates based on status
        $template_key = $status === 'approved' ? 'approval_email_template' : 'rejection_email_template';
        $default_subject = $status === 'approved'
            ? __( 'Your submission has been approved', 'jexi-forms' )
            : __( 'Your submission has been reviewed', 'jexi-forms' );

        $subject = $form->get_setting( "{$status}_email_subject" ) ?: $default_subject;
        $message = $form->get_setting( $template_key ) ?: $this->get_default_template( $status );

        // Add rejection reason
        if ( $status === 'rejected' && ! empty( $context['note'] ) ) {
            $message .= "\n\n" . sprintf(
                /* translators: %s: rejection reason */
                __( 'Reason: %s', 'jexi-forms' ),
                $context['note']
            );
        }

        // Process merge tags
        $subject = Merge_Tags::instance()->process( $subject, $form, $entry );
        $message = Merge_Tags::instance()->process( $message, $form, $entry );

        wp_mail(
            $recipient,
            $subject,
            $message,
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    /**
     * Get default email template.
     *
     * @param string $status Status.
     * @return string Template.
     */
    private function get_default_template( string $status ): string {
        if ( $status === 'approved' ) {
            return __(
                "Hello,\n\n" .
                "Your submission to \"{form_title}\" has been approved.\n\n" .
                "You can view your submission here:\n{entry_url}\n\n" .
                "Thank you.",
                'jexi-forms'
            );
        }

        return __(
            "Hello,\n\n" .
            "Your submission to \"{form_title}\" has been reviewed and was not approved.\n\n" .
            "If you have questions, please contact us.\n\n" .
            "Thank you.",
            'jexi-forms'
        );
    }

    /**
     * Show admin notice for pending entries.
     *
     * @return void
     */
    public function show_pending_notices(): void {
        if ( ! $this->can_moderate() ) {
            return;
        }

        // Only show on QAF pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'qaf' ) === false ) {
            return;
        }

        $pending_count = $this->get_pending_count();

        if ( $pending_count > 0 ) {
            printf(
                '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
                sprintf(
                    /* translators: %d: number of entries */
                    esc_html__( 'There are %d entries pending approval.', 'jexi-forms' ),
                    $pending_count
                ),
                esc_url( admin_url( 'admin.php?page=qaf-entries&approval_status=pending' ) ),
                esc_html__( 'View pending entries', 'jexi-forms' )
            );
        }
    }

    /**
     * Get count of pending entries.
     *
     * @param int|null $form_id Optional form ID to filter by.
     * @return int Count.
     */
    public function get_pending_count( ?int $form_id = null ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entry_meta';

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE meta_key = %s AND meta_value = %s",
            self::META_KEY,
            self::STATUS_PENDING
        );

        if ( $form_id ) {
            $entries_table = $wpdb->prefix . 'qaf_entries';
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} m 
                INNER JOIN {$entries_table} e ON m.entry_id = e.id 
                WHERE m.meta_key = %s AND m.meta_value = %s AND e.form_id = %d",
                self::META_KEY,
                self::STATUS_PENDING,
                $form_id
            );
        }

        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Send reminders for pending entries.
     *
     * @return void
     */
    public function send_pending_reminders(): void {
        global $wpdb;

        $entries_table = $wpdb->prefix . 'qaf_entries';
        $meta_table    = $wpdb->prefix . 'qaf_entry_meta';

        // Find forms with pending entries older than reminder threshold
        $reminder_days = apply_filters( 'qaf_approval_reminder_days', 3 );
        $threshold     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$reminder_days} days" ) );

        $pending_forms = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.form_id, COUNT(*) as pending_count 
                FROM {$entries_table} e
                INNER JOIN {$meta_table} m ON e.id = m.entry_id
                WHERE m.meta_key = %s 
                AND m.meta_value = %s 
                AND e.date_created < %s
                GROUP BY e.form_id",
                self::META_KEY,
                self::STATUS_PENDING,
                $threshold
            )
        );

        foreach ( $pending_forms as $row ) {
            $form = Form::get( $row->form_id );
            
            if ( ! $form || ! $form->get_setting( 'enable_approval' ) ) {
                continue;
            }

            // Check if reminders are enabled
            if ( ! $form->get_setting( 'approval_reminders' ) ) {
                continue;
            }

            // Send reminder
            $recipients = $form->get_setting( 'approval_notify_emails' ) ?: get_option( 'admin_email' );
            
            $subject = sprintf(
                /* translators: 1: Count, 2: Form title */
                __( 'Reminder: %1$d entries pending approval - %2$s', 'jexi-forms' ),
                $row->pending_count,
                $form->get_title()
            );

            $list_url = admin_url( 'admin.php?page=qaf-entries&form_id=' . $row->form_id . '&approval_status=pending' );

            $message = sprintf(
                /* translators: 1: Count, 2: Form title, 3: Days, 4: URL */
                __(
                    "This is a reminder that %1\$d entries for \"%2\$s\" have been pending approval for more than %3\$d days.\n\n" .
                    "Review pending entries:\n%4\$s",
                    'jexi-forms'
                ),
                $row->pending_count,
                $form->get_title(),
                $reminder_days,
                $list_url
            );

            wp_mail( $recipients, $subject, $message );
        }
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        register_rest_route( 'qaf/v1', '/entries/(?P<id>\d+)/approval', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'rest_get_status' ],
                'permission_callback' => [ $this, 'can_moderate' ],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ $this, 'rest_set_status' ],
                'permission_callback' => [ $this, 'can_moderate' ],
                'args'                => [
                    'status' => [
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return array_key_exists( $param, self::get_statuses() );
                        },
                    ],
                    'note' => [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                ],
            ],
        ] );

        register_rest_route( 'qaf/v1', '/entries/(?P<id>\d+)/approval/history', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_history' ],
            'permission_callback' => [ $this, 'can_moderate' ],
        ] );
    }

    /**
     * REST API: Get approval status.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response.
     */
    public function rest_get_status( \WP_REST_Request $request ): \WP_REST_Response {
        $entry_id = $request->get_param( 'id' );
        
        return new \WP_REST_Response( [
            'status'   => $this->get_status( $entry_id ),
            'statuses' => self::get_statuses(),
        ] );
    }

    /**
     * REST API: Set approval status.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response.
     */
    public function rest_set_status( \WP_REST_Request $request ): \WP_REST_Response {
        $entry_id = $request->get_param( 'id' );
        $status   = $request->get_param( 'status' );
        $note     = $request->get_param( 'note' ) ?: '';

        $result = $this->set_status( $entry_id, $status, [ 'note' => $note ] );

        if ( $result ) {
            return new \WP_REST_Response( [
                'success' => true,
                'status'  => $status,
            ] );
        }

        return new \WP_REST_Response( [
            'success' => false,
            'message' => __( 'Failed to update status.', 'jexi-forms' ),
        ], 400 );
    }

    /**
     * REST API: Get approval history.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response.
     */
    public function rest_get_history( \WP_REST_Request $request ): \WP_REST_Response {
        $entry_id = $request->get_param( 'id' );
        
        return new \WP_REST_Response( [
            'history' => $this->get_history( $entry_id ),
        ] );
    }
}
