<?php
/**
 * What the Visual mode's field cards do, on the model: add, duplicate,
 * delete, reorder, and a question's settings.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-form-model.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-builder-field-ops.php';

class BAF_Builder_Field_Ops_Test extends BAF_Unit_Test_Case {

    private static function model( $name ) {
        return json_decode( file_get_contents( BAF_PLUGIN_DIR . 'tests/conformance/models/valid/' . $name ), true );
    }

    private static function ids( $model ) {
        return array_values( array_filter( array_map( function ( $e ) { return $e['item']['id'] ?? null; }, BAF_Form_Model::items( $model ) ) ) );
    }

    public function test_adding_any_field_type_gives_a_valid_model() {
        $schema = json_decode( file_get_contents( BAF_PLUGIN_DIR . 'schema/form.schema.json' ), true );
        $types  = $schema['$defs']['field_type']['enum'] ?? array();
        $this->assertGreaterThan( 40, count( $types ) );
        foreach ( $types as $type ) {
            list( $m, $id ) = BAF_Builder_Field_Ops::add( self::model( 'quiz-and-translations.json' ), $type, 'New ' . $type );
            $this->assertSame( array(), BAF_Form_Model::validate( $m ), $type );
            $this->assertSame( $id, end( $m['pages'][0]['items'] )['id'], $type . ': added at the end of the last page' );
        }
    }

    public function test_added_ids_are_new_and_well_formed() {
        $m = self::model( 'quiz-and-translations.json' );
        list( $m, $a ) = BAF_Builder_Field_Ops::add( $m, 'text', 'Name' );
        list( $m, $b ) = BAF_Builder_Field_Ops::add( $m, 'text', 'Name' );
        $this->assertSame( array( 'text', 'text_2' ), array( $a, $b ) );
        $this->assertSame( 'q_9lives', BAF_Builder_Field_Ops::unique_id( $m, '9lives' ) );
        $this->assertSame( 'page_1_2', BAF_Builder_Field_Ops::unique_id( $m, 'page_1' ), 'page IDs are taken too' );
    }

    public function test_duplicate_goes_just_after_with_new_ids() {
        $m = self::model( 'logic-and-layout.json' );
        $repeater = null;
        foreach ( BAF_Form_Model::items( $m ) as $e ) {
            if ( ( $e['item']['type'] ?? '' ) === 'repeater' ) {
                $repeater = $e;
                break;
            }
        }
        $this->assertNotNull( $repeater, 'the shared model has a repeater' );
        list( $m2, $copy ) = BAF_Builder_Field_Ops::duplicate( $m, $repeater['item']['id'], ' (copy)' );
        $segments = explode( '/', ltrim( $repeater['path'], '/' ) );
        $index    = (int) array_pop( $segments );
        $list     = $m2;
        foreach ( $segments as $s ) {
            $list = $list[ ctype_digit( $s ) ? (int) $s : $s ];
        }
        $this->assertSame( $repeater['item']['id'], $list[ $index ]['id'] );
        $this->assertSame( $copy, $list[ $index + 1 ]['id'], 'the copy follows the original' );
        $inner_old = array_column( $repeater['item']['items'], 'id' );
        $inner_new = array_column( $list[ $index + 1 ]['items'], 'id' );
        $this->assertSame( count( $inner_old ), count( $inner_new ) );
        $this->assertSame( array(), array_intersect( $inner_old, $inner_new ), 'the copy\'s own questions have new IDs' );
        $after = self::ids( $m2 );
        $this->assertSame( count( $after ), count( array_unique( $after ) ), 'every ID is still unique' );
        $this->assertSame( array(), BAF_Form_Model::validate( $m2 ) );
        $this->assertNull( BAF_Builder_Field_Ops::duplicate( $m, 'nope', '' ) );
    }

    public function test_duplicate_changes_the_label_and_nothing_else() {
        $m = self::model( 'quiz-and-translations.json' );
        list( $m2, $copy ) = BAF_Builder_Field_Ops::duplicate( $m, 'q1', ' (copy)' );
        $this->assertSame( 'q1_2', $copy );
        $this->assertSame( 'How many dots are in a braille cell? (copy)', $m2['pages'][0]['items'][1]['label'] );
        $this->assertSame( $m['pages'][0]['items'][0]['choices'], $m2['pages'][0]['items'][1]['choices'] );
        $this->assertSame( $m['translations'], $m2['translations'] );
    }

    public function test_remove_takes_out_only_that_question() {
        $m  = self::model( 'quiz-and-translations.json' );
        $m2 = BAF_Builder_Field_Ops::remove( $m, 'q2' );
        $this->assertSame( array( 'q1', 'q3', 'feel' ), self::ids( $m2 ) );
        $this->assertSame( $m['scoring'], $m2['scoring'] );
        $this->assertNull( BAF_Builder_Field_Ops::remove( $m, 'nope' ) );
    }

    public function test_a_question_a_rule_names_cannot_be_deleted_silently() {
        $m = self::model( 'logic-and-layout.json' );
        $named = $m['rules'][0]['targets'][0];
        $m2 = BAF_Builder_Field_Ops::remove( $m, $named );
        $this->assertNotSame( array(), BAF_Form_Model::validate( $m2 ), 'validate() refuses the save, so the screen can say why' );
    }

    public function test_reorder_moves_among_neighbours_only() {
        $m  = self::model( 'quiz-and-translations.json' );
        $m2 = BAF_Builder_Field_Ops::reorder( $m, array( 'q2', 'q1', 'q3', 'feel' ) );
        $this->assertSame( array( 'q2', 'q1', 'q3', 'feel' ), self::ids( $m2 ) );
        $this->assertSame( array( 'q2', 'q1', 'q3', 'feel' ), BAF_Builder_Field_Ops::order( $m2, array( 'q1', 'q2', 'q3', 'feel' ) ) );

        // Two pages: a question asked to move onto the other page stays put.
        $m['pages'][] = array( 'id' => 'p2', 'items' => array( array( 'id' => 'extra', 'type' => 'text', 'label' => 'Extra' ) ) );
        $want = array( 'extra', 'q1', 'q2', 'q3', 'feel' );
        $m3   = BAF_Builder_Field_Ops::reorder( $m, $want );
        $this->assertSame( array( 'q1', 'q2', 'q3', 'feel', 'extra' ), BAF_Builder_Field_Ops::order( $m3, $want ) );
    }

    public function test_settings_change_only_their_four_keys() {
        $m  = self::model( 'quiz-and-translations.json' );
        $m2 = BAF_Builder_Field_Ops::apply_settings( $m, 'q3', array( 'label' => ' Surname ', 'help' => '', 'placeholder' => 'Braille', 'required' => '1' ) );
        $q3 = $m2['pages'][0]['items'][2];
        $this->assertSame( 'Surname', $q3['label'] );
        $this->assertArrayNotHasKey( 'help', $q3 );
        $this->assertSame( 'Braille', $q3['placeholder'] );
        $this->assertTrue( $q3['required'] );
        $this->assertSame( $m['pages'][0]['items'][2]['scoring'], $q3['scoring'] );
        $m3 = BAF_Builder_Field_Ops::apply_settings( $m2, 'q3', array( 'required' => '' ) );
        $this->assertArrayNotHasKey( 'required', $m3['pages'][0]['items'][2] );
        $this->assertSame( 'Braille', $m3['pages'][0]['items'][2]['placeholder'], 'a key not sent is kept' );
        $this->assertNull( BAF_Builder_Field_Ops::apply_settings( $m, 'nope', array() ) );
        $this->assertSame( 'label', BAF_Builder_Field_Ops::check_settings( array( 'label' => '  ' ) )[0]['field'] );
        $this->assertSame( array(), BAF_Builder_Field_Ops::check_settings( array( 'help' => '' ) ) );
    }

    public function test_settings_in_a_section_are_found() {
        $m = self::model( 'logic-and-layout.json' );
        foreach ( BAF_Form_Model::items( $m ) as $e ) {
            if ( substr_count( $e['path'], '/items/' ) > 1 && isset( $e['item']['id'] ) ) {
                $m2 = BAF_Builder_Field_Ops::apply_settings( $m, $e['item']['id'], array( 'label' => 'Inner' ) );
                foreach ( BAF_Form_Model::items( $m2 ) as $e2 ) {
                    if ( $e2['item']['id'] === $e['item']['id'] ) {
                        $this->assertSame( 'Inner', $e2['item']['label'] );
                        $this->assertSame( $e['path'], $e2['path'] );
                    }
                }
                return;
            }
        }
        $this->fail( 'the shared model has no nested question' );
    }
}
