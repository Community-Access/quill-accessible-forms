<?php
/**
 * Cards View Tests
 *
 * @package BAF
 */

class BAF_View_Cards_Test extends BAF_Unit_Test_Case {

    /**
     * @var BAF_View_Cards
     */
    private $view;

    protected function setUp(): void {
        parent::setUp();
        
        require_once BAF_PLUGIN_DIR . 'includes/core/class-baf-view.php';
        require_once BAF_PLUGIN_DIR . 'includes/views/class-baf-view-cards.php';
        
        $this->view = new BAF_View_Cards( array(
            'id'      => 1,
            'form_id' => 1,
            'title'   => 'Entries Cards',
            'layout'  => array(
                'title_field'   => 'name',
                'subtitle_field' => 'email',
                'image_field'   => 'photo',
                'content_fields' => array( 'message', 'status' ),
            ),
        ) );
    }

    public function test_view_type() {
        $this->assertEquals( 'cards', $this->view->get_type() );
    }

    public function test_card_layout() {
        $layout = $this->view->get_layout();
        
        $this->assertEquals( 'name', $layout['title_field'] );
        $this->assertEquals( 'email', $layout['subtitle_field'] );
    }

    public function test_columns_per_row() {
        $view = new BAF_View_Cards( array(
            'id'              => 1,
            'form_id'         => 1,
            'columns_per_row' => 3,
        ) );
        
        $this->assertEquals( 3, $view->get_columns_per_row() );
    }

    public function test_card_size() {
        $view = new BAF_View_Cards( array(
            'id'        => 1,
            'form_id'   => 1,
            'card_size' => 'large',
        ) );
        
        $this->assertEquals( 'large', $view->get_card_size() );
    }

    public function test_image_aspect_ratio() {
        $view = new BAF_View_Cards( array(
            'id'          => 1,
            'form_id'     => 1,
            'image_ratio' => '16:9',
        ) );
        
        $this->assertEquals( '16:9', $view->get_image_ratio() );
    }

    public function test_hover_effect() {
        $view = new BAF_View_Cards( array(
            'id'           => 1,
            'form_id'      => 1,
            'hover_effect' => 'lift',
        ) );
        
        $this->assertEquals( 'lift', $view->get_hover_effect() );
    }

    public function test_card_actions() {
        $view = new BAF_View_Cards( array(
            'id'           => 1,
            'form_id'      => 1,
            'card_actions' => array(
                array( 'action' => 'view', 'label' => 'View Details' ),
                array( 'action' => 'edit', 'label' => 'Edit Entry' ),
            ),
        ) );
        
        $actions = $view->get_card_actions();
        $this->assertCount( 2, $actions );
    }

    public function test_show_badges() {
        $view = new BAF_View_Cards( array(
            'id'          => 1,
            'form_id'     => 1,
            'badge_field' => 'status',
            'badge_colors' => array(
                'new'      => 'blue',
                'approved' => 'green',
                'rejected' => 'red',
            ),
        ) );
        
        $this->assertTrue( $view->has_badges() );
        $colors = $view->get_badge_colors();
        $this->assertEquals( 'green', $colors['approved'] );
    }

    public function test_masonry_layout() {
        $view = new BAF_View_Cards( array(
            'id'       => 1,
            'form_id'  => 1,
            'masonry'  => true,
        ) );
        
        $this->assertTrue( $view->is_masonry() );
    }

    public function test_card_click_action() {
        $view = new BAF_View_Cards( array(
            'id'          => 1,
            'form_id'     => 1,
            'click_action' => 'modal',
        ) );
        
        $this->assertEquals( 'modal', $view->get_click_action() );
    }

    public function test_truncate_content() {
        $view = new BAF_View_Cards( array(
            'id'               => 1,
            'form_id'          => 1,
            'content_truncate' => 150,
        ) );
        
        $this->assertEquals( 150, $view->get_content_truncate() );
    }

    public function test_responsive_columns() {
        $view = new BAF_View_Cards( array(
            'id'       => 1,
            'form_id'  => 1,
            'columns'  => array(
                'xs' => 1,
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
            ),
        ) );
        
        $cols = $view->get_responsive_columns();
        $this->assertEquals( 1, $cols['xs'] );
        $this->assertEquals( 4, $cols['lg'] );
    }

    public function test_gap_size() {
        $view = new BAF_View_Cards( array(
            'id'      => 1,
            'form_id' => 1,
            'gap'     => '20px',
        ) );
        
        $this->assertEquals( '20px', $view->get_gap() );
    }

    public function test_card_border_radius() {
        $view = new BAF_View_Cards( array(
            'id'            => 1,
            'form_id'       => 1,
            'border_radius' => '8px',
        ) );
        
        $this->assertEquals( '8px', $view->get_border_radius() );
    }

    public function test_accessibility_attributes() {
        $attrs = $this->view->get_card_attributes();
        
        $this->assertArrayHasKey( 'role', $attrs );
        $this->assertEquals( 'article', $attrs['role'] );
    }

    public function test_animation() {
        $view = new BAF_View_Cards( array(
            'id'                => 1,
            'form_id'           => 1,
            'animation'         => 'fade-up',
            'animation_delay'   => 100,
            'animation_stagger' => true,
        ) );
        
        $this->assertEquals( 'fade-up', $view->get_animation() );
        $this->assertTrue( $view->has_stagger_animation() );
    }

    public function test_lazy_load_images() {
        $view = new BAF_View_Cards( array(
            'id'        => 1,
            'form_id'   => 1,
            'lazy_load' => true,
        ) );
        
        $this->assertTrue( $view->uses_lazy_loading() );
    }

    public function test_no_image_fallback() {
        $view = new BAF_View_Cards( array(
            'id'              => 1,
            'form_id'         => 1,
            'fallback_image'  => '/placeholder.png',
            'fallback_color'  => '#f0f0f0',
        ) );
        
        $this->assertEquals( '/placeholder.png', $view->get_fallback_image() );
        $this->assertEquals( '#f0f0f0', $view->get_fallback_color() );
    }
}
