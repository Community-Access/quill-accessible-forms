<?php
/**
 * Runs tests/conformance/manifest.json: every case, by kind, the way any
 * other reader of the form model (QUILL's Python) runs it. Also checks the
 * manifest lists every file in the suite, so nothing goes unchecked.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-form-model-converter.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-bfl-writer.php';
if ( file_exists( BAF_PLUGIN_DIR . 'includes/model/class-baf-importers.php' ) ) {
    require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-importers.php';
}

class BAF_Conformance_Manifest_Test extends BAF_Unit_Test_Case {

    private static function root() {
        return BAF_PLUGIN_DIR . 'tests/conformance/';
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
                    if ( is_string( $v ) && in_array( $k, array( 'bfl', 'model', 'canonical', 'stored', 'report', 'input' ), true ) ) {
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
        $this->assertCount( 10, array_unique( $ran ) );
    }

    private function run_schema( $suite ) {
        $schema = self::json( $suite['file'] );
        $this->assertSame( 'https://json-schema.org/draft/2020-12/schema', $schema['$schema'] );
        $this->assertSame( self::manifest()['model_version'], $schema['properties']['version']['const'] );
    }

    private function run_expressions( $suite ) {
        $cases = self::json( $suite['file'] );
        foreach ( $cases['parse'] as $c ) {
            $ast = BAF_Expression::parse( $c['text'] );
            $this->assertSame( $c['canonical'], BAF_Expression::to_text( $ast ), $c['text'] );
            $this->assertSame( $c['canonical'], BAF_Expression::canonical( $c['canonical'] ), 'canonical text reads back: ' . $c['text'] );
            if ( isset( $c['ast'] ) ) {
                $this->assertTrue( BAF_Form_Model::same( $c['ast'], $ast ), $c['text'] );
            }
            if ( isset( $c['sentence'] ) ) {
                $this->assertSame( $c['sentence'], BAF_Expression::to_sentence( $ast, $cases['labels'], $cases['choices'] ), $c['text'] );
            }
        }
        foreach ( $cases['errors'] as $c ) {
            $this->assertNotNull( BAF_Expression::check( $c['text'] ), $c['text'] );
            if ( isset( $c['offset'] ) ) {
                try {
                    BAF_Expression::parse( $c['text'] );
                } catch ( BAF_Expression_Error $e ) {
                    $this->assertSame( $c['offset'], $e->offset, 'offset of ' . $c['text'] );
                }
            }
        }
        foreach ( $cases['evaluate'] as $c ) {
            $got = BAF_Expression::evaluate( $c['text'], $cases['context'] );
            if ( is_int( $c['result'] ) || is_float( $c['result'] ) ) {
                $this->assertEqualsWithDelta( $c['result'], $got, 1e-9, $c['text'] );
            } else {
                $this->assertSame( $c['result'], $got, $c['text'] );
            }
        }
    }

    private function run_valid_models( $suite ) {
        foreach ( $suite['files'] as $rel ) {
            $this->assertSame( array(), BAF_Form_Model::validate( self::json( $rel ) ), $rel );
        }
    }

    private function run_invalid_models( $suite ) {
        foreach ( self::json( $suite['file'] )['cases'] as $c ) {
            $paths = array_column( BAF_Form_Model::validate( $c['model'] ), 'path' );
            $this->assertContains( $c['path'], $paths, $c['name'] );
        }
    }

    private function run_behaviour( $suite ) {
        $spec = self::json( $suite['file'] );
        foreach ( $spec['cases'] as $c ) {
            $model = self::json( 'models/' . $c['model'] );
            $extra = array( 'today' => $spec['today'] );
            if ( isset( $c['total'] ) ) {
                $this->assertEquals( $c['total'], BAF_Form_Model::score( $model, $c['answers'] )['total'], $c['name'] );
                $this->assertSame( $c['outcome'], BAF_Form_Model::outcome( $model, $c['answers'], $extra )['id'] ?? null, $c['name'] );
            }
            foreach ( $c['shown'] ?? array() as $id ) {
                $this->assertTrue( BAF_Form_Model::is_shown( $model, $id, $c['answers'], $extra ), $c['name'] . ' ' . $id );
            }
            foreach ( $c['hidden'] ?? array() as $id ) {
                $this->assertFalse( BAF_Form_Model::is_shown( $model, $id, $c['answers'], $extra ), $c['name'] . ' ' . $id );
            }
        }
    }

    private function run_bfl_canonical( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $model = self::json( $c['model'] );
            $bfl   = self::load( $c['bfl'] );
            $this->assertSame( $bfl, BAF_BFL_Writer::write( $model ), $c['bfl'] );
            $read = BAF_BFL_Reader::read( $bfl );
            $this->assertSame( array(), array_merge( $read['errors'], $read['warnings'] ), $c['bfl'] );
            $this->assertTrue( BAF_Form_Model::same( $model, $read['model'] ), $c['bfl'] );
        }
    }

    private function run_bfl_legacy( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $model = self::json( $c['model'] );
            $read  = BAF_BFL_Reader::read( self::load( $c['bfl'] ) );
            $this->assertSame( array(), $read['errors'], $c['bfl'] );
            $this->assertTrue( BAF_Form_Model::same( $model, $read['model'] ), $c['bfl'] );
            $this->assertSame( self::load( $c['canonical'] ), BAF_BFL_Writer::write( $model ), $c['canonical'] );
            $this->assertSame( array(), BAF_Form_Model::validate( $model ), $c['model'] );
        }
    }

    private function run_bfl_errors( $suite ) {
        foreach ( self::json( $suite['file'] )['cases'] as $c ) {
            $lines = array_column( BAF_BFL_Reader::read( $c['bfl'] )['errors'], 'line' );
            $this->assertContains( $c['line'], $lines, $c['name'] );
        }
    }

    private function run_builder( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $stored = self::json( $c['stored'] );
            $result = BAF_Form_Model_Converter::from_legacy( $stored );
            $this->assertTrue( BAF_Form_Model::same( self::json( $c['model'] ), $result['model'] ), $c['stored'] );
            if ( isset( $c['report'] ) ) {
                $this->assertSame( self::json( $c['report'] )['entries'], $result['report']->entries, $c['report'] );
            }
        }
    }

    private function run_imports( $suite ) {
        foreach ( $suite['cases'] as $c ) {
            $result = BAF_Importers::import( $c['source'], self::load( $c['input'] ) );
            $this->assertTrue( BAF_Form_Model::same( self::json( $c['model'] ), $result['model'] ), $c['input'] );
            $this->assertSame( array(), BAF_Form_Model::validate( $result['model'] ), $c['input'] );
            $this->assertSame( self::json( $c['report'] ), $result['report']->to_array(), $c['report'] );
        }
    }
}
