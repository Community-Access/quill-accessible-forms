<?php
/**
 * Table View Tests
 *
 * The table renderer is checked through render(), the markup a visitor's
 * screen reader gets: scoped headers, labelled checkboxes, named buttons.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'views-test-helpers.php';

class QAF_View_Table_Test extends QAF_Unit_Test_Case {

    private function table( $config = array() ) {
        return new QAF_View_Table( array_merge( array(
            'columns' => array(
                'name'   => array( 'label' => 'Name', 'sortable' => true ),
                'email'  => array( 'label' => 'Email', 'sortable' => false ),
                'status' => array( 'label' => 'Status' ),
            ),
        ), $config ) );
    }

    private function entries() {
        return array(
            new QAF_Test_View_Entry( 1, array( 'name' => 'Ada', 'email' => 'ada@example.com' ) ),
            new QAF_Test_View_Entry( 2, array( 'name' => 'Grace', 'email' => 'grace@example.com' ), array( 'is_read' => false, 'is_starred' => true ) ),
        );
    }

    public function test_view_type() {
        $html = $this->table()->render( $this->entries() );
        $this->assertStringContainsString( 'qaf-view qaf-view-table', $html );
    }

    public function test_column_headers() {
        $html = $this->table()->render( $this->entries() );

        $this->assertStringContainsString( '<thead>', $html );
        $this->assertSame( 5, substr_count( $html, '<th scope="col"' ) ); // checkbox + 3 data + actions
        $this->assertStringContainsString( '>Email</th>', $html );
    }

    public function test_sortable_columns() {
        $html = $this->table()->render( $this->entries() );

        $this->assertStringContainsString( 'aria-sort="none"', $html );
        $this->assertStringContainsString( 'data-column="name"', $html );
        $this->assertStringNotContainsString( 'data-column="email"', $html );
    }

    public function test_bulk_actions() {
        $html = $this->table()->render( $this->entries() );
        $this->assertStringContainsString( 'aria-label="Select all entries"', $html );
        $this->assertStringContainsString( 'aria-label="Select entry 2"', $html );
        $this->assertStringContainsString( '<option value="trash">Move to Trash</option>', $html );

        $html = $this->table( array( 'bulk_actions' => false ) )->render( $this->entries() );
        $this->assertStringNotContainsString( 'Select all entries', $html );
    }

    public function test_row_actions() {
        $html = $this->table()->render( $this->entries() );

        $this->assertStringContainsString( 'aria-label="View entry 1"', $html );
        $this->assertStringContainsString( 'aria-label="Edit entry 1"', $html );
        $this->assertStringContainsString( 'aria-label="Delete entry 2"', $html );
        // Star is a toggle and says which way it is set.
        $this->assertStringContainsString( 'aria-label="Unstar entry 2" aria-pressed="true"', $html );
        $this->assertStringContainsString( 'aria-label="Star entry 1" aria-pressed="false"', $html );

        $html = $this->table( array( 'row_actions' => false ) )->render( $this->entries() );
        $this->assertStringNotContainsString( 'qaf-row-actions', $html );
    }

    public function test_cells_carry_column_labels() {
        $html = $this->table()->render( $this->entries() );

        // data-label lets the stacked (narrow screen) layout keep each value's name.
        $this->assertStringContainsString( '<td class="qaf-col-name" data-label="Name">Ada</td>', $html );
    }

    public function test_sticky_header() {
        $this->assertStringContainsString( 'qaf-sticky-header', $this->table()->render( $this->entries() ) );
        $this->assertStringNotContainsString( 'qaf-sticky-header', $this->table( array( 'sticky_header' => false ) )->render( $this->entries() ) );
    }

    public function test_zebra_striping() {
        $this->assertStringContainsString( 'qaf-zebra', $this->table()->render( $this->entries() ) );
        $this->assertStringNotContainsString( 'qaf-zebra', $this->table( array( 'zebra_striping' => false ) )->render( $this->entries() ) );
    }

    public function test_unread_and_starred_rows() {
        $html = $this->table()->render( $this->entries() );

        $this->assertStringContainsString( 'class="qaf-entry-row qaf-unread qaf-starred" data-entry-id="2"', $html );
        $this->assertStringContainsString( 'class="qaf-entry-row" data-entry-id="1"', $html );
    }

    public function test_status_column() {
        $html = $this->table()->render( $this->entries() );
        $this->assertStringContainsString( '<span class="qaf-status qaf-status-active">Active</span>', $html );
    }

    public function test_accessibility_attributes() {
        $html = $this->table()->render( $this->entries() );

        // A scrollable region must be focusable and named so keyboard users can scroll it.
        $this->assertStringContainsString( 'role="region" aria-label="Entries table" tabindex="0"', $html );
    }

    public function test_html_in_values_is_escaped() {
        $entries = array( new QAF_Test_View_Entry( 3, array( 'name' => '<img src=x onerror=alert(1)>' ) ) );
        $html    = $this->table()->render( $entries );

        $this->assertStringNotContainsString( '<img src=x', $html );
        $this->assertStringContainsString( '&lt;img src=x', $html );
    }

    public function test_array_values_are_joined() {
        $entries = array( new QAF_Test_View_Entry( 4, array( 'name' => array( 'red', 'blue' ) ) ) );
        $this->assertStringContainsString( '>red, blue</td>', $this->table()->render( $entries ) );
    }

    public function test_empty_state() {
        $html = $this->table()->render( array() );

        $this->assertStringContainsString( 'role="status"', $html );
        $this->assertStringContainsString( 'No entries found.', $html );
        $this->assertStringNotContainsString( '<table', $html );
        $this->assertStringContainsString( '0 entries', $html );
    }

    public function test_pagination() {
        $entries = array();
        for ( $i = 1; $i <= 7; $i++ ) {
            $entries[] = new QAF_Test_View_Entry( $i, array( 'name' => 'N' . $i ) );
        }

        $html = $this->table( array( 'pagination' => 3 ) )->render( $entries );
        $this->assertStringContainsString( '<nav class="qaf-pagination" aria-label="Entry pagination">', $html );
        $this->assertStringContainsString( 'aria-current="page" data-page="1"', $html );
        $this->assertStringContainsString( 'data-page="3"', $html );

        // One page: no pagination at all.
        $this->assertStringNotContainsString( 'qaf-pagination', $this->table()->render( $this->entries() ) );
    }
}
