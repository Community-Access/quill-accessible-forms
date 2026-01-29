<?php
/**
 * View Base Class Tests
 *
 * @package BAF
 */

class BAF_View_Test extends BAF_Unit_Test_Case {

    /**
     * Test view initialization
     */
    public function test_view_initialization() {
        $config = array(
            'id'      => 1,
            'form_id' => 1,
            'type'    => 'table',
            'title'   => 'Test View',
            'columns' => array( 'name', 'email' ),
        );

        $view = new BAF_View( $config );

        $this->assertEquals( 1, $view->get_id() );
        $this->assertEquals( 'table', $view->get_type() );
        $this->assertEquals( 'Test View', $view->get_title() );
    }

    /**
     * Test view columns
     */
    public function test_view_columns() {
        $view = new BAF_View( array(
            'id'      => 1,
            'form_id' => 1,
            'type'    => 'table',
            'columns' => array(
                array( 'field' => 'name', 'label' => 'Name', 'sortable' => true ),
                array( 'field' => 'email', 'label' => 'Email', 'sortable' => false ),
            ),
        ) );

        $columns = $view->get_columns();
        $this->assertCount( 2, $columns );
        $this->assertTrue( $columns[0]['sortable'] );
    }

    /**
     * Test view filters
     */
    public function test_view_filters() {
        $view = new BAF_View( array(
            'id'      => 1,
            'form_id' => 1,
            'type'    => 'table',
            'filters' => array(
                array( 'field' => 'status', 'operator' => 'equals', 'value' => 'approved' ),
            ),
        ) );

        $this->assertTrue( $view->has_filters() );
        $filters = $view->get_filters();
        $this->assertEquals( 'status', $filters[0]['field'] );
    }

    /**
     * Test view sorting
     */
    public function test_view_sorting() {
        $view = new BAF_View( array(
            'id'      => 1,
            'form_id' => 1,
            'type'    => 'table',
            'sort'    => array(
                'field' => 'created_at',
                'order' => 'DESC',
            ),
        ) );

        $sort = $view->get_sort();
        $this->assertEquals( 'created_at', $sort['field'] );
        $this->assertEquals( 'DESC', $sort['order'] );
    }

    /**
     * Test view pagination
     */
    public function test_view_pagination() {
        $view = new BAF_View( array(
            'id'        => 1,
            'form_id'   => 1,
            'type'      => 'table',
            'per_page'  => 25,
            'paginate'  => true,
        ) );

        $this->assertTrue( $view->is_paginated() );
        $this->assertEquals( 25, $view->get_per_page() );
    }

    /**
     * Test view permissions
     */
    public function test_view_permissions() {
        $view = new BAF_View( array(
            'id'          => 1,
            'form_id'     => 1,
            'type'        => 'table',
            'permissions' => array(
                'view'   => 'public',
                'edit'   => 'admin',
                'delete' => 'admin',
            ),
        ) );

        $perms = $view->get_permissions();
        $this->assertEquals( 'public', $perms['view'] );
        $this->assertEquals( 'admin', $perms['edit'] );
    }

    /**
     * Test view search configuration
     */
    public function test_view_search() {
        $view = new BAF_View( array(
            'id'            => 1,
            'form_id'       => 1,
            'type'          => 'table',
            'searchable'    => true,
            'search_fields' => array( 'name', 'email', 'comments' ),
        ) );

        $this->assertTrue( $view->is_searchable() );
        $this->assertContains( 'name', $view->get_search_fields() );
    }

    /**
     * Test view export data
     */
    public function test_view_export() {
        $view = new BAF_View( array(
            'id'      => 1,
            'form_id' => 1,
            'type'    => 'table',
            'title'   => 'Test View',
        ) );

        $export = $view->to_array();
        $this->assertArrayHasKeys( array( 'id', 'form_id', 'type', 'title' ), $export );
    }

    /**
     * Test view JSON serialization
     */
    public function test_view_json_serialization() {
        $view = new BAF_View( array(
            'id'      => 1,
            'form_id' => 1,
            'type'    => 'table',
        ) );

        $json = json_encode( $view );
        $this->assertValidJson( $json );
    }
}
