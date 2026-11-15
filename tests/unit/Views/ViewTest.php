<?php
/**
 * View Base Class Tests
 *
 * QAF_View is the stored view record (built from a database row object);
 * QAF_View_Renderer and its subclasses turn entries into markup.
 *
 * @package QAF
 */

require_once QAF_TESTS_DIR . 'views-test-helpers.php';

class QAF_View_Test extends QAF_Unit_Test_Case {

    private function row( $props = array() ) {
        return (object) array_merge( array(
            'id'          => 1,
            'form_id'     => 1,
            'title'       => 'Test View',
            'slug'        => 'test-view',
            'view_type'   => 'table',
            'config'      => '{}',
            'permissions' => '{}',
            'status'      => 'published',
        ), $props );
    }

    /**
     * Test view initialization from a database row
     */
    public function test_view_initialization() {
        $view = new QAF_View( $this->row() );

        $this->assertSame( 1, $view->id );
        $this->assertEquals( 'table', $view->view_type );
        $this->assertEquals( 'Test View', $view->title );
        $this->assertEquals( 'published', $view->status );
    }

    public function test_copy_constructor() {
        $copy = new QAF_View( new QAF_View( $this->row( array( 'title' => 'Original' ) ) ) );

        $this->assertEquals( 'Original', $copy->title );
    }

    /**
     * Test config JSON decodes and merges with type defaults
     */
    public function test_view_columns() {
        $view = new QAF_View( $this->row( array(
            'config' => wp_json_encode( array( 'fields' => array( 'name', 'email' ) ) ),
        ) ) );

        $this->assertEquals( array( 'name', 'email' ), $view->config['fields'] );
        // Table defaults are filled in around what was stored.
        $this->assertEquals( 'stackable', $view->config['responsive_mode'] );
        $this->assertTrue( $view->config['sortable_columns'] );
    }

    /**
     * Test filter defaults
     */
    public function test_view_filters() {
        $view = new QAF_View( $this->row( array(
            'config' => array( 'filter_fields' => array( 'status' ) ),
        ) ) );

        $this->assertTrue( $view->config['enable_filters'] );
        $this->assertEquals( array( 'status' ), $view->config['filter_fields'] );
    }

    /**
     * Test sorting defaults and override
     */
    public function test_view_sorting() {
        $view = new QAF_View( $this->row() );
        $this->assertEquals( 'created_at', $view->config['sort_field'] );
        $this->assertEquals( 'DESC', $view->config['sort_order'] );

        $view = new QAF_View( $this->row( array( 'config' => '{"sort_field":"name","sort_order":"ASC"}' ) ) );
        $this->assertEquals( 'name', $view->config['sort_field'] );
        $this->assertEquals( 'ASC', $view->config['sort_order'] );
    }

    /**
     * Test pagination defaults
     */
    public function test_view_pagination() {
        $view = new QAF_View( $this->row( array( 'config' => '{"items_per_page":10}' ) ) );

        $this->assertSame( 10, $view->config['items_per_page'] );
        $this->assertTrue( $view->config['show_pagination'] );
    }

    /**
     * Test permissions decode; unreadable JSON becomes an empty array
     */
    public function test_view_permissions() {
        $view = new QAF_View( $this->row( array(
            'permissions' => '{"enabled":true,"roles":["administrator","editor"]}',
        ) ) );
        $this->assertTrue( $view->permissions['enabled'] );
        $this->assertContains( 'editor', $view->permissions['roles'] );

        $broken = new QAF_View( $this->row( array( 'permissions' => 'not json' ) ) );
        $this->assertSame( array(), $broken->permissions );
    }

    /**
     * Test type-specific defaults
     */
    public function test_type_specific_defaults() {
        $cards = new QAF_View( $this->row( array( 'view_type' => 'cards' ) ) );
        $this->assertTrue( $cards->config['enable_search'] );
        $this->assertSame( 3, $cards->config['columns'] );

        $calendar = new QAF_View( $this->row( array( 'view_type' => 'calendar' ) ) );
        $this->assertEquals( 'month', $calendar->config['default_view'] );
    }

    /**
     * Test the renderer factory
     */
    public function test_renderer_factory() {
        $this->assertInstanceOf( 'QAF_View_Table', QAF_View_Renderer::factory( 'table' ) );
        $this->assertInstanceOf( 'QAF_View_Cards', QAF_View_Renderer::factory( 'cards' ) );
        $this->assertInstanceOf( 'QAF_View_List', QAF_View_Renderer::factory( 'list' ) );
        $this->assertInstanceOf( 'QAF_View_Map', QAF_View_Renderer::factory( 'map' ) );
        $this->assertInstanceOf( 'QAF_View_Calendar', QAF_View_Renderer::factory( 'calendar' ) );
        // Unknown types fall back to a table.
        $this->assertInstanceOf( 'QAF_View_Table', QAF_View_Renderer::factory( 'nonsense' ) );
    }

    /**
     * Test the shared header and footer markup
     */
    public function test_renderer_header_and_footer() {
        $renderer = QAF_View_Renderer::factory( 'table', array(
            'columns' => array( 'name' => array( 'label' => 'Name' ) ),
            'export'  => array( 'csv' ),
        ) );

        $html = $renderer->render( array( new QAF_Test_View_Entry( 1, array( 'name' => 'Ada' ) ) ) );

        $this->assertStringContainsString( 'type="search"', $html );
        $this->assertStringContainsString( 'aria-label="Search entries"', $html );
        $this->assertStringContainsString( 'data-format="csv"', $html );
        $this->assertStringContainsString( 'Export as CSV', $html );
        $this->assertStringContainsString( '<div class="qaf-entry-count" aria-live="polite">1 entry</div>', $html );
    }
}
