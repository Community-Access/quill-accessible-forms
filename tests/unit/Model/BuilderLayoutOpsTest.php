<?php
/**
 * The canvas's actions for sections and columns, on the model.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-field-ops.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-layout-ops.php';

class QAF_Builder_Layout_Ops_Test extends QAF_Unit_Test_Case {

    private static function model() {
        return json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/logic-and-layout.json' ), true );
    }

    private static function ids( $model ) {
        return array_values( array_filter( array_map( function ( $e ) { return $e['item']['id'] ?? null; }, QAF_Form_Model::items( $model ) ) ) );
    }

    /** The questions only (no sections or columns), in order. */
    private static function questions( $model ) {
        return array_keys( QAF_Form_Model::fields( $model ) );
    }

    public function test_add_a_section_and_columns_at_the_end_or_after_an_item() {
        $m = self::model();
        list( $a, $sid ) = QAF_Builder_Layout_Ops::add_section( $m, 'Extras' );
        $this->assertSame( array(), QAF_Form_Model::validate( $a ) );
        $last = end( $a['pages'] );
        $this->assertSame( array( 'type' => 'section', 'id' => $sid, 'label' => 'Extras', 'items' => array() ), end( $last['items'] ) );

        list( $b, $cid ) = QAF_Builder_Layout_Ops::add_columns( $m, 3, 'company' );
        $this->assertSame( array(), QAF_Form_Model::validate( $b ) );
        $this->assertSame( $cid, $b['pages'][0]['items'][2]['id'], 'just after Company name' );
        $this->assertCount( 3, $b['pages'][0]['items'][2]['columns'] );
        $this->assertNotSame( 'names', $cid, 'a new ID' );

        list( $c, ) = QAF_Builder_Layout_Ops::add_columns( $m, 9 );
        $this->assertCount( QAF_Builder_Layout_Ops::MAX_COLUMNS, end( end( $c['pages'] )['items'] )['columns'] );
        list( $d, $nid ) = QAF_Builder_Layout_Ops::add_section( $m, '  ' );
        $this->assertSame( 'New section', end( end( $d['pages'] )['items'] )['label'], 'a section always has a heading' );
    }

    public function test_move_a_group_among_its_neighbours_only() {
        $m = self::model();
        list( $a, $pos, $count ) = QAF_Builder_Layout_Ops::move( $m, 'contact', 'up' );
        $this->assertSame( 'contact', $a['pages'][0]['items'][3]['id'] );
        $this->assertSame( 'names', $a['pages'][0]['items'][4]['id'] );
        $this->assertSame( array( 4, 6 ), array( $pos, $count ) );
        // A nested section stays in its section.
        list( $b, $pos ) = QAF_Builder_Layout_Ops::move( $m, 'postal', 'down' );
        $this->assertSame( 3, $pos, 'already last in its section: it stays' );
        $this->assertSame( $m, $b );
        $this->assertNull( QAF_Builder_Layout_Ops::move( $m, 'nope', 'up' ) );
    }

    public function test_duplicate_a_group_gives_everything_in_it_new_ids() {
        $m = self::model();
        list( $a, $copy ) = QAF_Builder_Layout_Ops::duplicate( $m, 'contact', ' (copy)' );
        $this->assertSame( array(), QAF_Form_Model::validate( $a ) );
        $ids = self::ids( $a );
        $this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'no ID used twice' );
        $this->assertSame( 'How to reach you (copy)', $a['pages'][0]['items'][5]['label'] );
        $this->assertSame( $copy, $a['pages'][0]['items'][5]['id'] );
        $this->assertCount( count( self::ids( $m ) ) + 6, $ids, 'the section, its three questions and the postal section with its two' );

        list( $b, ) = QAF_Builder_Layout_Ops::duplicate( $m, 'names', '' );
        $this->assertSame( array(), QAF_Form_Model::validate( $b ) );
        $this->assertNotSame( 'first', $b['pages'][0]['items'][4]['columns'][0]['items'][0]['id'] );

        $this->assertNotNull( QAF_Builder_Field_Ops::refused( QAF_Builder_Layout_Ops::duplicate( $m, 'company', '' ) ) );
    }

    public function test_remove_a_group_keeps_its_questions_in_its_place() {
        $m = self::model();
        list( $a, $kept ) = QAF_Builder_Layout_Ops::remove( $m, 'names' );
        $this->assertSame( 2, $kept );
        $this->assertSame( array( 'first', 'last' ), array( $a['pages'][0]['items'][3]['id'], $a['pages'][0]['items'][4]['id'] ) );
        $this->assertSame( self::questions( $m ), self::questions( $a ), 'not one question lost' );
        $this->assertSame( array(), QAF_Form_Model::validate( $a ) );

        // A section with a condition: its questions take the condition with them.
        list( $b, $kept, $notes ) = QAF_Builder_Layout_Ops::remove( $m, 'postal' );
        $this->assertSame( 2, $kept );
        $this->assertNotEmpty( $notes );
        $fields = QAF_Form_Model::fields( $b );
        $this->assertSame( '#contact_by is "post"', $fields['country']['when'] );
        $this->assertSame( array(), QAF_Form_Model::validate( $b ) );

        $this->assertNotNull( QAF_Builder_Field_Ops::refused( QAF_Builder_Layout_Ops::remove( $m, 'company' ) ), 'a question is deleted with its own button' );
        $this->assertNull( QAF_Builder_Layout_Ops::remove( $m, 'nope' ) );
    }

    public function test_columns_can_be_added_moved_and_removed_without_losing_questions() {
        $m = self::model();
        list( $a, $n ) = QAF_Builder_Layout_Ops::add_column( $m, 'names' );
        $this->assertSame( 3, $n );
        $this->assertSame( array(), QAF_Form_Model::validate( $a ) );

        list( $b, $pos, $count ) = QAF_Builder_Layout_Ops::move_column( $m, 'names', 0, 'right' );
        $this->assertSame( array( 2, 2 ), array( $pos, $count ) );
        $this->assertSame( 'last', $b['pages'][0]['items'][3]['columns'][0]['items'][0]['id'] );

        list( $c, $moved, $into ) = QAF_Builder_Layout_Ops::remove_column( $m, 'names', 1 );
        $this->assertSame( array( 1, 1 ), array( $moved, $into ) );
        $this->assertSame( array( 'first', 'last' ), array_column( $c['pages'][0]['items'][3]['columns'][0]['items'], 'id' ) );
        $this->assertSame( array(), QAF_Form_Model::validate( $c ) );

        list( $d, , $into ) = QAF_Builder_Layout_Ops::remove_column( $m, 'names', 0 );
        $this->assertSame( 1, $into, 'the first column joins the one after it' );
        $this->assertSame( array( 'first', 'last' ), array_column( $d['pages'][0]['items'][3]['columns'][0]['items'], 'id' ) );

        $this->assertNotNull( QAF_Builder_Field_Ops::refused( QAF_Builder_Layout_Ops::remove_column( $c, 'names', 0 ) ), 'the only column stays' );
        $this->assertNotNull( QAF_Builder_Field_Ops::refused( QAF_Builder_Layout_Ops::add_column( $m, 'company' ) ) );
        list( $full, ) = QAF_Builder_Layout_Ops::add_columns( $m, 4 );
        $last = end( end( $full['pages'] )['items'] );
        $this->assertNotNull( QAF_Builder_Field_Ops::refused( QAF_Builder_Layout_Ops::add_column( $full, $last['id'] ) ) );
    }
}
