<?php
/**
 * Runs tests/conformance/manifest.json: every case, by kind, the way any
 * other reader of the form model (QUILL's Python) runs it. Also checks the
 * manifest lists every file in the suite, so nothing goes unchecked.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model-converter.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-writer.php';
if ( file_exists( QAF_PLUGIN_DIR . 'includes/model/class-qaf-importers.php' ) ) {
    require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-importers.php';
}
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-exporters.php';

class QAF_Conformance_Manifest_Test extends QAF_Unit_Test_Case {

    private static function root() {
        return QAF_PLUGIN_DIR . 'tests/conformance/';
    }

    private static function manifest() {
        return json_decode( file_get_contents( self::root() . 'manifest.json' ), true );
    }

    private static function load( $rel ) {
        return file_get_contents( self::root() . $rel );
    }

    private static function json( $rel ) {
        return json_decode( self::load( $rel ), true );
    }

    /** Every path the manifest names, relative to the suite folder. */
    private static function listed() {
        $out = array();
        foreach ( self::manifest()['suites'] as $suite ) {
            foreach ( array( 'file' ) as $k ) {
                if ( isset( $suite[ $k ] ) ) {
                    $out[] = $suite[ $k ];
                }
            }
            foreach ( $suite['files'] ?? array() as $f ) {
                $out[] = $f;
            }
            foreach ( $suite['cases'] ?? array() as $case ) {
                foreach ( $case as $k => $v ) {
                    if ( is_string( $v ) && in_array( $k, array( 'qaf', 'model', 'canonical', 'stored', 'report', 'input', 'output' ), true ) ) {
                        $out[] = $v;
                    }
                }
            }
        }
        return array_values( array_unique( $out ) );
    }

    public function test_every_listed_file_exists_and_every_file_is_listed() {
        $listed = self::listed();
        foreach ( $listed as $rel ) {
            $this->assertFileExists( self::root() . $rel, $rel );
        }
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::root(), FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file ) {
            $rel = str_replace( '\\', '/', substr( (string) $file, strlen( self::root() ) ) );
            if ( in_array( $rel, array( 'README.md', 'manifest.json' ), true ) ) {
                continue;
            }
            $this->assertContains( $rel, $listed, $rel . ' is not in manifest.json' );
        }
    }

    public function test_fixture_files_are_lf_utf8_without_bom() {
        foreach ( self::listed() as $rel ) {
            $bytes = self::load( $rel );
            $this->assertStringNotContainsString( "\r", $bytes, $rel );
            $this->assertNotSame( "\xEF\xBB\xBF", substr( $bytes, 0, 3 ), $rel );
            $this->assertSame( 1, preg_match( '//u', $bytes ), $rel . ' is UTF-8' );
        }
    }

    public function test_run_every_suite() {
        $ran = array();
        foreach ( self::manifest()['suites'] as $suite ) {
            $method = 'run_' . str_replace( '-', '_', $suite['kind'] );
            $this->assertTrue( method_exists( $this, $method ), 'no runner for ' . $suite['kind'] );
            $this->$method( $suite );
            $ran[] = $suite['kind'];
        }
        $this->assertCount( count( self::manifest()['suites'] ), $ran );
        $this->assertCount( 11, array_unique( $ran ) );
    }

    private function run_schema( $suite ) {
        $schema = self::json( $suite['file'] );
        $this->assertSame( 'https://json-schema.org/draft/2020-12/schema', $schema['$schema'] );
        $this->assertSame( self::manifest()['model_version'], $schema['properties']['version']['const'] );
    }

    private function run_expressions( $suite ) {
        $cases = self::json( $suite['file'] );
        foreach ( $cases['parse'] as $c ) {
            $ast = QAF_Expression::parse( $c['text'] );
            $this->assertSame( $c['canonical'], QAF_Expression::to_text( $ast ), $c['text'] );
            $this->assertSame( $c['canonical'], QAF_Expression::canonical( $c['canonical'] ), 'canonical text reads back: ' . $c['text'] );
            if ( isset( $c['ast'] ) ) {
                $this->assertTrue( QAF_Form_Model::same( $c['ast'], $ast ), $c['text'] );
            }
            if ( isset( $c['sentence'] ) ) {
                $this->assertSame( $c['sentence'], QAF_Expression::to_sentence( $ast, $cases['labels'], $cases['choices'] ), $c['text'] );
            }
        }
        foreach ( $cases['errors'] as $c ) {
            $this->assertNotNull( QAF_Expression::check( $c['text'] ), $c['text'] );
            if ( isset( $c['offset'] ) ) {
                try {
                    QAF_Expression::parse( $c['text'] );
                } catch ( QAF_Expression_Error $e ) {
                    $this->assertSame( $c['offset'], $e->offset, 'offset of ' . $c['text'] );
                }
            }
        }
        foreach ( $cases['evaluate'] as $c ) {
            $got = QAF_Expression::evaluate( $c['text'], $cases['context'] );
            if ( is_int( $c['result'] ) || is_float( $c['result'] ) ) {
                $this->assertEqualsWithDelta( $c['result'], $got, 1e-9, $c['text'] );
            } else {
                $this->assertSame( $c['result'], $got, $c['text'] );
            }
        }
    }

    private function run_valid_models( $suite ) {
        foreach ( $suite['files'] as $rel ) {
            $this->assertSame( array(), QAF_Form_Model::validate( self::json( $rel ) ), $rel );
        }
    }

    private function run_invalid_models( $suite ) {
        foreach ( self::json( $suite['file'] )['cases'] as $c ) {
            $paths = array_column( QAF_Form_Model::validate( $c['model'] ), 'path' );
            $this->assertContains( $c['path'], $paths, $c['name'] );
        }
    }

    private function run_behaviour( $suite ) {
        $spec = self::json( $suite['file'] );
        foreach ( $spec['cases'] as $c ) {
            $model = self::json( 'models/' . $c['model'] );
            $extra = array( 'today' => $spec['today'] );
            if ( isset( $c['total'] ) ) {
                $this->assertEquals( $c['total'], QAF_Form_Model::score( $model, $c['answers'] )['total'], $c['name'] );
                $this->assertSame( $c['outcome'], QAF_Form_Model::outcome( $model, $c['answers'], $extra )['id'] ?? null, $c['name'] );
            }
            foreach ( $c['shown'] ?? array() as $id ) {
                $this->assertTrue( QAF_Form_Model::is_shown( $model, $id, $c['answers'], $extra ), $c['name'] . ' ' . $id );
            }
            foreach ( $c['hidden'] ?? array() as $id ) {
                $this->assertFalse( QAF_Form_Model::is_shown( $model, $id, $c['answers'], $extra ), $c['name'] . ' ' . $id );
            }
        }
    }

    private function run_exports( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $out = QAF_Exporters::export( $c['target'], self::json( $c['model'] ) );
            $this->assertSame( self::load( $c['output'] ), $out['text'], $c['output'] );
            $this->assertSame( self::json( $c['report'] ), $out['report']->to_array(), $c['report'] );
            // What was exported reads back through the importer, and is a valid model.
            $back = QAF_Importers::import( $c['target'], $out['text'] );
            $this->assertSame( array(), QAF_Form_Model::validate( $back['model'] ), $c['output'] . ' reads back' );
        }
    }

    private function run_qaf_canonical( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $model = self::json( $c['model'] );
            $bfl   = self::load( $c['qaf'] );
            $this->assertSame( $bfl, QAF_BFL_Writer::write( $model ), $c['qaf'] );
            $read = QAF_BFL_Reader::read( $bfl );
            $this->assertSame( array(), array_merge( $read['errors'], $read['warnings'] ), $c['qaf'] );
            $this->assertTrue( QAF_Form_Model::same( $model, $read['model'] ), $c['qaf'] );
        }
    }

    private function run_qaf_legacy( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $model = self::json( $c['model'] );
            $read  = QAF_BFL_Reader::read( self::load( $c['qaf'] ) );
            $this->assertSame( array(), $read['errors'], $c['qaf'] );
            $this->assertTrue( QAF_Form_Model::same( $model, $read['model'] ), $c['qaf'] );
            $this->assertSame( self::load( $c['canonical'] ), QAF_BFL_Writer::write( $model ), $c['canonical'] );
            $this->assertSame( array(), QAF_Form_Model::validate( $model ), $c['model'] );
        }
    }

    private function run_qaf_errors( $suite ) {
        foreach ( self::json( $suite['file'] )['cases'] as $c ) {
            $lines = array_column( QAF_BFL_Reader::read( $c['qaf'] )['errors'], 'line' );
            $this->assertContains( $c['line'], $lines, $c['name'] );
        }
    }

    private function run_builder( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $stored = self::json( $c['stored'] );
            $result = QAF_Form_Model_Converter::from_legacy( $stored );
            $this->assertTrue( QAF_Form_Model::same( self::json( $c['model'] ), $result['model'] ), $c['stored'] );
            if ( isset( $c['report'] ) ) {
                $this->assertSame( self::json( $c['report'] )['entries'], $result['report']->entries, $c['report'] );
            }
        }
    }

    private function run_imports( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $result = QAF_Importers::import( $c['source'], self::load( $c['input'] ) );
            $this->assertTrue( QAF_Form_Model::same( self::json( $c['model'] ), $result['model'] ), $c['input'] );
            $this->assertSame( array(), QAF_Form_Model::validate( $result['model'] ), $c['input'] );
            $this->assertSame( self::json( $c['report'] ), $result['report']->to_array(), $c['report'] );
        }
    }
}
