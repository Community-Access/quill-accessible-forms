<?php
/**
 * Number Field Tests
 *
 * validate() returns a bool and leaves messages in get_errors().
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
            'id'    => 'quantity',
            'label' => 'Quantity',
        ) );
    }

    public function test_field_type() {
        $this->assertEquals( 'number', $this->field->get_type() );
    }

    public function test_valid_integer() {
        $this->assertTrue( $this->field->validate( '42' ) );
    }

    public function test_valid_decimal() {
        $this->assertTrue( $this->field->validate( '42.5' ) );
    }

    public function test_negative_number() {
        $this->assertTrue( $this->field->validate( '-10' ) );
    }

    public function test_invalid_number() {
        $this->assertFalse( $this->field->validate( 'abc' ) );
        $this->assertEquals( array( 'Please enter a valid number.' ), $this->field->get_errors() );
    }

    public function test_minimum_value() {
        $field = new QAF_Field_Number( array( 'id' => 'age', 'min' => 18 ) );

        $this->assertFalse( $field->validate( '17' ) );
        $this->assertTrue( $field->validate( '18' ) );
    }

    public function test_maximum_value() {
        $field = new QAF_Field_Number( array( 'id' => 'rating', 'max' => 10 ) );

        $this->assertFalse( $field->validate( '11' ) );
        $this->assertTrue( $field->validate( '10' ) );
    }

    public function test_step_validation() {
        $field = new QAF_Field_Number( array( 'id' => 'qty', 'step' => 5, 'min' => 1 ) );

        // Steps count from min: 1, 6, 11...
        $this->assertTrue( $field->validate( '11' ) );
        $this->assertFalse( $field->validate( '10' ) );

        $cents = new QAF_Field_Number( array( 'id' => 'price', 'step' => '0.01' ) );
        $this->assertTrue( $cents->validate( '19.99' ) );
        $this->assertFalse( $cents->validate( '19.995' ) );

        // "any" is the HTML way of saying no step.
        $any = new QAF_Field_Number( array( 'id' => 'x', 'step' => 'any' ) );
        $this->assertTrue( $any->validate( '1.2345' ) );
    }

    public function test_integer_only() {
        $field = new QAF_Field_Number( array( 'id' => 'count', 'format' => 'integer' ) );

        $this->assertFalse( $field->validate( '5.5' ) );
        $this->assertEquals( array( 'Please enter a whole number.' ), $field->get_errors() );
        $this->assertTrue( $field->validate( '5' ) );
        $this->assertSame( '5', $field->sanitize( '5.9' ) );
    }

    public function test_number_sanitization() {
        $this->assertEquals( 42.50, $this->field->sanitize( ' 42.50 ' ) );
        $this->assertSame( '', $this->field->sanitize( '' ) );
        $this->assertEquals( 1234.5, $this->field->sanitize( '$1,234.50' ) );
    }

    public function test_currency_format() {
        $field = new QAF_Field_Number( array(
            'id'     => 'price',
            'format' => 'currency',
            'prefix' => '$',
        ) );

        $this->assertEquals( '$1,099.90', $field->format_display( 1099.9 ) );
    }

    public function test_input_attributes() {
        $field = new QAF_Field_Number( array(
            'id'   => 'qty',
            'min'  => 0,
            'max'  => 100,
            'step' => 1,
        ), 4 );

        $html = $field->render();
        $this->assertStringContainsString( 'type="number"', $html );
        $this->assertStringContainsString( 'min="0"', $html );
        $this->assertStringContainsString( 'max="100"', $html );
        $this->assertStringContainsString( 'step="1"', $html );
        // The range hint is announced with the input.
        $this->assertStringContainsString( 'aria-describedby="qaf-field-4-qty-range"', $html );
        $this->assertStringContainsString( 'Enter a number between 0 and 100', $html );
    }

    public function test_empty_value_not_required() {
        $this->assertTrue( $this->field->validate( '' ) );
    }

    public function test_positive_only() {
        // There is no "positive" switch: a minimum of 0 is how it is said.
        $field = new QAF_Field_Number( array( 'id' => 'qty', 'min' => 0 ) );

        $this->assertFalse( $field->validate( '-5' ) );
        $this->assertTrue( $field->validate( '0' ) );
    }

    public function test_precision() {
        // "decimals" governs display; the stored value is kept as entered.
        $field = new QAF_Field_Number( array( 'id' => 'price', 'decimals' => 2 ) );

        $this->assertEquals( '100.00', $field->format_display( $field->sanitize( '99.999' ) ) );
        $this->assertEquals( '99.999', $field->sanitize( '99.999' ) );
    }
}
