<?php
/**
 * Email Field Tests
 *
 * validate() returns a bool and leaves messages in get_errors().
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
            'id'    => 'email',
            'label' => 'Email Address',
        ) );
    }

    protected function tearDown(): void {
        unset( $_POST['qaf_fields'] );
        parent::tearDown();
    }

    public function test_field_type() {
        $this->assertEquals( 'email', $this->field->get_type() );
    }

    public function test_valid_email() {
        $this->assertTrue( $this->field->validate( 'test@example.com' ) );
        $this->assertEmpty( $this->field->get_errors() );
    }

    public function test_invalid_email() {
        $this->assertFalse( $this->field->validate( 'not-an-email' ) );
        $this->assertEquals( array( 'Please enter a valid email address.' ), $this->field->get_errors() );
    }

    public function test_email_with_subdomain() {
        $this->assertTrue( $this->field->validate( 'user@mail.example.com' ) );
    }

    public function test_email_with_plus_sign() {
        $this->assertTrue( $this->field->validate( 'user+tag@example.com' ) );
    }

    public function test_empty_email_not_required() {
        $this->assertTrue( $this->field->validate( '' ) );
    }

    public function test_empty_email_required() {
        $field = new QAF_Field_Email( array(
            'id'       => 'email',
            'required' => true,
        ) );

        $this->assertFalse( $field->validate( '' ) );
    }

    public function test_disposable_domain_blocked_by_default() {
        $this->assertFalse( $this->field->validate( 'someone@mailinator.com' ) );
        $this->assertEquals( array( 'Please use a permanent email address.' ), $this->field->get_errors() );
    }

    public function test_email_sanitization() {
        // sanitize_email() strips characters an address cannot hold; it does
        // not change case (WordPress keeps the local part as typed).
        $sanitized = $this->field->sanitize( ' Test@Example.com ' );
        $this->assertEquals( 'Test@Example.com', $sanitized );
    }

    public function test_input_type_attribute() {
        $html = $this->field->render();
        $this->assertStringContainsString( 'type="email"', $html );
        $this->assertStringContainsString( 'name="qaf_field_email"', $html );
    }

    public function test_autocomplete_attribute() {
        $html = $this->field->render();
        $this->assertStringContainsString( 'autocomplete="email"', $html );
    }

    public function test_domain_restriction() {
        // Arrays (JSON/REST/BFL imports) and newline lists (admin UI) both work.
        foreach ( array( array( 'company.com', 'company.org' ), "company.com\ncompany.org" ) as $allowed ) {
            $field = new QAF_Field_Email( array(
                'id'              => 'email',
                'allowed_domains' => $allowed,
            ) );

            $this->assertTrue( $field->validate( 'user@company.com' ) );
            $this->assertTrue( $field->validate( 'user@COMPANY.org' ) );
            $this->assertFalse( $field->validate( 'user@other.com' ) );
        }
    }

    public function test_blocked_domains() {
        foreach ( array( array( 'spam.com', 'fake.com' ), "spam.com\r\nfake.com" ) as $blocked ) {
            $field = new QAF_Field_Email( array(
                'id'              => 'email',
                'blocked_domains' => $blocked,
            ) );

            $this->assertFalse( $field->validate( 'user@spam.com' ) );
            $this->assertTrue( $field->validate( 'user@legit.com' ) );
        }
    }

    public function test_confirmation_field() {
        $field = new QAF_Field_Email( array(
            'id'            => 'email',
            'confirm_email' => true,
        ), 2 );

        $html = $field->render();
        $this->assertStringContainsString( 'id="qaf-field-2-email-confirm"', $html );
        $this->assertStringContainsString( '<label for="qaf-field-2-email-confirm"', $html );

        $_POST['qaf_fields'] = array( 'email_confirm' => 'other@example.com' );
        $this->assertFalse( $field->validate( 'user@example.com' ) );
        $this->assertEquals( array( 'Email addresses do not match.' ), $field->get_errors() );

        $_POST['qaf_fields'] = array( 'email_confirm' => 'USER@example.com' );
        $this->assertTrue( $field->validate( 'user@example.com' ) );
    }
}
