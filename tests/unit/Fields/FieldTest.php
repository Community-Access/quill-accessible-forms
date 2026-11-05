<?php
/**
 * Field Base Class Tests
 *
 * @package QAF
 */

class QAF_Field_Test extends QAF_Unit_Test_Case {

    /**
     * Test field initialization
     */
    public function test_field_initialization() {
        $config = array(
            'name'        => 'test_field',
            'type'        => 'text',
            'label'       => 'Test Field',
            'required'    => true,
            'placeholder' => 'Enter text...',
        );

        $field = new QAF_Field( $config );

        $this->assertEquals( 'test_field', $field->get_name() );
        $this->assertEquals( 'text', $field->get_type() );
        $this->assertEquals( 'Test Field', $field->get_label() );
        $this->assertTrue( $field->is_required() );
    }

    /**
     * Test field default values
     */
    public function test_field_default_values() {
        $field = new QAF_Field( array(
            'name' => 'test',
            'type' => 'text',
        ) );

        $this->assertFalse( $field->is_required() );
        $this->assertFalse( $field->is_disabled() );
        $this->assertFalse( $field->is_readonly() );
        $this->assertEquals( '', $field->get_default() );
    }

    /**
     * Test field validation - required
     */
    public function test_field_validation_required() {
        $field = new QAF_Field( array(
            'name'     => 'test',
            'type'     => 'text',
            'required' => true,
        ) );

        $result = $field->validate( '' );
        $this->assertFalse( $result['valid'] );
        $this->assertContains( 'required', strtolower( $result['errors'][0] ) );

        $result = $field->validate( 'value' );
        $this->assertTrue( $result['valid'] );
    }

    /**
     * Test field sanitization
     */
    public function test_field_sanitization() {
        $field = new QAF_Field( array(
            'name' => 'test',
            'type' => 'text',
        ) );

        $sanitized = $field->sanitize( '<script>alert("XSS")</script>Hello' );
        $this->assertStringNotContainsString( '<script>', $sanitized );
    }

    /**
     * Test field ID generation
     */
    public function test_field_id_generation() {
        $field = new QAF_Field( array(
            'name' => 'user_email',
            'type' => 'email',
        ) );

        $id = $field->get_id();
        $this->assertStringContainsString( 'user_email', $id );
        $this->assertStringStartsWith( 'qaf-field-', $id );
    }

    /**
     * Test field CSS classes
     */
    public function test_field_css_classes() {
        $field = new QAF_Field( array(
            'name'     => 'test',
            'type'     => 'text',
            'required' => true,
            'class'    => 'custom-class',
        ) );

        $classes = $field->get_classes();
        $this->assertContains( 'qaf-field', $classes );
        $this->assertContains( 'qaf-field-text', $classes );
        $this->assertContains( 'qaf-field-required', $classes );
        $this->assertContains( 'custom-class', $classes );
    }

    /**
     * Test field ARIA attributes
     */
    public function test_field_aria_attributes() {
        $field = new QAF_Field( array(
            'name'        => 'test',
            'type'        => 'text',
            'required'    => true,
            'description' => 'Help text',
        ) );

        $aria = $field->get_aria_attributes();
        $this->assertArrayHasKey( 'aria-required', $aria );
        $this->assertEquals( 'true', $aria['aria-required'] );
        $this->assertArrayHasKey( 'aria-describedby', $aria );
    }

    /**
     * Test field conditional logic
     */
    public function test_field_conditional_logic() {
        $field = new QAF_Field( array(
            'name'        => 'test',
            'type'        => 'text',
            'conditional' => array(
                'field'    => 'other_field',
                'operator' => 'equals',
                'value'    => 'yes',
                'action'   => 'show',
            ),
        ) );

        $this->assertTrue( $field->has_conditional_logic() );
        
        $conditions = $field->get_conditional_logic();
        $this->assertEquals( 'other_field', $conditions['field'] );
        $this->assertEquals( 'show', $conditions['action'] );
    }

    /**
     * Test field render attributes
     */
    public function test_field_render_attributes() {
        $field = new QAF_Field( array(
            'name'        => 'test',
            'type'        => 'text',
            'maxlength'   => 100,
            'placeholder' => 'Enter value',
            'readonly'    => true,
        ) );

        $attrs = $field->get_attributes();
        $this->assertEquals( 100, $attrs['maxlength'] );
        $this->assertEquals( 'Enter value', $attrs['placeholder'] );
        $this->assertTrue( $attrs['readonly'] );
    }

    /**
     * Test field value transformation
     */
    public function test_field_value_transformation() {
        $field = new QAF_Field( array(
            'name'      => 'test',
            'type'      => 'text',
            'transform' => 'uppercase',
        ) );

        // Test value transformation on save
        $value = $field->transform( 'hello world' );
        $this->assertEquals( 'HELLO WORLD', $value );
    }

    /**
     * Test field export data
     */
    public function test_field_export_data() {
        $field = new QAF_Field( array(
            'name'  => 'test_field',
            'type'  => 'text',
            'label' => 'Test Field',
        ) );

        $export = $field->to_array();
        
        $this->assertArrayHasKeys( array( 'name', 'type', 'label' ), $export );
        $this->assertEquals( 'test_field', $export['name'] );
    }

    /**
     * Test field JSON serialization
     */
    public function test_field_json_serialization() {
        $field = new QAF_Field( array(
            'name' => 'test',
            'type' => 'text',
        ) );

        $json = json_encode( $field );
        $this->assertValidJson( $json );
        
        $decoded = json_decode( $json, true );
        $this->assertEquals( 'test', $decoded['name'] );
    }
}
