<?php
/**
 * Repeater cards: each entry of a repeating group is a list item holding a
 * group named by the repeater's legend and the entry's own title, and its
 * Add and Remove buttons say what they did.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';
require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-repeater.php';

class QAF_Repeater_Cards_Test extends QAF_Unit_Test_Case {

    private static function dom( $html ) {
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );
        libxml_clear_errors();
        return array( $doc, new DOMXPath( $doc ) );
    }

    private static function repeater( $config = array() ) {
        return new QAF_Field_Repeater( array_merge( array(
            'id'           => 'kids',
            'label'        => 'Children',
            'row_label'    => 'Child',
            'min_rows'     => 1,
            'max_rows'     => 3,
            'default_rows' => 2,
        ), $config ), 7 );
    }

    public function test_each_entry_is_a_group_named_by_the_legend_and_its_title() {
        list( $doc, $xp ) = self::dom( self::repeater()->render() );
        $groups = $xp->query( '//div[@role="listitem"][not(ancestor::template)]/div[@role="group"]' );
        $this->assertSame( 2, $groups->length, 'two entries to start with (the template is not in the DOM)' );
        foreach ( $groups as $n => $group ) {
            $ids = preg_split( '/\s+/', $group->getAttribute( 'aria-labelledby' ) );
            $this->assertCount( 2, $ids );
            $words = array_map( function ( $id ) use ( $doc ) {
                $el = $doc->getElementById( $id );
                $this->assertNotNull( $el, 'aria-labelledby names ' . $id );
                return trim( preg_replace( '/\s+/', ' ', $el->textContent ) );
            }, $ids );
            $this->assertStringStartsWith( 'Children', $words[0] );
            $this->assertSame( 'Child ' . ( $n + 1 ), $words[1] );
        }
        $list = $xp->query( '//div[@role="list"]' )->item( 0 );
        $this->assertSame( 'qaf-field-7-kids-legend', $list->getAttribute( 'aria-labelledby' ) );
    }

    public function test_buttons_name_their_entry_and_add_stays_reachable_when_full() {
        $html = self::repeater( array( 'default_rows' => 3 ) )->render();
        list( $doc, $xp ) = self::dom( $html );
        $remove = $xp->query( '//button[contains(@class,"qaf-repeater-remove")][not(ancestor::template)]' );
        $this->assertSame( 'Remove Child 3', $remove->item( 2 )->getAttribute( 'aria-label' ) );
        $this->assertStringContainsString( 'Remove', $remove->item( 2 )->textContent, 'the visible text is in the name' );
        $add = $xp->query( '//button[contains(@class,"qaf-repeater-add")]' )->item( 0 );
        $this->assertSame( 'true', $add->getAttribute( 'aria-disabled' ) );
        $this->assertFalse( $add->hasAttribute( 'disabled' ), 'a full group says so; it does not vanish from the tab order' );
        $status = $doc->getElementById( $add->getAttribute( 'aria-describedby' ) );
        $this->assertSame( 'status', $status->getAttribute( 'role' ) );
        $this->assertSame( 'polite', $status->getAttribute( 'aria-live' ) );
    }

    public function test_the_template_carries_placeholders_the_script_fills() {
        $html = self::repeater()->render();
        $this->assertStringContainsString( 'qaf-field-7-kids-row-{{INDEX}}-title', $html );
        $this->assertStringContainsString( 'Child {{NUMBER}}', $html );
    }

    public function test_the_page_script_announces_and_moves_focus() {
        $js = file_get_contents( QAF_PLUGIN_DIR . 'public/js/qaf-public.js' );
        $this->assertStringContainsString( 'this.bindRepeaters(form);', $js );
        foreach ( array( 'repeaterAdded', 'repeaterRemoved', 'repeaterFull', 'repeaterMin', 'repeaterMoved' ) as $key ) {
            $this->assertStringContainsString( 'i18n.' . $key, $js, $key );
            $this->assertStringContainsString( "'" . $key . "'", file_get_contents( QAF_PLUGIN_DIR . 'public/class-qaf-public.php' ), $key . ' is localised' );
        }
        $this->assertStringContainsString( 'target.focus()', $js, 'focus goes to the new entry' );
        $this->assertStringContainsString( "(target || addBtn).focus()", $js, 'after a removal, focus goes to the entry that took its place, or Add' );
    }
}
