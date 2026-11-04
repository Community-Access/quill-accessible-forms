<?php
/**
 * Storage: BAF's stored form (the builder format) and the form model.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-form-model-converter.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-bfl-writer.php';
require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-form.php';

class BAF_Model_Storage_Test extends BAF_Unit_Test_Case {

    private static function dir() {
        return BAF_PLUGIN_DIR . 'tests/conformance/';
    }

    private static function json( $path ) {
        return json_decode( file_get_contents( $path ), true );
    }

    /** Stored the way BAF_Form::save() stores it. */
    private static function as_stored( $stored ) {
        return json_decode( json_encode( $stored, JSON_PRESERVE_ZERO_FRACTION ), true );
    }

    private static function models() {
        $files = array_merge(
            glob( self::dir() . 'models/valid/*.json' ),
            array( self::dir() . 'bfl/canonical/edge-cases.json' ),
            glob( self::dir() . 'bfl/legacy/*.json' )
        );
        $out = array();
        foreach ( $files as $f ) {
            $out[ basename( $f ) ] = self::json( $f );
        }
        return $out;
    }

    public function test_model_to_builder_to_model_is_exact() {
        foreach ( self::models() as $name => $model ) {
            $stored = self::as_stored( BAF_Form_Model_Converter::to_legacy( $model ) );
            unset( $stored['config']['form_model'] );
            $back = BAF_Form_Model_Converter::from_legacy( $stored );
            $this->assertTrue( BAF_Form_Model::same( $model, $back['model'] ), $name );
            $this->assertTrue( $back['report']->is_complete(), $name . ': ' . $back['report']->to_text() );
        }
    }

    public function test_builder_to_model_to_builder_is_exact_for_what_the_model_wrote() {
        foreach ( self::models() as $name => $model ) {
            $stored = self::as_stored( BAF_Form_Model_Converter::to_legacy( $model ) );
            $again  = self::as_stored( BAF_Form_Model_Converter::to_legacy( BAF_Form_Model_Converter::from_legacy( $stored )['model'] ) );
            $this->assertTrue( BAF_Form_Model::same( $stored, $again ), $name );
        }
    }

    public function test_pinned_builder_shape() {
        $model = self::json( self::dir() . 'models/valid/logic-and-layout.json' );
        $this->assertTrue( BAF_Form_Model::same(
            self::json( self::dir() . 'builder/logic-and-layout.stored.json' ),
            self::as_stored( BAF_Form_Model_Converter::to_legacy( $model ) )
        ) );
    }

    public function test_form_saved_before_the_model_migrates_with_a_report() {
        $stored   = self::json( self::dir() . 'builder/saved-before-the-model.stored.json' );
        $expected = self::json( self::dir() . 'builder/saved-before-the-model.model.json' );
        $report   = self::json( self::dir() . 'builder/saved-before-the-model.report.json' );
        $result   = BAF_Form_Model_Converter::from_legacy( $stored );
        $this->assertTrue( BAF_Form_Model::same( $expected, $result['model'] ) );
        $this->assertSame( array(), BAF_Form_Model::validate( $result['model'] ) );
        $this->assertSame( $report['entries'], $result['report']->entries );
        $this->assertSame( $report['carried'], $result['report']->carried );
    }

    public function test_migration_details() {
        $stored = self::json( self::dir() . 'builder/saved-before-the-model.stored.json' );
        $model  = BAF_Form_Model_Converter::from_legacy( $stored )['model'];
        $f      = BAF_Form_Model::fields( $model );
        $this->assertSame( '#role is "driver" and #age > 17', $f['licence']['when'] );
        $this->assertSame( '#role is empty', $f['other_role']['when'] );
        $this->assertSame( '#hours * #rate', $f['claim']['calculation'] );
        $this->assertSame( 'sum(#hours, #rate)', $f['fancy']['calculation'] );
        $this->assertSame( 'today() + 3', $f['start']['smart_default'] );
        $this->assertSame( 'user("user_email")', $f['email']['smart_default'] );
        $this->assertSame( array( 'min' => 16, 'max' => 99 ), $f['age']['validation'] );
        $this->assertSame( 'us_states', $f['region']['choice_library'] );
        $this->assertSame( array( 'correct' => array( 'half_step_ahead' ), 'points' => 2, 'partial' => false, 'feedback_correct' => 'Yes.' ), $f['q1']['scoring'] );
        $this->assertArrayNotHasKey( 'beam', $f );
        $this->assertSame( 'teleporter', $model['extensions']['baf_legacy']['unknown_fields'][0]['type'] );
        // The builder's default settings on every field are not carried as props.
        $this->assertArrayNotHasKey( 'props', $f['full_name'] );
        // A flat section takes the fields after it, to the next section.
        $section = $model['pages'][0]['items'][3];
        $this->assertSame( 's1', $section['id'] );
        $this->assertSame( array( 'role', 'licence', 'other_role', 'agree', 'orphan' ), array_column( $section['items'], 'id' ) );
    }

    public function test_stored_model_is_returned_as_it_is() {
        $model  = self::json( self::dir() . 'models/valid/quiz-and-translations.json' );
        $result = BAF_Form_Model_Converter::from_legacy( array( 'config' => array( 'form_model' => $model, 'fields' => array() ) ) );
        $this->assertSame( $model, $result['model'] );
    }

    public function test_conditions_the_rule_list_cannot_say_ride_along_as_text() {
        $logic = BAF_Form_Model_Converter::legacy_condition( '#start is within 30 days' );
        $this->assertSame( array(), $logic['rules'] );
        $this->assertSame( '#start is within 30 days', $logic['expression'] );
        $logic = BAF_Form_Model_Converter::legacy_condition( 'not (#a is 1 or #b is empty)' );
        $this->assertSame( 'hide', $logic['action'] );
        $this->assertSame( 'any', $logic['logic'] );
        $this->assertCount( 2, $logic['rules'] );
    }

    public function test_baf_form_set_model_keeps_every_view_in_step() {
        $model = self::json( self::dir() . 'models/valid/logic-and-layout.json' );
        $form  = new BAF_Form();
        $this->assertTrue( $form->set_model( $model ) );
        $this->assertSame( $model, $form->config['form_model'] );
        $this->assertSame( 'Membership', $form->title );
        $this->assertSame( 'Join', $form->settings['submit_label'] );
        $this->assertSame( BAF_BFL_Writer::write( $model ), $form->bfl_source );
        $this->assertTrue( BAF_Form_Model::same( $model, $form->get_model() ) );
        // The builder's flat fields are there for the builder and renderer.
        $this->assertContains( 'company', array_column( $form->get_fields(), 'id' ) );
    }

    public function test_baf_form_refuses_an_invalid_model() {
        $form   = new BAF_Form();
        $result = $form->set_model( array( 'version' => 1, 'title' => 'T', 'pages' => array() ) );
        $this->assertTrue( is_wp_error( $result ) );
        $this->assertNotEmpty( $result->get_error_data() );
    }

    public function test_builder_save_keeps_what_the_builder_cannot_show() {
        $model = self::json( self::dir() . 'models/valid/quiz-and-translations.json' );
        $form  = new BAF_Form();
        $form->set_model( $model );

        // The builder sends its fields back with one label changed and
        // without the model's own keys.
        $config = $form->config;
        unset( $config['form_model'], $config['model_extra'], $config['model_version'] );
        $config['fields'][0]['label'] = 'How many dots?';
        $report = $form->apply_builder_config( $config );

        $after = $form->get_model();
        $this->assertSame( 'How many dots?', BAF_Form_Model::fields( $after )['q1']['label'] );
        $this->assertSame( $model['translations'], $after['translations'] );
        $this->assertSame( $model['scoring'], $after['scoring'] );
        $this->assertSame( array(), $report->of( BAF_Import_Report::ERROR ) );
    }

    public function test_upgrade_stores_the_model_for_a_form_saved_before_it() {
        $stored              = self::json( self::dir() . 'builder/saved-before-the-model.stored.json' );
        $form                = new BAF_Form();
        $form->title         = $stored['title'];
        $form->config        = $stored['config'];
        $form->settings      = $stored['settings'];
        $form->confirmations = $stored['confirmations'];
        $form->notifications = $stored['notifications'];
        $this->assertTrue( $form->upgrade_to_model() );
        $this->assertSame( 'Volunteer sign-up', $form->config['form_model']['title'] );
        // The builder's fields are left exactly as they were.
        $this->assertSame( $stored['config']['fields'], $form->config['fields'] );
    }

    public function test_report_text_is_plain_sentences() {
        $report = new BAF_Import_Report( 'Gravity Forms' );
        $report->carried = 3;
        $report->dropped( 'field 4', 'The model has no field type "teleporter".' );
        $this->assertSame( "Imported 3 item(s) from Gravity Forms.\nNot carried over: field 4: The model has no field type \"teleporter\".", $report->to_text() );
    }
}
