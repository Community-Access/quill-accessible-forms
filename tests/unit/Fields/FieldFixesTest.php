<?php
/**
 * Tests for the 2026-11 field fixes
 *
 * - QAF_Field_Textarea was written against an older field API (render( $value ),
 *   get_setting(), validate() returning WP_Error) and never declared the abstract
 *   get_field_settings(), so loading it was a fatal error. It now follows QAF_Field.
 * - get_wrapper_classes() lives on QAF_Field: eighteen field types that build
 *   their own wrapper call it.
 * - Twenty-two field types declared validate() with no parameter, incompatible with
 *   QAF_Field::validate( $value ) -- a fatal error when the class loaded. They now
 *   take ( $value = null ) and keep the value when given one.
 * - The repeater validated each row's sub-field with no value, so every row passed.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-field.php';

if ( ! function_exists( 'qaf_test_field_autoload' ) ) {
    /**
     * Load QAF_Field_* classes from includes/fields on demand, so a type that
     * extends another loads in the right order.
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

class QAF_Field_Fixes_Test extends QAF_Unit_Test_Case {

    /**
     * Every concrete QAF_Field subclass under includes/fields.
     *
     * @return array Class names keyed by file.
     */
    private function field_classes() {
        $classes = array();

        foreach ( glob( QAF_PLUGIN_DIR . 'includes/fields/class-qaf-field-*.php' ) as $file ) {
            $class = str_replace( ' ', '_', ucwords( str_replace( '-', ' ', basename( $file, '.php' ) ) ) );
            $class = preg_replace( '/^Class_Qaf_Field/', 'QAF_Field', $class );

            if ( ! class_exists( $class ) ) {
                continue; // The registry, for one, is not a field.
            }

            $reflection = new ReflectionClass( $class );
            if ( $reflection->isSubclassOf( 'QAF_Field' ) && ! $reflection->isAbstract() ) {
                $classes[ basename( $file ) ] = $class;
            }
        }

        return $classes;
    }

    // ------------------------------------------------------------------
    // validate( $value = null ) across every field type
    // ------------------------------------------------------------------

    public function test_every_field_class_loads() {
        $classes = $this->field_classes();

        // Loading a class whose validate() is incompatible with the parent's is a
        // fatal error, so reaching this line already proves the signatures. Check
        // the count so a renamed file cannot silently drop out of the scan.
        $this->assertGreaterThanOrEqual( 60, count( $classes ), implode( ', ', $classes ) );
        $this->assertContains( 'QAF_Field_Textarea', $classes );
        $this->assertContains( 'QAF_Field_Repeater', $classes );
    }

    public function test_validate_signature_is_compatible_everywhere() {
        $parent = new ReflectionMethod( 'QAF_Field', 'validate' );
        $this->assertSame( 1, $parent->getNumberOfParameters() );

        foreach ( $this->field_classes() as $class ) {
            $method = new ReflectionMethod( $class, 'validate' );

            $this->assertSame( 1, $method->getNumberOfParameters(), "$class::validate() must take the value" );
            $this->assertLessThanOrEqual( 1, $method->getNumberOfRequiredParameters(), $class );
            $this->assertTrue( $method->isPublic(), $class );
        }
    }

    public function test_every_field_type_validates_without_a_fatal() {
        $failures = array();

        foreach ( $this->field_classes() as $class ) {
            try {
                $field = new $class( array( 'id' => 'f', 'label' => 'F' ), 1 );
                $empty = $field->validate( '' );
                $field->validate( 'x' );
                $this->assertIsBool( $empty, $class );
                $this->assertIsArray( $field->get_errors(), $class );
            } catch ( Throwable $e ) {
                $failures[] = $class . ': ' . $e->getMessage();
            }
        }

        $this->assertSame( array(), $failures );
    }

    /**
     * The types that are always valid (layout, display, computed) keep the value
     * they are handed, as QAF_Field::validate() callers expect.
     */
    public function test_always_valid_types_keep_the_value() {
        $always_valid = array(
            'QAF_Field_Html', 'QAF_Field_Page', 'QAF_Field_Section', 'QAF_Field_Submit',
            'QAF_Field_Columns', 'QAF_Field_Save', 'QAF_Field_Price',
        );

        foreach ( $always_valid as $class ) {
            $field = new $class( array( 'id' => 'f' ) );

            $this->assertTrue( $field->validate(), "$class without a value" );
            $this->assertTrue( $field->validate( 'kept' ), $class );
            $this->assertSame( 'kept', $field->get_value(), $class );
        }
    }

    // ------------------------------------------------------------------
    // get_wrapper_classes()
    // ------------------------------------------------------------------

    public function test_wrapper_classes_are_on_the_base_class() {
        $method = new ReflectionMethod( 'QAF_Field', 'get_wrapper_classes' );
        $this->assertSame( 'QAF_Field', $method->getDeclaringClass()->getName() );
        $this->assertTrue( $method->isProtected() );
    }

    public function test_wrapper_classes_content() {
        $field  = new QAF_Field_Toggle( array( 'id' => 'agree', 'required' => true, 'css_class' => 'mine', 'size' => 'small' ) );
        $method = new ReflectionMethod( $field, 'get_wrapper_classes' );
        $method->setAccessible( true );

        $classes = $method->invoke( $field );
        $this->assertSame( array( 'qaf-field', 'qaf-field-toggle', 'qaf-field-size-small', 'mine', 'qaf-field-required' ), $classes );

        // A required field left empty adds the error class.
        $field->validate( '' );
        $this->assertContains( 'qaf-field-error', $method->invoke( $field ) );
    }

    public function test_wrapper_classes_default_size_when_missing() {
        $field = new QAF_Field_Time( array( 'id' => 't', 'size' => null ) );
        $method = new ReflectionMethod( $field, 'get_wrapper_classes' );
        $method->setAccessible( true );

        $this->assertContains( 'qaf-field-size-medium', $method->invoke( $field ) );
    }

    public function test_types_with_their_own_wrapper_render() {
        foreach ( array( 'QAF_Field_Toggle', 'QAF_Field_Time', 'QAF_Field_Rating', 'QAF_Field_Range' ) as $class ) {
            $field = new $class( array( 'id' => 'w', 'label' => 'Widget' ), 1 );
            $html  = $field->render();

            $this->assertStringContainsString( 'qaf-field-' . $field->get_type(), $html, $class );
        }
    }

    // ------------------------------------------------------------------
    // Textarea (Paragraph)
    // ------------------------------------------------------------------

    private function textarea( $config = array() ) {
        return new QAF_Field_Textarea( array_merge( array( 'id' => 'msg', 'label' => 'Message' ), $config ), 9 );
    }

    public function test_textarea_implements_the_field_api() {
        $field = $this->textarea();

        $this->assertSame( 'textarea', $field->get_type() );
        $this->assertSame( 'standard', $field->get_category() );
        $this->assertSame( array( 'general', 'appearance', 'validation', 'advanced' ), array_keys( $field->get_field_settings() ) );

        $defaults = $field->get_default_config();
        $this->assertSame( 5, $defaults['rows'] );
        $this->assertSame( 'vertical', $defaults['resize'] );
        // Base defaults survive the merge.
        $this->assertArrayHasKey( 'aria_label', $defaults );
    }

    public function test_textarea_render() {
        $field = $this->textarea( array( 'required' => true, 'rows' => 8, 'placeholder' => 'Say hello' ) );
        $field->set_value( 'a < b' );

        $html = $field->render();
        $this->assertStringContainsString( '<label for="qaf-field-9-msg"', $html );
        $this->assertStringContainsString( 'id="qaf-field-9-msg"', $html );
        $this->assertStringContainsString( 'name="qaf_field_msg"', $html );
        $this->assertStringContainsString( 'rows="8"', $html );
        $this->assertStringContainsString( 'placeholder="Say hello"', $html );
        $this->assertStringContainsString( 'aria-required="true"', $html );
        $this->assertStringContainsString( 'style="resize: vertical;"', $html );
        $this->assertStringContainsString( '>a &lt; b</textarea>', $html );
        // A textarea has no pattern attribute even when one is configured.
        $this->assertStringNotContainsString( 'pattern=', $this->textarea( array( 'pattern' => '\\d+' ) )->render() );
    }

    public function test_textarea_length_limits_and_counter() {
        $html = $this->textarea( array( 'max_length' => 200, 'min_length' => 10 ) )->render();
        $this->assertStringContainsString( 'maxlength="200"', $html );
        $this->assertStringContainsString( 'minlength="10"', $html );
        $this->assertStringContainsString( 'data-counter="true"', $html );
        // With a maximum, the script owns the counter: no server-side one.
        $this->assertStringNotContainsString( 'qaf-char-counter', $html );

        $field = $this->textarea( array( 'show_counter' => true ) );
        $field->set_value( 'four' );
        $html = $field->render();
        $this->assertStringContainsString( 'id="qaf-field-9-msg-counter" aria-live="polite"', $html );
        $this->assertStringContainsString( '4 characters entered', $html );
    }

    public function test_textarea_resize_both_sets_no_style() {
        $this->assertStringNotContainsString( 'resize:', $this->textarea( array( 'resize' => 'both' ) )->render() );
    }

    public function test_textarea_rich_editor_falls_back_without_wp_editor() {
        // wp_editor() is admin/front-end WordPress only; without it the plain textarea renders.
        $this->assertFalse( function_exists( 'wp_editor' ) );
        $html = $this->textarea( array( 'rich_editor' => true ) )->render();

        $this->assertStringContainsString( '<textarea ', $html );
        $this->assertStringContainsString( '<label for="qaf-field-9-msg"', $html );
    }

    public function test_textarea_validate_takes_the_value() {
        $field = $this->textarea( array( 'required' => true, 'min_length' => 3, 'max_length' => 5 ) );

        $this->assertFalse( $field->validate( '' ) );
        $this->assertSame( array( 'This field is required.' ), $field->get_errors() );

        $this->assertFalse( $field->validate( 'ab' ) );
        $this->assertSame( array( 'Please enter at least 3 characters.' ), $field->get_errors() );

        $this->assertFalse( $field->validate( 'abcdef' ) );
        $this->assertSame( array( 'Please enter no more than 5 characters.' ), $field->get_errors() );

        $this->assertTrue( $field->validate( 'abcd' ) );
        $this->assertSame( array(), $field->get_errors() );

        // Multi-byte text counts characters, not bytes.
        $this->assertTrue( $field->validate( 'ééééé' ) );

        $this->assertFalse( $field->validate( array( 'not', 'text' ) ) );
    }

    public function test_textarea_rich_length_ignores_markup() {
        $field = $this->textarea( array( 'rich_editor' => true, 'max_length' => 5 ) );

        $this->assertTrue( $field->validate( '<p><strong>Hello</strong></p>' ) );
    }

    public function test_textarea_sanitize() {
        $plain = $this->textarea();
        $this->assertSame( '', $plain->sanitize( array( 'x' ) ) );
        $this->assertStringNotContainsString( '<b>', $plain->sanitize( "<b>bold</b>\nline two" ) );
        $this->assertStringContainsString( "\n", $plain->sanitize( "one\ntwo" ) );

        $rich = $this->textarea( array( 'rich_editor' => true ) );
        $clean = $rich->sanitize( '<p><u>u</u><script>alert(1)</script></p>' );
        $this->assertStringContainsString( '<u>u</u>', $clean );
        $this->assertStringNotContainsString( '<script>', $clean );
    }

    public function test_textarea_display_and_export() {
        $field = $this->textarea();

        $this->assertSame( "a<br />\nb &lt;i&gt;", $field->format_display( "a\nb <i>" ) );
        $this->assertSame( $field->format_display( 'x' ), $field->get_display_value( 'x' ) );
        $this->assertSame( 'a, b', $field->format_display( array( 'a', 'b' ) ) );

        $rich = $this->textarea( array( 'rich_editor' => true ) );
        $this->assertSame( 'Hello', $rich->format_export( '<p>Hello</p>' ) );
    }

    public function test_textarea_schema_and_bfl() {
        $field = $this->textarea( array( 'required' => true, 'rows' => 3 ) );

        $schema = $field->get_schema();
        $this->assertSame( 'object', $schema['type'] );
        $this->assertSame( array( 'none', 'vertical', 'horizontal', 'both' ), $schema['properties']['resize']['enum'] );

        $bfl = $field->to_bfl();
        $this->assertStringStartsWith( 'textarea #msg "Message"', $bfl );
        $this->assertStringContainsString( '@required', $bfl );
        $this->assertStringContainsString( '@rows 3', $bfl );
    }

    // ------------------------------------------------------------------
    // Repeater
    // ------------------------------------------------------------------

    private function repeater( $config = array() ) {
        return new QAF_Field_Repeater( array_merge( array(
            'id'       => 'people',
            'label'    => 'People',
            'min_rows' => 1,
            'max_rows' => 3,
            'fields'   => array(
                array( 'id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ),
                array( 'id' => 'email', 'type' => 'email', 'label' => 'Email' ),
            ),
        ), $config ) );
    }

    public function test_repeater_validates_each_row_with_its_value() {
        $field = $this->repeater();

        // Before the fix the sub-field was validated with no value, so this passed.
        $rows = array(
            array( 'Ada', 'ada@example.com' ),
            array( '', 'not-an-email' ),
        );

        $this->assertFalse( $field->validate( $rows ) );
        $this->assertSame(
            array(
                'Row 2, Name: This field is required.',
                'Row 2, Email: Please enter a valid email address.',
            ),
            $field->get_errors()
        );
    }

    public function test_repeater_accepts_good_rows() {
        $field = $this->repeater();

        $this->assertTrue( $field->validate( array( array( 'Ada', '' ), array( 'Grace', 'grace@example.com' ) ) ) );
        $this->assertSame( array(), $field->get_errors() );
    }

    public function test_repeater_missing_cell_counts_as_empty() {
        $field = $this->repeater();

        // Row 1 has an email and no name cell at all.
        $this->assertFalse( $field->validate( array( array( 1 => 'ada@example.com' ) ) ) );
        $this->assertSame( array( 'Row 1, Name: This field is required.' ), $field->get_errors() );

        // A repeater left wholly blank is empty, and empty is fine when not required.
        $this->assertTrue( $field->validate( array( array() ) ) );
    }

    public function test_repeater_row_limits() {
        $field = $this->repeater();

        $this->assertFalse( $field->validate( array_fill( 0, 4, array( 'A', '' ) ) ) );
        $this->assertSame( array( 'Maximum 3 rows allowed.' ), $field->get_errors() );

        $two = $this->repeater( array( 'min_rows' => 2 ) );
        $this->assertFalse( $two->validate( array( array( 'A', '' ) ) ) );
        $this->assertSame( array( 'Please add at least 2 rows.' ), $two->get_errors() );
    }
}
