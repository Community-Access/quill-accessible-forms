<?php
/**
 * The builder's next pass: canvas actions for sections and columns, the
 * Accessibility Coach in the editor (each finding with its question), and
 * Preview (the form as a visitor sees it, sending nothing).
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-field-scoring-panel.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-field-ops.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-layout-ops.php';
require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-aria-generator.php';
require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-accessibility-coach.php';
require_once QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php';

class QAF_Builder_Next_Pass_Test extends QAF_Unit_Test_Case {

    protected function setUp(): void {
        parent::setUp();
        if ( ! class_exists( 'QAF_Field_Registry' ) ) {
            eval( 'class QAF_Field_Registry { public static function get_instance() { return new self(); } public function get_field_type( $t ) { return array( "label" => ucfirst( $t ), "icon" => "*" ); } }' );
        }
    }

    private static function model() {
        return json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/logic-and-layout.json' ), true );
    }

    private static function dom( $html ) {
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );
        $errors = array_filter( libxml_get_errors(), function ( $e ) {
            return ! preg_match( '/Tag (section|main|template) invalid/', $e->message );
        } );
        libxml_clear_errors();
        return array( $doc, new DOMXPath( $doc ), array_values( $errors ) );
    }

    // ------------------------------------------------------------------
    // Canvas actions for sections and columns
    // ------------------------------------------------------------------

    public function test_every_group_and_column_has_named_actions() {
        $builder = new QAF_Form_Builder();
        list( $doc, $xp, $errors ) = self::dom( '<h2 id="qaf-canvas-title">T</h2>' . $builder->render_canvas( self::model(), 3 ) );
        $this->assertSame( array(), array_map( function ( $e ) { return trim( $e->message ); }, $errors ) );

        $seen = array();
        foreach ( $xp->query( '//*[@id]' ) as $e ) {
            $this->assertArrayNotHasKey( $e->getAttribute( 'id' ), $seen, 'an id used twice' );
            $seen[ $e->getAttribute( 'id' ) ] = true;
        }
        $groups = $xp->query( '//div[contains(@class,"qaf-group-card")]' );
        $this->assertGreaterThan( 2, $groups->length );
        foreach ( $groups as $g ) {
            $actions = $xp->query( './div[contains(@class,"qaf-group-actions") and not(contains(@class,"qaf-column-actions"))]', $g );
            $this->assertSame( 1, $actions->length, 'one set of actions per group' );
            $this->assertSame( 'group', $actions->item( 0 )->getAttribute( 'role' ) );
            $this->assertStringStartsWith( 'Actions for ', $actions->item( 0 )->getAttribute( 'aria-label' ) );
            $ops = array_map( function ( $b ) { return $b->getAttribute( 'data-op' ); }, iterator_to_array( $xp->query( './/button', $actions->item( 0 ) ) ) );
            $want = $g->getAttribute( 'data-group-kind' ) === 'columns' ? array( 'move', 'move', 'duplicate', 'add_column', 'remove' ) : array( 'move', 'move', 'duplicate', 'remove' );
            $this->assertSame( $want, $ops );
            $this->assertSame( '-1', $xp->query( './p[contains(@class,"qaf-group-label")]', $g )->item( 0 )->getAttribute( 'tabindex' ), 'focus can be put on its heading' );
        }
        // The visible words are in every button's accessible name.
        foreach ( $xp->query( '//button[contains(@class,"qaf-group-action")]' ) as $b ) {
            $visible = trim( $b->textContent );
            $this->assertNotSame( '', $visible );
            $this->assertStringContainsString( strtolower( $visible ), strtolower( $b->getAttribute( 'aria-label' ) ) );
            $this->assertNull( $b->attributes->getNamedItem( 'disabled' ), 'never disabled, only aria-disabled' );
        }
        // The first group on the page cannot move up; it says so with aria-disabled.
        $names = $xp->query( '//div[@data-group-id="names"]/div/button[@data-direction="up"]' )->item( 0 );
        $this->assertSame( 'Move up: Columns: 2 columns, position 4 of 6', $names->getAttribute( 'aria-label' ) );
        $postal_down = $xp->query( '//div[@data-group-id="postal"]/div/button[@data-direction="down"]' )->item( 0 );
        $this->assertSame( 'true', $postal_down->getAttribute( 'aria-disabled' ), 'last in its section' );
        // Two columns, two sets of column actions.
        $this->assertSame( 2, $xp->query( '//div[@data-group-id="names"]/div[contains(@class,"qaf-column-actions")]' )->length );
        $this->assertSame( 'true', $xp->query( '//div[@data-group-id="names"]//button[@data-op="move_column"][@data-column="0"][@data-direction="left"]' )->item( 0 )->getAttribute( 'aria-disabled' ) );
    }

    public function test_an_empty_section_still_has_a_card() {
        $builder = new QAF_Form_Builder();
        list( $m, $id ) = QAF_Builder_Layout_Ops::add_section( QAF_Builder_Field_Ops::new_model( 'T' ), 'Contact' );
        $html = $builder->render_canvas( $m, 1 );
        $this->assertStringContainsString( 'data-group-id="' . $id . '"', $html );
        $this->assertStringNotContainsString( 'No questions yet', $html );
    }

    public function test_layout_op_says_what_it_did_and_where_focus_goes() {
        $builder = new QAF_Form_Builder();
        $m       = self::model();

        $r = $builder->layout_op( $m, 'add_section', '', '', 0, array( 'label' => 'Extras' ) );
        $this->assertSame( 'Section Extras added. Move questions into it in Structure mode.', $r['message'] );
        $this->assertNotEmpty( $r['focus'] );

        $r = $builder->layout_op( $m, 'move', 'names', 'up', 0 );
        $this->assertSame( 'The set of columns moved to position 3 of 6.', $r['message'] );
        $r = $builder->layout_op( $m, 'move', 'postal', 'down', 0 );
        $this->assertSame( 'Postal address is already last.', $r['message'] );

        $r = $builder->layout_op( $m, 'remove', 'contact', '', 0 );
        $this->assertSame( 'How to reach you removed. Its 3 questions stay where they were.', $r['message'] );
        $this->assertSame( 'contact_by', $r['focus'], 'focus goes to the first of its questions' );

        $r = $builder->layout_op( $m, 'remove_column', 'names', '', 1 );
        $this->assertSame( 'Column removed. Its 1 question joined column 1.', $r['message'] );
        $this->assertSame( 0, $r['column'] );

        $r = $builder->layout_op( $m, 'duplicate', 'company', '', 0 );
        $this->assertStringContainsString( 'Duplicate button', $r['error'] );
        $this->assertNull( $builder->layout_op( $m, 'remove', 'nope', '', 0 )['error'] );
        $this->assertNotNull( $builder->layout_op( $m, 'explode', 'names', '', 0 )['error'] );
    }

    public function test_the_page_wires_the_actions() {
        $js      = file_get_contents( QAF_PLUGIN_DIR . 'admin/js/qaf-form-builder.js' );
        $partial = file_get_contents( QAF_PLUGIN_DIR . 'admin/partials/form-builder.php' );
        $this->assertStringContainsString( "post('qaf_layout_action'", $js );
        $this->assertStringContainsString( "post('qaf_coach_check'", $js );
        $this->assertStringContainsString( 'focusAfterLayout', $js );
        $this->assertStringContainsString( '<label for="qaf-add-section-label">', $partial );
        $this->assertStringContainsString( 'id="qaf-add-columns-btn"', $partial );
    }

    // ------------------------------------------------------------------
    // The Accessibility Coach
    // ------------------------------------------------------------------

    public function test_the_coach_names_the_question_each_finding_concerns() {
        $m = self::model();
        $m['pages'][0]['items'][1]['label'] = '';                 // company: no label
        $m['pages'][0]['items'][4]['label'] = '';                 // contact: a section with no heading
        $m['pages'][1]['items'][0]['items'][0]['label'] = 'Name'; // a repeater's question: a generic label
        $coach  = new QAF_Accessibility_Coach();
        $result = $coach->analyze_model( $m );

        $by = array();
        foreach ( $result['issues'] as $issue ) {
            $by[ $issue['field_id'] ][] = $issue;
            $this->assertArrayHasKey( 'field_label', $issue );
            $this->assertArrayHasKey( 'page_id', $issue );
        }
        $this->assertSame( 'Missing field label', $by['company'][0]['title'] );
        $this->assertSame( 'about', $by['company'][0]['page_id'] );
        $this->assertSame( 'Section without a heading', $by['contact'][0]['title'] );
        $this->assertSame( 'Generic field label', $by['child_name'][0]['title'] );
        $this->assertSame( 'family', $by['child_name'][0]['page_id'] );
        $this->assertArrayNotHasKey( 'after_section', $by, 'an HTML block needs no label' );
        $this->assertSame( 'error', $result['issues'][0]['severity'], 'errors first' );
        $this->assertGreaterThan( 0, $result['summary']['errors'] );

        // Drawn: a heading per finding, and Go to for its question's card.
        list( $doc, $xp, $errors ) = self::dom( '<h3 id="qaf-coach-heading">Coach</h3>' . QAF_Form_Builder::render_coach_results( $result, $m ) );
        $this->assertSame( array(), $errors );
        $this->assertSame( count( $result['issues'] ), $xp->query( '//ol/li/h4' )->length );
        foreach ( $xp->query( '//button[contains(@class,"qaf-coach-goto")]' ) as $b ) {
            $this->assertNotNull( $doc->getElementById( $b->getAttribute( 'aria-describedby' ) ) );
            $this->assertStringStartsWith( 'Go to ', $b->textContent );
        }
        $goto = $xp->query( '//button[@data-card-id="children"]' );
        $this->assertGreaterThan( 0, $goto->length, "a repeater's own question is reached through the repeater's card" );
        $this->assertStringContainsString( 'Error: Missing field label, in #company', $doc->textContent );

        $this->assertStringContainsString( 'Each is listed with the question it concerns', QAF_Form_Builder::coach_summary( $result ) );
    }

    public function test_a_clean_form_says_so() {
        $m = array( 'version' => 1, 'title' => 'Feedback', 'pages' => array( array( 'id' => 'p', 'items' => array(
            array( 'id' => 'email', 'type' => 'email', 'label' => 'Your email address' ),
        ) ) ) );
        $coach  = new QAF_Accessibility_Coach();
        $result = $coach->analyze_model( $m );
        $this->assertSame( array(), $result['issues'] );
        $this->assertSame( 'The Accessibility Coach found nothing to fix.', QAF_Form_Builder::coach_summary( $result ) );
        $this->assertStringContainsString( 'Nothing to fix', QAF_Form_Builder::render_coach_results( $result, $m ) );
    }

    public function test_the_models_own_problems_are_joined_to_their_question() {
        $m = self::model();
        $m['pages'][0]['items'][1]['when'] = '#nobody is "x"';
        $coach  = new QAF_Accessibility_Coach();
        $found  = array_filter( $coach->analyze_model( $m )['issues'], function ( $i ) {
            return $i['title'] === 'The form cannot be saved like this';
        } );
        $this->assertNotEmpty( $found );
        $this->assertSame( 'company', array_values( $found )[0]['field_id'] );
    }

    // ------------------------------------------------------------------
    // Preview
    // ------------------------------------------------------------------

    public function test_the_preview_posts_nowhere_and_carries_no_token() {
        $form = '<div class="qaf-form-wrapper"><form id="f1" class="qaf-form qaf-ajax-form" method="post" action="https://example.com/wp-json/qaf/v1/submit/3" novalidate="novalidate">'
            . '<input type="hidden" id="_qaf_token" name="_qaf_token" value="abc" /><input type="hidden" name="_wp_http_referer" value="/x" />'
            . '<input type="hidden" name="form_id" value="3" /><input type="hidden" name="_qaf_form_sig" value="sig" />'
            . '<input type="text" name="qaf_field_name" /></form></div>';
        $out = QAF_Form_Builder::preview_markup( $form );
        $this->assertStringContainsString( '<form action="#" data-qaf-preview="1" id="f1"', $out );
        $this->assertStringNotContainsString( 'wp-json', $out );
        $this->assertStringNotContainsString( '_qaf_token', $out );
        $this->assertStringNotContainsString( '_qaf_form_sig', $out );
        $this->assertStringNotContainsString( '_wp_http_referer', $out );
        $this->assertStringContainsString( 'name="qaf_field_name"', $out, 'the questions are all there' );
        $this->assertStringContainsString( 'name="form_id"', $out );
    }

    public function test_the_preview_screen_is_reachable_and_the_script_sends_nothing() {
        $admin   = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-admin.php' );
        $builder = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php' );
        $partial = file_get_contents( QAF_PLUGIN_DIR . 'admin/partials/form-builder.php' );
        $js      = file_get_contents( QAF_PLUGIN_DIR . 'public/js/qaf-public.js' );
        $this->assertStringContainsString( "'qaf-form-preview'", $admin );
        $this->assertStringContainsString( 'render_form_preview_page', $admin );
        $this->assertMatchesRegularExpression( '/function render_preview.*current_user_can.*preview_markup\(/s', $builder );
        $this->assertStringContainsString( 'page=qaf-form-preview&form_id=', $partial );
        $this->assertStringContainsString( '(opens in a new tab)', $partial );
        // The preview check comes before anything is sent.
        $check = strpos( $js, 'if (form.dataset.qafPreview) {' );
        $fetch = strpos( $js, 'await fetch(form.action' );
        $this->assertNotFalse( $check );
        $this->assertLessThan( $fetch, $check );
        $this->assertStringContainsString( "'previewChecked'", file_get_contents( QAF_PLUGIN_DIR . 'public/class-qaf-public.php' ) );
    }
}
