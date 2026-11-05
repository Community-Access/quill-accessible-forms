<?php
/**
 * Entry model class
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Entry class
 * 
 * Represents a form entry and provides CRUD operations.
 */
class QAF_Entry {

    /**
     * Entry ID
     *
     * @var int
     */
    public $id = 0;

    /**
     * Form ID
     *
     * @var int
     */
    public $form_id = 0;

    /**
     * User ID
     *
     * @var int
     */
    public $user_id = 0;

    /**
     * Entry status
     *
     * @var string
     */
    public $status = 'active';

    /**
     * IP address
     *
     * @var string
     */
    public $ip_address = '';

    /**
     * User agent
     *
     * @var string
     */
    public $user_agent = '';

    /**
     * Source URL
     *
     * @var string
     */
    public $source_url = '';

    /**
     * Referrer URL
     *
     * @var string
     */
    public $referrer_url = '';

    /**
     * Payment status
     *
     * @var string
     */
    public $payment_status = '';

    /**
     * Payment amount
     *
     * @var float
     */
    public $payment_amount = 0.00;

    /**
     * Payment date
     *
     * @var string
     */
    public $payment_date = '';

    /**
     * Payment transaction ID
     *
     * @var string
     */
    public $payment_transaction_id = '';

    /**
     * Starred flag
     *
     * @var bool
     */
    public $is_starred = false;

    /**
     * Read flag
     *
     * @var bool
     */
    public $is_read = false;

    /**
     * Created timestamp
     *
     * @var string
     */
    public $created_at = '';

    /**
     * Updated timestamp
     *
     * @var string
     */
    public $updated_at = '';

    /**
     * Entry field values
     *
     * @var array
     */
    protected $field_values = array();

    /**
     * Entry notes
     *
     * @var array
     */
    protected $notes = array();

    /**
     * Associated form
     *
     * @var QAF_Form
     */
    protected $form = null;

    /**
     * Constructor
     *
     * @param int|object $entry Entry ID or database row object.
     */
    public function __construct( $entry = 0 ) {
        if ( $entry instanceof self ) {
            $this->init( (object) get_object_vars( $entry ) );
        } elseif ( is_object( $entry ) ) {
            $this->init( $entry );
        } elseif ( is_numeric( $entry ) && $entry > 0 ) {
            $this->read( $entry );
        }
    }

    /**
     * Initialize entry from object
     *
     * @param object $data Database row object.
     */
    protected function init( $data ) {
        foreach ( get_object_vars( $data ) as $key => $value ) {
            if ( property_exists( $this, $key ) ) {
                if ( in_array( $key, array( 'is_starred', 'is_read' ), true ) ) {
                    $this->$key = (bool) $value;
                } elseif ( $key === 'payment_amount' ) {
                    $this->$key = (float) $value;
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Read entry from database
     *
     * @param int $id Entry ID.
     * @return bool True if found, false otherwise.
     */
    public function read( $id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entries';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

        if ( $row ) {
            $this->init( $row );
            $this->load_field_values();
            return true;
        }

        return false;
    }

    /**
     * Load field values from database
     */
    protected function load_field_values() {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entry_meta';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_id, field_value FROM $table WHERE entry_id = %d",
            $this->id
        ) );

        $this->field_values = array();
        foreach ( $rows as $row ) {
            $value = maybe_unserialize( $row->field_value );
            $this->field_values[ $row->field_id ] = $value;
        }
    }

    /**
     * Save entry to database
     *
     * @return int|WP_Error Entry ID on success, WP_Error on failure.
     */
    public function save() {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entries';
        $now   = current_time( 'mysql' );

        $data = array(
            'form_id'                => $this->form_id,
            'user_id'                => $this->user_id ?: null,
            'status'                 => $this->status,
            'ip_address'             => $this->ip_address,
            'user_agent'             => substr( $this->user_agent, 0, 255 ),
            'source_url'             => substr( $this->source_url, 0, 2000 ),
            'referrer_url'           => substr( $this->referrer_url, 0, 2000 ),
            'payment_status'         => $this->payment_status ?: null,
            'payment_amount'         => $this->payment_amount ?: null,
            'payment_date'           => $this->payment_date ?: null,
            'payment_transaction_id' => $this->payment_transaction_id ?: null,
            'is_starred'             => $this->is_starred ? 1 : 0,
            'is_read'                => $this->is_read ? 1 : 0,
            'updated_at'             => $now,
        );

        $format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

        if ( $this->id > 0 ) {
            // Update
            $result = $wpdb->update( $table, $data, array( 'id' => $this->id ), $format, array( '%d' ) );

            if ( false === $result ) {
                return new WP_Error( 'db_error', __( 'Failed to update entry.', 'quill-accessible-forms' ) );
            }
        } else {
            // Insert
            $data['created_at'] = $now;
            $format[]           = '%s';

            $result = $wpdb->insert( $table, $data, $format );

            if ( false === $result ) {
                return new WP_Error( 'db_error', __( 'Failed to create entry.', 'quill-accessible-forms' ) );
            }

            $this->id         = $wpdb->insert_id;
            $this->created_at = $now;
        }

        $this->updated_at = $now;

        // Save field values
        $this->save_field_values();

        /**
         * Fires after an entry is saved
         *
         * @param QAF_Entry $entry The entry object.
         */
        do_action( 'qaf_entry_saved', $this );

        return $this->id;
    }

    /**
     * Save field values to database
     */
    protected function save_field_values() {
        global $wpdb;

        if ( empty( $this->field_values ) || ! $this->id ) {
            return;
        }

        $table = $wpdb->prefix . 'qaf_entry_meta';

        // Delete existing values
        $wpdb->delete( $table, array( 'entry_id' => $this->id ), array( '%d' ) );

        // Insert new values
        foreach ( $this->field_values as $field_id => $value ) {
            $wpdb->insert(
                $table,
                array(
                    'entry_id'    => $this->id,
                    'field_id'    => $field_id,
                    'field_value' => maybe_serialize( $value ),
                ),
                array( '%d', '%s', '%s' )
            );
        }
    }

    /**
     * Delete entry
     *
     * @param bool $force_delete True to permanently delete.
     * @return bool True on success, false on failure.
     */
    public function delete( $force_delete = false ) {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        if ( $force_delete ) {
            // Permanently delete
            $meta_table   = $wpdb->prefix . 'qaf_entry_meta';
            $notes_table  = $wpdb->prefix . 'qaf_entry_notes';
            $uploads_table = $wpdb->prefix . 'qaf_uploads';
            $entries_table = $wpdb->prefix . 'qaf_entries';

            // Delete associated files
            $uploads = $wpdb->get_results( $wpdb->prepare(
                "SELECT file_path FROM $uploads_table WHERE entry_id = %d",
                $this->id
            ) );

            foreach ( $uploads as $upload ) {
                if ( file_exists( $upload->file_path ) ) {
                    unlink( $upload->file_path );
                }
            }

            // Delete meta
            $wpdb->delete( $meta_table, array( 'entry_id' => $this->id ), array( '%d' ) );

            // Delete notes
            $wpdb->delete( $notes_table, array( 'entry_id' => $this->id ), array( '%d' ) );

            // Delete uploads
            $wpdb->delete( $uploads_table, array( 'entry_id' => $this->id ), array( '%d' ) );

            // Delete entry
            $result = $wpdb->delete( $entries_table, array( 'id' => $this->id ), array( '%d' ) );

            if ( $result ) {
                /**
                 * Fires after an entry is permanently deleted
                 *
                 * @param int $entry_id The deleted entry ID.
                 */
                do_action( 'qaf_entry_deleted', $this->id );
                return true;
            }
        } else {
            // Move to trash
            $this->status = 'trash';
            return ! is_wp_error( $this->save() );
        }

        return false;
    }

    /**
     * Restore entry from trash
     *
     * @return bool True on success.
     */
    public function restore() {
        if ( $this->status !== 'trash' ) {
            return false;
        }

        $this->status = 'active';
        return ! is_wp_error( $this->save() );
    }

    /**
     * Set field value
     *
     * @param string $field_id Field ID.
     * @param mixed  $value    Field value.
     */
    public function set_field_value( $field_id, $value ) {
        $this->field_values[ $field_id ] = $value;
    }

    /**
     * Get field value
     *
     * @param string $field_id Field ID.
     * @return mixed Field value or null if not found.
     */
    public function get_field_value( $field_id ) {
        return isset( $this->field_values[ $field_id ] ) ? $this->field_values[ $field_id ] : null;
    }

    /**
     * Get all field values
     *
     * @return array All field values.
     */
    public function get_field_values() {
        return $this->field_values;
    }

    /**
     * Set all field values
     *
     * @param array $values Field values keyed by field ID.
     */
    public function set_field_values( $values ) {
        $this->field_values = $values;
    }

    /**
     * Get associated form
     *
     * @return QAF_Form|null Form object or null.
     */
    public function get_form() {
        if ( $this->form === null && $this->form_id ) {
            $this->form = new QAF_Form( $this->form_id );
        }

        return $this->form;
    }

    /**
     * Add note to entry
     *
     * @param string $content   Note content.
     * @param string $note_type Note type (note, email, status_change, edit).
     * @return int|false Note ID on success, false on failure.
     */
    public function add_note( $content, $note_type = 'note' ) {
        global $wpdb;

        if ( ! $this->id ) {
            return false;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'qaf_entry_notes',
            array(
                'entry_id'   => $this->id,
                'user_id'    => get_current_user_id(),
                'note_type'  => $note_type,
                'content'    => $content,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get entry notes
     *
     * @return array Array of note objects.
     */
    public function get_notes() {
        global $wpdb;

        if ( ! $this->id ) {
            return array();
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT n.*, u.display_name as user_name
             FROM {$wpdb->prefix}qaf_entry_notes n
             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
             WHERE n.entry_id = %d
             ORDER BY n.created_at DESC",
            $this->id
        ) );
    }

    /**
     * Toggle starred status
     *
     * @return bool New starred status.
     */
    public function toggle_star() {
        $this->is_starred = ! $this->is_starred;
        $this->save();
        return $this->is_starred;
    }

    /**
     * Mark as read
     *
     * @return bool True on success.
     */
    public function mark_read() {
        if ( $this->is_read ) {
            return true;
        }

        $this->is_read = true;
        return ! is_wp_error( $this->save() );
    }

    /**
     * Mark as unread
     *
     * @return bool True on success.
     */
    public function mark_unread() {
        if ( ! $this->is_read ) {
            return true;
        }

        $this->is_read = false;
        return ! is_wp_error( $this->save() );
    }

    /**
     * Get formatted field value for display
     *
     * @param string $field_id Field ID.
     * @return string Formatted value.
     */
    public function get_formatted_value( $field_id ) {
        $value = $this->get_field_value( $field_id );
        $form  = $this->get_form();

        if ( $form ) {
            $field_config = $form->get_field( $field_id );

            if ( $field_config ) {
                // Get field instance and format
                $field_registry = QAF_Field_Registry::get_instance();
                $field          = $field_registry->create_field( $field_config, $form->id );

                if ( $field ) {
                    return $field->format_display( $value );
                }
            }
        }

        // Default formatting
        if ( is_array( $value ) ) {
            return implode( ', ', $value );
        }

        return esc_html( $value );
    }

    /**
     * Export entry to array
     *
     * @return array Entry data.
     */
    public function to_array() {
        $data = array(
            'id'                     => $this->id,
            'form_id'                => $this->form_id,
            'user_id'                => $this->user_id,
            'status'                 => $this->status,
            'ip_address'             => $this->ip_address,
            'source_url'             => $this->source_url,
            'payment_status'         => $this->payment_status,
            'payment_amount'         => $this->payment_amount,
            'payment_transaction_id' => $this->payment_transaction_id,
            'is_starred'             => $this->is_starred,
            'is_read'                => $this->is_read,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'fields'                 => $this->field_values,
        );

        return $data;
    }

    /**
     * Get entries for a form
     *
     * @param int   $form_id Form ID.
     * @param array $args    Query arguments.
     * @return array Array of QAF_Entry objects.
     */
    public static function get_for_form( $form_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'      => 'active',
            'orderby'     => 'created_at',
            'order'       => 'DESC',
            'limit'       => 20,
            'offset'      => 0,
            'search'      => '',
            'is_starred'  => null,
            'is_read'     => null,
            'date_from'   => '',
            'date_to'     => '',
        );

        $args  = wp_parse_args( $args, $defaults );
        $table = $wpdb->prefix . 'qaf_entries';

        $where  = array( 'form_id = %d' );
        $params = array( $form_id );

        if ( $args['status'] !== '*' ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if ( $args['is_starred'] !== null ) {
            $where[]  = 'is_starred = %d';
            $params[] = $args['is_starred'] ? 1 : 0;
        }

        if ( $args['is_read'] !== null ) {
            $where[]  = 'is_read = %d';
            $params[] = $args['is_read'] ? 1 : 0;
        }

        if ( $args['date_from'] ) {
            $where[]  = 'created_at >= %s';
            $params[] = $args['date_from'];
        }

        if ( $args['date_to'] ) {
            $where[]  = 'created_at <= %s';
            $params[] = $args['date_to'];
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'id', 'created_at', 'updated_at', 'is_starred', 'is_read' );
        if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
            $args['orderby'] = 'created_at';
        }

        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY {$args['orderby']} $order";

        if ( $args['limit'] > 0 ) {
            $sql     .= ' LIMIT %d OFFSET %d';
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        $entries = array();
        foreach ( $rows as $row ) {
            $entries[] = new self( $row );
        }

        return $entries;
    }

    /**
     * Count entries for a form
     *
     * @param int    $form_id Form ID.
     * @param string $status  Optional status filter.
     * @return int Number of entries.
     */
    public static function count_for_form( $form_id, $status = '' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'qaf_entries';

        if ( $status ) {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE form_id = %d AND status = %s",
                $form_id,
                $status
            ) );
        } else {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE form_id = %d",
                $form_id
            ) );
        }

        return (int) $count;
    }

    /**
     * Get entry counts by status for a form
     *
     * @param int $form_id Form ID.
     * @return array Counts keyed by status.
     */
    public static function get_counts_by_status( $form_id ) {
        global $wpdb;

        $table  = $wpdb->prefix . 'qaf_entries';
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM $table WHERE form_id = %d GROUP BY status",
            $form_id
        ) );

        $counts = array(
            'active'   => 0,
            'trash'    => 0,
            'spam'     => 0,
            'pending'  => 0,
            'approved' => 0,
            'rejected' => 0,
            'total'    => 0,
        );

        foreach ( $results as $row ) {
            $counts[ $row->status ] = (int) $row->count;
            $counts['total']       += (int) $row->count;
        }

        return $counts;
    }
}
