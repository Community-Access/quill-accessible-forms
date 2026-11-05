<?php
/**
 * Number Field Tests
 *
 * @package QAF
 */

class QAF_Field_Number_Test extends QAF_Unit_Test_Case {

    /**
     * @var QAF_Field_Number
     */
    private $field;

    protected function setUp(): void {
        parent::setUp();
        
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-number.php';
        
        $this->field = new QAF_Field_Number( array(
            'name'  => 'quantity',
            'label' => 'Quantity',
        ) );
    }

    public function test_field_type() {
        $this->assertEquals( 'number', $this->field->get_type() );
    }

    public function test_valid_integer() {
        $result = $this->field->validate( '42' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_valid_decimal() {
        $result = $this->field->validate( '3.14' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_negative_number() {
        $result = $this->field->validate( '-10' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_invalid_number() {
        $result = $this->field->validate( 'abc' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_minimum_value() {
        $field = new QAF_Field_Number( array(
            'name' => 'qty',
            'min'  => 1,
        ) );
        
        $result = $field->validate( '0' );
        $this->assertFalse( $result['valid'] );
        
        $result = $field->validate( '1' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_maximum_value() {
        $field = new QAF_Field_Number( array(
            'name' => 'qty',
            'max'  => 100,
        ) );
        
        $result = $field->validate( '101' );
        $this->assertFalse( $result['valid'] );
        
        $result = $field->validate( '100' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_step_validation() {
        $field = new QAF_Field_Number( array(
            'name' => 'qty',
            'step' => 5,
        ) );
        
        $result = $field->validate( '15' );
        $this->assertTrue( $result['valid'] );
        
        $result = $field->validate( '17' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_integer_only() {
        $field = new QAF_Field_Number( array(
            'name'    => 'qty',
            'integer' => true,
        ) );
        
        $result = $field->validate( '5.5' );
        $this->assertFalse( $result['valid'] );
        
        $result = $field->validate( '5' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_number_sanitization() {
        $sanitized = $this->field->sanitize( ' 42.50 ' );
        $this->assertEquals( 42.50, $sanitized );
    }

    public function test_currency_format() {
        $field = new QAF_Field_Number( array(
            'name'   => 'price',
            'format' => 'currency',
        ) );
        
        $display = $field->format_value( 99.99 );
        $this->assertStringContainsString( '99.99', $display );
    }

    public function test_input_attributes() {
        $field = new QAF_Field_Number( array(
            'name' => 'qty',
            'min'  => 0,
            'max'  => 100,
            'step' => 1,
        ) );
        
        $attrs = $field->get_attributes();
        $this->assertEquals( 'number', $attrs['type'] );
        $this->assertEquals( 0, $attrs['min'] );
        $this->assertEquals( 100, $attrs['max'] );
        $this->assertEquals( 1, $attrs['step'] );
    }

    public function test_empty_value_not_required() {
        $result = $this->field->validate( '' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_positive_only() {
        $field = new QAF_Field_Number( array(
            'name'     => 'qty',
            'positive' => true,
        ) );
        
        $result = $field->validate( '-5' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_precision() {
        $field = new QAF_Field_Number( array(
            'name'      => 'price',
            'precision' => 2,
        ) );
        
        $sanitized = $field->sanitize( '99.999' );
        $this->assertEquals( 100.00, $sanitized );
    }
}
