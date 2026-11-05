<?php
/**
 * A site that ran BITS Accessible Forms keeps its data and its callers under
 * QUILL Accessible Forms: the migrator (tables, options, meta, capabilities)
 * and the read-only baf/v1 REST alias.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/class-qaf-migrator.php';
require_once QAF_PLUGIN_DIR . 'includes/api/class-qaf-legacy-rest.php';

/** A site's storage, in memory, behind the migrator's adapter. */
class QAF_Fake_Migration_Store {
    public $tables = array();  // name => rows
    public $options = array(); // name => value
    public $meta = array( 'user' => array(), 'post' => array(), 'comment' => array() ); // kind => list of array( id, key )
    public $caps = array();    // role => caps
    public $log = array();

    public function prefix() { return 'wp_'; }
    public function tables_like( $start ) {
        return array_values( array_filter( array_keys( $this->tables ), function ( $t ) use ( $start ) { return strpos( $t, $start ) === 0; } ) );
    }
    public function table_rows( $t ) { return $this->tables[ $t ]; }
    public function rename_table( $a, $b ) { $this->tables[ $b ] = $this->tables[ $a ]; unset( $this->tables[ $a ] ); $this->log[] = "rename $a $b"; }
    public function drop_table( $t ) { unset( $this->tables[ $t ] ); $this->log[] = "drop $t"; }
    public function options_like( $start ) {
        return array_values( array_filter( array_keys( $this->options ), function ( $o ) use ( $start ) { return strpos( $o, $start ) === 0; } ) );
    }
    public function option_exists( $n ) { return array_key_exists( $n, $this->options ); }
    public function copy_option( $a, $b ) { $this->options[ $b ] = $this->options[ $a ]; }
    public function meta_keys( $kind ) {
        return array_values( array_unique( array_filter( array_column( $this->meta[ $kind ], 1 ), function ( $k ) { return preg_match( '/^_?baf_/', $k ); } ) ) );
    }
    public function rename_meta( $kind, $from, $to ) {
        $taken = array();
        foreach ( $this->meta[ $kind ] as $row ) {
            if ( $row[1] === $to ) { $taken[ $row[0] ] = true; }
        }
        $n = 0;
        foreach ( $this->meta[ $kind ] as $i => $row ) {
            if ( $row[1] === $from && ! isset( $taken[ $row[0] ] ) ) {
                $this->meta[ $kind ][ $i ][1] = $to;
                $n++;
            }
        }
        return $n;
    }
    public function roles() { return array_keys( $this->caps ); }
    public function role_caps( $r ) { return $this->caps[ $r ]; }
    public function add_cap( $r, $c ) { $this->caps[ $r ][] = $c; }
}

class QAF_Rename_Compat_Test extends QAF_Unit_Test_Case {

    private static function site() {
        $s = new QAF_Fake_Migration_Store();
        $s->tables  = array( 'wp_baf_forms' => 3, 'wp_baf_entries' => 40, 'wp_qaf_forms' => 0, 'wp_posts' => 9 );
        $s->options = array(
            'baf_db_version'                   => '1.0.0',
            'baf_settings'                     => array( 'upload_path' => 'baf-uploads' ),
            'baf_sequence_3'                   => 41,
            '_transient_baf_form_cache'        => 'x',
            '_transient_timeout_baf_form_cache' => 99,
            'qaf_db_version'                   => '1.1.0',
            'blogname'                         => 'Site',
        );
        $s->meta = array(
            'user'    => array( array( 1, 'baf_recent' ) ),
            'post'    => array( array( 7, '_baf_form_id' ), array( 8, '_baf_form_id' ), array( 8, '_qaf_form_id' ), array( 9, 'other' ) ),
            'comment' => array(),
        );
        $s->caps = array( 'administrator' => array( 'manage_options', 'baf_manage_forms', 'baf_view_entries' ), 'shop_manager' => array( 'baf_view_entries' ), 'subscriber' => array( 'read' ) );
        return $s;
    }

    public function test_new_names_keep_their_prefix() {
        $this->assertSame( 'wp_qaf_forms', QAF_Migrator::new_name( 'wp_baf_forms', 'wp_' ) );
        $this->assertSame( '_qaf_form_id', QAF_Migrator::new_name( '_baf_form_id', '_' ) );
        $this->assertSame( '_transient_qaf_x', QAF_Migrator::new_name( '_transient_baf_x', '_transient_' ) );
        $this->assertNull( QAF_Migrator::new_name( 'wp_posts', 'wp_' ) );
        $this->assertNull( QAF_Migrator::new_name( 'bafx', '' ) );
    }

    public function test_tables_are_renamed_and_an_empty_new_one_gives_way() {
        $s    = self::site();
        $done = QAF_Migrator::run( $s );
        $tables = $s->tables;
        ksort( $tables );
        $this->assertSame( array( 'wp_posts' => 9, 'wp_qaf_entries' => 40, 'wp_qaf_forms' => 3 ), $tables );
        $this->assertContains( 'drop wp_qaf_forms', $s->log, 'the empty table the activator made gave way to the old one' );
        $this->assertSame( array( 'wp_baf_forms => wp_qaf_forms', 'wp_baf_entries => wp_qaf_entries' ), $done['tables'] );
        $this->assertSame( array(), $done['conflicts'] );
    }

    public function test_two_tables_with_rows_are_left_and_reported() {
        $s = self::site();
        $s->tables['wp_qaf_forms'] = 2;
        $done = QAF_Migrator::run( $s );
        $this->assertSame( 3, $s->tables['wp_baf_forms'], 'nothing was dropped' );
        $this->assertSame( 2, $s->tables['wp_qaf_forms'] );
        $this->assertCount( 1, $done['conflicts'] );
    }

    public function test_options_are_copied_never_overwritten_and_old_ones_kept() {
        $s    = self::site();
        $done = QAF_Migrator::run( $s );
        $this->assertSame( array( 'upload_path' => 'baf-uploads' ), $s->options['qaf_settings'], 'a setting comes across, uploads folder and all' );
        $this->assertSame( 41, $s->options['qaf_sequence_3'], 'names made at run time come across too' );
        $this->assertSame( 'x', $s->options['_transient_qaf_form_cache'] );
        $this->assertSame( 99, $s->options['_transient_timeout_qaf_form_cache'] );
        $this->assertSame( '1.1.0', $s->options['qaf_db_version'], 'an existing qaf_ option is not overwritten' );
        $this->assertSame( '1.0.0', $s->options['baf_db_version'], 'the old option stays, for one release' );
        $this->assertSame( 4, $done['options'] );
    }

    public function test_meta_keys_move_unless_the_object_has_the_new_one() {
        $s    = self::site();
        $done = QAF_Migrator::run( $s );
        $this->assertSame( array( array( 1, 'qaf_recent' ) ), $s->meta['user'] );
        $this->assertSame( array( 7, '_qaf_form_id' ), $s->meta['post'][0] );
        $this->assertSame( array( 8, '_baf_form_id' ), $s->meta['post'][1], 'post 8 already had the new key' );
        $this->assertSame( 2, $done['meta'] );
    }

    public function test_every_role_keeps_what_it_could_do() {
        $s = self::site();
        QAF_Migrator::run( $s );
        $this->assertContains( 'qaf_manage_forms', $s->caps['administrator'] );
        $this->assertContains( 'qaf_view_entries', $s->caps['shop_manager'], 'a custom role too' );
        $this->assertSame( array( 'read' ), $s->caps['subscriber'] );
    }

    public function test_running_twice_changes_nothing_more() {
        $s = self::site();
        QAF_Migrator::run( $s );
        $after = array( $s->tables, $s->options, $s->meta, $s->caps );
        $done  = QAF_Migrator::run( $s );
        $this->assertSame( $after, array( $s->tables, $s->options, $s->meta, $s->caps ) );
        $this->assertSame( array(), $done['tables'] );
        $this->assertSame( 0, $done['options'] + $done['meta'] + $done['caps'] );
    }

    public function test_baf_v1_answers_get_only() {
        $cb    = function () { return 'ok'; };
        $perm  = function () { return true; };
        $routes = array(
            '/qaf/v1'                       => array( array( 'methods' => array( 'GET' => true ), 'callback' => $cb ) ),
            '/qaf/v1/forms'                 => array(
                array( 'methods' => array( 'GET' => true ), 'callback' => $cb, 'permission_callback' => $perm, 'args' => array( 'page' => array() ) ),
                array( 'methods' => array( 'POST' => true ), 'callback' => $cb, 'permission_callback' => $perm ),
            ),
            '/qaf/v1/forms/(?P<id>[\d]+)'   => array(
                array( 'methods' => array( 'GET' => true ), 'callback' => $cb, 'permission_callback' => $perm ),
                array( 'methods' => array( 'POST' => true, 'PUT' => true, 'PATCH' => true ), 'callback' => $cb ),
                array( 'methods' => array( 'DELETE' => true ), 'callback' => $cb ),
            ),
            '/qaf/v1/submit/(?P<form_id>[\d]+)' => array( array( 'methods' => array( 'POST' => true ), 'callback' => $cb ) ),
            '/qaf/v1/bfl/parse'             => array( array( 'methods' => 'POST', 'callback' => $cb ) ),
            '/other/v2/thing'               => array( array( 'methods' => array( 'GET' => true ), 'callback' => $cb ) ),
        );
        $aliases = QAF_Legacy_REST::aliases( $routes );
        $this->assertSame( array( '/forms', '/forms/(?P<id>[\d]+)' ), array_keys( $aliases ), 'writes and other namespaces are not aliased' );
        foreach ( $aliases as $route => $endpoints ) {
            $this->assertCount( 1, $endpoints, $route );
            $this->assertSame( 'GET', $endpoints[0]['methods'] );
            $this->assertSame( $perm, $endpoints[0]['permission_callback'], $route . ': the same permission check' );
        }
        $this->assertSame( array( 'page' => array() ), $aliases['/forms'][0]['args'] );
    }

    public function test_the_old_names_still_reach_the_plugin() {
        $public = file_get_contents( QAF_PLUGIN_DIR . 'public/class-qaf-public.php' );
        $this->assertStringContainsString( "add_shortcode( 'baf_form'", $public );
        $this->assertStringContainsString( "add_shortcode( 'baf_entries'", $public );
        $main = file_get_contents( QAF_PLUGIN_DIR . 'quill-accessible-forms.php' );
        $this->assertLessThan( strpos( $main, 'QAF_Activator::activate();' ), strpos( $main, 'QAF_Migrator::on_activate();' ), 'the migration runs before the activator makes empty tables' );
        $this->assertStringContainsString( 'QAF_Legacy_REST::init()', $main );
        $this->assertStringContainsString( 'Text Domain:       quill-accessible-forms', $main );
        $this->assertStringContainsString( 'Plugin Name:       QUILL Accessible Forms', $main );
        $this->assertStringContainsString( 'Copyright (c) 2024-2026 Jeff Bishop', $main );
        $importer = file_get_contents( QAF_PLUGIN_DIR . 'includes/import/class-qaf-form-importer.php' );
        $this->assertStringContainsString( "case 'qaf':", $importer );
        $this->assertStringContainsString( "case 'bfl':", $importer, 'files saved before the rename still open' );
        $this->assertStringContainsString( 'Copyright (c) 2024-2026 Jeff Bishop', file_get_contents( QAF_PLUGIN_DIR . 'LICENSE' ) );
    }

    public function test_a_saved_form_still_starts_with_the_language_version() {
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-writer.php';
        $m = json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/logic-and-layout.json' ), true );
        $this->assertStringStartsWith( "bfl 1\n", QAF_BFL_Writer::write( $m ) );
        foreach ( glob( QAF_PLUGIN_DIR . 'tests/conformance/qaf/canonical/*.qaf' ) as $f ) {
            $this->assertStringStartsWith( 'bfl 1', file_get_contents( $f ), basename( $f ) );
        }
    }
}
