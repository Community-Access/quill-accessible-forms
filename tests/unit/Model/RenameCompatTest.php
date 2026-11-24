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

/** The same storage with what step 2 reaches: cron, posts, widgets, saved forms and users' own capabilities. */
class QAF_Fake_Migration_Store_Full extends QAF_Fake_Migration_Store {
    public $cron = array();      // list of array( timestamp, hook, args, schedule )
    public $posts = array();     // id => content
    public $forms = array();     // list of array( config, bfl_source ), in wp_qaf_forms
    public $user_caps = array(); // user id => caps

    public function cron_events( $start ) {
        $out = array();
        foreach ( $this->cron as $e ) {
            if ( strpos( $e['hook'], $start ) === 0 ) {
                $out[] = $e;
            }
        }
        return $out;
    }
    public function move_cron( $event, $hook ) {
        $this->cron = array_values( array_filter( $this->cron, function ( $e ) use ( $event ) { return $e !== $event; } ) );
        foreach ( $this->cron as $e ) {
            if ( $e['hook'] === $hook && $e['args'] === $event['args'] ) {
                return;
            }
        }
        $this->cron[] = array_merge( $event, array( 'hook' => $hook ) );
    }
    private static function swap( $text, $pairs ) {
        return str_replace( array_keys( $pairs ), array_values( $pairs ), $text );
    }
    public function replace_in_posts( $pairs ) {
        $n = 0;
        foreach ( $this->posts as $id => $c ) {
            $new = self::swap( $c, $pairs );
            if ( $new !== $c ) { $this->posts[ $id ] = $new; $n++; }
        }
        return $n;
    }
    public function replace_in_options( $start, $pairs ) {
        $n = 0;
        foreach ( $this->options as $name => $v ) {
            if ( strpos( $name, $start ) === 0 && is_string( $v ) ) {
                $new = self::swap( $v, $pairs );
                if ( $new !== $v ) { $this->options[ $name ] = $new; $n++; }
            }
        }
        return $n;
    }
    public function replace_in_columns( $table, $columns, $pairs ) {
        $n = 0;
        foreach ( $this->forms as $i => $row ) {
            $new = $row;
            foreach ( $columns as $c ) { $new[ $c ] = self::swap( $row[ $c ], $pairs ); }
            if ( $new !== $row ) { $this->forms[ $i ] = $new; $n++; }
        }
        return $n;
    }
    public function user_caps( $start ) {
        return array_filter( $this->user_caps, function ( $caps ) use ( $start ) {
            foreach ( $caps as $c ) { if ( strpos( $c, $start ) === 0 ) { return true; } }
            return false;
        } );
    }
    public function add_user_cap( $id, $cap ) { $this->user_caps[ $id ][] = $cap; }
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

    /** A BAF site with something in every place step 2 reaches. */
    private static function full_site() {
        $s          = new QAF_Fake_Migration_Store_Full();
        $base       = self::site();
        foreach ( array( 'tables', 'options', 'meta', 'caps' ) as $k ) {
            $s->$k = $base->$k;
        }
        $widget     = serialize( array( 2 => array( 'content' => '<!-- wp:baf/form {"formId":3} /-->' ), 3 => array( 'text' => '[baf_form id="3"]' ) ) );
        $s->options['widget_block'] = $widget;
        $s->options['other_option'] = '[baf_form id="3"]';
        $s->cron    = array(
            array( 'timestamp' => 1700000000, 'hook' => 'baf_process_webhook_queue', 'args' => array(), 'schedule' => 'hourly' ),
            array( 'timestamp' => 1700000100, 'hook' => 'baf_send_async_webhook', 'args' => array( 42 ), 'schedule' => false ),
            array( 'timestamp' => 1700000200, 'hook' => 'wp_version_check', 'args' => array(), 'schedule' => 'twicedaily' ),
        );
        $s->posts   = array(
            10 => "<!-- wp:baf/form {\"formId\":3} /-->\n<p>Hello</p>",
            11 => 'Before [baf_form id="4"] and [baf_entries form="4"] after.',
            12 => '<!-- wp:paragraph --><p>Nothing to do.</p><!-- /wp:paragraph -->',
        );
        $s->forms   = array(
            array( 'config' => '{"extensions":{"baf_legacy":{"options":"x"}}}', 'bfl_source' => "bfl 1\n@extensions {\"baf_legacy\":{\"options\":\"x\"}}" ),
            array( 'config' => '{"title":"Plain"}', 'bfl_source' => '' ),
        );
        $s->user_caps = array( 5 => array( 'subscriber', 'baf_view_entries' ), 6 => array( 'editor' ) );
        return $s;
    }

    public function test_step_two_moves_what_refers_to_the_old_names() {
        $s = self::full_site();
        QAF_Migrator::run( $s );
        $done = QAF_Migrator::run_references( $s );
        $this->assertSame( array( 'cron' => 2, 'posts' => 2, 'widgets' => 1, 'forms' => 1, 'user_caps' => 1 ), $done );

        $hooks = array_column( $s->cron, 'hook' );
        sort( $hooks );
        $this->assertSame( array( 'qaf_process_webhook_queue', 'qaf_send_async_webhook', 'wp_version_check' ), $hooks );
        foreach ( $s->cron as $e ) {
            if ( 'qaf_send_async_webhook' === $e['hook'] ) {
                $this->assertSame( array( 1700000100, array( 42 ), false ), array( $e['timestamp'], $e['args'], $e['schedule'] ), 'same time, same arguments, still a single event' );
            }
            if ( 'qaf_process_webhook_queue' === $e['hook'] ) {
                $this->assertSame( 'hourly', $e['schedule'] );
            }
        }

        $this->assertStringStartsWith( '<!-- wp:qaf/form {"formId":3} /-->', $s->posts[10] );
        $this->assertSame( 'Before [qaf_form id="4"] and [qaf_entries form="4"] after.', $s->posts[11] );
        $this->assertSame( '<!-- wp:paragraph --><p>Nothing to do.</p><!-- /wp:paragraph -->', $s->posts[12] );

        $widgets = unserialize( $s->options['widget_block'] );
        $this->assertNotFalse( $widgets, 'the serialized widget option is still valid' );
        $this->assertSame( '<!-- wp:qaf/form {"formId":3} /-->', $widgets[2]['content'] );
        $this->assertSame( '[qaf_form id="3"]', $widgets[3]['text'] );
        $this->assertSame( '[baf_form id="3"]', $s->options['other_option'], 'only widget options are rewritten' );

        $this->assertSame( '{"extensions":{"qaf_legacy":{"options":"x"}}}', $s->forms[0]['config'] );
        $this->assertStringContainsString( '"qaf_legacy"', $s->forms[0]['bfl_source'] );
        $this->assertSame( '{"title":"Plain"}', $s->forms[1]['config'] );

        $this->assertSame( array( 'subscriber', 'baf_view_entries', 'qaf_view_entries' ), $s->user_caps[5] );
    }

    public function test_every_replacement_keeps_its_length() {
        foreach ( array_merge( QAF_Migrator::CONTENT_PAIRS, QAF_Migrator::FORM_PAIRS ) as $from => $to ) {
            $this->assertSame( strlen( $from ), strlen( $to ), $from . ': a serialized value keeps its length' );
        }
    }

    public function test_the_migration_is_versioned_and_runs_each_step_once() {
        $this->assertSame( 0, QAF_Migrator::stored_version( false, false ), 'a site that never migrated' );
        $this->assertSame( 1, QAF_Migrator::stored_version( false, array( 'tables' => array() ) ), 'QAF 1.1.0 kept only the report' );
        $this->assertSame( 2, QAF_Migrator::stored_version( '2', false ) );

        $s     = self::full_site();
        $steps = array();
        $done  = QAF_Migrator::migrate( $s, 0, function ( $step, $did ) use ( &$steps ) { $steps[] = $step; } );
        $this->assertSame( array( 1, 2 ), $steps, 'progress is stored after each step' );
        $this->assertSame( array( 1, 2 ), array_keys( $done ) );
        $this->assertSame( QAF_Migrator::VERSION, max( $steps ) );

        $after = array( $s->tables, $s->options, $s->meta, $s->caps, $s->cron, $s->posts, $s->forms, $s->user_caps );
        $this->assertSame( array(), QAF_Migrator::migrate( $s, QAF_Migrator::VERSION ), 'nothing is due on a migrated site' );
        QAF_Migrator::run( $s );
        QAF_Migrator::run_references( $s );
        $this->assertSame( $after, array( $s->tables, $s->options, $s->meta, $s->caps, $s->cron, $s->posts, $s->forms, $s->user_caps ), 'and forcing it again changes nothing' );
    }

    public function test_a_site_migrated_by_1_1_0_gets_only_step_two() {
        $s = self::full_site();
        QAF_Migrator::run( $s );
        $log  = $s->log;
        $done = QAF_Migrator::migrate( $s, 1 );
        $this->assertSame( array( 2 ), array_keys( $done ) );
        $this->assertSame( $log, $s->log, 'no table was touched again' );
        $this->assertSame( 2, $done[2]['posts'] );
    }

    public function test_the_migration_runs_before_anything_can_make_empty_tables() {
        $main = file_get_contents( QAF_PLUGIN_DIR . 'quill-accessible-forms.php' );
        $this->assertMatchesRegularExpression( "/QAF_Migrator::maybe_run\(\);\s*\}, 4 \);/", $main, 'plugins_loaded at 4, on any request' );
        $this->assertMatchesRegularExpression( "/QAF_Activator::maybe_upgrade\(\);\s*\}, 5 \);/", $main );
        $this->assertStringNotContainsString( "add_action( 'admin_init', function () {\n    if ( class_exists( 'QAF_Migrator' )", str_replace( "\r\n", "\n", $main ) );
        $public = file_get_contents( QAF_PLUGIN_DIR . 'public/class-qaf-public.php' );
        $this->assertStringContainsString( "register_block_type( 'baf/form'", $public, 'an old block still shows its form' );
        $this->assertStringContainsString( "'inserter' => false", $public );
    }

    public function test_a_form_file_saved_under_the_old_name_opens() {
        require_once QAF_PLUGIN_DIR . 'includes/import/class-qaf-form-importer.php';
        $importer = file_get_contents( QAF_PLUGIN_DIR . 'includes/import/class-qaf-form-importer.php' );
        $this->assertStringContainsString( "case 'baf':", $importer );
        $this->assertSame( '{"extensions":{"qaf_legacy":{"a":1}}}', QAF_Form_Importer::renamed_keys( '{"extensions":{"baf_legacy":{"a":1}}}' ) );
        $this->assertSame( 'bfl 1', QAF_Form_Importer::renamed_keys( 'bfl 1' ) );
    }
}
