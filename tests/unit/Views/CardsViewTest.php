<?php
/**
 * Cards View Tests
 *
 * The cards renderer is checked through render(): a named list of article
 * list items, a heading per card, and named buttons.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'views-test-helpers.php';

class QAF_View_Cards_Test extends QAF_Unit_Test_Case {

    private function cards( $config = array() ) {
        return new QAF_View_Cards( array_merge( array(
            'title_field'    => 'name',
            'subtitle_field' => 'email',
            'body_fields'    => array( 'message', 'city' ),
        ), $config ) );
    }

    private function entries() {
        return array(
            new QAF_Test_View_Entry( 1, array( 'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello', 'city' => '' ) ),
            new QAF_Test_View_Entry( 2, array( 'name' => '', 'email' => '' ), array( 'is_starred' => true ) ),
        );
    }

    public function test_view_type() {
        $this->assertStringContainsString( 'qaf-view qaf-view-cards', $this->cards()->render( $this->entries() ) );
    }

    public function test_cards_are_a_named_list() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( 'role="list" aria-label="Form entries"', $html );
        $this->assertSame( 2, substr_count( $html, 'role="listitem"' ) );
    }

    public function test_card_title_is_a_heading() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( '<h3 class="qaf-card-title"><a href="#" class="qaf-entry-link" data-entry-id="1">Ada</a></h3>', $html );
        // No title value: fall back to the entry number rather than an empty heading.
        $this->assertStringContainsString( '>Entry #2</a></h3>', $html );
    }

    public function test_title_without_link_when_not_clickable() {
        $html = $this->cards( array( 'clickable' => false ) )->render( $this->entries() );

        $this->assertStringContainsString( '<h3 class="qaf-card-title">Ada</h3>', $html );
        $this->assertStringNotContainsString( 'qaf-clickable', $html );
    }

    public function test_subtitle() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( '<p class="qaf-card-subtitle">ada@example.com</p>', $html );
        // Empty subtitle: no empty paragraph.
        $this->assertSame( 1, substr_count( $html, 'qaf-card-subtitle' ) );
    }

    public function test_body_fields_as_description_list() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( '<dl class="qaf-card-fields"><div class="qaf-card-field"><dt>message</dt><dd>Hello</dd></div></dl>', $html );
        $this->assertStringNotContainsString( '<dt>city</dt>', $html );
    }

    public function test_columns_and_size_classes() {
        $html = $this->cards( array( 'columns' => 4, 'card_size' => 'large' ) )->render( $this->entries() );

        $this->assertStringContainsString( 'qaf-cards-grid qaf-cards-large qaf-cols-4', $html );
    }

    public function test_default_grid() {
        $this->assertStringContainsString( 'qaf-cards-medium qaf-cols-3', $this->cards()->render( $this->entries() ) );
    }

    public function test_starred_card() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( 'class="qaf-card qaf-starred qaf-clickable" data-entry-id="2"', $html );
        $this->assertStringContainsString( 'aria-pressed="true" aria-label="Unstar"', $html );
        $this->assertStringContainsString( 'aria-pressed="false" aria-label="Star"', $html );
    }

    public function test_card_actions_are_named() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( 'aria-label="View entry 1"', $html );
        $this->assertStringContainsString( 'aria-label="Delete entry 2"', $html );
    }

    public function test_card_meta_time() {
        $html = $this->cards()->render( $this->entries() );

        $this->assertStringContainsString( '<time datetime="2026-11-13 10:00:00">', $html );
    }

    public function test_html_in_values_is_escaped() {
        $entries = array( new QAF_Test_View_Entry( 3, array( 'name' => '<script>x</script>' ) ) );
        $html    = $this->cards()->render( $entries );

        $this->assertStringNotContainsString( '<script>x', $html );
    }

    public function test_empty_state() {
        $html = $this->cards()->render( array() );

        $this->assertStringContainsString( 'role="status"', $html );
        $this->assertStringContainsString( 'No entries found.', $html );
        $this->assertStringNotContainsString( 'role="list"', $html );
    }
}
