<?php
/**
 * Bring a site's BITS Accessible Forms (BAF) data across to QUILL Accessible
 * Forms (QAF), the same plugin under its new name.
 *
 * The approach is migrate, not read in place: the old data is copied or
 * renamed to the new names once, and QAF then reads only its own names.
 * The migration is versioned: each step runs once, in order, and the last
 * step done is kept in the option qaf_migration_version, so a site updated
 * from an earlier QAF runs only the steps it has not had, and running it
 * again changes nothing.
 *
 * Step 1, the names:
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
 * Step 2, what refers to the names:
 * - Scheduled events (WP-Cron) on a baf_* hook move to the qaf_* hook, at
 *   the same time, with the same arguments and schedule.
 * - Posts (every type, revisions included) and the widget options: the
 *   block comment wp:baf/ becomes wp:qaf/ and a [baf_ shortcode becomes
 *   [qaf_. Each replacement is the same length, so a serialized widget
 *   option stays valid.
 * - Forms: a model saved by BAF kept what it could not place under
 *   extensions.baf_legacy; that key becomes extensions.qaf_legacy in the
 *   forms table's config and bfl_source.
 * - Users given a baf_* capability of their own (not through a role) get
 *   the qaf_* one as well.
 *
 * BAF never registered a post type (its forms, entries and views are in its
 * own tables), so there is no post type to move. The old names still work
 * for one release: the [baf_form] and [baf_entries] shortcodes, the
 * baf/form block on the front end, and baf/v1 for reading (QAF_Legacy_REST).
 * Uploaded files are not moved: entries store their URLs, and an old
 * upload_path setting comes across with the options. A saved form file
 * (.qaf, or .bfl or .baf from before the rename) opens as it always did:
 * its first line is the language version, not the plugin's name.
 *
 * It runs on activation, before the tables are created, and early on
 * plugins_loaded on any site whose stored version is behind (a site where
 * the files were replaced without activating again), before
 * QAF_Activator::maybe_upgrade() can make empty tables of its own and
 * before a visitor's submission can land in one. A lock keeps two requests
 * from running it at once. What each step did is kept in the option
 * qaf_migrated_from_baf.
 *
 * Going back to BAF (the rollback note in README.md, "Upgrading from BITS
 * Accessible Forms"): deactivate QAF, rename each {prefix}qaf_* table back
 * to {prefix}baf_*, run the step 2 replacements the other way (each is the
 * same length, so the SQL in the README is safe on serialized values), and
 * activate BAF. Its own baf_* options were never removed.
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

    /** What each step did, and when. */
    const DONE = 'qaf_migrated_from_baf';

    /** The last step done. */
    const VERSION_OPTION = 'qaf_migration_version';

    /** The steps there are. */
    const VERSION = 2;

    /** Held while a request migrates. */
    const LOCK = 'qaf_migration_lock';

    /** A lock older than this was left by a request that died. */
    const LOCK_SECONDS = 600;

    /**
     * Step 2's replacements in text: each the same length as what it
     * replaces, so serialized values keep their lengths.
     */
    const CONTENT_PAIRS = array(
        'wp:baf/' => 'wp:qaf/',
        '[baf_'   => '[qaf_',
        '[/baf_'  => '[/qaf_',
    );

    /** Step 2's replacement in a saved form. */
    const FORM_PAIRS = array(
        '"baf_legacy"' => '"qaf_legacy"',
    );

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
     * Step 1, the names, through a storage adapter (see QAF_Migrator_WPDB).
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
                if ( $new !== null && ! in_array( $new, array( self::DONE, self::VERSION_OPTION, self::LOCK ), true ) && ! $db->option_exists( $new ) ) {
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

    /**
     * Step 2, what refers to the names, through the same adapter.
     *
     * @param object $db Adapter, with step 1's methods and: cron_events( $start ),
     *                   move_cron( $event, $hook ), replace_in_posts( $pairs ),
     *                   replace_in_options( $start, $pairs ),
     *                   replace_in_columns( $table, $columns, $pairs ),
     *                   user_caps( $start ), add_user_cap( $user_id, $cap ).
     * @return array What was done: cron, posts, widgets, forms, user_caps.
     */
    public static function run_references( $db ) {
        $done = array( 'cron' => 0, 'posts' => 0, 'widgets' => 0, 'forms' => 0, 'user_caps' => 0 );

        foreach ( $db->cron_events( 'baf_' ) as $event ) {
            $new = self::new_name( $event['hook'], '' );
            if ( $new !== null ) {
                $db->move_cron( $event, $new );
                $done['cron']++;
            }
        }

        $done['posts']   = (int) $db->replace_in_posts( self::CONTENT_PAIRS );
        $done['widgets'] = (int) $db->replace_in_options( 'widget_', self::CONTENT_PAIRS );

        $forms = $db->prefix() . 'qaf_forms';
        if ( in_array( $forms, $db->tables_like( $forms ), true ) ) {
            $done['forms'] = (int) $db->replace_in_columns( $forms, array( 'config', 'bfl_source' ), self::FORM_PAIRS );
        }

        foreach ( $db->user_caps( 'baf_' ) as $user_id => $caps ) {
            foreach ( $caps as $cap ) {
                $new = self::new_name( $cap, '' );
                if ( $new !== null && ! in_array( $new, $caps, true ) ) {
                    $db->add_user_cap( $user_id, $new );
                    $done['user_caps']++;
                }
            }
        }
        return $done;
    }

    /**
     * The last step a site has had. A site migrated by QAF 1.1.0, which kept
     * only the report, has had step 1.
     *
     * @param mixed $version The stored version (false when there is none).
     * @param mixed $report  The stored report (false when there is none).
     * @return int
     */
    public static function stored_version( $version, $report ) {
        if ( false !== $version && null !== $version ) {
            return max( 0, (int) $version );
        }
        return false !== $report && null !== $report ? 1 : 0;
    }

    /**
     * Run each step after $from, in order.
     *
     * @param object   $db     Adapter.
     * @param int      $from   The last step the site has had.
     * @param callable $after  Called with ( step, what it did ) after each step, to store progress.
     * @return array Step => what it did.
     */
    public static function migrate( $db, $from, $after = null ) {
        $steps = array(
            1 => array( __CLASS__, 'run' ),
            2 => array( __CLASS__, 'run_references' ),
        );
        $done = array();
        foreach ( $steps as $step => $callback ) {
            if ( $step <= $from ) {
                continue;
            }
            $done[ $step ] = call_user_func( $callback, $db );
            if ( $after ) {
                call_user_func( $after, $step, $done[ $step ] );
            }
        }
        return $done;
    }

    /** On activation, and from maybe_run(): every step not yet done, once. */
    public static function on_activate() {
        global $wpdb;
        if ( ! isset( $wpdb ) ) {
            return;
        }
        if ( ! self::lock() ) {
            return; // Another request is migrating.
        }
        try {
            $from   = self::stored_version( get_option( self::VERSION_OPTION ), get_option( self::DONE ) );
            $report = get_option( self::DONE );
            $report = is_array( $report ) ? $report : array();
            self::migrate( new QAF_Migrator_WPDB( $wpdb ), $from, function ( $step, $did ) use ( &$report ) {
                if ( 1 === $step ) {
                    $report = array_merge( $report, $did ); // Where 1.1.0 kept it.
                }
                $report['steps'][ $step ] = array_merge( $did, array( 'at' => time() ) );
                $report['at']             = time();
                update_option( QAF_Migrator::DONE, $report, false );
                update_option( QAF_Migrator::VERSION_OPTION, $step, true );
            } );
        } finally {
            delete_option( self::LOCK );
        }
    }

    /**
     * Take the lock: add_option() fails when the row is there, which is
     * the database's own guarantee that one request has it.
     *
     * @return bool
     */
    private static function lock() {
        if ( add_option( self::LOCK, time(), '', 'no' ) ) {
            return true;
        }
        $since = (int) get_option( self::LOCK );
        if ( $since > 0 && time() - $since > self::LOCK_SECONDS ) {
            update_option( self::LOCK, time(), false );
            return true;
        }
        return false;
    }

    /** Early on plugins_loaded: the steps a site has not had, and nothing at all once it has them. */
    public static function maybe_run() {
        $version = get_option( self::VERSION_OPTION ); // Autoloaded: no query on a migrated site.
        if ( false !== $version && (int) $version >= self::VERSION ) {
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

    /**
     * Scheduled events on a hook starting with $start.
     *
     * @return array[] timestamp, hook, args, schedule (false for a single event).
     */
    public function cron_events( $start ) {
        $out  = array();
        $cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
        foreach ( (array) $cron as $timestamp => $hooks ) {
            foreach ( (array) $hooks as $hook => $events ) {
                if ( strpos( (string) $hook, $start ) !== 0 ) {
                    continue;
                }
                foreach ( (array) $events as $event ) {
                    $out[] = array(
                        'timestamp' => (int) $timestamp,
                        'hook'      => (string) $hook,
                        'args'      => isset( $event['args'] ) ? (array) $event['args'] : array(),
                        'schedule'  => isset( $event['schedule'] ) ? $event['schedule'] : false,
                    );
                }
            }
        }
        return $out;
    }

    public function move_cron( $event, $hook ) {
        wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );
        if ( wp_next_scheduled( $hook, $event['args'] ) ) {
            return; // QAF already scheduled its own.
        }
        if ( $event['schedule'] ) {
            wp_schedule_event( $event['timestamp'], $event['schedule'], $hook, $event['args'] );
        } else {
            wp_schedule_single_event( $event['timestamp'], $hook, $event['args'] );
        }
    }

    /** REPLACE() nested once per pair, for a column. */
    private function replaced( $column, $pairs ) {
        $sql = $column;
        foreach ( $pairs as $from => $to ) {
            $sql = $this->db->prepare( "REPLACE( $sql, %s, %s )", $from, $to );
        }
        return $sql;
    }

    /** WHERE the column holds any of the pairs' old texts. */
    private function holding( $column, $pairs ) {
        $or = array();
        foreach ( array_keys( $pairs ) as $from ) {
            $or[] = $this->db->prepare( "$column LIKE %s", '%' . $this->db->esc_like( $from ) . '%' );
        }
        return '( ' . implode( ' OR ', $or ) . ' )';
    }

    public function replace_in_posts( $pairs ) {
        $t = $this->db->posts;
        $n = (int) $this->db->query( "UPDATE $t SET post_content = " . $this->replaced( 'post_content', $pairs ) . ' WHERE ' . $this->holding( 'post_content', $pairs ) );
        if ( $n > 0 && function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        return $n;
    }

    public function replace_in_options( $start, $pairs ) {
        $t = $this->db->options;
        $n = (int) $this->db->query(
            "UPDATE $t SET option_value = " . $this->replaced( 'option_value', $pairs )
            . $this->db->prepare( ' WHERE option_name LIKE %s AND ', $this->db->esc_like( $start ) . '%' )
            . $this->holding( 'option_value', $pairs )
        );
        if ( $n > 0 && function_exists( 'wp_cache_delete' ) ) {
            wp_cache_delete( 'alloptions', 'options' );
        }
        return $n;
    }

    public function replace_in_columns( $table, $columns, $pairs ) {
        $set   = array();
        $where = array();
        foreach ( $columns as $c ) {
            $set[]   = "`$c` = " . $this->replaced( "`$c`", $pairs );
            $where[] = $this->holding( "`$c`", $pairs );
        }
        return (int) $this->db->query( 'UPDATE `' . esc_sql( $table ) . '` SET ' . implode( ', ', $set ) . ' WHERE ' . implode( ' OR ', $where ) );
    }

    /**
     * Users with a capability of their own starting with $start.
     *
     * @return array user id => that user's own capabilities.
     */
    public function user_caps( $start ) {
        $key = $this->db->get_blog_prefix() . 'capabilities';
        $ids = (array) $this->db->get_col( $this->db->prepare(
            "SELECT user_id FROM {$this->db->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
            $key,
            '%' . $this->db->esc_like( '"' . $start ) . '%'
        ) );
        $out = array();
        foreach ( $ids as $id ) {
            $caps = get_user_meta( (int) $id, $key, true );
            if ( is_array( $caps ) ) {
                $out[ (int) $id ] = array_keys( array_filter( $caps ) );
            }
        }
        return $out;
    }

    public function add_user_cap( $user_id, $cap ) {
        $user = new WP_User( (int) $user_id );
        if ( $user->exists() ) {
            $user->add_cap( $cap );
        }
    }
}
