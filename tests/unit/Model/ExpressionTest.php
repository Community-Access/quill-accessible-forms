<?php
/**
 * Expression language tests, driven by the shared conformance cases.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-form-model.php';

class BAF_Expression_Test extends BAF_Unit_Test_Case {

    private static function cases() {
        return json_decode( file_get_contents( BAF_PLUGIN_DIR . 'tests/conformance/expressions/cases.json' ), true );
    }

    public function test_conformance_parse_cases() {
        $cases = self::cases();
        foreach ( $cases['parse'] as $case ) {
            $ast = BAF_Expression::parse( $case['text'] );
            $this->assertSame( $case['canonical'], BAF_Expression::to_text( $ast ), 'canonical text of ' . $case['text'] );
            // Canonical text is a fixed point.
            $this->assertSame( $case['canonical'], BAF_Expression::canonical( $case['canonical'] ), 'canonical is stable for ' . $case['text'] );
            if ( isset( $case['ast'] ) ) {
                $this->assertTrue( BAF_Form_Model::same( $case['ast'], $ast ), 'AST of ' . $case['text'] . ': ' . json_encode( $ast ) );
            }
            if ( isset( $case['sentence'] ) ) {
                $this->assertSame( $case['sentence'], BAF_Expression::to_sentence( $ast, $cases['labels'], $cases['choices'] ), 'sentence of ' . $case['text'] );
            }
        }
    }

    public function test_conformance_error_cases() {
        foreach ( self::cases()['errors'] as $case ) {
            $this->assertNotNull( BAF_Expression::check( $case['text'] ), 'should be rejected: ' . $case['text'] );
        }
    }

    public function test_conformance_evaluate_cases() {
        $cases = self::cases();
        foreach ( $cases['evaluate'] as $case ) {
            $result = BAF_Expression::evaluate( $case['text'], $cases['context'] );
            if ( is_int( $case['result'] ) || is_float( $case['result'] ) ) {
                $this->assertTrue( is_int( $result ) || is_float( $result ), $case['text'] . ' should be a number, got ' . json_encode( $result ) );
                $this->assertEqualsWithDelta( $case['result'], $result, 1e-9, $case['text'] );
            } else {
                $this->assertSame( $case['result'], $result, $case['text'] );
            }
        }
    }

    public function test_refs_lists_each_field_once_in_order() {
        $ast = BAF_Expression::parse( '#b is 1 or #a is 2 or score(#b) > 1' );
        $this->assertSame( array( 'b', 'a' ), BAF_Expression::refs( $ast ) );
    }

    public function test_error_reports_offset() {
        try {
            BAF_Expression::parse( '#a is 1 #b' );
            $this->fail( 'expected an error' );
        } catch ( BAF_Expression_Error $e ) {
            $this->assertSame( 8, $e->offset );
        }
    }

    public function test_bare_words_read_as_text_for_old_bfl() {
        $this->assertTrue( BAF_Expression::evaluate( '#pay is Email', array( 'values' => array( 'pay' => 'Email' ) ) ) );
    }

    public function test_reserved_word_is_not_a_value() {
        $this->assertNotNull( BAF_Expression::check( '#a is or' ) );
    }

    public function test_today_defaults_to_the_current_date() {
        $this->assertSame( gmdate( 'Y-m-d' ), BAF_Expression::evaluate( 'today()' ) );
    }
}
