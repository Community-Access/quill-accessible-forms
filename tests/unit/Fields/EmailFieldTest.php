<?php
/**
 * Email Field Tests
 *
 * @package QAF
 */

class QAF_Field_Email_Test extends QAF_Unit_Test_Case {

    /**
     * @var QAF_Field_Email
     */
    private $field;

    protected function setUp(): void {
        parent::setUp();
        
        require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';
        require_once QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-email.php';
        
        $this->field = new QAF_Field_Email( array(
            'name'  => 'email',
            'label' => 'Email Address',
        ) );
    }

    public function test_field_type() {
        $this->assertEquals( 'email', $this->field->get_type() );
    }

    public function test_valid_email() {
        $result = $this->field->validate( 'test@example.com' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_invalid_email() {
        $result = $this->field->validate( 'not-an-email' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_email_with_subdomain() {
        $result = $this->field->validate( 'user@mail.example.com' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_email_with_plus_sign() {
        $result = $this->field->validate( 'user+tag@example.com' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_empty_email_not_required() {
        $result = $this->field->validate( '' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_empty_email_required() {
        $field = new QAF_Field_Email( array(
            'name'     => 'email',
            'required' => true,
        ) );
        
        $result = $field->validate( '' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_email_sanitization() {
        $sanitized = $this->field->sanitize( ' Test@Example.com ' );
        $this->assertEquals( 'test@example.com', $sanitized );
    }

    public function test_input_type_attribute() {
        $attrs = $this->field->get_attributes();
        $this->assertEquals( 'email', $attrs['type'] );
    }

    public function test_autocomplete_attribute() {
        $attrs = $this->field->get_attributes();
        $this->assertEquals( 'email', $attrs['autocomplete'] );
    }

    public function test_domain_restriction() {
        $field = new QAF_Field_Email( array(
            'name'            => 'email',
            'allowed_domains' => array( 'company.com', 'company.org' ),
        ) );
        
        $result = $field->validate( 'user@company.com' );
        $this->assertTrue( $result['valid'] );
        
        $result = $field->validate( 'user@other.com' );
        $this->assertFalse( $result['valid'] );
    }

    public function test_blocked_domains() {
        $field = new QAF_Field_Email( array(
            'name'            => 'email',
            'blocked_domains' => array( 'spam.com', 'fake.com' ),
        ) );
        
        $result = $field->validate( 'user@spam.com' );
        $this->assertFalse( $result['valid'] );
        
        $result = $field->validate( 'user@legit.com' );
        $this->assertTrue( $result['valid'] );
    }

    public function test_confirmation_field() {
        $field = new QAF_Field_Email( array(
            'name'         => 'email',
            'confirmation' => true,
        ) );
        
        $this->assertTrue( $field->requires_confirmation() );
    }
}
