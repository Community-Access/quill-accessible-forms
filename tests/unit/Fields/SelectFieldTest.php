<?php
/**
 * Select Field Tests
 *
 * validate() returns a bool and leaves messages in get_errors(); options are
 * checked through render(), which is what a visitor gets.
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
            'id'      => 'country',
            'label'   => 'Country',
            'choices' => array(
                array( 'value' => 'us', 'label' => 'United States' ),
                array( 'value' => 'ca', 'label' => 'Canada' ),
                array( 'value' => 'uk', 'label' => 'United Kingdom' ),
            ),
        ), 5 );
    }

    public function test_field_type() {
        $this->assertEquals( 'select', $this->field->get_type() );
    }

    public function test_valid_selection() {
        $this->assertTrue( $this->field->validate( 'us' ) );
    }

    public function test_invalid_selection() {
        $this->assertFalse( $this->field->validate( 'invalid' ) );
        $this->assertEquals( array( 'Please select a valid option.' ), $this->field->get_errors() );
    }

    public function test_empty_selection_not_required() {
        $this->assertTrue( $this->field->validate( '' ) );
    }

    public function test_empty_selection_required() {
        $field = new QAF_Field_Select( array(
            'id'       => 'country',
            'required' => true,
            'choices'  => array( array( 'value' => 'us', 'label' => 'US' ) ),
        ) );

        $this->assertFalse( $field->validate( '' ) );
    }

    public function test_options_count() {
        $html = $this->field->render();
        // Three choices plus the placeholder option.
        $this->assertSame( 4, substr_count( $html, '<option ' ) );
    }

    public function test_placeholder_option() {
        $field = new QAF_Field_Select( array(
            'id'          => 'country',
            'placeholder' => 'Select a country...',
            'choices'     => array( array( 'value' => 'us', 'label' => 'US' ) ),
        ) );

        $this->assertStringContainsString( '<option value="">Select a country...</option>', $field->render() );
    }

    public function test_optgroups() {
        $field = new QAF_Field_Select( array(
            'id'        => 'location',
            'optgroups' => true,
            'choices'   => array(
                array(
                    'label'   => 'North America',
                    'options' => array(
                        array( 'value' => 'us', 'label' => 'United States' ),
                        array( 'value' => 'ca', 'label' => 'Canada' ),
                    ),
                ),
                array(
                    'label'   => 'Europe',
                    'options' => array(
                        array( 'value' => 'uk', 'label' => 'United Kingdom' ),
                    ),
                ),
            ),
        ) );

        $html = $field->render();
        $this->assertStringContainsString( '<optgroup label="North America">', $html );
        $this->assertStringContainsString( '<optgroup label="Europe">', $html );
    }

    public function test_value_display() {
        $field = $this->field;
        $field->set_value( 'ca' );

        $this->assertStringContainsString( '<option value="ca" selected>Canada</option>', $field->render() );
    }

    public function test_multiple_selection() {
        $field = new QAF_Field_Select( array(
            'id'       => 'countries',
            'multiple' => true,
            'choices'  => array(
                array( 'value' => 'us', 'label' => 'US' ),
                array( 'value' => 'ca', 'label' => 'CA' ),
            ),
        ) );

        $html = $field->render();
        $this->assertStringContainsString( 'multiple', $html );
        $this->assertStringContainsString( 'name="qaf_field_countries[]"', $html );

        $this->assertTrue( $field->validate( array( 'us', 'ca' ) ) );
        $this->assertFalse( $field->validate( array( 'us', 'mx' ) ) );
    }

    public function test_searchable_attribute() {
        $field = new QAF_Field_Select( array(
            'id'         => 'country',
            'searchable' => true,
        ) );

        $this->assertStringContainsString( 'qaf-select-searchable', $field->render() );
    }

    public function test_choices_from_text_lines() {
        // The admin UI stores choices one per line, value|label or a bare label.
        $field = new QAF_Field_Select( array(
            'id'      => 'size',
            'choices' => "s|Small\nMedium\n\nl|Large",
        ) );

        $html = $field->render();
        $this->assertStringContainsString( '<option value="s">Small</option>', $html );
        $this->assertStringContainsString( '<option value="Medium">Medium</option>', $html );
        $this->assertTrue( $field->validate( 'l' ) );
        $this->assertFalse( $field->validate( 'xl' ) );
    }

    public function test_cascading_select_waits_for_parent() {
        $field = new QAF_Field_Select( array(
            'id'           => 'state',
            'cascade_from' => 'country',
            'choices'      => array( array( 'value' => 'ny', 'label' => 'New York' ) ),
        ) );

        $html = $field->render();
        $this->assertStringContainsString( 'data-cascade-from="country"', $html );
        $this->assertStringNotContainsString( 'New York', $html );
        $this->assertStringContainsString( 'aria-live="polite"', $html );
    }

    public function test_default_value() {
        $field = new QAF_Field_Select( array(
            'id'            => 'country',
            'default_value' => 'us',
            'choices'       => array(
                array( 'value' => 'us', 'label' => 'US' ),
                array( 'value' => 'ca', 'label' => 'CA' ),
            ),
        ) );

        $this->assertEquals( 'us', $field->get_value() );
        $this->assertStringContainsString( '<option value="us" selected>US</option>', $field->render() );
    }

    public function test_sanitization() {
        $sanitized = $this->field->sanitize( '<script>us</script>' );
        $this->assertStringNotContainsString( '<script>', $sanitized );
    }

    public function test_aria_attributes() {
        $field = new QAF_Field_Select( array(
            'id'          => 'country',
            'label'       => 'Country',
            'required'    => true,
            'description' => 'Where you live',
            'choices'     => array( array( 'value' => 'us', 'label' => 'US' ) ),
        ), 5 );

        $html = $field->render();
        $this->assertStringContainsString( 'aria-required="true"', $html );
        $this->assertStringContainsString( 'aria-describedby="qaf-field-5-country-desc"', $html );
        $this->assertStringContainsString( '<label for="qaf-field-5-country"', $html );
    }
}
