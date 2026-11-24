<?php
/**
 * Convert rewrites the conditions that read a question when its answers
 * change kind, and flags the ones it cannot rewrite.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-field-ops.php';

class QAF_Condition_Rewriter_Test extends QAF_Unit_Test_Case {

    private static function model() {
        return json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/logic-and-layout.json' ), true );
    }

    public function test_a_choice_becoming_typed_compares_with_the_choice_label() {
        $m = self::model();
        list( $a, $said ) = QAF_Builder_Field_Ops::convert( $m, 'contact_by', 'text' );
        $fields = QAF_Form_Model::fields( $a );
        $postal = null;
        foreach ( QAF_Form_Model::items( $a ) as $e ) {
            if ( $e['item']['id'] === 'postal' ) {
                $postal = $e['item'];
            }
        }
        $this->assertSame( '#contact_by is "Post"', $postal['when'] );
        $this->assertSame( array(), QAF_Form_Model::validate( $a ) );
        $this->assertStringContainsString( 'was rewritten', implode( ' ', $said ) );
        $this->assertArrayHasKey( 'contact_by', $fields );
    }

    public function test_a_choice_from_the_forms_library_is_found_too() {
        $m = self::model();
        list( $a, $said ) = QAF_Builder_Field_Ops::convert( $m, 'type', 'text' );
        $fields = QAF_Form_Model::fields( $a );
        $this->assertSame( '#type is "A business" or #type is "A charity"', $fields['company']['when'] );
        $this->assertSame( '#type is one of ["A business", "A charity"] and #company is not empty', $fields['staff']['when'] );
        $this->assertSame( '#type is "An individual"', $a['pages'][1]['when'] );
        $this->assertSame( array(), QAF_Form_Model::validate( $a ) );
    }

    public function test_typed_becoming_a_choice_finds_the_choice_by_its_label() {
        $m = self::model();
        // Make "type" typed first, then a choice again with choices whose labels match.
        list( $typed, ) = QAF_Builder_Field_Ops::convert( $m, 'type', 'text' );
        $before = QAF_Form_Model::fields( $typed )['type'];
        $after  = array_merge( $before, array(
            'type'    => 'radio',
            'choices' => array(
                array( 'value' => 'biz', 'label' => 'A business' ),
                array( 'value' => 'ind', 'label' => 'An individual' ),
            ),
        ) );
        list( $back, $rewritten, $flagged ) = QAF_Condition_Rewriter::rewrite( $typed, 'type', $before, $after, QAF_Builder_Field_Ops::CHOICE_TYPES );
        $fields = QAF_Form_Model::fields( $back );
        $this->assertSame( '#type is "biz" or #type is "A charity"', $fields['company']['when'] );
        $this->assertSame( '#type is "ind"', $back['pages'][1]['when'] );
        $this->assertNotEmpty( $rewritten );
        $reasons = array_column( $flagged, 'reason' );
        $this->assertStringContainsString( '"A charity", which is not one of its choices now', implode( ' ', $reasons ), 'no choice is labelled A charity: flagged, not guessed' );
    }

    public function test_a_number_becoming_text_flags_its_comparisons() {
        $m = self::model();
        $m['pages'][0]['items'][] = array( 'id' => 'big', 'type' => 'html', 'when' => '#staff > 50', 'props' => array( 'content' => '<p>Big.</p>' ) );
        list( $a, $said ) = QAF_Builder_Field_Ops::convert( $m, 'staff', 'text' );
        $this->assertSame( '#staff > 50', QAF_Form_Model::fields( $a )['big']['when'], 'flagged, never rewritten' );
        $this->assertStringContainsString( 'Check this condition: #staff > 50.', implode( ' ', $said ) );
        $this->assertStringContainsString( 'no longer one', implode( ' ', $said ) );
    }

    public function test_a_question_becoming_one_with_no_answer_flags_what_reads_it() {
        $m = self::model();
        list( , $said ) = QAF_Builder_Field_Ops::convert( $m, 'company', 'html' );
        $this->assertStringContainsString( 'has no answer to read', implode( ' ', $said ) );
    }

    public function test_choices_to_choices_and_unrelated_conditions_are_left_alone() {
        $m = self::model();
        list( $a, $said ) = QAF_Builder_Field_Ops::convert( $m, 'contact_by', 'radio' );
        $this->assertSame( array(), $said );
        $this->assertSame( json_encode( $m['pages'][1] ), json_encode( $a['pages'][1] ) );
        list( $b, $rewritten, $flagged ) = QAF_Condition_Rewriter::rewrite( $m, 'first', array( 'type' => 'text' ), array( 'type' => 'email' ), QAF_Builder_Field_Ops::CHOICE_TYPES );
        $this->assertSame( array( array(), array() ), array( $rewritten, $flagged ) );
        $this->assertSame( $m, $b );
    }

    public function test_the_dry_run_and_the_conversion_say_the_same() {
        // ajax_convert_question's dry run calls convert() too: the dialog says
        // before the change exactly what the change does.
        $src = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php' );
        $this->assertMatchesRegularExpression( '/function ajax_convert_question.*QAF_Builder_Field_Ops::convert\(.*dry_run/s', $src );
    }
}
