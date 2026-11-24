<?php
/**
 * Field Base Class Tests
 *
 * QAF_Field is abstract (render() and get_field_settings() are per type), so
 * these tests drive it through the smallest concrete subclass.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';

if ( ! class_exists( 'QAF_Test_Plain_Field' ) ) {
    /**
     * Minimal concrete field: a text input inside the base wrapper.
     */
    class QAF_Test_Plain_Field extends QAF_Field {
        protected $type = 'text';

        public function get_field_settings() {
            return array();
        }

        public function render() {
            $attrs = array_merge(
                array(
                    'type'  => 'text',
                    'id'    => $this->get_html_id(),
                    'name'  => $this->get_input_name(),
                    'value' => (string) $this->get_value(),
                ),
                $this->get_aria_attributes(),
                $this->get_html5_attributes()
            );

            return $this->render_wrapper( '<input ' . $this->build_attributes( $attrs ) . '>' );
        }

        public function aria() {
            return $this->get_aria_attributes();
        }

        public function wrapper_classes() {
            return $this->get_wrapper_classes();
        }
    }
}

class QAF_Field_Test extends QAF_Unit_Test_Case {

    public function test_base_class_is_abstract() {
        $reflection = new ReflectionClass( 'QAF_Field' );
        $this->assertTrue( $reflection->isAbstract() );
    }

    /**
     * Test field initialization
     */
    public function test_field_initialization() {
        $field = new QAF_Test_Plain_Field( array(
            'id'          => 'test_field',
            'label'       => 'Test Field',
            'required'    => true,
            'placeholder' => 'Enter text...',
        ), 7 );

        $this->assertEquals( 'test_field', $field->get_id() );
        $this->assertEquals( 'text', $field->get_type() );
        $this->assertEquals( 'Test Field', $field->get_label() );
        $this->assertTrue( $field->is_required() );
    }

    /**
     * Test field default values
     */
    public function test_field_default_values() {
        $field = new QAF_Test_Plain_Field( array( 'id' => 'test' ) );

        $this->assertFalse( $field->is_required() );
        $this->assertFalse( $field->is_admin_only() );
        $this->assertSame( '', $field->get_description() );
        $this->assertSame( '', $field->get_value() );
        $this->assertSame( 'standard', $field->get_category() );
    }

    /**
     * Test required validation: bool result, errors via get_errors()
     */
    public function test_field_validation_required() {
        $field = new QAF_Test_Plain_Field( array( 'id' => 'name', 'required' => true ) );

        $this->assertFalse( $field->validate( '' ) );
        $this->assertCount( 1, $field->get_errors() );
        $this->assertEquals( 'This field is required.', $field->get_errors()[0] );

        // Errors reset on every validate() call.
        $this->assertTrue( $field->validate( 'Ada' ) );
        $this->assertEmpty( $field->get_errors() );

        // Not required: empty is fine.
        $optional = new QAF_Test_Plain_Field( array( 'id' => 'nick' ) );
        $this->assertTrue( $optional->validate( '' ) );
    }

    public function test_custom_error_message_replaces_default() {
        $field = new QAF_Test_Plain_Field( array(
            'id'                   => 'name',
            'required'             => true,
            'custom_error_message' => 'Tell us your name.',
        ) );

        $field->validate( '' );
        $this->assertEquals( array( 'Tell us your name.' ), $field->get_errors() );
    }

    /**
     * Test field sanitization
     */
    public function test_field_sanitization() {
        $field = new QAF_Test_Plain_Field( array( 'id' => 'test' ) );

        $sanitized = $field->sanitize( '<script>alert("xss")</script>Hello' );
        $this->assertStringNotContainsString( '<script>', $sanitized );
        $this->assertStringContainsString( 'Hello', $sanitized );

        $this->assertEquals( array( 'a', 'b' ), $field->sanitize( array( '<b>a</b>', 'b' ) ) );
    }

    /**
     * Test HTML ID generation: qaf-field-{form}-{field}
     */
    public function test_field_id_generation() {
        $field = new QAF_Test_Plain_Field( array( 'id' => 'my_field' ), 12 );

        $this->assertEquals( 'qaf-field-12-my_field', $field->get_html_id() );
    }

    /**
     * Test wrapper CSS classes
     */
    public function test_field_css_classes() {
        $field = new QAF_Test_Plain_Field( array(
            'id'        => 'test',
            'css_class' => 'custom-class another-class',
            'size'      => 'large',
        ) );

        $classes = $field->wrapper_classes();
        $this->assertContains( 'qaf-field', $classes );
        $this->assertContains( 'qaf-field-text', $classes );
        $this->assertContains( 'qaf-field-size-large', $classes );

        $html = $field->render();
        $this->assertStringContainsString( 'custom-class another-class', $html );
    }

    /**
     * Test ARIA attributes
     */
    public function test_field_aria_attributes() {
        $field = new QAF_Test_Plain_Field( array(
            'id'          => 'test',
            'required'    => true,
            'description' => 'Help text',
            'read_only'   => true,
        ), 3 );

        $aria = $field->aria();
        $this->assertEquals( 'true', $aria['aria-required'] );
        $this->assertEquals( 'qaf-field-3-test-desc', $aria['aria-describedby'] );
        $this->assertEquals( 'true', $aria['aria-readonly'] );
        $this->assertArrayNotHasKey( 'aria-invalid', $aria );

        // After a failed validation the error is described and the field invalid.
        $field->validate( '' );
        $aria = $field->aria();
        $this->assertEquals( 'true', $aria['aria-invalid'] );
        $this->assertStringContainsString( 'qaf-field-3-test-error', $aria['aria-describedby'] );
    }

    /**
     * Conditional logic is evaluated in the browser; the server passes it through.
     */
    public function test_field_conditional_logic() {
        $field = new QAF_Test_Plain_Field( array(
            'id'                => 'conditional_field',
            'conditional_logic' => array(
                'enabled' => true,
                'action'  => 'show',
                'logic'   => 'all',
                'rules'   => array(
                    array( 'field' => 'other', 'operator' => 'equals', 'value' => 'yes' ),
                ),
            ),
        ) );

        $js = $field->get_js_config();
        $this->assertTrue( $js['conditionalLogic']['enabled'] );
        $this->assertEquals( 'other', $js['conditionalLogic']['rules'][0]['field'] );
    }

    /**
     * Test the rendered label, input and live error container
     */
    public function test_field_render_attributes() {
        $field = new QAF_Test_Plain_Field( array(
            'id'       => 'test',
            'label'    => 'Your name',
            'required' => true,
        ), 1 );

        $html = $field->render();
        $this->assertStringContainsString( '<label for="qaf-field-1-test"', $html );
        $this->assertStringContainsString( 'id="qaf-field-1-test"', $html );
        $this->assertStringContainsString( 'aria-required="true"', $html );
        $this->assertStringContainsString( 'role="alert"', $html );
    }

    /**
     * Test set/get value and the default
     */
    public function test_field_value_transformation() {
        $field = new QAF_Test_Plain_Field( array( 'id' => 'test', 'default_value' => 'fallback' ) );

        $this->assertEquals( 'fallback', $field->get_value() );
        $field->set_value( 'typed' );
        $this->assertEquals( 'typed', $field->get_value() );
    }

    /**
     * Test display and export formatting
     */
    public function test_field_export_data() {
        $field = new QAF_Test_Plain_Field( array( 'id' => 'test' ) );

        $this->assertEquals( '&lt;b&gt;', $field->format_display( '<b>' ) );
        $this->assertEquals( 'a, b', $field->format_export( array( 'a', 'b' ) ) );
    }

    /**
     * Test JS config and BFL serialisation
     */
    public function test_field_json_serialization() {
        $field = new QAF_Test_Plain_Field( array(
            'id'          => 'test',
            'label'       => 'Test',
            'required'    => true,
            'placeholder' => 'Type here',
        ) );

        $js = $field->get_js_config();
        $this->assertEquals( 'test', $js['id'] );
        $this->assertEquals( 'text', $js['type'] );
        $this->assertTrue( $js['required'] );
        $this->assertEquals( 'This field is required.', $js['errorMessages']['required'] );

        $bfl = $field->to_bfl();
        $this->assertStringStartsWith( 'text #test "Test"', $bfl );
        $this->assertStringContainsString( '@required', $bfl );
        $this->assertStringContainsString( '@placeholder "Type here"', $bfl );
    }
}
