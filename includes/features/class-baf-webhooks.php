<?php
/**
 * Webhooks Feature
 *
 * Send form entry data to external services via HTTP webhooks.
 * Supports multiple endpoints, conditional triggers, custom payloads,
 * authentication, and retry logic.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Webhooks
 *
 * Manages outgoing webhooks for form submissions.
 */
class Webhooks {

    /**
     * Maximum retry attempts.
     */
    private const MAX_RETRIES = 3;

    /**
     * Retry delay multiplier (exponential backoff).
     */
    private const RETRY_DELAY_MULTIPLIER = 2;

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
        // Trigger webhooks on submission
        add_action( 'baf_entry_created', [ $this, 'trigger_on_submit' ], 20, 2 );

        // Trigger on entry update
        add_action( 'baf_entry_updated', [ $this, 'trigger_on_update' ], 20, 2 );

        // Trigger on status change (for approval workflows)
        add_action( 'baf_entry_status_changed', [ $this, 'trigger_on_status_change' ], 20, 3 );

        // Process queued webhooks
        add_action( 'baf_process_webhook_queue', [ $this, 'process_queue' ] );

        // Schedule queue processing
        add_action( 'init', [ $this, 'schedule_queue_processing' ] );

        // Admin AJAX
        add_action( 'wp_ajax_baf_test_webhook', [ $this, 'ajax_test_webhook' ] );
        add_action( 'wp_ajax_baf_retry_webhook', [ $this, 'ajax_retry_webhook' ] );
    }

    /**
     * Trigger webhooks on form submission.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function trigger_on_submit( Entry $entry, Form $form ): void {
        $this->trigger_webhooks( $form->get_id(), $entry, 'submit' );
    }

    /**
     * Trigger webhooks on entry update.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function trigger_on_update( Entry $entry, Form $form ): void {
        $this->trigger_webhooks( $form->get_id(), $entry, 'update' );
    }

    /**
     * Trigger webhooks on status change.
     *
     * @param Entry  $entry      Entry object.
     * @param string $new_status New status.
     * @param string $old_status Old status.
     * @return void
     */
    public function trigger_on_status_change( Entry $entry, string $new_status, string $old_status ): void {
        $this->trigger_webhooks( $entry->get_form_id(), $entry, 'status_' . $new_status, [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ] );
    }

    /**
     * Trigger webhooks for a form.
     *
     * @param int    $form_id Form ID.
     * @param Entry  $entry   Entry object.
     * @param string $event   Event type.
     * @param array  $extra   Extra context data.
     * @return void
     */
    public function trigger_webhooks( int $form_id, Entry $entry, string $event, array $extra = [] ): void {
        $webhooks = $this->get_form_webhooks( $form_id );

        foreach ( $webhooks as $webhook ) {
            // Check if webhook is enabled
            if ( empty( $webhook['enabled'] ) ) {
                continue;
            }

            // Check if event matches
            $triggers = (array) ( $webhook['triggers'] ?? [ 'submit' ] );
            if ( ! in_array( $event, $triggers, true ) && ! $this->event_matches_trigger( $event, $triggers ) ) {
                continue;
            }

            // Check conditional logic
            if ( ! $this->check_conditions( $webhook, $entry ) ) {
                continue;
            }

            // Build and send/queue webhook
            $this->send_webhook( $webhook, $entry, $event, $extra );
        }
    }

    /**
     * Check if event matches any triggers (for status events).
     *
     * @param string $event    Event name.
     * @param array  $triggers Configured triggers.
     * @return bool
     */
    private function event_matches_trigger( string $event, array $triggers ): bool {
        // Handle status_* events
        if ( str_starts_with( $event, 'status_' ) ) {
            $status = substr( $event, 7 );
            return in_array( "status_change", $triggers, true ) ||
                   in_array( "status_{$status}", $triggers, true );
        }

        return false;
    }

    /**
     * Check webhook conditions.
     *
     * @param array $webhook Webhook configuration.
     * @param Entry $entry   Entry object.
     * @return bool True if conditions pass.
     */
    private function check_conditions( array $webhook, Entry $entry ): bool {
        $conditions = $webhook['conditions'] ?? [];

        if ( empty( $conditions ) ) {
            return true;
        }

        $logic = $webhook['condition_logic'] ?? 'all'; // 'all' or 'any'
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
     * Evaluate a single condition.
     *
     * @param mixed  $field_value Field value.
     * @param string $operator    Operator.
     * @param mixed  $compare     Value to compare.
     * @return bool
     */
    private function evaluate_condition( $field_value, string $operator, $compare ): bool {
        // Handle array field values
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
            case 'not_contains':
                return stripos( $field_value, $compare ) === false;
            case 'starts_with':
                return str_starts_with( strtolower( $field_value ), strtolower( $compare ) );
            case 'ends_with':
                return str_ends_with( strtolower( $field_value ), strtolower( $compare ) );
            case 'greater_than':
                return (float) $field_value > (float) $compare;
            case 'less_than':
                return (float) $field_value < (float) $compare;
            case 'is_empty':
                return empty( $field_value );
            case 'is_not_empty':
                return ! empty( $field_value );
            default:
                return false;
        }
    }

    /**
     * Send webhook.
     *
     * @param array  $webhook Webhook configuration.
     * @param Entry  $entry   Entry object.
     * @param string $event   Event type.
     * @param array  $extra   Extra context.
     * @return bool Success.
     */
    public function send_webhook( array $webhook, Entry $entry, string $event, array $extra = [] ): bool {
        $url    = $webhook['url'] ?? '';
        $method = strtoupper( $webhook['method'] ?? 'POST' );
        $async  = $webhook['async'] ?? true;

        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            $this->log_error( $webhook, $entry, 'Invalid URL' );
            return false;
        }

        // Build payload
        $payload = $this->build_payload( $webhook, $entry, $event, $extra );

        // Add to queue for async, or send immediately
        if ( $async && ! defined( 'BAF_WEBHOOK_SYNC' ) ) {
            return $this->queue_webhook( $webhook, $entry, $payload, $event );
        }

        return $this->execute_webhook( $webhook, $payload );
    }

    /**
     * Build webhook payload.
     *
     * @param array  $webhook Webhook configuration.
     * @param Entry  $entry   Entry object.
     * @param string $event   Event type.
     * @param array  $extra   Extra context.
     * @return array Payload.
     */
    private function build_payload( array $webhook, Entry $entry, string $event, array $extra = [] ): array {
        $format        = $webhook['payload_format'] ?? 'full';
        $custom_fields = $webhook['fields'] ?? [];
        $form          = Form::get( $entry->get_form_id() );

        $base_payload = [
            'event'      => $event,
            'timestamp'  => current_time( 'c' ),
            'entry_id'   => $entry->get_id(),
            'form_id'    => $entry->get_form_id(),
            'form_title' => $form ? $form->get_title() : '',
        ];

        if ( ! empty( $extra ) ) {
            $base_payload['context'] = $extra;
        }

        switch ( $format ) {
            case 'full':
                // Include all entry data
                $base_payload['entry']     = $entry->get_values();
                $base_payload['meta']      = $entry->get_all_meta();
                $base_payload['submitted'] = $entry->get_date_created();
                break;

            case 'selected':
                // Include only selected fields
                $base_payload['entry'] = [];
                foreach ( $custom_fields as $field_id ) {
                    $base_payload['entry'][ $field_id ] = $entry->get_field_value( $field_id );
                }
                break;

            case 'mapped':
                // Use custom field mapping
                $mapping = $webhook['field_mapping'] ?? [];
                $base_payload['data'] = [];
                
                foreach ( $mapping as $field_id => $key ) {
                    $base_payload['data'][ $key ] = $entry->get_field_value( $field_id );
                }
                break;

            case 'custom':
                // Use custom JSON template
                $template = $webhook['custom_payload'] ?? '{}';
                $base_payload = $this->parse_custom_payload( $template, $entry, $event, $extra );
                break;
        }

        /**
         * Filter webhook payload before sending.
         *
         * @param array  $payload Payload data.
         * @param array  $webhook Webhook configuration.
         * @param Entry  $entry   Entry object.
         * @param string $event   Event type.
         */
        return apply_filters( 'baf_webhook_payload', $base_payload, $webhook, $entry, $event );
    }

    /**
     * Parse custom payload template.
     *
     * @param string $template JSON template with merge tags.
     * @param Entry  $entry    Entry object.
     * @param string $event    Event type.
     * @param array  $extra    Extra context.
     * @return array Parsed payload.
     */
    private function parse_custom_payload( string $template, Entry $entry, string $event, array $extra ): array {
        // Replace merge tags in template
        $processed = preg_replace_callback(
            '/\{([^}]+)\}/',
            function( $matches ) use ( $entry, $event, $extra ) {
                $tag = $matches[1];

                // System tags
                switch ( $tag ) {
                    case 'event':
                        return $event;
                    case 'entry_id':
                        return $entry->get_id();
                    case 'form_id':
                        return $entry->get_form_id();
                    case 'timestamp':
                        return current_time( 'c' );
                    case 'ip':
                        return $entry->get_ip();
                }

                // Field values
                return $entry->get_field_value( $tag ) ?? '';
            },
            $template
        );

        $decoded = json_decode( $processed, true );
        
        return is_array( $decoded ) ? $decoded : [ 'data' => $processed ];
    }

    /**
     * Queue webhook for async processing.
     *
     * @param array  $webhook Webhook configuration.
     * @param Entry  $entry   Entry object.
     * @param array  $payload Payload.
     * @param string $event   Event type.
     * @return bool Success.
     */
    private function queue_webhook( array $webhook, Entry $entry, array $payload, string $event ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_webhook_queue';

        $result = $wpdb->insert(
            $table,
            [
                'webhook_id'   => $webhook['id'] ?? 0,
                'form_id'      => $entry->get_form_id(),
                'entry_id'     => $entry->get_id(),
                'url'          => $webhook['url'],
                'method'       => $webhook['method'] ?? 'POST',
                'headers'      => wp_json_encode( $this->build_headers( $webhook ) ),
                'payload'      => wp_json_encode( $payload ),
                'event'        => $event,
                'status'       => 'pending',
                'attempts'     => 0,
                'scheduled_at' => current_time( 'mysql' ),
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        // Schedule immediate processing if not already scheduled
        if ( ! wp_next_scheduled( 'baf_process_webhook_queue' ) ) {
            wp_schedule_single_event( time() + 10, 'baf_process_webhook_queue' );
        }

        return $result !== false;
    }

    /**
     * Execute webhook request.
     *
     * @param array  $webhook Webhook configuration.
     * @param array  $payload Payload.
     * @param int    $attempt Attempt number.
     * @return bool Success.
     */
    public function execute_webhook( array $webhook, array $payload, int $attempt = 1 ): bool {
        $url     = $webhook['url'];
        $method  = strtoupper( $webhook['method'] ?? 'POST' );
        $headers = $this->build_headers( $webhook );
        $timeout = $webhook['timeout'] ?? 30;

        $args = [
            'method'      => $method,
            'headers'     => $headers,
            'body'        => in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ? wp_json_encode( $payload ) : null,
            'timeout'     => $timeout,
            'sslverify'   => apply_filters( 'baf_webhook_verify_ssl', true ),
            'data_format' => 'body',
        ];

        // For GET requests, add payload as query params
        if ( $method === 'GET' && ! empty( $payload ) ) {
            $url = add_query_arg( $payload, $url );
        }

        $start    = microtime( true );
        $response = wp_remote_request( $url, $args );
        $duration = round( ( microtime( true ) - $start ) * 1000 );

        // Log the request
        $this->log_request( $webhook, $payload, $response, $duration, $attempt );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        
        // Consider 2xx as success
        return $code >= 200 && $code < 300;
    }

    /**
     * Build request headers.
     *
     * @param array $webhook Webhook configuration.
     * @return array Headers.
     */
    private function build_headers( array $webhook ): array {
        $headers = [
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
            'X-BAF-Webhook'   => '1.0',
            'X-BAF-Timestamp' => time(),
        ];

        // Add authentication
        $auth_type = $webhook['auth_type'] ?? 'none';

        switch ( $auth_type ) {
            case 'basic':
                $username = $webhook['auth_username'] ?? '';
                $password = $webhook['auth_password'] ?? '';
                if ( $username ) {
                    $headers['Authorization'] = 'Basic ' . base64_encode( "{$username}:{$password}" );
                }
                break;

            case 'bearer':
                $token = $webhook['auth_token'] ?? '';
                if ( $token ) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }
                break;

            case 'api_key':
                $key_name  = $webhook['api_key_name'] ?? 'X-API-Key';
                $key_value = $webhook['api_key_value'] ?? '';
                $location  = $webhook['api_key_location'] ?? 'header';
                
                if ( $location === 'header' && $key_value ) {
                    $headers[ $key_name ] = $key_value;
                }
                break;

            case 'signature':
                // HMAC signature for request verification
                $secret = $webhook['signing_secret'] ?? '';
                // Signature added after payload is built
                break;
        }

        // Add custom headers
        $custom = $webhook['custom_headers'] ?? [];
        foreach ( $custom as $name => $value ) {
            $headers[ $name ] = $value;
        }

        return $headers;
    }

    /**
     * Process queued webhooks.
     *
     * @return void
     */
    public function process_queue(): void {
        global $wpdb;

        $table   = $wpdb->prefix . 'baf_webhook_queue';
        $max     = 10; // Process 10 at a time
        $cutoff  = gmdate( 'Y-m-d H:i:s' );

        // Get pending webhooks ready for processing
        $pending = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                WHERE status = 'pending' AND scheduled_at <= %s 
                ORDER BY scheduled_at ASC LIMIT %d",
                $cutoff,
                $max
            )
        );

        foreach ( $pending as $item ) {
            // Mark as processing
            $wpdb->update(
                $table,
                [ 'status' => 'processing' ],
                [ 'id' => $item->id ],
                [ '%s' ],
                [ '%d' ]
            );

            // Build webhook config from queue item
            $webhook = [
                'id'     => $item->webhook_id,
                'url'    => $item->url,
                'method' => $item->method,
            ];

            $headers = json_decode( $item->headers, true ) ?: [];
            $payload = json_decode( $item->payload, true ) ?: [];

            // Execute
            $success = $this->execute_webhook( $webhook, $payload, $item->attempts + 1 );

            if ( $success ) {
                // Mark complete
                $wpdb->update(
                    $table,
                    [
                        'status'       => 'completed',
                        'completed_at' => current_time( 'mysql' ),
                        'attempts'     => $item->attempts + 1,
                    ],
                    [ 'id' => $item->id ],
                    [ '%s', '%s', '%d' ],
                    [ '%d' ]
                );
            } else {
                // Handle failure
                $attempts = $item->attempts + 1;

                if ( $attempts >= self::MAX_RETRIES ) {
                    // Mark as failed
                    $wpdb->update(
                        $table,
                        [
                            'status'   => 'failed',
                            'attempts' => $attempts,
                        ],
                        [ 'id' => $item->id ],
                        [ '%s', '%d' ],
                        [ '%d' ]
                    );

                    // Notify admin of failure
                    $this->notify_failure( $item );
                } else {
                    // Schedule retry with exponential backoff
                    $delay       = pow( self::RETRY_DELAY_MULTIPLIER, $attempts ) * 60; // minutes to seconds
                    $next_attempt = gmdate( 'Y-m-d H:i:s', time() + $delay );

                    $wpdb->update(
                        $table,
                        [
                            'status'       => 'pending',
                            'attempts'     => $attempts,
                            'scheduled_at' => $next_attempt,
                        ],
                        [ 'id' => $item->id ],
                        [ '%s', '%d', '%s' ],
                        [ '%d' ]
                    );
                }
            }
        }

        // Check if more items to process
        $remaining = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s",
                $cutoff
            )
        );

        if ( $remaining > 0 ) {
            wp_schedule_single_event( time() + 60, 'baf_process_webhook_queue' );
        }
    }

    /**
     * Schedule queue processing.
     *
     * @return void
     */
    public function schedule_queue_processing(): void {
        if ( ! wp_next_scheduled( 'baf_process_webhook_queue' ) ) {
            wp_schedule_event( time(), 'hourly', 'baf_process_webhook_queue' );
        }
    }

    /**
     * Log webhook request.
     *
     * @param array            $webhook  Webhook configuration.
     * @param array            $payload  Request payload.
     * @param array|\WP_Error  $response Response or error.
     * @param int              $duration Duration in milliseconds.
     * @param int              $attempt  Attempt number.
     * @return void
     */
    private function log_request( array $webhook, array $payload, $response, int $duration, int $attempt ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_webhook_logs';

        $is_error = is_wp_error( $response );
        $status   = $is_error ? 0 : wp_remote_retrieve_response_code( $response );
        $body     = $is_error ? $response->get_error_message() : wp_remote_retrieve_body( $response );

        $wpdb->insert(
            $table,
            [
                'webhook_id'      => $webhook['id'] ?? 0,
                'url'             => $webhook['url'],
                'method'          => $webhook['method'] ?? 'POST',
                'request_payload' => wp_json_encode( $payload ),
                'response_code'   => $status,
                'response_body'   => mb_substr( $body, 0, 10000 ), // Truncate
                'duration_ms'     => $duration,
                'attempt'         => $attempt,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' ]
        );

        // Clean old logs
        if ( mt_rand( 1, 100 ) === 1 ) {
            $this->cleanup_logs();
        }
    }

    /**
     * Log error without request.
     *
     * @param array  $webhook Webhook configuration.
     * @param Entry  $entry   Entry object.
     * @param string $error   Error message.
     * @return void
     */
    private function log_error( array $webhook, Entry $entry, string $error ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_webhook_logs';

        $wpdb->insert(
            $table,
            [
                'webhook_id'      => $webhook['id'] ?? 0,
                'url'             => $webhook['url'] ?? '',
                'method'          => $webhook['method'] ?? 'POST',
                'request_payload' => wp_json_encode( [ 'entry_id' => $entry->get_id() ] ),
                'response_code'   => 0,
                'response_body'   => $error,
                'duration_ms'     => 0,
                'attempt'         => 0,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s' ]
        );
    }

    /**
     * Get webhooks for a form.
     *
     * @param int $form_id Form ID.
     * @return array Webhooks.
     */
    public function get_form_webhooks( int $form_id ): array {
        $form = Form::get( $form_id );
        
        if ( ! $form ) {
            return [];
        }

        return $form->get_setting( 'webhooks' ) ?: [];
    }

    /**
     * Notify admin of webhook failure.
     *
     * @param object $item Queue item.
     * @return void
     */
    private function notify_failure( object $item ): void {
        $admin_email = get_option( 'admin_email' );
        $subject     = sprintf(
            /* translators: %s: site name */
            __( '[%s] Webhook Delivery Failed', 'jexi-forms' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: 1: webhook URL, 2: entry ID, 3: attempts */
            __( "A webhook failed to deliver after %3\$d attempts.\n\nURL: %1\$s\nEntry ID: %2\$d\n\nPlease check the webhook logs for details.", 'jexi-forms' ),
            $item->url,
            $item->entry_id,
            $item->attempts
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Cleanup old logs.
     *
     * @return void
     */
    private function cleanup_logs(): void {
        global $wpdb;

        $logs_table  = $wpdb->prefix . 'baf_webhook_logs';
        $queue_table = $wpdb->prefix . 'baf_webhook_queue';
        $retention   = apply_filters( 'baf_webhook_log_retention_days', 30 );
        $cutoff      = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );

        $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$logs_table} WHERE created_at < %s", $cutoff )
        );

        // Also clean completed queue items
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$queue_table} WHERE status IN ('completed', 'failed') AND created_at < %s",
                $cutoff
            )
        );
    }

    /**
     * AJAX: Test webhook.
     *
     * @return void
     */
    public function ajax_test_webhook(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $webhook = [
            'url'             => sanitize_url( $_POST['url'] ?? '' ),
            'method'          => sanitize_text_field( $_POST['method'] ?? 'POST' ),
            'auth_type'       => sanitize_text_field( $_POST['auth_type'] ?? 'none' ),
            'auth_username'   => sanitize_text_field( $_POST['auth_username'] ?? '' ),
            'auth_password'   => $_POST['auth_password'] ?? '',
            'auth_token'      => $_POST['auth_token'] ?? '',
            'api_key_name'    => sanitize_text_field( $_POST['api_key_name'] ?? '' ),
            'api_key_value'   => $_POST['api_key_value'] ?? '',
            'custom_headers'  => json_decode( wp_unslash( $_POST['custom_headers'] ?? '{}' ), true ),
        ];

        // Test payload
        $payload = [
            'test'      => true,
            'timestamp' => current_time( 'c' ),
            'form_id'   => (int) ( $_POST['form_id'] ?? 0 ),
            'message'   => 'This is a test webhook from BITS Accessible Forms (BAF).',
        ];

        $result = $this->execute_webhook( $webhook, $payload );

        if ( $result ) {
            wp_send_json_success( [ 'message' => __( 'Webhook test successful!', 'jexi-forms' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Webhook test failed. Check the logs for details.', 'jexi-forms' ) ] );
    }

    /**
     * AJAX: Retry failed webhook.
     *
     * @return void
     */
    public function ajax_retry_webhook(): void {
        check_ajax_referer( 'baf_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        global $wpdb;

        $queue_id = (int) ( $_POST['queue_id'] ?? 0 );
        $table    = $wpdb->prefix . 'baf_webhook_queue';

        // Reset for retry
        $result = $wpdb->update(
            $table,
            [
                'status'       => 'pending',
                'attempts'     => 0,
                'scheduled_at' => current_time( 'mysql' ),
            ],
            [ 
                'id'     => $queue_id,
                'status' => 'failed',
            ],
            [ '%s', '%d', '%s' ],
            [ '%d', '%s' ]
        );

        if ( $result ) {
            // Trigger processing
            wp_schedule_single_event( time() + 10, 'baf_process_webhook_queue' );
            wp_send_json_success( [ 'message' => __( 'Webhook queued for retry.', 'jexi-forms' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Could not retry webhook.', 'jexi-forms' ) ] );
    }

    /**
     * Create tables on activation.
     *
     * @return void
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Queue table
        $queue_table = $wpdb->prefix . 'baf_webhook_queue';
        $queue_sql   = "CREATE TABLE IF NOT EXISTS {$queue_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            form_id bigint(20) UNSIGNED NOT NULL,
            entry_id bigint(20) UNSIGNED NOT NULL,
            url varchar(2048) NOT NULL,
            method varchar(10) NOT NULL DEFAULT 'POST',
            headers longtext,
            payload longtext,
            event varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            scheduled_at datetime NOT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status_scheduled (status, scheduled_at),
            KEY form_id (form_id),
            KEY entry_id (entry_id)
        ) {$charset_collate};";

        // Logs table
        $logs_table = $wpdb->prefix . 'baf_webhook_logs';
        $logs_sql   = "CREATE TABLE IF NOT EXISTS {$logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            url varchar(2048) NOT NULL,
            method varchar(10) NOT NULL,
            request_payload longtext,
            response_code int(11) NOT NULL DEFAULT 0,
            response_body longtext,
            duration_ms int(11) NOT NULL DEFAULT 0,
            attempt int(11) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $queue_sql );
        dbDelta( $logs_sql );
    }
}
