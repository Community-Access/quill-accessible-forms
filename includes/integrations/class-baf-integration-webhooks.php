<?php
/**
 * Webhooks Integration
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Integration_Webhooks class
 * 
 * Handles webhook notifications for form events.
 */
class BAF_Integration_Webhooks extends BAF_Integration_Abstract {

    /**
     * Integration ID
     *
     * @var string
     */
    protected $id = 'webhooks';

    /**
     * Maximum retries
     *
     * @var int
     */
    const MAX_RETRIES = 3;

    /**
     * Request timeout (seconds)
     *
     * @var int
     */
    const TIMEOUT = 15;

    /**
     * Process entry - send webhook
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param array     $config Integration config.
     */
    public function process( $entry, $form, $config ) {
        $url = $config['url'] ?? '';
        
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            throw new Exception( __( 'Invalid webhook URL.', 'bits-accessible-forms' ) );
        }

        $event = 'entry.created';
        $payload = $this->build_payload( $entry, $form, $event, $config );
        $headers = $this->build_headers( $payload, $config );

        $this->send_webhook( $url, $payload, $headers, $config );
    }

    /**
     * Send webhook for custom event
     *
     * @param string $event   Event name.
     * @param array  $data    Event data.
     * @param array  $config  Webhook config.
     */
    public function send_event( $event, $data, $config ) {
        $url = $config['url'] ?? '';
        
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return;
        }

        $payload = array(
            'event'      => $event,
            'timestamp'  => gmdate( 'c' ),
            'webhook_id' => $config['id'] ?? '',
            'data'       => $data,
        );

        $headers = $this->build_headers( $payload, $config );
        $this->send_webhook( $url, $payload, $headers, $config );
    }

    /**
     * Build webhook payload
     *
     * @param BAF_Entry $entry  Entry object.
     * @param BAF_Form  $form   Form object.
     * @param string    $event  Event name.
     * @param array     $config Integration config.
     * @return array Payload.
     */
    private function build_payload( $entry, $form, $event, $config ) {
        $include_meta = $config['include_meta'] ?? true;
        $field_filter = $config['fields'] ?? array();

        $field_values = $entry->get_field_values();

        // Filter fields if specified
        if ( ! empty( $field_filter ) ) {
            $field_values = array_intersect_key( $field_values, array_flip( $field_filter ) );
        }

        $payload = array(
            'event'      => $event,
            'timestamp'  => gmdate( 'c' ),
            'webhook_id' => $config['id'] ?? '',
            'form'       => array(
                'id'    => $form->id,
                'title' => $form->title,
                'slug'  => $form->slug,
            ),
            'entry'      => array(
                'id'           => $entry->id,
                'status'       => $entry->status,
                'created_at'   => $entry->created_at,
                'field_values' => $field_values,
            ),
        );

        if ( $include_meta ) {
            $payload['entry']['meta'] = array(
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
                'source_url' => $entry->source_url,
                'user_id'    => $entry->user_id,
            );
        }

        /**
         * Filter webhook payload
         *
         * @param array     $payload Webhook payload.
         * @param BAF_Entry $entry   Entry object.
         * @param BAF_Form  $form    Form object.
         * @param string    $event   Event name.
         */
        return apply_filters( 'baf_webhook_payload', $payload, $entry, $form, $event );
    }

    /**
     * Build request headers
     *
     * @param array $payload Payload data.
     * @param array $config  Webhook config.
     * @return array Headers.
     */
    private function build_headers( $payload, $config ) {
        $headers = array(
            'Content-Type'     => 'application/json',
            'User-Agent'       => 'BAF-Webhook/1.0',
            'X-BAF-Event'      => $payload['event'],
            'X-BAF-Delivery'   => wp_generate_uuid4(),
            'X-BAF-Timestamp'  => time(),
        );

        // Add signature if secret is configured
        $secret = $config['secret'] ?? '';
        if ( ! empty( $secret ) ) {
            $signature = $this->generate_signature( $payload, $secret );
            $headers['X-BAF-Signature'] = $signature;
            $headers['X-BAF-Signature-256'] = 'sha256=' . $signature;
        }

        // Add custom headers
        $custom_headers = $config['headers'] ?? array();
        foreach ( $custom_headers as $name => $value ) {
            // Sanitize header name
            $name = preg_replace( '/[^a-zA-Z0-9\-]/', '', $name );
            if ( ! empty( $name ) ) {
                $headers[ $name ] = $value;
            }
        }

        // Add authentication
        $auth_type = $config['auth_type'] ?? '';
        switch ( $auth_type ) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . ( $config['auth_token'] ?? '' );
                break;
            case 'basic':
                $credentials = base64_encode( ( $config['auth_username'] ?? '' ) . ':' . ( $config['auth_password'] ?? '' ) );
                $headers['Authorization'] = 'Basic ' . $credentials;
                break;
            case 'api_key':
                $key_header = $config['api_key_header'] ?? 'X-API-Key';
                $headers[ $key_header ] = $config['api_key_value'] ?? '';
                break;
        }

        return $headers;
    }

    /**
     * Generate HMAC signature
     *
     * @param array  $payload Payload data.
     * @param string $secret  Secret key.
     * @return string Signature.
     */
    private function generate_signature( $payload, $secret ) {
        return hash_hmac( 'sha256', wp_json_encode( $payload ), $secret );
    }

    /**
     * Verify webhook signature (for incoming webhooks)
     *
     * @param string $payload   Raw payload.
     * @param string $signature Signature from header.
     * @param string $secret    Secret key.
     * @return bool True if valid.
     */
    public function verify_signature( $payload, $signature, $secret ) {
        $expected = hash_hmac( 'sha256', $payload, $secret );
        return hash_equals( $expected, $signature );
    }

    /**
     * Send webhook request
     *
     * @param string $url     Webhook URL.
     * @param array  $payload Payload data.
     * @param array  $headers Request headers.
     * @param array  $config  Webhook config.
     * @throws Exception On failure.
     */
    private function send_webhook( $url, $payload, $headers, $config ) {
        $method = strtoupper( $config['method'] ?? 'POST' );
        $retry_enabled = $config['retry'] ?? true;
        $async = $config['async'] ?? false;

        // Schedule async webhook
        if ( $async ) {
            $this->schedule_async_webhook( $url, $payload, $headers, $method );
            return;
        }

        $attempts = 0;
        $max_attempts = $retry_enabled ? self::MAX_RETRIES : 1;
        $last_error = null;

        while ( $attempts < $max_attempts ) {
            $attempts++;

            $response = wp_remote_request( $url, array(
                'method'    => $method,
                'headers'   => $headers,
                'body'      => wp_json_encode( $payload ),
                'timeout'   => self::TIMEOUT,
                'sslverify' => true,
            ) );

            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                $this->log_delivery( $url, $payload, null, $last_error, $attempts );
                
                if ( $attempts < $max_attempts ) {
                    sleep( pow( 2, $attempts - 1 ) ); // Exponential backoff
                }
                continue;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            // Log delivery
            $this->log_delivery( $url, $payload, $status_code, $body, $attempts );

            // Success: 2xx status code
            if ( $status_code >= 200 && $status_code < 300 ) {
                return;
            }

            // Retry on 5xx errors or specific 4xx errors
            if ( $status_code >= 500 || in_array( $status_code, array( 408, 429 ), true ) ) {
                $last_error = "HTTP {$status_code}: {$body}";
                
                if ( $attempts < $max_attempts ) {
                    // Check for Retry-After header
                    $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
                    $wait_time = $retry_after ? min( (int) $retry_after, 60 ) : pow( 2, $attempts - 1 );
                    sleep( $wait_time );
                }
                continue;
            }

            // Non-retryable error
            throw new Exception( sprintf(
                __( 'Webhook failed with status %d: %s', 'bits-accessible-forms' ),
                $status_code,
                $body
            ) );
        }

        // All retries exhausted
        throw new Exception( sprintf(
            __( 'Webhook failed after %d attempts: %s', 'bits-accessible-forms' ),
            $max_attempts,
            $last_error
        ) );
    }

    /**
     * Schedule async webhook via Action Scheduler or WP Cron
     *
     * @param string $url     Webhook URL.
     * @param array  $payload Payload data.
     * @param array  $headers Request headers.
     * @param string $method  HTTP method.
     */
    private function schedule_async_webhook( $url, $payload, $headers, $method ) {
        $args = array( $url, $payload, $headers, $method );

        if ( function_exists( 'as_schedule_single_action' ) ) {
            // Use Action Scheduler if available
            as_schedule_single_action( time(), 'baf_send_async_webhook', $args, 'baf-webhooks' );
        } else {
            // Fall back to WP Cron
            wp_schedule_single_event( time(), 'baf_send_async_webhook', $args );
        }
    }

    /**
     * Log webhook delivery
     *
     * @param string   $url         Webhook URL.
     * @param array    $payload     Payload data.
     * @param int|null $status_code HTTP status code.
     * @param string   $response    Response body or error message.
     * @param int      $attempt     Attempt number.
     */
    private function log_delivery( $url, $payload, $status_code, $response, $attempt ) {
        global $wpdb;

        $log_enabled = get_option( 'baf_webhook_logging', true );
        
        if ( ! $log_enabled ) {
            return;
        }

        $table = $wpdb->prefix . 'baf_webhook_logs';
        
        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $wpdb->insert( $table, array(
            'url'         => $url,
            'event'       => $payload['event'] ?? '',
            'payload'     => wp_json_encode( $payload ),
            'status_code' => $status_code,
            'response'    => is_string( $response ) ? substr( $response, 0, 65535 ) : '',
            'attempt'     => $attempt,
            'created_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' ) );

        // Cleanup old logs (keep last 1000)
        $this->cleanup_old_logs( $table );
    }

    /**
     * Cleanup old webhook logs
     *
     * @param string $table Table name.
     */
    private function cleanup_old_logs( $table ) {
        global $wpdb;

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        
        if ( $count > 1000 ) {
            $delete_count = $count - 1000;
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
                $delete_count
            ) );
        }
    }

    /**
     * Get recent webhook logs
     *
     * @param int   $limit  Number of logs.
     * @param array $filter Optional filters.
     * @return array Logs.
     */
    public function get_logs( $limit = 50, $filter = array() ) {
        global $wpdb;

        $table = $wpdb->prefix . 'baf_webhook_logs';
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return array();
        }

        $where = '1=1';
        $params = array();

        if ( ! empty( $filter['url'] ) ) {
            $where .= ' AND url LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $filter['url'] ) . '%';
        }

        if ( ! empty( $filter['event'] ) ) {
            $where .= ' AND event = %s';
            $params[] = $filter['event'];
        }

        if ( ! empty( $filter['status'] ) ) {
            if ( $filter['status'] === 'success' ) {
                $where .= ' AND status_code >= 200 AND status_code < 300';
            } elseif ( $filter['status'] === 'error' ) {
                $where .= ' AND (status_code IS NULL OR status_code < 200 OR status_code >= 300)';
            }
        }

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;

        if ( count( $params ) > 1 ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }

        return $wpdb->get_results( $wpdb->prepare( $sql, $limit ) );
    }

    /**
     * Test webhook endpoint
     *
     * @param string $url    Webhook URL.
     * @param array  $config Webhook config.
     * @return true|WP_Error True or error.
     */
    public function test_webhook( $url, $config = array() ) {
        $payload = array(
            'event'     => 'test',
            'timestamp' => gmdate( 'c' ),
            'message'   => 'This is a test webhook from BITS Accessible Forms.',
        );

        $headers = $this->build_headers( $payload, $config );

        try {
            $response = wp_remote_post( $url, array(
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
                'timeout' => self::TIMEOUT,
            ) );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code( $response );

            if ( $status_code >= 200 && $status_code < 300 ) {
                return true;
            }

            return new WP_Error(
                'webhook_error',
                sprintf( __( 'Webhook returned status %d', 'bits-accessible-forms' ), $status_code )
            );
        } catch ( Exception $e ) {
            return new WP_Error( 'webhook_error', $e->getMessage() );
        }
    }

    /**
     * Get settings fields
     *
     * @return array Settings fields.
     */
    public function get_settings_fields() {
        return array(
            'logging' => array(
                'type'        => 'checkbox',
                'label'       => __( 'Enable Logging', 'bits-accessible-forms' ),
                'description' => __( 'Log webhook deliveries for debugging', 'bits-accessible-forms' ),
                'default'     => true,
            ),
            'default_timeout' => array(
                'type'        => 'number',
                'label'       => __( 'Default Timeout', 'bits-accessible-forms' ),
                'description' => __( 'Timeout in seconds for webhook requests', 'bits-accessible-forms' ),
                'default'     => 15,
                'min'         => 5,
                'max'         => 60,
            ),
            'retry_failed' => array(
                'type'        => 'checkbox',
                'label'       => __( 'Retry Failed Webhooks', 'bits-accessible-forms' ),
                'description' => __( 'Automatically retry failed webhook deliveries', 'bits-accessible-forms' ),
                'default'     => true,
            ),
        );
    }

    /**
     * Create webhook logs table
     */
    public static function create_logs_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'baf_webhook_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            event varchar(100) NOT NULL,
            payload longtext NOT NULL,
            status_code smallint(3) DEFAULT NULL,
            response text DEFAULT NULL,
            attempt tinyint(2) DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event (event),
            KEY status_code (status_code),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

// Register async webhook handler
add_action( 'baf_send_async_webhook', function( $url, $payload, $headers, $method ) {
    wp_remote_request( $url, array(
        'method'    => $method,
        'headers'   => $headers,
        'body'      => wp_json_encode( $payload ),
        'timeout'   => BAF_Integration_Webhooks::TIMEOUT,
        'sslverify' => true,
    ) );
}, 10, 4 );
