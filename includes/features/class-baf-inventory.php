<?php
/**
 * Inventory Management Feature
 *
 * Track stock levels for products/options, handle out-of-stock scenarios,
 * and automatically deduct inventory on submission.
 *
 * @package BAF
 * @since 1.0.0
 */

namespace BAF\Features;

use BAF\Core\Form;
use BAF\Core\Entry;

/**
 * Class Inventory
 *
 * Manages inventory tracking for form fields.
 */
class Inventory {

    /**
     * Database table name.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'baf_inventory';
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Check inventory before submission
        add_filter( 'baf_pre_submission', [ $this, 'validate_inventory' ], 10, 2 );

        // Deduct inventory after successful submission
        add_action( 'baf_entry_created', [ $this, 'deduct_inventory' ], 10, 2 );

        // Restore inventory if entry is deleted/trashed
        add_action( 'baf_entry_deleted', [ $this, 'restore_inventory' ] );
        add_action( 'baf_entry_trashed', [ $this, 'restore_inventory' ] );

        // Filter field choices to show/hide out of stock
        add_filter( 'baf_field_choices', [ $this, 'filter_field_choices' ], 20, 3 );

        // Admin AJAX handlers
        add_action( 'wp_ajax_baf_update_inventory', [ $this, 'ajax_update_inventory' ] );
        add_action( 'wp_ajax_baf_get_inventory', [ $this, 'ajax_get_inventory' ] );

        // Frontend AJAX for real-time stock check
        add_action( 'wp_ajax_baf_check_stock', [ $this, 'ajax_check_stock' ] );
        add_action( 'wp_ajax_nopriv_baf_check_stock', [ $this, 'ajax_check_stock' ] );
    }

    /**
     * Create inventory table on plugin activation.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'baf_inventory';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id bigint(20) UNSIGNED NOT NULL,
            field_id varchar(64) NOT NULL,
            choice_value varchar(255) NOT NULL,
            stock_quantity int(11) NOT NULL DEFAULT 0,
            original_quantity int(11) NOT NULL DEFAULT 0,
            low_stock_threshold int(11) DEFAULT 5,
            allow_oversell tinyint(1) DEFAULT 0,
            out_of_stock_message text DEFAULT NULL,
            hide_when_empty tinyint(1) DEFAULT 0,
            last_updated datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY form_field_choice (form_id, field_id, choice_value(191)),
            KEY form_id (form_id),
            KEY stock_quantity (stock_quantity)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Create inventory log table
        $log_table = $wpdb->prefix . 'baf_inventory_log';
        $sql = "CREATE TABLE IF NOT EXISTS {$log_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            inventory_id bigint(20) UNSIGNED NOT NULL,
            entry_id bigint(20) UNSIGNED DEFAULT NULL,
            change_type varchar(20) NOT NULL,
            quantity_change int(11) NOT NULL,
            quantity_before int(11) NOT NULL,
            quantity_after int(11) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            note text DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY inventory_id (inventory_id),
            KEY entry_id (entry_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Get inventory for a field choice.
     *
     * @param int    $form_id     Form ID.
     * @param string $field_id    Field ID.
     * @param string $choice_value Choice value.
     * @return object|null Inventory record.
     */
    public function get_inventory( int $form_id, string $field_id, string $choice_value ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE form_id = %d AND field_id = %s AND choice_value = %s",
                $form_id,
                $field_id,
                $choice_value
            )
        );
    }

    /**
     * Get all inventory for a form.
     *
     * @param int $form_id Form ID.
     * @return array Inventory records.
     */
    public function get_form_inventory( int $form_id ): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE form_id = %d ORDER BY field_id, choice_value",
                $form_id
            )
        );
    }

    /**
     * Set inventory for a field choice.
     *
     * @param int    $form_id      Form ID.
     * @param string $field_id     Field ID.
     * @param string $choice_value Choice value.
     * @param array  $data         Inventory data.
     * @return bool Success.
     */
    public function set_inventory( int $form_id, string $field_id, string $choice_value, array $data ): bool {
        global $wpdb;

        $existing = $this->get_inventory( $form_id, $field_id, $choice_value );
        $now      = current_time( 'mysql' );

        $insert_data = [
            'form_id'             => $form_id,
            'field_id'            => $field_id,
            'choice_value'        => $choice_value,
            'stock_quantity'      => (int) ( $data['stock_quantity'] ?? 0 ),
            'original_quantity'   => (int) ( $data['original_quantity'] ?? $data['stock_quantity'] ?? 0 ),
            'low_stock_threshold' => (int) ( $data['low_stock_threshold'] ?? 5 ),
            'allow_oversell'      => (int) ( $data['allow_oversell'] ?? 0 ),
            'out_of_stock_message' => sanitize_text_field( $data['out_of_stock_message'] ?? '' ),
            'hide_when_empty'     => (int) ( $data['hide_when_empty'] ?? 0 ),
            'last_updated'        => $now,
        ];

        if ( $existing ) {
            $result = $wpdb->update(
                $this->table_name,
                $insert_data,
                [ 'id' => $existing->id ],
                [ '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s' ],
                [ '%d' ]
            );

            // Log the change
            if ( $result !== false && $existing->stock_quantity !== $insert_data['stock_quantity'] ) {
                $this->log_change(
                    $existing->id,
                    null,
                    'manual',
                    $insert_data['stock_quantity'] - $existing->stock_quantity,
                    $existing->stock_quantity,
                    $insert_data['stock_quantity'],
                    __( 'Manual inventory update', 'jexi-forms' )
                );
            }

            return $result !== false;
        }

        return $wpdb->insert(
            $this->table_name,
            $insert_data,
            [ '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s' ]
        ) !== false;
    }

    /**
     * Update stock quantity.
     *
     * @param int    $form_id      Form ID.
     * @param string $field_id     Field ID.
     * @param string $choice_value Choice value.
     * @param int    $change       Quantity change (negative to deduct).
     * @param int    $entry_id     Optional entry ID.
     * @param string $note         Optional note.
     * @return bool Success.
     */
    public function update_stock( int $form_id, string $field_id, string $choice_value, int $change, ?int $entry_id = null, string $note = '' ): bool {
        global $wpdb;

        $inventory = $this->get_inventory( $form_id, $field_id, $choice_value );

        if ( ! $inventory ) {
            return false;
        }

        $new_quantity = $inventory->stock_quantity + $change;

        // Don't allow negative unless oversell is enabled
        if ( $new_quantity < 0 && ! $inventory->allow_oversell ) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name,
            [
                'stock_quantity' => $new_quantity,
                'last_updated'   => current_time( 'mysql' ),
            ],
            [ 'id' => $inventory->id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            $change_type = $change > 0 ? 'restock' : 'deduct';
            $this->log_change(
                $inventory->id,
                $entry_id,
                $change_type,
                $change,
                $inventory->stock_quantity,
                $new_quantity,
                $note
            );

            // Check for low stock alert
            if ( $new_quantity <= $inventory->low_stock_threshold && $new_quantity > 0 ) {
                do_action( 'baf_low_stock_alert', $inventory, $new_quantity );
            }

            // Check for out of stock
            if ( $new_quantity <= 0 ) {
                do_action( 'baf_out_of_stock', $inventory );
            }

            return true;
        }

        return false;
    }

    /**
     * Log inventory change.
     *
     * @param int      $inventory_id Inventory record ID.
     * @param int|null $entry_id     Entry ID.
     * @param string   $change_type  Type of change.
     * @param int      $quantity_change Change amount.
     * @param int      $before       Quantity before.
     * @param int      $after        Quantity after.
     * @param string   $note         Note.
     * @return void
     */
    private function log_change( int $inventory_id, ?int $entry_id, string $change_type, int $quantity_change, int $before, int $after, string $note = '' ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'baf_inventory_log',
            [
                'inventory_id'    => $inventory_id,
                'entry_id'        => $entry_id,
                'change_type'     => $change_type,
                'quantity_change' => $quantity_change,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'user_id'         => get_current_user_id() ?: null,
                'note'            => $note,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ]
        );
    }

    /**
     * Validate inventory before submission.
     *
     * @param array $data Submission data.
     * @param Form  $form Form object.
     * @return array Data (may include errors).
     */
    public function validate_inventory( array $data, Form $form ): array {
        $form_id = $form->get_id();
        $errors  = [];

        // Get fields with inventory tracking
        $inventory_fields = $this->get_form_inventory( $form_id );
        
        if ( empty( $inventory_fields ) ) {
            return $data;
        }

        // Group by field
        $inventory_by_field = [];
        foreach ( $inventory_fields as $inv ) {
            $inventory_by_field[ $inv->field_id ][ $inv->choice_value ] = $inv;
        }

        // Check each field with inventory
        foreach ( $data['values'] as $field_id => $value ) {
            if ( ! isset( $inventory_by_field[ $field_id ] ) ) {
                continue;
            }

            $field_inventory = $inventory_by_field[ $field_id ];
            $values          = is_array( $value ) ? $value : [ $value ];
            $quantities      = $data['quantities'][ $field_id ] ?? [];

            foreach ( $values as $idx => $choice_value ) {
                if ( ! isset( $field_inventory[ $choice_value ] ) ) {
                    continue;
                }

                $inv      = $field_inventory[ $choice_value ];
                $quantity = $quantities[ $idx ] ?? 1;

                // Check stock
                if ( $inv->stock_quantity < $quantity && ! $inv->allow_oversell ) {
                    $field  = $form->get_field( $field_id );
                    $label  = $field ? $field->get_label() : $field_id;

                    if ( $inv->stock_quantity <= 0 ) {
                        $message = $inv->out_of_stock_message ?: sprintf(
                            /* translators: %s: Choice name */
                            __( '"%s" is out of stock.', 'jexi-forms' ),
                            $choice_value
                        );
                    } else {
                        $message = sprintf(
                            /* translators: 1: Choice name, 2: Available quantity */
                            __( 'Only %2$d of "%1$s" available.', 'jexi-forms' ),
                            $choice_value,
                            $inv->stock_quantity
                        );
                    }

                    $errors[ $field_id ] = $message;
                }
            }
        }

        if ( ! empty( $errors ) ) {
            $data['errors'] = array_merge( $data['errors'] ?? [], $errors );
        }

        return $data;
    }

    /**
     * Deduct inventory after successful submission.
     *
     * @param Entry $entry Entry object.
     * @param Form  $form  Form object.
     * @return void
     */
    public function deduct_inventory( Entry $entry, Form $form ): void {
        $form_id    = $form->get_id();
        $entry_id   = $entry->get_id();
        $entry_data = $entry->get_data();
        $quantities = $entry->get_meta( '_quantities' ) ?? [];

        // Get fields with inventory tracking
        $inventory_fields = $this->get_form_inventory( $form_id );
        
        if ( empty( $inventory_fields ) ) {
            return;
        }

        // Group by field
        $inventory_by_field = [];
        foreach ( $inventory_fields as $inv ) {
            $inventory_by_field[ $inv->field_id ][ $inv->choice_value ] = $inv;
        }

        // Deduct inventory for each field
        foreach ( $entry_data as $field_id => $value ) {
            if ( ! isset( $inventory_by_field[ $field_id ] ) ) {
                continue;
            }

            $field_inventory = $inventory_by_field[ $field_id ];
            $values          = is_array( $value ) ? $value : [ $value ];
            $field_qty       = $quantities[ $field_id ] ?? [];

            foreach ( $values as $idx => $choice_value ) {
                if ( ! isset( $field_inventory[ $choice_value ] ) ) {
                    continue;
                }

                $quantity = $field_qty[ $idx ] ?? 1;
                
                $this->update_stock(
                    $form_id,
                    $field_id,
                    $choice_value,
                    -$quantity,
                    $entry_id,
                    sprintf(
                        /* translators: %d: Entry ID */
                        __( 'Deducted for entry #%d', 'jexi-forms' ),
                        $entry_id
                    )
                );
            }
        }

        // Store what was deducted for potential restore
        $entry->update_meta( '_inventory_deducted', $this->get_deducted_items( $entry_data, $inventory_by_field, $quantities ) );
    }

    /**
     * Get deducted items for an entry.
     *
     * @param array $entry_data         Entry data.
     * @param array $inventory_by_field Inventory grouped by field.
     * @param array $quantities         Quantities.
     * @return array Deducted items.
     */
    private function get_deducted_items( array $entry_data, array $inventory_by_field, array $quantities ): array {
        $deducted = [];

        foreach ( $entry_data as $field_id => $value ) {
            if ( ! isset( $inventory_by_field[ $field_id ] ) ) {
                continue;
            }

            $values    = is_array( $value ) ? $value : [ $value ];
            $field_qty = $quantities[ $field_id ] ?? [];

            foreach ( $values as $idx => $choice_value ) {
                if ( isset( $inventory_by_field[ $field_id ][ $choice_value ] ) ) {
                    $deducted[] = [
                        'field_id'     => $field_id,
                        'choice_value' => $choice_value,
                        'quantity'     => $field_qty[ $idx ] ?? 1,
                    ];
                }
            }
        }

        return $deducted;
    }

    /**
     * Restore inventory when entry is deleted/trashed.
     *
     * @param Entry $entry Entry object.
     * @return void
     */
    public function restore_inventory( Entry $entry ): void {
        $deducted = $entry->get_meta( '_inventory_deducted' );
        
        if ( empty( $deducted ) ) {
            return;
        }

        $form_id  = $entry->get_form_id();
        $entry_id = $entry->get_id();

        foreach ( $deducted as $item ) {
            $this->update_stock(
                $form_id,
                $item['field_id'],
                $item['choice_value'],
                $item['quantity'], // Positive to restore
                $entry_id,
                sprintf(
                    /* translators: %d: Entry ID */
                    __( 'Restored from deleted entry #%d', 'jexi-forms' ),
                    $entry_id
                )
            );
        }
    }

    /**
     * Filter field choices based on inventory.
     *
     * @param array  $choices Choices array.
     * @param object $field   Field object.
     * @param Form   $form    Form object.
     * @return array Filtered choices.
     */
    public function filter_field_choices( array $choices, $field, Form $form ): array {
        $form_id  = $form->get_id();
        $field_id = $field->get_id();

        // Get inventory for this field
        $inventory = $this->get_field_inventory( $form_id, $field_id );
        
        if ( empty( $inventory ) ) {
            return $choices;
        }

        foreach ( $choices as $key => &$choice ) {
            $value = $choice['value'] ?? $choice;
            
            if ( isset( $inventory[ $value ] ) ) {
                $inv = $inventory[ $value ];

                // Hide if out of stock and configured to hide
                if ( $inv->stock_quantity <= 0 && $inv->hide_when_empty ) {
                    unset( $choices[ $key ] );
                    continue;
                }

                // Mark as out of stock
                if ( $inv->stock_quantity <= 0 && ! $inv->allow_oversell ) {
                    $choice['disabled'] = true;
                    $choice['label']   .= ' ' . ( $inv->out_of_stock_message ?: __( '(Out of Stock)', 'jexi-forms' ) );
                }

                // Show stock count if enabled
                $show_count = $field->get_property( 'show_stock_count' );
                if ( $show_count && $inv->stock_quantity > 0 ) {
                    $choice['label'] .= sprintf(
                        ' (%d %s)',
                        $inv->stock_quantity,
                        __( 'available', 'jexi-forms' )
                    );
                }

                // Add data attributes for JS
                $choice['data-stock'] = $inv->stock_quantity;
            }
        }

        return array_values( $choices ); // Re-index
    }

    /**
     * Get inventory for a field, keyed by choice value.
     *
     * @param int    $form_id  Form ID.
     * @param string $field_id Field ID.
     * @return array Inventory keyed by choice value.
     */
    public function get_field_inventory( int $form_id, string $field_id ): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE form_id = %d AND field_id = %s",
                $form_id,
                $field_id
            )
        );

        $inventory = [];
        foreach ( $results as $row ) {
            $inventory[ $row->choice_value ] = $row;
        }

        return $inventory;
    }

    /**
     * AJAX: Update inventory (admin).
     *
     * @return void
     */
    public function ajax_update_inventory(): void {
        check_ajax_referer( 'baf_inventory', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $form_id      = absint( $_POST['form_id'] ?? 0 );
        $field_id     = sanitize_key( $_POST['field_id'] ?? '' );
        $choice_value = sanitize_text_field( $_POST['choice_value'] ?? '' );
        $data         = $_POST['inventory'] ?? [];

        if ( ! $form_id || ! $field_id || ! $choice_value ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'jexi-forms' ) ] );
        }

        $result = $this->set_inventory( $form_id, $field_id, $choice_value, $data );

        if ( $result ) {
            wp_send_json_success( [
                'message'   => __( 'Inventory updated.', 'jexi-forms' ),
                'inventory' => $this->get_inventory( $form_id, $field_id, $choice_value ),
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to update inventory.', 'jexi-forms' ) ] );
        }
    }

    /**
     * AJAX: Get inventory (admin).
     *
     * @return void
     */
    public function ajax_get_inventory(): void {
        check_ajax_referer( 'baf_inventory', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'jexi-forms' ) ] );
        }

        $form_id = absint( $_GET['form_id'] ?? 0 );

        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid form.', 'jexi-forms' ) ] );
        }

        wp_send_json_success( [
            'inventory' => $this->get_form_inventory( $form_id ),
        ] );
    }

    /**
     * AJAX: Check stock (frontend).
     *
     * @return void
     */
    public function ajax_check_stock(): void {
        check_ajax_referer( 'baf_stock', 'nonce' );

        $form_id      = absint( $_GET['form_id'] ?? 0 );
        $field_id     = sanitize_key( $_GET['field_id'] ?? '' );
        $choice_value = sanitize_text_field( $_GET['choice_value'] ?? '' );

        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'jexi-forms' ) ] );
        }

        // Get all field inventory or specific choice
        if ( $field_id && $choice_value ) {
            $inventory = $this->get_inventory( $form_id, $field_id, $choice_value );
            
            wp_send_json_success( [
                'in_stock' => $inventory ? $inventory->stock_quantity > 0 || $inventory->allow_oversell : true,
                'quantity' => $inventory ? $inventory->stock_quantity : null,
            ] );
        } elseif ( $field_id ) {
            $inventory = $this->get_field_inventory( $form_id, $field_id );
            $stock     = [];
            
            foreach ( $inventory as $value => $inv ) {
                $stock[ $value ] = [
                    'in_stock' => $inv->stock_quantity > 0 || $inv->allow_oversell,
                    'quantity' => $inv->stock_quantity,
                ];
            }

            wp_send_json_success( [ 'stock' => $stock ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Missing field ID.', 'jexi-forms' ) ] );
        }
    }

    /**
     * Get inventory log for an entry or inventory item.
     *
     * @param int|null $inventory_id Inventory ID.
     * @param int|null $entry_id     Entry ID.
     * @return array Log entries.
     */
    public function get_log( ?int $inventory_id = null, ?int $entry_id = null ): array {
        global $wpdb;

        $log_table = $wpdb->prefix . 'baf_inventory_log';
        $where     = [];
        $values    = [];

        if ( $inventory_id ) {
            $where[]  = 'inventory_id = %d';
            $values[] = $inventory_id;
        }

        if ( $entry_id ) {
            $where[]  = 'entry_id = %d';
            $values[] = $entry_id;
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $sql = "SELECT * FROM {$log_table} {$where_sql} ORDER BY created_at DESC LIMIT 100";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
}
