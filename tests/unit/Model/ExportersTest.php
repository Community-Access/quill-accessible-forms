<?php
/**
 * Exports to other builders: every export reads back through the importer
 * to the same questions, choices and (where the builder has them) conditions.
 *
 * @package BAF
 */

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-importers.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-exporters.php';

class BAF_Exporters_Test extends BAF_Unit_Test_Case {

    private static function model( $name ) {
        return json_decode( file_get_contents( BAF_PLUGIN_DIR . 'tests/conformance/models/valid/' . $name . '.json' ), true );
    }

    /** Label => choice labels, for the fields an export carried. */
    private static function questions( $model ) {
        $out     = array();
        $choices = BAF_Form_Model::choice_labels( $model );
        foreach ( BAF_Form_Model::fields( $model ) as $id => $f ) {
            if ( in_array( $f['type'], array( 'html', 'hidden', 'submit' ), true ) || ( $f['label'] ?? '' ) === '' ) {
                continue;
            }
            $out[ $f['label'] ] = array_values( $choices[ $id ] ?? array() );
        }
        return $out;
    }

    public function test_every_export_reads_back_to_the_same_questions() {
        foreach ( array( 'logic-and-layout', 'quiz-and-translations', 'all-field-types' ) as $name ) {
            $model = self::model( $name );
            foreach ( array_keys( BAF_Exporters::TARGETS ) as $target ) {
                $out  = BAF_Exporters::export( $target, $model );
                $back = BAF_Importers::import( $target, $out['text'] );
                $this->assertSame( array(), BAF_Form_Model::validate( $back['model'] ), "$name $target" );
                $before = self::questions( $model );
                $after  = self::questions( $back['model'] );
                foreach ( $after as $label => $labels ) {
                    $this->assertArrayHasKey( $label, $before, "$name $target: $label came from nowhere" );
                    if ( $before[ $label ] && $labels !== array( 'Yes' ) ) {
                        $this->assertSame( $before[ $label ], $labels, "$name $target: choices of $label" );
                    }
                }
                // Everything not carried is in the report.
                $dropped = count( $out['report']->of( BAF_Import_Report::DROPPED ) );
                $this->assertTrue( count( $after ) > 0, "$name $target carried nothing" );
                $this->assertTrue( count( $after ) === count( $before ) || $dropped > 0, "$name $target lost questions without saying so" );
                $this->assertSame( 'export', $out['report']->to_array()['direction'] );
            }
        }
    }

    public function test_conditions_survive_where_the_builder_has_them() {
        $model = self::model( 'logic-and-layout' );
        foreach ( array( 'gravity-forms', 'wpforms' ) as $target ) {
            $back  = BAF_Importers::import( $target, BAF_Exporters::export( $target, $model )['text'] )['model'];
            $found = null;
            foreach ( BAF_Form_Model::fields( $back ) as $f ) {
                if ( ( $f['label'] ?? '' ) === 'Company name' ) {
                    $found = $f;
                }
            }
            $this->assertNotNull( $found, $target );
            $this->assertSame(
                BAF_Form_Model::sentence( $model, '#type is "business" or #type is "charity"' ),
                BAF_Form_Model::sentence( $back, $found['when'] ),
                $target
            );
        }
    }

    public function test_simple_conditions() {
        $c = BAF_Exporters::simple_condition( '#a is "x" and #b > 3 or #c is empty' );
        $this->assertSame( array( 'hide' => false, 'groups' => array( array( array( 'a', 'is', 'x' ), array( 'b', 'gt', '3' ) ), array( array( 'c', 'empty', '' ) ) ) ), $c );
        $this->assertTrue( BAF_Exporters::simple_condition( 'not (#a is "x" or #b is "y")' )['hide'] );
        $this->assertNull( BAF_Exporters::simple_condition( '#a is within 3 days' ) );
        $this->assertNull( BAF_Exporters::simple_condition( '#a is #b' ), 'field to field' );
        $this->assertNull( BAF_Exporters::simple_condition( '(#a is 1 or #b is 2) and #c is 3' ) );
        $this->assertNull( BAF_Exporters::simple_condition( '#a is' ) );
    }

    public function test_contact_form_7_template_and_reports() {
        $out = BAF_Exporters::export( 'contact-form-7', self::model( 'logic-and-layout' ) );
        $this->assertIsString( $out['data'] );
        $this->assertStringContainsString( '[radio* type "An individual|person" "A business|business" "A charity|charity"]', $out['text'] );
        $this->assertStringContainsString( '[submit "Join"]', $out['text'] );
        $this->assertSame( '.txt', substr( $out['filename'], -4 ) );
        $this->assertStringStartsWith( 'Exported ', $out['report']->to_text() );
        $this->assertStringContainsString( 'translations', BAF_Exporters::export( 'gravity-forms', self::model( 'quiz-and-translations' ) )['report']->to_text() );
    }

    public function test_library_choices_come_from_the_resolver() {
        $model = self::model( 'logic-and-layout' );
        BAF_Exporters::$library_resolver = function ( $name ) {
            return $name === 'countries' ? array( array( 'value' => 'CA', 'label' => 'Canada' ) ) : array();
        };
        try {
            $data = BAF_Exporters::export( 'gravity-forms', $model )['data'];
        } finally {
            BAF_Exporters::$library_resolver = null;
        }
        $country = null;
        foreach ( $data['0']['fields'] as $f ) {
            if ( ( $f['label'] ?? '' ) === 'Country' ) {
                $country = $f;
            }
        }
        $this->assertSame( array( array( 'text' => 'Canada', 'value' => 'CA' ) ), $country['choices'] );
    }

    public function test_unknown_target() {
        try {
            BAF_Exporters::export( 'typeform', self::model( 'logic-and-layout' ) );
            $this->fail( 'no exception' );
        } catch ( InvalidArgumentException $e ) {
            $this->assertStringContainsString( 'typeform', $e->getMessage() );
        }
    }
}
