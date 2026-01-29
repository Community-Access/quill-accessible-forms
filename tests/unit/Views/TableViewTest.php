<?php
/**
 * Table View Tests
 *
 * @package BAF
 */

class BAF_View_Table_Test extends BAF_Unit_Test_Case {

    /**
     * @var BAF_View_Table
     */
    private $view;

    protected function setUp(): void {
        parent::setUp();
        
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-view.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-table.php';
        
        $this->view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'title'   => 'Entries Table',
            'columns' => array(
                array( 'field' => 'name', 'label' => 'Name' ),
                array( 'field' => 'email', 'label' => 'Email' ),
                array( 'field' => 'status', 'label' => 'Status' ),
            ),
        ) );
    }

    public function test_view_type() {
        $this->assertEquals( 'table', $this->view->get_type() );
    }

    public function test_column_headers() {
        $headers = $this->view->get_column_headers();
        
        $this->assertCount( 3, $headers );
        $this->assertEquals( 'Name', $headers['name'] );
    }

    public function test_sortable_columns() {
        $view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'columns' => array(
                array( 'field' => 'name', 'label' => 'Name', 'sortable' => true ),
                array( 'field' => 'email', 'label' => 'Email', 'sortable' => false ),
            ),
        ) );
        
        $sortable = $view->get_sortable_columns();
        $this->assertContains( 'name', $sortable );
        $this->assertNotContains( 'email', $sortable );
    }

    public function test_bulk_actions() {
        $view = new BAF_View_Table( array(
            'id'           => 1,
            'form_id'      => 1,
            'bulk_actions' => array(
                array( 'action' => 'delete', 'label' => 'Delete Selected' ),
                array( 'action' => 'export', 'label' => 'Export Selected' ),
            ),
        ) );
        
        $this->assertTrue( $view->has_bulk_actions() );
        $actions = $view->get_bulk_actions();
        $this->assertCount( 2, $actions );
    }

    public function test_row_actions() {
        $view = new BAF_View_Table( array(
            'id'          => 1,
            'form_id'     => 1,
            'row_actions' => array(
                array( 'action' => 'view', 'label' => 'View', 'icon' => 'eye' ),
                array( 'action' => 'edit', 'label' => 'Edit', 'icon' => 'pencil' ),
            ),
        ) );
        
        $actions = $view->get_row_actions();
        $this->assertCount( 2, $actions );
    }

    public function test_column_width() {
        $view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'columns' => array(
                array( 'field' => 'name', 'label' => 'Name', 'width' => '200px' ),
                array( 'field' => 'email', 'label' => 'Email', 'width' => 'auto' ),
            ),
        ) );
        
        $columns = $view->get_columns();
        $this->assertEquals( '200px', $columns[0]['width'] );
    }

    public function test_sticky_header() {
        $view = new BAF_View_Table( array(
            'id'            => 1,
            'form_id'       => 1,
            'sticky_header' => true,
        ) );
        
        $this->assertTrue( $view->has_sticky_header() );
    }

    public function test_zebra_striping() {
        $view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'zebra'   => true,
        ) );
        
        $this->assertTrue( $view->is_zebra_striped() );
    }

    public function test_responsive_mode() {
        $view = new BAF_View_Table( array(
            'id'         => 1,
            'form_id'    => 1,
            'responsive' => 'scroll',
        ) );
        
        $this->assertEquals( 'scroll', $view->get_responsive_mode() );
    }

    public function test_column_visibility() {
        $view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'columns' => array(
                array( 'field' => 'name', 'label' => 'Name', 'visible' => true ),
                array( 'field' => 'id', 'label' => 'ID', 'visible' => false ),
            ),
        ) );
        
        $visible = $view->get_visible_columns();
        $this->assertCount( 1, $visible );
        $this->assertEquals( 'name', $visible[0]['field'] );
    }

    public function test_accessibility_attributes() {
        $attrs = $this->view->get_table_attributes();
        
        $this->assertArrayHasKey( 'role', $attrs );
        $this->assertEquals( 'table', $attrs['role'] );
        $this->assertArrayHasKey( 'aria-label', $attrs );
    }

    public function test_caption() {
        $view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'caption' => 'Form entries table showing submitted data',
        ) );
        
        $this->assertEquals( 'Form entries table showing submitted data', $view->get_caption() );
    }

    public function test_column_alignment() {
        $view = new BAF_View_Table( array(
            'id'      => 1,
            'form_id' => 1,
            'columns' => array(
                array( 'field' => 'name', 'label' => 'Name', 'align' => 'left' ),
                array( 'field' => 'amount', 'label' => 'Amount', 'align' => 'right' ),
            ),
        ) );
        
        $columns = $view->get_columns();
        $this->assertEquals( 'left', $columns[0]['align'] );
        $this->assertEquals( 'right', $columns[1]['align'] );
    }

    public function test_empty_state() {
        $view = new BAF_View_Table( array(
            'id'            => 1,
            'form_id'       => 1,
            'empty_message' => 'No entries found.',
            'empty_icon'    => 'inbox',
        ) );
        
        $this->assertEquals( 'No entries found.', $view->get_empty_message() );
    }

    public function test_loading_state() {
        $view = new BAF_View_Table( array(
            'id'              => 1,
            'form_id'         => 1,
            'loading_message' => 'Loading entries...',
        ) );
        
        $this->assertEquals( 'Loading entries...', $view->get_loading_message() );
    }

    public function test_inline_editing() {
        $view = new BAF_View_Table( array(
            'id'             => 1,
            'form_id'        => 1,
            'inline_editing' => true,
            'editable_fields' => array( 'status', 'notes' ),
        ) );
        
        $this->assertTrue( $view->supports_inline_editing() );
        $this->assertContains( 'status', $view->get_editable_fields() );
    }
}
