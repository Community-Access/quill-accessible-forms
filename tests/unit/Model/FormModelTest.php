<?php
/**
 * Form model tests: the schema, the rules beyond it, scoring and visibility.
 * Most cases come from tests/conformance/models, which QUILL runs too.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-form-model.php';

class BAF_Form_Model_Test extends BAF_Unit_Test_Case {

    private static function conformance( $path ) {
        return json_decode( file_get_contents( BAF_PLUGIN_DIR . 'tests/conformance/models/' . $path ), true );
    }

    private static function describe( $errors ) {
        return implode( '; ', array_map( function ( $e ) {
            return $e['path'] . ' ' . $e['message'];
        }, $errors ) );
    }

    public function test_schema_file_is_valid_json_with_every_field_type() {
        $schema = json_decode( file_get_contents( BAF_Form_Model::schema_path() ), true );
        $this->assertIsArray( $schema );
        $this->assertSame( BAF_Form_Model::FIELD_TYPES, $schema['$defs']['field_type']['enum'] );
    }

    public function test_model_covers_every_registered_baf_field_type() {
        // Every field class BAF registers is a model field type, or a layout item (section, columns, page).
        $source = file_get_contents( BAF_PLUGIN_DIR . 'includes/fields/class-baf-field-registry.php' );
        preg_match_all( "/->register\\( '([a-z_]+)'/", $source, $m );
        $this->assertCount( 67, $m[1] );
        foreach ( $m[1] as $type ) {
            $this->assertTrue( BAF_Form_Model::is_field_type( $type ) || in_array( $type, array( 'section', 'columns', 'page' ), true ), $type );
        }
        $this->assertCount( 64, BAF_Form_Model::FIELD_TYPES );
    }

    public function test_every_valid_conformance_model_passes() {
        foreach ( glob( BAF_PLUGIN_DIR . 'tests/conformance/models/valid/*.json' ) as $file ) {
            $model  = json_decode( file_get_contents( $file ), true );
            $errors = BAF_Form_Model::validate( $model );
            $this->assertSame( array(), $errors, basename( $file ) . ': ' . self::describe( $errors ) );
        }
    }

    public function test_all_field_types_model_has_one_of_each_type() {
        $model = self::conformance( 'valid/all-field-types.json' );
        $types = array();
        foreach ( BAF_Form_Model::fields( $model ) as $field ) {
            $types[ $field['type'] ] = true;
        }
        foreach ( BAF_Form_Model::FIELD_TYPES as $type ) {
            $this->assertArrayHasKey( $type, $types, 'missing an example of ' . $type );
        }
    }

    public function test_every_invalid_conformance_model_is_rejected_at_its_path() {
        foreach ( self::conformance( 'invalid/cases.json' )['cases'] as $case ) {
            $errors = BAF_Form_Model::validate( $case['model'] );
            $this->assertNotEmpty( $errors, $case['name'] . ' should be rejected' );
            $hit = false;
            foreach ( $errors as $e ) {
                if ( $e['path'] === $case['path'] && strpos( $e['message'], $case['contains'] ) !== false ) {
                    $hit = true;
                }
            }
            $this->assertTrue( $hit, $case['name'] . ': expected ' . $case['path'] . ' "' . $case['contains'] . '", got ' . self::describe( $errors ) );
        }
    }

    public function test_scoring_and_visibility_behaviour() {
        $spec = self::conformance( 'behaviour.json' );
        foreach ( $spec['cases'] as $case ) {
            $model = self::conformance( $case['model'] );
            $extra = array( 'today' => $spec['today'] );
            if ( isset( $case['scores'] ) ) {
                $score = BAF_Form_Model::score( $model, $case['answers'] );
                $this->assertEquals( $case['scores'], $score['fields'], $case['name'] . ' scores' );
                $this->assertEquals( $case['total'], $score['total'], $case['name'] . ' total' );
                $outcome = BAF_Form_Model::outcome( $model, $case['answers'], $extra );
                $this->assertSame( $case['outcome'], $outcome['id'] ?? null, $case['name'] . ' outcome' );
            }
            foreach ( $case['shown'] ?? array() as $id ) {
                $this->assertTrue( BAF_Form_Model::is_shown( $model, $id, $case['answers'], $extra ), $case['name'] . ': ' . $id . ' should be shown' );
            }
            foreach ( $case['hidden'] ?? array() as $id ) {
                $this->assertFalse( BAF_Form_Model::is_shown( $model, $id, $case['answers'], $extra ), $case['name'] . ': ' . $id . ' should be hidden' );
            }
        }
    }

    public function test_sentence_uses_labels_and_choice_labels() {
        $model = self::conformance( 'valid/logic-and-layout.json' );
        $this->assertSame( 'Joining as is A business or Joining as is A charity', BAF_Form_Model::sentence( $model, '#type is "business" or #type is "charity"' ) );
    }

    public function test_create_gives_a_valid_empty_form() {
        $this->assertSame( array(), BAF_Form_Model::validate( BAF_Form_Model::create( 'New form' ) ) );
    }

    public function test_slug_matches_sanitize_title_for_plain_labels() {
        $this->assertSame( 'audio-description', BAF_Form_Model::slug( 'Audio description' ) );
        $this->assertSame( 'yes-please', BAF_Form_Model::slug( '  Yes, please! ' ) );
        $this->assertSame( '', BAF_Form_Model::slug( '!!!' ) );
    }

    public function test_same_ignores_key_order_but_not_types() {
        $this->assertTrue( BAF_Form_Model::same( array( 'a' => 1, 'b' => array( 'x' => 1, 'y' => 2 ) ), array( 'b' => array( 'y' => 2, 'x' => 1 ), 'a' => 1 ) ) );
        $this->assertFalse( BAF_Form_Model::same( array( 'a' => 1 ), array( 'a' => '1' ) ) );
        $this->assertFalse( BAF_Form_Model::same( array( 1, 2 ), array( 2, 1 ) ) );
    }

    public function test_to_json_keeps_empty_objects_as_objects() {
        $json = BAF_Form_Model::to_json( array( 'version' => 1, 'title' => 'T', 'settings' => array(), 'pages' => array( array( 'id' => 'page_1', 'items' => array() ) ) ) );
        $this->assertStringContainsString( '"settings": {}', $json );
        $this->assertStringContainsString( '"items": []', $json );
    }
}
