<?php
/**
 * Every field posts under one name: qaf_field_<id>.
 *
 * Text, email and textarea used to render qaf_fields[<id>] while select,
 * number and every other type rendered qaf_field_<id>, so a form that mixed
 * them sent two shapes, and a form posted without the page script reached
 * the submission handler (which reads fields[<id>]) with nothing it could
 * read. Now every input is qaf_field_<id> (plus [] or [part]); the handler
 * gathers qaf_field_<id> and the old qaf_fields[<id>] into fields[<id>], so
 * a page rendered before the change and still open in a browser submits.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';

if ( ! function_exists( 'qaf_test_field_autoload' ) ) {
    /**
     * Load QAF_Field_* classes from includes/fields on demand.
     *
     * @param string $class Class name.
     */
    function qaf_test_field_autoload( $class ) {
        if ( strpos( $class, 'QAF_Field_' ) !== 0 ) {
            return;
        }
        $file = QAF_PLUGIN_DIR . 'includes/fields/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
    spl_autoload_register( 'qaf_test_field_autoload' );
}

class QAF_Field_Naming_Test extends QAF_Unit_Test_Case {

    protected function tearDown(): void {
        unset( $_POST['qaf_fields'], $_POST['qaf_field_email_confirm'], $_POST['fields'] );
        parent::tearDown();
    }

    /** Every name="..." in some HTML. */
    private static function names( $html ) {
        preg_match_all( '/\sname="([^"]*)"/', $html, $m );
        return $m[1];
    }

    public function data_types() {
        $two = array( array( 'value' => 'a', 'label' => 'A' ), array( 'value' => 'b', 'label' => 'B' ) );
        return array(
            'text'      => array( 'QAF_Field_Text', array() ),
            'email'     => array( 'QAF_Field_Email', array( 'confirm_email' => true ) ),
            'textarea'  => array( 'QAF_Field_Textarea', array() ),
            'select'    => array( 'QAF_Field_Select', array( 'choices' => $two ) ),
            'number'    => array( 'QAF_Field_Number', array() ),
            'rich text' => array( 'QAF_Field_Rich_Text', array() ),
            'checkbox'  => array( 'QAF_Field_Checkbox', array( 'choices' => $two ) ),
            'radio'     => array( 'QAF_Field_Radio', array( 'choices' => $two ) ),
            'phone'     => array( 'QAF_Field_Phone', array() ),
            'date'      => array( 'QAF_Field_Date', array() ),
            'hidden'    => array( 'QAF_Field_Hidden', array() ),
        );
    }

    /**
     * @dataProvider data_types
     */
    public function test_every_input_is_named_qaf_field_and_its_id( $class, $config ) {
        $field = new $class( array_merge( array( 'id' => 'q1', 'label' => 'Question' ), $config ), 4 );
        $names = self::names( $field->render() );
        $this->assertNotEmpty( $names, $class . ' renders no input' );
        foreach ( $names as $name ) {
            $this->assertMatchesRegularExpression( '/^qaf_field_q1(_[a-z]+)?(\[[^\]]*\])*$/', $name, $class );
        }
    }

    public function test_no_field_type_still_writes_the_old_array_name() {
        foreach ( glob( QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-*.php' ) as $file ) {
            $this->assertStringNotContainsString( "'qaf_fields['", file_get_contents( $file ), basename( $file ) );
        }
    }

    public function test_input_name_helpers() {
        $this->assertSame( 'qaf_field_email', QAF_Field::input_name_for( 'email' ) );
        $this->assertSame( 'qaf_field_email_confirm', QAF_Field::input_name_for( 'email', '_confirm' ) );
        $field = new QAF_Field_Text( array( 'id' => 'name', 'label' => 'Name' ), 1 );
        $this->assertSame( 'qaf_field_name', $field->get_input_name() );
    }

    public function test_posted_reads_the_name_then_the_old_name_then_fields() {
        $this->assertSame( 'new', QAF_Field::posted( 'a', '', array( 'qaf_field_a' => 'new', 'qaf_fields' => array( 'a' => 'old' ) ) ) );
        $this->assertSame( 'old', QAF_Field::posted( 'a', '', array( 'qaf_fields' => array( 'a' => 'old' ) ) ) );
        $this->assertSame( 'js', QAF_Field::posted( 'a', '_confirm', array( 'fields' => array( 'a_confirm' => 'js' ) ) ) );
        $this->assertNull( QAF_Field::posted( 'a', '', array( 'qaf_field_b' => 'x' ) ) );
    }

    public function test_submitted_values_gathers_every_shape_into_fields() {
        $params = array(
            'fields'         => array( 'name' => 'From the script' ),
            'qaf_field_name' => 'ignored: fields already has it',
            'qaf_field_size' => 'm',
            'qaf_field_tags' => array( 'a', 'b' ),
            'qaf_fields'     => array( 'email' => 'old@example.com', 'size' => 'ignored: the new name wins' ),
            '_qaf_token'     => 't',
        );
        $values = QAF_Field::submitted_values( $params, array( 'name', 'size', 'tags', 'email', 'missing' ) );
        $this->assertSame( array(
            'name'  => 'From the script',
            'size'  => 'm',
            'tags'  => array( 'a', 'b' ),
            'email' => 'old@example.com',
        ), $values );
    }

    public function test_the_submission_handler_reads_through_submitted_values() {
        $src = file_get_contents( QAF_PLUGIN_DIR . 'includes/api/class-qaf-rest-api.php' );
        $this->assertStringContainsString( 'QAF_Field::submitted_values(', $src );
    }

    public function test_email_confirmation_reads_the_new_name_and_the_old() {
        $field = new QAF_Field_Email( array( 'id' => 'email', 'confirm_email' => true ), 2 );
        $html  = $field->render();
        $this->assertStringContainsString( 'name="qaf_field_email"', $html );
        $this->assertStringContainsString( 'name="qaf_field_email_confirm"', $html );

        $_POST['qaf_field_email_confirm'] = 'other@example.com';
        $this->assertFalse( $field->validate( 'user@example.com' ) );
        $_POST['qaf_field_email_confirm'] = 'USER@example.com';
        $this->assertTrue( $field->validate( 'user@example.com' ) );

        // A page rendered before the change, still open, posts the old name.
        unset( $_POST['qaf_field_email_confirm'] );
        $_POST['qaf_fields'] = array( 'email_confirm' => 'user@example.com' );
        $this->assertTrue( $field->validate( 'user@example.com' ) );
    }

    public function test_rich_text_posts_its_textarea_and_labels_its_editing_area() {
        $field = new QAF_Field_Rich_Text( array( 'id' => 'bio', 'label' => 'About you', 'required' => true ), 3 );
        $html  = $field->render();
        $this->assertSame( array( 'qaf_field_bio' ), self::names( $html ) );
        $this->assertStringContainsString( '<label id="qaf-field-3-bio-label"', $html );
        $this->assertStringContainsString( 'aria-labelledby="qaf-field-3-bio-label"', $html );
        $this->assertStringContainsString( 'aria-required="true"', $html );
        $this->assertMatchesRegularExpression( '/<textarea[^>]*tabindex="-1"/', $html );
    }

    public function test_the_page_script_reads_every_shape_and_copies_rich_text() {
        $js = file_get_contents( QAF_PLUGIN_DIR . 'public/js/qaf-public.js' );
        $this->assertStringContainsString( "key.startsWith('qaf_field_')", $js );
        $this->assertStringContainsString( "key.startsWith('qaf_fields[')", $js, 'pages rendered before the change still submit' );
        $this->assertStringContainsString( '(\[\])?', $js, 'qaf_field_<id>[] gathers every checked value' );
        $this->assertStringContainsString( 'this.syncRichText(form);', $js );
    }
}
