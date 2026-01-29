<?php
/**
 * BFL Parser Tests
 *
 * Comprehensive tests for the BFL parser including all block syntaxes,
 * modifiers, and nested structures.
 *
 * @package BAF
 */

class BAF_BFL_Parser_Test extends BAF_Unit_Test_Case {

    /**
     * Parser instance
     *
     * @var BAF_BFL_Parser
     */
    private $parser;

    protected function setUp(): void {
        parent::setUp();
        
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-lexer.php';
        require_once BAF_PLUGIN_DIR . 'includes/bfl/class-baf-bfl-parser.php';
        $this->parser = new BAF_BFL_Parser();
    }

    // =========================================================================
    // BASIC FORM PARSING
    // =========================================================================

    public function test_parse_empty_form() {
        $bfl = 'form "Test Form"';
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'Test Form', $result['form']['title'] );
    }

    public function test_parse_form_with_description() {
        $bfl = 'form "Test Form" @description "A test form"';
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'Test Form', $result['form']['title'] );
        $this->assertEquals( 'A test form', $result['form']['description'] );
    }

    public function test_parse_simple_field() {
        $bfl = 'form "Test"
text #name "Your Name"';
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertCount( 1, $result['form']['fields'] );
        $this->assertEquals( 'text', $result['form']['fields'][0]['type'] );
        $this->assertEquals( 'name', $result['form']['fields'][0]['id'] );
        $this->assertEquals( 'Your Name', $result['form']['fields'][0]['label'] );
    }

    // =========================================================================
    // INDENTATION BLOCKS
    // =========================================================================

    public function test_parse_field_with_indentation_modifiers() {
        $bfl = 'form "Test"
text #name "Your Name"
  @required
  @placeholder "Enter your name"
  @maxlength 100';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertTrue( $field['required'] );
        $this->assertEquals( 'Enter your name', $field['placeholder'] );
        $this->assertEquals( 100, $field['max_length'] );
    }

    public function test_parse_multiple_fields_with_indentation() {
        $bfl = 'form "Test"
text #first "First Name"
  @required

text #last "Last Name"
  @required
  @placeholder "Last name"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertCount( 2, $result['form']['fields'] );
        
        $this->assertEquals( 'first', $result['form']['fields'][0]['id'] );
        $this->assertTrue( $result['form']['fields'][0]['required'] );
        
        $this->assertEquals( 'last', $result['form']['fields'][1]['id'] );
        $this->assertTrue( $result['form']['fields'][1]['required'] );
        $this->assertEquals( 'Last name', $result['form']['fields'][1]['placeholder'] );
    }

    // =========================================================================
    // CURLY BRACE BLOCKS { }
    // =========================================================================

    public function test_parse_field_with_brace_block() {
        $bfl = 'form "Test"
text #name "Your Name" { @required @placeholder "Enter name" }';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertTrue( $field['required'] );
        $this->assertEquals( 'Enter name', $field['placeholder'] );
    }

    public function test_parse_field_with_multiline_brace_block() {
        $bfl = 'form "Test"
text #name "Your Name" {
  @required
  @placeholder "Enter name"
  @maxlength 50
}';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertTrue( $field['required'] );
        $this->assertEquals( 'Enter name', $field['placeholder'] );
        $this->assertEquals( 50, $field['max_length'] );
    }

    // =========================================================================
    // SQUARE BRACKET BLOCKS [ ]
    // =========================================================================

    public function test_parse_field_with_bracket_block() {
        $bfl = 'form "Test"
text #name "Your Name" [ @required @placeholder "Enter name" ]';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertTrue( $field['required'] );
        $this->assertEquals( 'Enter name', $field['placeholder'] );
    }

    public function test_parse_field_with_multiline_bracket_block() {
        $bfl = 'form "Test"
text #name "Your Name" [
  @required
  @placeholder "Enter name"
  @minlength 2
]';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertTrue( $field['required'] );
        $this->assertEquals( 'Enter name', $field['placeholder'] );
        $this->assertEquals( 2, $field['min_length'] );
    }

    // =========================================================================
    // BEGIN/END BLOCKS
    // =========================================================================

    public function test_parse_field_with_begin_end_block() {
        $bfl = 'form "Test"
text #name "Your Name" begin
  @required
  @placeholder "Enter name"
end';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertTrue( $field['required'] );
        $this->assertEquals( 'Enter name', $field['placeholder'] );
    }

    // =========================================================================
    // CHOICE FIELDS
    // =========================================================================

    public function test_parse_select_with_indentation_choices() {
        $bfl = 'form "Test"
select #dept "Department"
  + Sales
  + Support
  + Engineering';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertEquals( 'select', $field['type'] );
        $this->assertCount( 3, $field['choices'] );
        $this->assertEquals( 'Sales', $field['choices'][0]['label'] );
    }

    public function test_parse_select_with_brace_choices() {
        $bfl = 'form "Test"
select #dept "Department" {
  + Sales
  + Support
  + Engineering
}';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertCount( 3, $field['choices'] );
    }

    public function test_parse_select_with_bracket_choices() {
        $bfl = 'form "Test"
select #dept "Department" [
  + Sales
  + Support
]';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertCount( 2, $field['choices'] );
    }

    public function test_parse_choices_with_value_label_format() {
        $bfl = 'form "Test"
radio #payment "Payment"
  + card|Credit Card
  + paypal|PayPal
  + bank|Bank Transfer';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertEquals( 'card', $field['choices'][0]['value'] );
        $this->assertEquals( 'Credit Card', $field['choices'][0]['label'] );
    }

    public function test_parse_choices_with_price_format() {
        $bfl = 'form "Test"
select #size "Size"
  + Small:10
  + Medium:15
  + Large:20';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $field = $result['form']['fields'][0];
        $this->assertEquals( 'Small', $field['choices'][0]['label'] );
        $this->assertEquals( '10', $field['choices'][0]['price'] );
    }

    // =========================================================================
    // COLUMNS LAYOUT
    // =========================================================================

    public function test_parse_columns_with_indentation() {
        $bfl = 'form "Test"
columns 2
  column
    text #first "First Name"
  column
    text #last "Last Name"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $columns_field = $result['form']['fields'][0];
        $this->assertEquals( 'columns', $columns_field['type'] );
        $this->assertCount( 2, $columns_field['columns'] );
    }

    public function test_parse_columns_with_braces() {
        $bfl = 'form "Test"
columns 2 {
  column {
    text #first "First Name" @required
  }
  column {
    text #last "Last Name" @required
  }
}';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $columns_field = $result['form']['fields'][0];
        $this->assertEquals( 'columns', $columns_field['type'] );
        $this->assertCount( 2, $columns_field['columns'] );
    }

    public function test_parse_columns_with_begin_end() {
        $bfl = 'form "Test"
columns 3 begin
  column begin
    text #first "First" @required
  end
  column begin
    text #middle "Middle"
  end
  column begin
    text #last "Last" @required
  end
end';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $columns_field = $result['form']['fields'][0];
        $this->assertEquals( 'columns', $columns_field['type'] );
        $this->assertCount( 3, $columns_field['columns'] );
    }

    // =========================================================================
    // CONDITIONAL BLOCKS
    // =========================================================================

    public function test_parse_conditional_with_indentation() {
        $bfl = 'form "Test"
radio #contact "Contact Method"
  + Email
  + Phone

if #contact is "Email"
  email #email "Email Address" @required';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $email_field = $result['form']['fields'][1];
        $this->assertTrue( $email_field['conditional_logic']['enabled'] );
        $this->assertEquals( 'contact', $email_field['conditional_logic']['rules'][0]['field'] );
        $this->assertEquals( 'equals', $email_field['conditional_logic']['rules'][0]['operator'] );
        $this->assertEquals( 'Email', $email_field['conditional_logic']['rules'][0]['value'] );
    }

    public function test_parse_conditional_with_braces() {
        $bfl = 'form "Test"
radio #contact "Contact" {
  + Email
  + Phone
}

if #contact is "Phone" {
  phone #phone "Phone Number" @required
}';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $phone_field = $result['form']['fields'][1];
        $this->assertTrue( $phone_field['conditional_logic']['enabled'] );
        $this->assertEquals( 'Phone', $phone_field['conditional_logic']['rules'][0]['value'] );
    }

    public function test_parse_conditional_with_begin_end() {
        $bfl = 'form "Test"
checkbox #newsletter "Subscribe to newsletter"

if #newsletter is "yes" begin
  email #email "Email" @required
end';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
    }

    public function test_parse_conditional_operators() {
        $operators = array(
            'is'       => 'equals',
            'contains' => 'contains',
            'starts'   => 'starts_with',
            'ends'     => 'ends_with',
            'greater'  => 'greater_than',
            'less'     => 'less_than',
        );
        
        foreach ( $operators as $bfl_op => $expected_op ) {
            $bfl = 'form "Test"
text #field "Field"

if #field ' . $bfl_op . ' "value"
  text #result "Result"';
            
            $result = $this->parser->parse( $bfl );
            
            $this->assertEmpty( $result['errors'], "Failed for operator: $bfl_op" );
            $this->assertEquals( 
                $expected_op, 
                $result['form']['fields'][1]['conditional_logic']['rules'][0]['operator'],
                "Operator mismatch for: $bfl_op"
            );
        }
    }

    public function test_parse_conditional_empty_filled() {
        $bfl = 'form "Test"
text #company "Company"

if #company filled
  text #website "Website"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'is_not_empty', $result['form']['fields'][1]['conditional_logic']['rules'][0]['operator'] );
    }

    // =========================================================================
    // PAGES AND SECTIONS
    // =========================================================================

    public function test_parse_multiple_pages() {
        $bfl = 'form "Multi-Page Form"

page "Step 1"
text #name "Name" @required

page "Step 2"
email #email "Email" @required';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertCount( 3, $result['form']['pages'] ); // Default + 2 pages
        
        $this->assertEquals( 'page_1', $result['form']['fields'][0]['page'] );
        $this->assertEquals( 'page_2', $result['form']['fields'][1]['page'] );
    }

    public function test_parse_sections() {
        $bfl = 'form "Test"

section "Personal Info"
text #name "Name"

section "Contact Info"
email #email "Email"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        
        // Find section fields
        $sections = array_filter( $result['form']['fields'], function( $f ) {
            return $f['type'] === 'section';
        });
        
        $this->assertCount( 2, $sections );
    }

    // =========================================================================
    // NEW MODIFIERS (pattern, autofocus, spellcheck, etc.)
    // =========================================================================

    public function test_parse_pattern_modifier() {
        $bfl = 'form "Test"
text #code "Code" @pattern "[A-Z]{3}-[0-9]{4}"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( '[A-Z]{3}-[0-9]{4}', $result['form']['fields'][0]['pattern'] );
    }

    public function test_parse_autofocus_modifier() {
        $bfl = 'form "Test"
text #first "First Field" @autofocus';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertTrue( $result['form']['fields'][0]['autofocus'] );
    }

    public function test_parse_spellcheck_modifier() {
        $bfl = 'form "Test"
text #code "Product Code" @spellcheck "false"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertFalse( $result['form']['fields'][0]['spellcheck'] );
    }

    public function test_parse_accept_modifier() {
        $bfl = 'form "Test"
fileupload #doc "Document" @accept "image/*,.pdf"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'image/*,.pdf', $result['form']['fields'][0]['accept'] );
    }

    public function test_parse_capture_modifier() {
        $bfl = 'form "Test"
fileupload #photo "Photo" @capture "environment"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'environment', $result['form']['fields'][0]['capture'] );
    }

    public function test_parse_cols_modifier() {
        $bfl = 'form "Test"
textarea #bio "Bio" @rows 10 @cols 80';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 10, $result['form']['fields'][0]['rows'] );
        $this->assertEquals( 80, $result['form']['fields'][0]['cols'] );
    }

    public function test_parse_inputmode_modifier() {
        $bfl = 'form "Test"
text #phone "Phone" @inputmode "tel"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'tel', $result['form']['fields'][0]['inputmode'] );
    }

    public function test_parse_enterkeyhint_modifier() {
        $bfl = 'form "Test"
text #search "Search" @enterkeyhint "search"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'search', $result['form']['fields'][0]['enterkeyhint'] );
    }

    public function test_parse_unique_modifier() {
        $bfl = 'form "Test"
email #email "Email" @unique @unique_scope "global" @unique_message "Already registered"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertTrue( $result['form']['fields'][0]['unique'] );
        $this->assertEquals( 'global', $result['form']['fields'][0]['unique_scope'] );
        $this->assertEquals( 'Already registered', $result['form']['fields'][0]['unique_message'] );
    }

    // =========================================================================
    // NESTED STRUCTURES
    // =========================================================================

    public function test_parse_columns_inside_conditional() {
        $bfl = 'form "Test"
checkbox #show_address "Show Address"

if #show_address is "yes"
  columns 2
    column
      text #street "Street"
    column
      text #city "City"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
    }

    public function test_parse_conditional_inside_page() {
        $bfl = 'form "Test"

page "Page 1"
radio #type "Type"
  + Personal
  + Business

if #type is "Business"
  text #company "Company Name"

page "Page 2"
text #details "Details"';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
    }

    // =========================================================================
    // FIELD TYPE ALIASES
    // =========================================================================

    public function test_parse_field_type_aliases() {
        $aliases = array(
            'dropdown'   => 'select',
            'paragraph'  => 'textarea',
            'url'        => 'website',
            'checkboxes' => 'checkbox',
            'file'       => 'fileupload',
            'savebutton' => 'save',
        );
        
        foreach ( $aliases as $alias => $expected ) {
            $bfl = 'form "Test"
' . $alias . ' #field "Label"';
            
            $result = $this->parser->parse( $bfl );
            
            $this->assertEmpty( $result['errors'], "Failed for alias: $alias" );
            $this->assertEquals( 
                $expected, 
                $result['form']['fields'][0]['type'],
                "Type mismatch for alias: $alias"
            );
        }
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_parse_escaped_quotes_in_strings() {
        $bfl = 'form "Test"
text #name "Say \"Hello\""';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertEquals( 'Say "Hello"', $result['form']['fields'][0]['label'] );
    }

    public function test_parse_mixed_block_styles() {
        $bfl = 'form "Test"

text #field1 "Field 1"
  @required

text #field2 "Field 2" { @required @placeholder "Test" }

text #field3 "Field 3" [
  @required
  @maxlength 50
]

text #field4 "Field 4" begin
  @required
end';
        
        $result = $this->parser->parse( $bfl );
        
        $this->assertEmpty( $result['errors'] );
        $this->assertCount( 4, $result['form']['fields'] );
        
        foreach ( $result['form']['fields'] as $i => $field ) {
            $this->assertTrue( $field['required'], "Field $i should be required" );
        }
    }
}
