<?php
/**
 * Imports from Gravity Forms, WPForms, Contact Form 7, Ninja Forms and
 * Google Forms. The fixtures are in tests/conformance/imports.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-importers.php';

class QAF_Importers_Test extends QAF_Unit_Test_Case {

    private static function import( $source, $file ) {
        return QAF_Importers::import( $source, file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/imports/' . $source . '/' . $file ) );
    }

    private static function messages( $report, $level ) {
        return implode( "\n", array_column( $report->of( $level ), 'message' ) );
    }

    public function test_every_fixture_converts_to_its_expected_model_and_report() {
        foreach ( glob( QAF_PLUGIN_DIR . 'tests/conformance/imports/*/*' ) as $file ) {
            if ( preg_match( '/\.(model|report)\.json$/', $file ) ) {
                continue;
            }
            $source = basename( dirname( $file ) );
            $stem   = preg_replace( '/\.(json|txt)$/', '', $file );
            $result = QAF_Importers::import( $source, file_get_contents( $file ) );
            $this->assertTrue( QAF_Form_Model::same( json_decode( file_get_contents( $stem . '.model.json' ), true ), $result['model'] ), basename( $file ) );
            $this->assertSame( array(), QAF_Form_Model::validate( $result['model'] ), basename( $file ) );
            $this->assertSame( json_decode( file_get_contents( $stem . '.report.json' ), true ), $result['report']->to_array(), basename( $file ) . ' report' );
        }
    }

    public function test_gravity_forms_mapping() {
        $r = self::import( 'gravity-forms', 'event-registration.json' );
        $m = $r['model'];
        $f = QAF_Form_Model::fields( $m );
        $this->assertSame( 'Register', $m['settings']['submit_label'] );
        $this->assertSame( array( 'You', 'Your visit' ), array_column( $m['pages'], 'title' ) );
        $this->assertSame( 'Next', $m['pages'][0]['props']['next_button_text'] );
        $this->assertSame( '#field_4 is "speaker"', $f['field_5']['when'] );
        $this->assertSame( '#field_11 * 140', $f['field_12']['calculation'] );
        $this->assertSame( 'calculation', $f['field_12']['type'] );
        $this->assertSame( 'product', $f['field_13']['type'] );
        $this->assertSame( 45, $f['field_13']['choices'][1]['price'] );
        $this->assertSame( 'today()', $f['field_10']['smart_default'] );
        // The section holds the fields after it, up to the page break, and hides for exhibitors.
        $section = $m['pages'][0]['items'][5];
        $this->assertSame( '#field_4 is not "exhibitor"', $section['when'] );
        $this->assertSame( array( 'field_7', 'field_8' ), array_column( $section['items'], 'id' ) );
        $this->assertStringContainsString( 'coupon', self::messages( $r['report'], 'dropped' ) );
        $this->assertStringContainsString( 'routing', self::messages( $r['report'], 'dropped' ) );
    }

    public function test_wpforms_mapping() {
        $r = self::import( 'wpforms', 'contact.json' );
        $f = QAF_Form_Model::fields( $r['model'] );
        // A choice condition names the choice by key; the model names it by value.
        $this->assertSame( '#field_2 is "Support"', $f['field_3']['when'] );
        $this->assertSame( '#field_6 is "Phone" or #field_2 is "Sales" and #field_1 is not empty', $f['field_7']['when'] );
        $this->assertSame( array( 'max_length' => 500 ), $f['field_5']['validation'] );
        $this->assertSame( 'Continue', $r['model']['pages'][0]['props']['next_button_text'] );
        $this->assertSame( '{field_1}', $r['model']['notifications'][0]['reply_to'] );
        $this->assertSame( '#field_2 is "press"', $r['model']['confirmations'][1]['when'] );
    }

    public function test_contact_form_7_mapping() {
        $r = self::import( 'contact-form-7', 'default-form.json' );
        $f = QAF_Form_Model::fields( $r['model'] );
        $this->assertSame( 'Your name', $f['your_name']['label'] );
        $this->assertTrue( $f['your_name']['required'] );
        $this->assertSame( array( 'support', 'sales', 'Press' ), array_column( $f['department']['choices'], 'value' ) );
        $this->assertTrue( $f['heard']['choices'][1]['selected'] );
        $this->assertSame( array( 'correct' => array( '2' ) ), $f['human']['scoring'] );
        $this->assertSame( 'I agree to the privacy policy.', $f['terms']['props']['consent_text'] );
        $this->assertSame( 'From: {your_name} <{your_email}>' . "\n\n" . '{your_message}', $r['model']['notifications'][0]['message'] );
        $this->assertSame( 'Thank you for your message. It has been sent.', $r['model']['confirmations'][0]['message'] );
        $this->assertStringContainsString( 'no label', self::messages( $r['report'], 'note' ) );
    }

    public function test_ninja_forms_mapping() {
        $r = self::import( 'ninja-forms', 'quote-request.json' );
        $f = QAF_Form_Model::fields( $r['model'] );
        $this->assertSame( '#rooms_1 * 120 + #extras_2', $f['estimate']['calculation'] );
        $this->assertSame( 60, $f['extras_2']['choices'][1]['points'] );
        $this->assertSame( 'countries', $f['country']['choice_library'] );
        $this->assertSame( '(999) 999-9999', $f['phone']['mask'] );
        $this->assertSame( '{email}', $r['model']['notifications'][0]['to'] );
        $this->assertStringContainsString( 'Conditional Logic', self::messages( $r['report'], 'dropped' ) );
    }

    public function test_google_forms_mapping() {
        $r = self::import( 'google-forms', 'braille-quiz.json' );
        $m = $r['model'];
        $f = QAF_Form_Model::fields( $m );
        $this->assertSame( array( 'correct' => array( 'Six' ), 'points' => 2, 'feedback_correct' => 'Right.', 'feedback_incorrect' => 'Not quite.' ), $f['q_4d5e']['scoring'] );
        $this->assertSame( 'matrix', $f['q_55ee']['type'] );
        $this->assertSame( 'nps', $f['q_8a8a']['type'] );
        $this->assertSame( array( array( 'action' => 'skip_to', 'targets' => array( 'page_2' ), 'when' => '#q_3c4d is "No"' ) ), $m['rules'] );
        $this->assertTrue( $m['scoring']['show_score'] );
        $this->assertSame( 10, $f['q_c5d6']['props']['max_size'] );
        // A quiz answered fully right scores the points Google gave it.
        $this->assertSame( 4, QAF_Form_Model::score( $m, array( 'q_4d5e' => 'Six', 'q_5e6f' => array( 'UEB', 'Nemeth' ) ) )['total'] );
    }

    public function test_unknown_source_and_bad_json_are_refused() {
        try {
            QAF_Importers::import( 'typeform', '{}' );
            $this->fail( 'expected an error' );
        } catch ( InvalidArgumentException $e ) {
            $this->assertStringContainsString( 'typeform', $e->getMessage() );
        }
        try {
            QAF_Importers::import( 'gravity-forms', 'not json' );
            $this->fail( 'expected an error' );
        } catch ( InvalidArgumentException $e ) {
            $this->assertStringContainsString( 'not JSON', $e->getMessage() );
        }
    }
}
