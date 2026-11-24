<?php
/**
 * Importing a form from Gravity Forms, Contact Form 7 or WPForms: fields
 * map to QAF types, what cannot be carried is reported, and entries are
 * never imported, even when the export carries them.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-importers.php';

class QAF_Import_Forms_Only_Test extends QAF_Unit_Test_Case {

    private static function gravity_with_entries() {
        return wp_json_encode( array(
            '0' => array(
                'title'   => 'Volunteer',
                'fields'  => array(
                    array( 'id' => 1, 'type' => 'name', 'label' => 'Your name', 'isRequired' => true ),
                    array( 'id' => 2, 'type' => 'email', 'label' => 'Email' ),
                    array( 'id' => 3, 'type' => 'select', 'label' => 'Shift', 'choices' => array( array( 'text' => 'Morning', 'value' => 'am' ), array( 'text' => 'Evening', 'value' => 'pm' ) ) ),
                ),
                'entries' => array( array( '1' => 'Ann', '2' => 'ann@example.com' ), array( '1' => 'Bo', '2' => 'bo@example.com' ) ),
            ),
            'version' => '2.8.0',
        ) );
    }

    private static function answers_in( $model ) {
        return wp_json_encode( $model );
    }

    public function test_gravity_forms_maps_fields_and_leaves_entries_behind() {
        $r = QAF_Importers::import( 'gravity-forms', self::gravity_with_entries() );
        $types = array_map( function ( $f ) { return $f['type']; }, QAF_Form_Model::fields( $r['model'] ) );
        $this->assertSame( array( 'name', 'email', 'select' ), array_values( $types ) );
        $this->assertSame( array(), QAF_Form_Model::validate( $r['model'] ) );
        $dropped = implode( ' ', array_column( $r['report']->of( QAF_Import_Report::DROPPED ), 'message' ) );
        $this->assertStringContainsString( 'The export holds 2 entries', $dropped );
        $this->assertStringContainsString( 'the entries were left behind', $dropped );
        $json = self::answers_in( $r['model'] );
        $this->assertStringNotContainsString( 'ann@example.com', $json, 'no answer reaches the model' );
        $this->assertStringNotContainsString( 'Bo', $json );
    }

    public function test_wpforms_json_maps_fields_and_leaves_entries_behind() {
        $export = wp_json_encode( array( array(
            'id'       => 9,
            'fields'   => array(
                '1' => array( 'id' => '1', 'type' => 'text', 'label' => 'Organisation' ),
                '2' => array( 'id' => '2', 'type' => 'checkbox', 'label' => 'Topics', 'choices' => array( '1' => array( 'label' => 'Braille' ), '2' => array( 'label' => 'Audio' ) ) ),
            ),
            'settings' => array( 'form_title' => 'Interests' ),
            'entries'  => array( array( 'fields' => '{"1":"ACME"}' ) ),
        ) ) );
        $r = QAF_Importers::import( 'wpforms', $export );
        $this->assertSame( 'Interests', $r['model']['title'] );
        $types = array_values( array_map( function ( $f ) { return $f['type']; }, QAF_Form_Model::fields( $r['model'] ) ) );
        $this->assertSame( array( 'text', 'checkbox' ), $types );
        $this->assertStringContainsString( 'The export holds 1 entries', implode( ' ', array_column( $r['report']->of( QAF_Import_Report::DROPPED ), 'message' ) ) );
        $this->assertStringNotContainsString( 'ACME', self::answers_in( $r['model'] ) );
    }

    public function test_contact_form_7_markup_maps_and_reports_what_it_cannot_carry() {
        $markup = "<label> Your name [text* your-name] </label>\n<label> Email [email* your-email] </label>\n[select topic \"Help\" \"Billing\"]\n[recaptcha]\n[submit \"Send\"]";
        $r      = QAF_Importers::import( 'contact-form-7', $markup );
        $fields = QAF_Form_Model::fields( $r['model'] );
        $types  = array();
        foreach ( $fields as $f ) {
            $types[ $f['label'] ?? $f['id'] ] = $f['type'];
        }
        $this->assertSame( 'text', $types['Your name'] ?? null );
        $this->assertSame( 'email', $types['Email'] ?? null );
        $this->assertContains( 'select', $types );
        $this->assertNotEmpty( $r['report']->entries, 'the reCAPTCHA tag it cannot carry is reported' );
        $this->assertSame( array(), QAF_Form_Model::validate( $r['model'] ) );
        $this->assertSame( 0, QAF_Importers::count_entries( array( 'form' => $markup ) ) );
    }

    public function test_a_clean_export_says_nothing_about_entries() {
        $r = QAF_Importers::import( 'gravity-forms', wp_json_encode( array( 'title' => 'T', 'fields' => array( array( 'id' => 1, 'type' => 'text', 'label' => 'A question' ) ) ) ) );
        $this->assertStringNotContainsString( 'entries', implode( ' ', array_column( $r['report']->entries, 'message' ) ) );
    }

    public function test_the_import_screen_saves_a_draft_form_and_no_entry() {
        $src = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php' );
        preg_match( '/function ajax_import_form\(\).*?\R    }\R/s', $src, $m );
        $this->assertNotEmpty( $m );
        $this->assertStringContainsString( "\$form->status = 'draft';", $m[0] );
        $this->assertStringNotContainsString( 'QAF_Entry', $m[0], 'the importer never makes an entry' );
        $this->assertStringContainsString( "'report'", $m[0], 'the report goes back to the page' );
    }
}
