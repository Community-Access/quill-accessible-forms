<?php
/**
 * Select Field Tests
 *
 * @package QAF
 */

class QAF_Field_Select_Test extends QAF_Unit_Test_Case {

    /**
     * @var QAF_Field_Select
     */
    private $field;

    protected function setUp(): void {
        parent::setUp();
        
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-select.php';
        
        $this->field = new QAF_Field_Select( array(
            'name'    => 'country',
            'label'   => 'Country',
            'options' => array(
                array( 'value' => 'us', 'label' => 'United States' ),
                array( 'value' => 'uk', 'label' => 'United Kingdom' ),
                array( 'value' => 'ca', 'label' => 'Canada' ),
            ),
        ) );
    }

    public function test_field_type() {
        $this->assertEquals( 'select', $this->field->get_type() );
    }

    public function test_valid_selection() {
        $result = $this->field->validate( 'us' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_invalid_selection() {
        $result = $this->field->validate( 'invalid_country' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_empty_selection_not_required() {
        $result = $this->field->validate( '' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_empty_selection_required() {
        $field = new QAF_Field_Select( array(
            'name'     => 'country',
            'required' => true,
            'options'  => array(
                array( 'value' => 'us', 'label' => 'United States' ),
            ),
        ) );
        
        $result = $field->validate( '' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_options_count() {
        $options = $this->field->get_options();
        $this->assertCount( 3, $options );
    }

    public function test_placeholder_option() {
        $field = new QAF_Field_Select( array(
            'name'        => 'country',
            'placeholder' => 'Select a country...',
            'options'     => array(
                array( 'value' => 'us', 'label' => 'United States' ),
            ),
        ) );
        
        $options = $field->get_render_options();
        $this->assertEquals( '', $options[0]['value'] );
        $this->assertEquals( 'Select a country...', $options[0]['label'] );
    }

    public function test_optgroups() {
        $field = new QAF_Field_Select( array(
            'name'    => 'country',
            'options' => array(
                array(
                    'group'   => 'North America',
                    'options' => array(
                        array( 'value' => 'us', 'label' => 'United States' ),
                        array( 'value' => 'ca', 'label' => 'Canada' ),
                    ),
                ),
                array(
                    'group'   => 'Europe',
                    'options' => array(
                        array( 'value' => 'uk', 'label' => 'United Kingdom' ),
                    ),
                ),
            ),
        ) );
        
        $this->assertTrue( $field->has_optgroups() );
    }

    public function test_value_display() {
        $display = $this->field->get_display_value( 'us' );
        $this->assertEquals( 'United States', $display );
    }

    public function test_multiple_selection() {
        $field = new QAF_Field_Select( array(
            'name'     => 'countries',
            'multiple' => true,
            'options'  => array(
                array( 'value' => 'us', 'label' => 'United States' ),
                array( 'value' => 'uk', 'label' => 'United Kingdom' ),
            ),
        ) );
        
        $this->assertTrue( $field->is_multiple() );
        
        $result = $field->validate( array( 'us', 'uk' ) );
        $this->assertTrue( $result['valid'] );
    }

    public function test_min_selections() {
        $field = new QAF_Field_Select( array(
            'name'          => 'countries',
            'multiple'      => true,
            'minSelections' => 2,
            'options'       => array(
                array( 'value' => 'us', 'label' => 'United States' ),
                array( 'value' => 'uk', 'label' => 'United Kingdom' ),
                array( 'value' => 'ca', 'label' => 'Canada' ),
            ),
        ) );
        
        $result = $field->validate( array( 'us' ) );
        $this->assertFalse( $result['valid'] );
        
        $result = $field->validate( array( 'us', 'uk' ) );
        $this->assertTrue( $result['valid'] );
    }

    public function test_max_selections() {
        $field = new QAF_Field_Select( array(
            'name'          => 'countries',
            'multiple'      => true,
            'maxSelections' => 2,
            'options'       => array(
                array( 'value' => 'us', 'label' => 'United States' ),
                array( 'value' => 'uk', 'label' => 'United Kingdom' ),
                array( 'value' => 'ca', 'label' => 'Canada' ),
            ),
        ) );
        
        $result = $field->validate( array( 'us', 'uk', 'ca' ) );
        $this->assertFalse( $result['valid'] );
    }

    public function test_searchable_attribute() {
        $field = new QAF_Field_Select( array(
            'name'       => 'country',
            'searchable' => true,
            'options'    => array(),
        ) );
        
        $this->assertTrue( $field->is_searchable() );
    }

    public function test_dynamic_options() {
        $field = new QAF_Field_Select( array(
            'name'           => 'user',
            'optionsSource'  => 'users',
            'optionsEndpoint' => '/wp-json/qaf/v1/options/users',
        ) );
        
        $this->assertTrue( $field->has_dynamic_options() );
    }

    public function test_default_value() {
        $field = new QAF_Field_Select( array(
            'name'    => 'country',
            'default' => 'us',
            'options' => array(
                array( 'value' => 'us', 'label' => 'United States' ),
            ),
        ) );
        
        $this->assertEquals( 'us', $field->get_default() );
    }

    public function test_sanitization() {
        $sanitized = $this->field->sanitize( '<script>us</script>' );
        $this->assertEquals( 'us', $sanitized );
    }

    public function test_aria_attributes() {
        $aria = $this->field->get_aria_attributes();
        $this->assertArrayHasKey( 'role', $aria );
        $this->assertEquals( 'listbox', $aria['role'] );
    }
}
