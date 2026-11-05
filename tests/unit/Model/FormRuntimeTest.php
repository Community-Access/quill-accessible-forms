<?php
/**
 * What a published form takes from its model: language, translation, the
 * part of the model the browser runs, and the quiz outcome on sending.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model-converter.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-runtime.php';

class QAF_Form_Runtime_Test extends QAF_Unit_Test_Case {

    private static function model( $name ) {
        return json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/' . $name . '.json' ), true );
    }

    public function test_language_prefers_the_request_then_the_locale_then_the_form() {
        $m = self::model( 'quiz-and-translations' );
        $this->assertSame( 'es', QAF_Form_Runtime::language( $m, 'es', 'fr_FR' ) );
        $this->assertSame( 'fr', QAF_Form_Runtime::language( $m, '', 'fr_CA' ) );
        $this->assertSame( 'fr', QAF_Form_Runtime::language( $m, 'de', 'fr_FR' ) );
        $this->assertSame( '', QAF_Form_Runtime::language( $m, '', 'en_GB' ), 'the form is written in English' );
        $this->assertSame( '', QAF_Form_Runtime::language( $m, 'de', 'it_IT' ) );
        $this->assertSame( '', QAF_Form_Runtime::language( self::model( 'logic-and-layout' ), 'es', 'es_ES' ), 'no translations' );
    }

    public function test_translate_config_changes_labels_help_choices_and_pages() {
        $m      = self::model( 'quiz-and-translations' );
        $stored = QAF_Form_Model_Converter::to_legacy( $m );
        $config = QAF_Form_Runtime::translate_config( $stored['config'], $m, 'es' );
        $by_id  = array_column( $config['fields'], null, 'id' );
        $this->assertSame( 'Preguntas', $config['pages'][0]['title'] );
        $this->assertSame( 'Seis', $by_id['q1']['choices'][1]['label'] );
        $this->assertSame( 'Una sola palabra.', $by_id['q3']['description'] );
        $this->assertSame( 'Apellido', $by_id['q3']['placeholder'] );
        $this->assertSame( 'Which are braille codes?', $by_id['q2']['label'], 'missing text falls back' );
        $this->assertSame( $stored['config'], QAF_Form_Runtime::translate_config( $stored['config'], $m, '' ) );
        $this->assertSame( 'Cuestionario de braille', QAF_Form_Runtime::text( $m, 'es', 'title', 'x' ) );
        $this->assertSame( 'Send', QAF_Form_Runtime::text( $m, 'es', 'submit', 'Send' ) );
    }

    public function test_the_page_gets_no_answers_addresses_or_settings() {
        $m      = self::model( 'quiz-and-translations' );
        $public = QAF_Form_Runtime::public_model( $m );
        $json   = json_encode( $public );
        foreach ( array( 'scoring', 'correct', 'outcomes', 'confirmations', 'translations', 'label', 'explanation' ) as $word ) {
            $this->assertStringNotContainsString( '"' . $word . '"', $json, $word );
        }
        $this->assertSame( array( 'value' => '6' ), $public['pages'][0]['items'][0]['choices'][1] );
        $this->assertArrayNotHasKey( 'points', $public['pages'][0]['items'][3]['choices'][0] );

        $l    = self::model( 'logic-and-layout' );
        $json = json_encode( QAF_Form_Runtime::public_model( $l ) );
        $this->assertStringNotContainsString( 'example.org', $json, 'notifications stay on the server' );
        $this->assertStringContainsString( '"skip_to"', $json );
        $this->assertStringContainsString( 'round(#members * #fee - #discount, 2)', $json );
    }

    public function test_the_public_model_still_decides_what_is_shown() {
        $l       = self::model( 'logic-and-layout' );
        $public  = QAF_Form_Runtime::public_model( $l );
        $answers = array( 'type' => 'charity', 'company' => 'Helping Hands', 'contact_by' => 'post' );
        foreach ( QAF_Form_Model::items( $l ) as $entry ) {
            $id = $entry['item']['id'];
            $this->assertSame( QAF_Form_Model::is_shown( $l, $id, $answers ), QAF_Form_Model::is_shown( $public, $id, $answers ), $id );
        }
    }

    public function test_only_the_user_facts_the_form_asks_for_reach_the_page() {
        $l = self::model( 'logic-and-layout' );
        $this->assertSame( array( 'first_name' ), QAF_Form_Runtime::user_keys( $l ) );
        $user    = array( 'first_name' => 'Jeff', 'email' => 'jeff@example.org', 'id' => 3 );
        $payload = QAF_Form_Runtime::payload( $l, '2026-11-04', $user );
        $this->assertEquals( (object) array( 'first_name' => 'Jeff' ), $payload['context']['user'] );
        $this->assertSame( '2026-11-04', $payload['context']['today'] );
        $this->assertSame( '{}', json_encode( QAF_Form_Runtime::payload( $l, '2026-11-04' )['context']['user'] ) );
    }

    public function test_outcome_is_chosen_on_the_server_and_translated() {
        $m   = self::model( 'quiz-and-translations' );
        $all = array( 'q1' => '6', 'q2' => array( 'nemeth', 'ueb' ), 'q3' => ' braille ', 'feel' => 'high' );
        $this->assertSame( array( 'id' => 'top', 'message' => 'Top marks.', 'score' => 7 ), QAF_Form_Runtime::outcome( $m, $all ) );
        $out = QAF_Form_Runtime::outcome( $m, array(), 'es' );
        $this->assertSame( 'retry', $out['id'] );
        $this->assertSame( 'Inténtelo de nuevo.', $out['message'] );
        $this->assertSame( 'Top marks.', QAF_Form_Runtime::outcome( $m, $all, 'es' )['message'], 'untranslated outcome keeps its own text' );
        $this->assertNull( QAF_Form_Runtime::outcome( self::model( 'logic-and-layout' ), array() ) );
    }

    public function test_renderer_gives_each_page_its_fields() {
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', QAF_PLUGIN_DIR );
        }
        require_once QAF_PLUGIN_DIR . 'public/class-qaf-form-renderer.php';
        $stored = QAF_Form_Model_Converter::to_legacy( self::model( 'logic-and-layout' ) );
        $pages  = QAF_Form_Renderer::fill_pages( $stored['config']['pages'], $stored['config']['fields'] );
        $this->assertCount( 3, $pages );
        $this->assertSame( 'type', $pages[0]['fields'][0]['id'] );
        $this->assertSame( array( 'children', 'start', 'renewal' ), array_column( $pages[1]['fields'], 'id' ) );
        $this->assertSame( 'members', $pages[2]['fields'][0]['id'] );

        $given = array( array( 'id' => 'p', 'fields' => array( array( 'id' => 'a' ) ) ) );
        $this->assertSame( $given, QAF_Form_Renderer::fill_pages( $given, array( array( 'id' => 'b', 'page' => 'p' ) ) ) );
        $lost = QAF_Form_Renderer::fill_pages( array( array( 'id' => 'p1' ), array( 'id' => 'p2' ) ), array( array( 'id' => 'a', 'page' => 'gone' ), array( 'id' => 'b' ) ) );
        $this->assertSame( array( 'a', 'b' ), array_column( $lost[0]['fields'], 'id' ) );
        $this->assertSame( array(), $lost[1]['fields'] );
    }
}
