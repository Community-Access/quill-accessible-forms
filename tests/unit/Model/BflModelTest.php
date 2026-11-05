<?php
/**
 * BFL as a notation of the form model: reading, writing and round trips.
 * The fixture files are in tests/conformance/bfl and are shared with QUILL.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-reader.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-bfl-writer.php';

class QAF_Bfl_Model_Test extends QAF_Unit_Test_Case {

    private static function dir() {
        return QAF_PLUGIN_DIR . 'tests/conformance/';
    }

    private static function json( $path ) {
        return json_decode( file_get_contents( $path ), true );
    }

    private static function problems( $result ) {
        return implode( '; ', array_map( function ( $e ) {
            return 'line ' . $e['line'] . ': ' . $e['message'];
        }, array_merge( $result['errors'], $result['warnings'] ) ) );
    }

    /**
     * Canonical pairs: the model and its BFL, both directions, byte for byte.
     */
    public function test_canonical_pairs_round_trip_exactly() {
        $files = glob( self::dir() . 'qaf/canonical/*.qaf' );
        $this->assertGreaterThanOrEqual( 4, count( $files ) );
        foreach ( $files as $bfl_file ) {
            $name  = basename( $bfl_file, '.qaf' );
            $local = self::dir() . 'qaf/canonical/' . $name . '.json';
            $model = self::json( file_exists( $local ) ? $local : self::dir() . 'models/valid/' . $name . '.json' );
            $bfl   = file_get_contents( $bfl_file );

            $this->assertSame( $bfl, QAF_BFL_Writer::write( $model ), $name . ': model to BFL' );

            $read = QAF_BFL_Reader::read( $bfl );
            $this->assertSame( '', self::problems( $read ), $name . ': reading canonical BFL is silent' );
            $this->assertTrue( QAF_Form_Model::same( $model, $read['model'] ), $name . ': BFL to model' );
            $this->assertSame( array(), QAF_Form_Model::validate( $read['model'] ), $name . ': the model is valid' );
        }
    }

    /**
     * Existing BFL keeps reading: each older file has an expected model, and
     * the canonical BFL for that model reads back to the same model.
     */
    public function test_legacy_bfl_reads_to_the_expected_model() {
        $files = array_filter( glob( self::dir() . 'qaf/legacy/*.qaf' ), function ( $f ) {
            return strpos( $f, '.canonical.qaf' ) === false;
        } );
        $this->assertGreaterThanOrEqual( 6, count( $files ) );
        foreach ( $files as $file ) {
            $stem     = substr( $file, 0, -4 );
            $expected = self::json( $stem . '.json' );
            $read     = QAF_BFL_Reader::read( file_get_contents( $file ) );
            $this->assertSame( array(), $read['errors'], basename( $file ) . ': ' . self::problems( $read ) );
            $this->assertTrue( QAF_Form_Model::same( $expected, $read['model'] ), basename( $file ) . ': model' );
            $this->assertSame( array(), QAF_Form_Model::validate( $read['model'] ), basename( $file ) . ': valid' );

            $canonical = file_get_contents( $stem . '.canonical.qaf' );
            $this->assertSame( $canonical, QAF_BFL_Writer::write( $expected ), basename( $file ) . ': canonical text' );
            $this->assertTrue( QAF_Form_Model::same( $expected, QAF_BFL_Reader::to_model( $canonical ) ), basename( $file ) . ': canonical reads back' );
        }
    }

    /**
     * The new reader agrees with the older parser on every field with an ID:
     * its type, label, required flag and choices.
     */
    public function test_new_reader_agrees_with_the_older_parser() {
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-lexer.php';
        require_once QAF_PLUGIN_DIR . 'includes/bfl/class-qaf-bfl-parser.php';
        $aliases = array( 'divider' => 'html', 'spacer' => 'html' );

        foreach ( glob( self::dir() . 'qaf/legacy/*.qaf' ) as $file ) {
            if ( strpos( $file, '.canonical.qaf' ) !== false ) {
                continue;
            }
            $source = file_get_contents( $file );
            $old    = ( new QAF_BFL_Parser() )->parse( $source )['form']['fields'];
            $new    = QAF_Form_Model::fields( QAF_BFL_Reader::to_model( $source ) );

            $flat = array();
            $walk = function ( $fields ) use ( &$walk, &$flat ) {
                foreach ( $fields as $f ) {
                    $flat[] = $f;
                    foreach ( $f['columns'] ?? array() as $col ) {
                        $walk( $col );
                    }
                }
            };
            $walk( $old );

            foreach ( $flat as $f ) {
                if ( in_array( $f['type'], array( 'section', 'columns' ), true ) || preg_match( '/^field_\d+$/', $f['id'] ) ) {
                    continue;
                }
                $where = basename( $file ) . ' #' . $f['id'];
                $this->assertArrayHasKey( $f['id'], $new, $where . ' is read' );
                $mine = $new[ $f['id'] ];
                $this->assertSame( $aliases[ $f['type'] ] ?? $f['type'], $mine['type'], $where . ' type' );
                $this->assertSame( $f['label'] ?? null, $mine['label'] ?? null, $where . ' label' );
                $this->assertSame( ! empty( $f['required'] ), ! empty( $mine['required'] ), $where . ' required' );
                if ( ! empty( $f['choices'] ) && ! isset( $f['choices']['library'] ) ) {
                    $old_pairs = array_map( function ( $c ) {
                        return $c['value'] . '=' . $c['label'];
                    }, $f['choices'] );
                    $new_pairs = array_map( function ( $c ) {
                        return $c['value'] . '=' . $c['label'];
                    }, $mine['choices'] );
                    // A colon not followed by a price is part of the label now, and
                    // the older parser drops the first choice of an indented list
                    // (its own ParserTest fails on that), so its choices are a tail of ours.
                    if ( $f['id'] !== 'when' ) {
                        $this->assertSame( $old_pairs, array_slice( $new_pairs, count( $new_pairs ) - count( $old_pairs ) ), $where . ' choices' );
                        $this->assertLessThanOrEqual( 1, count( $new_pairs ) - count( $old_pairs ), $where . ' choices' );
                    }
                }
            }
        }
    }

    public function test_error_cases_are_reported_on_their_line() {
        foreach ( self::json( self::dir() . 'qaf/errors.json' )['cases'] as $case ) {
            $read = QAF_BFL_Reader::read( $case['qaf'] );
            $hit  = false;
            foreach ( $read['errors'] as $e ) {
                if ( $e['line'] === $case['line'] && stripos( $e['message'], $case['contains'] ) !== false ) {
                    $hit = true;
                }
            }
            $this->assertTrue( $hit, $case['name'] . ': ' . self::problems( $read ) );
        }
    }

    public function test_and_or_not_and_else_in_bfl() {
        $bfl   = "form \"T\"\nradio #type \"Type\"\n  + Business\n  + Charity\n  + Person\nif #type is \"business\" or #type is \"charity\"\n  text #company \"Company\"\n    @when #type is not \"person\"\nelse\n  text #why \"Why join?\"\nif #type is \"person\" {\n  if #company is empty {\n    date #born \"Born\"\n  }\n}\n";
        $model = QAF_BFL_Reader::to_model( $bfl );
        $f     = QAF_Form_Model::fields( $model );
        $this->assertSame( '(#type is "business" or #type is "charity") and #type is not "person"', $f['company']['when'] );
        $this->assertSame( 'not (#type is "business" or #type is "charity")', $f['why']['when'] );
        $this->assertSame( '#type is "person" and #company is empty', $f['born']['when'] );
        $this->assertSame( 'Type is Business or Type is Charity', QAF_Form_Model::sentence( $model, '#type is "business" or #type is "charity"' ) );
    }

    public function test_page_and_section_conditions() {
        $model = QAF_BFL_Reader::to_model( "form \"T\"\ncheckbox #ship \"Ship it\"\npage \"Delivery\"\n  @when #ship is \"yes\"\n  section #addr \"Address\"\n    @when #ship is not empty\n    text #street \"Street\"\n" );
        $this->assertSame( '#ship is "yes"', $model['pages'][1]['when'] );
        $this->assertSame( '#ship is not empty', $model['pages'][1]['items'][0]['when'] );
        $this->assertSame( 'street', $model['pages'][1]['items'][0]['items'][0]['id'] );
    }

    public function test_version_line_crlf_and_bom_are_accepted() {
        $model = QAF_BFL_Reader::to_model( "\xEF\xBB\xBFbfl 1\r\nform \"T\"\r\ntext #a \"A\"\r\n  @required\r\n" );
        $this->assertTrue( QAF_Form_Model::fields( $model )['a']['required'] );
    }

    public function test_unsafe_attribute_is_dropped_with_a_warning() {
        $read = QAF_BFL_Reader::read( "text #a \"A\" @attr onclick \"steal()\" @attr data-x \"1\"" );
        $this->assertSame( array( 'data-x' => '1' ), QAF_Form_Model::fields( $read['model'] )['a']['attributes'] );
        $this->assertCount( 1, $read['warnings'] );
    }

    public function test_writer_starts_with_the_version_line() {
        $this->assertStringStartsWith( "bfl 1\nform \"New\"\n", QAF_BFL_Writer::write( QAF_Form_Model::create( 'New' ) ) );
    }

    public function test_quoted_choice_with_extras() {
        $model  = QAF_BFL_Reader::to_model( "product #p \"Shirt\"\n  + \"s\" | \"Small\" @price 15 @selected\n  + \"Large\" @price 17.5\n" );
        $choice = QAF_Form_Model::fields( $model )['p']['choices'];
        $this->assertSame( array( 'value' => 's', 'label' => 'Small', 'price' => 15, 'selected' => true ), $choice[0] );
        $this->assertSame( array( 'value' => 'large', 'label' => 'Large', 'price' => 17.5 ), $choice[1] );
    }
}
