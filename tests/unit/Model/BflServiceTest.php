<?php
/**
 * The REST API's and the builder's BFL operations, on the form model.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-form-model-converter.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-bfl-writer.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-bfl-service.php';

class BAF_BFL_Service_Test extends BAF_Unit_Test_Case {

    private static function dir() {
        return BAF_PLUGIN_DIR . 'tests/conformance/';
    }

    private static function json( $rel ) {
        return json_decode( file_get_contents( self::dir() . $rel ), true );
    }

    public function test_parse_reads_older_bfl_with_the_new_reader() {
        foreach ( glob( self::dir() . 'bfl/legacy/*.canonical.bfl' ) as $canonical ) {
            $source = str_replace( '.canonical.bfl', '.bfl', $canonical );
            $model  = self::json( 'bfl/legacy/' . basename( str_replace( '.canonical.bfl', '.json', $canonical ) ) );
            $result = BAF_BFL_Service::parse( file_get_contents( $source ) );
            $this->assertTrue( $result['valid'], basename( $source ) );
            $this->assertTrue( BAF_Form_Model::same( $model, $result['model'] ), basename( $source ) );
            $this->assertSame( BAF_Form_Model_Converter::to_legacy( $model )['config'], $result['config'], basename( $source ) );
        }
    }

    public function test_parse_reports_line_numbered_errors_and_no_config() {
        $result = BAF_BFL_Service::parse( "form \"X\"\ntext #a \"A\"\n  @when #b is\n" );
        $this->assertFalse( $result['valid'] );
        $this->assertNull( $result['config'] );
        $this->assertNotEmpty( $result['errors'] );
        $this->assertSame( 3, $result['errors'][0]['line'] );
    }

    public function test_validate_adds_the_models_own_rules() {
        $ok = BAF_BFL_Service::validate( file_get_contents( self::dir() . 'bfl/canonical/logic-and-layout.bfl' ) );
        $this->assertTrue( $ok['valid'] );
        $bad = BAF_BFL_Service::validate( "bfl 1\nform \"X\"\n\ntext #a \"A\"\n  @when #nobody is 1\n" );
        $this->assertFalse( $bad['valid'] );
        $this->assertSame( array(), $bad['errors'] );
        $this->assertNotEmpty( $bad['model_errors'] );
    }

    public function test_compile_writes_canonical_bfl_from_a_model_or_the_builder() {
        $model = self::json( 'models/valid/logic-and-layout.json' );
        $bfl   = file_get_contents( self::dir() . 'bfl/canonical/logic-and-layout.bfl' );
        $this->assertSame( $bfl, BAF_BFL_Service::compile( $model )['bfl'] );
        $stored = BAF_Form_Model_Converter::to_legacy( $model );
        $this->assertSame( $bfl, BAF_BFL_Service::compile( $stored )['bfl'], 'a stored form' );
        $builder = BAF_BFL_Service::compile( array( 'fields' => array( array( 'id' => 'name', 'type' => 'text', 'label' => 'Name' ) ) ) );
        $this->assertStringStartsWith( 'bfl 1', $builder['bfl'] );
        $this->assertIsArray( $builder['report'] );
        $this->assertTrue( BAF_BFL_Service::is_model( $model ) );
        $this->assertFalse( BAF_BFL_Service::is_model( $stored['config'] ) );
    }

    public function test_format_is_canonical_and_refuses_errors() {
        $source = file_get_contents( self::dir() . 'bfl/legacy/choices.bfl' );
        $this->assertSame( file_get_contents( self::dir() . 'bfl/legacy/choices.canonical.bfl' ), BAF_BFL_Service::format( $source )['bfl'] );
        $this->assertNull( BAF_BFL_Service::format( "form \"X\"\ntext #a \"A\"\n  @when #b is\n" )['bfl'] );
    }

    public function test_structure_mode_draws_the_model_tree() {
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', BAF_PLUGIN_DIR );
        }
        require_once BAF_PLUGIN_DIR . 'includes/accessibility/class-baf-aria-generator.php';
        require_once BAF_PLUGIN_DIR . 'admin/class-baf-structure-mode.php';
        $model = self::json( 'models/valid/logic-and-layout.json' );
        $sm    = new BAF_Structure_Mode();
        $html  = $sm->render( BAF_Form_Model_Converter::to_legacy( $model )['config'], 1, $model );
        $count = count( BAF_Form_Model::items( $model ) ) + count( $model['pages'] );
        $this->assertSame( $count, substr_count( $html, 'role="treeitem"' ), 'every page and item, nested' );
        $this->assertStringContainsString( 'Shown when Joining as is A business or Joining as is A charity', $html );
        $this->assertStringContainsString( 'data-item-id="region"', $html );
        $same = $sm->render( BAF_Form_Model_Converter::to_legacy( $model )['config'], 1 );
        $this->assertSame( $html, $same, 'the model read from the builder config is the same tree' );
    }

    public function test_outline_nests_items_and_reads_conditions_as_sentences() {
        $pages = BAF_BFL_Service::outline( self::json( 'models/valid/logic-and-layout.json' ) );
        $this->assertSame( array( 'about', 'family', 'pay' ), array_column( $pages, 'id' ) );
        $this->assertSame( 'Joining as is An individual', $pages[1]['when'] );
        $about = array_column( $pages[0]['items'], null, 'id' );
        $this->assertSame( 'Joining as is A business or Joining as is A charity', $about['company']['when'] );
        $this->assertSame( array( 'first', 'last' ), array_column( $about['names']['children'], 'id' ) );
        $contact = array_column( $about['contact']['children'], null, 'id' );
        $this->assertSame( array( 'country', 'region' ), array_column( $contact['postal']['children'], 'id' ) );
        $this->assertTrue( $about['type']['required'] );
    }
    public function test_pasted_choice_lines_read_as_bfl_does() {
        $r = BAF_BFL_Service::read_choice_lines( "+ Small\n\ns|Smaller\n  + Large:20.0\n+ \"Odd: one\" @points 2\n" );
        $this->assertSame( array(), $r['errors'] );
        $this->assertSame(
            array(
                array( 'value' => 'small', 'label' => 'Small' ),
                array( 'value' => 's', 'label' => 'Smaller' ),
                array( 'value' => 'large', 'label' => 'Large', 'price' => 20.0 ),
                array( 'value' => 'odd-one', 'label' => 'Odd: one', 'points' => 2 ),
            ),
            $r['choices']
        );
    }

    public function test_pasted_choice_problems_name_the_pasted_line() {
        $r = BAF_BFL_Service::read_choice_lines( "+ Small\n\n+ \"Not closed\n" );
        $this->assertSame( 1, count( $r['errors'] ) );
        $this->assertSame( 3, $r['errors'][0]['line'] );
        $this->assertSame( array(), BAF_BFL_Service::read_choice_lines( "  \n" )['choices'] );
    }
    /**
     * A model as the builder's Rules, Quiz outcomes, Translations and Choice
     * libraries screens left it (captured from those screens): it is valid,
     * and BFL written from it reads back to the same model.
     */
    public function test_a_model_edited_on_the_model_screens_is_what_bfl_gives() {
        $model = json_decode( file_get_contents( BAF_PLUGIN_DIR . 'tests/unit/Model/fixtures/edited-on-model-screens.json' ), true );
        $this->assertSame( array(), BAF_Form_Model::validate( $model ) );
        $read = BAF_BFL_Reader::read( BAF_BFL_Writer::write( $model ) );
        $this->assertSame( array(), $read['errors'] );
        $this->assertTrue( BAF_Form_Model::same( $model, $read['model'] ) );
    }
}