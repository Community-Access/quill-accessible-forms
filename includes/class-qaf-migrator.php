<?php
/**
 * Bring a site's BITS Accessible Forms (BAF) data across to QUILL Accessible
 * Forms (QAF), the same plugin under its new name.
 *
 * The approach is migrate, not read in place: the old data is copied or
 * renamed to the new names once, and QAF then reads only its own names.
 *
 * - Tables: each {prefix}baf_* table is renamed to {prefix}qaf_*. When the
 *   new table already exists and is empty (the plugin was activated before
 *   the old data was found), the empty one is dropped first. When both hold
 *   rows, nothing is touched and the conflict is recorded.
 * - Options: each baf_* option (and its transients) is copied to qaf_*
 *   unless a qaf_* option of that name already exists. The old rows are left
 *   in place for one release, so going back to the old plugin still works.
 * - User, post and comment meta keys baf_* and _baf_* are renamed to qaf_*
 *   and _qaf_* unless the new key is already in use for that object.
 * - Capabilities: every role that has a baf_* capability is given the
 *   qaf_* one as well.
 *
 * Shortcodes ([baf_form], [baf_entries]) and the baf/v1 REST namespace are
 * kept as aliases elsewhere, so pages and callers need no change. Uploaded
 * files are not moved: entries store their URLs, and an old upload_path
 * setting comes across with the options.
 *
 * It runs on activation, before the tables are created, and once more on
 * admin_init when it has never run, for a site where the files were
 * replaced without activating again. What it did is kept in the option
 * qaf_migrated_from_baf.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Migrator class.
 */
class QAF_Migrator {

    const DONE = 'qaf_migrated_from_baf';

    /**
     * The new name of an old one: the first baf_ becomes qaf_, keeping a
     * leading underscore or prefix (wp_baf_forms becomes wp_qaf_forms).
     *
     * @param string $old    Old name.
     * @param string $prefix What comes before baf_ (a table prefix, "_", "_transient_").
     * @return string|null New name, or null when the name is not one of BAF's.
     */
    public static function new_name( $old, $prefix = '' ) {
        $head = $prefix . 'baf_';
        if ( strpos( $old, $head ) !== 0 ) {
            return null;
        }
        return $prefix . 'qaf_' . substr( $old, strlen( $head ) );
    }

    /**
     * Run the migration through a storage adapter (see QAF_Migrator_WPDB).
     *
     * @param object $db Adapter: prefix(), tables_like( $like ), table_rows( $t ),
     *                   rename_table( $a, $b ), drop_table( $t ), options_like( $like ),
     *                   option_exists( $name ), copy_option( $from, $to ),
     *                   meta_keys( $kind ), rename_meta( $kind, $from, $to ),
     *                   roles(), role_caps( $role ), add_cap( $role, $cap ).
     * @return array What was done: tables, options, meta, caps, conflicts.
     */
    public static function run( $db ) {
        $done = array( 'tables' => array(), 'options' => 0, 'meta' => 0, 'caps' => 0, 'conflicts' => array() );

        $prefix = $db->prefix();
        foreach ( $db->tables_like( $prefix . 'baf_' ) as $old ) {
            $new = self::new_name( $old, $prefix );
            if ( $new === null ) {
                continue;
            }
            if ( in_array( $new, $db->tables_like( $new ), true ) ) {
                if ( $db->table_rows( $new ) > 0 ) {
                    $done['conflicts'][] = sprintf( '%s and %s both hold rows; %s was left as it is.', $old, $new, $old );
                    continue;
                }
                $db->drop_table( $new );
            }
            $db->rename_table( $old, $new );
            $done['tables'][] = $old . ' => ' . $new;
        }

        foreach ( array( '', '_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_' ) as $p ) {
            foreach ( $db->options_like( $p . 'baf_' ) as $old ) {
                $new = self::new_name( $old, $p );
                if ( $new !== null && $new !== self::DONE && ! $db->option_exists( $new ) ) {
                    $db->copy_option( $old, $new );
                    $done['options']++;
                }
            }
        }

        foreach ( array( 'user', 'post', 'comment' ) as $kind ) {
            foreach ( $db->meta_keys( $kind ) as $old ) {
                $new = self::new_name( $old, '' ) ?? self::new_name( $old, '_' );
                if ( $new !== null ) {
                    $done['meta'] += $db->rename_meta( $kind, $old, $new );
                }
            }
        }

        foreach ( $db->roles() as $role ) {
            foreach ( $db->role_caps( $role ) as $cap ) {
                $new = self::new_name( $cap, '' );
                if ( $new !== null && ! in_array( $new, $db->role_caps( $role ), true ) ) {
                    $db->add_cap( $role, $new );
                    $done['caps']++;
                }
            }
        }
        return $done;
    }

    /** On activation: migrate, then remember that it ran. */
    public static function on_activate() {
        global $wpdb;
        if ( ! isset( $wpdb ) ) {
            return;
        }
        $done = self::run( new QAF_Migrator_WPDB( $wpdb ) );
        update_option( self::DONE, array_merge( $done, array( 'at' => time() ) ), false );
    }

    /** On admin_init, once, for a site that never ran on_activate(). */
    public static function maybe_run() {
        if ( get_option( self::DONE ) !== false ) {
            return;
        }
        self::on_activate();
    }
}

/**
 * The migrator's storage on a real site.
 */
class QAF_Migrator_WPDB {

    /** @var wpdb */
    private $db;

    public function __construct( $wpdb ) {
        $this->db = $wpdb;
    }

    public function prefix() {
        return $this->db->prefix;
    }

    public function tables_like( $start ) {
        return (array) $this->db->get_col( $this->db->prepare( 'SHOW TABLES LIKE %s', $this->db->esc_like( $start ) . '%' ) );
    }

    public function table_rows( $table ) {
        return (int) $this->db->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '`' );
    }

    public function rename_table( $from, $to ) {
        $this->db->query( 'RENAME TABLE `' . esc_sql( $from ) . '` TO `' . esc_sql( $to ) . '`' );
    }

    public function drop_table( $table ) {
        $this->db->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
    }

    public function options_like( $start ) {
        return (array) $this->db->get_col( $this->db->prepare( "SELECT option_name FROM {$this->db->options} WHERE option_name LIKE %s", $this->db->esc_like( $start ) . '%' ) );
    }

    public function option_exists( $name ) {
        return (bool) $this->db->get_var( $this->db->prepare( "SELECT 1 FROM {$this->db->options} WHERE option_name = %s", $name ) );
    }

    public function copy_option( $from, $to ) {
        $row = $this->db->get_row( $this->db->prepare( "SELECT option_value, autoload FROM {$this->db->options} WHERE option_name = %s", $from ) );
        if ( $row ) {
            $this->db->insert( $this->db->options, array( 'option_name' => $to, 'option_value' => $row->option_value, 'autoload' => $row->autoload ) );
        }
    }

    private function meta_table( $kind ) {
        return array( 'user' => $this->db->usermeta, 'post' => $this->db->postmeta, 'comment' => $this->db->commentmeta )[ $kind ];
    }

    private function meta_id( $kind ) {
        return $kind === 'user' ? 'user_id' : $kind . '_id';
    }

    public function meta_keys( $kind ) {
        $t = $this->meta_table( $kind );
        return (array) $this->db->get_col( $this->db->prepare(
            "SELECT DISTINCT meta_key FROM $t WHERE meta_key LIKE %s OR meta_key LIKE %s",
            $this->db->esc_like( 'baf_' ) . '%',
            $this->db->esc_like( '_baf_' ) . '%'
        ) );
    }

    public function rename_meta( $kind, $from, $to ) {
        $t  = $this->meta_table( $kind );
        $id = $this->meta_id( $kind );
        // Only where the object has no value under the new key yet.
        return (int) $this->db->query( $this->db->prepare(
            "UPDATE $t SET meta_key = %s WHERE meta_key = %s AND $id NOT IN ( SELECT o FROM ( SELECT $id AS o FROM $t WHERE meta_key = %s ) AS taken )",
            $to,
            $from,
            $to
        ) );
    }

    public function roles() {
        $roles = wp_roles();
        return array_keys( $roles->roles );
    }

    public function role_caps( $role ) {
        $r = get_role( $role );
        return $r ? array_keys( array_filter( (array) $r->capabilities ) ) : array();
    }

    public function add_cap( $role, $cap ) {
        $r = get_role( $role );
        if ( $r ) {
            $r->add_cap( $cap );
        }
    }
}
